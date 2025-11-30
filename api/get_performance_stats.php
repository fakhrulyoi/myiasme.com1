<?php
// API endpoint: get_performance_stats.php
// Returns summary statistics for date range and team

require_once '../auth.php';
require_once '../dbconn_productProfit.php';

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Get parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$team_id = isset($_GET['team_id']) ? $_GET['team_id'] : 'all';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// SQL query for team filter
$team_filter = "";
if ($team_id !== 'all') {
    $team_id = $dbconn->real_escape_string($team_id);
    $team_filter = "AND team_id = '$team_id'";
}

// Prepare SQL query for overall stats
// Using the products table instead of sales
$stats_query = "
    SELECT 
        SUM(sales) as total_sales,
        SUM(profit) as total_profit,
        COUNT(id) as total_products,
        SUM(unit_sold) as total_units
    FROM 
        products
    WHERE 
        created_at BETWEEN '$start_date' AND '$end_date'
        $team_filter
";

// Execute query
$stats_result = $dbconn->query($stats_query);

if (!$stats_result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $dbconn->error]);
    exit();
}

// Get stats data
$stats = $stats_result->fetch_assoc();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'total_sales' => round($stats['total_sales'], 2),
        'total_profit' => round($stats['total_profit'], 2),
        'total_products' => (int)$stats['total_products'],
        'total_units' => (int)$stats['total_units']
    ]
]);