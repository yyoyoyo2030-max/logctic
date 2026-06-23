<?php
/**
 * نظام إدارة اللوجستيك
 * الصفحة الرئيسية - توجيه تلقائي
 */

require_once 'config/config.php';

// إذا كان المستخدم مسجل دخول، وجهه لـ dashboard
// وإلا وجهه لصفحة تسجيل الدخول
if (isLoggedIn()) {
    header("Location: views/dashboard/dashboard.php");
    exit();
} else {
    header("Location: views/auth/login.php");
    exit();
}
?>
