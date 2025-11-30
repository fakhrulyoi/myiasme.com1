<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if user is admin
if (!$is_admin) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> You do not have permission to view this content.</div>';
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Invalid proposal ID.</div>';
    exit;
}

$proposal_id = intval($_GET['id']);

// Get proposal details
$sql = "SELECT 
    pp.*,
    u.username as proposed_by,
    t.team_name,
    CASE 
        WHEN pp.admin_id IS NOT NULL THEN (SELECT username FROM users WHERE id = pp.admin_id)
        ELSE NULL
    END as admin_name
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
$profit_amount = $selling_price - $cost_price;

// Determine margin class for visual indicator
$margin_class = 'low-margin';
if ($profit_margin >= 30) {
    $margin_class = 'high-margin';
} elseif ($profit_margin >= 15) {
    $margin_class = 'medium-margin';
}

// Output the redesigned proposal details
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Proposal Details</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #4361ee;
      --primary-light: #4895ef;
      --primary-dark: #3f37c9;
      --success: #2ec4b6;
      --danger: #e63946;
      --warning: #fca311;
      --dark: #212529;
      --light: #f8f9fa;
      --gray: #6c757d;
      --gray-light: #e9ecef;
      --gray-dark: #495057;
      --border-radius: 0.75rem;
      --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
      background-color: #f5f7fa;
      color: var(--dark);
      line-height: 1.6;
    }

    .modal-header {
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      padding: 1.5rem;
      border-radius: var(--border-radius) var(--border-radius) 0 0;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .modal-header h2 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;
      color: white;
      display: flex;
      align-items: center;
    }

    .modal-header h2 i {
      margin-right: 0.75rem;
    }

    .modal-body {
      padding: 0;
    }

    .close-button {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      border-radius: 50%;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: var(--transition);
    }

    .close-button:hover {
      background: rgba(255, 255, 255, 0.3);
    }

    .status-banner {
      padding: 1.25rem 1.5rem;
      background-color: #f8f9fa;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .status-content {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }

    .status-main {
      display: flex;
      align-items: center;
    }

    .status-label {
      font-weight: 600;
      margin-right: 0.75rem;
    }

    .status-badge {
      display: inline-block;
      padding: 0.4rem 1rem;
      border-radius: 2rem;
      font-size: 0.875rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-approved {
      background-color: rgba(46, 196, 182, 0.15);
      color: var(--success);
    }

    .status-pending {
      background-color: rgba(252, 163, 17, 0.15);
      color: var(--warning);
    }

    .status-rejected {
      background-color: rgba(230, 57, 70, 0.15);
      color: var(--danger);
    }

    .status-date {
      color: var(--gray);
      font-size: 0.875rem;
    }

    .product-content {
      padding: 1.5rem;
    }

    .product-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .info-section {
      background-color: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      box-shadow: var(--box-shadow);
    }

    .info-section-title {
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--gray);
      font-weight: 600;
      margin-bottom: 1rem;
      border-bottom: 1px solid var(--gray-light);
      padding-bottom: 0.5rem;
    }

    .info-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .info-group {
      margin-bottom: 1rem;
    }

    .info-label {
      font-size: 0.875rem;
      color: var(--gray);
      margin-bottom: 0.25rem;
    }

    .info-value {
      font-weight: 600;
      color: var(--dark);
    }

    .highlight-value {
      color: var(--primary);
    }

    .price-value {
      font-size: 1.25rem;
      font-weight: 700;
    }

    .profit-positive {
      color: var(--success);
    }

    .description-section {
      background-color: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--box-shadow);
    }

    .link-section {
      background-color: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--box-shadow);
    }

    .link-value {
      display: inline-block;
      margin-top: 0.5rem;
      color: var(--primary);
      word-break: break-all;
    }

    .link-value:hover {
      text-decoration: underline;
    }

    .image-section {
      background-color: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--box-shadow);
    }

    .product-image {
      max-width: 100%;
      max-height: 400px;
      border-radius: 0.5rem;
      margin-top: 0.75rem;
      object-fit: contain;
    }

    .feedback-section {
      background-color: #f9f9ff;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--box-shadow);
    }
    
    .feedback-section.approved {
      border-left: 4px solid var(--success);
    }
    
    .feedback-section.rejected {
      border-left: 4px solid var(--danger);
    }

    .actions-section {
      display: flex;
      justify-content: flex-end;
      padding: 1rem 1.5rem;
      background-color: white;
      border-top: 1px solid var(--gray-light);
      border-radius: 0 0 var(--border-radius) var(--border-radius);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.625rem 1.25rem;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      border: none;
      font-size: 0.9rem;
      text-decoration: none;
    }

    .btn i {
      margin-right: 0.5rem;
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    .btn-success {
      background-color: var(--success);
      color: white;
    }

    .btn-success:hover {
      background-color: #25a99d;
    }

    .profit-details {
      border-left: 4px solid var(--success);
      background-color: rgba(46, 196, 182, 0.05);
    }

    .profit-margin-indicator {
      height: 8px;
      background-color: #e9ecef;
      border-radius: 4px;
      overflow: hidden;
      margin-top: 0.5rem;
    }

    .profit-margin-bar {
      height: 100%;
      border-radius: 4px;
    }

    .low-margin {
      background-color: var(--danger);
    }

    .medium-margin {
      background-color: var(--warning);
    }

    .high-margin {
      background-color: var(--success);
    }

    @media (max-width: 768px) {
      .info-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  

  <div class="modal-body">
    <div class="status-banner">
      <div class="status-content">
        <div class="status-main">
          <span class="status-label">Status:</span>
          <span class="status-badge status-<?php echo htmlspecialchars($proposal['status']); ?>">
            <?php echo ucfirst(htmlspecialchars($proposal['status'])); ?>
          </span>
        </div>
        <div class="status-dates">
          <div class="status-date">Submitted on: <?php echo date('F j, Y', strtotime($proposal['proposed_date'])); ?></div>
          <?php if ($proposal['status'] !== 'pending' && !empty($proposal['approved_rejected_date'])): ?>
            <div class="status-date">
              <?php echo ucfirst($proposal['status']); ?> on: <?php echo date('F j, Y', strtotime($proposal['approved_rejected_date'])); ?>
              <?php if (!empty($proposal['admin_name'])): ?> 
                by <?php echo htmlspecialchars($proposal['admin_name']); ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="product-content">
      <h1 class="product-title">
        <?php echo htmlspecialchars($proposal['product_name']); ?>
        <span class="status-badge status-<?php echo htmlspecialchars($proposal['status']); ?>">
          <?php echo ucfirst(htmlspecialchars($proposal['status'])); ?>
        </span>
      </h1>

      <div class="info-grid">
        <div class="info-section">
          <div class="info-section-title">Proposal Information</div>
          <div class="info-group">
            <div class="info-label">Team</div>
            <div class="info-value"><?php echo htmlspecialchars($proposal['team_name']); ?></div>
          </div>
          <div class="info-group">
            <div class="info-label">Proposed By</div>
            <div class="info-value"><?php echo htmlspecialchars($proposal['proposed_by']); ?></div>
          </div>
          <div class="info-group">
            <div class="info-label">Date</div>
            <div class="info-value"><?php echo date('Y-m-d', strtotime($proposal['proposed_date'])); ?></div>
          </div>
          <div class="info-group">
            <div class="info-label">Category</div>
            <div class="info-value highlight-value"><?php echo htmlspecialchars($proposal['category']); ?></div>
          </div>
        </div>

        <div class="info-section profit-details">
          <div class="info-section-title">Financial Analysis</div>
          <div class="info-row">
            <div class="info-group">
              <div class="info-label">Estimated Cost Price</div>
              <div class="info-value price-value">RM <?php echo number_format($proposal['cost_price'], 2); ?></div>
            </div>
            <div class="info-group">
              <div class="info-label">Estimated Selling Price</div>
              <div class="info-value price-value">RM <?php echo number_format($proposal['selling_price'], 2); ?></div>
            </div>
          </div>
          <div class="info-row">
            <div class="info-group">
              <div class="info-label">Estimated Profit</div>
              <div class="info-value price-value profit-positive">RM <?php echo number_format($profit_amount, 2); ?></div>
            </div>
            <div class="info-group">
              <div class="info-label">Profit Margin</div>
              <div class="info-value price-value profit-positive"><?php echo number_format($profit_margin, 1); ?>%</div>
              <div class="profit-margin-indicator">
                <div class="profit-margin-bar <?php echo $margin_class; ?>" style="width: <?php echo min(100, $profit_margin); ?>%"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="description-section">
        <div class="info-section-title">Product Description</div>
        <div class="info-value"><?php echo nl2br(htmlspecialchars($proposal['product_description'])); ?></div>
      </div>

      <?php if (!empty($proposal['tiktok_link'])): ?>
      <div class="link-section">
        <div class="info-section-title">TikTok/Product Link</div>
        <a href="<?php echo htmlspecialchars($proposal['tiktok_link']); ?>" target="_blank" class="link-value">
          <i class="fab fa-tiktok"></i> <?php echo htmlspecialchars($proposal['tiktok_link']); ?>
        </a>
      </div>
      <?php endif; ?>

      <?php if (!empty($proposal['product_image'])): ?>
      <div class="image-section">
        <div class="info-section-title">Product Image</div>
        <?php 
        // Display the image and add debug information
        $image_path = $proposal['product_image'];
        $image_exists = file_exists($image_path);
        ?>
        
        <?php if ($image_exists): ?>
          <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Product Image" class="product-image">
        <?php else: ?>
          <!-- Try with different path formats -->
          <?php 
          // Try with just the basename
          $basename = basename($image_path);
          $alt_path = "uploads/proposals/" . $basename;
          $alt_exists = file_exists($alt_path);
          ?>
          
          <?php if ($alt_exists): ?>
            <img src="<?php echo htmlspecialchars($alt_path); ?>" alt="Product Image" class="product-image">
          <?php else: ?>
            <!-- As a last resort, try direct URL -->
            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                 alt="Product Image" 
                 class="product-image"
                 onerror="this.onerror=null; this.src='images/no-image.png'; this.alt='No image available';">
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($proposal['admin_feedback']) && $proposal['status'] !== 'pending'): ?>
      <div class="feedback-section <?php echo $proposal['status']; ?>">
        <div class="info-section-title"><?php echo ucfirst($proposal['status']); ?> Feedback</div>
        <div class="info-value"><?php echo nl2br(htmlspecialchars($proposal['admin_feedback'])); ?></div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($proposal['status'] === 'approved'): ?>
    <div class="actions-section">
      <form method="POST" action="create_product_from_proposal.php" style="display: inline;">
        <input type="hidden" name="proposal_id" value="<?php echo $proposal_id; ?>">
        <button type="submit" class="btn btn-success">
          <i class="fas fa-plus"></i> Create Product
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>