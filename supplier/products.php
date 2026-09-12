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

        <!-- Quick Category Product Archetype Selector (Dynamic for ALL Categories) -->
        <div id="category_preset_selector" style="background:#fdf4ff; border:1px solid #f0abfc; border-radius:10px; padding:13px 15px; margin-bottom:16px; transition: all 0.3s ease;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
            <div id="preset_header_title" style="font-size:12.5px; font-weight:800; color:#86198f; display:flex; align-items:center; gap:6px;">
              <i class="fas fa-magic" id="preset_header_icon" style="color:#a855f7;"></i>
              <span id="preset_header_text">Quick Product Presets (Auto-fills Specs & Pricing):</span>
            </div>
            <div style="position:relative;">
              <input type="text" id="preset_search_input" placeholder="🔍 Search presets in this category..." oninput="filterActiveCategoryPresets(this.value)" style="padding:5px 12px; font-size:11.5px; border:1px solid #d8b4fe; border-radius:20px; outline:none; width:260px; background:#fff; color:#0f172a; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
            </div>
          </div>

          <!-- Sub-Group Filter Tabs (Optional per category) -->
          <div id="preset_subtabs_container" style="display:none; flex-wrap:wrap; gap:5px; margin-bottom:10px; border-bottom:1px dashed #e9d5ff; padding-bottom:8px;"></div>

          <!-- Presets Grid Container (Populated dynamically for active category) -->
          <div id="category_presets_container" style="display:flex; flex-wrap:wrap; gap:6px;"></div>

          <div id="preset_no_match" style="display:none; padding:12px; text-align:center; font-size:12px; color:#64748b;">
            <i class="fas fa-search" style="margin-right:4px;"></i> No presets found matching your search.
            <a href="javascript:void(0)" onclick="document.getElementById('preset_search_input').value=''; filterActiveCategoryPresets('');" style="color:#9333ea; margin-left:6px; font-weight:600; text-decoration:underline;">Clear Search</a>
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

    // =========================================================================
    // UNIVERSAL CATEGORY PRESETS CONFIGURATION (ALL 9 STORE CATEGORIES)
    // =========================================================================
    var categoryDefinitions = {
      'bags-footwear': {
        name: 'Bags & Footwear',
        title: 'Quick Bags & Footwear Presets (10 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-shoe-prints',
        theme: { bg: '#faf5ff', border: '#e9d5ff', titleColor: '#6b21a8', accentColor: '#9333ea', badgeBg: '#f3e8ff', badgeColor: '#7e22ce' },
        subtabs: [
          { key: 'all', label: 'All (10)' },
          { key: 'bags', label: '🎒 Bags & Totes' },
          { key: 'shoes_men', label: '👟 Men Footwear' },
          { key: 'shoes_women', label: '👡 Women Footwear' },
          { key: 'travel', label: '🧳 Luggage & Boots' }
        ],
        archetypes: ['backpack', 'handbag', 'sling_bag', 'running_shoes', 'formal_shoes', 'women_heels', 'slippers', 'luggage_trolley', 'laptop_briefcase', 'hiking_boots']
      },
      'women-ethnic': {
        name: 'Women Ethnic',
        title: 'Quick Women Ethnic Presets (8 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-female',
        theme: { bg: '#fdf2f8', border: '#fbcfe8', titleColor: '#9d174d', accentColor: '#db2777', badgeBg: '#fce7f3', badgeColor: '#be185d' },
        subtabs: [
          { key: 'all', label: 'All (8)' },
          { key: 'saree', label: '🥻 Sarees' },
          { key: 'suit', label: '👗 Kurtis & Suits' },
          { key: 'festive', label: '💃 Lehengas & Dupattas' }
        ],
        archetypes: ['kanjivaram_saree', 'georgette_saree', 'anarkali_kurti', 'cotton_kurti', 'lehenga_choli', 'sharara_suit', 'dupatta', 'palazzo_combo']
      },
      'women-western': {
        name: 'Women Western',
        title: 'Quick Women Western Presets (8 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-tshirt',
        theme: { bg: '#fff1f2', border: '#fecdd3', titleColor: '#9f1239', accentColor: '#e11d48', badgeBg: '#ffe4e6', badgeColor: '#be123c' },
        subtabs: [
          { key: 'all', label: 'All (8)' },
          { key: 'dresses', label: '👗 Dresses' },
          { key: 'tops_jeans', label: '👚 Tops & Jeans' },
          { key: 'active_outer', label: '🧥 Jackets & Tights' }
        ],
        archetypes: ['summer_dress', 'skinny_jeans', 'crop_top', 'denim_jacket', 'bodycon_dress', 'formal_shirt_women', 'yoga_leggings', 'cargo_pants']
      },
      'men': {
        name: 'Men',
        title: "Quick Men's Fashion Presets (8 Archetypes — Auto-fills Specs & Pricing):",
        icon: 'fas fa-male',
        theme: { bg: '#f0fdf4', border: '#bbf7d0', titleColor: '#166534', accentColor: '#16a34a', badgeBg: '#dcfce7', badgeColor: '#15803d' },
        subtabs: [
          { key: 'all', label: 'All (8)' },
          { key: 'topwear', label: '👕 Shirts & Tees' },
          { key: 'bottomwear', label: '👖 Jeans & Chinos' },
          { key: 'ethnic_winter', label: '🧥 Ethnic & Winter' }
        ],
        archetypes: ['cotton_tshirt', 'formal_shirt_men', 'casual_check_shirt', 'men_jeans', 'men_trousers', 'men_kurta', 'men_hoodie', 'men_boxers']
      },
      'kids': {
        name: 'Kids',
        title: 'Quick Kids & Baby Presets (6 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-child',
        theme: { bg: '#fffbeb', border: '#fde68a', titleColor: '#92400e', accentColor: '#d97706', badgeBg: '#fef3c7', badgeColor: '#b45309' },
        subtabs: [
          { key: 'all', label: 'All (6)' },
          { key: 'baby', label: '👶 Newborn & Rompers' },
          { key: 'kids_wear', label: '👕 Sets & Dresses' },
          { key: 'shoes_winter', label: '👟 Shoes & Winter' }
        ],
        archetypes: ['baby_romper', 'girls_frock', 'boys_tshirt_set', 'kids_traditional', 'kids_jacket', 'kids_shoes']
      },
      'home-kitchen': {
        name: 'Home & Kitchen',
        title: 'Quick Home & Kitchen Presets (6 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-home',
        theme: { bg: '#fff7ed', border: '#fed7aa', titleColor: '#9a3412', accentColor: '#ea580c', badgeBg: '#ffedd5', badgeColor: '#c2410c' },
        subtabs: [
          { key: 'all', label: 'All (6)' },
          { key: 'bedding_decor', label: '🛏️ Bedding & Curtains' },
          { key: 'cookware_dining', label: '🍲 Cookware & Dinnerware' },
          { key: 'utilities', label: '🧹 Bottles & Cleaning' }
        ],
        archetypes: ['bedsheet', 'cookware_kadai', 'water_bottle', 'curtains', 'spin_mop', 'dinner_set']
      },
      'beauty-health': {
        name: 'Beauty & Health',
        title: 'Quick Beauty & Health Presets (5 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-spa',
        theme: { bg: '#fdf2f8', border: '#fbcfe8', titleColor: '#831843', accentColor: '#db2777', badgeBg: '#fce7f3', badgeColor: '#be185d' },
        subtabs: [
          { key: 'all', label: 'All (5)' },
          { key: 'skincare', label: '🧴 Skincare' },
          { key: 'makeup', label: '💄 Makeup' },
          { key: 'haircare', label: '🧖‍♀️ Haircare' }
        ],
        archetypes: ['face_serum', 'matte_lipstick', 'hair_oil', 'sunscreen', 'face_wash']
      },
      'jewellery-accessories': {
        name: 'Jewellery & Accessories',
        title: 'Quick Jewellery & Accessories Presets (6 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-gem',
        theme: { bg: '#fffbeb', border: '#fef08a', titleColor: '#854d0e', accentColor: '#ca8a04', badgeBg: '#fef9c3', badgeColor: '#a16207' },
        subtabs: [
          { key: 'all', label: 'All (6)' },
          { key: 'jewellery', label: '📿 Necklaces & Rings' },
          { key: 'accessories', label: '🕶️ Eyewear & Belts' }
        ],
        archetypes: ['kundan_necklace', 'diamond_ring', 'bangles_set', 'sunglasses', 'men_wallet', 'men_belt']
      },
      'electronics': {
        name: 'Electronics',
        title: 'Quick Electronic Device Presets (27 Archetypes — Auto-fills Specs & Pricing):',
        icon: 'fas fa-microchip',
        theme: { bg: '#f0f9ff', border: '#bae6fd', titleColor: '#0369a1', accentColor: '#0284c7', badgeBg: '#e0f2fe', badgeColor: '#0284c7' },
        subtabs: [
          { key: 'all', label: 'All (27)' },
          { key: 'computing', label: '💻 Computing & Displays' },
          { key: 'audio', label: '🎧 Audio & Sound' },
          { key: 'mobile', label: '📱 Mobiles & Wearables' },
          { key: 'smart_home', label: '📷 Cameras & Smart Home' },
          { key: 'gadgets', label: '🎮 Gaming & Gadgets' }
        ],
        archetypes: ['laptop', 'tv', 'monitor', 'desktop', 'tablet', 'storage', 'printer', 'headphone', 'earbuds', 'neckband', 'speaker', 'soundbar', 'mic', 'smartphone', 'smartwatch', 'powerbank', 'charger', 'cable', 'camera', 'cctv', 'projector', 'router', 'smart_light', 'gaming', 'keyboard_mouse', 'trimmer', 'ring_light']
      }
    };

    // Master Archetype Presets Catalog
    var categoryPresets = {
      // ----------------- BAGS & FOOTWEAR -----------------
      'backpack': {
        name: 'Laptop Backpack', icon: '🎒', badge: '30L, 35L', group: 'bags',
        keywords: 'backpack bag laptop college travel school water resistant padded safari wildcraft',
        label: 'Fabric / Material Quality', fabricPlaceholder: 'e.g. Water-Resistant Polyester & Air Mesh',
        fabricDefault: 'Water-Resistant Polyester & Padded Air Mesh', defaultSizes: '30 Liters, 35 Liters',
        sizesHint: 'Backpack Volume / Capacity', chips: ['20 Liters', '25 Liters', '30 Liters', '35 Liters', '40 Liters'],
        colorsDefault: 'Black, Navy Blue, Charcoal Grey, Olive Green', suggestedPrice: 599, suggestedMrp: 1499,
        sampleTitle: 'Safari Large 35L Water-Resistant Laptop & College Backpack with Rain Cover',
        sampleDesc: 'Durable spacious 3-compartment college and travel backpack featuring water-resistant fabric, padded laptop sleeve fitting up to 15.6-inch laptops, ergonomic breathable mesh shoulder straps, and twin water bottle side pockets.'
      },
      'handbag': {
        name: 'Shoulder Tote Handbag', icon: '👜', badge: 'Standard, Large', group: 'bags',
        keywords: 'handbag tote shoulder bag purse leather satchel women ladies office lavie caprese',
        label: 'Material / Outer Build', fabricPlaceholder: 'e.g. Textured Grain PU Leather, Polycarbonate',
        fabricDefault: 'Textured Premium PU Leather & Metal Hardware', defaultSizes: 'Standard, Large Tote',
        sizesHint: 'Handbag Dimensions', chips: ['Compact', 'Standard', 'Large Tote', 'With Coin Pouch Combo'],
        colorsDefault: 'Classic Tan, Black, Wine Red, Blush Pink', suggestedPrice: 699, suggestedMrp: 1999,
        sampleTitle: "Lavie Women's Textured Faux Leather Shoulder Handbag Tote with Coin Pouch",
        sampleDesc: "Elegant women's faux leather shoulder handbag featuring textured grain finish, dual reinforced grab handles, detachable long shoulder strap, spacious multi-pocket interior, and polished gold-toned hardware."
      },
      'sling_bag': {
        name: 'Crossbody Sling Bag', icon: '👛', badge: 'Standard Fit', group: 'bags',
        keywords: 'sling bag crossbody clutch purse wallet party casual chain strap ladies',
        label: 'Material / Chain Finish', fabricPlaceholder: 'e.g. Quilted PU Leather & Golden Chain',
        fabricDefault: 'Quilted PU Leather & Golden Chain Strap', defaultSizes: 'Standard Fit',
        sizesHint: 'Bag Dimensions', chips: ['Mini Sling', 'Standard Fit', 'Envelope Clutch', '2-in-1 Combo'],
        colorsDefault: 'Beige, Black, Emerald Green, Baby Pink', suggestedPrice: 399, suggestedMrp: 1199,
        sampleTitle: "Exquisite Quilted Women's Crossbody Sling Bag with Gold Chain Strap",
        sampleDesc: "Chic quilted crossbody sling bag featuring twist-lock closure, high-grade golden chain shoulder strap, soft polyester lining, inner card slots, and versatile compact styling for parties and casual outings."
      },
      'running_shoes': {
        name: 'Sports & Running Shoes', icon: '👟', badge: 'IND-6 to 10', group: 'shoes_men',
        keywords: 'running shoes sports sneakers mesh gym athletic jogging walking lace up asian sparx',
        label: 'Upper & Sole Material', fabricPlaceholder: 'e.g. Breathable Flyknit Mesh & EVA Phylon Sole',
        fabricDefault: 'Breathable Flyknit Mesh & EVA Phylon Sole', defaultSizes: 'IND-6, IND-7, IND-8, IND-9, IND-10',
        sizesHint: "Men's Shoe Sizes (IND/UK)", chips: ['IND-6', 'IND-7', 'IND-8', 'IND-9', 'IND-10', 'IND-11'],
        colorsDefault: 'Black & White, Navy Blue & Neon, All Black, Grey & Orange', suggestedPrice: 749, suggestedMrp: 1899,
        sampleTitle: "Asian Lightweight Breathable Mesh Men's Sports Running & Walking Shoes",
        sampleDesc: "Ultra-lightweight sports running sneakers crafted with breathable knitted mesh upper, responsive bounce EVA foam cushioning sole, anti-skid grooved rubber grip, and padded collar for superior all-day comfort."
      },
      'formal_shoes': {
        name: 'Leather Formal Shoes', icon: '👞', badge: 'IND-6 to 10', group: 'shoes_men',
        keywords: 'formal shoes oxford derby loafers leather office wedding dress shoes bata hush puppies',
        label: 'Upper & Sole Material', fabricPlaceholder: 'e.g. Synthetic Patent Leather & TPR Sole',
        fabricDefault: 'Synthetic Patent Leather & TPR Anti-Skid Sole', defaultSizes: 'IND-6, IND-7, IND-8, IND-9, IND-10',
        sizesHint: "Men's Shoe Sizes (IND/UK)", chips: ['IND-6', 'IND-7', 'IND-8', 'IND-9', 'IND-10'],
        colorsDefault: 'Glossy Black, Dark Brown, Tan Brown', suggestedPrice: 849, suggestedMrp: 2199,
        sampleTitle: "Bata Men's Classic Lace-Up Leather Finish Formal Derby Office Shoes",
        sampleDesc: "Refined formal dress shoes featuring sleek burnished leatherette finish, cushioned memory foam insole, durable slip-resistant TPR sole, and classic lace-up closure. Perfect for corporate meetings and wedding ceremonies."
      },
      'women_heels': {
        name: 'Block Heels & Sandals', icon: '👡', badge: 'IND-4 to 8', group: 'shoes_women',
        keywords: 'heels sandals party block heel kitten ankle strap ethnic wedding ladies metro mochi',
        label: 'Upper & Insole Material', fabricPlaceholder: 'e.g. Synthetic Suede & Cushioned Insole',
        fabricDefault: 'Synthetic Velvet Suede & Cushioned Insole', defaultSizes: 'IND-4, IND-5, IND-6, IND-7, IND-8',
        sizesHint: "Women's Shoe Sizes (IND/UK)", chips: ['IND-4', 'IND-5', 'IND-6', 'IND-7', 'IND-8'],
        colorsDefault: 'Golden, Silver, Rose Gold, Nude, Black', suggestedPrice: 599, suggestedMrp: 1599,
        sampleTitle: "Metro Women's 2.5-inch Block Heel Ankle Strap Ethnic Party Sandals",
        sampleDesc: "Stunning ethnic party sandals featuring comfortable 2.5-inch block heels, cushioned footbed for pain-free wear, secure adjustable buckle ankle strap, and shimmering metallic strap accents."
      },
      'slippers': {
        name: 'Orthopedic Slippers / Slides', icon: '🩴', badge: 'IND-5 to 10', group: 'shoes_men',
        keywords: 'slippers slides flip flops chappal daily home bathroom soft comfort eva doctor extra soft',
        label: 'Footbed & Sole Material', fabricPlaceholder: 'e.g. Extra-Soft Cushion EVA & Anti-Slip Grip',
        fabricDefault: 'Ultra-Soft Cushion EVA & Anti-Slip Textured Grip', defaultSizes: 'IND-5, IND-6, IND-7, IND-8, IND-9, IND-10',
        sizesHint: 'Footwear Sizes (IND/UK)', chips: ['IND-5', 'IND-6', 'IND-7', 'IND-8', 'IND-9', 'IND-10'],
        colorsDefault: 'Slate Grey, Navy Blue, Jet Black, Olive', suggestedPrice: 299, suggestedMrp: 799,
        sampleTitle: 'Doctor Extra Soft Orthopedic Comfort Daily Wear Flip-Flops / Slippers',
        sampleDesc: 'Lightweight orthopedic flip-flop slippers designed with extra-thick shock-absorbing EVA footbed, textured skid-resistant bottom sole, water-friendly material, and skin-friendly soft toe separator.'
      },
      'luggage_trolley': {
        name: 'Trolley Suitcase', icon: '🧳', badge: '20", 24", 28"', group: 'travel',
        keywords: 'luggage trolley suitcase travel trolley bag 8 wheel tsa lock cabin medium aristocrat american tourister',
        label: 'Shell & Trolley Material', fabricPlaceholder: 'e.g. Unbreakable Polycarbonate Hard Shell',
        fabricDefault: 'Unbreakable Polycarbonate Hard Shell & Aluminum Trolley', defaultSizes: '20 Inch (Cabin), 24 Inch (Medium), 28 Inch (Large)',
        sizesHint: 'Suitcase Dimensions', chips: ['20 Inch (Cabin)', '24 Inch (Medium)', '28 Inch (Large)', '3-Piece Set Combo'],
        colorsDefault: 'Metallic Silver, Teal Blue, Rose Gold, Carbon Black', suggestedPrice: 1999, suggestedMrp: 5499,
        sampleTitle: 'Aristocrat 4-Wheel Spinner Hard-Shell Polycarbonate Trolley Suitcase',
        sampleDesc: 'Durable lightweight travel trolley suitcase featuring scratch-resistant polycarbonate hard shell, 360-degree silent dual-spinner wheels, integrated numeric combination lock, telescopic push-button handle, and organized interior divider.'
      },
      'laptop_briefcase': {
        name: 'Executive Laptop Briefcase', icon: '💼', badge: '15.6 Inch', group: 'bags',
        keywords: 'briefcase laptop messenger office document side bag vegan leather executive',
        label: 'Material & Hardware', fabricPlaceholder: 'e.g. High-Grade Vegan Leather & Brass Zippers',
        fabricDefault: 'High-Grade Vegan Leather & Brass Zippers', defaultSizes: '14 Inch, 15.6 Inch',
        sizesHint: 'Laptop Sleeve Dimensions', chips: ['14 Inch', '15.6 Inch', '17 Inch'],
        colorsDefault: 'Antique Tan, Dark Brown, Jet Black', suggestedPrice: 799, suggestedMrp: 2499,
        sampleTitle: 'Hammonds Flycatcher Executive Vegan Leather Laptop Messenger Briefcase',
        sampleDesc: 'Distinguished office messenger briefcase with padded shock-proof laptop compartment fitting up to 15.6-inch laptops, multi-utility organizer pockets for pens and tablets, detachable shoulder strap, and vintage leather grain texture.'
      },
      'hiking_boots': {
        name: 'Trekking & Hiking Boots', icon: '🥾', badge: 'IND-6 to 10', group: 'travel',
        keywords: 'hiking boots trekking boots high ankle rugged outdoor military desert shoes red chief woodland',
        label: 'Upper & Lugged Sole', fabricPlaceholder: 'e.g. Nubuck Leather & High-Traction Rubber',
        fabricDefault: 'Nubuck Leather & High-Traction Lugged Rubber Sole', defaultSizes: 'IND-6, IND-7, IND-8, IND-9, IND-10',
        sizesHint: "Men's Shoe Sizes (IND/UK)", chips: ['IND-6', 'IND-7', 'IND-8', 'IND-9', 'IND-10'],
        colorsDefault: 'Camel Tan, Olive Green, Stealth Black', suggestedPrice: 1199, suggestedMrp: 2999,
        sampleTitle: "Red Chief Men's High-Ankle Rugged Outdoor Trekking & Hiking Boots",
        sampleDesc: 'Heavy-duty outdoor trekking boots crafted with water-resistant nubuck synthetic upper, padded high-ankle collar for ankle support, deep lugged anti-slip grip rubber outsole, and metal speed lacing hooks.'
      },

      // ----------------- WOMEN ETHNIC -----------------
      'kanjivaram_saree': {
        name: 'Kanjivaram Silk Saree', icon: '🥻', badge: '5.5m + Blouse', group: 'saree',
        keywords: 'saree sari kanjivaram banarasi silk zari border bridal festive wedding',
        label: 'Fabric / Base Silk', fabricPlaceholder: 'e.g. Kanchipuram Art Silk & Gold Zari Weave',
        fabricDefault: 'Kanchipuram Art Silk & Gold Zari Weaving', defaultSizes: 'Free Size (5.5m Saree + 0.8m Blouse)',
        sizesHint: 'Saree Dimensions', chips: ['Free Size', 'With Unstitched Blouse', 'Stitched Blouse Option'],
        colorsDefault: 'Royal Blue & Gold, Deep Maroon, Emerald Green, Mustard Yellow', suggestedPrice: 899, suggestedMrp: 2999,
        sampleTitle: "Women's Woven Kanjivaram Art Silk Saree with Rich Gold Zari Pallu & Blouse Piece",
        sampleDesc: 'Regal Kanjivaram banarasi art silk saree highlighted with intricate floral gold zari jacquard weaving, heavy pallu, wide contrasting zari border, and 0.8m unstitched matching blouse piece.'
      },
      'georgette_saree': {
        name: 'Floral Georgette Saree', icon: '🥻', badge: 'Free Size', group: 'saree',
        keywords: 'georgette saree printed daily wear lightweight floral casual chiffon',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Soft Georgette & Lace Border',
        fabricDefault: 'Soft Breathable Georgette & Lace Border', defaultSizes: 'Free Size (5.5m Saree + 0.8m Blouse)',
        sizesHint: 'Saree Dimensions', chips: ['Free Size', 'Regular 5.5M', 'With Contrast Blouse'],
        colorsDefault: 'Dusty Rose, Sky Blue, Lavender, Mint Green, Peach', suggestedPrice: 399, suggestedMrp: 1199,
        sampleTitle: 'Lightweight Digital Floral Printed Georgette Saree with Scallop Lace Border',
        sampleDesc: 'Feather-light flowing georgette saree featuring pastel digital floral prints, delicate embroidered scallop border, soft breathable drape, and unstitched printed blouse fabric.'
      },
      'anarkali_kurti': {
        name: 'Anarkali Kurti & Pant Set', icon: '👗', badge: 'S, M, L, XL, XXL', group: 'suit',
        keywords: 'anarkali kurti suit set flared dupatta pant gota patti rayon party wedding',
        label: 'Fabric & Embellishment', fabricPlaceholder: 'e.g. Heavy Rayon & Gota Patti Lace',
        fabricDefault: 'Heavy Rayon & Foil Print with Gota Patti Lace', defaultSizes: 'S, M, L, XL, XXL',
        sizesHint: 'Kurti Set Sizes', chips: ['S', 'M', 'L', 'XL', 'XXL', '3XL'],
        colorsDefault: 'Wine, Teal Green, Mustard Yellow, Rani Pink', suggestedPrice: 749, suggestedMrp: 1999,
        sampleTitle: "Women's Flared Anarkali Kurta Pant Set with Printed Nazneen Dupatta",
        sampleDesc: 'Gorgeous 3-piece festive ethnic set including an Anarkali flared kurta with gota patti neck embellishment, matching elasticated trousers, and full-length printed chiffon dupatta.'
      },
      'cotton_kurti': {
        name: 'Straight Cotton Kurti', icon: '👗', badge: 'S to 3XL', group: 'suit',
        keywords: 'straight kurti cotton daily wear embroidered office wear casual college',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 100% Pure Cambric Cotton',
        fabricDefault: '100% Pure Cambric Cotton & Thread Embroidery', defaultSizes: 'S, M, L, XL, XXL, 3XL',
        sizesHint: 'Kurti Sizes', chips: ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'],
        colorsDefault: 'Indigo Blue, Coral Peach, Sage Green, Mustard, White', suggestedPrice: 349, suggestedMrp: 899,
        sampleTitle: "Women's Pure Cotton Straight Embroidered Daily Wear Kurti (Side Slit)",
        sampleDesc: 'Comfortable straight-fit daily wear kurti tailored from 100% pure breathable cotton, highlighted with delicate Kashmiri thread embroidery on the yoke, 3/4 sleeves, and round neckline.'
      },
      'lehenga_choli': {
        name: 'Bridal Lehenga Choli', icon: '💃', badge: 'Semi-Stitched', group: 'festive',
        keywords: 'lehenga choli bridal wedding party wear semi stitched sequin zari velvet',
        label: 'Fabric & Embroidery', fabricPlaceholder: 'e.g. Heavy Net & Mulberry Silk with Zari',
        fabricDefault: 'Heavy Net & Mulberry Silk with Zari Sequin Embroidery', defaultSizes: 'Semi-Stitched (Up to 42" Waist)',
        sizesHint: 'Lehenga Waist Size', chips: ['Semi-Stitched', 'Free Size', 'Fully Stitched M', 'Fully Stitched L'],
        colorsDefault: 'Bridal Red, Deep Wine, Navy Blue, Bottle Green', suggestedPrice: 1499, suggestedMrp: 4999,
        sampleTitle: "Women's Semi-Stitched Heavy Zari & Sequin Embroidered Bridal Lehenga Choli",
        sampleDesc: 'Dazzling designer wedding lehenga choli featuring heavy zari and sequin thread embroidery, 3.5-meter flared ghagra, unstitched matching silk choli fabric, and soft net dupatta with four-side border.'
      },
      'sharara_suit': {
        name: 'Mirror Work Sharara Suit', icon: '👘', badge: 'S, M, L, XL, XXL', group: 'suit',
        keywords: 'sharara gharara suit set peplum flared festive party wear dupatta mirror work',
        label: 'Fabric & Work', fabricPlaceholder: 'e.g. Georgette with Real Mirror Work',
        fabricDefault: 'Georgette with Mirror Work & Santoon Inner Lining', defaultSizes: 'S, M, L, XL, XXL',
        sizesHint: 'Suit Set Sizes', chips: ['S', 'M', 'L', 'XL', 'XXL'],
        colorsDefault: 'Sky Blue, Soft Lilac, Mehndi Green, Sunset Peach', suggestedPrice: 899, suggestedMrp: 2499,
        sampleTitle: "Women's Georgette Mirror Work Short Kurti with Tiered Flared Sharara & Dupatta",
        sampleDesc: 'Glamorous party wear suit set featuring short flared peplum kurti decorated with real mirror work and zari embroidery, multi-tiered flared sharara pants, and lightweight net dupatta.'
      },
      'dupatta': {
        name: 'Bandhani Silk Dupatta', icon: '🧣', badge: '2.25 Meters', group: 'festive',
        keywords: 'dupatta chunni bandhani banarasi silk phulkari embroidered stole scarf',
        label: 'Fabric / Border', fabricPlaceholder: 'e.g. Art Silk Bandhani with Gota Patti',
        fabricDefault: 'Art Silk Bandhani with Gotta Patti Border', defaultSizes: 'Free Size (2.25 Meters Length)',
        sizesHint: 'Dupatta Length', chips: ['Free Size (2.25M)', 'Heavy Bridal (2.5M)'],
        colorsDefault: 'Red & Yellow, Pink & Orange, Green & Red, Multi-Color', suggestedPrice: 249, suggestedMrp: 699,
        sampleTitle: 'Traditional Rajasthani Bandhani Silk Dupatta with Gota Patti Lace Border (2.25m)',
        sampleDesc: 'Vibrant traditional bandhani tie-dye dupatta adorned with exquisite golden gota patti border lace, glossy silk shine, and ethnic tassels. Enhances any simple kurta or lehenga.'
      },
      'palazzo_combo': {
        name: 'Rayon Flared Palazzo', icon: '🩳', badge: 'Pack of 2', group: 'festive',
        keywords: 'palazzo pant cigarette pant combo bottom wear elasticated rayon cotton',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 14kg Heavy Rayon Cotton',
        fabricDefault: 'High-Grade Heavy 14kg Rayon Cotton', defaultSizes: 'Free Size (28-36), Plus Size (36-44)',
        sizesHint: 'Waist Fit (Inches)', chips: ['Free Size (28-36)', 'Plus Size (36-44)', 'Pack of 2 Combo'],
        colorsDefault: 'White & Black Combo, Golden & Cream, Maroon & Navy', suggestedPrice: 399, suggestedMrp: 999,
        sampleTitle: "Women's High-Rise Flared Wide-Leg Rayon Palazzo Pants (Pack of 2)",
        sampleDesc: 'Super-soft premium rayon wide-leg palazzo pants with comfortable elasticated waistband and drawstring tie. Breathable, non-sheer fabric with elegant flare suitable for pairing with kurtis and tops.'
      },

      // ----------------- WOMEN WESTERN -----------------
      'summer_dress': {
        name: 'Floral Midi Dress', icon: '👗', badge: 'XS, S, M, L, XL', group: 'dresses',
        keywords: 'dress midi dress summer dress floral a-line women casual beach knee length',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Lightweight Rayon Crepe',
        fabricDefault: 'Lightweight Rayon Crepe & Smocked Waist', defaultSizes: 'XS, S, M, L, XL',
        sizesHint: 'Dress Sizes', chips: ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        colorsDefault: 'Floral Yellow, Sky Blue Flora, Pastel Pink, Dark Navy Flora', suggestedPrice: 499, suggestedMrp: 1299,
        sampleTitle: "Women's Fit & Flare Floral Printed V-Neck Short Sleeve Casual Midi Dress",
        sampleDesc: 'Breezy floral printed midi dress tailored with a flattering V-neckline, smocked elasticated waist, short ruffled sleeves, and flowy tiered hemline. Ideal for brunch, vacations, and casual outings.'
      },
      'skinny_jeans': {
        name: 'High-Waist Skinny Jeans', icon: '👖', badge: '28, 30, 32, 34', group: 'tops_jeans',
        keywords: 'jeans denim skinny high waist stretchable denim pants women casual',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 98% Cotton Denim & 2% Elastane',
        fabricDefault: '98% Denim Cotton & 2% Elastane Stretch', defaultSizes: '28, 30, 32, 34, 36',
        sizesHint: 'Waist Size (Inches)', chips: ['26', '28', '30', '32', '34', '36'],
        colorsDefault: 'Dark Blue Wash, Light Blue Denim, Jet Black, Charcoal Grey', suggestedPrice: 599, suggestedMrp: 1599,
        sampleTitle: "Women's High-Rise Ankle Length Super Stretchable Slim Fit Denim Jeans",
        sampleDesc: 'Curve-enhancing high-rise skinny jeans engineered with super-stretch denim fabric, 5-pocket styling, zip fly button closure, and clean ankle length fit that keeps its shape all day.'
      },
      'crop_top': {
        name: 'Ribbed Knit Crop Top', icon: '👚', badge: 'XS, S, M, L, XL', group: 'tops_jeans',
        keywords: 'crop top top tshirt ribbed knit square neck casual summer women',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Soft Ribbed Cotton Lycra Stretch',
        fabricDefault: 'Soft Ribbed Cotton Lycra Stretch', defaultSizes: 'XS, S, M, L, XL',
        sizesHint: 'Top Sizes', chips: ['XS', 'S', 'M', 'L', 'XL'],
        colorsDefault: 'White, Jet Black, Sage Green, Lilac, Terracotta', suggestedPrice: 249, suggestedMrp: 699,
        sampleTitle: "Women's Square Neck Full Sleeve Ribbed Knit Slim Fit Casual Crop Top",
        sampleDesc: 'Trendy square-neck crop top crafted in stretchy breathable ribbed cotton knit. Features elegant fitted silhouette, full sleeves, and versatile styling to pair effortlessly with high-waist jeans or skirts.'
      },
      'denim_jacket': {
        name: 'Boyfriend Denim Jacket', icon: '🧥', badge: 'S, M, L, XL', group: 'active_outer',
        keywords: 'denim jacket jacket coat outerwear oversized casual winter layer',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Heavyweight 100% Cotton Denim',
        fabricDefault: 'Heavyweight 100% Cotton Washed Denim', defaultSizes: 'S, M, L, XL',
        sizesHint: 'Jacket Sizes', chips: ['S', 'M', 'L', 'XL'],
        colorsDefault: 'Light Washed Blue, Classic Mid Blue, Vintage Black', suggestedPrice: 899, suggestedMrp: 2499,
        sampleTitle: "Women's Oversized Boyfriend Denim Trucker Jacket with Button Flap Pockets",
        sampleDesc: 'Classic trucker denim jacket featuring comfortable oversized relaxed fit, metallic shank buttons, twin chest flap pockets, spread collar, and distressed vintage wash detailing.'
      },
      'bodycon_dress': {
        name: 'Velvet Cocktail Bodycon', icon: '👗', badge: 'S, M, L, XL', group: 'dresses',
        keywords: 'bodycon dress party dress cocktail clubwear evening ruched slit',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Plush Stretchable Poly-Spandex Velvet',
        fabricDefault: 'Plush Stretchable Poly-Spandex Velvet', defaultSizes: 'S, M, L, XL',
        sizesHint: 'Dress Sizes', chips: ['XS', 'S', 'M', 'L', 'XL'],
        colorsDefault: 'Emerald Green, Wine Red, Midnight Black, Royal Blue', suggestedPrice: 649, suggestedMrp: 1799,
        sampleTitle: "Women's Ruched Side-Slit Velvet Sleeveless Evening Cocktail Bodycon Dress",
        sampleDesc: 'Figure-hugging party bodycon dress fashioned in rich lustrous stretch velvet, featuring flattering side ruching, square neckline, and sultry side slit designed for evening cocktail celebrations.'
      },
      'formal_shirt_women': {
        name: 'Office Formal Shirt', icon: '👔', badge: 'S, M, L, XL, XXL', group: 'tops_jeans',
        keywords: 'formal shirt office shirt button down formal wear corporate women top',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Wrinkle-Resistant Cotton Poplin Blend',
        fabricDefault: 'Wrinkle-Resistant Cotton Poplin Blend', defaultSizes: 'S, M, L, XL, XXL',
        sizesHint: 'Shirt Sizes', chips: ['S', 'M', 'L', 'XL', 'XXL'],
        colorsDefault: 'Crisp White, Sky Blue, Pastel Pink, Jet Black', suggestedPrice: 399, suggestedMrp: 999,
        sampleTitle: "Women's Classic Collared Long Sleeve Wrinkle-Resistant Formal Office Shirt",
        sampleDesc: 'Sharp professional formal shirt tailored from breathable wrinkle-resistant cotton poplin with darted back for tailored feminine silhouette, spread collar, and reinforced buttons.'
      },
      'yoga_leggings': {
        name: 'Seamless Yoga Leggings', icon: '🏃‍♀️', badge: 'S, M, L, XL', group: 'active_outer',
        keywords: 'leggings tights yoga gym workout activewear high waist squat proof',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 4-Way Stretch Microfiber Nylon & Spandex',
        fabricDefault: 'Four-Way Stretch Microfiber Nylon & Spandex', defaultSizes: 'S, M, L, XL',
        sizesHint: 'Tights Sizes', chips: ['XS', 'S', 'M', 'L', 'XL'],
        colorsDefault: 'Black, Dark Olive, Mauve Purple, Navy Blue', suggestedPrice: 399, suggestedMrp: 999,
        sampleTitle: "Women's High-Waist Squat-Proof 4-Way Stretch Workout Gym & Yoga Leggings",
        sampleDesc: 'Buttery-soft seamless workout tights featuring ultra-high tummy control waistband, 100% squat-proof opaque fabric, moisture-wicking technology, and hidden waistband pocket.'
      },
      'cargo_pants': {
        name: 'Utility Cargo Pants', icon: '🩳', badge: '28, 30, 32, 34', group: 'tops_jeans',
        keywords: 'cargo pants cargo trousers streetwear multi pocket baggy relaxed women',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Durable Cotton Twill & Utility Buckles',
        fabricDefault: 'Durable Cotton Twill & Utility Buckles', defaultSizes: '28, 30, 32, 34',
        sizesHint: 'Waist Sizes (Inches)', chips: ['26', '28', '30', '32', '34'],
        colorsDefault: 'Khaki Beige, Camo Green, Jet Black, Grey', suggestedPrice: 699, suggestedMrp: 1899,
        sampleTitle: "Women's High-Waist Baggy Fit Multi-Pocket Utility Cotton Cargo Trousers",
        sampleDesc: 'Streetwear inspired utility cargo pants with relaxed wide-leg cut, 6 deep functional utility flap pockets, adjustable toggle hem cords, and durable pre-washed cotton twill construction.'
      },

      // ----------------- MEN'S FASHION -----------------
      'cotton_tshirt': {
        name: 'Round Neck Cotton T-Shirt', icon: '👕', badge: '180 GSM, S-XXL', group: 'topwear',
        keywords: 'tshirt t-shirt cotton round neck crew neck solid plain daily wear men',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 100% Combed Biowashed Pure Cotton',
        fabricDefault: '100% Combed Biowashed Pure Cotton (180 GSM)', defaultSizes: 'S, M, L, XL, XXL',
        sizesHint: 'T-Shirt Sizes', chips: ['S', 'M', 'L', 'XL', 'XXL', '3XL'],
        colorsDefault: 'Jet Black, Navy Blue, Maroon, Olive Green, White', suggestedPrice: 249, suggestedMrp: 699,
        sampleTitle: "Men's 100% Combed Cotton Regular Fit Round Neck Solid Plain T-Shirt (180 GSM)",
        sampleDesc: 'Premium bio-washed 100% cotton casual crewneck t-shirt with soft hand-feel, zero pilling, ribbed collar with neck tape, and breathable all-day comfort.'
      },
      'formal_shirt_men': {
        name: 'Slim Fit Formal Shirt', icon: '👔', badge: '38, 40, 42, 44', group: 'topwear',
        keywords: 'formal shirt office shirt slim fit cotton button down dress shirt men raymond arrow',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Cotton Rich Giza Blend & Easy Iron',
        fabricDefault: 'Cotton Rich Giza Blend & Non-Iron Finish', defaultSizes: '38 (S), 40 (M), 42 (L), 44 (XL)',
        sizesHint: 'Collar / Chest Sizes', chips: ['38', '39', '40', '42', '44', '46'],
        colorsDefault: 'Classic White, Light Blue, Soft Pink, Charcoal Grey', suggestedPrice: 499, suggestedMrp: 1499,
        sampleTitle: "Raymond Men's Slim Fit Giza Cotton Full Sleeve Formal Office Dress Shirt",
        sampleDesc: 'Crisp formal dress shirt woven with high-thread-count Giza cotton blend, structured semi-spread collar, single chest pocket, convertible mitered cuffs, and anti-wrinkle easy-iron finish.'
      },
      'casual_check_shirt': {
        name: 'Buffalo Check Casual Shirt', icon: '👕', badge: 'M, L, XL, XXL', group: 'topwear',
        keywords: 'check shirt casual shirt flannel buffalo check button down cotton men',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 100% Pre-Washed Flannel Cotton',
        fabricDefault: '100% Pre-Washed Flannel Cotton', defaultSizes: 'M, L, XL, XXL',
        sizesHint: 'Shirt Sizes', chips: ['S', 'M', 'L', 'XL', 'XXL'],
        colorsDefault: 'Red & Navy Check, Green & Black, Blue & White, Yellow & Black', suggestedPrice: 449, suggestedMrp: 1299,
        sampleTitle: "Men's 100% Cotton Buffalo Checkered Full Sleeve Casual Button-Down Shirt",
        sampleDesc: 'Modern casual button-down shirt styled in timeless buffalo checks, tailored from soft pre-washed cotton flannel, featuring button-down collar, curved hem, and twin chest patch pockets.'
      },
      'men_jeans': {
        name: 'Stretch Slim Fit Jeans', icon: '👖', badge: '30, 32, 34, 36, 38', group: 'bottomwear',
        keywords: 'jeans denim pants slim fit stretchable blue black wash men trousers spykar levis',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Cotton Denim with 2% Spandex Stretch',
        fabricDefault: 'Cotton Denim with 2% Spandex Stretch', defaultSizes: '30, 32, 34, 36, 38',
        sizesHint: 'Waist Sizes (Inches)', chips: ['28', '30', '32', '34', '36', '38'],
        colorsDefault: 'Dark Indigo Wash, Mid Blue, Jet Black, Faded Grey', suggestedPrice: 699, suggestedMrp: 1799,
        sampleTitle: "Spykar Men's Mid-Rise Slim Fit Stretchable Denim Jeans (Dark Indigo Wash)",
        sampleDesc: 'Comfortable slim fit denim jeans crafted from durable cotton denim infused with spandex stretch for unrestricted flexibility, classic 5-pocket styling, and whiskers fade detailing.'
      },
      'men_trousers': {
        name: 'Cotton Chino Trousers', icon: '🩳', badge: '30 to 38 Inch', group: 'bottomwear',
        keywords: 'chinos trousers pants cotton formal casual slim fit stretch men khaki',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Stretch Cotton Satin Twill',
        fabricDefault: 'Stretch Cotton Satin Twill', defaultSizes: '30, 32, 34, 36, 38',
        sizesHint: 'Waist Sizes (Inches)', chips: ['30', '32', '34', '36', '38', '40'],
        colorsDefault: 'Khaki Beige, Navy Blue, Olive Green, Charcoal Grey, Black', suggestedPrice: 599, suggestedMrp: 1599,
        sampleTitle: "Men's Stretchable Cotton Chino Casual Trousers / Office Khaki Pants",
        sampleDesc: 'Versatile flat-front chino trousers fashioned from smooth cotton satin twill with built-in stretch, slant front pockets, rear button welt pockets, and tailored tapered leg silhouette.'
      },
      'men_kurta': {
        name: 'Festive Cotton Kurta Pyjama', icon: '🥻', badge: '38, 40, 42, 44', group: 'ethnic_winter',
        keywords: 'kurta pyjama set traditional ethnic wedding diwali eid cotton men manyavar',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Slub Cotton & Embroidered Mandarin Collar',
        fabricDefault: 'Slub Cotton & Thread Embroidery Mandarin Collar', defaultSizes: '38 (S), 40 (M), 42 (L), 44 (XL)',
        sizesHint: 'Chest Sizes (Inches)', chips: ['36', '38', '40', '42', '44', '46'],
        colorsDefault: 'Mustard Yellow, Royal Blue, Maroon, Pristine White, Emerald', suggestedPrice: 649, suggestedMrp: 1799,
        sampleTitle: "Men's Festive Pure Cotton Long Kurta with Churidar Pyjama Set",
        sampleDesc: 'Graceful ethnic long kurta set tailored in breathable slub cotton with subtle embroidered mandarin collar, full sleeves, side slits, and matching free-size white cotton drawstring pyjama.'
      },
      'men_hoodie': {
        name: 'Fleece Winter Hoodie', icon: '🧥', badge: '320 GSM Fleece', group: 'ethnic_winter',
        keywords: 'hoodie sweatshirt pullover winter jacket fleece warm kangaroo pocket men',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 320 GSM Heavyweight Brushed Cotton Fleece',
        fabricDefault: '320 GSM Heavyweight Brushed Cotton Fleece', defaultSizes: 'M, L, XL, XXL',
        sizesHint: 'Sweatshirt Sizes', chips: ['S', 'M', 'L', 'XL', 'XXL'],
        colorsDefault: 'Maroon, Heather Grey, Jet Black, Navy Blue, Bottle Green', suggestedPrice: 699, suggestedMrp: 1899,
        sampleTitle: "Men's Heavyweight Fleece Solid Hooded Sweatshirt with Kangaroo Pocket",
        sampleDesc: 'Cozy winter pullover hoodie constructed from premium 320 GSM fleece lined with brushed warm cotton, featuring double-layered hood with adjustable drawstrings, and spacious front kangaroo pocket.'
      },
      'men_boxers': {
        name: 'Cotton Boxer Briefs', icon: '🩲', badge: 'Pack of 3', group: 'bottomwear',
        keywords: 'boxers briefs innerwear underwear cotton trunks elastic pack of 3 men jockey',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 100% Combed Soft Cotton & Micro-Nylon',
        fabricDefault: '100% Combed Soft Cotton & Micro-Nylon Waistband', defaultSizes: 'M (30-32), L (32-34), XL (34-36), XXL (36-38)',
        sizesHint: 'Waist Fit (Inches)', chips: ['M (30-32)', 'L (32-34)', 'XL (34-36)', 'XXL (36-38)'],
        colorsDefault: 'Assorted Multi-Color (Pack of 3)', suggestedPrice: 349, suggestedMrp: 899,
        sampleTitle: "Jockey Men's 100% Combed Cotton Soft Stretch Boxer Briefs (Pack of 3)",
        sampleDesc: 'Ultra-soft breathable combed cotton boxer trunks with itch-free microfiber plush waistband, reinforced pouch support, and flatlock seams to prevent chafing during everyday wear.'
      },

      // ----------------- KIDS & BABY -----------------
      'baby_romper': {
        name: 'Organic Cotton Rompers', icon: '👶', badge: 'Pack of 3', group: 'baby',
        keywords: 'romper onesie bodysuit newborn baby infant cotton snap button pack of 3',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 100% Certified Organic Interlock Cotton',
        fabricDefault: '100% Certified Organic Interlock Soft Cotton', defaultSizes: '0-3 Months, 3-6 Months, 6-12 Months',
        sizesHint: 'Baby Age Groups', chips: ['0-3 Months', '3-6 Months', '6-12 Months', '12-18 Months'],
        colorsDefault: 'Pastel Blue, Mint, Lemon Yellow Prints', suggestedPrice: 399, suggestedMrp: 999,
        sampleTitle: 'Unisex Newborn Baby 100% Organic Soft Cotton Printed Rompers (Pack of 3)',
        sampleDesc: 'Ultra-gentle newborn onesie rompers made from chemical-free organic cotton, featuring nickel-free front snap buttons for easy diaper changing, expandable lap necklines, and cute animal prints.'
      },
      'girls_frock': {
        name: 'Princess Party Frock', icon: '👗', badge: '1-2Y to 5-6Y', group: 'kids_wear',
        keywords: 'frock dress baby girl floral party birthday summer net cotton princess',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Soft Net Overlay & Cotton Inner Lining',
        fabricDefault: 'Soft Net Overlay & 100% Cotton Inner Lining', defaultSizes: '1-2 Years, 2-3 Years, 3-4 Years, 5-6 Years',
        sizesHint: 'Age Groups', chips: ['6-12 Months', '1-2 Years', '2-3 Years', '3-4 Years', '5-6 Years', '7-8 Years'],
        colorsDefault: 'Blush Pink, Sky Blue, Lavender, Peach', suggestedPrice: 449, suggestedMrp: 1199,
        sampleTitle: 'Baby Girls Fluffy Party Wear Princess Frock with Flower Bow Accent',
        sampleDesc: 'Charming party frock for little girls featuring layered soft tulle skirt with satin waistband and floral bow, breathable pure cotton inner lining for skin protection, and concealed back zipper.'
      },
      'boys_tshirt_set': {
        name: 'Boys Tee & Shorts Set', icon: '👕', badge: '2-3Y to 8-9Y', group: 'kids_wear',
        keywords: 'boys clothing set tshirt shorts combo summer cotton cartoon printed',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. 100% Breathable Bio-Washed Cotton',
        fabricDefault: '100% Breathable Bio-Washed Cotton', defaultSizes: '2-3 Years, 4-5 Years, 6-7 Years, 8-9 Years',
        sizesHint: 'Age Groups', chips: ['1-2 Years', '2-3 Years', '4-5 Years', '6-7 Years', '8-9 Years', '10-11 Years'],
        colorsDefault: 'Navy & Yellow, Red & Grey, White & Blue', suggestedPrice: 399, suggestedMrp: 999,
        sampleTitle: 'Boys Cool Graphic Print Cotton T-Shirt & Elastic Drawstring Shorts Set',
        sampleDesc: 'Playful 2-piece summer clothing set for active boys including graphic printed cotton crewneck tee and matching elasticated waistband shorts with twin pockets.'
      },
      'kids_traditional': {
        name: 'Festive Dhoti Kurta Set', icon: '🥻', badge: '1-2Y to 6-7Y', group: 'kids_wear',
        keywords: 'kids ethnic traditional dhoti kurta lehenga festive diwali wedding boys girls',
        label: 'Fabric / Material', fabricPlaceholder: 'e.g. Jacquard Silk & Cotton Lining',
        fabricDefault: 'Jacquard Silk & Cotton Lining', defaultSizes: '1-2 Years, 2-3 Years, 4-5 Years, 6-7 Years',
        sizesHint: 'Age Groups', chips: ['1-2 Years', '2-3 Years', '4-5 Years', '6-7 Years', '8-9 Years'],
        colorsDefault: 'Yellow & Red, Maroon & Gold, Royal Blue', suggestedPrice: 599, suggestedMrp: 1599,
        sampleTitle: 'Kids Festive Jacquard Silk Kurta with Stitched Readymade Dhoti Pant Set',
        sampleDesc: 'Adorable festive outfit for kids featuring gold jacquard woven ethnic kurta with mandarin collar and comfortable pre-stitched elasticated waistband dhoti pants with zari border.'
      },
      'kids_jacket': {
        name: 'Kids Winter Puffer Jacket', icon: '🧥', badge: 'Hooded Warm', group: 'shoes_winter',
        keywords: 'kids jacket winter warm coat hooded puffer fleece zip boys girls',
        label: 'Fabric & Insulation', fabricPlaceholder: 'e.g. Windproof Polyester & Polyfill Insulation',
        fabricDefault: 'Windproof Polyester Shell & Polyfill Thermal Insulation', defaultSizes: '3-4 Years, 5-6 Years, 7-8 Years, 9-10 Years',
        sizesHint: 'Age Groups', chips: ['2-3 Years', '3-4 Years', '5-6 Years', '7-8 Years', '9-10 Years'],
        colorsDefault: 'Bright Red, Royal Blue, Mustard Yellow, Bubblegum Pink', suggestedPrice: 699, suggestedMrp: 1799,
        sampleTitle: 'Kids Warm Padded Winter Puffer Jacket with Fleece Lined Hood',
        sampleDesc: 'Lightweight yet ultra-warm quilted winter jacket with windproof outer fabric, thermal polyfill padding, cozy fleece-lined hood with chin guard, and smooth zip front closure.'
      },
      'kids_shoes': {
        name: 'LED Light-Up Shoes', icon: '👟', badge: 'IND-1 to IND-5', group: 'shoes_winter',
        keywords: 'kids shoes sneakers led light up boys girls sports velcro running',
        label: 'Upper & Sole', fabricPlaceholder: 'e.g. Breathable Mesh & Glowing LED Sole',
        fabricDefault: 'Breathable Mesh & Lightweight Glowing LED Sole', defaultSizes: 'IND-1, IND-2, IND-3, IND-4, IND-5',
        sizesHint: 'Kids Shoe Sizes (IND)', chips: ['IND-1', 'IND-2', 'IND-3', 'IND-4', 'IND-5'],
        colorsDefault: 'Blue & Orange, Pink & White, Black & Neon', suggestedPrice: 499, suggestedMrp: 1299,
        sampleTitle: 'Kids Lightweight LED Flashing Light-Up Sports Shoes with Velcro Strap',
        sampleDesc: 'Fun and comfortable kids sneakers featuring motion-activated glowing multi-color LED lights in the heel sole, breathable mesh upper, and easy slip-on hook-and-loop velcro strap.'
      },

      // ----------------- HOME & KITCHEN -----------------
      'bedsheet': {
        name: 'King Cotton Bedsheet', icon: '🛏️', badge: 'King Size (210 TC)', group: 'bedding_decor',
        keywords: 'bedsheet bed sheet cotton double bed king size floral 2 pillow covers bombay dyeing',
        label: 'Fabric / Thread Count', fabricPlaceholder: 'e.g. 100% Pure Glace Cotton (210 TC)',
        fabricDefault: '100% Pure Glace Cotton (210 TC)', defaultSizes: 'Double Bed (90x100 in), King Size (108x108 in)',
        sizesHint: 'Bedsheet Dimensions', chips: ['Single Bed', 'Double Bed (90x100 in)', 'King Size (108x108 in)'],
        colorsDefault: 'Floral Blue, Geometric Grey, Ethnic Maroon, Pastel Green', suggestedPrice: 499, suggestedMrp: 1299,
        sampleTitle: 'Bombay Dyeing 100% Pure Cotton Double Bed King Bedsheet with 2 Pillow Covers',
        sampleDesc: 'Luxurious 210 thread count pure cotton king-size bedsheet set with fade-resistant vibrant reactive floral prints, soft skin-friendly texture, and 2 matching envelope closure pillowcases.'
      },
      'cookware_kadai': {
        name: 'Tri-Ply Stainless Kadai', icon: '🍲', badge: '2L, 3L Induction', group: 'cookware_dining',
        keywords: 'kadai pan cookware stainless steel tri ply induction gas non stick kitchen prestige hawkins',
        label: 'Material / Specifications', fabricPlaceholder: 'e.g. Heavy Tri-Ply SS304 & Aluminum Core',
        fabricDefault: 'Heavy Tri-Ply SS304 Stainless Steel & Aluminum Core', defaultSizes: '2 Liters (22cm), 3 Liters (24cm), 4.5 Liters (26cm)',
        sizesHint: 'Capacity & Diameter', chips: ['1.5 Liters', '2 Liters', '3 Liters', '4.5 Liters', 'With Glass Lid'],
        colorsDefault: 'Polished Mirror Silver', suggestedPrice: 1199, suggestedMrp: 2699,
        sampleTitle: 'Prestige Tri-Ply Heavy Stainless Steel Deep Kadai with Stainless Steel Lid',
        sampleDesc: 'Professional grade 3-layer induction friendly cooking kadai with pure aluminum core encapsulated between food-grade SS304 steel for uniform heat distribution without burning, with cast steel handles.'
      },
      'water_bottle': {
        name: 'Insulated Thermos Bottle', icon: '🧊', badge: '500ml, 1000ml', group: 'utilities',
        keywords: 'water bottle flask insulated vacuum hot cold steel thermos gym office milton',
        label: 'Material / Grade', fabricPlaceholder: 'e.g. Food-Grade SS304 Stainless Steel',
        fabricDefault: 'Food-Grade SS304 Rust-Free Stainless Steel', defaultSizes: '500 ml, 750 ml, 1000 ml',
        sizesHint: 'Bottle Capacity', chips: ['500 ml', '750 ml', '1000 ml', '1500 ml'],
        colorsDefault: 'Matte Black, Metallic Blue, Stainless Silver, Rose Gold', suggestedPrice: 399, suggestedMrp: 999,
        sampleTitle: 'Milton Thermosteel 1000ml Vacuum Insulated Hot & Cold Water Flask',
        sampleDesc: 'Double-wall vacuum insulated stainless steel water bottle keeping beverages hot for 18 hours or cold for 24 hours. Features sweat-proof condensation-free exterior, leak-proof screw cap, and BPA-free construction.'
      },
      'curtains': {
        name: 'Jacquard Door Curtains', icon: '🚪', badge: '5ft, 7ft, 9ft', group: 'bedding_decor',
        keywords: 'curtains drape window door eyelet blackout jacquard luxury home decor set of 2',
        label: 'Fabric & Eyelets', fabricPlaceholder: 'e.g. Heavy Polyester Jacquard Weave',
        fabricDefault: 'Heavy Polyester Jacquard Weave & Brass Eyelets', defaultSizes: '5 Feet (Window), 7 Feet (Door), 9 Feet (Long Door)',
        sizesHint: 'Curtain Length (Set of 2)', chips: ['5 Feet (Window)', '7 Feet (Door)', '9 Feet (Long Door)'],
        colorsDefault: 'Royal Navy, Warm Beige, Coffee Brown, Wine Red, Grey', suggestedPrice: 599, suggestedMrp: 1599,
        sampleTitle: 'Home Sizzler Premium Heavy Jacquard Floral 7ft Door Curtains (Set of 2)',
        sampleDesc: 'Room-darkening heavy jacquard weave curtains designed to block out sunlight and thermal heat. Features 8 rust-resistant metallic eyelet grommets per panel for smooth sliding on any curtain rod.'
      },
      'spin_mop': {
        name: '360 Spin Floor Mop', icon: '🧹', badge: 'With 2 Refills', group: 'utilities',
        keywords: 'mop spin mop bucket microfiber floor cleaning cleaning wipe home gala scotch brite',
        label: 'Material / Mechanism', fabricPlaceholder: 'e.g. SS Spinner & Microfiber Head',
        fabricDefault: 'Virgin Plastic Bucket & SS Spinner with Microfiber Head', defaultSizes: 'Standard Bucket with 2 Microfiber Refills',
        sizesHint: 'Set Configuration', chips: ['With 2 Mop Refills', 'With 4 Mop Refills', 'Wheels & Puller Edition'],
        colorsDefault: 'Aqua Blue, Fresh Green, Purple', suggestedPrice: 799, suggestedMrp: 1899,
        sampleTitle: 'Gala e-Quick 360° Heavy Stainless Steel Wringer Spin Mop with 2 Refills',
        sampleDesc: 'Effortless floor cleaning spin mop set with rust-proof stainless steel wringer spinner, 360-degree rotating super-absorbent microfiber head, extendable stainless steel handle, and water outlet plug.'
      },
      'dinner_set': {
        name: 'Opalware Dinner Set', icon: '🍽️', badge: '18 / 33 Pcs', group: 'cookware_dining',
        keywords: 'dinner set plates bowls opalware ceramic bone china microwave safe tableware cello laopala',
        label: 'Material / Coating', fabricPlaceholder: 'e.g. Toughened Extra-Strong Opal Glass',
        fabricDefault: 'Toughened Extra-Strong Opal Glass & Scratch Resistant', defaultSizes: '18 Pieces (4-Person), 33 Pieces (6-Person)',
        sizesHint: 'Dinner Set Pieces', chips: ['12 Pieces', '18 Pieces', '27 Pieces', '33 Pieces'],
        colorsDefault: 'White with Floral Gold, Blue Royale, Black Geometric', suggestedPrice: 1299, suggestedMrp: 2999,
        sampleTitle: 'Cello Opalware Imperial Toughened 18-Piece Microwave Safe Dinnerware Set',
        sampleDesc: 'Elegant break-resistant opalware dinner set including full dinner plates, quarter snack plates, and vegetable bowls. 100% vegetarian bone-ash free, microwave safe, dishwasher safe, and chip-resistant.'
      },

      // ----------------- BEAUTY & HEALTH -----------------
      'face_serum': {
        name: 'Vitamin C Radiance Serum', icon: '🧴', badge: '30ml Glow', group: 'skincare',
        keywords: 'face serum vitamin c hyaluronic acid glowing skin dark spot anti aging garnier minimalist',
        label: 'Formulation / Active Actives', fabricPlaceholder: 'e.g. 10% Vitamin C + Hyaluronic Acid Formula',
        fabricDefault: '10% Vitamin C + Hyaluronic Acid + Ferulic Acid Formula', defaultSizes: '30 ml, 50 ml, Pack of 2 (30ml each)',
        sizesHint: 'Serum Volume', chips: ['15 ml', '30 ml', '50 ml', 'Pack of 2'],
        colorsDefault: 'Clear Glow Formula', suggestedPrice: 349, suggestedMrp: 699,
        sampleTitle: 'Garnier Skin Naturals 10% Vitamin C Brightening Face Serum for Glowing Skin (30ml)',
        sampleDesc: 'Concentrated lightweight radiance face serum enriched with Vitamin C and hyaluronic acid. Clinically proven to reduce dark spots, boost skin luminosity, and deeply hydrate without greasy residue.'
      },
      'matte_lipstick': {
        name: 'Matte Liquid Lipstick', icon: '💄', badge: 'Pack of 4', group: 'makeup',
        keywords: 'lipstick liquid matte waterproof smudge proof long lasting nude red combo swiss beauty maybelline',
        label: 'Finish / Formula', fabricPlaceholder: 'e.g. Velveteen Matte Long-Stay Formula with Vitamin E',
        fabricDefault: 'Velveteen Matte Long-Stay Formula with Vitamin E', defaultSizes: 'Set of 4 Shades, Set of 12 Shades',
        sizesHint: 'Lipstick Pack Units', chips: ['Pack of 1 (Single)', 'Pack of 4 Nudes', 'Pack of 4 Reds', 'Pack of 12 Mini Combo'],
        colorsDefault: 'Nude Brown, Ruby Red, Coral Pink, Plum Wine', suggestedPrice: 299, suggestedMrp: 799,
        sampleTitle: 'Swiss Beauty Non-Transfer 16H Waterproof Matte Liquid Lipstick (Pack of 4 Nudes)',
        sampleDesc: 'Weightless non-transfer liquid lipstick delivering intense matte color in one swipe. Enriched with Vitamin E and jojoba oil to keep lips moisturized with up to 16 hours smudge-proof stay.'
      },
      'hair_oil': {
        name: 'Red Onion Hair Oil', icon: '🧖‍♀️', badge: '200ml Growth', group: 'haircare',
        keywords: 'hair oil onion oil redensyl hair fall control growth castor oil herbal mamaearth wow',
        label: 'Key Herbal Extracts', fabricPlaceholder: 'e.g. Cold-Pressed Onion Seed Oil with Redensyl',
        fabricDefault: 'Cold-Pressed Onion Black Seed Oil with Redensyl', defaultSizes: '100 ml, 200 ml, Pack of 2 (200ml)',
        sizesHint: 'Bottle Volume', chips: ['100 ml', '150 ml', '200 ml', '300 ml'],
        colorsDefault: 'Golden Amber Herbal Extract', suggestedPrice: 299, suggestedMrp: 599,
        sampleTitle: 'Mamaearth Onion Hair Fall Control Oil with Redensyl & Almond Oil (200ml)',
        sampleDesc: 'Nourishing Ayurvedic hair oil formulated with sulfur-rich red onion extract, redensyl, and natural carrier oils. Strengthens hair roots, reduces breakage, controls dandruff, and boosts scalp circulation.'
      },
      'sunscreen': {
        name: 'Ultra-Light Gel Sunscreen', icon: '🧴', badge: 'SPF 50 PA+++', group: 'skincare',
        keywords: 'sunscreen sun block gel spf 50 uv protection non greasy white cast aqualogica derma co',
        label: 'UV Filter & Finish', fabricPlaceholder: 'e.g. Broad Spectrum UVA/UVB Gel with Hyaluronic Acid',
        fabricDefault: 'Broad Spectrum UVA/UVB Gel with Hyaluronic Acid', defaultSizes: '50 g, 100 g, 2x50g Combo',
        sizesHint: 'Tube Weight', chips: ['50 g', '80 g', '100 g', '2-Pack Combo'],
        colorsDefault: 'Zero White Cast Transparent Gel', suggestedPrice: 349, suggestedMrp: 699,
        sampleTitle: 'Aqualogica Radiance Dewy SPF 50+ PA++++ Sunscreen Gel with Niacinamide (50g)',
        sampleDesc: 'Feather-light hydrating sunscreen gel offering broad-spectrum SPF 50+ PA++++ protection against UVA, UVB, and blue light. Non-comedogenic, zero white cast, non-sticky water-fresh finish.'
      },
      'face_wash': {
        name: 'Purifying Neem Face Wash', icon: '🧼', badge: '150ml Anti-Acne', group: 'skincare',
        keywords: 'face wash cleanser tea tree salicylic acid acne pimple oil control himalaya clean and clear',
        label: 'Cleansing Base & Extract', fabricPlaceholder: 'e.g. Natural Tea Tree Leaf Oil & Salicylic Acid',
        fabricDefault: 'Natural Tea Tree Leaf Oil & 1% Salicylic Acid Gel', defaultSizes: '100 ml, 150 ml, 250 ml Pump',
        sizesHint: 'Volume', chips: ['100 ml', '150 ml', '200 ml', '250 ml Pump'],
        colorsDefault: 'Clear Purifying Gel', suggestedPrice: 249, suggestedMrp: 499,
        sampleTitle: 'Himalaya Purifying Neem & Tea Tree Oil-Free Face Wash for Acne Control (150ml)',
        sampleDesc: 'Herbal soap-free facial cleanser blending pure neem extract and natural tea tree oil to gently remove deep pore impurities, regulate excess oil production, and prevent recurring pimples.'
      },

      // ----------------- JEWELLERY & ACCESSORIES -----------------
      'kundan_necklace': {
        name: 'Kundan Choker Necklace Set', icon: '📿', badge: 'Bridal Set', group: 'jewellery',
        keywords: 'necklace choker kundan gold plated bridal wedding jewellery earrings maang tikka zaveri pearls',
        label: 'Material / Base Metal', fabricPlaceholder: 'e.g. Brass Alloy & Handcrafted Kundan Stones',
        fabricDefault: 'Brass Alloy & Handcrafted Kundan Stones with Pearls', defaultSizes: 'Free Size, Adjustable Dori',
        sizesHint: 'Necklace Fit', chips: ['Free Size', 'With Earrings & Maang Tikka', 'Heavy Choker Set'],
        colorsDefault: 'Gold & White Pearl, Emerald Green & Gold, Ruby Red & Gold', suggestedPrice: 499, suggestedMrp: 1499,
        sampleTitle: 'Zaveri Pearls Gold Plated Kundan & Emerald Beaded Bridal Choker Necklace Set',
        sampleDesc: 'Exquisite bridal wedding choker necklace set handcrafted with lustrous kundan stones, green drop beads, matching dangle jhumki earrings, maang tikka, and adjustable cord closure.'
      },
      'diamond_ring': {
        name: 'CZ Solitaire Adjustable Ring', icon: '💍', badge: 'Adjustable', group: 'jewellery',
        keywords: 'ring finger ring diamond cz american diamond solitaire adjustable silver gold giva',
        label: 'Base Metal & Plating', fabricPlaceholder: 'e.g. 925 Sterling Silver Plating & AAA Zirconia',
        fabricDefault: '925 Sterling Silver Plating & AAA Grade Swiss Zirconia', defaultSizes: 'Adjustable Free Size (Fits Ring 12 to 18)',
        sizesHint: 'Ring Size', chips: ['Adjustable Free Size', 'Ring 12', 'Ring 14', 'Ring 16', 'Ring 18'],
        colorsDefault: 'Sparkling Silver, Rose Gold, Classic Yellow Gold', suggestedPrice: 299, suggestedMrp: 899,
        sampleTitle: 'GIVA 925 Sterling Silver Plated Sparkling Solitaire CZ Adjustable Ring',
        sampleDesc: 'Radiant luxury solitaire ring crowned with AAA grade American Diamond cubic zirconia stone set in high-polish rhodium finish, crafted with open adjustable band for a comfortable custom fit.'
      },
      'bangles_set': {
        name: 'Velvet Glass Bangles Set', icon: '🪙', badge: 'Set of 24 (2.4, 2.6)', group: 'jewellery',
        keywords: 'bangles chuda kada velvet glass gold plated bridal festive wedding',
        label: 'Bangle Core & Coating', fabricPlaceholder: 'e.g. Velvet Coated Glass & Gold Kada',
        fabricDefault: 'Velvet Coated Glass Bangles & Gold Metal Kada Accent', defaultSizes: '2.4, 2.6, 2.8',
        sizesHint: 'Bangle Diameter (Inches)', chips: ['2.4 (Small)', '2.6 (Medium)', '2.8 (Large)', '2.10 (Extra Large)'],
        colorsDefault: 'Maroon & Gold, Rani Pink, Bottle Green, Royal Blue', suggestedPrice: 349, suggestedMrp: 899,
        sampleTitle: 'Traditional Velvet Coated Glass Bangles with Gold Plated Kadas (Set of 24)',
        sampleDesc: 'Vibrant festive bangle set combining velvety soft colored bangles paired with ornate golden antique kadas. Smooth finished edges that slip comfortably on wrists without scratching.'
      },
      'sunglasses': {
        name: 'Polarized UV400 Sunglasses', icon: '🕶️', badge: 'UV400 Wayfarer', group: 'accessories',
        keywords: 'sunglasses shades eyewear polarized uv400 protection wayfarer aviator men women fastrack vincent chase',
        label: 'Frame & Lens Quality', fabricPlaceholder: 'e.g. Acetate Frame & Polarized UV400 Lens',
        fabricDefault: 'Durable Acetate Frame & Polarized UV400 Polycarbonate Lens', defaultSizes: 'Medium (Standard), Large',
        sizesHint: 'Frame Width', chips: ['Medium (52mm)', 'Large (56mm)', 'With Hard Case & Cloth'],
        colorsDefault: 'Matte Black, Tortoise Shell, Gold Frame with Green G15 Lens', suggestedPrice: 399, suggestedMrp: 1199,
        sampleTitle: 'Fastrack Polarized UV400 Wayfarer Sunglasses with Protective Hard Case',
        sampleDesc: 'Classic wayfarer unisex sunglasses equipped with TAC polarized lenses providing 100% UV400 ultraviolet protection, glare reduction, impact-resistant acetate frame, and lightweight comfortable nose bridge.'
      },
      'men_wallet': {
        name: "Leather RFID Men's Wallet", icon: '👛', badge: 'Bi-Fold RFID', group: 'accessories',
        keywords: 'wallet purse leather rfid blocking bi fold card holder men money clip wildhorn woodland',
        label: 'Leather & Shielding', fabricPlaceholder: 'e.g. 100% Genuine Leather & RFID Lining',
        fabricDefault: '100% Genuine Top-Grain Leather & RFID Shielding Lining', defaultSizes: 'Standard Bi-Fold (4.5 x 3.5 inches)',
        sizesHint: 'Wallet Dimensions', chips: ['Standard Bi-Fold', 'Slim Minimalist', 'Zipper Coin Pocket Edition'],
        colorsDefault: 'Vintage Brown, Classic Black, Hunter Tan', suggestedPrice: 399, suggestedMrp: 999,
        sampleTitle: "WildHorn Genuine Leather Vintage RFID-Protected Bi-Fold Wallet for Men",
        sampleDesc: "Handcrafted full-grain leather men's wallet with built-in advanced RFID blocking technology to safeguard credit cards against electronic scanning. Features 8 card slots, 2 currency compartments, and ID window."
      },
      'men_belt': {
        name: 'Reversible Leather Belt', icon: '👔', badge: '2-in-1 Black/Brown', group: 'accessories',
        keywords: 'belt leather formal casual reversible black brown pin buckle men waist urban forest',
        label: 'Leather & Buckle', fabricPlaceholder: 'e.g. Full-Grain Leather & Alloy Swivel Buckle',
        fabricDefault: 'Full-Grain Leather & Alloy Reversible Swivel Buckle', defaultSizes: '28-34 (Small/Med), 34-40 (Large/XL)',
        sizesHint: 'Waist Fit (Inches)', chips: ['28-34 Inch', '34-40 Inch', '40-44 Inch'],
        colorsDefault: '2-in-1 Reversible (Black & Brown)', suggestedPrice: 349, suggestedMrp: 999,
        sampleTitle: "Urban Forest 2-in-1 Reversible Genuine Leather Men's Formal & Casual Belt",
        sampleDesc: 'Premium dual-sided leather belt with twistable swivel alloy buckle allowing seamless transition between formal black and casual rich brown. Micro-adjustable holes ensure a comfortable tailored fit.'
      },

      // ----------------- ELECTRONICS (27 ARCHETYPES) -----------------
      'laptop': {
        name: 'Laptop', icon: '💻', badge: '14", 15.6"', group: 'computing',
        keywords: 'laptop notebook macbook pc screen inch core i5 i7 ram display 14 15.6 16 hp dell lenovo asus',
        label: 'Chassis / Build Material', fabricPlaceholder: 'e.g. Aluminum Alloy, Metallic Body, Polycarbonate',
        fabricDefault: 'Aluminum Alloy & Metallic Finish', defaultSizes: '14 Inch, 15.6 Inch',
        sizesHint: 'Laptop Screen Sizes', chips: ['13.3 Inch', '14 Inch', '15 Inch', '15.6 Inch', '16 Inch', '17.3 Inch'],
        colorsDefault: 'Space Grey, Silver, Midnight Black', suggestedPrice: 34999, suggestedMrp: 49999,
        sampleTitle: 'HP 15s Intel Core i5 12th Gen Thin & Light FHD Laptop (16GB RAM / 512GB SSD)',
        sampleDesc: 'High-performance ultra-slim laptop powered by Intel Core i5 processor, vivid anti-glare FHD micro-edge display, fast PCIe NVMe SSD, backlit keyboard, long-lasting battery backup, and Windows 11 Home.'
      },
      'tv': {
        name: 'Smart TV', icon: '📺', badge: '32", 43", 55"', group: 'computing',
        keywords: 'tv television led 4k smart google bezel display 32 43 55 inch samsung lg mi sony',
        label: 'Bezel & Display Panel', fabricPlaceholder: 'e.g. Bezel-less Metal, 4K LED Display Panel',
        fabricDefault: 'Bezel-less Metal & 4K LED Panel', defaultSizes: '32 Inch, 43 Inch, 55 Inch',
        sizesHint: 'TV Screen Sizes', chips: ['24 Inch', '32 Inch', '40 Inch', '43 Inch', '50 Inch', '55 Inch', '65 Inch'],
        colorsDefault: 'Piano Black', suggestedPrice: 13999, suggestedMrp: 24999,
        sampleTitle: 'Mi 4K Ultra HD Dolby Audio Smart Google LED TV with Bezel-less Design',
        sampleDesc: 'Cinematic Smart Google TV with vivid 4K Ultra HD panel, HDR10+, 24W Dolby Audio stereo speakers, PatchWall integration, built-in Chromecast, and dual-band Wi-Fi.'
      },
      'monitor': {
        name: 'PC Monitor', icon: '🖥️', badge: '22", 24", 27"', group: 'computing',
        keywords: 'monitor display screen pc ips fhd 2k 4k curved gaming 22 24 27 inch 165hz lg samsung',
        label: 'Display Panel & Stand', fabricPlaceholder: 'e.g. IPS Panel & Ergonomic Tilt Stand',
        fabricDefault: 'IPS Panel & Ergonomic Tilt Stand', defaultSizes: '22 Inch, 24 Inch, 27 Inch',
        sizesHint: 'Monitor Screen Sizes', chips: ['21.5 Inch', '22 Inch', '24 Inch', '27 Inch', '32 Inch', 'Curved 34 Inch'],
        colorsDefault: 'Matte Black, Gunmetal Grey', suggestedPrice: 8499, suggestedMrp: 14999,
        sampleTitle: 'LG UltraGear 24-inch 165Hz IPS Gaming Monitor with 1ms AMD FreeSync',
        sampleDesc: 'Crisp Full HD IPS computer monitor engineered with 165Hz ultra-smooth refresh rate, 1ms MBR response time, 99% sRGB color accuracy, 3-side virtually borderless design, and HDMI/DisplayPort.'
      },
      'desktop': {
        name: 'Desktop PC', icon: '🖥️', badge: 'AIO / Tower', group: 'computing',
        keywords: 'desktop pc computer all-in-one aio tower cpu core i5 i7 ryzen lenovo hp',
        label: 'Cabinet & Display Housing', fabricPlaceholder: 'e.g. Brushed Aluminum & Slim Bezel Housing',
        fabricDefault: 'Brushed Aluminum & Slim Bezel Housing', defaultSizes: '8GB / 512GB SSD, 16GB / 1TB SSD',
        sizesHint: 'RAM & Storage Configurations', chips: ['8GB / 512GB SSD', '16GB / 512GB SSD', '16GB / 1TB SSD', '32GB / 1TB SSD'],
        colorsDefault: 'Arctic White, Shadow Black', suggestedPrice: 38999, suggestedMrp: 54999,
        sampleTitle: 'Lenovo IdeaCentre 24-inch Core i5 All-in-One Desktop PC with Wireless KB & Mouse',
        sampleDesc: 'Space-saving All-in-One desktop PC featuring high-performance Intel Core i5 processor, vibrant 23.8-inch FHD anti-glare display, dual Harman Kardon tuned speakers, 5MP IR webcam, and bundled wireless keyboard and mouse.'
      },
      'tablet': {
        name: 'Tablet / iPad', icon: '📱', badge: '64GB, 128GB', group: 'computing',
        keywords: 'tablet ipad android pad wifi cellular 64gb 128gb 256gb tab apple samsung',
        label: 'Body & Enclosure', fabricPlaceholder: 'e.g. 100% Recycled Aluminum Enclosure',
        fabricDefault: '100% Recycled Aluminum Enclosure', defaultSizes: '64 GB, 128 GB, 256 GB',
        sizesHint: 'Storage Capacity', chips: ['64 GB', '128 GB', '256 GB', '512 GB', 'Wi-Fi + Cellular'],
        colorsDefault: 'Silver, Sky Blue, Rose Pink, Space Grey', suggestedPrice: 26999, suggestedMrp: 36999,
        sampleTitle: 'Apple iPad 10th Gen 10.9-inch Liquid Retina Display Wi-Fi Tablet',
        sampleDesc: 'Versatile everyday tablet featuring stunning 10.9-inch Liquid Retina display with True Tone, powerful A14 Bionic chip, landscape 12MP Ultra-Wide camera with Center Stage, USB-C connectivity, and all-day battery.'
      },
      'storage': {
        name: 'Pen Drive & SSD', icon: '💾', badge: '64GB, 128GB, 1TB', group: 'computing',
        keywords: 'pendrive pen drive flash ssd nvme storage memory card micro sd usb type-c 64gb 128gb 256gb 1tb sandisk',
        label: 'Casing & Flash Memory', fabricPlaceholder: 'e.g. Metallic Casing & 3D NAND Flash',
        fabricDefault: 'Metallic Casing & 3D NAND Flash', defaultSizes: '64 GB, 128 GB, 256 GB',
        sizesHint: 'Storage Capacity', chips: ['32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB', '2 TB'],
        colorsDefault: 'Silver, Titanium Grey, Midnight Black', suggestedPrice: 699, suggestedMrp: 1499,
        sampleTitle: 'SanDisk Ultra Dual Drive Go 128GB USB Type-C & Type-A High-Speed Flash Drive',
        sampleDesc: 'Reversible 2-in-1 flash drive with USB Type-C and traditional Type-A connectors. Effortlessly move files between USB Type-C smartphones, tablets, Macs, and Type-A computers with up to 150MB/s read speeds.'
      },
      'printer': {
        name: 'WiFi Printer', icon: '🖨️', badge: 'Ink Tank, Laser', group: 'computing',
        keywords: 'printer inktank inkjet scanner laser all in one wifi duplex canon hp epson brother',
        label: 'Housing & Print Head', fabricPlaceholder: 'e.g. Durable ABS Plastic & Precision Printhead',
        fabricDefault: 'Durable ABS Plastic & Precision Printhead', defaultSizes: 'Ink Tank All-in-One, Laser Monochrome',
        sizesHint: 'Printer Types & Functions', chips: ['Ink Tank All-in-One', 'Color Inkjet', 'Laser Monochrome', 'Duplex WiFi'],
        colorsDefault: 'Matte Black, Crisp White', suggestedPrice: 10499, suggestedMrp: 15999,
        sampleTitle: 'Canon PIXMA MegaTank G3010 All-in-One WiFi Color Ink Tank Printer',
        sampleDesc: 'High-volume color ink tank printer engineered with integrated ink tanks, wireless WiFi direct mobile printing, flatbed scanner, borderless photo printing, and low-cost page printing yield.'
      },
      'headphone': {
        name: 'Headphones', icon: '🎧', badge: 'Standard, ANC', group: 'audio',
        keywords: 'headphone over-ear headset wireless anc bluetooth bass audio sony boat jbl bose',
        label: 'Earcup & Build Material', fabricPlaceholder: 'e.g. Cushioned Leatherette, Matte ABS',
        fabricDefault: 'Cushioned Leatherette & Matte ABS', defaultSizes: 'Standard, Over-Ear Fit',
        sizesHint: 'Headphone Fit & Type', chips: ['Standard', 'Over-Ear Fit', 'On-Ear Fit', 'Foldable Fit', 'Studio Monitor'],
        colorsDefault: 'Midnight Black, Army Green, Silver', suggestedPrice: 1999, suggestedMrp: 4499,
        sampleTitle: 'Sony WH-CH720N Wireless Over-Ear Active Noise Cancelling Headphones',
        sampleDesc: 'Lightweight wireless noise-cancelling headphones equipped with Integrated Processor V1, up to 35 hours battery life, multipoint Bluetooth connection, crystal-clear hands-free calls, and deep punchy bass.'
      },
      'earbuds': {
        name: 'TWS Earbuds', icon: '🎵', badge: 'Standard, Pro ANC', group: 'audio',
        keywords: 'earbuds tws airpods airpod earphone bluetooth wireless in-ear boat noise buds realme oneplus',
        label: 'Earbud & Case Housing', fabricPlaceholder: 'e.g. IPX5 Water-Resistant Matte Polycarbonate',
        fabricDefault: 'IPX5 Water-Resistant Matte Polycarbonate', defaultSizes: 'Standard Fit, Pro ANC Edition',
        sizesHint: 'Earbud Edition / Fit', chips: ['Standard Fit', 'Pro ANC Edition', 'Quad Mic Edition', 'Gaming Low Latency'],
        colorsDefault: 'Carbon Black, Bold Blue, Pure White', suggestedPrice: 999, suggestedMrp: 2990,
        sampleTitle: 'boAt Airdopes 141 True Wireless Bluetooth Earbuds with 42H Playtime & ENx Mic',
        sampleDesc: 'Ergonomic TWS Bluetooth earbuds delivering signature stereo sound, quad microphones with ENx noise cancellation for clear voice calls, Beast mode low-latency for gaming, and ASAP fast charge.'
      },
      'neckband': {
        name: 'Wireless Neckband', icon: '📿', badge: 'Bass Ed., 40H', group: 'audio',
        keywords: 'neckband earphone wireless magnetic bluetooth fast charging bass oneplus boat realme',
        label: 'Neckband Band & Cable', fabricPlaceholder: 'e.g. Skin-Friendly Silicone & Magnetic Earbuds',
        fabricDefault: 'Skin-Friendly Silicone & Magnetic Earbuds', defaultSizes: 'Standard Fit, Bass Edition',
        sizesHint: 'Model Variant', chips: ['Standard Fit', 'Bass Edition', 'ANC Edition', 'Long Playtime 40H'],
        colorsDefault: 'Acoustic Red, Magico Black, Beam Blue', suggestedPrice: 1299, suggestedMrp: 2299,
        sampleTitle: 'OnePlus Bullets Wireless Z2 Bluetooth Magnetic Neckband with Fast Charge (30H)',
        sampleDesc: 'Comfortable wireless magnetic neckband with 12.4mm dynamic bass drivers, 10-minute ultra-fast charge for 20 hours playback, anti-sweat IP55 water resistance, and magnetic instant connect/pause.'
      },
      'speaker': {
        name: 'BT Speaker', icon: '🔊', badge: '10W, 20W, 40W', group: 'audio',
        keywords: 'speaker bluetooth portable wireless bass party audio outdoor jbl boom boat sony',
        label: 'Enclosure & Grille', fabricPlaceholder: 'e.g. Rugged Waterproof Fabric & Rubber Housing',
        fabricDefault: 'Rugged Waterproof Fabric & Tough Rubber Housing', defaultSizes: '10W Portable, 20W Boom, 40W Party',
        sizesHint: 'Audio Output Power', chips: ['5W Mini', '10W Portable', '16W Dual', '20W Boom', '40W Party', '60W Bass'],
        colorsDefault: 'Squad Camo, Ocean Blue, Black, Red, Grey', suggestedPrice: 1499, suggestedMrp: 3499,
        sampleTitle: 'JBL Flip 6 Portable Waterproof Bluetooth Speaker with Bold Bass & 12H Battery',
        sampleDesc: 'Rugged outdoor waterproof Bluetooth speaker engineered with 2-way speaker system, racetrack woofer, separate tweeter, dual passive radiators, IP67 dust/waterproof rating, and PartyBoost pairing.'
      },
      'soundbar': {
        name: 'Soundbar 5.1', icon: '📻', badge: '60W, 120W, 240W', group: 'audio',
        keywords: 'soundbar sound bar home theater subwoofer dolby atmos 2.1 5.1 channel surround zebronics boat',
        label: 'Soundbar Cabinet & Grille', fabricPlaceholder: 'e.g. Polished Metal Grille & Wooden Subwoofer',
        fabricDefault: 'Polished Metal Grille & Wooden Subwoofer Box', defaultSizes: '60W (2.1 Ch), 120W (2.1 Ch), 240W (5.1 Ch)',
        sizesHint: 'Audio Output & Channels', chips: ['40W 2.0', '60W 2.1', '100W 2.1', '120W 2.1', '240W 5.1', '525W Dolby'],
        colorsDefault: 'Glossy Black, Titanium Grey', suggestedPrice: 4999, suggestedMrp: 11999,
        sampleTitle: 'Zebronics Juke Bar 9500 5.1 Dolby Atmos Soundbar with Wireless Subwoofer (240W)',
        sampleDesc: 'Cinematic 5.1 channel surround home theater soundbar system featuring dedicated wireless subwoofer, dual rear satellite surround speakers, HDMI ARC, Optical input, Bluetooth 5.0, and LED display.'
      },
      'mic': {
        name: 'Studio/Podcast Mic', icon: '🎙️', badge: 'USB, Boom Stand', group: 'audio',
        keywords: 'microphone mic podcast streaming condenser studio usb recording rgb fifine boya',
        label: 'Microphone Capsule & Body', fabricPlaceholder: 'e.g. Metal Body & Shock Mount with Pop Filter',
        fabricDefault: 'Metal Body & Shock Mount with Pop Filter', defaultSizes: 'Standard USB, With Boom Arm Stand',
        sizesHint: 'Mic Setup / Stand Type', chips: ['Standard USB', 'Desktop Tripod', 'With Boom Arm Stand', 'RGB Streaming'],
        colorsDefault: 'Midnight Black, Glacier White, Rose Pink', suggestedPrice: 2199, suggestedMrp: 4999,
        sampleTitle: 'Fifine AmpliGame USB Condenser Gaming & Podcast Microphone with RGB & Pop Filter',
        sampleDesc: 'Professional studio cardioid condenser microphone with gradient RGB lighting, tap-to-mute sensor, zero-latency headphone monitoring jack, integrated shock mount, and plug-and-play USB connection.'
      },
      'smartphone': {
        name: 'Smartphone', icon: '📱', badge: '128GB, 256GB', group: 'mobile',
        keywords: 'smartphone mobile phone android 5g amoled oneplus samsung 128gb 256gb redmi realme xiaomi',
        label: 'Body / Frame Material', fabricPlaceholder: 'e.g. Gorilla Glass, Aluminum Frame',
        fabricDefault: 'Gorilla Glass & Aluminum Frame', defaultSizes: '128 GB, 256 GB',
        sizesHint: 'Storage Capacity', chips: ['64 GB', '128 GB', '256 GB', '512 GB', '1 TB'],
        colorsDefault: 'Phantom Black, Titanium Silver, Blue', suggestedPrice: 17499, suggestedMrp: 22999,
        sampleTitle: 'OnePlus Nord CE 3 Lite 5G Smartphone (108MP Camera, 67W SUPERVOOC, 120Hz)',
        sampleDesc: 'Super-fast 5G smartphone equipped with 108MP high-resolution camera, 67W SUPERVOOC rapid fast charging, 5000mAh battery, 120Hz smooth FHD+ display, dual stereo speakers, and Snapdragon processor.'
      },
      'smartwatch': {
        name: 'Smartwatch', icon: '⌚', badge: '40mm, 44mm', group: 'mobile',
        keywords: 'smartwatch smart watch fitness band dial calling 40mm 44mm amoled noise boat fireboltt',
        label: 'Dial Case & Strap Material', fabricPlaceholder: 'e.g. Metallic Zinc Alloy, Silicone Strap',
        fabricDefault: 'Metallic Zinc Alloy & Silicone Strap', defaultSizes: '40mm, 44mm',
        sizesHint: 'Dial Case Sizes', chips: ['38mm', '40mm', '42mm', '44mm', '46mm', '49mm Ultra'],
        colorsDefault: 'Jet Black, Rose Gold, Deep Blue', suggestedPrice: 1499, suggestedMrp: 4999,
        sampleTitle: 'Fire-Boltt Phoenix Pro 1.39" Bluetooth Calling Smartwatch with AI Voice & SpO2',
        sampleDesc: 'Sleek luxury metal smartwatch with Bluetooth calling, 1.39-inch HD display, 120+ active sports modes, continuous heart rate and blood oxygen monitoring, smartphone notifications, and IP67 water resistance.'
      },
      'powerbank': {
        name: 'Power Bank', icon: '🔋', badge: '10k, 20k, 30k mAh', group: 'mobile',
        keywords: 'powerbank power bank battery fast charge mah 10000 20000 30000 pd mi ambrane realme',
        label: 'Battery Cells & Housing', fabricPlaceholder: 'e.g. High-Density Li-Po & Anodized Metal Shell',
        fabricDefault: 'High-Density Li-Po & Anodized Metal Shell', defaultSizes: '10000 mAh, 20000 mAh',
        sizesHint: 'Battery Capacity', chips: ['5000 mAh', '10000 mAh', '20000 mAh', '30000 mAh', '65W Laptop PD'],
        colorsDefault: 'Carbon Black, Navy Blue, Metallic Grey', suggestedPrice: 1199, suggestedMrp: 2199,
        sampleTitle: 'Mi 3i 20000mAh 18W Fast Charging Power Bank with Triple Output Ports',
        sampleDesc: 'High-capacity lithium-polymer portable power bank featuring 18W fast charge, dual input (Type-C & Micro-USB), triple output ports, 12-layer advanced circuit protection, and smart low power charging mode.'
      },
      'charger': {
        name: 'GaN Fast Charger', icon: '⚡', badge: '20W, 33W, 65W', group: 'mobile',
        keywords: 'charger gan fast charging type-c adapter pd 20w 33w 65w 100w vooc dash stuffcool',
        label: 'Semiconductor & Casing', fabricPlaceholder: 'e.g. Gallium Nitride (GaN) & Flame Retardant PC',
        fabricDefault: 'Gallium Nitride (GaN) & Flame Retardant PC', defaultSizes: '20W PD, 33W GaN, 65W GaN',
        sizesHint: 'Power Output Wattage', chips: ['20W PD', '33W GaN', '45W GaN', '65W GaN', '100W GaN', '120W Ultra'],
        colorsDefault: 'Arctic White, Sleek Black', suggestedPrice: 799, suggestedMrp: 1999,
        sampleTitle: 'Stuffcool 65W Dual Port GaN Fast Wall Charger Adapter (Type-C PD + USB-A)',
        sampleDesc: 'Ultra-compact next-gen GaN fast wall charger supporting Power Delivery (PD 3.0) and Quick Charge 3.0. Fast charges laptops, MacBooks, tablets, and flagship smartphones at maximum speed.'
      },
      'cable': {
        name: 'Fast Cable', icon: '🔌', badge: 'Type-C 100W, 1.5m', group: 'mobile',
        keywords: 'cable data cable charging cord type-c lightning 65w 100w braided nylon 1m 2m ambrane',
        label: 'Braiding & Core Wire', fabricPlaceholder: 'e.g. Military-Grade Nylon Braided & Copper Core',
        fabricDefault: 'Military-Grade Nylon Braided & 100% Copper Core', defaultSizes: '1 Meter, 1.5 Meter, 2 Meter',
        sizesHint: 'Cable Length', chips: ['0.5 Meter', '1 Meter', '1.2 Meter', '1.5 Meter', '2 Meter', '3 Meter'],
        colorsDefault: 'Midnight Black, Crimson Red, Metallic Silver', suggestedPrice: 199, suggestedMrp: 699,
        sampleTitle: 'Ambrane 100W 6A Fast Charging Type-C to Type-C Braided Cable (1.5m)',
        sampleDesc: 'Heavy-duty braided Type-C fast charging cable capable of delivering up to 100W PD power, 480Mbps high-speed data sync, reinforced strain-relief joints, and 15,000+ bend lifespan.'
      },
      'camera': {
        name: 'Action Camera', icon: '📷', badge: '1080p, 4K HD', group: 'smart_home',
        keywords: 'camera action camera 4k dashcam dash cam waterproof gopro vlog 1080p sjcam dji',
        label: 'Camera Housing & Lens', fabricPlaceholder: 'e.g. Tough Polycarbonate & 6-Glass Lens',
        fabricDefault: 'Tough Polycarbonate & 6-Glass Aspherical Lens', defaultSizes: '1080p FHD, 4K Ultra HD',
        sizesHint: 'Video Recording Resolution', chips: ['720p HD', '1080p FHD', '2.7K HD', '4K Ultra HD', '4K 60FPS Pro'],
        colorsDefault: 'Jet Black, Cool White', suggestedPrice: 3499, suggestedMrp: 7999,
        sampleTitle: 'SJCAM C300 4K 60FPS Action Camera with 30M Waterproof Case & Dual Screens',
        sampleDesc: 'Pocket action camera and bike dashcam with 4K 60FPS video recording, 6-axis gyro stabilization, dual touch screens, 30-meter waterproof enclosure, handheld remote, and WiFi app control.'
      },
      'cctv': {
        name: '360° CCTV Camera', icon: '📹', badge: '1080p, 2K QHD', group: 'smart_home',
        keywords: 'cctv security camera wifi smart home night vision 360 outdoor pan tilt tapo mi cp plus',
        label: 'Body & Sensor Housing', fabricPlaceholder: 'e.g. Weather-Proof Polycarbonate & Optical Glass',
        fabricDefault: 'Weather-Proof Polycarbonate & Optical Glass', defaultSizes: '1080p Full HD, 2K QHD (3MP)',
        sizesHint: 'Camera Resolution', chips: ['1080p Full HD', '2K QHD (3MP)', '4MP Ultra HD', 'Solar Battery Edition'],
        colorsDefault: 'Pure White & Black Accent', suggestedPrice: 1899, suggestedMrp: 3299,
        sampleTitle: 'TP-Link Tapo C200 360° Pan/Tilt WiFi Home Security Camera with Night Vision',
        sampleDesc: 'Smart home indoor WiFi security camera with 360° horizontal and 114° vertical coverage, advanced infrared night vision up to 30 feet, sound and light alarm, two-way audio talk, and microSD storage up to 512GB.'
      },
      'projector': {
        name: 'Smart Projector', icon: '📽️', badge: '720p, 1080p, 4K', group: 'smart_home',
        keywords: 'projector smart home cinema android 1080p 4k led beamer lumens home theater egate zebronics',
        label: 'Optical Engine & Housing', fabricPlaceholder: 'e.g. Sealed Optical Engine & Matte ABS',
        fabricDefault: 'Sealed Optical Engine & Matte ABS Enclosure', defaultSizes: '720p HD, 1080p Native FHD, 4K Support',
        sizesHint: 'Projection Resolution', chips: ['480p Portable', '720p HD', '1080p Native FHD', '4K HDR Smart Edition'],
        colorsDefault: 'Moonlight White, Space Black', suggestedPrice: 7499, suggestedMrp: 16990,
        sampleTitle: 'Egate O9 Pro Full HD 1080p Smart LED Android Home Projector (6000 Lumens)',
        sampleDesc: 'Cinematic LED home theater projector with native 1080p Full HD resolution, 6000 lumens high brightness, Android smart OS, pre-installed Netflix and Prime Video, electronic keystone correction, and up to 200-inch screen display.'
      },
      'router': {
        name: 'WiFi-6 Router', icon: '📡', badge: 'AX1500, AX1800', group: 'smart_home',
        keywords: 'router wifi modem gigabit ac1200 ax1800 mesh dual band tp-link d-link tenda',
        label: 'Antennas & Casing', fabricPlaceholder: 'e.g. High-Gain 4-Antenna Array & Ventilated Shell',
        fabricDefault: 'High-Gain 4-Antenna Array & Ventilated Plastic Shell', defaultSizes: 'AC1200 Dual-Band, AX1500 WiFi 6, AX1800 Gigabit',
        sizesHint: 'Speed & Standard', chips: ['N300 Single', 'AC1200 Dual-Band', 'AX1500 WiFi 6', 'AX1800 Gigabit', 'AX3000 Mesh'],
        colorsDefault: 'Obsidian Black, Modern White', suggestedPrice: 2299, suggestedMrp: 4499,
        sampleTitle: 'TP-Link Archer AX12 WiFi 6 Next-Gen Gigabit Dual-Band Router (1.5 Gbps)',
        sampleDesc: 'Next-generation WiFi 6 gigabit router delivering lightning speeds up to 1.5 Gbps, revolutionary OFDMA and MU-MIMO technology for 30+ devices, 4 high-gain antennas with beamforming, and WPA3 security.'
      },
      'smart_light': {
        name: 'Smart WiFi Bulb', icon: '💡', badge: '9W, 12W, 16M Color', group: 'smart_home',
        keywords: 'smart bulb plug led light alexa google home rgb wifi 9w 12w 16a wipro philips',
        label: 'Diffuser & Base', fabricPlaceholder: 'e.g. Polycarbonate Diffuser & Aluminium Heat Sink',
        fabricDefault: 'Polycarbonate Diffuser & Aluminium Heat Sink Base', defaultSizes: '9W (810 Lm), 12W (1050 Lm), 16W (Smart Plug Combo)',
        sizesHint: 'Bulb Wattage / Lumens', chips: ['7W (B22)', '9W (B22)', '12W (B22)', '16A Smart Plug', '2-Pack Combo'],
        colorsDefault: '16 Million Colors RGB, Warm White, Cool Day White', suggestedPrice: 499, suggestedMrp: 1290,
        sampleTitle: 'Wipro Next 12W B22 Smart WiFi Color Changing LED Bulb (Works with Alexa/Google)',
        sampleDesc: 'Smart WiFi LED bulb featuring 16 million customizable RGB colors, tunable warm-to-cool white tones, timer scheduling, group control, and hands-free voice control via Amazon Alexa and Google Assistant.'
      },
      'gaming': {
        name: 'Gaming Gamepad', icon: '🎮', badge: 'Wireless, Hall Pro', group: 'gadgets',
        keywords: 'gaming controller gamepad joystick wireless bluetooth pc android ps5 xbox triggers evofox redgear',
        label: 'Grip & Thumbsticks', fabricPlaceholder: 'e.g. Textured Grips & Hall-Effect Joysticks',
        fabricDefault: 'Textured Grips & Hall-Effect Magnetic Joysticks', defaultSizes: 'Standard Wireless, Pro Hall-Effect Edition',
        sizesHint: 'Controller Edition', chips: ['Wired USB', 'Standard Wireless', 'Pro Hall-Effect Edition', 'Dual Vibration RGB'],
        colorsDefault: 'Stealth Black, Camo Blue, Neon Red', suggestedPrice: 1499, suggestedMrp: 2999,
        sampleTitle: 'EvoFox Elite X Wireless Gamepad Controller for PC, Android & PS3',
        sampleDesc: 'Precision wireless gaming controller with dual rumble feedback vibration motors, low-latency 2.4GHz wireless & Bluetooth connectivity, anti-drift magnetic joysticks, ergonomic textured grips, and long-lasting rechargeable battery.'
      },
      'keyboard_mouse': {
        name: 'Keyboard & Mouse', icon: '⌨️', badge: 'Wireless, Mech', group: 'gadgets',
        keywords: 'keyboard mouse combo mechanical wireless rgb gaming silent switches logitech ant esports',
        label: 'Switch & Keycap Build', fabricPlaceholder: 'e.g. Outemu Blue Mechanical Switches & ABS',
        fabricDefault: 'Outemu Blue Mechanical Switches & Double-Shot ABS', defaultSizes: 'Wireless Slim Combo, Mechanical RGB Combo',
        sizesHint: 'Combo Hardware Type', chips: ['Wireless Slim Combo', 'Silent Office Combo', 'Tenkeyless (TKL) RGB', 'Full-Size Mechanical RGB'],
        colorsDefault: 'Stealth Black, Retro White, Cyber Pink', suggestedPrice: 1399, suggestedMrp: 3299,
        sampleTitle: 'Ant Esports MK1000 Backlit Mechanical Gaming Keyboard & Mouse Combo',
        sampleDesc: 'Full-size mechanical gaming keyboard with responsive tactile click switches, dynamic multi-color LED backlighting, 100% anti-ghosting keys, and high-precision 3200 DPI ergonomic optical gaming mouse.'
      },
      'trimmer': {
        name: 'Beard Trimmer', icon: '✂️', badge: 'Cordless, 120M', group: 'gadgets',
        keywords: 'trimmer beard hair shaver grooming cordless philips titanium clipper razor mi nova',
        label: 'Blades & Housing', fabricPlaceholder: 'e.g. Self-Sharpening Stainless Steel Blades',
        fabricDefault: 'Self-Sharpening Stainless Steel Blades & Non-Slip Grip', defaultSizes: '60 Min Runtime, 120 Min Fast-Charge Pro',
        sizesHint: 'Battery Runtime / Features', chips: ['45 Min Standard', '60 Min Runtime', '90 Min Titanium', '120 Min Fast-Charge Pro', 'All-in-1 9-Kit'],
        colorsDefault: 'Navy Blue, Matte Black, Champagne Gold', suggestedPrice: 899, suggestedMrp: 1995,
        sampleTitle: 'Philips Series 3000 Cordless Beard & Body Groomer Trimmer with Lift & Trim System',
        sampleDesc: 'Cordless men\'s beard trimmer with 20 lock-in precision length settings (0.5mm to 10mm), skin-friendly rounded blade tips, 60 minutes cordless runtime, USB fast charging with battery level indicator, and washable heads.'
      },
      'ring_light': {
        name: 'Ring Light & Tripod', icon: '💍', badge: '10", 12", 7ft Stand', group: 'gadgets',
        keywords: 'ring light ringlight tripod stand youtube video studio vlogging led 10 12 18 inch digitek',
        label: 'Ring Shell & Tripod', fabricPlaceholder: 'e.g. Aluminium Alloy Tripod & Frosted ABS Ring',
        fabricDefault: 'Heavy-Duty Aluminium Alloy Tripod & Frosted ABS Ring', defaultSizes: '10 Inch (Desktop), 12 Inch (7ft Stand), 18 Inch (Studio Pro)',
        sizesHint: 'Ring Diameter & Stand', chips: ['10 Inch (Desktop)', '12 Inch (7ft Stand)', '14 Inch (7ft Stand)', '18 Inch (Studio Pro)'],
        colorsDefault: '3 Light Modes (Warm, Natural, Cool White)', suggestedPrice: 799, suggestedMrp: 1999,
        sampleTitle: 'DIGITEK 12-inch LED Ring Light with 7ft Extendable Metal Tripod Stand & Phone Holder',
        sampleDesc: 'Professional LED ring light for video recording, YouTube tutorials, vlogging, and live makeup streaming. Offers 3 lighting modes (Warm, Day, Cool White), 10 dimmable brightness levels, 360° ball head, and 7-foot heavy tripod.'
      }
    };

    var currentCategoryKey = 'apparel';
    var currentActiveCategorySlug = 'bags-footwear';
    var lastPresetKey = null;
    window.activeSubtab = 'all';
    window.activeSearchQuery = '';

    // =========================================================================
    // DYNAMIC CATEGORY PRESET RENDERER
    // =========================================================================
    function renderCategoryPresets(catSlug) {
      currentActiveCategorySlug = catSlug;
      var def = categoryDefinitions[catSlug];
      var selectorBox = document.getElementById('category_preset_selector');
      var headerText = document.getElementById('preset_header_text');
      var headerIcon = document.getElementById('preset_header_icon');
      var subtabsContainer = document.getElementById('preset_subtabs_container');
      var presetsContainer = document.getElementById('category_presets_container');
      var searchInput = document.getElementById('preset_search_input');
      var noMatch = document.getElementById('preset_no_match');

      if (!selectorBox || !presetsContainer) return;

      if (!def) {
        selectorBox.style.display = 'none';
        return;
      }

      selectorBox.style.display = 'block';
      if (def.theme) {
        selectorBox.style.background = def.theme.bg || '#fdf4ff';
        selectorBox.style.borderColor = def.theme.border || '#f0abfc';
        if (headerText) {
          headerText.innerText = def.title;
          headerText.style.color = def.theme.titleColor || '#86198f';
        }
        if (headerIcon) {
          headerIcon.className = def.icon || 'fas fa-magic';
          headerIcon.style.color = def.theme.accentColor || '#a855f7';
        }
      }

      // Reset search and tab
      window.activeSubtab = 'all';
      window.activeSearchQuery = '';
      if (searchInput) {
        searchInput.value = '';
        searchInput.placeholder = '🔍 Search ' + def.name + ' presets...';
      }
      if (noMatch) noMatch.style.display = 'none';

      // Render Subtabs
      if (subtabsContainer) {
        subtabsContainer.innerHTML = '';
        if (def.subtabs && def.subtabs.length > 1) {
          subtabsContainer.style.display = 'flex';
          def.subtabs.forEach(function(st, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'category-subtab-btn' + (idx === 0 ? ' active-subtab' : '');
            btn.innerText = st.label;
            btn.style.padding = '3px 10px';
            btn.style.fontSize = '11px';
            btn.style.fontWeight = '700';
            btn.style.borderRadius = '12px';
            btn.style.cursor = 'pointer';
            btn.style.transition = 'all 0.2s';
            if (idx === 0) {
              btn.style.background = def.theme.accentColor || '#0284c7';
              btn.style.color = '#fff';
              btn.style.border = '1px solid ' + (def.theme.accentColor || '#0284c7');
            } else {
              btn.style.background = '#f1f5f9';
              btn.style.color = '#475569';
              btn.style.border = '1px solid #cbd5e1';
            }
            btn.onclick = function() {
              filterCategorySubtab(st.key, this, def.theme.accentColor);
            };
            subtabsContainer.appendChild(btn);
          });
        } else {
          subtabsContainer.style.display = 'none';
        }
      }

      // Render Archetype Pills Grid
      presetsContainer.innerHTML = '';
      if (def.archetypes && def.archetypes.length) {
        def.archetypes.forEach(function(key) {
          var p = categoryPresets[key];
          if (!p) return;

          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'category-pill-btn';
          btn.setAttribute('data-key', key);
          btn.setAttribute('data-group', p.group || '');
          btn.setAttribute('data-keywords', (p.keywords || '') + ' ' + (p.name || '') + ' ' + key);
          btn.style.display = 'inline-flex';
          btn.style.alignItems = 'center';
          btn.style.gap = '5px';
          btn.style.padding = '5px 10px';
          btn.style.borderRadius = '14px';
          btn.style.fontSize = '11.5px';
          btn.style.fontWeight = '700';
          btn.style.cursor = 'pointer';
          btn.style.border = '1px solid ' + (def.theme.border || '#bae6fd');
          btn.style.background = '#fff';
          btn.style.color = def.theme.titleColor || '#0369a1';
          btn.style.transition = 'all 0.2s';

          var badgeBg = def.theme.badgeBg || '#e0f2fe';
          var badgeColor = def.theme.badgeColor || '#0284c7';

          btn.innerHTML = `<span>${p.icon || '🏷️'} ${p.name || key}</span> <span class="pill-badge" style="font-size:9.5px; background:${badgeBg}; color:${badgeColor}; padding:1px 5px; border-radius:8px;">${p.badge || ''}</span>`;

          btn.onclick = function() {
            applyCategoryPreset(key, this);
          };

          presetsContainer.appendChild(btn);
        });
      }
    }

    function filterCategorySubtab(group, btn, activeAccent) {
      window.activeSubtab = group || 'all';
      var tabBtns = document.querySelectorAll('.category-subtab-btn');
      tabBtns.forEach(function(b) {
        b.style.background = '#f1f5f9';
        b.style.color = '#475569';
        b.style.borderColor = '#cbd5e1';
      });
      if (btn) {
        btn.style.background = activeAccent || '#0284c7';
        btn.style.color = '#fff';
        btn.style.borderColor = activeAccent || '#0284c7';
      }
      applyActivePresetFilter();
    }

    function filterActiveCategoryPresets(query) {
      window.activeSearchQuery = (query || '').toLowerCase().trim();
      applyActivePresetFilter();
    }

    function applyActivePresetFilter() {
      var tab = window.activeSubtab || 'all';
      var query = window.activeSearchQuery || '';
      var pills = document.querySelectorAll('.category-pill-btn');
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

      var noMatch = document.getElementById('preset_no_match');
      if (noMatch) {
        noMatch.style.display = visibleCount === 0 ? 'block' : 'none';
      }
    }

    function applyCategoryPreset(key, btn) {
      var def = categoryDefinitions[currentActiveCategorySlug] || categoryDefinitions['electronics'];
      var accent = (def && def.theme && def.theme.accentColor) ? def.theme.accentColor : '#9333ea';

      // Highlight the clicked pill button
      var pills = document.querySelectorAll('.category-pill-btn');
      pills.forEach(function(p) {
        p.style.background = '#fff';
        p.style.color = (def && def.theme && def.theme.titleColor) ? def.theme.titleColor : '#333';
        p.style.borderColor = (def && def.theme && def.theme.border) ? def.theme.border : '#e5e7eb';
        p.style.boxShadow = 'none';
        var badge = p.querySelector('.pill-badge');
        if (badge) {
          badge.style.background = (def && def.theme && def.theme.badgeBg) ? def.theme.badgeBg : '#f3e8ff';
          badge.style.color = (def && def.theme && def.theme.badgeColor) ? def.theme.badgeColor : '#7e22ce';
        }
      });

      if (btn) {
        btn.style.background = accent;
        btn.style.color = '#ffffff';
        btn.style.borderColor = accent;
        btn.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.15)';
        var activeBadge = btn.querySelector('.pill-badge');
        if (activeBadge) {
          activeBadge.style.background = 'rgba(255, 255, 255, 0.3)';
          activeBadge.style.color = '#ffffff';
        }
      }

      var preset = categoryPresets[key];
      if (!preset) return;

      currentCategoryKey = key;
      lastPresetKey = key;

      var titleInp = document.getElementById('product_title_input');
      var priceInp = document.getElementById('base_price_input');
      var mrpInp = document.getElementById('base_mrp_input');
      var fabricLabel = document.getElementById('fabric_label');
      var fabricInp = document.getElementById('fabric_input');
      var sizeHint = document.getElementById('category_size_hint');
      var sizesInp = document.getElementById('sizes_input');
      var colorsInp = document.getElementById('colors_input');
      var descInp = document.querySelector('textarea[name="description"]');

      if (titleInp && preset.sampleTitle) titleInp.value = preset.sampleTitle;
      if (descInp && preset.sampleDesc) descInp.value = preset.sampleDesc;
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

    // Aliases for compatibility
    function applyDevicePreset(type, btn) { applyCategoryPreset(type, btn); }
    function filterDeviceTab(group, btn) { filterCategorySubtab(group, btn); }
    function filterDevicePresets(query) { filterActiveCategoryPresets(query); }

    function getCategorySlugFromSelect() {
      var select = document.getElementById('category_select');
      if (!select) return 'bags-footwear';
      var selectedOpt = select.options[select.selectedIndex];
      var slug = selectedOpt ? (selectedOpt.getAttribute('data-slug') || '') : '';
      var name = selectedOpt ? (selectedOpt.getAttribute('data-name') || '') : '';

      slug = slug.toLowerCase().trim();
      name = name.toLowerCase().trim();

      if (categoryDefinitions[slug]) return slug;

      if (slug.indexOf('bag') !== -1 || slug.indexOf('foot') !== -1 || name.indexOf('footwear') !== -1) return 'bags-footwear';
      if (slug.indexOf('electron') !== -1 || name.indexOf('electron') !== -1) return 'electronics';
      if (slug.indexOf('ethnic') !== -1 || name.indexOf('ethnic') !== -1) return 'women-ethnic';
      if (slug.indexOf('western') !== -1 || name.indexOf('western') !== -1) return 'women-western';
      if (slug === 'men' || name === 'men' || slug.indexOf('men-') === 0 || name.indexOf("men's") !== -1 || name.indexOf('men ') === 0) return 'men';
      if (slug.indexOf('kid') !== -1 || name.indexOf('kid') !== -1) return 'kids';
      if (slug.indexOf('home') !== -1 || slug.indexOf('kitchen') !== -1 || name.indexOf('kitchen') !== -1) return 'home-kitchen';
      if (slug.indexOf('beauty') !== -1 || name.indexOf('beauty') !== -1) return 'beauty-health';
      if (slug.indexOf('jewel') !== -1 || name.indexOf('jewel') !== -1) return 'jewellery-accessories';
      return 'bags-footwear';
    }

    function onTitleOrCategoryChange() {
      var select = document.getElementById('category_select');
      var titleInput = document.getElementById('product_title_input');
      if (!select) return;

      var currentSlug = getCategorySlugFromSelect();

      // If category dropdown changed, re-render the category preset selector!
      if (currentSlug !== currentActiveCategorySlug || !lastPresetKey) {
        renderCategoryPresets(currentSlug);
      }

      // Check if product title matches any archetype keywords
      var titleText = titleInput ? titleInput.value.toLowerCase().trim() : '';
      if (titleText.length >= 3) {
        for (var key in categoryPresets) {
          var p = categoryPresets[key];
          if (!p) continue;
          var kws = (p.keywords || '').split(' ');
          for (var i = 0; i < kws.length; i++) {
            var kw = kws[i].trim().toLowerCase();
            if (kw.length >= 4 && titleText.indexOf(kw) !== -1) {
              // Found matching archetype, update active if not already
              if (currentCategoryKey !== key) {
                currentCategoryKey = key;
                lastPresetKey = key;
                var fabricLabel = document.getElementById('fabric_label');
                var fabricInp = document.getElementById('fabric_input');
                var sizeHint = document.getElementById('category_size_hint');
                var sizesInp = document.getElementById('sizes_input');
                var colorsInp = document.getElementById('colors_input');

                if (fabricLabel) fabricLabel.innerText = p.label;
                if (fabricInp) {
                  fabricInp.placeholder = p.fabricPlaceholder;
                  if (!fabricInp.value || fabricInp.value === 'Pure Cotton') fabricInp.value = p.fabricDefault;
                }
                if (sizeHint) sizeHint.innerText = p.sizesHint;
                if (sizesInp && (!sizesInp.value || sizesInp.value === 'S, M, L, XL, XXL')) sizesInp.value = p.defaultSizes;
                if (colorsInp && (!colorsInp.value || colorsInp.value === 'Red, Navy Blue, Green')) colorsInp.value = p.colorsDefault;

                renderSizeChips();
                updateSizePricingRows();
              }
              return;
            }
          }
        }
      }
    }

    function onCategoryChange() {
      currentCategoryKey = null;
      lastPresetKey = null; // force re-evaluation on category switch
      onTitleOrCategoryChange();
    }

    function renderSizeChips() {
      var container = document.getElementById('size_chips_container');
      var sizesInput = document.getElementById('sizes_input');
      if (!container || !sizesInput) return;

      var preset = categoryPresets[currentCategoryKey];
      if (!preset) {
        var def = categoryDefinitions[currentActiveCategorySlug];
        if (def && def.archetypes && def.archetypes[0]) {
          preset = categoryPresets[def.archetypes[0]];
        }
      }
      if (!preset) preset = categoryPresets['backpack'];
      if (!preset) return;

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
