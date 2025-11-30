<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Product ID is required";
    header("Location: team_products.php");
    exit;
}

$product_id = $_GET['id'];

// First, let's check what column exists in the teams table to determine the primary key
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Get product details
$sql = "SELECT p.*, t.team_name 
        FROM products p
        LEFT JOIN teams t ON p.team_id = t.$team_pk
        WHERE p.id = ?";

$stmt = $dbconn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Product not found";
    header("Location: team_products.php");
    exit;
}

$product = $result->fetch_assoc();

// Check if user has permission to view this product
if (!$is_admin && $product['team_id'] != $team_id) {
    $_SESSION['error_message'] = "You don't have permission to view this product";
    header("Location: team_products.php");
    exit;
}

// Get product sales history (for chart)
$sql_history = "SELECT 
    DATE(created_at) as sale_date,
    SUM(unit_sold) as units,
    SUM(sales) as daily_sales,
    SUM(profit) as daily_profit
FROM products
WHERE sku = ? AND created_at <= ?
GROUP BY DATE(created_at)
ORDER BY sale_date
LIMIT 30";

$stmt_history = $dbconn->prepare($sql_history);
$stmt_history->bind_param("ss", $product['sku'], $product['created_at']);
$stmt_history->execute();
$history_result = $stmt_history->get_result();

// Prepare data for chart
$dates = [];
$units_data = [];
$sales_data = [];
$profit_data = [];

while ($row = $history_result->fetch_assoc()) {
    $dates[] = $row['sale_date'];
    $units_data[] = $row['units'];
    $sales_data[] = $row['daily_sales'];
    $profit_data[] = $row['daily_profit'];
}

// Calculate profit margin
$profit_margin = (($product['sales'] ?? 0) > 0) ? (($product['profit'] ?? 0) / $product['sales']) * 100 : 0;

// Include the navigation component
include 'navigation.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product - <?php echo htmlspecialchars($product['product_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --background-light: #f4f6f8;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--background-light);
            line-height: 1.6;
            color: var(--text-dark);
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: white;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .product-info {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
        }

        .product-info h2 {
            margin-top: 0;
            color: var(--primary-color);
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }

        .product-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .meta-item {
            margin-bottom: 1rem;
        }

        .meta-item .label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 0.25rem;
        }

        .meta-item .value {
            font-size: 1.1rem;
            font-weight: 500;
        }

        .chart-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .stat-card:nth-child(2) .stat-icon {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-card:nth-child(3) .stat-icon {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
        }

        .stat-card:nth-child(4) .stat-icon {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }

        .stat-title {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0.5rem 0;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
        }

        .btn:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
        }

        .btn-warning {
            background-color: var(--warning-color);
        }

        .btn-danger {
            background-color: var(--accent-color);
        }

        .btn-back {
            background-color: var(--text-light);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Product Details</h1>
            <a href="team_products.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </header>

        <!-- Product Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <p class="stat-title">Sales Amount</p>
                <h3 class="stat-value">RM <?php echo number_format($product['sales'] ?? 0, 2); ?></h3>

            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <p class="stat-title">Profit</p>
               <h3 class="stat-value">RM <?php echo number_format($product['profit'] ?? 0, 2); ?></h3>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <p class="stat-title">Profit Margin</p>
                <h3 class="stat-value"><?php echo number_format($profit_margin ?? 0, 1); ?>%</h3>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <p class="stat-title">Units Sold</p>
                <h3 class="stat-value"><?php echo number_format($product['unit_sold'] ?? 0); ?></h3>
            </div>
        </div>

        <div class="product-grid">
            <!-- Product Info -->
            <div class="product-info">
                <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
                
                <div class="product-meta">
                    <div class="meta-item">
                        <div class="label">SKU</div>
                        <div class="value"><?php echo htmlspecialchars($product['sku']); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="label">Date</div>
                        <div class="value"><?php echo date('d M Y', strtotime($product['created_at'])); ?></div>
                    </div>
                    
                    <?php if($is_admin): ?>
                    <div class="meta-item">
                        <div class="label">Team</div>
                        <div class="value"><?php echo htmlspecialchars($product['team_name'] ?? 'N/A'); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="meta-item">
                        <div class="label">Item Cost</div>
                        <div class="value">RM <?php echo number_format($product['item_cost'] ?? 0, 2); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="label">Actual Cost</div>
                        <div class="value">RM <?php echo number_format($product['actual_cost'], 2); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="label">Total Parcels</div>
                        <div class="value"><?php echo number_format($product['purchase'] ?? 0); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="label">Profit per Parcel</div>
                        <div class="value">RM <?php echo number_format($product['purchase'] > 0 ? $product['profit'] / $product['purchase'] : 0, 2); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="label">Sales per Parcel</div>
                        <div class="value">RM <?php echo number_format($product['purchase'] > 0 ? $product['sales'] / $product['purchase'] : 0, 2); ?></div>
                    </div>
                    
                    <div class="meta-item">
                        <div class="label">ROAS</div>
                        <div class="value"><?php echo $product['ads_spend'] > 0 ? number_format($product['sales'] / $product['ads_spend'], 2) . 'x' : 'N/A'; ?></div>
                    </div>

                    <div class="meta-item">
                        <div class="label">Profit per Unit</div>
                        <div class="value">RM <?php echo number_format($product['unit_sold'] > 0 ? $product['profit'] / $product['unit_sold'] : 0, 2); ?></div>
                    </div>
                    
                    <?php if(isset($product['ads_spend']) && $product['ads_spend'] > 0): ?>
                    <div class="meta-item">
                        <div class="label">Ad Spend</div>
                        <div class="value">RM <?php echo number_format($product['ads_spend'], 2); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($product['purchase']) && $product['purchase'] > 0): ?>
                    <div class="meta-item">
                        <div class="label">Purchase</div>
                        <div class="value">RM <?php echo number_format($product['purchase'], 2); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($product['cod']) && $product['cod'] > 0): ?>
                    <div class="meta-item">
                        <div class="label">COD</div>
                        <div class="value">RM <?php echo number_format($product['cod'], 2); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit Product
                    </a>
                    
                    <form method="POST" action="delete_product.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Product Performance Chart -->
            <div class="chart-container">
                <h3>Product Performance History</h3>
                <canvas id="productPerformanceChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Product Performance Chart
        const performanceCtx = document.getElementById('productPerformanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [
                    {
                        label: 'Units Sold',
                        data: <?php echo json_encode($units_data); ?>,
                        borderColor: 'rgba(243, 156, 18, 1)',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        borderWidth: 2,
                        yAxisID: 'y1',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Sales (RM)',
                        data: <?php echo json_encode($sales_data); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 2,
                        yAxisID: 'y',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Profit (RM)',
                        data: <?php echo json_encode($profit_data); ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        borderWidth: 2,
                        yAxisID: 'y',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Amount (RM)'
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Units'
                        },
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>