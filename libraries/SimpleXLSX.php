<?php
// excel_processor.php - A simple file to process Excel file without external dependencies

// Function to get product cost based on product name
function getProductCost($productName) {
    // Extract the product name without the IASFLIP code
    $nameOnly = '';
    if (preg_match('/- (.+)$/', $productName, $matches)) {
        $nameOnly = trim($matches[1]);
    }
    
    // Mapping of product costs based on the sidebar
    $productCosts = [
        'Racun Rumput' => 8,
        'Jam Nadi Pro' => 20,
        'Simen' => 3.5,
        'PAM JAMBAN' => 8,
        'BAJA' => 4,
        'Baja akar' => 3,
        'Baja bunga' => 6,
        'Topeng Muka' => 10,
        'Minyak Resdung' => 10,
        'Span pintu' => 3.5,
        'Roadtax holder' => 3,
        'Box pakaian' => 13,
        'Tuala' => 12,
        'Ubat nyamuk' => 1,
        'Garam laut' => 9,
        'Sabun MInyak Zaitun' => 7,
        'Sticker subjek' => 4,
        'Jadual waktu & sticker' => 8,
        'Twister disc' => 8,
        'Bingkai' => 2,
        'Sempurnakan solat' => 8,
        'koleksi surah pilihan' => 13,
        'Spray aircond' => 5,
        'Tekanan tayar' => 9,
        'Spaghetti' => 2,
        'Rempah ayam' => 10,
        'Sambal Serbaguna' => 5,
        'Aglio olio' => 10,
        'Ayam madu' => 10,
        'Sambal bahau' => 8,
        'PENDRIVE RAYA' => 10
    ];
    
    // Look through the costs array for a matching product
    foreach ($productCosts as $product => $cost) {
        // Use fuzzy matching to find similar product names
        if (stripos($nameOnly, $product) !== false || stripos($product, $nameOnly) !== false) {
            return $cost;
        }
    }
    
    // Default value if product not found
    return 0;
}

// Function to process text-based sales data without needing Excel parsing
function processSalesData($text, $productName, $startDate = null, $endDate = null) {
    $lines = explode("\n", $text);
    
    $data = [];
    $purchaseCount = 0;
    $unitsSold = 0;
    $totalSales = 0;
    $salesBreakdown = [];
    
    // Define the patterns to match in the sales data
    $patterns = [
        'UNIT' => '/(\d+)\s+UNIT.*?R\.M\.(\d+)/i',
        'SIMEN' => '/(\d+)\s+SIMEN.*?R\.M\.(\d+)/i',
        'BOTOL' => '/(\d+)\s+BOTOL.*?R\.M\.(\d+)/i',
        'HELAI' => '/(\d+)\s+HELAI.*?R\.M\.(\d+)/i',
        'KOTAK' => '/(\d+)\s+KOTAK.*?R\.M\.(\d+)/i',
        'PAKET' => '/(\d+)\s+PAKET.*?R\.M\.(\d+)/i'
    ];
    
    // Extract the product code from the product name (e.g., "IASFLIP C1" from "IASFLIP C1 - PAM JAMBAN")
    $productCode = "";
    if (preg_match('/IASFLIP\s+([A-Z0-9]+)/i', $productName, $matches)) {
        $productCode = $matches[1];
    }
    
    $currentDate = null;
    
    // Process each line
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        // Check if this line might be a date
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $line) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $line)) {
            // This looks like a date format
            $currentDate = date('Y-m-d', strtotime($line));
            continue;
        }
        
        // If we have date filters and no current date or date is outside range, skip
        if (($startDate || $endDate) && 
            (!$currentDate || 
             ($startDate && $currentDate < $startDate) ||
             ($endDate && $currentDate > $endDate))) {
            continue;
        }
        
        // Check if this line is for our product
        if (stripos($line, $productCode) !== false || stripos($line, str_replace(' - ', ' ', $productName)) !== false) {
            $purchaseCount++; // Count each relevant entry as a purchase
            
            // Apply pattern matching to extract units and prices
            foreach ($patterns as $type => $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $units = intval($matches[1]);
                    $price = floatval($matches[2]);
                    $totalPrice = $price + 10; // Adding RM10 for POS as mentioned
                    
                    $unitsSold += $units;
                    $totalSales += $totalPrice;
                    
                    // Store detailed breakdown for reporting
                    $salesBreakdown[] = [
                        'date' => $currentDate ?: date('Y-m-d'),
                        'type' => $type,
                        'units' => $units,
                        'price' => $price,
                        'total' => $totalPrice
                    ];
                }
            }
        }
    }
    
    // Get the actual cost per unit from the sidebar product listing
    $actualCost = getProductCost($productName);
    
    return [
        'purchase' => $purchaseCount,
        'units_sold' => $unitsSold,
        'total_sales' => $totalSales,
        'actual_cost' => $actualCost,
        'sales_breakdown' => $salesBreakdown
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Data Processor</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .textarea-container {
            width: 100%;
            margin-bottom: 20px;
        }
        .textarea-container textarea {
            width: 100%;
            min-height: 300px;
            padding: 10px;
            font-family: monospace;
        }
        .upload-container {
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .results-container {
            background-color: #eaf7ea;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .breakdown-table th, .breakdown-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .breakdown-table th {
            background-color: #f2f2f2;
        }
        
        .btn-submit-final {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-submit-final:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <h2>Dashboard</h2>
            <ul>
                <li><a href="index.php#add-product">Add Product</a></li>
                <li><a href="index.php#download-report">Download Report</a></li>
                <li><a href="index.php#product-summary">Product Summary</a></li>
                <li><a href="sales_calculator.php">Sales Calculator</a></li>
                <li><a href="excel_processor.php"><strong>Sales Data Processor</strong></a></li>
            </ul>
        </nav>
        
        <div class="main-content">
            <header>
                <h1>Sales Data Processor</h1>
                <p>Paste your sales data and select a product to automatically calculate purchases, units sold, and sales.</p>
            </header>
            
            <main>
                <section>
                    <div class="upload-container">
                        <h2>Process Sales Data</h2>
                        <?php if (isset($error)): ?>
                            <div style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <label for="productName">Product Name</label>
                            <select id="productName" name="productName" required>
                                <option value="">-- Select Product Name --</option>
                                <option value="IASFLIP 3 - Racun Rumput">IASFLIP 3 - Racun Rumput</option>
                                <option value="IASFLIP 4 - Jam Nadi Pro">IASFLIP 4 - Jam Nadi Pro</option>
                                <option value="IASFLIP 8 - Simen">IASFLIP 8 - Simen</option>
                                <option value="IASFLIP C1 - PAM JAMBAN">IASFLIP C1 - PAM JAMBAN</option>
                                <option value="IASFLIP C2 - BAJA">IASFLIP C2 - BAJA</option>
                                <option value="IASFLIP 3B - Baja akar">IASFLIP 3B - Baja akar</option>
                                <option value="IASFLIP 3C - Baja bunga">IASFLIP 3C - Baja bunga</option>
                                <option value="IASFLIP D3 - Topeng Muka">IASFLIP D3 - Topeng Muka</option>
                                <option value="IASFLIP E1 - Minyak Resdung">IASFLIP E1 - Minyak Resdung</option>
                                <option value="IASFLIP E2 - Span pintu">IASFLIP E2 - Span pintu</option>
                                <option value="IASFLIP 5 - Roadtax holder">IASFLIP 5 - Roadtax holder</option>
                                <option value="IASFLIP 2 - Box pakaian">IASFLIP 2 - Box pakaian</option>
                                <option value="IASFLIP 1 - Tuala">IASFLIP 1 - Tuala</option>
                                <option value="IASFLIP 3C - Ubat nyamuk">IASFLIP 3C - Ubat nyamuk</option>
                                <option value="IASFLIP 3A - Garam laut">IASFLIP 3A - Garam laut</option>
                                <option value="IASFLIP D1 - Sabun MInyak Zaitun">IASFLIP D1 - Sabun MInyak Zaitun</option>
                                <option value="IASFLIP 8B - Sticker subjek">IASFLIP 8B - Sticker subjek</option>
                                <option value="IASFLIP 3E - Twister disc">IASFLIP 3E - Twister disc</option>
                                <option value="IASFLIP 8A - Jadual waktu & sticker">IASFLIP 8A - Jadual waktu & sticker</option>
                                <option value="IASFLIP 8C - Bingkai">IASFLIP 8C - Bingkai</option>
                                <option value="IASFLIP 6 - Sempurnakan solat">IASFLIP 6 - Sempurnakan solat</option>
                                <option value="IASFLIP 8D - Koleksi surah pilihan">IASFLIP 8D - Koleksi surah pilihan</option>
                                <option value="IASFLIP 7 - Spray Aircond">IASFLIP 7 - Spray Aircond</option>
                                <option value="IASFLIP E3 - Tekanan Tayar">IASFLIP E3 - Tekanan Tayar</option>
                                <option value="IASFLIP 3G - Spaghetti">IASFLIP 3G - Spaghetti</option>
                                <option value="IASFLIP 8E - Rempah ayam">IASFLIP 8E - Rempah ayam</option>
                                <option value="IASFLIP 3F - Sambal Serbaguna">IASFLIP 3F - Sambal Serbaguna</option>
                                <option value="IASFLIP C5 - Aglio olio">IASFLIP C5 - Aglio olio</option>
                                <option value="IASFLIP C4 - Ayam madu">IASFLIP C4 - Ayam madu</option>
                                <option value="IASFLIP 3D - Sambal bahau">IASFLIP 3D - Sambal bahau</option>
                                <option value="IASFLIP F1 - PENDRIVE RAYA">IASFLIP F1 - PENDRIVE RAYA</option>
                            </select>
                            
                            <label for="adsSpend">Ads Spend (RM)</label>
                            <input type="number" id="adsSpend" name="adsSpend" step="0.01" required>
                            
                            <label for="startDate">Start Date (Optional)</label>
                            <input type="date" id="startDate" name="startDate">
                            
                            <label for="endDate">End Date</label>
                            <input type="date" id="endDate" name="endDate" required>
                            
                            <div class="textarea-container">
                                <label for="salesData">Paste Your Sales Data Here</label>
                                <textarea id="salesData" name="salesData" placeholder="Copy and paste your sales data here..." required></textarea>
                                <p>Include dates in format YYYY-MM-DD or DD/MM/YYYY before each day's sales entries.</p>
                            </div>
                            
                            <button type="submit" style="margin-top: 15px;">Process Data</button>
                        </form>
                    </div>
                    
                    <?php
                    // Process the form submission
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salesData'])) {
                        $productName = $_POST['productName'] ?? '';
                        $adsSpend = $_POST['adsSpend'] ?? 0;
                        $startDate = !empty($_POST['startDate']) ? $_POST['startDate'] : null;
                        $endDate = $_POST['endDate'] ?? date('Y-m-d');
                        $salesData = $_POST['salesData'] ?? '';
                        
                        // Process the data
                        $productData = processSalesData($salesData, $productName, $startDate, $endDate);
                        
                        $purchase = $productData['purchase'];
                        $unitsSold = $productData['units_sold'];
                        $totalSales = $productData['total_sales'];
                        $actualCost = $productData['actual_cost'];
                        $salesBreakdown = $productData['sales_breakdown'];
                    ?>
                        <div class="results-container">
                            <h2>Extracted Data</h2>
                            <p>Here are the automatically extracted details for <strong><?php echo htmlspecialchars($productName); ?></strong>:</p>
                            
                            <form method="POST" action="save_product.php">
                                <input type="hidden" name="productName" value="<?php echo htmlspecialchars($productName); ?>">
                                <input type="hidden" name="adsSpend" value="<?php echo htmlspecialchars($adsSpend); ?>">
                                <input type="hidden" name="dateAdded" value="<?php echo htmlspecialchars($endDate); ?>">
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                                    <div style="flex: 1; min-width: 200px;">
                                        <label for="purchase">Purchase:</label>
                                        <input type="number" id="purchase" name="purchase" value="<?php echo $purchase; ?>" readonly>
                                    </div>
                                    
                                    <div style="flex: 1; min-width: 200px;">
                                        <label for="unitSold">Units Sold:</label>
                                        <input type="number" id="unitSold" name="unitSold" value="<?php echo $unitsSold; ?>" readonly>
                                    </div>
                                    
                                    <div style="flex: 1; min-width: 200px;">
                                        <label for="actualCost">Actual Cost (Per Unit):</label>
                                        <input type="number" id="actualCost" name="actualCost" step="0.01" value="<?php echo $actualCost; ?>" readonly>
                                    </div>
                                    
                                    <div style="flex: 1; min-width: 200px;">
                                        <label for="sales">Sales (RM):</label>
                                        <input type="number" id="sales" name="sales" step="0.01" value="<?php echo $totalSales; ?>" readonly>
                                    </div>
                                </div>
                                
                                <h3 style="margin-top: 20px;">Sales Breakdown</h3>
                                <table class="breakdown-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Units</th>
                                            <th>Base Price (RM)</th>
                                            <th>Total with POS (RM)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($salesBreakdown as $sale): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sale['date']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['type']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['units']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['price']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['total']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <button type="submit" class="btn-submit-final" style="margin-top: 20px;">Add to Product Database</button>
                            </form>
                        </div>
                    <?php } ?>
                </section>
            </main>
        </div>
    </div>
</body>
</html>