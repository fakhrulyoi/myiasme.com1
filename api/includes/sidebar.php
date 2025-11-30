<div class="sidebar">
    <div class="sidebar-header">
        <h3>Dr Ecomm</h3>
    </div>
    
    <div class="user-info">
        <img src="assets/images/avatar.png" alt="User Avatar" class="user-avatar">
        <div class="user-details">
            <span class="user-name"><?php echo $_SESSION['username']; ?></span>
            <span class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?></span>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <ul>
            <li class="<?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                <a href="admin_dashboard.php">
                    <i class="fa fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="<?php echo $current_page == 'teams' ? 'active' : ''; ?>">
                <a href="teams.php">
                    <i class="fa fa-users"></i>
                    <span>Teams</span>
                </a>
            </li>
            
            <li class="<?php echo $current_page == 'products' ? 'active' : ''; ?>">
                <a href="all_products.php">
                    <i class="fa fa-box"></i>
                    <span>All Products</span>
                </a>
            </li>
            
            <li class="<?php echo $current_page == 'commission' ? 'active' : ''; ?>">
                <a href="commission_calculator.php">
                    <i class="fa fa-calculator"></i>
                    <span>Commission Calculator</span>
                </a>
            </li>
            
            <li class="<?php echo $current_page == 'reports' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <i class="fa fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <!-- New Winning DNA Menu Item -->
            <li class="<?php echo $current_page == 'winning_dna' ? 'active' : ''; ?>">
                <a href="winning_dna.php">
                    <i class="fa fa-dna"></i>
                    <span>Winning DNA</span>
                    <!-- Add "New" badge -->
                    <span class="badge badge-primary badge-pill ml-2">New</span>
                </a>
            </li>
            
            <li>
                <a href="logout.php">
                    <i class="fa fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>