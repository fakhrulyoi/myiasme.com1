<?php
require 'dbconn_productProfit.php';
session_start(); // Start session to get team_id

// Check if user is logged in
$team_id = isset($_SESSION['team_id']) ? intval($_SESSION['team_id']) : 0;
if ($team_id == 0) {
    die("Error: Please log in to access this feature.");
}

if (isset($_GET['date'])) {
    $selectedDate = $_GET['date'];

    // Fetch data for the selected date and specific team
    $sql = "SELECT * FROM products 
            WHERE DATE(created_at) = ? 
            AND team_id = ? 
            ORDER BY created_at ASC";
    
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("si", $selectedDate, $team_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("No data available for the selected date and team.");
    }

    // Sanitize filename to prevent potential security issues
    $safeFileName = preg_replace("/[^a-zA-Z0-9_-]/", "", $selectedDate);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Sales_Profit_' . $safeFileName . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add CSV Header Row
    fputcsv($output, ['ID', 'Product Name', 'Ads Spend (RM)', 'Purchase', 'CPP (RM)', 'Units Sold', 'Item Cost (RM)', 'COD (RM)', 'COGS (RM)', 'Sales (RM)', 'Profit (RM)', 'Date']);

    // Fill Data
    while ($row = $result->fetch_assoc()) {
        $cpp = ($row['purchase'] > 0) ? ($row['ads_spend'] / $row['purchase']) : 0;
        $cogs = $row['item_cost'] + $row['cod'];

        fputcsv($output, [
            $row['id'],
            htmlspecialchars($row['product_name']),
            number_format($row['ads_spend'], 2),
            $row['purchase'],
            number_format($cpp, 2),
            $row['unit_sold'],
            number_format($row['item_cost'], 2),
            number_format($row['cod'], 2),
            number_format($cogs, 2),
            number_format($row['sales'], 2),
            number_format($row['profit'], 2),
            $row['created_at']
        ]);
    }

    fclose($output);
    exit();
} else {
    die("No date specified.");
}
?>