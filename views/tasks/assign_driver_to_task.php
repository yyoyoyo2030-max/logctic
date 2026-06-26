<?php
/**
 * صفحة تعيين مشيك للمهمة
 */
require_once '../../config/config.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/views/auth/login.php');
    exit;
}

$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$task_id) {
    header('Location: tasks.php');
    exit;
}

// الحصول على معلومات المهمة
$stmt = $conn->prepare("
    SELECT t.*, 
           u1.full_name as assigned_to_name,
           u2.full_name as assigned_by_name,
           b.name as branch_name
    FROM tasks t
    LEFT JOIN users u1 ON t.assigned_to = u1.id
    LEFT JOIN users u2 ON t.assigned_by = u2.id
    LEFT JOIN branches b ON t.branch_id = b.id
    WHERE t.id = ?
");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: tasks.php');
    exit;
}

// الحصول على السائقين المعينين حالياً
$stmt = $conn->prepare("
    SELECT tda.*, d.name as driver_name, d.phone, d.vehicle_type, d.vehicle_number,
           u.full_name as assigned_by_name
    FROM task_driver_assignments tda
    INNER JOIN drivers d ON tda.driver_id = d.id
    LEFT JOIN users u ON tda.assigned_by = u.id
    WHERE tda.task_id = ?
    ORDER BY tda.assigned_at DESC
");
$stmt->execute([$task_id]);
$assigned_drivers = $stmt->fetchAll();

$success_msg = '';
$error_msg = '';

// معالجة إضافة سائق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_driver'])) {
    $driver_id = intval($_POST['driver_id']);
    $notes = trim($_POST['notes']);

    if ($driver_id) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO task_driver_assignments (task_id, driver_id, assigned_by, notes)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$task_id, $driver_id, $_SESSION['user_id'], $notes]);
            
            // تحديث حالة المهمة والسائق
            $stmt_update = $conn->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ?");
            $stmt_update->execute([$task_id]);
            
            $stmt_avail = $conn->prepare("UPDATE drivers SET is_available = 0 WHERE id = ?");
            $stmt_avail->execute([$driver_id]);
            
            // إرسال رسالة واتساب للسائق
            $stmt_d = $conn->prepare("SELECT phone FROM drivers WHERE id = ?");
            $stmt_d->execute([$driver_id]);
            $d_info = $stmt_d->fetch();
            
            if ($d_info && !empty($d_info['phone'])) {
                require_once '../../api/whatsapp.php';
                $msg = "🌟 *إشعار نظام اللوجستيات* 🌟\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $msg .= "📋 *تم تكليفك بمهمة جديدة*\n\n";
                $msg .= "📌 *عنوان المهمة:* {$task['title']}\n";
                if (!empty($notes)) {
                    $msg .= "📝 *ملاحظات هامة:* {$notes}\n";
                }
                $msg .= "\n━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "🚀 نتمنى لك التوفيق، الرجاء البدء بتنفيذ المهمة.";
                
                // تجاهل النتيجة لأن الغرض إعلامي
                sendWhatsAppMessage($d_info['phone'], $msg);
                notifyCustomRoutes('driver_assigned', $msg);
            }
            
            $success_msg = "تم تعيين السائق للمهمة بنجاح";
            
            // تحديث الصفحة لإظهار السائق الجديد
            header("Location: assign_driver_to_task.php?id=$task_id&success=1");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error_msg = "هذا السائق معين للمهمة مسبقاً";
            } else {
                $error_msg = "حدث خطأ أثناء تعيين السائق";
            }
        }
    } else {
        $error_msg = "يرجى اختيار سائق";
    }
}

// معالجة حذف تعيين سائق
if (isset($_GET['remove']) && isset($_GET['assignment_id'])) {
    $assignment_id = intval($_GET['assignment_id']);
    
    $stmt = $conn->prepare("DELETE FROM task_driver_assignments WHERE id = ? AND task_id = ?");
    if ($stmt->execute([$assignment_id, $task_id])) {
        header("Location: assign_driver_to_task.php?id=$task_id&removed=1");
        exit;
    }
}

// الحصول على جميع السائقين المتاحين
$stmt = $conn->query("
    SELECT d.*, 
           t.status as current_status, 
           t.to_location as last_to_location,
           da.delivery_date as last_delivery_date
    FROM drivers d
    LEFT JOIN (
        SELECT driver_id, MAX(assigned_at) as last_assigned
        FROM driver_assignments
        GROUP BY driver_id
    ) latest_da ON d.id = latest_da.driver_id
    LEFT JOIN driver_assignments da ON da.driver_id = latest_da.driver_id AND da.assigned_at = latest_da.last_assigned
    LEFT JOIN transfers t ON da.transfer_id = t.id
    ORDER BY 
        CASE WHEN t.status IN ('assigned', 'in_transit') THEN 1 ELSE 0 END, 
        d.name
");
$all_drivers = $stmt->fetchAll();

if (isset($_GET['success'])) {
    $success_msg = "تم تعيين السائق للمهمة بنجاح";
}

if (isset($_GET['removed'])) {
    $success_msg = "تم إلغاء تعيين السائق من المهمة";
}

include '../../includes/header.php';
?>

<style>
.task-info-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.task-info-header {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.task-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.task-detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.task-detail-label {
    font-size: 13px;
    color: #666;
    font-weight: 500;
}

.task-detail-value {
    font-size: 15px;
    color: #333;
    font-weight: 600;
}

.assign-form {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.assigned-drivers-list {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.driver-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-right: 4px solid #007bff;
}

.driver-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.driver-name {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.driver-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-bottom: 10px;
}

.driver-info-item {
    font-size: 14px;
    color: #666;
}

.driver-notes {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #dee2e6;
    font-size: 14px;
    color: #666;
}

.no-drivers {
    text-align: center;
    padding: 40px;
    color: #999;
}
</style>

<div class="page-header">
    <h1>تعيين سائق للمهمة</h1>
    <a href="tasks.php" class="btn btn-secondary">← العودة للمهام</a>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success"><?php echo $success_msg; ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert-error"><?php echo $error_msg; ?></div>
<?php endif; ?>

<!-- معلومات المهمة -->
<div class="task-info-box">
    <div class="task-info-header">
        📋 <?php echo htmlspecialchars($task['title']); ?>
    </div>
    
    <div class="task-details">
        <div class="task-detail-item">
            <span class="task-detail-label">الحالة</span>
            <span class="task-detail-value">
                <span class="status-badge status-<?php echo $task['status']; ?>">
                    <?php 
                    $status_labels = [
                        'pending' => 'قيد الانتظار',
                        'in_progress' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        'cancelled' => 'ملغية'
                    ];
                    echo $status_labels[$task['status']];
                    ?>
                </span>
            </span>
        </div>
        
        <div class="task-detail-item">
            <span class="task-detail-label">الأولوية</span>
            <span class="task-detail-value priority-<?php echo $task['priority']; ?>">
                <?php 
                $priority_labels = [
                    'urgent' => '🔴 عاجل',
                    'high' => '🟠 عالية',
                    'medium' => '🟡 متوسطة',
                    'low' => '🟢 منخفضة'
                ];
                echo $priority_labels[$task['priority']];
                ?>
            </span>
        </div>
        
        <?php if ($task['assigned_to_name']): ?>
        <div class="task-detail-item">
            <span class="task-detail-label">مُخصص لـ</span>
            <span class="task-detail-value"><?php echo htmlspecialchars($task['assigned_to_name']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($task['branch_name']): ?>
        <div class="task-detail-item">
            <span class="task-detail-label">الفرع</span>
            <span class="task-detail-value"><?php echo htmlspecialchars($task['branch_name']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($task['due_date']): ?>
        <div class="task-detail-item">
            <span class="task-detail-label">موعد الاستحقاق</span>
            <span class="task-detail-value"><?php echo date('Y-m-d', strtotime($task['due_date'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($task['description']): ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f0;">
            <div class="task-detail-label">الوصف</div>
            <div style="margin-top: 8px; color: #666; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- نموذج تعيين سائق جديد -->
<div class="assign-form">
    <h2 style="margin-bottom: 20px;">➕ تعيين سائق جديد</h2>
    
    <form method="POST">
        <input type="hidden" name="assign_driver" value="1">
        
        <div class="form-group">
            <label>اختر السائق *</label>
            <div class="driver-selector-list">
                <?php foreach ($all_drivers as $driver): ?>
                    <?php 
                        $isInTransit = in_array($driver['current_status'], ['assigned', 'in_transit']); 
                        $lastTo = htmlspecialchars($driver['last_to_location'] ?? '');
                        $isRecommended = false;
                        
                        // For tasks, we only have branch_name in $task
                        if (!empty($lastTo) && !empty($task['branch_name']) && trim($driver['last_to_location']) == trim($task['branch_name'])) {
                            $isRecommended = true;
                        }
                    ?>
                    <label class="driver-card-radio <?php echo $isRecommended ? 'recommended' : ''; ?>">
                        <input type="radio" name="driver_id" value="<?php echo $driver['id']; ?>" required>
                        <div class="driver-card-content">
                            <div class="driver-header">
                                <div class="driver-name">
                                    <i class="fas fa-user-circle"></i>
                                    <?php echo htmlspecialchars($driver['name']); ?>
                                </div>
                                <?php if ($isRecommended): ?>
                                    <span class="badge-recommended"><i class="fas fa-star"></i> موصى به</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="driver-details">
                                <span class="vehicle-info">
                                    <i class="fas fa-truck"></i> <?php echo htmlspecialchars($driver['vehicle_number']); ?>
                                </span>
                                
                                <?php if ($isInTransit): ?>
                                    <span class="driver-status status-transit">
                                        <i class="fas fa-route"></i> متجه إلى: <?php echo ($lastTo ?: 'غير محدد'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="driver-status status-available">
                                        <i class="fas fa-check-circle"></i> متاح <?php echo $lastTo ? "(في: $lastTo)" : ""; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label>ملاحظات</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="أضف أي ملاحظات خاصة بهذا التعيين..."></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">✅ تعيين السائق</button>
        </div>
    </form>
</div>

<!-- قائمة السائقين المعينين -->
<div class="assigned-drivers-list">
    <h2 style="margin-bottom: 20px;">👥 السائقين المعينين (<?php echo count($assigned_drivers); ?>)</h2>
    
    <?php if (count($assigned_drivers) > 0): ?>
        <?php foreach ($assigned_drivers as $assignment): ?>
            <div class="driver-card">
                <div class="driver-card-header">
                    <div class="driver-name">
                        🚗 <?php echo htmlspecialchars($assignment['driver_name']); ?>
                    </div>
                    <a href="?id=<?php echo $task_id; ?>&remove=1&assignment_id=<?php echo $assignment['id']; ?>" 
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('هل أنت متأكد من إلغاء تعيين هذا السائق؟')">
                        إلغاء التعيين
                    </a>
                </div>
                
                <div class="driver-info">
                    <div class="driver-info-item">
                        📞 الهاتف: <strong><?php echo htmlspecialchars($assignment['phone']); ?></strong>
                    </div>
                    <?php if ($assignment['vehicle_type']): ?>
                        <div class="driver-info-item">
                            🚙 نوع المركبة: <strong><?php echo htmlspecialchars($assignment['vehicle_type']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($assignment['vehicle_number']): ?>
                        <div class="driver-info-item">
                            🔢 رقم المركبة: <strong><?php echo htmlspecialchars($assignment['vehicle_number']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="driver-info-item">
                        👤 معين بواسطة: <strong><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></strong>
                    </div>
                    <div class="driver-info-item">
                        🕒 تاريخ التعيين: <strong><?php echo date('Y-m-d H:i', strtotime($assignment['assigned_at'])); ?></strong>
                    </div>
                </div>
                
                <?php if ($assignment['notes']): ?>
                    <div class="driver-notes">
                        <strong>📝 ملاحظات:</strong><br>
                        <?php echo nl2br(htmlspecialchars($assignment['notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-drivers">
            <div style="font-size: 50px; margin-bottom: 15px;">🚫</div>
            <p>لم يتم تعيين أي سائق لهذه المهمة بعد</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const select = this.querySelector('select[name="driver_id"]');
            if (select) {
                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption && selectedOption.dataset.inTransit === 'true') {
                    if (!confirm('هذا السائق في مهمة حالياً (جاري التوصيل)، هل أنت متأكد من رغبتك في إسناد هذه المهمة له أيضاً (كشحن عكسي مثلاً)؟')) {
                        e.preventDefault();
                    }
                }
            }
        });
    });
});
</script>
