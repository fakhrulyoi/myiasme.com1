<?php
require 'auth.php';
require 'dbconn_productProfit.php';
// Start the session to retrieve messages
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

// Display success message if it exists
if (isset($_SESSION['success_message'])) {
    echo "<script>alert('" . $_SESSION['success_message'] . "');</script>";
    // Clear the message so it doesn't show again on refresh
    unset($_SESSION['success_message']);
}

// Display error message if it exists
if (isset($_SESSION['error_message'])) {
    echo "<script>alert('" . $_SESSION['error_message'] . "');</script>";
    // Clear the message so it doesn't show again on refresh
    unset($_SESSION['error_message']);
}

// Get search query parameter
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Get date range for filtering
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// First, let's check what column exists in the teams table to determine the primary key
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Get team name
$team_name = "Your Team";
if (!$is_admin && isset($team_id)) {
    $sql = "SELECT team_name FROM teams WHERE $team_pk = ?";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($team_row = $result->fetch_assoc()) {
        $team_name = $team_row['team_name'];
    }
}

// Get team stats
$sql_stats = "SELECT 
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    COUNT(*) as total_products,
    COUNT(DISTINCT product_name) as unique_products,
    SUM(unit_sold) as total_units
FROM products
WHERE created_at BETWEEN ? AND ? ";

if (!$is_admin) {
    $sql_stats .= "AND team_id = ?";
}

$stmt_stats = $dbconn->prepare($sql_stats);

if (!$is_admin) {
    $stmt_stats->bind_param("ssi", $start_date, $end_date, $team_id);
} else {
    $stmt_stats->bind_param("ss", $start_date, $end_date);
}

$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// Get top selling products for team
$sql_top_products = "SELECT 
    sku,
    product_name,
    SUM(unit_sold) as total_sold,
    SUM(sales) as total_sales,
    SUM(profit) as total_profit,
    AVG(profit/sales)*100 as profit_margin,
    MAX(created_at) as last_sale
FROM products
WHERE created_at BETWEEN ? AND ? ";

if (!$is_admin) {
    $sql_top_products .= "AND team_id = ? ";
}

$sql_top_products .= "GROUP BY sku, product_name
ORDER BY total_sales DESC
LIMIT 10";

$stmt_top_products = $dbconn->prepare($sql_top_products);

if (!$is_admin) {
    $stmt_top_products->bind_param("ssi", $start_date, $end_date, $team_id);
} else {
    $stmt_top_products->bind_param("ss", $start_date, $end_date);
}

$stmt_top_products->execute();
$top_products = $stmt_top_products->get_result();

// Get daily sales data for chart
$sql_daily_sales = "SELECT 
    DATE(created_at) as sale_date,
    SUM(sales) as daily_sales,
    SUM(profit) as daily_profit
FROM products
WHERE created_at BETWEEN ? AND ? ";

if (!$is_admin) {
    $sql_daily_sales .= "AND team_id = ? ";
}

$sql_daily_sales .= "GROUP BY DATE(created_at)
ORDER BY sale_date";

$stmt_daily_sales = $dbconn->prepare($sql_daily_sales);

if (!$is_admin) {
    $stmt_daily_sales->bind_param("ssi", $start_date, $end_date, $team_id);
} else {
    $stmt_daily_sales->bind_param("ss", $start_date, $end_date);
}

$stmt_daily_sales->execute();
$daily_sales = $stmt_daily_sales->get_result();

// Prepare data for charts
$dates = [];
$sales_data = [];
$profit_data = [];

while ($row = $daily_sales->fetch_assoc()) {
    $dates[] = $row['sale_date'];
    $sales_data[] = $row['daily_sales'];
    $profit_data[] = $row['daily_profit'];
}

// Include the navigation component
include 'navigation.php';

// Get all products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$sql_all_products = "SELECT 
    p.id,  
    p.created_at,
    p.sku,
    p.product_name,
    p.unit_sold,
    p.item_cost,
    p.sales,
    p.profit,
    t.team_name 
FROM products p
LEFT JOIN teams t ON p.team_id = t.$team_pk
WHERE 1=1 "; // Start with 1=1 to make adding conditions easier

// Add search condition if search query is provided
if (!empty($search_query)) {
    $sql_all_products .= "AND (p.sku LIKE ? OR p.product_name LIKE ?) ";
}

// Add date filtering only if not showing all
if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
    $sql_all_products .= "AND p.created_at BETWEEN ? AND ? ";
}

if (!$is_admin) {
    $sql_all_products .= "AND p.team_id = ? ";
}

$sql_all_products .= "ORDER BY p.created_at DESC
LIMIT ?, ?";

// Prepare the statement before binding parameters
$stmt_all_products = $dbconn->prepare($sql_all_products);

// Binding parameters for the all products query
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    
    if (!$is_admin) {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_all_products->bind_param("ssssiiii", $search_param, $search_param, $start_date, $end_date, $team_id, $offset, $items_per_page);
        } else {
            $stmt_all_products->bind_param("ssiii", $search_param, $search_param, $team_id, $offset, $items_per_page);
        }
    } else {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_all_products->bind_param("ssssii", $search_param, $search_param, $start_date, $end_date, $offset, $items_per_page);
        } else {
            $stmt_all_products->bind_param("ssii", $search_param, $search_param, $offset, $items_per_page);
        }
    }
} else {
    // Original binding logic without search
    if (!$is_admin) {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_all_products->bind_param("ssiii", $start_date, $end_date, $team_id, $offset, $items_per_page);
        } else {
            $stmt_all_products->bind_param("iii", $team_id, $offset, $items_per_page);
        }
    } else {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_all_products->bind_param("ssii", $start_date, $end_date, $offset, $items_per_page);
        } else {
            $stmt_all_products->bind_param("ii", $offset, $items_per_page);
        }
    }
}

$stmt_all_products->execute();
$all_products = $stmt_all_products->get_result();

// Get total count of products for pagination
$sql_count = "SELECT COUNT(*) as total FROM products p WHERE 1=1 ";

// Add search condition if search query is provided
if (!empty($search_query)) {
    $sql_count .= "AND (p.sku LIKE ? OR p.product_name LIKE ?) ";
}

// Add date filtering if not showing all
if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
    $sql_count .= "AND p.created_at BETWEEN ? AND ? ";
}

if (!$is_admin) {
    $sql_count .= "AND p.team_id = ? ";
}

$stmt_count = $dbconn->prepare($sql_count);

// Binding parameters for the count query
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    
    if (!$is_admin) {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_count->bind_param("ssssi", $search_param, $search_param, $start_date, $end_date, $team_id);
        } else {
            $stmt_count->bind_param("ssi", $search_param, $search_param, $team_id);
        }
    } else {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_count->bind_param("ssss", $search_param, $search_param, $start_date, $end_date);
        } else {
            $stmt_count->bind_param("ss", $search_param, $search_param);
        }
    }
} else {
    // Original binding logic without search
    if (!$is_admin) {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_count->bind_param("ssi", $start_date, $end_date, $team_id);
        } else {
            $stmt_count->bind_param("i", $team_id);
        }
    } else {
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && !isset($_GET['show_all'])) {
            $stmt_count->bind_param("ss", $start_date, $end_date);
        }
        // No parameters needed if admin and no date filter
    }
}

$stmt_count->execute();
$count_result = $stmt_count->get_result();
$count_data = $count_result->fetch_assoc();
$total_records = $count_data['total'];
$total_pages = ceil($total_records / $items_per_page);
?>
<style>
    /* Enhanced buttons for the Actions column */
.action-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.action-btn i {
    margin-right: 5px;
    font-size: 13px;
}

.action-btn-view {
    background: linear-gradient(to bottom, #3498db, #2980b9);
}

.action-btn-view:hover {
    background: linear-gradient(to bottom, #2980b9, #2573a7);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
}

.action-btn-edit {
    background: linear-gradient(to bottom, #f39c12, #e67e22);
}

.action-btn-edit:hover {
    background: linear-gradient(to bottom, #e67e22, #d35400);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
}

.action-btn-delete {
    background: linear-gradient(to bottom, #e74c3c, #c0392b);
}

.action-btn-delete:hover {
    background: linear-gradient(to bottom, #c0392b, #a93226);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
}

/* For smaller screens, adjust the icons */
@media (max-width: 576px) {
    .action-buttons {
        gap: 5px;
    }
    
    .action-btn {
        padding: 6px 10px;
    }
    
    .btn-text {
        display: none; /* Hide text on small screens */
    }
    
    .action-btn i {
        margin-right: 0;
        font-size: 14px;
    }
}
    :root {
    --primary-color: #2c3e50;
    --secondary-color: #3498db;
    --background-light: #f4f6f8;
    --text-dark: #2c3e50;
    --border-radius: 8px;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
    background-color: var(--background-light);
    line-height: 1.6;
    color: var(--text-dark);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
}

.date-range form {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background-color: white;
    border-radius: var(--border-radius);
    padding: 1rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.chart-card {
    background-color: white;
    border-radius: var(--border-radius);
    padding: 1rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background-color: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

table thead {
    background-color: #f1f3f5;
}

table th, table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #f1f3f5;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding: 1rem;
    background-color: white;
    border-radius: var(--border-radius);
}
.search-container {
    display: flex;
    align-items: center;
}

.search-box {
    position: relative;
}

.search-box input {
    padding-right: 30px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
    border-color: #3498db;
    outline: none;
}

/* Highlight search results */
tbody tr:has(td:contains("<?php echo $search_query; ?>")) {
    background-color: rgba(52, 152, 219, 0.05);
}

@media (max-width: 768px) {
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-container {
        width: 100%;
        margin-top: 10px;
    }
    
    .search-box input {
        width: 100%;
    }
}

.btn {
    background-color: var(--secondary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.btn:hover {
    background-color: #2980b9;
}

.btn-danger {
    background-color: #e74c3c;
}

.btn-danger:hover {
    background-color: #c0392b;
}
</style>

<!-- Page Header -->
<header class="page-header">
    <h1><?php echo $is_admin ? "All Teams Products" : $team_name . " Products"; ?></h1>
    <div class="date-range">
        <form method="GET" action="" id="dateFilterForm">
            <!-- Preserve search query when filtering by date -->
            <?php if(!empty($search_query)): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
            <?php endif; ?>
            
            <label for="start_date">From:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            
            <label for="end_date">To:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="team_products.php<?php echo !empty($search_query) ? '?search=' . urlencode($search_query) . '&show_all=1' : '?show_all=1'; ?>" class="btn btn-secondary">Show All</a>
        </form>
    </div>
</header>

<!-- Stats Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon sales-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <p class="stat-title">Total Sales</p>
        <h3 class="stat-value">RM <?php echo number_format($stats['total_sales'] ?? 0, 2); ?></h3>
        <p class="stat-change">
            <i class="fas fa-calendar"></i> Selected period
        </p>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon profit-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <p class="stat-title">Total Profit</p>
        <h3 class="stat-value">RM <?php echo number_format($stats['total_profit'] ?? 0, 2); ?></h3>
        <p class="stat-change positive">
            <?php 
            $margin = ($stats['total_sales'] > 0) ? (($stats['total_profit'] ?? 0) / $stats['total_sales']) * 100 : 0;
            echo number_format($margin, 1) . '% margin'; 
            ?>
        </p>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon products-icon">
            <i class="fas fa-box"></i>
        </div>
        <p class="stat-title">Products</p>
        <h3 class="stat-value"><?php echo number_format($stats['unique_products'] ?? 0); ?></h3>
        <p class="stat-change">
            <i class="fas fa-box"></i> Unique products
        </p>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orders-icon">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <p class="stat-title">Units Sold</p>
        <h3 class="stat-value"><?php echo number_format($stats['total_units'] ?? 0); ?></h3>
        <p class="stat-change">
            <i class="fas fa-boxes"></i> Total units
        </p>
    </div>
</div>

<!-- Sales Chart -->
<div class="chart-card">
    <h3>Sales & Profit Trend</h3>
    <canvas id="teamSalesChart"></canvas>
</div>

<!-- Products Table -->
<div class="table-container">
    <div class="table-header">
        <h3>Top Selling Products</h3>
    </div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Units Sold</th>
                <th>Total Sales (RM)</th>
                <th>Total Profit (RM)</th>
                <th>Margin</th>
                <th>Last Sale</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($product = $top_products->fetch_assoc()): ?>
            <tr>
                <td><?php echo $product['sku']; ?></td>
                <td><?php echo $product['product_name']; ?></td>
                <td><?php echo number_format($product['total_sold'] ?? 0); ?></td>
                <td>RM <?php echo number_format($product['total_sales'] ?? 0, 2); ?></td>
                <td>RM <?php echo number_format($product['total_profit'] ?? 0, 2); ?></td>
                <td><?php echo number_format($product['profit_margin'] ?? 0, 1); ?>%</td>
                <td><?php echo date('d M Y', strtotime($product['last_sale'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- All Team Products with Pagination and Search -->
<div class="table-container">
    <!-- Table header with search functionality -->
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3>All Products</h3>
        <div class="search-container">
            <form method="GET" action="" id="searchForm" style="display: flex; gap: 10px;">
                <!-- Preserve other query params -->
                <?php if(isset($_GET['start_date'])): ?>
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                <?php endif; ?>
                
                <?php if(isset($_GET['end_date'])): ?>
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                <?php endif; ?>

                <?php if(isset($_GET['show_all'])): ?>
                <input type="hidden" name="show_all" value="1">
                <?php endif; ?>
                
                <div class="search-box" style="position: relative;">
                    <input type="text" name="search" id="searchInput" placeholder="Search by SKU or name..." 
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        style="padding: 8px 12px; width: 250px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    <?php if(!empty($search_query)): ?>
                    <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query(array_diff_key($_GET, ['search' => ''])); ?>" 
                    style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); color: #999; text-decoration: none;"
                    title="Clear search">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn" style="background-color: var(--secondary-color); height: 36px;">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <!-- Display search results info -->
    <?php if(!empty($search_query)): ?>
    <div class="search-info" style="margin-bottom: 15px;">
        <p style="margin: 5px 0; font-size: 14px; color: #666;">
            Showing results for: <strong>"<?php echo htmlspecialchars($search_query); ?>"</strong>
            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query(array_diff_key($_GET, ['search' => ''])); ?>" 
               style="margin-left: 10px; color: #3498db;">
               <i class="fas fa-times"></i> Clear
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Product listing table -->
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>SKU</th>
                <th>Product</th>
                <?php if ($is_admin): ?>
                <th>Team</th>
                <?php endif; ?>
                <th>Units</th>
                <th>Item Cost</th>
                <th>Sales (RM)</th>
                <th>Profit (RM)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($product = $all_products->fetch_assoc()): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($product['created_at'])); ?></td>
                <td><?php echo $product['sku']; ?></td>
                <td><?php echo $product['product_name']; ?></td>
                <?php if ($is_admin): ?>
                <td><?php echo $product['team_name'] ?? 'N/A'; ?></td>
                <?php endif; ?>
                <td><?php echo $product['unit_sold']; ?></td>
                <td>RM <?php echo number_format($product['item_cost'] ?? 0, 2); ?></td>
                <td>RM <?php echo number_format($product['sales'] ?? 0, 2); ?></td>
                <td>RM <?php echo number_format($product['profit'] ?? 0, 2); ?></td>
                <td>
                    <div class="action-buttons">
                        <a href="view_product.php?id=<?php echo $product['id']; ?>" class="action-btn action-btn-view">
                            <i class="fas fa-eye"></i> <span class="btn-text">View</span>
                        </a>
                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="action-btn action-btn-edit">
                            <i class="fas fa-edit"></i> <span class="btn-text">Edit</span>
                        </a>
                        <form method="POST" action="delete_product.php" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="action-btn action-btn-delete">
                                <i class="fas fa-trash-alt"></i> <span class="btn-text">Delete</span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            
            <?php if ($all_products->num_rows == 0): ?>
            <tr>
                <td colspan="<?php echo $is_admin ? 9 : 8; ?>" style="text-align: center; padding: 20px;">
                    <?php if (!empty($search_query)): ?>
                        No products found matching '<?php echo htmlspecialchars($search_query); ?>'.
                    <?php else: ?>
                        No products found for the selected period.
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <p>Page <?php echo $page; ?> of <?php echo $total_pages; ?></p>
        <div class="pagination-links">
            <?php if ($page > 1): ?>
                <a href="?page=1<?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?><?php echo isset($_GET['show_all']) ? '&show_all=1' : ''; ?>" class="btn">First</a>
                <a href="?page=<?php echo $page-1; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?><?php echo isset($_GET['show_all']) ? '&show_all=1' : ''; ?>" class="btn">Previous</a>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?><?php echo isset($_GET['show_all']) ? '&show_all=1' : ''; ?>" class="btn">Next</a>
                <a href="?page=<?php echo $total_pages; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?><?php echo isset($_GET['show_all']) ? '&show_all=1' : ''; ?>" class="btn">Last</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Chart -->
<script>
// Team Sales Chart
const teamSalesCtx = document.getElementById('teamSalesChart').getContext('2d');
const teamSalesChart = new Chart(teamSalesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [
            {
                label: 'Sales (RM)',
                data: <?php echo json_encode($sales_data); ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Profit (RM)',
                data: <?php echo json_encode($profit_data); ?>,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                borderWidth: 2,
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
                beginAtZero: true
            }
        }
    }
});

// Add event listener for Enter key on search input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('searchForm').submit();
            }
        });
    }
});
</script>

</main>
</div>
</body>
</html>