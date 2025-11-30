<?php
// API endpoint: get_monthly_performance.php
// Returns monthly sales and profit data for a specific year and team

require_once '../auth.php';
require_once '../dbconn_productProfit.php';

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Get parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$team_id = isset($_GET['team_id']) ? $_GET['team_id'] : 'all';

// Validate year
if ($year < 2000 || $year > 2100) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid year']);
    exit();
}

// SQL query for team filter
$team_filter = "";
if ($team_id !== 'all') {
    $team_id = $dbconn->real_escape_string($team_id);
    $team_filter = "AND team_id = '$team_id'";
}

// Prepare SQL query for monthly data
// Using products table and created_at instead of sales table and date
$monthly_query = "
    SELECT 
        MONTH(created_at) as month,
        SUM(sales) as sales,
        SUM(profit) as profit
    FROM 
        products
    WHERE 
        YEAR(created_at) = $year
        $team_filter
    GROUP BY 
        MONTH(created_at)
    ORDER BY 
        month
";

// Execute query
$monthly_result = $dbconn->query($monthly_query);

if (!$monthly_result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $dbconn->error]);
    exit();
}

// Initialize arrays for all months
$month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$sales_data = array_fill(0, 12, 0);
$profit_data = array_fill(0, 12, 0);

// Fill in the data we have
while ($row = $monthly_result->fetch_assoc()) {
    $month_index = (int)$row['month'] - 1; // Convert to 0-based index
    $sales_data[$month_index] = round($row['sales'], 2);
    $profit_data[$month_index] = round($row['profit'], 2);
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'labels' => $month_names,
        'sales' => $sales_data,
        'profit' => $profit_data
    ]
]);