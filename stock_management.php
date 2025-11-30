<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Configure MySQL to ensure GROUP BY works properly
$dbconn->query("SET SQL_MODE = ''");

// Redirect if not admin
if (!$is_admin) {
    header("Location: team_products.php");
    exit();
}

// Handle team selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_team'])) {
    $_SESSION['selected_team'] = $_POST['selected_team'];
    // Reset product selection when team changes
    unset($_SESSION['selected_product']);
    // Redirect to refresh with new team selection
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle product selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_product'])) {
    $_SESSION['selected_product'] = $_POST['selected_product'];
    // Redirect to refresh with new product selection
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get selected team (default to 'all' if not set)
$selected_team = isset($_SESSION['selected_team']) ? $_SESSION['selected_team'] : 'all';

// Get selected product (default to 'all' if not set)
$selected_product = isset($_SESSION['selected_product']) ? $_SESSION['selected_product'] : 'all';

// Get selected team name for display
$team_name = "All Teams";
if ($selected_team != 'all') {
    $team_query = "SELECT team_name FROM teams WHERE team_id = ?";
    $team_stmt = $dbconn->prepare($team_query);
    $team_stmt->bind_param("i", $selected_team);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    if ($team_result && $team_result->num_rows > 0) {
        $team_name = $team_result->fetch_assoc()['team_name'];
    }
    $team_stmt->close();
}

// Get selected product name for display
$product_name = "All Products";
if ($selected_product != 'all') {
    $product_query = "SELECT product_name FROM products WHERE id = ?";
    $product_stmt = $dbconn->prepare($product_query);
    $product_stmt->bind_param("i", $selected_product);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    if ($product_result && $product_result->num_rows > 0) {
        $product_name = $product_result->fetch_assoc()['product_name'];
    }
    $product_stmt->close();
}

// Check if stock_entries table exists, if not create it with product_id field
$check_stock_entries = $dbconn->query("SHOW TABLES LIKE 'stock_entries'");
if ($check_stock_entries->num_rows == 0) {
    // Create stock_entries table with team_id and product_id fields
    $dbconn->query("CREATE TABLE IF NOT EXISTS stock_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        description VARCHAR(255) NOT NULL,
        platform VARCHAR(50) NOT NULL,
        quantity INT NOT NULL,
        total_rm DECIMAL(10, 2) NOT NULL,
        price_per_unit DECIMAL(10, 2) GENERATED ALWAYS AS (total_rm / quantity) STORED,
        eta DATE,
        remarks TEXT,
        team_id INT,
        product_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(team_id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
} else {
    // Check if product_id column exists, if not add it
    $check_product_id = $dbconn->query("SHOW COLUMNS FROM stock_entries LIKE 'product_id'");
    if ($check_product_id->num_rows == 0) {
        $dbconn->query("ALTER TABLE stock_entries ADD COLUMN product_id INT, ADD FOREIGN KEY (product_id) REFERENCES products(id)");
    }
}

// Check if stock_orders table exists, if not create it with product_id field
$check_stock_orders = $dbconn->query("SHOW TABLES LIKE 'stock_orders'");
if ($check_stock_orders->num_rows == 0) {
    // Create stock_orders table with team_id and product_id fields
    $dbconn->query("CREATE TABLE IF NOT EXISTS stock_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        order_received INT NOT NULL,
        balance_stock INT NOT NULL,
        status ENUM('Healthy', 'Low Stock', 'Out of Stock') NOT NULL,
        team_id INT,
        product_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (team_id) REFERENCES teams(team_id),
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
} else {
    // Check if product_id column exists, if not add it
    $check_product_id = $dbconn->query("SHOW COLUMNS FROM stock_orders LIKE 'product_id'");
    if ($check_product_id->num_rows == 0) {
        $dbconn->query("ALTER TABLE stock_orders ADD COLUMN product_id INT, ADD FOREIGN KEY (product_id) REFERENCES products(id)");
    }
}

// Function to get stock threshold for the selected team/product
// Updated to ensure minimum threshold of 50 units
function getStockThreshold($dbconn, $team_id = null, $product_id = null) {
    // First check for product-specific threshold
    if ($product_id && $product_id != 'all') {
        $product_query = "SELECT reorder_level FROM products WHERE id = ?";
        $product_stmt = $dbconn->prepare($product_query);
        $product_stmt->bind_param("i", $product_id);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();
        
        if ($product_result && $product_result->num_rows > 0) {
            $reorder_level = $product_result->fetch_assoc()['reorder_level'];
            if (!is_null($reorder_level) && $reorder_level > 0) {
                return max(50, (int)$reorder_level); // Ensure minimum of 50 units
            }
        }
        $product_stmt->close();
    }
    
    // Then fall back to team/global threshold
    $threshold_sql = "SELECT setting_value FROM stock_settings WHERE setting_name = 'low_stock_threshold'";
    
    if ($team_id && $team_id != 'all') {
        $threshold_sql .= " AND (team_id = ? OR team_id IS NULL)";
        $threshold_stmt = $dbconn->prepare($threshold_sql);
        $threshold_stmt->bind_param("i", $team_id);
        $threshold_stmt->execute();
        $threshold_result = $threshold_stmt->get_result();
    } else {
        $threshold_result = $dbconn->query($threshold_sql);
    }
    
    if ($threshold_result && $threshold_result->num_rows > 0) {
        return max(50, (int)$threshold_result->fetch_assoc()['setting_value']); // Ensure minimum of 50 units
    }
    
    return 50; // Default threshold if not set
}
// Function to convert URLs in text to clickable links
function makeLinksClickable($text) {
    // Regular expression to match URLs
    $urlPattern = '/(https?:\/\/[^\s]+)/i';
    
    // Replace URLs with clickable links
    $clickableText = preg_replace($urlPattern, '<a href="$1" target="_blank" class="remark-link">$1</a>', $text);
    
    return $clickableText;
}

function updateProductStock($dbconn, $product_id, $quantity, $team_id = null) {
    // Begin transaction
    $dbconn->begin_transaction();
    
    try {
        // 1. Get the SKU of the product being updated
        $sku_query = "SELECT sku FROM products WHERE id = ?";
        $sku_stmt = $dbconn->prepare($sku_query);
        $sku_stmt->bind_param("i", $product_id);
        $sku_stmt->execute();
        $result = $sku_stmt->get_result();
        $sku = ($result->num_rows > 0) ? $result->fetch_assoc()['sku'] : null;
        
        if (!$sku) {
            throw new Exception("Product not found");
        }
        
        // 2. Update stock_quantity for the specific product
        $update_product = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?";
        $update_stmt = $dbconn->prepare($update_product);
        $update_stmt->bind_param("ii", $quantity, $product_id);
        $update_stmt->execute();
        
        // 3. Get the current total stock for this SKU
        $total_stock_query = "SELECT SUM(stock_quantity) as total FROM products WHERE sku = ?";
        $total_stmt = $dbconn->prepare($total_stock_query);
        $total_stmt->bind_param("s", $sku);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_stock = ($total_result->num_rows > 0) ? $total_result->fetch_assoc()['total'] : 0;
        
        // 4. Determine the stock status based on total stock for this SKU
        $threshold_query = "SELECT COALESCE(reorder_level, 50) as threshold FROM products WHERE id = ?";
        $threshold_stmt = $dbconn->prepare($threshold_query);
        $threshold_stmt->bind_param("i", $product_id);
        $threshold_stmt->execute();
        $threshold_result = $threshold_stmt->get_result();
        $threshold = ($threshold_result->num_rows > 0) ? $threshold_result->fetch_assoc()['threshold'] : 50;
        
        $stock_status = 'Healthy';
        if ($total_stock <= 0) {
            $stock_status = 'Out of Stock';
        } elseif ($total_stock <= $threshold) {
            $stock_status = 'Low Stock';
        }
        
        // 5. Update status for ALL products with this SKU
        $update_status = "UPDATE products SET stock_status = ? WHERE sku = ?";
        $status_stmt = $dbconn->prepare($update_status);
        $status_stmt->bind_param("ss", $stock_status, $sku);
        $status_stmt->execute();
        
        // 6. Add a record in stock_orders
        $today = date('Y-m-d');
        $order_sql = "INSERT INTO stock_orders (date, order_received, balance_stock, status, team_id, product_id)
                    VALUES (?, 0, ?, ?, ?, ?)";
        $order_stmt = $dbconn->prepare($order_sql);
        $order_stmt->bind_param("sisis", $today, $total_stock, $stock_status, $team_id, $product_id);
        $order_stmt->execute();
        
        $dbconn->commit();
        return true;
    } catch (Exception $e) {
        $dbconn->rollback();
        throw $e;
    }
}

// Get the low stock threshold for the selected team/product
$low_stock_threshold = getStockThreshold($dbconn, $selected_team, $selected_product);

// Handle session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Process stock entry form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock_entry'])) {
    $date = $_POST['date'];
    $description = $_POST['description'];
    $platform = $_POST['platform'];
    $quantity = $_POST['quantity'];
    $total_rm = $_POST['totalRM'];
    $eta = !empty($_POST['eta']) ? $_POST['eta'] : NULL;
    $remarks = !empty($_POST['remarks']) ? $_POST['remarks'] : NULL;
    $team_id = ($selected_team != 'all') ? $selected_team : NULL;
    $product_id = ($selected_product != 'all') ? $selected_product : NULL;
    $status = $_POST['status']; // Add this line
    
    // Validate inputs
    if (empty($date) || empty($description) || empty($platform) || empty($quantity) || empty($total_rm)) {
        $error_message = "All required fields must be filled!";
    } elseif ($selected_product == 'all') {
        $error_message = "Please select a specific product to add stock!";
    } else {
        try {
            // Insert stock entry with team_id and product_id
            $sql = "INSERT INTO stock_entries (date, description, platform, quantity, total_rm, eta, remarks, team_id, product_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("sssidssiis", $date, $description, $platform, $quantity, $total_rm, $eta, $remarks, $team_id, $product_id, $status);
            
            if ($stmt->execute()) {
                // Only update product stock_quantity if status is "Available"
                if ($status == "Available") {
                    // Use the new function to handle SKU-based updates
                    updateProductStock($dbconn, $product_id, $quantity, $team_id);
                }
                
                $success_message = "Stock entry added successfully!";
            } else {
                $error_message = "Error adding stock entry: " . $dbconn->error;
            }
        } catch (Exception $e) {
            $error_message = "Error processing stock entry: " . $e->getMessage();
        }
    }
}

// Build product condition for SQL queries
$product_condition = "";
if ($selected_product != 'all') {
    $product_condition = "AND id = $selected_product";
} elseif ($selected_team != 'all') {
    $product_condition = "AND team_id = $selected_team";
}

// FIX: Correctly calculate product statistics using a subquery to group by product first
$product_stats_sql = "SELECT 
                      COUNT(DISTINCT sku) as total_products,
                      SUM(CASE WHEN latest_stock > $low_stock_threshold THEN 1 ELSE 0 END) as healthy_stock,
                      SUM(CASE WHEN latest_stock <= $low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count,
                      SUM(CASE WHEN latest_stock <= 0 THEN 1 ELSE 0 END) as out_stock_count,
                      SUM(latest_stock) as total_stock
                    FROM (
                      SELECT 
                          p.sku,
                          (
                              SELECT so.balance_stock 
                              FROM stock_orders so 
                              JOIN products p2 ON so.product_id = p2.id
                              WHERE p2.sku = p.sku 
                              " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                              ORDER BY so.id DESC 
                              LIMIT 1
                          ) as latest_stock
                      FROM products p
                      WHERE 1=1 $product_condition
                      GROUP BY p.sku
                    ) as product_summary";

// If WITH clause not supported, use alternative approach
$product_stats_result = $dbconn->query($product_stats_sql);
if (!$product_stats_result) {
    // Fallback to direct queries if WITH clause fails
  $direct_stats_sql = "SELECT 
    (SELECT SUM(sq.total) FROM (SELECT SUM(stock_quantity) as total FROM products WHERE 1=1 $product_condition GROUP BY product_name) as sq) as total_stock,
    (SELECT COUNT(DISTINCT product_name) FROM products WHERE 1=1 $product_condition) as total_products,
    (SELECT COUNT(*) FROM (
        SELECT product_name, SUM(stock_quantity) as total 
        FROM products 
        WHERE 1=1 $product_condition 
        GROUP BY product_name
        HAVING total <= $low_stock_threshold
    ) as ls) as low_stock_count,
    (SELECT COUNT(*) FROM (
        SELECT product_name, SUM(stock_quantity) as total 
        FROM products 
        WHERE 1=1 $product_condition 
        GROUP BY product_name
        HAVING total <= 0
    ) as os) as out_stock_count";
    
    $product_stats = $dbconn->query($direct_stats_sql)->fetch_assoc();
} else {
    $product_stats = $product_stats_result->fetch_assoc();
}

// Get order statistics for today
$order_condition = "";
if ($selected_product != 'all') {
    $order_condition = "AND product_id = $selected_product";
} elseif ($selected_team != 'all') {
    $order_condition = "AND team_id = $selected_team";
}

$order_stats_sql = "SELECT 
    COUNT(*) as orders_today,
    COALESCE(SUM(order_received), 0) as units_today
FROM stock_orders 
WHERE DATE(date) = CURDATE() $order_condition";

$order_stats = $dbconn->query($order_stats_sql)->fetch_assoc();

// Get latest stock for the form for the selected product
$latest_stock = 0;

if ($selected_product != 'all') {
    $latest_stock_sql = "SELECT stock_quantity FROM products WHERE id = ?";
    $latest_stock_stmt = $dbconn->prepare($latest_stock_sql);
    $latest_stock_stmt->bind_param("i", $selected_product);
    $latest_stock_stmt->execute();
    $latest_stock_result = $latest_stock_stmt->get_result();
    
    if ($latest_stock_result && $latest_stock_result->num_rows > 0) {
        $latest_stock = $latest_stock_result->fetch_assoc()['stock_quantity'];
    }
}

// Get stock entries with pagination for the selected team/product
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

$stock_sql = "SELECT se.*, p.product_name FROM stock_entries se 
            LEFT JOIN products p ON se.product_id = p.id WHERE 1=1 ";

// Build filter conditions
$conditions = array();
$params = array();
$types = "";

if ($selected_team != 'all') {
    $conditions[] = "se.team_id = ?";
    $params[] = $selected_team;
    $types .= "i";
}

if ($selected_product != 'all') {
    $conditions[] = "se.product_id = ?";
    $params[] = $selected_product;
    $types .= "i";
}

if (!empty($conditions)) {
    $stock_sql .= " AND " . implode(" AND ", $conditions);
}

$stock_sql .= " ORDER BY se.date DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stock_stmt = $dbconn->prepare($stock_sql);

if (!empty($params)) {
    $stock_stmt->bind_param($types, ...$params);
}

$stock_stmt->execute();
$stock_entries = $stock_stmt->get_result();

// Count total stock entries for pagination
$total_stock_sql = "SELECT COUNT(*) as total FROM stock_entries WHERE 1=1 ";

// Reset params and types for count query
$count_params = array();
$count_types = "";

if ($selected_team != 'all') {
    $total_stock_sql .= " AND team_id = ? ";
    $count_params[] = $selected_team;
    $count_types .= "i";
}

if ($selected_product != 'all') {
    $total_stock_sql .= " AND product_id = ? ";
    $count_params[] = $selected_product;
    $count_types .= "i";
}

$total_stock_stmt = $dbconn->prepare($total_stock_sql);

if (!empty($count_params)) {
    $total_stock_stmt->bind_param($count_types, ...$count_params);
}

$total_stock_stmt->execute();
$total_stock_result = $total_stock_stmt->get_result();
$total_stock_entries = $total_stock_result->fetch_assoc()['total'];
$total_stock_pages = ceil($total_stock_entries / $records_per_page);

// Get order entries with pagination
$order_sql = "SELECT so.*, p.product_name FROM stock_orders so 
            LEFT JOIN products p ON so.product_id = p.id WHERE 1=1 ";

// Reset conditions, params and types
$conditions = array();
$params = array();
$types = "";

if ($selected_team != 'all') {
    $conditions[] = "so.team_id = ?";
    $params[] = $selected_team;
    $types .= "i";
}

if ($selected_product != 'all') {
    $conditions[] = "so.product_id = ?";
    $params[] = $selected_product;
    $types .= "i";
}

if (!empty($conditions)) {
    $order_sql .= " AND " . implode(" AND ", $conditions);
}

$order_sql .= " ORDER BY so.date DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$order_stmt = $dbconn->prepare($order_sql);

if (!empty($params)) {
    $order_stmt->bind_param($types, ...$params);
}

$order_stmt->execute();
$order_entries = $order_stmt->get_result();

// Count total order entries for pagination
$total_order_sql = "SELECT COUNT(*) as total FROM stock_orders WHERE 1=1 ";

// Reset params and types for count query
$count_params = array();
$count_types = "";

if ($selected_team != 'all') {
    $total_order_sql .= " AND team_id = ? ";
    $count_params[] = $selected_team;
    $count_types .= "i";
}

if ($selected_product != 'all') {
    $total_order_sql .= " AND product_id = ? ";
    $count_params[] = $selected_product;
    $count_types .= "i";
}

$total_order_stmt = $dbconn->prepare($total_order_sql);

if (!empty($count_params)) {
    $total_order_stmt->bind_param($count_types, ...$count_params);
}

$total_order_stmt->execute();
$total_order_result = $total_order_stmt->get_result();
$total_order_entries = $total_order_result->fetch_assoc()['total'];
$total_order_pages = ceil($total_order_entries / $records_per_page);

// Check for low stock alerts
// FIX: Use a subquery to correctly group products by name
$alert_sql = "SELECT COUNT(*) as count FROM (
                SELECT 
                    p.sku, 
                    (
                        SELECT so.balance_stock 
                        FROM stock_orders so 
                        JOIN products p2 ON so.product_id = p2.id
                        WHERE p2.sku = p.sku 
                        " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                        ORDER BY so.id DESC 
                        LIMIT 1
                    ) as total_stock
                FROM products p
                WHERE 1=1 ";

if ($selected_team != 'all') {
    $alert_sql .= " AND p.team_id = $selected_team";
}
if ($selected_product != 'all') {
    $alert_sql .= " AND p.id = $selected_product";
}

$alert_sql .= " GROUP BY p.sku
              HAVING total_stock <= " . ($low_stock_threshold) . "
              ) AS subquery";

$alert_result = $dbconn->query($alert_sql);
$alert_count = $alert_result->fetch_assoc()['count'];

// Get new products (added in the last 7 days)
$new_products_sql = "SELECT COUNT(*) as count FROM products WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
if ($selected_team != 'all') {
    $new_products_sql .= " AND team_id = $selected_team";
}
$new_products_result = $dbconn->query($new_products_sql);
$new_products_count = $new_products_result->fetch_assoc()['count'];

// Get killed/discontinued products (stock_status = 'Out of Stock' for more than 30 days)
$killed_products_sql = "SELECT COUNT(*) as count FROM products WHERE stock_status = 'Out of Stock' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
if ($selected_team != 'all') {
    $killed_products_sql .= " AND team_id = $selected_team";
}
$killed_products_result = $dbconn->query($killed_products_sql);
$killed_products_count = $killed_products_result->fetch_assoc()['count'];

// Get all teams for the team selector
$teams_sql = "SELECT team_id, team_name FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_sql);

// Check if we should show killed/discontinued products
$show_killed = isset($_GET['show_killed']) && $_GET['show_killed'] == 1;

// Get all products for the product selector based on selected team
// Modified query to group by SKU to avoid duplicate products
$products_sql = "SELECT 
                    p1.id,
                    p1.sku,
                    p1.product_name,
                    (
                        SELECT so.balance_stock 
                        FROM stock_orders so 
                        JOIN products p2 ON so.product_id = p2.id
                        WHERE p2.sku = p1.sku 
                        " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                        ORDER BY so.id DESC 
                        LIMIT 1
                    ) as stock_quantity,
                    p1.created_at,
                    p1.status as product_status,
                    CASE 
                      WHEN (
                          SELECT so.balance_stock 
                          FROM stock_orders so 
                          JOIN products p2 ON so.product_id = p2.id
                          WHERE p2.sku = p1.sku 
                          " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                          ORDER BY so.id DESC 
                          LIMIT 1
                      ) <= 0 THEN 'Out of Stock'
                      WHEN (
                          SELECT so.balance_stock 
                          FROM stock_orders so 
                          JOIN products p2 ON so.product_id = p2.id
                          WHERE p2.sku = p1.sku 
                          " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                          ORDER BY so.id DESC 
                          LIMIT 1
                      ) <= $low_stock_threshold THEN 'Low Stock'
                      ELSE 'Healthy' 
                    END as stock_status
                FROM products p1
                WHERE 1=1 ";

if ($selected_team != 'all') {
    $products_sql .= " AND p1.team_id = $selected_team";
}

if (!$show_killed) {
    $products_sql .= " AND p1.status != 'killed'";
}

$products_sql .= " GROUP BY p1.sku 
                   ORDER BY p1.product_name";
$products_result = $dbconn->query($products_sql);

// FIX: For Current Stock Details modal - properly group by product name
$current_stock_products_sql = "SELECT 
                               p.sku,
                               MAX(p.product_name) as product_name,
                               (
                                   SELECT so.balance_stock 
                                   FROM stock_orders so 
                                   JOIN products p2 ON so.product_id = p2.id
                                   WHERE p2.sku = p.sku 
                                   " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                                   ORDER BY so.id DESC 
                                   LIMIT 1
                               ) as stock_quantity,
                               CASE 
                                   WHEN (
                                       SELECT so.balance_stock 
                                       FROM stock_orders so 
                                       JOIN products p2 ON so.product_id = p2.id
                                       WHERE p2.sku = p.sku 
                                       " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                                       ORDER BY so.id DESC 
                                       LIMIT 1
                                   ) <= 0 THEN 'Out of Stock'
                                   WHEN (
                                       SELECT so.balance_stock 
                                       FROM stock_orders so 
                                       JOIN products p2 ON so.product_id = p2.id
                                       WHERE p2.sku = p.sku 
                                       " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                                       ORDER BY so.id DESC 
                                       LIMIT 1
                                   ) <= $low_stock_threshold THEN 'Low Stock'
                                   ELSE 'Healthy' 
                               END as stock_status
                               FROM products p
                               WHERE 1=1 $product_condition 
                               GROUP BY p.sku
                               ORDER BY product_name 
                               LIMIT 50";
$current_stock_products = $dbconn->query($current_stock_products_sql);

// FIX: For Low Stock Details modal - properly group by product name
$low_stock_products_sql = "SELECT 
                          sku,
                          product_name,
                          stock_quantity,
                          stock_status,
                          $low_stock_threshold as threshold
                          FROM (
                              SELECT 
                                  p.sku,
                                  MAX(p.product_name) as product_name,
                                  (
                                      SELECT so.balance_stock 
                                      FROM stock_orders so 
                                      JOIN products p2 ON so.product_id = p2.id
                                      WHERE p2.sku = p.sku 
                                      " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                                      ORDER BY so.id DESC 
                                      LIMIT 1
                                  ) as stock_quantity,
                                  CASE 
                                      WHEN (
                                          SELECT so.balance_stock 
                                          FROM stock_orders so 
                                          JOIN products p2 ON so.product_id = p2.id
                                          WHERE p2.sku = p.sku 
                                          " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                                          ORDER BY so.id DESC 
                                          LIMIT 1
                                      ) <= 0 THEN 'Out of Stock'
                                      WHEN (
                                          SELECT so.balance_stock 
                                          FROM stock_orders so 
                                          JOIN products p2 ON so.product_id = p2.id
                                          WHERE p2.sku = p.sku 
                                          " . ($selected_team != 'all' ? "AND p2.team_id = $selected_team" : "") . "
                                          ORDER BY so.id DESC 
                                          LIMIT 1
                                      ) <= $low_stock_threshold THEN 'Low Stock'
                                      ELSE 'Healthy' 
                                  END as stock_status
                              FROM products p
                              WHERE 1=1 $product_condition 
                              GROUP BY p.sku
                          ) as grouped
                          WHERE stock_status IN ('Low Stock', 'Out of Stock')
                          ORDER BY stock_status DESC, stock_quantity ASC 
                          LIMIT 50";
$low_stock_products = $dbconn->query($low_stock_products_sql);

// For Orders Today Details modal
$orders_today_sql = "SELECT so.*, p.product_name 
                    FROM stock_orders so 
                    LEFT JOIN products p ON so.product_id = p.id 
                    WHERE DATE(so.date) = CURDATE() ";

// We need to create a separate order condition for this query that uses the so alias
$orders_today_condition = "";
if ($selected_product != 'all') {
    $orders_today_condition = "AND so.product_id = $selected_product";
} elseif ($selected_team != 'all') {
    $orders_today_condition = "AND so.team_id = $selected_team";
}

$orders_today_sql .= $orders_today_condition . " ORDER BY so.created_at DESC LIMIT 50";
$orders_today = $dbconn->query($orders_today_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - Dr Ecomm Formula</title>
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
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
            
            /* Stock status colors */
            --in-stock-color: #1abc9c;
            --low-stock-color: #f39c12;
            --out-of-stock-color: #e74c3c;
            --new-product-color: #3498db;
            --killed-product-color: #8e44ad;
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
        .remark-link {
    color: var(--secondary-color);
    text-decoration: underline;
    word-break: break-all;
    transition: var(--transition);
}

.remark-link:hover {
    color: var(--secondary-light);
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
        
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer; /* Make the cards clickable */
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card:active {
            transform: translateY(0);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }
        
        .clickable-hint {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 12px;
            color: #6c757d;
            background-color: rgba(255,255,255,0.7);
            padding: 2px 8px;
            border-radius: 12px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: white;
        }
        
        .current-stock-card .stat-icon {
            background-color: var(--secondary-color);
        }
        
        .orders-today-card .stat-icon {
            background-color: var(--in-stock-color);
        }
        
        .low-stock-card .stat-icon {
            background-color: var(--low-stock-color);
        }
        
        .threshold-card .stat-icon {
            background-color: var(--out-of-stock-color);
        }
        
        .stat-content {
            position: relative;
            z-index: 2;
        }
        
        .stat-title {
            margin: 0;
            font-size: 15px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 10px 0 5px;
            color: var(--dark-text);
        }
        
        .stat-info {
            font-size: 14px;
            color: #6c757d;
        }
        
        /* Stock Entry Form */
        .form-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
        }
        
        .form-header h3 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 15px;
            color: var(--dark-text);
        }
        
        .form-control {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-select {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 15px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232c3e50' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 5px;
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-light);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--dark-text);
            border: 1px solid #dee2e6;
        }
        
        .btn-outline:hover {
            background-color: var(--light-bg);
        }
        
        .read-only {
            background-color: var(--light-bg);
            cursor: not-allowed;
        }
        
        /* Table styles */
        .table-container {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .table-header {
            padding: 20px 25px;
            background-color: var(--light-bg);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
        }
        
        .table-header h3 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background-color: var(--light-bg);
        }
        
        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        th {
            font-weight: 600;
            color: var(--dark-text);
            white-space: nowrap;
        }
        
        tbody tr:hover {
            background-color: rgba(0,0,0,0.01);
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        .highlight-row {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        /* Stock level indicators */
        .stock-normal {
            color: #1abc9c;
            font-weight: 500;
        }
        
        .stock-warning {
            color: #f39c12;
            font-weight: 500;
        }
        
        .stock-danger {
            color: #e74c3c;
            font-weight: 500;
        }
        
        .stock-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-normal {
            background-color: rgba(26, 188, 156, 0.1);
            color: #1abc9c;
        }
        
        .badge-warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }
        
        .badge-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        /* New indicator for new products */
        .new-product-badge {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Platform badges */
        .platform-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .platform-shopee {
            background-color: #fe5621;
            color: white;
        }
        
        .platform-tiktok {
            background-color: #000000;
            color: white;
        }
        
        .platform-lazada {
            background-color: #0f146d;
            color: white;
        }
        
        .platform-taobao {
            background-color: #ff4400;
            color: white;
        }
        
        .platform-other {
            background-color: #6c757d;
            color: white;
        }
        
        /* Alert styles */
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: rgba(26, 188, 156, 0.1);
            color: #1abc9c;
            border-left: 4px solid #1abc9c;
        }
        
        .alert-warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
            border-left: 4px solid #f39c12;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            border-left: 4px solid #3498db;
        }
        
        .alert-killed {
            background-color: rgba(142, 68, 173, 0.1);
            color: #8e44ad;
            border-left: 4px solid #8e44ad;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin: 20px 0;
        }
        
        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 35px;
            height: 35px;
            padding: 0 10px;
            border-radius: 5px;
            background-color: white;
            color: var(--dark-text);
            text-decoration: none;
            border: 1px solid #dee2e6;
            transition: var(--transition);
        }
        
        .pagination a:hover {
            background-color: var(--light-bg);
        }
        
        .pagination a.active {
            background-color: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px 0;
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Product search styles */
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-container input {
            padding-left: 40px !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 15px center;
            background-size: 16px;
            transition: var(--transition);
        }
        
        .search-container input:focus {
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        /* Style for hiding the default dropdown arrow in Firefox */
        @-moz-document url-prefix() {
            select.filtered {
                text-indent: 0.01px;
                text-overflow: '';
            }
        }
        
        /* Highlight search results */
        .highlight-match {
            background-color: rgba(52, 152, 219, 0.2);
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: var(--border-radius);
            width: 80%;
            max-width: 900px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .modal-title {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
        }
        
        .modal-title i {
            margin-right: 10px;
            color: var(--secondary-color);
            font-size: 20px;
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
            transition: var(--transition);
        }
        
        .close-modal:hover {
            color: var(--dark-text);
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Responsive styles */
        @media (max-width: 1200px) {
            .form-grid {
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
        
        /* Add styles for team selector */
        .team-selector {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
        }
        
        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .team-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            margin: 0;
        }
        
        .team-title i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .team-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            background-color: var(--secondary-light);
            color: white;
            display: inline-block;
        }
        
        /* Add styles for product selector */
        .product-selector {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            margin: 0;
        }
        
        .product-title i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .product-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            background-color: var(--accent-color);
            color: white;
            display: inline-block;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .product-card {
            background-color: white;
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            padding: 15px;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow);
        }
        
        .product-card.selected {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(26, 188, 156, 0.3);
        }
        
        .product-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .product-card-name {
            font-weight: 600;
            font-size: 15px;
            margin: 0 0 8px 0;
            color: var(--dark-text);
        }
        
        .product-card-status {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .product-card-details {
            font-size: 13px;
            color: #6c757d;
        }
        
        .product-card-stock {
            margin-top: 10px;
            font-weight: 500;
        }
        
        /* Hidden option in product search */
        .hidden-option {
            display: none;
        }
        
        @-moz-document url-prefix() {
            #product_selector option.hidden-option {
                display: none;
            }
        }
        
        /* Killed product badge */
        .killed-product-badge {
            background-color: rgba(142, 68, 173, 0.1);
            color: #8e44ad;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Style for killed products in the dropdown */
        option.killed-product {
            background-color: rgba(142, 68, 173, 0.05);
            font-style: italic;
        }
        
        /* Toggle switch for showing killed products */
        .killed-toggle-label {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #6c757d;
            cursor: pointer;
        }
        
        .killed-toggle-label:hover {
            color: #8e44ad;
        }
    </style>
</head>
<body>
    <div class="app-container">
        
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-clinic-medical"></i>
                <h2>Dr Ecomm</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo isset($username) ? $username : 'Admin'; ?></span>
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
                    <li>
                        <a href="commission_calculator.php">
                            <i class="fas fa-calculator"></i>
                            <span>Commission Calculator</span>
                        </a>
                    </li>
                    <li class="active">
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
                <h1><i class="fas fa-boxes-stacked"></i> Stock Management</h1>
                
                <div class="action-buttons">
                    <a href="export_stock.php<?php echo ($selected_team != 'all') ? '?team_id='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product_id='.$selected_product : ''; ?>" class="btn btn-outline">
                        <i class="fas fa-file-export"></i> Export Data
                    </a>
                    <button class="btn btn-primary" id="bulkImportBtn">
                        <i class="fas fa-plus"></i> Add Bulk Stock
                    </button>
                </div>
            </header>
            
            <!-- Team Selector -->
            <div class="team-selector">
                <div class="team-header">
                    <h3 class="team-title"><i class="fas fa-users"></i> Team Selection</h3>
                    <?php if ($selected_team != 'all'): ?>
                    <span class="team-badge">
                        <i class="fas fa-user-group"></i> Team: <?php echo htmlspecialchars($team_name); ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <select id="team_selector" name="selected_team" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($selected_team == 'all') ? 'selected' : ''; ?>>All Teams</option>
                            <?php 
                            // Reset the teams result pointer
                            $teams_result->data_seek(0);
                            while ($team = $teams_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $team['team_id']; ?>" <?php echo ($selected_team == $team['team_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['team_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Product Selector with Search and Toggle for Killed Products -->
            <div class="product-selector">
                <div class="product-header">
                    <h3 class="product-title"><i class="fas fa-cube"></i> Product Selection</h3>
                    <?php if ($selected_product != 'all'): ?>
                    <span class="product-badge">
                        <i class="fas fa-box"></i> Product: <?php echo htmlspecialchars($product_name); ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- Add search input -->
                <div class="search-container" style="margin-bottom: 15px;">
                    <input type="text" id="productSearch" class="form-control" placeholder="Search products..." style="padding: 12px 15px; width: 100%;">
                    <small class="search-info" style="display: block; margin-top: 5px; color: #6c757d;">
                        Search by product name, status, or stock level
                    </small>
                </div>
                
                <!-- Toggle for showing killed products -->
                <div class="killed-products-toggle" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <label class="killed-toggle-label" style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="showKilledProducts" <?php echo $show_killed ? 'checked' : ''; ?> style="margin-right: 8px;">
                        <span>Show discontinued products (<?php echo $killed_products_count; ?>)</span>
                    </label>
                    
                    <?php if ($show_killed && $killed_products_count > 0): ?>
                    <div class="killed-products-notice" style="font-size: 13px; color: #8e44ad;">
                        <i class="fas fa-info-circle"></i> Showing discontinued products that have been out of stock for 30+ days
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($show_killed && $killed_products_count > 0): ?>
                <div class="alert alert-killed" style="font-size: 14px; margin-bottom: 15px;">
                    <i class="fas fa-ghost"></i>
                    <div>
                        You are viewing <?php echo $killed_products_count; ?> discontinued products that have been out of stock for more than 30 days.
                        These products are normally hidden from selection.
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <select id="product_selector" name="selected_product" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($selected_product == 'all') ? 'selected' : ''; ?>>All Products</option>
                            <?php 
                            // Reset products result pointer
                            $products_result->data_seek(0);
                            
                            while ($product = $products_result->fetch_assoc()): 
                                // Determine stock status class
                                $status_class = 'stock-normal';
                                if ($product['stock_status'] == 'Out of Stock') {
                                    $status_class = 'stock-danger';
                                } elseif ($product['stock_status'] == 'Low Stock') {
                                    $status_class = 'stock-warning';
                                }
                                
                                // Check if product is new (added in the last 7 days)
                                $is_new_product = false;
                                if (!empty($product['created_at'])) {
                                    $created_date = new DateTime($product['created_at']);
                                    $current_date = new DateTime();
                                    $days_diff = $current_date->diff($created_date)->days;
                                    if ($days_diff <= 7) {
                                        $is_new_product = true;
                                    }
                                }
                                
                                // Check if product is killed/discontinued
                                $is_killed = ($product['product_status'] == 'killed');
                            ?>
                                <option value="<?php echo $product['id']; ?>" 
                                        <?php echo ($selected_product == $product['id']) ? 'selected' : ''; ?> 
                                        class="<?php echo $status_class; ?> <?php echo $is_killed ? 'killed-product' : ''; ?>" 
                                        data-name="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                        data-stock="<?php echo $product['stock_quantity']; ?>" 
                                        data-status="<?php echo $product['stock_status']; ?>"
                                        data-killed="<?php echo $is_killed ? 'true' : 'false'; ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?> 
                                    <?php if ($is_new_product): ?><span class="new-product-badge">NEW</span><?php endif; ?>
                                    <?php if ($is_killed): ?><span class="killed-product-badge">DISCONTINUED</span><?php endif; ?>
                                    (Stock: <?php echo $product['stock_quantity']; ?> - <?php echo $product['stock_status']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success_message; ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error_message; ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Enhanced Alert System -->
            <!-- Low Stock Alert -->
            <?php if ($alert_count > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>Warning: <?php echo $alert_count; ?> products are running low on stock (below <?php echo $low_stock_threshold; ?> units) or out of stock<?php echo ($selected_team != 'all') ? ' for '.htmlspecialchars($team_name) : ''; ?><?php echo ($selected_product != 'all') ? ' including '.htmlspecialchars($product_name) : ''; ?>. Please check the stock levels and reorder if necessary.</div>
            </div>
            <?php endif; ?>

            <!-- New Products Alert -->
            <?php if ($new_products_count > 0): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>Information: <?php echo $new_products_count; ?> new products were added in the last 7 days<?php echo ($selected_team != 'all') ? ' for '.htmlspecialchars($team_name) : ''; ?>. Review them to ensure proper stock levels are set.</div>
            </div>
            <?php endif; ?>

            <!-- Killed Products Alert -->
            <?php if ($killed_products_count > 0): ?>
            <div class="alert alert-killed">
                <i class="fas fa-ghost"></i>
                <div>Notice: <?php echo $killed_products_count; ?> products have been out of stock for more than 30 days<?php echo ($selected_team != 'all') ? ' for '.htmlspecialchars($team_name) : ''; ?>. Consider marking them as discontinued or restocking them.</div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Overview - ENHANCED WITH CLICKABLE CARDS -->
            <div class="stats-grid">
                <div class="stat-card current-stock-card" onclick="showCurrentStockModal()">
                    <span class="clickable-hint"><i class="fas fa-info-circle"></i> Click for details</span>
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <p class="stat-title">Current Stock</p>
                        <h3 class="stat-value"><?php echo $product_stats['total_stock'] ?? 0; ?></h3>
                        <p class="stat-info">Available balance<?php echo ($selected_team != 'all') ? ' for '.htmlspecialchars($team_name) : ''; ?><?php echo ($selected_product != 'all') ? ' - '.htmlspecialchars($product_name) : ''; ?></p>
                    </div>
                </div>
                
                <div class="stat-card orders-today-card" onclick="showOrdersTodayModal()">
                    <span class="clickable-hint"><i class="fas fa-info-circle"></i> Click for details</span>
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <p class="stat-title">Orders Today</p>
                        <h3 class="stat-value"><?php echo $order_stats['orders_today']; ?></h3>
                        <p class="stat-info"><?php echo $order_stats['units_today']; ?> units ordered today</p>
                    </div>
                </div>
                
              <div class="stat-card low-stock-card" onclick="showLowStockModal()">
    <span class="clickable-hint"><i class="fas fa-info-circle"></i> Click for details</span>
    <div class="stat-header">
        <div class="stat-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
    </div>
    <div class="stat-content">
        <p class="stat-title">Stock Alerts</p>
        <h3 class="stat-value"><?php echo $product_stats['low_stock_count']; ?></h3>
        <p class="stat-info">Low & out of stock products</p>
    </div>
</div>
                
                <div class="stat-card threshold-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <p class="stat-title">Stock Threshold</p>
                        <h3 class="stat-value"><?php echo $low_stock_threshold; ?></h3>
                        <p class="stat-info">Minimum stock level</p>
                    </div>
                </div>
            </div>

            <?php if ($selected_product != 'all'): ?>
            <!-- Stock Entry Form (only shown when a product is selected) -->
            <div class="form-container">
                <div class="form-header">
                    <h3><i class="fas fa-plus-circle"></i> Add New Stock for <?php echo htmlspecialchars($product_name); ?></h3>
                </div>
                
                <form id="stockEntryForm" method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" id="description" name="description" class="form-control" placeholder="e.g. stock order" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="platform">Platform</label>
                            <select id="platform" name="platform" class="form-select" required>
                                <option value="">Select platform</option>
                                <option value="SHOPEE">SHOPEE</option>
                                <option value="TIKTOK">TIKTOK</option>
                                <option value="LAZADA">LAZADA</option>
                                <option value="TAOBAO">TAOBAO</option>
                                <option value="OTHER">Other Platform</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" min="1" placeholder="e.g. 50" required onchange="calculatePricePerUnit()" oninput="calculatePricePerUnit()">
                        </div>
                        
                        <div class="form-group">
                            <label for="totalRM">Total RM</label>
                            <input type="number" id="totalRM" name="totalRM" class="form-control" min="0" step="0.01" placeholder="e.g. 250.50" required onchange="calculatePricePerUnit()" oninput="calculatePricePerUnit()">
                        </div>
                        
                        <div class="form-group">
                            <label for="pricePerUnit">Price Per Unit</label>
                            <input type="number" id="pricePerUnit" name="pricePerUnit" class="form-control read-only" step="0.01" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="eta">ETA</label>
                            <input type="date" id="eta" name="eta" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="remarks">Remarks</label>
                            <input type="text" id="remarks" name="remarks" class="form-control" placeholder="Link TikTok">
                        </div>
                        <div class="form-group">
                            <label for="status">Initial Status</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="OFD" selected>Out for Delivery (OFD)</option>
                                <option value="Available">Available (Only if already received)</option>
                            </select>
                            <small>New stock will typically be "Out for Delivery" until Operations confirms receipt</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button type="submit" name="save_stock_entry" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Entry
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Information Message about Automated Stock Updates -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>Note: Outgoing stock is now automatically updated when sales data is added through the "Add Product" form. The manual order form has been removed.</div>
            </div>
            
            <?php else: ?>
            <!-- Message when no product is selected -->
            <div class="form-container">
                <div style="text-align: center; padding: 30px;">
                    <i class="fas fa-hand-point-up" style="font-size: 48px; color: var(--secondary-color); margin-bottom: 20px;"></i>
                    <h3>Please Select a Product</h3>
                    <p>To add stock entries, please select a specific product from the dropdown above.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stock History Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-history"></i> Stock Entry History
                        <?php echo ($selected_team != 'all') ? ' for '.htmlspecialchars($team_name) : ''; ?>
                        <?php echo ($selected_product != 'all') ? ' - '.htmlspecialchars($product_name) : ''; ?>
                    </h3>
                    <div class="table-actions">
                        <a href="export_stock.php?type=entries<?php echo ($selected_team != 'all') ? '&team_id='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product_id='.$selected_product : ''; ?>" class="btn btn-outline">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Platform</th>
                                <?php if ($selected_product == 'all'): ?>
                                <th>Product</th>
                                <?php endif; ?>
                                <th>QTY</th>
                                <th>Total RM</th>
                                <th>Price Per Unit</th>
                                <th>ETA</th>
                                <th>Remarks</th>
                                <?php if ($selected_team == 'all'): ?>
                                <th>Team</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stock_entries && $stock_entries->num_rows > 0): ?>
                                <?php while ($entry = $stock_entries->fetch_assoc()): 
                                    // Get team name if needed
                                    $entry_team_name = "N/A";
                                    if ($selected_team == 'all' && !is_null($entry['team_id'])) {
                                        $team_name_query = "SELECT team_name FROM teams WHERE team_id = ?";
                                        $team_name_stmt = $dbconn->prepare($team_name_query);
                                        $team_name_stmt->bind_param("i", $entry['team_id']);
                                        $team_name_stmt->execute();
                                        $team_name_result = $team_name_stmt->get_result();
                                        if ($team_name_result && $team_name_result->num_rows > 0) {
                                            $entry_team_name = $team_name_result->fetch_assoc()['team_name'];
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?php echo date('d-M', strtotime($entry['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                    <td>
                                        <?php 
                                        $platform_class = 'platform-other';
                                        switch($entry['platform']) {
                                            case 'SHOPEE': $platform_class = 'platform-shopee'; break;
                                            case 'TIKTOK': $platform_class = 'platform-tiktok'; break;
                                            case 'LAZADA': $platform_class = 'platform-lazada'; break;
                                            case 'TAOBAO': $platform_class = 'platform-taobao'; break;
                                        }
                                        ?>
                                        <span class="platform-badge <?php echo $platform_class; ?>"><?php echo $entry['platform']; ?></span>
                                    </td>
                                    <?php if ($selected_product == 'all'): ?>
                                    <td><?php echo htmlspecialchars($entry['product_name'] ?: 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo $entry['quantity']; ?></td>
                                    <td>RM<?php echo number_format($entry['total_rm'], 2); ?></td>
                                    <td>RM<?php echo number_format($entry['price_per_unit'], 2); ?></td>
                                    <td><?php echo $entry['eta'] ? date('d-M', strtotime($entry['eta'])) : ''; ?></td>
                                    <td><?php echo $entry['remarks'] ? makeLinksClickable(htmlspecialchars($entry['remarks'])) : ''; ?></td>
                                    <?php if ($selected_team == 'all'): ?>
                                    <td><?php echo htmlspecialchars($entry_team_name); ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($selected_team == 'all' ? 1 : 0) + ($selected_product == 'all' ? 1 : 0) + 8; ?>" style="text-align: center;">
                                        No stock entries found
                                        <?php echo ($selected_team != 'all') ? ' for this team' : ''; ?>
                                        <?php echo ($selected_product != 'all') ? ' and product' : ''; ?>.
                                        <?php echo ($selected_product != 'all') ? 'Add your first stock entry above.' : 'Please select a product to add stock.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination for Stock Entries -->
                <?php if ($total_stock_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?page=<?php echo $page-1; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for($i = max(1, $page-2); $i <= min($total_stock_pages, $page+2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>" <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_stock_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-right"></i></a>
                        <a href="?page=<?php echo $total_stock_pages; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Order History Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-shopping-cart"></i> Order History
                        <?php echo ($selected_team != 'all') ? ' for '.htmlspecialchars($team_name) : ''; ?>
                        <?php echo ($selected_product != 'all') ? ' - '.htmlspecialchars($product_name) : ''; ?>
                    </h3>
                    <div class="table-actions">
                        <a href="export_stock.php?type=orders<?php echo ($selected_team != 'all') ? '&team_id='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product_id='.$selected_product : ''; ?>" class="btn btn-outline">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <?php if ($selected_product == 'all'): ?>
                                <th>Product</th>
                                <?php endif; ?>
                                <th>Order Received</th>
                                <th>Balance Stock</th>
                                <th>Status</th>
                                <?php if ($selected_team == 'all'): ?>
                                <th>Team</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
<?php if ($order_entries && $order_entries->num_rows > 0): ?>
                                <?php while ($order = $order_entries->fetch_assoc()): 
                                    // Get team name if needed
                                    $order_team_name = "N/A";
                                    if ($selected_team == 'all' && !is_null($order['team_id'])) {
                                        $team_name_query = "SELECT team_name FROM teams WHERE team_id = ?";
                                        $team_name_stmt = $dbconn->prepare($team_name_query);
                                        $team_name_stmt->bind_param("i", $order['team_id']);
                                        $team_name_stmt->execute();
                                        $team_name_result = $team_name_stmt->get_result();
                                        if ($team_name_result && $team_name_result->num_rows > 0) {
                                            $order_team_name = $team_name_result->fetch_assoc()['team_name'];
                                        }
                                    }
                                
                                    // Determine stock status and CSS class
                                    $stock_class = 'stock-normal';
                                    $badge_class = 'badge-normal';
                                    
                                    if ($order['status'] == 'Out of Stock') {
                                        $stock_class = 'stock-danger';
                                        $badge_class = 'badge-danger';
                                    } elseif ($order['status'] == 'Low Stock') {
                                        $stock_class = 'stock-warning';
                                        $badge_class = 'badge-warning';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo date('d-M', strtotime($order['date'])); ?></td>
                                    <?php if ($selected_product == 'all'): ?>
                                    <td><?php echo htmlspecialchars($order['product_name'] ?: 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo $order['order_received']; ?></td>
                                    <td><span class="<?php echo $stock_class; ?>"><?php echo $order['balance_stock']; ?></span></td>
                                    <td><span class="stock-badge <?php echo $badge_class; ?>"><?php echo $order['status']; ?></span></td>
                                    <?php if ($selected_team == 'all'): ?>
                                    <td><?php echo htmlspecialchars($order_team_name); ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($selected_team == 'all' ? 1 : 0) + ($selected_product == 'all' ? 1 : 0) + 4; ?>" style="text-align: center;">
                                        No orders found
                                        <?php echo ($selected_team != 'all') ? ' for this team' : ''; ?>
                                        <?php echo ($selected_product != 'all') ? ' and product' : ''; ?>.
                                        Orders are now automatically created when sales data is added.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination for Order Entries -->
                <?php if ($total_order_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?page=<?php echo $page-1; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for($i = max(1, $page-2); $i <= min($total_order_pages, $page+2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>" <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_order_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-right"></i></a>
                        <a href="?page=<?php echo $total_order_pages; ?><?php echo ($selected_team != 'all') ? '&team='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product='.$selected_product : ''; ?><?php echo ($show_killed) ? '&show_killed=1' : ''; ?>"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Bulk Import Modal (Hidden by default) -->
            <div id="bulkImportModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title"><i class="fas fa-file-import"></i> Bulk Import Stock</h3>
                        <span class="close-modal" id="closeBulkImportModal">&times;</span>
                    </div>
                    
                    <div class="modal-body">
                        <form method="POST" action="bulk_import_stock.php" enctype="multipart/form-data">
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 10px; font-weight: 500;">Upload Excel/CSV File:</label>
                                <input type="file" name="stock_file" accept=".xlsx,.xls,.csv" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <p style="margin-top: 10px; font-size: 14px; color: #6c757d;">
                                    File should contain: Date, Description, Platform, Quantity, Total RM, ETA, Remarks
                                </p>
                                <a href="download_stock_template.php<?php echo ($selected_team != 'all') ? '?team_id='.$selected_team : ''; ?><?php echo ($selected_product != 'all') ? '&product_id='.$selected_product : ''; ?>" style="color: var(--secondary-color); text-decoration: none; font-weight: 500; font-size: 14px;">
                                    <i class="fas fa-download"></i> Download Template
                                </a>
                            </div>
                            
                            <?php if ($selected_team != 'all'): ?>
                            <input type="hidden" name="team_id" value="<?php echo $selected_team; ?>">
                            <?php endif; ?>
                            
                            <?php if ($selected_product != 'all'): ?>
                            <input type="hidden" name="product_id" value="<?php echo $selected_product; ?>">
                            <?php endif; ?>
                            
                            <div class="modal-footer">
                                <button type="button" id="cancelBulkImport" class="btn btn-outline">Cancel</button>
                                <button type="submit" name="bulk_import" class="btn btn-primary">Import</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Current Stock Details Modal -->
            <div id="currentStockModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title"><i class="fas fa-box"></i> Current Stock Details</h3>
                        <span class="close-modal" id="closeCurrentStockModal">&times;</span>
                    </div>
                    
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Stock Quantity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($current_stock_products && $current_stock_products->num_rows > 0): ?>
                                        <?php while ($product = $current_stock_products->fetch_assoc()): 
                                            // Determine stock status class
                                            $status_class = 'stock-normal';
                                            $badge_class = 'badge-normal';
                                            
                                            if ($product['stock_status'] == 'Out of Stock') {
                                                $status_class = 'stock-danger';
                                                $badge_class = 'badge-danger';
                                            } elseif ($product['stock_status'] == 'Low Stock') {
                                                $status_class = 'stock-warning';
                                                $badge_class = 'badge-warning';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td class="<?php echo $status_class; ?>"><?php echo $product['stock_quantity']; ?></td>
                                            <td><span class="stock-badge <?php echo $badge_class; ?>"><?php echo $product['stock_status']; ?></span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center;">No products found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline close-modal-btn">Close</button>
                    </div>
                </div>
            </div>
            
            <!-- Low Stock Details Modal -->
            <div id="lowStockModal" class="modal">
                <div class="modal-content">
                   <div class="modal-header">
    <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Stock Alerts</h3>
    <span class="close-modal" id="closeLowStockModal">&times;</span>
</div>
                    
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Current Stock</th>
                                        <th>Threshold</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($low_stock_products && $low_stock_products->num_rows > 0): ?>
                                        <?php while ($product = $low_stock_products->fetch_assoc()): 
                                            // Determine stock status class
                                            $status_class = 'stock-warning';
                                            $badge_class = 'badge-warning';
                                            
                                            if ($product['stock_status'] == 'Out of Stock') {
                                                $status_class = 'stock-danger';
                                                $badge_class = 'badge-danger';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td class="<?php echo $status_class; ?>"><?php echo $product['stock_quantity']; ?></td>
                                            <td><?php echo $product['threshold']; ?></td>
                                            <td><span class="stock-badge <?php echo $badge_class; ?>"><?php echo $product['stock_status']; ?></span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center;">No products with low stock found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline close-modal-btn">Close</button>
                    </div>
                </div>
            </div>
            
            <!-- Orders Today Details Modal -->
            <div id="ordersTodayModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title"><i class="fas fa-shopping-cart"></i> Orders Today Details</h3>
                        <span class="close-modal" id="closeOrdersTodayModal">&times;</span>
                    </div>
                    
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Product</th>
                                        <th>Order Quantity</th>
                                        <th>Balance Stock</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($orders_today && $orders_today->num_rows > 0): ?>
                                        <?php while ($order = $orders_today->fetch_assoc()): 
                                            // Determine stock status class
                                            $status_class = 'stock-normal';
                                            $badge_class = 'badge-normal';
                                            
                                            if ($order['status'] == 'Out of Stock') {
                                                $status_class = 'stock-danger';
                                                $badge_class = 'badge-danger';
                                            } elseif ($order['status'] == 'Low Stock') {
                                                $status_class = 'stock-warning';
                                                $badge_class = 'badge-warning';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo date('H:i', strtotime($order['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                            <td><?php echo $order['order_received']; ?></td>
                                            <td class="<?php echo $status_class; ?>"><?php echo $order['balance_stock']; ?></td>
                                            <td><span class="stock-badge <?php echo $badge_class; ?>"><?php echo $order['status']; ?></span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center;">No orders received today</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline close-modal-btn">Close</button>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> Dr Ecomm Formula | 🛠️ Developed with care by Fakhrul 💻</p>
            </div>
        </main>
    </div>
    
    <!-- JavaScript for Functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const bulkImportModal = document.getElementById('bulkImportModal');
            const currentStockModal = document.getElementById('currentStockModal');
            const lowStockModal = document.getElementById('lowStockModal');
            const ordersTodayModal = document.getElementById('ordersTodayModal');
            
            // Open modal buttons
            const bulkImportBtn = document.getElementById('bulkImportBtn');
            
            // Close modal elements
            const closeBulkImportModal = document.getElementById('closeBulkImportModal');
            const closeCurrentStockModal = document.getElementById('closeCurrentStockModal');
            const closeLowStockModal = document.getElementById('closeLowStockModal');
            const closeOrdersTodayModal = document.getElementById('closeOrdersTodayModal');
            const closeModalBtns = document.querySelectorAll('.close-modal-btn');
            
            // Add click event listeners for opening modals
            if (bulkImportBtn) {
                bulkImportBtn.addEventListener('click', function() {
                    <?php if ($selected_product == 'all'): ?>
                    alert('Please select a specific product before importing stock.');
                    <?php else: ?>
                    bulkImportModal.style.display = 'block';
                    <?php endif; ?>
                });
            }
            
            // Add click event listeners for closing modals
            if (closeBulkImportModal) closeBulkImportModal.addEventListener('click', function() { bulkImportModal.style.display = 'none'; });
            if (closeCurrentStockModal) closeCurrentStockModal.addEventListener('click', function() { currentStockModal.style.display = 'none'; });
            if (closeLowStockModal) closeLowStockModal.addEventListener('click', function() { lowStockModal.style.display = 'none'; });
            if (closeOrdersTodayModal) closeOrdersTodayModal.addEventListener('click', function() { ordersTodayModal.style.display = 'none'; });
            
            // Add click event listeners for all close buttons
            closeModalBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) modal.style.display = 'none';
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == bulkImportModal) bulkImportModal.style.display = 'none';
                if (event.target == currentStockModal) currentStockModal.style.display = 'none';
                if (event.target == lowStockModal) lowStockModal.style.display = 'none';
                if (event.target == ordersTodayModal) ordersTodayModal.style.display = 'none';
            });
            
            // Cancel button for bulk import modal
            const cancelBulkImport = document.getElementById('cancelBulkImport');
            if (cancelBulkImport) {
                cancelBulkImport.addEventListener('click', function() {
                    bulkImportModal.style.display = 'none';
                });
            }
            
            // Auto-calculate price per unit
            const quantityEl = document.getElementById('quantity');
            const totalRMEl = document.getElementById('totalRM');
            if (quantityEl && totalRMEl) {
                quantityEl.addEventListener('input', calculatePricePerUnit);
                totalRMEl.addEventListener('input', calculatePricePerUnit);
            }
            
            // Toggle sidebar on mobile
            const toggleSidebarBtn = document.createElement('button');
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
            toggleSidebarBtn.style.cursor = 'pointer';
            toggleSidebarBtn.style.fontSize = '18px';
            toggleSidebarBtn.style.alignItems = 'center';
            toggleSidebarBtn.style.justifyContent = 'center';
            
            document.body.appendChild(toggleSidebarBtn);
            
            const sidebar = document.getElementById('sidebar');
            toggleSidebarBtn.addEventListener('click', function() {
                sidebar.classList.toggle('expanded');
            });
            
            // Show toggle button on mobile
            function checkMobile() {
                if (window.innerWidth <= 768) {
                    toggleSidebarBtn.style.display = 'flex';
                    sidebar.classList.remove('expanded');
                } else {
                    toggleSidebarBtn.style.display = 'none';
                    sidebar.classList.remove('expanded');
                }
            }
            
            window.addEventListener('resize', checkMobile);
            checkMobile(); // Initial check
            
            // Auto dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
            
            // Toggle for showing killed products
            const showKilledCheckbox = document.getElementById('showKilledProducts');
            if (showKilledCheckbox) {
                showKilledCheckbox.addEventListener('change', function() {
                    // Update the URL with show_killed parameter
                    const currentUrl = new URL(window.location.href);
                    if (this.checked) {
                        currentUrl.searchParams.set('show_killed', '1');
                    } else {
                        currentUrl.searchParams.delete('show_killed');
                    }
                    window.location.href = currentUrl.toString();
                });
            }
            
            // Product search functionality
            const productSearch = document.getElementById('productSearch');
            const productSelector = document.getElementById('product_selector');
            
            if (productSearch && productSelector) {
                productSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const options = productSelector.querySelectorAll('option');
                    
                    options.forEach(option => {
                        if (option.value === 'all') return; // Skip the "All Products" option
                        
                        const productName = option.getAttribute('data-name')?.toLowerCase() || '';
                        const productStock = option.getAttribute('data-stock') || '';
                        const productStatus = option.getAttribute('data-status')?.toLowerCase() || '';
                        const isKilled = option.getAttribute('data-killed') === 'true';
                        
                        // If search is empty and product is killed and we're not showing killed, hide it
                        if (searchTerm === '' && isKilled && !showKilledCheckbox?.checked) {
                            option.classList.add('hidden-option');
                            return;
                        }
                        
                        const matchesSearch = 
                            productName.includes(searchTerm) || 
                            productStock.includes(searchTerm) || 
                            productStatus.includes(searchTerm) ||
                            (isKilled && 'discontinued'.includes(searchTerm)) ||
                            (isKilled && 'killed'.includes(searchTerm));
                        
                        // Using display none doesn't work in options, so we use a class
                        if (matchesSearch) {
                            option.classList.remove('hidden-option');
                            if (searchTerm) {
                                option.classList.add('highlight-match');
                            } else {
                                option.classList.remove('highlight-match');
                            }
                        } else {
                            option.classList.add('hidden-option');
                            option.classList.remove('highlight-match');
                        }
                    });
                    
                    // Count visible options
                    let visibleOptions = 0;
                    options.forEach(option => {
                        if (option.value !== 'all' && !option.classList.contains('hidden-option')) {
                            visibleOptions++;
                        }
                    });
                    
                    // Show no results message if needed
                    const noResultsMessage = document.getElementById('noResultsMessage');
                    if (visibleOptions === 0 && searchTerm !== '') {
                        if (!noResultsMessage) {
                            const message = document.createElement('div');
                            message.id = 'noResultsMessage';
                            message.className = 'alert alert-info';
                            message.style.marginTop = '10px';
                            message.innerHTML = `<i class="fas fa-search"></i> No products found matching "${searchTerm}"`;
                            productSelector.parentNode.appendChild(message);
                        }
                    } else {
                        if (noResultsMessage) {
                            noResultsMessage.remove();
                        }
                    }
                });
                
                // Trigger search on page load to apply initial filtering
                const searchEvent = new Event('input', {
                    bubbles: true,
                    cancelable: true,
                });
                productSearch.dispatchEvent(searchEvent);
                
                // Clear search when selecting a product
                productSelector.addEventListener('change', function() {
                    productSearch.value = '';
                    const options = productSelector.querySelectorAll('option');
                    options.forEach(option => {
                        option.classList.remove('hidden-option');
                        option.classList.remove('highlight-match');
                    });
                    
                    const noResultsMessage = document.getElementById('noResultsMessage');
                    if (noResultsMessage) {
                        noResultsMessage.remove();
                    }
                });
            }
        });
        
        // Function to show Current Stock Modal
        function showCurrentStockModal() {
            document.getElementById('currentStockModal').style.display = 'block';
        }
        
        // Function to show Low Stock Modal
        function showLowStockModal() {
            document.getElementById('lowStockModal').style.display = 'block';
        }
        
        // Function to show Orders Today Modal
        function showOrdersTodayModal() {
            document.getElementById('ordersTodayModal').style.display = 'block';
        }
        
        // Function to calculate price per unit
        function calculatePricePerUnit() {
            const quantityEl = document.getElementById('quantity');
            const totalRMEl = document.getElementById('totalRM');
            const pricePerUnitEl = document.getElementById('pricePerUnit');
            
            if (!quantityEl || !totalRMEl || !pricePerUnitEl) return;
            
            const quantity = parseFloat(quantityEl.value) || 0;
            const totalRM = parseFloat(totalRMEl.value) || 0;
            
            if (quantity > 0 && totalRM > 0) {
                const pricePerUnit = (totalRM / quantity).toFixed(2);
                pricePerUnitEl.value = pricePerUnit;
            } else {
                pricePerUnitEl.value = '';
            }
        }
    </script>
</body>
</html>