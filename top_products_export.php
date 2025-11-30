<?php
require 'super_auth.php';
require 'dbconn_productProfit.php';

// Ensure only super admin or admin can access
require_super_admin();

// Get date range
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Build SQL conditions
$team_condition = $selected_team > 0 ? "AND team_id = " . $selected_team : "";

// Get top selling products
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
LIMIT 50";

$stmt_top_products = $dbconn->prepare($sql_top_products);
$stmt_top_products->bind_param("ss", $start_date, $end_date);
$stmt_top_products->execute();
$top_products = $stmt_top_products->get_result();

// Get team names for mapping
$teams_sql = "SELECT team_id, team_name FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_sql);
$team_map = [];
while($team = $teams_result->fetch_assoc()) {
    $team_map[$team['team_id']] = $team['team_name'];
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="top_products_' . $start_date . '_to_' . $end_date . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Product',
    'Units Sold',
    'Total Sales (RM)',
    'Total Profit (RM)',
    'Ads Spend (RM)',
    'COGS (RM)',
    'Profit Margin',
    'Team'
]);

// Add data rows
while ($product = $top_products->fetch_assoc()) {
    fputcsv($output, [
        $product['product_name'],
        $product['total_sold'],
        number_format($product['total_sales'], 2, '.', ''),
        number_format($product['total_profit'], 2, '.', ''),
        number_format($product['ads_spend'], 2, '.', ''),
        number_format($product['cogs'], 2, '.', ''),
        number_format($product['profit_margin'], 1, '.', ''),
        isset($team_map[$product['team_id']]) ? $team_map[$product['team_id']] : 'Unknown'
    ]);
}

fclose($output);
exit;
?>