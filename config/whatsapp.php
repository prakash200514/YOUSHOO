<?php
/**
 * WhatsApp Gateway Configuration & Settings
 * Youshoo E-Commerce Platform
 */

require_once __DIR__ . '/db.php';

/**
 * Get all WhatsApp settings from database or fallbacks
 * @return array
 */
function get_whatsapp_settings() {
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $default = [
        'whatsapp_provider'     => 'direct_link', // 'direct_link', 'ultramsg', 'callmebot', 'twilio', 'custom_webhook'
        'default_country_code'   => '91',
        'ultramsg_instance_id'  => '',
        'ultramsg_token'        => '',
        'callmebot_phone'       => '',
        'callmebot_apikey'      => '',
        'twilio_account_sid'    => '',
        'twilio_auth_token'     => '',
        'twilio_whatsapp_from'  => '',
        'custom_webhook_url'    => '',
        'auto_send_enabled'     => '1'
    ];

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM whatsapp_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = array_merge($default, $rows ?: []);
    } catch (Exception $e) {
        $settings = $default;
    }

    return $settings;
}

/**
 * Update a WhatsApp setting in database
 * @param string $key
 * @param string $value
 * @return bool
 */
function update_whatsapp_setting($key, $value) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO whatsapp_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}
