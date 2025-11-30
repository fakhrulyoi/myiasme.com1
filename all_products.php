<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Redirect if not admin
if (!$is_admin) {
    header("Location: team_products.php");
    exit();
}

// First, let's check what column exists in the teams table
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}
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

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Get date range for filtering (optional)
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get team filter
$team_filter = isset($_GET['team_id']) ? $_GET['team_id'] : '';

// Get all teams for filter dropdown
$teams_query = "SELECT * FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_query);
$teams = [];
while ($team = $teams_result->fetch_assoc()) {
    $teams[] = $team;
}

// Helper function for active menu items
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page) ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            --primary-color: #2c3e50;
            --primary-light: #34495e;
            --secondary-color: #3498db;
            --secondary-light: #5dade2;
            --accent-color: #1abc9c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #ecf0f1;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
            
            /* Colors for stat cards */
            --sales-color: #3498db;
            --profit-color: #1abc9c;
            --orders-color: #9b59b6;
            --units-color: #f39c12;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: var(--dark-text);
        }
        
        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-light));
            color: var(--light-text);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            box-shadow: var(--box-shadow);
            overflow-y: auto;
            transition: var(--transition);
        }
        
        .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo h2 {
            margin: 0;
            font-size: 26px;
            font-weight: 600;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 26px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        
        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-weight: 500;
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .role {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-links li {
            margin: 5px 10px;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .nav-links li a {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: var(--light-text);
            text-decoration: none;
            transition: var(--transition);
            border-radius: var(--border-radius);
        }
        
        .nav-links li a i {
            margin-right: 12px;
            font-size: 18px;
            width: 22px;
            text-align: center;
            transition: var(--transition);
        }
        
        .nav-links li:hover a {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .nav-links li.active a {
            background-color: var(--secondary-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .nav-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 20px;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.6);
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 25px;
            min-height: 100vh;
            flex: 1;
            transition: var(--transition);
        }
        
        /* Page header */
        .page-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-header h1 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        /* Filters container */
        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            align-items: center;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: var(--dark-text);
        }
        
        .date-range, .team-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .team-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 150px;
        }

        /* Table styles */
        .table-container {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            transition: var(--transition);
        }
        
        .table-container:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .table-header {
            background-color: var(--light-bg);
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
        }
        
        .table-header h3 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .table-body {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        table th {
            background-color: var(--primary-color);
            color: white;
            position: sticky;
            top: 0;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover td {
            background-color: rgba(0,0,0,0.01);
        }

        /* Button styles */
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-light);
        }
        
        .btn-edit {
            background-color: #FFC107;
            color: black;
        }

        .btn-delete {
            background-color: #F44336;
            color: white;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        /* Stats cards */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .summary-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e6e6e6;
            transition: var(--transition);
        }

        .summary-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .summary-label {
            font-weight: 500;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .chart-card {
            background-color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            transition: var(--transition);
        }
        
        .chart-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                transform: translateX(0);
            }
            
            .sidebar.expanded {
                width: 280px;
            }
            
            .logo h2, .user-details, .nav-links li a span, .nav-section-title {
                display: none;
            }
            
            .sidebar.expanded .logo h2, 
            .sidebar.expanded .user-details, 
            .sidebar.expanded .nav-links li a span,
            .sidebar.expanded .nav-section-title {
                display: block;
            }
            
            .nav-links li a i {
                margin-right: 0;
            }
            
            .sidebar.expanded .nav-links li a i {
                margin-right: 12px;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .sidebar.expanded + .main-content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }
            
            .filter-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-range, .team-filter {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-clinic-medical"></i>
                <h2>MYIASME</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo $username; ?></span>
                    <span class="role"><?php echo $is_admin ? 'Administrator' : 'Team Member'; ?></span>
                </div>
            </div>
            
            <ul class="nav-links">
                <?php if ($is_admin): ?>
                <li class="<?php echo isActive('admin_dashboard.php'); ?>">
                    <a href="admin_dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
               
                <li class="<?php echo isActive('teams.php'); ?>">
                    <a href="teams.php">
                        <i class="fas fa-users"></i>
                        <span>Teams</span>
                    </a>
                </li>
                <li class="<?php echo isActive('all_products.php'); ?>">
                    <a href="all_products.php">
                        <i class="fas fa-boxes"></i>
                        <span>All Products</span>
                    </a>
                </li>
              
                <?php endif; ?>
                
                <div class="nav-section">
                    <p class="nav-section-title">Tools</p>
                    <li class="<?php echo isActive('commission_calculator.php'); ?>">
                        <a href="commission_calculator.php">
                            <i class="fas fa-calculator"></i>
                            <span>Commission Calculator</span>
                        </a>
                    </li>
                                 <li class="">
            <a href="stock_management.php">
                <i class="fas fa-warehouse"></i>
                <span>Stock Management</span>
            </a>
        </li>
                    <li class="<?php echo isActive('admin_reports.php'); ?>">
                        <a href="admin_reports.php">
                            <i class="fas fa-file-download"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                </div>
                
                <div class="nav-section">
                    <p class="nav-section-title">Account</p>
                    
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </div>
            </ul>
        </nav>

        <main class="main-content" id="main-content">
    <!-- Page Header -->
    <header class="page-header">
        <h1><i class="fas fa-boxes"></i> All Products</h1>
    </header>

    <div class="filters-container">
        <form method="GET" action="" id="filterForm">
            <div class="filter-row">
                <div class="date-range">
                    <label for="start_date">From:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                    
                    <label for="end_date">To:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                </div>
                
                <div class="team-filter">
                    <label for="team_id">Team:</label>
                    <select id="team_id" name="team_id" class="form-control">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team[$team_pk]; ?>" <?php if ($team_filter == $team[$team_pk]) echo 'selected'; ?>>
                                <?php echo $team['team_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
    <?php if (isset($_GET['delete_success'])): ?>
<div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
    <i class="fas fa-check-circle"></i> Product deleted successfully!
</div>
<?php endif; ?>

<?php if (isset($_GET['delete_error'])): ?>
<div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
    <i class="fas fa-exclamation-circle"></i> Error deleting product. Please try again.
</div>
<?php endif; ?>
    <!-- Statistics Section - MOVED TO TOP -->
    <div class="chart-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Total Product Stats</h3>
        </div>
        <div class="stats-summary">
            <?php
            // Calculate totals with team filter
            $sql_totals = "SELECT 
                SUM(sales) as total_sales,
                SUM(profit) as total_profit,
                COUNT(*) as total_products,
                SUM(unit_sold) as total_units
                FROM products 
                WHERE created_at BETWEEN ? AND ?";
            
            $total_params = [$start_date, $end_date];
            $total_types = "ss";
            
            if (!empty($team_filter)) {
                $sql_totals .= " AND team_id = ?";
                $total_params[] = $team_filter;
                $total_types .= "i";
            }
            
            $stmt_totals = $dbconn->prepare($sql_totals);
            $stmt_totals->bind_param($total_types, ...$total_params);
            $stmt_totals->execute();
            $totals = $stmt_totals->get_result()->fetch_assoc();
            ?>
           <div class="summary-item">
    <span class="summary-label">Total Sales:</span>
    <span class="summary-value">RM <?php echo number_format($totals['total_sales'] ?? 0, 2); ?></span>
</div>
<div class="summary-item">
    <span class="summary-label">Total Profit:</span>
    <span class="summary-value">RM <?php echo number_format($totals['total_profit'] ?? 0, 2); ?></span>
</div>
<div class="summary-item">
    <span class="summary-label">Products Listed:</span>
    <span class="summary-value"><?php echo $totals['total_products'] ?? 0; ?></span>
</div>
<div class="summary-item">
    <span class="summary-label">Units Sold:</span>
    <span class="summary-value"><?php echo number_format($totals['total_units'] ?? 0); ?></span>
</div>
        </div>
    </div>

    <!-- All Products Table -->
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-boxes"></i> All Products</h3>
        </div>
        
        <div class="table-body">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Ads Spend</th>
                        <th>Purchase</th>
                        <th>CPP</th>
                        <th>Units Sold</th>
                        <th>Actual Cost</th>
                        <th>Item Cost</th>
                        <th>COD</th>
                        <th>Sales</th>
                        <th>Profit</th>
                        <th>Created At</th>
                        <th>Pakej</th>
                        <th>Team</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                            <?php
                            // Query to get all products with date and team filter
                            $sql = "SELECT p.*, t.team_name 
                                    FROM products p
                                    LEFT JOIN teams t ON p.team_id = t.$team_pk 
                                    WHERE p.created_at BETWEEN ? AND ?";
                            
                            $params = [$start_date, $end_date];
                            $types = "ss";
                            
                            if (!empty($team_filter)) {
                                $sql .= " AND p.team_id = ?";
                                $params[] = $team_filter;
                                $types .= "i";
                            }
                            
                            $sql .= " ORDER BY p.created_at DESC";
                            
                            $stmt = $dbconn->prepare($sql);
                            $stmt->bind_param($types, ...$params);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            while ($product = $result->fetch_assoc()):
                                $cpp = (($product['purchase'] ?? 0) > 0) ? number_format(($product['ads_spend'] ?? 0) / $product['purchase'], 2) : '0.00';
                            ?>
                            <tr>
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo $product['sku'] ?? 'N/A'; ?></td>
                                <td><?php echo $product['product_name']; ?></td>
                                <td><?php echo is_null($product['ads_spend']) ? '0.00' : number_format($product['ads_spend'], 2); ?></td>
                                <td><?php echo $product['purchase']; ?></td>
                                <td><?php echo $cpp; ?></td>
                                <td><?php echo $product['unit_sold']; ?></td>
                                <td><?php echo is_null($product['actual_cost']) ? '0.00' : number_format($product['actual_cost'], 2); ?></td>
                                <td><?php echo number_format($product['item_cost'] ?? 0, 2); ?></td>
                                <td><?php echo is_null($product['cod']) ? '0.00' : number_format($product['cod'], 2); ?></td>
                                <td><?php echo number_format($product['sales'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($product['profit'] ?? 0, 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($product['created_at'])); ?></td>
                                <td><?php echo $product['pakej'] ?: ''; ?></td>
                                <td><?php echo $product['team_name'] ?: 'NULL'; ?></td>
                                <td class="actions">
    <a href="admin_edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
    <a href="admin_delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-delete"><i class="fas fa-trash"></i></a>
</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

          
        </main>
    </div>
    
    <!-- Add JavaScript for mobile sidebar toggle -->
    <script>
    // Toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        // Create toggle button
        const toggleSidebarBtn = document.createElement('button');
        toggleSidebarBtn.classList.add('toggle-sidebar-btn');
        toggleSidebarBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleSidebarBtn.style.position = 'fixed';
        toggleSidebarBtn.style.top = '15px';
        toggleSidebarBtn.style.left = '15px';
        toggleSidebarBtn.style.zIndex = '1000';
        toggleSidebarBtn.style.background = 'var(--primary-color)';
        toggleSidebarBtn.style.color = 'white';
        toggleSidebarBtn.style.border = 'none';
        toggleSidebarBtn.style.borderRadius = '5px';
        toggleSidebarBtn.style.width = '40px';
        toggleSidebarBtn.style.height = '40px';
        toggleSidebarBtn.style.display = 'none';
        
        document.body.appendChild(toggleSidebarBtn);
        
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        toggleSidebarBtn.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
        });
        
        // Show toggle button on mobile
        function checkMobile() {
            if (window.innerWidth <= 768) {
                toggleSidebarBtn.style.display = 'block';
            } else {
                toggleSidebarBtn.style.display = 'none';
                sidebar.classList.remove('expanded');
            }
        }
        
        window.addEventListener('resize', checkMobile);
        checkMobile(); // Initial check
    });
    </script>
</body>
</html>