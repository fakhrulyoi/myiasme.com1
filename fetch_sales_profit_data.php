<?php
require 'dbconn_productProfit.php';

// Query untuk jumlah total sales dan profit berdasarkan product_name
$sql = "SELECT product_name, SUM(sales) AS total_sales, SUM(profit) AS total_profit FROM products GROUP BY product_name";
$result = $dbconn->query($sql);

$data = [
    'products' => [],
    'sales' => [],
    'profit' => []
];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data['products'][] = $row['product_name'];
        $data['sales'][] = $row['total_sales'];
        $data['profit'][] = $row['total_profit'];
    }
}

// Hantar data dalam format JSON
header('Content-Type: application/json');
echo json_encode($data);
?>
