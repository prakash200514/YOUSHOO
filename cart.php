<?php
/**
 * Meesho Cart Page
 */
$pageTitle = "Shopping Bag";
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();
$sessionId = get_cart_session_id();
$userId = $_SESSION['user_id'] ?? null;

// Fetch Cart Items
if ($userId) {
    $stmt = $pdo->prepare("SELECT c.*, p.title, p.price, p.mrp, p.stock,
        (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ? OR c.session_id = ?
        ORDER BY c.created_at DESC");
    $stmt->execute([$userId, $sessionId]);
} else {
    $stmt = $pdo->prepare("SELECT c.*, p.title, p.price, p.mrp, p.stock,
        (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.session_id = ?
        ORDER BY c.created_at DESC");
    $stmt->execute([$sessionId]);
}
$cartItems = $stmt->fetchAll();

// Calculate totals
$totalMRP = 0;
$totalPrice = 0;
$totalItemsCount = 0;

foreach ($cartItems as $item) {
    $totalMRP += ($item['mrp'] * $item['quantity']);
    $totalPrice += ($item['price'] * $item['quantity']);
    $totalItemsCount += $item['quantity'];
}

$totalDiscount = $totalMRP - $totalPrice;
?>

<main class="main-content">
  <div class="container">

    <div style="padding: 24px 0 12px; display:flex; align-items:center; justify-content:space-between;">
      <h1 style="font-size:22px; font-weight:800; color:#333;">Shopping Bag (<?php echo $totalItemsCount; ?> Items)</h1>
      <a href="/MEESHO/index.php" style="font-size:13px; font-weight:600; color:#9f2089;"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
    </div>

    <?php if (empty($cartItems)): ?>
      <div style="background:#ffffff; border-radius:12px; border:1px solid #e6e9ef; padding:60px 20px; text-align:center; margin: 20px 0 60px;">
        <i class="fas fa-shopping-bag" style="font-size:54px; color:#d1d5db; margin-bottom:16px;"></i>
        <h2 style="font-size:20px; font-weight:700; color:#333; margin-bottom:8px;">Your Cart is Empty</h2>
        <p style="font-size:14px; color:#666; margin-bottom:24px;">Explore our grand catalog of 50 Lakh+ products with Free Delivery.</p>
        <a href="/MEESHO/index.php" class="btn-buy-now" style="display:inline-block; padding:12px 32px; font-size:15px;">Shop Now</a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <!-- Cart Items List -->
        <div class="cart-items-card">
          <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e6e9ef; padding-bottom:14px; margin-bottom:20px;">
            <span style="font-size:14px; font-weight:700; color:#333;">Items in Cart</span>
            <span style="font-size:12px; color:#038d63; font-weight:700;"><i class="fas fa-check-circle"></i> Eligible for 100% Free Delivery</span>
          </div>

          <?php foreach ($cartItems as $item): ?>
            <div class="cart-item-row" id="cart-item-<?php echo $item['id']; ?>">
              <a href="/MEESHO/product.php?id=<?php echo $item['product_id']; ?>" class="cart-item-thumb">
                <img src="<?php echo htmlspecialchars($item['primary_image'] ?: 'https://placehold.co/100'); ?>" alt="">
              </a>

              <div class="cart-item-details">
                <h4><a href="/MEESHO/product.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['title']); ?></a></h4>
                <div class="cart-item-meta">
                  <span>Size: <strong><?php echo htmlspecialchars($item['size']); ?></strong></span> &bull;
                  <span>Supplier: <strong>Meesho Verified</strong></span>
                </div>

                <div style="display:flex; align-items:baseline; gap:10px; margin-bottom:12px;">
                  <span style="font-size:18px; font-weight:800; color:#333;">₹<?php echo number_format($item['price'], 0); ?></span>
                  <span style="font-size:13px; color:#888; text-decoration:line-through;">₹<?php echo number_format($item['mrp'], 0); ?></span>
                  <span style="font-size:12px; font-weight:700; color:#038d63;">
                    <?php echo round((($item['mrp'] - $item['price'])/$item['mrp']) * 100); ?>% off
                  </span>
                </div>

                <div style="display:flex; align-items:center;">
                  <div class="qty-control">
                    <button class="qty-btn" onclick="updateItemQty(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">-</button>
                    <span class="qty-num"><?php echo $item['quantity']; ?></span>
                    <button class="qty-btn" onclick="updateItemQty(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">+</button>
                  </div>

                  <button class="cart-item-remove-btn" onclick="removeItem(<?php echo $item['id']; ?>)">
                    <i class="far fa-trash-alt"></i> REMOVE
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Price Details Summary -->
        <aside class="price-summary-card">
          <div class="price-summary-title">Price Details (<?php echo $totalItemsCount; ?> Items)</div>

          <div class="summary-row">
            <span>Total Product Price</span>
            <span>₹<?php echo number_format($totalMRP, 0); ?></span>
          </div>

          <div class="summary-row">
            <span>Total Product Discount</span>
            <span style="color:#038d63; font-weight:700;">- ₹<?php echo number_format($totalDiscount, 0); ?></span>
          </div>

          <div class="summary-row">
            <span>Delivery Fee</span>
            <span><s style="color:#888; margin-right:6px;">₹70</s> <span class="summary-free-green">FREE</span></span>
          </div>

          <div class="summary-row">
            <span>Cash on Delivery</span>
            <span class="summary-free-green">FREE</span>
          </div>

          <div class="summary-row total-row">
            <span>Order Total</span>
            <span style="color:#9f2089;">₹<?php echo number_format($totalPrice, 0); ?></span>
          </div>

          <div style="background:#e6f7f2; color:#038d63; font-size:12px; font-weight:700; padding:10px; border-radius:6px; margin-top:14px; text-align:center;">
            🎉 Yay! You saved ₹<?php echo number_format($totalDiscount, 0); ?> on this order!
          </div>

          <a href="/MEESHO/checkout.php" class="btn-checkout-cta">
            Proceed to Checkout &rarr;
          </a>

          <div style="font-size:11.5px; color:#888; text-align:center; margin-top:14px;">
            <i class="fas fa-shield-alt" style="color:#038d63;"></i> Safe and Secure Payments. 100% Authentic Products.
          </div>
        </aside>
      </div>
    <?php endif; ?>

  </div>
</main>

<script>
function updateItemQty(cartId, newQty) {
  const formData = new FormData();
  formData.append("action", "update");
  formData.append("cart_id", cartId);
  formData.append("quantity", newQty);

  fetch("/MEESHO/api/cart.php", {
    method: "POST",
    body: formData
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      window.location.reload();
    }
  });
}

function removeItem(cartId) {
  if (!confirm("Remove this item from your shopping bag?")) return;

  const formData = new FormData();
  formData.append("action", "remove");
  formData.append("cart_id", cartId);

  fetch("/MEESHO/api/cart.php", {
    method: "POST",
    body: formData
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      window.location.reload();
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
