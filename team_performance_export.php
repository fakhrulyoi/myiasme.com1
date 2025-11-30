<?php
require 'super_auth.php';
require 'dbconn_productProfit.php';

// Ensure only super admin or admin can access
require_super_admin();

// Get date range
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get sales by team
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

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="team_performance_' . $start_date . '_to_' . $end_date . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Team', 
    'Total Sales (RM)', 
    'Total Profit (RM)', 
    'Margin %', 
    'Ads Spend (RM)', 
    'ROI %', 
    'COGS (RM)', 
    'Shipping (RM)', 
    'Orders', 
    'Units Sold'
]);

// Add data rows
while ($team = $team_sales->fetch_assoc()) {
    $margin = ($team['team_sales'] > 0) ? ($team['team_profit'] / $team['team_sales']) * 100 : 0;
    $roi = ($team['team_ads_spend'] > 0) ? ($team['team_profit'] / $team['team_ads_spend']) * 100 : 0;
    
    fputcsv($output, [
        $team['team_name'],
        number_format($team['team_sales'] ?? 0, 2, '.', ''),
        number_format($team['team_profit'] ?? 0, 2, '.', ''),
        number_format($margin, 1, '.', ''),
        number_format($team['team_ads_spend'] ?? 0, 2, '.', ''),
        number_format($roi, 1, '.', ''),
        number_format($team['team_cogs'] ?? 0, 2, '.', ''),
        number_format($team['team_shipping'] ?? 0, 2, '.', ''),
        $team['orders_count'] ?? 0,
        $team['units_sold'] ?? 0
    ]);
}

fclose($output);
exit;
?>