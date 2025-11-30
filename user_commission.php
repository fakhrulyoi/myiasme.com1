<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dbconn_productProfit.php';

// Initialize variables
$user_id = $_SESSION['user_id'];
$team_id = null;
$team_name = '';
$username = $_SESSION['username'] ?? '';
$error_message = '';

// Fetch user's team information
try {
    $sql_user = "SELECT u.username, u.team_id, t.team_name 
                 FROM users u
                 JOIN teams t ON u.team_id = t.team_id
                 WHERE u.id = ?";
    $stmt_user = $dbconn->prepare($sql_user);
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($row = $result_user->fetch_assoc()) {
        $team_id = $row['team_id'];
        $team_name = $row['team_name'];
        $username = $row['username'];
    } else {
        throw new Exception("User data not found");
    }
    $stmt_user->close();
} catch (Exception $e) {
    $error_message = "Error retrieving user data: " . $e->getMessage();
    error_log($error_message);
}

// Get financial data based on team_id
$financial_data = [];
$total_net_profit = 0;
$years = [];
$months = [];

try {
    // Check if team_id exists
    if ($team_id) {
        // Updated query to focus on net profit directly rather than commission calculation
        $query = "SELECT 
            fr.id_financial_report, 
            fr.report_month, 
            fr.net_profit, 
            fr.operating_profit,
            fr.created_at, 
            t.team_name
        FROM financial_report fr
        JOIN teams t ON fr.team_id = t.team_id
        WHERE fr.team_id = ?
        ORDER BY fr.report_month DESC";
        
        $stmt = $dbconn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $dbconn->error);
        }
        
        $stmt->bind_param("i", $team_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if (!$result) {
            throw new Exception("Get result failed: " . $stmt->error);
        }
        
        // Reset arrays to ensure clean data
        $financial_data = [];
        $years = [];
        $months = [];
        $total_net_profit = 0;

        while ($row = $result->fetch_assoc()) {
            $financial_data[] = $row;
            $total_net_profit += $row['net_profit'];
            
            // Track years and months for filters
            $year = date('Y', strtotime($row['report_month']));
            if (!in_array($year, $years)) {
                $years[] = $year;
            }
            
            $month = date('F Y', strtotime($row['report_month']));
            if (!in_array($month, $months)) {
                $months[] = $month;
            }
        }
        
        // Close the statement
        $stmt->close();
        
        // Sort years in descending order
        rsort($years);
        
        // Debugging logs
        error_log("Fetched financial data for team_id: $team_id");
        error_log("Total financial records: " . count($financial_data));
    } else {
        error_log("No team_id found for user_id: $user_id");
    }
} catch (Exception $e) {
    $error_message = "Error fetching financial data: " . $e->getMessage();
    error_log($error_message);
}

// Calculate average net profit
$avg_net_profit = !empty($financial_data) ? $total_net_profit / count($financial_data) : 0;
$latest_net_profit = !empty($financial_data) ? $financial_data[0]['net_profit'] : 0;
$latest_month = !empty($financial_data) ? date('F Y', strtotime($financial_data[0]['report_month'])) : 'N/A';

// Helper function for active menu items
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page) ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Financial Statement -MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Pre-load JS libraries for PDF and Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 24px;
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
        }/* Main content styles */
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
            font-size: 24px;
            display: flex;
            align-items: center;
        }
        
        header h1 i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .download-options {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: #1E3C72;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2A5298;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-secondary {
            background-color: #f1f1f1;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #e1e1e1;
        }
        
        .form-control {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        /* Stats cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-card-value {
            font-size: 24px;
            font-weight: 700;
            color: #1E3C72;
            margin-bottom: 5px;
        }
        
        .stat-card-subtitle {
            font-size: 13px;
            color: #777;
        }
        
        .stat-card-icon {
            float: right;
            font-size: 36px;
            color: #1E3C72;
            opacity: 0.2;
            margin-top: -55px;
        }
        
        /* Financial statement section */
        .financial-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1E3C72;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #3498db;
        }
        
        /* Filter controls */
        .filter-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 180px;
        }
        
        /* Financial table */
        .financial-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .financial-table th {
            background-color: #f5f7fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            color: #1E3C72;
            border-bottom: 2px solid #eaecef;
        }
        
        .financial-table td {
            padding: 15px;
            border-bottom: 1px solid #eaecef;
            color: #444;
        }
        
        .financial-table tr:last-child td {
            border-bottom: none;
        }
        
        .financial-table tr:hover td {
            background-color: #f9fafb;
        }
        
        .table-month {
            font-weight: 600;
            color: #1E3C72;
        }
        
        .table-team {
            color: #666;
        }
        
        .table-amount {
            font-weight: 600;
            color: #27ae60;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
            display: block;
        }
        
        .empty-state p {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        /* Alert styles */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-info {
            background-color: #e6f3ff;
            color: #0056b3;
            border-left: 4px solid #0077ff;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                transform: translateX(0);
            }
            
            .sidebar.expanded {
                width: 260px;
            }
            
            .logo h2, .user-details, .nav-links li a span {
                display: none;
            }
            
            .sidebar.expanded .logo h2, 
            .sidebar.expanded .user-details, 
            .sidebar.expanded .nav-links li a span {
                display: block;
            }
            
            .nav-links li a i {
                margin-right: 0;
            }
            
            .sidebar.expanded .nav-links li a i {
                margin-right: 12px;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .sidebar.expanded + .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
            }
            
            .avatar {
                margin-right: 0;
            }
            
            .financial-table {
                display: block;
                overflow-x: auto;
            }
            
            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .download-options {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            
            .download-options .form-control {
                width: 100%;
            }
            
            .download-options .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-controls {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
        }

        /* Theme color customization */
        :root {
            --primary-color: #2c3e50;
            --primary-light: #34495e;
            --secondary-color: #3498db;
            --secondary-light: #5dade2;
            --accent-color: #1abc9c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #ecf0f1;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        /* Notification styles */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {transform: translateY(100px); opacity: 0;}
            to {transform: translateY(0); opacity: 1;}
        }
        
        /* Custom notice */
        .data-notice {
            background-color: #f2f8ff;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 14px;
            color: #555;
        }

        /* Tooltip for profit explanation */
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 250px;
            background-color: rgba(0,0,0,0.8);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -125px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            line-height: 1.4;
        }

        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: rgba(0,0,0,0.8) transparent transparent transparent;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-clinic-medical"></i>
                <h2>MYIASME</h2>
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
                <li class="<?php echo isActive('dashboard.php'); ?>">
                    <a href="dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Team Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo isActive('index.php'); ?>">
                    <a href="index.php">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Product</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_product_proposed.php'); ?>">
                    <a href="user_product_proposed.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>Product Proposals</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_winning.php'); ?>">
                    <a href="user_winning.php">
                        <i class="fa-solid fa-medal"></i>
                        <span>Winning DNA</span>
                    </a>
                </li>
                <li class="<?php echo isActive('team_products.php'); ?>">
                    <a href="team_products.php">
                        <i class="fas fa-box"></i>
                        <span>Team Products</span>
                    </a>
                </li>
                  <li class="<?php echo isActive('team_products_status.php'); ?>">
                    <a href="team_products_status.php">
                        <i class="fa-solid fa-bell"></i>
                        <span>Status Products</span>
                    </a>
                </li>
                    <li class="<?php echo isActive('domain.php'); ?>">
                    <a href="domain.php">
                        <i class="fas fa-globe"></i>
                        <span>Domain & Projects</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_commission.php'); ?>">
                    <a href="user_commission.php">
                        <i class="fas fa-calculator"></i>
                        <span>Comission View</span>
                    </a>
                </li>
                   <li class="">
                    <a href="view_stock.php">
                        <i class="fas fa-warehouse"></i>
                        <span>View Stock</span>
                    </a>
                </li>
                <li class="<?php echo isActive('reports.php'); ?>">
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
        </nav><!-- Main Content Container -->
        <div class="main-content">
            <!-- Page Header -->
            <header>
                <h1><i class="fas fa-chart-line"></i> Team Financial Statement</h1>
                <div class="download-options">
                    <select id="downloadFormat" class="form-control">
                        <option value="pdf">PDF Format</option>
                        <option value="excel">Excel Format</option>
                    </select>
                    <button class="btn btn-primary" id="downloadStatement">
                        <i class="fas fa-download"></i> Download Report
                    </button>
                </div>
            </header>
            
            <!-- Team Information Alert -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Team Information:</strong> Viewing financial data for <?php echo htmlspecialchars($team_name); ?>.
                    <span class="tooltip">How is this calculated?
                        <span class="tooltiptext">Net Profit is calculated after deducting all operating expenses from Gross Profit. It represents the team's overall financial performance.</span>
                    </span>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-title">Total Net Profit</div>
                    <div class="stat-card-value">RM <?php echo number_format($total_net_profit, 2); ?></div>
                    <div class="stat-card-subtitle">Lifetime earnings</div>
                    <div class="stat-card-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-title">Latest Month</div>
                    <div class="stat-card-value">
                        RM <?php echo number_format($latest_net_profit, 2); ?>
                    </div>
                    <div class="stat-card-subtitle"><?php echo $latest_month; ?></div>
                    <div class="stat-card-icon"><i class="fas fa-calendar-alt"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-title">Average Monthly</div>
                    <div class="stat-card-value">RM <?php echo number_format($avg_net_profit, 2); ?></div>
                    <div class="stat-card-subtitle">Based on <?php echo count($financial_data); ?> months</div>
                    <div class="stat-card-icon"><i class="fas fa-chart-bar"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-title">Records</div>
                    <div class="stat-card-value"><?php echo count($financial_data); ?></div>
                    <div class="stat-card-subtitle">Financial reports</div>
                    <div class="stat-card-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
            </div>
            
            <!-- Financial Statement -->
            <div class="financial-container">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Financial Statement</h2>
                    
                    <div class="filter-controls">
                        <select class="filter-select" id="yearFilter">
                            <option value="all">All Years</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <?php if (!empty($error_message)): ?>
                <div class="data-notice">
                    <p>There was an issue retrieving your financial data. Please refresh the page or contact support if the issue persists.</p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($financial_data)): ?>
                <div class="table-responsive">
                    <table class="financial-table" id="financialTable">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Operating Profit</th>
                                <th>Net Profit</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($financial_data as $row): ?>
                            <tr data-year="<?php echo date('Y', strtotime($row['report_month'])); ?>">
                                <td class="table-month"><?php echo date('F Y', strtotime($row['report_month'])); ?></td>
                                <td>RM <?php echo number_format($row['operating_profit'], 2); ?></td>
                                <td class="table-amount">RM <?php echo number_format($row['net_profit'], 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p>No financial data available yet.</p>
                    <p>Your financial data will appear here once your team leader submits financial reports.</p>
                    <a href="#" class="btn btn-secondary" id="checkLatestBtn">
                        <i class="fas fa-sync"></i> Check for Latest Data
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar on mobile
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        
        // Create toggle button for mobile
        const toggleSidebarBtn = document.createElement('button');
        toggleSidebarBtn.classList.add('toggle-sidebar-btn');
        toggleSidebarBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleSidebarBtn.style.position = 'fixed';
        toggleSidebarBtn.style.top = '15px';
        toggleSidebarBtn.style.left = '15px';
        toggleSidebarBtn.style.zIndex = '1000';
        toggleSidebarBtn.style.background = 'var(--primary-color)';
        toggleSidebarBtn.style.color = 'white';
        toggleSidebarBtn.style.border = 'none';
        toggleSidebarBtn.style.borderRadius = '5px';
        toggleSidebarBtn.style.width = '40px';
        toggleSidebarBtn.style.height = '40px';
        toggleSidebarBtn.style.display = 'none';
        
        document.body.appendChild(toggleSidebarBtn);
        
        toggleSidebarBtn.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
            if (sidebar.classList.contains('expanded')) {
                mainContent.style.marginLeft = '260px';
            } else {
                mainContent.style.marginLeft = '70px';
            }
        });
        
        // Show toggle button on mobile
        function checkMobile() {
            if (window.innerWidth <= 768) {
                toggleSidebarBtn.style.display = 'block';
                sidebar.classList.remove('expanded');
                mainContent.style.marginLeft = '70px';
            } else {
                toggleSidebarBtn.style.display = 'none';
                sidebar.classList.remove('expanded');
                mainContent.style.marginLeft = '260px';
            }
        }
        
        window.addEventListener('resize', checkMobile);
        checkMobile(); // Initial check
        
        // Filtering functionality
        const yearFilter = document.getElementById('yearFilter');
        const financialTable = document.getElementById('financialTable');
        
        function applyFilters() {
            if (!financialTable) return;
            
            const selectedYear = yearFilter.value;
            
            const rows = financialTable.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const yearMatch = selectedYear === 'all' || row.getAttribute('data-year') === selectedYear;
                
                if (yearMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        if (yearFilter) {
            yearFilter.addEventListener('change', applyFilters);
        }
        
        // Check for latest data button functionality
        const checkLatestBtn = document.getElementById('checkLatestBtn');
        if (checkLatestBtn) {
            checkLatestBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Show loading animation
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
                
                // Fetch the latest data
                fetch('get_latest_financial.php?team_id=<?php echo $team_id; ?>')
                    .then(response => response.json())
                    .then(data => {
                        // Reset button
                        this.innerHTML = '<i class="fas fa-sync"></i> Check for Latest Data';
                        
                        if (data.success) {
                            if (data.new_data) {
                                // Reload the page to show new data
                                showNotification('New financial data found! Refreshing...', 'success');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showNotification('No new financial data found', 'info');
                            }
                        } else {
                            showNotification('Error checking for new data: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.innerHTML = '<i class="fas fa-sync"></i> Check for Latest Data';
                        showNotification('Error connecting to server. Please try again.', 'error');
                    });
            });
        }
        
        // Download reports functionality
        const downloadBtn = document.getElementById('downloadStatement');
        const downloadFormat = document.getElementById('downloadFormat');
        
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function() {
                const format = downloadFormat.value;
                
                if (format === 'pdf') {
                    downloadPDF();
                } else {
                    downloadExcel();
                }
            });
        }
        
        // Function to download as PDF
        function downloadPDF() {
            try {
                const { jsPDF } = window.jspdf;
                if (!jsPDF) {
                    throw new Error("PDF library not loaded");
                }
                
                const doc = new jsPDF();
                
                // Add header
                doc.setFontSize(18);
                doc.setTextColor(30, 60, 114);
                doc.text('Financial Statement', 105, 20, { align: 'center' });
                
                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text('MYIASME', 105, 30, { align: 'center' });
                doc.text('User: <?php echo htmlspecialchars($username); ?>', 105, 40, { align: 'center' });
                doc.text('Team: <?php echo htmlspecialchars($team_name); ?>', 105, 50, { align: 'center' });
                doc.text('Generated on: <?php echo date("d F Y"); ?>', 105, 60, { align: 'center' });
                
                // Add line
                doc.line(20, 65, 190, 65);
                
                // Section headers
                doc.setFontSize(14);
                doc.setTextColor(0, 102, 204);
                doc.text('Financial Summary', 20, 75);
                
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text('Total Net Profit: RM <?php echo number_format($total_net_profit, 2); ?>', 20, 85);
                doc.text('Average Monthly Net Profit: RM <?php echo number_format($avg_net_profit, 2); ?>', 20, 95);
                doc.text('Latest Month (<?php echo $latest_month; ?>): RM <?php echo number_format($latest_net_profit, 2); ?>', 20, 105);
                
                <?php if (!empty($financial_data)): ?>
                // Create table data
                const tableColumn = ["Month", "Operating Profit (RM)", "Net Profit (RM)", "Date Created"];
                const tableRows = [];
                
                <?php foreach ($financial_data as $row): ?>
                tableRows.push([
                    "<?php echo date('M Y', strtotime($row['report_month'])); ?>",
                    "<?php echo number_format($row['operating_profit'], 2); ?>",
                    "<?php echo number_format($row['net_profit'], 2); ?>",
                    "<?php echo date('d M Y', strtotime($row['created_at'])); ?>"
                ]);
                <?php endforeach; ?>
                
                // Create the table in the PDF
                doc.autoTable({
                    startY: 115,
                    head: [tableColumn],
                    body: tableRows,
                    theme: 'grid',
                    headStyles: { 
                        fillColor: [30, 60, 114],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold'
                    },
                    styles: { 
                        cellPadding: 5,
                        fontSize: 10,
                        overflow: 'linebreak'
                    },
                    columnStyles: {
                        2: { fontStyle: 'bold', textColor: [39, 174, 96] } // Style for net profit amount
                    }
                });
                
                <?php else: ?>
                // If no data, add a note
                doc.setFontSize(12);
                doc.setTextColor(150, 150, 150);
                doc.text('No financial data available.', 105, 125, { align: 'center' });
                <?php endif; ?>
                
                // Add footer
                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setFontSize(10);
                    doc.setTextColor(100, 100, 100);
                    doc.text('Page ' + i + ' of ' + pageCount, 105, doc.internal.pageSize.height - 10, { align: 'center' });
                    doc.text('© MYIASME - <?php echo date("Y"); ?>', 105, doc.internal.pageSize.height - 20, { align: 'center' });
                }
                
                // Save the PDF
                doc.save('Financial_Statement_<?php echo date("Y-m"); ?>.pdf');
                
                showNotification('PDF downloaded successfully', 'success');
            } catch (error) {
                console.error('PDF generation error:', error);
                showNotification('Error generating PDF: ' + error.message, 'error');
            }
        }
        
        // Function to download as Excel (XLSX)
        function downloadExcel() {
            try {
                // Check if XLSX is available
                if (typeof XLSX === 'undefined') {
                    throw new Error('Excel library not loaded');
                }
                
                <?php if (empty($financial_data)): ?>
                showNotification('No data available to download', 'warning');
                return;
                <?php endif; ?>
                
                // Create workbook and worksheet
                const wb = XLSX.utils.book_new();
                
                // Define data for summary sheet
                const summaryData = [
                    ['MYIASME - Financial Statement'],
                    ['User: <?php echo htmlspecialchars($username); ?>'],
                    ['Team: <?php echo htmlspecialchars($team_name); ?>'],
                    ['Generated On: <?php echo date("d F Y"); ?>'],
                    [''],
                    ['SUMMARY'],
                    ['Total Net Profit', 'RM <?php echo number_format($total_net_profit, 2); ?>'],
                    ['Average Monthly', 'RM <?php echo number_format($avg_net_profit, 2); ?>'],
                    ['Latest Month (<?php echo $latest_month; ?>)', 'RM <?php echo number_format($latest_net_profit, 2); ?>'],
                    ['Total Months', '<?php echo count($financial_data); ?>'],
                    ['']
                ];
                
                // Create summary worksheet
                const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
                XLSX.utils.book_append_sheet(wb, summaryWs, "Summary");
                
                // Define data for detailed statement sheet
                const detailData = [
                    ['Month', 'Operating Profit (RM)', 'Net Profit (RM)', 'Date Created']
                ];
                
                <?php foreach ($financial_data as $row): ?>
                detailData.push([
                    '<?php echo date('F Y', strtotime($row['report_month'])); ?>',
                    '<?php echo number_format($row['operating_profit'], 2); ?>',
                    '<?php echo number_format($row['net_profit'], 2); ?>',
                    '<?php echo date('d M Y', strtotime($row['created_at'])); ?>'
                ]);
                <?php endforeach; ?>
                
                // Create detailed statement worksheet
                const detailWs = XLSX.utils.aoa_to_sheet(detailData);
                XLSX.utils.book_append_sheet(wb, detailWs, "Financial Details");
                
                // Generate Excel file
                XLSX.writeFile(wb, 'Financial_Statement_<?php echo date("Y-m"); ?>.xlsx');
                
                showNotification('Excel file downloaded successfully', 'success');
            } catch (error) {
                console.error('Excel generation error:', error);
                showNotification('Error generating Excel file: ' + error.message, 'error');
            }
        }
        
        // Function to show notifications
        function showNotification(message, type = 'info') {
            // Remove any existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'notification';
            
            // Set background color based on type
            let bgColor, color, icon;
            switch (type) {
                case 'success':
                    bgColor = '#d4edda';
                    color = '#155724';
                    icon = 'check-circle';
                    break;
                case 'error':
                    bgColor = '#f8d7da';
                    color = '#721c24';
                    icon = 'exclamation-circle';
                    break;
                case 'warning':
                    bgColor = '#fff3cd';
                    color = '#856404';
                    icon = 'exclamation-triangle';
                    break;
                default: // info
                    bgColor = '#e6f3ff';
                    color = '#0056b3';
                    icon = 'info-circle';
            }
            
            // Add content and styling
            notification.innerHTML = `
                <div style="position: fixed; bottom: 20px; right: 20px; background: ${bgColor}; color: ${color}; 
                            padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
                            z-index: 9999; max-width: 300px; animation: slideIn 0.3s ease-out;">
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="fas fa-${icon}" style="margin-right: 10px;"></i>
                        <strong>Notification</strong>
                        <span style="margin-left: auto; cursor: pointer;" onclick="this.parentNode.parentNode.remove()">×</span>
                    </div>
                    <p>${message}</p>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 4000);
        }
        
        // Check for recent financial updates
        function checkForRecentUpdates() {
            <?php if (!empty($financial_data) && isset($financial_data[0])): ?>
            const latestDate = new Date("<?php echo $financial_data[0]['created_at']; ?>");
            const now = new Date();
            const daysDifference = Math.floor((now - latestDate) / (1000 * 60 * 60 * 24));
            
            // If the latest report was added in the last 2 days, show a notification
            if (daysDifference <= 2) {
                setTimeout(() => {
                    showNotification('You have a recent financial update from <?php echo date('F Y', strtotime($financial_data[0]['report_month'])); ?> showing net profit of RM <?php echo number_format($financial_data[0]['net_profit'], 2); ?>', 'success');
                }, 1500);
            }
            <?php endif; ?>
        }
        
        // Initialize recent updates check
        checkForRecentUpdates();
    });
    </script>
</body>
</html>