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

// Get Winning DNA (top performing products)
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
LIMIT 50";

$stmt_winning_dna = $dbconn->prepare($sql_winning_dna);
$stmt_winning_dna->bind_param("ss", $start_date, $end_date);
$stmt_winning_dna->execute();
$winning_dna = $stmt_winning_dna->get_result();

// Get team names for mapping
$teams_sql = "SELECT team_id, team_name FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_sql);
$team_map = [];
while($team = $teams_result->fetch_assoc()) {
    $team_map[$team['team_id']] = $team['team_name'];
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="winning_dna_' . $start_date . '_to_' . $end_date . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Product',
    'Sales (RM)',
    'Profit (RM)',
    'Ads Spend (RM)',
    'ROI %',
    'Margin %',
    'Units Sold',
    'Team'
]);

// Add data rows
while ($product = $winning_dna->fetch_assoc()) {
    fputcsv($output, [
        $product['product_name'],
        number_format($product['total_sales'], 2, '.', ''),
        number_format($product['total_profit'], 2, '.', ''),
        number_format($product['total_ads_spend'], 2, '.', ''),
        number_format($product['roi'], 1, '.', ''),
        number_format($product['profit_margin'], 1, '.', ''),
        $product['total_sold'],
        isset($team_map[$product['team_id']]) ? $team_map[$product['team_id']] : 'Unknown'
    ]);
}

fclose($output);
exit;
?>