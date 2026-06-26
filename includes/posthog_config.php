<?php
/**
 * PostHog Dynamic Configuration
 * Included to fetch PostHog settings from the database.
 */

// إعدادات PostHog الافتراضية
if (!defined('POSTHOG_PROJECT_API_KEY')) {
    define('POSTHOG_PROJECT_API_KEY', 'phc_zkY3cbF8xDssbupMiPjQmtVAw5DoLRb7iB9CPV6nNp4q');
}
if (!defined('POSTHOG_HOST')) {
    define('POSTHOG_HOST', 'https://us.i.posthog.com');
}
if (!defined('POSTHOG_PROJECT_ID')) {
    define('POSTHOG_PROJECT_ID', '484728');
}

$GLOBALS['POSTHOG_SETTINGS'] = [
    'api_key'    => POSTHOG_PROJECT_API_KEY,
    'host'       => POSTHOG_HOST,
    'project_id' => POSTHOG_PROJECT_ID
];

// التأكد من توفر اتصال قاعدة البيانات $conn
global $conn;
if (isset($conn)) {
    try {
        // إنشاء الجدول إن لم يكن موجوداً
        $conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // جلب الإعدادات من قاعدة البيانات
        $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'posthog_%'");
        if ($stmt) {
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($settings['posthog_api_key'])) {
                $GLOBALS['POSTHOG_SETTINGS']['api_key'] = $settings['posthog_api_key'];
            }
            if (!empty($settings['posthog_host'])) {
                $GLOBALS['POSTHOG_SETTINGS']['host'] = $settings['posthog_host'];
            }
            if (!empty($settings['posthog_project_id'])) {
                $GLOBALS['POSTHOG_SETTINGS']['project_id'] = $settings['posthog_project_id'];
            }
        }
    } catch(Throwable $e) {
        // الاعتماد على الإعدادات الافتراضية
    }
}

if (!function_exists('getPosthogSetting')) {
    function getPosthogSetting($key) {
        $map = ['api_key' => 'api_key', 'host' => 'host', 'project_id' => 'project_id'];
        if (!isset($GLOBALS['POSTHOG_SETTINGS']) || !is_array($GLOBALS['POSTHOG_SETTINGS'])) {
            if ($key === 'api_key') return defined('POSTHOG_PROJECT_API_KEY') ? POSTHOG_PROJECT_API_KEY : '';
            if ($key === 'host') return defined('POSTHOG_HOST') ? POSTHOG_HOST : 'https://us.i.posthog.com';
            if ($key === 'project_id') return defined('POSTHOG_PROJECT_ID') ? POSTHOG_PROJECT_ID : '484728';
            return '';
        }
        $mappedKey = $map[$key] ?? $key;
        return isset($GLOBALS['POSTHOG_SETTINGS'][$mappedKey]) ? $GLOBALS['POSTHOG_SETTINGS'][$mappedKey] : '';
    }
}
?>
