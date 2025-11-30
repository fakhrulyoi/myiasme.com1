<?php
// File: get_dna_user.php
require 'auth.php';
require 'dbconn_productProfit.php';

// Set the connection variable
if (isset($dbconn) && $dbconn instanceof mysqli) {
    $conn = $dbconn;
} else if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli("localhost", "root", "", "product_profit_db");
    
    // Check connection
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
    }
}

// Check if user is logged in (remove role restriction for viewing)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get product ID from request
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit();
}

// Ensure product_dna table exists
$table_check = $conn->query("SHOW TABLES LIKE 'product_dna'");
if ($table_check && $table_check->num_rows == 0) {
    // Create the table if it doesn't exist
    $create_table_sql = "CREATE TABLE product_dna (
        id INT AUTO_INCREMENT PRIMARY KEY,
        winning_product_id INT NOT NULL,
        suggested_product_name VARCHAR(255) NOT NULL,
        reason TEXT,
        added_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_table_sql);
}

// Query for DNA suggestions
$sql = "SELECT 
    pd.id,
    pd.suggested_product_name,
    pd.reason,
    u.username as added_by_name,
    pd.created_at
FROM 
    product_dna pd
LEFT JOIN 
    users u ON pd.added_by = u.id
WHERE 
    pd.winning_product_id = ?
ORDER BY 
    pd.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Prepare statement failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row;
    }
}

// Return as JSON
header('Content-Type: application/json');
echo json_encode($suggestions);
exit();