<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Redirect if not admin
if (!$is_admin) {
    header("Location: index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: all_products.php");
    exit();
}

$id = intval($_GET['id']);

// If confirmation is needed
if (!isset($_GET['confirm']) || $_GET['confirm'] != 'yes') {
    // Display confirmation page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirm Delete - Dr Ecomm Formula</title>
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
                --danger-color: #e74c3c;
                --danger-light: #f5b4ae;
            }
            
            body {
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f0f2f5;
                color: var(--dark-text);
            }
            
            .main-content {
                max-width: 600px;
                margin: 100px auto;
                padding: 20px;
            }
            
            .confirmation-box {
                background-color: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                padding: 30px;
                text-align: center;
            }
            
            .icon-container {
                margin-bottom: 20px;
            }
            
            .icon-container i {
                font-size: 64px;
                color: var(--danger-color);
            }
            
            h1 {
                color: var(--dark-text);
                font-size: 24px;
                margin-bottom: 15px;
            }
            
            p {
                color: #555;
                margin-bottom: 25px;
                font-size: 16px;
            }
            
            .actions {
                display: flex;
                justify-content: center;
                gap: 15px;
            }
            
            .btn {
                padding: 10px 20px;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: var(--transition);
                text-decoration: none;
            }
            
            .btn i {
                margin-right: 6px;
            }
            
            .btn-danger {
                background-color: var(--danger-color);
                color: white;
                border: none;
            }
            
            .btn-danger:hover {
                background-color: #c0392b;
            }
            
            .btn-secondary {
                background-color: var(--light-bg);
                color: var(--dark-text);
                border: 1px solid #ddd;
            }
            
            .btn-secondary:hover {
                background-color: #e9ecef;
            }
        </style>
    </head>
    <body>
        <div class="main-content">
            <div class="confirmation-box">
                <div class="icon-container">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Confirm Delete</h1>
                <p>Are you sure you want to delete this product? This action cannot be undone.</p>
                <div class="actions">
                    <a href="all_products.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <a href="admin_delete_product.php?id=<?php echo $id; ?>&confirm=yes" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get product info for logging
$sql = "SELECT product_name FROM products WHERE id = ?";
$stmt = $dbconn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product_name = ($result->num_rows > 0) ? $result->fetch_assoc()['product_name'] : 'Unknown Product';

// Process deletion
$sql = "DELETE FROM products WHERE id = ?";
$stmt = $dbconn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Logging is removed since the activity_log table doesn't exist
    
    // Redirect with success message
    header("Location: all_products.php?delete_success=1");
} else {
    // Failed to delete
    header("Location: all_products.php?delete_error=1");
}

$stmt->close();
$dbconn->close();
exit();
?>