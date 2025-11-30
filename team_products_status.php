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

// Get filter for active/killed products
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

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

// Check if we need to update product status
if (isset($_POST['update_status']) && isset($_POST['product_id']) && isset($_POST['new_status'])) {
    $product_id = $_POST['product_id'];
    $new_status = $_POST['new_status'];
    
    // Verify the user has permissions to update this product
    $can_update = false;
    
    if ($is_admin) {
        $can_update = true;
    } else {
        // Check if the product belongs to the user's team
        $check_sql = "SELECT id FROM products WHERE id = ? AND team_id = ?";
        $check_stmt = $dbconn->prepare($check_sql);
        $check_stmt->bind_param("ii", $product_id, $team_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $can_update = true;
        }
        $check_stmt->close();
    }
    
    if ($can_update) {
        // Update the product status
        $update_sql = "UPDATE products SET status = ? WHERE id = ?";
        $update_stmt = $dbconn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $product_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Product status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating product status: " . $dbconn->error;
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "You don't have permission to update this product.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

// Get all unique products with their status, total sales, and total profit
$sql_products = "SELECT 
    p.id,
    p.sku,
    p.product_name,
    p.status,
    COUNT(p.id) AS entry_count,
    SUM(p.unit_sold) AS total_units,
    SUM(p.sales) AS total_sales,
    SUM(p.profit) AS total_profit,
    MAX(p.created_at) AS last_update
FROM products p
WHERE 1=1 ";

// Add search condition if search query is provided
if (!empty($search_query)) {
    $sql_products .= "AND (p.sku LIKE ? OR p.product_name LIKE ?) ";
}

// Add status filter if provided
if ($status_filter !== 'all') {
    $sql_products .= "AND p.status = ? ";
}

// Add team filter if not admin
if (!$is_admin) {
    $sql_products .= "AND p.team_id = ? ";
}

$sql_products .= "GROUP BY p.sku, p.product_name, p.status
ORDER BY p.product_name ASC";

// Prepare statement
$stmt_products = $dbconn->prepare($sql_products);

// Binding parameters
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    
    if ($status_filter !== 'all') {
        if (!$is_admin) {
            $stmt_products->bind_param("sssi", $search_param, $search_param, $status_filter, $team_id);
        } else {
            $stmt_products->bind_param("sss", $search_param, $search_param, $status_filter);
        }
    } else {
        if (!$is_admin) {
            $stmt_products->bind_param("ssi", $search_param, $search_param, $team_id);
        } else {
            $stmt_products->bind_param("ss", $search_param, $search_param);
        }
    }
} else {
    if ($status_filter !== 'all') {
        if (!$is_admin) {
            $stmt_products->bind_param("si", $status_filter, $team_id);
        } else {
            $stmt_products->bind_param("s", $status_filter);
        }
    } else {
        if (!$is_admin) {
            $stmt_products->bind_param("i", $team_id);
        }
        // No parameters needed if admin and no filters
    }
}

$stmt_products->execute();
$products_result = $stmt_products->get_result();

// Include the navigation component
include 'navigation.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_admin ? "All Teams Products Status" : $team_name . " Products Status"; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --background-light: #f4f6f8;
            --text-dark: #2c3e50;
            --border-radius: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--background-light);
            line-height: 1.6;
            color: var(--text-dark);
            margin: 0;
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 8px 12px;
            padding-right: 30px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 250px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
            border-color: #3498db;
            outline: none;
        }

        .status-filters {
            display: flex;
            gap: 10px;
        }

        .status-filter {
            padding: 8px 16px;
            background-color: #f1f3f5;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .status-filter.active {
            background-color: var(--secondary-color);
            color: white;
        }

        .status-filter:hover {
            background-color: #e9ecef;
        }

        .status-filter.active:hover {
            background-color: #2980b9;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        table thead {
            background-color: #f1f3f5;
        }

        table th, table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f1f3f5;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.15);
            color: #27ae60;
        }

        .status-killed {
            background-color: rgba(231, 76, 60, 0.15);
            color: #c0392b;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: white;
            gap: 5px;
        }

        .btn-primary {
            background-color: var(--secondary-color);
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: var(--success-color);
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-danger {
            background-color: var(--accent-color);
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #666;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 1.5rem;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .page-header, .filter-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .search-container, .status-filters {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <header class="page-header">
            <h1><?php echo $is_admin ? "All Teams Products Status" : $team_name . " Products Status"; ?></h1>
        </header>

        <!-- Filters -->
        <div class="filter-container">
            <div class="status-filters">
                <a href="?status=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="status-filter <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                   All Products
                </a>
                <a href="?status=active<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="status-filter <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                   <i class="fas fa-check-circle"></i> Active
                </a>
                <a href="?status=killed<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="status-filter <?php echo $status_filter === 'killed' ? 'active' : ''; ?>">
                   <i class="fas fa-times-circle"></i> Killed
                </a>
            </div>
            
            <div class="search-container">
                <form method="GET" action="" id="searchForm" style="display: flex; gap: 10px;">
                    <!-- Preserve status filter when searching -->
                    <?php if($status_filter !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <?php endif; ?>
                    
                    <div class="search-box">
                        <input type="text" name="search" id="searchInput" placeholder="Search by SKU or name..." 
                            value="<?php echo htmlspecialchars($search_query); ?>">
                        <?php if(!empty($search_query)): ?>
                        <a href="?<?php echo $status_filter !== 'all' ? 'status=' . $status_filter : ''; ?>" 
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #999; text-decoration: none;"
                        title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <?php if ($products_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Status</th>
                    <th>Total Units Sold</th>
                    <th>Total Sales (RM)</th>
                    <th>Total Profit (RM)</th>
                    <th>Last Update</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($product = $products_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($product['status']); ?>">
                            <?php if ($product['status'] === 'active'): ?>
                                <i class="fas fa-check-circle"></i>
                            <?php else: ?>
                                <i class="fas fa-times-circle"></i>
                            <?php endif; ?>
                            <?php echo ucfirst($product['status']); ?>
                        </span>
                    </td>
                  <td><?php echo number_format((float)$product['total_units'] ?? 0); ?></td>
<td>RM <?php echo number_format((float)$product['total_sales'] ?? 0, 2); ?></td>
<td>RM <?php echo number_format((float)$product['total_profit'] ?? 0, 2); ?></td>
                    <td><?php echo date('d M Y', strtotime($product['last_update'])); ?></td>
                    <td>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $product['status'] === 'active' ? 'killed' : 'active'; ?>">
                            <input type="hidden" name="update_status" value="1">
                            <?php if ($product['status'] === 'active'): ?>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times-circle"></i> Kill Product
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle"></i> Activate Product
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <?php if (!empty($search_query)): ?>
                <h3>No products found</h3>
                <p>No products match your search for "<?php echo htmlspecialchars($search_query); ?>"</p>
                <a href="?<?php echo $status_filter !== 'all' ? 'status=' . $status_filter : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-times"></i> Clear Search
                </a>
            <?php elseif ($status_filter !== 'all'): ?>
                <h3>No <?php echo $status_filter; ?> products found</h3>
                <p>There are no <?php echo $status_filter; ?> products to display</p>
                <a href="?" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Products
                </a>
            <?php else: ?>
                <h3>No products available</h3>
                <p>No products have been added yet</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
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
</body>
</html>