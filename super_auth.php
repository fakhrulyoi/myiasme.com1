<?php
// Include regular auth file
require 'auth.php';

// Include database connection if not already included
if (!isset($dbconn) || $dbconn === null) {
    require 'dbconn_productProfit.php';
}

// Add super admin check
$is_super_admin = false;
if(isset($_SESSION['user_id'])) {
    $super_check = $dbconn->prepare("SELECT role FROM users WHERE id = ?");
    $super_check->bind_param("i", $_SESSION['user_id']);
    $super_check->execute();
    $super_result = $super_check->get_result();
    if($super_result->num_rows > 0) {
        $super_user = $super_result->fetch_assoc();
        if($super_user['role'] == 'super_admin') {
            $is_super_admin = true;
        }
    }
}

// Redirect if not super admin or admin (when needed)
function require_super_admin() {
    global $is_super_admin, $is_admin;
    if(!$is_super_admin && !$is_admin) {
        header("Location: team_products.php");
        exit();
    }
}
?>