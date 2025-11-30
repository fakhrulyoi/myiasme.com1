<?php
require 'auth.php';
require 'dbconn_productProfit.php';
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

// Check if user is admin
if (!$is_admin) {
    header("Location: team_products.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Calculator - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            --primary-color: #2c3e50;
            --primary-light: #34495e;
            --secondary-color: #3498db;
            --secondary-light: #5dade2;
            --accent-color: #1abc9c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #ecf0f1;
            --border-radius: 8px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
            
            /* Colors for stat cards */
            --sales-color: #3498db;
            --profit-color: #1abc9c;
            --orders-color: #9b59b6;
            --units-color: #f39c12;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: var(--dark-text);
        }
        
        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-light));
            color: var(--light-text);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            box-shadow: var(--box-shadow);
            overflow-y: auto;
            transition: var(--transition);
        }
        
        .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-weight: 500;
            font-size: 15px;
            margin-bottom: 3px;
        }
        
        .role {
            font-size: 12px;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-links li {
            margin: 5px 10px;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .nav-links li a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--light-text);
            text-decoration: none;
            transition: var(--transition);
            border-radius: var(--border-radius);
        }
        
        .nav-links li a i {
            margin-right: 12px;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .nav-links li:hover a {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .nav-links li.active a {
            background-color: var(--secondary-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .nav-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 20px;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.6);
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 25px;
            min-height: 100vh;
            flex: 1;
            transition: var(--transition);
        }
        
        /* Page header */
        .page-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 {
            font-size: 26px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-header h1 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        /* Alert messages */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            border-left: 4px solid;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        /* Form styling */
        .form-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: var(--transition);
        }
        
        .form-container:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-container h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .form-container h3 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .form-group {
            flex: 1 0 300px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-actions {
            margin-top: 20px;
        }
        
        /* Button styles */
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            border: none;
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-light);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Download options */
        .download-options {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Financial report specific styles */
        .section-header {
            background-color: var(--light-bg);
            padding: 10px 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--secondary-color);
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .section-header i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: var(--light-bg);
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-title {
            font-size: 14px;
            color: #555;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .financial-section {
            margin-bottom: 25px;
        }
        
        .form-row-divider {
            width: 100%;
            height: 1px;
            background-color: #eee;
            margin: 15px 0;
        }
        
        .commission-section {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-top: 20px;
            border: 1px solid #c0d8ff;
        }
        
        .commission-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .commission-title i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .commission-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 15px;
            text-align: center;
        }
        
        .highlighted-input {
            background-color: #f0f7ff;
            font-weight: 500;
        }
        
        /* Template-specific styles */
        .template-style {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .template-style th, .template-style td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .template-style th {
            background-color: #f5f5f5;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .template-style tr:hover {
            background-color: #f9f9f9;
        }
        
        .total-row {
            background-color: #f0f7ff;
            font-weight: 600;
        }
        
        .subtotal-row {
            background-color: #f9f9f9;
            font-weight: 500;
        }
        
        .indented {
            padding-left: 25px;
        }
        
        /* Individual commission form */
        .individual-commission-form {
            margin-top: 30px;
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: var(--border-radius);
            border: 1px solid #b8daff;
            transition: background-color 0.3s ease;
        }
        
        .individual-commission-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .individual-commission-title i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                transform: translateX(0);
            }
            
            .sidebar.expanded {
                width: 280px;
            }
            
            .logo h2, .user-details, .nav-links li a span, .nav-section-title {
                display: none;
            }
            
            .sidebar.expanded .logo h2, 
            .sidebar.expanded .user-details, 
            .sidebar.expanded .nav-links li a span,
            .sidebar.expanded .nav-section-title {
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
                margin-left: 280px;
                width: calc(100% - 280px);
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .download-options {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .download-options select {
                margin-bottom: 10px;
                width: 100% !important;
            }
            
            .template-style {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        
        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-clinic-medical"></i>
                <h2>MYIASME</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo $username; ?></span>
                    <span class="role"><?php echo $is_admin ? 'Administrator' : 'Team Member'; ?></span>
                </div>
            </div>
            
            <ul class="nav-links">
                <li>
                    <a href="admin_dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
              
                <li>
                    <a href="teams.php">
                        <i class="fas fa-users"></i>
                        <span>Teams</span>
                    </a>
                </li>
                <li>
                    <a href="all_products.php">
                        <i class="fas fa-boxes"></i>
                        <span>All Products</span>
                    </a>
                </li>
              
                
                <div class="nav-section">
                    <p class="nav-section-title">Tools</p>
                    <li class="active">
                        <a href="commission_calculator.php">
                            <i class="fas fa-calculator"></i>
                            <span>Commission Calculator</span>
                        </a>
                    </li>
                                 <li class="">
            <a href="stock_management.php">
                <i class="fas fa-warehouse"></i>
                <span>Stock Management</span>
            </a>
        </li>
                    <li>
                        <a href="admin_reports.php">
                            <i class="fas fa-file-download"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    
                </div>
                
                <div class="nav-section">
                    <p class="nav-section-title">Account</p>
                    
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </div>
            </ul>
        </nav>
        
        <!-- Main Content Container -->
        <main class="main-content" id="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <h1><i class="fas fa-calculator"></i> Commission Calculator</h1>
                <div class="download-options">
                    <select id="downloadFormat" class="form-control" style="display: inline-block; width: auto; margin-right: 10px;">
                        <option value="excel">Excel Format</option>
                        <option value="pdf">PDF Format</option>
                    </select>
                    <button class="btn btn-primary" id="downloadReportBtn">
                        <i class="fas fa-download"></i> Download Report
                    </button>
                </div>
            </header>
            
            <!-- Alert placeholder -->
            <div id="alertsContainer"></div>
            
            <!-- Summary Statistics -->
            <div class="summary-stats">
                <div class="stat-card">
                    <div class="stat-title">Net Revenue</div>
                    <div class="stat-value" id="netRevenueDisplay">RM 0.00</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Gross Profit</div>
                    <div class="stat-value" id="grossProfitDisplay">RM 0.00</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Operating Profit</div>
                    <div class="stat-value" id="operatingProfitDisplay">RM 0.00</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Net Profit</div>
                    <div class="stat-value" id="netProfitDisplay">RM 0.00</div>
                </div>
            </div>
            
            <!-- Financial Report Form -->
            <form id="financialReportForm" class="form-container" action="save_financial_report.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="teamSelect">Team</label>
                        <select id="teamSelect" name="team_id" class="form-control" required>
                            <option value="">Select Team</option>
                            <option value="1" selected>Team A</option>
                            <option value="2">Team B1</option>
                            <option value="16">Team B2</option>
                            <option value="21">Team B3</option>
                            
                            <option value="3">Team C</option>
                            <option value="4">Team D</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reportMonth">Report Month</label>
                        <input type="month" id="reportMonth" name="report_month" class="form-control" value="2025-04" required>
                    </div>
                    <div class="form-group">
                        <label for="ssmNumber">SSM Number</label>
                        <input type="text" id="ssmNumber" name="ssm_number" class="form-control" placeholder="Enter company registration number">
                    </div>
                </div>
                
                <!-- Template-Style Financial Statement -->
                <h3 class="section-header"><i class="fas fa-file-invoice-dollar"></i> Financial Statement</h3>
                
                <table class="template-style">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Amount (RM)</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Revenue Section -->
                        <tr>
                            <td><strong>Net Revenue</strong></td>
                            <td>
                                <input type="number" id="netRevenue" name="net_revenue" class="form-control" value="0.00" step="0.01" required>
                            </td>
                            <td>Total revenue after platform fees</td>
                        </tr>
                        
                        <!-- Direct Cost (COGS) Section -->
                        <tr>
                            <td><strong>Direct Cost (COGS)</strong> (-)</td>
                            <td>
                                <input type="number" id="directCost" name="direct_cost" class="form-control" value="0.00" step="0.01" required>
                            </td>
                            <td>Direct cost of products sold</td>
                        </tr>
                        
                        <!-- Gross Profit -->
                        <tr class="total-row">
                            <td><strong>Gross Profit</strong></td>
                            <td>
                                <input type="number" id="grossProfit" name="gross_profit" class="form-control highlighted-input" value="0.00" step="0.01" readonly>
                            </td>
                            <td>Net Revenue - Direct Cost</td>
                        </tr>
                        
                        <!-- Operating Expenses Section -->
                        <tr>
                            <td><strong>Ads Cost</strong></td>
                            <td>
                                <input type="number" id="adsCost" name="ads_cost" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Facebook ads, Google ads, etc.</td>
                        </tr>
                        <tr>
                            <td><strong>Shipping Fee (NINJAVAN)</strong></td>
                            <td>
                                <input type="number" id="shippingFee" name="shipping_fee" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>NINJA or other courier services</td>
                        </tr>
                        <tr>
                            <td><strong>Web Hosting/domain</strong></td>
                            <td>
                                <input type="number" id="webHosting" name="web_hosting" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Monthly web hosting costs</td>
                        </tr>
                        <tr>
                            <td><strong>Operating Cost</strong></td>
                            <td>
                            <input type="number" id="operatingCost" name="operating_cost" class="form-control highlighted-input" value="0.00" step="0.01" readonly>
                            </td>
                            <td><td>Auto-calculated: Ads + Shipping + Web Hosting</td></td>
                        </tr>
                        
                        <!-- Additional Expense Items -->
                        <tr>
                            <td><strong>Salary</strong></td>
                            <td>
                                <input type="number" id="salary" name="salary" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Employee salaries</td>
                        </tr>
                        <tr>
                            <td><strong>Kos Wrap Parcel (Completed)</strong></td>
                            <td>
                                <input type="number" id="kosWrapParcel" name="kos_wrap_parcel" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Parcel wrapping costs</td>
                        </tr>
                        <tr>
                            <td><strong>Commission Parcel</strong></td>
                            <td>
                                <input type="number" id="commissionParcel" name="commission_parcel" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Parcel shipping commissions</td>
                        </tr>
                        <tr>
                            <td><strong>Training</strong></td>
                            <td>
                                <input type="number" id="training" name="training" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Staff training expenses</td>
                        </tr>
                        <tr>
                            <td><strong>INTERNET</strong></td>
                            <td>
                                <input type="number" id="internet" name="internet" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Internet service expenses</td>
                        </tr>
                        <tr>
                            <td><strong>Bil Postpaid</strong></td>
                            <td>
                                <input type="number" id="bilPostpaid" name="bil_postpaid" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Phone/communication expenses</td>
                        </tr>
                        <tr>
                            <td><strong>Rent</strong></td>
                            <td>
                                <input type="number" id="rent" name="rent" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Office/workspace rent</td>
                        </tr>
                        <tr>
                            <td><strong>Utilities</strong></td>
                            <td>
                                <input type="number" id="utilities" name="utilities" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Water, electricity, etc.</td>
                        </tr>
                        <tr>
                            <td><strong>Maintenance and Repair</strong></td>
                            <td>
                                <input type="number" id="maintenanceRepair" name="maintenance_repair" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Equipment and facility maintenance</td>
                        </tr>
                        <tr>
                            <td><strong>Staff Pay and Claim</strong></td>
                            <td>
                                <input type="number" id="staffPayClaim" name="staff_pay_claim" class="form-control" value="0.00" step="0.01">
                            </td>
                            <td>Staff reimbursements and claims</td>
                        </tr>
                        
                        <tr>
                            <td><strong>Total Operating Expenses</strong></td>
                            <td>
                                <input type="number" id="totalOperatingExpenses" name="total_operating_expenses" class="form-control highlighted-input" value="0.00" step="0.01" readonly>
                            </td>
                            <td>Sum of all operating expenses</td>
                        </tr>
                        
                        <!-- Operating Profit -->
                        <tr class="total-row">
                            <td><strong>Operating Profit</strong></td>
                            <td>
                                <input type="number" id="operatingProfit" name="operating_profit" class="form-control highlighted-input" value="0.00" step="0.01" readonly>
                            </td>
                            <td>Gross Profit - Total Operating Expenses</td>
                        </tr>
                        
                        <!-- Net Profit (NEW) -->
                        <tr class="total-row">
                            <td><strong>Net Profit<td><strong>Net Profit</strong></td>
                            <td>
                                <input type="number" id="netProfit" name="net_profit" class="form-control highlighted-input" value="0.00" step="0.01" readonly>
                            </td>
                            <td>Operating Profit - This will be visible to team members</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Individual Commission Calculator Section (NEW) -->
                <!-- Add instructions alert -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> To calculate commissions for multiple team members using the same financial data, simply enter each person's name, adjust the commission rate if needed, and click "Save Financial Report" for each person.
                </div>
                
                <div class="individual-commission-form">
                    <h3 class="individual-commission-title"><i class="fas fa-user-check"></i> Individual Commission Calculator</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="personName">Person Name</label>
                            <input type="text" id="personName" name="person_name" class="form-control" placeholder="Enter person name" required>
                        </div>
                        <div class="form-group">
                            <label for="customCommissionRate">Commission Rate (%)</label>
                            <input type="number" id="customCommissionRate" name="custom_commission_rate" class="form-control" min="0" max="100" step="0.01" value="5" required>
                        </div>
                    </div>
                    
                    <div class="commission-section">
                        <div class="commission-title">
                            <i class="fas fa-money-bill-wave"></i> Commission Calculation
                        </div>
                        <p>Based on Net Profit: <strong>RM <span id="netProfitValue">0.00</span></strong></p>
                        <p>Commission Rate: <strong><span id="commissionRateValue">5</span>%</strong></p>
                        <div class="commission-value" id="individualCommissionDisplay">RM 0.00</div>
                        <input type="hidden" id="individualCommissionAmount" name="individual_commission_amount" value="0.00">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" id="saveReportBtn">
                        <i class="fas fa-save"></i> Save Financial Report
                    </button>
                </div>
            </form>
        </main>
    </div>

    <!-- Add script for client-side calculations and export functionality -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        // Dynamic Calculations and Form Interactions
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Element References
            const form = document.getElementById('financialReportForm');
            const teamSelect = document.getElementById('teamSelect');
            const alertsContainer = document.getElementById('alertsContainer');
            
            // Show success message if in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                showAlert('Financial report saved successfully!', 'success');
                
                if (urlParams.get('report_id')) {
                    showAlert('Report ID: ' + urlParams.get('report_id'), 'success');
                }
            }
            
            if (urlParams.get('error')) {
                showAlert('Error: ' + urlParams.get('error'), 'danger');
            }
            
            // Input Fields - Original
            const netRevenueInput = document.getElementById('netRevenue');
            const directCostInput = document.getElementById('directCost');
            const adsCostInput = document.getElementById('adsCost');
            const shippingFeeInput = document.getElementById('shippingFee');
            const webHostingInput = document.getElementById('webHosting');
            const operatingCostInput = document.getElementById('operatingCost');
            
            // New Input Fields - Additional Expenses
            const salaryInput = document.getElementById('salary');
            const kosWrapParcelInput = document.getElementById('kosWrapParcel');
            const commissionParcelInput = document.getElementById('commissionParcel');
            const trainingInput = document.getElementById('training');
            const internetInput = document.getElementById('internet');
            const bilPostpaidInput = document.getElementById('bilPostpaid');
            const rentInput = document.getElementById('rent');
            const utilitiesInput = document.getElementById('utilities');
            const maintenanceRepairInput = document.getElementById('maintenanceRepair');
            const staffPayClaimInput = document.getElementById('staffPayClaim');
            
            const totalOperatingExpensesInput = document.getElementById('totalOperatingExpenses');
            
            // Display Fields
            const grossProfitInput = document.getElementById('grossProfit');
            const operatingProfitInput = document.getElementById('operatingProfit');
            const netProfitInput = document.getElementById('netProfit');
            
            // Individual Commission Fields
            const personNameInput = document.getElementById('personName');
            const customCommissionRateInput = document.getElementById('customCommissionRate');
            const individualCommissionAmountInput = document.getElementById('individualCommissionAmount');
            
            // Summary Display Fields
            const netRevenueDisplay = document.getElementById('netRevenueDisplay');
            const grossProfitDisplay = document.getElementById('grossProfitDisplay');
            const operatingProfitDisplay = document.getElementById('operatingProfitDisplay');
            const netProfitDisplay = document.getElementById('netProfitDisplay');
            const individualCommissionDisplay = document.getElementById('individualCommissionDisplay');
            const netProfitValue = document.getElementById('netProfitValue');
            const commissionRateValue = document.getElementById('commissionRateValue');
            
            // Download Button
            const downloadReportBtn = document.getElementById('downloadReportBtn');
            const downloadFormatSelect = document.getElementById('downloadFormat');
            
            // Alert Function
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type}`;
                alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
                
                alertsContainer.appendChild(alertDiv);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
            
            // Calculation Functions
            function calculateGrossProfit() {
    const netRevenue = parseFloat(netRevenueInput.value) || 0;
    const directCost = parseFloat(directCostInput.value) || 0;
    return Math.max(netRevenue - directCost, 0);
}
function calculateOperatingCost() {
    const adsCost = parseFloat(adsCostInput.value) || 0;
    const shippingFee = parseFloat(shippingFeeInput.value) || 0;
    const webHosting = parseFloat(webHostingInput.value) || 0;
    
    // Operating cost is the sum of these three items
    return adsCost + shippingFee + webHosting;
}
            function calculateTotalOperatingExpenses() {
                // Original expenses
                const adsCost = parseFloat(adsCostInput.value) || 0;
                const shippingFee = parseFloat(shippingFeeInput.value) || 0;
                const webHosting = parseFloat(webHostingInput.value) || 0;
                const operatingCost = parseFloat(operatingCostInput.value) || 0;
                
                // Additional expenses
                const salary = parseFloat(salaryInput.value) || 0;
                const kosWrapParcel = parseFloat(kosWrapParcelInput.value) || 0;
                const commissionParcel = parseFloat(commissionParcelInput.value) || 0;
                const training = parseFloat(trainingInput.value) || 0;
                const internet = parseFloat(internetInput.value) || 0;
                const bilPostpaid = parseFloat(bilPostpaidInput.value) || 0;
                const rent = parseFloat(rentInput.value) || 0;
                const utilities = parseFloat(utilitiesInput.value) || 0;
                const maintenanceRepair = parseFloat(maintenanceRepairInput.value) || 0;
                const staffPayClaim = parseFloat(staffPayClaimInput.value) || 0;
                
                return adsCost + shippingFee + webHosting + operatingCost + 
                       salary + kosWrapParcel + commissionParcel + training + 
                       internet + bilPostpaid + rent + utilities + 
                       maintenanceRepair + staffPayClaim;
            }
            
            function calculateOperatingProfit() {
    const grossProfit = calculateGrossProfit();
    const operatingCost = calculateOperatingCost();
    return Math.max(grossProfit - operatingCost, 0);
}
            
function calculateNetProfit() {
    const grossProfit = calculateGrossProfit();
    const totalExpenses = calculateTotalExpenses();
    return Math.max(grossProfit - totalExpenses, 0);
}
            
function calculateIndividualCommission() {
    const netProfit = calculateNetProfit();
    const commissionRate = parseFloat(customCommissionRateInput.value) / 100;
    return netProfit * commissionRate;
}
            function calculateTotalExpenses() {
    // Get operating cost (ads, shipping, web hosting)
    const operatingCost = calculateOperatingCost();
    
    // Additional expenses
    const salary = parseFloat(salaryInput.value) || 0;
    const kosWrapParcel = parseFloat(kosWrapParcelInput.value) || 0;
    const commissionParcel = parseFloat(commissionParcelInput.value) || 0;
    const training = parseFloat(trainingInput.value) || 0;
    const internet = parseFloat(internetInput.value) || 0;
    const bilPostpaid = parseFloat(bilPostpaidInput.value) || 0;
    const rent = parseFloat(rentInput.value) || 0;
    const utilities = parseFloat(utilitiesInput.value) || 0;
    const maintenanceRepair = parseFloat(maintenanceRepairInput.value) || 0;
    const staffPayClaim = parseFloat(staffPayClaimInput.value) || 0;
    
    // Total expenses includes operating cost plus all other expense items
    return operatingCost + salary + kosWrapParcel + commissionParcel + 
           training + internet + bilPostpaid + rent + utilities + 
           maintenanceRepair + staffPayClaim;
}
            // Update Functions
            function updateAllCalculations() {
    // Calculate and update gross profit
    const grossProfit = calculateGrossProfit();
    grossProfitInput.value = grossProfit.toFixed(2);
    grossProfitDisplay.textContent = `RM ${grossProfit.toFixed(2)}`;
    
    // Calculate and update operating cost
    const operatingCost = calculateOperatingCost();
    operatingCostInput.value = operatingCost.toFixed(2); // Auto-calculate operating cost
    
    // Calculate and update operating profit
    const operatingProfit = calculateOperatingProfit();
    operatingProfitInput.value = operatingProfit.toFixed(2);
    operatingProfitDisplay.textContent = `RM ${operatingProfit.toFixed(2)}`;
    
    // Calculate and update total expenses
    const totalExpenses = calculateTotalExpenses();
    totalOperatingExpensesInput.value = totalExpenses.toFixed(2);
    
    // Calculate and update net profit
    const netProfit = calculateNetProfit();
    netProfitInput.value = netProfit.toFixed(2);
    netProfitDisplay.textContent = `RM ${netProfit.toFixed(2)}`;
    netProfitValue.textContent = netProfit.toFixed(2);
    
    // Calculate and update individual commission amount
    const individualCommission = calculateIndividualCommission();
    individualCommissionAmountInput.value = individualCommission.toFixed(2);
    individualCommissionDisplay.textContent = `RM ${individualCommission.toFixed(2)}`;
    
    // Update commission rate display
    commissionRateValue.textContent = customCommissionRateInput.value;
    
    // Update net revenue display
    const netRevenue = parseFloat(netRevenueInput.value) || 0;
    netRevenueDisplay.textContent = `RM ${netRevenue.toFixed(2)}`;
}
            
            // Event Listeners for Recalculation
            const inputFields = [
                netRevenueInput, directCostInput, adsCostInput, 
                shippingFeeInput, webHostingInput, operatingCostInput,
                salaryInput, kosWrapParcelInput, commissionParcelInput,
                trainingInput, internetInput, bilPostpaidInput,
                rentInput, utilitiesInput, maintenanceRepairInput,
                staffPayClaimInput, customCommissionRateInput
            ];
            
            inputFields.forEach(input => {
                input.addEventListener('input', updateAllCalculations);
            });
            
            // Team Selection Handler Function
            function handleTeamChange(teamId) {
                // Update the form action to include the team_id
                const formAction = form.getAttribute('action').split('?')[0];
                form.setAttribute('action', `${formAction}?team_id=${teamId}`);
                
                // Reset form fields to empty or default values
                netRevenueInput.value = "0.00";
                directCostInput.value = "0.00";
                adsCostInput.value = "0.00";
                shippingFeeInput.value = "0.00";
                webHostingInput.value = "0.00";
                operatingCostInput.value = "0.00";
                
                // Reset additional expense fields
                salaryInput.value = "0.00";
                kosWrapParcelInput.value = "0.00";
                commissionParcelInput.value = "0.00";
                trainingInput.value = "0.00";
                internetInput.value = "0.00";
                bilPostpaidInput.value = "0.00";
                rentInput.value = "0.00";
                utilitiesInput.value = "0.00";
                maintenanceRepairInput.value = "0.00";
                staffPayClaimInput.value = "0.00";
                
                // Reset person name
                personNameInput.value = "";
                customCommissionRateInput.value = "5";
                
                // Recalculate all values with empty data
                updateAllCalculations();
            }
            
            // Team Selection Event Listener
            teamSelect.addEventListener('change', function() {
                const selectedTeamId = parseInt(this.value);
                handleTeamChange(selectedTeamId);
            });
            
            // Helper function to format currency
            function formatCurrency(value) {
                return 'RM ' + parseFloat(value).toFixed(2);
            }
            
            // Create report data object
            function getReportData() {
                const teamName = teamSelect.options[teamSelect.selectedIndex].text;
                const reportMonth = document.getElementById('reportMonth').value;
                const ssmNumber = document.getElementById('ssmNumber').value;
                const personName = personNameInput.value;
                
                return {
                    reportInfo: {
                        title: 'Financial Report',
                        team: teamName,
                        month: reportMonth,
                        ssmNumber: ssmNumber,
                        date: new Date().toLocaleDateString(),
                        personName: personName
                    },
                    financials: {
                        netRevenue: parseFloat(netRevenueInput.value) || 0,
                        directCost: parseFloat(directCostInput.value) || 0,
                        grossProfit: parseFloat(grossProfitInput.value) || 0
                    },
                    operatingExpenses: {
                        adsCost: parseFloat(adsCostInput.value) || 0,
                        shippingFee: parseFloat(shippingFeeInput.value) || 0,
                        webHosting: parseFloat(webHostingInput.value) || 0,
                        operatingCost: parseFloat(operatingCostInput.value) || 0,
                        
                        // Additional expenses
                        salary: parseFloat(salaryInput.value) || 0,
                        kosWrapParcel: parseFloat(kosWrapParcelInput.value) || 0,
                        commissionParcel: parseFloat(commissionParcelInput.value) || 0,
                        training: parseFloat(trainingInput.value) || 0,
                        internet: parseFloat(internetInput.value) || 0,
                        bilPostpaid: parseFloat(bilPostpaidInput.value) || 0,
                        rent: parseFloat(rentInput.value) || 0,
                        utilities: parseFloat(utilitiesInput.value) || 0,
                        maintenanceRepair: parseFloat(maintenanceRepairInput.value) || 0,
                        staffPayClaim: parseFloat(staffPayClaimInput.value) || 0,
                        
                        totalOperatingExpenses: parseFloat(totalOperatingExpensesInput.value) || 0
                    },
                    profitAndCommission: {
                        operatingProfit: parseFloat(operatingProfitInput.value) || 0,
                        netProfit: parseFloat(netProfitInput.value) || 0,
                        commissionRate: parseFloat(customCommissionRateInput.value) || 0,
                        individualCommission: parseFloat(individualCommissionAmountInput.value) || 0
                    }
                };
            }
            
            // Generate Excel file
            function generateExcel() {
                const data = getReportData();
                const team = data.reportInfo.team;
                const month = data.reportInfo.month;
                const personName = data.reportInfo.personName;
                
                // Create workbook
                const wb = XLSX.utils.book_new();
                
                // Format data for Excel
                const excelData = [
                    ['Financial Report', '', ''],
                    [team, '', ''],
                    ['Month:', month, ''],
                    ['SSM Number:', data.reportInfo.ssmNumber, ''],
                    ['Generated on:', data.reportInfo.date, ''],
                    ['', '', ''],
                    
                    ['Description', 'Amount (RM)', 'Notes'],
                    ['', '', ''],
                    
                    ['Net Revenue', formatCurrency(data.financials.netRevenue), ''],
                    ['Direct Cost (COGS) (-)', formatCurrency(data.financials.directCost), ''],
                    ['Gross Profit', formatCurrency(data.financials.grossProfit), ''],
                    ['', '', ''],
                    
                    // Original operating expenses
                    ['Ads Cost', formatCurrency(data.operatingExpenses.adsCost), ''],
                    ['Shipping Fee (NINJAVAN)', formatCurrency(data.operatingExpenses.shippingFee), ''],
                    ['Web Hosting/domain', formatCurrency(data.operatingExpenses.webHosting), ''],
                    ['Operating Cost', formatCurrency(data.operatingExpenses.operatingCost), ''],
                    
                    // Additional operating expenses
                    ['Salary', formatCurrency(data.operatingExpenses.salary), ''],
                    ['Kos Wrap Parcel (Completed)', formatCurrency(data.operatingExpenses.kosWrapParcel), ''],
                    ['Commission Parcel', formatCurrency(data.operatingExpenses.commissionParcel), ''],
                    ['Training', formatCurrency(data.operatingExpenses.training), ''],
                    ['INTERNET', formatCurrency(data.operatingExpenses.internet), ''],
                    ['Bil Postpaid', formatCurrency(data.operatingExpenses.bilPostpaid), ''],
                    ['Rent', formatCurrency(data.operatingExpenses.rent), ''],
                    ['Utilities', formatCurrency(data.operatingExpenses.utilities), ''],
                    ['Maintenance and Repair', formatCurrency(data.operatingExpenses.maintenanceRepair), ''],
                    ['Staff Pay and Claim', formatCurrency(data.operatingExpenses.staffPayClaim), ''],
                    
                    ['Total Operating Expenses', formatCurrency(data.operatingExpenses.totalOperatingExpenses), ''],
                    ['', '', ''],
                    
                    ['Operating Profit', formatCurrency(data.profitAndCommission.operatingProfit), ''],
                    ['Net Profit', formatCurrency(data.profitAndCommission.netProfit), 'Visible to team members'],
                    ['', '', ''],
                    
                    ['Individual Commission', '', ''],
                    ['Person Name:', personName, ''],
                    ['Commission Rate', data.profitAndCommission.commissionRate + '%', ''],
                    ['Commission Amount', formatCurrency(data.profitAndCommission.individualCommission), '']
                ];
                
                // Create worksheet and add to workbook
                const ws = XLSX.utils.aoa_to_sheet(excelData);
                
                // Apply some styling (column widths)
                ws['!cols'] = [
                    { wch: 30 }, // Description column width
                    { wch: 15 }, // Amount column width
                    { wch: 40 }  // Notes column width
                ];
                
                XLSX.utils.book_append_sheet(wb, ws, 'Financial Report');
                
                // Generate Excel file and trigger download
                const excelFileName = `Financial_Report_${team}_${month}.xlsx`;
                XLSX.writeFile(wb, excelFileName);
            }
            
            // Generate PDF file
            function generatePDF() {
                const { jsPDF } = window.jspdf;
                const data = getReportData();
                const team = data.reportInfo.team;
                const month = data.reportInfo.month;
                const personName = data.reportInfo.personName || 'Unknown';
                
                // Format month for display (e.g., "2025-04" to "April_2025")
                const formattedMonth = new Date(month + '-01').toLocaleString('en-US', { month: 'long', year: 'numeric' }).replace(' ', '_');
                
                // Create new document
                const doc = new jsPDF();
                
                // Set font and colors
                doc.setFont('helvetica');
                doc.setFontSize(16);
                doc.setTextColor(44, 62, 80); // Primary color
                
                // Add title
                doc.text('Financial Report', 105, 20, { align: 'center' });
                doc.setFontSize(12);
                doc.text(team, 105, 28, { align: 'center' });
                doc.text('Month: ' + month, 105, 35, { align: 'center' });
                
                // Add report info
                doc.setFontSize(10);
                doc.text('SSM Number: ' + data.reportInfo.ssmNumber, 20, 45);
                doc.text('Generated on: ' + data.reportInfo.date, 20, 50);
                
                // Add line
                doc.line(20, 55, 190, 55);
                
                // Section headers
                doc.setFontSize(11);
                doc.setTextColor(52, 152, 219); // Secondary color
                let y = 65;
                
                // Table headers
                doc.setFillColor(240, 240, 240);
                doc.rect(20, y, 170, 7, 'F');
                doc.setTextColor(44, 62, 80);
                doc.text('Description', 22, y + 5);
                doc.text('Amount (RM)', 120, y + 5);
                y += 12;
                
                // Financial statement
                doc.setTextColor(44, 62, 80);
                doc.setFontSize(10);
                
                // Net Revenue
                doc.setFont('helvetica', 'bold');
                doc.text('Net Revenue', 20, y);
                doc.text(formatCurrency(data.financials.netRevenue), 120, y);
                y += 7;
                
                // Direct Cost
                doc.text('Direct Cost (COGS) (-)', 20, y);
                doc.text(formatCurrency(data.financials.directCost), 120, y);
                y += 7;
                
                // Gross Profit
                doc.setFillColor(240, 247, 255);
                doc.rect(20, y-5, 170, 7, 'F');
                doc.text('Gross Profit', 20, y);
                doc.text(formatCurrency(data.financials.grossProfit), 120, y);
                y += 10;
                
                // Operating Expenses
                doc.setFont('helvetica', 'normal');
                
                // Original expenses
                doc.text('Ads Cost', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.adsCost), 120, y);
                y += 7;
                
                doc.text('Shipping Fee (NINJAVAN)', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.shippingFee), 120, y);
                y += 7;
                
                doc.text('Web Hosting/domain', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.webHosting), 120, y);
                y += 7;
                
                doc.text('Operating Cost', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.operatingCost), 120, y);
                y += 7;
                
                // Additional expenses
                doc.text('Salary', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.salary), 120, y);
                y += 7;
                
                // Check if we need a new page
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }
                
                doc.text('Kos Wrap Parcel (Completed)', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.kosWrapParcel), 120, y);
                y += 7;
                
                doc.text('Commission Parcel', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.commissionParcel), 120, y);
                y += 7;
                
                doc.text('Training', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.training), 120, y);
                y += 7;
                
                doc.text('INTERNET', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.internet), 120, y);
                y += 7;
                
                doc.text('Bil Postpaid', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.bilPostpaid), 120, y);
                y += 7;
                
                // Check if we need another new page
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }
                
                doc.text('Rent', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.rent), 120, y);
                y += 7;
                
                doc.text('Utilities', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.utilities), 120, y);
                y += 7;
                
                doc.text('Maintenance and Repair', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.maintenanceRepair), 120, y);
                y += 7;
                
                doc.text('Staff Pay and Claim', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.staffPayClaim), 120, y);
                y += 7;
                
                doc.text('Total Operating Expenses', 20, y);
                doc.text(formatCurrency(data.operatingExpenses.totalOperatingExpenses), 120, y);
                y += 10;
                
                // Operating Profit
                doc.setFillColor(240, 247, 255);
                doc.rect(20, y-5, 170, 7, 'F');
                doc.setFont('helvetica', 'bold');
                doc.text('Operating Profit', 20, y);
                doc.text(formatCurrency(data.profitAndCommission.operatingProfit), 120, y);
                y += 10;
                
                // Net Profit
                doc.setFillColor(240, 247, 255);
                doc.rect(20, y-5, 170, 7, 'F');
                doc.text('Net Profit', 20, y);
                doc.text(formatCurrency(data.profitAndCommission.netProfit), 120, y);
                y += 15;
                
                // Check if we need a new page
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }
                
                // Individual Commission section
                doc.setTextColor(52, 152, 219);
                doc.setFontSize(12);
                doc.text('Individual Commission', 20, y);
                y += 10;
                
                doc.setTextColor(44, 62, 80);
                doc.setFontSize(10);
                doc.text('Person Name:', 20, y);
                doc.text(personName, 70, y);
                y += 7;
                
                doc.text('Commission Rate:', 20, y);
                doc.text(data.profitAndCommission.commissionRate + '%', 70, y);
                y += 7;
                
                doc.setFont('helvetica', 'bold');
                doc.setFillColor(240, 247, 255);
                doc.rect(20, y-5, 170, 7, 'F');
                doc.text('Commission Amount:', 20, y);
                doc.text(formatCurrency(data.profitAndCommission.individualCommission), 70, y);
                
                // Add footer
                doc.setFontSize(8);
                doc.text('MYIASME - Financial Report', 105, 285, { align: 'center' });
                
                // Save PDF with person's name
                const pdfFileName = `Commission_${personName.replace(/\s+/g, '_')}_${formattedMonth}.pdf`;
                doc.save(pdfFileName);
                
                return pdfFileName;
            }
            
            // Download Report Event Listener
            downloadReportBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default button action
                
                const downloadFormat = downloadFormatSelect.value;
                
                if (downloadFormat === 'excel') {
                    generateExcel();
                } else if (downloadFormat === 'pdf') {
                    generatePDF();
                }
            });
            
            // Modified Save Report Event Listener with AJAX
            const saveReportBtn = document.getElementById('saveReportBtn');
            
            saveReportBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default button action
                
                // Validate person name is filled
                if (!personNameInput.value.trim()) {
                    showAlert('Please enter a person name for the commission calculation', 'danger');
                    personNameInput.focus();
                    return;
                }
                
                // First generate and download PDF
                const pdfFileName = generatePDF();
                
                // Show temporary message
                showAlert(`Generating PDF: ${pdfFileName}...`, 'success');
                
                // Get form data to submit via AJAX
                const formData = new FormData(form);
                
                // Add the individual commission field mapping
                formData.set('person_name', personNameInput.value);
                formData.set('commission_rate', customCommissionRateInput.value);
                formData.set('commission_amount', individualCommissionAmountInput.value);
                
                // Fix some field mappings that might be different between the form and PHP
                formData.set('direct_cost_cogs', directCostInput.value);
                formData.set('total_operating_cost', operatingCostInput.value);
                formData.set('total_expenses', totalOperatingExpensesInput.value);
                
                // Use AJAX to submit the form data without page reload
                fetch(form.getAttribute('action'), {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showAlert(`Financial report for ${personNameInput.value} saved successfully!`, 'success');
                        
                        if (data.report_id) {
                            showAlert(`Report ID: ${data.report_id}`, 'success');
                        }
                        
                        // Highlight the individual commission section briefly to show it's ready for the next person
                        const commissionSection = document.querySelector('.individual-commission-form');
                        commissionSection.style.backgroundColor = '#e8f8e8'; // Light green
                        
                        setTimeout(() => {
                            commissionSection.style.backgroundColor = '#e8f4fd'; // Return to original color
                        }, 2000);
                        
                        // Clear only the person name to prepare for the next entry
                        // but keep all financial data
                        personNameInput.value = "";
                        personNameInput.focus();
                        
                        // Update calculations (to make sure everything is still correct)
                        updateAllCalculations();
                    } else {
                        // Show error message
                        showAlert(`Error: ${data.error || 'Unknown error occurred'}`, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error saving report. Please try again.', 'danger');
                });
            });
            
            // Add a Clear Form button to the form-actions div
            const formActions = document.querySelector('.form-actions');
            const clearFormBtn = document.createElement('button');
            clearFormBtn.type = 'button';
            clearFormBtn.className = 'btn btn-secondary';
            clearFormBtn.style.marginLeft = '10px';
            clearFormBtn.innerHTML = '<i class="fas fa-eraser"></i> Clear Form';
            formActions.appendChild(clearFormBtn);
            
            // Clear Form Event Listener
            clearFormBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (confirm('Are you sure you want to clear all form data? This will reset all financial information.')) {
                    // Reset the form using the existing handleTeamChange function
                    const selectedTeamId = parseInt(teamSelect.value);
                    handleTeamChange(selectedTeamId);
                    showAlert('Form has been cleared', 'success');
                }
            });
            
            // Initialize with empty form for team 1
            handleTeamChange(1);
        });
    </script>
</body>
</html>