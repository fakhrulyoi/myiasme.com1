<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if user is logged in and has operations role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operation') {
    header("Location: login.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Operation Staff';

// Check if operation_id is set in the session, default to 1 if not
$operation_id = isset($_SESSION['operation_id']) ? $_SESSION['operation_id'] : 1;

// You may also want to store the operation ID if it's missing
if (!isset($_SESSION['operation_id'])) {
    // You could determine the operation ID based on user_id or role
    // For now, we'll set a default value of 1
    $_SESSION['operation_id'] = $operation_id;
    
    // Optional: Log this issue for administrative review
    error_log("Operation ID was not set for user {$_SESSION['user_id']}. Using default: $operation_id");
}

// Handle the confirm receipt form submission
// The issue is that there's a mismatch between what's stored in the products table (90) 
// and what's being displayed in the UI (-10).

// The problem lies in how the UI queries for the stock data. It's using the stock_orders table's 
// balance_stock field instead of products.stock_quantity. The stock_orders table isn't being 
// properly updated when new stock is confirmed by operations.

// Here's the fix for the operations_stock.php file:

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receipt'])) {
    $entry_id = $_POST['entry_id'];
    $actual_quantity = $_POST['actual_quantity'];
    $defect_quantity = $_POST['defect_quantity'];
    $available_quantity = $actual_quantity - $defect_quantity;
    
    if ($available_quantity < 0) {
        $error_message = "Available quantity cannot be negative!";
    } else {
        // Begin transaction for data consistency
        $dbconn->begin_transaction();
        
        try {
            // 1. Update the stock entry status to Available
            $update_entry = "UPDATE stock_entries SET 
                            status = 'Available', 
                            actual_quantity = ?,
                            defect_quantity = ?,
                            confirmed_by = ?,
                            confirmed_at = NOW() 
                            WHERE id = ?";
            $update_stmt = $dbconn->prepare($update_entry);
            $update_stmt->bind_param("iiii", $actual_quantity, $defect_quantity, $_SESSION['user_id'], $entry_id);
            $update_stmt->execute();
            
            // 2. Get the product_id and team_id from the entry
            $get_entry = "SELECT product_id, team_id FROM stock_entries WHERE id = ?";
            $get_stmt = $dbconn->prepare($get_entry);
            $get_stmt->bind_param("i", $entry_id);
            $get_stmt->execute();
            $entry_result = $get_stmt->get_result();
            $entry_data = $entry_result->fetch_assoc();
            
            $product_id = $entry_data['product_id'];
            $team_id = $entry_data['team_id'];
            
            // 3. Check if the product exists first
            $check_product = "SELECT id, product_name, stock_quantity FROM products WHERE id = ?";
            $check_stmt = $dbconn->prepare($check_product);
            $check_stmt->bind_param("i", $product_id);
            $check_stmt->execute();
            $product_result = $check_stmt->get_result();
            
            if ($product_result->num_rows == 0) {
                // Product doesn't exist - let's create a record
                $product_info = "SELECT product_name FROM stock_entries WHERE id = ? LIMIT 1";
                $product_info_stmt = $dbconn->prepare($product_info);
                $product_info_stmt->bind_param("i", $entry_id);
                $product_info_stmt->execute();
                $product_info_result = $product_info_stmt->get_result();
                $product_name = $product_info_result->fetch_assoc()['product_name'] ?? "Product $product_id";
                
                // Insert a new product record
                $insert_product = "INSERT INTO products (id, product_name, stock_quantity, team_id, status) 
                                VALUES (?, ?, ?, ?, 'active')";
                $insert_stmt = $dbconn->prepare($insert_product);
                $insert_stmt->bind_param("isii", $product_id, $product_name, $available_quantity, $team_id);
                $insert_stmt->execute();
                
                // Log that we created a product
                error_log("Created missing product ID $product_id with $available_quantity units");
            } else {
                // Update existing product
                $product_data = $product_result->fetch_assoc();
                $current_qty = (int)$product_data['stock_quantity'];
                $new_qty = $current_qty + $available_quantity;
                
                // Get the low stock threshold
                $threshold_sql = "SELECT COALESCE(
                                    (SELECT setting_value FROM stock_settings WHERE setting_name = 'low_stock_threshold' AND team_id = ? LIMIT 1),
                                    (SELECT setting_value FROM stock_settings WHERE setting_name = 'low_stock_threshold' AND team_id IS NULL LIMIT 1),
                                    '50'
                                ) as threshold";
                $threshold_stmt = $dbconn->prepare($threshold_sql);
                $threshold_stmt->bind_param("i", $team_id);
                $threshold_stmt->execute();
                $threshold_result = $threshold_stmt->get_result();
                $threshold = (int)$threshold_result->fetch_assoc()['threshold'];
                
                // Determine status
                $status = 'Healthy';
                if ($new_qty <= 0) {
                    $status = 'Out of Stock';
                } else if ($new_qty <= $threshold) {
                    $status = 'Low Stock';
                }
                
                // Update the product record
                $update_product = "UPDATE products SET 
                                  stock_quantity = ?,
                                  stock_status = ?
                                  WHERE id = ?";
                $update_stmt = $dbconn->prepare($update_product);
                $update_stmt->bind_param("isi", $new_qty, $status, $product_id);
                $update_stmt->execute();
                
                // Log the update
                error_log("Updated product ID $product_id from $current_qty to $new_qty units");
            }
            
            // *** FIX: Also update the stock_orders table with the new balance ***
            // This is what's missing and causing the UI display issue
            $today = date('Y-m-d');
            $new_balance_sql = "INSERT INTO stock_orders 
                               (date, order_received, balance_stock, status, team_id, product_id)
                               VALUES (?, ?, ?, ?, ?, ?)";
            $new_balance_stmt = $dbconn->prepare($new_balance_sql);
            
            // Get the current stock_quantity after update
            $updated_stock_query = "SELECT stock_quantity, stock_status FROM products WHERE id = ?";
            $updated_stock_stmt = $dbconn->prepare($updated_stock_query);
            $updated_stock_stmt->bind_param("i", $product_id);
            $updated_stock_stmt->execute();
            $updated_stock_result = $updated_stock_stmt->get_result();
            $updated_stock_data = $updated_stock_result->fetch_assoc();
            
            // Use the confirmed available quantity as the order_received value
            // and the updated stock_quantity from products as the balance_stock
            $order_received = $available_quantity;
            $balance_stock = $updated_stock_data['stock_quantity'];
            $current_status = $updated_stock_data['stock_status'];
            
            $new_balance_stmt->bind_param("siisis", $today, $order_received, $balance_stock, 
                                         $current_status, $team_id, $product_id);
            $new_balance_stmt->execute();
            
            $dbconn->commit();
            $success_message = "Stock receipt confirmed and product stock updated!";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $dbconn->rollback();
            $error_message = "Error processing stock confirmation: " . $e->getMessage();
            error_log($error_message);
        }
    }
}

// Get the teams assigned to this operation
// Get the teams assigned to this operation - more dynamic approach
if ($operation_id == 1) {
    // For operation 1: Get Team A, all Team B variations, and try team
    $teams_query = "SELECT team_id FROM teams WHERE team_id = 1 OR team_name LIKE 'Team B%' OR team_name LIKE 'TEAM B%' OR team_id = 23";
    $teams_result = $dbconn->query($teams_query);
    $operation_teams = [];
    while ($team = $teams_result->fetch_assoc()) {
        $operation_teams[] = $team['team_id'];
    }
} elseif ($operation_id == 2) {
    // For operation 2: Get Team C, Team D, and try team
    $teams_query = "SELECT team_id FROM teams WHERE team_id IN (3, 4) OR team_name LIKE 'TEAM C%' OR team_name LIKE 'TEAM D%' OR team_id = 23";
    $teams_result = $dbconn->query($teams_query);
    $operation_teams = [];
    while ($team = $teams_result->fetch_assoc()) {
        $operation_teams[] = $team['team_id'];
    }
} else {
    // Fallback - get all teams if operation_id is not recognized
    $teams_query = "SELECT team_id FROM teams ORDER BY team_id";
    $teams_result = $dbconn->query($teams_query);
    $operation_teams = [];
    while ($team = $teams_result->fetch_assoc()) {
        $operation_teams[] = $team['team_id'];
    }
}

// Handle team selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_team'])) {
    $_SESSION['operations_selected_team'] = $_POST['selected_team'];
    // Redirect to refresh with new team selection
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get selected team (default to 'all' if not set)
$selected_team = isset($_SESSION['operations_selected_team']) ? $_SESSION['operations_selected_team'] : 'all';

// Make sure the selected team is one of the teams assigned to this operation
if ($selected_team != 'all' && !in_array($selected_team, $operation_teams)) {
    $selected_team = 'all';
    $_SESSION['operations_selected_team'] = 'all';
}

// Get selected team name for display
$team_name = "All Teams";
if ($selected_team != 'all') {
    $team_query = "SELECT team_name FROM teams WHERE team_id = ?";
    $team_stmt = $dbconn->prepare($team_query);
    $team_stmt->bind_param("i", $selected_team);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    if ($team_result && $team_result->num_rows > 0) {
        $team_name = $team_result->fetch_assoc()['team_name'];
    }
    $team_stmt->close();
}

// Convert array to comma-separated string for SQL
$team_ids = implode(',', $operation_teams);

// Build team filter condition
$team_filter = " AND se.team_id IN ($team_ids)";
if ($selected_team != 'all') {
    $team_filter = " AND se.team_id = $selected_team";
}

// Get pending stock entries (status = 'OFD')
$sql_pending = "SELECT 
                se.id, 
                se.date, 
                se.description, 
                se.platform, 
                se.quantity, 
                se.eta, 
                se.remarks,
                p.product_name,
                t.team_name
            FROM stock_entries se
            JOIN products p ON se.product_id = p.id
            JOIN teams t ON se.team_id = t.team_id
            WHERE se.status = 'OFD'
            $team_filter
            ORDER BY se.date DESC";

$pending_result = $dbconn->query($sql_pending);

// Get recently confirmed entries (status = 'Available', last 7 days)
$sql_confirmed = "SELECT 
                se.id, 
                se.date, 
                se.description, 
                se.platform, 
                se.quantity, 
                se.actual_quantity,
                se.defect_quantity,
                se.confirmed_at,
                p.product_name,
                t.team_name
            FROM stock_entries se
            JOIN products p ON se.product_id = p.id
            JOIN teams t ON se.team_id = t.team_id
            WHERE se.status = 'Available' 
            $team_filter
            AND se.confirmed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY se.confirmed_at DESC";

$confirmed_result = $dbconn->query($sql_confirmed);

// Get all teams for the team selector (only the ones assigned to this operation)
$teams_sql = "SELECT team_id, team_name FROM teams WHERE team_id IN ($team_ids) ORDER BY team_name";
$teams_result = $dbconn->query($teams_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Stock Management | Iasme Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --darker: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --gray-light: #94a3b8;
            --gray-lighter: #e2e8f0;
            --transition: all 0.3s ease;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
            line-height: 1.6;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
            position: relative;
        }

        /* Sidebar Styles */
        .sidebar {
            background: var(--darker);
            color: var(--light);
            height: 100vh;
            position: sticky;
            top: 0;
            transition: var(--transition);
            z-index: 100;
            box-shadow: var(--shadow-md);
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .sidebar-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }

        .sidebar-menu a {
            color: var(--gray-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 1.5rem;
            font-size: 0.95rem;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .sidebar-menu li.active a {
            background: rgba(255, 255, 255, 0.05);
            color: var(--light);
            border-left: 3px solid var(--primary);
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--light);
        }

        .sidebar-menu i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            position: relative;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gray-lighter);
        }

        .user-profile span {
            font-weight: 500;
            font-size: 0.95rem;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 2rem;
            border-radius: var(--radius-md);
            color: white;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            font-size: 1.05rem;
            opacity: 0.85;
            max-width: 600px;
        }

        /* Alert styles */
        .alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Dashboard Card Styles */
        .dashboard-card {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 2rem;
            transition: var(--transition);
        }

        .dashboard-card:hover {
            box-shadow: var(--shadow-md);
        }

        .metrics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-lighter);
        }

        .metrics-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .metrics-header h2 i {
            color: var(--primary);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .operations-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .operations-table th {
            padding: 1rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray);
            border-bottom: 2px solid var(--gray-lighter);
            white-space: nowrap;
        }

        .operations-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--gray-lighter);
            vertical-align: middle;
        }

        .operations-table tr:hover td {
            background-color: rgba(79, 70, 229, 0.03);
        }

        .operations-table tr:last-child td {
            border-bottom: none;
        }

        /* Status badges */
        .status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .status.in-progress {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status.completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status.delayed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .status::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status.in-progress::before {
            background: var(--warning);
        }

        .status.completed::before {
            background: var(--success);
        }

        .status.delayed::before {
            background: var(--danger);
        }

        /* Button styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            gap: 8px;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.85rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--gray-lighter);
            color: var(--dark);
        }

        .btn-outline:hover {
            background-color: var(--gray-lighter);
        }

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            text-align: center;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-light);
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Form styles */
        .confirm-form {
            background-color: var(--light);
            padding: 1.25rem;
            border-radius: var(--radius-sm);
            margin-top: 1rem;
            display: none;
            border: 1px solid var(--gray-lighter);
        }

        .grid-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control-sm {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-lighter);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
        }

        .form-control-display {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-lighter);
            border-radius: var(--radius-sm);
            background-color: var(--gray-lighter);
            font-weight: 500;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
.coming-soon-link {
        position: relative;
        cursor: default;
        opacity: 0.8;
    }
    
    .coming-soon-badge {
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        background-color: var(--warning);
        color: white;
        font-size: 0.65rem;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: 600;
        opacity: 0.9;
    }
    
    .sidebar-menu li:hover .coming-soon-badge {
        background-color: white;
        color: var(--warning);
    }
        /* Pending and Confirmed item styling */
        .pending-item {
            border-left: 4px solid var(--warning);
        }

        .confirmed-item {
            border-left: 4px solid var(--success);
        }

        /* Team selector styles */
        .team-selector {
            background-color: white;
            border-radius: var(--radius-md);
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .team-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .team-badge {
            background-color: var(--primary-light);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-lighter);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            background-color: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%230f172a' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(8, 1fr);
            }
        }

        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 0fr 1fr;
            }

            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .dashboard-container.sidebar-visible .sidebar {
                width: 260px;
            }

            .hamburger-menu {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .dashboard-content {
                padding: 1.5rem 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .dashboard-card h2 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="logo igocc.png" alt="Iasme Trading Logo" class="sidebar-logo">
                <h3>IASME GROUP</h3>
            </div>
            
           <div class="sidebar-menu">
    <ul>
        <li>
            <a href="operations_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="active">
            <a href="operations_stock.php">
                <i class="fas fa-boxes"></i>
                <span>Stock Management</span>
            </a>
        </li>
        <li>
            <a href="javascript:void(0);" class="coming-soon-link">
                <i class="fas fa-warehouse"></i>
                <span>Inventory Management</span>
                <span class="coming-soon-badge">Coming Soon</span>
            </a>
        </li>
        <li>
            <a href="javascript:void(0);" class="coming-soon-link">
                <i class="fas fa-truck"></i>
                <span>Logistics</span>
                <span class="coming-soon-badge">Coming Soon</span>
            </a>
        </li>
        <li>
            <a href="javascript:void(0);" class="coming-soon-link">
                <i class="fas fa-clipboard-check"></i>
                <span>Quality Control</span>
                <span class="coming-soon-badge">Coming Soon</span>
            </a>
        </li>
        <li>
            <a href="javascript:void(0);" class="coming-soon-link">
                <i class="fas fa-industry"></i>
                <span>Production Planning</span>
                <span class="coming-soon-badge">Coming Soon</span>
            </a>
        </li>
        <li>
            <a href="javascript:void(0);" class="coming-soon-link">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
                <span class="coming-soon-badge">Coming Soon</span>
            </a>
        </li>
        <li>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="hamburger-menu">
                    <i class="fas fa-bars"></i>
                </div>
                
                <div class="user-area">
                    <div class="user-profile">
                        <img src="logo igocc.png" alt="User Avatar">
                        <span><?php echo htmlspecialchars($username); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-boxes"></i> Stock Management</h1>
                    <p>Confirm incoming stock deliveries and track product availability for your assigned teams.</p>
                </div>

                <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success_message; ?></div>
                </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error_message; ?></div>
                </div>
                <?php endif; ?>

                <!-- Team Selector -->
                <div class="team-selector">
                    <div class="team-header">
                        <h3 class="team-title">
                            <i class="fas fa-users"></i> Team Selection
                        </h3>
                        <?php if ($selected_team != 'all'): ?>
                        <div class="team-badge">
                            <i class="fas fa-user-group"></i> Team: <?php echo htmlspecialchars($team_name); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" action="">
                        <select id="team_selector" name="selected_team" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($selected_team == 'all') ? 'selected' : ''; ?>>All Assigned Teams</option>
                            <?php if ($teams_result && $teams_result->num_rows > 0): ?>
                                <?php while ($team = $teams_result->fetch_assoc()): ?>
                                    <option value="<?php echo $team['team_id']; ?>" <?php echo ($selected_team == $team['team_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['team_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </form>
                </div>

                <!-- Pending Stock Entries -->
                <div class="dashboard-card">
                    <div class="metrics-header">
                        <h2><i class="fas fa-truck-loading"></i> Pending Deliveries</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="operations-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Team</th>
                                    <th>Product</th>
                                    <th>Description</th>
                                    <th>Platform</th>
                                    <th>Quantity</th>
                                    <th>ETA</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                                    <?php while ($entry = $pending_result->fetch_assoc()): ?>
                                        <tr class="pending-item">
                                            <td><?php echo date('M d, Y', strtotime($entry['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($entry['team_name']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['platform']); ?></td>
                                            <td><?php echo $entry['quantity']; ?> units</td>
                                            <td>
                                                <?php if (!empty($entry['eta'])): ?>
                                                    <?php echo date('M d, Y', strtotime($entry['eta'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No ETA</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="showConfirmForm('<?php echo $entry['id']; ?>', '<?php echo $entry['quantity']; ?>')">
                                                    <i class="fas fa-check"></i> Confirm Receipt
                                                </button>
                                                
                                                <div id="confirm-form-<?php echo $entry['id']; ?>" class="confirm-form">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                                        
                                                        <div class="grid-form">
                                                            <div class="form-group">
                                                                <label for="actual_quantity_<?php echo $entry['id']; ?>">Actual Quantity:</label>
                                                                <input type="number" class="form-control-sm" 
                                                                       id="actual_quantity_<?php echo $entry['id']; ?>" 
                                                                       name="actual_quantity" 
                                                                       value="<?php echo $entry['quantity']; ?>" 
                                                                       min="0" required>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label for="defect_quantity_<?php echo $entry['id']; ?>">Defect Quantity:</label>
                                                                <input type="number" class="form-control-sm" 
                                                                       id="defect_quantity_<?php echo $entry['id']; ?>" 
                                                                       name="defect_quantity" 
                                                                       value="0" 
                                                                       min="0" 
                                                                       onchange="updateAvailable('<?php echo $entry['id']; ?>')" 
                                                                       oninput="updateAvailable('<?php echo $entry['id']; ?>')" 
                                                                       required>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Available Quantity:</label>
                                                                <div class="form-control-display" id="available_quantity_<?php echo $entry['id']; ?>">
                                                                    <?php echo $entry['quantity']; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="form-actions">
                                                            <button type="button" class="btn btn-outline" 
                                                                    onclick="hideConfirmForm('<?php echo $entry['id']; ?>')">
                                                                Cancel
                                                            </button>
                                                            <button type="submit" name="confirm_receipt" class="btn btn-primary">
                                                                <i class="fas fa-check-circle"></i> Confirm
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <h4>No pending deliveries</h4>
                                                <p>There are no pending stock deliveries at this time<?php echo ($selected_team != 'all') ? ' for ' . htmlspecialchars($team_name) : ''; ?>. New deliveries will appear here when they are added by the admin team.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recently Confirmed Stock -->
                <div class="dashboard-card">
                    <div class="metrics-header">
                        <h2><i class="fas fa-clipboard-check"></i> Recently Confirmed Stock</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="operations-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Team</th>
                                    <th>Product</th>
                                    <th>Expected Qty</th>
                                    <th>Actual Qty</th>
                                    <th>Defect Qty</th>
                                    <th>Available</th>
                                    <th>Confirmed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($confirmed_result && $confirmed_result->num_rows > 0): ?>
                                    <?php while ($entry = $confirmed_result->fetch_assoc()): ?>
                                        <tr class="confirmed-item">
                                            <td><?php echo date('M d, Y', strtotime($entry['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($entry['team_name']); ?></td>
                                            <td><?php echo htmlspecialchars($entry['product_name']); ?></td>
                                            <td><?php echo $entry['quantity']; ?> units</td>
                                            <td><?php echo $entry['actual_quantity']; ?> units</td>
                                            <td><?php echo $entry['defect_quantity']; ?> units</td>
                                            <td><?php echo ($entry['actual_quantity'] - $entry['defect_quantity']); ?> units</td>
                                            <td><?php echo date('M d, Y H:i', strtotime($entry['confirmed_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <i class="fas fa-history"></i>
                                                <h4>No recent confirmations</h4>
                                                <p>No stock has been confirmed in the last 7 days<?php echo ($selected_team != 'all') ? ' for ' . htmlspecialchars($team_name) : ''; ?>. Confirmed deliveries will appear here.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to show the confirm receipt form
        function showConfirmForm(entryId, quantity) {
            document.getElementById('confirm-form-' + entryId).style.display = 'block';
            document.getElementById('actual_quantity_' + entryId).value = quantity;
            document.getElementById('defect_quantity_' + entryId).value = 0;
            updateAvailable(entryId);
        }
        
        // Function to hide the confirm receipt form
        function hideConfirmForm(entryId) {
            document.getElementById('confirm-form-' + entryId).style.display = 'none';
        }
        
        // Function to update the available quantity
        function updateAvailable(entryId) {
            const actualQty = parseInt(document.getElementById('actual_quantity_' + entryId).value) || 0;
            const defectQty = parseInt(document.getElementById('defect_quantity_' + entryId).value) || 0;
            const availableQty = Math.max(0, actualQty - defectQty);
            
            document.getElementById('available_quantity_' + entryId).textContent = availableQty;
            
            // Add color coding based on available quantity
            const availableEl = document.getElementById('available_quantity_' + entryId);
            
            if (availableQty === 0) {
                availableEl.style.color = '#ef4444'; // Red for zero
            } else if (availableQty < actualQty) {
                availableEl.style.color = '#f59e0b'; // Orange for partial
            } else {
                availableEl.style.color = '#10b981'; // Green for full
            }
        }

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const dashboardContainer = document.querySelector('.dashboard-container');
            
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', function() {
                    dashboardContainer.classList.toggle('sidebar-visible');
                });
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>