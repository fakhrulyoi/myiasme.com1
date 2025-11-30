<?php
require 'dbconn_productProfit.php';

if (isset($_GET['month'])) {
    $selectedMonth = $_GET['month'];

    // Fetch Sales, COGS, and Profit for the selected month
    $sql = "SELECT DATE(created_at) AS sales_date, SUM(sales) AS total_sales, SUM(item_cost + cod) AS total_cogs, SUM(profit) AS total_profit 
            FROM products 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
            GROUP BY sales_date 
            ORDER BY sales_date ASC";

    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("s", $selectedMonth);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["error" => "No data available for the selected month."]);
        exit();
    }

    $dates = [];
    $sales = [];
    $cogs = [];
    $profit = [];

    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['sales_date'];
        $sales[] = $row['total_sales'];
        $cogs[] = $row['total_cogs'];
        $profit[] = $row['total_profit'];
    }

    echo json_encode([
        "dates" => $dates,
        "sales" => $sales,
        "cogs" => $cogs,
        "profit" => $profit
    ]);

    exit();
}
?>
