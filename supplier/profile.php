<?php
/**
 * Supplier Shop Profile & Bank Payout Settings
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

require_supplier();

$pdo = getDBConnection();
$supplier = get_current_supplier();
$supplierId = $supplier['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $shopName = trim($_POST['shop_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $bankIfsc = trim($_POST['bank_ifsc'] ?? '');

    $stmtUp = $pdo->prepare("UPDATE suppliers SET 
        shop_name = ?, phone = ?, address = ?, city = ?, state = ?, pincode = ?, bank_account = ?, bank_ifsc = ?
        WHERE id = ?");
    $stmtUp->execute([$shopName, $phone, $address, $city, $state, $pincode, $bankAccount, $bankIfsc, $supplierId]);

    $msg = "Shop profile & bank payout settings updated successfully!";
    $supplier = get_current_supplier(); // Refresh
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop Profile & Payout Settings | Meesho Supplier Hub</title>
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
        <li class="dash-nav-item"><a href="/MEESHO/supplier/orders.php"><i class="fas fa-shopping-bag"></i> Customer Orders</a></li>
        <li class="dash-nav-item active"><a href="/MEESHO/supplier/profile.php"><i class="fas fa-store"></i> Shop Profile</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
      <div style="max-width:760px;">
        <div style="margin-bottom:24px;">
          <h1 style="font-size:24px; font-weight:800; color:#222;">Store Profile & Bank Details</h1>
          <p style="font-size:13px; color:#666;">Set your shop identity and bank account for weekly payout settlements</p>
        </div>

        <?php if (!empty($msg)): ?>
          <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
          </div>
        <?php endif; ?>

        <div class="table-card">
          <form action="/MEESHO/supplier/profile.php" method="POST">
            <input type="hidden" name="save_profile" value="1">

            <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px;">1. Store Identity</h3>
            
            <div style="margin-bottom:14px;">
              <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Shop / Brand Name *</label>
              <input type="text" name="shop_name" required value="<?php echo htmlspecialchars($supplier['shop_name']); ?>" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
              <div>
                <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Contact Phone *</label>
                <input type="tel" name="phone" required value="<?php echo htmlspecialchars($supplier['phone']); ?>" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
              </div>
              <div>
                <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">GSTIN</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($supplier['gstin'] ?: 'Not specified'); ?>" style="width:100%; padding:10px 12px; border:1px solid #e6e9ef; background:#f9fafb; border-radius:6px; font-size:14px; outline:none;">
              </div>
            </div>

            <div style="margin-bottom:14px;">
              <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Business Address *</label>
              <input type="text" name="address" required value="<?php echo htmlspecialchars($supplier['address']); ?>" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; margin-bottom:24px;">
              <div>
                <label style="display:block; font-size:12px; font-weight:700; color:#333; margin-bottom:4px;">City</label>
                <input type="text" name="city" required value="<?php echo htmlspecialchars($supplier['city']); ?>" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
              </div>
              <div>
                <label style="display:block; font-size:12px; font-weight:700; color:#333; margin-bottom:4px;">State</label>
                <input type="text" name="state" required value="<?php echo htmlspecialchars($supplier['state']); ?>" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
              </div>
              <div>
                <label style="display:block; font-size:12px; font-weight:700; color:#333; margin-bottom:4px;">Pincode</label>
                <input type="text" name="pincode" required value="<?php echo htmlspecialchars($supplier['pincode']); ?>" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
              </div>
            </div>

            <h3 style="font-size:16px; font-weight:700; color:#333; margin-bottom:16px; border-top:1px solid #f1f1f1; padding-top:20px;">
              2. Bank Account Details (For 7-day payouts)
            </h3>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:24px;">
              <div>
                <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Account Number</label>
                <input type="text" name="bank_account" placeholder="e.g. 50100234567890" value="<?php echo htmlspecialchars($supplier['bank_account'] ?? '50100456123490'); ?>" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
              </div>
              <div>
                <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">IFSC Code</label>
                <input type="text" name="bank_ifsc" placeholder="e.g. HDFC0001234" value="<?php echo htmlspecialchars($supplier['bank_ifsc'] ?? 'HDFC0000456'); ?>" style="width:100%; padding:10px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:14px; outline:none;">
              </div>
            </div>

            <button type="submit" class="btn-buy-now" style="padding:12px 28px; font-size:14px;">
              Save Profile Changes
            </button>
          </form>
        </div>
      </div>
    </main>
  </div>

</body>
</html>
