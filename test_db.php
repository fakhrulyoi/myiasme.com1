<?php
// Test with your current credentials
$host = "localhost";
$user = "flippecc_myiasme";
$pass = "1evis501lvC";
$db = "flippecc_product_profit";

echo "Testing connection to MySQL server...<br>";
try {
    $conn = mysqli_connect($host, $user, $pass);
    echo "Connected to MySQL server successfully!<br>";
    
    if(mysqli_select_db($conn, $db)) {
        echo "Database '$db' selected successfully!";
    } else {
        echo "Error selecting database: " . mysqli_error($conn);
    }
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>