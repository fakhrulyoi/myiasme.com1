<?php
// Function to calculate totals
function calculate_totals($data) {
    $pattern = '/(\d+)\s+\w+\s+\(.*\)\s*-\s*RM(\d+)/';

    $total_frequency = 0;
    $total_units = 0;
    $total_price = 0;

    if (preg_match_all($pattern, $data, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $quantity = intval($match[1]);
            $price = intval($match[2]);

            $total_frequency += 1;
            $total_units += $quantity;
            $total_price += $price;
        }
    }

    return [
        'total_frequency' => $total_frequency,
        'total_units' => $total_units,
        'total_price' => $total_price
    ];
}

// Get posted data
$data = isset($_POST['data']) ? $_POST['data'] : "";

// Calculate if data exists
$totals = ($data) ? calculate_totals($data) : ['total_frequency' => 0, 'total_units' => 0, 'total_price' => 0];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr Ecomm Formula Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function autoCalculate() {
            let data = document.getElementById("data").value;
            let formData = new FormData();
            formData.append("data", data);

            fetch("", { method: "POST", body: formData })
                .then(response => response.text())
                .then(html => {
                    document.open();
                    document.write(html);
                    document.close();
                });
        }
    </script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        textarea { width: 80%; height: 200px; font-size: 16px; }
        .box { margin-top: 10px; font-size: 18px; }
        .dashboard-container { display: flex; }
        .sidebar { width: 20%; background: #f4f4f4; padding: 10px; }
        .main-content { flex: 1; padding: 20px; }
        button { padding: 10px 15px; background: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background: #218838; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <h2>Dashboard</h2>
        <ul>
            <li><a href="#add-product">Add Product</a></li>
            <li><a href="#download-report">Download Report</a></li>
            <li><a href="#product-summary">Product Summary</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1>Dr Ecomm Formula</h1>
        </header>
        <main>

            <!-- Add New Product Form -->
            <section id="add-product">
                <h2>Add New Product</h2>
                <form id="productForm" method="POST" action="save_product.php">
                    <label for="productName">Product Name</label>
                    <select id="productName" name="productName" required>
                        <option value="">-- Select Product Name --</option>
                        <option value="IASFLIP 3 - Racun Rumput">IASFLIP 3 - Racun Rumput</option>
                        <option value="IASFLIP 4 - Jam Nadi Pro">IASFLIP 4 - Jam Nadi Pro</option>
                        <option value="IASFLIP 8 - Simen">IASFLIP 8 - Simen</option>
                    </select>

                    <label for="adsSpend">Ads Spend (RM)</label>
                    <input type="number" id="adsSpend" name="adsSpend" step="0.01" required>

                    <label for="purchase">Purchase</label>
                    <input type="number" id="purchase" name="purchase" required>

                    <label for="unitSold">Units Sold</label>
                    <input type="number" id="unitSold" name="unitSold" required>

                    <label for="actualCost">Actual Cost (Per Unit)</label>
                    <input type="number" id="actualCost" name="actualCost" step="0.01" required>

                    <label for="sales">Sales (RM)</label>
                    <input type="number" id="sales" name="sales" step="0.01" required>

                    <label for="dateAdded">Date</label>
                    <input type="date" id="dateAdded" name="dateAdded" required>

                    <button type="submit">Add Product</button>
                </form>
            </section>

            <!-- Auto Calculate Order Data -->
            <section id="auto-calculate">
                <h2>Auto Calculate Total Orders</h2>
                <textarea id="data" oninput="autoCalculate()"><?php echo htmlspecialchars($data); ?></textarea>

                <div class="box">
                    <p><strong>Total Frequency (Orders):</strong> <?php echo $totals['total_frequency']; ?></p>
                    <p><strong>Total Units:</strong> <?php echo $totals['total_units']; ?></p>
                    <p><strong>Total Price (RM):</strong> <?php echo $totals['total_price']; ?></p>
                </div>
            </section>

            <!-- Download Report -->
            <section id="download-report">
                <h2>Download Report</h2>
                <form action="generate_pdf.php" method="GET">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" required>

                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" required>

                    <button type="submit">Download PDF</button>
                </form>
            </section>

        </main>
    </div>
</div>

</body>
</html>
