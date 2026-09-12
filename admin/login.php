<?php
/**
 * Professional Admin Management System - Secure Login
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$pdo = getDBConnection();
$error = '';

if (is_admin()) {
    header("Location: /MEESHO/admin/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both admin email and password.";
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
            $error = "Invalid admin credentials. Access restricted.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meesho Admin Console - Secure Control Center</title>
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
    }
    .admin-login-box {
      background: rgba(36, 40, 54, 0.85);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      padding: 40px;
      max-width: 420px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
      animation: slideInUp 0.4s ease-out;
    }
    .admin-input {
      width: 100%;
      background: rgba(20, 22, 31, 0.8);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 8px;
      padding: 12px 14px;
      color: #fff;
      font-size: 14px;
      outline: none;
      transition: var(--meesho-transition);
    }
    .admin-input:focus {
      border-color: #f43397;
      box-shadow: 0 0 0 3px rgba(244, 51, 151, 0.2);
    }
  </style>
</head>
<body>

  <div class="admin-login-box">
    <div style="text-align:center; margin-bottom: 28px;">
      <div class="brand-logo" style="justify-content:center; color:#fff; font-size:32px; margin-bottom:8px;">
        meesho
        <span class="brand-tag" style="background:#f43397;">Admin Suite</span>
      </div>
      <p style="font-size:13px; color:#a0a5b9;">Global Platform Management & Analytics</p>
    </div>

    <?php if (!empty($error)): ?>
      <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #dc2626; color:#fca5a5; padding:10px 14px; border-radius:8px; margin-bottom:20px; font-size:13px;">
        <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form action="/MEESHO/admin/login.php" method="POST">
      <div style="margin-bottom:18px;">
        <label style="display:block; font-size:12.5px; font-weight:600; color:#c2c5d1; margin-bottom:6px;">Administrator Email</label>
        <input type="email" name="email" required value="admin@meesho.com" class="admin-input">
      </div>

      <div style="margin-bottom:24px;">
        <label style="display:block; font-size:12.5px; font-weight:600; color:#c2c5d1; margin-bottom:6px;">Security Password</label>
        <input type="password" name="password" required value="admin123" class="admin-input">
      </div>

      <button type="submit" class="btn-buy-now" style="width:100%; padding:13px; font-size:15px; background:linear-gradient(90deg, #9f2089, #f43397);">
        <i class="fas fa-lock" style="margin-right:8px;"></i> Authenticate & Enter
      </button>

      <div style="margin-top:20px; padding:12px; background:rgba(255,255,255,0.05); border:1px dashed rgba(255,255,255,0.2); border-radius:8px; text-align:center; font-size:12px; color:#a0a5b9;">
        Default Credentials: <strong>admin@meesho.com</strong> / <strong>admin123</strong>
      </div>

      <div style="text-align:center; margin-top:20px;">
        <a href="/MEESHO/index.php" style="font-size:13px; color:#f43397;">&larr; Return to Customer Marketplace</a>
      </div>
    </form>
  </div>

</body>
</html>
