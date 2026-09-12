<?php
/**
 * Youshoo Admin Portal - Dedicated Secure Access Point
 * Route: http://localhost/MEESHO/admin.php
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

$pdo = getDBConnection();
$error = '';

// If already logged in as admin, send directly to Admin Executive Dashboard
if (is_admin()) {
    header("Location: /MEESHO/admin/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both admin email and security password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_name'] = $admin['full_name'];
            $_SESSION['user_email'] = $admin['email'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_phone'] = $admin['phone'];

            header("Location: /MEESHO/admin/index.php");
            exit;
        } else {
            $error = "Access Denied: Invalid administrator credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Secure Admin Gateway | Youshoo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/MEESHO/assets/css/meesho.css">
  <style>
    body {
      background: radial-gradient(circle at 10% 20%, #1f222e 0%, #14161f 90%);
      color: #fff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
      padding: 20px;
    }
    .admin-login-box {
      background: rgba(36, 40, 54, 0.9);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 40px;
      max-width: 440px;
      width: 100%;
      box-shadow: 0 25px 70px rgba(0, 0, 0, 0.5);
      animation: slideInUp 0.4s ease-out;
    }
    .admin-input {
      width: 100%;
      background: rgba(20, 22, 31, 0.85);
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 8px;
      padding: 12px 14px;
      color: #fff;
      font-size: 14px;
      outline: none;
      transition: all 0.25s ease;
    }
    .admin-input:focus {
      border-color: #f43397;
      box-shadow: 0 0 0 3px rgba(244, 51, 151, 0.25);
    }
  </style>
</head>
<body>

  <div class="admin-login-box">
    <div style="text-align:center; margin-bottom: 28px;">
      <div class="brand-logo" style="justify-content:center; color:#fff; font-size:32px; margin-bottom:8px;">
        youshoo
        <span class="brand-tag" style="background:#f43397;">Admin</span>
      </div>
      <p style="font-size:13px; color:#a0a5b9;">Restricted Area &bull; Executive Operations Console</p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background:rgba(220, 38, 38, 0.25); border:1px solid #dc2626; color:#fca5a5; padding:12px 14px; border-radius:8px; margin-bottom:20px; font-size:13px; font-weight:600;">
        <i class="fas fa-shield-alt" style="margin-right:6px;"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form action="/MEESHO/admin.php" method="POST">
      <div style="margin-bottom:18px;">
        <label style="display:block; font-size:12.5px; font-weight:600; color:#c2c5d1; margin-bottom:6px;">Administrator Email</label>
        <input type="email" name="email" required value="admin@youshoo.com" class="admin-input" autocomplete="username">
      </div>

      <div style="margin-bottom:24px;">
        <label style="display:block; font-size:12.5px; font-weight:600; color:#c2c5d1; margin-bottom:6px;">Security Password</label>
        <input type="password" name="password" required value="admin123" class="admin-input" autocomplete="current-password">
      </div>

      <button type="submit" class="btn-buy-now" style="width:100%; padding:13px; font-size:15px; background:linear-gradient(90deg, #9f2089, #f43397); border:none; cursor:pointer;">
        <i class="fas fa-lock" style="margin-right:8px;"></i> Authenticate & Enter
      </button>

      <div style="margin-top:20px; padding:12px; background:rgba(255,255,255,0.05); border:1px dashed rgba(255,255,255,0.2); border-radius:8px; text-align:center; font-size:12px; color:#a0a5b9;">
        Authorized Credentials: <strong>admin@youshoo.com</strong> / <strong>admin123</strong>
      </div>

      <div style="text-align:center; margin-top:22px;">
        <a href="/MEESHO/index.php" style="font-size:13px; color:#f43397; text-decoration:none;">&larr; Return to Customer Storefront</a>
      </div>
    </form>
  </div>

</body>
</html>
