<?php
/**
 * Meesho Homepage & Product Feed
 */
$pageTitle = "Online Shopping Site for Fashion, Electronics, Home & More";
require_once __DIR__ . '/includes/header.php';

$pdo = getDBConnection();

// Filters & Query Parameters
$categorySlug = $_GET['category'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$priceFilter = $_GET['price'] ?? '';
$minRating = (float)($_GET['rating'] ?? 0);
$sortBy = $_GET['sort'] ?? 'relevance';

// Build Dynamic SQL Query
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, s.shop_name,
        (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.status = 'approved'";
$params = [];

if (!empty($categorySlug)) {
    $sql .= " AND c.slug = ?";
    $params[] = $categorySlug;
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $like = "%$searchQuery%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($priceFilter === 'under299') {
    $sql .= " AND p.price < 300";
} elseif ($priceFilter === '300to499') {
    $sql .= " AND p.price BETWEEN 300 AND 499";
} elseif ($priceFilter === '500to999') {
    $sql .= " AND p.price BETWEEN 500 AND 999";
} elseif ($priceFilter === 'above1000') {
    $sql .= " AND p.price >= 1000";
}

if ($minRating > 0) {
    $sql .= " AND p.rating_avg >= ?";
    $params[] = $minRating;
}

// Sorting
switch ($sortBy) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY p.rating_avg DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY p.created_at DESC";
        break;
    default:
        $sql .= " ORDER BY p.is_featured DESC, p.id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch active promotional banners
$stmtBanners = $pdo->query("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC");
$banners = $stmtBanners->fetchAll();
?>

<main class="main-content">
  <div class="container">

    <!-- 1. HERO SLIDER SECTION (Only shown when no search or category filter is active) -->
    <?php if (empty($categorySlug) && empty($searchQuery)): ?>
      <section class="hero-slider-section">
        <div class="hero-slider-container">
          <div class="slider-track">
            <?php foreach ($banners as $b): ?>
              <div class="hero-slide">
                <img src="<?php echo htmlspecialchars($b['image_url']); ?>" alt="" class="hero-slide-bg">
                <div class="hero-slide-content">
                  <?php if (!empty($b['badge'])): ?>
                    <span class="hero-badge"><?php echo htmlspecialchars($b['badge']); ?></span>
                  <?php endif; ?>
                  <h2><?php echo htmlspecialchars($b['title']); ?></h2>
                  <p><?php echo htmlspecialchars($b['subtitle']); ?></p>
                  <a href="<?php echo htmlspecialchars($b['link_url']); ?>" class="hero-btn">
                    <?php echo htmlspecialchars($b['button_text']); ?> <i class="fas fa-arrow-right"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <!-- Slider Navigation Arrows -->
          <button class="slider-arrow prev"><i class="fas fa-chevron-left"></i></button>
          <button class="slider-arrow next"><i class="fas fa-chevron-right"></i></button>
          <!-- Slider Indicator Dots -->
          <div class="slider-dots">
            <?php foreach ($banners as $idx => $b): ?>
              <div class="slider-dot <?php echo $idx === 0 ? 'active' : ''; ?>"></div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <!-- 2. VALUE PROPOSITIONS (MEESHO PROMISES) -->
      <section class="value-props-section">
        <div class="value-props-grid">
          <div class="value-prop-card">
            <div class="value-prop-icon"><i class="fas fa-tags"></i></div>
            <div class="value-prop-text">
              <h4>Lowest Prices</h4>
              <p>Direct from wholesale manufacturers</p>
            </div>
          </div>
          <div class="value-prop-card">
            <div class="value-prop-icon"><i class="fas fa-shipping-fast"></i></div>
            <div class="value-prop-text">
              <h4>Free Delivery</h4>
              <p>On all orders with no minimum value</p>
            </div>
          </div>
          <div class="value-prop-card">
            <div class="value-prop-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="value-prop-text">
              <h4>Cash On Delivery</h4>
              <p>Pay when package arrives at doorstep</p>
            </div>
          </div>
          <div class="value-prop-card">
            <div class="value-prop-icon"><i class="fas fa-undo-alt"></i></div>
            <div class="value-prop-text">
              <h4>Easy Returns</h4>
              <p>7-day hassle-free refund process</p>
            </div>
          </div>
        </div>
      </section>

      <!-- 3. TOP CATEGORIES TO CHOOSE FROM -->
      <section class="top-cats-section">
        <h3 class="section-header-title">Top Categories to choose from</h3>
        <div class="category-circles-scroll">
          <?php foreach ($allCategories as $cat): ?>
            <a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>" class="cat-circle-card">
              <div class="cat-circle-img-wrap">
                <img src="<?php echo htmlspecialchars($cat['image'] ?: 'https://placehold.co/100'); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
              </div>
              <span><?php echo htmlspecialchars($cat['name']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- 4. "PRODUCTS FOR YOU" FEED WITH SIDEBAR -->
    <div style="margin-top: 24px;">
      <h2 class="section-header-title">
        <?php 
          if (!empty($searchQuery)) {
            echo 'Search results for "' . htmlspecialchars($searchQuery) . '"';
          } elseif (!empty($categorySlug)) {
            $catName = '';
            foreach ($allCategories as $c) {
              if ($c['slug'] === $categorySlug) { $catName = $c['name']; break; }
            }
            echo htmlspecialchars($catName ?: 'Products');
          } else {
            echo 'Products For You';
          }
        ?>
      </h2>

      <div class="products-feed-layout">
        <!-- Sidebar Filters -->
        <aside class="filters-sidebar">
          <div class="filters-header">
            <h3>Filters</h3>
            <a href="/MEESHO/index.php<?php echo !empty($categorySlug) ? '?category=' . urlencode($categorySlug) : ''; ?>" class="clear-filters-btn">RESET ALL</a>
          </div>

          <form action="/MEESHO/index.php" method="GET" id="filter-form">
            <?php if (!empty($categorySlug)): ?>
              <input type="hidden" name="category" value="<?php echo htmlspecialchars($categorySlug); ?>">
            <?php endif; ?>
            <?php if (!empty($searchQuery)): ?>
              <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
            <?php endif; ?>

            <!-- Category Filter (if browsing all) -->
            <?php if (empty($categorySlug)): ?>
              <div class="filter-group">
                <div class="filter-title">Category</div>
                <?php foreach (array_slice($allCategories, 0, 6) as $c): ?>
                  <label class="filter-option">
                    <input type="radio" name="category" value="<?php echo htmlspecialchars($c['slug']); ?>" onchange="document.getElementById('filter-form').submit();">
                    <span><?php echo htmlspecialchars($c['name']); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- Price Filter -->
            <div class="filter-group">
              <div class="filter-title">Price</div>
              <label class="filter-option">
                <input type="radio" name="price" value="" <?php echo empty($priceFilter) ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                <span>All Prices</span>
              </label>
              <label class="filter-option">
                <input type="radio" name="price" value="under299" <?php echo $priceFilter === 'under299' ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                <span>Under ₹299</span>
              </label>
              <label class="filter-option">
                <input type="radio" name="price" value="300to499" <?php echo $priceFilter === '300to499' ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                <span>₹300 - ₹499</span>
              </label>
              <label class="filter-option">
                <input type="radio" name="price" value="500to999" <?php echo $priceFilter === '500to999' ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                <span>₹500 - ₹999</span>
              </label>
              <label class="filter-option">
                <input type="radio" name="price" value="above1000" <?php echo $priceFilter === 'above1000' ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                <span>₹1000 and Above</span>
              </label>
            </div>

            <!-- Rating Filter -->
            <div class="filter-group">
              <div class="filter-title">Rating</div>
              <label class="filter-option">
                <input type="radio" name="rating" value="4.0" <?php echo $minRating >= 4.0 ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                <span>4.0 ★ and above</span>
              </label>
              <label class="filter-option">
                <input type="radio" name="rating" value="3.5" <?php echo ($minRating >= 3.5 && $minRating < 4.0) ? 'checked' : ''; ?> onchange="document.getElementById('filter-form').submit();">
                <span>3.5 ★ and above</span>
              </label>
            </div>

            <!-- Assured Promises -->
            <div class="filter-group">
              <div class="filter-title">Delivery & Perks</div>
              <label class="filter-option">
                <input type="checkbox" checked disabled>
                <span>Free Delivery Guaranteed</span>
              </label>
              <label class="filter-option">
                <input type="checkbox" checked disabled>
                <span>Cash On Delivery Available</span>
              </label>
            </div>
          </form>
        </aside>

        <!-- Main Feed Column -->
        <section class="products-feed-main">
          <div class="feed-toolbar">
            <div class="feed-count">
              Showing <strong><?php echo count($products); ?></strong> items
            </div>
            <div class="sort-container">
              <span>Sort by:</span>
              <select class="sort-select" onchange="window.location.href=this.value;">
                <?php
                  $urlBase = '/MEESHO/index.php?' . http_build_query(array_merge($_GET, ['sort' => '']));
                ?>
                <option value="<?php echo $urlBase . 'relevance'; ?>" <?php echo $sortBy === 'relevance' ? 'selected' : ''; ?>>Relevance</option>
                <option value="<?php echo $urlBase . 'price_asc'; ?>" <?php echo $sortBy === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="<?php echo $urlBase . 'price_desc'; ?>" <?php echo $sortBy === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                <option value="<?php echo $urlBase . 'rating'; ?>" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Customer Rating</option>
                <option value="<?php echo $urlBase . 'newest'; ?>" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>New Arrivals</option>
              </select>
            </div>
          </div>

          <?php if (empty($products)): ?>
            <div style="text-align:center; padding: 60px 20px; background:#fff; border-radius:12px; border:1px solid #e6e9ef;">
              <i class="fas fa-box-open" style="font-size:48px; color:#c0c4cc; margin-bottom:16px;"></i>
              <h3 style="font-size:18px; color:#333; margin-bottom:8px;">No products found</h3>
              <p style="font-size:14px; color:#666; margin-bottom:20px;">Try clearing your filters or search for something else.</p>
              <a href="/MEESHO/index.php" class="btn-buy-now" style="display:inline-block; padding:10px 24px;">Browse All Products</a>
            </div>
          <?php else: ?>
            <div class="products-grid">
              <?php foreach ($products as $p): ?>
                <div class="product-card">
                  <a href="/MEESHO/product.php?id=<?php echo $p['id']; ?>" class="product-image-wrap">
                    <img src="<?php echo htmlspecialchars($p['primary_image'] ?: 'https://placehold.co/400x500'); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" loading="lazy">
                  </a>

                  <!-- Quick Action Buttons -->
                  <div class="quick-action-bar">
                    <button class="quick-btn" title="Quick View" onclick="openQuickView(<?php echo $p['id']; ?>)">
                      <i class="far fa-eye"></i>
                    </button>
                    <button class="quick-btn" title="Add to Cart" onclick="addToCart(<?php echo $p['id']; ?>, 'Free Size', 'Default', 1, this)">
                      <i class="fas fa-cart-plus"></i>
                    </button>
                  </div>

                  <div class="product-info-wrap">
                    <a href="/MEESHO/product.php?id=<?php echo $p['id']; ?>" class="product-title" title="<?php echo htmlspecialchars($p['title']); ?>">
                      <?php echo htmlspecialchars($p['title']); ?>
                    </a>

                    <div class="product-price-row">
                      <span class="product-price">₹<?php echo number_format($p['price'], 0); ?></span>
                      <span class="product-mrp">₹<?php echo number_format($p['mrp'], 0); ?></span>
                      <span class="product-discount"><?php echo $p['discount_percent']; ?>% off</span>
                    </div>

                    <div class="delivery-badge">
                      <i class="fas fa-truck"></i> Free Delivery
                    </div>

                    <div class="rating-row">
                      <span class="rating-pill">
                        <?php echo number_format($p['rating_avg'], 1); ?> <i class="fas fa-star" style="font-size:9px;"></i>
                      </span>
                      <span class="reviews-count-text"><?php echo number_format($p['rating_count']); ?> Ratings</span>
                    </div>

                    <div class="supplier-badge">
                      <span><i class="fas fa-store"></i> <?php echo htmlspecialchars($p['shop_name']); ?></span>
                      <span style="color:#038d63; font-weight:600;"><i class="fas fa-shield-alt"></i> Trusted</span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
