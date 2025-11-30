<?php
// API endpoint: get_category_performance.php
// Returns sales data by product category for a specific time range

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
    $team_filter = "AND p.team_id = '$team_id'";
}

// Prepare SQL query for category data
$category_query = "
    SELECT 
        p.category as category,
        SUM(s.total_amount) as sales
    FROM 
        sales s
    JOIN 
        products p ON s.product_id = p.id
    WHERE 
        s.date BETWEEN '$start_date' AND '$end_date'
        $team_filter
    GROUP BY 
        p.category
    ORDER BY 
        sales DESC
";

// Execute query
$category_result = $dbconn->query($category_query);

if (!$category_result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $dbconn->error]);
    exit();
}

// Process category data
$categories = [];
$sales_values = [];

while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row['category'];
    $sales_values[] = round($row['sales'], 2);
}

// If we have more than 6 categories, combine the rest into "Other"
if (count($categories) > 6) {
    $main_categories = array_slice($categories, 0, 5);
    $main_values = array_slice($sales_values, 0, 5);
    
    $other_sum = array_sum(array_slice($sales_values, 5));
    
    $main_categories[] = 'Other';
    $main_values[] = round($other_sum, 2);
    
    $categories = $main_categories;
    $sales_values = $main_values;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'labels' => $categories,
        'values' => $sales_values
    ]
]);