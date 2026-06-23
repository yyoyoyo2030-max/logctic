<?php
/**
 * Server-Sent Events (SSE) للتحديث اللحظي
 * يرسل تحديثات فورية للمتصفح بدون الحاجة لطلبات متكررة
 */

// تمكين عرض الأخطاء للتشخيص
error_reporting(E_ALL);
ini_set('display_errors', 0); // لا نعرض الأخطاء في output لأنها ستكسر SSE
ini_set('log_errors', 1);

// منع التخزين المؤقت
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // لتعطيل buffering في Nginx
header('Access-Control-Allow-Origin: *'); // للسماح بـ CORS

// التأكد من إرسال البيانات فوراً
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
if (ob_get_level()) ob_end_clean();

// تعطيل time limit
set_time_limit(0);

try {
    session_start();
    require_once __DIR__ . '/../config/config.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    echo "event: error\n";
    echo "data: {\"message\": \"غير مصرح\"}\n\n";
    flush();
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// حفظ آخر البيانات المرسلة لإرسال التحديثات فقط عند التغيير
$lastData = [
    'transfers_count' => 0,
    'notifications_count' => 0,
    'stats_hash' => '',
    'transfers_hash' => '',
    'tasks_hash' => ''
];

// إرسال رسالة اتصال ناجح
echo "event: connected\n";
echo "data: {\"status\": \"connected\", \"time\": \"" . date('Y-m-d H:i:s') . "\"}\n\n";
if (ob_get_level()) ob_flush();
flush();

} catch (Exception $e) {
    // إرسال الخطأ كحدث
    echo "event: error\n";
    echo "data: {\"message\": \"" . addslashes($e->getMessage()) . "\"}\n\n";
    if (ob_get_level()) ob_flush();
    flush();
    exit();
}

// الحلقة الرئيسية للتحديثات
$counter = 0;
$maxIterations = 20; // 20 تكرار × 3 ثواني = دقيقة واحدة
while (true) {
    // كسر الاتصال بعد دقيقة (يعيد المتصفح الاتصال تلقائياً)
    // هذا يضمن تحديثات متكررة وسريعة
    if ($counter++ >= $maxIterations) {
        break;
    }
    
    try {
        // 1. التحقق من الإشعارات الجديدة (فقط إذا كان الجدول موجود)
        $notifications_count = 0;
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            $notifications_count = $result ? $result['count'] : 0;
        } catch (Exception $e) {
            // الجدول غير موجود - نتجاهل
            $notifications_count = 0;
        }
        
        if ($notifications_count != $lastData['notifications_count']) {
            echo "event: notifications\n";
            echo "data: {\"count\": $notifications_count}\n\n";
            if (ob_get_level()) ob_flush();
            flush();
            $lastData['notifications_count'] = $notifications_count;
        }
        
        // 2. إحصائيات لوحة التحكم
        $stats = [];
        
        if ($user_role === 'admin') {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM transfers");
            $stats['total_transfers'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM transfers WHERE status = 'pending'");
            $stats['pending_transfers'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM transfers WHERE status = 'in_transit'");
            $stats['in_transit'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM drivers WHERE is_available = 1");
            $stats['available_drivers'] = $stmt->fetch()['count'];
        } else {
            $branch_id = $_SESSION['branch_id'];
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transfers WHERE branch_id = ?");
            $stmt->execute([$branch_id]);
            $stats['total_transfers'] = $stmt->fetch()['count'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transfers WHERE branch_id = ? AND status = 'pending'");
            $stmt->execute([$branch_id]);
            $stats['pending_transfers'] = $stmt->fetch()['count'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transfers WHERE branch_id = ? AND status = 'in_transit'");
            $stmt->execute([$branch_id]);
            $stats['in_transit'] = $stmt->fetch()['count'];
            
            $stmt = $conn->query("SELECT COUNT(*) as count FROM drivers WHERE is_available = 1");
            $stats['available_drivers'] = $stmt->fetch()['count'];
        }
        
        $stats_hash = md5(json_encode($stats));
        if ($stats_hash != $lastData['stats_hash']) {
            echo "event: stats\n";
            echo "data: " . json_encode($stats) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();
            $lastData['stats_hash'] = $stats_hash;
        }
        
        // 3. تحديثات التحويلات
        try {
            if ($user_role === 'admin') {
                $stmt = $conn->query("SELECT id, status, created_at FROM transfers ORDER BY id DESC LIMIT 10");
        } else {
                $branch_id = $_SESSION['branch_id'];
                $stmt = $conn->prepare("SELECT id, status, created_at FROM transfers WHERE branch_id = ? ORDER BY id DESC LIMIT 10");
                $stmt->execute([$branch_id]);
            }
            $transfers = $stmt->fetchAll();
            $transfers_hash = md5(json_encode($transfers));
            
            if ($transfers_hash != $lastData['transfers_hash']) {
                echo "event: transfers\n";
                echo "data: {\"updated\": true, \"hash\": \"$transfers_hash\"}\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                $lastData['transfers_hash'] = $transfers_hash;
            }
        } catch (Exception $e) {
            // تجاهل
        }
        
        // 4. تحديثات المهام
        try {
            if ($user_role === 'admin') {
                $stmt = $conn->query("SELECT id, transfer_id, driver_id, assigned_at FROM driver_assignments ORDER BY id DESC LIMIT 10");
            } else {
                $branch_id = $_SESSION['branch_id'];
                $stmt = $conn->prepare("
                    SELECT da.id, da.transfer_id, da.driver_id, da.assigned_at 
                    FROM driver_assignments da
                    INNER JOIN transfers t ON da.transfer_id = t.id
                    WHERE t.branch_id = ?
                    ORDER BY da.id DESC LIMIT 10
                ");
                $stmt->execute([$branch_id]);
            }
            $tasks = $stmt->fetchAll();
            $tasks_hash = md5(json_encode($tasks));
            
            if ($tasks_hash != $lastData['tasks_hash']) {
                echo "event: tasks\n";
                echo "data: {\"updated\": true, \"hash\": \"$tasks_hash\"}\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                $lastData['tasks_hash'] = $tasks_hash;
            }
        } catch (Exception $e) {
            // تجاهل
        }
        
        // إرسال heartbeat كل 10 تحديثات (30 ثانية تقريباً)
        if ($counter % 10 == 0) {
            echo "event: heartbeat\n";
            echo "data: {\"time\": \"" . date('H:i:s') . "\"}\n\n";
            if (ob_get_level()) ob_flush();
            flush();
        }
        
    } catch (Exception $e) {
        // إرسال الخطأ بدون إيقاف الحلقة
        echo "event: error\n";
        echo "data: {\"message\": \"" . addslashes($e->getMessage()) . "\"}\n\n";
        if (ob_get_level()) ob_flush();
        flush();
        // استمر في المحاولة
    }
    
    // التحقق من انقطاع الاتصال
    if (connection_aborted()) {
        break;
    }
    
    // انتظار 3 ثواني قبل التحقق التالي
    sleep(3);
}

// إغلاق الاتصال
echo "event: close\n";
echo "data: {\"message\": \"تم إغلاق الاتصال\"}\n\n";
flush();
