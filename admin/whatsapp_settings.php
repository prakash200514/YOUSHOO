<?php
/**
 * Admin Suite - WhatsApp Gateway Configuration & Notification Audit
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

require_admin();

$pdo = getDBConnection();
$msg = '';
$error = '';
$testResult = null;

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_whatsapp_settings'])) {
    $keys = [
        'whatsapp_provider', 'default_country_code', 'auto_send_enabled',
        'ultramsg_instance_id', 'ultramsg_token',
        'callmebot_phone', 'callmebot_apikey',
        'twilio_account_sid', 'twilio_auth_token', 'twilio_whatsapp_from',
        'custom_webhook_url'
    ];

    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            update_whatsapp_setting($key, trim($_POST[$key]));
        }
    }

    $msg = "WhatsApp gateway settings saved successfully!";
}

// Handle Test Send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_whatsapp'])) {
    $testPhone = trim($_POST['test_phone'] ?? '');
    $testMsg = trim($_POST['test_message'] ?? '');

    if (!empty($testPhone) && !empty($testMsg)) {
        $testResult = send_whatsapp_message(
            $testPhone,
            $testMsg,
            0,
            0,
            'TEST-' . rand(1000, 9999),
            'Test Recipient'
        );
        $msg = "Test message processed! Check delivery status below.";
    } else {
        $error = "Please provide both phone number and message for testing.";
    }
}

$settings = get_whatsapp_settings();

// Fetch WhatsApp Logs
$stmtLogs = $pdo->query("SELECT * FROM whatsapp_logs ORDER BY id DESC LIMIT 30");
$logs = $stmtLogs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WhatsApp Gateway & Logs | Youshoo Admin Suite</title>
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
        <li class="dash-nav-item"><a href="/MEESHO/admin/orders.php"><i class="fas fa-shopping-cart"></i> Global Orders</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/admin/banners.php"><i class="fas fa-images"></i> Marketing Banners</a></li>
        <li class="dash-nav-item active"><a href="/MEESHO/admin/whatsapp_settings.php"><i class="fab fa-whatsapp"></i> WhatsApp Settings</a></li>
        <li class="dash-nav-item"><a href="/MEESHO/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> Customer Storefront</a></li>
        <li class="dash-nav-item" style="margin-top:30px;"><a href="/MEESHO/logout.php" style="color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </aside>

    <main class="dashboard-main">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
          <h1 style="font-size:24px; font-weight:800; color:#222; display:flex; align-items:center; gap:10px;">
            <i class="fab fa-whatsapp" style="color:#25d366;"></i> WhatsApp Order Alerts & Gateway
          </h1>
          <p style="font-size:13px; color:#666;">Configure automated notifications sent to sellers whenever customers place orders</p>
        </div>
      </div>

      <?php if (!empty($msg)): ?>
        <div style="background:#e6f7f2; color:#038d63; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
          <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <?php if ($testResult): ?>
        <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:16px; margin-bottom:20px;">
          <div style="font-weight:700; color:#166534; margin-bottom:6px;">Test Message Result:</div>
          <div style="font-size:13px; color:#374151;">Status: <strong><?php echo htmlspecialchars($testResult['status']); ?></strong> | To: <strong>+<?php echo htmlspecialchars($testResult['recipient_phone']); ?></strong></div>
          <div style="margin-top:8px;">
            <a href="<?php echo htmlspecialchars($testResult['wa_url']); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:#25d366; color:#fff; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:700; text-decoration:none;">
              <i class="fab fa-whatsapp"></i> Open in WhatsApp Web / App
            </a>
          </div>
          <?php if (!empty($testResult['api_response'])): ?>
            <pre style="margin-top:8px; background:#fff; padding:8px; border-radius:4px; font-size:11px; overflow-x:auto;"><?php echo htmlspecialchars($testResult['api_response']); ?></pre>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
        <!-- Gateway Configuration Form -->
        <div class="table-card" style="padding:24px;">
          <h2 style="font-size:17px; font-weight:800; color:#333; margin-bottom:16px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-cog"></i> Gateway Settings
          </h2>

          <form action="/MEESHO/admin/whatsapp_settings.php" method="POST">
            <input type="hidden" name="save_whatsapp_settings" value="1">

            <div style="margin-bottom:14px;">
              <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Delivery Method</label>
              <select name="whatsapp_provider" id="providerSelect" onchange="toggleProviderFields()" style="width:100%; padding:10px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; background:#fff;">
                <option value="direct_link" <?php echo $settings['whatsapp_provider'] === 'direct_link' ? 'selected' : ''; ?>>Direct Link / One-Click WhatsApp (Free & Instant, wa.me)</option>
                <option value="ultramsg" <?php echo $settings['whatsapp_provider'] === 'ultramsg' ? 'selected' : ''; ?>>UltraMsg WhatsApp API (Cloud Automated)</option>
                <option value="callmebot" <?php echo $settings['whatsapp_provider'] === 'callmebot' ? 'selected' : ''; ?>>CallMeBot WhatsApp Gateway (Free Key)</option>
                <option value="twilio" <?php echo $settings['whatsapp_provider'] === 'twilio' ? 'selected' : ''; ?>>Twilio WhatsApp Business API</option>
                <option value="custom_webhook" <?php echo $settings['whatsapp_provider'] === 'custom_webhook' ? 'selected' : ''; ?>>Custom Webhook URL (POST JSON)</option>
              </select>
              <span style="font-size:11px; color:#666; margin-top:4px; display:block;">
                Select how order notifications are sent to sellers when customer places an order.
              </span>
              <div id="notice_direct_link" style="display:none; background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; padding:10px; margin-top:8px; font-size:11.5px; color:#92400e; line-height:1.4;">
                <i class="fas fa-info-circle"></i> <strong>Note on Direct Link:</strong> Generates <code>wa.me</code> links for manual click-to-send via WhatsApp Web or mobile app. To have WhatsApp messages sent <strong>100% automatically in the background without manual clicks</strong>, select an API gateway below (such as UltraMsg, CallMeBot, or Twilio) and enter your credentials.
              </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
              <div>
                <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Default Country Code</label>
                <input type="text" name="default_country_code" value="<?php echo htmlspecialchars($settings['default_country_code']); ?>" placeholder="91" style="width:100%; padding:8px 10px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
              </div>
              <div>
                <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Auto Dispatch</label>
                <select name="auto_send_enabled" style="width:100%; padding:8px 10px; border:1px solid #d5d8de; border-radius:6px; font-size:13px; background:#fff;">
                  <option value="1" <?php echo $settings['auto_send_enabled'] === '1' ? 'selected' : ''; ?>>Enabled on Order Placement</option>
                  <option value="0" <?php echo $settings['auto_send_enabled'] === '0' ? 'selected' : ''; ?>>Manual Trigger Only</option>
                </select>
              </div>
            </div>

            <!-- UltraMsg Fields -->
            <div id="fields_ultramsg" style="display:none; background:#f9fafb; padding:12px; border-radius:8px; margin-bottom:14px; border:1px solid #e5e7eb;">
              <div style="font-weight:700; font-size:12.5px; color:#333; margin-bottom:8px;">UltraMsg API Credentials</div>
              <div style="margin-bottom:8px;">
                <label style="font-size:11.5px; color:#555;">Instance ID</label>
                <input type="text" name="ultramsg_instance_id" value="<?php echo htmlspecialchars($settings['ultramsg_instance_id']); ?>" placeholder="e.g. instance12345" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
              <div>
                <label style="font-size:11.5px; color:#555;">API Token</label>
                <input type="password" name="ultramsg_token" value="<?php echo htmlspecialchars($settings['ultramsg_token']); ?>" placeholder="UltraMsg Token" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
            </div>

            <!-- CallMeBot Fields -->
            <div id="fields_callmebot" style="display:none; background:#f9fafb; padding:12px; border-radius:8px; margin-bottom:14px; border:1px solid #e5e7eb;">
              <div style="font-weight:700; font-size:12.5px; color:#333; margin-bottom:8px;">CallMeBot Credentials</div>
              <div style="margin-bottom:8px;">
                <label style="font-size:11.5px; color:#555;">Registered Phone (with country code)</label>
                <input type="text" name="callmebot_phone" value="<?php echo htmlspecialchars($settings['callmebot_phone']); ?>" placeholder="e.g. 919820044556" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
              <div>
                <label style="font-size:11.5px; color:#555;">API Key</label>
                <input type="text" name="callmebot_apikey" value="<?php echo htmlspecialchars($settings['callmebot_apikey']); ?>" placeholder="CallMeBot API Key" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
            </div>

            <!-- Twilio Fields -->
            <div id="fields_twilio" style="display:none; background:#f9fafb; padding:12px; border-radius:8px; margin-bottom:14px; border:1px solid #e5e7eb;">
              <div style="font-weight:700; font-size:12.5px; color:#333; margin-bottom:8px;">Twilio WhatsApp API Credentials</div>
              <div style="margin-bottom:8px;">
                <label style="font-size:11.5px; color:#555;">Account SID</label>
                <input type="text" name="twilio_account_sid" value="<?php echo htmlspecialchars($settings['twilio_account_sid']); ?>" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
              <div style="margin-bottom:8px;">
                <label style="font-size:11.5px; color:#555;">Auth Token</label>
                <input type="password" name="twilio_auth_token" value="<?php echo htmlspecialchars($settings['twilio_auth_token']); ?>" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
              <div>
                <label style="font-size:11.5px; color:#555;">Twilio WhatsApp Number (From)</label>
                <input type="text" name="twilio_whatsapp_from" value="<?php echo htmlspecialchars($settings['twilio_whatsapp_from']); ?>" placeholder="+14155238886" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
            </div>

            <!-- Custom Webhook Fields -->
            <div id="fields_custom_webhook" style="display:none; background:#f9fafb; padding:12px; border-radius:8px; margin-bottom:14px; border:1px solid #e5e7eb;">
              <div style="font-weight:700; font-size:12.5px; color:#333; margin-bottom:8px;">Custom Webhook Destination</div>
              <div>
                <label style="font-size:11.5px; color:#555;">Webhook URL (Receives JSON POST)</label>
                <input type="url" name="custom_webhook_url" value="<?php echo htmlspecialchars($settings['custom_webhook_url']); ?>" placeholder="https://example.com/api/whatsapp-webhook" style="width:100%; padding:7px; border:1px solid #d5d8de; border-radius:4px; font-size:12px;">
              </div>
            </div>

            <button type="submit" style="background:#9f2089; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:700; cursor:pointer; width:100%; font-size:14px;">
              <i class="fas fa-save"></i> Save Configuration
            </button>
          </form>
        </div>

        <!-- Live WhatsApp Simulator / Test Tool -->
        <div class="table-card" style="padding:24px;">
          <h2 style="font-size:17px; font-weight:800; color:#333; margin-bottom:16px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fas fa-paper-plane"></i> Test Live WhatsApp Message
          </h2>

          <form action="/MEESHO/admin/whatsapp_settings.php" method="POST">
            <input type="hidden" name="send_test_whatsapp" value="1">

            <div style="margin-bottom:14px;">
              <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Seller / Test Phone Number *</label>
              <input type="tel" name="test_phone" required placeholder="e.g. 9820044556 or 08270365246" value="9820044556" style="width:100%; padding:10px; border:1px solid #d5d8de; border-radius:6px; font-size:13px;">
              <span style="font-size:11px; color:#666; margin-top:3px; display:block;">Enter 10-digit mobile number. Country code will be auto-formatted.</span>
            </div>

            <div style="margin-bottom:14px;">
              <label style="display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px;">Message Text *</label>
              <textarea name="test_message" rows="7" style="width:100%; padding:10px; border:1px solid #d5d8de; border-radius:6px; font-size:12.5px; font-family:inherit; line-height:1.4;">🛍️ *NEW ORDER ALERT - Youshoo*
Hello Seller, you have a new order:
• Order ID: YOUSH-TEST-99
• Customer: Marimuthu Prakash M (08270365246)
• Address: 7/234 A1 MAINROAD, Tirunelveli - 627353
• Product: True Wireless Bluetooth 5.3 Earbuds (Qty: 1)
• Total: ₹549 (COD)
Please pack and ship immediately!
👉 http://localhost/MEESHO/supplier/orders.php</textarea>
            </div>

            <button type="submit" style="background:#25d366; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-weight:700; cursor:pointer; width:100%; font-size:14px; display:flex; align-items:center; justify-content:center; gap:8px;">
              <i class="fab fa-whatsapp"></i> Test Send Message
            </button>
          </form>
        </div>
      </div>

      <!-- Recent WhatsApp Notification Logs -->
      <div class="table-card">
        <div style="padding:16px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
          <h3 style="font-size:16px; font-weight:700; color:#333;">
            <i class="fas fa-list-alt" style="color:#9f2089;"></i> WhatsApp Order Alert Audit Logs
          </h3>
          <span style="font-size:12px; color:#666;"><?php echo count($logs); ?> recorded dispatches</span>
        </div>

        <table class="custom-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Order Ref</th>
              <th>Seller / Recipient</th>
              <th>WhatsApp Phone</th>
              <th>Status</th>
              <th>Message Snippet</th>
              <th>Date & Time</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="8" style="text-align:center; padding:30px; color:#888;">No WhatsApp messages logged yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($logs as $l): 
                $waLink = generate_whatsapp_url($l['recipient_phone'], $l['message_text']);
              ?>
                <tr>
                  <td>#<?php echo $l['id']; ?></td>
                  <td><strong><?php echo htmlspecialchars($l['order_number']); ?></strong></td>
                  <td><?php echo htmlspecialchars($l['recipient_name']); ?></td>
                  <td>
                    <strong>+<?php echo htmlspecialchars($l['recipient_phone']); ?></strong>
                  </td>
                  <td>
                    <?php if ($l['status'] === 'sent'): ?>
                      <span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px;">Sent</span>
                    <?php elseif ($l['status'] === 'failed'): ?>
                      <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px;">Failed</span>
                    <?php else: ?>
                      <span style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px;">Direct Link</span>
                    <?php endif; ?>
                  </td>
                  <td style="max-width:260px; font-size:11.5px; color:#555; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?php echo htmlspecialchars(substr($l['message_text'], 0, 80)); ?>...
                  </td>
                  <td style="font-size:11.5px; color:#777;">
                    <?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?>
                  </td>
                  <td>
                    <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" style="background:#25d366; color:#fff; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                      <i class="fab fa-whatsapp"></i> Chat
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>

  <script>
    function toggleProviderFields() {
      var val = document.getElementById('providerSelect').value;
      var providers = ['ultramsg', 'callmebot', 'twilio', 'custom_webhook'];
      providers.forEach(function(p) {
        var el = document.getElementById('fields_' + p);
        if (el) el.style.display = (val === p) ? 'block' : 'none';
      });
      var noticeEl = document.getElementById('notice_direct_link');
      if (noticeEl) noticeEl.style.display = (val === 'direct_link') ? 'block' : 'none';
    }
    toggleProviderFields();
  </script>
</body>
</html>
