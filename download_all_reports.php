<?php
require 'auth.php';
require 'dbconn_productProfit.php';
require_once 'report_functions.php';

// Add debug functionality
$debug_log = [];
function debug_log($message, $type = 'info') {
    global $debug_log;
    $debug_log[] = ['message' => $message, 'type' => $type, 'time' => date('H:i:s')];
}

// Enable more detailed error reporting during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if ZIP extension is loaded
if (!extension_loaded('zip')) {
    die("Error: ZIP extension is not loaded in PHP. Please contact your server administrator.");
}

// Initialize message and error variables
$message = '';
$error = '';

// Get current user's team_id
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0) {
    header('Location: login.php');
    exit;
}

$sql_team = "SELECT team_id FROM users WHERE id = ?";
$stmt_team = $dbconn->prepare($sql_team);
$stmt_team->bind_param("i", $user_id);
$stmt_team->execute();
$team_result = $stmt_team->get_result();
$team_data = $team_result->fetch_assoc();
$team_id = $team_data['team_id'] ?? 0;

// Validate team ID
if ($team_id <= 0) {
    die("Error: Invalid team ID. Please contact support.");
}

// Get team name
$team_name = getTeamName($team_id);

// Get username from session or database
$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    $sql_username = "SELECT username FROM users WHERE id = ?";
    $stmt_username = $dbconn->prepare($sql_username);
    $stmt_username->bind_param("i", $user_id);
    $stmt_username->execute();
    $username_result = $stmt_username->get_result();
    $username_data = $username_result->fetch_assoc();
    $username = $username_data['username'] ?? 'User';
}

// Process batch export if requested
if (isset($_POST['batch_export'])) {
    debug_log("Batch export requested", 'info');
    $export_type = isset($_POST['export_type']) && in_array($_POST['export_type'], ['excel', 'pdf']) ? $_POST['export_type'] : 'excel';
    $date_range = isset($_POST['date_range']) && in_array($_POST['date_range'], ['7days', '30days', '90days', 'thismonth', 'lastmonth']) ? $_POST['date_range'] : '7days';
    
    debug_log("Export type: $export_type, Date range: $date_range", 'info');
    
    // Calculate date range
    $end_date = date('Y-m-d');
    
    switch ($date_range) {
        case '7days':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30days':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90days':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'thismonth':
            $start_date = date('Y-m-01');
            break;
        case 'lastmonth':
            $start_date = date('Y-m-d', strtotime('first day of last month'));
            $end_date = date('Y-m-d', strtotime('last day of last month'));
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-7 days'));
    }
    
    debug_log("Date range calculated: $start_date to $end_date", 'info');
    
    // Create directory for exports if it doesn't exist
    $export_dir = 'exports';
    $team_dir = $export_dir . '/' . $team_id;

    // Check if main exports directory exists
    if (!file_exists($export_dir)) {
        debug_log("Main exports directory doesn't exist, creating...", 'info');
        if (!mkdir($export_dir, 0755, true)) {
            $error = "Failed to create main export directory. Please check server permissions.";
            debug_log($error, 'error');
        }
    }

    // Check if team directory exists
    if (!file_exists($team_dir)) {
        debug_log("Team directory doesn't exist, creating...", 'info');
        if (!mkdir($team_dir, 0755, true)) {
            $error = "Failed to create team export directory. Please check server permissions.";
            debug_log($error, 'error');
        }
    }

    // Make sure the exports directory is writable
    if (!is_writable($team_dir)) {
        debug_log("Directory not writable, attempting to set permissions", 'warning');
        // Try to set permissions
        chmod($team_dir, 0755);
        if (!is_writable($team_dir)) {
            $error = "Export directory is not writable. Please check server permissions.";
            debug_log($error, 'error');
        }
    }// Only proceed if no error
    if (empty($error)) {
        debug_log("No directory errors, proceeding with report generation", 'success');
        // Clean old files
        $files = glob($team_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file) > 86400)) { // Delete files older than 1 day
                unlink($file);
                debug_log("Deleted old file: " . basename($file), 'info');
            }
        }
        
        // Create a ZIP archive
        $zip_filename = $team_dir . '/reports_' . date('Y-m-d_H-i-s') . '.zip';
        debug_log("Creating ZIP archive: " . basename($zip_filename), 'info');
        
        $zip = new ZipArchive();
        
        if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
            $error = "Cannot create ZIP archive. ZipArchive error code: " . $zip->getStatusString();
            debug_log($error, 'error');
        } else {
            debug_log("ZIP archive created successfully", 'success');
            
            // Fixed: Properly build base URL with URL encoding
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            
            // Fix for spaces in URL path
            $path_segments = explode('/', dirname($_SERVER['PHP_SELF']));
            $encoded_segments = array_map('rawurlencode', $path_segments);
            $encoded_path = implode('/', $encoded_segments);
            $baseUrl = $protocol . "://" . $host . $encoded_path;
            
            debug_log("Base URL: $baseUrl", 'info');
            
            // Test URL to make sure it's valid
            $test_url = $baseUrl . '/test_connection.php';
            debug_log("Testing URL construction with: $test_url", 'info');
            
            $ch_test = curl_init();
            curl_setopt($ch_test, CURLOPT_URL, $test_url);
            curl_setopt($ch_test, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_test, CURLOPT_NOBODY, true);
            curl_setopt($ch_test, CURLOPT_TIMEOUT, 5);
            $test_result = curl_exec($ch_test);
            $test_error = curl_error($ch_test);
            curl_close($ch_test);
            
            if ($test_result === false) {
                debug_log("URL test failed: $test_error", 'error');
                // Fall back to simple path without spaces
                $baseUrl = $protocol . "://" . $host . str_replace(' ', '_', dirname($_SERVER['PHP_SELF']));
                debug_log("Falling back to alternative URL: $baseUrl", 'info');
            } else {
                debug_log("URL test successful", 'success');
            }
            
            $success = true;
            $selected_reports = $_POST['reports'] ?? ['daily', 'monthly', 'date_range', 'product_performance', 'advanced_analytics'];
            
            // Validate selected reports
            $valid_report_types = ['daily', 'monthly', 'date_range', 'product_performance', 'advanced_analytics'];
            $selected_reports = array_intersect($selected_reports, $valid_report_types);
            debug_log("Selected report types: " . implode(", ", $selected_reports), 'info');
            
            if (empty($selected_reports)) {
                $error = "No valid report types selected.";
                debug_log($error, 'error');
                $success = false;
            } else {
                // Add reports to ZIP
                $reports = [
                    'daily' => [
                        'url' => "{$baseUrl}/generate_{$export_type}.php?report_type=daily&date={$end_date}&team_id={$team_id}",
                        'filename' => "daily_report_{$end_date}.{$export_type}"
                    ],
                    'monthly' => [
                        'url' => "{$baseUrl}/generate_{$export_type}.php?report_type=monthly&month=" . date('Y-m') . "&team_id={$team_id}",
                        'filename' => "monthly_report_" . date('Y-m') . ".{$export_type}"
                    ],
                    'date_range' => [
                        'url' => "{$baseUrl}/generate_{$export_type}.php?report_type=date_range&start_date={$start_date}&end_date={$end_date}&team_id={$team_id}",
                        'filename' => "date_range_report_{$start_date}_to_{$end_date}.{$export_type}"
                    ],
                    'product_performance' => [
                        'url' => "{$baseUrl}/generate_{$export_type}.php?report_type=product_performance&start_date={$start_date}&end_date={$end_date}&team_id={$team_id}",
                        'filename' => "product_performance_{$start_date}_to_{$end_date}.{$export_type}"
                    ],
                    'advanced_analytics' => [
                        'url' => "{$baseUrl}/generate_{$export_type}.php?report_type=advanced_analytics&team_id={$team_id}",
                        'filename' => "advanced_analytics.{$export_type}"
                    ]
                ];
                
                $downloaded_reports = [];// Use cURL for better error handling and URL encoding
                foreach ($selected_reports as $type) {
                    if (!isset($reports[$type])) {
                        debug_log("Report type $type not found in reports configuration", 'error');
                        continue;
                    }
                    
                    $report = $reports[$type];
                    $url = $report['url'];
                    
                    debug_log("Attempting to download $type report from: $url", 'info');
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increase timeout to 60 seconds
                    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output
                    
                    // Create a file handle for the verbose information
                    $verbose = fopen('php://temp', 'w+');
                    curl_setopt($ch, CURLOPT_STDERR, $verbose);
                    
                    $report_content = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    
                    // Get verbose information
                    rewind($verbose);
                    $verbose_log = stream_get_contents($verbose);
                    fclose($verbose);
                    
                    curl_close($ch);
                    
                    debug_log("HTTP Status: $http_code, Content Type: $content_type", 'info');
                    
                    if ($report_content === false || $http_code !== 200) {
                        $error_detail = empty($curl_error) ? "HTTP code: $http_code" : $curl_error;
                        debug_log("Failed to download $type report: $error_detail", 'error');
                        debug_log("Verbose log: $verbose_log", 'debug');
                        $error .= "Failed to download {$type} report: {$error_detail}. ";
                        $success = false;
                        continue;
                    }
                    
                    // Check if response is too small (might be an error)
                    if (strlen($report_content) < 100) {
                        debug_log("Warning: $type report content is very small (" . strlen($report_content) . " bytes)", 'warning');
                        debug_log("Content: " . substr($report_content, 0, 100), 'debug');
                    }
                    
                    $zip->addFromString($report['filename'], $report_content);
                    debug_log("Successfully added $type report to ZIP", 'success');
                    $downloaded_reports[] = $report['filename'];
                }
                
                // Add a README file
                $readme = "Dr Ecomm Formula Reports\n";
                $readme .= "=======================\n\n";
                $readme .= "Team: " . $team_name . "\n";
                $readme .= "Generated: " . date('Y-m-d H:i:s') . "\n";
                $readme .= "Date Range: " . $start_date . " to " . $end_date . "\n\n";
                $readme .= "Contents:\n";
                
                if (empty($downloaded_reports)) {
                    $readme .= "- No reports were successfully generated\n";
                    debug_log("No reports were successfully generated", 'error');
                } else {
                    foreach ($downloaded_reports as $filename) {
                        $readme .= "- {$filename}\n";
                    }
                    debug_log("Added " . count($downloaded_reports) . " reports to ZIP", 'success');
                }
                
                $zip->addFromString('README.txt', $readme);
                debug_log("Added README.txt to ZIP", 'info');
                
                // Close the ZIP file
                $zip->close();
                debug_log("ZIP file closed", 'info');
                
                // Set success or error message
                if ($success) {
                    $message = "All reports have been exported and compressed into a ZIP file. <a href='{$zip_filename}' class='download-link'>Download ZIP</a>";
                    debug_log("Success message set", 'success');
                } else {
                    if (empty($downloaded_reports)) {
                        $message = "No reports could be generated. Please check your server configuration.";
                        debug_log("Error message set - no reports generated", 'error');
                        if (file_exists($zip_filename)) {
                            unlink($zip_filename); // Delete empty ZIP file
                            debug_log("Deleted empty ZIP file", 'info');
                        }
                    } else {
                        $message = "Some reports could not be generated. <a href='{$zip_filename}' class='download-link'>Download available reports</a>";
                        debug_log("Warning message set - some reports failed", 'warning');
                    }
                }
            }
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download All Reports - Dr Ecomm Formula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        
        .dashboard-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #1E3C72, #2A5298);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-weight: 500;
            font-size: 15px;
        }
        
        .role {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-links li {
            margin: 2px 0;
        }
        
        .nav-links li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-links li a i {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        .nav-links li:hover a {
            background-color: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        
        .nav-links li.active a {
            background-color: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 20px;
            min-height: 100vh;
            flex: 1;
        }
        
        header {
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            margin: 0;
            color: #1E3C72;
        }
        
        section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        section h2 {
            color: #1E3C72;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
            display: block;
        }
        
        input, select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            transition: border 0.3s;
        }
        
        input:focus, select:focus {
            border-color: #1E3C72;
            outline: none;
            box-shadow: 0 0 0 2px rgba(30, 60, 114, 0.2);
        }
        
        button {
            background-color: #1E3C72;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            min-width: 120px;
        }
        
        button:hover {
            background-color: #2A5298;
        }
        
        .excel-btn {
            background-color: #217346;
        }
        
        .excel-btn:hover {
            background-color: #1e653e;
        }
        
        .pdf-btn {
            background-color: #ed2939;
        }
        
        .pdf-btn:hover {
            background-color: #c9252e;
        }
        
        .submit-btn {
            margin-top: 20px;
            padding: 15px 30px;
            font-size: 16px;
        }
        
        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 4px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .download-link {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #1E3C72;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .download-link:hover {
            background-color: #2A5298;
        }
        
        /* Loading spinner */
        .loader-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .loader {
            border: 5px solid #f3f3f3;
            border-radius: 50%;
            border-top: 5px solid #1E3C72;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .logo h2, .user-details, .nav-links li a span {
                display: none;
            }
            
            .nav-links li a i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Debug log styles */
        .debug-log {
            max-height: 400px;
            overflow-y: auto;
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            margin-top: 20px;
        }
        
        .debug-entry {
            margin-bottom: 5px;
            padding: 5px;
            border-left: 3px solid #ccc;
        }
        
        .debug-entry.error {
            border-left-color: #dc3545;
        }
        
        .debug-entry.warning {
            border-left-color: #ffc107;
        }
        
        .debug-entry.success {
            border-left-color: #28a745;
        }
        
        .debug-entry.info {
            border-left-color: #17a2b8;
        }
        
        .debug-time {
            color: #666;
        }
        
        .debug-type {
            font-weight: bold;
        }
        
        .debug-type.error {
            color: #dc3545;
        }
        
        .debug-type.warning {
            color: #ffc107;
        }
        
        .debug-type.success {
            color: #28a745;
        }
        
        .debug-type.info {
            color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h2>Dr Ecomm</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <span class="role"><?php echo htmlspecialchars($team_name); ?></span>
                </div>
            </div>
            
            <ul class="nav-links">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Team Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="index.php">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Product</span>
                    </a>
                </li>
                <li>
                    <a href="user_product_proposed.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>Product Proposals</span>
                    </a>
                </li>
                <li>
                    <a href="user_winning.php">
                        <i class="fa-solid fa-medal"></i>
                        <span>Winning DNA</span>
                    </a>
                </li>
                <li>
                    <a href="team_products.php">
                        <i class="fas fa-box"></i>
                        <span>Team Products</span>
                    </a>
                </li>
                <li>
                    <a href="user_commission.php">
                        <i class="fas fa-calculator"></i>
                        <span>Comission View</span>
                    </a>
                </li>
                <li class="active">
                    <a href="reports.php">
                        <i class="fas fa-file-download"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Download All Reports</h1>
                <a href="reports.php" class="btn" style="background-color: #6c757d; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
            </header>
            
            <section>
                <h2>Batch Export Reports</h2>
                
                <?php if (!empty($message)): ?>
                <div class="message <?php echo (!empty($error)) ? 'error' : ''; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <p style="margin: 15px 0;">Select options below to export multiple reports at once in your preferred format.</p>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?debug=1'); ?>" id="exportForm">
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label for="export_type">Export Format:</label>
                                <select name="export_type" id="export_type">
                                    <option value="excel">Excel (.xls)</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_range">Date Range:</label>
                                <select name="date_range" id="date_range">
                                    <option value="7days">Last 7 Days</option>
                                    <option value="30days">Last 30 Days</option>
                                    <option value="90days">Last 90 Days</option>
                                    <option value="thismonth">This Month</option>
                                    <option value="lastmonth">Last Month</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label>Reports to Include:</label>
                                <div style="margin-top: 10px;">
                                    <div style="margin-bottom: 8px;">
                                        <input type="checkbox" id="include_daily" name="reports[]" value="daily" checked>
                                        <label for="include_daily" style="display: inline; font-weight: normal;">Daily Sales Report</label>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <input type="checkbox" id="include_monthly" name="reports[]" value="monthly" checked>
                                        <label for="include_monthly" style="display: inline; font-weight: normal;">Monthly Performance</label>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <input type="checkbox" id="include_date_range" name="reports[]" value="date_range" checked>
                                        <label for="include_date_range" style="display: inline; font-weight: normal;">Date Range Report</label>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <input type="checkbox" id="include_product" name="reports[]" value="product_performance" checked>
                                        <label for="include_product" style="display: inline; font-weight: normal;">Product Performance</label>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <input type="checkbox" id="include_advanced" name="reports[]" value="advanced_analytics" checked>
                                        <label for="include_advanced" style="display: inline; font-weight: normal;">Advanced Analytics</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="text-align: center;">
                        <input type="hidden" name="batch_export" value="1">
                        <button type="submit" class="submit-btn" id="exportButton">
                            <i class="fas fa-download"></i> Generate Reports
                        </button>
                    </div>
                </form>
                
                <!-- Debug Log Section -->
                <?php if (isset($_GET['debug']) && !empty($debug_log)): ?>
                <div class="debug-log">
                    <h3>Debug Log</h3>
                    <?php foreach ($debug_log as $log): ?>
                    <div class="debug-entry <?php echo htmlspecialchars($log['type']); ?>">
                        <span class="debug-time">[<?php echo htmlspecialchars($log['time']); ?>]</span>
                        <span class="debug-type <?php echo htmlspecialchars($log['type']); ?>"><?php echo strtoupper(htmlspecialchars($log['type'])); ?></span>:
                        <?php echo htmlspecialchars($log['message']); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
        
        <!-- Loading Spinner -->
        <div class="loader-container" id="loaderContainer">
            <div class="loader"></div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const exportForm = document.getElementById('exportForm');
        const exportButton = document.getElementById('exportButton');
        const loaderContainer = document.getElementById('loaderContainer');
        
        exportForm.addEventListener('submit', function(e) {
            // Validate at least one report type is selected
            const selectedReports = document.querySelectorAll('input[name="reports[]"]:checked');
            
            if (selectedReports.length === 0) {
                e.preventDefault();
                alert('Please select at least one report type to export.');
                return;
            }
            
            // Show loading spinner
            loaderContainer.style.display = 'flex';
            exportButton.disabled = true;
            exportButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        });
        
        // Hide loader on page load (in case of page refresh/back navigation)
        window.addEventListener('pageshow', function() {
            loaderContainer.style.display = 'none';
            exportButton.disabled = false;
            exportButton.innerHTML = '<i class="fas fa-download"></i> Generate Reports';
        });
    });
    </script>
</body>
</html>