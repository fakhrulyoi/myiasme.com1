<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require 'dbconn_productProfit.php';
require 'auth.php'; // Ensure user is authenticated

// Check which report type is requested
$report_type = isset($_GET['report_type']) && in_array($_GET['report_type'], ['daily', 'monthly', 'date_range', 'product_performance', 'advanced_analytics']) ? $_GET['report_type'] : '';
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Get parameters based on report type
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set appropriate filename based on report type
$filename = "Report.xls"; // Default
switch ($report_type) {
    case 'daily':
        $filename = "Daily_Report_" . $date . ".xls";
        break;
    case 'monthly':
        $filename = "Monthly_Report_" . $month . ".xls";
        break;
    case 'date_range':
        $filename = "Date_Range_" . $start_date . "_to_" . $end_date . ".xls";
        break;
    case 'product_performance':
        $filename = "Product_Performance_" . $start_date . "_to_" . $end_date . ".xls";
        break;
    case 'advanced_analytics':
        $filename = "Advanced_Analytics.xls";
        break;
}

// Set headers for Excel download - MOVED HERE BEFORE ANY OUTPUT
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Validate team_id
if ($team_id <= 0) {
    die("Invalid team ID");
}

// Set default values for headers and title
$title = "Sales Report";
$headers = ['Product', 'Sales (RM)', 'Cost (RM)', 'Profit (RM)', 'Profit Margin (%)'];
$data = [];

switch ($report_type) {
    case 'daily':
        // Get and validate date parameter
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            die("Invalid date format. Use YYYY-MM-DD");
        }
        
        // Fix: Use NULLIF to prevent division by zero
        $sql = "SELECT product_name, sales, item_cost + cod AS total_cost, profit, 
                (profit / NULLIF(sales, 0)) * 100 AS profit_margin 
                FROM products 
                WHERE DATE(created_at) = ? AND team_id = ? 
                ORDER BY sales DESC";
        
        // Better error handling for prepared statements
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            die("SQL Error: " . $dbconn->error);
        }
        
        $stmt->bind_param("si", $date, $team_id);
        if (!$stmt->execute()) {
            die("Execution Error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                $row['product_name'],
                number_format($row['sales'], 2),
                number_format($row['total_cost'], 2),
                number_format($row['profit'], 2),
                number_format($row['profit_margin'], 2) . '%'
            ];
        }
       
        break;
        
    
        
    case 'monthly':
        // Get the month from the query parameters
        $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        
        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            die("Invalid month format. Use YYYY-MM");
        }
        
        $title = "Monthly Sales Report - " . $month;
        $headers = ['Date', 'Total Sales (RM)', 'Total Cost (RM)', 'Total Profit (RM)', 'Profit Margin (%)'];
        
        // Get monthly sales data
        $sql = "SELECT DATE(created_at) AS sales_date, 
                SUM(sales) AS total_sales, 
                SUM(item_cost + cod) AS total_cost, 
                SUM(profit) AS total_profit,
                (SUM(profit) / SUM(sales)) * 100 AS profit_margin
                FROM products 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND team_id = ?
                GROUP BY sales_date 
                ORDER BY sales_date ASC";
        
        $stmt = $dbconn->prepare($sql);
        $stmt->bind_param("si", $month, $team_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                $row['sales_date'],
                number_format($row['total_sales'], 2),
                number_format($row['total_cost'], 2),
                number_format($row['total_profit'], 2),
                number_format($row['profit_margin'], 2) . '%'
            ];
        }
        break;
        
    case 'date_range':
        // Get date range from query parameters
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        
        // Validate date formats
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            die("Invalid date format. Use YYYY-MM-DD");
        }
        
        $title = "Sales Report: " . $start_date . " to " . $end_date;
        $headers = ['Date', 'Product', 'Sales (RM)', 'Cost (RM)', 'Profit (RM)', 'Margin (%)'];
        
        // Get date range sales data
        $sql = "SELECT DATE(created_at) AS sales_date, product_name, sales, item_cost + cod AS total_cost, profit, 
                (profit / sales) * 100 AS profit_margin 
                FROM products 
                WHERE created_at BETWEEN ? AND ? AND team_id = ? 
                ORDER BY sales_date ASC, sales DESC";
        
        $stmt = $dbconn->prepare($sql);
        $end_date_adj = date('Y-m-d', strtotime($end_date . ' +1 day')); // Include end date fully
        $stmt->bind_param("ssi", $start_date, $end_date_adj, $team_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                $row['sales_date'],
                $row['product_name'],
                number_format($row['sales'], 2),
                number_format($row['total_cost'], 2),
                number_format($row['profit'], 2),
                number_format($row['profit_margin'], 2) . '%'
            ];
        }
        break;
        
    case 'product_performance':
        // Get date range from query parameters
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        
        $title = "Product Performance Report: " . $start_date . " to " . $end_date;
        $headers = ['Product', 'Total Sales (RM)', 'Total Cost (RM)', 'Total Profit (RM)', 'Avg. Margin (%)', 'Units Sold'];
        
        // Get product performance data
        $sql = "SELECT product_name, 
                SUM(sales) AS total_sales, 
                SUM(item_cost + cod) AS total_cost, 
                SUM(profit) AS total_profit,
                (SUM(profit) / SUM(sales)) * 100 AS avg_margin,
                COUNT(*) AS units_sold
                FROM products 
                WHERE created_at BETWEEN ? AND ? AND team_id = ?
                GROUP BY product_name 
                ORDER BY total_sales DESC";
        
        $stmt = $dbconn->prepare($sql);
        $end_date_adj = date('Y-m-d', strtotime($end_date . ' +1 day')); // Include end date fully
        $stmt->bind_param("ssi", $start_date, $end_date_adj, $team_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                $row['product_name'],
                number_format($row['total_sales'], 2),
                number_format($row['total_cost'], 2),
                number_format($row['total_profit'], 2),
                number_format($row['avg_margin'], 2) . '%',
                $row['units_sold']
            ];
        }
        break;
        
    case 'advanced_analytics':
        $title = "Advanced Analytics Report";
        $headers = ['Week', 'Sales (RM)', 'Ads Spend (RM)', 'Conversion Rate (%)', 'ROI (%)'];
        
        // Try to get data from analytics_data table if it exists
        $check_table_sql = "SHOW TABLES LIKE 'analytics_data'";
        $table_exists = $dbconn->query($check_table_sql);
        
        if ($table_exists && $table_exists->num_rows > 0) {
            // Table exists, get real data
            $sql = "SELECT 
                    CONCAT('Week ', WEEK(date)) as week_label,
                    SUM(total_sales) as weekly_sales,
                    SUM(ad_spend) as weekly_ad_spend,
                    (SUM(conversions) / SUM(visitors)) * 100 as conversion_rate,
                    ((SUM(total_sales) - SUM(ad_spend)) / SUM(ad_spend)) * 100 as roi
                FROM analytics_data
                WHERE team_id = ?
                GROUP BY WEEK(date)
                ORDER BY WEEK(date) DESC
                LIMIT 10";
            
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $row['week_label'],
                    number_format($row['weekly_sales'], 2),
                    number_format($row['weekly_ad_spend'], 2),
                    number_format($row['conversion_rate'], 2) . '%',
                    number_format($row['roi'], 2) . '%'
                ];
            }
        } else {
            // Generate sample data
            $sample_weeks = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'];
            $sample_sales = [5000, 5500, 4800, 6200, 7000];
            $sample_spend = [1000, 1200, 900, 1500, 1800];
            $sample_conv = [2.1, 2.5, 1.8, 3.0, 3.2];
            
            for ($i = 0; $i < count($sample_weeks); $i++) {
                $roi = (($sample_sales[$i] - $sample_spend[$i]) / $sample_spend[$i]) * 100;
                $data[] = [
                    $sample_weeks[$i],
                    number_format($sample_sales[$i], 2),
                    number_format($sample_spend[$i], 2),
                    number_format($sample_conv[$i], 2) . '%',
                    number_format($roi, 2) . '%'
                ];
            }
        }
        break;
        
    default:
        // Default to daily report for current day
        $date = date('Y-m-d');
        $title = "Daily Sales Report - " . $date;
        $headers = ['Product', 'Sales (RM)', 'Cost (RM)', 'Profit (RM)', 'Profit Margin (%)'];
        
        $sql = "SELECT product_name, sales, item_cost + cod AS total_cost, profit, 
                (profit / sales) * 100 AS profit_margin 
                FROM products 
                WHERE DATE(created_at) = ? AND team_id = ? 
                ORDER BY sales DESC";
        
        $stmt = $dbconn->prepare($sql);
        $stmt->bind_param("si", $date, $team_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                $row['product_name'],
                number_format($row['sales'], 2),
                number_format($row['total_cost'], 2),
                number_format($row['profit'], 2),
                number_format($row['profit_margin'], 2) . '%'
            ];
        }
}

// Begin Excel Output
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<style>
    table {border-collapse: collapse; width: 100%;}
    th, td {border: 1px solid #ddd; padding: 8px; text-align: left;}
    th {background-color: #f2f2f2; font-weight: bold;}
    .title {font-size: 18px; font-weight: bold; margin-bottom: 10px;}
    </style>';
echo '</head>';
echo '<body>';
echo '<div class="title">' . $title . '</div>';

// Create table with headers
echo '<table>';
echo '<tr>';
foreach ($headers as $header) {
    echo '<th>' . $header . '</th>';
}
echo '</tr>';

// Add data rows
if (count($data) > 0) {
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . $cell . '</td>';
        }
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="' . count($headers) . '">No data available</td></tr>';
}

// Add summary row if applicable
if (in_array($report_type, ['daily', 'monthly', 'date_range', 'product_performance'])) {
    echo '<tr style="font-weight: bold; background-color: #e6e6e6;">';
    echo '<td>TOTAL</td>';
    
    // Calculate totals
    $total_sales = 0;
    $total_cost = 0;
    $total_profit = 0;
    $total_units = 0;
    
    foreach ($data as $row) {
        $total_sales += (float)str_replace(',', '', $row[count($headers) > 5 ? 1 : 1]);
        $total_cost += (float)str_replace(',', '', $row[count($headers) > 5 ? 2 : 2]);
        $total_profit += (float)str_replace(',', '', $row[count($headers) > 5 ? 3 : 3]);
        
        if (isset($row[5])) {
            $total_units += $row[5];
        }
    }
    
    $avg_margin = ($total_sales > 0) ? ($total_profit / $total_sales) * 100 : 0;
    
    // Output totals based on report type
    if ($report_type == 'product_performance') {
        echo '<td>' . number_format($total_sales, 2) . '</td>';
        echo '<td>' . number_format($total_cost, 2) . '</td>';
        echo '<td>' . number_format($total_profit, 2) . '</td>';
        echo '<td>' . number_format($avg_margin, 2) . '%</td>';
        echo '<td>' . $total_units . '</td>';
    } else if ($report_type == 'date_range') {
        echo '<td>All Products</td>';
        echo '<td>' . number_format($total_sales, 2) . '</td>';
        echo '<td>' . number_format($total_cost, 2) . '</td>';
        echo '<td>' . number_format($total_profit, 2) . '</td>';
        echo '<td>' . number_format($avg_margin, 2) . '%</td>';
    } else {
        echo '<td>' . number_format($total_sales, 2) . '</td>';
        echo '<td>' . number_format($total_cost, 2) . '</td>';
        echo '<td>' . number_format($total_profit, 2) . '</td>';
        echo '<td>' . number_format($avg_margin, 2) . '%</td>';
    }
    echo '</tr>';
}

echo '</table>';
echo '</body>';
echo '</html>';
?>