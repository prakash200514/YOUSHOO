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

// Fetch Categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
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
      
      <h2 style="font-size: 20px; font-weight: 800; color:#333; margin-bottom: 6px;">List New Product on Youshoo</h2>
      <p style="font-size: 13px; color:#666; margin-bottom: 20px;">Fill in wholesale listing details. 0% Commission applies automatically.</p>

      <form action="/MEESHO/supplier/products.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="create_product" value="1">

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Product Title *</label>
          <input type="text" name="title" required placeholder="e.g. Elegant Floral Georgette Printed Saree with Blouse Piece" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
          <div>
            <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Category *</label>
            <select name="category_id" id="category_select" onchange="onCategoryChange()" required style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none; background:#fff;">
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

        <div style="display:grid; grid-template-columns: 1.25fr 0.75fr; gap:14px; margin-bottom:14px;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <label style="font-size:12.5px; font-weight:700; color:#333;">Available Sizes *</label>
              <span id="category_size_hint" style="font-size:11px; color:#9f2089; font-weight:700;">Apparel Sizes</span>
            </div>
            <input type="text" name="sizes" id="sizes_input" value="S, M, L, XL, XXL" placeholder="e.g. Free Size, Adjustable or S, M, L" required style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13.5px; outline:none;">
            
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

    // Category-Adaptive Presets Configuration
    var categoryPresets = {
      'jewellery-accessories': {
        label: 'Material / Base Metal',
        fabricPlaceholder: 'e.g. Brass, Alloy, Gold Plated, Silver Plated, Kundan',
        fabricDefault: 'Brass & Kundan Stone',
        defaultSizes: 'Free Size, Adjustable',
        sizesHint: 'Jewellery Sizes (No S/M/L)',
        chips: ['Free Size', 'Adjustable', 'One Size', '2.4', '2.6', '2.8', 'Ring 12', 'Ring 14', 'Ring 16'],
        colorsDefault: 'Gold, Silver, Rose Gold'
      },
      'electronics': {
        label: 'Material / Build Quality',
        fabricPlaceholder: 'e.g. ABS Plastic, Silicone, Aluminum Alloy',
        fabricDefault: 'ABS Plastic & Silicone',
        defaultSizes: 'Standard Fit, Free Size',
        sizesHint: 'Electronics / Device Fit',
        chips: ['Standard Fit', 'Free Size', 'One Size', 'Universal Fit', 'Adjustable Strap'],
        colorsDefault: 'Black, White, Blue, Grey'
      },
      'beauty-health': {
        label: 'Formulation / Skin Type',
        fabricPlaceholder: 'e.g. Liquid Matte, Herbal Cream, Gel',
        fabricDefault: 'Herbal Liquid Formula',
        defaultSizes: 'Standard, Free Size',
        sizesHint: 'Volume / Pack Units',
        chips: ['Standard', 'Free Size', '30ml', '50ml', '100ml', 'Pack of 2', 'Pack of 4'],
        colorsDefault: 'Nude Pink, Deep Red, Coral'
      },
      'home-kitchen': {
        label: 'Material / Specifications',
        fabricPlaceholder: 'e.g. SS304 Stainless Steel, Ceramic, Cotton',
        fabricDefault: 'Stainless Steel',
        defaultSizes: 'Standard, Free Size',
        sizesHint: 'Dimensions / Set Count',
        chips: ['Standard', 'Free Size', 'Single', 'Double', 'King Size', 'Set of 1', 'Set of 2', 'Set of 3'],
        colorsDefault: 'Silver, Multi-color, White'
      },
      'bags-footwear': {
        label: 'Material / Upper',
        fabricPlaceholder: 'e.g. PU Leather, Canvas, Mesh, Synthetic',
        fabricDefault: 'Textured PU Leather',
        defaultSizes: 'Free Size, Standard',
        sizesHint: 'Shoe & Bag Sizes',
        chips: ['Free Size', 'Standard', 'IND-5', 'IND-6', 'IND-7', 'IND-8', 'IND-9', 'IND-10'],
        colorsDefault: 'Black, Brown, Tan, Navy Blue'
      },
      'kids': {
        label: 'Fabric / Material',
        fabricPlaceholder: 'e.g. 100% Organic Cotton, Breathable Net',
        fabricDefault: '100% Organic Soft Cotton',
        defaultSizes: '0-6M, 6-12M, 1-2Y, 2-3Y, Free Size',
        sizesHint: 'Kids Age Groups',
        chips: ['0-6 Months', '6-12 Months', '1-2 Years', '2-3 Years', '3-4 Years', '5-6 Years', 'Free Size'],
        colorsDefault: 'Yellow & Navy, Sky Blue, Pink'
      },
      'apparel': {
        label: 'Fabric / Material',
        fabricPlaceholder: 'e.g. Pure Cotton, Jacquard Silk, Georgette',
        fabricDefault: 'Pure Cotton',
        defaultSizes: 'S, M, L, XL, XXL',
        sizesHint: 'Clothing Sizes',
        chips: ['Free Size', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', 'Unstitched'],
        colorsDefault: 'Red, Navy Blue, Green, Black'
      }
    };

    var currentCategoryKey = 'apparel';

    function getCategoryKey(slug, name) {
      slug = (slug || '').toLowerCase();
      name = (name || '').toLowerCase();

      if (slug.indexOf('jewel') !== -1 || name.indexOf('jewel') !== -1) return 'jewellery-accessories';
      if (slug.indexOf('electron') !== -1 || name.indexOf('electron') !== -1) return 'electronics';
      if (slug.indexOf('beauty') !== -1 || name.indexOf('beauty') !== -1) return 'beauty-health';
      if (slug.indexOf('home') !== -1 || slug.indexOf('kitchen') !== -1 || name.indexOf('kitchen') !== -1) return 'home-kitchen';
      if (slug.indexOf('bag') !== -1 || slug.indexOf('foot') !== -1 || name.indexOf('footwear') !== -1) return 'bags-footwear';
      if (slug.indexOf('kid') !== -1 || name.indexOf('kid') !== -1) return 'kids';
      return 'apparel';
    }

    function onCategoryChange() {
      var select = document.getElementById('category_select');
      if (!select) return;

      var selectedOpt = select.options[select.selectedIndex];
      var slug = selectedOpt ? selectedOpt.getAttribute('data-slug') : '';
      var name = selectedOpt ? selectedOpt.getAttribute('data-name') : '';

      currentCategoryKey = getCategoryKey(slug, name);
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

      renderSizeChips();
    }

    function renderSizeChips() {
      var container = document.getElementById('size_chips_container');
      var sizesInput = document.getElementById('sizes_input');
      if (!container || !sizesInput) return;

      var preset = categoryPresets[currentCategoryKey] || categoryPresets['apparel'];
      var currentSizes = sizesInput.value.split(',').map(function(s) { return s.trim().toLowerCase(); }).filter(Boolean);

      container.innerHTML = '';
      preset.chips.forEach(function(chip) {
        var isSelected = currentSizes.indexOf(chip.toLowerCase()) !== -1;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.innerText = (isSelected ? '✓ ' : '+ ') + chip;
        btn.style.padding = '3px 9px';
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
      var lowerChip = chipName.toLowerCase();

      var existingIdx = -1;
      for (var i = 0; i < currentSizes.length; i++) {
        if (currentSizes[i].toLowerCase() === lowerChip) {
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
    }

    // Attach real-time input listener to update chips if user edits manually
    document.addEventListener('DOMContentLoaded', function() {
      var sizesInput = document.getElementById('sizes_input');
      if (sizesInput) {
        sizesInput.addEventListener('input', function() {
          renderSizeChips();
        });
      }
      // Initialize on load
      onCategoryChange();
    });

    // Also run immediately in case DOM is already loaded
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      onCategoryChange();
    }
  </script>

</body>
</html>
