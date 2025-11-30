<?php
// Include database connection and check for AJAX request
include '../includes/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Validate request
if (!isset($_GET['products']) || empty($_GET['products'])) {
    echo json_encode(['error' => 'No products specified for comparison']);
    exit;
}

// Parse product IDs
$product_ids = explode(',', $_GET['products']);

// Sanitize input
$product_ids = array_map(function($id) {
    return intval($id);
}, $product_ids);

// Limit to 4 products maximum
$product_ids = array_slice($product_ids, 0, 4);

// Prepare placeholders for SQL IN clause
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

// Get date range - default to last 30 days if not specified
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Get previous period for growth calculation
$prev_from_date = date('Y-m-d', strtotime($from_date . ' -30 days'));
$prev_to_date = date('Y-m-d', strtotime($to_date . ' -30 days'));

// Query product information
$product_sql = "SELECT 
                    p.product_id,
                    p.product_name,
                    p.category,
                    p.price,
                    p.cost_price
                FROM 
                    products p
                WHERE 
                    p.product_id IN ($placeholders)";

$stmt = $conn->prepare($product_sql);
$stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
$stmt->execute();
$product_result = $stmt->get_result();

$products = [];
while ($row = $product_result->fetch_assoc()) {
    $products[$row['product_id']] = [
        'id' => $row['product_id'],
        'name' => $row['product_name'],
        'category' => $row['category'],
        'price' => $row['price'],
        'cost' => $row['cost_price'],
        'sales' => 0,
        'profit' => 0,
        'units' => 0,
        'margin' => 0,
        'prev_sales' => 0,
        'growth' => 0
    ];
}

// Query current period sales data
$current_sql = "SELECT 
                    p.product_id,
                    SUM(od.quantity) as total_units,
                    SUM(od.price * od.quantity) as total_sales,
                    SUM((od.price - p.cost_price) * od.quantity) as total_profit
                FROM 
                    order_details od
                JOIN 
                    products p ON od.product_id = p.product_id
                JOIN 
                    orders o ON od.order_id = o.order_id
                WHERE 
                    p.product_id IN ($placeholders)
                    AND o.order_date BETWEEN ? AND ?
                GROUP BY 
                    p.product_id";

$stmt = $conn->prepare($current_sql);
$params = array_merge($product_ids, [$from_date, $to_date]);
$types = str_repeat('i', count($product_ids)) . 'ss';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$current_result = $stmt->get_result();

while ($row = $current_result->fetch_assoc()) {
    if (isset($products[$row['product_id']])) {
        $products[$row['product_id']]['sales'] = $row['total_sales'];
        $products[$row['product_id']]['profit'] = $row['total_profit'];
        $products[$row['product_id']]['units'] = $row['total_units'];
        
        // Calculate margin
        if ($row['total_sales'] > 0) {
            $products[$row['product_id']]['margin'] = ($row['total_profit'] / $row['total_sales']) * 100;
        }
    }
}

// Query previous period sales data for growth calculation
$previous_sql = "SELECT 
                    p.product_id,
                    SUM(od.price * od.quantity) as total_sales
                FROM 
                    order_details od
                JOIN 
                    products p ON od.product_id = p.product_id
                JOIN 
                    orders o ON od.order_id = o.order_id
                WHERE 
                    p.product_id IN ($placeholders)
                    AND o.order_date BETWEEN ? AND ?
                GROUP BY 
                    p.product_id";

$stmt = $conn->prepare($previous_sql);
$params = array_merge($product_ids, [$prev_from_date, $prev_to_date]);
$types = str_repeat('i', count($product_ids)) . 'ss';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$previous_result = $stmt->get_result();

while ($row = $previous_result->fetch_assoc()) {
    if (isset($products[$row['product_id']])) {
        $products[$row['product_id']]['prev_sales'] = $row['total_sales'];
        
        // Calculate growth rate
        if ($row['total_sales'] > 0) {
            $current_sales = $products[$row['product_id']]['sales'];
            $previous_sales = $row['total_sales'];
            $products[$row['product_id']]['growth'] = (($current_sales - $previous_sales) / $previous_sales) * 100;
        } else if ($products[$row['product_id']]['sales'] > 0) {
            // If previous sales were 0 but current sales exist, set growth to 100%
            $products[$row['product_id']]['growth'] = 100;
        }
    }
}

// Format products array for JSON response
$response = [
    'products' => array_values($products)
];

// Return JSON response
echo json_encode($response);