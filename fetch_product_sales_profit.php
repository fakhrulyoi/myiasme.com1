<?php
// Include database connection
require 'dbconn_productProfit.php';

// Check if required parameters are set
if (!isset($_GET['date']) || !isset($_GET['team_id'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Get parameters
$selectedDate = $_GET['date'];
$team_id = intval($_GET['team_id']);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit();
}

// Prepare SQL query
$sql = "SELECT product_name, sales, profit 
        FROM products 
        WHERE DATE(created_at) = ? AND team_id = ? 
        ORDER BY sales DESC";

$stmt = $dbconn->prepare($sql);
$stmt->bind_param("si", $selectedDate, $team_id);
$stmt->execute();
$result = $stmt->get_result();

// Initialize arrays
$productNames = [];
$salesData = [];
$profitData = [];

// Fetch data
while ($row = $result->fetch_assoc()) {
    $productNames[] = $row['product_name'];
    $salesData[] = floatval($row['sales']);
    $profitData[] = floatval($row['profit']);
}

// Check if no products found
if (empty($productNames)) {
    echo json_encode([
        'error' => 'No products found for the selected date',
        'products' => [],
        'sales' => [],
        'profits' => []
    ]);
    exit();
}

// Prepare response
$response = [
    'products' => $productNames,
    'sales' => $salesData,
    'profits' => $profitData
];

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>