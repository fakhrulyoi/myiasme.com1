<?php
require 'auth.php';

// Redirect if not admin
if (!$is_admin) {
    header("Location: team_products.php");
    exit();
}

// Get team_id and product_id from URL parameter if provided
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

$team_name = "All_Teams";
$product_name = "All_Products";

// Get team and product names for display
if ($team_id || $product_id) {
    require 'dbconn_productProfit.php';
    
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
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=stock_import_template_' . $team_name . '_' . $product_name . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// CSV Headers - Note: We don't include team_id/product_id in the template as it will be set from the form
fputcsv($output, array('Date', 'Description', 'Platform', 'Quantity', 'Total RM', 'ETA', 'Remarks'));

// Sample data
$current_date = date('Y-m-d');
fputcsv($output, array($current_date, 'Stock order', 'SHOPEE', '50', '250.50', date('Y-m-d', strtotime('+7 days')), 'Sample remarks'));
fputcsv($output, array($current_date, 'Stock order', 'TIKTOK', '25', '150.75', date('Y-m-d', strtotime('+5 days')), ''));
fputcsv($output, array($current_date, 'Stock order', 'LAZADA', '30', '180.00', '', 'Priority order'));

// Add team/product-specific note
if ($team_id || $product_id) {
    fputcsv($output, array('', '', '', '', '', '', ''));
    $note = "Note: This template will import data for ";
    if ($team_id) {
        $note .= "team: $team_name";
    }
    if ($team_id && $product_id) {
        $note .= " and ";
    }
    if ($product_id) {
        $note .= "product: $product_name";
    }
    fputcsv($output, array($note, '', '', '', '', '', ''));
}

fclose($output);
exit();
?>