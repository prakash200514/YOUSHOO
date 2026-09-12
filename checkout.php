<?php
/**
 * Meesho Checkout & Order Placement
 */
require_once __DIR__ . '/includes/auth_helper.php';

if (!is_logged_in()) {
    header("Location: /MEESHO/auth.php?redirect=checkout&msg=checkout_required");
    exit;
}

$pageTitle = "Checkout";
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();
$sessionId = get_cart_session_id();
$userId = $_SESSION['user_id'] ?? null;

// Fetch Cart Items
if ($userId) {
    $stmt = $pdo->prepare("SELECT c.*, p.title, p.price, p.mrp, p.supplier_id,
        (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ? OR c.session_id = ?");
    $stmt->execute([$userId, $sessionId]);
} else {
    $stmt = $pdo->prepare("SELECT c.*, p.title, p.price, p.mrp, p.supplier_id,
        (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.session_id = ?");
    $stmt->execute([$sessionId]);
}
$cartItems = $stmt->fetchAll();

if (empty($cartItems) && !isset($_POST['place_order'])) {
    header("Location: /MEESHO/cart.php");
    exit;
}

// Calculate totals
$totalMRP = 0;
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalMRP += ($item['mrp'] * $item['quantity']);
    $totalPrice += ($item['price'] * $item['quantity']);
}
$totalDiscount = $totalMRP - $totalPrice;

// Handle Order Placement
$orderSuccess = null;
$newOrderNumber = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $name = trim($_POST['shipping_name'] ?? '');
    $phone = trim($_POST['shipping_phone'] ?? '');
    $address = trim($_POST['shipping_address'] ?? '');
    $city = trim($_POST['shipping_city'] ?? '');
    $state = trim($_POST['shipping_state'] ?? '');
    $pincode = trim($_POST['shipping_pincode'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cod';

    if (empty($name) || empty($phone) || empty($address) || empty($city) || empty($pincode)) {
        $error = "Please fill in all required shipping address fields.";
    } else {
        $orderNumber = "YOUSH-" . strtoupper(bin2hex(random_bytes(4)));

        $stmtOrder = $pdo->prepare("INSERT INTO orders 
            (order_number, user_id, total_amount, discount_amount, delivery_fee, final_amount, payment_method, payment_status, order_status, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_pincode)
            VALUES (?, ?, ?, ?, 0.00, ?, ?, ?, 'placed', ?, ?, ?, ?, ?, ?)");
        
        $payStatus = ($paymentMethod === 'cod') ? 'pending' : 'paid';
        $stmtOrder->execute([
            $orderNumber, $userId, $totalMRP, $totalDiscount, $totalPrice,
            $paymentMethod, $payStatus, $name, $phone, $address, $city, $state, $pincode
        ]);
        $orderId = $pdo->lastInsertId();

        // Insert Order Items
        $stmtItem = $pdo->prepare("INSERT INTO order_items 
            (order_id, supplier_id, product_id, product_title, product_image, size, color, quantity, unit_price, total_price, supplier_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

        foreach ($cartItems as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $stmtItem->execute([
                $orderId, $item['supplier_id'], $item['product_id'], $item['title'],
                $item['primary_image'] ?: '', $item['size'], $item['color'],
                $item['quantity'], $item['price'], $itemTotal
            ]);
        }

        // Clear Cart
        if ($userId) {
            $pdo->prepare("DELETE FROM cart WHERE user_id = ? OR session_id = ?")->execute([$userId, $sessionId]);
        } else {
            $pdo->prepare("DELETE FROM cart WHERE session_id = ?")->execute([$sessionId]);
        }

        $orderSuccess = true;
        $newOrderNumber = $orderNumber;
    }
}
?>

<main class="main-content">
  <div class="container">

    <?php if ($orderSuccess): ?>
      <!-- CONFETTI CELEBRATION SCREEN -->
      <div style="background:#ffffff; border-radius:16px; border:1px solid #e6e9ef; padding:60px 30px; text-align:center; max-width:680px; margin:40px auto 80px; box-shadow:0 10px 40px rgba(0,0,0,0.08); position:relative; overflow:hidden;">
        
        <!-- Animated Checkmark Icon -->
        <div style="width:84px; height:84px; border-radius:50%; background:#e6f7f2; color:#038d63; display:flex; align-items:center; justify-content:center; font-size:42px; margin:0 auto 20px; animation:bounceCart 0.8s ease;">
          <i class="fas fa-check"></i>
        </div>

        <h1 style="font-size:26px; font-weight:800; color:#333; margin-bottom:8px;">Order Placed Successfully!</h1>
        <p style="font-size:15px; color:#666; margin-bottom:18px;">
          Thank you for shopping with Youshoo. We're packing your order with love.
        </p>

        <div style="background:#fdfafc; border:1.5px dashed #9f2089; border-radius:10px; padding:16px; display:inline-block; margin-bottom:24px;">
          <div style="font-size:12px; font-weight:700; color:#9f2089; text-transform:uppercase; letter-spacing:1px;">Order Reference ID</div>
          <div style="font-size:22px; font-weight:800; color:#333; margin-top:4px; font-family:Consolas, monospace;"><?php echo htmlspecialchars($newOrderNumber); ?></div>
        </div>

        <div style="display:flex; justify-content:center; gap:16px; margin-top:10px;">
          <a href="/MEESHO/orders.php" class="btn-buy-now" style="padding:12px 28px;">
            <i class="fas fa-truck"></i> Track Order Status
          </a>
          <a href="/MEESHO/index.php" class="btn-add-cart" style="padding:12px 28px;">
            Continue Shopping &rarr;
          </a>
        </div>
      </div>
    <?php else: ?>
      <!-- CHECKOUT FORM -->
      <div style="padding: 24px 0 16px;">
        <h1 style="font-size:24px; font-weight:800; color:#333;">Checkout & Delivery</h1>
        <p style="font-size:13px; color:#666;">Step 2 of 2: Shipping details and payment</p>
      </div>

      <?php if (!empty($error)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:14px; font-weight:600;">
          <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form action="/MEESHO/checkout.php" method="POST">
        <div class="cart-layout">
          <!-- Left: Address & Payment -->
          <div>
            <!-- Shipping Address Card -->
            <div class="cart-items-card" style="margin-bottom:24px;">
              <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-map-marker-alt" style="color:#9f2089;"></i> 1. Delivery Address
              </h3>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div>
                  <label style="display:block; font-size:12.5px; font-weight:600; color:#444; margin-bottom:6px;">Full Name *</label>
                  <input type="text" name="shipping_name" required value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" style="width:100%; padding:10px 14px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
                </div>
                <div>
                  <label style="display:block; font-size:12.5px; font-weight:600; color:#444; margin-bottom:6px;">Phone Number *</label>
                  <input type="tel" name="shipping_phone" required value="<?php echo htmlspecialchars($currentUser['phone'] ?? '9876543210'); ?>" style="width:100%; padding:10px 14px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
                </div>
                <div style="grid-column: 1 / -1;">
                  <label style="display:block; font-size:12.5px; font-weight:600; color:#444; margin-bottom:6px;">House No. / Building / Street Address *</label>
                  <input type="text" name="shipping_address" required placeholder="Flat 204, Rose Garden Apartments, MG Road" style="width:100%; padding:10px 14px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
                </div>
                <div>
                  <label style="display:block; font-size:12.5px; font-weight:600; color:#444; margin-bottom:6px;">City *</label>
                  <input type="text" name="shipping_city" required value="Mumbai" style="width:100%; padding:10px 14px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
                </div>
                <div>
                  <label style="display:block; font-size:12.5px; font-weight:600; color:#444; margin-bottom:6px;">State *</label>
                  <input type="text" name="shipping_state" required value="Maharashtra" style="width:100%; padding:10px 14px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
                </div>
                <div>
                  <label style="display:block; font-size:12.5px; font-weight:600; color:#444; margin-bottom:6px;">Pincode *</label>
                  <input type="text" name="shipping_pincode" required value="400001" maxlength="6" style="width:100%; padding:10px 14px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
                </div>
              </div>
            </div>

            <!-- Payment Methods Card -->
            <div class="cart-items-card">
              <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-credit-card" style="color:#9f2089;"></i> 2. Payment Method
              </h3>

              <div style="display:flex; flex-direction:column; gap:12px;">
                <!-- Cash On Delivery -->
                <label style="display:flex; align-items:center; justify-content:space-between; padding:16px; border:1.5px solid #9f2089; background:#fdfafc; border-radius:8px; cursor:pointer;">
                  <div style="display:flex; align-items:center; gap:12px;">
                    <input type="radio" name="payment_method" value="cod" checked style="accent-color:#9f2089; width:18px; height:18px;">
                    <div>
                      <div style="font-size:14px; font-weight:700; color:#333;">Cash On Delivery (COD)</div>
                      <div style="font-size:12px; color:#666;">Pay cash or scan QR when your package arrives</div>
                    </div>
                  </div>
                  <span style="background:#e6f7f2; color:#038d63; font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px;">No Extra Fee</span>
                </label>

                <!-- UPI / Online -->
                <label style="display:flex; align-items:center; justify-content:space-between; padding:16px; border:1.5px solid #e6e9ef; border-radius:8px; cursor:pointer;">
                  <div style="display:flex; align-items:center; gap:12px;">
                    <input type="radio" name="payment_method" value="upi" style="accent-color:#9f2089; width:18px; height:18px;">
                    <div>
                      <div style="font-size:14px; font-weight:700; color:#333;">UPI (Google Pay, PhonePe, Paytm)</div>
                      <div style="font-size:12px; color:#666;">Instant refund guaranteed on return</div>
                    </div>
                  </div>
                  <span style="color:#9f2089; font-size:18px;"><i class="fab fa-google-pay"></i></span>
                </label>

                <!-- Debit/Credit Card -->
                <label style="display:flex; align-items:center; justify-content:space-between; padding:16px; border:1.5px solid #e6e9ef; border-radius:8px; cursor:pointer;">
                  <div style="display:flex; align-items:center; gap:12px;">
                    <input type="radio" name="payment_method" value="card" style="accent-color:#9f2089; width:18px; height:18px;">
                    <div>
                      <div style="font-size:14px; font-weight:700; color:#333;">Credit / Debit Card</div>
                      <div style="font-size:12px; color:#666;">Visa, Mastercard, RuPay & Maestro</div>
                    </div>
                  </div>
                  <span style="color:#555; font-size:16px;"><i class="far fa-credit-card"></i></span>
                </label>
              </div>
            </div>
          </div>

          <!-- Right: Sticky Order Summary -->
          <aside class="price-summary-card">
            <div class="price-summary-title">Order Summary (<?php echo count($cartItems); ?> Items)</div>

            <div style="max-height:200px; overflow-y:auto; margin-bottom:16px; border-bottom:1px solid #f1f1f1; padding-bottom:12px;">
              <?php foreach ($cartItems as $ci): ?>
                <div style="display:flex; gap:10px; margin-bottom:10px; font-size:13px;">
                  <img src="<?php echo htmlspecialchars($ci['primary_image'] ?: 'https://placehold.co/40'); ?>" style="width:40px; height:48px; object-fit:cover; border-radius:4px;">
                  <div style="flex:1;">
                    <div style="font-weight:600; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px;"><?php echo htmlspecialchars($ci['title']); ?></div>
                    <div style="font-size:11px; color:#777;">Qty: <?php echo $ci['quantity']; ?> &bull; Size: <?php echo htmlspecialchars($ci['size']); ?></div>
                  </div>
                  <div style="font-weight:700; color:#333;">₹<?php echo number_format($ci['price'] * $ci['quantity'], 0); ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="summary-row">
              <span>Total MRP</span>
              <span>₹<?php echo number_format($totalMRP, 0); ?></span>
            </div>

            <div class="summary-row">
              <span>Discount</span>
              <span style="color:#038d63; font-weight:700;">- ₹<?php echo number_format($totalDiscount, 0); ?></span>
            </div>

            <div class="summary-row">
              <span>Delivery Charges</span>
              <span class="summary-free-green">FREE</span>
            </div>

            <div class="summary-row total-row">
              <span>Final Payable</span>
              <span style="color:#9f2089;">₹<?php echo number_format($totalPrice, 0); ?></span>
            </div>

            <button type="submit" name="place_order" class="btn-checkout-cta" style="font-size:16px;">
              <i class="fas fa-lock"></i> Place Order Now
            </button>

            <div style="font-size:11px; color:#888; text-align:center; margin-top:14px;">
              By clicking "Place Order Now", you agree to Youshoo's Terms of Service and Privacy Policy.
            </div>
          </aside>
        </div>
      </form>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
