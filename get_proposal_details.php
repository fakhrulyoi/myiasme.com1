<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid proposal ID.</div>';
    exit;
}

$proposal_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get current user's team_id
$sql_team = "SELECT team_id FROM users WHERE id = ?";
$stmt_team = $dbconn->prepare($sql_team);
$stmt_team->bind_param("i", $user_id);
$stmt_team->execute();
$team_result = $stmt_team->get_result();
$team_data = $team_result->fetch_assoc();
$team_id = $team_data['team_id'];

// Get proposal details - REMOVED the team_id restriction to allow cross-team viewing
$sql = "SELECT 
    pp.*,
    u.username as proposed_by,
    t.team_name
FROM product_proposals pp
JOIN users u ON pp.user_id = u.id
JOIN teams t ON pp.team_id = t.team_id
WHERE pp.id = ?";

$stmt = $dbconn->prepare($sql);
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Proposal not found.</div>';
    exit;
}

$proposal = $result->fetch_assoc();

// Calculate profit margin
$cost_price = floatval($proposal['cost_price']);
$selling_price = floatval($proposal['selling_price']);
$profit_margin = ($cost_price > 0) ? (($selling_price - $cost_price) / $selling_price) * 100 : 0;

// Output HTML for the proposal details
?>

<div class="status-info" style="margin-bottom: 20px; padding: 15px; border-radius: 5px; background-color: #f8f9fa;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin: 0;">Status: 
            <span class="status-badge status-<?php echo htmlspecialchars($proposal['status']); ?>">
                <?php echo ucfirst(htmlspecialchars($proposal['status'])); ?>
            </span>
        </h4>
        <span class="proposal-date">Submitted on: <?php echo date('F j, Y', strtotime($proposal['proposed_date'])); ?></span>
    </div>
</div>

<div class="detail-row" style="display: flex; margin-bottom: 20px;">
    <div style="flex: 1; padding-right: 15px;">
        <h4>Product Name</h4>
        <p class="detail-product-name"><?php echo htmlspecialchars($proposal['product_name']); ?></p>
    </div>
    <div style="flex: 1;">
        <h4>Category</h4>
        <p class="detail-category"><?php echo htmlspecialchars($proposal['category']); ?></p>
    </div>
</div>

<div class="detail-row" style="display: flex; margin-bottom: 20px;">
    <div style="flex: 1; padding-right: 15px;">
        <h4>Estimated Cost Price</h4>
        <p class="detail-cost">RM <?php echo number_format($proposal['cost_price'], 2); ?></p>
    </div>
    <div style="flex: 1;">
        <h4>Estimated Selling Price</h4>
        <p class="detail-price">RM <?php echo number_format($proposal['selling_price'], 2); ?></p>
    </div>
    <div style="flex: 1;">
        <h4>Profit Margin</h4>
        <p class="detail-margin"><?php echo number_format($profit_margin, 1); ?>%</p>
    </div>
</div>

<div class="detail-row" style="display: flex; margin-bottom: 20px;">
    <div style="flex: 1; padding-right: 15px;">
        <h4>Proposed By</h4>
        <p class="detail-proposer"><?php echo htmlspecialchars($proposal['proposed_by']); ?></p>
    </div>
    <div style="flex: 1;">
        <h4>Team</h4>
        <p class="detail-team"><?php echo htmlspecialchars($proposal['team_name']); ?></p>
    </div>
</div>

<div class="detail-section" style="margin-bottom: 20px;">
    <h4>Product Description</h4>
    <p class="detail-description"><?php echo nl2br(htmlspecialchars($proposal['product_description'])); ?></p>
</div>

<div class="detail-section" style="margin-bottom: 20px;">
    <h4>TikTok/Product Link</h4>
    <p class="detail-link">
        <a href="<?php echo htmlspecialchars($proposal['tiktok_link']); ?>" target="_blank">
            <?php echo htmlspecialchars($proposal['tiktok_link']); ?>
        </a>
    </p>
</div>

<?php if (!empty($proposal['product_image'])): ?>
<div class="detail-section" style="margin-bottom: 20px;">
    <h4>Product Image</h4>
    <img src="<?php echo htmlspecialchars($proposal['product_image']); ?>" style="max-width: 100%; max-height: 300px;">
</div>
<?php endif; ?>

<?php if (!empty($proposal['admin_feedback'])): ?>
<div class="admin-feedback" style="margin-top: 30px; padding: 15px; border-radius: 5px; background-color: #e0f2fe; border-left: 4px solid #0ea5e9;">
    <h4>Admin Feedback</h4>
    <p class="detail-feedback"><?php echo nl2br(htmlspecialchars($proposal['admin_feedback'])); ?></p>
</div>
<?php endif; ?>

<?php
// If this proposal was approved and then added as a product, show link to the product
// Commented out because the 'proposal_id' column doesn't exist in the products table
/*
if ($proposal['status'] === 'approved') {
    // Check if product exists in products table
    $sql_product = "SELECT id FROM products WHERE proposal_id = ?";
    $stmt_product = $dbconn->prepare($sql_product);
    $stmt_product->bind_param("i", $proposal_id);
    $stmt_product->execute();
    $product_result = $stmt_product->get_result();
    
    if ($product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        echo '<div class="alert alert-success" style="margin-top: 20px;">
            <i class="fas fa-check-circle"></i>
            This proposal has been converted to a product. 
            <a href="view_product.php?id=' . $product['id'] . '" class="btn btn-primary btn-sm" style="margin-left: 10px;">
                <i class="fas fa-external-link-alt"></i> View Product
            </a>
        </div>';
    }
}
*/
?>