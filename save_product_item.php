<?php
require 'dbconn_productProfit.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $product_name = $_POST['product_name'];
    $product_code = $_POST['product_code'];
    $actual_cost = $_POST['actual_cost'];
    $selling_price = $_POST['selling_price'];
    
    // Check if we're updating an existing product
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update existing product
        $id = $_POST['id'];
        
        $stmt = $dbconn->prepare("UPDATE products SET 
                                 product_name = ?,
                                 product_code = ?,
                                 actual_cost = ?,
                                 selling_price = ? 
                                 WHERE id = ?");
        
        $stmt->bind_param("ssddi", $product_name, $product_code, $actual_cost, $selling_price, $id);
        
        if ($stmt->execute()) {
            // Success message
            header("Location: product_management.php?success=Product updated successfully");
            exit;
        } else {
            // Error message
            header("Location: product_management.php?error=Failed to update product: " . $dbconn->error);
            exit;
        }
    } else {
        // Insert new product
        $stmt = $dbconn->prepare("INSERT INTO products (product_name, product_code, actual_cost, selling_price) 
                                VALUES (?, ?, ?, ?)");
        
        $stmt->bind_param("ssdd", $product_name, $product_code, $actual_cost, $selling_price);
        
        if ($stmt->execute()) {
            // Success message
            header("Location: product_management.php?success=Product added successfully");
            exit;
        } else {
            // Error message
            header("Location: product_management.php?error=Failed to add product: " . $dbconn->error);
            exit;
        }
    }
} else {
    // If accessed directly without form submission
    header("Location: product_management.php");
    exit;
}