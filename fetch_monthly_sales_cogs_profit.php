<?php
require 'dbconn_productProfit.php';
session_start(); // Start session to get logged-in team_id

if (isset($_GET['month'])) {
    $selectedMonth = $_GET['month'];
    $team_id = isset($_SESSION['team_id']) ? intval($_SESSION['team_id']) : 0;

    if ($team_id == 0) {
        echo json_encode(["error" => "No team ID found."]);
        exit();
    }

    // Fetch data for the selected month and team
    $sql = "SELECT DATE(created_at) AS sales_date, 
                   SUM(sales) AS total_sales, 
                   SUM(item_cost + cod) AS total_cogs, 
                   SUM(profit) AS total_profit 
            FROM products 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
              AND team_id = ?
            GROUP BY sales_date 
            ORDER BY sales_date ASC";

    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("si", $selectedMonth, $team_id);
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