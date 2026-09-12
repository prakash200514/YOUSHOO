<?php
/**
 * Customer Authentication (Login & Sign Up)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

$pdo = getDBConnection();
$error = '';
$success = '';

// If already logged in
if (is_logged_in()) {
    if (is_admin()) header("Location: /MEESHO/admin/index.php");
    elseif (is_supplier()) header("Location: /MEESHO/supplier/dashboard.php");
    else header("Location: /MEESHO/index.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please fill in both email and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error = "Your account has been deactivated. Please contact support.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_phone'] = $user['phone'];

                    // Transfer guest cart to logged-in user
                    $sessionId = get_cart_session_id();
                    $pdo->prepare("UPDATE cart SET user_id = ? WHERE session_id = ? AND user_id IS NULL")
                        ->execute([$user['id'], $sessionId]);

                    if ($user['role'] === 'admin') header("Location: /MEESHO/admin/index.php");
                    elseif ($user['role'] === 'supplier') header("Location: /MEESHO/supplier/dashboard.php");
                    else header("Location: /MEESHO/index.php");
                    exit;
                }
            } else {
                $error = "Invalid email address or password.";
            }
        }
    } elseif ($action === 'register') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($fullName) || empty($email) || empty($password)) {
            $error = "Please provide name, email, and password.";
        } else {
            // Check if email already registered
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $error = "Email address already registered. Please log in.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtIns = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, 'customer', 'active')");
                $stmtIns->execute([$fullName, $email, $phone, $hashed]);
                
                $newUserId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'customer';
                $_SESSION['user_phone'] = $phone;

                header("Location: /MEESHO/index.php");
                exit;
            }
        }
    }
}

$pageTitle = "Sign In or Register";
require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content" style="background: linear-gradient(135deg, #fdfafc, #f7ebf5); min-height: 75vh; padding: 40px 0;">
  <div class="container" style="max-width: 460px;">

    <div style="background:#ffffff; border-radius:16px; border:1px solid #e6e9ef; padding:32px; box-shadow:var(--meesho-shadow-md);">
      
      <!-- Top Logo / Title -->
      <div style="text-align:center; margin-bottom:24px;">
        <div class="brand-logo" style="justify-content:center; margin-bottom:6px;">
          meesho
          <span class="brand-tag">User</span>
        </div>
        <p style="font-size:13px; color:#666;">Sign up or log in to view orders & track delivery</p>
      </div>

      <?php if (!empty($error)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:10px 14px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600;">
          <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <!-- Tabs (Login vs Register) -->
      <div style="display:flex; border-bottom:2px solid #f1f1f1; margin-bottom:24px;">
        <button type="button" id="tab-login-btn" onclick="switchAuthTab('login')" style="flex:1; padding:10px; background:none; border:none; font-size:15px; font-weight:700; color:#9f2089; border-bottom:2px solid #9f2089; cursor:pointer;">
          Sign In
        </button>
        <button type="button" id="tab-register-btn" onclick="switchAuthTab('register')" style="flex:1; padding:10px; background:none; border:none; font-size:15px; font-weight:600; color:#888; border-bottom:2px solid transparent; cursor:pointer;">
          Create Account
        </button>
      </div>

      <!-- Login Form -->
      <form action="/MEESHO/auth.php" method="POST" id="login-form">
        <input type="hidden" name="action" value="login">

        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Email Address</label>
          <input type="email" name="email" required value="customer@meesho.com" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Password</label>
          <input type="password" name="password" required value="user123" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <button type="submit" class="btn-buy-now" style="width:100%; padding:12px; font-size:15px;">
          Sign In to Meesho
        </button>

        <!-- One-Click Demo Login -->
        <div style="margin-top:20px; padding:12px; background:#fdfafc; border:1px dashed #9f2089; border-radius:8px; text-align:center;">
          <div style="font-size:12px; font-weight:700; color:#9f2089; margin-bottom:4px;">Quick Demo Account:</div>
          <div style="font-size:11.5px; color:#555;">Email: <strong>customer@meesho.com</strong> | Pass: <strong>user123</strong></div>
        </div>
      </form>

      <!-- Register Form -->
      <form action="/MEESHO/auth.php" method="POST" id="register-form" style="display:none;">
        <input type="hidden" name="action" value="register">

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Full Name *</label>
          <input type="text" name="full_name" required placeholder="e.g. Aarti Verma" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Email Address *</label>
          <input type="email" name="email" required placeholder="aarti@example.com" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <div style="margin-bottom:14px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Phone Number</label>
          <input type="tel" name="phone" placeholder="9876543210" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Create Password *</label>
          <input type="password" name="password" required placeholder="Min 6 characters" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <button type="submit" class="btn-buy-now" style="width:100%; padding:12px; font-size:15px;">
          Create Customer Account
        </button>
      </form>

      <!-- Seller registration link -->
      <div style="text-align:center; margin-top:24px; border-top:1px solid #f1f1f1; padding-top:16px; font-size:13px; color:#666;">
        Want to sell products on Meesho? <a href="/MEESHO/supplier/index.php" style="color:#9f2089; font-weight:700;">Join as a Supplier &rarr;</a>
      </div>

    </div>

  </div>
</main>

<script>
function switchAuthTab(tab) {
  const loginForm = document.getElementById('login-form');
  const regForm = document.getElementById('register-form');
  const loginBtn = document.getElementById('tab-login-btn');
  const regBtn = document.getElementById('tab-register-btn');

  if (tab === 'login') {
    loginForm.style.display = 'block';
    regForm.style.display = 'none';
    loginBtn.style.color = '#9f2089';
    loginBtn.style.borderBottomColor = '#9f2089';
    regBtn.style.color = '#888';
    regBtn.style.borderBottomColor = 'transparent';
  } else {
    loginForm.style.display = 'none';
    regForm.style.display = 'block';
    regBtn.style.color = '#9f2089';
    regBtn.style.borderBottomColor = '#9f2089';
    loginBtn.style.color = '#888';
    loginBtn.style.borderBottomColor = 'transparent';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
