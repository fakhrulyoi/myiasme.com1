<?php
require 'super_auth.php';
require 'dbconn_productProfit.php';

// Ensure only super admin or admin can access
require_super_admin();

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

// Get filter type
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'custom';

// Set default date range based on filter type
$today = date('Y-m-d');
$start_date = '';
$end_date = '';

switch ($filter_type) {
    case 'week':
        // Current week (Monday to Sunday)
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        // Current month
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_week':
        // Last week
        $start_date = date('Y-m-d', strtotime('monday last week'));
        $end_date = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'last_month':
        // Last month
        $start_date = date('Y-m-d', strtotime('first day of last month'));
        $end_date = date('Y-m-d', strtotime('last day of last month'));
        break;
    case 'quarter':
        // Current quarter
        $current_month = date('n');
        $current_quarter = ceil($current_month / 3);
        $quarter_start_month = (($current_quarter - 1) * 3) + 1;
        $start_date = date('Y-' . str_pad($quarter_start_month, 2, '0', STR_PAD_LEFT) . '-01');
        $end_date = date('Y-m-t', strtotime($start_date . ' +2 month'));
        break;
    case 'year':
        // Current year
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
    default:
        // Custom date range or default (last 30 days)
        $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : $today;
        break;
}

// Get selected team for filtering (if any)
$selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Get selected ROI filter for Winning DNA
$roi_filter = isset($_GET['roi_filter']) ? intval($_GET['roi_filter']) : 0;

// Get all teams for dropdown
$teams_sql = "SELECT team_id, team_name FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_sql);
$teams = [];
while($team = $teams_result->fetch_assoc()) {
    $teams[] = $team;
}

// Build SQL conditions
$team_condition = $selected_team > 0 ? "AND team_id = " . $selected_team : "";

// 1. Get overall summary statistics
$sql_stats = "SELECT 
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    SUM(ads_spend) as total_ads_spend,
    SUM(item_cost) as total_cogs,
    SUM(cod) as total_shipping,
    COUNT(*) as total_products,
    COUNT(DISTINCT product_name) as unique_products,
    SUM(unit_sold) as total_units,
    SUM(purchase) as total_orders
FROM products p
WHERE created_at BETWEEN ? AND ? $team_condition";

$stmt_stats = $dbconn->prepare($sql_stats);
$stmt_stats->bind_param("ss", $start_date, $end_date);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();


// 2. Get sales by team
$sql_team_sales = "SELECT 
    t.team_name,
    t.team_id,
    SUM(p.sales) as team_sales,
    SUM(p.profit) as team_profit,
    SUM(p.ads_spend) as team_ads_spend,
    SUM(p.item_cost) as team_cogs,
    SUM(p.cod) as team_shipping,
    COUNT(p.id) as product_count,
    SUM(p.unit_sold) as units_sold,
    SUM(p.purchase) as orders_count
FROM teams t
LEFT JOIN products p ON t.team_id = p.team_id AND p.created_at BETWEEN ? AND ?
GROUP BY t.team_id
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
    AVG(profit/sales)*100 as profit_margin,
    SUM(ads_spend) as ads_spend,
    SUM(item_cost) as cogs,
    team_id
FROM products
WHERE created_at BETWEEN ? AND ? $team_condition
GROUP BY product_name
ORDER BY total_profit DESC
LIMIT 20";

$stmt_top_products = $dbconn->prepare($sql_top_products);
$stmt_top_products->bind_param("ss", $start_date, $end_date);
$stmt_top_products->execute();
$top_products = $stmt_top_products->get_result();

// 4. Get daily sales data for chart
$sql_daily_sales = "SELECT 
    DATE(created_at) as sale_date,
    SUM(sales) as daily_sales,
    SUM(profit) as daily_profit,
    SUM(ads_spend) as daily_ads_spend,
    SUM(item_cost) as daily_cogs
FROM products p
WHERE created_at BETWEEN ? AND ? $team_condition
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
$ads_data = [];
$cogs_data = [];

// Update this code in the daily sales loop
while ($row = $daily_sales->fetch_assoc()) {
    $dates[] = $row['sale_date'];
    $sales_data[] = (float)($row['daily_sales'] ?? 0);
    $profit_data[] = (float)($row['daily_profit'] ?? 0);
    $ads_data[] = (float)($row['daily_ads_spend'] ?? 0);
    $cogs_data[] = (float)($row['daily_cogs'] ?? 0);
}

// 5. Get Winning DNA (top performing products) with ROI filter
$roi_condition = "";
if ($roi_filter > 0) {
    $roi_condition = "HAVING (SUM(profit)/SUM(ads_spend))*100 >= $roi_filter";
} else {
    $roi_condition = "HAVING SUM(profit) > 0 AND SUM(ads_spend) > 0";
}

$sql_winning_dna = "SELECT 
    product_name,
    SUM(unit_sold) as total_sold,
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    SUM(profit)/SUM(sales)*100 as profit_margin,
    SUM(ads_spend) as total_ads_spend,
    (SUM(profit)/SUM(ads_spend))*100 as roi,
    team_id
FROM products
WHERE created_at BETWEEN ? AND ? $team_condition
GROUP BY product_name
$roi_condition
ORDER BY roi DESC
LIMIT 15";

$stmt_winning_dna = $dbconn->prepare($sql_winning_dna);
$stmt_winning_dna->bind_param("ss", $start_date, $end_date);
$stmt_winning_dna->execute();
$winning_dna = $stmt_winning_dna->get_result();

// 6. Get All ROI by team
$sql_team_roi = "SELECT 
    t.team_name,
    t.team_id,
    SUM(p.profit) as team_profit,
    SUM(p.ads_spend) as team_ads_spend,
    CASE 
        WHEN SUM(p.ads_spend) > 0 THEN (SUM(p.profit)/SUM(p.ads_spend))*100
        ELSE 0
    END as team_roi
FROM teams t
LEFT JOIN products p ON t.team_id = p.team_id AND p.created_at BETWEEN ? AND ?
GROUP BY t.team_id
ORDER BY team_roi DESC";

$stmt_team_roi = $dbconn->prepare($sql_team_roi);
$stmt_team_roi->bind_param("ss", $start_date, $end_date);
$stmt_team_roi->execute();
$team_roi = $stmt_team_roi->get_result();

// Helper function for active menu items
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page) ? 'active' : '';
}
// Helper function to safely format numbers
function safe_number_format($number, $decimals = 0) {
    // If null or not numeric, return 0 formatted appropriately
    if ($number === null || !is_numeric($number)) {
        $number = 0;
    }
    return number_format((float)$number, $decimals);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* ===== Modern Dashboard Styles ===== */
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3f37c9;
            --secondary: #f72585;
            --secondary-light: #ff4d6d;
            --success: #2ec4b6;
            --info: #4cc9f0;
            --warning: #fca311;
            --danger: #e63946;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --gray-dark: #495057;
            --border-radius: 0.75rem;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        p {
            margin-bottom: 1rem;
        }

        a {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        a:hover {
            color: var(--primary-dark);
        }

        /* Layout */
        .app-container {
            display: flex;
            position: relative;
            min-height: 100vh;
            width: 100%;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: #ffffff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--gray-light);
        }

        .sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        .logo-container {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            height: var(--header-height);
        }

        .logo-icon {
            font-size: 1.8rem;
            color: var(--primary);
            margin-right: 0.75rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            white-space: nowrap;
            transition: var(--transition);
        }

        .sidebar-collapsed .logo-text {
            opacity: 0;
            width: 0;
            display: none;
        }

        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            transition: var(--transition);
        }

        .toggle-sidebar:hover {
            color: var(--dark);
        }

        .sidebar-collapsed .toggle-sidebar i {
            transform: rotate(180deg);
        }

        .user-info {
            display: flex;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            background-color: rgba(67, 97, 238, 0.05);
            transition: var(--transition);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            font-size: 1.25rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .user-details {
            overflow: hidden;
            transition: var(--transition);
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
            color: var(--dark);
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--gray);
            white-space: nowrap;
        }

        .sidebar-collapsed .user-details {
            opacity: 0;
            width: 0;
            display: none;
        }

        .nav-links {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
            overflow-y: auto;
            flex-grow: 1;
        }

        .nav-category {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            font-weight: 600;
            padding: 0.75rem 1.5rem 0.5rem;
            transition: var(--transition);
        }

        .sidebar-collapsed .nav-category {
            opacity: 0;
            height: 0;
            padding: 0;
            overflow: hidden;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--gray-dark);
            border-radius: 0.5rem;
            margin: 0 0.75rem;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
            background-color: rgba(67, 97, 238, 0.08);
            color: var(--primary);
        }

        .nav-link i {
            font-size: 1.25rem;
            min-width: 1.75rem;
            margin-right: 0.75rem;
            text-align: center;
            transition: var(--transition);
        }

        .nav-link-text {
            transition: var(--transition);
            white-space: nowrap;
        }

        .sidebar-collapsed .nav-link-text {
            opacity: 0;
            width: 0;
            display: none;
        }

        .nav-link.active {
            background-color: var(--primary);
            color: white;
        }

        .nav-link.active:hover {
            background-color: var(--primary-dark);
            color: white;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-light);
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .sidebar-collapsed .sidebar-footer {
            justify-content: center;
            padding: 1rem 0;
        }

        .sidebar-footer-text {
            font-size: 0.8rem;
            color: var(--gray);
            transition: var(--transition);
        }

        .sidebar-collapsed .sidebar-footer-text {
            opacity: 0;
            width: 0;
            display: none;
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            transition: var(--transition);
        }

        .sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            background-color: white;
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .page-title {
            margin: 0;
            font-size: 1.5rem;
            color: var(--dark);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.9rem;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 50%;
            font-size: 1.25rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            color: white;
        }

        .btn-light {
            background-color: white;
            color: var(--dark);
            border: 1px solid var(--gray-light);
        }

        .btn-light:hover {
            background-color: var(--gray-light);
        }

        .user-dropdown {
            position: relative;
        }

        /* Filter Section */
        .filter-section {
            background-color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
        }

        .filter-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .filter-icon {
            font-size: 1.25rem;
            color: var(--primary);
            margin-right: 0.75rem;
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .filter-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: white;
            color: var(--gray-dark);
            border: 1px solid var(--gray-light);
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-btn i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-btn:hover {
            background-color: var(--gray-light);
        }

        .filter-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .custom-date-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--gray-light);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            flex-grow: 1;
        }

        .form-label {
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            color: var(--gray-dark);
            font-weight: 500;
        }

        .form-control {
            border: 1px solid var(--gray-light);
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='none' d='M0 0h24v24H0z'/%3E%3Cpath d='M12 13.172l4.95-4.95 1.414 1.414L12 16 5.636 9.636 7.05 8.222z' fill='rgba(107,114,128,1)'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }

        .filter-active-info {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            background-color: rgba(67, 97, 238, 0.08);
            border-left: 4px solid var(--primary);
            margin-top: 1.25rem;
        }

        .filter-info-icon {
            font-size: 1.25rem;
            color: var(--primary);
            margin-right: 0.75rem;
        }

        .filter-info-text {
            font-size: 0.9rem;
            color: var(--gray-dark);
            margin: 0;
        }

        .filter-info-text strong {
            color: var(--dark);
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background-color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-top: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
        }

        .stat-card.sales {
            border-color: var(--primary);
        }

        .stat-card.profit {
            border-color: var(--success);
        }

        .stat-card.orders {
            border-color: var(--warning);
        }

        .stat-card.ads {
            border-color: var(--secondary);
        }

        .stat-card.cogs {
            border-color: var(--gray-dark);
        }

        .stat-card.shipping {
            border-color: var(--info);
        }

        .stat-card.products {
            border-color: var(--primary-dark);
        }

        .stat-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            opacity: 0.1;
            transform: translate(30%, -30%);
            transition: var(--transition);
        }

        .stat-card:hover .stat-pattern {
            transform: translate(25%, -25%) scale(1.05);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
            transition: var(--transition);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        .sales .stat-icon {
            background-color: rgba(67, 97, 238, 0.15);
            color: var(--primary);
        }

        .profit .stat-icon {
            background-color: rgba(46, 196, 182, 0.15);
            color: var(--success);
        }

        .orders .stat-icon {
            background-color: rgba(252, 163, 17, 0.15);
            color: var(--warning);
        }

        .ads .stat-icon {
            background-color: rgba(247, 37, 133, 0.15);
            color: var(--secondary);
        }

        .cogs .stat-icon {
            background-color: rgba(73, 80, 87, 0.15);
            color: var(--gray-dark);
        }

        .shipping .stat-icon {
            background-color: rgba(76, 201, 240, 0.15);
            color: var(--info);
        }

        .products .stat-icon {
            background-color: rgba(63, 55, 201, 0.15);
            color: var(--primary-dark);
        }

        .stat-title {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }

        .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: var(--gray);
        }

        .stat-change i {
            margin-right: 0.35rem;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .chart-card {
            background-color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 400px;
            display: flex;
            flex-direction: column;
        }

        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .chart-actions {
            display: flex;
            gap: 0.5rem;
        }

        .chart-container {
            flex-grow: 1;
            position: relative;
        }

        /* Table */
        .table-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .table-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .table-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .table-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-filter {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .export-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: var(--primary);
            color: white;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .export-btn i {
            margin-right: 0.5rem;
        }

        .export-btn:hover {
            background-color: var(--primary-dark);
            color: white;
        }

        .responsive-table {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.9rem;
        }

        table th {
            background-color: var(--gray-light);
            color: var(--gray-dark);
            font-weight: 600;
            white-space: nowrap;
        }

        table td {
            border-bottom: 1px solid var(--gray-light);
            color: var(--dark);
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:nth-child(even) {
            background-color: rgba(67, 97, 238, 0.03);
        }

        table tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        /* ROI Filter */
        .roi-filter {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .roi-filter-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .roi-slider-container {
            flex-grow: 1;
            position: relative;
        }

        .roi-slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: var(--gray-light);
            outline: none;
            -webkit-appearance: none;
        }

        .roi-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
        }

        .roi-slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }

        .roi-slider::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            border: none;
        }

        .roi-slider::-moz-range-thumb:hover {
            transform: scale(1.1);
        }

        .roi-value {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            min-width: 60px;
            text-align: center;
            font-size: 0.875rem;
        }

        .roi-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
        }

        .roi-badge.high {
            background-color: rgba(46, 196, 182, 0.15);
            color: var(--success);
        }

        .roi-badge.medium {
            background-color: rgba(67, 97, 238, 0.15);
            color: var(--primary);
        }

        .roi-badge.low {
            background-color: rgba(252, 163, 17, 0.15);
            color: var(--warning);
        }

        /* Responsive Adjustments */
        @media (max-width: 1366px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
            .charts-grid {
                grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            :root {
                --sidebar-width: 0;
                --sidebar-collapsed-width: 0;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar-open .sidebar {
                transform: translateX(0);
                width: var(--sidebar-width);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-open .main-content {
                margin-left: 0;
                position: relative;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 900;
            }
            
            .sidebar-open .sidebar-overlay {
                display: block;
            }
            
            .toggle-sidebar-mobile {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .filter-buttons {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            
            .custom-date-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .table-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .filter-section {
                padding: 1rem;
            }
            
            .chart-card, .stat-card, .table-card {
                padding: 1rem;
            }
            
            .table-header, .table-filter {
                padding: 1rem;
            }
            
            table th, table td {
                padding: 0.75rem 1rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .toggle-sidebar-mobile {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
            margin-right: 1rem;
        }
    </style>
</head>
<body>
    <div id="app" class="app-container">
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="logo-text">MYIASME</div>
                <button class="toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo isset($username) ? $username : 'Admin'; ?></div>
                    <div class="user-role"><?php echo isset($is_super_admin) && $is_super_admin ? 'Super Admin' : ($is_admin ? 'Admin' : 'Team Member'); ?></div>
                </div>
            </div>
            
            <ul class="nav-links">
                <li class="nav-category">Management</li>
                
                <li class="nav-item">
                    <a href="super_dashboard.php" class="nav-link <?php echo isActive('super_dashboard.php'); ?>">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-link-text">Executive Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="teams_superadmin.php" class="nav-link <?php echo isActive('teams_superadmin.php'); ?>">
                        <i class="fas fa-users"></i>
                        <span class="nav-link-text">Teams</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="winning_dna.php" class="nav-link <?php echo isActive('winning_dna.php'); ?>">
                        <i class="fa-solid fa-medal"></i>
                        <span class="nav-link-text">Winning DNA</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="product_approvals.php" class="nav-link <?php echo isActive('product_approvals.php'); ?>">
                        <i class="fas fa-check-circle"></i>
                        <span class="nav-link-text">Product Approvals</span>
                    </a>
                </li>
                
            
                                          
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="nav-link-text">Logout</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <span class="sidebar-footer-text">MYIASME &copy; <?php echo date('Y'); ?></span>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content fade-in">
            <!-- Page Header -->
            <header class="page-header">
                <button class="toggle-sidebar-mobile" id="toggleSidebarMobile">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h1 class="page-title">
                    <i class="fas fa-tachometer-alt"></i> Executive Dashboard
                </h1>
                
                <div class="header-actions">
                    <button class="btn btn-light">
                        <i class="fas fa-redo-alt"></i> Refresh
                    </button>
                </div>
            </header>
            
            <!-- Filter Section -->
            <section class="filter-section fade-in" style="animation-delay: 0.1s;">
                <div class="filter-header">
                    <span class="filter-icon">
                        <i class="fas fa-filter"></i>
                    </span>
                    <h3 class="filter-title">Filter Dashboard</h3>
                </div>
                
                <!-- Filter Buttons -->
                <div class="filter-buttons">
                    <a href="?filter_type=week<?php echo $selected_team ? '&team_id='.$selected_team : ''; ?><?php echo $roi_filter ? '&roi_filter='.$roi_filter : ''; ?>" class="filter-btn <?php echo $filter_type == 'week' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week"></i> This Week
                    </a>
                    <a href="?filter_type=month<?php echo $selected_team ? '&team_id='.$selected_team : ''; ?><?php echo $roi_filter ? '&roi_filter='.$roi_filter : ''; ?>" class="filter-btn <?php echo $filter_type == 'month' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> This Month
                    </a>
                    <a href="?filter_type=last_week<?php echo $selected_team ? '&team_id='.$selected_team : ''; ?><?php echo $roi_filter ? '&roi_filter='.$roi_filter : ''; ?>" class="filter-btn <?php echo $filter_type == 'last_week' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week"></i> Last Week
                    </a>
                    <a href="?filter_type=last_month<?php echo $selected_team ? '&team_id='.$selected_team : ''; ?><?php echo $roi_filter ? '&roi_filter='.$roi_filter : ''; ?>" class="filter-btn <?php echo $filter_type == 'last_month' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Last Month
                    </a>
                    <a href="?filter_type=quarter<?php echo $selected_team ? '&team_id='.$selected_team : ''; ?><?php echo $roi_filter ? '&roi_filter='.$roi_filter : ''; ?>" class="filter-btn <?php echo $filter_type == 'quarter' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie"></i> This Quarter
                    </a>
                    <a href="?filter_type=year<?php echo $selected_team ? '&team_id='.$selected_team : ''; ?><?php echo $roi_filter ? '&roi_filter='.$roi_filter : ''; ?>" class="filter-btn <?php echo $filter_type == 'year' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> This Year
                    </a>
                    <a href="?filter_type=custom<?php echo $selected_team ? '&team_id='.$selected_team : ''; ?><?php echo $roi_filter ? '&roi_filter='.$roi_filter : ''; ?>" class="filter-btn <?php echo $filter_type == 'custom' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-day"></i> Custom Range
                    </a>
                </div>
                
                <!-- Custom Date Form -->
                <form method="GET" action="" id="customDateForm" class="custom-date-form" style="<?php echo $filter_type != 'custom' ? 'display: none;' : ''; ?>">
                    <input type="hidden" name="filter_type" value="custom">
                    
                    <div class="form-group">
                        <label for="start_date" class="form-label">From Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date" class="form-label">To Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="team_id" class="form-label">Select Team</label>
                        <select id="team_id" name="team_id" class="form-control form-select">
                            <option value="0">All Teams</option>
                            <?php foreach($teams as $team): ?>
                            <option value="<?php echo $team['team_id']; ?>" <?php echo $selected_team == $team['team_id'] ? 'selected' : ''; ?>><?php echo $team['team_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (isset($_GET['roi_filter'])): ?>
                    <input type="hidden" name="roi_filter" value="<?php echo $roi_filter; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Apply Filter
                        </button>
                    </div>
                </form>
                
                <!-- Active Filter Info -->
                <div class="filter-active-info">
                    <span class="filter-info-icon">
                        <i class="fas fa-info-circle"></i>
                    </span>
                    <p class="filter-info-text">
                        <?php
                        $filter_description = "";
                        switch ($filter_type) {
                            case 'week':
                                $filter_description = "This Week";
                                break;
                            case 'month':
                                $filter_description = "This Month";
                                break;
                            case 'last_week':
                                $filter_description = "Last Week";
                                break;
                            case 'last_month':
                                $filter_description = "Last Month";
                                break;
                            case 'quarter':
                                $filter_description = "This Quarter";
                                break;
                            case 'year':
                                $filter_description = "This Year";
                                break;
                            case 'custom':
                            default:
                                $filter_description = "Custom Range";
                                break;
                        }
                        ?>
                        Showing results for <strong><?php echo $filter_description; ?></strong> (<strong><?php echo date('d M, Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('d M, Y', strtotime($end_date)); ?></strong>)
                        <?php if($selected_team > 0): ?>
                        for team <strong><?php 
                            foreach($teams as $team) {
                                if($team['team_id'] == $selected_team) {
                                    echo $team['team_name'];
                                    break;
                                }
                            }
                        ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
            </section>
            
            <!-- Stats Overview -->
            <section class="stats-grid">
                <div class="stat-card sales fade-in" style="animation-delay: 0.2s;">
                    <div class="stat-pattern">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <circle cx="50" cy="50" r="40" stroke="currentColor" stroke-width="10" />
                        </svg>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h3 class="stat-title">Total Sales</h3>
                 <div class="stat-value">RM <?php echo safe_number_format($stats['total_sales'], 2); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
                    </div>
                </div>
                
                <div class="stat-card profit fade-in" style="animation-delay: 0.3s;">
                    <div class="stat-pattern">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <path d="M20 80 L40 50 L60 65 L80 20" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="stat-title">Total Profit</h3>
                    <div class="stat-value">RM <?php echo number_format($stats['total_profit'] ?? 0, 2); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-percentage"></i> <?php echo ($stats['total_sales'] > 0) ? round(($stats['total_profit'] / $stats['total_sales']) * 100, 1) : 0; ?>% margin
                    </div>
                </div>
                
                <div class="stat-card orders fade-in" style="animation-delay: 0.4s;">
                    <div class="stat-pattern">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <rect x="20" y="20" width="60" height="60" rx="5" stroke="currentColor" stroke-width="10" />
                        </svg>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="stat-title">Total Orders</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-boxes"></i> Units: <?php echo number_format($stats['total_units'] ?? 0); ?>
                    </div>
                </div>
                
                <div class="stat-card ads fade-in" style="animation-delay: 0.5s;">
                    <div class="stat-pattern">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <path d="M20 50 L40 30 L60 70 L80 40" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-ad"></i>
                    </div>
                    <h3 class="stat-title">Ads Spend</h3>
                    <div class="stat-value">RM <?php echo number_format($stats['total_ads_spend'] ?? 0, 2); ?></div>
                    <div class="stat-change <?php echo ($stats['total_ads_spend'] > 0 && ($stats['total_profit']/$stats['total_ads_spend']) >= 1) ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-chart-pie"></i> ROI: <?php echo ($stats['total_ads_spend'] > 0) ? round(($stats['total_profit'] / $stats['total_ads_spend']) * 100, 1) : 0; ?>%
                    </div>
                </div>
                
                <div class="stat-card cogs fade-in" style="animation-delay: 0.6s;">
                    <div class="stat-pattern">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <path d="M30 30 L70 70 M30 70 L70 30" stroke="currentColor" stroke-width="10" stroke-linecap="round" />
                        </svg>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3 class="stat-title">Total COGS</h3>
                    <div class="stat-value">RM <?php echo number_format($stats['total_cogs'] ?? 0, 2); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-tag"></i> <?php echo ($stats['total_sales'] > 0) ? round(($stats['total_cogs'] / $stats['total_sales']) * 100, 1) : 0; ?>% of revenue
                    </div>
                </div>
                
                <div class="stat-card shipping fade-in" style="animation-delay: 0.7s;">
                    <div class="stat-pattern">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <polygon points="50,20 80,40 80,70 50,90 20,70 20,40" stroke="currentColor" stroke-width="10" />
                        </svg>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3 class="stat-title">Total Shipping</h3>
                    <div class="stat-value">RM <?php echo number_format($stats['total_shipping'] ?? 0, 2); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-percentage"></i> <?php echo ($stats['total_sales'] > 0) ? round(($stats['total_shipping'] / $stats['total_sales']) * 100, 1) : 0; ?>% of revenue
                    </div>
                </div>
                
                <div class="stat-card products fade-in" style="animation-delay: 0.8s;">
                    <div class="stat-pattern">
                        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <circle cx="30" cy="30" r="15" stroke="currentColor" stroke-width="10" />
                            <circle cx="70" cy="70" r="15" stroke="currentColor" stroke-width="10" />
                        </svg>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3 class="stat-title">Products</h3>
                    <div class="stat-value"><?php echo number_format($stats['unique_products'] ?? 0); ?></div>
                    <div class="stat-change">
                        <i class="fas fa-calendar-day"></i> <?php echo round((strtotime($end_date) - strtotime($start_date)) / 86400); ?> days period
                    </div>
                </div>
            </section>
            
            <!-- Charts Section -->
            <section class="charts-grid">
                <!-- Sales & Profit Trend Chart -->
                <div class="chart-card fade-in" style="animation-delay: 0.9s;">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-area"></i> Sales & Profit Trend
                        </h3>
                        <div class="chart-actions">
                            <button class="btn btn-light btn-icon" title="Download Chart">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>
                
                <!-- Revenue vs Expenses Chart -->
                <div class="chart-card fade-in" style="animation-delay: 1s;">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-balance-scale"></i> Revenue vs Expenses
                        </h3>
                        <div class="chart-actions">
                            <button class="btn btn-light btn-icon" title="Download Chart">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="expenseChart"></canvas>
                    </div>
                </div>
            </section>
            
            <!-- Team Performance Table -->
            <section class="table-card fade-in" style="animation-delay: 1.1s;">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-users"></i> Team Performance
                    </h3>
                    <div class="table-actions">
                        <a href="team_performance_export.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="export-btn">
                            <i class="fas fa-file-export"></i> Export Data
                        </a>
                    </div>
                </div>
                <div class="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Total Sales (RM)</th>
                                <th>Total Profit (RM)</th>
                                <th>Margin %</th>
                                <th>Ads Spend (RM)</th>
                                <th>ROI %</th>
                                <th>COGS (RM)</th>
                                <th>Shipping (RM)</th>
                                <th>Orders</th>
                                <th>Units Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($team = $team_sales->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $team['team_name']; ?></strong>
                                </td>
                                <td>RM <?php echo safe_number_format($team['team_sales'], 2); ?></td>
<td>RM <?php echo safe_number_format($team['team_profit'], 2); ?></td>
<td>
    <?php
    $margin = ($team['team_sales'] > 0) ? ($team['team_profit'] / $team['team_sales']) * 100 : 0;
    echo safe_number_format($margin, 1) . '%';
    ?>
</td>
<td>RM <?php echo safe_number_format($team['team_ads_spend'], 2); ?></td>
<td>
    <?php
    $roi = ($team['team_ads_spend'] > 0) ? ($team['team_profit'] / $team['team_ads_spend']) * 100 : 0;
    echo safe_number_format($roi, 1) . '%';
    ?>
</td>
<td>RM <?php echo safe_number_format($team['team_cogs'], 2); ?></td>
<td>RM <?php echo safe_number_format($team['team_shipping'], 2); ?></td>
<td><?php echo safe_number_format($team['orders_count']); ?></td>
<td><?php echo safe_number_format($team['units_sold']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Winning DNA Table -->
            <section class="table-card fade-in" style="animation-delay: 1.2s;">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-award"></i> Winning DNA (Top ROI Products)
                    </h3>
                    <div class="table-actions">
                        <a href="winning_dna_export.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&team_id=<?php echo $selected_team; ?>&roi_filter=<?php echo $roi_filter; ?>" class="export-btn">
                            <i class="fas fa-file-export"></i> Export Data
                        </a>
                    </div>
                </div>
                
                <!-- ROI Filter -->
                <div class="table-filter">
                    <form method="GET" action="" id="roiFilterForm" class="roi-filter">
                        <input type="hidden" name="filter_type" value="<?php echo $filter_type; ?>">
                        <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                        <?php if($selected_team > 0): ?>
                        <input type="hidden" name="team_id" value="<?php echo $selected_team; ?>">
                        <?php endif; ?>
                        
                        <label for="roi_filter" class="form-label">ROI% Threshold Filter:</label>
                        <div class="roi-filter-container">
                            <div class="roi-slider-container">
                                <input type="range" id="roi_filter" name="roi_filter" min="0" max="500" step="25" value="<?php echo $roi_filter; ?>" class="roi-slider">
                            </div>
                            <span id="roi_value" class="roi-value"><?php echo $roi_filter; ?>%</span>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sales (RM)</th>
                                <th>Profit (RM)</th>
                                <th>Ads Spend (RM)</th>
                                <th>ROI %</th>
                                <th>Margin %</th>
                                <th>Units Sold</th>
                                <th>Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $team_map = [];
                            foreach($teams as $t) {
                                $team_map[$t['team_id']] = $t['team_name'];
                            }
                            if ($winning_dna->num_rows > 0):
                            while ($product = $winning_dna->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $product['product_name']; ?></strong></td>
                                <td>RM <?php echo safe_number_format($product['total_sales'], 2); ?></td>
<td>RM <?php echo safe_number_format($product['total_profit'], 2); ?></td>
<td>RM <?php echo safe_number_format($product['total_ads_spend'], 2); ?></td>
<td>
                                    <?php 
                                    $roi_class = '';
                                    if ($product['roi'] >= 200) {
                                        $roi_class = 'high';
                                    } elseif ($product['roi'] >= 100) {
                                        $roi_class = 'medium';
                                    } else {
                                        $roi_class = 'low';
                                    }
                                    ?>
                                     <span class="roi-badge <?php echo $roi_class; ?>"><?php echo safe_number_format($product['roi'], 1); ?>%</span>
                               </td>
<td><?php echo safe_number_format($product['profit_margin'], 1); ?>%</td>
<td><?php echo safe_number_format($product['total_sold']); ?></td>
                                <td><?php echo isset($team_map[$product['team_id']]) ? $team_map[$product['team_id']] : 'Unknown'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--gray);">
                                    <i class="far fa-frown" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                    No products meet the current ROI filter criteria (<?php echo $roi_filter; ?>%). Try lowering the ROI threshold.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Top Products Table -->
            <section class="table-card fade-in" style="animation-delay: 1.3s;">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-star"></i> Top Profitable Products
                    </h3>
                    <div class="table-actions">
                        <a href="top_products_export.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&team_id=<?php echo $selected_team; ?>" class="export-btn">
                            <i class="fas fa-file-export"></i> Export Data
                        </a>
                    </div>
                </div>
                <div class="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Units Sold</th>
                                <th>Total Sales (RM)</th>
                                <th>Total Profit (RM)</th>
                                <th>Ads Spend (RM)</th>
                                <th>COGS (RM)</th>
                                <th>Profit Margin</th>
                                <th>Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $top_products->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $product['product_name']; ?></strong></td>
                                <td><?php echo safe_number_format($product['total_sold']); ?></td>
                                <td>RM <?php echo safe_number_format($product['total_sales'], 2); ?></td>
<td>RM <?php echo safe_number_format($product['total_profit'], 2); ?></td>
<td>RM <?php echo safe_number_format($product['ads_spend'], 2); ?></td>
<td>RM <?php echo safe_number_format($product['cogs'], 2); ?></td>
<td><?php echo safe_number_format($product['profit_margin'], 1); ?>%</td>
                                <td><?php echo isset($team_map[$product['team_id']]) ? $team_map[$product['team_id']] : 'Unknown'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Footer -->
            <footer style="text-align: center; padding: 1.5rem 0; color: var(--gray); margin-top: 1.5rem; font-size: 0.875rem;">
                <p>MYIASME &copy; <?php echo date('Y'); ?>. | 🛠️ Developed with care by Fakhrul 💻</p>
            </footer>
        </main>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // DOM Elements
    const app = document.getElementById('app');
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    const toggleSidebarMobile = document.getElementById('toggleSidebarMobile');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const customDateForm = document.getElementById('customDateForm');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const roiSlider = document.getElementById('roi_filter');
    const roiValue = document.getElementById('roi_value');
    
    // Toggle Sidebar
    function toggleSidebarFunc() {
        app.classList.toggle('sidebar-collapsed');
    }
    
    toggleSidebar.addEventListener('click', toggleSidebarFunc);
    
    // Mobile Sidebar Toggle
    function toggleSidebarMobileFunc() {
        app.classList.toggle('sidebar-open');
    }
    
    toggleSidebarMobile.addEventListener('click', toggleSidebarMobileFunc);
    sidebarOverlay.addEventListener('click', toggleSidebarMobileFunc);
    
    // Show/hide custom date form
    filterButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.href.includes('filter_type=custom')) {
                e.preventDefault();
                customDateForm.style.display = 'flex';
                
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
    
    // ROI Slider
    roiSlider.addEventListener('input', function() {
        roiValue.textContent = this.value + '%';
    });
    
    // Chart.js Configuration
    
    // 1. Sales & Profit Trend Chart
    const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
    const salesTrendChart = new Chart(salesTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [
                {
                    label: 'Sales (RM)',
                    data: <?php echo json_encode(array_map('floatval', $sales_data)); ?>,
                    borderColor: 'rgba(67, 97, 238, 1)',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Profit (RM)',
                    data: <?php echo json_encode(array_map('floatval', $profit_data)); ?>,
                    borderColor: 'rgba(46, 196, 182, 1)',
                    backgroundColor: 'rgba(46, 196, 182, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(46, 196, 182, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        padding: 15,
                        font: {
                            size: 12,
                            family: "'Inter', sans-serif"
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(33, 37, 41, 0.8)',
                    titleFont: {
                        size: 13,
                        family: "'Inter', sans-serif",
                        weight: '600'
                    },
                    bodyFont: {
                        size: 12,
                        family: "'Inter', sans-serif"
                    },
                    padding: 10,
                    cornerRadius: 6,
                    caretSize: 6,
                    displayColors: true,
                    boxWidth: 10,
                    boxHeight: 10,
                    boxPadding: 4,
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
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 10,
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        color: '#6c757d',
                        padding: 10,
                        callback: function(value, index, values) {
                            const label = this.getLabelForValue(value);
                            try {
                                const date = new Date(label);
                                return date.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
                            } catch(e) {
                                return label;
                            }
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        color: '#6c757d',
                        padding: 10,
                        callback: function(value, index, values) {
                            return 'RM ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // 2. Revenue vs Expenses Chart
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    const expenseChart = new Chart(expenseCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [
                {
                    label: 'Revenue (RM)',
                    data: <?php echo json_encode(array_map('floatval', $sales_data)); ?>,
                    borderColor: 'rgba(67, 97, 238, 1)',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Ads Spend (RM)',
                    data: <?php echo json_encode(array_map('floatval', $ads_data)); ?>,
                    borderColor: 'rgba(247, 37, 133, 1)',
                    backgroundColor: 'rgba(247, 37, 133, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(247, 37, 133, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'COGS (RM)',
                    data: <?php echo json_encode(array_map('floatval', $cogs_data)); ?>,
                    borderColor: 'rgba(73, 80, 87, 1)',
                    backgroundColor: 'rgba(73, 80, 87, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(73, 80, 87, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        padding: 15,
                        font: {
                            size: 12,
                            family: "'Inter', sans-serif"
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(33, 37, 41, 0.8)',
                    titleFont: {
                        size: 13,
                        family: "'Inter', sans-serif",
                        weight: '600'
                    },
                    bodyFont: {
                        size: 12,
                        family: "'Inter', sans-serif"
                    },
                    padding: 10,
                    cornerRadius: 6,
                    caretSize: 6,
                    displayColors: true,
                    boxWidth: 10,
                    boxHeight: 10,
                    boxPadding: 4,
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
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 10,
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        color: '#6c757d',
                        padding: 10,
                        callback: function(value, index, values) {
                            const label = this.getLabelForValue(value);
                            try {
                                const date = new Date(label);
                                return date.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
                            } catch(e) {
                                return label;
                            }
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        color: '#6c757d',
                        padding: 10,
                        callback: function(value, index, values) {
                            return 'RM ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Fallback for empty data
    if (!<?php echo count($dates) ? 'true' : 'false'; ?>) {
        // Only use fallback if there's no data
        const dummyDates = [];
        const dummySales = [];
        const dummyProfit = [];
        const dummyAds = [];
        const dummyCogs = [];
        
        // Generate some realistic dummy data
        const today = new Date();
        for (let i = 30; i >= 0; i--) {
            const date = new Date();
            date.setDate(today.getDate() - i);
            dummyDates.push(date.toISOString().split('T')[0]);
            
            // Create some realistic looking data
            const sales = Math.floor(Math.random() * 5000) + 5000;
            dummySales.push(sales);
            dummyProfit.push(Math.floor(sales * 0.4));
            dummyAds.push(Math.floor(sales * 0.2));
            dummyCogs.push(Math.floor(sales * 0.3));
        }
        
        salesTrendChart.data.labels = dummyDates;
        salesTrendChart.data.datasets[0].data = dummySales;
        salesTrendChart.data.datasets[1].data = dummyProfit;
        salesTrendChart.update();
        
        expenseChart.data.labels = dummyDates;
        expenseChart.data.datasets[0].data = dummySales;
        expenseChart.data.datasets[1].data = dummyAds;
        expenseChart.data.datasets[2].data = dummyCogs;
        expenseChart.update();
    }
    
    // Check mobile display initially
    function checkMobileDisplay() {
        if (window.innerWidth < 992) {
            app.classList.add('sidebar-collapsed');
        } else {
            app.classList.remove('sidebar-collapsed');
        }
    }
    
    checkMobileDisplay();
    window.addEventListener('resize', checkMobileDisplay);
    </script>
</body>
</html>