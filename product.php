<?php
/**
 * Meesho Product Details Page
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

$pdo = getDBConnection();
$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch Product & Supplier Details
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug,
        s.id as supplier_id, s.shop_name, s.owner_name, s.rating as supplier_rating, s.followers_count, s.city, s.state
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ? AND p.status = 'approved'");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo "Product not found or not approved. <a href='index.php'>Back to Home</a>";
    exit;
}

// Fetch Images
$stmtImages = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$stmtImages->execute([$productId]);
$images = $stmtImages->fetchAll();
if (empty($images)) {
    $images = [['image_url' => 'https://placehold.co/600x700?text=Product+Image']];
}

// Fetch Reviews
$stmtReviews = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? ORDER BY created_at DESC");
$stmtReviews->execute([$productId]);
$reviews = $stmtReviews->fetchAll();

// Fetch Similar Products
$stmtSimilar = $pdo->prepare("SELECT p.*, 
    (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
    FROM products p 
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'approved' 
    LIMIT 4");
$stmtSimilar->execute([$product['category_id'], $productId]);
$similarProducts = $stmtSimilar->fetchAll();

$pageTitle = $product['title'];
require_once __DIR__ . '/includes/header.php';

// Prepare sizes & size-specific pricing
$sizes = array_map('trim', explode(',', $product['sizes'] ?: 'Free Size'));
$sizePrices = !empty($product['size_prices']) ? json_decode($product['size_prices'], true) : [];
if (!is_array($sizePrices)) $sizePrices = [];

// Compute display price based on the initial active size
$currentPrice = $product['price'];
$currentMrp = $product['mrp'];
$currentDiscount = $product['discount_percent'];
$firstSize = $sizes[0] ?? '';
if (!empty($firstSize) && !empty($sizePrices[$firstSize]['price'])) {
    $currentPrice = (float)$sizePrices[$firstSize]['price'];
    $currentMrp = (float)($sizePrices[$firstSize]['mrp'] ?? ($currentPrice * 1.5));
    $currentDiscount = ($currentMrp > $currentPrice) ? round((($currentMrp - $currentPrice) / $currentMrp) * 100) : 0;
}
?>

<main class="main-content">
  <div class="container">

    <!-- Breadcrumbs -->
    <div style="font-size:12px; color:#888; padding: 16px 0 8px;">
      <a href="/MEESHO/index.php" style="color:#666;">Home</a> /
      <a href="/MEESHO/index.php?category=<?php echo urlencode($product['category_slug']); ?>" style="color:#666;"><?php echo htmlspecialchars($product['category_name']); ?></a> /
      <span style="color:#333; font-weight:500;"><?php echo htmlspecialchars($product['title']); ?></span>
    </div>

    <!-- Main Product Layout -->
    <div class="product-detail-layout">

      <!-- LEFT: Image Gallery & CTA -->
      <div class="gallery-sticky-wrap">
        <div class="gallery-container">
          <!-- Thumbnails -->
          <div class="thumbnail-col">
            <?php foreach ($images as $idx => $img): ?>
              <div class="thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" data-full-img="<?php echo htmlspecialchars($img['image_url']); ?>">
                <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt="">
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Main Viewport -->
          <div class="main-image-viewport">
            <img src="<?php echo htmlspecialchars($images[0]['image_url']); ?>" id="detail-main-img" alt="<?php echo htmlspecialchars($product['title']); ?>">
          </div>
        </div>

        <!-- Sticky Action CTA Buttons -->
        <div class="detail-cta-row">
          <input type="hidden" id="selected-product-size" value="<?php echo htmlspecialchars($sizes[0]); ?>">
          
          <button type="button" class="btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>, document.getElementById('selected-product-size').value, 'Default', 1, this)">
            <i class="fas fa-cart-plus"></i> Add to Cart
          </button>
          
          <button type="button" class="btn-buy-now" onclick="addToCart(<?php echo $product['id']; ?>, document.getElementById('selected-product-size').value, 'Default', 1, this); setTimeout(() => { window.location.href='/MEESHO/checkout.php'; }, 400);">
            <i class="fas fa-bolt"></i> Buy Now
          </button>
        </div>

        <!-- Trust Badges Under Buttons -->
        <div style="display:flex; justify-content:space-around; background:#fff; border:1px solid #e6e9ef; border-radius:8px; padding:12px; margin-top:16px; font-size:11.5px; color:#555; text-align:center;">
          <div><i class="fas fa-truck" style="color:#038d63; font-size:16px; display:block; margin-bottom:4px;"></i> Free Delivery</div>
          <div><i class="fas fa-wallet" style="color:#038d63; font-size:16px; display:block; margin-bottom:4px;"></i> Pay On Delivery</div>
          <div><i class="fas fa-undo" style="color:#038d63; font-size:16px; display:block; margin-bottom:4px;"></i> 7-Day Returns</div>
        </div>
      </div>

      <!-- RIGHT: Product Details & Purchase Form -->
      <div class="product-info-column">

        <!-- Card 1: Title & Price -->
        <div class="detail-card">
          <h1 class="detail-title"><?php echo htmlspecialchars($product['title']); ?></h1>
          
          <div class="detail-price-box" style="display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
            <span class="detail-price">₹<?php echo number_format($currentPrice, 0); ?></span>
            <span class="detail-mrp">₹<?php echo number_format($currentMrp, 0); ?></span>
            <span class="detail-discount"><?php echo $currentDiscount; ?>% off</span>
            <?php if (!empty($sizePrices)): ?>
              <span style="font-size:11.5px; font-weight:700; color:#9f2089; background:#fdf2f8; border:1px solid #fbcfe8; padding:3px 8px; border-radius:12px;">
                <i class="fas fa-tags"></i> Price varies by size
              </span>
            <?php endif; ?>
          </div>

          <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
            <span class="rating-pill" style="font-size:13px; padding:3px 8px;">
              <?php echo number_format($product['rating_avg'], 1); ?> <i class="fas fa-star" style="font-size:11px;"></i>
            </span>
            <span class="reviews-count-text" style="font-size:13px;">
              <?php echo number_format($product['rating_count']); ?> Ratings, <?php echo count($reviews); ?> Reviews
            </span>
          </div>

          <div class="delivery-badge" style="font-size:12px; padding:4px 10px;">
            <i class="fas fa-truck"></i> Free Delivery on this order
          </div>
        </div>

        <!-- Card 2: Select Size -->
        <div class="detail-card">
          <div class="size-selector-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Select Size</span>
            <?php if (!empty($sizePrices)): ?>
              <span style="font-size:11.5px; font-weight:600; color:#038d63;"><i class="fas fa-check-circle"></i> Price updates per size</span>
            <?php endif; ?>
          </div>
          <div class="size-chips-wrap">
            <?php foreach ($sizes as $idx => $s): 
              $sPrice = isset($sizePrices[$s]) ? (float)$sizePrices[$s]['price'] : (float)$product['price'];
              $sMrp = isset($sizePrices[$s]) ? (float)$sizePrices[$s]['mrp'] : (float)$product['mrp'];
              $sDisc = ($sMrp > $sPrice) ? round((($sMrp - $sPrice) / $sMrp) * 100) : 0;
              $hasCustomPrice = isset($sizePrices[$s]);
            ?>
              <div class="size-chip detail-size-chip <?php echo $idx === 0 ? 'selected' : ''; ?>" 
                   data-size="<?php echo htmlspecialchars($s); ?>"
                   data-price="<?php echo $sPrice; ?>"
                   data-mrp="<?php echo $sMrp; ?>"
                   data-discount="<?php echo $sDisc; ?>">
                <span><?php echo htmlspecialchars($s); ?></span>
                <?php if ($hasCustomPrice): ?>
                  <span class="size-price-tag" style="font-size:11px; font-weight:700; color:#038d63; margin-left:5px;">₹<?php echo number_format($sPrice, 0); ?></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Pincode Delivery Checker -->
          <div style="margin-top:20px; border-top:1px solid #f1f1f1; padding-top:16px;">
            <div style="font-size:13px; font-weight:700; color:#333; margin-bottom:6px;">Check Delivery Date</div>
            <div class="pincode-box">
              <input type="text" class="pincode-input" placeholder="Enter 6-digit Pincode" maxlength="6" value="400001">
              <button type="button" class="pincode-check-btn">Check</button>
            </div>
            <div class="pincode-status-text">
              <div style="color:#038d63; font-size:12px; font-weight:600; margin-top:6px;">
                <i class="fas fa-shipping-fast"></i> Estimated Delivery: <strong>Within 2-3 Business Days</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 3: Product Specifications & Details -->
        <div class="detail-card">
          <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:14px;">Product Details</h3>
          <p style="font-size:13.5px; color:#555; line-height:1.6; margin-bottom:18px;">
            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
          </p>

          <table class="specs-table">
            <tr>
              <td>Fabric</td>
              <td><?php echo htmlspecialchars($product['fabric'] ?: 'Pure Quality Blend'); ?></td>
            </tr>
            <tr>
              <td>Color Variants</td>
              <td><?php echo htmlspecialchars($product['colors'] ?: 'Multi'); ?></td>
            </tr>
            <tr>
              <td>Sizes Available</td>
              <td><?php echo htmlspecialchars($product['sizes'] ?: 'Free Size'); ?></td>
            </tr>
            <tr>
              <td>SKU Code</td>
              <td><?php echo htmlspecialchars($product['sku'] ?: 'YOUSH-' . $product['id']); ?></td>
            </tr>
            <tr>
              <td>Country of Origin</td>
              <td>India 🇮🇳</td>
            </tr>
          </table>
        </div>

        <!-- Card 4: Supplier Information (Shop Profile) -->
        <div class="detail-card">
          <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:14px;">Sold By Supplier</h3>
          <div class="supplier-detail-box">
            <div class="supplier-info-left">
              <h4><?php echo htmlspecialchars($product['shop_name']); ?></h4>
              <div class="supplier-meta-row">
                <span><i class="fas fa-map-marker-alt" style="color:#9f2089;"></i> <?php echo htmlspecialchars($product['city'] . ', ' . $product['state']); ?></span>
                <span><i class="fas fa-star" style="color:#038d63;"></i> <?php echo number_format($product['supplier_rating'], 1); ?> Supplier Rating</span>
                <span><i class="fas fa-users" style="color:#9f2089;"></i> <?php echo number_format($product['followers_count']); ?> Followers</span>
              </div>
            </div>
            <div>
              <span style="display:inline-flex; align-items:center; gap:4px; background:#e6f7f2; color:#038d63; font-size:12px; font-weight:700; padding:4px 10px; border-radius:20px;">
                <i class="fas fa-check-circle"></i> Verified Seller
              </span>
            </div>
          </div>
        </div>

        <!-- Card 5: Ratings & Customer Reviews -->
        <div class="detail-card">
          <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px;">Customer Ratings & Reviews</h3>
          
          <div style="display:flex; align-items:center; gap:24px; padding-bottom:18px; border-bottom:1px solid #f1f1f1; margin-bottom:18px;">
            <div style="text-align:center;">
              <div style="font-size:38px; font-weight:800; color:#038d63; line-height:1;"><?php echo number_format($product['rating_avg'], 1); ?></div>
              <div style="font-size:12px; color:#777; margin-top:4px;"><?php echo number_format($product['rating_count']); ?> Ratings</div>
            </div>
            <div style="flex:1;">
              <div style="font-size:12px; color:#555; margin-bottom:4px;">92% of customers recommend this product</div>
              <div style="background:#e6e9ef; height:8px; border-radius:4px; overflow:hidden;">
                <div style="background:#038d63; height:100%; width:92%;"></div>
              </div>
            </div>
          </div>

          <!-- Reviews List -->
          <div class="reviews-list">
            <?php foreach ($reviews as $rev): ?>
              <div style="border-bottom:1px solid #f1f1f1; padding-bottom:14px; margin-bottom:14px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                  <span class="rating-pill" style="font-size:11px;">
                    <?php echo $rev['rating']; ?> <i class="fas fa-star" style="font-size:9px;"></i>
                  </span>
                  <span style="font-size:13px; font-weight:700; color:#333;"><?php echo htmlspecialchars($rev['user_name']); ?></span>
                  <span style="font-size:11px; color:#999; margin-left:auto;"><i class="fas fa-check-circle" style="color:#038d63;"></i> Verified Purchase</span>
                </div>
                <p style="font-size:13px; color:#555; line-height:1.5;">
                  <?php echo htmlspecialchars($rev['review_text']); ?>
                </p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Similar Products Carousel -->
    <?php if (!empty($similarProducts)): ?>
      <section style="margin: 40px 0 20px;">
        <h3 class="section-header-title">Similar Products You Might Like</h3>
        <div class="products-grid" style="grid-template-columns: repeat(4, 1fr);">
          <?php foreach ($similarProducts as $sp): ?>
            <div class="product-card">
              <a href="/MEESHO/product.php?id=<?php echo $sp['id']; ?>" class="product-image-wrap">
                <img src="<?php echo htmlspecialchars($sp['primary_image'] ?: 'https://placehold.co/400x500'); ?>" alt="">
              </a>
              <div class="product-info-wrap">
                <a href="/MEESHO/product.php?id=<?php echo $sp['id']; ?>" class="product-title"><?php echo htmlspecialchars($sp['title']); ?></a>
                <div class="product-price-row">
                  <span class="product-price">₹<?php echo number_format($sp['price'], 0); ?></span>
                  <span class="product-mrp">₹<?php echo number_format($sp['mrp'], 0); ?></span>
                  <span class="product-discount"><?php echo $sp['discount_percent']; ?>% off</span>
                </div>
                <div class="delivery-badge"><i class="fas fa-truck"></i> Free Delivery</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
