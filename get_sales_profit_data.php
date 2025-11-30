<?php
require 'dbconn_productProfit.php';

// Get parameters from request
$product = isset($_GET['product']) ? $_GET['product'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Debugging: Log received parameters
file_put_contents("debug_log.txt", "Product: $product | Start Date: $start_date | End Date: $end_date\n", FILE_APPEND);

if (empty($product) || empty($start_date) || empty($end_date)) {
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

// Debugging: Check if the table actually contains this product
$check_sql = "SELECT COUNT(*) as count FROM product_profit.products WHERE product_name = ?";
$check_stmt = $dbconn->prepare($check_sql);
$check_stmt->bind_param("s", $product);
$check_stmt->execute();
$check_result = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($check_result['count'] == 0) {
    echo json_encode(["error" => "Product '$product' not found in database"]);
    exit;
}

// Fetch Sales & Profit Data
$sql = "SELECT DATE(created_at) as date, SUM(sales) as total_sales, SUM(profit) as total_profit 
        FROM product_profit.products 
        WHERE product_name = ? AND created_at BETWEEN ? AND ? 
        GROUP BY DATE(created_at) 
        ORDER BY created_at ASC";

$stmt = $dbconn->prepare($sql);
if (!$stmt) {
    echo json_encode(["error" => "SQL Error: " . $dbconn->error]);
    exit;
}

$stmt->bind_param("sss", $product, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$data = ["dates" => [], "sales" => [], "profit" => []];

while ($row = $result->fetch_assoc()) {
    $data["dates"][] = $row['date'];
    $data["sales"][] = $row['total_sales'];
    $data["profit"][] = $row['total_profit'];
}

$stmt->close();
$dbconn->close();

if (empty($data["dates"])) {
    echo json_encode(["error" => "No data found for this selection"]);
} else {
    echo json_encode($data);
}
?>
