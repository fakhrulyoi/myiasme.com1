<?php
/**
 * Report Functions Library
 * Contains common functions for data retrieval and reports generation
 * Fixed version with improved error handling, input validation, and division by zero protection
 */

// Enable error reporting in development only
// Comment these lines in production
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

/**
 * Connect to the database
 * @return mysqli|false Database connection or false on failure
 */
function connectToDb() {
    global $dbconn;
    
    try {
        if (!isset($dbconn) || !$dbconn) {
            require_once 'dbconn_productProfit.php';
            
            if (!$dbconn) {
                throw new Exception("Failed to establish database connection");
            }
        }
        
        return $dbconn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate date in YYYY-MM-DD format
 * @param string $date Date to validate
 * @return bool True if valid, false otherwise
 */
function validateDate($date) {
    if (empty($date)) return false;
    
    // Check format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    
    // Check if it's a valid date
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate month in YYYY-MM format
 * @param string $month Month to validate
 * @return bool True if valid, false otherwise
 */
function validateMonth($month) {
    if (empty($month)) return false;
    
    // Check format
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return false;
    }
    
    // Check if it's a valid month
    $d = DateTime::createFromFormat('Y-m', $month);
    return $d && $d->format('Y-m') === $month;
}

/**
 * Validate team ID
 * @param int $team_id Team ID to validate
 * @return bool True if valid, false otherwise
 */
function validateTeamId($team_id) {
    return is_numeric($team_id) && intval($team_id) > 0;
}

/**
 * Get daily sales and profit data for a specific date and team
 * @param string $date Date in YYYY-MM-DD format
 * @param int $team_id Team ID
 * @return array Data array with products, sales, costs, profits or error message
 */
function getDailySalesProfit($date, $team_id) {
    // Validate inputs
    if (!validateDate($date)) {
        return ['error' => 'Invalid date format. Please use YYYY-MM-DD format.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        // Use NULLIF to prevent division by zero in SQL
        $sql = "SELECT product_name, sales, item_cost + cod AS total_cost, profit, 
                (profit / NULLIF(sales, 0)) * 100 AS profit_margin 
                FROM products 
                WHERE DATE(created_at) = ? AND team_id = ? 
                ORDER BY sales DESC";
        
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL preparation error: " . $dbconn->error);
        }
        
        $stmt->bind_param("si", $date, $team_id);
        
        if (!$stmt->execute()) {
            throw new Exception("SQL execution error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $data = [
            'products' => [],
            'sales' => [],
            'costs' => [],
            'profits' => [],
            'margins' => [],
            'date' => $date
        ];
        
        $total_sales = 0;
        $total_costs = 0;
        $total_profit = 0;
        
        while ($row = $result->fetch_assoc()) {
            $data['products'][] = $row['product_name'];
            $data['sales'][] = $row['sales'];
            $data['costs'][] = $row['total_cost'];
            $data['profits'][] = $row['profit'];
            $data['margins'][] = $row['profit_margin'] ?? 0; // Handle NULL values
            
            $total_sales += $row['sales'];
            $total_costs += $row['total_cost'];
            $total_profit += $row['profit'];
        }
        
        $data['total_sales'] = $total_sales;
        $data['total_costs'] = $total_costs;
        $data['total_profit'] = $total_profit;
        
        // Calculate total margin safely
        $data['total_margin'] = ($total_sales > 0) ? ($total_profit / $total_sales) * 100 : 0;
        $data['count'] = count($data['products']);
        
        return $data;
    } catch (Exception $e) {
        error_log("getDailySalesProfit error: " . $e->getMessage());
        return ['error' => 'Failed to retrieve daily sales data: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Get monthly sales data grouped by day
 * @param string $month Month in YYYY-MM format
 * @param int $team_id Team ID
 * @return array Data array with dates, sales, cogs, profit or error message
 */
function getMonthlySalesCOGSProfit($month, $team_id) {
    // Validate inputs
    if (!validateMonth($month)) {
        return ['error' => 'Invalid month format. Please use YYYY-MM format.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        $sql = "SELECT DATE(created_at) AS sales_date, 
                SUM(sales) AS total_sales, 
                SUM(item_cost + cod) AS total_cogs, 
                SUM(profit) AS total_profit 
                FROM products 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
                AND team_id = ?
                GROUP BY sales_date 
                ORDER BY sales_date ASC";
        
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL preparation error: " . $dbconn->error);
        }
        
        $stmt->bind_param("si", $month, $team_id);
        
        if (!$stmt->execute()) {
            throw new Exception("SQL execution error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $data = [
            'dates' => [],
            'sales' => [],
            'cogs' => [],
            'profit' => [],
            'month' => $month
        ];
        
        $total_sales = 0;
        $total_cogs = 0;
        $total_profit = 0;
        
        while ($row = $result->fetch_assoc()) {
            $data['dates'][] = $row['sales_date'];
            $data['sales'][] = $row['total_sales'];
            $data['cogs'][] = $row['total_cogs'];
            $data['profit'][] = $row['total_profit'];
            
            $total_sales += $row['total_sales'];
            $total_cogs += $row['total_cogs'];
            $total_profit += $row['total_profit'];
        }
        
        $data['total_sales'] = $total_sales;
        $data['total_cogs'] = $total_cogs;
        $data['total_profit'] = $total_profit;
        $data['total_margin'] = ($total_sales > 0) ? ($total_profit / $total_sales) * 100 : 0;
        $data['count'] = count($data['dates']);
        
        return $data;
    } catch (Exception $e) {
        error_log("getMonthlySalesCOGSProfit error: " . $e->getMessage());
        return ['error' => 'Failed to retrieve monthly sales data: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Get data for date range
 * @param string $start_date Start date in YYYY-MM-DD format
 * @param string $end_date End date in YYYY-MM-DD format
 * @param int $team_id Team ID
 * @return array Data array with detailed sales information or error message
 */
function getDateRangeSalesProfit($start_date, $end_date, $team_id) {
    // Validate inputs
    if (!validateDate($start_date)) {
        return ['error' => 'Invalid start date format. Please use YYYY-MM-DD format.'];
    }
    
    if (!validateDate($end_date)) {
        return ['error' => 'Invalid end date format. Please use YYYY-MM-DD format.'];
    }
    
    // Ensure end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        return ['error' => 'End date cannot be before start date.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        $sql = "SELECT DATE(created_at) AS sales_date, product_name, sales, 
                item_cost + cod AS total_cost, profit, 
                (profit / NULLIF(sales, 0)) * 100 AS profit_margin 
                FROM products 
                WHERE created_at BETWEEN ? AND ? AND team_id = ? 
                ORDER BY sales_date ASC, sales DESC";
        
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL preparation error: " . $dbconn->error);
        }
        
        // Include the entire end date by adding 1 day and subtracting 1 second
        $end_date_adj = date('Y-m-d 23:59:59', strtotime($end_date));
        $stmt->bind_param("ssi", $start_date, $end_date_adj, $team_id);
        
        if (!$stmt->execute()) {
            throw new Exception("SQL execution error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $data = [
            'rows' => [],
            'total_sales' => 0,
            'total_cost' => 0,
            'total_profit' => 0,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
        
        while ($row = $result->fetch_assoc()) {
            $data['rows'][] = [
                'date' => $row['sales_date'],
                'product' => $row['product_name'],
                'sales' => $row['sales'],
                'cost' => $row['total_cost'],
                'profit' => $row['profit'],
                'margin' => $row['profit_margin'] ?? 0
            ];
            
            $data['total_sales'] += $row['sales'];
            $data['total_cost'] += $row['total_cost'];
            $data['total_profit'] += $row['profit'];
        }
        
        $data['total_margin'] = ($data['total_sales'] > 0) ? ($data['total_profit'] / $data['total_sales']) * 100 : 0;
        $data['count'] = count($data['rows']);
        
        // Additional statistics - group by product
        $products = [];
        
        foreach ($data['rows'] as $row) {
            $product = $row['product'];
            
            if (!isset($products[$product])) {
                $products[$product] = [
                    'sales' => 0,
                    'cost' => 0,
                    'profit' => 0,
                    'count' => 0
                ];
            }
            
            $products[$product]['sales'] += $row['sales'];
            $products[$product]['cost'] += $row['cost'];
            $products[$product]['profit'] += $row['profit'];
            $products[$product]['count']++;
        }
        
        // Calculate margins and add to product stats
        foreach ($products as $product => $stats) {
            $products[$product]['margin'] = ($stats['sales'] > 0) ? ($stats['profit'] / $stats['sales']) * 100 : 0;
        }
        
        $data['products'] = $products;
        
        // Sort products by sales (descending)
        uasort($data['products'], function($a, $b) {
            return $b['sales'] <=> $a['sales'];
        });
        
        return $data;
    } catch (Exception $e) {
        error_log("getDateRangeSalesProfit error: " . $e->getMessage());
        return ['error' => 'Failed to retrieve date range sales data: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Get product performance data
 * @param string $start_date Start date in YYYY-MM-DD format
 * @param string $end_date End date in YYYY-MM-DD format
 * @param int $team_id Team ID
 * @return array Data array with product performance metrics or error message
 */
function getProductPerformance($start_date, $end_date, $team_id) {
    // Validate inputs
    if (!validateDate($start_date)) {
        return ['error' => 'Invalid start date format. Please use YYYY-MM-DD format.'];
    }
    
    if (!validateDate($end_date)) {
        return ['error' => 'Invalid end date format. Please use YYYY-MM-DD format.'];
    }
    
    // Ensure end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        return ['error' => 'End date cannot be before start date.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        $sql = "SELECT product_name, 
                SUM(sales) AS total_sales, 
                SUM(item_cost + cod) AS total_cost, 
                SUM(profit) AS total_profit,
                (SUM(profit) / NULLIF(SUM(sales), 0)) * 100 AS avg_margin,
                COUNT(*) AS units_sold
                FROM products 
                WHERE created_at BETWEEN ? AND ? AND team_id = ?
                GROUP BY product_name 
                ORDER BY total_sales DESC";
        
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL preparation error: " . $dbconn->error);
        }
        
        // Include the entire end date by adding 1 day and subtracting 1 second
        $end_date_adj = date('Y-m-d 23:59:59', strtotime($end_date));
        $stmt->bind_param("ssi", $start_date, $end_date_adj, $team_id);
        
        if (!$stmt->execute()) {
            throw new Exception("SQL execution error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $data = [
            'products' => [],
            'sales' => [],
            'costs' => [],
            'profits' => [],
            'margins' => [],
            'units' => [],
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
        
        $total_sales = 0;
        $total_costs = 0;
        $total_profit = 0;
        $total_units = 0;
        
        while ($row = $result->fetch_assoc()) {
            $data['products'][] = $row['product_name'];
            $data['sales'][] = $row['total_sales'];
            $data['costs'][] = $row['total_cost'];
            $data['profits'][] = $row['total_profit'];
            $data['margins'][] = $row['avg_margin'] ?? 0;
            $data['units'][] = $row['units_sold'];
            
            $total_sales += $row['total_sales'];
            $total_costs += $row['total_cost'];
            $total_profit += $row['total_profit'];
            $total_units += $row['units_sold'];
        }
        
        $data['total_sales'] = $total_sales;
        $data['total_costs'] = $total_costs;
        $data['total_profit'] = $total_profit;
        $data['total_units'] = $total_units;
        $data['total_margin'] = ($total_sales > 0) ? ($total_profit / $total_sales) * 100 : 0;
        $data['count'] = count($data['products']);
        
        return $data;
    } catch (Exception $e) {
        error_log("getProductPerformance error: " . $e->getMessage());
        return ['error' => 'Failed to retrieve product performance data: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Get advanced analytics data
 * @param int $team_id Team ID
 * @return array Data array with advanced analytics or error message
 */
function getAdvancedAnalytics($team_id) {
    // Validate team ID
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        // Check if analytics_data table exists
        $table_exists = false;
        $check_table_sql = "SHOW TABLES LIKE 'analytics_data'";
        $table_result = $dbconn->query($check_table_sql);
        
        if ($table_result && $table_result->num_rows > 0) {
            $table_exists = true;
        }
        
        if ($table_exists) {
            // Table exists, get real data
            $sql = "SELECT 
                    CONCAT('Week ', WEEK(date)) as week_label,
                    SUM(total_sales) as weekly_sales,
                    SUM(ad_spend) as weekly_ad_spend,
                    (SUM(conversions) / NULLIF(SUM(visitors), 0)) * 100 as conversion_rate,
                    ((SUM(total_sales) - SUM(ad_spend)) / NULLIF(SUM(ad_spend), 0)) * 100 as roi
                FROM analytics_data
                WHERE team_id = ?
                GROUP BY WEEK(date)
                ORDER BY WEEK(date) DESC
                LIMIT 10";
            
            $stmt = $dbconn->prepare($sql);
            if (!$stmt) {
                throw new Exception("SQL preparation error: " . $dbconn->error);
            }
            
            $stmt->bind_param("i", $team_id);
            
            if (!$stmt->execute()) {
                throw new Exception("SQL execution error: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            $data = [
                'weeks' => [],
                'sales' => [],
                'ads_spend' => [],
                'conversion_rates' => [],
                'roi' => []
            ];
            
            while ($row = $result->fetch_assoc()) {
                $data['weeks'][] = $row['week_label'];
                $data['sales'][] = $row['weekly_sales'];
                $data['ads_spend'][] = $row['weekly_ad_spend'];
                $data['conversion_rates'][] = $row['conversion_rate'] ?? 0;
                $data['roi'][] = $row['roi'] ?? 0;
            }
            
            $data['is_sample'] = false;
        } else {
            // Table doesn't exist, return sample data with a notice
            $data = [
                'weeks' => ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                'sales' => [5000, 5500, 4800, 6200, 7000],
                'ads_spend' => [1000, 1200, 900, 1500, 1800],
                'conversion_rates' => [2.1, 2.5, 1.8, 3.0, 3.2],
                'roi' => []
            ];
            
            // Calculate ROI
            for ($i = 0; $i < count($data['weeks']); $i++) {
                $ads_spend = $data['ads_spend'][$i];
                $sales = $data['sales'][$i];
                $data['roi'][] = ($ads_spend > 0) ? (($sales - $ads_spend) / $ads_spend) * 100 : 0;
            }
            
            $data['is_sample'] = true;
            $data['notice'] = 'Using sample data. Analytics table not found in database.';
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("getAdvancedAnalytics error: " . $e->getMessage());
        return ['error' => 'Failed to retrieve advanced analytics data: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Get daily metrics including total parcels and ads spend
 * @param string $date The date to get metrics for
 * @param int $team_id The team ID
 * @return array Array of metrics or error
 */
function getDailyMetrics($date, $team_id) {
    // Validate inputs
    if (!validateDate($date)) {
        return ['error' => 'Invalid date format. Please use YYYY-MM-DD format.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        // Get total parcels from orders table
        $total_parcels = 0;
        $sql_parcels = "SELECT COUNT(*) as total_parcels FROM orders 
                      WHERE DATE(order_date) = ? AND team_id = ?";
                      
        $stmt_parcels = $dbconn->prepare($sql_parcels);
        if ($stmt_parcels) {
            $stmt_parcels->bind_param("si", $date, $team_id);
            $stmt_parcels->execute();
            $result_parcels = $stmt_parcels->get_result();
            
            if ($result_parcels && $parcels_data = $result_parcels->fetch_assoc()) {
                $total_parcels = $parcels_data['total_parcels'] ?? 0;
            }
            
            $stmt_parcels->close();
        }
        
        // If orders table doesn't exist or query fails, try alternative table
        if ($total_parcels == 0) {
           // In getDailyMetrics() function
// Replace the alternative table query with:
$alt_sql = "SELECT COALESCE(SUM(unit_sold), 0) as total_parcels FROM products 
WHERE DATE(created_at) = ? AND team_id = ?";
            
            $alt_stmt = $dbconn->prepare($alt_sql);
            if ($alt_stmt) {
                $alt_stmt->bind_param("si", $date, $team_id);
                $alt_stmt->execute();
                $alt_result = $alt_stmt->get_result();
                
                if ($alt_result && $alt_data = $alt_result->fetch_assoc()) {
                    $total_parcels = $alt_data['total_parcels'] ?? 0;
                }
                
                $alt_stmt->close();
            }
        }
        
        // Get ads spend from analytics table
        $ads_spend = 0;
        $sql_ads = "SELECT SUM(ad_spend) as total_ads FROM analytics_data 
                   WHERE DATE(date) = ? AND team_id = ?";
        
        $stmt_ads = $dbconn->prepare($sql_ads);
        if ($stmt_ads) {
            $stmt_ads->bind_param("si", $date, $team_id);
            $stmt_ads->execute();
            $result_ads = $stmt_ads->get_result();
            
            if ($result_ads && $ads_data = $result_ads->fetch_assoc()) {
                $ads_spend = $ads_data['total_ads'] ?? 0;
            }
            
            $stmt_ads->close();
        }
        
        // If analytics table doesn't exist, try the ads_spend table
        if ($ads_spend == 0) {
            $sql_ads_alt = "SELECT SUM(amount) as total_ads FROM ads_spend 
                           WHERE DATE(spend_date) = ? AND team_id = ?";
            
            $stmt_ads_alt = $dbconn->prepare($sql_ads_alt);
            if ($stmt_ads_alt) {
                $stmt_ads_alt->bind_param("si", $date, $team_id);
                $stmt_ads_alt->execute();
                $result_ads_alt = $stmt_ads_alt->get_result();
                
                if ($result_ads_alt && $ads_alt_data = $result_ads_alt->fetch_assoc()) {
                    $ads_spend = $ads_alt_data['total_ads'] ?? 0;
                }
                
                $stmt_ads_alt->close();
            }
        }
        
        return [
            'total_parcels' => $total_parcels,
            'ads_spend' => $ads_spend
        ];
    } catch (Exception $e) {
        error_log("getDailyMetrics error: " . $e->getMessage());
        return ['error' => 'Error retrieving daily metrics: ' . $e->getMessage()];
    }
}

/**
 * Get monthly metrics including total parcels and ads spend
 * @param string $month The month to get metrics for (YYYY-MM)
 * @param int $team_id The team ID
 * @return array Array of metrics or error
 */
function getMonthlyMetrics($month, $team_id) {
    // Validate inputs
    if (!validateMonth($month)) {
        return ['error' => 'Invalid month format. Please use YYYY-MM format.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        // Extract year and month from the input
        list($year, $month_num) = explode('-', $month);
        
        // Get total parcels from orders table
        $total_parcels = 0;
        $sql_parcels = "SELECT COUNT(*) as total_parcels FROM orders 
                      WHERE YEAR(order_date) = ? AND MONTH(order_date) = ? AND team_id = ?";
        
        $stmt_parcels = $dbconn->prepare($sql_parcels);
        if ($stmt_parcels) {
            $stmt_parcels->bind_param("sii", $year, $month_num, $team_id);
            $stmt_parcels->execute();
            $result_parcels = $stmt_parcels->get_result();
            
            if ($result_parcels && $parcels_data = $result_parcels->fetch_assoc()) {
                $total_parcels = $parcels_data['total_parcels'] ?? 0;
            }
            
            $stmt_parcels->close();
        }
        
        // If orders table doesn't exist or query fails, try alternative table
        if ($total_parcels == 0) {
        // In getMonthlyMetrics() function
// Replace the alternative table query with:
$alt_sql = "SELECT COALESCE(SUM(unit_sold), 0) as total_parcels FROM products 
WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? AND team_id = ?";
            
            $alt_stmt = $dbconn->prepare($alt_sql);
            if ($alt_stmt) {
                $alt_stmt->bind_param("sii", $year, $month_num, $team_id);
                $alt_stmt->execute();
                $alt_result = $alt_stmt->get_result();
                
                if ($alt_result && $alt_data = $alt_result->fetch_assoc()) {
                    $total_parcels = $alt_data['total_parcels'] ?? 0;
                }
                
                $alt_stmt->close();
            }
        }
        
        // Get ads spend from analytics table
        $ads_spend = 0;
        $sql_ads = "SELECT SUM(ad_spend) as total_ads FROM analytics_data 
                   WHERE YEAR(date) = ? AND MONTH(date) = ? AND team_id = ?";
        
        $stmt_ads = $dbconn->prepare($sql_ads);
        if ($stmt_ads) {
            $stmt_ads->bind_param("sii", $year, $month_num, $team_id);
            $stmt_ads->execute();
            $result_ads = $stmt_ads->get_result();
            
            if ($result_ads && $ads_data = $result_ads->fetch_assoc()) {
                $ads_spend = $ads_data['total_ads'] ?? 0;
            }
            
            $stmt_ads->close();
        }
        
        // If analytics table doesn't exist, try the ads_spend table
        if ($ads_spend == 0) {
            $sql_ads_alt = "SELECT SUM(amount) as total_ads FROM ads_spend 
                           WHERE YEAR(spend_date) = ? AND MONTH(spend_date) = ? AND team_id = ?";
            
            $stmt_ads_alt = $dbconn->prepare($sql_ads_alt);
            if ($stmt_ads_alt) {
                $stmt_ads_alt->bind_param("sii", $year, $month_num, $team_id);
                $stmt_ads_alt->execute();
                $result_ads_alt = $stmt_ads_alt->get_result();
                
                if ($result_ads_alt && $ads_alt_data = $result_ads_alt->fetch_assoc()) {
                    $ads_spend = $ads_alt_data['total_ads'] ?? 0;
                }
                
                $stmt_ads_alt->close();
            }
        }
        
        return [
            'total_parcels' => $total_parcels,
            'ads_spend' => $ads_spend
        ];
    } catch (Exception $e) {
        error_log("getMonthlyMetrics error: " . $e->getMessage());
        return ['error' => 'Error retrieving monthly metrics: ' . $e->getMessage()];
    }
}

/**
 * Get date range metrics including total parcels and ads spend
 * @param string $start_date The start date
 * @param string $end_date The end date
 * @param int $team_id The team ID
 * @return array Array of metrics or error
 */
function getDateRangeMetrics($start_date, $end_date, $team_id) {
    // Validate inputs
    if (!validateDate($start_date)) {
        return ['error' => 'Invalid start date format. Please use YYYY-MM-DD format.'];
    }
    
    if (!validateDate($end_date)) {
        return ['error' => 'Invalid end date format. Please use YYYY-MM-DD format.'];
    }
    
    // Ensure end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        return ['error' => 'End date cannot be before start date.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        // Include the entire end date
        $end_date_adj = date('Y-m-d 23:59:59', strtotime($end_date));
        
        // Get total parcels from orders table
        $total_parcels = 0;
        $sql_parcels = "SELECT COUNT(*) as total_parcels FROM orders 
                      WHERE order_date BETWEEN ? AND ? AND team_id = ?";
        
        $stmt_parcels = $dbconn->prepare($sql_parcels);
        if ($stmt_parcels) {
            $stmt_parcels->bind_param("ssi", $start_date, $end_date_adj, $team_id);
            $stmt_parcels->execute();
            $result_parcels = $stmt_parcels->get_result();
            
            if ($result_parcels && $parcels_data = $result_parcels->fetch_assoc()) {
                $total_parcels = $parcels_data['total_parcels'] ?? 0;
            }
            
            $stmt_parcels->close();
        }
        
        // If orders table doesn't exist or query fails, try alternative table
        if ($total_parcels == 0) {
           // In getDateRangeMetrics() function
// Replace the alternative table query with:
$alt_sql = "SELECT COALESCE(SUM(unit_sold), 0) as total_parcels FROM products 
WHERE created_at BETWEEN ? AND ? AND team_id = ?";
            
            $alt_stmt = $dbconn->prepare($alt_sql);
            if ($alt_stmt) {
                $alt_stmt->bind_param("ssi", $start_date, $end_date_adj, $team_id);
                $alt_stmt->execute();
                $alt_result = $alt_stmt->get_result();
                
                if ($alt_result && $alt_data = $alt_result->fetch_assoc()) {
                    $total_parcels = $alt_data['total_parcels'] ?? 0;
                }
                
                $alt_stmt->close();
            }
        }
        
        // Get ads spend from analytics table
        $ads_spend = 0;
        $sql_ads = "SELECT SUM(ad_spend) as total_ads FROM analytics_data 
                   WHERE date BETWEEN ? AND ? AND team_id = ?";
        
        $stmt_ads = $dbconn->prepare($sql_ads);
        if ($stmt_ads) {
            $stmt_ads->bind_param("ssi", $start_date, $end_date_adj, $team_id);
            $stmt_ads->execute();
            $result_ads = $stmt_ads->get_result();
            
            if ($result_ads && $ads_data = $result_ads->fetch_assoc()) {
                $ads_spend = $ads_data['total_ads'] ?? 0;
            }
            
            $stmt_ads->close();
        }
        
        // If analytics table doesn't exist, try the ads_spend table
        if ($ads_spend == 0) {
            $sql_ads_alt = "SELECT SUM(amount) as total_ads FROM ads_spend 
                           WHERE spend_date BETWEEN ? AND ? AND team_id = ?";
            
            $stmt_ads_alt = $dbconn->prepare($sql_ads_alt);
            if ($stmt_ads_alt) {
                $stmt_ads_alt->bind_param("ssi", $start_date, $end_date_adj, $team_id);
                $stmt_ads_alt->execute();
                $result_ads_alt = $stmt_ads_alt->get_result();
                
                if ($result_ads_alt && $ads_alt_data = $result_ads_alt->fetch_assoc()) {
                    $ads_spend = $ads_alt_data['total_ads'] ?? 0;
                }
                
                $stmt_ads_alt->close();
            }
        }
        
        return [
            'total_parcels' => $total_parcels,
            'ads_spend' => $ads_spend
        ];
    } catch (Exception $e) {
        error_log("getDateRangeMetrics error: " . $e->getMessage());
        return ['error' => 'Error retrieving date range metrics: ' . $e->getMessage()];
    }
}

/**
 * Get product metrics for a date range
 * @param string $start_date The start date
 * @param string $end_date The end date
 * @param int $team_id The team ID
 * @return array Array of metrics or error
 */
function getProductMetrics($start_date, $end_date, $team_id) {
    // Validate inputs
    if (!validateDate($start_date)) {
        return ['error' => 'Invalid start date format. Please use YYYY-MM-DD format.'];
    }
    
    if (!validateDate($end_date)) {
        return ['error' => 'Invalid end date format. Please use YYYY-MM-DD format.'];
    }
    
    // Ensure end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        return ['error' => 'End date cannot be before start date.'];
    }
    
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        // Include the entire end date
        $end_date_adj = date('Y-m-d 23:59:59', strtotime($end_date));
        
        // Get ads spend from analytics table for this period
        $ads_spend = 0;
        $sql_ads = "SELECT SUM(ad_spend) as total_ads FROM analytics_data 
                   WHERE date BETWEEN ? AND ? AND team_id = ?";
        
        $stmt_ads = $dbconn->prepare($sql_ads);
        if ($stmt_ads) {
            $stmt_ads->bind_param("ssi", $start_date, $end_date_adj, $team_id);
            $stmt_ads->execute();
            $result_ads = $stmt_ads->get_result();
            
            if ($result_ads && $ads_data = $result_ads->fetch_assoc()) {
                $ads_spend = $ads_data['total_ads'] ?? 0;
            }
            
            $stmt_ads->close();
        }
        
        // If analytics table doesn't exist, try the ads_spend table
        if ($ads_spend == 0) {
            $sql_ads_alt = "SELECT SUM(amount) as total_ads FROM ads_spend 
                           WHERE spend_date BETWEEN ? AND ? AND team_id = ?";
            
            $stmt_ads_alt = $dbconn->prepare($sql_ads_alt);
            if ($stmt_ads_alt) {
                $stmt_ads_alt->bind_param("ssi", $start_date, $end_date_adj, $team_id);
                $stmt_ads_alt->execute();
                $result_ads_alt = $stmt_ads_alt->get_result();
                
                if ($result_ads_alt && $ads_alt_data = $result_ads_alt->fetch_assoc()) {
                    $ads_spend = $ads_alt_data['total_ads'] ?? 0;
                }
                
                $stmt_ads_alt->close();
            }
        }
        
        return [
            'ads_spend' => $ads_spend
        ];
    } catch (Exception $e) {
        error_log("getProductMetrics error: " . $e->getMessage());
        return ['error' => 'Error retrieving product metrics: ' . $e->getMessage()];
    }
}

/**
 * Get analytics metrics for summary section
 * @param int $team_id The team ID
 * @return array Array of metrics or error
 */
function getAnalyticsMetrics($team_id) {
    // Validate team ID
    if (!validateTeamId($team_id)) {
        return ['error' => 'Invalid team ID.'];
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return ['error' => 'Database connection failed.'];
    }
    
    try {
        // Get total COGS and profit from sales
        $total_cogs = 0;
        $total_profit = 0;
        
        $sql = "SELECT SUM(item_cost + cod) as total_cogs, SUM(profit) as total_profit 
                FROM products WHERE team_id = ?";
        
        $stmt = $dbconn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $data = $result->fetch_assoc()) {
                $total_cogs = $data['total_cogs'] ?? 0;
                $total_profit = $data['total_profit'] ?? 0;
            }
            
            $stmt->close();
        }
        
        // Get total parcels from orders
        $total_parcels = 0;
        $sql_parcels = "SELECT COUNT(*) as total_parcels FROM orders WHERE team_id = ?";
        
        $stmt_parcels = $dbconn->prepare($sql_parcels);
        if ($stmt_parcels) {
            $stmt_parcels->bind_param("i", $team_id);
            $stmt_parcels->execute();
            $result_parcels = $stmt_parcels->get_result();
            
            if ($result_parcels && $parcels_data = $result_parcels->fetch_assoc()) {
                $total_parcels = $parcels_data['total_parcels'] ?? 0;
            }
            
            $stmt_parcels->close();
        }
        
        // If orders table doesn't exist, try products table
        if ($total_parcels == 0) {
            $alt_sql = "SELECT COUNT(*) as total_parcels FROM products WHERE team_id = ?";
            
            $alt_stmt = $dbconn->prepare($alt_sql);
            if ($alt_stmt) {
                $alt_stmt->bind_param("i", $team_id);
                $alt_stmt->execute();
                $alt_result = $alt_stmt->get_result();
                
                if ($alt_result && $alt_data = $alt_result->fetch_assoc()) {
                    $total_parcels = $alt_data['total_parcels'] ?? 0;
                }
                
                $alt_stmt->close();
            }
        }
        
        return [
            'total_cogs' => $total_cogs,
            'total_profit' => $total_profit,
            'total_parcels' => $total_parcels
        ];
    } catch (Exception $e) {
        error_log("getAnalyticsMetrics error: " . $e->getMessage());
        return ['error' => 'Error retrieving analytics metrics: ' . $e->getMessage()];
    }
}

/**
 * Get team name by team ID
 * @param int $team_id Team ID
 * @return string Team name or error message
 */
function getTeamName($team_id) {
    // Validate team ID
    if (!validateTeamId($team_id)) {
        return 'Unknown Team (Invalid ID)';
    }
    
    $dbconn = connectToDb();
    if (!$dbconn) {
        return 'Unknown Team (DB Error)';
    }
    
    try {
        $sql = "SELECT team_name FROM teams WHERE team_id = ?";
        $stmt = $dbconn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL preparation error: " . $dbconn->error);
        }
        
        $stmt->bind_param("i", $team_id);
        
        if (!$stmt->execute()) {
            throw new Exception("SQL execution error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return htmlspecialchars($row['team_name']);
        }
        
        return 'Unknown Team';
    } catch (Exception $e) {
        error_log("getTeamName error: " . $e->getMessage());
        return 'Unknown Team (Error)';
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

/**
 * Check if CSV export functionality is available
 * @return bool True if available, false otherwise
 */
function isCsvExportAvailable() {
    return function_exists('fputcsv');
}

/**
 * Export data to CSV file
 * @param array $data Data to export
 * @param string $filename Filename for export
 * @return bool True on success, false on failure
 */
function exportToCsv($data, $filename) {
    if (!isCsvExportAvailable()) {
        return false;
    }
    
    try {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new Exception("Could not open output stream");
        }
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        return true;
    } catch (Exception $e) {
        error_log("exportToCsv error: " . $e->getMessage());
        return false;
    }
}


/**
 * Process error responses consistently
 * @param array $data Data array potentially containing an error
 * @return bool True if data contains error, false otherwise
 */
function hasError($data) {
    return is_array($data) && isset($data['error']);
}

/**
 * Get error message from data array
 * @param array $data Data array potentially containing an error
 * @return string Error message or empty string
 */
function getErrorMessage($data) {
    return hasError($data) ? $data['error'] : '';
}

?>