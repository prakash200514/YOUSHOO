<?php
/**
 * Customer Authentication (Login & Sign Up)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_helper.php';

$pdo = getDBConnection();
$error = '';
$success = '';
$info = '';

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
$activeTab = $_GET['tab'] ?? 'login';

if (isset($_GET['registered'])) {
    $success = "Account registered successfully! Please sign in with your email and password.";
    $activeTab = 'login';
}

$msg = $_GET['msg'] ?? '';
if ($msg === 'checkout_required') {
    $info = "Please sign in or create an account to proceed to checkout.";
} elseif ($msg === 'orders_required') {
    $info = "Please sign in or create an account to view your orders.";
}

// If already logged in
if (is_logged_in()) {
    if (!empty($redirect)) {
        if ($redirect === 'checkout') header("Location: /MEESHO/checkout.php");
        elseif ($redirect === 'orders') header("Location: /MEESHO/orders.php");
        else header("Location: " . $redirect);
        exit;
    }
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

                    if (!empty($redirect)) {
                        if ($redirect === 'checkout') header("Location: /MEESHO/checkout.php");
                        elseif ($redirect === 'orders') header("Location: /MEESHO/orders.php");
                        else header("Location: " . $redirect);
                        exit;
                    }

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
            $activeTab = 'register';
        } else {
            // Check if email already registered
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $error = "Email address already registered. Please log in.";
                $activeTab = 'register';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtIns = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, 'customer', 'active')");
                $stmtIns->execute([$fullName, $email, $phone, $hashed]);

                // Customer must register their account and then log in (no auto-login)
                $redirectQuery = !empty($redirect) ? '&redirect=' . urlencode($redirect) : '';
                header("Location: /MEESHO/auth.php?registered=1&email=" . urlencode($email) . $redirectQuery);
                exit;
            }
        }
    }
}

$loginEmailVal = htmlspecialchars($_GET['email'] ?? ($_POST['email'] ?? ''));

$pageTitle = "Sign In or Register";
require_once __DIR__ . '/includes/header.php';
?>

<main class="main-content" style="background: linear-gradient(135deg, #fdfafc, #f7ebf5); min-height: 75vh; padding: 40px 0;">
  <div class="container" style="max-width: 460px;">

    <div style="background:#ffffff; border-radius:16px; border:1px solid #e6e9ef; padding:32px; box-shadow:var(--meesho-shadow-md);">
      
      <!-- Top Logo / Title -->
      <div style="text-align:center; margin-bottom:24px;">
        <div class="brand-logo" style="justify-content:center; margin-bottom:6px;">
          youshoo
          <span class="brand-tag">User</span>
        </div>
        <p style="font-size:13px; color:#666;">Sign up or log in to view orders & track delivery</p>
      </div>

      <?php if (!empty($success)): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 14px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600; border:1px solid #a7f3d0;">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($info)): ?>
        <div style="background:#eff6ff; color:#1d4ed8; padding:12px 14px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600; border:1px solid #bfdbfe;">
          <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($info); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:10px 14px; border-radius:8px; margin-bottom:18px; font-size:13px; font-weight:600; border:1px solid #fecaca;">
          <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <!-- Tabs (Login vs Register) -->
      <div style="display:flex; border-bottom:2px solid #f1f1f1; margin-bottom:24px;">
        <button type="button" id="tab-login-btn" onclick="switchAuthTab('login')" style="flex:1; padding:10px; background:none; border:none; font-size:15px; font-weight:700; color:<?php echo $activeTab === 'register' ? '#888' : '#9f2089'; ?>; border-bottom:2px solid <?php echo $activeTab === 'register' ? 'transparent' : '#9f2089'; ?>; cursor:pointer;">
          Sign In
        </button>
        <button type="button" id="tab-register-btn" onclick="switchAuthTab('register')" style="flex:1; padding:10px; background:none; border:none; font-size:15px; font-weight:600; color:<?php echo $activeTab === 'register' ? '#9f2089' : '#888'; ?>; border-bottom:2px solid <?php echo $activeTab === 'register' ? '#9f2089' : 'transparent'; ?>; cursor:pointer;">
          Create Account
        </button>
      </div>

      <!-- Login Form -->
      <form action="/MEESHO/auth.php" method="POST" id="login-form" style="<?php echo $activeTab === 'register' ? 'display:none;' : ''; ?>">
        <input type="hidden" name="action" value="login">
        <?php if (!empty($redirect)): ?>
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
        <?php endif; ?>

        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Email Address</label>
          <input type="email" name="email" required placeholder="Enter your email address" value="<?php echo $loginEmailVal; ?>" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px;">Password</label>
          <input type="password" name="password" required placeholder="Enter your password" style="width:100%; padding:11px 14px; border:1.5px solid #d5d8de; border-radius:8px; font-size:14px; outline:none;">
        </div>

        <button type="submit" class="btn-buy-now" style="width:100%; padding:12px; font-size:15px;">
          Sign In to Youshoo
        </button>
      </form>

      <!-- Register Form -->
      <form action="/MEESHO/auth.php" method="POST" id="register-form" style="<?php echo $activeTab === 'register' ? '' : 'display:none;'; ?>">
        <input type="hidden" name="action" value="register">
        <?php if (!empty($redirect)): ?>
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
        <?php endif; ?>

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
        Want to sell products on Youshoo? <a href="/MEESHO/supplier/index.php" style="color:#9f2089; font-weight:700;">Join as a Supplier &rarr;</a>
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
