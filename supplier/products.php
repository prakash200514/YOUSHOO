<?php
/**
 * Supplier Catalog Management - Add, Edit, Delete Products
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_supplier();

$pdo = getDBConnection();
$supplier = get_current_supplier();
$supplierId = $supplier['id'];
$msg = '';
$err = '';

// Handle Product Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_product'])) {
    $title = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $mrp = (float)($_POST['mrp'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 10));
    $sizes = trim($_POST['sizes'] ?? 'Free Size');
    $colors = trim($_POST['colors'] ?? 'Multi');
    $fabric = trim($_POST['fabric'] ?? 'Cotton Blend');
    $description = trim($_POST['description'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');

    if (empty($title) || $categoryId <= 0 || $price <= 0 || $mrp <= 0) {
        $err = "Please fill in product title, valid category, selling price, and MRP.";
    } else {
        if ($mrp < $price) $mrp = $price * 1.5;
        $discount = round((($mrp - $price) / $mrp) * 100);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();
        $sku = 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $stmtIns = $pdo->prepare("INSERT INTO products 
            (supplier_id, category_id, title, slug, description, price, mrp, discount_percent, stock, sku, sizes, colors, fabric, free_delivery, cod_available, rating_avg, rating_count, status, is_featured)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 4.3, 10, 'approved', 0)");
        $stmtIns->execute([$supplierId, $categoryId, $title, $slug, $description, $price, $mrp, $discount, $stock, $sku, $sizes, $colors, $fabric]);
        $newProdId = $pdo->lastInsertId();

        // Add Image
        if (empty($imageUrl)) {
            $imageUrl = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&auto=format&fit=crop&q=80';
        }
        $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, 1, 0)")
            ->execute([$newProdId, $imageUrl]);

        $msg = "Product successfully listed on Meesho!";
    }
}

// Handle Delete Product
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = ? AND supplier_id = ?");
    $stmtDel->execute([$delId, $supplierId]);
    header("Location: /MEESHO/supplier/products.php?msg=deleted");
    exit;
}

// Fetch all products for this supplier
$stmtList = $pdo->prepare("SELECT p.*, c.name as category_name,
    (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) as primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.supplier_id = ?
    ORDER BY p.id DESC");
$stmtList->execute([$supplierId]);
$myProducts = $stmtList->fetchAll();

// Fetch Categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Products | Supplier Hub | Meesho</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
</head>
<body style="background:#f4f6fa;">

  <div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
      <div class="dash-brand">
        meesho <span>Supplier</span>
      </div>
      <div style="padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.08);">
        <div style="font-size:14px; font-weight:700; color:#fff;"><?php echo htmlspecialchars($supplier['shop_name']); ?></div>
        <div style="font-size:12px; color:#23bb75;"><i class="fas fa-check-circle"></i> Active Supplier</div>
      </div>
      <ul class="dash-nav">
        <li class="dash-nav-item"><a href="/MEESHO/supplier/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="dash-nav-item active"><a href="/MEESHO/supplier/products.php"><i class="fas fa-boxes"></i> Product Catalog</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/supplier/orders.php"><i class="fas fa-shopping-bag"></i> Customer Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/supplier/profile.php"><i class="fas fa-store"></i> Shop Profile</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222;">Product Catalog</h1>
          <p style="font-size:13px; color:#666;">Upload new products, manage wholesale inventory and live prices</p>
        </div>
        <button onclick="document.getElementById('add-product-modal').classList.add('open')" class="btn-buy-now" style="font-size:14px; padding:10px 20px;">
          <i class="fas fa-plus-circle"></i> Upload New Product
        </button>
      </div>

      <?php if (!empty($msg) || (isset($_GET['msg']) && $_GET['msg'] === 'deleted')): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-check-circle"></i> <?php echo !empty($msg) ? htmlspecialchars($msg) : "Product deleted successfully."; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($err)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($err); ?>
        </div>
      <?php endif; ?>

      <!-- Products Table -->
      <div class="table-card">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Image</th>
              <th>Product Details</th>
              <th>Category</th>
              <th>Price / MRP</th>
              <th>Discount</th>
              <th>Stock</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($myProducts)): ?>
              <tr>
                <td colspan="8" style="text-align:center; padding:40px; color:#888;">
                  You haven't listed any products yet. Click "Upload New Product" to start selling!
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($myProducts as $p): ?>
                <tr>
                  <td>
                    <img src="<?php echo htmlspecialchars($p['primary_image'] ?: 'https://placehold.co/50'); ?>" style="width:48px; height:58px; object-fit:cover; border-radius:4px; border:1px solid #e6e9ef;">
                  </td>
                  <td>
                    <div style="font-weight:700; color:#333; max-width:240px; line-height:1.3; margin-bottom:2px;"><?php echo htmlspecialchars($p['title']); ?></div>
                    <div style="font-size:11.5px; color:#777;">Sizes: <?php echo htmlspecialchars($p['sizes']); ?> &bull; SKU: <?php echo htmlspecialchars($p['sku']); ?></div>
                  </td>
                  <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                  <td>
                    <strong style="color:#038d63;">₹<?php echo number_format($p['price'], 0); ?></strong>
                    <div style="font-size:11px; color:#888; text-decoration:line-through;">₹<?php echo number_format($p['mrp'], 0); ?></div>
                  </td>
                  <td><span style="font-weight:700; color:#038d63;"><?php echo $p['discount_percent']; ?>% off</span></td>
                  <td>
                    <span style="font-weight:700; color:<?php echo $p['stock'] < 10 ? '#dc2626' : '#333'; ?>">
                      <?php echo $p['stock']; ?> units
                    </span>
                  </td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($p['status']); ?>">
                      <?php echo ucfirst($p['status']); ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:8px;">
                      <a href="/MEESHO/product.php?id=<?php echo $p['id']; ?>" target="_blank" class="nav-link-btn" style="padding:6px; font-size:13px;" title="View in Store">
                        <i class="far fa-eye"></i>
                      </a>
                      <a href="/MEESHO/supplier/products.php?action=delete&id=<?php echo $p['id']; ?>" onclick="return confirm('Are you sure you want to remove this product?')" class="nav-link-btn" style="padding:6px; font-size:13px; color:#dc2626;" title="Delete Product">
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

  <!-- Add Product Modal -->
  <div class="meesho-modal-backdrop" id="add-product-modal" <?php echo isset($_GET['action']) && $_GET['action'] === 'new' ? 'style="display:flex;"' : ''; ?>>
    <div class="meesho-modal-box" style="max-width: 680px; padding: 28px;">
      <button class="modal-close-btn" onclick="document.getElementById('add-product-modal').classList.remove('open')">&times;</button>
      
      <h2 style="font-size: 20px; font-weight: 800; color:#333; margin-bottom: 6px;">List New Product on Meesho</h2>
      <p style="font-size: 13px; color:#666; margin-bottom: 20px;">Fill in wholesale listing details. 0% Commission applies automatically.</p>

      <form action="/MEESHO/supplier/products.php" method="POST">
        <input type="hidden" name="create_product" value="1">

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Product Title *</label>
          <input type="text" name="title" required placeholder="e.g. Elegant Floral Georgette Printed Saree with Blouse Piece" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Category *</label>
            <select name="category_id" required style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none; background:#fff;">
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Fabric / Material</label>
            <input type="text" name="fabric" placeholder="e.g. Pure Cotton, Jacquard Silk" value="Pure Cotton" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px; margin-bottom:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Selling Price (₹) *</label>
            <input type="number" step="1" name="price" required placeholder="399" value="399" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">MRP Strike Price (₹) *</label>
            <input type="number" step="1" name="mrp" required placeholder="1299" value="1299" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Stock Inventory *</label>
            <input type="number" name="stock" required placeholder="50" value="75" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Available Sizes</label>
            <input type="text" name="sizes" value="S, M, L, XL, XXL" placeholder="e.g. Free Size or S, M, L, XL" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Colors</label>
            <input type="text" name="colors" value="Red, Navy Blue, Green" placeholder="e.g. Black, White, Maroon" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
        </div>

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Product Image URL</label>
          <input type="url" name="image_url" placeholder="https://images.unsplash.com/..." value="https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          <div style="font-size:11px; color:#888; margin-top:2px;">Paste any direct web image URL (Unsplash, Pexels, Imgur, or your image CDN).</div>
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Product Description *</label>
          <textarea name="description" rows="4" required style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">Premium high quality ethnic collection. Features vibrant color print, soft breathable fabric texture, comfortable regular fit, and long-lasting fabric durability. Ideal for festivities, family gatherings, and everyday wear.</textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px;">
          <button type="button" onclick="document.getElementById('add-product-modal').classList.remove('open')" style="padding:10px 20px; border:1px solid #d5d8de; background:#fff; border-radius:6px; font-weight:600; cursor:pointer;">Cancel</button>
          <button type="submit" class="btn-buy-now" style="padding:10px 24px;">Publish Product to Storefront</button>
        </div>
      </form>
    </div>
  </div>

</body>
</html>
