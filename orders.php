<?php
/**
 * Customer Orders & Tracking Timeline
 */
require_once __DIR__ . '/includes/auth_helper.php';

if (!is_logged_in()) {
    header("Location: /MEESHO/auth.php?redirect=orders&msg=orders_required");
    exit;
}

$pageTitle = "My Orders";
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Fetch Orders for logged in customer
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $productId = (int)($_POST['product_id'] ?? 0);
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $reviewText = trim($_POST['review_text'] ?? '');
    $userName = $currentUser['name'] ?? 'Verified Customer';

    if ($productId > 0 && !empty($reviewText)) {
        $stmtRev = $pdo->prepare("INSERT INTO reviews (product_id, user_id, user_name, rating, review_text, helpful_count) VALUES (?, ?, ?, ?, ?, 1)");
        $stmtRev->execute([$productId, $userId, $userName, $rating, $reviewText]);
        $reviewSuccess = true;
    }
}
?>

<main class="main-content">
  <div class="container" style="max-width:960px;">

    <div style="padding: 24px 0 16px;">
      <h1 style="font-size:24px; font-weight:800; color:#333;">My Orders</h1>
      <p style="font-size:13px; color:#666;">Track your packages, download delivery receipts & leave reviews</p>
    </div>

    <?php if (isset($reviewSuccess)): ?>
      <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
        <i class="fas fa-check-circle"></i> Thank you! Your product review has been published.
      </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
      <div style="background:#ffffff; border-radius:12px; border:1px solid #e6e9ef; padding:60px 20px; text-align:center; margin: 20px 0 60px;">
        <i class="fas fa-truck-loading" style="font-size:54px; color:#d1d5db; margin-bottom:16px;"></i>
        <h2 style="font-size:20px; font-weight:700; color:#333; margin-bottom:8px;">No Orders Placed Yet</h2>
        <p style="font-size:14px; color:#666; margin-bottom:24px;">Browse through our handpicked catalogue and place your first order!</p>
        <a href="/MEESHO/index.php" class="btn-buy-now" style="display:inline-block; padding:12px 32px; font-size:15px;">Shop Now</a>
      </div>
    <?php else: ?>
      <div style="display:flex; flex-direction:column; gap:20px; margin-bottom:60px;">
        <?php foreach ($orders as $ord): 
          // Fetch Items for this order
          $stmtItems = $pdo->prepare("SELECT oi.*, s.shop_name FROM order_items oi JOIN suppliers s ON oi.supplier_id = s.id WHERE oi.order_id = ?");
          $stmtItems->execute([$ord['id']]);
          $items = $stmtItems->fetchAll();

          // Tracking progress step index (0 to 4)
          $statuses = ['placed', 'confirmed', 'shipped', 'out_for_delivery', 'delivered'];
          $currentStepIdx = array_search($ord['order_status'], $statuses);
          if ($currentStepIdx === false) $currentStepIdx = 1;
        ?>
          <div style="background:#ffffff; border-radius:12px; border:1px solid #e6e9ef; padding:24px; box-shadow:var(--meesho-shadow-sm);">
            
            <!-- Order Header -->
            <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #f1f1f1; padding-bottom:16px; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
              <div>
                <span style="font-size:11px; font-weight:700; color:#9f2089; text-transform:uppercase; letter-spacing:0.5px;">Order ID</span>
                <div style="font-size:16px; font-weight:800; color:#333; font-family:Consolas, monospace;"><?php echo htmlspecialchars($ord['order_number']); ?></div>
                <div style="font-size:12px; color:#777; margin-top:2px;">Placed on: <?php echo date('d M Y, h:i A', strtotime($ord['created_at'])); ?></div>
              </div>

              <div style="text-align:right;">
                <span class="status-badge <?php echo htmlspecialchars($ord['order_status']); ?>" style="font-size:12px; padding:6px 14px;">
                  <i class="fas fa-circle" style="font-size:8px; vertical-align:middle; margin-right:4px;"></i>
                  <?php echo ucwords(str_replace('_', ' ', $ord['order_status'])); ?>
                </span>
                <div style="font-size:16px; font-weight:800; color:#333; margin-top:6px;">
                  ₹<?php echo number_format($ord['final_amount'], 0); ?>
                  <span style="font-size:11px; font-weight:500; color:#888;">(<?php echo strtoupper($ord['payment_method']); ?>)</span>
                </div>
              </div>
            </div>

            <!-- Stepper Progress Timeline -->
            <div style="margin: 20px 0 28px;">
              <div style="display:flex; justify-content:space-between; position:relative;">
                <!-- Background connecting line -->
                <div style="position:absolute; top:14px; left:20px; right:20px; height:3px; background:#e6e9ef; z-index:1;"></div>
                <div style="position:absolute; top:14px; left:20px; width:<?php echo ($currentStepIdx / 4) * 100; ?>%; height:3px; background:#038d63; z-index:2; transition:width 0.5s ease;"></div>

                <?php 
                $stepLabels = ['Order Placed', 'Confirmed', 'Shipped', 'Out for Delivery', 'Delivered'];
                foreach ($stepLabels as $sIdx => $label): 
                  $isDone = ($sIdx <= $currentStepIdx);
                ?>
                  <div style="position:relative; z-index:3; text-align:center; width:80px;">
                    <div style="width:30px; height:30px; border-radius:50%; background:<?php echo $isDone ? '#038d63' : '#e6e9ef'; ?>; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 6px; font-size:12px; font-weight:700;">
                      <?php echo $isDone ? '✓' : ($sIdx + 1); ?>
                    </div>
                    <div style="font-size:11px; font-weight:<?php echo $isDone ? '700' : '500'; ?>; color:<?php echo $isDone ? '#333' : '#888'; ?>;"><?php echo $label; ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Order Items -->
            <div style="border-top:1px solid #f1f1f1; padding-top:16px;">
              <?php foreach ($items as $item): ?>
                <div style="display:flex; gap:16px; align-items:center; margin-bottom:14px; border-bottom:1px dashed #f1f1f1; padding-bottom:14px;">
                  <img src="<?php echo htmlspecialchars($item['product_image'] ?: 'https://placehold.co/60'); ?>" style="width:60px; height:75px; object-fit:cover; border-radius:6px;">
                  <div style="flex:1;">
                    <h4 style="font-size:14px; font-weight:600; color:#333; margin-bottom:4px;"><?php echo htmlspecialchars($item['product_title']); ?></h4>
                    <div style="font-size:12px; color:#777;">
                      Size: <strong><?php echo htmlspecialchars($item['size']); ?></strong> &bull; Qty: <strong><?php echo $item['quantity']; ?></strong> &bull; Supplier: <strong><?php echo htmlspecialchars($item['shop_name']); ?></strong>
                    </div>
                    <div style="font-size:14px; font-weight:700; color:#038d63; margin-top:4px;">₹<?php echo number_format($item['total_price'], 0); ?></div>
                  </div>

                  <!-- Rate & Review Button -->
                  <div>
                    <button type="button" class="btn-add-cart" style="padding:6px 14px; font-size:12px;" onclick="openReviewModal(<?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['product_title'])); ?>')">
                      <i class="far fa-star"></i> Rate Product
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Delivery Address Snippet -->
            <div style="background:#f8f9fc; border-radius:8px; padding:12px 16px; font-size:12px; color:#555; display:flex; justify-content:space-between; align-items:center;">
              <div>
                <i class="fas fa-map-marker-alt" style="color:#9f2089; margin-right:6px;"></i>
                Shipping to: <strong><?php echo htmlspecialchars($ord['shipping_name']); ?></strong> (<?php echo htmlspecialchars($ord['shipping_phone']); ?>), <?php echo htmlspecialchars($ord['shipping_address'] . ', ' . $ord['shipping_city'] . ' - ' . $ord['shipping_pincode']); ?>
              </div>
              <div>
                <button onclick="window.print()" style="background:none; border:none; color:#9f2089; font-weight:700; cursor:pointer;"><i class="fas fa-print"></i> Print Invoice</button>
              </div>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<!-- Review Modal -->
<div class="meesho-modal-backdrop" id="review-modal">
  <div class="meesho-modal-box" style="max-width:500px; padding:24px;">
    <button class="modal-close-btn" onclick="document.getElementById('review-modal').classList.remove('open')">&times;</button>
    <h3 style="font-size:18px; font-weight:700; color:#333; margin-bottom:8px;">Rate & Review Product</h3>
    <p id="review-modal-prod-title" style="font-size:13px; color:#666; margin-bottom:16px;"></p>

    <form action="/MEESHO/orders.php" method="POST">
      <input type="hidden" name="product_id" id="review-modal-prod-id">
      
      <div style="margin-bottom:16px;">
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Your Rating</label>
        <select name="rating" style="width:100%; padding:10px; border-radius:6px; border:1px solid #d5d8de; font-size:14px;">
          <option value="5">⭐⭐⭐⭐⭐ 5 Stars (Excellent)</option>
          <option value="4">⭐⭐⭐⭐ 4 Stars (Very Good)</option>
          <option value="3">⭐⭐⭐ 3 Stars (Average)</option>
          <option value="2">⭐⭐ 2 Stars (Poor)</option>
          <option value="1">⭐ 1 Star (Terrible)</option>
        </select>
      </div>

      <div style="margin-bottom:20px;">
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Your Review Description</label>
        <textarea name="review_text" rows="4" required placeholder="What did you like or dislike? How was the fabric quality and fitting?" style="width:100%; padding:10px; border-radius:6px; border:1px solid #d5d8de; font-size:13px;"></textarea>
      </div>

      <button type="submit" name="submit_review" class="btn-buy-now" style="width:100%;">
        Submit Verified Review
      </button>
    </form>
  </div>
</div>

<script>
function openReviewModal(prodId, title) {
  document.getElementById('review-modal-prod-id').value = prodId;
  document.getElementById('review-modal-prod-title').textContent = title;
  document.getElementById('review-modal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
