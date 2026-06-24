<?php
require_once '../../config/config.php';

// مسح توكن "تذكرني" من قاعدة البيانات إذا كان مسجل الدخول
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch(Exception $e) {}
}

// مسح الكعكة (Cookie)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, "/");
}

session_destroy();
redirect('views/auth/login.php');
?>
