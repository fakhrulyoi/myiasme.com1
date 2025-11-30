<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Get current user's team_id based on the database structure
$user_id = $_SESSION['user_id'];
$sql_team = "SELECT team_id FROM users WHERE id = ?";
$stmt_team = $dbconn->prepare($sql_team);
$stmt_team->bind_param("i", $user_id);
$stmt_team->execute();
$team_result = $stmt_team->get_result();
$team_data = $team_result->fetch_assoc();
$team_id = $team_data['team_id'];

// Get team name
$sql_team_name = "SELECT team_name FROM teams WHERE team_id = ?";
$stmt_team_name = $dbconn->prepare($sql_team_name);
$stmt_team_name->bind_param("i", $team_id);
$stmt_team_name->execute();
$team_name_result = $stmt_team_name->get_result();
$team_name_data = $team_name_result->fetch_assoc();
$team_name = $team_name_data['team_name'] ?? 'Your Team';

// Get username from session or database
$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    $sql_username = "SELECT username FROM users WHERE id = ?";
    $stmt_username = $dbconn->prepare($sql_username);
    $stmt_username->bind_param("i", $user_id);
    $stmt_username->execute();
    $username_result = $stmt_username->get_result();
    $username_data = $username_result->fetch_assoc();
    $username = $username_data['username'] ?? 'User';
}

// Get stock threshold for status calculations
function getStockThreshold($dbconn, $team_id) {
    $threshold_sql = "SELECT setting_value FROM stock_settings WHERE setting_name = 'low_stock_threshold'";
    
    if ($team_id) {
        $threshold_sql .= " AND (team_id = ? OR team_id IS NULL) ORDER BY team_id DESC LIMIT 1";
        $threshold_stmt = $dbconn->prepare($threshold_sql);
        $threshold_stmt->bind_param("i", $team_id);
        $threshold_stmt->execute();
        $threshold_result = $threshold_stmt->get_result();
    } else {
        $threshold_result = $dbconn->query($threshold_sql);
    }
    
    if ($threshold_result && $threshold_result->num_rows > 0) {
        return max(50, (int)$threshold_result->fetch_assoc()['setting_value']);
    }
    
    return 50; // Default threshold if not set
}

$low_stock_threshold = getStockThreshold($dbconn, $team_id);

// Handle search functionality
$search_term = '';
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Handle sorting functionality
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'product_name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Validate sort field
$allowed_sort_fields = ['product_name', 'sku', 'stock_quantity', 'stock_status', 'actual_cost'];
if (!in_array($sort, $allowed_sort_fields)) {
    $sort = 'product_name';
}

// Validate order
if ($order != 'asc' && $order != 'desc') {
    $order = 'asc';
}

// DEBUGGING: Let's first check what SKUs exist for the team
$debug_sql = "SELECT id, sku, product_name FROM products WHERE team_id = ? ORDER BY sku, id";
$debug_stmt = $dbconn->prepare($debug_sql);
$debug_stmt->bind_param("i", $team_id);
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();

// Get team's products with correct stock information - FINAL FIX v2
$sql_products = "
    SELECT 
        p.sku,
        p.product_name,
        AVG(p.actual_cost) as actual_cost,
        -- Get the latest balance_stock from stock_orders for products with this SKU
        -- Fixed to order by ID DESC to get the most recent record by order ID
        (
            SELECT so.balance_stock 
            FROM stock_orders so 
            JOIN products p2 ON so.product_id = p2.id
            WHERE p2.sku = p.sku 
            AND p2.team_id = ?
            ORDER BY so.id DESC 
            LIMIT 1
        ) as stock_quantity,
        -- Debug: Let's also get what product_id this came from
        (
            SELECT CONCAT('Product ID: ', so.product_id, ', Date: ', so.date, ', Order: ', so.id) 
            FROM stock_orders so 
            JOIN products p2 ON so.product_id = p2.id
            WHERE p2.sku = p.sku 
            AND p2.team_id = ?
            ORDER BY so.id DESC 
            LIMIT 1
        ) as debug_info,
        -- Get status from the most recent product with this SKU
        (
            SELECT p2.status 
            FROM products p2 
            WHERE p2.sku = p.sku 
            AND p2.team_id = ?
            ORDER BY p2.id DESC 
            LIMIT 1
        ) as status,
        -- Calculate stock status based on latest balance
        CASE 
            WHEN (
                SELECT p2.status 
                FROM products p2 
                WHERE p2.sku = p.sku 
                AND p2.team_id = ?
                ORDER BY p2.id DESC 
                LIMIT 1
            ) = 'OFD' THEN 'Out For Delivery'
            WHEN (
                SELECT so.balance_stock 
                FROM stock_orders so 
                JOIN products p2 ON so.product_id = p2.id
                WHERE p2.sku = p.sku 
                AND p2.team_id = ?
                ORDER BY so.id DESC 
                LIMIT 1
            ) <= 0 THEN 'Out of Stock'
            WHEN (
                SELECT so.balance_stock 
                FROM stock_orders so 
                JOIN products p2 ON so.product_id = p2.id
                WHERE p2.sku = p.sku 
                AND p2.team_id = ?
                ORDER BY so.id DESC 
                LIMIT 1
            ) <= ? THEN 'Low Stock'
            ELSE 'Healthy' 
        END as stock_status,
        -- Get latest created_at for this SKU
        MAX(p.created_at) as created_at,
        -- Count total orders for this SKU
        (
            SELECT COUNT(so.id)
            FROM stock_orders so 
            JOIN products p2 ON so.product_id = p2.id
            WHERE p2.sku = p.sku 
            AND p2.team_id = ?
        ) as order_count,
        -- Total units sold for this SKU
        (
            SELECT SUM(so.order_received)
            FROM stock_orders so 
            JOIN products p2 ON so.product_id = p2.id
            WHERE p2.sku = p.sku 
            AND p2.team_id = ?
        ) as total_units_sold,
        -- Last sale date for this SKU
        (
            SELECT DATE_FORMAT(MAX(so.date), '%d-%b-%Y')
            FROM stock_orders so
            JOIN products p2 ON so.product_id = p2.id
            WHERE p2.sku = p.sku 
            AND p2.team_id = ?
        ) as last_sale_date
    FROM 
        products p
    WHERE 
        p.team_id = ?";

// Add search condition if search term is provided
if (!empty($search_term)) {
    $sql_products .= " AND (p.product_name LIKE ? OR p.sku LIKE ?)";
}

// Group by SKU and product name
$sql_products .= " GROUP BY p.sku, p.product_name ";

// Add sorting
$sql_products .= " ORDER BY " . $sort . " " . $order;

$stmt_products = $dbconn->prepare($sql_products);

if (!empty($search_term)) {
    $search_param = '%' . $search_term . '%';
    $stmt_products->bind_param("iiiiiiiiiiss", $team_id, $team_id, $team_id, $team_id, $team_id, $team_id, $low_stock_threshold, $team_id, $team_id, $team_id, $team_id, $search_param, $search_param);
} else {
    $stmt_products->bind_param("iiiiiiiiiii", $team_id, $team_id, $team_id, $team_id, $team_id, $team_id, $low_stock_threshold, $team_id, $team_id, $team_id, $team_id);
}

$stmt_products->execute();
$products_result = $stmt_products->get_result();

// Calculate stats
$total_products = $products_result->num_rows;
$healthy_stock = 0;
$low_stock = 0;
$out_of_stock = 0;
$total_stock_value = 0;

$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[] = $product;
    
    // Update stats
    if ($product['stock_status'] == 'Healthy') {
        $healthy_stock++;
    } elseif ($product['stock_status'] == 'Low Stock') {
        $low_stock++;
    } elseif ($product['stock_status'] == 'Out of Stock') {
        $out_of_stock++;
    }
    
    // Calculate stock value
    $total_stock_value += $product['stock_quantity'] * $product['actual_cost'];
}

// Get recent stock movements
$sql_recent = "
    SELECT 
        se.date,
        se.description,
        p.product_name,
        se.quantity,
        'IN' as type
    FROM 
        stock_entries se
    JOIN 
        products p ON se.product_id = p.id
    WHERE 
        se.team_id = ?
    
    UNION ALL
    
    SELECT 
        so.date,
        'Order' as description,
        p.product_name,
        so.order_received,
        'OUT' as type
    FROM 
        stock_orders so
    JOIN 
        products p ON so.product_id = p.id
    WHERE 
        so.team_id = ?
    
    ORDER BY 
        date DESC
    LIMIT 5";

$stmt_recent = $dbconn->prepare($sql_recent);
$stmt_recent->bind_param("ii", $team_id, $team_id);
$stmt_recent->execute();
$recent_movements = $stmt_recent->get_result();

function safe_htmlspecialchars($value, $flags = ENT_QUOTES, $encoding = 'UTF-8', $double_encode = true) {
    // Convert null to empty string, otherwise use the value as is
    $str = ($value === null) ? '' : (string)$value;
    return htmlspecialchars($str, $flags, $encoding, $double_encode);
}
// Function to check if current page is active
function isActive($page) {
    return basename($_SERVER['REQUEST_URI']) == $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Stock - Dr Ecomm Formula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        
        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #1E3C72, #2A5298);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-weight: 500;
            font-size: 15px;
        }
        
        .role {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-links li {
            margin: 2px 0;
        }
        
        .nav-links li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-links li a i {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        .nav-links li:hover a {
            background-color: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        
        .nav-links li.active a {
            background-color: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 20px;
            min-height: 100vh;
            flex: 1;
        }
        
        /* Header styles */
        .page-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1E3C72;
            margin: 0;
        }
        
        /* Debug info */
        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 14px;
        }
        
        /* Search bar */
        .search-bar {
            max-width: 300px;
            margin-left: auto;
        }
        
        .search-form {
            display: flex;
        }
        
        .search-input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            font-size: 14px;
            flex: 1;
        }
        
        .search-button {
            background-color: #1E3C72;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        /* Stats cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .icon-blue {
            background-color: #36A2EB;
        }
        
        .icon-green {
            background-color: #4BC0C0;
        }
        
        .icon-yellow {
            background-color: #FFCD56;
        }
        
        .icon-red {
            background-color: #FF6384;
        }
        
        .stat-title {
            color: #6c757d;
            font-size: 15px;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-description {
            color: #6c757d;
            font-size: 13px;
        }
        
        /* Products table */
        .products-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .products-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .products-title {
            font-size: 18px;
            font-weight: 600;
            color: #1E3C72;
            margin: 0;
        }
        
        .products-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-button {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            color: #333;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .action-button:hover {
            background-color: #e9ecef;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #1E3C72;
            border-bottom: 1px solid #ddd;
            position: relative;
            cursor: pointer;
        }
        
        th:hover {
            background-color: #e9ecef;
        }
        
        th.sorted-asc::after {
            content: "↑";
            position: absolute;
            right: 10px;
        }
        
        th.sorted-desc::after {
            content: "↓";
            position: absolute;
            right: 10px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-healthy {
            background-color: rgba(75, 192, 192, 0.2);
            color: #4BC0C0;
        }
        
        .status-low {
            background-color: rgba(255, 205, 86, 0.2);
            color: #FFCD56;
        }
        
        .status-out {
            background-color: rgba(255, 99, 132, 0.2);
            color: #FF6384;
        }
        
        /* Recent stock movements */
        .recent-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .recent-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .recent-title {
            font-size: 18px;
            font-weight: 600;
            color: #1E3C72;
            margin: 0;
        }
        
        .movement-list {
            list-style: none;
            padding: 0;
        }
        
        .movement-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .movement-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .in-indicator {
            background-color: rgba(75, 192, 192, 0.2);
            color: #4BC0C0;
        }
        
        .out-indicator {
            background-color: rgba(255, 99, 132, 0.2);
            color: #FF6384;
        }
        
        .movement-details {
            flex: 1;
        }
        
        .movement-product {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .movement-info {
            color: #6c757d;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-icon {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-message {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .logo h2, .user-details, .nav-links li a span {
                display: none;
            }
            
            .nav-links li a i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="logo">
                <h2>Dr Ecomm</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo safe_htmlspecialchars($username); ?></span>
<span class="role"><?php echo safe_htmlspecialchars($team_name); ?></span>
                </div>
            </div>
            
            <ul class="nav-links">
                <li class="<?php echo isActive('dashboard.php'); ?>">
                    <a href="dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Team Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo isActive('index.php'); ?>">
                    <a href="index.php">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Product</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_product_proposed.php'); ?>">
                    <a href="user_product_proposed.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>Product Proposals</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_winning.php'); ?>">
                    <a href="user_winning.php">
                        <i class="fas fa-medal"></i>
                        <span>Winning DNA</span>
                    </a>
                </li>
                <li class="<?php echo isActive('team_products.php'); ?>">
                    <a href="team_products.php">
                        <i class="fas fa-box"></i>
                        <span>Team Products</span>
                    </a>
                </li>
                <li class="<?php echo isActive('team_products_status.php'); ?>">
                    <a href="team_products_status.php">
                        <i class="fa-solid fa-bell"></i>
                        <span>Status Products</span>
                    </a>
                </li>
                    <li class="<?php echo isActive('domain.php'); ?>">
                    <a href="domain.php">
                        <i class="fas fa-globe"></i>
                        <span>Domain & Projects</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_commission.php'); ?>">
                    <a href="user_commission.php">
                        <i class="fas fa-calculator"></i>
                        <span>Commission View</span>
                    </a>
                </li>
                <li class="active">
                    <a href="view_stock.php">
                        <i class="fas fa-warehouse"></i>
                        <span>View Stock</span>
                    </a>
                </li>
                <li class="<?php echo isActive('reports.php'); ?>">
                    <a href="reports.php">
                        <i class="fas fa-file-download"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Main Content Container -->
        <main class="main-content">
            <!-- Debug Information -->
        
            
            <!-- Page Header with Search -->
            <header class="page-header">
                <h1><i class="fas fa-warehouse"></i> View Stock</h1>
                
                <div class="search-bar">
                    <form action="" method="GET" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </header>
            
            <!-- Stats Overview -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon icon-blue">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <h3 class="stat-title">Total Products</h3>
                    <p class="stat-value"><?php echo $total_products; ?></p>
                    <p class="stat-description">Products in your inventory</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon icon-green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="stat-title">In Stock</h3>
                    <p class="stat-value"><?php echo $healthy_stock; ?></p>
                    <p class="stat-description">Products with healthy stock levels</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon icon-yellow">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <h3 class="stat-title">Low Stock</h3>
                    <p class="stat-value"><?php echo $low_stock; ?></p>
                    <p class="stat-description">Products below threshold (<?php echo $low_stock_threshold; ?> units)</p>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon icon-red">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <h3 class="stat-title">Out of Stock</h3>
                    <p class="stat-value"><?php echo $out_of_stock; ?></p>
                    <p class="stat-description">Products needing immediate restock</p>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="products-container">
                <div class="products-header">
                    <h2 class="products-title"><i class="fas fa-boxes"></i> Your Products Inventory</h2>
                    
                    <div class="products-actions">
                        <a href="index.php" class="action-button">
                            <i class="fas fa-plus"></i> Add Sales Data
                        </a>
                        <button class="action-button" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="action-button" onclick="exportToCSV()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="productsTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable('product_name')" class="<?php echo $sort == 'product_name' ? 'sorted-' . $order : ''; ?>">Product Name</th>
                                <th onclick="sortTable('sku')" class="<?php echo $sort == 'sku' ? 'sorted-' . $order : ''; ?>">SKU</th>
                                <th onclick="sortTable('stock_quantity')" class="<?php echo $sort == 'stock_quantity' ? 'sorted-' . $order : ''; ?>">Available Stock</th>
                                <th onclick="sortTable('stock_status')" class="<?php echo $sort == 'stock_status' ? 'sorted-' . $order : ''; ?>">Status</th>
                                <th onclick="sortTable('actual_cost')" class="<?php echo $sort == 'actual_cost' ? 'sorted-' . $order : ''; ?>">Cost (RM)</th>
                                <th>Stock Value (RM)</th>
                                <th>Units Sold</th>
                                <th>Last Sale</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <?php echo safe_htmlspecialchars($product['product_name']); ?>
                                            <div style="font-size: 11px; color: #666; margin-top: 5px;">
                                                <strong>Debug:</strong> <?php echo safe_htmlspecialchars($product['debug_info']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo safe_htmlspecialchars($product['sku']); ?></td>
                                        <td><?php echo $product['stock_quantity']; ?></td>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            switch ($product['stock_status']) {
                                                case 'Healthy':
                                                    $status_class = 'status-healthy';
                                                    break;
                                                case 'Low Stock':
                                                    $status_class = 'status-low';
                                                    break;
                                                case 'Out of Stock':
                                                    $status_class = 'status-out';
                                                    break;
                                                default:
                                                    $status_class = '';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($product['stock_status']); ?>
                                            </span>
                                        </td>
                                        <td>RM <?php echo number_format($product['actual_cost'], 2); ?></td>
                                        <td>RM <?php echo number_format($product['stock_quantity'] * $product['actual_cost'], 2); ?></td>
                                        <td><?php echo $product['total_units_sold'] ?? 0; ?></td>
                                        <td><?php echo $product['last_sale_date'] ?? 'Never'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <div class="empty-icon">
                                                <i class="fas fa-box-open"></i>
                                            </div>
                                            <p class="empty-message">No products found. Add products to see your inventory.</p>
                                            <a href="index.php" class="action-button">
                                                <i class="fas fa-plus"></i> Add Product
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Stock Movements -->
            <div class="recent-container">
                <div class="recent-header">
                    <h2 class="recent-title"><i class="fas fa-history"></i> Recent Stock Movements</h2>
                </div>
                
                <ul class="movement-list">
                    <?php if ($recent_movements && $recent_movements->num_rows > 0): ?>
                        <?php while ($movement = $recent_movements->fetch_assoc()): ?>
                            <li class="movement-item">
                                <div class="movement-indicator <?php echo $movement['type'] == 'IN' ? 'in-indicator' : 'out-indicator'; ?>">
                                    <i class="fas <?php echo $movement['type'] == 'IN' ? 'fa-arrow-alt-circle-up' : 'fa-arrow-alt-circle-down'; ?>"></i>
                                </div>
                                <div class="movement-details">
                                    <div class="movement-product"><?php echo safe_htmlspecialchars($movement['product_name']); ?></div>
                                    <div class="movement-info">
                                        <span><?php echo safe_htmlspecialchars($movement['description']); ?> - <?php echo $movement['quantity']; ?> units</span>
                                        <span><?php echo date('d M Y', strtotime($movement['date'])); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="movement-item">
                            <div class="empty-state" style="padding: 30px 0;">
                                <p class="empty-message">No recent stock movements found.</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>
    </div>

    <script>
        // Function to handle table sorting
        function sortTable(column) {
            const currentSort = '<?php echo $sort; ?>';
            const currentOrder = '<?php echo $order; ?>';
            
            let newOrder = 'asc';
            if (column === currentSort && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            window.location.href = `?sort=${column}&order=${newOrder}<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>`;
        }
        
        // Function to export table data to CSV
        function exportToCSV() {
            const table = document.getElementById('productsTable');
            let csv = [];
            
            // Get all rows including headers
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Get the text content and clean it up
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                    
                    // Quote the data if it contains commas
                    data = data.replace(/"/g, '""');
                    if (data.includes(',')) {
                        data = `"${data}"`;
                    }
                    
                    row.push(data);
                }
                
                csv.push(row.join(','));
            }
            
            // Download CSV file
            const csvString = csv.join('\n');
            const filename = 'stock_inventory_' + new Date().toISOString().slice(0, 10) + '.csv';
            
            const link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('target', '_blank');
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>