<?php
// API endpoint: get_product_performance.php
// Returns sales data by top products for a specific time range

require_once '../auth.php';
require_once '../dbconn_productProfit.php';

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Get parameters
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$team_id = isset($_GET['team_id']) ? $_GET['team_id'] : 'all';

// Validate days
if ($days <= 0 || $days > 3650) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid time range']);
    exit();
}

// Calculate date range
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-$days days"));

// SQL query for team filter
$team_filter = "";
if ($team_id !== 'all') {
    $team_id = $dbconn->real_escape_string($team_id);
    $team_filter = "AND team_id = '$team_id'";
}

// Prepare SQL query for product data
// Get top 6 products by sales
$product_query = "
    SELECT 
        product_name,
        sales
    FROM 
        products
    WHERE 
        created_at BETWEEN '$start_date' AND '$end_date'
        $team_filter
    ORDER BY 
        sales DESC
    LIMIT 6
";

// Execute query
$product_result = $dbconn->query($product_query);

if (!$product_result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $dbconn->error]);
    exit();
}

// Process product data
$products = [];
$sales_values = [];

while ($row = $product_result->fetch_assoc()) {
    $products[] = $row['product_name'];
    $sales_values[] = round($row['sales'], 2);
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'labels' => $products,
        'values' => $sales_values
    ]
]);