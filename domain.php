<?php
require 'auth.php';
require 'dbconn_productProfit.php';
// Start the session to retrieve messages

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

// Display success message if it exists
if (isset($_SESSION['success_message'])) {
    echo "<script>alert('" . $_SESSION['success_message'] . "');</script>";
    unset($_SESSION['success_message']);
}

// Display error message if it exists
if (isset($_SESSION['error_message'])) {
    echo "<script>alert('" . $_SESSION['error_message'] . "');</script>";
    unset($_SESSION['error_message']);
}

// Get search query parameter
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Get filter for domain status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// First, let's check what column exists in the teams table to determine the primary key
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';

// Get team name
$team_name = "Your Team";
if (!$is_admin && isset($team_id)) {
    $sql = "SELECT team_name FROM teams WHERE $team_pk = ?";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($team_row = $result->fetch_assoc()) {
        $team_name = $team_row['team_name'];
    }
}

// Handle domain status update via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'update_domain_status') {
    $domain_id = (int)$_POST['domain_id'];
    $new_status = $_POST['new_status'] === 'on' ? 'ON' : 'OFF';
    
    $update_sql = "UPDATE domain_status SET status = ? WHERE id = ?";
    $update_stmt = $dbconn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $domain_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'new_status' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'error' => $dbconn->error]);
    }
    exit;
}

// Get projects with domain status
$sql_projects = "SELECT 
    ps.id,
    ps.sku,
    ps.product_name,
    ps.domain,
    ps.fb_page,
    ps.bm_acc,
    ds.id as domain_id,
    ds.status as domain_status
FROM project_status ps
LEFT JOIN domain_status ds ON ps.domain = ds.domain_name
WHERE 1=1 ";

// Add team filter if not admin
if (!$is_admin) {
    $sql_projects .= "AND ps.team_id = ? ";
}

// Add search condition if search query is provided
if (!empty($search_query)) {
    $sql_projects .= "AND (ps.sku LIKE ? OR ps.product_name LIKE ? OR ps.domain LIKE ? OR ps.fb_page LIKE ?) ";
}

// Add domain status filter if provided
if ($status_filter !== 'all') {
    if ($status_filter === 'on') {
        $sql_projects .= "AND ds.status = 'ON' ";
    } elseif ($status_filter === 'off') {
        $sql_projects .= "AND ds.status = 'OFF' ";
    } elseif ($status_filter === 'no_domain') {
        $sql_projects .= "AND ds.id IS NULL ";
    }
}

$sql_projects .= "ORDER BY ps.id DESC";

// Prepare statement
$stmt_projects = $dbconn->prepare($sql_projects);

// Binding parameters
$params = [];
$types = "";

// Add team filter parameter if not admin
if (!$is_admin) {
    $params[] = $team_id;
    $types .= "i";
}

// Add search parameters
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Bind parameters if any
if (!empty($params)) {
    $stmt_projects->bind_param($types, ...$params);
}

$stmt_projects->execute();
$projects_result = $stmt_projects->get_result();

// Get domain statistics (filtered by team if not admin)
$stats_sql = "SELECT 
    COUNT(DISTINCT ds.id) as total_domains,
    SUM(CASE WHEN ds.status = 'ON' THEN 1 ELSE 0 END) as active_domains,
    SUM(CASE WHEN ds.status = 'OFF' THEN 1 ELSE 0 END) as inactive_domains
FROM domain_status ds";

if (!$is_admin) {
    $stats_sql .= " INNER JOIN project_status ps ON ds.domain_name = ps.domain WHERE ps.team_id = ?";
}

$stats_stmt = $dbconn->prepare($stats_sql);
if (!$is_admin) {
    $stats_stmt->bind_param("i", $team_id);
}
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Include the navigation component
include 'navigation.php';
?>

<style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --background-light: #f4f6f8;
            --text-dark: #2c3e50;
            --border-radius: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--background-light);
            line-height: 1.6;
            color: var(--text-dark);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background-color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card.total i { color: var(--secondary-color); }
        .stat-card.active i { color: var(--success-color); }
        .stat-card.inactive i { color: var(--accent-color); }

        .stat-card h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 8px 12px;
            padding-right: 30px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 250px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
            border-color: #3498db;
            outline: none;
        }

        .status-filters {
            display: flex;
            gap: 10px;
        }

        .status-filter {
            padding: 8px 16px;
            background-color: #f1f3f5;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .status-filter.active {
            background-color: var(--secondary-color);
            color: white;
        }

        .status-filter:hover {
            background-color: #e9ecef;
        }

        .status-filter.active:hover {
            background-color: #2980b9;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        table thead {
            background-color: #f1f3f5;
        }

        table th, table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f1f3f5;
        }

        .domain-link {
            color: var(--secondary-color);
            text-decoration: none;
        }

        .domain-link:hover {
            text-decoration: underline;
        }

        .status-toggle {
            cursor: pointer;
            padding: 6px 14px;
            border: none;
            border-radius: 20px;
            font-weight: bold;
            color: #fff;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .status-toggle.on {
            background-color: var(--success-color);
        }

        .status-toggle.off {
            background-color: var(--accent-color);
        }

        .status-toggle:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        .no-domain {
            color: #999;
            font-style: italic;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: white;
            gap: 5px;
        }

        .btn-primary {
            background-color: var(--secondary-color);
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #666;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 1.5rem;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .page-header, .filter-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .search-container, .status-filters {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>

    <div class="container">
        <!-- Page Header -->
        <header class="page-header">
            <h1><?php echo $is_admin ? "All Teams Domain & Project Status" : $team_name . " Domain & Project Status"; ?></h1>
            <a href="add_domain.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Project
            </a>
        </header>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <i class="fas fa-globe"></i>
                <h3><?php echo $stats['total_domains']; ?></h3>
                <p>Total Domains</p>
            </div>
            <div class="stat-card active">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $stats['active_domains']; ?></h3>
                <p>Active Domains</p>
            </div>
            <div class="stat-card inactive">
                <i class="fas fa-times-circle"></i>
                <h3><?php echo $stats['inactive_domains']; ?></h3>
                <p>Inactive Domains</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-container">
            <div class="status-filters">
                <a href="?status=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="status-filter <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                   <i class="fas fa-list"></i> All Projects
                </a>
                <a href="?status=on<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="status-filter <?php echo $status_filter === 'on' ? 'active' : ''; ?>">
                   <i class="fas fa-check-circle"></i> Active Domains
                </a>
                <a href="?status=off<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="status-filter <?php echo $status_filter === 'off' ? 'active' : ''; ?>">
                   <i class="fas fa-times-circle"></i> Inactive Domains
                </a>
                <a href="?status=no_domain<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="status-filter <?php echo $status_filter === 'no_domain' ? 'active' : ''; ?>">
                   <i class="fas fa-question-circle"></i> No Domain Status
                </a>
            </div>
            
            <div class="search-container">
                <form method="GET" action="" id="searchForm" style="display: flex; gap: 10px;">
                    <!-- Preserve status filter when searching -->
                    <?php if($status_filter !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <?php endif; ?>
                    
                    <div class="search-box">
                        <input type="text" name="search" id="searchInput" placeholder="Search by SKU, name, domain, or FB page..." 
                            value="<?php echo htmlspecialchars($search_query); ?>">
                        <?php if(!empty($search_query)): ?>
                        <a href="?<?php echo $status_filter !== 'all' ? 'status=' . $status_filter : ''; ?>" 
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #999; text-decoration: none;"
                        title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>

        <!-- Projects & Domains Table -->
        <?php if ($projects_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Domain</th>
                    <th>Domain Status</th>
                    <th>FB Page</th>
                    <th>Business Manager</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($project = $projects_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($project['sku']); ?></td>
                    <td><?php echo htmlspecialchars($project['product_name']); ?></td>
                    <td>
                        <?php if (!empty($project['domain'])): ?>
                            <a href="<?php echo htmlspecialchars($project['domain']); ?>" 
                               target="_blank" class="domain-link">
                                <?php echo htmlspecialchars($project['domain']); ?>
                            </a>
                        <?php else: ?>
                            <span class="no-domain">No domain</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($project['domain_id'])): ?>
                            <button
                                class="status-toggle <?php echo strtolower($project['domain_status']); ?>"
                                onclick="toggleDomainStatus(this, <?php echo $project['domain_id']; ?>)">
                                <?php echo $project['domain_status']; ?>
                            </button>
                        <?php else: ?>
                            <span class="no-domain">No status</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($project['fb_page']); ?></td>
                    <td><?php echo htmlspecialchars($project['bm_acc']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-globe"></i>
            <?php if (!empty($search_query)): ?>
                <h3>No projects found</h3>
                <p>No projects match your search for "<?php echo htmlspecialchars($search_query); ?>"</p>
                <a href="?<?php echo $status_filter !== 'all' ? 'status=' . $status_filter : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-times"></i> Clear Search
                </a>
            <?php elseif ($status_filter !== 'all'): ?>
                <h3>No projects found</h3>
                <p>No projects match the selected status filter</p>
                <a href="?" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Projects
                </a>
            <?php else: ?>
                <h3>No projects available</h3>
                <p>No projects have been added yet</p>
                <a href="add_domain.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Project
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle domain status function
        function toggleDomainStatus(button, domainId) {
            const currentStatus = button.classList.contains('on') ? 'on' : 'off';
            const newStatus = currentStatus === 'on' ? 'off' : 'on';

            // Update UI immediately for better UX
            button.classList.toggle('on');
            button.classList.toggle('off');
            button.textContent = newStatus.toUpperCase();

            // Send AJAX request
            fetch('domain.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_domain_status&domain_id=${domainId}&new_status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Revert UI changes if request failed
                    button.classList.toggle('on');
                    button.classList.toggle('off');
                    button.textContent = currentStatus.toUpperCase();
                    alert('Error updating domain status: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                // Revert UI changes if request failed
                button.classList.toggle('on');
                button.classList.toggle('off');
                button.textContent = currentStatus.toUpperCase();
                alert('Network error: ' + error.message);
            });
        }

        // Add event listener for Enter key on search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('searchForm').submit();
                    }
                });
            }
        });
    </script>