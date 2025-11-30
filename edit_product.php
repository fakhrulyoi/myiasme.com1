<?php
require 'dbconn_productProfit.php';
require 'auth.php'; // For authentication

// Fetch product data for the given ID
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM products WHERE id = ?";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Check if user has permission to edit this product
        if (!$is_admin && isset($team_id) && $product['team_id'] != $team_id) {
            echo "<script>alert('You do not have permission to edit this product.'); window.location.href='team_products.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Product not found.'); window.location.href='team_products.php';</script>";
        exit;
    }
}

// Handle form submission for updating product data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $sku = $_POST['sku'];
    $adsSpend = floatval($_POST['adsSpend']);
    $purchase = intval($_POST['purchase']);
    $unitSold = intval($_POST['unitSold']);
    $actualCost = floatval($_POST['actualCost']);
    $sales = floatval($_POST['sales']);
    $dateAdded = $_POST['dateAdded'];

    // Perform calculations
    $adsSpendWithSST = $adsSpend;
    $cpp = ($purchase > 0) ? ($adsSpendWithSST / $purchase) : 0;
    $itemCost = $unitSold * $actualCost;
    $cod = $purchase * 10;
    $cogs = $itemCost + $cod;
    $profit = $sales - $adsSpendWithSST - $cogs;

    // Update query with SKU field
    $sql = "UPDATE products 
            SET sku = ?, ads_spend = ?, purchase = ?, cpp = ?, unit_sold = ?, actual_cost = ?, item_cost = ?, cod = ?, sales = ?, profit = ?, created_at = ? 
            WHERE id = ?";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("sddiddidddsi", $sku, $adsSpendWithSST, $purchase, $cpp, $unitSold, $actualCost, $itemCost, $cod, $sales, $profit, $dateAdded, $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Product updated successfully!";
        header("Location: team_products.php");
        exit;
    } else {
        $error_message = "Error updating product: " . $stmt->error;
    }
    $stmt->close();
}

// Calculate some metrics for the preview panel
$cpp = ($product['purchase'] > 0) ? ($product['ads_spend'] / $product['purchase']) : 0;
$profitMargin = ($product['sales'] > 0) ? ($product['profit'] / $product['sales']) * 100 : 0;
$roas = ($product['ads_spend'] > 0) ? ($product['sales'] / $product['ads_spend']) : 0;

// Include the navigation component
include 'navigation.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - <?php echo htmlspecialchars($product['product_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #1E3C72;
            --primary-hover: #2A5298;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --light-bg: #f4f6f9;
            --border-color: #dbe0e6;
            --text-color: #2c3e50;
            --text-muted: #7f8c8d;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --border-radius: 8px;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-header h1 {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 600;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: var(--primary-hover);
        }
        
        /* Main content layout */
        .edit-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 992px) {
            .edit-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Form styles */
        .edit-form {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 25px;
        }
        
        .form-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }
        
        .form-control[readonly] {
            background-color: var(--light-bg);
            cursor: not-allowed;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon .form-control {
            padding-left: 40px;
        }
        
        .input-icon i {
            position: absolute;
            top: 12px;
            left: 15px;
            color: var(--text-muted);
        }
        
        .form-row.actions {
            margin-top: 30px;
            gap: 15px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-cancel {
            background-color: #e9ecef;
            color: var(--text-color);
        }
        
        .btn-cancel:hover {
            background-color: #dee2e6;
            transform: translateY(-2px);
        }
        
        /* Preview panel */
        .preview-panel {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 25px;
        }
        
        .preview-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .preview-item {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 15px;
            transition: transform 0.2s;
        }
        
        .preview-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .preview-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        
        .preview-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .preview-item.success {
            border-left: 4px solid var(--success-color);
        }
        
        .preview-item.warning {
            border-left: 4px solid var(--warning-color);
        }
        
        .preview-item.danger {
            border-left: 4px solid var(--accent-color);
        }
        
        .preview-item.primary {
            border-left: 4px solid var(--primary-color);
        }
        
        .preview-item.secondary {
            border-left: 4px solid var(--secondary-color);
        }
        
        /* Calculation box */
        .calculation-box {
            margin-top: 25px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }
        
        .calculation-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .calculation-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--border-color);
        }
        
        .calculation-item:last-child {
            border-bottom: none;
            font-weight: 600;
        }
        
        .calculation-label {
            color: var(--text-muted);
        }
        
        .calculation-value {
            font-weight: 500;
        }
        
        /* Alert box */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }
        
        /* Live updates feature */
        .live-update {
            font-size: 13px;
            color: var(--success-color);
            margin-top: 5px;
            display: none;
        }
        
        .live-update.show {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>Edit Product</h1>
            <a href="team_products.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="edit-layout">
            <!-- Edit Form -->
            <div class="edit-form">
                <h2 class="form-title">Product Information</h2>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Edit the fields below to update product information. The preview panel on the right shows calculated metrics.
                </div>
                
                <form method="POST" action="edit_product.php" id="productForm">
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sku" class="form-label">SKU</label>
                            <div class="input-icon">
                                <i class="fas fa-barcode"></i>
                                <input type="text" id="sku" name="sku" class="form-control" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="productName" class="form-label">Product Name</label>
                            <div class="input-icon">
                                <i class="fas fa-box"></i>
                                <input type="text" id="productName" name="productName" class="form-control" value="<?php echo htmlspecialchars($product['product_name']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adsSpend" class="form-label">Ads Spend (RM)</label>
                            <div class="input-icon">
                                <i class="fas fa-ad"></i>
                                <input type="number" id="adsSpend" name="adsSpend" class="form-control" step="0.01" value="<?php echo $product['ads_spend']; ?>" required>
                            </div>
                            <div class="live-update" id="adsSpendUpdate">Updated</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="purchase" class="form-label">Purchase (Total Parcels)</label>
                            <div class="input-icon">
                                <i class="fas fa-shopping-bag"></i>
                                <input type="number" id="purchase" name="purchase" class="form-control" value="<?php echo $product['purchase']; ?>" required>
                            </div>
                            <div class="live-update" id="purchaseUpdate">Updated</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unitSold" class="form-label">Units Sold</label>
                            <div class="input-icon">
                                <i class="fas fa-cart-plus"></i>
                                <input type="number" id="unitSold" name="unitSold" class="form-control" value="<?php echo $product['unit_sold']; ?>" required>
                            </div>
                            <div class="live-update" id="unitSoldUpdate">Updated</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="actualCost" class="form-label">Actual Cost Per Unit (RM)</label>
                            <div class="input-icon">
                                <i class="fas fa-tag"></i>
                                <input type="number" id="actualCost" name="actualCost" class="form-control" step="0.01" value="<?php echo $product['actual_cost']; ?>" required>
                            </div>
                            <div class="live-update" id="actualCostUpdate">Updated</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sales" class="form-label">Total Sales (RM)</label>
                            <div class="input-icon">
                                <i class="fas fa-dollar-sign"></i>
                                <input type="number" id="sales" name="sales" class="form-control" step="0.01" value="<?php echo $product['sales']; ?>" required>
                            </div>
                            <div class="live-update" id="salesUpdate">Updated</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dateAdded" class="form-label">Date</label>
                            <div class="input-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" id="dateAdded" name="dateAdded" class="form-control" value="<?php echo $product['created_at']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row actions">
                        <a href="team_products.php" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Preview Panel -->
            <div class="preview-panel">
                <h2 class="preview-title">Live Preview</h2>
                
                <div class="preview-grid">
                    <div class="preview-item success">
                        <div class="preview-label">Profit</div>
                        <div class="preview-value" id="previewProfit">RM <?php echo number_format($product['profit'], 2); ?></div>
                    </div>
                    
                    <div class="preview-item primary">
                        <div class="preview-label">ROAS</div>
                        <div class="preview-value" id="previewROAS"><?php echo number_format($roas, 2); ?>x</div>
                    </div>
                    
                    <div class="preview-item secondary">
                        <div class="preview-label">Cost Per Purchase (CPP)</div>
                        <div class="preview-value" id="previewCPP">RM <?php echo number_format($cpp, 2); ?></div>
                    </div>
                    
                    <div class="preview-item warning">
                        <div class="preview-label">Profit Margin</div>
                        <div class="preview-value" id="previewMargin"><?php echo number_format($profitMargin, 1); ?>%</div>
                    </div>
                </div>
                
                <div class="calculation-box">
                    <h3 class="calculation-title">Profit Calculation</h3>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Total Sales</span>
                        <span class="calculation-value" id="calcSales">RM <?php echo number_format($product['sales'], 2); ?></span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Ads Spend</span>
                        <span class="calculation-value" id="calcAdsSpend">- RM <?php echo number_format($product['ads_spend'], 2); ?></span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Item Cost (<?php echo number_format($product['unit_sold']); ?> units × RM <?php echo number_format($product['actual_cost'], 2); ?>)</span>
                        <span class="calculation-value" id="calcItemCost">- RM <?php echo number_format($product['item_cost'], 2); ?></span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">COD (<?php echo number_format($product['purchase']); ?> parcels × RM 10)</span>
                        <span class="calculation-value" id="calcCOD">- RM <?php echo number_format($product['cod'], 2); ?></span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Net Profit</span>
                        <span class="calculation-value" id="calcProfit">RM <?php echo number_format($product['profit'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Live calculation updates
        document.addEventListener('DOMContentLoaded', function() {
            const adsSpendInput = document.getElementById('adsSpend');
            const purchaseInput = document.getElementById('purchase');
            const unitSoldInput = document.getElementById('unitSold');
            const actualCostInput = document.getElementById('actualCost');
            const salesInput = document.getElementById('sales');
            
            // Update function
            function updateCalculations() {
                // Get values
                const adsSpend = parseFloat(adsSpendInput.value) || 0;
                const purchase = parseInt(purchaseInput.value) || 0;
                const unitSold = parseInt(unitSoldInput.value) || 0;
                const actualCost = parseFloat(actualCostInput.value) || 0;
                const sales = parseFloat(salesInput.value) || 0;
                
                // Calculate
                const cpp = purchase > 0 ? adsSpend / purchase : 0;
                const itemCost = unitSold * actualCost;
                const cod = purchase * 10;
                const cogs = itemCost + cod;
                const profit = sales - adsSpend - cogs;
                const profitMargin = sales > 0 ? (profit / sales) * 100 : 0;
                const roas = adsSpend > 0 ? sales / adsSpend : 0;
                
                // Update preview values
                document.getElementById('previewProfit').textContent = 'RM ' + profit.toFixed(2);
                document.getElementById('previewROAS').textContent = roas.toFixed(2) + 'x';
                document.getElementById('previewCPP').textContent = 'RM ' + cpp.toFixed(2);
                document.getElementById('previewMargin').textContent = profitMargin.toFixed(1) + '%';
                
                // Update calculation details
                document.getElementById('calcSales').textContent = 'RM ' + sales.toFixed(2);
                document.getElementById('calcAdsSpend').textContent = '- RM ' + adsSpend.toFixed(2);
                document.getElementById('calcItemCost').textContent = '- RM ' + itemCost.toFixed(2);
                document.getElementById('calcCOD').textContent = '- RM ' + cod.toFixed(2);
                document.getElementById('calcProfit').textContent = 'RM ' + profit.toFixed(2);
                
                // Set color for profit based on value
                const profitElement = document.getElementById('previewProfit');
                if (profit > 0) {
                    profitElement.style.color = 'var(--success-color)';
                } else if (profit < 0) {
                    profitElement.style.color = 'var(--accent-color)';
                } else {
                    profitElement.style.color = 'var(--text-color)';
                }
            }
            
            // Show update notification
            function showUpdateNotification(elementId) {
                const notification = document.getElementById(elementId);
                notification.classList.add('show');
                setTimeout(() => {
                    notification.classList.remove('show');
                }, 1000);
            }
            
            // Add event listeners
            adsSpendInput.addEventListener('input', function() {
                updateCalculations();
                showUpdateNotification('adsSpendUpdate');
            });
            
            purchaseInput.addEventListener('input', function() {
                updateCalculations();
                showUpdateNotification('purchaseUpdate');
            });
            
            unitSoldInput.addEventListener('input', function() {
                updateCalculations();
                showUpdateNotification('unitSoldUpdate');
            });
            
            actualCostInput.addEventListener('input', function() {
                updateCalculations();
                showUpdateNotification('actualCostUpdate');
            });
            
            salesInput.addEventListener('input', function() {
                updateCalculations();
                showUpdateNotification('salesUpdate');
            });
            
            // Run initial calculation
            updateCalculations();
        });
    </script>
</body>
</html>