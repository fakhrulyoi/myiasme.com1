<?php
require 'dbconn_productProfit.php';

// Check if ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];
    
    // Delete the product
    $stmt = $dbconn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Success message
        header("Location: product_management.php?success=Product deleted successfully");
        exit;
    } else {
        // Error message
        header("Location: product_management.php?error=Failed to delete product: " . $dbconn->error);
        exit;
    }
} else {
    // If accessed without ID
    header("Location: product_management.php?error=No product specified for deletion");
    exit;
}