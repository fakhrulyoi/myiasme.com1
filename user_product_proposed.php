<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Get current user's team information
$user_id = $_SESSION['user_id'];
$sql_team = "SELECT team_id FROM users WHERE id = ?";
$stmt_team = $dbconn->prepare($sql_team);
$stmt_team->bind_param("i", $user_id);
$stmt_team->execute();
$team_result = $stmt_team->get_result();
$team_data = $team_result->fetch_assoc();
$team_id = $team_data['team_id'];

// Get team name
$sql_team_name = "SELECT team_name FROM teams WHERE team_id = ?";
$stmt_team_name = $dbconn->prepare($sql_team_name);
$stmt_team_name->bind_param("i", $team_id);
$stmt_team_name->execute();
$team_name_result = $stmt_team_name->get_result();
$team_name_data = $team_name_result->fetch_assoc();
$team_name = $team_name_data['team_name'] ?? 'Unassigned Team';

// Ensure username is defined
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';

// Fetch user's proposals
$my_proposals_query = "SELECT 
    pp.*,
    u.username as proposed_by
FROM product_proposals pp
JOIN users u ON pp.user_id = u.id
WHERE pp.user_id = ?
ORDER BY pp.proposed_date DESC";

$stmt_my_proposals = $dbconn->prepare($my_proposals_query);
$stmt_my_proposals->bind_param("i", $user_id);
$stmt_my_proposals->execute();
$my_proposals_result = $stmt_my_proposals->get_result();

$team_proposals_query = "SELECT 
    pp.*,
    u.username as proposed_by,
    t.team_name as proposer_team_name
FROM product_proposals pp
JOIN users u ON pp.user_id = u.id
JOIN teams t ON pp.team_id = t.team_id
WHERE pp.user_id != ?  -- Only exclude the current user's own proposals
ORDER BY pp.proposed_date DESC";

$stmt_team_proposals = $dbconn->prepare($team_proposals_query);
$stmt_team_proposals->bind_param("i", $user_id);
$stmt_team_proposals->execute();
$team_proposals_result = $stmt_team_proposals->get_result();

// Handle file upload
function handleFileUpload($file) {
    $targetDir = "uploads/proposals/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . basename($file["name"]);
    $targetFile = $targetDir . $fileName;
    
    // Check if image file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'File is too large (max 5MB).'];
    }
    
    // Allow certain file formats
    $imageFileType = strtolower(pathinfo($targetFile,PATHINFO_EXTENSION));
    if(!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];
    }
    
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ['success' => true, 'file_path' => $targetFile];
    } else {
        return ['success' => false, 'message' => 'Error uploading file.'];
    }
}

// Initialize messages and form data
$success_message = '';
$error_message = '';
$form_data = [];

// Check for stored form data and error message from session
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

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
    <title>Product Proposals - MYIASME</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        
        .dashboard-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #1E3C72, #2A5298);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-weight: 500;
            font-size: 15px;
        }
        
        .role {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-links li {
            margin: 2px 0;
        }
        
        .nav-links li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-links li a i {
            margin-right: 12px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        .nav-links li:hover a {
            background-color: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        
        .nav-links li.active a {
            background-color: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 20px;
            min-height: 100vh;
            flex: 1;
        }
        
        header {
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            margin: 0;
            color: #1E3C72;
        }
        
        /* Tab styling */
        .tabs {
            display: flex;
            background-color: white;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
            margin-bottom: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-bottom-color: #1E3C72;
            color: #1E3C72;
        }
        
        .tab:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        /* Section styling */
        section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        section h2 {
            color: #1E3C72;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        section h2 i {
            margin-right: 10px;
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border 0.3s;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            border-color: #1E3C72;
            outline: none;
            box-shadow: 0 0 0 2px rgba(30, 60, 114, 0.2);
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        button {
            background-color: #1E3C72;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
        }
        
        button:hover {
            background-color: #2A5298;
        }
        
        button i {
            margin-right: 8px;
        }
        
        /* Table styling */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        thead {
            background-color: #f3f4f6;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            font-weight: 600;
            color: #1E3C72;
        }
        
        tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }
        
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .status-approved {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .status-rejected {
            background-color: #FEE2E2;
            color: #B91C1C;
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
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 100%;
            max-width: 600px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #777;
            padding: 5px;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .modal h3 {
            margin-top: 0;
            color: #1E3C72;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .alert-success {
            background-color: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #10B981;
        }
        
        .alert-danger {
            background-color: #FEE2E2;
            color: #B91C1C;
            border-left: 4px solid #EF4444;
        }
        
        .alert-info {
            background-color: #E0F2FE;
            color: #0369A1;
            border-left: 4px solid #0EA5E9;
        }
        
        .alert-warning {
            background-color: #FEF3C7;
            color: #92400E;
            border-left: 4px solid #F59E0B;
        }
        
        /* Grid layout */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .logo h2, .user-details, .nav-links li a span {
                display: none;
            }
            
            .nav-links li a i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h2>MYIASME</h2>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <span class="role"><?php echo htmlspecialchars($team_name); ?></span>
                </div>
            </div>
            
            <ul class="nav-links">
                <li class="<?php echo isActive('dashboard.php'); ?>">
                    <a href="dashboard.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Team Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo isActive('index.php'); ?>">
                    <a href="index.php">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Product</span>
                    </a>
                </li>
                <li class="active">
                    <a href="product_proposals.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>Product Proposals</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_winning.php'); ?>">
                    <a href="user_winning.php">
                        <i class="fa-solid fa-medal"></i>
                        <span>Winning DNA</span>
                    </a>
                </li>
                <li class="<?php echo isActive('team_products.php'); ?>">
                    <a href="team_products.php">
                        <i class="fas fa-box"></i>
                        <span>Team Products</span>
                    </a>
                </li>
                  <li class="<?php echo isActive('team_products_status.php'); ?>">
                    <a href="team_products_status.php">
                        <i class="fa-solid fa-bell"></i>
                        <span>Status Products</span>
                    </a>
                </li>
                    <li class="<?php echo isActive('domain.php'); ?>">
                    <a href="domain.php">
                        <i class="fas fa-globe"></i>
                        <span>Domain & Projects</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user_commission.php'); ?>">
                    <a href="user_commission.php">
                        <i class="fas fa-calculator"></i>
                        <span>Comission View</span>
                    </a>
                </li>
                    <li class="">
                    <a href="view_stock.php">
                        <i class="fas fa-warehouse"></i>
                        <span>View Stock</span>
                    </a>
                </li>
                <li class="<?php echo isActive('reports.php'); ?>">
                    <a href="reports.php">
                        <i class="fas fa-file-download"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Product Proposals</h1>
                <button id="newProposalBtn"><i class="fas fa-plus"></i> New Proposal</button>
            </header>
            
            <!-- Success/Error Alert -->
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" data-tab="all">All Proposals</div>
                <div class="tab" data-tab="pending">Pending</div>
                <div class="tab" data-tab="approved">Approved</div>
                <div class="tab" data-tab="rejected">Rejected</div>
            </div>
            
            <!-- Main Section -->
            <section>
                <h2><i class="fas fa-lightbulb"></i> My Product Proposals</h2>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Proposed Date</th>
                                <th>Status</th>
                                <th>Admin Feedback</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($my_proposals_result && $my_proposals_result->num_rows > 0): ?>
                                <?php while ($proposal = $my_proposals_result->fetch_assoc()): ?>
                                    <tr data-status="<?php echo htmlspecialchars($proposal['status']); ?>">
                                        <td><?php echo htmlspecialchars($proposal['id']); ?></td>
                                        <td><?php echo htmlspecialchars($proposal['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($proposal['category']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($proposal['proposed_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($proposal['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($proposal['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $proposal['admin_feedback'] ? htmlspecialchars($proposal['admin_feedback']) : '-'; ?></td>
                                        <td>
                                            <button class="view-btn" data-id="<?php echo htmlspecialchars($proposal['id']); ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">You have not submitted any product proposals yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Team Proposals Section -->
            <section>
                <h2><i class="fas fa-users"></i> Team Proposals</h2>
                
                <div class="table-container">
                    <table>
                    <thead>
    <tr>
        <th>ID</th>
        <th>Product Name</th>
        <th>Category</th>
        <th>Proposed By</th>
        <th>Team</th>
        <th>Proposed Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
                        <tbody>
    <?php if ($team_proposals_result && $team_proposals_result->num_rows > 0): ?>
        <?php while ($proposal = $team_proposals_result->fetch_assoc()): ?>
            <tr data-status="<?php echo htmlspecialchars($proposal['status']); ?>">
                <td><?php echo htmlspecialchars($proposal['id']); ?></td>
                <td><?php echo htmlspecialchars($proposal['product_name']); ?></td>
                <td><?php echo htmlspecialchars($proposal['category']); ?></td>
                <td><?php echo htmlspecialchars($proposal['proposed_by']); ?></td>
                <td><?php echo htmlspecialchars($proposal['proposer_team_name']); ?></td>
                <td><?php echo date('Y-m-d', strtotime($proposal['proposed_date'])); ?></td>
                <td>
                    <span class="status-badge status-<?php echo htmlspecialchars($proposal['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($proposal['status'])); ?>
                    </span>
                </td>
                <td>
                    <button class="view-team-btn" data-id="<?php echo htmlspecialchars($proposal['id']); ?>">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="8" style="text-align: center;">No proposals from other teams.</td>
        </tr>
    <?php endif; ?>
</tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
    
    <!-- New Product Proposal Modal -->
    <div class="modal" id="newProposalModal">
        <div class="modal-content">
            <button class="modal-close" id="closeNewProposalModal">&times;</button>
            <h3><i class="fas fa-lightbulb"></i> New Product Proposal</h3>
            
            <form id="proposalForm" method="POST" action="product_proposals.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="submit_proposal">
    
    <div class="form-row">
        <div class="form-group">
            <label for="productName">Product Name*</label>
            <input type="text" id="productName" name="productName" required 
       value="<?php echo htmlspecialchars($form_data['productName'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="category">Category*</label>
            <select id="category" name="category" required>
                <option value="">Select Category</option>
                <option value="Electronics" <?php echo ($_POST['category'] ?? '') === 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                <option value="Fashion" <?php echo ($_POST['category'] ?? '') === 'Fashion' ? 'selected' : ''; ?>>Fashion</option>
                <option value="Home & Kitchen" <?php echo ($_POST['category'] ?? '') === 'Home & Kitchen' ? 'selected' : ''; ?>>Home & Kitchen</option>
                <option value="Beauty & Personal Care" <?php echo ($_POST['category'] ?? '') === 'Beauty & Personal Care' ? 'selected' : ''; ?>>Beauty & Personal Care</option>
                <option value="Health & Fitness" <?php echo ($_POST['category'] ?? '') === 'Health & Fitness' ? 'selected' : ''; ?>>Health & Fitness</option>
                <option value="Food & Beverage" <?php echo ($_POST['category'] ?? '') === 'Food & Beverage' ? 'selected' : ''; ?>>Food & Beverage</option>
                <option value="Office Supplies" <?php echo ($_POST['category'] ?? '') === 'Office Supplies' ? 'selected' : ''; ?>>Office Supplies</option>
                <option value="Mobile Accessories" <?php echo ($_POST['category'] ?? '') === 'Mobile Accessories' ? 'selected' : ''; ?>>Mobile Accessories</option>
                <option value="Lifestyle" <?php echo ($_POST['category'] ?? '') === 'Lifestyle' ? 'selected' : ''; ?>>Lifestyle</option>
                <option value="Other" <?php echo ($_POST['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>
    </div>
    
    <div class="form-row">
        <div class="form-group">
            <label for="costPrice">Estimated Cost Price (RM)*</label>
            <input type="number" id="costPrice" name="costPrice" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['costPrice'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="sellingPrice">Estimated Selling Price (RM)*</label>
            <input type="number" id="sellingPrice" name="sellingPrice" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['sellingPrice'] ?? ''); ?>">
        </div>
    </div>
    
    <div class="form-group">
        <label for="productDescription">Product Description*</label>
        <textarea id="productDescription" name="productDescription" required><?php echo htmlspecialchars($_POST['productDescription'] ?? ''); ?></textarea>
    </div>
    
    <div class="form-group">
        <label for="tiktokLink">TikTok/Product Link*</label>
        <input type="url" id="tiktokLink" name="tiktokLink" required value="<?php echo htmlspecialchars($_POST['tiktokLink'] ?? ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="productImage">Product Image</label>
        <input type="file" id="productImage" name="productImage" accept="image/*">
    </div>
    
    <div class="form-actions" style="margin-top: 20px; display: flex; justify-content: flex-end;">
        <button type="button" id="cancelProposal" style="background-color: #6c757d; margin-right: 10px;">
            <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit">
            <i class="fas fa-paper-plane"></i> Submit Proposal
        </button>
    </div>
</form>
        </div>
    </div>
    
    <!-- View Proposal Modal -->
    <div class="modal" id="viewProposalModal">
        <div class="modal-content">
            <button class="modal-close" id="closeViewProposalModal">&times;</button>
            <h3><i class="fas fa-file-alt"></i> Product Proposal Details</h3>
            
            <div class="proposal-details">
                <!-- Will be populated dynamically with AJAX -->
                <div id="proposalDetailContent">Loading details...</div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // New Proposal Modal
        const newProposalBtn = document.getElementById('newProposalBtn');
        const newProposalModal = document.getElementById('newProposalModal');
        const closeNewProposalModal = document.getElementById('closeNewProposalModal');
        const cancelProposal = document.getElementById('cancelProposal');
        
        newProposalBtn.addEventListener('click', function() {
            newProposalModal.style.display = 'flex';
        });
        
        closeNewProposalModal.addEventListener('click', function() {
            newProposalModal.style.display = 'none';
        });
        
        cancelProposal.addEventListener('click', function() {
            newProposalModal.style.display = 'none';
        });
        
        // View Proposal Modal
        const viewBtns = document.querySelectorAll('.view-btn');
        const viewTeamBtns = document.querySelectorAll('.view-team-btn');
        const viewProposalModal = document.getElementById('viewProposalModal');
        const closeViewProposalModal = document.getElementById('closeViewProposalModal');
        const proposalDetailContent = document.getElementById('proposalDetailContent');
        
        function loadProposalDetails(proposalId) {
            // Show loading state
            proposalDetailContent.innerHTML = '<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #1E3C72;"></i><p style="margin-top: 15px;">Loading proposal details...</p></div>';
            
            // Fetch proposal details via AJAX
            fetch(`get_proposal_details.php?id=${proposalId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    proposalDetailContent.innerHTML = html;
                })
                .catch(error => {
                    proposalDetailContent.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error loading details: ${error.message}</div>`;
                });
        }
        
        viewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-id');
                loadProposalDetails(proposalId);
                viewProposalModal.style.display = 'flex';
            });
        });
        
        viewTeamBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-id');
                loadProposalDetails(proposalId);
                viewProposalModal.style.display = 'flex';
            });
        });
        
        closeViewProposalModal.addEventListener('click', function() {
            viewProposalModal.style.display = 'none';
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === newProposalModal) {
                newProposalModal.style.display = 'none';
            }
            if (event.target === viewProposalModal) {
                viewProposalModal.style.display = 'none';
            }
        });
        
        // Form validation
        const proposalForm = document.getElementById('proposalForm');
        
        proposalForm.addEventListener('submit', function(e) {
            const productName = document.getElementById('productName').value.trim();
            const category = document.getElementById('category').value;
            const costPrice = parseFloat(document.getElementById('costPrice').value);
            const sellingPrice = parseFloat(document.getElementById('sellingPrice').value);
            const productDescription = document.getElementById('productDescription').value.trim();
            const targetMarket = document.getElementById('targetMarket').value.trim();
            
            let isValid = true;
            let errorMessage = '';
            
            if (!productName) {
                errorMessage = 'Product name is required.';
                isValid = false;
            } else if (!category) {
                errorMessage = 'Please select a category.';
                isValid = false;
            } else if (isNaN(costPrice) || costPrice <= 0) {
                errorMessage = 'Please enter a valid cost price.';
                isValid = false;
            } else if (isNaN(sellingPrice) || sellingPrice <= 0) {
                errorMessage = 'Please enter a valid selling price.';
                isValid = false;
            } else if (!productDescription) {
                errorMessage = 'Product description is required.';
                isValid = false;
            } else if (!targetMarket) {
                errorMessage = 'Target market information is required.';
                isValid = false;
            } else if (sellingPrice <= costPrice) {
                errorMessage = 'Selling price should be greater than cost price.';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
            }
        });
        
        // Tab functionality
        const tabs = document.querySelectorAll('.tab');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                tabs.forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Get tab value
                const tabValue = this.getAttribute('data-tab');
                
                // Filter table rows based on tab
                filterTableRows(tabValue);
            });
        });
        
        function filterTableRows(status) {
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if (row.hasAttribute('data-status')) {
                    const rowStatus = row.getAttribute('data-status');
                    
                    if (status === 'all') {
                        row.style.display = '';
                    } else if (rowStatus === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
    });
    </script>
</body>
</html>