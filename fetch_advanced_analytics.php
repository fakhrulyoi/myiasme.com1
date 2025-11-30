<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require 'dbconn_productProfit.php';

// Check if team_id is provided
if (!isset($_GET['team_id']) || empty($_GET['team_id'])) {
    echo json_encode([
        'error' => 'Team ID is required',
        'debug_info' => 'No team_id parameter provided in URL'
    ]);
    exit;
}

$team_id = intval($_GET['team_id']);

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Verify database connection
    if (!$dbconn) {
        throw new Exception("Database connection failed");
    }
    
    // Check if analytics_data table exists
    $check_table_sql = "SHOW TABLES LIKE 'analytics_data'";
    $table_exists = $dbconn->query($check_table_sql);
    
    if ($table_exists && $table_exists->num_rows > 0) {
        // Table exists, proceed with real data query
        $sql = "SELECT 
                    CONCAT('Week ', WEEK(date)) as week_label,
                    SUM(total_sales) as weekly_sales,
                    SUM(ad_spend) as weekly_ad_spend,
                    (SUM(conversions) / SUM(visitors)) * 100 as conversion_rate
                FROM analytics_data
                WHERE team_id = ?
                GROUP BY WEEK(date)
                ORDER BY WEEK(date) DESC
                LIMIT 10";
        
        $stmt = $dbconn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $dbconn->error);
        }
        
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        // Prepare data arrays
        $weeks = [];
        $sales = [];
        $ads_spend = [];
        $conversion_rates = [];
        
        // Process results
        while ($row = $result->fetch_assoc()) {
            $weeks[] = $row['week_label'];
            $sales[] = floatval($row['weekly_sales']);
            $ads_spend[] = floatval($row['weekly_ad_spend']);
            $conversion_rates[] = floatval($row['conversion_rate']);
        }
        
        // If no results, provide sample data
        if (count($weeks) == 0) {
            echo json_encode([
                'debug_info' => 'No data found for team_id: ' . $team_id . '. Using sample data.',
                'weeks' => ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                'sales' => [5000, 5500, 4800, 6200, 7000],
                'ads_spend' => [1000, 1200, 900, 1500, 1800],
                'conversion_rates' => [2.1, 2.5, 1.8, 3.0, 3.2]
            ]);
            exit;
        }
        
        // Return data as JSON
        echo json_encode([
            'weeks' => $weeks,
            'sales' => $sales,
            'ads_spend' => $ads_spend,
            'conversion_rates' => $conversion_rates
        ]);
    } else {
        // Table doesn't exist, return sample data
        echo json_encode([
            'debug_info' => 'Analytics table does not exist. Using sample data.',
            'weeks' => ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
            'sales' => [5000, 5500, 4800, 6200, 7000],
            'ads_spend' => [1000, 1200, 900, 1500, 1800],
            'conversion_rates' => [2.1, 2.5, 1.8, 3.0, 3.2]
        ]);
    }
} catch (Exception $e) {
    // Return error information
    echo json_encode([
        'error' => 'Failed to fetch analytics data',
        'debug_info' => $e->getMessage()
    ]);
}
?>