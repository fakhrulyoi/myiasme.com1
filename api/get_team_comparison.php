<?php
// API endpoint: get_team_comparison.php
// Returns team performance comparison data for a specific time period

require_once '../auth.php';
require_once '../dbconn_productProfit.php';

// Only allow authenticated admin users
if (!isset($_SESSION['user_id']) || !$is_admin) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
    exit();
}

// Get parameters
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// Validate days
if ($days <= 0 || $days > 3650) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid time range']);
    exit();
}

// Calculate date range
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-$days days"));

// Check what column exists in the teams table
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}

// Determine the correct primary key and name column
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';
$team_name_col = in_array('team_name', $column_names) ? 'team_name' : 'name';

// Prepare SQL query for team data
// Using JOIN between teams and products tables
$team_query = "
    SELECT 
        t.$team_pk as team_id,
        t.$team_name_col as team_name,
        SUM(p.sales) as total_sales,
        SUM(p.profit) as total_profit,
        COUNT(p.id) as product_count
    FROM 
        teams t
    LEFT JOIN 
        products p ON t.$team_pk = p.team_id AND p.created_at BETWEEN '$start_date' AND '$end_date'
    GROUP BY 
        t.$team_pk, t.$team_name_col
    ORDER BY 
        total_sales DESC
";

// Execute query
$team_result = $dbconn->query($team_query);

if (!$team_result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $dbconn->error]);
    exit();
}

// Get previous period data for growth calculation
$prev_end_date = date('Y-m-d', strtotime("-$days days"));
$prev_start_date = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

$prev_query = "
    SELECT 
        t.$team_pk as team_id,
        SUM(p.sales) as prev_sales
    FROM 
        teams t
    LEFT JOIN 
        products p ON t.$team_pk = p.team_id AND p.created_at BETWEEN '$prev_start_date' AND '$prev_end_date'
    GROUP BY 
        t.$team_pk
";

$prev_result = $dbconn->query($prev_query);
$prev_data = [];

if ($prev_result) {
    while ($prev_row = $prev_result->fetch_assoc()) {
        $prev_data[$prev_row['team_id']] = $prev_row['prev_sales'];
    }
}

// Process team data
$team_names = [];
$sales_data = [];
$profit_data = [];
$team_stats = [];

while ($row = $team_result->fetch_assoc()) {
    $team_names[] = $row['team_name'];
    $sales_data[] = (float)round($row['total_sales'], 2);
    $profit_data[] = (float)round($row['total_profit'], 2);
    
    // Calculate profit margin
    $profit_margin = ($row['total_sales'] > 0) ? ($row['total_profit'] / $row['total_sales'] * 100) : 0;
    
    // Calculate growth
    $prev_sales = isset($prev_data[$row['team_id']]) ? $prev_data[$row['team_id']] : 0;
    $growth = ($prev_sales > 0) ? (($row['total_sales'] - $prev_sales) / $prev_sales * 100) : 0;
    
    $team_stats[] = [
        'name' => $row['team_name'],
        'sales' => (float)round($row['total_sales'], 2),
        'profit' => (float)round($row['total_profit'], 2),
        'products' => (int)$row['product_count'],
        'profitMargin' => (float)round($profit_margin, 1),
        'growth' => (float)round($growth, 1)
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'team_names' => $team_names,
        'sales' => $sales_data,
        'profit' => $profit_data,
        'team_stats' => $team_stats
    ]
]);