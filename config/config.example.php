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
define('PRODUCTION_DB_USER', 'your_db_user');
define('PRODUCTION_DB_PASS', 'your_db_password');
define('PRODUCTION_DB_NAME', 'your_db_name');
define('PRODUCTION_SITE_URL', 'https://yourdomain.com');

// ========================================
// تطبيق الإعدادات (الأولوية لمتغيرات البيئة ENV ثم الثوابت)
// ========================================
if (getenv('DB_HOST')) {
    // 1. بيئة الاستضافات الحديثة (مثل Render) عبر Environment Variables
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASS'));
    define('DB_NAME', getenv('DB_NAME'));
    
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

// زيادة مدة الجلسة - 8 ساعات (28800 ثانية)
ini_set('session.gc_maxlifetime', 28800);
ini_set('session.cookie_lifetime', 28800);

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
    session_start();
    
    // تجديد الجلسة بشكل دوري لمنع انتهائها
    if (!isset($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
    } else {
        // إذا مر أكثر من 30 دقيقة، جدد معرف الجلسة للأمان
        $inactive = time() - $_SESSION['LAST_ACTIVITY'];
        if ($inactive > 1800) {
            session_regenerate_id(true);
        }
    }
    
    // تحديث وقت آخر نشاط
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // حفظ معلومات المتصفح للأمان
    if (!isset($_SESSION['HTTP_USER_AGENT'])) {
        $_SESSION['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

// ========================================
// الاتصال بقاعدة البيانات
// ========================================
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+03:00'"
    ]);
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
        return false;
    }
    
    // التحقق من تطابق المتصفح (حماية من سرقة الجلسة)
    if (isset($_SESSION['HTTP_USER_AGENT'])) {
        if ($_SESSION['HTTP_USER_AGENT'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            session_destroy();
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
