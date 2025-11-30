<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if user is logged in and has operations role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'operation') {
    header("Location: login.php");
    exit;
}

// Get user info
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Operation Staff';

// Determine operation_id based on user_id if not explicitly set
if (!isset($_SESSION['operation_id'])) {
    // Get operation_id from database if available
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT operation_id FROM users WHERE id = ?";
    $user_stmt = $dbconn->prepare($user_query);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result && $user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $_SESSION['operation_id'] = $user_data['operation_id'] ?? (($user_id % 2 == 1) ? 1 : 2);
    } else {
        $_SESSION['operation_id'] = ($user_id % 2 == 1) ? 1 : 2;
    }
}
$operation_id = $_SESSION['operation_id'];

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

// Convert array to comma-separated string for SQL
$team_ids = implode(',', $operation_teams);

// Get today's date
$today = date('Y-m-d');

// Get pending deliveries (OFD status items with ETA)
$sql_pending = "SELECT 
                se.id, 
                se.date, 
                se.description, 
                se.platform, 
                se.quantity, 
                se.eta, 
                se.product_id,
                p.product_name,
                t.team_name
            FROM stock_entries se
            LEFT JOIN products p ON se.product_id = p.id
            LEFT JOIN teams t ON se.team_id = t.team_id
            WHERE se.status = 'OFD' 
            AND se.team_id IN ($team_ids)
            ORDER BY se.eta ASC";

$pending_result = $dbconn->query($sql_pending);
$pending_deliveries = [];
if ($pending_result) {
    while ($row = $pending_result->fetch_assoc()) {
        $pending_deliveries[] = $row;
    }
}

// Get today's deliveries (ETA = today)
$sql_today = "SELECT 
                COUNT(*) as count, 
                SUM(quantity) as total_units
            FROM stock_entries 
            WHERE status = 'OFD' 
            AND eta = '$today'
            AND team_id IN ($team_ids)";

$today_result = $dbconn->query($sql_today);
$today_data = $today_result ? $today_result->fetch_assoc() : ['count' => 0, 'total_units' => 0];
$today_deliveries = $today_data['count'] ?? 0;
$today_units = $today_data['total_units'] ?: 0;

// Get this week's confirmed deliveries
$sql_confirmed = "SELECT 
                    COUNT(*) as count, 
                    SUM(quantity) as total_units
                FROM stock_entries 
                WHERE status = 'Available' 
                AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND team_id IN ($team_ids)";

$confirmed_result = $dbconn->query($sql_confirmed);
$confirmed_data = $confirmed_result ? $confirmed_result->fetch_assoc() : ['count' => 0, 'total_units' => 0];
$confirmed_deliveries = $confirmed_data['count'] ?? 0;
$confirmed_units = $confirmed_data['total_units'] ?: 0;

// Get defective items this week
$sql_defective = "SELECT 
                    SUM(defect_quantity) as total_defects
                FROM stock_entries 
                WHERE status = 'Available' 
                AND defect_quantity > 0
                AND confirmed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND team_id IN ($team_ids)";

$defective_result = $dbconn->query($sql_defective);
$defective_data = $defective_result ? $defective_result->fetch_assoc() : ['total_defects' => 0];
$defective_units = $defective_data['total_defects'] ?: 0;

// Get inventory totals
$sql_inventory_totals = "SELECT 
                            SUM(stock_quantity) as total_stock_quantity,
                            COUNT(*) as total_products
                        FROM products
                        WHERE team_id IN ($team_ids)";
$inventory_totals_result = $dbconn->query($sql_inventory_totals);
$inventory_totals = $inventory_totals_result ? $inventory_totals_result->fetch_assoc() : ['total_stock_quantity' => 0, 'total_products' => 0];

// Get low stock and out of stock counts
$sql_stock_status = "SELECT 
                        SUM(CASE WHEN stock_status = 'Low Stock' THEN 1 ELSE 0 END) as low_stock_count,
                        SUM(CASE WHEN stock_status = 'Out of Stock' THEN 1 ELSE 0 END) as out_stock_count
                    FROM products
                    WHERE team_id IN ($team_ids)";
$stock_status_result = $dbconn->query($sql_stock_status);
$stock_status = $stock_status_result ? $stock_status_result->fetch_assoc() : ['low_stock_count' => 0, 'out_stock_count' => 0];

// Get team inventory stats
$sql_inventory = "SELECT 
                    t.team_id,
                    t.team_name,
                    COUNT(p.id) as product_count,
                    SUM(COALESCE(p.stock_quantity, 0)) as total_stock,
                    SUM(CASE WHEN p.stock_status = 'Low Stock' THEN 1 ELSE 0 END) as low_stock,
                    SUM(CASE WHEN p.stock_status = 'Out of Stock' THEN 1 ELSE 0 END) as out_of_stock
                FROM teams t
                LEFT JOIN products p ON t.team_id = p.team_id
                WHERE t.team_id IN ($team_ids)
                GROUP BY t.team_id, t.team_name
                ORDER BY t.team_name";

$inventory_result = $dbconn->query($sql_inventory);
$team_inventory = [];
if ($inventory_result) {
    while ($row = $inventory_result->fetch_assoc()) {
        $team_inventory[] = $row;
    }
}

// Calendar Data: Get upcoming deliveries from stock_entries with ETA
$days_to_show = 14; // Show deliveries for the next 14 days

// Get the next 14 days including today
$calendar_dates = [];
for ($i = 0; $i < $days_to_show; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $calendar_dates[$date] = [
        'date' => $date,
        'day' => date('d', strtotime($date)),
        'month' => date('M', strtotime($date)),
        'day_name' => date('D', strtotime($date)),
        'deliveries' => []
    ];
}

// Get upcoming deliveries for the calendar
$sql_calendar = "SELECT 
                    se.eta,
                    se.product_id,
                    se.quantity,
                    p.product_name,
                    t.team_name,
                    se.platform
                FROM stock_entries se
                LEFT JOIN products p ON se.product_id = p.id
                LEFT JOIN teams t ON se.team_id = t.team_id
                WHERE se.status = 'OFD'
                AND se.team_id IN ($team_ids)
                AND se.eta BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days_to_show DAY)
                ORDER BY se.eta, t.team_name";

$calendar_result = $dbconn->query($sql_calendar);
if ($calendar_result) {
    while ($row = $calendar_result->fetch_assoc()) {
        $eta = $row['eta'];
        if (isset($calendar_dates[$eta])) {
            $calendar_dates[$eta]['deliveries'][] = [
                'product_name' => $row['product_name'] ?? 'Unknown Product',
                'team_name' => $row['team_name'] ?? 'Unknown Team',
                'quantity' => $row['quantity'],
                'platform' => $row['platform']
            ];
        }
    }
}

// Get teams for dropdown
$teams_dropdown_sql = "SELECT team_id, team_name FROM teams WHERE team_id IN ($team_ids) ORDER BY team_name";
$teams_dropdown_result = $dbconn->query($teams_dropdown_sql);
$teams_dropdown = [];
if ($teams_dropdown_result) {
    while ($team = $teams_dropdown_result->fetch_assoc()) {
        $teams_dropdown[] = $team;
    }
}

// Get recent activity for the activity feed
$sql_activity = "SELECT 
                    'stock_confirmation' as activity_type,
                    se.id,
                    se.confirmed_at as timestamp,
                    CONCAT(u.username, ' confirmed receipt of ', se.actual_quantity, ' units of \"', COALESCE(p.product_name, 'Unknown Product'), '\" for team ', COALESCE(t.team_name, 'Unknown Team')) as description,
                    u.username as actor,
                    IF(se.defect_quantity > 0, CONCAT(se.defect_quantity, ' defective units reported'), NULL) as note
                FROM stock_entries se
                LEFT JOIN users u ON se.confirmed_by = u.id
                LEFT JOIN products p ON se.product_id = p.id
                LEFT JOIN teams t ON se.team_id = t.team_id
                WHERE se.status = 'Available'
                AND se.team_id IN ($team_ids)
                AND se.confirmed_at IS NOT NULL
                
                UNION
                
                SELECT 
                    'stock_order' as activity_type,
                    so.id,
                    so.created_at as timestamp,
                    CONCAT(so.order_received, ' units of \"', COALESCE(p.product_name, 'Unknown Product'), '\" sold by team ', COALESCE(t.team_name, 'Unknown Team')) as description,
                    'System' as actor,
                    CONCAT('New balance: ', so.balance_stock, ' units (', so.status, ')') as note
                FROM stock_orders so
                LEFT JOIN products p ON so.product_id = p.id
                LEFT JOIN teams t ON so.team_id = t.team_id
                WHERE so.team_id IN ($team_ids)
                
                ORDER BY timestamp DESC
                LIMIT 8";

$activity_result = $dbconn->query($sql_activity);
$activities = [];
if ($activity_result) {
    while ($row = $activity_result->fetch_assoc()) {
        $activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Dashboard | IASME Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3f37c9;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #ff5a5f;
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

        .hamburger-menu {
            display: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: var(--dark);
            margin-right: 1rem;
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .team-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .team-selector label {
            font-weight: 500;
            color: var(--dark);
        }

        .team-selector select {
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-lighter);
            background-color: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }

        .notifications {
            position: relative;
            cursor: pointer;
        }

        .notifications i {
            font-size: 1.2rem;
            color: var(--gray);
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--warning);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
            padding: 2.5rem;
            border-radius: var(--radius-md);
            color: white;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 40%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.6;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 640px;
            line-height: 1.6;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .dashboard-card {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
        }

        .dashboard-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* Metrics Cards */
        .metrics-grid {
            grid-column: span 12;
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

        .metrics-content {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .metric-card {
            position: relative;
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-lighter);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            background: white;
        }

        .metric-card:hover {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 1px var(--primary-light);
        }

        .metric-card .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .metric-card:nth-child(1) .metric-icon {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .metric-card:nth-child(2) .metric-icon {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .metric-card:nth-child(3) .metric-icon {
            background: rgba(247, 37, 133, 0.1);
            color: var(--warning);
        }

        .metric-card:nth-child(4) .metric-icon {
            background: rgba(255, 90, 95, 0.1);
            color: var(--danger);
        }

        .metric-card .metric-icon i {
            font-size: 1.6rem;
        }

        .metric-card .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }

        .metric-card .metric-label {
            font-size: 1rem;
            color: var(--gray);
            font-weight: 500;
        }

        .metric-card .trend {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 20px;
        }

        .trend.positive {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .trend.negative {
            background: rgba(255, 90, 95, 0.1);
            color: var(--danger);
        }

        .trend.neutral {
            background: rgba(100, 116, 139, 0.1);
            color: var(--gray);
        }

        /* Team Inventory Grid */
        .inventory-grid {
            grid-column: span 7;
        }

        .inventory-content {
            padding: 1.5rem;
        }

        /* Activity Feed Grid */
        .activity-feed-grid {
            grid-column: span 5;
        }

        .activity-list {
            padding: 1.5rem;
            max-height: 460px;
            overflow-y: auto;
        }

        .activity-header {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-lighter);
        }

        .activity-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .activity-item {
            display: flex;
            gap: 1rem;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--gray-lighter);
        }

        .activity-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon.stock-confirmation {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .activity-icon.stock-order {
            background: rgba(247, 37, 133, 0.1);
            color: var(--warning);
        }

        .activity-content {
            flex: 1;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.25rem;
        }

        .activity-actor {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--dark);
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .activity-description {
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .activity-note {
            font-size: 0.85rem;
            padding: 5px 10px;
            background: var(--gray-lighter);
            border-radius: 4px;
            color: var(--gray);
            display: inline-block;
        }

        /* Pending Deliveries Section */
        .deliveries-grid {
            grid-column: span 12;
        }

        .table-responsive {
            overflow-x: auto;
            padding: 0 1.5rem 1.5rem;
        }

        .deliveries-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .deliveries-table th {
            padding: 0.85rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-lighter);
            color: var(--gray);
            font-weight: 600;
            white-space: nowrap;
        }

        .deliveries-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--gray-lighter);
        }

        .deliveries-table tr:hover td {
            background-color: rgba(79, 70, 229, 0.03);
        }

        .deliveries-table tr:last-child td {
            border-bottom: none;
        }

        .eta-cell {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .eta-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .eta-today {
            background-color: var(--success);
        }

        .eta-tomorrow {
            background-color: var(--primary);
        }

        .eta-later {
            background-color: var(--gray);
        }

        .eta-past {
            background-color: var(--danger);
        }

        .confirm-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .confirm-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Empty state styles */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            text-align: center;
        }

        .empty-state i {
            font-size: 3.5rem;
            color: var(--gray-lighter);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray);
            max-width: 300px;
            margin: 0 auto;
        }

        /* Calendar Styles */
        .calendar-grid {
            grid-column: span 12;
        }

        .calendar-container {
            padding: 1.5rem;
            position: relative;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .calendar-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }

        .calendar-controls {
            display: flex;
            gap: 0.5rem;
        }

        .calendar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-lighter);
            background: white;
            color: var(--dark);
            cursor: pointer;
            transition: var(--transition);
        }

        .calendar-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .day-label {
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-lighter);
        }

        .calendar-dates {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding-bottom: 1rem;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }

        .date-card {
            flex: 0 0 220px;
            scroll-snap-align: start;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-lighter);
            overflow: hidden;
            transition: var(--transition);
        }

        .date-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }

        .date-header {
            background: var(--primary-light);
            color: white;
            padding: 0.75rem;
            text-align: center;
            position: relative;
        }

        .date-number {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .date-info {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .date-today {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: white;
            color: var(--primary-dark);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .date-content {
            padding: 1rem;
            max-height: 250px;
            overflow-y: auto;
        }

        .date-content::-webkit-scrollbar {
            width: 6px;
        }

        .date-content::-webkit-scrollbar-track {
            background: var(--gray-lighter);
            border-radius: 3px;
        }

        .date-content::-webkit-scrollbar-thumb {
            background: var(--gray-light);
            border-radius: 3px;
        }

        .delivery-item {
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            margin-bottom: 0.75rem;
            background: rgba(67, 97, 238, 0.05);
            border-left: 3px solid var(--primary);
            transition: var(--transition);
        }

        .delivery-item:last-child {
            margin-bottom: 0;
        }

        .delivery-item:hover {
            background: rgba(67, 97, 238, 0.1);
            transform: translateY(-2px);
        }

        .delivery-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            color: var(--dark);
            display: flex;
            justify-content: space-between;
        }

        .delivery-details {
            font-size: 0.85rem;
            color: var(--gray);
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .delivery-team, .delivery-quantity {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .delivery-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .no-deliveries {
            padding: 1.5rem 1rem;
            text-align: center;
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Today indicator */
        .date-today-card .date-header {
            background: var(--success);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(1, 1fr);
            }

            .metrics-grid, .inventory-grid, .activity-feed-grid, .actions-grid, .deliveries-grid, .calendar-grid {
                grid-column: span 1;
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

            .sidebar.visible {
                width: 260px;
            }

            .hamburger-menu {
                display: block;
            }

            .metrics-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-content {
                padding: 1.5rem 1rem;
            }

            .page-header {
                padding: 2rem 1.5rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .metrics-content {
                grid-template-columns: 1fr;
            }

            .team-selector {
                display: none;
            }

            .date-card {
                flex: 0 0 180px;
            }
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
        <li class="active">
            <a href="operations_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
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
                
                <div class="team-selector">
                    <label for="team-filter">Filter by Team:</label>
                    <select id="team-filter">
                        <option value="all">All Teams</option>
                        <?php foreach ($teams_dropdown as $team): ?>
                            <option value="<?php echo $team['team_id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="user-area">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($today_deliveries > 0): ?>
                            <span class="badge"><?php echo $today_deliveries; ?></span>
                        <?php endif; ?>
                    </div>
                    
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
                    <h1><i class="fas fa-cogs"></i> Operations Dashboard</h1>
                    <p>Welcome to the operations control center! Monitor product metrics, manage inventory, and oversee operational activities for your assigned teams. Your operation manages <?php echo count($operation_teams); ?> teams with a total of <?php echo $inventory_totals['total_products']; ?> products.</p>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Performance Metrics -->
                    <div class="dashboard-card metrics-grid">
                        <div class="metrics-header">
                            <h2><i class="fas fa-chart-line"></i> Key Metrics</h2>
                        </div>
                        <div class="metrics-content">
                            <div class="metric-card">
                                <div class="metric-icon">
                                    <i class="fas fa-truck-loading"></i>
                                </div>
                                <div class="trend positive">
                                    <i class="fas fa-arrow-up"></i> Today
                                </div>
                                <div class="metric-value"><?php echo $today_deliveries; ?></div>
                                <div class="metric-label">Deliveries Expected Today</div>
                            </div>
                            
                            <div class="metric-card">
                                <div class="metric-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="trend positive">
                                    <i class="fas fa-arrow-up"></i> This Week
                                </div>
                                <div class="metric-value"><?php echo $confirmed_deliveries; ?></div>
                                <div class="metric-label">Deliveries Confirmed</div>
                            </div>
                            
                            <div class="metric-card">
                                <div class="metric-icon">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <div class="trend neutral">
                                    <i class="fas fa-check"></i> Inventory
                                </div>
                                <div class="metric-value"><?php echo $inventory_totals['total_stock_quantity']; ?></div>
                                <div class="metric-label">Total Units in Stock</div>
                            </div>
                            
                            <div class="metric-card">
                                <div class="metric-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="trend negative">
                                    <i class="fas fa-arrow-down"></i> Alerts
                                </div>
                                <div class="metric-value"><?php echo $stock_status['low_stock_count'] + $stock_status['out_stock_count']; ?></div>
                                <div class="metric-label">Products Need Attention</div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Deliveries Calendar -->
                    <div class="dashboard-card calendar-grid">
                        <div class="metrics-header">
                            <h2><i class="fas fa-calendar-alt"></i> Upcoming Product Deliveries</h2>
                            <a href="operations_stock.php">View All Stock</a>
                        </div>
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h3 class="calendar-title">Showing the next 14 days</h3>
                                <div class="calendar-controls">
                                    <button id="scroll-left" class="calendar-btn"><i class="fas fa-chevron-left"></i></button>
                                    <button id="scroll-right" class="calendar-btn"><i class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>
                            
                            <div class="calendar-dates">
                                <?php foreach ($calendar_dates as $date_info): 
                                    $is_today = ($date_info['date'] === $today);
                                    $has_deliveries = !empty($date_info['deliveries']);
                                    
                                    // Change header color based on deliveries
                                    $header_class = $is_today ? 'date-today-card' : '';
                                    if ($has_deliveries && !$is_today) {
                                        $header_class .= ' date-with-deliveries';
                                    }
                                ?>
                                    <div class="date-card <?php echo $header_class; ?>">
                                        <div class="date-header">
                                            <div class="date-number"><?php echo $date_info['day']; ?></div>
                                            <div class="date-info"><?php echo $date_info['month'] . ' ' . $date_info['day_name']; ?></div>
                                            <?php if ($is_today): ?>
                                                <span class="date-today">TODAY</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="date-content">
                                            <?php if ($has_deliveries): ?>
                                                <?php foreach ($date_info['deliveries'] as $delivery): ?>
                                                    <div class="delivery-item">
                                                        <div class="delivery-title">
                                                            <?php echo htmlspecialchars($delivery['product_name']); ?>
                                                            <span class="delivery-badge"><?php echo htmlspecialchars($delivery['platform']); ?></span>
                                                        </div>
                                                        <div class="delivery-details">
                                                            <div class="delivery-team">
                                                                <i class="fas fa-users"></i> <?php echo htmlspecialchars($delivery['team_name']); ?>
                                                            </div>
                                                            <div class="delivery-quantity">
                                                                <i class="fas fa-box"></i> <?php echo $delivery['quantity']; ?> units
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="no-deliveries">
                                                    No deliveries scheduled
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Deliveries -->
                    <div class="dashboard-card deliveries-grid">
                        <div class="metrics-header">
                            <h2><i class="fas fa-truck-loading"></i> Pending Deliveries</h2>
                            <a href="operations_stock.php" class="view-all">Manage All Stock</a>
                        </div>
                        <div class="table-responsive">
                            <table class="deliveries-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Team</th>
                                        <th>Product</th>
                                        <th>Platform</th>
                                        <th>ETA</th>
                                        <th>Quantity</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($pending_deliveries) > 0): ?>
                                        <?php foreach ($pending_deliveries as $delivery): 
                                            // Determine ETA indicator color
                                            $eta_class = 'eta-later';
                                            $today_date = date('Y-m-d');
                                            $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
                                            
                                            if (empty($delivery['eta'])) {
                                                $eta_display = 'No ETA';
                                            } else {
                                                $eta_display = date('M d, Y', strtotime($delivery['eta']));
                                                
                                                if ($delivery['eta'] == $today_date) {
                                                    $eta_class = 'eta-today';
                                                } elseif ($delivery['eta'] == $tomorrow_date) {
                                                    $eta_class = 'eta-tomorrow';
                                                } elseif ($delivery['eta'] < $today_date) {
                                                    $eta_class = 'eta-past';
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($delivery['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($delivery['team_name'] ?? 'Unknown Team'); ?></td>
                                                <td><?php echo htmlspecialchars($delivery['product_name'] ?? 'Unknown Product'); ?></td>
                                                <td><?php echo htmlspecialchars($delivery['platform']); ?></td>
                                                <td>
                                                    <div class="eta-cell">
                                                        <span class="eta-indicator <?php echo $eta_class; ?>"></span>
                                                        <?php echo $eta_display; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $delivery['quantity']; ?> units</td>
                                                <td>
                                                    <a href="operations_stock.php" class="confirm-btn">
                                                        <i class="fas fa-check"></i> Confirm Receipt
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7">
                                                <div class="empty-state">
                                                    <i class="fas fa-truck"></i>
                                                    <h3>No Pending Deliveries</h3>
                                                    <p>There are no pending deliveries at this time. New deliveries will appear here when added.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Activity Feed -->
                    <div class="dashboard-card activity-feed-grid">
                        <div class="metrics-header">
                            <h2><i class="fas fa-history"></i> Recent Activity</h2>
                        </div>
                        <div class="activity-list">
                            <?php if (count($activities) > 0): ?>
                                <?php foreach ($activities as $activity): 
                                    $icon_class = $activity['activity_type'] == 'stock_confirmation' ? 'stock-confirmation' : 'stock-order';
                                    $icon = $activity['activity_type'] == 'stock_confirmation' ? 'fa-clipboard-check' : 'fa-shopping-cart';
                                    
                                    // Format timestamp for display
                                    $timestamp = strtotime($activity['timestamp']);
                                    $today_start = strtotime('today');
                                    $yesterday_start = strtotime('yesterday');
                                    
                                    if ($timestamp >= $today_start) {
                                        $time_display = 'Today at ' . date('g:i A', $timestamp);
                                    } elseif ($timestamp >= $yesterday_start) {
                                        $time_display = 'Yesterday at ' . date('g:i A', $timestamp);
                                    } else {
                                        $time_display = date('M j at g:i A', $timestamp);
                                    }
                                ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?php echo $icon_class; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-header">
                                                <div class="activity-actor"><?php echo htmlspecialchars($activity['actor']); ?></div>
                                                <div class="activity-time"><?php echo $time_display; ?></div>
                                            </div>
                                            <div class="activity-description">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </div>
                                            <?php if (!empty($activity['note'])): ?>
                                                <div class="activity-note">
                                                    <?php echo htmlspecialchars($activity['note']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <h3>No Recent Activity</h3>
                                    <p>Activity related to stock management will appear here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile sidebar toggle
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const sidebar = document.querySelector('.sidebar');
            
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', function() {
                    sidebar.classList.toggle('visible');
                });
            }
            
            // Team filter functionality
            const teamFilter = document.getElementById('team-filter');
            if (teamFilter) {
                teamFilter.addEventListener('change', function() {
                    const teamId = this.value;
                    // You can implement client-side filtering or redirect to a filtered view
                    // For now, we'll just reload the page with a team parameter
                    if (teamId === 'all') {
                        window.location.href = 'operations_dashboard.php';
                    } else {
                        window.location.href = 'operations_dashboard.php?team_id=' + teamId;
                    }
                });
            }
            
            // Calendar scroll controls
            const calendarDates = document.querySelector('.calendar-dates');
            const scrollLeftBtn = document.getElementById('scroll-left');
            const scrollRightBtn = document.getElementById('scroll-right');
            
            if (calendarDates && scrollLeftBtn && scrollRightBtn) {
                scrollLeftBtn.addEventListener('click', function() {
                    calendarDates.scrollBy({
                        left: -600,
                        behavior: 'smooth'
                    });
                });
                
                scrollRightBtn.addEventListener('click', function() {
                    calendarDates.scrollBy({
                        left: 600,
                        behavior: 'smooth'
                    });
                });
                
                // Scroll to today
                const todayCard = document.querySelector('.date-today-card');
                if (todayCard) {
                    todayCard.scrollIntoView({
                        behavior: 'smooth', 
                        block: 'nearest',
                        inline: 'center'
                    });
                }
            }
            
            // Auto-animate elements for better UX
            const animateElements = document.querySelectorAll('.dashboard-card, .metric-card, .date-card, .activity-item');
            animateElements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.animation = `fadeIn 0.3s ease forwards ${index * 0.05}s`;
            });
        });
    </script>
</body>
</html>