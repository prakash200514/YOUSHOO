<?php
/**
 * Supplier Customer Orders & Fulfillment Hub
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_supplier();

$pdo = getDBConnection();
$supplier = get_current_supplier();
$supplierId = $supplier['id'];
$filterStatus = $_GET['status'] ?? 'all';

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    $validStatuses = ['pending', 'accepted', 'shipped', 'delivered', 'cancelled'];
    if (in_array($newStatus, $validStatuses)) {
        // Update order_items
        $stmtUp = $pdo->prepare("UPDATE order_items SET supplier_status = ? WHERE id = ? AND supplier_id = ?");
        $stmtUp->execute([$newStatus, $itemId, $supplierId]);

        // Also sync overall order_status if relevant
        $stmtOrdId = $pdo->prepare("SELECT order_id FROM order_items WHERE id = ?");
        $stmtOrdId->execute([$itemId]);
        $ordId = $stmtOrdId->fetchColumn();

        if ($ordId) {
            $mappedOrderStatus = [
                'accepted' => 'confirmed',
                'shipped' => 'shipped',
                'delivered' => 'delivered',
                'cancelled' => 'cancelled'
            ][$newStatus] ?? 'placed';

            $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$mappedOrderStatus, $ordId]);
        }

        $msg = "Order item status updated to " . ucfirst($newStatus);
    }
}

// Query Orders for this supplier
$sql = "SELECT oi.*, o.order_number, o.shipping_name, o.shipping_phone, o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_pincode, o.created_at as order_date, o.payment_method, o.order_status
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.supplier_id = ?";
$params = [$supplierId];

if ($filterStatus !== 'all') {
    $sql .= " AND oi.supplier_status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY oi.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Orders | Supplier Hub | Meesho</title>
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
        <li class="dash-nav-item"><a href="/MEESHO/supplier/products.php"><i class="fas fa-boxes"></i> Product Catalog</a></li>
        <li class="dash-nav-item active"><a href="/MEESHO/supplier/orders.php"><i class="fas fa-shopping-bag"></i> Customer Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/supplier/profile.php"><i class="fas fa-store"></i> Shop Profile</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222;">Customer Orders Fulfillment</h1>
          <p style="font-size:13px; color:#666;">Pack, verify shipping addresses, and mark shipments dispatched</p>
        </div>
      </div>

      <?php if (!empty($msg)): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <!-- Filter Tabs -->
      <div style="display:flex; gap:10px; margin-bottom:20px;">
        <a href="/MEESHO/supplier/orders.php?status=all" class="nav-link-btn <?php echo $filterStatus === 'all' ? 'seller-cta' : ''; ?>" style="background:#fff;">All Orders</a>
        <a href="/MEESHO/supplier/orders.php?status=pending" class="nav-link-btn <?php echo $filterStatus === 'pending' ? 'seller-cta' : ''; ?>" style="background:#fff;">Pending</a>
        <a href="/MEESHO/supplier/orders.php?status=accepted" class="nav-link-btn <?php echo $filterStatus === 'accepted' ? 'seller-cta' : ''; ?>" style="background:#fff;">Accepted</a>
        <a href="/MEESHO/supplier/orders.php?status=shipped" class="nav-link-btn <?php echo $filterStatus === 'shipped' ? 'seller-cta' : ''; ?>" style="background:#fff;">Shipped</a>
        <a href="/MEESHO/supplier/orders.php?status=delivered" class="nav-link-btn <?php echo $filterStatus === 'delivered' ? 'seller-cta' : ''; ?>" style="background:#fff;">Delivered</a>
      </div>

      <!-- Orders List -->
      <div class="table-card">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Order Ref</th>
              <th>Product Details</th>
              <th>Customer Address</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Update Status</th>
              <th>Slip</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="7" style="text-align:center; padding:40px; color:#888;">
                  No orders found in this status category.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $ord): ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($ord['order_number']); ?></strong>
                    <div style="font-size:11px; color:#888;"><?php echo date('d M, h:i A', strtotime($ord['order_date'])); ?></div>
                  </td>
                  <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                      <img src="<?php echo htmlspecialchars($ord['product_image'] ?: 'https://placehold.co/40'); ?>" style="width:36px; height:46px; object-fit:cover; border-radius:4px;">
                      <div>
                        <div style="font-weight:600; font-size:13px; max-width:200px;"><?php echo htmlspecialchars($ord['product_title']); ?></div>
                        <div style="font-size:11px; color:#666;">Size: <strong><?php echo htmlspecialchars($ord['size']); ?></strong> | Qty: <strong><?php echo $ord['quantity']; ?></strong></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:13px; font-weight:600; color:#333;"><?php echo htmlspecialchars($ord['shipping_name']); ?></div>
                    <div style="font-size:11.5px; color:#666;"><?php echo htmlspecialchars($ord['shipping_phone']); ?></div>
                    <div style="font-size:11.5px; color:#888; max-width:200px;"><?php echo htmlspecialchars($ord['shipping_address'] . ', ' . $ord['shipping_city'] . ' - ' . $ord['shipping_pincode']); ?></div>
                  </td>
                  <td>
                    <strong style="font-size:15px; color:#038d63;">₹<?php echo number_format($ord['total_price'], 0); ?></strong>
                    <div style="font-size:11px; color:#888;"><?php echo strtoupper($ord['payment_method']); ?></div>
                  </td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($ord['supplier_status']); ?>">
                      <?php echo ucfirst($ord['supplier_status']); ?>
                    </span>
                  </td>
                  <td>
                    <form action="/MEESHO/supplier/orders.php" method="POST" style="display:flex; gap:6px;">
                      <input type="hidden" name="item_id" value="<?php echo $ord['id']; ?>">
                      <input type="hidden" name="update_status" value="1">
                      <select name="new_status" style="padding:6px; font-size:12px; border:1px solid #d5d8de; border-radius:4px; background:#fff;">
                        <option value="pending" <?php echo $ord['supplier_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="accepted" <?php echo $ord['supplier_status'] === 'accepted' ? 'selected' : ''; ?>>Accept Order</option>
                        <option value="shipped" <?php echo $ord['supplier_status'] === 'shipped' ? 'selected' : ''; ?>>Mark Shipped</option>
                        <option value="delivered" <?php echo $ord['supplier_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                      </select>
                      <button type="submit" style="background:#9f2089; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; cursor:pointer;">
                        Save
                      </button>
                    </form>
                  </td>
                  <td>
                    <button onclick="window.print()" style="background:none; border:1px solid #d5d8de; padding:4px 8px; border-radius:4px; font-size:11px; color:#555; cursor:pointer;">
                      <i class="fas fa-print"></i> Slip
                    </button>
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
