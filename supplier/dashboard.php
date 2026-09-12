<?php
/**
 * Supplier Management Hub - Executive Dashboard
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_supplier();

$pdo = getDBConnection();
$supplier = get_current_supplier();

if (!$supplier) {
    echo "Supplier record not found. Please contact admin.";
    exit;
}

$supplierId = $supplier['id'];

// Metrics
// Total Revenue
$stmtRev = $pdo->prepare("SELECT SUM(oi.total_price) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.supplier_id = ? AND o.order_status != 'cancelled'");
$stmtRev->execute([$supplierId]);
$totalRevenue = (float)($stmtRev->fetchColumn() ?: 0);

// Total Orders
$stmtOrders = $pdo->prepare("SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi WHERE oi.supplier_id = ?");
$stmtOrders->execute([$supplierId]);
$totalOrders = (int)($stmtOrders->fetchColumn() ?: 0);

// Active Products
$stmtProds = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
$stmtProds->execute([$supplierId]);
$totalProducts = (int)($stmtProds->fetchColumn() ?: 0);

// Low Stock Alert
$stmtLow = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ? AND stock < 10");
$stmtLow->execute([$supplierId]);
$lowStockCount = (int)($stmtLow->fetchColumn() ?: 0);

// Recent Orders
$stmtRecent = $pdo->prepare("SELECT oi.*, o.order_number, o.shipping_name, o.shipping_city, o.created_at as order_date, o.payment_method
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.supplier_id = ?
    ORDER BY oi.id DESC
    LIMIT 6");
$stmtRecent->execute([$supplierId]);
$recentOrders = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Supplier Dashboard | <?php echo htmlspecialchars($supplier['shop_name']); ?> | Meesho Hub</title>
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
        <div style="font-size:12px; color:#23bb75;"><i class="fas fa-check-circle"></i> 0% Commission Verified</div>
      </div>

      <ul class="dash-nav">
        <li class="dash-nav-item active"><a href="/MEESHO/supplier/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/supplier/products.php"><i class="fas fa-boxes"></i> Product Catalog</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/supplier/orders.php"><i class="fas fa-shopping-bag"></i> Customer Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/supplier/profile.php"><i class="fas fa-store"></i> Shop Profile</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
      <!-- Top header bar -->
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222;">Supplier Overview</h1>
          <p style="font-size:13px; color:#666;">Manage your wholesale listings, inventory and customer order shipments</p>
        </div>
        <div style="display:flex; gap:12px;">
          <a href="/MEESHO/supplier/products.php?action=new" class="btn-buy-now" style="font-size:14px; padding:10px 20px;">
            <i class="fas fa-plus-circle"></i> Add New Product
          </a>
        </div>
      </div>

      <!-- 4 KPI Cards -->
      <div class="kpi-cards-grid">
        <div class="kpi-card">
          <div class="kpi-icon purple"><i class="fas fa-rupee-sign"></i></div>
          <div class="kpi-info">
            <h3>₹<?php echo number_format($totalRevenue, 0); ?></h3>
            <p>Total Net Revenue</p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-shopping-cart"></i></div>
          <div class="kpi-info">
            <h3><?php echo $totalOrders; ?></h3>
            <p>Orders Received</p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon green"><i class="fas fa-tshirt"></i></div>
          <div class="kpi-info">
            <h3><?php echo $totalProducts; ?></h3>
            <p>Active Catalog Items</p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="kpi-info">
            <h3><?php echo $lowStockCount; ?></h3>
            <p>Low Stock Items</p>
          </div>
        </div>
      </div>

      <!-- Recent Orders Table -->
      <div class="table-card">
        <div class="table-header-row">
          <h3 style="font-size:17px; font-weight:700; color:#222;">Recent Customer Orders</h3>
          <a href="/MEESHO/supplier/orders.php" style="font-size:13px; font-weight:600; color:#9f2089;">View All Orders &rarr;</a>
        </div>

        <?php if (empty($recentOrders)): ?>
          <div style="text-align:center; padding:30px; color:#888;">
            <i class="fas fa-box-open" style="font-size:36px; margin-bottom:10px;"></i>
            <p>No orders received yet. Keep your product catalog fresh and competitive!</p>
          </div>
        <?php else: ?>
          <table class="custom-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Product</th>
                <th>Customer</th>
                <th>Qty</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $ro): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($ro['order_number']); ?></strong></td>
                  <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                      <img src="<?php echo htmlspecialchars($ro['product_image'] ?: 'https://placehold.co/40'); ?>" style="width:36px; height:44px; object-fit:cover; border-radius:4px;">
                      <span style="font-weight:600; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($ro['product_title']); ?></span>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($ro['shipping_name']); ?> (<?php echo htmlspecialchars($ro['shipping_city']); ?>)</td>
                  <td><?php echo $ro['quantity']; ?> (<?php echo htmlspecialchars($ro['size']); ?>)</td>
                  <td><strong>₹<?php echo number_format($ro['total_price'], 0); ?></strong></td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($ro['supplier_status']); ?>">
                      <?php echo ucfirst($ro['supplier_status']); ?>
                    </span>
                  </td>
                  <td>
                    <a href="/MEESHO/supplier/orders.php?order_id=<?php echo $ro['order_id']; ?>" class="nav-link-btn seller-cta" style="padding:4px 10px; font-size:12px; display:inline-block;">
                      Fulfill Order
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </main>
  </div>

</body>
</html>
