<?php
/**
 * نظام إدارة اللوجستيك
 * API الخاص بإرسال رسائل الواتساب عبر Evolution API
 */

require_once __DIR__ . '/../config/config.php';

/**
 * إرسال رسالة واتساب
 * @param string $recipient_id رقم الهاتف (يجب أن ينتهي بـ @s.whatsapp.net) أو الجروب (@g.us)
 * @param string $message نص الرسالة
 * @return array ['success' => bool, 'message' => string]
 */
function sendWhatsAppMessage($recipient_id, $message) {
    global $conn;
    
    try {
        // جلب الإعدادات
        $stmt = $conn->query("SELECT * FROM whatsapp_settings LIMIT 1");
        $settings = $stmt->fetch();
        
        if (!$settings || !$settings['is_active']) {
            return ['success' => false, 'message' => 'خدمة الواتساب غير مفعلة أو لم يتم إعدادها'];
        }
        
        $api_url = rtrim($settings['api_url'], '/');
        $instance = $settings['instance_name'];
        $apikey = $settings['api_key'];
        
        $endpoint = "{$api_url}/message/sendText/{$instance}";
        
        // تجهيز رقم المستلم
        // تأكد من وجود اللاحقة الصحيحة إذا كان رقما عاديا وليس جروب
        if (!str_contains($recipient_id, '@g.us') && !str_contains($recipient_id, '@s.whatsapp.net')) {
            // إزالة الصفر في البداية وإضافة رمز الدولة إذا لزم (يُفترض السعودية 966)
            $phone = preg_replace('/^0/', '966', preg_replace('/[^0-9]/', '', $recipient_id));
            $recipient_id = $phone . '@s.whatsapp.net';
        }
        
        $payload = [
            'number' => $recipient_id,
            'text' => $message
        ];
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $apikey
        ]);
        // تجاهل التحقق من الشهادة (اختياري، يفضل تفعيله في الإنتاج)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // تسجيل الرسالة
        $type = str_contains($recipient_id, '@g.us') ? 'group' : 'driver';
        $status = ($http_status >= 200 && $http_status < 300) ? 'sent' : 'failed';
        
        $log_stmt = $conn->prepare("INSERT INTO whatsapp_logs (recipient_type, recipient_id, message_type, message_content, status, response_data) VALUES (?, ?, 'text', ?, ?, ?)");
        $log_stmt->execute([$type, $recipient_id, $message, $status, $response]);
        
        if ($status == 'sent') {
            return ['success' => true, 'message' => 'تم الإرسال بنجاح', 'response' => json_decode($response, true)];
        } else {
            return ['success' => false, 'message' => 'فشل الإرسال', 'error' => $error, 'response' => $response];
        }
        
    } catch(Exception $e) {
        return ['success' => false, 'message' => 'حدث خطأ غير متوقع: ' . $e->getMessage()];
    }
}
