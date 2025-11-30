<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return JSON response instead of redirecting
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Return JSON response instead of redirecting
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Database connection
try {
    // Include database connection
    require_once 'dbconn_productProfit.php';
    
    if (!$dbconn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    // Return JSON response instead of redirecting
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get form data with proper validation
function getFormField($field, $default = null) {
    if (isset($_POST[$field]) && $_POST[$field] !== '') {
        return $_POST[$field];
    }
    return $default;
}

// Get numeric field with proper validation
function getNumericField($field, $default = 0) {
    if (isset($_POST[$field]) && is_numeric($_POST[$field])) {
        return floatval($_POST[$field]);
    }
    return $default;
}

// Get form fields
$team_id = getFormField('team_id', 0);
$company_name = getFormField('team_name', ''); // Get company name
if (empty($company_name)) {
    $company_name = getFormField('company_name', ''); // Fallback to company_name field
}

$ssm_number = getFormField('ssm_number', '');
$report_month_str = getFormField('report_month', date('Y-m'));
$report_month = date('Y-m-d', strtotime($report_month_str . '-01')); // Convert to proper date format

// Financial values
$total_sales = getNumericField('total_sales');
$roas = getNumericField('roas');
$net_revenue = getNumericField('net_revenue');
$ads_cost = getNumericField('ads_cost');
$direct_cost_cogs = getNumericField('direct_cost_cogs');
$gross_profit = getNumericField('gross_profit');
$shipping_fee = getNumericField('shipping_fee');
$web_hosting_domain = getNumericField('web_hosting_domain');
$operating_cost = getNumericField('total_operating_cost');
$operating_profit = getNumericField('operating_profit');
$salary = getNumericField('salary');
$operation_cost = getNumericField('operation_cost');
$wrap_parcel_cost = getNumericField('wrap_parcel_cost');
$commission_parcel = getNumericField('commission_parcel');
$training_cost = getNumericField('training_cost');
$internet_cost = getNumericField('internet_cost');
$postpaid_bill = getNumericField('postpaid_bill');
$rent = getNumericField('rent');
$utilities = getNumericField('utilities');
$maintenance_repair = getNumericField('maintenance_repair');
$staff_pay_and_claim = getNumericField('staff_pay_and_claim');
$other_expenses = getNumericField('other_expenses');
$total_expenses = getNumericField('total_expenses');
$net_profit = getNumericField('net_profit');

// Commission values
$commission_rate = getNumericField('commission_rate', 5);
$commission_amount = getNumericField('commission_amount', 0);
$person_name = getFormField('person_name', ''); // Get person name for individual commission

// For debugging, let's check the SQL and parameters
try {
    // Begin transaction
    mysqli_autocommit($dbconn, FALSE);
    
    // Check if report for this team and month already exists
    $check_sql = "SELECT id_financial_report FROM financial_report WHERE team_id = ? AND report_month = ?";
    $check_stmt = mysqli_prepare($dbconn, $check_sql);
    
    if (!$check_stmt) {
        throw new Exception("Prepare check statement failed: " . mysqli_error($dbconn));
    }
    
    mysqli_stmt_bind_param($check_stmt, "is", $team_id, $report_month);
    
    if (!mysqli_stmt_execute($check_stmt)) {
        throw new Exception("Execute check failed: " . mysqli_stmt_error($check_stmt));
    }
    
    mysqli_stmt_store_result($check_stmt);
    $exists = mysqli_stmt_num_rows($check_stmt) > 0;
    
    if ($exists) {
        // Get the existing report ID
        mysqli_stmt_bind_result($check_stmt, $report_id);
        mysqli_stmt_fetch($check_stmt);
        mysqli_stmt_close($check_stmt);
        
        // Update existing report
        $update_sql = "UPDATE financial_report SET 
                company_name = ?, 
                ssm_number = ?, 
                total_sales = ?, 
                roas = ?, 
                net_revenue = ?, 
                ads_cost = ?, 
                direct_cost_cogs = ?, 
                gross_profit = ?, 
                shipping_fee = ?, 
                web_hosting_domain = ?, 
                operating_cost = ?, 
                operating_profit = ?, 
                salary = ?, 
                operation_cost = ?, 
                wrap_parcel_cost = ?, 
                commission_parcel = ?, 
                training_cost = ?, 
                internet_cost = ?, 
                postpaid_bill = ?, 
                rent = ?, 
                utilities = ?, 
                maintenance_repair = ?, 
                staff_pay_and_claim = ?, 
                other_expenses = ?, 
                total_expenses = ?, 
                net_profit = ?,
                commission_rate = ? 
                WHERE id_financial_report = ?";
        
        $update_stmt = mysqli_prepare($dbconn, $update_sql);
        
        if (!$update_stmt) {
            throw new Exception("Prepare update statement failed: " . mysqli_error($dbconn));
        }
        
       // Update the binding to include all parameters properly
        mysqli_stmt_bind_param($update_stmt, "ssdddddddddddddddddddddddddi", 
        $company_name, $ssm_number, $total_sales, $roas, $net_revenue, $ads_cost, 
        $direct_cost_cogs, $gross_profit, $shipping_fee, $web_hosting_domain, 
        $operating_cost, $operating_profit, $salary, $operation_cost, 
        $wrap_parcel_cost, $commission_parcel, $training_cost, $internet_cost, 
        $postpaid_bill, $rent, $utilities, $maintenance_repair, 
        $staff_pay_and_claim, $other_expenses, $total_expenses, $net_profit,
        $commission_rate, $report_id
        );
        
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Execute update failed: " . mysqli_stmt_error($update_stmt));
        }
        
        mysqli_stmt_close($update_stmt);
        $message = "Financial report updated successfully!";
    } else {
        // Check structure of financial_report table
        $table_check = mysqli_query($dbconn, "DESCRIBE financial_report");
        if (!$table_check) {
            throw new Exception("Failed to check table structure: " . mysqli_error($dbconn));
        }
        
        // Count columns in the table
        $column_count = mysqli_num_rows($table_check);
        
        // Insert new report - simplified to ensure parameter count matches
        $insert_sql = "INSERT INTO financial_report (
            team_id, company_name, ssm_number, report_month, total_sales, 
            net_revenue, ads_cost, direct_cost_cogs, gross_profit,
            operating_profit, total_expenses, net_profit, commission_rate
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
        
        $insert_stmt = mysqli_prepare($dbconn, $insert_sql);
        
        if (!$insert_stmt) {
            throw new Exception("Prepare insert statement failed: " . mysqli_error($dbconn));
        }
        
        mysqli_stmt_bind_param($insert_stmt, "isssddddddddd", 
        $team_id, $company_name, $ssm_number, $report_month, $total_sales,
        $net_revenue, $ads_cost, $direct_cost_cogs, $gross_profit,
        $operating_profit, $total_expenses, $net_profit, $commission_rate
        );
        
        if (!mysqli_stmt_execute($insert_stmt)) {
            throw new Exception("Execute insert failed: " . mysqli_stmt_error($insert_stmt));
        }
        
        $report_id = mysqli_insert_id($dbconn);
        mysqli_stmt_close($insert_stmt);
        $message = "Financial report saved successfully!";
    }
    
    // Save individual commission record
    if (!empty($person_name)) {
        $commission_sql = "INSERT INTO commission_records (
            report_id, person_name, commission_rate, commission_amount, created_at
        ) VALUES (
            ?, ?, ?, ?, NOW()
        )";
        
        $commission_stmt = mysqli_prepare($dbconn, $commission_sql);
        
        if (!$commission_stmt) {
            throw new Exception("Prepare commission statement failed: " . mysqli_error($dbconn));
        }
        
        mysqli_stmt_bind_param($commission_stmt, "isdd", 
            $report_id, $person_name, $commission_rate, $commission_amount
        );
        
        if (!mysqli_stmt_execute($commission_stmt)) {
            throw new Exception("Execute commission insert failed: " . mysqli_stmt_error($commission_stmt));
        }
        
        mysqli_stmt_close($commission_stmt);
    }
    
    // Commit transaction
    if (!mysqli_commit($dbconn)) {
        throw new Exception("Commit failed");
    }
    
    // Return JSON response instead of redirecting
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'report_id' => $report_id,
        'message' => $message,
        'person_name' => $person_name
    ]);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($dbconn);
    
    // Return JSON response instead of redirecting
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}