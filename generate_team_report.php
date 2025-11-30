<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary libraries
require_once('tcpdf/tcpdf.php');
require_once('dbconn_productProfit.php');
session_start();

// Uncomment and modify the admin check as needed
// if (!$is_admin) {
//     die("Unauthorized access. Admin rights required.");
// }

// Capture GET parameters for filtering
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 'all';

// If team_id is 0, treat it as 'all'
if ($team_id === 0) {
    $team_id = 'all';
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    // Prepare the SQL query based on the date range and team_id
    if ($team_id === 'all') {
        $sql = "SELECT p.*, t.team_name 
                FROM products p
                LEFT JOIN teams t ON p.team_id = t.team_id
                WHERE p.created_at BETWEEN ? AND ? 
                ORDER BY p.created_at ASC";
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $dbconn->error);
        }
        $stmt->bind_param("ss", $start_date, $end_date);
    } else {
        $sql = "SELECT p.*, t.team_name 
                FROM products p
                LEFT JOIN teams t ON p.team_id = t.team_id
                WHERE p.team_id = ? AND p.created_at BETWEEN ? AND ? 
                ORDER BY p.created_at ASC";
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $dbconn->error);
        }
        $stmt->bind_param("iss", $team_id, $start_date, $end_date);
    }

    // Execute the query
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();

    // Get team name
    $teamName = ($team_id === 'all') ? "All Teams" : "Unknown Team";
    if ($result->num_rows > 0) {
        $firstRow = $result->fetch_assoc();
        $teamName = $firstRow['team_name'] ?? "Unknown Team";
        
        // Reset the result pointer
        $result->data_seek(0);
    }

    // Format the date range for display in the PDF
    $filterDate = "$start_date to $end_date";

    // Start PDF document
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Product Profit System');
    $pdf->SetTitle('Team Performance Report');
    $pdf->SetSubject('Team Performance Calculation Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    // Start HTML content with CSS (same as previous script)
    $html = '
    <style>
        /* CSS styles remain the same as in the previous script */
    </style>

    <h2>Team Performance Report: ' . htmlspecialchars($teamName) . '</h2>
    <p><strong>Date Range:</strong> ' . htmlspecialchars($filterDate) . '</p>
    ';

    // Initialize totals
    $totalSales = 0;
    $totalParcels = 0;
    $totalAdsSpend = 0;
    $totalProfit = 0;
    $totalCogs = 0;

    // Check if there are any results
    if ($result->num_rows > 0) {
        $html .= '
        <table class="products-table">
            <thead>
                <tr>
                    <th>Team</th>
                    <th>Product</th>
                    <th>Ads Spend (RM)</th>
                    <th>Purchase</th>
                    <th>CPP</th>
                    <th>Units Sold</th>
                    <th>Item Cost (RM)</th>
                    <th>COD (RM)</th>
                    <th>COGS (RM)</th>
                    <th>Sales (RM)</th>
                    <th>Profit (RM)</th>
                </tr>
            </thead>
            <tbody>
        ';

        // Reset result pointer and process rows
        $result->data_seek(0);
        
// Replace numeric calculations with null-safe methods

// Modify the row processing section
while ($row = $result->fetch_assoc()) {
    // Null-safe numeric conversions
    $adsspend = floatval($row['ads_spend'] ?? 0);
    $purchase = intval($row['purchase'] ?? 0);
    $itemCost = floatval($row['item_cost'] ?? 0);
    $cod = floatval($row['cod'] ?? 0);
    $sales = floatval($row['sales'] ?? 0);
    $profit = floatval($row['profit'] ?? 0);

    $cogs = $itemCost + $cod;
    $totalSales += $sales;
    $totalParcels += $purchase;
    $totalAdsSpend += $adsspend;
    $totalProfit += $profit;
    $totalCogs += $cogs;

    $profitColor = ($profit > 0) ? 'green' : 'red';
    $teamDisplayName = $row['team_name'] ?? 'Unknown';

    $html .= '
    <tr>
        <td>' . htmlspecialchars($teamDisplayName) . '</td>
        <td>' . htmlspecialchars($row['product_name'] ?? 'N/A') . '</td>
        <td>' . number_format($adsspend, 2) . '</td>
        <td>' . ($purchase ?: 'N/A') . '</td>
        <td>' . number_format($purchase > 0 ? $adsspend / $purchase : 0, 2) . '</td>
        <td>' . ($row['unit_sold'] ?? 'N/A') . '</td>
        <td>' . number_format($itemCost, 2) . '</td>
        <td>' . number_format($cod, 2) . '</td>
        <td>' . number_format($cogs, 2) . '</td>
        <td>' . number_format($sales, 2) . '</td>
        <td style="color:' . $profitColor . ';">' . number_format($profit, 2) . '</td>
    </tr>';
}

// Modify summary calculations
$salesPerParcel = ($totalParcels > 0) ? number_format($totalSales / $totalParcels, 2) : '0.00';
$profitPerParcel = ($totalParcels > 0) ? number_format($totalProfit / $totalParcels, 2) : '0.00';
$roas = ($totalAdsSpend > 0) ? number_format($totalSales / $totalAdsSpend, 2) : '0.00';

        // Add color for profit in summary
        $profitColorSummary = ($totalProfit > 0) ? 'green' : 'red';

        $html .= '
            </tbody>
        </table>

        <h3>Summary</h3>
        <table class="summary-table">
            <tr>
                <th>Total COGS</th>
                <td>RM ' . number_format($totalCogs, 2) . '</td>
            </tr>
            <tr>
                <th>Total Sales</th>
                <td>RM ' . number_format($totalSales, 2) . '</td>
            </tr>
            <tr>
                <th>Total Parcels</th>
                <td>' . $totalParcels . '</td>
            </tr>
            <tr>
                <th>Total Ads Spend</th>
                <td>RM ' . number_format($totalAdsSpend, 2) . '</td>
            </tr>
            <tr>
                <th>Sales Per Parcel</th>
                <td>RM ' . $salesPerParcel . '</td>
            </tr>
            <tr>
                <th>Profit Per Parcel</th>
                <td>RM ' . $profitPerParcel . '</td>
            </tr>
            <tr>
                <th>Total Profit</th>
                <td style="color:' . $profitColorSummary . ';">RM ' . number_format($totalProfit, 2) . '</td>
            </tr>
            <tr>
                <th>ROAS (Return on Ad Spend)</th>
                <td>' . $roas . '</td>
            </tr>
        </table>
        ';
    } else {
        $html .= '<p class="no-data">No data available for the selected date range.</p>';
        
        // Additional debugging information
        $html .= '
        <div style="margin-top: 20px; border: 1px solid red; padding: 10px;">
            <h4>Debugging Information</h4>
            <p><strong>Team ID:</strong> ' . htmlspecialchars($team_id) . '</p>
            <p><strong>Start Date:</strong> ' . htmlspecialchars($start_date) . '</p>
            <p><strong>End Date:</strong> ' . htmlspecialchars($end_date) . '</p>
            <p><strong>SQL Query:</strong> ' . htmlspecialchars($sql) . '</p>
        </div>';
    }

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('team_performance_report.pdf', 'D'); // 'D' triggers download
    exit;

} catch (Exception $e) {
    // Error handling
    $errorMessage = $e->getMessage();
    
    // Create a PDF to show the error
    $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Error in Team Performance Report');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    
    $html = '
    <h1>Error Generating Report</h1>
    <p>An error occurred while generating the team performance report:</p>
    <div style="background-color: #ffcccc; padding: 10px; border: 1px solid red;">
        ' . htmlspecialchars($errorMessage) . '
    </div>
    <p>Please contact your system administrator with these details.</p>
    ';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('error_report.pdf', 'D');
    exit;
}