<?php
/**
 * Admin Suite - Global Order Management & Master Tracking
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_admin();

$pdo = getDBConnection();
$msg = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['order_status'] ?? '';
    $newPay = $_POST['payment_status'] ?? '';

    $stmtUp = $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?");
    $stmtUp->execute([$newStatus, $newPay, $orderId]);
    $msg = "Order status updated successfully.";
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT o.*, u.email as user_email,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND o.order_status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Global Orders Tracker | Youshoo Admin Suite</title>
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
        <li class="dash-nav-item active"><a href="/MEESHO/admin/orders.php"><i class="fas fa-shopping-cart"></i> Global Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/banners.php"><i class="fas fa-images"></i> Marketing Banners</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Customer Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222;">Global Platform Orders</h1>
          <p style="font-size:13px; color:#666;">Track every customer order across all suppliers, override tracking and payment statuses</p>
        </div>
      </div>

      <?php if (!empty($msg)): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <!-- Filter Controls -->
      <form action="/MEESHO/admin/orders.php" method="GET" style="display:flex; gap:12px; margin-bottom:20px; background:#fff; padding:16px; border-radius:8px; border:1px solid #e6e9ef;">
        <input type="text" name="search" placeholder="Search by Order ID, Customer name or phone..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding:8px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; outline:none;">

        <select name="status" style="padding:8px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; background:#fff; outline:none;">
          <option value="all">All Statuses</option>
          <option value="placed" <?php echo $statusFilter === 'placed' ? 'selected' : ''; ?>>Placed</option>
          <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
          <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
          <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
          <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>

        <button type="submit" class="btn-buy-now" style="padding:8px 18px; font-size:13px;">Filter</button>
      </form>

      <!-- Master Orders Table -->
      <div class="table-card">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer Details</th>
              <th>Shipping Address</th>
              <th>Amount</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Change Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="7" style="text-align:center; padding:40px; color:#888;">No orders found matching filters.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $ord): ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($ord['order_number']); ?></strong>
                    <div style="font-size:11px; color:#777;"><?php echo date('d M Y, h:i A', strtotime($ord['created_at'])); ?></div>
                    <div style="font-size:11px; color:#9f2089; font-weight:600;"><?php echo $ord['items_count']; ?> Items</div>
                  </td>
                  <td>
                    <div style="font-weight:700; color:#333;"><?php echo htmlspecialchars($ord['shipping_name']); ?></div>
                    <div style="font-size:11.5px; color:#666;"><?php echo htmlspecialchars($ord['shipping_phone']); ?></div>
                  </td>
                  <td style="max-width:200px; font-size:12px; color:#555;">
                    <?php echo htmlspecialchars($ord['shipping_address'] . ', ' . $ord['shipping_city'] . ' - ' . $ord['shipping_pincode']); ?>
                  </td>
                  <td>
                    <strong style="color:#038d63; font-size:15px;">₹<?php echo number_format($ord['final_amount'], 0); ?></strong>
                  </td>
                  <td>
                    <span style="font-size:11.5px; font-weight:700; color:#333;"><?php echo strtoupper($ord['payment_method']); ?></span>
                    <div style="font-size:11px; color:<?php echo $ord['payment_status'] === 'paid' ? '#038d63' : '#d97706'; ?>; font-weight:600;">
                      (<?php echo ucfirst($ord['payment_status']); ?>)
                    </div>
                  </td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($ord['order_status']); ?>">
                      <?php echo ucwords(str_replace('_', ' ', $ord['order_status'])); ?>
                    </span>
                  </td>
                  <td>
                    <form action="/MEESHO/admin/orders.php" method="POST" style="display:flex; gap:6px;">
                      <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                      <input type="hidden" name="update_order_status" value="1">
                      
                      <select name="order_status" style="padding:6px; font-size:11.5px; border:1px solid #d5d8de; border-radius:4px; background:#fff;">
                        <option value="placed" <?php echo $ord['order_status'] === 'placed' ? 'selected' : ''; ?>>Placed</option>
                        <option value="confirmed" <?php echo $ord['order_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="shipped" <?php echo $ord['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="out_for_delivery" <?php echo $ord['order_status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                        <option value="delivered" <?php echo $ord['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $ord['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                      </select>

                      <select name="payment_status" style="padding:6px; font-size:11.5px; border:1px solid #d5d8de; border-radius:4px; background:#fff;">
                        <option value="pending" <?php echo $ord['payment_status'] === 'pending' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="paid" <?php echo $ord['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                      </select>

                      <button type="submit" style="background:#9f2089; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; cursor:pointer;">
                        Update
                      </button>
                    </form>
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
