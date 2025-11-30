<?php
/**
 * Authentication and Authorization Handler
 * 
 * This file manages user authentication, session handling,
 * and access control for different user roles.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set global variables for access control
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$team_id = isset($_SESSION['team_id']) ? $_SESSION['team_id'] : null;

// Set role-specific variables
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

// Make sure is_admin is always set
if (!isset($_SESSION['is_admin'])) {
    // If is_admin is not set but role is set to 'admin', use that
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $_SESSION['is_admin'] = true;
    } else {
        // Default to non-admin
        $_SESSION['is_admin'] = false;
    }
}

// Set the is_admin value
$is_admin = $_SESSION['is_admin'];

// Handle operation role
if ($role === 'operation' && !isset($_SESSION['operation_id'])) {
    // Determine operation_id based on user_id
    // Odd-numbered user IDs manage Teams A(1) and B(2), even-numbered user IDs manage Teams C(3) and D(4)
    $_SESSION['operation_id'] = ($user_id % 2 == 1) ? 1 : 2;
}

// Set the operation_id value if applicable
$operation_id = ($role === 'operation') ? $_SESSION['operation_id'] : null;

// Set the teams assigned to this operation (if applicable)
if ($role === 'operation') {
    $operation_teams = [];
    if ($operation_id == 1) {
        $operation_teams = [1, 2]; // Teams A and B
    } elseif ($operation_id == 2) {
        $operation_teams = [3, 4]; // Teams C and D
    }
    $_SESSION['operation_teams'] = $operation_teams;
}

// For team-specific database queries
$team_condition = "";
if (!$is_admin) {
    // Only see products from their team
    $team_condition = " AND team_id = " . ($team_id ?: 'NULL');
}

// Function to check if current page is active
if (!function_exists('isActive')) {
    function isActive($pageName) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        return ($currentPage == $pageName) ? 'active' : '';
    }
}

/**
 * Helper function to check if user has permission for a specific action
 * 
 * @param string $action The action to check permission for
 * @return bool True if user has permission, false otherwise
 */
function hasPermission($action) {
    global $is_admin, $role;
    
    // Admins have all permissions
    if ($is_admin) {
        return true;
    }
    
    // Define permission matrix
    $permissions = [
        'user' => ['view_own_products', 'add_product', 'edit_own_product'],
        'team_lead' => ['view_own_products', 'add_product', 'edit_own_product', 'view_team_reports', 'manage_team'],
        'operation' => ['view_stock', 'confirm_stock', 'view_logistics', 'manage_inventory'],
    ];
    
    // Check if the user's role has the requested permission
    return isset($permissions[$role]) && in_array($action, $permissions[$role]);
}

/**
 * Redirect if user doesn't have required permission
 * 
 * @param string $requiredPermission The permission required to access the page
 */
function requirePermission($requiredPermission) {
    if (!hasPermission($requiredPermission)) {
        // Redirect to appropriate page based on role
        if (isset($_SESSION['role'])) {
            switch ($_SESSION['role']) {
                case 'user':
                case 'team_lead':
                    header("Location: dashboard.php");
                    break;
                case 'operation':
                    header("Location: operations_dashboard.php");
                    break;
                default:
                    header("Location: login.php");
            }
        } else {
            header("Location: login.php");
        }
        exit();
    }
}

/**
 * Database connection function (if not included elsewhere)
 * Can be uncommented and modified if needed
 */
/*
function getDbConnection() {
    static $dbconn = null;
    
    if ($dbconn === null) {
        $host = "localhost";
        $user = "your_db_user";
        $pass = "your_db_password";
        $dbname = "your_db_name";
        
        // Create connection
        $dbconn = new mysqli($host, $user, $pass, $dbname);
        
        // Check connection
        if ($dbconn->connect_error) {
            die("Connection failed: " . $dbconn->connect_error);
        }
        
        // Set charset
        $dbconn->set_charset("utf8mb4");
    }
    
    return $dbconn;
}
*/