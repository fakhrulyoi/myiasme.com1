<?php
require 'auth.php';
require 'dbconn_productProfit.php';
require_once 'report_functions.php'; // Include the report functions

// Get current user's team_id based on the database structure
$user_id = $_SESSION['user_id'];
$sql_team = "SELECT team_id FROM users WHERE id = ?";
$stmt_team = $dbconn->prepare($sql_team);
$stmt_team->bind_param("i", $user_id);
$stmt_team->execute();
$team_result = $stmt_team->get_result();
$team_data = $team_result->fetch_assoc();
$team_id = $team_data['team_id'];

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
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - MYIASME</title>
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
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .chart-container {
            min-height: 300px;
            margin-top: 20px;
            position: relative;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-end;
        }
        
        .input-group > div {
            flex: 1;
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
        
        /* Enhanced styles for download options */
        .report-card {
            border: 1px solid #eee;
            padding: 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .report-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #ddd;
        }
        
        .report-card h3 {
            color: #1E3C72;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .report-card p {
            color: #666;
            margin-bottom: 15px;
        }
        
        .format-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
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
        
        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .download-btn i {
            font-size: 16px;
        }
        
        /* Debug styles */
        .debug-container {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: none;
        }
        
        .debug-message {
            padding: 5px;
            margin-bottom: 5px;
            border-left: 3px solid;
            padding-left: 10px;
        }
        
        .debug-message.error {
            border-left-color: #dc3545;
        }
        
        .debug-message.success {
            border-left-color: #28a745;
        }
        
        .debug-message.info {
            border-left-color: #17a2b8;
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
            
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .format-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h2>MYIASME</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo $username; ?></span>
                    <span class="role"><?php echo $team_name; ?></span>
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
                <li>
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
                <h1>Reports & Analytics</h1>
                <button id="toggleDebugBtn" style="background-color: #6c757d; display: none;">Show Debug Info</button>
            </header>
            
            <div class="report-grid">
                <!-- Monthly Performance Chart -->
                <section id="monthly-sales-cogs-profit-chart">
                    <h2>Monthly Performance</h2>
                    <div class="input-group">
                        <div>
                            <label for="monthSelector">Select Month:</label>
                            <input type="month" id="monthSelector">
                        </div>
                        <button id="fetchMonthlyChartButton">Show Chart</button>
                        <div class="format-buttons">
                            <button class="download-btn excel-btn" id="downloadMonthlyExcel">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                            <button class="download-btn pdf-btn" id="downloadMonthlyPDF">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlySalesCogsProfitChart"></canvas>
                    </div>
                </section>

                <!-- Product Performance Chart -->
                <section id="product-sales-profit-chart">
                    <h2>Product Performance</h2>
                    <div class="input-group">
                        <div>
                            <label for="dateSelector">Select Date:</label>
                            <input type="date" id="dateSelector">
                        </div>
                        <button id="fetchGraphButton">Show Chart</button>
                        <div class="format-buttons">
                            <button class="download-btn excel-btn" id="downloadDailyExcel">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                            <button class="download-btn pdf-btn" id="downloadDailyPDF">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="productSalesProfitChart"></canvas>
                    </div>
                </section>
            </div>
            
            <!-- Enhanced Download Reports Section -->
            <section id="download-reports">
                <h2>Download Reports</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <!-- Daily Sales Report -->
                    <div class="report-card">
                        <h3>Daily Sales Report</h3>
                        <p>Download detailed sales and profit data for a specific date.</p>
                        <div>
                            <div>
                                <label for="singleDateSelector">Select Date:</label>
                                <input type="date" id="singleDateSelector">
                            </div>
                            <div class="format-buttons">
                                <button class="download-btn excel-btn" id="downloadDailyCSVButton">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                                <button class="download-btn pdf-btn" id="downloadDailyPDFButton">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Date Range Report -->
                    <div class="report-card">
                        <h3>Date Range Report</h3>
                        <p>Generate a comprehensive report for a date range.</p>
                        <div>
                            <div style="margin-bottom: 15px;">
                                <label for="start_date">Start Date:</label>
                                <input type="date" id="start_date" name="start_date">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label for="end_date">End Date:</label>
                                <input type="date" id="end_date" name="end_date">
                            </div>
                            <div class="format-buttons">
                                <button class="download-btn excel-btn" id="downloadRangeExcelButton">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                                <button class="download-btn pdf-btn" id="downloadRangePDFButton">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Performance Report -->
                    <div class="report-card">
                        <h3>Product Performance</h3>
                        <p>Analyze product sales and profitability over a time period.</p>
                        <div>
                            <div style="margin-bottom: 15px;">
                                <label for="product_start_date">Start Date:</label>
                                <input type="date" id="product_start_date" name="product_start_date">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label for="product_end_date">End Date:</label>
                                <input type="date" id="product_end_date" name="product_end_date">
                            </div>
                            <div class="format-buttons">
                                <button class="download-btn excel-btn" id="downloadProductPerformanceExcel">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                                <button class="download-btn pdf-btn" id="downloadProductPerformancePDF">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Analytics Report -->
                    <div class="report-card">
                        <h3>Advanced Analytics</h3>
                        <p>Download advanced metrics including conversion rates and ad performance.</p>
                        <div>
                            <p style="margin-bottom: 15px;">This report includes conversion rates, ad spend, and ROI data.</p>
                            <div class="format-buttons">
                                <button class="download-btn excel-btn" id="downloadAdvancedAnalyticsExcel">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                                <button class="download-btn pdf-btn" id="downloadAdvancedAnalyticsPDF">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Additional Analytics Section -->
            <section id="advanced-analytics">
                <h2>Advanced Analytics</h2>
                
                <div class="chart-container" style="margin-top: 20px;">
                    <canvas id="productPerformanceComparison"></canvas>
                </div>
                
                <div style="display: flex; justify-content: center; margin-top: 15px; gap: 10px;">
                    <button id="loadAdvancedAnalytics">Load Analytics</button>
                    <div class="format-buttons">
                        <button class="download-btn excel-btn" id="downloadAdvancedChartExcel">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </div>
            </section>
            <!-- Add this to reports.php just before the closing </div> tag in the main-content section (before the debug container section) -->

<div style="display: flex; justify-content: center; margin: 20px 0;">
    <a href="download_all_reports.php" style="display: flex; align-items: center; gap: 10px; background-color: #1E3C72; color: white; text-decoration: none; padding: 15px 25px; border-radius: 4px; font-weight: 600; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s;">
        <i class="fas fa-download" style="font-size: 18px;"></i>
        Download All Reports in One Package
    </a>
</div>
            <!-- Debug Container -->
            <section id="debug-container" class="debug-container">
                <h2>Debug Information</h2>
                <div id="debug-output"></div>
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button id="check-file-exists" style="background-color: #6c757d;">Check if PHP File Exists</button>
                    <button id="clear-debug" style="background-color: #dc3545;">Clear Debug Info</button>
                </div>
            </section>
        </div>
    </div>
    
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    // Debug helper functions
    function toggleDebug() {
        const debugContainer = document.getElementById('debug-container');
        const toggleBtn = document.getElementById('toggleDebugBtn');
        
        if (debugContainer.style.display === 'none') {
            debugContainer.style.display = 'block';
            toggleBtn.textContent = 'Hide Debug Info';
        } else {
            debugContainer.style.display = 'none';
            toggleBtn.textContent = 'Show Debug Info';
        }
    }
    
    function addDebugMessage(message, type = 'info') {
        const debugOutput = document.getElementById('debug-output');
        const messageElement = document.createElement('div');
        messageElement.classList.add('debug-message');
        messageElement.classList.add(type);
        messageElement.textContent = message;
        debugOutput.appendChild(messageElement);
        
        // Show debug container and button
        document.getElementById('debug-container').style.display = 'block';
        document.getElementById('toggleDebugBtn').style.display = 'block';
    }
    
    // Attach event listener to debug toggle button
    document.getElementById('toggleDebugBtn').addEventListener('click', toggleDebug);
    
    // Check if PHP file exists
    document.getElementById('check-file-exists').addEventListener('click', function() {
        const filesToCheck = ['generate_excel.php', 'generate_pdf.php', 'report_functions.php'];
        
        filesToCheck.forEach(fileName => {
            addDebugMessage(`Checking if ${fileName} exists...`, 'info');
            
            fetch(fileName, {
                method: 'HEAD'
            })
            .then(response => {
                if (response.ok) {
                    addDebugMessage(`✅ ${fileName} exists on the server.`, 'success');
                } else {
                    addDebugMessage(`❌ ${fileName} does not exist or is not accessible. Status: ${response.status}`, 'error');
                }
            })
            .catch(error => {
                addDebugMessage(`❌ Error checking file: ${error.message}`, 'error');
            });
        });
    });
    
    // Clear debug output
    document.getElementById('clear-debug').addEventListener('click', function() {
        document.getElementById('debug-output').innerHTML = '';
    });

    // Monthly Sales, COGS & Profit Chart
    document.getElementById('fetchMonthlyChartButton').addEventListener('click', function () {
        const selectedMonth = document.getElementById('monthSelector').value;

        if (selectedMonth) {
            addDebugMessage(`Fetching monthly data for ${selectedMonth}...`, 'info');
            
            fetch(`fetch_monthly_sales_cogs_profit.php?month=${selectedMonth}&team_id=<?php echo $team_id; ?>`)
                .then(response => {
                    addDebugMessage(`Monthly data fetch status: ${response.status}`, response.ok ? 'success' : 'error');
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        addDebugMessage(`Error: ${data.error}`, 'error');
                        alert(data.error);
                        return;
                    }

                    addDebugMessage(`Monthly data received: ${data.dates.length} data points`, 'success');
                    
                    const ctx = document.getElementById('monthlySalesCogsProfitChart').getContext('2d');

                    if (window.monthlySalesChart) {
                        window.monthlySalesChart.destroy();
                    }

                    window.monthlySalesChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.dates,
                            datasets: [
                                {
                                    label: 'Total Sales (RM)',
                                    data: data.sales,
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.4
                                },
                                {
                                    label: 'Total COGS (RM)',
                                    data: data.cogs,
                                    borderColor: 'rgba(255, 206, 86, 1)',
                                    backgroundColor: 'rgba(255, 206, 86, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.4
                                },
                                {
                                    label: 'Total Profit (RM)',
                                    data: data.profit,
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    
                    addDebugMessage('Monthly chart rendered successfully', 'success');
                })
                .catch(error => {
                    addDebugMessage(`Error fetching monthly sales data: ${error.message}`, 'error');
                    console.error('Error fetching monthly sales data:', error);
                });
        } else {
            alert("Please select a month first!");
            addDebugMessage('Month selection is required', 'error');
        }
    });

    // Product Sales & Profit Chart
    document.getElementById('fetchGraphButton').addEventListener('click', function () {
        const selectedDate = document.getElementById('dateSelector').value;
        
        if (selectedDate) {
            addDebugMessage(`Fetching product data for ${selectedDate}...`, 'info');
            
            fetch(`fetch_product_sales_profit.php?date=${selectedDate}&team_id=<?php echo $team_id; ?>`)
                .then(response => {
                    addDebugMessage(`Product data fetch status: ${response.status}`, response.ok ? 'success' : 'error');
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        addDebugMessage(`Error: ${data.error}`, 'error');
                        alert(data.error);
                        return;
                    }
                    
                    addDebugMessage(`Product data received: ${data.products.length} products`, 'success');
                    
                    const ctx = document.getElementById('productSalesProfitChart').getContext('2d');
                    
                    if (window.productChart) {
                        window.productChart.destroy();
                    }
                    
                    window.productChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.products,
                            datasets: [
                                {
                                    label: 'Total Sales (RM)',
                                    data: data.sales,
                                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Total Profit (RM)',
                                    data: data.profits,
                                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                                    borderColor: 'rgba(255, 99, 132, 1)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    
                    addDebugMessage('Product chart rendered successfully', 'success');
                })
                .catch(error => {
                    addDebugMessage(`Error fetching product sales data: ${error.message}`, 'error');
                    console.error('Error fetching product sales data:', error);
                });
        } else {
            alert("Please select a date first!");
            addDebugMessage('Date selection is required', 'error');
        }
    });

    // Excel Downloads
    
    // Daily Report - Excel
    document.getElementById('downloadDailyCSVButton').addEventListener('click', function () {
        const selectedDate = document.getElementById('singleDateSelector').value;

        if (selectedDate) {
            addDebugMessage(`Downloading Excel for ${selectedDate}...`, 'info');
            window.location.href = `generate_excel.php?report_type=daily&date=${selectedDate}&team_id=<?php echo $team_id; ?>`;
        } else {
            alert("Please select a date first!");
            addDebugMessage('Date selection is required for Excel download', 'error');
        }
    });
    
    // Monthly Report - Excel
    document.getElementById('downloadMonthlyExcel').addEventListener('click', function () {
        const selectedMonth = document.getElementById('monthSelector').value;

        if (selectedMonth) {
            addDebugMessage(`Downloading Monthly Excel for ${selectedMonth}...`, 'info');
            window.location.href = `generate_excel.php?report_type=monthly&month=${selectedMonth}&team_id=<?php echo $team_id; ?>`;
        } else {
            alert("Please select a month first!");
            addDebugMessage('Month selection is required for Excel download', 'error');
        }
    });
    
    // Date Range Report - Excel
    document.getElementById('downloadRangeExcelButton').addEventListener('click', function () {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;

        if (startDate && endDate) {
            addDebugMessage(`Downloading Date Range Excel for ${startDate} to ${endDate}...`, 'info');
            window.location.href = `generate_excel.php?report_type=date_range&start_date=${startDate}&end_date=${endDate}&team_id=<?php echo $team_id; ?>`;
        } else {
            alert("Please select both start and end dates!");
            addDebugMessage('Both start and end dates are required for Excel download', 'error');
        }
    });
    
    // Product Performance - Excel
    document.getElementById('downloadProductPerformanceExcel').addEventListener('click', function () {
        const startDate = document.getElementById('product_start_date').value;
        const endDate = document.getElementById('product_end_date').value;

        if (startDate && endDate) {
            addDebugMessage(`Downloading Product Performance Excel for ${startDate} to ${endDate}...`, 'info');
            window.location.href = `generate_excel.php?report_type=product_performance&start_date=${startDate}&end_date=${endDate}&team_id=<?php echo $team_id; ?>`;
        } else {
            alert("Please select both start and end dates!");
            addDebugMessage('Both start and end dates are required for Excel download', 'error');
        }
    });
    
    // Advanced Analytics - Excel
    document.getElementById('downloadAdvancedAnalyticsExcel').addEventListener('click', function () {
        addDebugMessage(`Downloading Advanced Analytics Excel...`, 'info');
        window.location.href = `generate_excel.php?report_type=advanced_analytics&team_id=<?php echo $team_id; ?>`;
    });
    
    document.getElementById('downloadAdvancedChartExcel').addEventListener('click', function () {
        addDebugMessage(`Downloading Advanced Analytics Chart Excel...`, 'info');
        window.location.href = `generate_excel.php?report_type=advanced_analytics&team_id=<?php echo $team_id; ?>`;
    });
    
    // PDF Downloads
    
  // Daily Report from Chart - PDF
// Daily Report - PDF
document.getElementById('downloadDailyPDFButton').addEventListener('click', function () {
    const selectedDate = document.getElementById('singleDateSelector').value;
    if (selectedDate) {
        window.location.href = `generate_pdf.php?report_type=daily&date=${selectedDate}&team_id=<?php echo $team_id; ?>`;
    } else {
        alert("Please select a date first!");
    }
});

// Monthly Report - PDF
document.getElementById('downloadMonthlyPDF').addEventListener('click', function () {
    const selectedMonth = document.getElementById('monthSelector').value;
    if (selectedMonth) {
        addDebugMessage(`Downloading Monthly PDF for ${selectedMonth}...`, 'info');
        window.location.href = `generate_pdf.php?report_type=monthly&month=${selectedMonth}&team_id=<?php echo $team_id; ?>`;
    } else {
        alert("Please select a month first!");
        addDebugMessage('Month selection is required for PDF download', 'error');
    }
});

// Date Range Report - PDF
document.getElementById('downloadRangePDFButton').addEventListener('click', function () {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate && endDate) {
        addDebugMessage(`Downloading Date Range PDF for ${startDate} to ${endDate}...`, 'info');
        window.location.href = `generate_pdf.php?report_type=date_range&start_date=${startDate}&end_date=${endDate}&team_id=<?php echo $team_id; ?>`;
    } else {
        alert("Please select both start and end dates!");
        addDebugMessage('Both start and end dates are required for PDF download', 'error');
    }
});

// Product Performance - PDF
document.getElementById('downloadProductPerformancePDF').addEventListener('click', function () {
    const startDate = document.getElementById('product_start_date').value;
    const endDate = document.getElementById('product_end_date').value;
    if (startDate && endDate) {
        addDebugMessage(`Downloading Product Performance PDF for ${startDate} to ${endDate}...`, 'info');
        window.location.href = `generate_pdf.php?report_type=product_performance&start_date=${startDate}&end_date=${endDate}&team_id=<?php echo $team_id; ?>`;
    } else {
        alert("Please select both start and end dates!");
        addDebugMessage('Both start and end dates are required for PDF download', 'error');
    }
});

// Advanced Analytics - PDF
document.getElementById('downloadAdvancedAnalyticsPDF').addEventListener('click', function () {
    addDebugMessage(`Downloading Advanced Analytics PDF...`, 'info');
    window.location.href = `generate_pdf.php?report_type=advanced_analytics&team_id=<?php echo $team_id; ?>`;
});
    
    // Advanced Analytics Chart - Improved with error handling
    document.getElementById('loadAdvancedAnalytics').addEventListener('click', function() {
        addDebugMessage('Attempting to fetch advanced analytics data...', 'info');
        
        fetch(`fetch_advanced_analytics.php?team_id=<?php echo $team_id; ?>`)
            .then(response => {
                addDebugMessage(`Advanced analytics fetch status: ${response.status}`, response.ok ? 'success' : 'error');
                
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                
                const contentType = response.headers.get('content-type');
                addDebugMessage(`Content-Type: ${contentType}`, 'info');
                
                if (!contentType || !contentType.includes('application/json')) {
                    addDebugMessage('Warning: Response is not JSON format', 'error');
                }
                
                return response.text();
            })
            .then(text => {
                addDebugMessage(`Response size: ${text.length} characters`, 'info');
                
                try {
                    // Try to parse as JSON
                    const data = JSON.parse(text);
                    addDebugMessage('Successfully parsed JSON response', 'success');
                    
                    // Check for expected data structure
                    if (!data.weeks || !data.conversion_rates || !data.ads_spend || !data.sales) {
                        addDebugMessage('JSON is missing required fields (weeks, conversion_rates, ads_spend, sales)', 'error');
                        
                        // Display what was received for debugging
                        addDebugMessage(`Received data keys: ${Object.keys(data).join(', ')}`, 'info');
                        
                        if (data.error) {
                            addDebugMessage(`Server error: ${data.error}`, 'error');
                            if (data.debug_info) {
                                addDebugMessage(`Debug info: ${data.debug_info}`, 'info');
                            }
                        }
                        
                        return;
                    }
                    
                    addDebugMessage(`Data structure looks valid (found ${data.weeks.length} data points)`, 'success');
                    
                    // Continue with chart rendering
                    const ctx = document.getElementById('productPerformanceComparison').getContext('2d');
                    
                    if (window.advancedChart) {
                        addDebugMessage('Destroying previous chart instance', 'info');
                        window.advancedChart.destroy();
                    }
                    
                    try {
                        window.advancedChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.weeks,
                                datasets: [
                                    {
                                        label: 'Conversion Rate (%)',
                                        data: data.conversion_rates,
                                        borderColor: 'rgba(153, 102, 255, 1)',
                                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                                        yAxisID: 'y',
                                        type: 'line',
                                        fill: false,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Ads Spend (RM)',
                                        data: data.ads_spend,
                                        backgroundColor: 'rgba(255, 159, 64, 0.6)',
                                        borderColor: 'rgba(255, 159, 64, 1)',
                                        borderWidth: 1,
                                        type: 'bar',
                                        yAxisID: 'y1'
                                    },
                                    {
                                        label: 'Sales (RM)',
                                        data: data.sales,
                                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1,
                                        type: 'bar',
                                        yAxisID: 'y1'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        title: {
                                            display: true,
                                            text: 'Conversion Rate (%)'
                                        },
                                        min: 0,
                                        max: 5
                                    },
                                    y1: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        title: {
                                            display: true,
                                            text: 'Amount (RM)'
                                        },
                                        min: 0,
                                        grid: {
                                            drawOnChartArea: false
                                        }
                                    }
                                }
                            }
                        });
                        addDebugMessage('Advanced analytics chart rendered successfully', 'success');
                    } catch (chartError) {
                        addDebugMessage(`Error creating chart: ${chartError.message}`, 'error');
                        console.error('Chart initialization error:', chartError);
                    }
                } catch (jsonError) {
                    addDebugMessage(`Failed to parse JSON: ${jsonError.message}`, 'error');
                    console.error('JSON parse error:', jsonError);
                    
                    // Try to display the response for debugging
                    const preview = text.length > 200 ? text.substring(0, 200) + '...' : text;
                    addDebugMessage(`Response preview: ${preview}`, 'info');
                    addDebugMessage('Server might be returning HTML or error message instead of JSON', 'error');
                }
            })
            .catch(error => {
                addDebugMessage(`Fetch error: ${error.message}`, 'error');
                console.error('Fetch error:', error);
            });
    });
    
    // Set active class for sidebar navigation
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const sidebarLinks = document.querySelectorAll('.sidebar a');
        
        sidebarLinks.forEach(link => {
            if (currentPath.includes(link.getAttribute('href'))) {
                link.parentElement.classList.add('active');
            } else {
                link.parentElement.classList.remove('active');
            }
        });
        
        // Initialize with current date/month values
        const today = new Date();
        const currentYear = today.getFullYear();
        const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
        
        document.getElementById('monthSelector').value = `${currentYear}-${currentMonth}`;
        
        const formattedDate = today.toISOString().split('T')[0];
        document.getElementById('dateSelector').value = formattedDate;
        document.getElementById('singleDateSelector').value = formattedDate;
        document.getElementById('start_date').value = formattedDate;
        document.getElementById('end_date').value = formattedDate;
        document.getElementById('product_start_date').value = formattedDate;
        document.getElementById('product_end_date').value = formattedDate;
        
        // Automatically load charts on page load
        setTimeout(() => {
            document.getElementById('fetchMonthlyChartButton').click();
            document.getElementById('fetchGraphButton').click();
            document.getElementById('loadAdvancedAnalytics').click();
        }, 500);
    });
    // Add this to the debug section in reports.php

// Test PDF generation directly
document.getElementById('check-file-exists').addEventListener('click', function() {
    const filesToCheck = ['generate_excel.php', 'generate_pdf.php', 'report_functions.php'];
    
    filesToCheck.forEach(fileName => {
        addDebugMessage(`Checking if ${fileName} exists...`, 'info');
        
        fetch(fileName, {
            method: 'HEAD'
        })
        .then(response => {
            if (response.ok) {
                addDebugMessage(`✅ ${fileName} exists on the server.`, 'success');
            } else {
                addDebugMessage(`❌ ${fileName} does not exist or is not accessible. Status: ${response.status}`, 'error');
            }
        })
        .catch(error => {
            addDebugMessage(`❌ Error checking file: ${error.message}`, 'error');
        });
    });
    
    // Test if TCPDF library is available
    fetch('tcpdf/tcpdf.php', {
        method: 'HEAD'
    })
    .then(response => {
        if (response.ok) {
            addDebugMessage(`✅ TCPDF library exists on the server.`, 'success');
        } else {
            addDebugMessage(`❌ TCPDF library does not exist or is not accessible. Status: ${response.status}`, 'error');
        }
    })
    .catch(error => {
        addDebugMessage(`❌ Error checking TCPDF: ${error.message}`, 'error');
    });
    
    // Test PDF generation with minimal parameters
    addDebugMessage('Testing PDF generation...', 'info');
    const testUrl = `generate_pdf.php?report_type=daily&date=${new Date().toISOString().split('T')[0]}&team_id=<?php echo $team_id; ?>`;
    
    fetch(testUrl, {
        method: 'HEAD'
    })
    .then(response => {
        if (response.ok) {
            addDebugMessage(`✅ PDF generation test successful.`, 'success');
        } else {
            addDebugMessage(`❌ PDF generation test failed. Status: ${response.status}`, 'error');
        }
    })
    .catch(error => {
        addDebugMessage(`❌ Error testing PDF generation: ${error.message}`, 'error');
    });
});

// Add a new button for testing PDF in a new tab (avoiding download)
const debugContainer = document.getElementById('debug-container');
const testPdfButton = document.createElement('button');
testPdfButton.id = 'test-pdf-gen';
testPdfButton.textContent = 'Test PDF Generation in New Tab';
testPdfButton.style.backgroundColor = '#28a745';
testPdfButton.style.marginLeft = '10px';

document.querySelector('#debug-container div[style="margin-top: 15px; display: flex; gap: 10px;"]').appendChild(testPdfButton);

document.getElementById('test-pdf-gen').addEventListener('click', function() {
    const date = new Date().toISOString().split('T')[0];
    const testUrl = `generate_pdf.php?report_type=daily&date=${date}&team_id=<?php echo $team_id; ?>&debug=1`;
    window.open(testUrl, '_blank');
    addDebugMessage(`Opened PDF generation test in new tab with date ${date}`, 'info');
});

// Make debug panel visible by default during testing
document.getElementById('debug-container').style.display = 'block';
document.getElementById('toggleDebugBtn').style.display = 'block';
document.getElementById('toggleDebugBtn').textContent = 'Hide Debug Info';
    </script>
</body>
</html>