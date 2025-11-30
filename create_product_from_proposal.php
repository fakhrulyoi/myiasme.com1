<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if user is admin
if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

// Check if POST request and proposal_id is set
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['proposal_id']) || !is_numeric($_POST['proposal_id'])) {
    $_SESSION['error_message'] = "Invalid request or missing proposal ID.";
    header("Location: product_approvals.php");
    exit();
}

$proposal_id = intval($_POST['proposal_id']);

// Get proposal details - only if it's approved
$sql = "SELECT 
    pp.*,
    t.team_name
FROM product_proposals pp
JOIN teams t ON pp.team_id = t.team_id
WHERE pp.id = ? AND pp.status = 'approved'";

$stmt = $dbconn->prepare($sql);
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Proposal not found or not approved.";
    header("Location: product_approvals.php");
    exit();
}

$proposal = $result->fetch_assoc();

// Begin transaction
$dbconn->begin_transaction();

try {
    // Insert into products table
    $sql_insert = "INSERT INTO products (
        product_name, 
        item_cost,
        cpp,
        pakej,
        team_id,
        created_at
    ) VALUES (?, ?, ?, ?, ?, CURDATE())";
    
    // Use proposal details to populate product fields
    $product_name = $proposal['product_name'];
    $item_cost = $proposal['cost_price'];
    $cpp = $proposal['selling_price']; // Using selling price as CPP initially
    $pakej = $proposal['category']; // Using category as pakej

    $stmt_insert = $dbconn->prepare($sql_insert);
    $stmt_insert->bind_param(
        "sddsi",
        $product_name,
        $item_cost,
        $cpp,
        $pakej,
        $proposal['team_id']
    );
    
    $stmt_insert->execute();
    $product_id = $dbconn->insert_id;
    
    // Log the product creation
    $log_message = "Product created from proposal #$proposal_id";
    $sql_log = "INSERT INTO activity_log (user_id, action, entity_type, entity_id, message, created_at)
                VALUES (?, 'create', 'product', ?, ?, NOW())";
    
    $stmt_log = $dbconn->prepare($sql_log);
    $stmt_log->bind_param("iis", $user_id, $product_id, $log_message);
    $stmt_log->execute();
    
    // Commit the transaction
    $dbconn->commit();
    
    $_SESSION['success_message'] = "Product has been successfully created from the proposal.";
    header("Location: edit_product.php?id=$product_id");
    exit();
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $dbconn->rollback();
    $_SESSION['error_message'] = "Error creating product: " . $e->getMessage();
    header("Location: product_approvals.php");
    exit();
}
?>