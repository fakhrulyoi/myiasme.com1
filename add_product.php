<?php
require 'auth.php';
require 'dbconn_productProfit.php'; // Changed from dbconn.php to match your existing file

// Get all teams for admin selection
$teams = [];
if ($is_admin) {
    $sql_teams = "SELECT * FROM teams ORDER BY team_name";
    $result_teams = $dbconn->query($sql_teams);
    while ($row = $result_teams->fetch_assoc()) {
        $teams[] = $row;
    }
}

include 'navigation.php';
?>

<!-- Page Header -->
<header class="page-header">
    <h1>Add New Product</h1>
</header>

<div class="form-container">
    <h3>Product Details</h3>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        There was an error saving the product. Please try again.
    </div>
    <?php endif; ?>
    
    <form id="productForm" method="POST" action="save_product.php">
        <div class="form-row">
            <div class="form-group">
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
            </div>
            
            <?php if ($is_admin): ?>
            <div class="form-group">
                <label for="team_id">Assign to Team</label>
                <select id="team_id" name="team_id" required>
                    <?php foreach ($teams as $team): ?>
                    <option value="<?php echo $team['id']; ?>"><?php echo $team['team_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="adsSpend">Ads Spend (RM)</label>
                <input type="number" id="adsSpend" name="adsSpend" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="purchase">Purchase</label>
                <input type="number" id="purchase" name="purchase" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="unitSold">Units Sold</label>
                <input type="number" id="unitSold" name="unitSold" required>
            </div>

            <div class="form-group">
                <label for="actualCost">Actual Cost (Per Unit)</label>
                <input type="number" id="actualCost" name="actualCost" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="sales">Sales (RM)</label>
                <input type="number" id="sales" name="sales" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="dateAdded">Date</label>
                <input type="date" id="dateAdded" name="dateAdded" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Product</button>
        </div>
    </form>
</div>

<!-- Calculator Section -->
<div class="form-container">
    <h3>Sales Calculator</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="salesData">Paste Sales Data</label>
            <textarea id="salesData" rows="5" placeholder="Paste your sales data here..."></textarea>
        </div>
    </div>
    <div class="form-actions">
        <button onclick="calculateSales()" class="btn btn-primary">Calculate</button>
    </div>
    
    <!-- Results (Hidden by Default) -->
    <div id="resultsContainer" style="display: none; margin-top: 20px;">
        <h4>Results:</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Total Purchases:</label>
                <input type="text" id="totalPurchase" readonly>
            </div>
            <div class="form-group">
                <label>Total Units Sold:</label>
                <input type="text" id="totalUnits" readonly>
            </div>
            <div class="form-group">
                <label>Total Sales (RM):</label>
                <input type="text" id="totalSales" readonly>
            </div>
        </div>
        <div class="form-actions">
            <button onclick="applyCalculation()" class="btn btn-success">Apply to Form</button>
        </div>
    </div>
</div>

<script>
function calculateSales() {
    let data = document.getElementById('salesData').value.trim().split("\n");
    let totalPurchase = 0;
    let totalUnits = 0;
    let totalSales = 0;

    data.forEach(line => {
        let match = line.match(/(\d+) UNIT.*?R\.M\.(\d+)/i); // Match UNIT sales
        let simenMatch = line.match(/(\d+) SIMEN.*?R\.M\.(\d+)/i); // Match SIMEN sales
        let botolMatch = line.match(/(\d+) BOTOL.*?R\.M\.(\d+)/i); // Match BOTOL sales
        let helaiMatch = line.match(/(\d+) HELAI.*?R\.M\.(\d+)/i); // Match HELAI sales
        let kotakMatch = line.match(/(\d+) KOTAK.*?R\.M\.(\d+)/i); // Match KOTAK sales
        let paketMatch = line.match(/(\d+) PAKET.*?R\.M\.(\d+)/i); // Match PAKET sales

        if (match) {
            let units = parseInt(match[1]);
            let price = parseFloat(match[2]);
            let sales = price + 10; // Adding RM10 POS

            totalPurchase += 1;
            totalUnits += units;
            totalSales += sales;
        }

        if (simenMatch) {
            let simenUnits = parseInt(simenMatch[1]);
            let simenPrice = parseFloat(simenMatch[2]);
            let simenSales = simenPrice + 10; // Adding RM10 POS

            totalPurchase += 1;
            totalUnits += simenUnits; 
            totalSales += simenSales;
        }

        if (botolMatch) {
            let botolUnits = parseInt(botolMatch[1]);
            let botolPrice = parseFloat(botolMatch[2]);
            let botolSales = botolPrice + 10; // Adding RM10 POS

            totalPurchase += 1;
            totalUnits += botolUnits;
            totalSales += botolSales;
        }

        if (helaiMatch) {
            let helaiUnits = parseInt(helaiMatch[1]);
            let helaiPrice = parseFloat(helaiMatch[2]);
            let helaiSales = helaiPrice + 10; // Adding RM10 POS

            totalPurchase += 1;
            totalUnits += helaiUnits;
            totalSales += helaiSales;
        }

        if (kotakMatch) {
            let kotakUnits = parseInt(kotakMatch[1]);
            let kotakPrice = parseFloat(kotakMatch[2]);
            let kotakSales = kotakPrice + 10; // Adding RM10 POS

            totalPurchase += 1;
            totalUnits += kotakUnits;
            totalSales += kotakSales;
        }

        if (paketMatch) {
            let paketUnits = parseInt(paketMatch[1]);
            let paketPrice = parseFloat(paketMatch[2]);
            let paketSales = paketPrice + 10; // Adding RM10 POS

            totalPurchase += 1;
            totalUnits += paketUnits;
            totalSales += paketSales;
        }
    });

    // Show results after calculation
    document.getElementById('resultsContainer').style.display = "block";
    document.getElementById('totalPurchase').value = totalPurchase;
    document.getElementById('totalUnits').value = totalUnits;
    document.getElementById('totalSales').value = totalSales.toFixed(2);
}

function applyCalculation() {
    // Apply calculated values to the form
    document.getElementById('purchase').value = document.getElementById('totalPurchase').value;
    document.getElementById('unitSold').value = document.getElementById('totalUnits').value;
    document.getElementById('sales').value = document.getElementById('totalSales').value;
}
</script>

</main>
</div>
</body>
</html>