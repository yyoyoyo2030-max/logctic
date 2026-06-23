#!/bin/bash

# Configure Apache to listen on the PORT provided by Render (defaults to 80)
PORT=${PORT:-80}
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf

# Generate config.php from environment variables if DB_HOST is set
if [ -n "$DB_HOST" ]; then
    cat > /var/www/html/config/config.php << 'EOFHEADER'
<?php
/**
 * نظام إدارة اللوجستيك
 * ملف التكوين - تم إنشاؤه تلقائياً من متغيرات البيئة
 */

EOFHEADER

    cat >> /var/www/html/config/config.php << EOFVARS
define('DB_HOST', '${DB_HOST}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASS}');
define('DB_NAME', '${DB_NAME}');
define('DB_PORT', '${DB_PORT:-3306}');
define('DB_SSL', ${DB_SSL:-0});
define('PRODUCTION_MODE', true);

EOFVARS

    cat >> /var/www/html/config/config.php << 'EOFREST'
// Site URL auto-detection
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$site_url = getenv('SITE_URL') ?: ($protocol . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('SITE_URL', $site_url);

// إعدادات عامة
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
date_default_timezone_set('Asia/Riyadh');

// إخفاء الأخطاء في الإنتاج
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error_log.txt');
error_reporting(E_ALL);

// إعدادات الجلسة الآمنة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 28800);
ini_set('session.cookie_lifetime', 28800);
ini_set('session.save_handler', 'files');
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// إعدادات رفع الملفات
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_execution_time', '30');
ini_set('memory_limit', '128M');

// بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    if (!isset($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
    } else {
        $inactive = time() - $_SESSION['LAST_ACTIVITY'];
        if ($inactive > 1800) {
            session_regenerate_id(true);
        }
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    if (!isset($_SESSION['HTTP_USER_AGENT'])) {
        $_SESSION['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

// الاتصال بقاعدة البيانات
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+03:00'"
    ];
    if (DB_SSL) {
        $pdo_options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $pdo_options[PDO::MYSQL_ATTR_SSL_CA] = true;
    }
    $conn = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// الدوال المساعدة
function isLoggedIn() {
    global $conn;
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['HTTP_USER_AGENT'])) {
        if ($_SESSION['HTTP_USER_AGENT'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            session_destroy();
            return false;
        }
    }
    try {
        $stmt = $conn->prepare("SELECT role, branch_id, is_active, full_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        if (!$user_data || !$user_data['is_active']) {
            session_destroy();
            return false;
        }
        $_SESSION['role']      = $user_data['role'];
        $_SESSION['branch_id'] = $user_data['branch_id'];
        $_SESSION['full_name'] = $user_data['full_name'];
    } catch (Exception $e) {}
    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLogisticsManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager']);
}

function isWarehouseManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager', 'warehouse_manager']);
}

function isDriversManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager', 'drivers_manager']);
}

function isBranchEntry() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'branch_entry';
}

function isWarehouseEntry() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'warehouse_entry';
}

function canManageAllBranches() {
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager', 'warehouse_manager', 'drivers_manager'])) {
        return true;
    }
    if (isset($_SESSION['user_id']) && empty($_SESSION['branch_id'])) {
        return true;
    }
    return false;
}

function redirect($url) {
    if (strpos($url, 'http') === 0 || strpos($url, '/') === 0) {
        header("Location: " . $url);
    } else {
        header("Location: " . SITE_URL . "/" . $url);
    }
    exit();
}

function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

$pdo = $conn;
?>
EOFREST

    chown www-data:www-data /var/www/html/config/config.php
    echo "Config generated from environment variables."
fi

# Start Apache in foreground
exec apache2-foreground
