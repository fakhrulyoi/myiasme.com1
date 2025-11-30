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

// Get date parameters
$period = isset($_GET['period']) ? $_GET['period'] : '30';
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-$period days"));

// Get team metrics
$sql_team_metrics = "SELECT 
    t.team_name,
    t.team_id,
    SUM(p.sales) as team_sales,
    SUM(p.profit) as team_profit,
    SUM(p.ads_spend) as team_ads_spend,
    SUM(p.item_cost) as team_cogs,
    SUM(p.cod) as team_shipping,
    COUNT(DISTINCT p.product_name) as product_count,
    SUM(p.unit_sold) as units_sold,
    SUM(p.purchase) as orders_count
FROM teams t
LEFT JOIN products p ON t.team_id = p.team_id AND p.created_at BETWEEN ? AND ?
GROUP BY t.team_id
ORDER BY team_sales DESC";

$stmt_team_metrics = $dbconn->prepare($sql_team_metrics);
$stmt_team_metrics->bind_param("ss", $start_date, $end_date);
$stmt_team_metrics->execute();
$result_team_metrics = $stmt_team_metrics->get_result();

$team_metrics = [];
// Get previous period for growth calculation
$prev_end_date = date('Y-m-d', strtotime("-$period days"));
$prev_start_date = date('Y-m-d', strtotime("-" . ($period * 2) . " days"));

while ($team = $result_team_metrics->fetch_assoc()) {
    // Calculate profit margin and ROI
    $team['profit_margin'] = $team['team_sales'] > 0 ? ($team['team_profit'] / $team['team_sales']) * 100 : 0;
    $team['roi'] = $team['team_ads_spend'] > 0 ? ($team['team_profit'] / $team['team_ads_spend']) * 100 : 0;
    
    // Get previous period data for this team
    $sql_prev = "SELECT 
        SUM(sales) as prev_sales,
        SUM(profit) as prev_profit
    FROM products 
    WHERE team_id = ? AND created_at BETWEEN ? AND ?";
    
    $stmt_prev = $dbconn->prepare($sql_prev);
    $stmt_prev->bind_param("iss", $team['team_id'], $prev_start_date, $prev_end_date);
    $stmt_prev->execute();
    $prev_data = $stmt_prev->get_result()->fetch_assoc();
    
    // Calculate growth
    if ($prev_data && $prev_data['prev_sales'] > 0) {
        $team['growth'] = (($team['team_sales'] - $prev_data['prev_sales']) / $prev_data['prev_sales']) * 100;
    } else {
        $team['growth'] = 0; // No previous sales or no data
    }
    
    $team_metrics[] = $team;
}

// Get top products by team
$sql_top_by_team = "SELECT 
    team_id,
    product_name,
    SUM(unit_sold) as total_sold,
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    SUM(profit)/SUM(sales)*100 as profit_margin
FROM products
WHERE created_at BETWEEN ? AND ?
GROUP BY team_id, product_name
ORDER BY team_id, total_profit DESC";

$stmt_top_by_team = $dbconn->prepare($sql_top_by_team);
$stmt_top_by_team->bind_param("ss", $start_date, $end_date);
$stmt_top_by_team->execute();
$result_top_by_team = $stmt_top_by_team->get_result();

$top_products_by_team = [];
while ($product = $result_top_by_team->fetch_assoc()) {
    if (!isset($top_products_by_team[$product['team_id']])) {
        $top_products_by_team[$product['team_id']] = [];
    }
    
    // Only store the top 5 products per team
    if (count($top_products_by_team[$product['team_id']]) < 5) {
        $top_products_by_team[$product['team_id']][] = $product;
    }
}

// Prepare response
$response = [
    'period' => $period,
    'team_metrics' => $team_metrics,
    'top_products_by_team' => $top_products_by_team
];

echo json_encode($response);
?>