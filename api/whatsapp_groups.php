<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$stmt = $conn->query("SELECT * FROM whatsapp_settings LIMIT 1");
$settings = $stmt->fetch();

if (!$settings || empty($settings['api_url']) || empty($settings['instance_name']) || empty($settings['api_key'])) {
    echo json_encode(['success' => false, 'message' => 'إعدادات الواتساب غير مكتملة']);
    exit;
}

$api_url = rtrim($settings['api_url'], '/');
$instance = $settings['instance_name'];
$apikey = $settings['api_key'];

$endpoint = "{$api_url}/group/fetchAllGroups/{$instance}?getParticipants=false";

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apikey: ' . $apikey
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_status >= 200 && $http_status < 300) {
    $data = json_decode($response, true);
    // بعض نسخ Evolution API ترجع البيانات كـ array مباشر
    if (!isset($data['success']) && is_array($data)) {
        echo json_encode(['success' => true, 'groups' => $data]);
    } else {
        echo json_encode(['success' => true, 'groups' => $data]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بالواتساب، تأكد من صحة الرابط ومفتاح الـ API واسم النسخة', 'details' => json_decode($response)]);
}
