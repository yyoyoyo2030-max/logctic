<?php
/**
 * نظام إدارة اللوجستيك
 * ملف التكوين الرئيسي
 */

// ========================================
// تحديد بيئة العمل
// ========================================
define('ENVIRONMENT', 'local'); // 'local' أو 'production'

// ========================================
// إعدادات البيئة المحلية (Local)
// ========================================
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_USER', 'root');
define('LOCAL_DB_PASS', '');
define('LOCAL_DB_NAME', 'logistic_system');
define('LOCAL_SITE_URL', 'http://localhost/logctic');

// ========================================
// إعدادات الإنتاج (Production)
// ========================================
define('PRODUCTION_DB_HOST', 'localhost');
define('PRODUCTION_DB_USER', 'u824688047_logis');
define('PRODUCTION_DB_PASS', 'Haro@12345678');
define('PRODUCTION_DB_NAME', 'u824688047_logistic');
define('PRODUCTION_SITE_URL', 'https://shop.alrsheed.net/logistic_pro');

// ========================================
// تطبيق الإعدادات (الأولوية لمتغيرات البيئة ENV ثم الثوابت)
// ========================================
if (getenv('DB_HOST')) {
    // 1. بيئة الاستضافات الحديثة (مثل Render) عبر Environment Variables
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_SSL', getenv('DB_SSL') ?: false);
    
    // إذا لم يتم تمرير رابط، استخدم رابط السيرفر التلقائي
    $site_url = getenv('SITE_URL');
    if (!$site_url) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $site_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
    }
    define('SITE_URL', $site_url);
    define('PRODUCTION_MODE', true);
} else if (ENVIRONMENT === 'production') {
    // إعدادات الإنتاج
    define('DB_HOST', PRODUCTION_DB_HOST);
    define('DB_USER', PRODUCTION_DB_USER);
    define('DB_PASS', PRODUCTION_DB_PASS);
    define('DB_NAME', PRODUCTION_DB_NAME);
    define('SITE_URL', PRODUCTION_SITE_URL);
    define('PRODUCTION_MODE', true);
} else {
    // إعدادات محلية (Local)
    define('DB_HOST', LOCAL_DB_HOST);
    define('DB_USER', LOCAL_DB_USER);
    define('DB_PASS', LOCAL_DB_PASS);
    define('DB_NAME', LOCAL_DB_NAME);
    define('SITE_URL', LOCAL_SITE_URL);
    define('PRODUCTION_MODE', false);
}

// ========================================
// إعدادات عامة
// ========================================
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// ضبط التوقيت على توقيت مكة المكرمة (السعودية)
date_default_timezone_set('Asia/Riyadh');

if (PRODUCTION_MODE) {
    // إخفاء الأخطاء عن المستخدمين وتسجيلها في ملف
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../error_log.txt');
    error_reporting(E_ALL);
} else {
    // عرض الأخطاء في التطوير
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}

// إعدادات الجلسة الآمنة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', PRODUCTION_MODE ? 1 : 0); // HTTPS فقط في الإنتاج
ini_set('session.cookie_samesite', 'Lax'); // تغيير من Strict إلى Lax لمنع انقطاع الجلسة

// زيادة مدة الجلسة - سنة واحدة (31536000 ثانية)
ini_set('session.gc_maxlifetime', 31536000);
ini_set('session.cookie_lifetime', 31536000);

// تحسين معالج حفظ الجلسة
ini_set('session.save_handler', 'files');
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// إعدادات رفع الملفات
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_execution_time', '30');
ini_set('memory_limit', '128M');

// ========================================
// بدء الجلسة
// ========================================
if (session_status() == PHP_SESSION_NONE) {
    // تحديد مسار حفظ الجلسات لمنع فقدانها على الاستضافة المشتركة
    if (PRODUCTION_MODE) {
        $sessionPath = dirname(__DIR__) . '/sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0755, true);
        }
        if (is_writable($sessionPath)) {
            ini_set('session.save_path', $sessionPath);
        }
    }
    
    // إعدادات الكوكيز قبل بدء الجلسة
    session_set_cookie_params([
        'lifetime' => 31536000, // سنة
        'path' => '/',
        'domain' => '',
        'secure' => PRODUCTION_MODE,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
    
    // تحديث وقت آخر نشاط (بدون session_regenerate_id لأنه يسبب فقدان الجلسة)
    $_SESSION['LAST_ACTIVITY'] = time();
}

// ========================================
// الاتصال بقاعدة البيانات
// ========================================
try {
    $db_port = defined('DB_PORT') ? DB_PORT : '3306';
    $dsn = "mysql:host=" . DB_HOST . ";port=" . $db_port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo_options = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+03:00'"
    ];
    // دعم SSL للاتصالات السحابية (مثل Aiven)
    if (defined('DB_SSL') && DB_SSL) {
        $pdo_options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $pdo_options[PDO::MYSQL_ATTR_SSL_CA] = true;
    }
    $conn = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch(PDOException $e) {
    die("عذراً، حدث خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// ========================================
// الدوال المساعدة
// ========================================

/**
 * التحقق من تسجيل دخول المستخدم
 * @return bool
 */
function isLoggedIn() {
    global $conn;
    // التحقق من وجود معرف المستخدم
    if (!isset($_SESSION['user_id'])) {
        // التحقق من كعكة "تذكرني" إذا لم تكن الجلسة موجودة
        if (isset($_COOKIE['remember_token'])) {
            $cookieParts = explode(':', $_COOKIE['remember_token']);
            if (count($cookieParts) == 2) {
                $user_id = (int)$cookieParts[0];
                $token = $cookieParts[1];
                
                try {
                    // تأكد من وجود العمود (لمرة واحدة فقط)
                    try {
                        $conn->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(100) NULL DEFAULT NULL");
                    } catch(Exception $e) { /* العمود موجود بالفعل */ }
                    
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND remember_token = ? AND is_active = 1");
                    $stmt->execute([$user_id, $token]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // إعادة إنشاء الجلسة
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['branch_id'] = $user['branch_id'];
                        $_SESSION['LAST_ACTIVITY'] = time();
                    } else {
                        // الكعكة غير صالحة
                        setcookie('remember_token', '', time() - 3600, "/");
                        return false;
                    }
                } catch(Exception $e) {
                    error_log("isLoggedIn remember_token error: " . $e->getMessage());
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    // تحديث الدور والفرع من قاعدة البيانات عند كل طلب لضمان تطبيق أي تغييرات فورياً
    try {
        $stmt = $conn->prepare("SELECT role, branch_id, is_active, full_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        
        if (!$user_data || !$user_data['is_active']) {
            session_destroy();
            return false;
        }
        
        // تحديث بيانات الجلسة من قاعدة البيانات
        $_SESSION['role']      = $user_data['role'];
        $_SESSION['branch_id'] = $user_data['branch_id'];
        $_SESSION['full_name'] = $user_data['full_name'];
    } catch (Exception $e) {
        // في حالة خطأ، نبقي الجلسة كما هي
    }
    
    // تجديد وقت النشاط عند كل طلب
    $_SESSION['LAST_ACTIVITY'] = time();
    
    return true;
}

/**
 * التحقق من صلاحية المدير (أدمن)
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * التحقق مما إذا كان المستخدم يملك صلاحيات مدير النظام بالكامل تشغيلياً أو إدارياً
 * @return bool
 */
function isLogisticsManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager']);
}

/**
 * التحقق مما إذا كان المستخدم يستطيع إدارة كافة المستودعات والفروع
 * @return bool
 */
function isWarehouseManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager', 'warehouse_manager']);
}

/**
 * التحقق مما إذا كان المستخدم يستطيع إدارة السائقين
 * @return bool
 */
function isDriversManager() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager', 'drivers_manager']);
}

/**
 * التحقق مما إذا كان المستخدم مدخل بيانات فرع محدد
 * @return bool
 */
function isBranchEntry() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'branch_entry';
}

/**
 * التحقق مما إذا كان المستخدم مدخل بيانات مستودع محدد
 * @return bool
 */
function isWarehouseEntry() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'warehouse_entry';
}

/**
 * التحقق مما إذا كان المستخدم يملك صلاحية رؤية كافة الفروع والتحويلات
 * @return bool
 */
function canManageAllBranches() {
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'logistics_manager', 'warehouse_manager', 'drivers_manager'])) {
        return true;
    }
    // المستخدمين غير المربوطين بفرع محدد (الفرع الرئيسي) يملكون صلاحيات كاملة
    if (isset($_SESSION['user_id']) && empty($_SESSION['branch_id'])) {
        return true;
    }
    return false;
}



/**
 * إعادة توجيه المستخدم لصفحة معينة
 * @param string $url رابط الصفحة (نسبي أو مطلق)
 */
function redirect($url) {
    // إذا كان المسار يبدأ بـ http أو /, استخدمه كما هو
    if (strpos($url, 'http') === 0 || strpos($url, '/') === 0) {
        header("Location: " . $url);
    } else {
        // إذا كان مسار نسبي، أضف SITE_URL
        header("Location: " . SITE_URL . "/" . $url);
    }
    exit();
}

/**
 * تنظيف وتأمين المدخلات
 * @param string $data البيانات المدخلة
 * @return string البيانات النظيفة
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// إنشاء متغير $pdo للاستخدام العام (اسم بديل لـ $conn)
$pdo = $conn;
?>
