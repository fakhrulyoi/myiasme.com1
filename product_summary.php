<?php
require 'auth.php';
require 'dbconn_productProfit.php';

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
$sql_team_name = "SELECT team_name FROM teams WHERE team_id = ?";
$stmt_team_name = $dbconn->prepare($sql_team_name);
$stmt_team_name->bind_param("i", $team_id);
$stmt_team_name->execute();
$team_name_result = $stmt_team_name->get_result();
$team_name_data = $team_name_result->fetch_assoc();
$team_name = $team_name_data['team_name'] ?? 'Your Team';

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
    <title>Product Summary - Dr Ecomm Formula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional inline styles for improved UI */
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #f39c12;
            --dark-color: #34495e;
            --light-color: #ecf0f1;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .dashboard-container {
            display: flex;
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
        
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 260px;
            width: calc(100% - 260px);
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
            color: var(--dark-color);
        }
        
        section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        section h2 {
            color: var(--dark-color);
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (min-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr 1fr auto;
            }
        }
        
        label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 5px;
            display: block;
        }
        
        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            transition: border 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th {
            background-color: var(--dark-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        
        .data-table tbody tr {
            border-bottom: 1px solid #ddd;
        }
        
        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .data-table td {
            padding: 12px 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .edit-btn {
            background-color: var(--accent-color);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .edit-btn:hover {
            background-color: #d35400;
        }
        
        .delete-btn {
            background-color: var(--danger-color);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
            cursor: pointer;
        }
        
        .delete-btn:hover {
            background-color: #c0392b;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 70vh;
        }
        
        .totals-row {
            background-color: var(--light-color) !important;
            font-weight: bold;
        }
        
        .export-button {
            background-color: var(--secondary-color);
            margin-left: 10px;
        }
        
        .export-button:hover {
            background-color: #27ae60;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }
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
                <li class="active">
                    <a href="team_products.php">
                        <i class="fas fa-box"></i>
                        <span>Team Products</span>
                    </a>
                </li>
                <li>
                    <a href="sales_calculator.php">
                        <i class="fas fa-calculator"></i>
                        <span>Sales Calculator</span>
                    </a>
                </li>
                <li>
                    <a href="product_uploader.php">
                        <i class="fas fa-upload"></i>
                        <span>Product Uploader</span>
                    </a>
                </li>
                <li>
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
                <h1>Product Summary</h1>
                <div class="header-actions">
                    <a href="index.php#add-product" style="text-decoration: none;">
                        <button>Add New Product</button>
                    </a>
                    <button class="export-button" id="exportBtn">Export to Excel</button>
                </div>
            </header>
            
            <section id="product-summary">
                <h2>Filter Products</h2>
                <form method="GET" action="" class="filter-form">
                    <div>
                        <label for="filter_start_date">Start Date:</label>
                        <input type="date" id="filter_start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                    </div>

                    <div>
                        <label for="filter_end_date">End Date:</label>
                        <input type="date" id="filter_end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                    </div>

                    <div style="align-self: end;">
                        <button type="submit">Apply Filter</button>
                    </div>
                </form>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Ads Spend (RM)</th>
                                <th>Purchase</th>
                                <th>CPP</th>
                                <th>Units Sold</th>
                                <th>Item Cost (RM)</th>
                                <th>COD (RM)</th>
                                <th>COGS (RM)</th>
                                <th>Sales (RM)</th>
                                <th>Profit (RM)</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            require 'dbconn_productProfit.php';

                            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
                            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

                            $sql = "SELECT * FROM products WHERE 1";
                            if ($start_date && $end_date) {
                                $sql .= " AND created_at BETWEEN ? AND ?";
                            }
                            $sql .= " ORDER BY created_at DESC";

                            $stmt = $dbconn->prepare($sql);

                            if ($start_date && $end_date) {
                                $stmt->bind_param("ss", $start_date, $end_date);
                            }
                            
                            $stmt->execute();
                            $result = $stmt->get_result();

                            $total_ads_spend = 0;
                            $total_purchase = 0;
                            $total_units = 0;
                            $total_item_cost = 0;
                            $total_cod = 0;
                            $total_cogs = 0;
                            $total_sales = 0;
                            $total_profit = 0;

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $cpp = $row['purchase'] > 0 ? number_format($row['ads_spend'] / $row['purchase'], 2) : '0.00';
                                    $cogs = $row['item_cost'] + $row['cod'];
                                    
                                    // Add to totals
                                    $total_ads_spend += $row['ads_spend'];
                                    $total_purchase += $row['purchase'];
                                    $total_units += $row['unit_sold'];
                                    $total_item_cost += $row['item_cost'] * $row['unit_sold'];
                                    $total_cod += $row['cod'] * $row['unit_sold'];
                                    $total_cogs += $cogs * $row['unit_sold'];
                                    $total_sales += $row['sales'];
                                    $total_profit += $row['profit'];
                                    
                                    echo "<tr>
                                            <td>{$row['product_name']}</td>
                                            <td>" . number_format($row['ads_spend'], 2) . "</td>
                                            <td>{$row['purchase']}</td>
                                            <td>{$cpp}</td>
                                            <td>{$row['unit_sold']}</td>
                                            <td>" . number_format($row['item_cost'], 2) . "</td>
                                            <td>" . number_format($row['cod'], 2) . "</td>
                                            <td>" . number_format($cogs, 2) . "</td>
                                            <td>" . number_format($row['sales'], 2) . "</td>
                                            <td>" . number_format($row['profit'], 2) . "</td>
                                            <td>{$row['created_at']}</td>
                                            <td class='action-buttons'>
                                                <a href='edit_product.php?id={$row['id']}' class='edit-btn'>Edit</a>
                                                <form method='POST' action='delete_product.php' style='display:inline;'>
                                                    <input type='hidden' name='id' value='{$row['id']}'>
                                                    <button type='submit' class='delete-btn' onclick=\"return confirm('Are you sure you want to delete this product?');\">Delete</button>
                                                </form>
                                            </td>
                                          </tr>";
                                }
                                
                                // Calculate average CPP for the totals row
                                $avg_cpp = $total_purchase > 0 ? number_format($total_ads_spend / $total_purchase, 2) : '0.00';
                                
                                // Output totals row
                                echo "<tr class='totals-row'>
                                        <td><strong>TOTALS</strong></td>
                                        <td>" . number_format($total_ads_spend, 2) . "</td>
                                        <td>{$total_purchase}</td>
                                        <td>{$avg_cpp}</td>
                                        <td>{$total_units}</td>
                                        <td>" . number_format($total_item_cost, 2) . "</td>
                                        <td>" . number_format($total_cod, 2) . "</td>
                                        <td>" . number_format($total_cogs, 2) . "</td>
                                        <td>" . number_format($total_sales, 2) . "</td>
                                        <td>" . number_format($total_profit, 2) . "</td>
                                        <td colspan='2'></td>
                                      </tr>";
                            } else {
                                echo "<tr><td colspan='12'>No products found for the selected date range</td></tr>";
                           
                            }
                            
                            $stmt->close();
                            $dbconn->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <!-- JavaScript for Excel Export -->
    <script>
        document.getElementById('exportBtn').addEventListener('click', function() {
            // Get the table data
            let table = document.querySelector('.data-table');
            let rows = Array.from(table.querySelectorAll('tr'));
            
            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            
            rows.forEach(function(row) {
                let rowData = Array.from(row.querySelectorAll('th, td'))
                    .map(cell => {
                        // Get text content and remove any commas to avoid CSV issues
                        let content = cell.textContent.trim().replace(/,/g, ' ');
                        // Wrap content in quotes to handle spaces and special characters
                        return `"${content}"`;
                    });
                
                // Skip the Actions column for export
                if (rowData.length > 11) {
                    rowData.pop(); // Remove the Actions column
                }
                
                csvContent += rowData.join(',') + '\r\n';
            });
            
            // Create download link and trigger click
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'product_summary_' + new Date().toISOString().slice(0,10) + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>
</html>