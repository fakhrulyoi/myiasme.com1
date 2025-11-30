<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if user is super admin
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] != true) {
    header("Location: dashboard.php");
    exit();
}

// Set page title
$current_page_title = "Product Approval Management";
// Get username from session or database
$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    $sql_username = "SELECT username FROM users WHERE id = ?";
    $stmt_username = $dbconn->prepare($sql_username);
    $stmt_username->bind_param("i", $user_id);
    $stmt_username->execute();
    $username_result = $stmt_username->get_result();
    $username_data = $username_result->fetch_assoc();
    $username = $username_data['username'] ?? 'User';
}

// Process approval/rejection form submission
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
    $admin_feedback = isset($_POST['admin_feedback']) ? trim($_POST['admin_feedback']) : '';
    
    if ($proposal_id <= 0) {
        $error_message = "Invalid proposal ID.";
    } elseif (empty($admin_feedback)) {
        $error_message = "Admin feedback is required.";
    } else {
        $current_time = date('Y-m-d H:i:s');
        
        if ($_POST['action'] === 'approve') {
            // Approve proposal
            $sql = "UPDATE product_proposals 
                    SET status = 'approved', 
                        admin_feedback = ?, 
                        approved_rejected_date = ?,
                        admin_id = ?
                    WHERE id = ?";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("ssii", $admin_feedback, $current_time, $user_id, $proposal_id);
            
            if ($stmt->execute()) {
                $success_message = "Proposal #$proposal_id has been approved successfully.";
            } else {
                $error_message = "Error approving proposal: " . $dbconn->error;
            }
        } elseif ($_POST['action'] === 'reject') {
            // Reject proposal
            $sql = "UPDATE product_proposals 
                    SET status = 'rejected', 
                        admin_feedback = ?, 
                        approved_rejected_date = ?,
                        admin_id = ?
                    WHERE id = ?";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("ssii", $admin_feedback, $current_time, $user_id, $proposal_id);
            
            if ($stmt->execute()) {
                $success_message = "Proposal #$proposal_id has been rejected.";
            } else {
                $error_message = "Error rejecting proposal: " . $dbconn->error;
            }
        }
    }
}

// Get all product proposals with team and user info
$sql = "SELECT 
    pp.id,
    pp.product_name,
    pp.category,
    t.team_name,
    u.username as proposed_by,
    pp.proposed_date,
    pp.status
FROM product_proposals pp
JOIN teams t ON pp.team_id = t.team_id
JOIN users u ON pp.user_id = u.id
ORDER BY pp.proposed_date DESC";

$result = $dbconn->query($sql);

// Count proposals by status
$sql_counts = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
FROM product_proposals";

$counts_result = $dbconn->query($sql_counts);
$counts = $counts_result->fetch_assoc();

// Get all teams for filter dropdown
$sql_teams = "SELECT team_id, team_name FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($sql_teams);

// Helper function for active menu items
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return ($current_page == $page) ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Approval - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* Modern Dashboard Styles */
    :root {
        --primary: #4361ee;
        --primary-light: #4895ef;
        --primary-dark: #3f37c9;
        --secondary: #f72585;
        --secondary-light: #ff4d6d;
        --success: #2ec4b6;
        --info: #4cc9f0;
        --warning: #fca311;
        --danger: #e63946;
        --dark: #212529;
        --light: #f8f9fa;
        --gray: #6c757d;
        --gray-light: #e9ecef;
        --gray-dark: #495057;
        --border-radius: 0.75rem;
        --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
        --transition: all 0.3s ease;
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
        --header-height: 70px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        background-color: #f5f7fa;
        color: var(--dark);
        line-height: 1.6;
        overflow-x: hidden;
    }

    /* Typography */
    h1, h2, h3, h4, h5, h6 {
        font-weight: 600;
        line-height: 1.3;
        margin-bottom: 0.5rem;
        color: var(--dark);
    }

    p {
        margin-bottom: 1rem;
    }

    a {
        color: var(--primary);
        text-decoration: none;
        transition: var(--transition);
    }

    a:hover {
        color: var(--primary-dark);
    }

    /* Layout */
    .app-container {
        display: flex;
        position: relative;
        min-height: 100vh;
        width: 100%;
    }

    /* Sidebar */
    .sidebar {
        width: var(--sidebar-width);
        background: #ffffff;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        z-index: 1000;
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--gray-light);
    }

    .sidebar-collapsed .sidebar {
        width: var(--sidebar-collapsed-width);
    }

    .logo-container {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid var(--gray-light);
        height: var(--header-height);
    }

    .logo-icon {
        font-size: 1.8rem;
        color: var(--primary);
        margin-right: 0.75rem;
    }

    .logo-text {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
        white-space: nowrap;
        transition: var(--transition);
    }

    .sidebar-collapsed .logo-text {
        opacity: 0;
        width: 0;
        display: none;
    }

    .toggle-sidebar {
        background: none;
        border: none;
        color: var(--gray);
        cursor: pointer;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: auto;
        transition: var(--transition);
    }

    .toggle-sidebar:hover {
        color: var(--dark);
    }

    .sidebar-collapsed .toggle-sidebar i {
        transform: rotate(180deg);
    }

    .user-info {
        display: flex;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-light);
        background-color: rgba(67, 97, 238, 0.05);
        transition: var(--transition);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
        font-size: 1.25rem;
        margin-right: 0.75rem;
        flex-shrink: 0;
    }

    .user-details {
        overflow: hidden;
        transition: var(--transition);
    }

    .user-name {
        font-weight: 600;
        font-size: 0.95rem;
        white-space: nowrap;
        color: var(--dark);
    }

    .user-role {
        font-size: 0.8rem;
        color: var(--gray);
        white-space: nowrap;
    }

    .sidebar-collapsed .user-details {
        opacity: 0;
        width: 0;
        display: none;
    }

    .nav-links {
        list-style: none;
        padding: 1rem 0;
        margin: 0;
        overflow-y: auto;
        flex-grow: 1;
    }

    .nav-category {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--gray);
        font-weight: 600;
        padding: 0.75rem 1.5rem 0.5rem;
        transition: var(--transition);
    }

    .sidebar-collapsed .nav-category {
        opacity: 0;
        height: 0;
        padding: 0;
        overflow: hidden;
    }

    .nav-item {
        margin: 0.25rem 0;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.5rem;
        color: var(--gray-dark);
        border-radius: 0.5rem;
        margin: 0 0.75rem;
        transition: var(--transition);
        position: relative;
    }

    .nav-link:hover {
        background-color: rgba(67, 97, 238, 0.08);
        color: var(--primary);
    }

    .nav-link i {
        font-size: 1.25rem;
        min-width: 1.75rem;
        margin-right: 0.75rem;
        text-align: center;
        transition: var(--transition);
    }

    .nav-link-text {
        transition: var(--transition);
        white-space: nowrap;
    }

    .sidebar-collapsed .nav-link-text {
        opacity: 0;
        width: 0;
        display: none;
    }

    .nav-link.active {
        background-color: var(--primary);
        color: white;
    }

    .nav-link.active:hover {
        background-color: var(--primary-dark);
        color: white;
    }

    .sidebar-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--gray-light);
        display: flex;
        align-items: center;
        transition: var(--transition);
    }

    .sidebar-collapsed .sidebar-footer {
        justify-content: center;
        padding: 1rem 0;
    }

    .sidebar-footer-text {
        font-size: 0.8rem;
        color: var(--gray);
        transition: var(--transition);
    }

    .sidebar-collapsed .sidebar-footer-text {
        opacity: 0;
        width: 0;
        display: none;
    }

    /* Main Content Area */
    .main-content {
        flex-grow: 1;
        margin-left: var(--sidebar-width);
        padding: 1.5rem;
        transition: var(--transition);
    }

    .sidebar-collapsed .main-content {
        margin-left: var(--sidebar-collapsed-width);
    }

    /* Page Header */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        background-color: white;
        padding: 1.25rem 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }

    .page-title {
        margin: 0;
        font-size: 1.5rem;
        color: var(--dark);
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    /* Stats cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background-color: white;
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: var(--box-shadow);
        transition: var(--transition);
    }

    .stat-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transform: translateY(-5px);
    }

    .stat-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        margin-bottom: 15px;
        font-size: 20px;
        color: white;
    }

    .stat-pending {
        background-color: var(--warning);
    }

    .stat-approved {
        background-color: var(--success);
    }

    .stat-rejected {
        background-color: var(--danger);
    }

    .stat-total {
        background-color: var(--info);
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        margin: 5px 0;
    }

    .stat-label {
        color: var(--gray);
        font-size: 14px;
        font-weight: 500;
    }

    /* Card styles */
    .card {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin-bottom: 25px;
        transition: var(--transition);
        overflow: hidden;
        border: none;
    }

    .card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-header h2, .card-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
    }

    .card-header i {
        margin-right: 10px;
        color: var(--primary);
    }

    .card-body {
        padding: 25px;
    }

    /* Table styles */
    .table-container {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th, .table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }

    .table th {
        background-color: var(--light);
        font-weight: 600;
        color: var(--dark);
    }

    .table tr:last-child td {
        border-bottom: none;
    }

    .table tr:hover td {
        background-color: rgba(0,0,0,0.01);
    }

    /* Status badges */
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-pending {
        background-color: rgba(252, 163, 17, 0.2);
        color: var(--warning);
    }

    .status-approved {
        background-color: rgba(46, 196, 182, 0.2);
        color: var(--success);
    }

    .status-rejected {
        background-color: rgba(230, 57, 70, 0.2);
        color: var(--danger);
    }

    /* Button styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        border: none;
        font-size: 0.9rem;
    }

    .btn i {
        margin-right: 6px;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
    }

    .btn-success {
        background-color: var(--success);
        color: white;
    }

    .btn-success:hover {
        background-color: #28b5a7;
    }

    .btn-danger {
        background-color: var(--danger);
        color: white;
    }

    .btn-danger:hover {
        background-color: #d32f2f;
    }

    .btn-secondary {
        background-color: var(--gray);
        color: white;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .btn-info {
        background-color: var(--info);
        color: white;
    }

    .btn-info:hover {
        background-color: #138496;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    /* Tabs */
    .custom-tabs {
        display: flex;
        border-bottom: 1px solid #ddd;
        margin-bottom: 20px;
    }

    .tab-button {
        background-color: transparent;
        border: none;
        outline: none;
        cursor: pointer;
        padding: 12px 20px;
        margin-right: 5px;
        font-size: 15px;
        font-weight: 500;
        color: var(--gray);
        border-bottom: 3px solid transparent;
        transition: var(--transition);
    }

    .tab-button:hover {
        color: var(--primary);
    }

    .tab-button.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    /* Alert styles */
    .alert {
        padding: 15px 20px;
        border-radius: var(--border-radius);
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        border-left: 4px solid transparent;
    }

    .alert i {
        margin-right: 10px;
        font-size: 18px;
    }

    .alert-success {
        background-color: rgba(46, 196, 182, 0.1);
        color: var(--success);
        border-left-color: var(--success);
    }

    .alert-danger {
        background-color: rgba(230, 57, 70, 0.1);
        color: var(--danger);
        border-left-color: var(--danger);
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        overflow-y: auto;
    }

    .modal-content {
        background-color: white;
        border-radius: var(--border-radius);
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 0;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        position: relative;
    }

    .modal-close {
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        font-size: 22px;
        cursor: pointer;
        color: white;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        transition: all 0.2s;
        z-index: 1;
    }

    .modal-close:hover {
        background-color: rgba(255,255,255,0.2);
    }

    .modal-header {
        padding: 20px 25px;
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }

    .modal-header h3 {
        margin: 0;
        color: white;
        font-size: 20px;
        font-weight: 600;
    }

    .modal-body {
        padding: 25px;
        margin-bottom: 0;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        padding: 15px 25px;
        border-top: 1px solid rgba(0,0,0,0.05);
        gap: 10px;
    }

    /* Form styles */
    .form-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--dark);
    }

    input[type="text"],
    input[type="email"],
    input[type="number"],
    select,
    textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--gray-light);
        border-radius: 0.5rem;
        font-size: 14px;
        transition: var(--transition);
    }

    input:focus,
    select:focus,
    textarea:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.25);
    }

    textarea {
        min-height: 100px;
        resize: vertical;
    }

    /* Animations */
    .fade-in {
        animation: fadeIn 0.5s ease forwards;
        opacity: 0;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .sidebar {
            width: 70px;
        }

        .main-content {
            margin-left: 70px;
        }

        .sidebar.expanded {
            width: 280px;
        }

        .sidebar.expanded + .main-content {
            margin-left: 280px;
        }
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 999;
    }

    .sidebar-open .sidebar-overlay {
        display: block;
    }

    @media (max-width: 576px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }

        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }

        .sidebar-open .sidebar {
            transform: translateX(0);
        }

        .toggle-sidebar-mobile {
            display: block;
        }
    }

    .toggle-sidebar-mobile {
        display: none;
        background: none;
        border: none;
        color: var(--dark);
        font-size: 1.25rem;
        margin-right: 1rem;
        cursor: pointer;
    }

    @media (max-width: 576px) {
        .toggle-sidebar-mobile {
            display: block;
        }
    }
    </style>
</head>
<body>
    <div id="app" class="app-container">
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="logo-text">MYIASME</div>
                <button class="toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role">Super Admin</div>
                </div>
            </div>
            
            <ul class="nav-links">
                <li class="nav-category">Management</li>
                
                <li class="nav-item">
                    <a href="super_dashboard.php" class="nav-link <?php echo isActive('super_dashboard.php'); ?>">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-link-text">Executive Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="teams_superadmin.php" class="nav-link <?php echo isActive('teams_superadmin.php'); ?>">
                        <i class="fas fa-users"></i>
                        <span class="nav-link-text">Teams</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="winning_dna.php" class="nav-link <?php echo isActive('winning_dna.php'); ?>">
                        <i class="fa-solid fa-medal"></i>
                        <span class="nav-link-text">Winning DNA</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="product_approvals.php" class="nav-link <?php echo isActive('product_approvals.php'); ?>">
                        <i class="fas fa-check-circle"></i>
                        <span class="nav-link-text">Product Approvals</span>
                    </a>
                </li>
                
              
                
                
                
             
                
               
                
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="nav-link-text">Logout</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <span class="sidebar-footer-text">MYIASME &copy; <?php echo date('Y'); ?></span>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="main-content fade-in">
            <!-- Page Header -->
            <header class="page-header">
                <button class="toggle-sidebar-mobile" id="toggleSidebarMobile">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h1 class="page-title">
                    <i class="fas fa-check-circle"></i> Product Approval Management
                </h1>
                
                <div class="header-actions">
                    <button class="btn btn-light" id="refreshDataBtn">
                        <i class="fas fa-redo-alt"></i> Refresh Data
                    </button>
                </div>
            </header>
            
            <!-- Success/Error Alert -->
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Stats Section -->
            <div class="stats-grid fade-in" style="animation-delay: 0.1s;">
                <div class="stat-card">
                    <div class="stat-icon stat-pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo $counts['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $counts['approved'] ?? 0; ?></div>
                    <div class="stat-label">Approved Products</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $counts['rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected Products</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-total">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-value"><?php echo $counts['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Proposals</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="custom-tabs fade-in" style="animation-delay: 0.2s;">
                <button class="tab-button active" data-tab="allProposals">All Proposals</button>
                <button class="tab-button" data-tab="pendingProposals">Pending <span class="counter">(<?php echo $counts['pending'] ?? 0; ?>)</span></button>
                <button class="tab-button" data-tab="approvedProposals">Approved <span class="counter">(<?php echo $counts['approved'] ?? 0; ?>)</span></button>
                <button class="tab-button" data-tab="rejectedProposals">Rejected <span class="counter">(<?php echo $counts['rejected'] ?? 0; ?>)</span></button>
            </div>
            
            <!-- Proposals Table Card -->
            <div class="card fade-in" style="animation-delay: 0.3s;">
                <div class="card-header">
                    <h2><i class="fas fa-lightbulb"></i> Product Proposals</h2>
                    
                    <div class="card-actions">
                        <input type="search" placeholder="Search proposals..." id="searchProposals" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #ddd; margin-right: 10px;">
                        
                        <select id="teamFilter" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="">All Teams</option>
                            <?php if ($teams_result && $teams_result->num_rows > 0): ?>
                                <?php while ($team = $teams_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($team['team_name']); ?>">
                                        <?php echo htmlspecialchars($team['team_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Team</th>
                                    <th>Proposed By</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($proposal = $result->fetch_assoc()): ?>
                                        <tr data-status="<?php echo htmlspecialchars($proposal['status']); ?>">
                                            <td><?php echo htmlspecialchars($proposal['id']); ?></td>
                                            <td><?php echo htmlspecialchars($proposal['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($proposal['category']); ?></td>
                                            <td><?php echo htmlspecialchars($proposal['team_name']); ?></td>
                                            <td><?php echo htmlspecialchars($proposal['proposed_by']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($proposal['proposed_date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($proposal['status']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($proposal['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-info btn-sm view-btn" data-id="<?php echo htmlspecialchars($proposal['id']); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($proposal['status'] === 'pending'): ?>
                                                        <button class="btn btn-success btn-sm approve-btn" data-id="<?php echo htmlspecialchars($proposal['id']); ?>">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm reject-btn" data-id="<?php echo htmlspecialchars($proposal['id']); ?>">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No product proposals found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer style="text-align: center; padding: 1.5rem 0; color: var(--gray); margin-top: 1.5rem; font-size: 0.875rem;">
                <p>MYIASME &copy; <?php echo date('Y'); ?>. All rights reserved.</p>
            </footer>
        </main>
    </div>
    
    <!-- View Proposal Modal -->
    <div class="modal" id="viewProposalModal">
        <div class="modal-content">
            <button class="modal-close" id="closeViewModal">&times;</button>
            
            <div class="modal-header">
                <h3><i class="fas fa-lightbulb"></i> Product Proposal Details</h3>
            </div>
            
            <div class="modal-body">
                <!-- Proposal details will be loaded here -->
                <div id="proposalDetailContent">Loading details...</div>
            </div>
            
            <div class="modal-footer" id="modalActions">
                <!-- Action buttons will be dynamically added based on proposal status -->
            </div>
        </div>
    </div>
    
    <!-- Approval Form Modal -->
    <div class="modal" id="approvalModal">
        <div class="modal-content">
            <button class="modal-close" id="closeApprovalModal">&times;</button>
            
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Approve Product Proposal</h3>
            </div>
            
            <div class="modal-body">
                <form id="approvalForm" method="POST" action="product_approvals.php">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="proposal_id" id="approval_proposal_id">
                    
                    <div class="form-group">
                        <label for="admin_feedback">Feedback for Team (Required)</label>
                        <textarea id="admin_feedback" name="admin_feedback" required placeholder="Provide feedback about why this proposal is being approved..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="create_product" name="create_product" value="1">
                            Also create this product in the system
                        </label>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelApproval">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve Proposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Rejection Form Modal -->
    <div class="modal" id="rejectionModal">
        <div class="modal-content">
            <button class="modal-close" id="closeRejectionModal">&times;</button>
            
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Reject Product Proposal</h3>
            </div>
            
            <div class="modal-body">
                <form id="rejectionForm" method="POST" action="product_approvals.php">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="proposal_id" id="rejection_proposal_id">
                    
                    <div class="form-group">
                        <label for="admin_feedback">Rejection Reason (Required)</label>
                        <textarea id="rejection_feedback" name="admin_feedback" required placeholder="Provide feedback about why this proposal is being rejected..."></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelRejection">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Proposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM Elements
        const app = document.getElementById('app');
        const sidebar = document.getElementById('sidebar');
        const toggleSidebar = document.getElementById('toggleSidebar');
        const toggleSidebarMobile = document.getElementById('toggleSidebarMobile');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        // Toggle Sidebar
        function toggleSidebarFunc() {
            app.classList.toggle('sidebar-collapsed');
        }

        toggleSidebar.addEventListener('click', toggleSidebarFunc);

        // Mobile Sidebar Toggle
        function toggleSidebarMobileFunc() {
            app.classList.toggle('sidebar-open');
        }

        if (toggleSidebarMobile) {
            toggleSidebarMobile.addEventListener('click', toggleSidebarMobileFunc);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebarMobileFunc);
        }

        // Check mobile display initially
        function checkMobileDisplay() {
            if (window.innerWidth < 992) {
                app.classList.add('sidebar-collapsed');
            } else {
                app.classList.remove('sidebar-collapsed');
            }
        }

        checkMobileDisplay();
        window.addEventListener('resize', checkMobileDisplay);
        
        // Hide alerts after 5 seconds
        setTimeout(function() {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
        
        // Tab functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                tabButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Get tab ID
                const tabId = this.getAttribute('data-tab');
                
                // Filter table rows
                filterTableRows(tabId);
            });
        });
        
        function filterTableRows(tabId) {
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if (tabId === 'allProposals') {
                    row.style.display = '';
                } else {
                    const status = row.getAttribute('data-status');
                    
                    if (tabId === 'pendingProposals' && status === 'pending') {
                        row.style.display = '';
                    } else if (tabId === 'approvedProposals' && status === 'approved') {
                        row.style.display = '';
                    } else if (tabId === 'rejectedProposals' && status === 'rejected') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchProposals');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const productName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const category = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const team = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                const proposedBy = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                
                if (productName.includes(searchTerm) || 
                    category.includes(searchTerm) || 
                    team.includes(searchTerm) || 
                    proposedBy.includes(searchTerm)) {
                    row.style.display = row.style.display === 'none' ? 'none' : '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Team filter
        const teamFilter = document.getElementById('teamFilter');
        
        teamFilter.addEventListener('change', function() {
            const selectedTeam = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const team = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                
                if (!selectedTeam || team === selectedTeam) {
                    row.style.display = row.style.display === 'none' ? 'none' : '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // View proposal details
        const viewButtons = document.querySelectorAll('.view-btn');
        const viewModal = document.getElementById('viewProposalModal');
        const closeViewBtn = document.getElementById('closeViewModal');
        const proposalDetailContent = document.getElementById('proposalDetailContent');
        const modalActions = document.getElementById('modalActions');
        
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-id');
                const row = this.closest('tr');
                const status = row.getAttribute('data-status');
                
                // Show loading state
                proposalDetailContent.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #1E3C72;"></i><p style="margin-top: 15px;">Loading proposal details...</p></div>';
                
                // Clear modal actions
                modalActions.innerHTML = '';
                
                // Fetch proposal details via AJAX
                fetch(`get_admin_proposal_details.php?id=${proposalId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(html => {
                        proposalDetailContent.innerHTML = html;
                        
                        // Add appropriate action buttons based on status
                        if (status === 'pending') {
                            modalActions.innerHTML = `
                                <button class="btn btn-danger" onclick="openRejectionModal(${proposalId})">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <button class="btn btn-success" onclick="openApprovalModal(${proposalId})">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            `;
                        } else {
                            modalActions.innerHTML = `
                                <button class="btn btn-primary" onclick="closeViewModal()">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            `;
                        }
                    })
                    .catch(error => {
                        proposalDetailContent.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error loading details: ${error.message}</div>`;
                    });
                
                viewModal.style.display = 'flex';
            });
        });
        
        closeViewBtn.addEventListener('click', function() {
            viewModal.style.display = 'none';
        });
        
        // Make closeViewModal function globally available
        window.closeViewModal = function() {
            viewModal.style.display = 'none';
        };
        
        // Approval modal
        const approvalModal = document.getElementById('approvalModal');
        const closeApprovalModal = document.getElementById('closeApprovalModal');
        const cancelApproval = document.getElementById('cancelApproval');
        const approvalForm = document.getElementById('approvalForm');
        const approvalProposalId = document.getElementById('approval_proposal_id');
        
        // Function to open approval modal
        window.openApprovalModal = function(proposalId) {
            approvalProposalId.value = proposalId;
            viewModal.style.display = 'none';
            approvalModal.style.display = 'flex';
        };
        
        closeApprovalModal.addEventListener('click', function() {
            approvalModal.style.display = 'none';
        });
        
        cancelApproval.addEventListener('click', function() {
            approvalModal.style.display = 'none';
            viewModal.style.display = 'flex';
        });
        
        // Validate approval form before submission
        approvalForm.addEventListener('submit', function(e) {
            const feedback = document.getElementById('admin_feedback').value.trim();
            
            if (!feedback) {
                e.preventDefault();
                alert('Please provide feedback before approving the proposal.');
            }
        });
        
        // Rejection modal
        const rejectionModal = document.getElementById('rejectionModal');
        const closeRejectionModal = document.getElementById('closeRejectionModal');
        const cancelRejection = document.getElementById('cancelRejection');
        const rejectionForm = document.getElementById('rejectionForm');
        const rejectionProposalId = document.getElementById('rejection_proposal_id');
        
        // Function to open rejection modal
        window.openRejectionModal = function(proposalId) {
            rejectionProposalId.value = proposalId;
            viewModal.style.display = 'none';
            rejectionModal.style.display = 'flex';
        };
        
        closeRejectionModal.addEventListener('click', function() {
            rejectionModal.style.display = 'none';
        });
        
        cancelRejection.addEventListener('click', function() {
            rejectionModal.style.display = 'none';
            viewModal.style.display = 'flex';
        });
        
        // Validate rejection form before submission
        rejectionForm.addEventListener('submit', function(e) {
            const feedback = document.getElementById('rejection_feedback').value.trim();
            
            if (!feedback) {
                e.preventDefault();
                alert('Please provide a reason for rejecting the proposal.');
            }
        });
        
        // Quick approve/reject buttons
        const approveButtons = document.querySelectorAll('.approve-btn');
        const rejectButtons = document.querySelectorAll('.reject-btn');
        
        approveButtons.forEach(button => {
            button.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-id');
                openApprovalModal(proposalId);
            });
        });
        
        rejectButtons.forEach(button => {
            button.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-id');
                openRejectionModal(proposalId);
            });
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === viewModal) {
                viewModal.style.display = 'none';
            }
            if (event.target === approvalModal) {
                approvalModal.style.display = 'none';
            }
            if (event.target === rejectionModal) {
                rejectionModal.style.display = 'none';
            }
        });
        
        // Refresh data button
        document.getElementById('refreshDataBtn').addEventListener('click', function() {
            window.location.reload();
        });
    });
    </script>
</body>
</html>