<?php
/**
 * API لتحديثات حالة الشحنات
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
    $transfers = [];
    
    // جلب التحديثات الأخيرة (آخر 5 دقائق)
    $since = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    if ($role == 'admin') {
        // كل التحديثات للمدير
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.tracking_number,
                t.status,
                t.updated_at,
                d.name as driver_name,
                fb.name as from_branch_name,
                tb.name as to_branch_name
            FROM transfers t
            LEFT JOIN driver_assignments da ON t.id = da.transfer_id
            LEFT JOIN drivers d ON da.driver_id = d.id
            LEFT JOIN branches fb ON t.from_branch = fb.id
            LEFT JOIN branches tb ON t.to_branch = tb.id
            WHERE t.updated_at > ?
            ORDER BY t.updated_at DESC
            LIMIT 100
        ");
        $stmt->execute([$since]);
        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($role == 'driver') {
        // تحديثات شحنات السائق فقط
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.tracking_number,
                t.updated_at,
                fb.name as from_branch_name,
                tb.name as to_branch_name
            FROM transfers t
            JOIN driver_assignments da ON t.id = da.transfer_id
            JOIN drivers d ON da.driver_id = d.id
            LEFT JOIN branches fb ON t.from_branch = fb.id
            LEFT JOIN branches tb ON t.to_branch = tb.id
            WHERE d.user_id = ?
            AND t.updated_at > ?
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute([$user_id, $since]);
        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // تحديثات شحنات الفرع
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.tracking_number,
                t.status,
                t.updated_at,
                d.name as driver_name,
                fb.name as from_branch_name,
                tb.name as to_branch_name
            FROM transfers t
            LEFT JOIN driver_assignments da ON t.id = da.transfer_id
            LEFT JOIN drivers d ON da.driver_id = d.id
            LEFT JOIN branches fb ON t.from_branch = fb.id
            LEFT JOIN branches tb ON t.to_branch = tb.id
            WHERE (t.from_branch = ? OR t.to_branch = ?)
            AND t.updated_at > ?
            ORDER BY t.updated_at DESC
            LIMIT 100
        ");
        $stmt->execute([$branch_id, $branch_id, $since]);
        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'transfers' => $transfers,
        'count' => count($transfers),
        'timestamp' => time() * 1000
    ]);
    
} catch (PDOException $e) {
    error_log("Error in transfers-status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في جلب التحويلات',
        'error' => $e->getMessage() // للتطوير فقط
    ]);
} catch (Exception $e) {
    error_log("General error in transfers-status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في النظام',
        'error' => $e->getMessage() // للتطوير فقط
    ]);
}
