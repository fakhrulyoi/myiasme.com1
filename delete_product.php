<?php
// Start the session to store messages
session_start();

// Include database connection
require 'dbconn_productProfit.php';
require 'auth.php';

// If this is a confirmation step (actual delete operation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && isset($_POST['id'])) {
    // Get the ID from the form
    $id = intval($_POST['id']);
    
    // Prepare and execute the delete query
    $sql = "DELETE FROM products WHERE id = ?";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Success - store a message and redirect
        $_SESSION['success_message'] = "Product deleted successfully!";
    } else {
        // Error - store the error message
        $_SESSION['error_message'] = "Error deleting product: " . $stmt->error;
    }
    
    // Close the statement
    $stmt->close();
    
    // Redirect back to products page
    header("Location: team_products.php");
    exit();
}

// If this is the initial request to delete (show confirmation page)
$id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($id <= 0) {
    $_SESSION['error_message'] = "Invalid product ID.";
    header("Location: team_products.php");
    exit();
}

// Get product details to show in the confirmation page
$sql = "SELECT p.*, t.team_name 
        FROM products p
        LEFT JOIN teams t ON p.team_id = t.team_id
        WHERE p.id = ?";
        
// Check if teams table uses id or team_id
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Update the query with the correct primary key
$sql = "SELECT p.*, t.team_name 
        FROM products p
        LEFT JOIN teams t ON p.team_id = t.$team_pk
        WHERE p.id = ?";

$stmt = $dbconn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Product not found.";
    header("Location: team_products.php");
    exit();
}

$product = $result->fetch_assoc();

// Check if user has permission to delete this product
if (!$is_admin && $product['team_id'] != $team_id) {
    $_SESSION['error_message'] = "You don't have permission to delete this product.";
    header("Location: team_products.php");
    exit();
}

// Include the navigation
include 'navigation.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Product Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
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
            max-width: 700px;
            margin: 40px auto;
        }

        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .card-header {
            background-color: var(--accent-color);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header .icon {
            background-color: rgba(255, 255, 255, 0.2);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .card-header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        .warning-message {
            padding: 16px;
            background-color: rgba(231, 76, 60, 0.08);
            border-left: 4px solid var(--accent-color);
            border-radius: 4px;
            margin-bottom: 24px;
        }

        .product-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            background-color: var(--background-light);
            border-radius: var(--border-radius);
        }

        .product-details p {
            margin: 0 0 8px 0;
        }

        .detail-label {
            font-weight: 500;
            color: var(--text-light);
            font-size: 14px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-danger {
            background-color: var(--accent-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        /* Animation for warning icon */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .warning-icon {
            animation: pulse 1.5s infinite;
            display: inline-block;
        }

        @media (max-width: 600px) {
            .product-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Delete Product Confirmation</h1>
            </div>
            <div class="card-body">
                <div class="warning-message">
                    <p><i class="fas fa-exclamation-circle warning-icon"></i> <strong>Warning:</strong> This action cannot be undone. Once deleted, all data related to this product will be permanently removed.</p>
                </div>
                
                <h2>Product Information</h2>
                
                <div class="product-details">
                    <div>
                        <p>
                            <span class="detail-label">Product Name:</span><br>
                            <span class="detail-value"><?php echo htmlspecialchars($product['product_name']); ?></span>
                        </p>
                    </div>
                    <div>
                        <p>
                            <span class="detail-label">SKU:</span><br>
                            <span class="detail-value"><?php echo htmlspecialchars($product['sku']); ?></span>
                        </p>
                    </div>
                    <div>
                        <p>
                            <span class="detail-label">Units Sold:</span><br>
                            <span class="detail-value"><?php echo number_format($product['unit_sold']); ?></span>
                        </p>
                    </div>
                    <div>
                        <p>
                            <span class="detail-label">Sales Amount:</span><br>
                            <span class="detail-value">RM <?php echo number_format($product['sales'], 2); ?></span>
                        </p>
                    </div>
                    <div>
                        <p>
                            <span class="detail-label">Profit:</span><br>
                            <span class="detail-value">RM <?php echo number_format($product['profit'], 2); ?></span>
                        </p>
                    </div>
                    <div>
                        <p>
                            <span class="detail-label">Date Added:</span><br>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($product['created_at'])); ?></span>
                        </p>
                    </div>
                </div>
                
                <form method="POST" action="delete_product.php">
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="confirm_delete" value="1">
                    
                    <div class="actions">
                        <a href="team_products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Confirm Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>