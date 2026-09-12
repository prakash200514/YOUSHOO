<?php
/**
 * Admin Suite - Category & Navigation Master
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_admin();

$pdo = getDBConnection();
$msg = '';
$err = '';

// Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-tag');
    $image = trim($_POST['image'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if (empty($name)) {
        $err = "Category name cannot be empty.";
    } else {
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }

        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, image, sort_order) VALUES (?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $slug, $icon, $image, $sortOrder]);
            $msg = "Category '{$name}' created successfully!";
        } catch (Exception $e) {
            $err = "Category with this slug already exists.";
        }
    }
}

// Delete Category
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    $msg = "Category deleted.";
}

// Fetch all categories
$stmt = $pdo->query("SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.sort_order ASC, c.name ASC");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Category Master | Meesho Admin Suite</title>
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
        <li class="dash-nav-item"><a href="/MEESHO/admin/products.php"><i class="fas fa-boxes"></i> Catalog & Moderation</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/suppliers.php"><i class="fas fa-store"></i> Supplier Directory</a></li>
        <li class="dash-nav-item active"><a href="/MEESHO/admin/categories.php"><i class="fas fa-tags"></i> Category Master</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/orders.php"><i class="fas fa-shopping-cart"></i> Global Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/banners.php"><i class="fas fa-images"></i> Marketing Banners</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Customer Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222;">Category Master & Mega Navigation</h1>
          <p style="font-size:13px; color:#666;">Organize top navigation, category banner images and catalog taxonomy</p>
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

      <div style="display:grid; grid-template-columns: 340px 1fr; gap:24px;">
        <!-- Add Category Form -->
        <div class="table-card" style="height:fit-content;">
          <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px;">Add New Category</h3>
          
          <form action="/MEESHO/admin/categories.php" method="POST">
            <input type="hidden" name="add_category" value="1">

            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Category Name *</label>
              <input type="text" name="name" required placeholder="e.g. Footwear & Watches" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Slug (URL friendly)</label>
              <input type="text" name="slug" placeholder="footwear-watches" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <div style="margin-bottom:12px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Thumbnail Image URL</label>
              <input type="url" name="image" placeholder="https://images.unsplash.com/..." style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <div style="margin-bottom:18px;">
              <label style="display:block; font-size:12.5px; font-weight:700; margin-bottom:4px;">Sort Order</label>
              <input type="number" name="sort_order" value="10" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
            </div>

            <button type="submit" class="btn-buy-now" style="width:100%; padding:10px; font-size:14px;">
              Create Category
            </button>
          </form>
        </div>

        <!-- Categories Table -->
        <div class="table-card">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Image</th>
                <th>Category Name</th>
                <th>Slug</th>
                <th>Products</th>
                <th>Order</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categories as $c): ?>
                <tr>
                  <td>
                    <img src="<?php echo htmlspecialchars($c['image'] ?: 'https://placehold.co/40'); ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                  </td>
                  <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                  <td style="font-family:Consolas, monospace; font-size:12px; color:#666;"><?php echo htmlspecialchars($c['slug']); ?></td>
                  <td><span class="status-badge active"><?php echo $c['product_count']; ?> products</span></td>
                  <td><?php echo $c['sort_order']; ?></td>
                  <td>
                    <a href="/MEESHO/admin/categories.php?action=delete&id=<?php echo $c['id']; ?>" onclick="return confirm('Delete category?')" class="nav-link-btn" style="color:#dc2626; padding:4px 8px; font-size:12px;">
                      <i class="far fa-trash-alt"></i> Delete
                    </a>
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
