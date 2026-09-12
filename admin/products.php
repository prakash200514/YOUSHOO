<?php
/**
 * Admin Suite - Catalog & Product Quality Moderation
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_admin();

$pdo = getDBConnection();
$msg = '';

// Handle Status or Featured toggles
if (isset($_GET['action']) && isset($_GET['id'])) {
    $prodId = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $pdo->prepare("UPDATE products SET status = 'approved' WHERE id = ?")->execute([$prodId]);
        $msg = "Product approved for storefront listing.";
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE products SET status = 'rejected' WHERE id = ?")->execute([$prodId]);
        $msg = "Product rejected.";
    } elseif ($action === 'feature') {
        $pdo->prepare("UPDATE products SET is_featured = 1 WHERE id = ?")->execute([$prodId]);
        $msg = "Product marked as Featured.";
    } elseif ($action === 'unfeature') {
        $pdo->prepare("UPDATE products SET is_featured = 0 WHERE id = ?")->execute([$prodId]);
        $msg = "Product removed from Featured.";
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$prodId]);
        $msg = "Product deleted permanently.";
    }
}

// Filters
$catFilter = (int)($_GET['category'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT p.*, c.name as category_name, s.shop_name,
    (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) as primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN suppliers s ON p.supplier_id = s.id
    WHERE 1=1";
$params = [];

if ($catFilter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

if ($statusFilter !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (p.title LIKE ? OR p.sku LIKE ? OR s.shop_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catalog Moderation | Meesho Admin Suite</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
</head>
<body style="background:#f4f6fa;">

  <div class="dashboard-container">
    <aside class="dashboard-sidebar">
      <div class="dash-brand">
        meesho <span style="color:#f43397;">Admin</span>
      </div>
      <ul class="dash-nav">
        <li class="dash-nav-item"><a href="/MEESHO/admin/index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li class="dash-nav-item active"><a href="/MEESHO/admin/products.php"><i class="fas fa-boxes"></i> Catalog & Moderation</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/suppliers.php"><i class="fas fa-store"></i> Supplier Directory</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/categories.php"><i class="fas fa-tags"></i> Category Master</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/orders.php"><i class="fas fa-shopping-cart"></i> Global Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/banners.php"><i class="fas fa-images"></i> Marketing Banners</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Customer Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222;">Product Catalog & Moderation</h1>
          <p style="font-size:13px; color:#666;">Review supplier listings, approve products, feature deals and monitor stock</p>
        </div>
      </div>

      <?php if (!empty($msg)): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <!-- Filter Controls Toolbar -->
      <form action="/MEESHO/admin/products.php" method="GET" style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; background:#fff; padding:16px; border-radius:8px; border:1px solid #e6e9ef;">
        <input type="text" name="q" placeholder="Search by title, SKU, or shop..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; min-width:200px; padding:8px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
        
        <select name="category" style="padding:8px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; background:#fff; outline:none;">
          <option value="0">All Categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <select name="status" style="padding:8px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; background:#fff; outline:none;">
          <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
          <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>

        <button type="submit" class="btn-buy-now" style="padding:8px 18px; font-size:13px;">Filter</button>
      </form>

      <!-- Products Master Table -->
      <div class="table-card">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Product Details</th>
              <th>Supplier Shop</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th>Featured</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr>
                <td colspan="8" style="text-align:center; padding:40px; color:#888;">No products matching criteria found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                      <img src="<?php echo htmlspecialchars($p['primary_image'] ?: 'https://placehold.co/40'); ?>" style="width:40px; height:50px; object-fit:cover; border-radius:4px; border:1px solid #e6e9ef;">
                      <div>
                        <div style="font-weight:700; font-size:13px; color:#333; max-width:220px; line-height:1.3;"><?php echo htmlspecialchars($p['title']); ?></div>
                        <div style="font-size:11px; color:#888;">SKU: <?php echo htmlspecialchars($p['sku']); ?></div>
                      </div>
                    </div>
                  </td>
                  <td><strong><?php echo htmlspecialchars($p['shop_name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                  <td><strong style="color:#038d63;">₹<?php echo number_format($p['price'], 0); ?></strong></td>
                  <td><?php echo $p['stock']; ?> units</td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($p['status']); ?>">
                      <?php echo ucfirst($p['status']); ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($p['is_featured']): ?>
                      <a href="/MEESHO/admin/products.php?action=unfeature&id=<?php echo $p['id']; ?>" style="color:#f59e0b; font-size:16px;" title="Unfeature"><i class="fas fa-star"></i></a>
                    <?php else: ?>
                      <a href="/MEESHO/admin/products.php?action=feature&id=<?php echo $p['id']; ?>" style="color:#d1d5db; font-size:16px;" title="Feature on Homepage"><i class="far fa-star"></i></a>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex; gap:6px;">
                      <?php if ($p['status'] !== 'approved'): ?>
                        <a href="/MEESHO/admin/products.php?action=approve&id=<?php echo $p['id']; ?>" class="nav-link-btn" style="color:#038d63; padding:4px 8px; font-size:11px; font-weight:700;" title="Approve">
                          <i class="fas fa-check"></i> Approve
                        </a>
                      <?php endif; ?>
                      <?php if ($p['status'] !== 'rejected'): ?>
                        <a href="/MEESHO/admin/products.php?action=reject&id=<?php echo $p['id']; ?>" class="nav-link-btn" style="color:#dc2626; padding:4px 8px; font-size:11px;" title="Reject">
                          <i class="fas fa-ban"></i>
                        </a>
                      <?php endif; ?>
                      <a href="/MEESHO/admin/products.php?action=delete&id=<?php echo $p['id']; ?>" onclick="return confirm('Permanently remove this product from the database?')" class="nav-link-btn" style="color:#dc2626; padding:4px 8px; font-size:11px;" title="Delete">
                        <i class="far fa-trash-alt"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>

</body>
</html>
