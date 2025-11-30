<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if user is admin
if (!$is_admin) {
    header("Location: team_products.php");
    exit();
}

// First, let's check what column exists in the teams table
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}
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

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new team
        if ($_POST['action'] == 'add') {
            $team_name = $_POST['team_name'];
            $team_description = $_POST['team_description'];
            
            $sql = "INSERT INTO teams (team_name, team_description) VALUES (?, ?)";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("ss", $team_name, $team_description);
            $stmt->execute();
            
            // Redirect to refresh the page
            header("Location: teams.php?success=1");
            exit();
        }
        
        // Edit existing team
        elseif ($_POST['action'] == 'edit') {
            $team_id = $_POST['team_id'];
            $team_name = $_POST['team_name'];
            $team_description = $_POST['team_description'];
            
            $sql = "UPDATE teams SET team_name = ?, team_description = ? WHERE $team_pk = ?";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("ssi", $team_name, $team_description, $team_id);
            $stmt->execute();
            
            // Redirect to refresh the page
            header("Location: teams.php?success=2");
            exit();
        }
        
        // Delete team
        elseif ($_POST['action'] == 'delete') {
            $team_id = $_POST['team_id'];
            
            // First, update all products from this team to have NULL team_id
            $sql = "UPDATE products SET team_id = NULL WHERE team_id = ?";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            
            // Then, update all users from this team to have NULL team_id
            $sql = "UPDATE users SET team_id = NULL WHERE team_id = ?";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            
            // Finally, delete the team
            $sql = "DELETE FROM teams WHERE $team_pk = ?";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            
            // Redirect to refresh the page
            header("Location: teams.php?success=3");
            exit();
        }
    }
}

// Get all teams
$sql = "SELECT 
    t.*, 
    COUNT(DISTINCT u.id) as user_count,
    COUNT(DISTINCT p.id) as product_count,
    SUM(p.sales) as total_sales,
    SUM(p.profit) as total_profit
FROM teams t
LEFT JOIN users u ON t.$team_pk = u.team_id
LEFT JOIN products p ON t.$team_pk = p.team_id
GROUP BY t.$team_pk
ORDER BY t.team_name";

$result = $dbconn->query($sql);

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
    <title>Team Management - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            --primary-color: #2c3e50;
            --primary-light: #34495e;
            --secondary-color: #3498db;
            --secondary-light: #5dade2;
            --accent-color: #1abc9c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #ecf0f1;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
            
            /* Colors for stat cards */
            --sales-color: #3498db;
            --profit-color: #1abc9c;
            --orders-color: #9b59b6;
            --units-color: #f39c12;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: var(--dark-text);
        }
        
        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-light));
            color: var(--light-text);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            box-shadow: var(--box-shadow);
            overflow-y: auto;
            transition: var(--transition);
        }
        
        .logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo h2 {
            margin: 0;
            font-size: 26px;
            font-weight: 600;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 26px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        
        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-weight: 500;
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .role {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-links li {
            margin: 5px 10px;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .nav-links li a {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: var(--light-text);
            text-decoration: none;
            transition: var(--transition);
            border-radius: var(--border-radius);
        }
        
        .nav-links li a i {
            margin-right: 12px;
            font-size: 18px;
            width: 22px;
            text-align: center;
            transition: var(--transition);
        }
        
        .nav-links li:hover a {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .nav-links li.active a {
            background-color: var(--secondary-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .nav-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 20px;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.6);
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 25px;
            min-height: 100vh;
            flex: 1;
            transition: var(--transition);
        }
        
        /* Page header */
        .page-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-header h1 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        /* Alert styles */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Tab system */
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
            color: #555;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }

        .tab-button:hover {
            color: var(--secondary-color);
        }

        .tab-button.active {
            color: var(--secondary-color);
            border-bottom-color: var(--secondary-color);
        }

        .tab-content {
            display: none;
            padding: 0;
        }

        .tab-content.active {
            display: block;
        }
        
        /* Form styling */
        .form-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: var(--transition);
        }
        
        .form-container:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-container h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .form-container h3 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .form-group {
            flex: 1 0 300px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-actions {
            margin-top: 20px;
        }
        
        /* Table styles */
        .table-container {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            transition: var(--transition);
        }
        
        .table-container:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .table-header {
            background-color: var(--light-bg);
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-text);
            display: flex;
            align-items: center;
        }
        
        .table-header h3 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover td {
            background-color: rgba(0,0,0,0.01);
        }
        
        /* Button styles */
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            border: none;
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-light);
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 13px;
        }
        
        .action-buttons {
            white-space: nowrap;
        }

        .action-buttons button, 
        .action-buttons .btn {
            margin: 2px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 50%;
            max-width: 600px;
            position: relative;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 15px;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        
        /* Utility classes */
        .text-center {
            text-align: center;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                transform: translateX(0);
            }
            
            .sidebar.expanded {
                width: 280px;
            }
            
            .logo h2, .user-details, .nav-links li a span, .nav-section-title {
                display: none;
            }
            
            .sidebar.expanded .logo h2, 
            .sidebar.expanded .user-details, 
            .sidebar.expanded .nav-links li a span,
            .sidebar.expanded .nav-section-title {
                display: block;
            }
            
            .nav-links li a i {
                margin-right: 0;
            }
            
            .sidebar.expanded .nav-links li a i {
                margin-right: 12px;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .sidebar.expanded + .main-content {
                margin-left: 280px;
                width: calc(100% - 280px);
            }
        }
        
        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .modal-content {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-clinic-medical"></i>
                <h2>MYIASME</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo $username; ?></span>
                    <span class="role"><?php echo $is_admin ? 'Administrator' : 'Team Member'; ?></span>
                </div>
            </div>
            
            <ul class="nav-links">
                <?php if ($is_admin): ?>
                <li class="<?php echo isActive('admin_dashboard.php'); ?>">
                    <a href="admin_dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
               
                <li class="<?php echo isActive('teams.php'); ?>">
                    <a href="teams.php">
                        <i class="fas fa-users"></i>
                        <span>Teams</span>
                    </a>
                </li>
                <li class="<?php echo isActive('all_products.php'); ?>">
                    <a href="all_products.php">
                        <i class="fas fa-boxes"></i>
                        <span>All Products</span>
                    </a>
                </li>
              
                <?php endif; ?>
                
                <div class="nav-section">
                    <p class="nav-section-title">Tools</p>
                    <li class="<?php echo isActive('commission_calculator.php'); ?>">
                        <a href="commission_calculator.php">
                            <i class="fas fa-calculator"></i>
                            <span>Commission Calculator</span>
                        </a>
                    </li>
                                 <li class="">
            <a href="stock_management.php">
                <i class="fas fa-warehouse"></i>
                <span>Stock Management</span>
            </a>
        </li>
                    <li class="<?php echo isActive('admin_reports.php'); ?>">
                        <a href="admin_reports.php">
                            <i class="fas fa-file-download"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                </div>
                
                <div class="nav-section">
                    <p class="nav-section-title">Account</p>
                    
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </div>
            </ul>
        </nav>
        
        <!-- Main Content Container -->
        <main class="main-content" id="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <h1><i class="fas fa-users"></i> Team Management</h1>
            </header>
            
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                $message = "";
                switch ($_GET['success']) {
                    case 1:
                        $message = "Team added successfully.";
                        break;
                    case 2:
                        $message = "Team updated successfully.";
                        break;
                    case 3:
                        $message = "Team deleted successfully.";
                        break;
                }
                echo $message;
                ?>
            </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="custom-tabs">
                <button class="tab-button active" onclick="openTab(event, 'addTeam')">Add New Team</button>
                <button class="tab-button" onclick="openTab(event, 'currentTeams')">Current Teams</button>
            </div>

            <!-- Add Team Tab -->
            <div id="addTeam" class="tab-content active">
                <div class="form-container">
                    <h3><i class="fas fa-plus-circle"></i> Create Team</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="team_name">Team Name</label>
                                <input type="text" id="team_name" name="team_name" required class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="team_description">Description</label>
                                <textarea id="team_description" name="team_description" rows="3" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Team</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Teams Tab -->
            <div id="currentTeams" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-users"></i> Current Teams</h3>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Team Name</th>
                                    <th>Description</th>
                                    <th>Members</th>
                                    <th>Products</th>
                                    <th>Total Sales</th>
                                    <th>Total Profit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($team = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $team[$team_pk]; ?></td>
                                        <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                        <td><?php echo htmlspecialchars($team['team_description'] ?? ''); ?></td>
                                        <td><?php echo $team['user_count']; ?></td>
                                        <td><?php echo $team['product_count']; ?></td>
                                        <td>RM <?php echo number_format($team['total_sales'] ?? 0, 2); ?></td>
                                        <td>RM <?php echo number_format($team['total_profit'] ?? 0, 2); ?></td>
                                        <td class="action-buttons">
                                            <button class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo $team[$team_pk]; ?>, '<?php echo addslashes(htmlspecialchars($team['team_name'])); ?>', '<?php echo addslashes(htmlspecialchars($team['team_description'] ?? '')); ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="team_id" value="<?php echo $team[$team_pk]; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this team? All products and users will be reassigned.');">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No teams found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Edit Team Modal -->
            <div id="editTeamModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3><i class="fas fa-edit"></i> Edit Team</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit_team_id" name="team_id">
                        <div class="form-group">
                            <label for="edit_team_name">Team Name</label>
                            <input type="text" id="edit_team_name" name="team_name" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_team_description">Description</label>
                            <textarea id="edit_team_description" name="team_description" rows="3" class="form-control"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Team</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Tab functionality
    function openTab(evt, tabName) {
        // Hide all tab content
        var tabContents = document.getElementsByClassName("tab-content");
        for (var i = 0; i < tabContents.length; i++) {
            tabContents[i].style.display = "none";
        }
        
        // Remove active class from all tab buttons
        var tabButtons = document.getElementsByClassName("tab-button");
        for (var i = 0; i < tabButtons.length; i++) {
            tabButtons[i].className = tabButtons[i].className.replace(" active", "");
        }
        
        // Show the current tab and add active class to the button
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

    // Modal functionality
    const modal = document.getElementById("editTeamModal");
    const closeBtn = document.getElementsByClassName("close")[0];

    // Close modal when clicking X
    closeBtn.onclick = function() {
        modal.style.display = "none";
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Open edit modal with team data
    function openEditModal(id, name, description) {
        document.getElementById("edit_team_id").value = id;
        document.getElementById("edit_team_name").value = name;
        document.getElementById("edit_team_description").value = description;
        modal.style.display = "block";
    }

    // Set default tab on page load
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['success'])): ?>
            // Show current teams tab if there was a successful action
            document.querySelector('.tab-button:nth-child(2)').click();
        <?php else: ?>
            // Default tab is already set via CSS (.active classes)
        <?php endif; ?>
        
        // Toggle sidebar on mobile
        // Create toggle button
        const toggleSidebarBtn = document.createElement('button');
        toggleSidebarBtn.classList.add('toggle-sidebar-btn');
        toggleSidebarBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleSidebarBtn.style.position = 'fixed';
        toggleSidebarBtn.style.top = '15px';
        toggleSidebarBtn.style.left = '15px';
        toggleSidebarBtn.style.zIndex = '1000';
        toggleSidebarBtn.style.background = 'var(--primary-color)';
        toggleSidebarBtn.style.color = 'white';
        toggleSidebarBtn.style.border = 'none';
        toggleSidebarBtn.style.borderRadius = '5px';
        toggleSidebarBtn.style.width = '40px';
        toggleSidebarBtn.style.height = '40px';
        toggleSidebarBtn.style.display = 'none';
        
        document.body.appendChild(toggleSidebarBtn);
        
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        
        toggleSidebarBtn.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
        });
        
        // Show toggle button on mobile
        function checkMobile() {
            if (window.innerWidth <= 768) {
                toggleSidebarBtn.style.display = 'block';
            } else {
                toggleSidebarBtn.style.display = 'none';
                sidebar.classList.remove('expanded');
            }
        }
        
        window.addEventListener('resize', checkMobile);
        checkMobile(); // Initial check
    });
    </script>
</body>
</html>