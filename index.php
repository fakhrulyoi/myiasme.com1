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

// Get team's products
$sql_products = "SELECT DISTINCT sku, product_name, actual_cost FROM products 
                 WHERE team_id = ? AND status = 'active'";
$stmt_products = $dbconn->prepare($sql_products);
$stmt_products->bind_param("i", $team_id);
$stmt_products->execute();
$products_result = $stmt_products->get_result();
$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[] = $product;
}

// Check for success message from save_product.php
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['message'])) {
    $success_message = $_GET['message'];
    $product_name = isset($_GET['product']) ? $_GET['product'] : '';
    $profit = isset($_GET['profit']) ? $_GET['profit'] : 0;
}
// Process add new product form if submitted
$product_added = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_product'])) {
    $new_sku = trim($_POST['new_sku']);
    $new_product_name = trim($_POST['new_product_name']);
    $new_actual_cost = floatval($_POST['new_actual_cost']);
    
    // Validate inputs
    if (empty($new_sku)) {
        $error_message = "SKU cannot be empty";
    } elseif (empty($new_product_name)) {
        $error_message = "Product name cannot be empty";
    } elseif ($new_actual_cost <= 0) {
        $error_message = "Actual cost must be greater than zero";
    } else {
        // Check if SKU already exists for this team
      $sql_check_sku = "SELECT COUNT(*) as count FROM products 
                 WHERE sku = ? AND team_id = ? AND actual_cost IS NOT NULL
                 LIMIT 1";
$stmt_check_sku = $dbconn->prepare($sql_check_sku);
$stmt_check_sku->bind_param("si", $new_sku, $team_id);
        $stmt_check_sku->execute();
        $check_sku_result = $stmt_check_sku->get_result();
        $check_sku_data = $check_sku_result->fetch_assoc();
        
        // Check if product name already exists for this team
        $sql_check_name = "SELECT COUNT(*) as count FROM products WHERE product_name = ? AND team_id = ?";
        $stmt_check_name = $dbconn->prepare($sql_check_name);
        $stmt_check_name->bind_param("si", $new_product_name, $team_id);
        $stmt_check_name->execute();
        $check_name_result = $stmt_check_name->get_result();
        $check_name_data = $check_name_result->fetch_assoc();
        
        if ($check_sku_data['count'] > 0) {
            $error_message = "SKU already exists for your team";
        } elseif ($check_name_data['count'] > 0) {
            $error_message = "Product already exists for your team";
        } else {
            // Insert new product
$sql_insert = "INSERT INTO products (sku, product_name, actual_cost, team_id, status, stock_quantity, stock_status) 
               VALUES (?, ?, ?, ?, 'active', 0, 'Out of Stock')";
$stmt_insert = $dbconn->prepare($sql_insert);
$stmt_insert->bind_param("ssdi", $new_sku, $new_product_name, $new_actual_cost, $team_id);
            
            if ($stmt_insert->execute()) {
                $product_added = true;
                // Add the new product to the products array
               $products[] = [
    'sku' => $new_sku,
    'product_name' => $new_product_name,
    'actual_cost' => $new_actual_cost
    // Status is 'active' by default, so no need to include it here as it's only used for filtering
];
            } else {
                $error_message = "Failed to add product: " . $dbconn->error;
            }
        }
    }
}

// Process product sales data form if submitted
$sales_data_added = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sales_data'])) {
    // Here you would handle saving the sales data to your database
    // For demonstration, we'll just set a flag
    $sales_data_added = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MYIASME Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
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
        
        /* Form and calculator styles */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 20px;
        }
        
        section h2 {
            color: #1E3C72;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        form {
            display: grid;
            grid-gap: 15px;
        }
        
        label {
            font-weight: 500;
            color: #333;
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
        }
        
        button:hover {
            background-color: #2A5298;
        }
        
        .results {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        .results p {
            margin: 10px 0;
        }
        
        /* Header styles */
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
        
        /* Quick access cards */
        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .quick-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: white;
        }
        
        .quick-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .quick-card p {
            margin: 0;
        }
        
        .card-primary {
            background-color: #36A2EB;
        }
        
        .card-success {
            background-color: #4BC0C0;
        }
        
        .card-warning {
            background-color: #FF9F40;
        }
        
        /* Alert messages */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Button styles */
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        /* Improved notification styles */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    max-width: 400px;
    padding: 16px 20px;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    display: flex;
    align-items: center;
    animation: slideIn 0.5s ease-out forwards;
    transition: all 0.3s;
}

.notification.success {
    background-color: #28a745;
    color: white;
}

.notification.error {
    background-color: #dc3545;
    color: white;
}

.notification-icon {
    margin-right: 12px;
    font-size: 20px;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.notification-message {
    opacity: 0.9;
}

.notification-close {
    color: white;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    margin-left: 12px;
    font-size: 18px;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

/* Profit summary styles */
.profit-summary {
    background-color: #f8f9fa;
    border-left: 4px solid #28a745;
    padding: 15px;
    margin-top: 20px;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profit-summary.negative {
    border-left-color: #dc3545;
}

.profit-summary-details {
    flex: 1;
}

.profit-summary-label {
    font-weight: 500;
    color: #333;
    margin-bottom: 5px;
}

.profit-summary-value {
    font-size: 22px;
    font-weight: 600;
}

.profit-summary-value.positive {
    color: #28a745;
}

.profit-summary-value.negative {
    color: #dc3545;
}
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation - Updated to match dashboard.php -->
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
                <li class="active">
                    <a href="index.php">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Product</span>
                    </a>
                </li>
                  <li class="">
                    <a href="user_product_proposed.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>Product Proposals</span>
                    </a>
                </li>
                <li class="<?php echo isActive('winning_dna.php'); ?>">
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
                        <span>Commision View</span>
                    </a>
                </li>
                    <li class="">
                    <a href="view_stock.php">
                        <i class="fas fa-warehouse"></i>
                        <span>View Stock</span>
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
                <h1>Add New Product</h1>
            </header>
            <!-- Success notification that will be shown dynamically -->
<?php if (!empty($success_message)): ?>
<div id="successNotification" class="notification success">
    <div class="notification-icon">
        <i class="fas fa-check-circle"></i>
    </div>
    <div class="notification-content">
        <div class="notification-title">Success!</div>
        <div class="notification-message"><?php echo $success_message; ?></div>
    </div>
    <button class="notification-close" onclick="dismissNotification()">
        <i class="fas fa-times"></i>
    </button>
</div>

<?php if (isset($_GET['profit'])): ?>
<!-- Profit summary box - shows only when product is saved with profit data -->
<section>
    <div class="profit-summary <?php echo ($_GET['profit'] < 0) ? 'negative' : ''; ?>">
        <div class="profit-summary-details">
            <div class="profit-summary-label">Profit Summary for <?php echo htmlspecialchars($_GET['product']); ?></div>
            <div class="profit-summary-value <?php echo ($_GET['profit'] < 0) ? 'negative' : 'positive'; ?>">
                RM <?php echo number_format($_GET['profit'], 2); ?>
            </div>
        </div>
        <div>
            <i class="fas <?php echo ($_GET['profit'] < 0) ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up'; ?> fa-2x"></i>
        </div>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>
            
            <div class="content-grid">
                <!-- Add New Product to Database Form -->
                <section id="add-new-product-to-db">
                    <h2>Add New Product to Database</h2>
                    
                    <?php if ($product_added): ?>
                    <div class="alert alert-success">
                        Product has been successfully added to the database!
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="newProductForm">
                        <div>
                            <label for="new_sku">SKU (Product ID)</label>
                            <input type="text" id="new_sku" name="new_sku" required>
                        </div>
                        
                        <div>
                            <label for="new_product_name">Product Name</label>
                            <input type="text" id="new_product_name" name="new_product_name" required>
                        </div>
                        
                        <div>
                            <label for="new_actual_cost">Actual Cost (Per Unit)</label>
                            <input type="number" id="new_actual_cost" name="new_actual_cost" step="0.01" min="0.01" required>
                        </div>
                        
                        <button type="submit" name="add_new_product" value="1">Add Product to Database</button>
                    </form>
                </section>
                
                <!-- Add Sales Data Form -->
               <section id="add-product">
    <h2>Add Product Sales Data</h2>
    
    <?php if ($sales_data_added): ?>
    <div class="alert alert-success">
        Sales data has been successfully added!
    </div>
    <?php endif; ?>
    
    <form id="productForm" method="POST" action="save_product.php">
        <!-- Date Field - Moved to the top -->
        <div>
            <label for="dateAdded">Date</label>
            <input type="date" id="dateAdded" name="dateAdded" required>
        </div>
        
        <div>
            <label for="productName">Product Name</label>
            <select id="productName" name="productName" class="select2-dropdown" required onchange="updateProductDetails()">
                <option value="">-- Select Product Name --</option>
                <?php foreach($products as $product): ?>
                <option value="<?php echo htmlspecialchars($product['product_name']); ?>" 
                        data-sku="<?php echo htmlspecialchars($product['sku']); ?>"
                        data-cost="<?php echo htmlspecialchars($product['actual_cost']); ?>">
                    <?php echo htmlspecialchars($product['sku'] . ' - ' . $product['product_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="sku">SKU</label>
            <input type="text" id="sku" name="sku" readonly>
        </div>

        <div>
            <label for="adsSpend">Ads Spend (RM)</label>
            <input type="number" id="adsSpend" name="adsSpend" step="0.01" required>
        </div>

        <div>
            <label for="purchase">Purchase</label>
            <input type="number" id="purchase" name="purchase" required>
        </div>

        <div>
            <label for="unitSold">Units Sold</label>
            <input type="number" id="unitSold" name="unitSold" required>
        </div>

        <div>
            <label for="actualCost">Actual Cost (Per Unit)</label>
            <input type="number" id="actualCost" name="actualCost" step="0.01" required>
            <small>Default value is from database, but you can update it if the cost has changed.</small>
        </div>

        <div>
            <label for="sales">Sales (RM)</label>
            <input type="number" id="sales" name="sales" step="0.01" required>
        </div>

        <button type="submit" name="add_sales_data" value="1">Add Product</button>
    </form>
</section>

                <!-- Sales Calculator -->
                <section id="sales-calculator">
                    <h2>Sales Calculator</h2>
                    <div>
                        <label for="salesData">(Follow format, contoh: 2unit(salah) ,2 unit(betul) (jarak antara number dan unit)</label>
                        <textarea id="salesData" rows="8" placeholder="Paste your sales data here..."></textarea>
                    </div>
                    
                    <button onclick="calculateSales()">Calculate</button>

                    <!-- Results Container -->
                    <div id="resultsContainer" class="results" style="display: none;">
                        <h3>Results</h3>
                        <p><strong>Total Purchases:</strong> <span id="totalPurchase">0</span></p>
                        <p><strong>Total Units Sold:</strong> <span id="totalUnits">0</span></p>
                        <p><strong>Total Sales (RM):</strong> <span id="totalSales">0.00</span></p>
                        <p><strong>Total COD (RM):</strong> <span id="totalCod">0.00</span></p>
                        <p><strong>COGS will be calculated based on actual cost × units sold + COD</strong></p>
                        <button onclick="populateForm()" class="btn-secondary">Fill Form with These Values</button>
                    </div>
                </section>
            </div>

            <!-- Quick Access Dashboard Cards -->
            <section id="quick-access">
                <h2>Quick Access</h2>
                <div class="quick-access-grid">
                    <a href="team_products.php" style="text-decoration: none;">
                        <div class="quick-card card-success">
                            <h3>Team Products</h3>
                            <p>View and manage all product data</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" style="text-decoration: none;">
                        <div class="quick-card card-primary">
                            <h3>Sales Reports</h3>
                            <p>View charts and download reports</p>
                        </div>
                    </a>
                </div>
            </section>
        </main>
    </div>

    <script>
    // Function to update product details when a product is selected
function updateProductDetails() {
    const productSelect = document.getElementById('productName');
    const skuInput = document.getElementById('sku');
    const actualCostInput = document.getElementById('actualCost');
    
    if (productSelect.selectedIndex > 0) {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const sku = selectedOption.getAttribute('data-sku');
        const cost = selectedOption.getAttribute('data-cost');
        
        // Update the SKU field
        skuInput.value = sku;
        
        // Only set the actual cost if the field is empty or if the user hasn't manually changed it
        if (actualCostInput.value === '' || !actualCostInput.dataset.userModified) {
            actualCostInput.value = cost;
        }
    } else {
        skuInput.value = '';
        if (!actualCostInput.dataset.userModified) {
            actualCostInput.value = '';
        }
    }
}
    
  function calculateSales() {
    let data = document.getElementById('salesData').value.trim().split("\n");
    let totalPurchase = 0;
    let totalUnits = 0;
    let totalSales = 0;
    let totalCod = 0;

    data.forEach(line => {
        let match = line.match(/(\d+) UNIT.*?R\.M\.(\d+)/i); // Match UNIT sales
        let simenMatch = line.match(/(\d+) SIMEN.*?R\.M\.(\d+)/i); // Match SIMEN sales
        let botolMatch = line.match(/(\d+) BOTOL.*?R\.M\.(\d+)/i); // Match BOTOL sales
        let helaiMatch = line.match(/(\d+) HELAI.*?R\.M\.(\d+)/i); // Match HELAI sales
        let kotakMatch = line.match(/(\d+) KOTAK.*?R\.M\.(\d+)/i); // Match KOTAK sales
        let paketMatch = line.match(/(\d+) PAKET.*?R\.M\.(\d+)/i); // Match PAKET sales
        let racunMatch = line.match(/(\d+) RACUN.*?R\.M\.(\d+)/i); // Match RACUN sales
        let setMatch = line.match(/(\d+) SET.*?R\.M\.(\d+)/i); // Match SET sales
        
        if (match) {
            let units = parseInt(match[1]);
            let price = parseFloat(match[2]);
            
            totalPurchase += 1;
            totalUnits += units;
            totalSales += price;
            totalCod += 10; // Each purchase has RM10 COD
        }

        if (simenMatch) {
            let simenUnits = parseInt(simenMatch[1]);
            let simenPrice = parseFloat(simenMatch[2]);
            
            totalPurchase += 1;
            totalUnits += simenUnits;
            totalSales += simenPrice;
            totalCod += 10; // Each purchase has RM10 COD
        }

        if (botolMatch) {
            let botolUnits = parseInt(botolMatch[1]);
            let botolPrice = parseFloat(botolMatch[2]);
            
            totalPurchase += 1;
            totalUnits += botolUnits;
            totalSales += botolPrice;
            totalCod += 10; // Each purchase has RM10 COD
        }

        if (helaiMatch) {
            let helaiUnits = parseInt(helaiMatch[1]);
            let helaiPrice = parseFloat(helaiMatch[2]);
            
            totalPurchase += 1;
            totalUnits += helaiUnits;
            totalSales += helaiPrice;
            totalCod += 10; // Each purchase has RM10 COD
        }

        if (kotakMatch) {
            let kotakUnits = parseInt(kotakMatch[1]);
            let kotakPrice = parseFloat(kotakMatch[2]);
            
            totalPurchase += 1;
            totalUnits += kotakUnits;
            totalSales += kotakPrice;
            totalCod += 10; // Each purchase has RM10 COD
        }

        if (paketMatch) {
            let paketUnits = parseInt(paketMatch[1]);
            let paketPrice = parseFloat(paketMatch[2]);
            
            totalPurchase += 1;
            totalUnits += paketUnits;
            totalSales += paketPrice;
            totalCod += 10; // Each purchase has RM10 COD
        }

        if (racunMatch) {
            let racunUnits = parseInt(racunMatch[1]);
            let racunPrice = parseFloat(racunMatch[2]);
            
            totalPurchase += 1;
            totalUnits += racunUnits;
            totalSales += racunPrice;
            totalCod += 10; // Each purchase has RM10 COD
        }
        
        if (setMatch) {
            let setUnits = parseInt(setMatch[1]);
            let setPrice = parseFloat(setMatch[2]);
            
            totalPurchase += 1;
            totalUnits += setUnits;
            totalSales += setPrice;
            totalCod += 10; // Each purchase has RM10 COD
        }
    });

    // Calculate total sales including COD
    let totalSalesWithCod = totalSales + totalCod;

    // Show results after calculation
    document.getElementById('resultsContainer').style.display = "block";
    document.getElementById('totalPurchase').innerText = totalPurchase;
    document.getElementById('totalUnits').innerText = totalUnits;
    document.getElementById('totalSales').innerText = totalSalesWithCod.toFixed(2); // Now includes COD
    document.getElementById('totalCod').innerText = totalCod.toFixed(2);
}

function populateForm() {
    const purchase = document.getElementById('totalPurchase').innerText;
    const unitSold = document.getElementById('totalUnits').innerText;
    const sales = document.getElementById('totalSales').innerText; // This now includes COD
    
    document.getElementById('purchase').value = purchase;
    document.getElementById('unitSold').value = unitSold;
    document.getElementById('sales').value = sales;
    
    // Alert user that values have been populated
    alert('Sales data has been populated in the form. Please complete any remaining fields.');
}
    
    // Set today's date as default for dateAdded
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('dateAdded').value = today;
        
        // Initialize Select2 on the product dropdown
        $('.select2-dropdown').select2({
            placeholder: "Search and select a product...",
            allowClear: true,
            width: '100%'
        });
        
        // Make sure the updateProductDetails function works with Select2
        $('#productName').on('select2:select', function (e) {
            updateProductDetails();
        });
        
        // Also trigger when changed directly
        $('#productName').on('change', function() {
            updateProductDetails();
        });
        
        // Initialize values on page load if a product is already selected
        updateProductDetails();
    });
    // Dismiss success notification
function dismissNotification() {
    const notification = document.getElementById('successNotification');
    if (notification) {
        notification.style.animation = 'fadeOut 0.5s forwards';
        setTimeout(() => {
            notification.remove();
        }, 500);
    }
}

// Add this to your document.addEventListener
document.addEventListener('DOMContentLoaded', function() {
    // Your existing code here
    
    // Auto-dismiss success notification after 6 seconds
    const successNotification = document.getElementById('successNotification');
    if (successNotification) {
        setTimeout(() => {
            dismissNotification();
        }, 6000);
    }
});
document.addEventListener('DOMContentLoaded', function() {
    const actualCostInput = document.getElementById('actualCost');
    
    // Track when user manually edits the actual cost
    actualCostInput.addEventListener('input', function() {
        this.dataset.userModified = 'true';
    });
    
    // When form is reset, clear the userModified flag
    document.getElementById('productForm').addEventListener('reset', function() {
        actualCostInput.dataset.userModified = '';
    });
    
    // Rest of your existing DOMContentLoaded code...
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('dateAdded').value = today;
    
    // Initialize Select2 on the product dropdown
    $('.select2-dropdown').select2({
        placeholder: "Search and select a product...",
        allowClear: true,
        width: '100%'
    });
    
    // Make sure the updateProductDetails function works with Select2
    $('#productName').on('select2:select', function (e) {
        updateProductDetails();
    });
    
    // Also trigger when changed directly
    $('#productName').on('change', function() {
        updateProductDetails();
    });
    
    // Initialize values on page load if a product is already selected
    updateProductDetails();
    
    // Auto-dismiss success notification after 6 seconds
    const successNotification = document.getElementById('successNotification');
    if (successNotification) {
        setTimeout(() => {
            dismissNotification();
        }, 6000);
    }
});
    </script>
</body>
</html>