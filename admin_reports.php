<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Redirect if not admin
if (!$is_admin) {
    header("Location: reports.php");
    exit();
}

// First, let's check what column exists in the teams table
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}
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

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Get all teams for filter dropdown
$teams_query = "SELECT * FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_query);
$teams = [];
while ($team = $teams_result->fetch_assoc()) {
    $teams[] = $team;
}

// Get available years for reports from the database
// Using created_at from products table instead of date from sales
$years_query = "SELECT DISTINCT YEAR(created_at) as year FROM products ORDER BY year DESC";
$years_result = $dbconn->query($years_query);
$available_years = [];
if ($years_result && $years_result->num_rows > 0) {
    while ($year_row = $years_result->fetch_assoc()) {
        $available_years[] = $year_row['year'];
    }
} else {
    // Default years if no data is available
    $available_years = [date('Y'), date('Y')-1, date('Y')-2];
}

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
    <title>Admin Reports - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
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
            --border-radius: 10px;
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
            font-size: 26px;
            font-weight: 600;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 26px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        
        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-weight: 500;
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .role {
            font-size: 13px;
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
            padding: 14px 18px;
            color: var(--light-text);
            text-decoration: none;
            transition: var(--transition);
            border-radius: var(--border-radius);
        }
        
        .nav-links li a i {
            margin-right: 12px;
            font-size: 18px;
            width: 22px;
            text-align: center;
            transition: var(--transition);
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
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-header h1 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        /* Card styles */
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            transition: var(--transition);
        }
        
        .card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
        }
        
        .card-header h3 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }

        /* Section styling */
        section {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 25px;
            transition: var(--transition);
        }
        
        section:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        section h2 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        section h2 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
        }
        
        .chart-container {
            min-height: 300px;
            margin-top: 20px;
            position: relative;
        }

        /* Form inputs */
        .input-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .input-group > div {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
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
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        button {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        button:hover {
            background-color: var(--secondary-light);
        }
        
        /* Stats cards styling */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .summary-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e6e6e6;
            transition: var(--transition);
        }

        .summary-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .summary-label {
            font-weight: 500;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }

        .summary-value {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Team comparison section */
        .team-comparison {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .team-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            transition: var(--transition);
            background-color: white;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .team-card h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .team-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .stat-box {
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            background-color: #f8f9fa;
        }

        .stat-box .value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-box .label {
            font-size: 12px;
            color: #666;
        }
        
        /* Loading indicator */
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            font-weight: 500;
            color: #666;
        }
        
        .loading i {
            margin-right: 8px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Filters section */
        .filters-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        /* Utility classes */
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .stats-summary {
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
            
            .input-group {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .input-group > div {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
                <?php if ($is_admin): ?>
                <li class="<?php echo isActive('admin_dashboard.php'); ?>">
                    <a href="admin_dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
              
                <li class="<?php echo isActive('teams.php'); ?>">
                    <a href="teams.php">
                        <i class="fas fa-users"></i>
                        <span>Teams</span>
                    </a>
                </li>
                <li class="<?php echo isActive('all_products.php'); ?>">
                    <a href="all_products.php">
                        <i class="fas fa-boxes"></i>
                        <span>All Products</span>
                    </a>
                </li>
              
                <?php endif; ?>
                
                <div class="nav-section">
                    <p class="nav-section-title">Tools</p>
                    <li class="<?php echo isActive('commission_calculator.php'); ?>">
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
                    <li class="<?php echo isActive('admin_reports.php'); ?>">
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
        
        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <header class="page-header">
                <h1><i class="fas fa-chart-bar"></i> Admin Reports & Analytics</h1>
                <div class="filters-container">
                    <div class="team-filter">
                        <label for="team_filter">Team:</label>
                        <select id="team_filter">
                            <option value="all">All Teams</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team[$team_pk]; ?>"><?php echo $team['team_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </header>
            
            <!-- Summary Stats Section -->
            <section id="summary-stats">
                <h2><i class="fas fa-chart-line"></i> Overall Performance</h2>
                <div class="input-group">
                    <div>
                        <label for="startDate">Start Date:</label>
                        <input type="date" id="startDate" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div>
                        <label for="endDate">End Date:</label>
                        <input type="date" id="endDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button id="fetchStatsButton"><i class="fas fa-sync-alt"></i> Update Stats</button>
                </div>
                
                <div class="loading" id="statsLoading">
                    <i class="fas fa-spinner"></i> Loading statistics...
                </div>
                
                <div class="stats-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Sales</span>
                        <span class="summary-value" id="totalSales">RM 0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Profit</span>
                        <span class="summary-value" id="totalProfit">RM 0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Products</span>
                        <span class="summary-value" id="totalProducts">0</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Units Sold</span>
                        <span class="summary-value" id="totalUnits">0</span>
                    </div>
                </div>
            </section>
            
            <div class="report-grid">
                <!-- Monthly Performance Chart -->
                <section id="monthly-performance">
                    <h2><i class="fas fa-calendar-alt"></i> Monthly Performance Comparison</h2>
                    <div class="input-group">
                        <div>
                            <label for="yearSelector">Select Year:</label>
                            <select id="yearSelector">
                                <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == date('Y')) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button id="fetchMonthlyChartButton"><i class="fas fa-chart-line"></i> Show Chart</button>
                    </div>
                    
                    <div class="loading" id="monthlyChartLoading">
                        <i class="fas fa-spinner"></i> Loading chart data...
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="monthlyComparisonChart"></canvas>
                    </div>
                </section>

                <!-- Category Performance Chart -->
                <section id="category-performance">
                    <h2><i class="fas fa-tags"></i> Product Type Performance</h2>
                    <div class="input-group">
                        <div>
                            <label for="categoryTimeRange">Time Range:</label>
                            <select id="categoryTimeRange">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="180">Last 180 Days</option>
                                <option value="365">Last 365 Days</option>
                            </select>
                        </div>
                        <button id="fetchCategoryChartButton"><i class="fas fa-chart-pie"></i> Show Chart</button>
                    </div>
                    
                    <div class="loading" id="categoryChartLoading">
                        <i class="fas fa-spinner"></i> Loading chart data...
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="categoryPerformanceChart"></canvas>
                    </div>
                </section>
            </div>
            
            <!-- Team Comparison Section -->
            <section id="team-comparison">
                <h2><i class="fas fa-users"></i> Team Performance Comparison</h2>
                <div class="input-group">
                    <div>
                        <label for="teamComparisonPeriod">Time Period:</label>
                        <select id="teamComparisonPeriod">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                        </select>
                    </div>
                    <button id="fetchTeamComparisonButton"><i class="fas fa-chart-bar"></i> Compare Teams</button>
                </div>
                
                <div class="loading" id="teamChartLoading">
                    <i class="fas fa-spinner"></i> Loading team comparison data...
                </div>
                
                <div class="chart-container">
                    <canvas id="teamComparisonChart"></canvas>
                </div>
                
                <div class="team-comparison" id="teamStatCards">
                    <!-- Team performance cards will be generated here -->
                </div>
            </section>
            
            <!-- Download Reports Section -->
            <section id="download-reports">
                <h2><i class="fas fa-file-download"></i> Download Reports</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <!-- Team Performance Report -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-users"></i> Team Performance Report</h3>
                        </div>
                        <p>Download detailed team performance data for a specific date range.</p>
                        <div style="margin-top: 15px;">
                            <div class="team-filter" style="margin-bottom: 15px;">
                                <label for="reportTeamSelect">Select Team:</label>
                                <select id="reportTeamSelect">
                                    <option value="all">All Teams</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo $team[$team_pk]; ?>"><?php echo $team['team_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label for="report_start_date">Start Date:</label>
                                <input type="date" id="report_start_date" name="report_start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label for="report_end_date">End Date:</label>
                                <input type="date" id="report_end_date" name="report_end_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button id="downloadTeamReportButton"><i class="fas fa-download"></i> Download Report</button>
                        </div>
                    </div>
                    
                    <!-- Comprehensive Analytics Report -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-file-alt"></i> Comprehensive Analytics Report</h3>
                        </div>
                        <p>Generate a detailed PDF analytics report with sales, profit, and performance metrics.</p>
                        <div style="margin-top: 15px;">
                            <div style="margin-bottom: 15px;">
                                <label for="analytics_start_date">Start Date:</label>
                                <input type="date" id="analytics_start_date" name="analytics_start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label for="analytics_end_date">End Date:</label>
                                <input type="date" id="analytics_end_date" name="analytics_end_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="input-group">
                                <div>
                                    <label for="report_type">Report Type:</label>
                                    <select id="report_type">
                                        <option value="summary">Summary Report</option>
                                        <option value="detailed">Detailed Report</option>
                                        <option value="financial">Financial Analysis</option>
                                    </select>
                                </div>
                            </div>
                            <button id="downloadAnalyticsReportButton"><i class="fas fa-file-pdf"></i> Generate Report</button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <script>
    // Toggle sidebar on mobile
    document.addEventListener('DOMContentLoaded', function() {
        // Create toggle button
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
        
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        toggleSidebarBtn.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
        });
        
        // Show toggle button on mobile
        function checkMobile() {
            if (window.innerWidth <= 768) {
                toggleSidebarBtn.style.display = 'block';
            } else {
                toggleSidebarBtn.style.display = 'none';
                sidebar.classList.remove('expanded');
            }
        }
        
        window.addEventListener('resize', checkMobile);
        checkMobile(); // Initial check
        
        // Initialize date values
        // Fetch initial stats
        fetchStatsSummary();
    });
    
    // Overall Performance Stats
    function fetchStatsSummary() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const teamFilter = document.getElementById('team_filter').value;
        
        // Show loading indicator
        document.getElementById('statsLoading').style.display = 'block';
        
        // Make AJAX call to fetch real data
        $.ajax({
            url: 'api/get_performance_stats.php',
            type: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate,
                team_id: teamFilter
            },
            dataType: 'json',
            success: function(response) {
                // Hide loading indicator
                document.getElementById('statsLoading').style.display = 'none';
                
                if (response.success) {
                    // Update the stats in the UI with real data
                    document.getElementById('totalSales').textContent = `RM ${parseFloat(response.data.total_sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    document.getElementById('totalProfit').textContent = `RM ${parseFloat(response.data.total_profit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    document.getElementById('totalProducts').textContent = parseInt(response.data.total_products).toLocaleString();
                    document.getElementById('totalUnits').textContent = parseInt(response.data.total_units).toLocaleString();
                } else {
                    // Show error
                    alert('Error fetching statistics: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                document.getElementById('statsLoading').style.display = 'none';
                console.error('AJAX Error:', error);
                alert('Failed to fetch statistics. Please try again.');
            }
        });
    }
    
    // Event listeners
    document.getElementById('fetchStatsButton').addEventListener('click', fetchStatsSummary);
    
    // Monthly Performance Chart
    document.getElementById('fetchMonthlyChartButton').addEventListener('click', function() {
        const selectedYear = document.getElementById('yearSelector').value;
        const teamFilter = document.getElementById('team_filter').value;
        
        // Show loading indicator
        document.getElementById('monthlyChartLoading').style.display = 'block';
        
        // Make AJAX call to fetch real data
        $.ajax({
            url: 'api/get_monthly_performance.php',
            type: 'GET',
            data: {
                year: selectedYear,
                team_id: teamFilter
            },
            dataType: 'json',
            success: function(response) {
                // Hide loading indicator
                document.getElementById('monthlyChartLoading').style.display = 'none';
                
                if (response.success) {
                    // Prepare chart data
                    const chartData = {
                        labels: response.data.labels,
                        datasets: [
                            {
                                label: 'Sales (RM)',
                                data: response.data.sales,
                                borderColor: 'rgba(54, 162, 235, 1)',
                                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4
                            },
                            {
                                label: 'Profit (RM)',
                                data: response.data.profit,
                                borderColor: 'rgba(75, 192, 192, 1)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4
                            }
                        ]
                    };
                    
                    // Create or update chart
                    const ctx = document.getElementById('monthlyComparisonChart').getContext('2d');
                    
                    if (window.monthlyChart) {
                        window.monthlyChart.destroy();
                    }
                    
                    window.monthlyChart = new Chart(ctx, {
                        type: 'line',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: `Monthly Performance for ${selectedYear}`
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    // Show error
                    alert('Error fetching chart data: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                document.getElementById('monthlyChartLoading').style.display = 'none';
                console.error('AJAX Error:', error);
                alert('Failed to fetch chart data. Please try again.');
            }
        });
    });
    
    // Category Performance Chart (using product data since we don't have categories)
    document.getElementById('fetchCategoryChartButton').addEventListener('click', function() {
        const timeRange = document.getElementById('categoryTimeRange').value;
        const teamFilter = document.getElementById('team_filter').value;
        
        // Show loading indicator
        document.getElementById('categoryChartLoading').style.display = 'block';
        
        // Make AJAX call to fetch real data
        $.ajax({
            url: 'api/get_product_performance.php',
            type: 'GET',
            data: {
                days: timeRange,
                team_id: teamFilter
            },
            dataType: 'json',
            success: function(response) {
                // Hide loading indicator
                document.getElementById('categoryChartLoading').style.display = 'none';
                
                if (response.success) {
                    // Prepare chart data
                    const data = {
                        labels: response.data.labels,
                        datasets: [{
                            label: 'Sales (RM)',
                            data: response.data.values,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 159, 64, 0.7)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)',
                                'rgba(255, 159, 64, 1)'
                            ],
                            borderWidth: 1
                        }]
                    };
                    
                    const ctx = document.getElementById('categoryPerformanceChart').getContext('2d');
                    
                    if (window.categoryChart) {
                        window.categoryChart.destroy();
                    }
                    
                    window.categoryChart = new Chart(ctx, {
                        type: 'pie',
                        data: data,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: `Top Products Performance (Last ${timeRange} Days)`
                                },
                                legend: {
                                    position: 'right'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label;
                                            const value = context.raw;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: RM ${value.toLocaleString()} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Show error
                    alert('Error fetching product data: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                document.getElementById('categoryChartLoading').style.display = 'none';
                console.error('AJAX Error:', error);
                alert('Failed to fetch product data. Please try again.');
            }
            
        });
    });
    
   // Updated Team Comparison Chart code
    document.getElementById('fetchTeamComparisonButton').addEventListener('click', function() {
        const timePeriod = document.getElementById('teamComparisonPeriod').value;
        
        // Show loading indicator
        document.getElementById('teamChartLoading').style.display = 'block';
        
        // Make AJAX call to fetch team comparison data
        $.ajax({
            url: 'api/get_team_comparison.php',
            type: 'GET',
            data: {
                days: timePeriod
            },
            dataType: 'json',
            success: function(response) {
                // Hide loading indicator
                document.getElementById('teamChartLoading').style.display = 'none';
                
                if (response.success) {
                    // Create team performance cards
                    createTeamCards(response.data.team_stats);
                    
                    // Prepare chart data
                    const chartData = {
                        labels: response.data.team_names,
                        datasets: [
                            {
                                label: 'Total Sales (RM)',
                                data: response.data.sales,
                                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Total Profit (RM)',
                                data: response.data.profit,
                                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }
                        ]
                    };
                    
                    // Create or update chart
                    const ctx = document.getElementById('teamComparisonChart').getContext('2d');
                    
                    if (window.teamChart) {
                        window.teamChart.destroy();
                    }
                    
                    window.teamChart = new Chart(ctx, {
                        type: 'bar',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: `Team Performance Comparison (Last ${timePeriod} Days)`
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    // Show error
                    alert('Error fetching team comparison data: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                document.getElementById('teamChartLoading').style.display = 'none';
                console.error('AJAX Error:', error);
                alert('Failed to fetch team comparison data. Please try again.');
            }
        });
    });
    
    // Function to create team performance cards
    function createTeamCards(teamStats) {
        const container = document.getElementById('teamStatCards');
        container.innerHTML = ''; // Clear existing cards
        
        teamStats.forEach(team => {
            const teamCard = document.createElement('div');
            teamCard.className = 'team-card';
            
            teamCard.innerHTML = `
                <h3>${team.name}</h3>
                <div class="team-stats">
                    <div class="stat-box">
                        <div class="value">RM ${parseFloat(team.sales).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                        <div class="label">Total Sales</div>
                    </div>
                    <div class="stat-box">
                        <div class="value">RM ${parseFloat(team.profit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                        <div class="label">Total Profit</div>
                    </div>
                    <div class="stat-box">
                        <div class="value">${team.products}</div>
                        <div class="label">Products</div>
                    </div>
                    <div class="stat-box">
                        <div class="value">${team.profitMargin}%</div>
                        <div class="label">Profit Margin</div>
                    </div>
                    <div class="stat-box">
                        <div class="value ${team.growth >= 0 ? 'text-success' : 'text-danger'}">
                            ${team.growth >= 0 ? '+' : ''}${team.growth}%
                        </div>
                        <div class="label">Growth</div>
                    </div>
                </div>
            `;
            
            container.appendChild(teamCard);
        });
    }
    
    // Download Team Performance Report
    document.getElementById('downloadTeamReportButton').addEventListener('click', function() {
        const teamId = document.getElementById('reportTeamSelect').value;
        const startDate = document.getElementById('report_start_date').value;
        const endDate = document.getElementById('report_end_date').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates for the report.');
            return;
        }
        
        // Create URL with parameters
        let url = `generate_team_report.php?team_id=${teamId}&start_date=${startDate}&end_date=${endDate}`;
        
        // Open in new tab or download directly
        window.open(url, '_blank');
    });
    
    // Download Comprehensive Analytics Report
    document.getElementById('downloadAnalyticsReportButton').addEventListener('click', function() {
        const startDate = document.getElementById('analytics_start_date').value;
        const endDate = document.getElementById('analytics_end_date').value;
        const reportType = document.getElementById('report_type').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates for the report.');
            return;
        }
        
        // Create URL with parameters
        let url = `generate_analytics_report.php?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`;
        
        // Open in new tab or download directly
        window.open(url, '_blank');
    });
    
    // Update all data when team filter changes
    document.getElementById('team_filter').addEventListener('change', function() {
        fetchStatsSummary();
        
        // If charts are already initialized, refresh them too
        if (window.monthlyChart) {
            document.getElementById('fetchMonthlyChartButton').click();
        }
        
        if (window.categoryChart) {
            document.getElementById('fetchCategoryChartButton').click();
        }
    });
    
    // Initialize first charts on page load
    document.getElementById('fetchMonthlyChartButton').click();
    document.getElementById('fetchCategoryChartButton').click();
    document.getElementById('fetchTeamComparisonButton').click();
    </script>
</body>
</html>