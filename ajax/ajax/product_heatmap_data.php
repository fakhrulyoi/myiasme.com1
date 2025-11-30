<?php
// Include database connection
include '../includes/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Get product ID from request, default to top selling product if not specified
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

// If no product ID specified, get the top selling product
if (!$product_id) {
    $top_sql = "SELECT 
                    p.product_id,
                    SUM(od.price * od.quantity) as total_sales
                FROM 
                    order_details od
                JOIN 
                    products p ON od.product_id = p.product_id
                JOIN 
                    orders o ON od.order_id = o.order_id
                WHERE 
                    o.order_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY 
                    p.product_id
                ORDER BY 
                    total_sales DESC
                LIMIT 1";
    
    $top_result = $conn->query($top_sql);
    if ($top_result && $top_result->num_rows > 0) {
        $product_id = $top_result->fetch_assoc()['product_id'];
    } else {
        // If no products found, return empty array
        echo json_encode([]);
        exit;
    }
}

// Get date range - default to last 90 days
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d', strtotime('-90 days'));
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Query daily sales data for the product
$sql = "SELECT 
            DATE(o.order_date) as date,
            SUM(od.price * od.quantity) as sales
        FROM 
            order_details od
        JOIN 
            orders o ON od.order_id = o.order_id
        WHERE 
            od.product_id = ?
            AND o.order_date BETWEEN ? AND ?
        GROUP BY 
            DATE(o.order_date)
        ORDER BY 
            date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $product_id, $from_date, $to_date);
$stmt->execute();
$result = $stmt->get_result();

// Prepare data array
$data = [];
if ($result) {
    // Find the maximum sales value to normalize intensities
    $max_sales = 0;
    $temp_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $temp_data[] = $row;
        if ($row['sales'] > $max_sales) {
            $max_sales = $row['sales'];
        }
    }
    
    // Calculate intensity and format data
    foreach ($temp_data as $row) {
        $intensity = $max_sales > 0 ? $row['sales'] / $max_sales : 0;
        
        $data[] = [
            'date' => $row['date'],
            'sales' => $row['sales'],
            'intensity' => $intensity
        ];
    }
}

// Return JSON data
echo json_encode($data);