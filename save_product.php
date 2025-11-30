<?php
require 'auth.php';
require 'dbconn_productProfit.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $productName = $_POST['productName'];
    $sku = $_POST['sku'];
    $adsSpend = floatval($_POST['adsSpend']) * 1.08; // ADS SPEND with 8% tax
    $purchase = intval($_POST['purchase']);
    $unitSold = intval($_POST['unitSold']);
    $actualCost = floatval($_POST['actualCost']);
    $sales = floatval($_POST['sales']);
    $dateAdded = $_POST['dateAdded'];
    
    // Calculate derived values
    $cod = $purchase * 10; // COD is RM10 per purchase
    $cpp = ($purchase > 0) ? $adsSpend / $purchase : 0; // Cost per purchase
    $itemCost = $actualCost * $unitSold; // Item cost is actual cost * units sold
    $cogs = $itemCost + $cod; // COGS = total cost + total COD
    $profit = $sales - $adsSpend - $cogs; // Profit = sales - ads spend - COGS
    
    // Get current user's team_id
    $user_id = $_SESSION['user_id'];
    $sql_team = "SELECT team_id FROM users WHERE id = ?";
    $stmt_team = $dbconn->prepare($sql_team);
    $stmt_team->bind_param("i", $user_id);
    $stmt_team->execute();
    $team_result = $stmt_team->get_result();
    $team_data = $team_result->fetch_assoc();
    $team_id = $team_data['team_id'];
    
    // Determine team_id to use (for admin override)
    if (isset($is_admin) && $is_admin && isset($_POST['team_id'])) {
        $team_id_to_use = intval($_POST['team_id']);
    } else {
        $team_id_to_use = $team_id;
    }
    
    // Check if this exact combination exists (same SKU, same date, same team)
    $sql_check = "SELECT id, stock_quantity FROM products 
                 WHERE sku = ? AND team_id = ? AND DATE(created_at) = ? 
                 LIMIT 1";
    $stmt_check = $dbconn->prepare($sql_check);
    $stmt_check->bind_param("sis", $sku, $team_id_to_use, $dateAdded);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    
    $isUpdate = false;
    $current_stock = 0;
    $product_id = null;
    
    if ($check_result->num_rows > 0) {
        // Entry for this SKU on this date exists - UPDATE
        $existing_data = $check_result->fetch_assoc();
        $product_id = $existing_data['id'];
        // Handle NULL stock_quantity properly
        $current_stock = intval($existing_data['stock_quantity'] ?? 0);  // Default to 0 if NULL
        $isUpdate = true;
        
        // Get the previous unit_sold to adjust stock calculation
        $sql_get_prev = "SELECT unit_sold FROM products WHERE id = ?";
        $stmt_prev = $dbconn->prepare($sql_get_prev);
        $stmt_prev->bind_param("i", $product_id);
        $stmt_prev->execute();
        $prev_result = $stmt_prev->get_result();
        $prev_data = $prev_result->fetch_assoc();
        $prev_unit_sold = intval($prev_data['unit_sold'] ?? 0);
        
        // Adjust current stock by adding back the previous units
        $current_stock = $current_stock + $prev_unit_sold;
        
        $sql = "UPDATE products SET 
                product_name = ?,
                ads_spend = ?, 
                purchase = ?, 
                cpp = ?, 
                unit_sold = ?, 
                actual_cost = ?, 
                item_cost = ?, 
                cod = ?, 
                sales = ?, 
                profit = ?,
                cogs = ?
                WHERE id = ?";
        
        $stmt = $dbconn->prepare($sql);
     $stmt->bind_param("sdididdidddi", $productName, $adsSpend, $purchase, $cpp, $unitSold, $actualCost, $itemCost, $cod, $sales, $profit, $cogs, $product_id);
    } else {
        // No entry for this SKU on this date - check if product base exists
        $sql_check_base = "SELECT id, stock_quantity FROM products 
                          WHERE sku = ? AND team_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT 1";
        $stmt_check_base = $dbconn->prepare($sql_check_base);
        $stmt_check_base->bind_param("si", $sku, $team_id_to_use);
        $stmt_check_base->execute();
        $base_result = $stmt_check_base->get_result();
        
        if ($base_result->num_rows > 0) {
            // Product exists, get latest stock
            $base_data = $base_result->fetch_assoc();
            // Handle NULL stock_quantity properly
            $current_stock = intval($base_data['stock_quantity'] ?? 0);  // Default to 0 if NULL
        } else {
            // Completely new product
            $current_stock = 0;
        }
        
        // Insert new entry for this date
        $pakej = NULL;
        $sql = "INSERT INTO products (sku, product_name, ads_spend, purchase, cpp, unit_sold, actual_cost, item_cost, cod, sales, profit, cogs, created_at, pakej, team_id, status, stock_quantity) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";
                
        $stmt = $dbconn->prepare($sql);
      $stmt->bind_param("ssdididdddddssii", $sku, $productName, $adsSpend, $purchase, $cpp, $unitSold, $actualCost, $itemCost, $cod, $sales, $profit, $cogs, $dateAdded, $pakej, $team_id_to_use, $current_stock);
    }
    
    // Begin transaction
    $dbconn->begin_transaction();
    
    try {
        // Execute the UPDATE or INSERT
        if (!$stmt->execute()) {
            throw new Exception("Error saving product data: " . $dbconn->error);
        }
        
        // Get the product ID if it's a new insert
        if (!$isUpdate) {
            $product_id = $dbconn->insert_id;
        }
        
        // Calculate new stock (allow negative values)
        $new_stock = $current_stock - $unitSold;
        
        // Get low stock threshold
        $threshold_sql = "SELECT setting_value FROM stock_settings WHERE setting_name = 'low_stock_threshold'";
        $threshold_sql .= " AND (team_id = ? OR team_id IS NULL) ORDER BY team_id DESC LIMIT 1";
        $threshold_stmt = $dbconn->prepare($threshold_sql);
        $threshold_stmt->bind_param("i", $team_id_to_use);
        $threshold_stmt->execute();
        $threshold_result = $threshold_stmt->get_result();
        $low_stock_threshold = 50; // Default
        
        if ($threshold_result && $threshold_result->num_rows > 0) {
            $low_stock_threshold = intval($threshold_result->fetch_assoc()['setting_value']);
            $low_stock_threshold = max(50, $low_stock_threshold); // Ensure minimum of 50
        }
        
        // Set appropriate stock status (including for negative stock)
        if ($new_stock < 0) {
            $stock_status = 'Negative Stock'; // New status for negative stock
        } elseif ($new_stock == 0) {
            $stock_status = 'Out of Stock';
        } elseif ($new_stock <= $low_stock_threshold) {
            $stock_status = 'Low Stock';
        } else {
            $stock_status = 'Healthy';
        }
        
        // Update ALL entries with the same SKU to have the latest stock
        $update_stock = "UPDATE products SET stock_quantity = ?, stock_status = ? WHERE sku = ? AND team_id = ?";
        $update_stmt = $dbconn->prepare($update_stock);
        $update_stmt->bind_param("issi", $new_stock, $stock_status, $sku, $team_id_to_use);
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating stock: " . $dbconn->error);
        }
        
        // Create stock order entry
        $stock_order_sql = "INSERT INTO stock_orders (date, order_received, balance_stock, status, team_id, product_id) 
                            VALUES (?, ?, ?, ?, ?, ?)";
        $stock_order_stmt = $dbconn->prepare($stock_order_sql);
        $stock_order_stmt->bind_param("siisii", $dateAdded, $unitSold, $new_stock, $stock_status, $team_id_to_use, $product_id);
        if (!$stock_order_stmt->execute()) {
            throw new Exception("Error creating stock order: " . $dbconn->error);
        }
        
        // Commit the transaction
        $dbconn->commit();
        
        // Prepare success message
        $action = $isUpdate ? "updated" : "added";
        $message = "Product $productName successfully $action with profit: RM" . number_format($profit, 2);
        
        // Add warning if stock went negative
        if ($new_stock < 0) {
            $message .= " (Warning: Stock is now negative: $new_stock)";
        }
        
        header("Location: index.php?success=1&message=" . urlencode($message) . "&product=" . urlencode($productName) . "&profit=" . $profit);
        exit();
        
    } catch (Exception $e) {
        // Roll back the transaction in case of any error
        $dbconn->rollback();
        header("Location: index.php?error=1&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // If not POST request, redirect to index
    header("Location: index.php");
    exit();
}