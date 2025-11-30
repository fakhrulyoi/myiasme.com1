<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Include database connection
require_once 'dbconn_productProfit.php';

// Get team ID from request
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Validate team ID
if ($team_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid team ID']);
    exit;
}

// Check for latest commission data
try {
    // Build query to get the latest commission data
    $query = "SELECT 
                MAX(created_at) as latest_date,
                COUNT(*) as total_count
            FROM financial_report 
            WHERE team_id = ?";
    
    $stmt = $dbconn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $dbconn->error);
    }
    
    $stmt->bind_param("i", $team_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    $data = $result->fetch_assoc();
    $stmt->close();
    
    // Get the latest date from session if it exists
    $session_latest_date = isset($_SESSION['latest_commission_date']) ? $_SESSION['latest_commission_date'] : null;
    $session_total_count = isset($_SESSION['commission_total_count']) ? $_SESSION['commission_total_count'] : 0;
    
    // Check if there's new data
    $new_data = false;
    if ($data['latest_date'] && (
        $session_latest_date === null || 
        $data['latest_date'] > $session_latest_date ||
        $data['total_count'] > $session_total_count
    )) {
        $new_data = true;
        // Update session with latest date
        $_SESSION['latest_commission_date'] = $data['latest_date'];
        $_SESSION['commission_total_count'] = $data['total_count'];
    }
    
    // Return the result
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'new_data' => $new_data,
        'latest_date' => $data['latest_date'],
        'total_count' => $data['total_count']
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_latest_commission.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error checking for new commission data: ' . $e->getMessage()
    ]);
}
?>