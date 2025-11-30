<?php
// Include necessary files
require 'super_auth.php';
require 'dbconn_productProfit.php';

// Ensure only super admin or admin can access
require_super_admin();

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Get date range for filtering
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get selected team for filtering (if any)
$selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Build SQL conditions
$team_condition = $selected_team > 0 ? "AND p.team_id = " . $selected_team : "";

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
$result_team_sales = $stmt_team_sales->get_result();
$team_sales = [];
while ($row = $result_team_sales->fetch_assoc()) {
    // Calculate profit margin and ROI
    $row['profit_margin'] = $row['team_sales'] > 0 ? ($row['team_profit'] / $row['team_sales']) * 100 : 0;
    $row['roi'] = $row['team_ads_spend'] > 0 ? ($row['team_profit'] / $row['team_ads_spend']) * 100 : 0;
    
    // For demonstration purposes, add a growth field (you'd replace this with actual calculation)
    // In a real scenario, you might compare current period to previous period
    $row['growth'] = $row['team_id'] == 1 ? -44.9 : 0; // Example: Team A has -44.9% growth
    
    $team_sales[] = $row;
}

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
$result_top_products = $stmt_top_products->get_result();
$top_products = [];
while ($row = $result_top_products->fetch_assoc()) {
    $top_products[] = $row;
}

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
$result_daily_sales = $stmt_daily_sales->get_result();
$daily_sales = [];
while ($row = $result_daily_sales->fetch_assoc()) {
    $daily_sales[] = $row;
}

// 5. Get Winning DNA (top performing products)
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
HAVING SUM(profit) > 0 AND SUM(ads_spend) > 0
ORDER BY roi DESC
LIMIT 15";

$stmt_winning_dna = $dbconn->prepare($sql_winning_dna);
$stmt_winning_dna->bind_param("ss", $start_date, $end_date);
$stmt_winning_dna->execute();
$result_winning_dna = $stmt_winning_dna->get_result();
$winning_dna = [];
while ($row = $result_winning_dna->fetch_assoc()) {
    $winning_dna[] = $row;
}

// Get all teams for team mapping
$teams_sql = "SELECT team_id, team_name FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_sql);
$teams = [];
while($team = $teams_result->fetch_assoc()) {
    $teams[] = $team;
}

// Prepare the final JSON response
$response = [
    'stats' => $stats,
    'team_sales' => $team_sales,
    'top_products' => $top_products,
    'daily_sales' => $daily_sales,
    'winning_dna' => $winning_dna,
    'teams' => $teams
];

// Send the response
echo json_encode($response);
?>