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

// Get team name - based on screenshots, using team_id and team_name columns
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


// Get date range for filtering
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// 1. Get team statistics


$sql_stats = "SELECT 
    SUM(sales) as total_sales,
    SUM(sales - cod) as regular_sales,
    SUM(cod) as cod_sales,
    SUM(profit) as total_profit,
    COUNT(*) as total_products,
    COUNT(DISTINCT product_name) as unique_products,
    SUM(unit_sold) as total_units,
    SUM(purchase) as total_purchases
FROM products
WHERE team_id = ? AND created_at BETWEEN ? AND ?";

$stmt_stats = $dbconn->prepare($sql_stats);
$stmt_stats->bind_param("sss", $team_id, $start_date, $end_date);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// 2. Get top selling products for this team
$sql_top_products = "SELECT 
    product_name,
    SUM(unit_sold) as total_sold,
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    AVG(profit/sales)*100 as profit_margin
FROM products
WHERE team_id = ? AND created_at BETWEEN ? AND ?
GROUP BY product_name
ORDER BY total_sales DESC
LIMIT 5";

$stmt_top_products = $dbconn->prepare($sql_top_products);
$stmt_top_products->bind_param("sss", $team_id, $start_date, $end_date);
$stmt_top_products->execute();
$top_products = $stmt_top_products->get_result();

// 3. Get daily sales data for chart
$sql_daily_sales = "SELECT 
    DATE(created_at) as sale_date,
    SUM(sales) as daily_sales,
    SUM(profit) as daily_profit
FROM products
WHERE team_id = ? AND created_at BETWEEN ? AND ?
GROUP BY DATE(created_at)
ORDER BY sale_date";

$stmt_daily_sales = $dbconn->prepare($sql_daily_sales);
$stmt_daily_sales->bind_param("sss", $team_id, $start_date, $end_date);
$stmt_daily_sales->execute();
$daily_sales = $stmt_daily_sales->get_result();

// Prepare data for charts
$dates = [];
$sales_data = [];
$profit_data = [];

while ($row = $daily_sales->fetch_assoc()) {
    $dates[] = $row['sale_date'];
    $sales_data[] = (float)$row['daily_sales']; // Cast to float
    $profit_data[] = (float)$row['daily_profit']; // Cast to float
}

$sql_categories = "SELECT 
    product_name as category,
    SUM(sales) as category_sales,
    SUM(profit) as category_profit
FROM products
WHERE team_id = ? AND created_at BETWEEN ? AND ?
GROUP BY category
ORDER BY category_sales DESC
LIMIT 10"; // Limit to top 10 products

$stmt_categories = $dbconn->prepare($sql_categories);
$stmt_categories->bind_param("sss", $team_id, $start_date, $end_date);
$stmt_categories->execute();
$categories = $stmt_categories->get_result();

// Prepare data for pie chart
$category_names = [];
$category_sales = [];
$category_colors = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', 
    '#FF9F40', '#C9CBCF', '#7BC8A4', '#E7E9ED', '#1ABC9C'
];

$i = 0;
while ($row = $categories->fetch_assoc()) {
    $category_names[] = $row['category'];
    $category_sales[] = (float)$row['category_sales']; // Cast to float
    $i++;
}

// 5. Get team members performance - based on screenshots, using id as primary key in users table
// 5. Get team members performance - using team_id to join tables
$sql_team_members = "SELECT 
    u.username,
    COUNT(p.id) as product_count,
    SUM(p.sales) as member_sales,
    SUM(p.profit) as member_profit
FROM users u
LEFT JOIN products p ON u.team_id = p.team_id AND p.created_at BETWEEN ? AND ?
WHERE u.team_id = ?
GROUP BY u.username
ORDER BY member_sales DESC";

$stmt_team_members = $dbconn->prepare($sql_team_members);
$stmt_team_members->bind_param("ssi", $start_date, $end_date, $team_id);
$stmt_team_members->execute();
$team_members = $stmt_team_members->get_result();

// Helper function for active menu items
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page) ? 'active' : '';
}
function safeNumberFormat($value, $decimals = 2) {
    if ($value === null || $value === '') {
        return '0.00';
    }
    return number_format((float)$value, $decimals);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Dashboard - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
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
        
        /* Additional styles for charts */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            min-height: 400px;
            padding: 24px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        canvas {
            max-height: 350px;
        }
        
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .sales-icon {
            background-color: rgba(54, 162, 235, 0.2);
            color: #36A2EB;
        }
        
        .profit-icon {
            background-color: rgba(75, 192, 192, 0.2);
            color: #4BC0C0;
        }
        
        .orders-icon {
            background-color: rgba(255, 159, 64, 0.2);
            color: #FF9F40;
        }
        
        .products-icon {
            background-color: rgba(153, 102, 255, 0.2);
            color: #9966FF;
        }
        
        .stat-card .stat-title {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 8px 0;
            color: #1E3C72;
        }
        
        .stat-card .stat-change {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
        }
        
        .stat-card .stat-change i {
            margin-right: 5px;
        }
        
        .stat-card .stat-change.positive {
            color: #4BC0C0;
        }
        
        .stat-card .stat-change.negative {
            color: #FF6384;
        }
        
        /* Table styles */
        .table-container {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .table-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .table-header h3 {
            margin: 0;
            color: #1E3C72;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            background-color: #1E3C72;
            color: white;
        }
        
        /* Date range picker */
        .date-range {
            background-color: white;
            border-radius: 8px;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .date-range input {
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 4px;
            margin: 0 5px;
        }
        
        .date-range button {
            margin-left: 10px;
            background-color: #1E3C72;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        /* Page header */
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
        
        /* Fix for page layout */
        #dateFilterForm {
            display: flex;
            align-items: center;
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
            
            .chart-grid {
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
                <h2>MYIASME</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
 <div class="user-details">
                    <span class="username"><?php echo $username; ?></span>
                    <span class="role"><?php echo $team_name; ?></span>
                </div>
            </div>
            
            <ul class="nav-links">
                <li class="<?php echo isActive('team_dashboard.php'); ?>">
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
                <li class="">
                    <a href="user_product_proposed.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>Product Proposals</span>
                    </a>
                </li>
                <li class="<?php echo isActive('winning_dna.php'); ?>">
                    <a href="user_winning.php">
                        <i class="fa-solid fa-medal"></i>
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
                        <span>Commision View</span>
                    </a>
                </li>
                   <li class="">
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
            <!-- Page Header -->
            <header class="page-header">
                <h1><?php echo $team_name; ?> Dashboard</h1>
                <div class="date-range">
                    <form method="GET" action="" id="dateFilterForm">
                        <label for="start_date">From:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        
                        <label for="end_date">To:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>
            </header>

            <!-- Stats Overview -->
            <div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon sales-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <p class="stat-title">Total Sales</p>
        <h3 class="stat-value">RM <?php echo number_format($stats['total_sales'] ?? 0, 2); ?></h3>
        <p class="stat-change positive">
            <i class="fas fa-arrow-up"></i> <?php echo $team_name; ?>
        </p>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon sales-icon" style="background-color: rgba(75, 192, 192, 0.2); color: #4BC0C0;">
            <i class="fas fa-credit-card"></i>
        </div>
        <p class="stat-title">Regular Sales</p>
        <h3 class="stat-value">RM <?php echo number_format($stats['regular_sales'] ?? 0, 2); ?></h3>
        <p class="stat-change">
            <i class="fas fa-percentage"></i> <?php echo ($stats['total_sales'] > 0) ? round(($stats['regular_sales'] / $stats['total_sales']) * 100, 1) : 0; ?>% of total
        </p>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon sales-icon" style="background-color: rgba(255, 159, 64, 0.2); color: #FF9F40;">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <p class="stat-title">COD Sales</p>
        <h3 class="stat-value">RM <?php echo number_format($stats['cod_sales'] ?? 0, 2); ?></h3>
        <p class="stat-change">
            <i class="fas fa-percentage"></i> <?php echo ($stats['total_sales'] > 0) ? round(($stats['cod_sales'] / $stats['total_sales']) * 100, 1) : 0; ?>% of total
        </p>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon profit-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <p class="stat-title">Total Profit</p>
        <h3 class="stat-value">RM <?php echo number_format($stats['total_profit'] ?? 0, 2); ?></h3>
        <p class="stat-change positive">
            <i class="fas fa-arrow-up"></i> <?php echo ($stats['total_sales'] > 0) ? round(($stats['total_profit'] / $stats['total_sales']) * 100, 1) : 0; ?>% margin
        </p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon orders-icon">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <p class="stat-title">Total Orders</p>
        <h3 class="stat-value"><?php echo number_format($stats['total_purchases'] ?? 0); ?></h3>
        <p class="stat-change">
            <i class="fas fa-calendar"></i> Past <?php echo round((strtotime($end_date) - strtotime($start_date)) / 86400); ?> days
        </p>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon products-icon">
            <i class="fas fa-box"></i>
        </div>
        <p class="stat-title">Units Sold</p>
        <h3 class="stat-value"><?php echo number_format($stats['total_units'] ?? 0); ?></h3>
        <p class="stat-change">
            <i class="fas fa-box"></i> <?php echo number_format($stats['unique_products'] ?? 0); ?> products
        </p>
    </div>

    <!-- You can add two more stat cards here if needed -->
</div>

            <!-- Charts Section -->
            <div class="chart-grid">
                <!-- Sales & Profit Trend Chart -->
                <div class="chart-card">
                    <h3>Team Sales & Profit Trend</h3>
                    <canvas id="salesTrendChart"></canvas>
                </div>
                
                <!-- Product Categories Pie Chart -->
                <div class="chart-card">
                    <h3>Top 10 Sales by Product Category</h3>
                    <canvas id="categoriesChart"></canvas>
                </div>
            </div>

            <!-- Team Members Performance Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Team Members Performance</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th>Products</th>
                            <th>Total Sales (RM)</th>
                            <th>Total Profit (RM)</th>
                            <th>Profit Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($member = $team_members->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $member['username']; ?></td>
                            <td><?php echo $member['product_count']; ?></td>
                            <td>RM <?php echo number_format($member['member_sales'] ?? 0, 2); ?></td>
                            <td>RM <?php echo number_format($member['member_profit'] ?? 0, 2); ?></td>
                            <td>
                                <?php
                                $margin = ($member['member_sales'] > 0) ? ($member['member_profit'] / $member['member_sales']) * 100 : 0;
                                echo number_format($margin, 1) . '%';
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Products Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Top Selling Team Products</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Units Sold</th>
                            <th>Total Sales (RM)</th>
                            <th>Total Profit (RM)</th>
                            <th>Profit Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $top_products->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $product['product_name']; ?></td>
                            <td><?php echo safeNumberFormat($product['total_sold']); ?></td>
                            <td>RM <?php echo safeNumberFormat($product['total_sales'], 2); ?></td>
<td>RM <?php echo safeNumberFormat($product['total_profit'], 2); ?></td>
<td><?php echo safeNumberFormat($product['profit_margin'], 1); ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- JavaScript for Charts -->
    <script>
    // Sales & Profit Trend Chart
    const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
    const salesTrendChart = new Chart(salesTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [
                {
                    label: 'Sales (RM)',
                    data: <?php echo json_encode($sales_data); ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Profit (RM)',
                    data: <?php echo json_encode($profit_data); ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Fallback for empty data
 //   if (!<?php echo json_encode($dates); ?>.length) {
     //   salesTrendChart.data.labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May'];
     //   salesTrendChart.data.datasets[0].data = [500, 800, 600, 900, 700];
    //    salesTrendChart.data.datasets[1].data = [200, 400, 300, 450, 350];
     //   salesTrendChart.update();
 //   }

    // Product Categories Pie Chart
    const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
    const categoriesChart = new Chart(categoriesCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($category_names); ?>,
            datasets: [{
                label: 'Sales (RM)',
                data: <?php echo json_encode($category_sales); ?>,
                backgroundColor: <?php echo json_encode($category_colors); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.formattedValue;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${label}: RM ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Fallback for empty categories data
    if (!<?php echo json_encode($category_names); ?>.length) {
        categoriesChart.data.labels = ['Product 1', 'Product 2', 'Product 3', 'Product 4', 'Product 5'];
        categoriesChart.data.datasets[0].data = [0, 0, 0, 0, 0];
        categoriesChart.update();
    }
    </script>
</body>
</html>