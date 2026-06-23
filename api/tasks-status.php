<?php
/**
 * API لتحديثات حالة المهام
 * محسّن للعمل مع AJAX Polling
 */

require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$branch_id = $_SESSION['branch_id'] ?? null;

try {
    $tasks = [];
    
    // جلب التحديثات الأخيرة (آخر 5 دقائق)
    $since = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    if ($role == 'admin') {
        // كل التحديثات للمدير
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.title,
                t.status,
                t.priority,
                t.updated_at,
                GROUP_CONCAT(d.name SEPARATOR ', ') as drivers
            FROM tasks t
            LEFT JOIN task_driver_assignments tda ON t.id = tda.task_id
            LEFT JOIN drivers d ON tda.driver_id = d.id
            WHERE t.updated_at > ?
            GROUP BY t.id
            ORDER BY t.updated_at DESC
            LIMIT 50
        ");
        $stmt->execute([$since]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($role == 'driver') {
        // مهام السائق فقط
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.title,
                t.status,
                t.priority,
                t.updated_at
            FROM tasks t
            JOIN task_driver_assignments tda ON t.id = tda.task_id
            WHERE tda.driver_id = (SELECT id FROM drivers WHERE user_id = ?)
            AND t.updated_at > ?
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute([$user_id, $since]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // مهام الفرع
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.title,
                t.status,
                t.priority,
                t.updated_at,
                GROUP_CONCAT(d.name SEPARATOR ', ') as drivers
            FROM tasks t
            LEFT JOIN task_driver_assignments tda ON t.id = tda.task_id
            LEFT JOIN drivers d ON tda.driver_id = d.id
            WHERE t.branch_id = ?
            AND t.updated_at > ?
            GROUP BY t.id
            ORDER BY t.updated_at DESC
            LIMIT 50
        ");
        $stmt->execute([$branch_id, $since]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'count' => count($tasks),
        'timestamp' => time() * 1000
    ]);
    
} catch (PDOException $e) {
    error_log("Error in tasks-status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في جلب المهام'
    ]);
}
