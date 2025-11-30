<?php
$user = "root";         // MySQL username
$pass = "";             // MySQL password
$host = "localhost";    // Hostname
$dbname= "product_profit"; // Database name

// Sambungan ke MySQL
$dbconn = mysqli_connect("localhost:3308", "root", "", "product_profit");


// Semak sambungan
if (!$dbconn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
