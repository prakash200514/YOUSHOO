<?php
/**
 * Admin Suite - Supplier Verification & Directory Management
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_admin();

$pdo = getDBConnection();
$msg = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $supId = (int)$_GET['id'];
    $act = $_GET['action'];

    if ($act === 'verify') {
        $pdo->prepare("UPDATE suppliers SET verified = 1 WHERE id = ?")->execute([$supId]);
        $msg = "Supplier verified successfully.";
    } elseif ($act === 'unverify') {
        $pdo->prepare("UPDATE suppliers SET verified = 0 WHERE id = ?")->execute([$supId]);
        $msg = "Supplier verification badge removed.";
    } elseif ($act === 'toggle_user') {
        // Toggle active/suspended in users table
        $stmtUid = $pdo->prepare("SELECT user_id FROM suppliers WHERE id = ?");
        $stmtUid->execute([$supId]);
        $uid = $stmtUid->fetchColumn();
        if ($uid) {
            $pdo->prepare("UPDATE users SET status = IF(status='active', 'suspended', 'active') WHERE id = ?")->execute([$uid]);
            $msg = "Supplier account status updated.";
        }
    }
}

// Fetch all suppliers
$stmt = $pdo->query("SELECT s.*, u.email as user_email, u.status as account_status,
    (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count,
    (SELECT COALESCE(SUM(total_price), 0) FROM order_items WHERE supplier_id = s.id) as total_sales
    FROM suppliers s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.id DESC");
$suppliers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Supplier Management | Meesho Admin Suite</title>
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
        <li class="dash-nav-item active"><a href="/MEESHO/admin/suppliers.php"><i class="fas fa-store"></i> Supplier Directory</a></li>
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
          <h1 style="font-size:24px; font-weight:800; color:#222;">Supplier Directory & Verification</h1>
          <p style="font-size:13px; color:#666;">Onboard, verify wholesale suppliers and inspect individual shop performance</p>
        </div>
      </div>

      <?php if (!empty($msg)): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <div class="table-card">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Shop Name</th>
              <th>Owner Details</th>
              <th>Location</th>
              <th>GSTIN</th>
              <th>Catalog</th>
              <th>Total Sales</th>
              <th>Account</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($suppliers as $s): ?>
              <tr>
                <td>
                  <strong style="color:#333;"><?php echo htmlspecialchars($s['shop_name']); ?></strong>
                  <div>
                    <?php if ($s['verified']): ?>
                      <span style="color:#038d63; font-size:11px; font-weight:700;"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php else: ?>
                      <span style="color:#f59e0b; font-size:11px; font-weight:700;"><i class="fas fa-clock"></i> Pending Verification</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div style="font-weight:600;"><?php echo htmlspecialchars($s['owner_name']); ?></div>
                  <div style="font-size:11.5px; color:#666;"><?php echo htmlspecialchars($s['user_email']); ?></div>
                  <div style="font-size:11.5px; color:#666;"><?php echo htmlspecialchars($s['phone']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($s['city'] . ', ' . $s['state']); ?></td>
                <td style="font-size:12px; font-family:Consolas, monospace;"><?php echo htmlspecialchars($s['gstin'] ?: 'N/A'); ?></td>
                <td><strong><?php echo $s['product_count']; ?></strong> products</td>
                <td><strong style="color:#038d63;">₹<?php echo number_format($s['total_sales'], 0); ?></strong></td>
                <td>
                  <span class="status-badge <?php echo $s['account_status'] === 'active' ? 'approved' : 'rejected'; ?>">
                    <?php echo ucfirst($s['account_status']); ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex; gap:6px;">
                    <?php if ($s['verified']): ?>
                      <a href="/MEESHO/admin/suppliers.php?action=unverify&id=<?php echo $s['id']; ?>" class="nav-link-btn" style="padding:4px 8px; font-size:11px; color:#888;" title="Remove Badge">Unverify</a>
                    <?php else: ?>
                      <a href="/MEESHO/admin/suppliers.php?action=verify&id=<?php echo $s['id']; ?>" class="nav-link-btn" style="padding:4px 8px; font-size:11px; color:#038d63; font-weight:700;" title="Verify Supplier">Verify</a>
                    <?php endif; ?>
                    <a href="/MEESHO/admin/suppliers.php?action=toggle_user&id=<?php echo $s['id']; ?>" class="nav-link-btn" style="padding:4px 8px; font-size:11px; color:#dc2626;" onclick="return confirm('Change account status?')">
                      <?php echo $s['account_status'] === 'active' ? 'Suspend' : 'Activate'; ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>

</body>
</html>
