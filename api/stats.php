<?php
/**
 * API للإحصائيات اللحظية
 */

require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$branch_id = $_SESSION['branch_id'] ?? null;

try {
    // إحصائيات بسيطة وآمنة
    
    // عدد التحويلات
    if (isAdmin()) {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM transfers");
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transfers WHERE branch_id = ?");
        $stmt->execute([$branch_id]);
    }
    $transfers_count = $stmt->fetch()['total'];
    
    // عدد السائقين
    $stmt = $conn->query("SELECT COUNT(*) as total FROM drivers WHERE is_active = 1");
    $drivers_count = $stmt->fetch()['total'];
    
    // عدد الفروع
    $stmt = $conn->query("SELECT COUNT(*) as total FROM branches WHERE is_active = 1");
    $branches_count = $stmt->fetch()['total'];
    
    // عدد المهام (قد لا يكون موجود في الجدول)
    $tasks_count = 0;
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM tasks");
        $tasks_count = $stmt->fetch()['total'];
    } catch (Exception $e) {
        // الجدول غير موجود - استخدم صفر
    }
    
    echo json_encode([
        'success' => true,
        'drivers_count' => $drivers_count,
        'branches_count' => $branches_count,
        'tasks_count' => $tasks_count,
        'transfers_count' => $transfers_count,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    error_log("Error in stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات'
    ]);
} catch (Exception $e) {
    error_log("General error in stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في النظام'
    ]);
}
