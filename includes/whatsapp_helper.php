<?php
/**
 * WhatsApp Notification Service & Messaging Helper
 * Youshoo E-Commerce Platform
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/whatsapp.php';

/**
 * Clean and normalize phone numbers for WhatsApp
 * Handles Indian numbers (leading 0, spaces, dashes, +91)
 *
 * @param string $phone
 * @param string $defaultCountryCode
 * @return string
 */
function normalize_whatsapp_phone($phone, $defaultCountryCode = '91') {
    // Remove all non-digits
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);

    // Remove leading zero(s) (e.g. 08270365246 -> 8270365246)
    $clean = ltrim($clean, '0');

    // If 10 digits (standard Indian mobile), prepend default country code
    if (strlen($clean) === 10) {
        $clean = $defaultCountryCode . $clean;
    }

    return $clean;
}

/**
 * Generate click-to-chat wa.me URL
 *
 * @param string $phone
 * @param string $messageText
 * @return string
 */
function generate_whatsapp_url($phone, $messageText) {
    $clean = normalize_whatsapp_phone($phone);
    return "https://wa.me/{$clean}?text=" . rawurlencode($messageText);
}

/**
 * Generate WhatsApp Web direct send URL
 *
 * @param string $phone
 * @param string $messageText
 * @return string
 */
function generate_whatsapp_web_url($phone, $messageText) {
    $clean = normalize_whatsapp_phone($phone);
    return "https://web.whatsapp.com/send?phone={$clean}&text=" . rawurlencode($messageText);
}

/**
 * Build professional WhatsApp message for a seller when order is placed
 *
 * @param array $supplier
 * @param array $order
 * @param array $items
 * @return string
 */
function build_seller_whatsapp_message($supplier, $order, $items) {
    $shopName = $supplier['shop_name'] ?? 'Partner Seller';
    $orderNumber = $order['order_number'] ?? ('YOUSH-' . $order['id']);
    $orderDate = !empty($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : date('d M Y, h:i A');
    $payMethod = strtoupper($order['payment_method'] ?? 'COD');
    $payStatus = ucfirst($order['payment_status'] ?? 'pending');

    // Calculate supplier item totals
    $supplierTotal = 0;
    $itemsListText = "";
    $itemIndex = 1;

    foreach ($items as $item) {
        $itemTotal = (float)($item['total_price'] ?? ($item['unit_price'] * $item['quantity']));
        $supplierTotal += $itemTotal;

        $size = !empty($item['size']) ? $item['size'] : 'Standard';
        $color = !empty($item['color']) ? $item['color'] : 'Standard';
        $title = $item['product_title'] ?? $item['title'] ?? 'Product';

        $itemsListText .= "{$itemIndex}. *{$title}*\n";
        $itemsListText .= "   • Qty: *{$item['quantity']}* | Size: {$size} | Color: {$color}\n";
        $itemsListText .= "   • Price: ₹" . number_format($itemTotal, 2) . "\n";
        $itemIndex++;
    }

    $customerName = $order['shipping_name'] ?? 'Customer';
    $customerPhone = $order['shipping_phone'] ?? 'N/A';
    $customerAddress = trim($order['shipping_address'] ?? '');
    $customerCity = trim($order['shipping_city'] ?? '');
    $customerState = trim($order['shipping_state'] ?? '');
    $customerPincode = trim($order['shipping_pincode'] ?? '');

    $fullAddress = "{$customerAddress}, {$customerCity}, {$customerState} - {$customerPincode}";

    // Construct clear, structured WhatsApp message
    $msg  = "🛍️ *NEW ORDER RECEIVED - YOUSHOO MARKETPLACE*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "Hello *{$shopName}*,\n";
    $msg .= "Good news! You have received a new customer order on Youshoo.\n\n";

    $msg .= "📦 *ORDER INFORMATION:*\n";
    $msg .= "• *Order ID:* `{$orderNumber}`\n";
    $msg .= "• *Date & Time:* {$orderDate}\n";
    $msg .= "• *Payment Mode:* {$payMethod} ({$payStatus})\n";
    $msg .= "• *Seller Item Subtotal:* ₹" . number_format($supplierTotal, 2) . "\n\n";

    $msg .= "👤 *CUSTOMER & DELIVERY DETAILS:*\n";
    $msg .= "• *Customer Name:* {$customerName}\n";
    $msg .= "• *Customer Phone:* {$customerPhone}\n";
    $msg .= "• *Delivery Address:*\n  {$fullAddress}\n\n";

    $msg .= "📋 *ORDERED ITEM(S):*\n";
    $msg .= $itemsListText . "\n";

    $msg .= "⚡ *SELLER ACTION REQUIRED:*\n";
    $msg .= "1. Please inspect and confirm this order promptly.\n";
    $msg .= "2. Pack items and generate shipping dispatch slip in your Seller Portal:\n";
    $msg .= "👉 http://localhost/MEESHO/supplier/orders.php\n\n";

    $msg .= "Thank you for selling with Youshoo! ✨";

    return $msg;
}

/**
 * Send WhatsApp message via configured API Gateway or generate direct link
 *
 * @param string $toPhone
 * @param string $messageText
 * @param int $orderId
 * @param int $supplierId
 * @param string $orderNumber
 * @param string $recipientName
 * @return array
 */
function send_whatsapp_message($toPhone, $messageText, $orderId, $supplierId, $orderNumber, $recipientName) {
    $settings = get_whatsapp_settings();
    $defaultCc = $settings['default_country_code'] ?? '91';
    $cleanPhone = normalize_whatsapp_phone($toPhone, $defaultCc);

    $provider = $settings['whatsapp_provider'] ?? 'direct_link';
    $autoSend = ($settings['auto_send_enabled'] ?? '1') === '1';

    $status = 'direct_link';
    $apiResponse = null;
    $sentSuccess = false;

    // Send via API if enabled and provider is configured
    if ($autoSend && $provider !== 'direct_link' && !empty($cleanPhone)) {
        try {
            if ($provider === 'ultramsg' && !empty($settings['ultramsg_instance_id']) && !empty($settings['ultramsg_token'])) {
                $instance = trim($settings['ultramsg_instance_id']);
                $token = trim($settings['ultramsg_token']);
                $url = "https://api.ultramsg.com/{$instance}/messages/chat";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'token' => $token,
                    'to'    => '+' . $cleanPhone,
                    'body'  => $messageText
                ]));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                $apiResponse = $resp ?: ($curlErr ? "cURL Error: " . $curlErr : "No response from UltraMsg");
                $decoded = json_decode($resp, true);
                if ($httpCode >= 200 && $httpCode < 300 && (!is_array($decoded) || empty($decoded['error']))) {
                    $status = 'sent';
                    $sentSuccess = true;
                } else {
                    $status = 'failed';
                }
            } elseif ($provider === 'callmebot' && !empty($settings['callmebot_apikey'])) {
                $phoneForCallmebot = !empty($settings['callmebot_phone']) ? $settings['callmebot_phone'] : $cleanPhone;
                $apikey = trim($settings['callmebot_apikey']);
                $url = "https://api.callmebot.com/whatsapp.php?phone=" . urlencode($phoneForCallmebot) . "&text=" . urlencode($messageText) . "&apikey=" . urlencode($apikey);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $apiResponse = $resp;
                if ($httpCode >= 200 && $httpCode < 300) {
                    $status = 'sent';
                    $sentSuccess = true;
                } else {
                    $status = 'failed';
                }
            } elseif ($provider === 'twilio' && !empty($settings['twilio_account_sid']) && !empty($settings['twilio_auth_token'])) {
                $sid = trim($settings['twilio_account_sid']);
                $token = trim($settings['twilio_auth_token']);
                $from = trim($settings['twilio_whatsapp_from']); // e.g. 'whatsapp:+14155238886'
                if (strpos($from, 'whatsapp:') !== 0) $from = 'whatsapp:' . $from;

                $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$sid}:{$token}");
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'From' => $from,
                    'To'   => 'whatsapp:+' . $cleanPhone,
                    'Body' => $messageText
                ]));
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $apiResponse = $resp;
                if ($httpCode >= 200 && $httpCode < 300) {
                    $status = 'sent';
                    $sentSuccess = true;
                } else {
                    $status = 'failed';
                }
            } elseif ($provider === 'custom_webhook' && !empty($settings['custom_webhook_url'])) {
                $webhookUrl = trim($settings['custom_webhook_url']);
                $payload = json_encode([
                    'event'            => 'order_placed',
                    'order_id'         => $orderId,
                    'order_number'     => $orderNumber,
                    'supplier_id'      => $supplierId,
                    'recipient_name'   => $recipientName,
                    'recipient_phone'  => $cleanPhone,
                    'message'          => $messageText,
                    'timestamp'        => date('Y-m-d H:i:s')
                ]);

                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $apiResponse = $resp;
                if ($httpCode >= 200 && $httpCode < 300) {
                    $status = 'sent';
                    $sentSuccess = true;
                } else {
                    $status = 'failed';
                }
            }
        } catch (Exception $e) {
            $status = 'failed';
            $apiResponse = "Exception: " . $e->getMessage();
        }
    }

    // Save log entry to database
    try {
        $pdo = getDBConnection();
        $stmtLog = $pdo->prepare("INSERT INTO whatsapp_logs 
            (order_id, supplier_id, order_number, recipient_phone, recipient_name, message_text, status, api_response)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtLog->execute([
            $orderId, $supplierId, $orderNumber, $cleanPhone, $recipientName, $messageText, $status, $apiResponse
        ]);
        $logId = $pdo->lastInsertId();
    } catch (Exception $e) {
        $logId = null;
    }

    $waUrl = generate_whatsapp_url($cleanPhone, $messageText);
    $waWebUrl = generate_whatsapp_web_url($cleanPhone, $messageText);

    return [
        'success'         => ($status === 'sent' || $status === 'direct_link'),
        'status'          => $status,
        'provider'        => $provider,
        'log_id'          => $logId,
        'recipient_phone' => $cleanPhone,
        'recipient_name'  => $recipientName,
        'message'         => $messageText,
        'wa_url'          => $waUrl,
        'wa_web_url'      => $waWebUrl,
        'api_response'    => $apiResponse
    ];
}

/**
 * Send WhatsApp notifications to all sellers involved in an order
 *
 * @param int $orderId
 * @return array Array of notification results keyed by supplier_id
 */
function notify_sellers_for_order($orderId) {
    $pdo = getDBConnection();

    // Fetch order details
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        return [];
    }

    // Fetch order items
    $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmtItems->execute([$orderId]);
    $allItems = $stmtItems->fetchAll();

    if (empty($allItems)) {
        return [];
    }

    // Group items by supplier_id
    $supplierItems = [];
    foreach ($allItems as $item) {
        $supplierId = (int)$item['supplier_id'];
        if (!isset($supplierItems[$supplierId])) {
            $supplierItems[$supplierId] = [];
        }
        $supplierItems[$supplierId][] = $item;
    }

    $notifications = [];

    // Notify each supplier
    foreach ($supplierItems as $supplierId => $items) {
        // Fetch supplier info
        $stmtSup = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmtSup->execute([$supplierId]);
        $supplier = $stmtSup->fetch();

        if (!$supplier) {
            continue;
        }

        $sellerPhone = !empty($supplier['phone']) ? $supplier['phone'] : '';
        $sellerShopName = !empty($supplier['shop_name']) ? $supplier['shop_name'] : 'Valued Seller';

        // Build seller-specific WhatsApp message
        $messageText = build_seller_whatsapp_message($supplier, $order, $items);

        // Dispatch or prepare notification
        $res = send_whatsapp_message(
            $sellerPhone,
            $messageText,
            $orderId,
            $supplierId,
            $order['order_number'],
            $sellerShopName
        );

        $res['supplier'] = $supplier;
        $res['items'] = $items;
        $notifications[$supplierId] = $res;
    }

    return $notifications;
}
