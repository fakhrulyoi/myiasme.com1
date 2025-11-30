<?php
require 'super_auth.php';
require 'dbconn_productProfit.php';

// Ensure only super admin can access
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

// Get time period for filtering
$time_period = isset($_GET['time_period']) ? $_GET['time_period'] : 'last_30_days';

// Set date range based on time period
$end_date = date('Y-m-d');
switch ($time_period) {
    case 'last_7_days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_display = 'Last 7 Days';
        break;
    case 'last_90_days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $period_display = 'Last 90 Days';
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date = date('Y-m-t', strtotime('last day of last month'));
        $period_display = 'Last Month';
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $period_display = 'This Month';
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $period_display = 'This Year';
        break;
    case 'last_year':
        $start_date = date('Y-01-01', strtotime('-1 year'));
        $end_date = date('Y-12-31', strtotime('-1 year'));
        $period_display = 'Last Year';
        break;
    default: // last_30_days
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_display = 'Last 30 Days';
        break;
}

// Get team performance data 
$sql_teams = "SELECT 
    t.team_id,
    t.team_name,
    SUM(p.sales) as total_sales,
    SUM(p.profit) as total_profit,
    SUM(p.ads_spend) as total_ads_spend,
    SUM(p.cod) as total_shipping,
    SUM(p.item_cost) as total_cogs,
    COUNT(DISTINCT p.product_name) as total_products,
    (SUM(p.profit) / NULLIF(SUM(p.sales), 0)) * 100 as profit_margin
FROM teams t
LEFT JOIN products p ON t.team_id = p.team_id AND p.created_at BETWEEN ? AND ?
GROUP BY t.team_id
ORDER BY total_sales DESC";

$stmt_teams = $dbconn->prepare($sql_teams);
$stmt_teams->bind_param("ss", $start_date, $end_date);
$stmt_teams->execute();
$teams_result = $stmt_teams->get_result();

// Calculate growth (needs previous period data)
$prev_start_date = date('Y-m-d', strtotime($start_date . ' -' . (strtotime($end_date) - strtotime($start_date)) . ' seconds'));
$prev_end_date = date('Y-m-d', strtotime($end_date . ' -' . (strtotime($end_date) - strtotime($start_date)) . ' seconds'));

$sql_prev = "SELECT 
    team_id,
    SUM(sales) as prev_sales
FROM products
WHERE created_at BETWEEN ? AND ?
GROUP BY team_id";

$stmt_prev = $dbconn->prepare($sql_prev);
$stmt_prev->bind_param("ss", $prev_start_date, $prev_end_date);
$stmt_prev->execute();
$prev_result = $stmt_prev->get_result();

$prev_sales = [];
while ($row = $prev_result->fetch_assoc()) {
    $prev_sales[$row['team_id']] = $row['prev_sales'];
}

// Get top winning products for each team
$sql_top_products = "SELECT 
    t.team_id,
    p.product_name,
    SUM(p.profit) as total_profit,
    SUM(p.unit_sold) as units_sold,
    SUM(p.profit)/SUM(p.sales)*100 as profit_margin
FROM teams t
JOIN products p ON t.team_id = p.team_id
WHERE p.created_at BETWEEN ? AND ?
GROUP BY t.team_id, p.product_name
ORDER BY t.team_id, total_profit DESC";

$stmt_top_products = $dbconn->prepare($sql_top_products);
$stmt_top_products->bind_param("ss", $start_date, $end_date);
$stmt_top_products->execute();
$top_products_result = $stmt_top_products->get_result();

$top_products = [];
$current_team = 0;
$count = 0;

while ($product = $top_products_result->fetch_assoc()) {
    if ($current_team != $product['team_id']) {
        $current_team = $product['team_id'];
        $count = 0;
    }
    
    if ($count < 3) {
        $top_products[$product['team_id']][] = $product;
        $count++;
    }
}

// Prepare data for chart
$chart_data = [
    'labels' => [],
    'sales' => [],
    'profit' => [],
    'ads_spend' => [],
    'shipping' => [],
    'cogs' => []
];

$teams_data = [];  // Store team data for display in cards

// Reset the result pointer
$teams_result->data_seek(0);

while ($team = $teams_result->fetch_assoc()) {
    // Add to chart data
$chart_data['sales'][] = $team['total_sales'] !== null ? round($team['total_sales'], 2) : 0;
$chart_data['profit'][] = $team['total_profit'] !== null ? round($team['total_profit'], 2) : 0;
$chart_data['ads_spend'][] = $team['total_ads_spend'] !== null ? round($team['total_ads_spend'], 2) : 0;
$chart_data['shipping'][] = $team['total_shipping'] !== null ? round($team['total_shipping'], 2) : 0;
$chart_data['cogs'][] = $team['total_cogs'] !== null ? round($team['total_cogs'], 2) : 0;
    
    // Calculate growth
    $growth = 0;
    if (isset($prev_sales[$team['team_id']]) && $prev_sales[$team['team_id']] > 0) {
        $growth = (($team['total_sales'] - $prev_sales[$team['team_id']]) / $prev_sales[$team['team_id']]) * 100;
    }
    
    // Store team data for display
    $teams_data[] = [
        'id' => $team['team_id'],
        'name' => $team['team_name'],
        'sales' => $team['total_sales'],
        'profit' => $team['total_profit'],
        'ads_spend' => $team['total_ads_spend'],
        'shipping' => $team['total_shipping'],
        'cogs' => $team['total_cogs'],
        'products' => $team['total_products'],
        'profit_margin' => $team['profit_margin'],
        'growth' => $growth,
        'top_products' => $top_products[$team['team_id']] ?? []
    ];
}

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
    <title>Team Performance Comparison - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* ===== Modern Dashboard Styles ===== */
    /* Team Cards (Part 2: Styling) */
.team-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.team-card {
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.team-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem;
    background: linear-gradient(to right, var(--primary-light), var(--primary));
    color: white;
    position: relative;
}

.team-title-area {
    display: flex;
    align-items: center;
}

.team-icon {
    width: 36px;
    height: 36px;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 1rem;
}

.team-name {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.team-growth {
    display: flex;
    align-items: center;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.875rem;
}

.team-growth.positive {
    background-color: rgba(46, 196, 182, 0.3);
}

.team-growth.negative {
    background-color: rgba(247, 37, 133, 0.3);
}

.team-growth i {
    margin-right: 0.35rem;
    font-size: 0.8rem;
}

.team-card-body {
    padding: 1.25rem;
}

/* KPI Row */
.team-kpi-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.team-kpi {
    background-color: var(--gray-light);
    padding: 1rem;
    border-radius: 0.5rem;
    text-align: center;
}

.team-kpi.sales {
    background-color: rgba(67, 97, 238, 0.1);
    border-left: 4px solid var(--primary);
}

.team-kpi.profit {
    background-color: rgba(46, 196, 182, 0.1);
    border-left: 4px solid var(--success);
}

.kpi-value {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: var(--dark);
}

.kpi-label {
    font-size: 0.825rem;
    color: var(--gray);
    font-weight: 500;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 1.25rem 0 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-light);
    color: var(--dark);
    display: flex;
    align-items: center;
}

.section-title i {
    margin-right: 0.5rem;
    color: var(--primary);
}
/* Team Cards (Part 3: Metrics and Expenses) */
/* Team Metrics */
.team-metrics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.metric-item {
    display: flex;
    align-items: center;
    background-color: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.5rem;
}

.metric-icon {
    width: 36px;
    height: 36px;
    background-color: rgba(67, 97, 238, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    color: var(--primary);
    font-size: 1rem;
}

.metric-details {
    flex: 1;
}

.metric-value {
    font-weight: 700;
    font-size: 1.125rem;
    color: var(--dark);
}

.metric-label {
    font-size: 0.75rem;
    color: var(--gray);
    margin-top: 0.125rem;
}

/* Expenses */
.expenses-container {
    margin-bottom: 1rem;
}

.expenses-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.expense-item {
    background-color: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.5rem;
    text-align: center;
    border-top: 3px solid transparent;
}

.expense-item:nth-child(1) {
    border-top-color: var(--secondary);
}

.expense-item:nth-child(2) {
    border-top-color: var(--warning);
}

.expense-item:nth-child(3) {
    border-top-color: var(--info);
}

.expense-label {
    font-size: 0.75rem;
    color: var(--gray);
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.expense-value {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--dark);
}

/* Responsive adjustments */
@media (max-width: 1400px) {
    .team-cards {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .team-cards {
        grid-template-columns: 1fr;
    }
    
    .team-kpi-row,
    .team-metrics,
    .expenses-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .expenses-grid {
        row-gap: 0.75rem;
    }
}

@media (max-width: 480px) {
    .team-metrics {
        grid-template-columns: 1fr;
    }
}
/* Team Cards (Part 4: Products Section) */
/* Products Section */
.products-container {
    margin-top: 1.25rem;
}

.products-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.product-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    border-left: 3px solid var(--success);
    transition: transform 0.2s ease;
}

.product-item:hover {
    transform: translateX(5px);
    background-color: rgba(46, 196, 182, 0.05);
}

.product-rank {
    width: 24px;
    height: 24px;
    background-color: var(--success);
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-right: 0.75rem;
}

.product-item:nth-child(1) .product-rank {
    background-color: #ffd700; /* Gold */
}

.product-item:nth-child(2) .product-rank {
    background-color: #c0c0c0; /* Silver */
}

.product-item:nth-child(3) .product-rank {
    background-color: #cd7f32; /* Bronze */
}

.product-details {
    flex: 1;
}

.product-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
    color: var(--dark);
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.75rem;
    color: var(--gray-dark);
}

.product-stats span {
    display: flex;
    align-items: center;
}

.product-stats i {
    margin-right: 0.25rem;
    font-size: 0.7rem;
}

.no-products {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    color: var(--gray);
    text-align: center;
}

.no-products i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.no-products p {
    margin: 0;
    font-size: 0.9rem;
}

/* Animations */
.fade-in {
    animation: fadeIn 0.5s ease forwards;
    opacity: 0;
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

/* Additional hover effects */
.team-card:hover .team-kpi,
.team-card:hover .metric-item,
.team-card:hover .expense-item {
    transition: transform 0.2s ease;
}

.team-card:hover .team-kpi:hover,
.team-card:hover .metric-item:hover,
.team-card:hover .expense-item:hover {
    transform: translateY(-3px);
}

/* Make scrollbar more modern */
.team-cards {
    scrollbar-width: thin;
    scrollbar-color: var(--gray-light) transparent;
}

.team-cards::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.team-cards::-webkit-scrollbar-track {
    background: transparent;
}

.team-cards::-webkit-scrollbar-thumb {
    background-color: var(--gray-light);
    border-radius: 3px;
}

.team-cards::-webkit-scrollbar-thumb:hover {
    background-color: var(--gray);
}
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

/* Dark Mode Variables */
[data-theme="dark"] {
    --primary: #4361ee;
    --primary-light: #4895ef;
    --primary-dark: #3f37c9;
    --secondary: #f72585;
    --secondary-light: #ff4d6d;
    --success: #2ec4b6;
    --info: #4cc9f0;
    --warning: #fca311;
    --danger: #e63946;
    --dark: #f8f9fa;
    --light: #212529;
    --gray: #adb5bd;
    --gray-light: #343a40;
    --gray-dark: #e9ecef;
    --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.25);
}

/* Dark Mode Body */
[data-theme="dark"] body {
    background-color: #1a1d21;
    color: var(--dark);
}

/* Dark Mode Sidebar */
[data-theme="dark"] .sidebar {
    background: #242830;
    border-right: 1px solid #343a40;
}

[data-theme="dark"] .sidebar .logo-text,
[data-theme="dark"] .sidebar .user-name {
    color: #f8f9fa;
}

[data-theme="dark"] .sidebar .user-role {
    color: #adb5bd;
}

[data-theme="dark"] .logo-container,
[data-theme="dark"] .user-info,
[data-theme="dark"] .sidebar-footer {
    border-color: #343a40;
}

[data-theme="dark"] .user-info {
    background-color: rgba(67, 97, 238, 0.15);
}

[data-theme="dark"] .nav-link {
    color: #adb5bd;
}

[data-theme="dark"] .nav-link:hover {
    background-color: rgba(67, 97, 238, 0.2);
    color: #4895ef;
}

/* Dark Mode Page Header and Content */
[data-theme="dark"] .page-header,
[data-theme="dark"] .filter-section,
[data-theme="dark"] .chart-wrapper,
[data-theme="dark"] .team-card {
    background-color: #242830;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

[data-theme="dark"] .team-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] .page-title,
[data-theme="dark"] .filter-title,
[data-theme="dark"] .chart-title,
[data-theme="dark"] h1, [data-theme="dark"] h2, 
[data-theme="dark"] h3, [data-theme="dark"] h4, 
[data-theme="dark"] h5, [data-theme="dark"] h6 {
    color: #f8f9fa;
}

/* Dark Mode Card Elements */
[data-theme="dark"] .metric-item,
[data-theme="dark"] .expense-item,
[data-theme="dark"] .product-item,
[data-theme="dark"] .no-products,
[data-theme="dark"] .filter-btn,
[data-theme="dark"] .btn-light {
    background-color: #343a40;
    color: #e9ecef;
    border-color: #495057;
}

[data-theme="dark"] .metric-icon {
    background-color: rgba(67, 97, 238, 0.2);
}

[data-theme="dark"] .team-kpi.sales {
    background-color: rgba(67, 97, 238, 0.2);
}

[data-theme="dark"] .team-kpi.profit {
    background-color: rgba(46, 196, 182, 0.2);
}

[data-theme="dark"] .product-item:hover {
    background-color: rgba(46, 196, 182, 0.15);
}

[data-theme="dark"] .kpi-value,
[data-theme="dark"] .metric-value,
[data-theme="dark"] .expense-value, 
[data-theme="dark"] .product-name {
    color: #f8f9fa;
}

[data-theme="dark"] .kpi-label,
[data-theme="dark"] .metric-label,
[data-theme="dark"] .expense-label,
[data-theme="dark"] .product-stats,
[data-theme="dark"] .filter-info-text,
[data-theme="dark"] footer {
    color: #adb5bd;
}

/* Dark Mode Toggle Animation */
@keyframes darkModeIn {
    from {
        transform: scale(0.8);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

[data-theme="dark"] .main-content {
    animation: darkModeIn 0.3s forwards;
}

/* Dark Mode Chart */
[data-theme="dark"] .chart-container canvas {
    filter: brightness(0.9);
}

/* Dark Mode for Section Title */
[data-theme="dark"] .section-title {
    border-color: #343a40;
}
</style>
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
                    <div class="user-role"><?php echo isset($is_super_admin) && $is_super_admin ? 'Super Admin' : (isset($is_admin) && $is_admin ? 'Admin' : 'Team Member'); ?></div>
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
                    <i class="fas fa-users"></i> Team Performance Comparison
                </h1>
                
                <div class="header-actions">
    <button class="btn btn-light" id="darkModeToggle" title="Toggle Dark Mode">
        <i class="fas fa-moon"></i>
    </button>
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
                    <h3 class="filter-title">Filter Teams</h3>
                </div>
                
                <div class="filter-buttons">
                    <a href="?time_period=last_30_days" class="filter-btn <?php echo $time_period == 'last_30_days' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Last 30 Days
                    </a>
                    <a href="?time_period=last_7_days" class="filter-btn <?php echo $time_period == 'last_7_days' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week"></i> Last 7 Days
                    </a>
                    <a href="?time_period=last_90_days" class="filter-btn <?php echo $time_period == 'last_90_days' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Last 90 Days
                    </a>
                    <a href="?time_period=this_month" class="filter-btn <?php echo $time_period == 'this_month' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> This Month
                    </a>
                    <a href="?time_period=last_month" class="filter-btn <?php echo $time_period == 'last_month' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Last Month
                    </a>
                    <a href="?time_period=this_year" class="filter-btn <?php echo $time_period == 'this_year' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> This Year
                    </a>
                    <a href="?time_period=last_year" class="filter-btn <?php echo $time_period == 'last_year' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> Last Year
                    </a>
                </div>
                
                <!-- Active Filter Info -->
                <div class="filter-active-info">
                    <span class="filter-info-icon">
                        <i class="fas fa-info-circle"></i>
                    </span>
                    <p class="filter-info-text">
                        Showing team performance for <strong><?php echo $period_display; ?></strong> (<strong><?php echo date('d M, Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('d M, Y', strtotime($end_date)); ?></strong>)
                    </p>
                </div>
            </section>
            
            <!-- Chart Container -->
            <section class="chart-wrapper fade-in" style="animation-delay: 0.2s;">
                <div class="chart-title-container">
                    <div class="chart-title-wrapper">
                        <span class="chart-icon">
                            <i class="fas fa-chart-bar"></i>
                        </span>
                        <h3 class="chart-title">Team Performance Comparison</h3>
                    </div>
                    <div class="chart-actions">
                        <button class="btn btn-light btn-icon" title="Download Chart">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="teamComparisonChart" height="350"></canvas>
                </div>
            </section>
            
            <!-- Team Cards -->
         <!-- Team Cards (Part 1: Structure) -->
<div class="team-cards fade-in" style="animation-delay: 0.3s;">
    <?php foreach ($teams_data as $team): ?>
    <div class="team-card">
        <div class="team-card-header">
            <div class="team-title-area">
                <div class="team-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h3 class="team-name"><?php echo $team['name']; ?></h3>
            </div>
            <div class="team-growth <?php echo $team['growth'] >= 0 ? 'positive' : 'negative'; ?>">
                <i class="fas <?php echo $team['growth'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                <span><?php echo safe_number_format(abs($team['growth']), 1); ?>%</span>
            </div>
        </div>
        
        <div class="team-card-body">
            <!-- Primary KPIs -->
            <div class="team-kpi-row">
                <div class="team-kpi sales">
                    <div class="kpi-value">RM <?php echo safe_number_format($team['sales'], 2); ?></div>
                    <div class="kpi-label">Total Sales</div>
                </div>
                <div class="team-kpi profit">
                    <div class="kpi-value">RM <?php echo safe_number_format($team['profit'], 2); ?></div>
                    <div class="kpi-label">Total Profit</div>
                </div>
            </div>
            
            <!-- Secondary metrics -->
            <div class="team-metrics">
                <div class="metric-item">
                    <div class="metric-icon"><i class="fas fa-box"></i></div>
                    <div class="metric-details">
                        <div class="metric-value"><?php echo safe_number_format($team['products']); ?></div>
                        <div class="metric-label">Products</div>
                    </div>
                </div>
                <div class="metric-item">
                    <div class="metric-icon"><i class="fas fa-percentage"></i></div>
                    <div class="metric-details">
                        <div class="metric-value"><?php echo safe_number_format($team['profit_margin'], 1); ?>%</div>
                        <div class="metric-label">Profit Margin</div>
                    </div>
                </div>
            </div>
            
            <!-- Expenses Section -->
            <div class="expenses-container">
                <h4 class="section-title"><i class="fas fa-receipt"></i> Expenses</h4>
                <div class="expenses-grid">
                    <div class="expense-item">
                        <div class="expense-label">Ads</div>
                        <div class="expense-value">RM <?php echo safe_number_format($team['ads_spend'], 2); ?></div>
                    </div>
                    <div class="expense-item">
                        <div class="expense-label">Shipping</div>
                        <div class="expense-value">RM <?php echo safe_number_format($team['shipping'], 2); ?></div>
                    </div>
                    <div class="expense-item">
                        <div class="expense-label">COGS</div>
                        <div class="expense-value">RM <?php echo safe_number_format($team['cogs'], 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Products Section -->
            <div class="products-container">
                <h4 class="section-title"><i class="fas fa-trophy"></i> Top Products</h4>
                <?php if (!empty($team['top_products'])): ?>
                    <div class="products-list">
                        <?php foreach ($team['top_products'] as $index => $product): ?>
                        <div class="product-item">
                            <div class="product-rank">#<?php echo $index + 1; ?></div>
                            <div class="product-details">
                                <div class="product-name" title="<?php echo $product['product_name']; ?>"><?php echo $product['product_name']; ?></div>
                                <div class="product-stats">
                                    <span><i class="fas fa-box"></i> <?php echo safe_number_format($product['units_sold']); ?></span>
                                    <span><i class="fas fa-dollar-sign"></i> RM <?php echo safe_number_format($product['total_profit'], 2); ?></span>
                                    <span><i class="fas fa-percentage"></i> <?php echo safe_number_format($product['profit_margin'], 1); ?>%</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-products">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>No products found for this time period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
            
            <!-- Footer -->
            <footer style="text-align: center; padding: 1.5rem 0; color: var(--gray); margin-top: 1.5rem; font-size: 0.875rem;">
                <p>MYIASME &copy; <?php echo date('Y'); ?>. All rights reserved.</p>
            </footer>
        </main>
    </div>

    <!-- JavaScript for Chart and Interaction -->
    <script>
    // DOM Elements
    const app = document.getElementById('app');
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('toggleSidebar');
    const toggleSidebarMobile = document.getElementById('toggleSidebarMobile');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
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
    
    // Team Comparison Chart
    const teamComparisonCtx = document.getElementById('teamComparisonChart').getContext('2d');
    const teamComparisonChart = new Chart(teamComparisonCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_data['labels']); ?>,
            datasets: [
                {
                    label: 'Total Sales (RM)',
                    data: <?php echo json_encode($chart_data['sales']); ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.8)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Total Profit (RM)',
                    data: <?php echo json_encode($chart_data['profit']); ?>,
                    backgroundColor: 'rgba(46, 196, 182, 0.8)',
                    borderColor: 'rgba(46, 196, 182, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Ads Spend (RM)',
                    data: <?php echo json_encode($chart_data['ads_spend']); ?>,
                    backgroundColor: 'rgba(247, 37, 133, 0.8)',
                    borderColor: 'rgba(247, 37, 133, 1)',
                    borderWidth: 1
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
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        color: '#6c757d'
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
                        callback: function(value) {
                            return 'RM ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
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
    const darkModeToggle = document.getElementById('darkModeToggle');
const body = document.body;

// Check for saved theme preference or default to light
const savedTheme = localStorage.getItem('theme') || 'light';
body.setAttribute('data-theme', savedTheme);

// Update button icon based on current theme
updateDarkModeIcon(savedTheme);

// Toggle dark mode
darkModeToggle.addEventListener('click', () => {
    const currentTheme = body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update charts for better visibility in dark mode
    if (teamComparisonChart) {
        if (newTheme === 'dark') {
            teamComparisonChart.options.scales.x.ticks.color = '#adb5bd';
            teamComparisonChart.options.scales.y.ticks.color = '#adb5bd';
            teamComparisonChart.options.scales.y.grid.color = 'rgba(255, 255, 255, 0.05)';
        } else {
            teamComparisonChart.options.scales.x.ticks.color = '#6c757d';
            teamComparisonChart.options.scales.y.ticks.color = '#6c757d';
            teamComparisonChart.options.scales.y.grid.color = 'rgba(0, 0, 0, 0.05)';
        }
        teamComparisonChart.update();
    }
    
    updateDarkModeIcon(newTheme);
});

// Function to update dark mode icon
function updateDarkModeIcon(theme) {
    const icon = darkModeToggle.querySelector('i');
    if (theme === 'dark') {
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
        darkModeToggle.title = 'Switch to Light Mode';
    } else {
        icon.classList.remove('fa-sun');
        icon.classList.add('fa-moon');
        darkModeToggle.title = 'Switch to Dark Mode';
    }
}
    </script>
</body>
</html>