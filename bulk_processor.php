<?php
// bulk_processor.php - Process multiple products at once from a single Excel file

// Include the SimpleXLSX library
require_once('libraries/SimpleXLSX.php');

// Database connection
require 'dbconn_productProfit.php';

// Function to get all product information
function getAllProducts() {
    $products = [
        'IASFLIP 3 - Racun Rumput' => ['code' => '3', 'cost' => 8],
        'IASFLIP 4 - Jam Nadi Pro' => ['code' => '4', 'cost' => 20],
        'IASFLIP 8 - Simen' => ['code' => '8', 'cost' => 3.5],
        'IASFLIP C1 - PAM JAMBAN' => ['code' => 'C1', 'cost' => 8],
        'IASFLIP C2 - BAJA' => ['code' => 'C2', 'cost' => 4],
        'IASFLIP 3B - Baja akar' => ['code' => '3B', 'cost' => 3],
        'IASFLIP 3C - Baja bunga' => ['code' => '3C', 'cost' => 6],
        'IASFLIP D3 - Topeng Muka' => ['code' => 'D3', 'cost' => 10],
        'IASFLIP E1 - Minyak Resdung' => ['code' => 'E1', 'cost' => 10],
        'IASFLIP E2 - Span pintu' => ['code' => 'E2', 'cost' => 3.5],
        'IASFLIP 5 - Roadtax holder' => ['code' => '5', 'cost' => 3],
        'IASFLIP 2 - Box pakaian' => ['code' => '2', 'cost' => 13],
        'IASFLIP 1 - Tuala' => ['code' => '1', 'cost' => 12],
        'IASFLIP 3C - Ubat nyamuk' => ['code' => '3C', 'cost' => 1],
        'IASFLIP 3A - Garam laut' => ['code' => '3A', 'cost' => 9],
        'IASFLIP D1 - Sabun MInyak Zaitun' => ['code' => 'D1', 'cost' => 7],
        'IASFLIP 8B - Sticker subjek' => ['code' => '8B', 'cost' => 4],
        'IASFLIP 3E - Twister disc' => ['code' => '3E', 'cost' => 8],
        'IASFLIP 8A - Jadual waktu & sticker' => ['code' => '8A', 'cost' => 8],
        'IASFLIP 8C - Bingkai' => ['code' => '8C', 'cost' => 2],
        'IASFLIP 6 - Sempurnakan solat' => ['code' => '6', 'cost' => 8],
        'IASFLIP 8D - Koleksi surah pilihan' => ['code' => '8D', 'cost' => 13],
        'IASFLIP 7 - Spray Aircond' => ['code' => '7', 'cost' => 5],
        'IASFLIP E3 - Tekanan Tayar' => ['code' => 'E3', 'cost' => 9],
        'IASFLIP 3G - Spaghetti' => ['code' => '3G', 'cost' => 2],
        'IASFLIP 8E - Rempah ayam' => ['code' => '8E', 'cost' => 10],
        'IASFLIP 3F - Sambal Serbaguna' => ['code' => '3F', 'cost' => 5],
        'IASFLIP C5 - Aglio olio' => ['code' => 'C5', 'cost' => 10],
        'IASFLIP C4 - Ayam madu' => ['code' => 'C4', 'cost' => 10],
        'IASFLIP 3D - Sambal bahau' => ['code' => '3D', 'cost' => 8],
        'IASFLIP F1 - PENDRIVE RAYA' => ['code' => 'F1', 'cost' => 10],
    ];
    
    return $products;
}

// Function to process Excel file and extract data for all products
function processExcelFile($filePath, $startDate, $endDate, $adsSpendTotal) {
    try {
        $xlsx = new SimpleXLSX($filePath);
        
        $allProducts = getAllProducts();
        $results = [];
        
        // Initialize counters for each product
        foreach ($allProducts as $productName => $productInfo) {
            $results[$productName] = [
                'purchase' => 0,
                'units_sold' => 0,
                'total_sales' => 0,
                'actual_cost' => $productInfo['cost'],
                'code' => $productInfo['code'],
                'sales_breakdown' => []
            ];
        }
        
        // Define the patterns to match in the sales data
        $patterns = [
            'UNIT' => '/(\d+)\s+UNIT.*?R\.M\.(\d+)/i',
            'SIMEN' => '/(\d+)\s+SIMEN.*?R\.M\.(\d+)/i',
            'BOTOL' => '/(\d+)\s+BOTOL.*?R\.M\.(\d+)/i',
            'HELAI' => '/(\d+)\s+HELAI.*?R\.M\.(\d+)/i',
            'KOTAK' => '/(\d+)\s+KOTAK.*?R\.M\.(\d+)/i',
            'PAKET' => '/(\d+)\s+PAKET.*?R\.M\.(\d+)/i'
        ];
        
        // Loop through the rows in the spreadsheet
        foreach ($xlsx->rows() as $rowData) {
            // Check if this row contains the date
            $date = null;
            if (isset($rowData[0]) && !empty($rowData[0])) {
                // Try to parse the date in various formats
                if (is_numeric($rowData[0]) && strlen($rowData[0]) > 5) {
                    // This might be an Excel date serial number
                    $unixTimestamp = ($rowData[0] - 25569) * 86400;
                    $date = date('Y-m-d', $unixTimestamp);
                } else {
                    $possibleDate = strtotime($rowData[0]);
                    if ($possibleDate !== false) {
                        $date = date('Y-m-d', $possibleDate);
                    }
                }
            }
            
            // If date is within range and row contains sales data
            if ($date !== null && $date >= $startDate && $date <= $endDate) {
                $salesText = implode(' ', $rowData); // Combine all cells for easier searching
                
                // Check each product
                foreach ($allProducts as $productName => $productInfo) {
                    $productCode = $productInfo['code'];
                    
                    // Check if this row contains this product's code or name
                    if (
                        stripos($salesText, " $productCode ") !== false || 
                        stripos($salesText, "IASFLIP $productCode") !== false ||
                        stripos($salesText, "IASFLIP$productCode") !== false ||
                        stripos($salesText, str_replace(' - ', ' ', $productName)) !== false
                    ) {
                        // Count this as a purchase for this product
                        $results[$productName]['purchase']++;
                        
                        // Apply pattern matching to extract units and prices
                        foreach ($patterns as $type => $pattern) {
                            if (preg_match($pattern, $salesText, $matches)) {
                                $units = intval($matches[1]);
                                $price = floatval($matches[2]);
                                $totalPrice = $price + 10; // Adding RM10 for POS
                                
                                $results[$productName]['units_sold'] += $units;
                                $results[$productName]['total_sales'] += $totalPrice;
                                
                                // Store detailed breakdown for reporting
                                $results[$productName]['sales_breakdown'][] = [
                                    'date' => $date,
                                    'type' => $type,
                                    'units' => $units,
                                    'price' => $price,
                                    'total' => $totalPrice
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Calculate profit metrics for each product
        $totalUnitsSold = 0;
        $totalRevenue = 0;
        $totalCost = 0;
        
        foreach ($results as $productName => &$productData) {
            // Calculate total cost
            $productData['total_cost'] = $productData['units_sold'] * $productData['actual_cost'];
            
            // Calculate profit
            $productData['profit'] = $productData['total_sales'] - $productData['total_cost'];
            
            // Calculate profit margin
            if ($productData['total_sales'] > 0) {
                $productData['profit_margin'] = ($productData['profit'] / $productData['total_sales']) * 100;
            } else {
                $productData['profit_margin'] = 0;
            }
            
            // Add to totals
            $totalUnitsSold += $productData['units_sold'];
            $totalRevenue += $productData['total_sales'];
            $totalCost += $productData['total_cost'];
        }
        
        // Calculate overall metrics
        $totalProfit = $totalRevenue - $totalCost;
        $totalProfitAfterAds = $totalProfit - $adsSpendTotal;
        $overallProfitMargin = ($totalRevenue > 0) ? ($totalProfit / $totalRevenue) * 100 : 0;
        $overallProfitMarginAfterAds = ($totalRevenue > 0) ? ($totalProfitAfterAds / $totalRevenue) * 100 : 0;
        
        // Add summary data to results
        $results['summary'] = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_units_sold' => $totalUnitsSold,
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'ads_spend' => $adsSpendTotal,
            'profit_after_ads' => $totalProfitAfterAds,
            'profit_margin' => $overallProfitMargin,
            'profit_margin_after_ads' => $overallProfitMarginAfterAds
        ];
        
        return $results;
        
    } catch (Exception $e) {
        // Handle any errors that occurred during processing
        return ['error' => $e->getMessage()];
    }
}

// Function to save results to database
function saveResultsToDatabase($results) {
    global $conn;
    
    if (!isset($results['summary'])) {
        return false;
    }
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // First, insert the summary record
        $summaryStmt = $conn->prepare("INSERT INTO analysis_summary 
            (start_date, end_date, total_units_sold, total_revenue, total_cost, 
            total_profit, ads_spend, profit_after_ads, profit_margin, profit_margin_after_ads) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
        $summaryStmt->execute([
            $results['summary']['start_date'],
            $results['summary']['end_date'],
            $results['summary']['total_units_sold'],
            $results['summary']['total_revenue'],
            $results['summary']['total_cost'],
            $results['summary']['total_profit'],
            $results['summary']['ads_spend'],
            $results['summary']['profit_after_ads'],
            $results['summary']['profit_margin'],
            $results['summary']['profit_margin_after_ads']
        ]);
        
        $summaryId = $conn->lastInsertId();
        
        // Now insert the product details
        $productStmt = $conn->prepare("INSERT INTO product_analysis 
            (summary_id, product_name, product_code, units_sold, total_sales, 
            actual_cost, total_cost, profit, profit_margin, purchase_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($results as $productName => $productData) {
            // Skip the summary entry
            if ($productName === 'summary') {
                continue;
            }
            
            $productStmt->execute([
                $summaryId,
                $productName,
                $productData['code'],
                $productData['units_sold'],
                $productData['total_sales'],
                $productData['actual_cost'],
                $productData['total_cost'],
                $productData['profit'],
                $productData['profit_margin'],
                $productData['purchase']
            ]);
            
            // Optionally, save detailed sales breakdown
            // This depends on your database schema
        }
        
        // Commit the transaction
        $conn->commit();
        return $summaryId;
        
    } catch (Exception $e) {
        // Rollback the transaction if an error occurs
        $conn->rollBack();
        return ['error' => $e->getMessage()];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $uploadDir = 'uploads/';
    $uploadFile = $uploadDir . basename($_FILES['excel_file']['name']);
    
    // Ensure the upload directory exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadFile)) {
        // Get form data
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $adsSpend = floatval($_POST['ads_spend']);
        
        // Process the Excel file
        $results = processExcelFile($uploadFile, $startDate, $endDate, $adsSpend);
        
        if (isset($results['error'])) {
            $errorMessage = $results['error'];
        } else {
            // Save results to database
            $saveResult = saveResultsToDatabase($results);
            
            if (isset($saveResult['error'])) {
                $errorMessage = $saveResult['error'];
            } else {
                $successMessage = "Analysis completed successfully. Summary ID: " . $saveResult;
            }
        }
    } else {
        $errorMessage = "Failed to upload file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Product Analysis</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Bulk Product Analysis</h1>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="excel_file">Excel File</label>
                <input type="file" class="form-control" id="excel_file" name="excel_file" required>
                <small class="form-text text-muted">Please upload an Excel file containing sales data.</small>
            </div>
            
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" required>
            </div>
            
            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" required>
            </div>
            
            <div class="form-group">
                <label for="ads_spend">Ads Spend (RM)</label>
                <input type="number" step="0.01" class="form-control" id="ads_spend" name="ads_spend" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Process File</button>
        </form>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>