<?php
// Script to generate comprehensive analytics PDF report
require_once 'auth.php';
require_once 'dbconn_productProfit.php';
require_once 'tcpdf/tcpdf.php'; // For TCPDF library

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only allow authenticated admin users
if (!isset($_SESSION['user_id']) || !$is_admin) {
    header('Location: login.php');
    exit();
}

// Get parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'summary';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    die('Invalid date format');
}

// Validate report type
$valid_types = ['summary', 'detailed', 'financial'];
if (!in_array($report_type, $valid_types)) {
    die('Invalid report type');
}

try {
    // Check what column exists in the teams table
    $check_column = $dbconn->query("SHOW COLUMNS FROM teams");
    if (!$check_column) {
        throw new Exception("Error checking columns: " . $dbconn->error);
    }
    
    $column_names = [];
    while($row = $check_column->fetch_assoc()) {
        $column_names[] = $row['Field'];
    }
    
    // Determine the correct primary key and name column
    $team_pk = in_array('id', $column_names) ? 'id' : 'team_id';
    $team_name_col = in_array('team_name', $column_names) ? 'team_name' : 'name';
    
    // Get overall summary stats - Use prepared statements
    $summary_query = "
        SELECT 
            SUM(sales) as total_sales,
            SUM(profit) as total_profit,
            COUNT(id) as total_products,
            SUM(unit_sold) as total_units,
            AVG(CASE WHEN sales > 0 THEN profit / sales * 100 ELSE 0 END) as avg_margin
        FROM 
            products
        WHERE 
            created_at BETWEEN ? AND ?
    ";
    
    $stmt = $dbconn->prepare($summary_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for summary query: " . $dbconn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for summary query: " . $stmt->error);
    }
    
    $summary_result = $stmt->get_result();
    $summary = $summary_result->fetch_assoc();
    $stmt->close();
    
    // Get team performance data - Use prepared statements
    $team_query = "
        SELECT 
            t.$team_pk as team_id,
            t.$team_name_col as team_name,
            SUM(IFNULL(p.sales, 0)) as team_sales,
            SUM(IFNULL(p.profit, 0)) as team_profit,
            COUNT(p.id) as team_products,
            SUM(IFNULL(p.unit_sold, 0)) as team_units,
            AVG(CASE WHEN p.sales > 0 THEN p.profit / p.sales * 100 ELSE 0 END) as team_margin
        FROM 
            teams t
        LEFT JOIN 
            products p ON t.$team_pk = p.team_id AND p.created_at BETWEEN ? AND ?
        GROUP BY 
            t.$team_pk, t.$team_name_col
        ORDER BY 
            team_sales DESC
    ";
    
    $stmt = $dbconn->prepare($team_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for team query: " . $dbconn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for team query: " . $stmt->error);
    }
    
    $team_result = $stmt->get_result();
    $teams_data = [];
    while ($team_row = $team_result->fetch_assoc()) {
        $teams_data[] = $team_row;
    }
    $stmt->close();
    
    // Get top products data - Use prepared statements
    $top_products_query = "
        SELECT 
            id as product_id,
            product_name,
            IFNULL(ads_spend, 0) as ads_spend,
            IFNULL(unit_sold, 0) as unit_sold,
            IFNULL(sales, 0) as sales,
            IFNULL(profit, 0) as profit,
            created_at
        FROM 
            products
        WHERE 
            created_at BETWEEN ? AND ?
        ORDER BY 
            sales DESC
        LIMIT 10
    ";
    
    $stmt = $dbconn->prepare($top_products_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for top products query: " . $dbconn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for top products query: " . $stmt->error);
    }
    
    $top_products_result = $stmt->get_result();
    $top_products = [];
    while ($product_row = $top_products_result->fetch_assoc()) {
        $top_products[] = $product_row;
    }
    $stmt->close();
    
    // Get monthly trend data - Use prepared statements
    $monthly_trend_query = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(IFNULL(sales, 0)) as monthly_sales,
            SUM(IFNULL(profit, 0)) as monthly_profit,
            COUNT(id) as monthly_products
        FROM 
            products
        WHERE 
            created_at BETWEEN DATE_SUB(?, INTERVAL 6 MONTH) AND ?
        GROUP BY 
            DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY 
            month
    ";
    
    $stmt = $dbconn->prepare($monthly_trend_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for monthly trend query: " . $dbconn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for monthly trend query: " . $stmt->error);
    }
    
    $monthly_trend_result = $stmt->get_result();
    $monthly_trend = [];
    while ($trend_row = $monthly_trend_result->fetch_assoc()) {
        $month_label = date('M Y', strtotime($trend_row['month'] . '-01'));
        $trend_row['month_label'] = $month_label;
        $monthly_trend[] = $trend_row;
    }
    $stmt->close();
    
    // For detailed and financial reports, get additional data
    $detailed_products = [];
    $team_monthly_data = [];
    $financial_metrics = [];
    
    if ($report_type === 'detailed' || $report_type === 'financial') {
        // Get detailed product performance - Use prepared statements
        $detailed_products_query = "
            SELECT 
                p.id as product_id,
                p.product_name,
                IFNULL(p.ads_spend, 0) as ads_spend,
                IFNULL(p.unit_sold, 0) as unit_sold,
                IFNULL(p.sales, 0) as sales,
                IFNULL(p.profit, 0) as profit,
                p.created_at,
                t.$team_name_col as team_name
            FROM 
                products p
            JOIN
                teams t ON p.team_id = t.$team_pk
            WHERE 
                p.created_at BETWEEN ? AND ?
            ORDER BY 
                p.sales DESC
        ";
        
        $stmt = $dbconn->prepare($detailed_products_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for detailed products query: " . $dbconn->error);
        }
        
        $stmt->bind_param("ss", $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for detailed products query: " . $stmt->error);
        }
        
        $detailed_products_result = $stmt->get_result();
        while ($product_row = $detailed_products_result->fetch_assoc()) {
            $detailed_products[] = $product_row;
        }
        $stmt->close();
        
        // Get team monthly performance - Use prepared statements
        $team_monthly_query = "
            SELECT 
                t.$team_pk as team_id,
                t.$team_name_col as team_name,
                DATE_FORMAT(p.created_at, '%Y-%m') as month,
                SUM(IFNULL(p.sales, 0)) as monthly_sales,
                SUM(IFNULL(p.profit, 0)) as monthly_profit
            FROM 
                teams t
            JOIN 
                products p ON t.$team_pk = p.team_id
            WHERE 
                p.created_at BETWEEN DATE_SUB(?, INTERVAL 3 MONTH) AND ?
            GROUP BY 
                t.$team_pk, t.$team_name_col, DATE_FORMAT(p.created_at, '%Y-%m')
            ORDER BY 
                t.$team_name_col, month
        ";
        
        $stmt = $dbconn->prepare($team_monthly_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for team monthly query: " . $dbconn->error);
        }
        
        $stmt->bind_param("ss", $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for team monthly query: " . $stmt->error);
        }
        
        $team_monthly_result = $stmt->get_result();
        while ($monthly_row = $team_monthly_result->fetch_assoc()) {
            $month_label = date('M Y', strtotime($monthly_row['month'] . '-01'));
            $monthly_row['month_label'] = $month_label;
            $team_monthly_data[] = $monthly_row;
        }
        $stmt->close();
    }
    
    if ($report_type === 'financial') {
        // Calculate financial metrics
        $prev_period_start = date('Y-m-d', strtotime("$start_date -" . (strtotime($end_date) - strtotime($start_date)) . " seconds"));
        $prev_period_end = date('Y-m-d', strtotime("$end_date -" . (strtotime($end_date) - strtotime($start_date)) . " seconds"));
        
        // Current period stats - Use prepared statements
        $current_period_query = "
            SELECT 
                SUM(IFNULL(sales, 0)) as total_sales,
                SUM(IFNULL(profit, 0)) as total_profit,
                COUNT(id) as total_products,
                SUM(IFNULL(unit_sold, 0)) as total_units
            FROM 
                products
            WHERE 
                created_at BETWEEN ? AND ?
        ";
        
        $stmt = $dbconn->prepare($current_period_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for current period query: " . $dbconn->error);
        }
        
        $stmt->bind_param("ss", $start_date, $end_date);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for current period query: " . $stmt->error);
        }
        
        $current_result = $stmt->get_result();
        $current_period = $current_result->fetch_assoc();
        $stmt->close();
        
        // Previous period stats - Use prepared statements
        $prev_period_query = "
            SELECT 
                SUM(IFNULL(sales, 0)) as total_sales,
                SUM(IFNULL(profit, 0)) as total_profit,
                COUNT(id) as total_products,
                SUM(IFNULL(unit_sold, 0)) as total_units
            FROM 
                products
            WHERE 
                created_at BETWEEN ? AND ?
        ";
        
        $stmt = $dbconn->prepare($prev_period_query);
        if (!$stmt) {
            throw new Exception("Prepare failed for previous period query: " . $dbconn->error);
        }
        
        $stmt->bind_param("ss", $prev_period_start, $prev_period_end);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for previous period query: " . $stmt->error);
        }
        
        $prev_result = $stmt->get_result();
        $prev_period = $prev_result->fetch_assoc();
        $stmt->close();
        
        // Calculate growth rates with null safety checks
        $sales_growth = ($prev_period['total_sales'] > 0) ? 
            (($current_period['total_sales'] - $prev_period['total_sales']) / $prev_period['total_sales'] * 100) : 0;
        
        $profit_growth = ($prev_period['total_profit'] > 0) ? 
            (($current_period['total_profit'] - $prev_period['total_profit']) / $prev_period['total_profit'] * 100) : 0;
        
        $products_growth = ($prev_period['total_products'] > 0) ? 
            (($current_period['total_products'] - $prev_period['total_products']) / $prev_period['total_products'] * 100) : 0;
        
        $units_growth = ($prev_period['total_units'] > 0) ? 
            (($current_period['total_units'] - $prev_period['total_units']) / $prev_period['total_units'] * 100) : 0;
        
        // Store financial metrics
        $financial_metrics = [
            'current_period' => $current_period,
            'prev_period' => $prev_period,
            'sales_growth' => $sales_growth,
            'profit_growth' => $profit_growth,
            'products_growth' => $products_growth,
            'units_growth' => $units_growth,
            'prev_period_start' => $prev_period_start,
            'prev_period_end' => $prev_period_end
        ];
    }
    
    // Create PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $report_title = ucfirst($report_type) . " Analytics Report (" . $start_date . " to " . $end_date . ")";
    $pdf->SetCreator('Analytics System');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle($report_title);
    $pdf->SetSubject('Analytics Report');
    $pdf->SetKeywords('analytics, report, sales, profit');
    
    // Set default header and footer data
    $pdf->SetHeaderData('', 0, $report_title, 'Generated on: ' . date('Y-m-d H:i:s'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Set image scale factor
    $pdf->setImageScale(1.25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Write title
    $pdf->Cell(0, 10, $report_title, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Period: ' . $start_date . ' to ' . $end_date, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Summary section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Summary Overview', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Create summary table
    $summary_html = '
    <table border="1" cellpadding="5">
        <tr style="background-color:#f0f0f0; font-weight:bold;">
            <th>Total Sales</th>
            <th>Total Profit</th>
            <th>Products</th>
            <th>Units Sold</th>
            <th>Avg. Margin %</th>
        </tr>
        <tr>
            <td>RM ' . number_format(floatval($summary['total_sales'] ?? 0), 2) . '</td>
            <td>RM ' . number_format(floatval($summary['total_profit'] ?? 0), 2) . '</td>
            <td>' . number_format(intval($summary['total_products'] ?? 0)) . '</td>
            <td>' . number_format(intval($summary['total_units'] ?? 0)) . '</td>
            <td>' . number_format(floatval($summary['avg_margin'] ?? 0), 2) . '%</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($summary_html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Team Performance Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Team Performance', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Create team table
    $team_html = '
    <table border="1" cellpadding="5">
        <tr style="background-color:#f0f0f0; font-weight:bold;">
            <th>Team</th>
            <th>Sales</th>
            <th>Profit</th>
            <th>Products</th>
            <th>Units</th>
            <th>Margin %</th>
        </tr>';
    
    foreach ($teams_data as $team) {
        $team_html .= '
        <tr>
            <td>' . htmlspecialchars($team['team_name'] ?? 'Unknown') . '</td>
            <td>RM ' . number_format(floatval($team['team_sales'] ?? 0), 2) . '</td>
            <td>RM ' . number_format(floatval($team['team_profit'] ?? 0), 2) . '</td>
            <td>' . number_format(intval($team['team_products'] ?? 0)) . '</td>
            <td>' . number_format(intval($team['team_units'] ?? 0)) . '</td>
            <td>' . number_format(floatval($team['team_margin'] ?? 0), 2) . '%</td>
        </tr>';
    }
    
    $team_html .= '</table>';
    $pdf->writeHTML($team_html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Monthly Trend Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Monthly Trend', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Create monthly trend table
    $monthly_html = '
    <table border="1" cellpadding="5">
        <tr style="background-color:#f0f0f0; font-weight:bold;">
            <th>Month</th>
            <th>Sales</th>
            <th>Profit</th>
            <th>Products</th>
        </tr>';
    
    foreach ($monthly_trend as $month) {
        $monthly_html .= '
        <tr>
            <td>' . htmlspecialchars($month['month_label'] ?? 'Unknown') . '</td>
            <td>RM ' . number_format(floatval($month['monthly_sales'] ?? 0), 2) . '</td>
            <td>RM ' . number_format(floatval($month['monthly_profit'] ?? 0), 2) . '</td>
            <td>' . number_format(intval($month['monthly_products'] ?? 0)) . '</td>
        </tr>';
    }
    
    $monthly_html .= '</table>';
    $pdf->writeHTML($monthly_html, true, false, true, false, '');
    $pdf->Ln(10);
    
    // Top Products Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Top 10 Products', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Create top products table
    $products_html = '
    <table border="1" cellpadding="5">
        <tr style="background-color:#f0f0f0; font-weight:bold;">
            <th>Product</th>
            <th>Sales</th>
            <th>Profit</th>
            <th>Units</th>
            <th>Ad Spend</th>
            <th>Date</th>
        </tr>';
    
    foreach ($top_products as $product) {
        $products_html .= '
        <tr>
            <td>' . htmlspecialchars($product['product_name'] ?? 'Unknown') . '</td>
            <td>RM ' . number_format(floatval($product['sales'] ?? 0), 2) . '</td>
            <td>RM ' . number_format(floatval($product['profit'] ?? 0), 2) . '</td>
            <td>' . number_format(intval($product['unit_sold'] ?? 0)) . '</td>
            <td>RM ' . number_format(floatval($product['ads_spend'] ?? 0), 2) . '</td>
            <td>' . date('Y-m-d', strtotime($product['created_at'])) . '</td>
        </tr>';
    }
    
    $products_html .= '</table>';
    $pdf->writeHTML($products_html, true, false, true, false, '');
    
    // Add specific sections for detailed and financial reports
    if ($report_type === 'detailed' || $report_type === 'financial') {
        $pdf->AddPage();
        
        // Team Monthly Performance
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Team Monthly Performance', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        // Group team monthly data by team
        $team_monthly_grouped = [];
        foreach ($team_monthly_data as $data) {
            $team_id = $data['team_id'];
            if (!isset($team_monthly_grouped[$team_id])) {
                $team_monthly_grouped[$team_id] = [
                    'team_name' => $data['team_name'] ?? 'Unknown',
                    'months' => []
                ];
            }
            $team_monthly_grouped[$team_id]['months'][$data['month']] = [
                'month_label' => $data['month_label'],
                'sales' => $data['monthly_sales'],
                'profit' => $data['monthly_profit']
            ];
        }
        
        foreach ($team_monthly_grouped as $team_data) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, $team_data['team_name'], 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            // Create monthly table for each team
            $team_monthly_html = '
            <table border="1" cellpadding="5">
                <tr style="background-color:#f0f0f0; font-weight:bold;">
                    <th>Month</th>
                    <th>Sales</th>
                    <th>Profit</th>
                </tr>';
            
            foreach ($team_data['months'] as $month_data) {
                $team_monthly_html .= '
                <tr>
                    <td>' . htmlspecialchars($month_data['month_label'] ?? 'Unknown') . '</td>
                    <td>$' . number_format(floatval($month_data['sales'] ?? 0), 2) . '</td>
                    <td>$' . number_format(floatval($month_data['profit'] ?? 0), 2) . '</td>
                </tr>';
            }
            
            $team_monthly_html .= '</table>';
            $pdf->writeHTML($team_monthly_html, true, false, true, false, '');
            $pdf->Ln(5);
        }
        
        // Detailed Products List
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Detailed Product Performance', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        // Create detailed products table
        $detailed_products_html = '
        <table border="1" cellpadding="5">
            <tr style="background-color:#f0f0f0; font-weight:bold;">
                <th>Product</th>
                <th>Team</th>
                <th>Sales</th>
                <th>Profit</th>
                <th>Units</th>
                <th>Ad Spend</th>
                <th>Date</th>
            </tr>';
        
        foreach ($detailed_products as $product) {
            $detailed_products_html .= '
            <tr>
                <td>' . htmlspecialchars($product['product_name'] ?? 'Unknown') . '</td>
                <td>' . htmlspecialchars($product['team_name'] ?? 'Unknown') . '</td>
                <td>$' . number_format(floatval($product['sales'] ?? 0), 2) . '</td>
                <td>$' . number_format(floatval($product['profit'] ?? 0), 2) . '</td>
                <td>' . number_format(intval($product['unit_sold'] ?? 0)) . '</td>
                <td>$' . number_format(floatval($product['ads_spend'] ?? 0), 2) . '</td>
                <td>' . date('Y-m-d', strtotime($product['created_at'])) . '</td>
            </tr>';
        }
        
        $detailed_products_html .= '</table>';
        $pdf->writeHTML($detailed_products_html, true, false, true, false, '');
    }
    
    // Financial report specific sections
    if ($report_type === 'financial') {
        $pdf->AddPage();
        
        // Financial Metrics Section
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Financial Performance', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        // Create financial metrics comparison table
        $financial_html = '
        <table border="1" cellpadding="5">
            <tr style="background-color:#f0f0f0; font-weight:bold;">
                <th>Metric</th>
                <th>Current Period<br>(' . $start_date . ' to ' . $end_date . ')</th>
                <th>Previous Period<br>(' . $financial_metrics['prev_period_start'] . ' to ' . $financial_metrics['prev_period_end'] . ')</th>
                <th>Growth %</th>
            </tr>
            <tr>
                <td>Total Sales</td>
                <td>$' . number_format(floatval($financial_metrics['current_period']['total_sales'] ?? 0), 2) . '</td>
                <td>$' . number_format(floatval($financial_metrics['prev_period']['total_sales'] ?? 0), 2) . '</td>
                <td>' . number_format(floatval($financial_metrics['sales_growth'] ?? 0), 2) . '%</td>
            </tr>
            <tr>
                <td>Total Profit</td>
                <td>$' . number_format(floatval($financial_metrics['current_period']['total_profit'] ?? 0), 2) . '</td>
                <td>$' . number_format(floatval($financial_metrics['prev_period']['total_profit'] ?? 0), 2) . '</td>
                <td>' . number_format(floatval($financial_metrics['profit_growth'] ?? 0), 2) . '%</td>
            </tr>
            <tr>
                <td>Total Products</td>
                <td>' . number_format(intval($financial_metrics['current_period']['total_products'] ?? 0)) . '</td>
                <td>' . number_format(intval($financial_metrics['prev_period']['total_products'] ?? 0)) . '</td>
                <td>' . number_format(floatval($financial_metrics['products_growth'] ?? 0), 2) . '%</td>
            </tr>
            <tr>
                <td>Total Units</td>
                <td>' . number_format(intval($financial_metrics['current_period']['total_units'] ?? 0)) . '</td>
                <td>' . number_format(intval($financial_metrics['prev_period']['total_units'] ?? 0)) . '</td>
                <td>' . number_format(floatval($financial_metrics['units_growth'] ?? 0), 2) . '%</td>
            </tr>
        </table>';
        
        $pdf->writeHTML($financial_html, true, false, true, false, '');
        $pdf->Ln(10);
        
        // Financial Analysis
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Financial Analysis', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        // Generate analysis text based on the data
        $analysis_text = '<p>Analysis for the period ' . $start_date . ' to ' . $end_date . ' compared to the previous equivalent period:</p>';
        
        // Sales analysis
        if ($financial_metrics['sales_growth'] > 0) {
            $analysis_text .= '<p><strong>Sales:</strong> Increased by ' . number_format(abs($financial_metrics['sales_growth']), 2) . '%. ';
            if ($financial_metrics['profit_growth'] > $financial_metrics['sales_growth']) {
                $analysis_text .= 'Profit growth outpaced sales growth, indicating improved margins.</p>';
            } else {
                $analysis_text .= 'Sales grew faster than profit, suggesting potential pricing or cost challenges.</p>';
            }
        } else {
            $analysis_text .= '<p><strong>Sales:</strong> Decreased by ' . number_format(abs($financial_metrics['sales_growth']), 2) . '%. ';
            $analysis_text .= 'This represents a challenge that requires attention.</p>';
        }
        
        // Profit analysis
        if ($financial_metrics['profit_growth'] > 0) {
            $analysis_text .= '<p><strong>Profit:</strong> Increased by ' . number_format(abs($financial_metrics['profit_growth']), 2) . '%. ';
        } else {
            $analysis_text .= '<p><strong>Profit:</strong> Decreased by ' . number_format(abs($financial_metrics['profit_growth']), 2) . '%. ';
        }
        
        // Product and unit analysis
        $analysis_text .= '<p><strong>Product Count:</strong> ' . ($financial_metrics['products_growth'] >= 0 ? 'Increased' : 'Decreased') . ' by ' . 
            number_format(abs($financial_metrics['products_growth']), 2) . '%. </p>';
        
        $analysis_text .= '<p><strong>Units Sold:</strong> ' . ($financial_metrics['units_growth'] >= 0 ? 'Increased' : 'Decreased') . ' by ' . 
            number_format(abs($financial_metrics['units_growth']), 2) . '%. </p>';
        
        // Overall assessment
        $overall_trend = ($financial_metrics['sales_growth'] + $financial_metrics['profit_growth']) / 2;
        if ($overall_trend > 10) {
            $analysis_text .= '<p><strong>Overall Assessment:</strong> Strong financial performance with substantial growth.</p>';
        } elseif ($overall_trend > 0) {
            $analysis_text .= '<p><strong>Overall Assessment:</strong> Positive financial performance with modest growth.</p>';
        } elseif ($overall_trend > -10) {
            $analysis_text .= '<p><strong>Overall Assessment:</strong> Slight decline in financial performance, requiring attention.</p>';
        } else {
            $analysis_text .= '<p><strong>Overall Assessment:</strong> Significant decline in financial performance, requiring immediate action.</p>';
        }
        
        $pdf->writeHTML($analysis_text, true, false, true, false, '');
    }
    
    // Output the PDF
    $pdf_filename = 'analytics_report_' . $report_type . '_' . date('Ymd') . '.pdf';
    $pdf->Output($pdf_filename, 'D'); // 'D' triggers download
    exit;

} catch (Exception $e) {
    // Error handling
    $errorMessage = $e->getMessage();
    
    // Create a PDF to show the error
    $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Error in Analytics Report');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    
    $html = '
    <h1>Error Generating Report</h1>
    <p>An error occurred while generating the analytics report:</p>
    <div style="background-color: #ffcccc; padding: 10px; border: 1px solid red;">
        ' . htmlspecialchars($errorMessage) . '
    </div>
    <p>Please contact your system administrator with these details.</p>
    ';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('error_report.pdf', 'D');
    exit;
}