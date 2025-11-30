<?php
require 'dbconn_productProfit.php';
session_start(); // Start session to get logged-in team_id

$month = isset($_GET['month']) ? $_GET['month'] : '';
$team_id = isset($_SESSION['team_id']) ? intval($_SESSION['team_id']) : 0;

if (!$month || $team_id == 0) {
    echo json_encode([
        'error' => 'Missing month or team ID', 
        'details' => [
            'month' => $month, 
            'team_id' => $team_id
        ]
    ]);
    exit;
}

// More detailed error logging
try {
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS day, 
                   SUM(sales) AS total_sales, 
                   SUM(profit) AS total_profit 
            FROM products 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
              AND team_id = ?
            GROUP BY day 
            ORDER BY day ASC";

    // Prepare statement with error checking
    $stmt = $dbconn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $dbconn->error);
    }

    $stmt->bind_param("si", $month, $team_id);
    $stmt->execute();

    // Add error checking for execution
    if ($stmt->errno) {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    $data = [
        'days' => [],
        'sales' => [],
        'profits' => [],
        'debug' => [
            'month' => $month,
            'team_id' => $team_id,
            'row_count' => $result->num_rows
        ]
    ];

    while ($row = $result->fetch_assoc()) {
        $data['days'][] = $row['day'];
        $data['sales'][] = $row['total_sales'];
        $data['profits'][] = $row['total_profit'];
    }

    header('Content-Type: application/json');
    echo json_encode($data);
} catch (Exception $e) {
    // Log the error (ideally to a file in production)
    error_log($e->getMessage());
    
    echo json_encode([
        'error' => 'Database query failed',
        'message' => $e->getMessage()
    ]);
}
?>