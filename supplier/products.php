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

    $finalImageUrl = '';

    // Handle Uploaded File (JPG, JPEG, PNG)
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['product_image']['tmp_name'];
        $fileName = $_FILES['product_image']['name'];
        $fileSize = $_FILES['product_image']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png'];
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/pjpeg', 'image/x-png'];

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $fileTmp);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($fileTmp);
        }

        if (!in_array($ext, $allowedExts) || (!empty($mime) && !in_array($mime, $allowedMimes))) {
            $err = "Invalid image format! Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($fileSize > 10 * 1024 * 1024) {
            $err = "Image size exceeds 10MB limit.";
        } else {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $safeName = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $uploadDir . $safeName;

            if (move_uploaded_file($fileTmp, $destPath)) {
                $finalImageUrl = '/MEESHO/uploads/products/' . $safeName;
            } else {
                $err = "Failed to save uploaded image file.";
            }
        }
    } elseif (!empty($imageUrl)) {
        $finalImageUrl = $imageUrl;
    }

    if (empty($err)) {
        if (empty($title) || $categoryId <= 0 || $price <= 0 || $mrp <= 0) {
            $err = "Please fill in product title, valid category, selling price, and MRP.";
        } else {
            // Process Size-Specific Pricing if enabled
            $sizePrices = [];
            if (!empty($_POST['enable_size_pricing']) && !empty($_POST['size_price']) && is_array($_POST['size_price'])) {
                foreach ($_POST['size_price'] as $sName => $sPrice) {
                    $sNameClean = trim($sName);
                    $sPriceVal = (float)$sPrice;
                    $sMrpVal = isset($_POST['size_mrp'][$sName]) && (float)$_POST['size_mrp'][$sName] > 0
                        ? (float)$_POST['size_mrp'][$sName]
                        : ($sPriceVal * 1.5);

                    if ($sPriceVal > 0 && !empty($sNameClean)) {
                        $sizePrices[$sNameClean] = [
                            'price' => $sPriceVal,
                            'mrp'   => $sMrpVal
                        ];
                    }
                }

                if (!empty($sizePrices)) {
                    // Set base catalog price to lowest size price
                    $minP = null;
                    $minM = null;
                    foreach ($sizePrices as $sp) {
                        if ($minP === null || $sp['price'] < $minP) {
                            $minP = $sp['price'];
                            $minM = $sp['mrp'];
                        }
                    }
                    if ($minP !== null) {
                        $price = $minP;
                        $mrp = $minM;
                    }
                }
            }
            $sizePricesJson = !empty($sizePrices) ? json_encode($sizePrices) : null;

            if ($mrp < $price) $mrp = $price * 1.5;
            $discount = round((($mrp - $price) / $mrp) * 100);
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();
            $sku = 'SKU-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $stmtIns = $pdo->prepare("INSERT INTO products 
                (supplier_id, category_id, title, slug, description, price, mrp, discount_percent, stock, sku, sizes, size_prices, colors, fabric, free_delivery, cod_available, rating_avg, rating_count, status, is_featured)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 4.3, 10, 'approved', 0)");
            $stmtIns->execute([$supplierId, $categoryId, $title, $slug, $description, $price, $mrp, $discount, $stock, $sku, $sizes, $sizePricesJson, $colors, $fabric]);
            $newProdId = $pdo->lastInsertId();

            // Add Image
            if (empty($finalImageUrl)) {
                $finalImageUrl = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&auto=format&fit=crop&q=80';
            }
            $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, 1, 0)")
                ->execute([$newProdId, $finalImageUrl]);

            $msg = "Product successfully listed on Youshoo!";
        }
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

// Fetch Categories for dropdown (wears/apparel categories first)
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Products | Supplier Hub | Youshoo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
</head>
<body style="background:#f4f6fa;">

  <div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
      <div class="dash-brand">
        youshoo <span>Supplier</span>
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
                    <?php 
                      $hasSizePrices = !empty($p['size_prices']);
                    ?>
                    <strong style="color:#038d63;"><?php echo $hasSizePrices ? 'From ' : ''; ?>₹<?php echo number_format($p['price'], 0); ?></strong>
                    <div style="font-size:11px; color:#888; text-decoration:line-through;">₹<?php echo number_format($p['mrp'], 0); ?></div>
                    <?php if ($hasSizePrices): ?>
                      <div style="margin-top:2px;"><span style="display:inline-block; font-size:10px; background:#fdf2f8; color:#9f2089; border:1px solid #fbcfe8; padding:1px 5px; border-radius:4px; font-weight:700;"><i class="fas fa-tags" style="font-size:9px;"></i> Size Prices</span></div>
                    <?php endif; ?>
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
      
      <h2 style="font-size: 20px; font-weight: 800; color:#333; margin-bottom: 6px;">List New Product on Youshoo</h2>
      <p style="font-size: 13px; color:#666; margin-bottom: 20px;">Fill in wholesale listing details. 0% Commission applies automatically.</p>

      <form action="/MEESHO/supplier/products.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="create_product" value="1">

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Product Title *</label>
          <input type="text" name="title" id="product_title_input" oninput="onTitleOrCategoryChange()" required placeholder="e.g. Elegant Floral Georgette Printed Saree or Slim Core i5 Laptop" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Category *</label>
            <select name="category_id" id="category_select" onchange="onTitleOrCategoryChange()" required style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none; background:#fff;">
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" data-slug="<?php echo htmlspecialchars($cat['slug']); ?>" data-name="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label id="fabric_label" style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Fabric / Material</label>
            <input type="text" name="fabric" id="fabric_input" placeholder="e.g. Pure Cotton, Jacquard Silk" value="Pure Cotton" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
        </div>

        <!-- Quick Electronic Device Archetype Selector -->
        <div id="electronics_device_selector" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:13px 15px; margin-bottom:16px;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
            <div style="font-size:12.5px; font-weight:800; color:#0369a1; display:flex; align-items:center; gap:6px;">
              <i class="fas fa-bolt" style="color:#0284c7;"></i>
              <span>Quick Electronic Device Presets (27 Archetypes — Auto-fills Specs & Pricing):</span>
            </div>
            <div style="position:relative;">
              <input type="text" id="device_search_input" placeholder="🔍 Search device (e.g. TV, earbuds, SSD, camera)..." oninput="filterDevicePresets(this.value)" style="padding:5px 12px; font-size:11.5px; border:1px solid #7dd3fc; border-radius:20px; outline:none; width:260px; background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
            </div>
          </div>

          <!-- Filter Tabs -->
          <div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:10px; border-bottom:1px dashed #bae6fd; padding-bottom:8px;">
            <button type="button" class="device-tab-btn active-tab" onclick="filterDeviceTab('all', this)" style="padding:3px 10px; font-size:11px; font-weight:700; border-radius:12px; border:1px solid #0284c7; background:#0284c7; color:#fff; cursor:pointer; transition:all 0.2s;">
              All (27)
            </button>
            <button type="button" class="device-tab-btn" onclick="filterDeviceTab('computing', this)" style="padding:3px 10px; font-size:11px; font-weight:700; border-radius:12px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; cursor:pointer; transition:all 0.2s;">
              💻 Computing & Displays
            </button>
            <button type="button" class="device-tab-btn" onclick="filterDeviceTab('audio', this)" style="padding:3px 10px; font-size:11px; font-weight:700; border-radius:12px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; cursor:pointer; transition:all 0.2s;">
              🎧 Audio & Sound
            </button>
            <button type="button" class="device-tab-btn" onclick="filterDeviceTab('mobile', this)" style="padding:3px 10px; font-size:11px; font-weight:700; border-radius:12px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; cursor:pointer; transition:all 0.2s;">
              📱 Mobiles & Wearables
            </button>
            <button type="button" class="device-tab-btn" onclick="filterDeviceTab('smart_home', this)" style="padding:3px 10px; font-size:11px; font-weight:700; border-radius:12px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; cursor:pointer; transition:all 0.2s;">
              📷 Cameras & Smart Home
            </button>
            <button type="button" class="device-tab-btn" onclick="filterDeviceTab('gadgets', this)" style="padding:3px 10px; font-size:11px; font-weight:700; border-radius:12px; border:1px solid #cbd5e1; background:#f1f5f9; color:#475569; cursor:pointer; transition:all 0.2s;">
              🎮 Gaming & Gadgets
            </button>
          </div>

          <!-- Presets Grid -->
          <div id="device_presets_container" style="display:flex; flex-wrap:wrap; gap:6px;">
            <!-- 1. Laptop -->
            <button type="button" onclick="applyDevicePreset('laptop', this)" class="device-pill-btn" data-group="computing" data-keywords="laptop notebook macbook pc screen inch core i5 i7 ram display 14 15.6 16 hp dell lenovo asus" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>💻 Laptop</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">14", 15.6"</span>
            </button>

            <!-- 2. Smart TV -->
            <button type="button" onclick="applyDevicePreset('tv', this)" class="device-pill-btn" data-group="computing" data-keywords="tv television led 4k smart google bezel display 32 43 55 inch samsung lg mi sony" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📺 Smart TV</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">32", 43", 55"</span>
            </button>

            <!-- 3. PC Monitor -->
            <button type="button" onclick="applyDevicePreset('monitor', this)" class="device-pill-btn" data-group="computing" data-keywords="monitor display screen pc ips fhd 2k 4k curved gaming 22 24 27 inch 165hz" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🖥️ PC Monitor</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">22", 24", 27"</span>
            </button>

            <!-- 4. Desktop / AIO PC -->
            <button type="button" onclick="applyDevicePreset('desktop', this)" class="device-pill-btn" data-group="computing" data-keywords="desktop pc computer all-in-one aio tower cpu core i5 i7 ryzen lenovo hp" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🖥️ Desktop PC</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">AIO / Tower</span>
            </button>

            <!-- 5. Tablet / iPad -->
            <button type="button" onclick="applyDevicePreset('tablet', this)" class="device-pill-btn" data-group="computing" data-keywords="tablet ipad android pad wifi cellular 64gb 128gb 256gb tab apple samsung" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📱 Tablet/iPad</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">64GB, 128GB</span>
            </button>

            <!-- 6. Pen Drive & SSD Storage -->
            <button type="button" onclick="applyDevicePreset('storage', this)" class="device-pill-btn" data-group="computing" data-keywords="pendrive pen drive flash ssd nvme storage memory card micro sd usb type-c 64gb 128gb 256gb 1tb sandisk" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>💾 Pen Drive & SSD</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">64GB, 128GB, 1TB</span>
            </button>

            <!-- 7. WiFi Printer -->
            <button type="button" onclick="applyDevicePreset('printer', this)" class="device-pill-btn" data-group="computing" data-keywords="printer inktank inkjet scanner laser all in one wifi duplex canon hp epson brother" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🖨️ WiFi Printer</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Ink Tank, Laser</span>
            </button>

            <!-- 8. Headphones -->
            <button type="button" onclick="applyDevicePreset('headphone', this)" class="device-pill-btn" data-group="audio" data-keywords="headphone over-ear headset wireless anc bluetooth bass audio sony boat jbl bose" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🎧 Headphones</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Standard, ANC</span>
            </button>

            <!-- 9. TWS Earbuds -->
            <button type="button" onclick="applyDevicePreset('earbuds', this)" class="device-pill-btn" data-group="audio" data-keywords="earbuds tws airpods airpod earphone bluetooth wireless in-ear boat noise buds realme oneplus" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🎵 TWS Earbuds</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Standard, Pro ANC</span>
            </button>

            <!-- 10. Neckband Earphones -->
            <button type="button" onclick="applyDevicePreset('neckband', this)" class="device-pill-btn" data-group="audio" data-keywords="neckband earphone wireless magnetic bluetooth fast charging bass oneplus boat realme" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📿 Wireless Neckband</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Bass Ed., 40H</span>
            </button>

            <!-- 11. Bluetooth Speaker -->
            <button type="button" onclick="applyDevicePreset('speaker', this)" class="device-pill-btn" data-group="audio" data-keywords="speaker bluetooth portable wireless bass party audio outdoor jbl boom boat sony" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🔊 BT Speaker</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">10W, 20W, 40W</span>
            </button>

            <!-- 12. Soundbar & Subwoofer -->
            <button type="button" onclick="applyDevicePreset('soundbar', this)" class="device-pill-btn" data-group="audio" data-keywords="soundbar sound bar home theater subwoofer dolby atmos 2.1 5.1 channel surround zebronics boat" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📻 Soundbar 5.1</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">60W, 120W, 240W</span>
            </button>

            <!-- 13. Studio & Podcast Mic -->
            <button type="button" onclick="applyDevicePreset('mic', this)" class="device-pill-btn" data-group="audio" data-keywords="microphone mic podcast streaming condenser studio usb recording rgb fifine boya" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🎙️ Studio/Podcast Mic</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">USB, Boom Stand</span>
            </button>

            <!-- 14. Smartphone -->
            <button type="button" onclick="applyDevicePreset('smartphone', this)" class="device-pill-btn" data-group="mobile" data-keywords="smartphone mobile phone android 5g amoled oneplus samsung 128gb 256gb redmi realme xiaomi" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📱 Smartphone</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">128GB, 256GB</span>
            </button>

            <!-- 15. Smartwatch -->
            <button type="button" onclick="applyDevicePreset('smartwatch', this)" class="device-pill-btn" data-group="mobile" data-keywords="smartwatch smart watch fitness band dial calling 40mm 44mm amoled noise boat fireboltt" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>⌚ Smartwatch</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">40mm, 44mm</span>
            </button>

            <!-- 16. Power Bank -->
            <button type="button" onclick="applyDevicePreset('powerbank', this)" class="device-pill-btn" data-group="mobile" data-keywords="powerbank power bank battery fast charge mah 10000 20000 30000 pd mi ambrane realme" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🔋 Power Bank</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">10k, 20k, 30k mAh</span>
            </button>

            <!-- 17. GaN Fast Charger -->
            <button type="button" onclick="applyDevicePreset('charger', this)" class="device-pill-btn" data-group="mobile" data-keywords="charger gan fast charging type-c adapter pd 20w 33w 65w 100w vooc dash stuffcool" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>⚡ GaN Fast Charger</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">20W, 33W, 65W</span>
            </button>

            <!-- 18. Fast Charging Cable -->
            <button type="button" onclick="applyDevicePreset('cable', this)" class="device-pill-btn" data-group="mobile" data-keywords="cable data cable charging cord type-c lightning 65w 100w braided nylon 1m 2m ambrane" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🔌 Fast Cable</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Type-C 100W, 1.5m</span>
            </button>

            <!-- 19. Action Camera -->
            <button type="button" onclick="applyDevicePreset('camera', this)" class="device-pill-btn" data-group="smart_home" data-keywords="camera action camera 4k dashcam dash cam waterproof gopro vlog 1080p sjcam dji" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📷 Action Camera</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">1080p, 4K HD</span>
            </button>

            <!-- 20. 360 WiFi CCTV Camera -->
            <button type="button" onclick="applyDevicePreset('cctv', this)" class="device-pill-btn" data-group="smart_home" data-keywords="cctv security camera wifi smart home night vision 360 outdoor pan tilt tapo mi cp plus" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📹 360° CCTV Camera</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">1080p, 2K QHD</span>
            </button>

            <!-- 21. Smart Projector -->
            <button type="button" onclick="applyDevicePreset('projector', this)" class="device-pill-btn" data-group="smart_home" data-keywords="projector smart home cinema android 1080p 4k led beamer lumens home theater egate zebronics" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📽️ Smart Projector</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">720p, 1080p, 4K</span>
            </button>

            <!-- 22. WiFi-6 Router -->
            <button type="button" onclick="applyDevicePreset('router', this)" class="device-pill-btn" data-group="smart_home" data-keywords="router wifi modem gigabit ac1200 ax1800 mesh dual band tp-link d-link tenda" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>📡 WiFi-6 Router</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">AX1500, AX1800</span>
            </button>

            <!-- 23. Smart WiFi Bulb & Plug -->
            <button type="button" onclick="applyDevicePreset('smart_light', this)" class="device-pill-btn" data-group="smart_home" data-keywords="smart bulb plug led light alexa google home rgb wifi 9w 12w 16a wipro philips" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>💡 Smart WiFi Bulb</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">9W, 12W, 16M Color</span>
            </button>

            <!-- 24. Gaming Controller -->
            <button type="button" onclick="applyDevicePreset('gaming', this)" class="device-pill-btn" data-group="gadgets" data-keywords="gaming controller gamepad joystick wireless bluetooth pc android ps5 xbox triggers evofox redgear" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>🎮 Gaming Gamepad</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Wireless, Hall Pro</span>
            </button>

            <!-- 25. Keyboard & Mouse Combo -->
            <button type="button" onclick="applyDevicePreset('keyboard_mouse', this)" class="device-pill-btn" data-group="gadgets" data-keywords="keyboard mouse combo mechanical wireless rgb gaming silent switches logitech ant esports" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>⌨️ Keyboard & Mouse</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Wireless, Mech</span>
            </button>

            <!-- 26. Beard Trimmer / Groomer -->
            <button type="button" onclick="applyDevicePreset('trimmer', this)" class="device-pill-btn" data-group="gadgets" data-keywords="trimmer beard hair shaver grooming cordless philips titanium clipper razor mi nova" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>✂️ Beard Trimmer</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">Cordless, 120M</span>
            </button>

            <!-- 27. Ring Light & Tripod -->
            <button type="button" onclick="applyDevicePreset('ring_light', this)" class="device-pill-btn" data-group="gadgets" data-keywords="ring light ringlight tripod stand youtube video studio vlogging led 10 12 18 inch digitek" style="display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:14px; font-size:11.5px; font-weight:700; cursor:pointer; border:1px solid #bae6fd; background:#fff; color:#0369a1; transition:all 0.2s;">
              <span>💍 Ring Light & Tripod</span> <span class="pill-badge" style="font-size:9.5px; background:#e0f2fe; color:#0284c7; padding:1px 5px; border-radius:8px;">10", 12", 7ft Stand</span>
            </button>
          </div>

          <div id="device_no_match" style="display:none; padding:12px; text-align:center; font-size:12px; color:#64748b;">
            <i class="fas fa-search" style="margin-right:4px;"></i> No electronic devices found matching your search.
            <a href="javascript:void(0)" onclick="document.getElementById('device_search_input').value=''; filterDevicePresets('');" style="color:#0284c7; margin-left:6px; font-weight:600; text-decoration:underline;">Clear Search</a>
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px; margin-bottom:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Selling Price (₹) *</label>
            <input type="number" step="1" name="price" id="base_price_input" oninput="onBasePriceChange()" required placeholder="399" value="399" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">MRP Strike Price (₹) *</label>
            <input type="number" step="1" name="mrp" id="base_mrp_input" oninput="onBasePriceChange()" required placeholder="1299" value="1299" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Stock Inventory *</label>
            <input type="number" name="stock" required placeholder="50" value="75" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1.25fr 0.75fr; gap:14px; margin-bottom:14px;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <label style="font-size:12.5px; font-weight:700; color:#333;">Available Sizes *</label>
              <span id="category_size_hint" style="font-size:11px; color:#9f2089; font-weight:700;">Apparel Sizes</span>
            </div>
            <input type="text" name="sizes" id="sizes_input" value="S, M, L, XL, XXL" placeholder="e.g. Free Size, Adjustable or S, M, L" required oninput="onSizesInputChange()" onchange="onSizesInputChange()" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
            
            <!-- Quick Click Category Size Chips -->
            <div style="margin-top:6px;">
              <div style="font-size:10.5px; color:#666; margin-bottom:4px;">Click tags below to quickly add/remove sizes for this category:</div>
              <div id="size_chips_container" style="display:flex; flex-wrap:wrap; gap:5px;"></div>
            </div>
          </div>
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Colors</label>
            <input type="text" name="colors" id="colors_input" value="Red, Navy Blue, Green" placeholder="e.g. Gold, Silver, Rose Gold" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
            <div style="font-size:10.5px; color:#777; margin-top:5px;">Comma-separated color variants</div>
          </div>
        </div>

        <!-- Size-Specific Pricing Toggle & Dynamic Table -->
        <div style="background:#fdf2f8; border:1px solid #fbcfe8; border-radius:8px; padding:13px 15px; margin-bottom:16px;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; color:#831843; font-size:13px; margin:0;">
            <input type="checkbox" id="enable_size_pricing" name="enable_size_pricing" value="1" onchange="toggleSizePricingTable()" style="accent-color:#9f2089; width:16px; height:16px; cursor:pointer;">
            <span><i class="fas fa-tags" style="color:#9f2089; margin-right:3px;"></i> Set Different Price for Each Size (e.g. S: ₹450, M: ₹550, L: ₹650)</span>
          </label>
          <div style="font-size:11.5px; color:#9d174d; margin-top:3px; margin-left:24px;">
            Enable this if product price varies according to size. You can customize selling price and MRP for each selected size.
          </div>

          <div id="size_pricing_table_wrap" style="display:none; margin-top:12px;">
            <div style="background:#ffffff; border:1px solid #f472b6; border-radius:6px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
              <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
                <thead style="background:#fce7f3; color:#831843; text-align:left;">
                  <tr>
                    <th style="padding:9px 12px; font-weight:700; border-bottom:1px solid #fbcfe8;">Size</th>
                    <th style="padding:9px 12px; font-weight:700; border-bottom:1px solid #fbcfe8;">Selling Price (₹) *</th>
                    <th style="padding:9px 12px; font-weight:700; border-bottom:1px solid #fbcfe8;">MRP (₹)</th>
                    <th style="padding:9px 12px; font-weight:700; border-bottom:1px solid #fbcfe8;">Discount</th>
                  </tr>
                </thead>
                <tbody id="size_pricing_tbody">
                  <!-- Generated dynamically via JS -->
                </tbody>
              </table>
            </div>
            <div style="font-size:11px; color:#666; margin-top:6px;">
              💡 <em>Note: The lowest size price will be shown as the starting catalog price on search and browse cards.</em>
            </div>
          </div>
        </div>

        <!-- Product Image File Upload (JPG, JPEG, PNG) -->
        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">
            <i class="fas fa-image" style="color:#9f2089; margin-right:4px;"></i> Upload Product Image (.jpg, .jpeg, .png) *
          </label>
          
          <div id="drop-zone" onclick="document.getElementById('product-image-file').click()" style="border: 2px dashed #d5d8de; border-radius: 8px; padding: 20px; text-align: center; background: #fafafa; cursor: pointer; transition: all 0.2s ease;">
            <input type="file" id="product-image-file" name="product_image" accept=".jpg, .jpeg, .png, image/jpeg, image/png" style="display:none;" onchange="handleImagePreview(event)">
            
            <div id="upload-placeholder">
              <i class="fas fa-cloud-upload-alt" style="font-size: 34px; color: #9f2089; margin-bottom: 8px; display:inline-block;"></i>
              <div style="font-size: 13.5px; font-weight: 700; color: #333;">Click to browse or drop an image here</div>
              <div style="font-size: 11.5px; color: #666; margin-top: 4px;">Supported Formats: <strong>JPG, JPEG, PNG</strong> (Max size: 10MB)</div>
            </div>

            <div id="image-preview-container" style="display:none; align-items:center; justify-content:center; gap:16px;">
              <img id="image-preview-thumb" src="" alt="Preview" style="width: 70px; height: 85px; object-fit: cover; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd;">
              <div style="text-align:left;">
                <div id="image-file-name" style="font-size: 13px; font-weight: 700; color: #333;"></div>
                <div id="image-file-size" style="font-size: 11.5px; color: #666; margin-top: 2px;"></div>
                <button type="button" onclick="event.stopPropagation(); removeSelectedImage();" style="margin-top: 6px; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">
                  <i class="fas fa-trash-alt"></i> Remove / Change Image
                </button>
              </div>
            </div>
          </div>

          <div style="margin-top: 6px; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:11px; color:#888;">Select an image from your computer (.jpg, .jpeg, .png).</span>
            <a href="javascript:void(0)" onclick="toggleUrlFallback()" style="font-size:11.5px; color:#9f2089; font-weight:600; text-decoration:none;">
              <i class="fas fa-link"></i> Or paste image URL
            </a>
          </div>

          <div id="url-fallback-div" style="display:none; margin-top: 8px;">
            <input type="url" name="image_url" id="image-url-input" placeholder="https://images.unsplash.com/..." style="width:100%; padding:8px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">
          </div>
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

  <script>
    function handleImagePreview(event) {
      var file = event.target.files[0];
      if (file) {
        var ext = file.name.split('.').pop().toLowerCase();
        var allowedExts = ['jpg', 'jpeg', 'png'];
        if (!allowedExts.includes(ext)) {
          alert('Please select a valid image file with extension .jpg, .jpeg, or .png');
          event.target.value = '';
          return;
        }
        if (file.size > 10 * 1024 * 1024) {
          alert('Image size exceeds 10MB limit. Please choose a smaller file.');
          event.target.value = '';
          return;
        }

        var reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('image-preview-thumb').src = e.target.result;
          document.getElementById('image-file-name').innerText = file.name;
          document.getElementById('image-file-size').innerText = (file.size / 1024).toFixed(1) + ' KB';
          document.getElementById('upload-placeholder').style.display = 'none';
          document.getElementById('image-preview-container').style.display = 'flex';
          document.getElementById('drop-zone').style.borderColor = '#038d63';
          document.getElementById('drop-zone').style.background = '#f0fdf4';
        };
        reader.readAsDataURL(file);
      }
    }

    function removeSelectedImage() {
      document.getElementById('product-image-file').value = '';
      document.getElementById('image-preview-thumb').src = '';
      document.getElementById('upload-placeholder').style.display = 'block';
      document.getElementById('image-preview-container').style.display = 'none';
      document.getElementById('drop-zone').style.borderColor = '#d5d8de';
      document.getElementById('drop-zone').style.background = '#fafafa';
    }

    function toggleUrlFallback() {
      var el = document.getElementById('url-fallback-div');
      el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    }

    // Category-Adaptive Presets Configuration (Comprehensive 27+ Devices + General Categories)
    var categoryPresets = {
      'jewellery-accessories': {
        label: 'Material / Base Metal',
        fabricPlaceholder: 'e.g. Brass, Alloy, Gold Plated, Silver Plated, Kundan',
        fabricDefault: 'Brass & Kundan Stone',
        defaultSizes: 'Free Size, Adjustable',
        sizesHint: 'Jewellery Sizes (No S/M/L)',
        chips: ['Free Size', 'Adjustable', 'One Size', '2.4', '2.6', '2.8', 'Ring 12', 'Ring 14', 'Ring 16'],
        colorsDefault: 'Gold, Silver, Rose Gold',
        suggestedPrice: 299,
        suggestedMrp: 999,
        sampleTitle: 'Traditional Gold Plated Kundan Choker Necklace Set',
        sampleDesc: 'Exquisite handcrafted bridal jewelry set featuring shimmering kundan stones, durable brass base metal, lustrous gold polish, and adjustable drawstring dori.'
      },
      // 1. Computing & Displays
      'laptop': {
        label: 'Chassis / Build Material',
        fabricPlaceholder: 'e.g. Aluminum Alloy, Metallic Body, Polycarbonate',
        fabricDefault: 'Aluminum Alloy & Metallic Finish',
        defaultSizes: '14 Inch, 15.6 Inch',
        sizesHint: 'Laptop Screen Sizes',
        chips: ['13.3 Inch', '14 Inch', '15 Inch', '15.6 Inch', '16 Inch', '17.3 Inch'],
        colorsDefault: 'Space Grey, Silver, Midnight Black',
        suggestedPrice: 34999,
        suggestedMrp: 49999,
        sampleTitle: 'HP 15s Intel Core i5 12th Gen Thin & Light FHD Laptop (16GB RAM / 512GB SSD)',
        sampleDesc: 'High-performance ultra-slim laptop powered by Intel Core i5 processor, vivid anti-glare FHD micro-edge display, fast PCIe NVMe SSD, backlit keyboard, long-lasting battery backup, and Windows 11 Home.'
      },
      'tv': {
        label: 'Bezel & Display Panel',
        fabricPlaceholder: 'e.g. Bezel-less Metal, 4K LED Display Panel',
        fabricDefault: 'Bezel-less Metal & 4K LED Panel',
        defaultSizes: '32 Inch, 43 Inch, 55 Inch',
        sizesHint: 'TV Screen Sizes',
        chips: ['24 Inch', '32 Inch', '40 Inch', '43 Inch', '50 Inch', '55 Inch', '65 Inch'],
        colorsDefault: 'Piano Black',
        suggestedPrice: 13999,
        suggestedMrp: 24999,
        sampleTitle: 'Mi 4K Ultra HD Dolby Audio Smart Google LED TV with Bezel-less Design',
        sampleDesc: 'Cinematic Smart Google TV with vivid 4K Ultra HD panel, HDR10+, 24W Dolby Audio stereo speakers, PatchWall integration, built-in Chromecast, and dual-band Wi-Fi.'
      },
      'monitor': {
        label: 'Display Panel & Stand',
        fabricPlaceholder: 'e.g. IPS Panel & Ergonomic Tilt Stand',
        fabricDefault: 'IPS Panel & Ergonomic Tilt Stand',
        defaultSizes: '22 Inch, 24 Inch, 27 Inch',
        sizesHint: 'Monitor Screen Sizes',
        chips: ['21.5 Inch', '22 Inch', '24 Inch', '27 Inch', '32 Inch', 'Curved 34 Inch'],
        colorsDefault: 'Matte Black, Gunmetal Grey',
        suggestedPrice: 8499,
        suggestedMrp: 14999,
        sampleTitle: 'LG UltraGear 24-inch 165Hz IPS Gaming Monitor with 1ms AMD FreeSync',
        sampleDesc: 'Crisp Full HD IPS computer monitor engineered with 165Hz ultra-smooth refresh rate, 1ms MBR response time, 99% sRGB color accuracy, 3-side virtually borderless design, and HDMI/DisplayPort.'
      },
      'desktop': {
        label: 'Cabinet & Display Housing',
        fabricPlaceholder: 'e.g. Brushed Aluminum & Slim Bezel Housing',
        fabricDefault: 'Brushed Aluminum & Slim Bezel Housing',
        defaultSizes: '8GB / 512GB SSD, 16GB / 1TB SSD',
        sizesHint: 'RAM & Storage Configurations',
        chips: ['8GB / 512GB SSD', '16GB / 512GB SSD', '16GB / 1TB SSD', '32GB / 1TB SSD'],
        colorsDefault: 'Arctic White, Shadow Black',
        suggestedPrice: 38999,
        suggestedMrp: 54999,
        sampleTitle: 'Lenovo IdeaCentre 24-inch Core i5 All-in-One Desktop PC with Wireless KB & Mouse',
        sampleDesc: 'Space-saving All-in-One desktop PC featuring high-performance Intel Core i5 processor, vibrant 23.8-inch FHD anti-glare display, dual Harman Kardon tuned speakers, 5MP IR webcam, and bundled wireless keyboard and mouse.'
      },
      'tablet': {
        label: 'Body & Enclosure',
        fabricPlaceholder: 'e.g. 100% Recycled Aluminum Enclosure',
        fabricDefault: '100% Recycled Aluminum Enclosure',
        defaultSizes: '64 GB, 128 GB, 256 GB',
        sizesHint: 'Storage Capacity',
        chips: ['64 GB', '128 GB', '256 GB', '512 GB', 'Wi-Fi + Cellular'],
        colorsDefault: 'Silver, Sky Blue, Rose Pink, Space Grey',
        suggestedPrice: 26999,
        suggestedMrp: 36999,
        sampleTitle: 'Apple iPad 10th Gen 10.9-inch Liquid Retina Display Wi-Fi Tablet',
        sampleDesc: 'Versatile everyday tablet featuring stunning 10.9-inch Liquid Retina display with True Tone, powerful A14 Bionic chip, landscape 12MP Ultra-Wide camera with Center Stage, USB-C connectivity, and all-day battery.'
      },
      'storage': {
        label: 'Casing & Flash Memory',
        fabricPlaceholder: 'e.g. Metallic Casing & 3D NAND Flash',
        fabricDefault: 'Metallic Casing & 3D NAND Flash',
        defaultSizes: '64 GB, 128 GB, 256 GB',
        sizesHint: 'Storage Capacity',
        chips: ['32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB', '2 TB'],
        colorsDefault: 'Silver, Titanium Grey, Midnight Black',
        suggestedPrice: 699,
        suggestedMrp: 1499,
        sampleTitle: 'SanDisk Ultra Dual Drive Go 128GB USB Type-C & Type-A High-Speed Flash Drive',
        sampleDesc: 'Reversible 2-in-1 flash drive with USB Type-C and traditional Type-A connectors. Effortlessly move files between USB Type-C smartphones, tablets, Macs, and Type-A computers with up to 150MB/s read speeds.'
      },
      'printer': {
        label: 'Housing & Print Head',
        fabricPlaceholder: 'e.g. Durable ABS Plastic & Precision Printhead',
        fabricDefault: 'Durable ABS Plastic & Precision Printhead',
        defaultSizes: 'Ink Tank All-in-One, Laser Monochrome',
        sizesHint: 'Printer Types & Functions',
        chips: ['Ink Tank All-in-One', 'Color Inkjet', 'Laser Monochrome', 'Duplex WiFi'],
        colorsDefault: 'Matte Black, Crisp White',
        suggestedPrice: 10499,
        suggestedMrp: 15999,
        sampleTitle: 'Canon PIXMA MegaTank G3010 All-in-One WiFi Color Ink Tank Printer',
        sampleDesc: 'High-volume color ink tank printer engineered with integrated ink tanks, wireless WiFi direct mobile printing, flatbed scanner, borderless photo printing, and low-cost page printing yield.'
      },

      // 2. Audio & Sound
      'headphone': {
        label: 'Earcup & Build Material',
        fabricPlaceholder: 'e.g. Cushioned Leatherette, Matte ABS',
        fabricDefault: 'Cushioned Leatherette & Matte ABS',
        defaultSizes: 'Standard, Over-Ear Fit',
        sizesHint: 'Headphone Fit & Type',
        chips: ['Standard', 'Over-Ear Fit', 'On-Ear Fit', 'Foldable Fit', 'Studio Monitor'],
        colorsDefault: 'Midnight Black, Army Green, Silver',
        suggestedPrice: 1999,
        suggestedMrp: 4499,
        sampleTitle: 'Sony WH-CH720N Wireless Over-Ear Active Noise Cancelling Headphones',
        sampleDesc: 'Lightweight wireless noise-cancelling headphones equipped with Integrated Processor V1, up to 35 hours battery life, multipoint Bluetooth connection, crystal-clear hands-free calls, and deep punchy bass.'
      },
      'earbuds': {
        label: 'Earbud & Case Housing',
        fabricPlaceholder: 'e.g. IPX5 Water-Resistant Matte Polycarbonate',
        fabricDefault: 'IPX5 Water-Resistant Matte Polycarbonate',
        defaultSizes: 'Standard Fit, Pro ANC Edition',
        sizesHint: 'Earbud Edition / Fit',
        chips: ['Standard Fit', 'Pro ANC Edition', 'Quad Mic Edition', 'Gaming Low Latency'],
        colorsDefault: 'Carbon Black, Bold Blue, Pure White',
        suggestedPrice: 999,
        suggestedMrp: 2990,
        sampleTitle: 'boAt Airdopes 141 True Wireless Bluetooth Earbuds with 42H Playtime & ENx Mic',
        sampleDesc: 'Ergonomic TWS Bluetooth earbuds delivering signature stereo sound, quad microphones with ENx noise cancellation for clear voice calls, Beast mode low-latency for gaming, and ASAP fast charge.'
      },
      'neckband': {
        label: 'Neckband Band & Cable',
        fabricPlaceholder: 'e.g. Skin-Friendly Silicone & Magnetic Earbuds',
        fabricDefault: 'Skin-Friendly Silicone & Magnetic Earbuds',
        defaultSizes: 'Standard Fit, Bass Edition',
        sizesHint: 'Model Variant',
        chips: ['Standard Fit', 'Bass Edition', 'ANC Edition', 'Long Playtime 40H'],
        colorsDefault: 'Acoustic Red, Magico Black, Beam Blue',
        suggestedPrice: 1299,
        suggestedMrp: 2299,
        sampleTitle: 'OnePlus Bullets Wireless Z2 Bluetooth Magnetic Neckband with Fast Charge (30H)',
        sampleDesc: 'Comfortable wireless magnetic neckband with 12.4mm dynamic bass drivers, 10-minute ultra-fast charge for 20 hours playback, anti-sweat IP55 water resistance, and magnetic instant connect/pause.'
      },
      'speaker': {
        label: 'Enclosure & Grille',
        fabricPlaceholder: 'e.g. Rugged Waterproof Fabric & Tough Rubber Housing',
        fabricDefault: 'Rugged Waterproof Fabric & Tough Rubber Housing',
        defaultSizes: '10W Portable, 20W Boom, 40W Party',
        sizesHint: 'Audio Output Power',
        chips: ['5W Mini', '10W Portable', '16W Dual', '20W Boom', '40W Party', '60W Bass'],
        colorsDefault: 'Squad Camo, Ocean Blue, Black, Red, Grey',
        suggestedPrice: 1499,
        suggestedMrp: 3499,
        sampleTitle: 'JBL Flip 6 Portable Waterproof Bluetooth Speaker with Bold Bass & 12H Battery',
        sampleDesc: 'Rugged outdoor waterproof Bluetooth speaker engineered with 2-way speaker system, racetrack woofer, separate tweeter, dual passive radiators, IP67 dust/waterproof rating, and PartyBoost pairing.'
      },
      'soundbar': {
        label: 'Soundbar Cabinet & Grille',
        fabricPlaceholder: 'e.g. Polished Metal Grille & Wooden Subwoofer Box',
        fabricDefault: 'Polished Metal Grille & Wooden Subwoofer Box',
        defaultSizes: '60W (2.1 Ch), 120W (2.1 Ch), 240W (5.1 Ch)',
        sizesHint: 'Audio Output & Channels',
        chips: ['40W 2.0', '60W 2.1', '100W 2.1', '120W 2.1', '240W 5.1', '525W Dolby'],
        colorsDefault: 'Glossy Black, Titanium Grey',
        suggestedPrice: 4999,
        suggestedMrp: 11999,
        sampleTitle: 'Zebronics Juke Bar 9500 5.1 Dolby Atmos Soundbar with Wireless Subwoofer (240W)',
        sampleDesc: 'Cinematic 5.1 channel surround home theater soundbar system featuring dedicated wireless subwoofer, dual rear satellite surround speakers, HDMI ARC, Optical input, Bluetooth 5.0, and LED display.'
      },
      'mic': {
        label: 'Microphone Capsule & Body',
        fabricPlaceholder: 'e.g. Metal Body & Shock Mount with Pop Filter',
        fabricDefault: 'Metal Body & Shock Mount with Pop Filter',
        defaultSizes: 'Standard USB, With Boom Arm Stand',
        sizesHint: 'Mic Setup / Stand Type',
        chips: ['Standard USB', 'Desktop Tripod', 'With Boom Arm Stand', 'RGB Streaming'],
        colorsDefault: 'Midnight Black, Glacier White, Rose Pink',
        suggestedPrice: 2199,
        suggestedMrp: 4999,
        sampleTitle: 'Fifine AmpliGame USB Condenser Gaming & Podcast Microphone with RGB & Pop Filter',
        sampleDesc: 'Professional studio cardioid condenser microphone with gradient RGB lighting, tap-to-mute sensor, zero-latency headphone monitoring jack, integrated shock mount, and plug-and-play USB connection.'
      },

      // 3. Mobiles & Wearables
      'smartphone': {
        label: 'Body / Frame Material',
        fabricPlaceholder: 'e.g. Gorilla Glass, Aluminum Frame',
        fabricDefault: 'Gorilla Glass & Aluminum Frame',
        defaultSizes: '128 GB, 256 GB',
        sizesHint: 'Storage Capacity',
        chips: ['64 GB', '128 GB', '256 GB', '512 GB', '1 TB'],
        colorsDefault: 'Phantom Black, Titanium Silver, Blue',
        suggestedPrice: 17499,
        suggestedMrp: 22999,
        sampleTitle: 'OnePlus Nord CE 3 Lite 5G Smartphone (108MP Camera, 67W SUPERVOOC, 120Hz)',
        sampleDesc: 'Super-fast 5G smartphone equipped with 108MP high-resolution camera, 67W SUPERVOOC rapid fast charging, 5000mAh battery, 120Hz smooth FHD+ display, dual stereo speakers, and Snapdragon processor.'
      },
      'smartwatch': {
        label: 'Dial Case & Strap Material',
        fabricPlaceholder: 'e.g. Metallic Zinc Alloy, Silicone Strap',
        fabricDefault: 'Metallic Zinc Alloy & Silicone Strap',
        defaultSizes: '40mm, 44mm',
        sizesHint: 'Dial Case Sizes',
        chips: ['38mm', '40mm', '42mm', '44mm', '46mm', '49mm Ultra'],
        colorsDefault: 'Jet Black, Rose Gold, Deep Blue',
        suggestedPrice: 1499,
        suggestedMrp: 4999,
        sampleTitle: 'Fire-Boltt Phoenix Pro 1.39" Bluetooth Calling Smartwatch with AI Voice & SpO2',
        sampleDesc: 'Sleek luxury metal smartwatch with Bluetooth calling, 1.39-inch HD display, 120+ active sports modes, continuous heart rate and blood oxygen monitoring, smartphone notifications, and IP67 water resistance.'
      },
      'powerbank': {
        label: 'Battery Cells & Housing',
        fabricPlaceholder: 'e.g. High-Density Li-Po & Anodized Metal Shell',
        fabricDefault: 'High-Density Li-Po & Anodized Metal Shell',
        defaultSizes: '10000 mAh, 20000 mAh',
        sizesHint: 'Battery Capacity',
        chips: ['5000 mAh', '10000 mAh', '20000 mAh', '30000 mAh', '65W Laptop PD'],
        colorsDefault: 'Carbon Black, Navy Blue, Metallic Grey',
        suggestedPrice: 1199,
        suggestedMrp: 2199,
        sampleTitle: 'Mi 3i 20000mAh 18W Fast Charging Power Bank with Triple Output Ports',
        sampleDesc: 'High-capacity lithium-polymer portable power bank featuring 18W fast charge, dual input (Type-C & Micro-USB), triple output ports, 12-layer advanced circuit protection, and smart low power charging mode.'
      },
      'charger': {
        label: 'Semiconductor & Casing',
        fabricPlaceholder: 'e.g. Gallium Nitride (GaN) & Flame Retardant PC',
        fabricDefault: 'Gallium Nitride (GaN) & Flame Retardant PC',
        defaultSizes: '20W PD, 33W GaN, 65W GaN',
        sizesHint: 'Power Output Wattage',
        chips: ['20W PD', '33W GaN', '45W GaN', '65W GaN', '100W GaN', '120W Ultra'],
        colorsDefault: 'Arctic White, Sleek Black',
        suggestedPrice: 799,
        suggestedMrp: 1999,
        sampleTitle: 'Stuffcool 65W Dual Port GaN Fast Wall Charger Adapter (Type-C PD + USB-A)',
        sampleDesc: 'Ultra-compact next-gen GaN fast wall charger supporting Power Delivery (PD 3.0) and Quick Charge 3.0. Fast charges laptops, MacBooks, tablets, and flagship smartphones at maximum speed.'
      },
      'cable': {
        label: 'Braiding & Core Wire',
        fabricPlaceholder: 'e.g. Military-Grade Nylon Braided & 100% Copper Core',
        fabricDefault: 'Military-Grade Nylon Braided & 100% Copper Core',
        defaultSizes: '1 Meter, 1.5 Meter, 2 Meter',
        sizesHint: 'Cable Length',
        chips: ['0.5 Meter', '1 Meter', '1.2 Meter', '1.5 Meter', '2 Meter', '3 Meter'],
        colorsDefault: 'Midnight Black, Crimson Red, Metallic Silver',
        suggestedPrice: 199,
        suggestedMrp: 699,
        sampleTitle: 'Ambrane 100W 6A Fast Charging Type-C to Type-C Braided Cable (1.5m)',
        sampleDesc: 'Heavy-duty braided Type-C fast charging cable capable of delivering up to 100W PD power, 480Mbps high-speed data sync, reinforced strain-relief joints, and 15,000+ bend lifespan.'
      },

      // 4. Cameras & Smart Home
      'camera': {
        label: 'Camera Housing & Lens',
        fabricPlaceholder: 'e.g. Tough Polycarbonate & 6-Glass Aspherical Lens',
        fabricDefault: 'Tough Polycarbonate & 6-Glass Aspherical Lens',
        defaultSizes: '1080p FHD, 4K Ultra HD',
        sizesHint: 'Video Recording Resolution',
        chips: ['720p HD', '1080p FHD', '2.7K HD', '4K Ultra HD', '4K 60FPS Pro'],
        colorsDefault: 'Jet Black, Cool White',
        suggestedPrice: 3499,
        suggestedMrp: 7999,
        sampleTitle: 'SJCAM C300 4K 60FPS Action Camera with 30M Waterproof Case & Dual Screens',
        sampleDesc: 'Pocket action camera and bike dashcam with 4K 60FPS video recording, 6-axis gyro stabilization, dual touch screens, 30-meter waterproof enclosure, handheld remote, and WiFi app control.'
      },
      'cctv': {
        label: 'Body & Sensor Housing',
        fabricPlaceholder: 'e.g. Weather-Proof Polycarbonate & Optical Glass',
        fabricDefault: 'Weather-Proof Polycarbonate & Optical Glass',
        defaultSizes: '1080p Full HD, 2K QHD (3MP)',
        sizesHint: 'Camera Resolution',
        chips: ['1080p Full HD', '2K QHD (3MP)', '4MP Ultra HD', 'Solar Battery Edition'],
        colorsDefault: 'Pure White & Black Accent',
        suggestedPrice: 1899,
        suggestedMrp: 3299,
        sampleTitle: 'TP-Link Tapo C200 360° Pan/Tilt WiFi Home Security Camera with Night Vision',
        sampleDesc: 'Smart home indoor WiFi security camera with 360° horizontal and 114° vertical coverage, advanced infrared night vision up to 30 feet, sound and light alarm, two-way audio talk, and microSD storage up to 512GB.'
      },
      'projector': {
        label: 'Optical Engine & Housing',
        fabricPlaceholder: 'e.g. Sealed Optical Engine & Matte ABS Enclosure',
        fabricDefault: 'Sealed Optical Engine & Matte ABS Enclosure',
        defaultSizes: '720p HD, 1080p Native FHD, 4K Support',
        sizesHint: 'Projection Resolution',
        chips: ['480p Portable', '720p HD', '1080p Native FHD', '4K HDR Smart Edition'],
        colorsDefault: 'Moonlight White, Space Black',
        suggestedPrice: 7499,
        suggestedMrp: 16990,
        sampleTitle: 'Egate O9 Pro Full HD 1080p Smart LED Android Home Projector (6000 Lumens)',
        sampleDesc: 'Cinematic LED home theater projector with native 1080p Full HD resolution, 6000 lumens high brightness, Android smart OS, pre-installed Netflix and Prime Video, electronic keystone correction, and up to 200-inch screen display.'
      },
      'router': {
        label: 'Antennas & Casing',
        fabricPlaceholder: 'e.g. High-Gain 4-Antenna Array & Ventilated Plastic Shell',
        fabricDefault: 'High-Gain 4-Antenna Array & Ventilated Plastic Shell',
        defaultSizes: 'AC1200 Dual-Band, AX1500 WiFi 6, AX1800 Gigabit',
        sizesHint: 'Speed & Standard',
        chips: ['N300 Single', 'AC1200 Dual-Band', 'AX1500 WiFi 6', 'AX1800 Gigabit', 'AX3000 Mesh'],
        colorsDefault: 'Obsidian Black, Modern White',
        suggestedPrice: 2299,
        suggestedMrp: 4499,
        sampleTitle: 'TP-Link Archer AX12 WiFi 6 Next-Gen Gigabit Dual-Band Router (1.5 Gbps)',
        sampleDesc: 'Next-generation WiFi 6 gigabit router delivering lightning speeds up to 1.5 Gbps, revolutionary OFDMA and MU-MIMO technology for 30+ devices, 4 high-gain antennas with beamforming, and WPA3 security.'
      },
      'smart_light': {
        label: 'Diffuser & Base',
        fabricPlaceholder: 'e.g. Polycarbonate Diffuser & Aluminium Heat Sink Base',
        fabricDefault: 'Polycarbonate Diffuser & Aluminium Heat Sink Base',
        defaultSizes: '9W (810 Lm), 12W (1050 Lm), 16W (Smart Plug Combo)',
        sizesHint: 'Bulb Wattage / Lumens',
        chips: ['7W (B22)', '9W (B22)', '12W (B22)', '16A Smart Plug', '2-Pack Combo'],
        colorsDefault: '16 Million Colors RGB, Warm White, Cool Day White',
        suggestedPrice: 499,
        suggestedMrp: 1290,
        sampleTitle: 'Wipro Next 12W B22 Smart WiFi Color Changing LED Bulb (Works with Alexa/Google)',
        sampleDesc: 'Smart WiFi LED bulb featuring 16 million customizable RGB colors, tunable warm-to-cool white tones, timer scheduling, group control, and hands-free voice control via Amazon Alexa and Google Assistant.'
      },

      // 5. Gaming & Gadgets
      'gaming': {
        label: 'Grip & Thumbsticks',
        fabricPlaceholder: 'e.g. Textured Grips & Hall-Effect Magnetic Joysticks',
        fabricDefault: 'Textured Grips & Hall-Effect Magnetic Joysticks',
        defaultSizes: 'Standard Wireless, Pro Hall-Effect Edition',
        sizesHint: 'Controller Edition',
        chips: ['Wired USB', 'Standard Wireless', 'Pro Hall-Effect Edition', 'Dual Vibration RGB'],
        colorsDefault: 'Stealth Black, Camo Blue, Neon Red',
        suggestedPrice: 1499,
        suggestedMrp: 2999,
        sampleTitle: 'EvoFox Elite X Wireless Gamepad Controller for PC, Android & PS3',
        sampleDesc: 'Precision wireless gaming controller with dual rumble feedback vibration motors, low-latency 2.4GHz wireless & Bluetooth connectivity, anti-drift magnetic joysticks, ergonomic textured grips, and long-lasting rechargeable battery.'
      },
      'keyboard_mouse': {
        label: 'Switch & Keycap Build',
        fabricPlaceholder: 'e.g. Outemu Blue Mechanical Switches & Double-Shot ABS',
        fabricDefault: 'Outemu Blue Mechanical Switches & Double-Shot ABS',
        defaultSizes: 'Wireless Slim Combo, Mechanical RGB Combo',
        sizesHint: 'Combo Hardware Type',
        chips: ['Wireless Slim Combo', 'Silent Office Combo', 'Tenkeyless (TKL) RGB', 'Full-Size Mechanical RGB'],
        colorsDefault: 'Stealth Black, Retro White, Cyber Pink',
        suggestedPrice: 1399,
        suggestedMrp: 3299,
        sampleTitle: 'Ant Esports MK1000 Backlit Mechanical Gaming Keyboard & Mouse Combo',
        sampleDesc: 'Full-size mechanical gaming keyboard with responsive tactile click switches, dynamic multi-color LED backlighting, 100% anti-ghosting keys, and high-precision 3200 DPI ergonomic optical gaming mouse.'
      },
      'trimmer': {
        label: 'Blades & Housing',
        fabricPlaceholder: 'e.g. Self-Sharpening Stainless Steel Blades & Non-Slip Grip',
        fabricDefault: 'Self-Sharpening Stainless Steel Blades & Non-Slip Grip',
        defaultSizes: '60 Min Runtime, 120 Min Fast-Charge Pro',
        sizesHint: 'Battery Runtime / Features',
        chips: ['45 Min Standard', '60 Min Runtime', '90 Min Titanium', '120 Min Fast-Charge Pro', 'All-in-1 9-Kit'],
        colorsDefault: 'Navy Blue, Matte Black, Champagne Gold',
        suggestedPrice: 899,
        suggestedMrp: 1995,
        sampleTitle: 'Philips Series 3000 Cordless Beard & Body Groomer Trimmer with Lift & Trim System',
        sampleDesc: 'Cordless men\'s beard trimmer with 20 lock-in precision length settings (0.5mm to 10mm), skin-friendly rounded blade tips, 60 minutes cordless runtime, USB fast charging with battery level indicator, and washable heads.'
      },
      'ring_light': {
        label: 'Ring Shell & Tripod',
        fabricPlaceholder: 'e.g. Heavy-Duty Aluminium Alloy Tripod & Frosted ABS Ring',
        fabricDefault: 'Heavy-Duty Aluminium Alloy Tripod & Frosted ABS Ring',
        defaultSizes: '10 Inch (Desktop), 12 Inch (7ft Stand), 18 Inch (Studio Pro)',
        sizesHint: 'Ring Diameter & Stand',
        chips: ['10 Inch (Desktop)', '12 Inch (7ft Stand)', '14 Inch (7ft Stand)', '18 Inch (Studio Pro)'],
        colorsDefault: '3 Light Modes (Warm, Natural, Cool White)',
        suggestedPrice: 799,
        suggestedMrp: 1999,
        sampleTitle: 'DIGITEK 12-inch LED Ring Light with 7ft Extendable Metal Tripod Stand & Phone Holder',
        sampleDesc: 'Professional LED ring light for video recording, YouTube tutorials, vlogging, and live makeup streaming. Offers 3 lighting modes (Warm, Day, Cool White), 10 dimmable brightness levels, 360° ball head, and 7-foot heavy tripod.'
      },

      // Generic Electronics fallback
      'electronics': {
        label: 'Material / Build Quality',
        fabricPlaceholder: 'e.g. ABS Plastic, Silicone, Aluminum Alloy',
        fabricDefault: 'ABS Plastic & Silicone',
        defaultSizes: 'Standard Fit, Universal',
        sizesHint: 'Electronics / Device Fit',
        chips: ['Standard Fit', 'Universal Fit', 'Free Size', 'One Size', 'Adjustable'],
        colorsDefault: 'Black, White, Blue, Grey',
        suggestedPrice: 499,
        suggestedMrp: 1299,
        sampleTitle: 'Premium Multi-Utility Electronic Device with High Durability',
        sampleDesc: 'Engineered with premium durable materials, modern lightweight design, fast responsiveness, long battery efficiency, and universal compatibility.'
      },

      // Other store categories
      'beauty-health': {
        label: 'Formulation / Skin Type',
        fabricPlaceholder: 'e.g. Liquid Matte, Herbal Cream, Gel',
        fabricDefault: 'Herbal Liquid Formula',
        defaultSizes: 'Standard, Free Size',
        sizesHint: 'Volume / Pack Units',
        chips: ['Standard', 'Free Size', '30ml', '50ml', '100ml', 'Pack of 2', 'Pack of 4'],
        colorsDefault: 'Nude Pink, Deep Red, Coral',
        suggestedPrice: 249,
        suggestedMrp: 599,
        sampleTitle: 'Herbal Organic Glow Face Serum & Moisturizer Pack',
        sampleDesc: 'Natural dermatologically tested organic formula enriched with Vitamin C and natural botanical extracts for radiant, hydrated, and youthful skin.'
      },
      'home-kitchen': {
        label: 'Material / Specifications',
        fabricPlaceholder: 'e.g. SS304 Stainless Steel, Ceramic, Cotton',
        fabricDefault: 'Stainless Steel',
        defaultSizes: 'Standard, Free Size',
        sizesHint: 'Dimensions / Set Count',
        chips: ['Standard', 'Free Size', 'Single', 'Double', 'King Size', 'Set of 1', 'Set of 2', 'Set of 3'],
        colorsDefault: 'Silver, Multi-color, White',
        suggestedPrice: 399,
        suggestedMrp: 999,
        sampleTitle: 'Stainless Steel Air-Tight Food Storage Container Set',
        sampleDesc: 'Heavy-duty food-grade SS304 stainless steel kitchen containers with air-tight leak-proof silicone seal lids, rust-proof finish, and stackable modular design.'
      },
      'bags-footwear': {
        label: 'Material / Upper',
        fabricPlaceholder: 'e.g. PU Leather, Canvas, Mesh, Synthetic',
        fabricDefault: 'Textured PU Leather',
        defaultSizes: 'Free Size, Standard',
        sizesHint: 'Shoe & Bag Sizes',
        chips: ['Free Size', 'Standard', 'IND-5', 'IND-6', 'IND-7', 'IND-8', 'IND-9', 'IND-10'],
        colorsDefault: 'Black, Brown, Tan, Navy Blue',
        suggestedPrice: 449,
        suggestedMrp: 1199,
        sampleTitle: 'Stylish Waterproof Casual Backpack / Sneaker Shoes',
        sampleDesc: 'Crafted with premium water-resistant textured fabric, padded shoulder straps, ergonomic cushioned insole, and durable non-slip grip outsole.'
      },
      'kids': {
        label: 'Fabric / Material',
        fabricPlaceholder: 'e.g. 100% Organic Cotton, Breathable Net',
        fabricDefault: '100% Organic Soft Cotton',
        defaultSizes: '0-6M, 6-12M, 1-2Y, 2-3Y, Free Size',
        sizesHint: 'Kids Age Groups',
        chips: ['0-6 Months', '6-12 Months', '1-2 Years', '2-3 Years', '3-4 Years', '5-6 Years', 'Free Size'],
        colorsDefault: 'Yellow & Navy, Sky Blue, Pink',
        suggestedPrice: 299,
        suggestedMrp: 699,
        sampleTitle: 'Cute 100% Organic Soft Cotton Kids Clothing Set',
        sampleDesc: 'Ultra-soft breathable 100% pure organic cotton fabric, gentle on delicate skin, vibrant non-toxic prints, and stretchable comfortable fit.'
      },
      'apparel': {
        label: 'Fabric / Material',
        fabricPlaceholder: 'e.g. Pure Cotton, Jacquard Silk, Georgette',
        fabricDefault: 'Pure Cotton',
        defaultSizes: 'S, M, L, XL, XXL',
        sizesHint: 'Clothing Sizes',
        chips: ['Free Size', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', 'Unstitched'],
        colorsDefault: 'Red, Navy Blue, Green, Black',
        suggestedPrice: 399,
        suggestedMrp: 1299,
        sampleTitle: 'Elegant Floral Printed Cotton Kurti / Saree with Border',
        sampleDesc: 'Premium quality apparel featuring vibrant ethnic prints, soft breathable fabric texture, comfortable regular fit, and long-lasting color durability.'
      }
    };

    var currentCategoryKey = 'apparel';
    var lastPresetKey = null;
    window.currentDeviceTab = 'all';
    window.currentDeviceSearch = '';

    // Device Preset Filtering by Tabs
    function filterDeviceTab(group, btn) {
      window.currentDeviceTab = group || 'all';
      var tabBtns = document.querySelectorAll('.device-tab-btn');
      tabBtns.forEach(function(b) {
        b.style.background = '#f1f5f9';
        b.style.color = '#475569';
        b.style.borderColor = '#cbd5e1';
      });
      if (btn) {
        btn.style.background = '#0284c7';
        btn.style.color = '#fff';
        btn.style.borderColor = '#0284c7';
      }
      applyDeviceFilter();
    }

    // Device Preset Live Search
    function filterDevicePresets(query) {
      window.currentDeviceSearch = (query || '').toLowerCase().trim();
      applyDeviceFilter();
    }

    function applyDeviceFilter() {
      var tab = window.currentDeviceTab || 'all';
      var query = window.currentDeviceSearch || '';
      var pills = document.querySelectorAll('.device-pill-btn');
      var visibleCount = 0;

      pills.forEach(function(pill) {
        var pGroup = pill.getAttribute('data-group') || '';
        var pKeywords = (pill.getAttribute('data-keywords') || '').toLowerCase();
        var pText = pill.innerText.toLowerCase();

        var matchesTab = (tab === 'all' || pGroup === tab);
        var matchesQuery = (!query || pKeywords.indexOf(query) !== -1 || pText.indexOf(query) !== -1);

        if (matchesTab && matchesQuery) {
          pill.style.display = 'inline-flex';
          visibleCount++;
        } else {
          pill.style.display = 'none';
        }
      });

      var noMatch = document.getElementById('device_no_match');
      if (noMatch) {
        noMatch.style.display = visibleCount === 0 ? 'block' : 'none';
      }
    }

    function getCategoryKey(slug, name, title) {
      slug = (slug || '').toLowerCase();
      name = (name || '').toLowerCase();
      title = (title || '').toLowerCase();

      // Check title for specific electronic devices
      if (title.indexOf('laptop') !== -1 || title.indexOf('notebook') !== -1 || title.indexOf('macbook') !== -1 || title.indexOf('chromebook') !== -1) return 'laptop';
      if (title.indexOf('tv') !== -1 || title.indexOf('television') !== -1) return 'tv';
      if (title.indexOf('monitor') !== -1) return 'monitor';
      if (title.indexOf('desktop') !== -1 || title.indexOf('all-in-one') !== -1 || title.indexOf(' aio ') !== -1) return 'desktop';
      if (title.indexOf('tablet') !== -1 || title.indexOf('ipad') !== -1) return 'tablet';
      if (title.indexOf('pendrive') !== -1 || title.indexOf('pen drive') !== -1 || title.indexOf('ssd') !== -1 || title.indexOf('flash drive') !== -1 || title.indexOf('storage') !== -1) return 'storage';
      if (title.indexOf('printer') !== -1) return 'printer';
      if (title.indexOf('headphone') !== -1 || title.indexOf('headset') !== -1) return 'headphone';
      if (title.indexOf('earbud') !== -1 || title.indexOf('tws') !== -1 || title.indexOf('airpod') !== -1 || title.indexOf('buds') !== -1) return 'earbuds';
      if (title.indexOf('neckband') !== -1) return 'neckband';
      if (title.indexOf('soundbar') !== -1 || title.indexOf('subwoofer') !== -1 || title.indexOf('home theater') !== -1) return 'soundbar';
      if (title.indexOf('speaker') !== -1) return 'speaker';
      if (title.indexOf('mic') !== -1 || title.indexOf('microphone') !== -1) return 'mic';
      if (title.indexOf('smartphone') !== -1 || title.indexOf('mobile') !== -1 || title.indexOf('phone') !== -1) return 'smartphone';
      if (title.indexOf('smartwatch') !== -1 || title.indexOf('watch') !== -1 || title.indexOf('fitness band') !== -1) return 'smartwatch';
      if (title.indexOf('powerbank') !== -1 || title.indexOf('power bank') !== -1) return 'powerbank';
      if (title.indexOf('charger') !== -1 || title.indexOf('adapter') !== -1) return 'charger';
      if (title.indexOf('cable') !== -1 || title.indexOf('cord') !== -1) return 'cable';
      if (title.indexOf('action camera') !== -1 || title.indexOf('dashcam') !== -1 || title.indexOf('gopro') !== -1) return 'camera';
      if (title.indexOf('cctv') !== -1 || title.indexOf('security camera') !== -1) return 'cctv';
      if (title.indexOf('projector') !== -1) return 'projector';
      if (title.indexOf('router') !== -1 || title.indexOf('modem') !== -1) return 'router';
      if (title.indexOf('bulb') !== -1 || title.indexOf('smart light') !== -1 || title.indexOf('plug') !== -1) return 'smart_light';
      if (title.indexOf('gamepad') !== -1 || title.indexOf('controller') !== -1 || title.indexOf('gaming') !== -1) return 'gaming';
      if (title.indexOf('keyboard') !== -1 || title.indexOf('mouse') !== -1) return 'keyboard_mouse';
      if (title.indexOf('trimmer') !== -1 || title.indexOf('shaver') !== -1 || title.indexOf('groomer') !== -1) return 'trimmer';
      if (title.indexOf('ring light') !== -1 || title.indexOf('ringlight') !== -1 || title.indexOf('tripod') !== -1) return 'ring_light';

      if (slug.indexOf('jewel') !== -1 || name.indexOf('jewel') !== -1) return 'jewellery-accessories';
      if (slug.indexOf('electron') !== -1 || name.indexOf('electron') !== -1) return 'electronics';
      if (slug.indexOf('beauty') !== -1 || name.indexOf('beauty') !== -1) return 'beauty-health';
      if (slug.indexOf('home') !== -1 || slug.indexOf('kitchen') !== -1 || name.indexOf('kitchen') !== -1) return 'home-kitchen';
      if (slug.indexOf('bag') !== -1 || slug.indexOf('foot') !== -1 || name.indexOf('footwear') !== -1) return 'bags-footwear';
      if (slug.indexOf('kid') !== -1 || name.indexOf('kid') !== -1) return 'kids';
      return 'apparel';
    }

    function onTitleOrCategoryChange() {
      var select = document.getElementById('category_select');
      var titleInput = document.getElementById('product_title_input');
      if (!select) return;

      var selectedOpt = select.options[select.selectedIndex];
      var slug = selectedOpt ? selectedOpt.getAttribute('data-slug') : '';
      var name = selectedOpt ? selectedOpt.getAttribute('data-name') : '';
      var titleText = titleInput ? titleInput.value : '';

      // Show/Hide Quick Electronic Device Presets
      var devSelector = document.getElementById('electronics_device_selector');
      var isElec = (slug.indexOf('electron') !== -1 || name.indexOf('electron') !== -1);
      if (devSelector) {
        devSelector.style.display = isElec ? 'block' : 'none';
      }

      currentCategoryKey = getCategoryKey(slug, name, titleText);

      // Only update defaults if product archetype or category has changed
      if (currentCategoryKey !== lastPresetKey) {
        lastPresetKey = currentCategoryKey;
        var preset = categoryPresets[currentCategoryKey] || categoryPresets['apparel'];

        // Update Material Label and Input
        var fabricLabel = document.getElementById('fabric_label');
        var fabricInput = document.getElementById('fabric_input');
        if (fabricLabel) fabricLabel.innerText = preset.label;
        if (fabricInput) {
          fabricInput.placeholder = preset.fabricPlaceholder;
          fabricInput.value = preset.fabricDefault;
        }

        // Update Size Hint & Default Value
        var sizeHint = document.getElementById('category_size_hint');
        var sizesInput = document.getElementById('sizes_input');
        if (sizeHint) sizeHint.innerText = preset.sizesHint;
        if (sizesInput) {
          sizesInput.value = preset.defaultSizes;
        }

        // Update Colors Default
        var colorsInput = document.getElementById('colors_input');
        if (colorsInput) {
          colorsInput.value = preset.colorsDefault;
        }

        // If high-value electronic device, suggest realistic pricing if current price is low default
        if (preset.suggestedPrice) {
          var priceInp = document.getElementById('base_price_input');
          var mrpInp = document.getElementById('base_mrp_input');
          if (priceInp && (priceInp.value == '399' || priceInp.value == '400' || priceInp.value == '450')) {
            priceInp.value = preset.suggestedPrice;
          }
          if (mrpInp && (mrpInp.value == '1299' || mrpInp.value == '999')) {
            mrpInp.value = preset.suggestedMrp;
          }
        }

        renderSizeChips();
        updateSizePricingRows();
      }
    }

    function applyDevicePreset(type, btn) {
      // Highlight the clicked device pill button
      var pills = document.querySelectorAll('.device-pill-btn');
      pills.forEach(function(p) {
        p.style.background = '#fff';
        p.style.color = '#0369a1';
        p.style.borderColor = '#bae6fd';
        p.style.boxShadow = 'none';
        var badge = p.querySelector('.pill-badge');
        if (badge) {
          badge.style.background = '#e0f2fe';
          badge.style.color = '#0284c7';
        }
      });

      if (btn) {
        btn.style.background = '#0284c7';
        btn.style.color = '#fff';
        btn.style.borderColor = '#0284c7';
        btn.style.boxShadow = '0 2px 6px rgba(2, 132, 199, 0.35)';
        var activeBadge = btn.querySelector('.pill-badge');
        if (activeBadge) {
          activeBadge.style.background = 'rgba(255, 255, 255, 0.25)';
          activeBadge.style.color = '#ffffff';
        }
      }

      var titleInp = document.getElementById('product_title_input');
      var priceInp = document.getElementById('base_price_input');
      var mrpInp = document.getElementById('base_mrp_input');
      var fabricLabel = document.getElementById('fabric_label');
      var fabricInp = document.getElementById('fabric_input');
      var sizeHint = document.getElementById('category_size_hint');
      var sizesInp = document.getElementById('sizes_input');
      var colorsInp = document.getElementById('colors_input');
      var descInp = document.querySelector('textarea[name="description"]');

      currentCategoryKey = type;
      lastPresetKey = type;
      var preset = categoryPresets[type] || categoryPresets['electronics'];

      if (titleInp && preset.sampleTitle) {
        titleInp.value = preset.sampleTitle;
      }
      if (descInp && preset.sampleDesc) {
        descInp.value = preset.sampleDesc;
      }
      if (fabricLabel) fabricLabel.innerText = preset.label;
      if (fabricInp) {
        fabricInp.value = preset.fabricDefault;
        fabricInp.placeholder = preset.fabricPlaceholder;
      }
      if (sizeHint) sizeHint.innerText = preset.sizesHint;
      if (sizesInp) sizesInp.value = preset.defaultSizes;
      if (colorsInp) colorsInp.value = preset.colorsDefault;
      if (priceInp && preset.suggestedPrice) priceInp.value = preset.suggestedPrice;
      if (mrpInp && preset.suggestedMrp) mrpInp.value = preset.suggestedMrp;

      // Clear price cache to populate fresh tiered values for new device sizes
      sizePriceCache = {};
      renderSizeChips();
      updateSizePricingRows();
    }

    function onCategoryChange() {
      lastPresetKey = null; // force re-evaluation on category switch
      onTitleOrCategoryChange();
    }

    function renderSizeChips() {
      var container = document.getElementById('size_chips_container');
      var sizesInput = document.getElementById('sizes_input');
      if (!container || !sizesInput) return;

      var preset = categoryPresets[currentCategoryKey] || categoryPresets['apparel'];
      var currentSizes = sizesInput.value.split(',').map(function(s) { 
        return s.trim().toLowerCase().replace(/[\s-]+/g, ''); 
      }).filter(Boolean);

      container.innerHTML = '';
      if (!preset.chips || !preset.chips.length) return;

      preset.chips.forEach(function(chip) {
        var cleanChip = chip.toLowerCase().replace(/[\s-]+/g, '');
        var isSelected = currentSizes.indexOf(cleanChip) !== -1;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.innerText = (isSelected ? '✓ ' : '+ ') + chip;
        btn.style.padding = '4px 10px';
        btn.style.borderRadius = '12px';
        btn.style.fontSize = '11px';
        btn.style.fontWeight = isSelected ? '700' : '600';
        btn.style.cursor = 'pointer';
        btn.style.border = isSelected ? '1px solid #9f2089' : '1px solid #d5d8de';
        btn.style.background = isSelected ? '#9f2089' : '#f9fafb';
        btn.style.color = isSelected ? '#ffffff' : '#374151';
        btn.style.transition = 'all 0.15s ease';

        btn.onclick = function() {
          toggleSizeChip(chip);
        };

        container.appendChild(btn);
      });
    }

    function toggleSizeChip(chipName) {
      var sizesInput = document.getElementById('sizes_input');
      if (!sizesInput) return;

      var currentSizes = sizesInput.value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
      var cleanTarget = chipName.toLowerCase().replace(/[\s-]+/g, '');

      var existingIdx = -1;
      for (var i = 0; i < currentSizes.length; i++) {
        if (currentSizes[i].toLowerCase().replace(/[\s-]+/g, '') === cleanTarget) {
          existingIdx = i;
          break;
        }
      }

      if (existingIdx !== -1) {
        currentSizes.splice(existingIdx, 1);
      } else {
        currentSizes.push(chipName);
      }

      sizesInput.value = currentSizes.join(', ');
      renderSizeChips();
      updateSizePricingRows();
    }

    function onSizesInputChange() {
      renderSizeChips();
      updateSizePricingRows();
    }

    function onBasePriceChange() {
      updateSizePricingRows();
    }

    function toggleSizePricingTable() {
      var chk = document.getElementById('enable_size_pricing');
      var wrap = document.getElementById('size_pricing_table_wrap');
      if (wrap) {
        wrap.style.display = (chk && chk.checked) ? 'block' : 'none';
      }
      if (chk && chk.checked) {
        updateSizePricingRows();
      }
    }

    var sizePriceCache = {};

    function updateSizePricingRows() {
      var chk = document.getElementById('enable_size_pricing');
      var tbody = document.getElementById('size_pricing_tbody');
      var sizesInput = document.getElementById('sizes_input');
      if (!tbody || !sizesInput) return;

      if (!chk || !chk.checked) return;

      // Save currently typed values before re-rendering
      var existingInputs = tbody.querySelectorAll('input[data-sizename]');
      existingInputs.forEach(function(inp) {
        var sName = inp.getAttribute('data-sizename');
        var field = inp.getAttribute('data-field');
        if (!sizePriceCache[sName]) sizePriceCache[sName] = {};
        sizePriceCache[sName][field] = inp.value;
      });

      var rawSizes = sizesInput.value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
      tbody.innerHTML = '';

      if (rawSizes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="padding:14px; text-align:center; color:#888;">No sizes entered yet. Type sizes like "S, M, L, XL, XXL" or click tags above.</td></tr>';
        return;
      }

      var basePriceInput = document.querySelector('input[name="price"]');
      var baseMrpInput = document.querySelector('input[name="mrp"]');
      var basePrice = parseFloat(basePriceInput ? basePriceInput.value : 0) || 450;
      var baseMrp = parseFloat(baseMrpInput ? baseMrpInput.value : 0) || Math.round(basePrice * 1.5);

      rawSizes.forEach(function(s, idx) {
        var cached = sizePriceCache[s] || {};
        var priceVal = cached.price !== undefined ? cached.price : (idx === 0 ? basePrice : (basePrice + (idx * 50)));
        var mrpVal = cached.mrp !== undefined ? cached.mrp : Math.round(priceVal * 1.5);

        var pNum = parseFloat(priceVal) || 0;
        var mNum = parseFloat(mrpVal) || 0;
        var disc = (mNum > pNum && mNum > 0) ? Math.round(((mNum - pNum) / mNum) * 100) : 0;

        var safeSizeAttr = s.replace(/"/g, '&quot;');
        var tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #f9e2ee';

        tr.innerHTML = `
          <td style="padding:10px 12px; font-weight:700; color:#333; font-size:13px;">
            <span style="display:inline-block; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:4px; padding:2px 8px;">${escapeHtml(s)}</span>
          </td>
          <td style="padding:10px 12px;">
            <div style="position:relative; width:130px;">
              <span style="position:absolute; left:8px; top:8px; color:#888; font-weight:600;">₹</span>
              <input type="number" step="any" min="1" required
                     name="size_price[${safeSizeAttr}]" 
                     data-sizename="${safeSizeAttr}" data-field="price"
                     value="${priceVal}" 
                     oninput="onSizePriceInput(this, '${escapeJs(s)}')"
                     style="width:100%; padding:6px 8px 6px 20px; border:1px solid #d5d8de; border-radius:5px; font-size:13px; font-weight:700; color:#333; outline:none;">
            </div>
          </td>
          <td style="padding:10px 12px;">
            <div style="position:relative; width:130px;">
              <span style="position:absolute; left:8px; top:8px; color:#888; font-weight:600;">₹</span>
              <input type="number" step="any" min="1"
                     name="size_mrp[${safeSizeAttr}]" 
                     data-sizename="${safeSizeAttr}" data-field="mrp"
                     value="${mrpVal}" 
                     oninput="onSizePriceInput(this, '${escapeJs(s)}')"
                     style="width:100%; padding:6px 8px 6px 20px; border:1px solid #d5d8de; border-radius:5px; font-size:13px; color:#666; outline:none;">
            </div>
          </td>
          <td style="padding:10px 12px;">
            <span class="size-disc-badge" style="font-weight:700; color:#038d63; font-size:12.5px;">${disc}% off</span>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    function onSizePriceInput(el, sizeName) {
      var row = el ? el.closest('tr') : null;
      if (!row) return;

      var pInp = row.querySelector('input[data-field="price"]');
      var mInp = row.querySelector('input[data-field="mrp"]');
      var discEl = row.querySelector('.size-disc-badge');

      var pVal = parseFloat(pInp ? pInp.value : 0) || 0;
      var mVal = parseFloat(mInp ? mInp.value : 0) || 0;

      if (!sizePriceCache[sizeName]) sizePriceCache[sizeName] = {};
      if (pInp) sizePriceCache[sizeName]['price'] = pInp.value;
      if (mInp) sizePriceCache[sizeName]['mrp'] = mInp.value;

      if (discEl) {
        if (mVal > pVal && mVal > 0) {
          var d = Math.round(((mVal - pVal) / mVal) * 100);
          discEl.innerText = d + '% off';
        } else {
          discEl.innerText = '0% off';
        }
      }
    }

    function escapeHtml(str) {
      return (str + '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escapeJs(str) {
      return (str + '').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    // Attach listeners and initialize
    function initAddProductForm() {
      var sizesInput = document.getElementById('sizes_input');
      if (sizesInput) {
        sizesInput.removeEventListener('input', onSizesInputChange);
        sizesInput.addEventListener('input', onSizesInputChange);
      }
      onCategoryChange();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initAddProductForm);
    } else {
      initAddProductForm();
    }
  </script>

</body>
</html>
