<?php
/**
 * نظام التثبيت التلقائي لقاعدة البيانات (VPS Installer)
 * يرجى تشغيل هذا الملف مرة واحدة بعد الرفع.
 */
header('Content-Type: text/html; charset=utf-8');

// تضمين الإعدادات التي تحوي تفاصيل الاتصال
require_once 'config/config.php';

echo "<h1>مثبت قاعدة البيانات التلقائي</h1>";
echo "<p>جاري فحص وتثبيت قاعدة البيانات...</p>";

try {
    // الاتصال باستخدام PDO
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $conn = new PDO($dsn, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // إنشاء قاعدة البيانات إن لم تكن موجودة
    $dbName = DB_NAME;
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->exec("USE `$dbName`");

    // قراءة ملف database.sql
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        die("<p style='color:red;'>خطأ: ملف database.sql غير موجود في المجلد الرئيسي.</p>");
    }

    $sql = file_get_contents($sqlFile);
    
    // تنفيذ استعلامات SQL
    $conn->exec($sql);

    echo "<p style='color:green;'><strong>نجاح! تم تثبيت الجداول وقاعدة البيانات بنجاح.</strong></p>";

    // إضافة مستخدم مسؤول افتراضي إذا لم يكن هناك مستخدمون
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $conn->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES ('admin', ?, 'المدير العام', 'admin', 1)");
        $insert->execute([$adminPass]);
        echo "<p>تم إنشاء حساب مسؤول افتراضي:<br>المستخدم: <strong>admin</strong><br>كلمة المرور: <strong>admin123</strong></p>";
    }

    echo "<p style='color:red;'><strong>هام جداً:</strong> لأسباب أمنية، يرجى حذف ملف <code>install.php</code> فوراً بعد التثبيت.</p>";
    echo "<a href='views/auth/login.php' style='display:inline-block; padding:10px 20px; background:#03A9F4; color:#fff; text-decoration:none; border-radius:5px;'>الذهاب لتسجيل الدخول</a>";

} catch (Exception $e) {
    echo "<p style='color:red;'><strong>حدث خطأ:</strong> " . $e->getMessage() . "</p>";
}
?>
