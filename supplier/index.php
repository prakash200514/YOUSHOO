<?php
/**
 * Meesho Supplier Hub - Landing Page & Supplier Auth
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$pdo = getDBConnection();
$error = '';
$success = '';

// If already logged in as supplier
if (is_supplier()) {
    header("Location: /MEESHO/supplier/dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please enter your supplier email and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'supplier'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = 'supplier';
                $_SESSION['user_phone'] = $user['phone'];

                header("Location: /MEESHO/supplier/dashboard.php");
                exit;
            } else {
                $error = "Invalid supplier credentials or account does not exist.";
            }
        }
    } elseif ($action === 'register') {
        $shopName = trim($_POST['shop_name'] ?? '');
        $ownerName = trim($_POST['owner_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $gstin = trim($_POST['gstin'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');

        if (empty($shopName) || empty($ownerName) || empty($email) || empty($phone) || empty($password)) {
            $error = "Please fill in all required registration fields.";
        } else {
            // Check if email taken
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $error = "An account with this email already exists. Please log in.";
            } else {
                // Begin transaction
                $pdo->beginTransaction();
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, 'supplier', 'active')");
                    $stmtUser->execute([$ownerName, $email, $phone, $hash]);
                    $userId = $pdo->lastInsertId();

                    $stmtSupplier = $pdo->prepare("INSERT INTO suppliers 
                        (user_id, shop_name, owner_name, gstin, phone, business_email, address, city, state, pincode, rating, followers_count, verified, commission_rate)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 4.5, 50, 1, 0.00)");
                    $stmtSupplier->execute([$userId, $shopName, $ownerName, $gstin, $phone, $email, $address, $city, $state, $pincode]);

                    $pdo->commit();

                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $ownerName;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'supplier';
                    $_SESSION['user_phone'] = $phone;

                    header("Location: /MEESHO/supplier/dashboard.php");
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Registration error: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Become a Supplier | Sell Online at 0% Commission on Youshoo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
</head>
<body style="background:#f4f6fa;">

  <!-- Header -->
  <header style="background:#ffffff; border-bottom:1px solid #e6e9ef; padding:16px 0;">
    <div class="container" style="display:flex; align-items:center; justify-content:space-between;">
      <a href="/MEESHO/index.php" class="brand-logo">
        youshoo
        <span class="brand-tag" style="background:#038d63;">Supplier Hub</span>
      </a>
      <div style="display:flex; gap:16px; align-items:center;">
        <a href="/MEESHO/index.php" style="font-size:13px; font-weight:600; color:#555;">&larr; Back to Shopping</a>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section style="background: linear-gradient(135deg, #56034c 0%, #9f2089 100%); color:#fff; padding: 50px 0 70px;">
    <div class="container" style="display:grid; grid-template-columns: 1.2fr 1fr; gap:40px; align-items:center;">
      <div>
        <span style="display:inline-block; background:rgba(255,255,255,0.2); padding:4px 14px; border-radius:20px; font-size:13px; font-weight:700; margin-bottom:14px; text-transform:uppercase; letter-spacing:0.5px;">
          🚀 India's #1 Reselling & E-Commerce Network
        </span>
        <h1 style="font-size:38px; font-weight:800; line-height:1.2; margin-bottom:16px;">
          Sell Online to 14 Crore+ Customers at <span style="color:#23bb75;">0% Commission</span>
        </h1>
        <p style="font-size:16px; opacity:0.9; margin-bottom:28px; line-height:1.5;">
          Join 6,00,000+ suppliers who trust Youshoo to grow their offline business online. Enjoy 7-day payment settlement, zero penalty charges, and nationwide delivery.
        </p>
        
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
          <div style="background:rgba(255,255,255,0.12); padding:14px; border-radius:8px;">
            <div style="font-size:24px; font-weight:800; color:#23bb75;">0%</div>
            <div style="font-size:12px; opacity:0.9;">Commission Fee across all product categories</div>
          </div>
          <div style="background:rgba(255,255,255,0.12); padding:14px; border-radius:8px;">
            <div style="font-size:24px; font-weight:800;">7 Days</div>
            <div style="font-size:12px; opacity:0.9;">Fastest Payouts directly into Bank account</div>
          </div>
          <div style="background:rgba(255,255,255,0.12); padding:14px; border-radius:8px;">
            <div style="font-size:24px; font-weight:800;">₹0</div>
            <div style="font-size:12px; opacity:0.9;">Zero penalty on customer returns</div>
          </div>
        </div>
      </div>

      <!-- Auth Form Box -->
      <div style="background:#ffffff; border-radius:16px; padding:32px; color:#333; box-shadow:0 15px 40px rgba(0,0,0,0.2);">
        <?php if (!empty($error)): ?>
          <div style="background:#fee2e2; color:#dc2626; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight:600;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <div style="display:flex; border-bottom:2px solid #f1f1f1; margin-bottom:20px;">
          <button type="button" id="s-login-tab" onclick="switchSupplierTab('login')" style="flex:1; padding:10px; background:none; border:none; font-size:15px; font-weight:700; color:#9f2089; border-bottom:2px solid #9f2089; cursor:pointer;">
            Supplier Login
          </button>
          <button type="button" id="s-reg-tab" onclick="switchSupplierTab('register')" style="flex:1; padding:10px; background:none; border:none; font-size:15px; font-weight:600; color:#888; border-bottom:2px solid transparent; cursor:pointer;">
            Create Shop
          </button>
        </div>

        <!-- Supplier Login -->
        <form action="/MEESHO/supplier/index.php" method="POST" id="supplier-login-form">
          <input type="hidden" name="action" value="login">

          <div style="margin-bottom:16px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Registered Email Address</label>
            <input type="email" name="email" required value="supplier@youshoo.com" style="width:100%; padding:11px 14px; border:1px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
          </div>

          <div style="margin-bottom:20px;">
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Password</label>
            <input type="password" name="password" required value="seller123" style="width:100%; padding:11px 14px; border:1px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
          </div>

          <button type="submit" class="btn-buy-now" style="width:100%; padding:12px; font-size:15px;">
            Access Supplier Dashboard &rarr;
          </button>

          <div style="margin-top:16px; padding:10px; background:#fdfafc; border:1px dashed #9f2089; border-radius:8px; text-align:center; font-size:12px;">
            Demo Supplier: <strong>supplier@youshoo.com</strong> / <strong>seller123</strong>
          </div>
        </form>

        <!-- Supplier Register Form -->
        <form action="/MEESHO/supplier/index.php" method="POST" id="supplier-reg-form" style="display:none; max-height:420px; overflow-y:auto; padding-right:6px;">
          <input type="hidden" name="action" value="register">

          <div style="margin-bottom:12px;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Shop / Store Name *</label>
            <input type="text" name="shop_name" required placeholder="e.g. Royal Jaipur Sarees" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
          </div>

          <div style="margin-bottom:12px;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Owner Full Name *</label>
            <input type="text" name="owner_name" required placeholder="e.g. Rajesh Kumar" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:12px;">
            <div>
              <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Email *</label>
              <input type="email" name="email" required placeholder="shop@example.com" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
            </div>
            <div>
              <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Mobile *</label>
              <input type="tel" name="phone" required placeholder="9820011223" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
            </div>
          </div>

          <div style="margin-bottom:12px;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Create Password *</label>
            <input type="password" name="password" required placeholder="Min 6 characters" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
          </div>

          <div style="margin-bottom:12px;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">GSTIN or Business PAN</label>
            <input type="text" name="gstin" placeholder="24ABCDE1234F1Z5" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
          </div>

          <div style="margin-bottom:12px;">
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Business Address *</label>
            <input type="text" name="address" required placeholder="Shop 12, Wholesale Textile Market" style="width:100%; padding:9px 12px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px; margin-bottom:16px;">
            <div>
              <label style="display:block; font-size:11px; font-weight:600; margin-bottom:4px;">City</label>
              <input type="text" name="city" required value="Surat" style="width:100%; padding:8px; border:1px solid #d5d8de; border-radius:6px; font-size:12px;">
            </div>
            <div>
              <label style="display:block; font-size:11px; font-weight:600; margin-bottom:4px;">State</label>
              <input type="text" name="state" required value="Gujarat" style="width:100%; padding:8px; border:1px solid #d5d8de; border-radius:6px; font-size:12px;">
            </div>
            <div>
              <label style="display:block; font-size:11px; font-weight:600; margin-bottom:4px;">Pincode</label>
              <input type="text" name="pincode" required value="395002" style="width:100%; padding:8px; border:1px solid #d5d8de; border-radius:6px; font-size:12px;">
            </div>
          </div>

          <button type="submit" class="btn-buy-now" style="width:100%; padding:12px; font-size:14px;">
            Register & Open Shop
          </button>
        </form>

      </div>
    </div>
  </section>

  <script>
  function switchSupplierTab(t) {
    const lForm = document.getElementById('supplier-login-form');
    const rForm = document.getElementById('supplier-reg-form');
    const lTab = document.getElementById('s-login-tab');
    const rTab = document.getElementById('s-reg-tab');

    if (t === 'login') {
      lForm.style.display = 'block';
      rForm.style.display = 'none';
      lTab.style.color = '#9f2089';
      lTab.style.borderBottomColor = '#9f2089';
      rTab.style.color = '#888';
      rTab.style.borderBottomColor = 'transparent';
    } else {
      lForm.style.display = 'none';
      rForm.style.display = 'block';
      rTab.style.color = '#9f2089';
      rTab.style.borderBottomColor = '#9f2089';
      lTab.style.color = '#888';
      lTab.style.borderBottomColor = 'transparent';
    }
  }
  </script>

</body>
</html>
