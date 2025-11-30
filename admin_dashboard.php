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

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Get time period filter
$time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'custom';

// Set date range based on time period
$end_date = date('Y-m-d');

switch ($time_period) {
    case 'today':
        $start_date = date('Y-m-d');
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        break;
    case 'custom':
    default:
        // For custom range, use provided dates or default
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        break;
}

// 1. Get overall statistics
$sql_stats = "SELECT 
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    COUNT(*) as total_products,
    COUNT(DISTINCT product_name) as unique_products,
    SUM(unit_sold) as total_units,
    SUM(purchase) as total_purchases
FROM products
WHERE created_at BETWEEN ? AND ?";

$stmt_stats = $dbconn->prepare($sql_stats);
$stmt_stats->bind_param("ss", $start_date, $end_date);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// 2. Get sales by team
$sql_team_sales = "SELECT 
    t.team_name,
    t.$team_pk as team_id,
    SUM(p.sales) as team_sales,
    SUM(p.profit) as team_profit,
    COUNT(p.id) as product_count
FROM teams t
LEFT JOIN products p ON t.$team_pk = p.team_id AND p.created_at BETWEEN ? AND ?
GROUP BY t.$team_pk
ORDER BY team_sales DESC";

$stmt_team_sales = $dbconn->prepare($sql_team_sales);
$stmt_team_sales->bind_param("ss", $start_date, $end_date);
$stmt_team_sales->execute();
$team_sales = $stmt_team_sales->get_result();

// 3. Get top selling products
$sql_top_products = "SELECT 
    product_name,
    SUM(unit_sold) as total_sold,
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    AVG(profit/sales)*100 as profit_margin
FROM products
WHERE created_at BETWEEN ? AND ?
GROUP BY product_name
ORDER BY total_sales DESC
LIMIT 5";

$stmt_top_products = $dbconn->prepare($sql_top_products);
$stmt_top_products->bind_param("ss", $start_date, $end_date);
$stmt_top_products->execute();
$top_products = $stmt_top_products->get_result();

// 4. Get daily sales data for chart
$sql_daily_sales = "SELECT 
    DATE(created_at) as sale_date,
    SUM(sales) as daily_sales,
    SUM(profit) as daily_profit
FROM products
WHERE created_at BETWEEN ? AND ?
GROUP BY DATE(created_at)
ORDER BY sale_date";

$stmt_daily_sales = $dbconn->prepare($sql_daily_sales);
$stmt_daily_sales->bind_param("ss", $start_date, $end_date);
$stmt_daily_sales->execute();
$daily_sales = $stmt_daily_sales->get_result();

// Prepare data for charts
$dates = [];
$sales_data = [];
$profit_data = [];

while ($row = $daily_sales->fetch_assoc()) {
    $dates[] = $row['sale_date'];
    $sales_data[] = $row['daily_sales'];
    $profit_data[] = $row['daily_profit'];
}

// 5. Get product categories breakdown
$sql_categories = "SELECT 
    product_name as category,
    SUM(sales) as category_sales,
    SUM(profit) as category_profit
FROM products
WHERE created_at BETWEEN ? AND ?
GROUP BY category
ORDER BY category_sales DESC
LIMIT 10"; // Limit to top 10 products

$stmt_categories = $dbconn->prepare($sql_categories);
$stmt_categories->bind_param("ss", $start_date, $end_date);
$stmt_categories->execute();
$categories = $stmt_categories->get_result();

// Prepare data for pie chart
$category_names = [];
$category_sales = [];
$category_colors = [
    '#4361ee', '#3a0ca3', '#7209b7', '#f72585', '#4cc9f0', 
    '#4895ef', '#560bad', '#f15bb5', '#fee440', '#00bbf9'
];

$i = 0;
while ($row = $categories->fetch_assoc()) {
    $category_names[] = $row['category'];
    $category_sales[] = $row['category_sales'];
    $i++;
}

// Debug data
$debug_mode = false; // Set to true to see data in console

// Helper function for active menu items
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page) ? 'active' : '';
}

// Get growth rates for stats cards
function calculateGrowth($current, $previous) {
    if ($previous == 0) return 100;
    return (($current - $previous) / $previous) * 100;
}

// Function to format number with K/M/B suffix
function formatNumber($number) {
    if ($number < 1000) {
        return number_format($number);
    } elseif ($number < 1000000) {
        return number_format($number / 1000, 1) . 'K';
    } elseif ($number < 1000000000) {
        return number_format($number / 1000000, 1) . 'M';
    } else {
        return number_format($number / 1000000000, 1) . 'B';
    }
}

// Get previous period stats for comparison (simplified)
$prev_start_date = date('Y-m-d', strtotime($start_date . ' -' . (strtotime($end_date) - strtotime($start_date)) . ' seconds'));
$prev_end_date = date('Y-m-d', strtotime($end_date . ' -' . (strtotime($end_date) - strtotime($start_date)) . ' seconds'));

$sql_prev_stats = "SELECT 
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    SUM(unit_sold) as total_units,
    SUM(purchase) as total_purchases
FROM products
WHERE created_at BETWEEN ? AND ?";

$stmt_prev_stats = $dbconn->prepare($sql_prev_stats);
$stmt_prev_stats->bind_param("ss", $prev_start_date, $prev_end_date);
$stmt_prev_stats->execute();
$prev_stats = $stmt_prev_stats->get_result()->fetch_assoc();

// Calculate growth rates
$sales_growth = calculateGrowth($stats['total_sales'] ?? 0, $prev_stats['total_sales'] ?? 0);
$profit_growth = calculateGrowth($stats['total_profit'] ?? 0, $prev_stats['total_profit'] ?? 0);
$orders_growth = calculateGrowth($stats['total_purchases'] ?? 0, $prev_stats['total_purchases'] ?? 0);
$units_growth = calculateGrowth($stats['total_units'] ?? 0, $prev_stats['total_units'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Dr Ecomm Formula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    
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
            
            /* New colors for stat cards */
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
        
        /* Filters section */
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
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: var(--dark-text);
        }
        
        .time-filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .time-filter-btn {
            padding: 8px 15px;
            background-color: var(--light-bg);
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--dark-text);
        }
        
        .time-filter-btn:hover {
            background-color: #e9ecef;
        }
        
        .time-filter-btn.active {
            background-color: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        
        .date-range-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-range-inputs input {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .apply-filter-btn {
            padding: 8px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: var(--transition);
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .apply-filter-btn:hover {
            background-color: var(--primary-light);
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
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            opacity: 0.1;
            transform: translate(30%, -30%);
            z-index: 1;
        }
        
        .sales-card {
            border-left: 4px solid var(--sales-color);
        }
        
        .sales-card .stat-icon {
            background-color: var(--sales-color);
            color: white;
        }
        
        .sales-card::after {
            background-color: var(--sales-color);
        }
        
        .profit-card {
            border-left: 4px solid var(--profit-color);
        }
        
        .profit-card .stat-icon {
            background-color: var(--profit-color);
            color: white;
        }
        
        .profit-card::after {
            background-color: var(--profit-color);
        }
        
        .orders-card {
            border-left: 4px solid var(--orders-color);
        }
        
        .orders-card .stat-icon {
            background-color: var(--orders-color);
            color: white;
        }
        
        .orders-card::after {
            background-color: var(--orders-color);
        }
        
        .units-card {
            border-left: 4px solid var(--units-color);
        }
        
        .units-card .stat-icon {
            background-color: var(--units-color);
            color: white;
        }
        
        .units-card::after {
            background-color: var(--units-color);
        }
        
        .stat-content {
            position: relative;
            z-index: 2;
        }
        
        .stat-title {
            margin: 0;
            font-size: 15px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
            color: var(--dark-text);
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            font-size: 14px;
            margin-top: 5px;
            color: #6c757d;
        }
        
        .stat-change i {
            margin-right: 5px;
            font-size: 12px;
        }
        
        .stat-change.positive {
            color: var(--profit-color);
        }
        
        .stat-change.negative {
            color: #e74c3c;
        }
        
        /* Chart grid */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background-color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            
            transition: var(--transition);
        }
        
        .chart-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
        }
        
        .chart-options {
            display: flex;
            gap: 10px;
        }
        
        .chart-option-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #6c757d;
            font-size: 16px;
            transition: var(--transition);
        }
        
        .chart-option-btn:hover {
            color: var(--secondary-color);
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
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .table-action-btn {
            padding: 6px 12px;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .table-action-btn:hover {
            background-color: var(--light-bg);
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
            background-color: var(--light-bg);
            font-weight: 600;
            color: var(--dark-text);
            position: sticky;
            top: 0;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover td {
            background-color: rgba(0,0,0,0.01);
        }
        
        .table-pagination {
            padding: 15px 25px;
            border-top: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-info {
            font-size: 14px;
            color: #6c757d;
        }
        
        .pagination-controls {
            display: flex;
            gap: 5px;
        }
        
        .pagination-btn {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: white;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .pagination-btn:hover {
            background-color: var(--light-bg);
        }
        
        .pagination-btn.active {
            background-color: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        
        /* Loading indicators & animations */
        .loading {
            position: relative;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: inherit;
        }
        
        .loading::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--secondary-color);
            animation: spin 1s linear infinite;
            z-index: 11;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        /* Badges */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-success {
            background-color: rgba(26, 188, 156, 0.1);
            color: #1abc9c;
        }
        
        .badge-warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }
        
        .badge-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .badge-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        /* Progress bar */
        .progress-container {
            width: 100%;
            height: 8px;
            background-color: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease-in-out;
        }
        
        /* Tooltips */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 5px;
            padding: 5px 10px;
            position: absolute;
            z-index: 100;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }
        
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        /* Dropdown */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 5px;
            padding: 8px 0;
            z-index: 100;
            display: none;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            padding: 8px 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .dropdown-item:hover {
            background-color: var(--light-bg);
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding: 20px 0;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Quick stats row */
        .quick-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        
        .quick-stat {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px 20px;
            min-width: 180px;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: var(--transition);
        }
        
        .quick-stat:hover {
            transform: translateY(-3px);
        }
        
        .quick-stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .quick-stat-content {
            display: flex;
            flex-direction: column;
        }
        
        .quick-stat-label {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 3px;
        }
        
        .quick-stat-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .apply-filter-btn {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-range-inputs {
                flex-direction: column;
                width: 100%;
            }
            
            .date-range-inputs input {
                width: 100%;
            }
            
            .time-filter-buttons {
                flex-wrap: wrap;
                width: 100%;
                justify-content: space-between;
            }
            
            .time-filter-btn {
                flex: 1;
                text-align: center;
                min-width: calc(33.333% - 10px);
                margin-bottom: 8px;
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
                <h2>Dr Ecomm</h2>
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
        
        <!-- Main Content Container -->
        <main class="main-content" id="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                
                <div class="dropdown">
                    <button class="dropdown-toggle" id="quickActionsToggle">
                        <i class="fas fa-bolt"></i> Quick Actions
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="quickActionsMenu">
                        <a href="add_product.php" class="dropdown-item">
                            <i class="fas fa-plus"></i> Add New Product
                        </a>
                        <a href="add_team.php" class="dropdown-item">
                            <i class="fas fa-users-cog"></i> Create New Team
                        </a>
                        <a href="export_report.php" class="dropdown-item">
                            <i class="fas fa-file-export"></i> Export Reports
                        </a>
                        <a href="system_settings.php" class="dropdown-item">
                            <i class="fas fa-cogs"></i> System Settings
                        </a>
                    </div>
                </div>
            </header>

            <!-- Filters Section -->
            <form method="GET" action="" id="dateFilterForm" class="filters-container">
                <div class="filter-group">
                    <label>Time Period:</label>
                    <div class="time-filter-buttons">
                        <button type="submit" name="time_period" value="today" class="time-filter-btn <?php echo $time_period == 'today' ? 'active' : ''; ?>">Today</button>
                        <button type="submit" name="time_period" value="yesterday" class="time-filter-btn <?php echo $time_period == 'yesterday' ? 'active' : ''; ?>">Yesterday</button>
                        <button type="submit" name="time_period" value="week" class="time-filter-btn <?php echo $time_period == 'week' ? 'active' : ''; ?>">Last 7 Days</button>
                        <button type="submit" name="time_period" value="month" class="time-filter-btn <?php echo $time_period == 'month' ? 'active' : ''; ?>">Last 30 Days</button>
                        <button type="submit" name="time_period" value="quarter" class="time-filter-btn <?php echo $time_period == 'quarter' ? 'active' : ''; ?>">Last 90 Days</button>
                        <button type="submit" name="time_period" value="year" class="time-filter-btn <?php echo $time_period == 'year' ? 'active' : ''; ?>">Last Year</button>
                        <button type="submit" name="time_period" value="custom" class="time-filter-btn <?php echo $time_period == 'custom' ? 'active' : ''; ?>">Custom</button>
                    </div>
                </div>
                
                <div class="filter-group" id="customDateRange" style="<?php echo $time_period != 'custom' ? 'display:none;' : ''; ?>">
                    <label>Custom Range:</label>
                    <div class="date-range-inputs">
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        <span>to</span>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                
                <button type="submit" class="apply-filter-btn" id="applyFilterBtn" style="<?php echo $time_period != 'custom' ? 'display:none;' : ''; ?>">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
            </form>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card sales-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <p class="stat-title">Total Sales</p>
                        <h3 class="stat-value">RM <?php echo number_format($stats['total_sales'] ?? 0, 2); ?></h3>
                        <div class="stat-change <?php echo $sales_growth >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $sales_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs(round($sales_growth, 1)); ?>% vs previous period
                        </div>
                    </div>
                </div>
                
                <div class="stat-card profit-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <p class="stat-title">Total Profit</p>
                        <h3 class="stat-value">RM <?php echo number_format($stats['total_profit'] ?? 0, 2); ?></h3>
                        <div class="stat-change <?php echo $profit_growth >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $profit_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs(round($profit_growth, 1)); ?>% vs previous period
                        </div>
                    </div>
                </div>
                
                <div class="stat-card orders-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <p class="stat-title">Total Orders</p>
                        <h3 class="stat-value"><?php echo formatNumber($stats['total_purchases'] ?? 0); ?></h3>
                        <div class="stat-change <?php echo $orders_growth >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $orders_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs(round($orders_growth, 1)); ?>% vs previous period
                        </div>
                    </div>
                </div>
                
                <div class="stat-card units-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <p class="stat-title">Units Sold</p>
                        <h3 class="stat-value"><?php echo formatNumber($stats['total_units'] ?? 0); ?></h3>
                        <div class="stat-change <?php echo $units_growth >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $units_growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <?php echo abs(round($units_growth, 1)); ?>% vs previous period
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="chart-grid">
                <!-- Sales & Profit Trend Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Sales & Profit Trend</h3>
                        <div class="chart-options">
                            <button class="chart-option-btn" id="toggleChartType" title="Toggle Chart Type">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                            <button class="chart-option-btn" id="downloadChartData" title="Download Data">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="chart-option-btn" title="Refresh Data">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <canvas id="salesTrendChart"></canvas>
                </div>
                
                <!-- Product Categories Pie Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Sales by Top 10 Products</h3>
                        <div class="chart-options">
                            <button class="chart-option-btn" id="togglePieChart" title="Toggle Chart Type">
                                <i class="fas fa-chart-pie"></i>
                            </button>
                            <button class="chart-option-btn" id="downloadPieData" title="Download Data">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="chart-option-btn" title="Refresh Data">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <canvas id="categoriesChart"></canvas>
                </div>
            </div>

            <!-- Team Performance Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-users"></i> Team Performance</h3>
                    <div class="table-actions">
                        <button class="table-action-btn" id="exportTeamData">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="table-action-btn">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="table-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Products</th>
                                <th>Total Sales (RM)</th>
                                <th>Total Profit (RM)</th>
                                <th>Profit Margin</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $teams_data = []; // For storing teams data for JavaScript
                            while ($team = $team_sales->fetch_assoc()): 
                                $margin = ($team['team_sales'] > 0) ? ($team['team_profit'] / $team['team_sales']) * 100 : 0;
                                $margin_formatted = number_format($margin, 1);
                                
                                // Determine margin rating
                                $margin_class = '';
                                if ($margin < 20) {
                                    $margin_class = 'badge-danger';
                                } elseif ($margin < 35) {
                                    $margin_class = 'badge-warning';
                                } else {
                                    $margin_class = 'badge-success';
                                }
                                
                                // Store team data for JavaScript
                                $teams_data[] = [
                                    'name' => $team['team_name'],
                                    'sales' => $team['team_sales'] ?? 0,
                                    'profit' => $team['team_profit'] ?? 0,
                                    'margin' => $margin
                                ];
                            ?>
                            <tr>
                                <td><?php echo $team['team_name']; ?></td>
                                <td><?php echo $team['product_count']; ?></td>
                                <td>RM <?php echo number_format($team['team_sales'] ?? 0, 2); ?></td>
                                <td>RM <?php echo number_format($team['team_profit'] ?? 0, 2); ?></td>
                                <td><span class="badge <?php echo $margin_class; ?>"><?php echo $margin_formatted; ?>%</span></td>
                                <td>
                                    <div class="progress-container" title="Profit Margin">
                                        <div class="progress-bar" style="width: <?php echo min($margin, 100); ?>%; background-color: var(--<?php echo $margin < 20 ? 'orders' : ($margin < 35 ? 'units' : 'profit'); ?>-color);"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination">
                    <span class="page-info">Showing all teams</span>
                    <div class="pagination-controls">
                        <button class="pagination-btn active">1</button>
                    </div>
                </div>
            </div>

            <!-- Top Products Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-star"></i> Top Selling Products</h3>
                    <div class="table-actions">
                        <button class="table-action-btn" id="exportProductsData">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="table-action-btn">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="table-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Units Sold</th>
                                <th>Total Sales (RM)</th>
                                <th>Total Profit (RM)</th>
                                <th>Profit Margin</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $products_data = []; // For storing products data for JavaScript
                            while ($product = $top_products->fetch_assoc()): 
                                $margin = $product['profit_margin'];
                                $margin_formatted = number_format($margin, 1);
                                
                                // Determine margin rating
                                $margin_class = '';
                                if ($margin < 20) {
                                    $margin_class = 'badge-danger';
                                } elseif ($margin < 35) {
                                    $margin_class = 'badge-warning';
                                } else {
                                    $margin_class = 'badge-success';
                                }
                                
                                // Store product data for JavaScript
                                $products_data[] = [
                                    'name' => $product['product_name'],
                                    'units' => $product['total_sold'],
                                    'sales' => $product['total_sales'],
                                    'profit' => $product['total_profit'],
                                    'margin' => $margin
                                ];
                            ?>
                            <tr>
                                <td><?php echo $product['product_name']; ?></td>
                                <td><?php echo number_format($product['total_sold']); ?></td>
                                <td>RM <?php echo number_format($product['total_sales'], 2); ?></td>
                                <td>RM <?php echo number_format($product['total_profit'], 2); ?></td>
                                <td><span class="badge <?php echo $margin_class; ?>"><?php echo $margin_formatted; ?>%</span></td>
                                <td>
                                    <div class="progress-container" title="Profit Margin">
                                        <div class="progress-bar" style="width: <?php echo min($margin, 100); ?>%; background-color: var(--<?php echo $margin < 20 ? 'orders' : ($margin < 35 ? 'units' : 'profit'); ?>-color);"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination">
                    <span class="page-info">Showing top 5 products</span>
                    <div class="pagination-controls">
                        <button class="pagination-btn active">1</button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> Dr Ecomm Formula | 🛠️ Developed with care by Fakhrul 💻</p>
            </div>
        </main>
    </div>

    <!-- JavaScript for Charts and Interactivity -->
    <script>
    // Toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dropdown functionality
        const dropdownToggle = document.getElementById('quickActionsToggle');
        const dropdownMenu = document.getElementById('quickActionsMenu');
        
        if (dropdownToggle && dropdownMenu) {
            dropdownToggle.addEventListener('click', function() {
                dropdownMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!dropdownToggle.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        }
        
        // Custom date range visibility
        const timeFilterBtns = document.querySelectorAll('.time-filter-btn');
        const customDateRange = document.getElementById('customDateRange');
        const applyFilterBtn = document.getElementById('applyFilterBtn');
        
        timeFilterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const isCustom = this.value === 'custom';
                customDateRange.style.display = isCustom ? 'block' : 'none';
                applyFilterBtn.style.display = isCustom ? 'block' : 'none';
                
                // If not custom, auto-submit the form
                if (!isCustom) {
                    document.getElementById('dateFilterForm').submit();
                }
            });
        });
        
        // Toggle sidebar
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

    // Sales & Profit Trend Chart
    const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
    let chartType = 'line';
    let salesTrendChart;
    
    function initSalesTrendChart() {
        salesTrendChart = new Chart(salesTrendCtx, {
            type: chartType,
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [
                    {
                        label: 'Sales (RM)',
                        data: <?php echo json_encode($sales_data); ?>,
                        borderColor: 'rgba(52, 152, 219, 1)',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Profit (RM)',
                        data: <?php echo json_encode($profit_data); ?>,
                        borderColor: 'rgba(26, 188, 156, 1)',
                        backgroundColor: 'rgba(26, 188, 156, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-MY', {
                                        style: 'currency',
                                        currency: 'MYR'
                                    }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Toggle chart type
    document.getElementById('toggleChartType').addEventListener('click', function() {
        if (chartType === 'line') {
            chartType = 'bar';
            this.innerHTML = '<i class="fas fa-chart-line"></i>';
        } else {
            chartType = 'line';
            this.innerHTML = '<i class="fas fa-chart-bar"></i>';
        }
        
        salesTrendChart.destroy();
        initSalesTrendChart();
    });
    
    // Initialize the chart
    initSalesTrendChart();

    // Fallback for empty data
    if (!<?php echo json_encode($dates); ?>.length) {
        salesTrendChart.data.labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May'];
        salesTrendChart.data.datasets[0].data = [500, 800, 600, 900, 700];
        salesTrendChart.data.datasets[1].data = [200, 400, 300, 450, 350];
        salesTrendChart.update();
    }

    // Product Categories Pie Chart
    const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
    let pieChartType = 'pie';
    let categoriesChart;
    
    function initCategoriesChart() {
        categoriesChart = new Chart(categoriesCtx, {
            type: pieChartType,
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
                maintainAspectRatio: true,
                aspectRatio: 2,
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
    }
    
    // Toggle pie chart type
    document.getElementById('togglePieChart').addEventListener('click', function() {
        if (pieChartType === 'pie') {
            pieChartType = 'doughnut';
            this.innerHTML = '<i class="fas fa-chart-pie"></i>';
        } else if (pieChartType === 'doughnut') {
            pieChartType = 'polarArea';
            this.innerHTML = '<i class="fas fa-chart-area"></i>';
        } else {
            pieChartType = 'pie';
            this.innerHTML = '<i class="fas fa-chart-pie"></i>';
        }
        
        categoriesChart.destroy();
        initCategoriesChart();
    });
    
    // Initialize the chart
    initCategoriesChart();

    // Fallback for empty categories data
    if (!<?php echo json_encode($category_names); ?>.length) {
        categoriesChart.data.labels = ['IASFLIP 1', 'IASFLIP 2', 'IASFLIP 3', 'IASFLIP 4', 'IASFLIP 5'];
        categoriesChart.data.datasets[0].data = [3000, 2500, 2000, 1500, 1000];
        categoriesChart.update();
    }
    
    // Download chart data
    document.getElementById('downloadChartData').addEventListener('click', function() {
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,Date,Sales,Profit\n";
        
        const dates = <?php echo json_encode($dates); ?>;
        const sales = <?php echo json_encode($sales_data); ?>;
        const profits = <?php echo json_encode($profit_data); ?>;
        
        for (let i = 0; i < dates.length; i++) {
            csvContent += dates[i] + "," + sales[i] + "," + profits[i] + "\n";
        }
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "sales_profit_data_<?php echo date('Y-m-d'); ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Download pie chart data
    document.getElementById('downloadPieData').addEventListener('click', function() {
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,Product,Sales\n";
        
        const categories = <?php echo json_encode($category_names); ?>;
        const sales = <?php echo json_encode($category_sales); ?>;
        
        for (let i = 0; i < categories.length; i++) {
            csvContent += categories[i] + "," + sales[i] + "\n";
        }
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "product_sales_data_<?php echo date('Y-m-d'); ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Export team data
    document.getElementById('exportTeamData').addEventListener('click', function() {
        // Get team data
        const teams = <?php echo json_encode($teams_data); ?>;
        
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,Team,Sales,Profit,Margin\n";
        
        teams.forEach(team => {
            csvContent += `${team.name},${team.sales},${team.profit},${team.margin}\n`;
        });
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "team_performance_<?php echo date('Y-m-d'); ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Export products data
    document.getElementById('exportProductsData').addEventListener('click', function() {
        // Get products data
        const products = <?php echo json_encode($products_data); ?>;
        
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,Product,Units,Sales,Profit,Margin\n";
        
        products.forEach(product => {
            csvContent += `${product.name},${product.units},${product.sales},${product.profit},${product.margin}\n`;
        });
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "top_products_<?php echo date('Y-m-d'); ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    </script>
</body>
</html>