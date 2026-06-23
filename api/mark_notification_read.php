<?php
/**
 * Mark Notification as Read API
 * Endpoint: /api/mark_notification_read.php
 * Method: POST
 * Purpose: Mark single or all notifications as read
 */

require_once '../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'غير مصرح'
    ]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['notification_id']) && $data['notification_id'] !== 'all') {
        // Mark single notification as read
        $notification_id = intval($data['notification_id']);
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$notification_id, $user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديد الإشعار كمقروء'
        ]);
        
    } else {
        // Mark all notifications as read
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        
        $stmt->execute([$user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديد جميع الإشعارات كمقروءة',
            'affected_rows' => $stmt->rowCount()
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()
    ]);
}
