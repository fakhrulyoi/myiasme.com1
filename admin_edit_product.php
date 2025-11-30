<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Redirect if not admin
if (!$is_admin) {
    header("Location: index.php");
    exit();
}

// Fetch product data for the given ID
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM products WHERE id = ?";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        echo "<script>alert('Product not found.'); window.location.href='all_products.php';</script>";
        exit;
    }
}

// Handle form submission for updating product data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $sku = trim($_POST['sku']); // Added SKU field
    $productName = $_POST['productName'];
    $adsSpend = floatval($_POST['adsSpend']);
    $purchase = intval($_POST['purchase']);
    $unitSold = intval($_POST['unitSold']);
    $actualCost = floatval($_POST['actualCost']);
    $sales = floatval($_POST['sales']);
    $dateAdded = $_POST['dateAdded'];
    $pakej = $_POST['pakej'] ?? '';
    $team_id = intval($_POST['team_id']);

    // Perform calculations
    $adsSpendWithSST = $adsSpend;
    $cpp = ($purchase > 0) ? ($adsSpendWithSST / $purchase) : 0;
    $itemCost = $unitSold * $actualCost;
    $cod = $purchase * 10;
    $cogs = $itemCost + $cod;
    $profit = $sales - $adsSpendWithSST - $cogs;

    // Update query with SKU field
    $sql = "UPDATE products 
            SET sku = ?, product_name = ?, ads_spend = ?, purchase = ?, cpp = ?, unit_sold = ?, actual_cost = ?, 
            item_cost = ?, cod = ?, sales = ?, profit = ?, created_at = ?, pakej = ?, team_id = ? 
            WHERE id = ?";
    $stmt = $dbconn->prepare($sql);
    
    // Fix: Make sure the type definition matches the number of parameters
    // s = string, d = double, i = integer
    // 15 parameters total (14 SET values + 1 WHERE condition)
    $stmt->bind_param("ssddiidddddssii", $sku, $productName, $adsSpendWithSST, $purchase, $cpp, $unitSold, $actualCost, $itemCost, $cod, $sales, $profit, $dateAdded, $pakej, $team_id, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Product updated successfully!'); window.location.href='all_products.php';</script>";
    } else {
        echo "<script>alert('Error updating product: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Get teams for dropdown
$teams_query = "SELECT * FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_query);
$teams = [];
while ($team = $teams_result->fetch_assoc()) {
    $teams[] = $team;
}

// First, let's check what column exists in the teams table
$check_column = $dbconn->query("SHOW COLUMNS FROM teams");
$column_names = [];
while($row = $check_column->fetch_assoc()) {
    $column_names[] = $row['Field'];
}

// Determine the correct primary key
$team_pk = in_array('id', $column_names) ? 'id' : 'team_id';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Dr Ecomm Formula</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            --primary-color: #2c3e50;
            --primary-light: #34495e;
            --secondary-color: #3498db;
            --secondary-light: #5dade2;
            --accent-color: #1abc9c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #ecf0f1;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: var(--dark-text);
        }
        
        .main-content {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
        }
        
        header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        
        header h1 i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        header a {
            text-decoration: none;
            color: var(--secondary-color);
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        header a i {
            margin-right: 5px;
        }
        
        form {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-text);
            font-weight: 500;
            font-size: 14px;
        }
        
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
        }
        
        input[readonly] {
            background-color: var(--light-bg);
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .btn i {
            margin-right: 6px;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-light);
        }
        
        .btn-secondary {
            background-color: var(--light-bg);
            color: var(--dark-text);
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <header>
            <h1><i class="fas fa-edit"></i> Edit Product</h1>
            <a href="all_products.php"><i class="fas fa-arrow-left"></i> Back to All Products</a>
        </header>
        <main>
            <form method="POST" action="admin_edit_product.php">
                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">

                <div class="form-group">
                    <label for="sku">SKU</label>
                    <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="productName">Product Name</label>
                    <input type="text" id="productName" name="productName" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="adsSpend">Ads Spend (RM)</label>
                    <input type="number" id="adsSpend" name="adsSpend" step="0.01" value="<?php echo $product['ads_spend']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="purchase">Purchase</label>
                    <input type="number" id="purchase" name="purchase" value="<?php echo $product['purchase']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="unitSold">Units Sold</label>
                    <input type="number" id="unitSold" name="unitSold" value="<?php echo $product['unit_sold']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="actualCost">Actual Cost (Per Unit)</label>
                    <input type="number" id="actualCost" name="actualCost" step="0.01" value="<?php echo $product['actual_cost']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="sales">Sales (RM)</label>
                    <input type="number" id="sales" name="sales" step="0.01" value="<?php echo $product['sales']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="dateAdded">Date</label>
                    <input type="date" id="dateAdded" name="dateAdded" value="<?php echo $product['created_at']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="pakej">Pakej</label>
                    <input type="text" id="pakej" name="pakej" value="<?php echo htmlspecialchars($product['pakej'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="team_id">Team</label>
                    <select id="team_id" name="team_id" required>
                        <option value="">Select Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team[$team_pk]; ?>" <?php if ($product['team_id'] == $team[$team_pk]) echo 'selected'; ?>>
                                <?php echo $team['team_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <a href="all_products.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Product</button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>