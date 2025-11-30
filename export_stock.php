<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Redirect if not admin
if (!$is_admin) {
    header("Location: team_products.php");
    exit();
}

// Get team_id and product_id from URL parameters if provided
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

// Get team and product names for filename
$team_name = "All_Teams";
$product_name = "All_Products";

// Get team name if specified
if ($team_id) {
    $team_query = "SELECT team_name FROM teams WHERE team_id = ?";
    $team_stmt = $dbconn->prepare($team_query);
    $team_stmt->bind_param("i", $team_id);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    
    if ($team_result && $team_result->num_rows > 0) {
        $team_name = str_replace(' ', '_', $team_result->fetch_assoc()['team_name']);
    }
}

// Get product name if specified
if ($product_id) {
    $product_query = "SELECT product_name FROM products WHERE id = ?";
    $product_stmt = $dbconn->prepare($product_query);
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    
    if ($product_result && $product_result->num_rows > 0) {
        $product_name = str_replace(' ', '_', $product_result->fetch_assoc()['product_name']);
    }
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=stock_export_' . $team_name . '_' . $product_name . '_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Export type (entries or orders)
$type = isset($_GET['type']) ? $_GET['type'] : 'entries';

if ($type == 'entries') {
    // Headers for stock entries
    $headers = array('Date', 'Description', 'Platform', 'Quantity', 'Total RM', 'Price Per Unit', 'ETA', 'Remarks');
    
    // Add Team/Product column headers if exporting all
    if (!$team_id) {
        $headers[] = 'Team';
    }
    if (!$product_id) {
        $headers[] = 'Product';
    }
    
    fputcsv($output, $headers);
    
    // Get stock entries with team and product filtering
    $sql = "SELECT se.*, t.team_name, p.product_name 
            FROM stock_entries se 
            LEFT JOIN teams t ON se.team_id = t.team_id
            LEFT JOIN products p ON se.product_id = p.id
            WHERE 1=1";
    
    $conditions = array();
    $params = array();
    $types = "";
    
    if ($team_id) {
        $conditions[] = "se.team_id = ?";
        $params[] = $team_id;
        $types .= "i";
    }
    
    if ($product_id) {
        $conditions[] = "se.product_id = ?";
        $params[] = $product_id;
        $types .= "i";
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY se.date DESC";
    
    $stmt = $dbconn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data = array(
                date('Y-m-d', strtotime($row['date'])),
                $row['description'],
                $row['platform'],
                $row['quantity'],
                $row['total_rm'],
                $row['price_per_unit'],
                $row['eta'] ? date('Y-m-d', strtotime($row['eta'])) : '',
                $row['remarks']
            );
            
            // Add team/product if exporting all
            if (!$team_id) {
                $data[] = $row['team_name'] ? $row['team_name'] : 'N/A';
            }
            if (!$product_id) {
                $data[] = $row['product_name'] ? $row['product_name'] : 'N/A';
            }
            
            fputcsv($output, $data);
        }
    }
} else {
    // Headers for orders
    $headers = array('Date', 'Order Received', 'Balance Stock', 'Status');
    
    // Add Team/Product column headers if exporting all
    if (!$team_id) {
        $headers[] = 'Team';
    }
    if (!$product_id) {
        $headers[] = 'Product';
    }
    
    fputcsv($output, $headers);
    
    // Get orders with team and product filtering
    $sql = "SELECT so.*, t.team_name, p.product_name 
            FROM stock_orders so 
            LEFT JOIN teams t ON so.team_id = t.team_id
            LEFT JOIN products p ON so.product_id = p.id
            WHERE 1=1";
    
    $conditions = array();
    $params = array();
    $types = "";
    
    if ($team_id) {
        $conditions[] = "so.team_id = ?";
        $params[] = $team_id;
        $types .= "i";
    }
    
    if ($product_id) {
        $conditions[] = "so.product_id = ?";
        $params[] = $product_id;
        $types .= "i";
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY so.date DESC";
    
    $stmt = $dbconn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data = array(
                date('Y-m-d', strtotime($row['date'])),
                $row['order_received'],
                $row['balance_stock'],
                $row['status']
            );
            
            // Add team/product if exporting all
            if (!$team_id) {
                $data[] = $row['team_name'] ? $row['team_name'] : 'N/A';
            }
            if (!$product_id) {
                $data[] = $row['product_name'] ? $row['product_name'] : 'N/A';
            }
            
            fputcsv($output, $data);
        }
    }
}

fclose($output);
exit();
?>