<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$stmt = $conn->query("SELECT * FROM whatsapp_settings LIMIT 1");
$settings = $stmt->fetch();

if (!$settings || empty($settings['api_url']) || empty($settings['api_key'])) {
    echo json_encode(['success' => false, 'message' => 'إعدادات الواتساب غير مكتملة. يرجى الحفظ أولاً.']);
    exit;
}

$api_url = rtrim($settings['api_url'], '/');
$instance = !empty($settings['instance_name']) ? $settings['instance_name'] : 'logistic_system';
$apikey = $settings['api_key'];

$action = $_GET['action'] ?? 'status';

function makeRequest($url, $method = 'GET', $data = null) {
    global $apikey;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $apikey
    ];
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['status' => $http_status, 'body' => json_decode($response, true)];
}

if ($action == 'status') {
    $res = makeRequest("{$api_url}/instance/connectionState/{$instance}");
    
    if ($res['status'] >= 200 && $res['status'] < 300) {
        $state = $res['body']['instance']['state'] ?? 'unknown';
        echo json_encode(['success' => true, 'state' => $state]);
    } else {
        echo json_encode(['success' => false, 'state' => 'not_found', 'message' => 'النسخة غير موجودة أو مفصولة.']);
    }
} 
elseif ($action == 'qr') {
    // 1. Try to fetch QR for existing instance
    $res = makeRequest("{$api_url}/instance/connect/{$instance}");
    
    if ($res['status'] >= 200 && $res['status'] < 300) {
        if (isset($res['body']['base64'])) {
            echo json_encode(['success' => true, 'qr' => $res['body']['base64']]);
        } elseif (isset($res['body']['instance']['state']) && $res['body']['instance']['state'] === 'open') {
             echo json_encode(['success' => true, 'state' => 'open', 'message' => 'الرقم متصل بالفعل']);
        } else {
            echo json_encode(['success' => false, 'message' => 'لم يتم إرجاع كود QR']);
        }
    } else {
        // 2. If instance doesn't exist, create it
        $createData = [
            'instanceName' => $instance,
            'token' => $apikey,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS'
        ];
        $createRes = makeRequest("{$api_url}/instance/create", 'POST', $createData);
        if ($createRes['status'] >= 200 && $createRes['status'] < 300) {
            if (isset($createRes['body']['qrcode']['base64'])) {
                echo json_encode(['success' => true, 'qr' => $createRes['body']['qrcode']['base64']]);
            } else if (isset($createRes['body']['hash']['qrcode'])) {
                 echo json_encode(['success' => true, 'qr' => $createRes['body']['hash']['qrcode']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'تم إنشاء النسخة لكن لم يظهر كود QR', 'data' => $createRes['body']]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل إنشاء النسخة', 'data' => $createRes['body']]);
        }
    }
}
elseif ($action == 'reconnect') {
    // محاولة إعادة الاتصال بالجلسة المحفوظة بدون QR
    // أولاً: فحص الحالة الحالية
    $statusRes = makeRequest("{$api_url}/instance/connectionState/{$instance}");
    
    if ($statusRes['status'] >= 200 && $statusRes['status'] < 300) {
        $state = $statusRes['body']['instance']['state'] ?? 'unknown';
        
        if ($state === 'open') {
            echo json_encode(['success' => true, 'state' => 'open', 'message' => 'الرقم متصل بالفعل']);
            exit;
        }
    }
    
    // ثانياً: محاولة إعادة الاتصال عبر connect
    $connectRes = makeRequest("{$api_url}/instance/connect/{$instance}");
    
    if ($connectRes['status'] >= 200 && $connectRes['status'] < 300) {
        // إذا أعاد الاتصال بنجاح بدون QR
        if (isset($connectRes['body']['instance']['state']) && $connectRes['body']['instance']['state'] === 'open') {
            echo json_encode(['success' => true, 'state' => 'open', 'message' => 'تمت إعادة الاتصال بنجاح']);
        }
        // إذا احتاج QR جديد (الجلسة انتهت)
        elseif (isset($connectRes['body']['base64'])) {
            echo json_encode(['success' => true, 'qr' => $connectRes['body']['base64'], 'message' => 'الجلسة انتهت، يرجى مسح الكود الجديد']);
        }
        else {
            // قد يكون في حالة connecting، ننتظر قليلاً
            echo json_encode(['success' => true, 'state' => 'connecting', 'message' => 'جاري إعادة الاتصال... انتظر قليلاً ثم اضغط مرة أخرى.']);
        }
    } else {
        // النسخة غير موجودة، نحتاج إنشاءها من جديد
        $createData = [
            'instanceName' => $instance,
            'token' => $apikey,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS'
        ];
        $createRes = makeRequest("{$api_url}/instance/create", 'POST', $createData);
        if ($createRes['status'] >= 200 && $createRes['status'] < 300) {
            if (isset($createRes['body']['qrcode']['base64'])) {
                echo json_encode(['success' => true, 'qr' => $createRes['body']['qrcode']['base64'], 'message' => 'تم إنشاء نسخة جديدة، يرجى مسح الكود']);
            } elseif (isset($createRes['body']['hash']['qrcode'])) {
                echo json_encode(['success' => true, 'qr' => $createRes['body']['hash']['qrcode']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'تم إنشاء النسخة لكن لم يظهر كود QR']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'فشل إعادة الاتصال']);
        }
    }
}
