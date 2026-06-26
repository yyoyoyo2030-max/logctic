<?php
/**
 * نظام رصد الأخطاء المتطور
 * يقوم برصد كافة الأخطاء والتحذيرات وتسجيلها في قاعدة البيانات
 */

function custom_error_handler($errno, $errstr, $errfile, $errline) {
    // تجاهل الأخطاء التي تم إخفاؤها بـ @
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    // تصنيف الخطأ
    $error_type = 'warning';
    switch ($errno) {
        case E_USER_ERROR:
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_PARSE:
            $error_type = 'fatal';
            break;
    }

    log_system_error($error_type, $errstr, $errfile, $errline);
    
    // لا نوقف التنفيذ إلا للأخطاء الفادحة
    if ($error_type === 'fatal') {
        exit(1);
    }
    
    return true; // نمنع PHP من طباعة الخطأ بالشكل الافتراضي
}

function custom_exception_handler($exception) {
    $message = "Uncaught Exception: " . $exception->getMessage();
    log_system_error('exception', $message, $exception->getFile(), $exception->getLine());
}

function custom_shutdown_handler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_PARSE])) {
        log_system_error('fatal', $error['message'], $error['file'], $error['line']);
    }
}

function log_system_error($type, $message, $file, $line) {
    global $conn; // استخدام اتصال قاعدة البيانات الموجود في config.php
    
    if (!$conn) {
        return; // إذا لم يكن هناك اتصال، لا تفعل شيئاً
    }

    // تجهيز التفاصيل بصيغة JSON ليسهل استخراجها في صفحة المراقبة
    $details = json_encode([
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'error_type' => $type
    ], JSON_UNESCAPED_UNICODE);

    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (strpos($ip_address, ',') !== false) {
        $ip_address = trim(explode(',', $ip_address)[0]);
    }
    $page_url = $_SERVER['REQUEST_URI'] ?? 'Unknown';

    try {
        $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, action, details, page_url, ip_address, created_at) VALUES (?, 'error', ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id,
            "System Error ($type)",
            $details,
            $page_url,
            $ip_address
        ]);
    } catch (Exception $e) {
        // فشل التسجيل، لا نفعل شيئاً لمنع التكرار اللانهائي
    }
}

// تفعيل صائدي الأخطاء
set_error_handler("custom_error_handler");
set_exception_handler("custom_exception_handler");
register_shutdown_function("custom_shutdown_handler");

?>
