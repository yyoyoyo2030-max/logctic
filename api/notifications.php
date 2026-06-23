<?php
/**
 * API للإشعارات اللحظية
 */

require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_check = isset($_GET['last']) ? intval($_GET['last']) : 0;
$last_check_date = date('Y-m-d H:i:s', $last_check / 1000);

try {
    // جلب الإشعارات الجديدة
    $stmt = $conn->prepare("
        SELECT 
            id,
            type,
            message,
            related_id,
            created_at,
            is_read,
            show_toast,
            play_sound,
            browser_notification
        FROM notifications
        WHERE user_id = ? 
        AND created_at > ?
        AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$user_id, $last_check_date]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تحديث عدد غير المقروء
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetch()['unread_count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'timestamp' => time() * 1000
    ]);
    
} catch (PDOException $e) {
    error_log("Error in notifications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات',
        'error' => $e->getMessage() // للتطوير فقط
    ]);
} catch (Exception $e) {
    error_log("General error in notifications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في النظام',
        'error' => $e->getMessage() // للتطوير فقط
    ]);
}
