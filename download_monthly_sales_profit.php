<?php
require 'dbconn_productProfit.php';

if (isset($_GET['month'])) {
    $selectedMonth = $_GET['month']; // Format: YYYY-MM

    // Fetch data for the selected month
    $sql = "SELECT * FROM products WHERE DATE_FORMAT(created_at, '%Y-%m') = ? ORDER BY created_at ASC";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("s", $selectedMonth);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("No data available for the selected month.");
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Sales_Profit_' . $selectedMonth . '.csv"');

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
            $row['product_name'],
            $row['ads_spend'],
            $row['purchase'],
            number_format($cpp, 2),
            $row['unit_sold'],
            $row['item_cost'],
            $row['cod'],
            number_format($cogs, 2),
            $row['sales'],
            $row['profit'],
            $row['created_at']
        ]);
    }

    fclose($output);
    exit();
}
?>
