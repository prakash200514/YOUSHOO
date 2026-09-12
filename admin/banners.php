<?php
/**
 * Admin Suite - Homepage Marketing Banners & Promo Slider Manager
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_admin();

$pdo = getDBConnection();
$msg = '';
$err = '';

// Add Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $badge = trim($_POST['badge'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '#');
    $buttonText = trim($_POST['button_text'] ?? 'Shop Now');
    $sortOrder = (int)($_POST['sort_order'] ?? 1);

    if (empty($title) || empty($imageUrl)) {
        $err = "Banner title and image URL are required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO banners (title, subtitle, badge, image_url, link_url, button_text, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt->execute([$title, $subtitle, $badge, $imageUrl, $linkUrl, $buttonText, $sortOrder]);
        $msg = "Marketing banner added to homepage carousel!";
    }
}

// Toggle or Delete Banner
if (isset($_GET['action']) && isset($_GET['id'])) {
    $bId = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'toggle') {
        $pdo->prepare("UPDATE banners SET active = IF(active=1, 0, 1) WHERE id = ?")->execute([$bId]);
        $msg = "Banner status updated.";
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([$bId]);
        $msg = "Banner deleted.";
    }
}

// Fetch Banners
$banners = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Marketing Banners | Youshoo Admin Suite</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
</head>
<body style="background:#f4f6fa;">

  <div class="dashboard-container">
    <aside class="dashboard-sidebar">
      <div class="dash-brand">
        youshoo <span style="color:#f43397;">Admin</span>
      </div>
      <ul class="dash-nav">
        <li class="dash-nav-item"><a href="/MEESHO/admin/index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/products.php"><i class="fas fa-boxes"></i> Catalog & Moderation</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/suppliers.php"><i class="fas fa-store"></i> Supplier Directory</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/categories.php"><i class="fas fa-tags"></i> Category Master</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/orders.php"><i class="fas fa-shopping-cart"></i> Global Orders</a></li>
        <li class="dash-nav-item active"><a href="/MEESHO/admin/banners.php"><i class="fas fa-images"></i> Marketing Banners</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Customer Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222;">Marketing & Carousel Banners</h1>
          <p style="font-size:13px; color:#666;">Design promotional campaigns, sale announcements and featured categories</p>
        </div>
      </div>

      <?php if (!empty($msg)): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($err)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($err); ?>
        </div>
      <?php endif; ?>

      <div style="display:grid; grid-template-columns: 360px 1fr; gap:24px;">
        <!-- Add Banner Form -->
        <div class="table-card" style="height:fit-content;">
          <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px;">Create Promo Banner</h3>
          
          <form action="/MEESHO/admin/banners.php" method="POST">
            <input type="hidden" name="add_banner" value="1">

            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Campaign Title *</label>
              <input type="text" name="title" required placeholder="e.g. Grand Festive Dhamaka" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Subtitle / Offer Details</label>
              <input type="text" name="subtitle" placeholder="e.g. Up to 80% Off on Kurtas & Sarees" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Offer Badge Pill</label>
              <input type="text" name="badge" placeholder="e.g. FLAT 70% OFF" value="FLAT 70% OFF" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Banner Image URL *</label>
              <input type="url" name="image_url" required placeholder="https://images.unsplash.com/..." value="https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1200&auto=format&fit=crop&q=80" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:12px;">
              <div>
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Button Text</label>
                <input type="text" name="button_text" value="Shop Now" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
              </div>
              <div>
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Sort Order</label>
                <input type="number" name="sort_order" value="1" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
              </div>
            </div>

            <div style="margin-bottom:18px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Target Link URL</label>
              <input type="text" name="link_url" value="/MEESHO/index.php?category=women-ethnic" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <button type="submit" class="btn-buy-now" style="width:100%; padding:10px; font-size:14px;">
              Publish Banner
            </button>
          </form>
        </div>

        <!-- Banners List -->
        <div class="table-card">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Preview</th>
                <th>Campaign Details</th>
                <th>Badge</th>
                <th>Order</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($banners as $b): ?>
                <tr>
                  <td>
                    <img src="<?php echo htmlspecialchars($b['image_url']); ?>" style="width:90px; height:50px; object-fit:cover; border-radius:6px;">
                  </td>
                  <td>
                    <strong style="color:#333; font-size:14px;"><?php echo htmlspecialchars($b['title']); ?></strong>
                    <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($b['subtitle']); ?></div>
                    <div style="font-size:11px; color:#9f2089;"><?php echo htmlspecialchars($b['button_text']); ?> &rarr; <?php echo htmlspecialchars($b['link_url']); ?></div>
                  </td>
                  <td>
                    <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:2px 8px; border-radius:12px;">
                      <?php echo htmlspecialchars($b['badge']); ?>
                    </span>
                  </td>
                  <td><?php echo $b['sort_order']; ?></td>
                  <td>
                    <span class="status-badge <?php echo $b['active'] ? 'active' : 'rejected'; ?>">
                      <?php echo $b['active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:6px;">
                      <a href="/MEESHO/admin/banners.php?action=toggle&id=<?php echo $b['id']; ?>" class="nav-link-btn" style="padding:4px 8px; font-size:11px;">
                        <?php echo $b['active'] ? 'Hide' : 'Show'; ?>
                      </a>
                      <a href="/MEESHO/admin/banners.php?action=delete&id=<?php echo $b['id']; ?>" onclick="return confirm('Delete banner?')" class="nav-link-btn" style="color:#dc2626; padding:4px 8px; font-size:11px;">
                        <i class="far fa-trash-alt"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>

</body>
</html>
