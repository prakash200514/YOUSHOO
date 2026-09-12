<?php
/**
 * Professional Admin Management System - Executive Dashboard
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_admin();

$pdo = getDBConnection();

// 1. Core Platform Metrics
// GMV
$stmtGMV = $pdo->query("SELECT SUM(final_amount) FROM orders WHERE order_status != 'cancelled'");
$totalGMV = (float)($stmtGMV->fetchColumn() ?: 0);

// Total Orders
$stmtOrders = $pdo->query("SELECT COUNT(*) FROM orders");
$totalOrders = (int)($stmtOrders->fetchColumn() ?: 0);

// Total Suppliers
$stmtSuppliers = $pdo->query("SELECT COUNT(*) FROM suppliers");
$totalSuppliers = (int)($stmtSuppliers->fetchColumn() ?: 0);

// Total Products
$stmtProds = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'approved'");
$totalProducts = (int)($stmtProds->fetchColumn() ?: 0);

// Total Customers
$stmtCust = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$totalCustomers = (int)($stmtCust->fetchColumn() ?: 0);

// 2. Category Distribution
$stmtCats = $pdo->query("SELECT c.name, COUNT(p.id) as prod_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY prod_count DESC");
$categoryStats = $stmtCats->fetchAll();

// 3. Top Suppliers
$stmtTopSuppliers = $pdo->query("SELECT s.*, 
    COUNT(DISTINCT oi.order_id) as orders_count, 
    COALESCE(SUM(oi.total_price), 0) as total_earned
    FROM suppliers s
    LEFT JOIN order_items oi ON s.id = oi.supplier_id
    GROUP BY s.id
    ORDER BY total_earned DESC
    LIMIT 5");
$topSuppliers = $stmtTopSuppliers->fetchAll();

// 4. Recent Platform Orders
$stmtRecentOrders = $pdo->query("SELECT o.*, u.email as user_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.id DESC
    LIMIT 6");
$recentOrders = $stmtRecentOrders->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Control Center | Meesho Admin Suite</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
</head>
<body style="background:#f4f6fa;">

  <div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
      <div class="dash-brand">
        meesho <span style="color:#f43397;">Admin</span>
      </div>

      <div style="padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.08);">
        <div style="font-size:14px; font-weight:700; color:#fff;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Super Admin'); ?></div>
        <div style="font-size:12px; color:#a0a5b9;">Platform Master Access</div>
      </div>

      <ul class="dash-nav">
        <li class="dash-nav-item active"><a href="/MEESHO/admin/index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/products.php"><i class="fas fa-boxes"></i> Catalog & Moderation</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/suppliers.php"><i class="fas fa-store"></i> Supplier Directory</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/categories.php"><i class="fas fa-tags"></i> Category Master</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/orders.php"><i class="fas fa-shopping-cart"></i> Global Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/banners.php"><i class="fas fa-images"></i> Marketing Banners</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Customer Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px;">
        <div>
          <h1 style="font-size:26px; font-weight:800; color:#222;">Meesho Marketplace Overview</h1>
          <p style="font-size:13px; color:#666;">Real-time ecosystem metrics, transaction analytics and catalog control</p>
        </div>
        <div>
          <span style="background:#e6f7f2; color:#038d63; font-size:12px; font-weight:700; padding:6px 14px; border-radius:20px;">
            <i class="fas fa-circle" style="font-size:8px; margin-right:4px;"></i> System Live & Active
          </span>
        </div>
      </div>

      <!-- 5 KPI Cards -->
      <div class="kpi-cards-grid" style="grid-template-columns: repeat(5, 1fr);">
        <div class="kpi-card">
          <div class="kpi-icon purple"><i class="fas fa-rupee-sign"></i></div>
          <div class="kpi-info">
            <h3>₹<?php echo number_format($totalGMV, 0); ?></h3>
            <p>Platform GMV</p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-shopping-bag"></i></div>
          <div class="kpi-info">
            <h3><?php echo $totalOrders; ?></h3>
            <p>Total Orders</p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon green"><i class="fas fa-store"></i></div>
          <div class="kpi-info">
            <h3><?php echo $totalSuppliers; ?></h3>
            <p>Verified Suppliers</p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon orange"><i class="fas fa-tshirt"></i></div>
          <div class="kpi-info">
            <h3><?php echo $totalProducts; ?></h3>
            <p>Active Products</p>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon purple" style="background:#fce7f3; color:#ec4899;"><i class="fas fa-users"></i></div>
          <div class="kpi-info">
            <h3><?php echo $totalCustomers; ?></h3>
            <p>Customers</p>
          </div>
        </div>
      </div>

      <!-- Two-Column Grid: Category Breakdown + Top Suppliers -->
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:28px;">
        <!-- Category Distribution -->
        <div class="table-card" style="margin-bottom:0;">
          <h3 style="font-size:16px; font-weight:700; color:#222; margin-bottom:16px;">Product Catalog by Category</h3>
          <div style="display:flex; flex-direction:column; gap:12px;">
            <?php foreach (array_slice($categoryStats, 0, 6) as $cs): 
              $percent = $totalProducts > 0 ? round(($cs['prod_count'] / $totalProducts) * 100) : 0;
            ?>
              <div>
                <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:4px;">
                  <span><?php echo htmlspecialchars($cs['name']); ?></span>
                  <span><?php echo $cs['prod_count']; ?> items (<?php echo $percent; ?>%)</span>
                </div>
                <div style="background:#edf2f7; height:7px; border-radius:4px; overflow:hidden;">
                  <div style="background:linear-gradient(90deg, #9f2089, #f43397); height:100%; width:<?php echo $percent; ?>%;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Top Performing Suppliers -->
        <div class="table-card" style="margin-bottom:0;">
          <h3 style="font-size:16px; font-weight:700; color:#222; margin-bottom:16px;">Top Wholesale Suppliers</h3>
          <div style="display:flex; flex-direction:column; gap:14px;">
            <?php foreach ($topSuppliers as $ts): ?>
              <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #f1f1f1; padding-bottom:10px;">
                <div>
                  <div style="font-weight:700; font-size:13.5px; color:#333;"><?php echo htmlspecialchars($ts['shop_name']); ?></div>
                  <div style="font-size:11.5px; color:#777;">
                    <?php echo htmlspecialchars($ts['city'] . ', ' . $ts['state']); ?> &bull; 
                    <span style="color:#038d63; font-weight:600;"><i class="fas fa-star"></i> <?php echo number_format($ts['rating'], 1); ?></span>
                  </div>
                </div>
                <div style="text-align:right;">
                  <strong style="color:#9f2089; font-size:14px;">₹<?php echo number_format($ts['total_earned'], 0); ?></strong>
                  <div style="font-size:11px; color:#888;"><?php echo $ts['orders_count']; ?> Orders</div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Recent Platform Orders Table -->
      <div class="table-card">
        <div class="table-header-row">
          <h3 style="font-size:17px; font-weight:700; color:#222;">Recent Platform Transactions</h3>
          <a href="/MEESHO/admin/orders.php" style="font-size:13px; font-weight:600; color:#9f2089;">View All Orders &rarr;</a>
        </div>

        <table class="custom-table">
          <thead>
            <tr>
              <th>Order Ref</th>
              <th>Customer</th>
              <th>Destination</th>
              <th>Final Amount</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $ro): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($ro['order_number']); ?></strong></td>
                <td>
                  <div style="font-weight:600; color:#333;"><?php echo htmlspecialchars($ro['shipping_name']); ?></div>
                  <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($ro['shipping_phone']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($ro['shipping_city'] . ', ' . $ro['shipping_state']); ?></td>
                <td><strong style="color:#038d63;">₹<?php echo number_format($ro['final_amount'], 0); ?></strong></td>
                <td><span style="text-transform:uppercase; font-size:11.5px; font-weight:700; color:#555;"><?php echo $ro['payment_method']; ?></span></td>
                <td>
                  <span class="status-badge <?php echo htmlspecialchars($ro['order_status']); ?>">
                    <?php echo ucfirst($ro['order_status']); ?>
                  </span>
                </td>
                <td style="font-size:12px; color:#777;"><?php echo date('d M Y, h:i A', strtotime($ro['created_at'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>

</body>
</html>
