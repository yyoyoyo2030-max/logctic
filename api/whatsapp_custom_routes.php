<?php
/**
 * نظام إدارة اللوجستيك
 * API لإدارة توجيهات الواتساب المخصصة
 */

require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $event_type = clean_input($_POST['event_type'] ?? '');
        $phone_number = clean_input($_POST['phone_number'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        
        if (empty($event_type) || empty($phone_number)) {
            echo json_encode(['success' => false, 'message' => 'نوع العملية ورقم الهاتف مطلوبان']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("INSERT INTO whatsapp_custom_routes (event_type, phone_number, description) VALUES (?, ?, ?)");
            $stmt->execute([$event_type, $phone_number, $description]);
            echo json_encode(['success' => true, 'message' => 'تمت الإضافة بنجاح']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            $stmt = $conn->prepare("DELETE FROM whatsapp_custom_routes WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم الحذف بنجاح']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 0);
        
        try {
            $stmt = $conn->prepare("UPDATE whatsapp_custom_routes SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $id]);
            echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'إجراء غير صالح']);
