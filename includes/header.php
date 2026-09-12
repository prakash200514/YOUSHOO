<?php
/**
 * Meesho Header Template
 */
require_once __DIR__ . '/auth_helper.php';

$currentUser = get_logged_user();
$cartCount = get_cart_count();

// Fetch categories for mega menu
$pdo = getDBConnection();
$stmtCat = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC");
$allCategories = $stmtCat->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | Youshoo' : 'Youshoo: Online Shopping for Sarees, Kurtis, Fashion, Electronics & More'; ?></title>
  
  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Custom Meesho CSS -->
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
</head>
<body>

  <!-- Top Announcement Bar -->
  <div class="top-bar">
    <div class="container">
      ✨ <strong>Mega Summer Sale is Live!</strong> Lowest Prices on 50 Lakh+ Products &bull; Free Delivery On All Orders
      <span>0% Commission for Sellers</span>
    </div>
  </div>

  <!-- Main Sticky Header -->
  <header class="meesho-header">
    <div class="container">
      <div class="nav-main">
        <!-- Logo -->
        <a href="/MEESHO/index.php" class="brand-logo">
          youshoo
          <span class="brand-tag">Market</span>
        </a>

        <!-- Search Bar with Live Autocomplete -->
        <div class="search-container">
          <form action="/MEESHO/index.php" method="GET" class="search-bar" id="header-search-form">
            <i class="fas fa-search"></i>
            <input type="text" name="search" id="header-search-input" placeholder="Try Saree, Kurti or Search by Product Code" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" autocomplete="off">
            <?php if (!empty($_GET['search'])): ?>
              <a href="/MEESHO/index.php" style="color:#999; margin-left:8px;"><i class="fas fa-times"></i></a>
            <?php endif; ?>
          </form>
          <div class="search-results-dropdown" id="search-results-dropdown"></div>
        </div>

        <!-- Right Actions -->
        <div class="nav-actions">
          <!-- Become a Supplier -->
          <a href="/MEESHO/supplier/index.php" class="nav-link-btn seller-cta">
            <i class="fas fa-store"></i>
            <span>Become a Supplier</span>
          </a>

          <!-- Profile Dropdown -->
          <div class="profile-menu-container">
            <div class="profile-trigger">
              <i class="far fa-user"></i>
              <span>Profile</span>
            </div>
            <div class="profile-dropdown">
              <div class="profile-dropdown-header">
                <?php if ($currentUser): ?>
                  <h4>Hello, <?php echo htmlspecialchars($currentUser['name']); ?>!</h4>
                  <p><?php echo htmlspecialchars($currentUser['email']); ?> (<?php echo ucfirst($currentUser['role']); ?>)</p>
                <?php else: ?>
                  <h4>Welcome to Youshoo</h4>
                  <p>To access your orders and wishlist</p>
                  <a href="/MEESHO/auth.php" class="btn-login-purple">Sign In / Register</a>
                <?php endif; ?>
              </div>
              <ul class="profile-links">
                <?php if ($currentUser): ?>
                  <?php if ($currentUser['role'] === 'customer'): ?>
                    <li><a href="/MEESHO/orders.php"><i class="fas fa-box-open"></i> My Orders</a></li>
                  <?php elseif ($currentUser['role'] === 'supplier'): ?>
                    <li><a href="/MEESHO/supplier/dashboard.php"><i class="fas fa-tachometer-alt"></i> Supplier Dashboard</a></li>
                  <?php elseif ($currentUser['role'] === 'admin'): ?>
                    <li><a href="/MEESHO/admin/index.php"><i class="fas fa-user-shield"></i> Admin Control</a></li>
                  <?php endif; ?>
                  <li><a href="/MEESHO/logout.php" style="color:#dc2626;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                <?php else: ?>
                  <li><a href="/MEESHO/auth.php?tab=register"><i class="fas fa-user-plus"></i> Create Customer Account</a></li>
                  <li><a href="/MEESHO/orders.php"><i class="fas fa-box-open"></i> My Orders</a></li>
                  <li><a href="/MEESHO/supplier/index.php"><i class="fas fa-briefcase"></i> Supplier Hub</a></li>
                <?php endif; ?>
              </ul>
            </div>
          </div>

          <!-- Cart Icon -->
          <a href="/MEESHO/cart.php" class="cart-btn" id="header-cart-btn">
            <i class="fas fa-shopping-bag"></i>
            <span>Cart</span>
            <span class="cart-badge"><?php echo $cartCount; ?></span>
          </a>
        </div>
      </div>
    </div>

    <!-- Mega Navigation Category Bar -->
    <div class="category-nav-wrapper">
      <div class="container">
        <ul class="category-nav">
          <?php foreach ($allCategories as $cat): ?>
            <li class="cat-item <?php echo (isset($_GET['category']) && $_GET['category'] === $cat['slug']) ? 'active' : ''; ?>">
              <a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>" class="cat-link">
                <?php echo htmlspecialchars($cat['name']); ?>
              </a>
              <!-- Mega Dropdown Panel -->
              <div class="mega-dropdown">
                <div class="mega-column">
                  <h5>Popular in <?php echo htmlspecialchars($cat['name']); ?></h5>
                  <ul>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>&sort=popular">All New Arrivals</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>&sort=rating">Top Rated Collection</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>&deal=bestseller">Best Sellers</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>&price_max=499">Under ₹499 Deals</a></li>
                  </ul>
                </div>
                <div class="mega-column">
                  <h5>Trending Styles</h5>
                  <ul>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>">Festive & Occasion</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>">Daily Essentials</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>">Premium Collections</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>">Budget Combos</a></li>
                  </ul>
                </div>
                <div class="mega-column">
                  <h5>Customer Favorites</h5>
                  <ul>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>">Youshoo Choice</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>">Lowest Prices Guaranteed</a></li>
                    <li><a href="/MEESHO/index.php?category=<?php echo urlencode($cat['slug']); ?>">Customer Photos & Reviews</a></li>
                  </ul>
                </div>
                <div class="mega-column">
                  <h5>Services</h5>
                  <ul>
                    <li><span style="color:#038d63; font-size:12px; font-weight:700;"><i class="fas fa-check-circle"></i> Free Delivery</span></li>
                    <li><span style="color:#555; font-size:12px;"><i class="fas fa-undo"></i> 7 Days Easy Returns</span></li>
                    <li><span style="color:#555; font-size:12px;"><i class="fas fa-money-bill-wave"></i> Cash On Delivery</span></li>
                  </ul>
                </div>
                <div class="mega-banner">
                  <img src="<?php echo htmlspecialchars($cat['image'] ?: 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=300&auto=format&fit=crop&q=80'); ?>" alt="">
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </header>
