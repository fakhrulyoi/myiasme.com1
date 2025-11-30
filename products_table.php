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

// Query to get products data
$sql = "SELECT * FROM products WHERE team_id = ? ORDER BY created_at DESC";
$stmt = $dbconn->prepare($sql);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Table - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
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

        .app-container {
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

        /* Page header */
        .page-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1E3C72;
            margin: 0;
        }

        /* Table styles */
        .table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .table-header {
            padding: 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .table-header h2 {
            margin: 0;
            color: #1E3C72;
            font-size: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        table th {
            background-color: #1E3C72;
            color: white;
            font-weight: 600;
        }

        table tr:hover td {
            background-color: #f8f9fa;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
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

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
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
                <li class="active">
                    <a href="products_table.php">
                        <i class="fas fa-table"></i>
                        <span>Products Table</span>
                    </a>
                </li>
                <li>
                    <a href="team_products.php">
                        <i class="fas fa-box"></i>
                        <span>Team Products</span>
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

        <!-- Main Content Container -->
        <main class="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <h1>Products Table</h1>
            </header>

            <!-- Products Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2><?php echo $team_name; ?> Products Data</h2>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Ads Spend</th>
                            <th>Purchase</th>
                            <th>CPP</th>
                            <th>Units Sold</th>
                            <th>Actual Cost</th>
                            <th>Item Cost</th>
                            <th>COD</th>
                            <th>Sales</th>
                            <th>Profit</th>
                            <th>Created At</th>
                            <th>Pakej</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td>RM <?php echo number_format($product['ads_spend'] ?? 0, 2); ?></td>
                                    <td><?php echo $product['purchase']; ?></td>
                                    <td><?php echo ($product['purchase'] > 0) ? number_format(($product['ads_spend'] ?? 0) / $product['purchase'], 2) : '0.00'; ?></td>
                                    <td><?php echo $product['unit_sold']; ?></td>
                                    <td>RM <?php echo number_format($product['actual_cost'] ?? 0, 2); ?></td>
                                    <td>RM <?php echo number_format($product['item_cost'] ?? 0, 2); ?></td>
                                    <td>RM <?php echo number_format($product['cod'] ?? 0, 2); ?></td>
                                    <td>RM <?php echo number_format($product['sales'] ?? 0, 2); ?></td>
                                    <td>RM <?php echo number_format($product['profit'] ?? 0, 2); ?></td>
                                    <td><?php echo date('d M Y H:i', strtotime($product['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($product['pakej'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="14" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>No products found for your team.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
