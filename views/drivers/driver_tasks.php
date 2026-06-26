<?php
/**
 * صفحة مهام المشيك - عرض جميع المهام قيد التنفيذ
 */
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn()) {
    redirect('views/auth/login.php');
}

// التحقق من أن المستخدم مشيك
if ($_SESSION['role'] != 'driver') {
    redirect('views/dashboard/dashboard.php');
}

$success_msg = '';
$error_msg = '';

// تحديث حالة المهمة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $task_id = intval($_POST['task_id']);
    $new_status = clean_input($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $task_id])) {
        $success_msg = 'تم تأكيد إكمال المهمة بنجاح';
        
        // إذا تم إكمال المهمة، نضيف تاريخ الإكمال ونجعل السائق متاحاً
        if ($new_status == 'completed') {
            $stmt = $conn->prepare("UPDATE tasks SET completed_at = NOW() WHERE id = ?");
            $stmt->execute([$task_id]);
            
            // إعادة السائق ليكون متاحاً (يتم تلقائياً عند عدم وجود مهام نشطة)
        }
    } else {
        $error_msg = 'حدث خطأ أثناء التحديث';
    }
}

// الحصول على المهام قيد التنفيذ المعينة لسائقين وتخص فرع المشيك
$stmt = $conn->prepare("
    SELECT t.*, 
           u2.full_name as assigned_by_name,
           b.name as branch_name,
           tda.created_at as assigned_at,
           d.name as driver_name
    FROM tasks t 
    LEFT JOIN users u2 ON t.assigned_by = u2.id
    LEFT JOIN branches b ON t.branch_id = b.id
    INNER JOIN task_driver_assignments tda ON t.id = tda.task_id
    LEFT JOIN drivers d ON tda.driver_id = d.id
    WHERE t.status = 'in_progress' AND t.branch_id = ?
    ORDER BY 
        CASE t.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        t.created_at DESC
");
$stmt->execute([$_SESSION['branch_id']]);
$tasks = $stmt->fetchAll();

// إحصائيات المهام
$stats = [
    'total' => count($tasks)
];

include '../../includes/header.php';
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    margin: 8px 0;
}

.stat-label {
    color: #666;
    font-size: 13px;
}

.priority-urgent { color: #dc3545; font-weight: bold; }
.priority-high { color: #fd7e14; font-weight: bold; }
.priority-medium { color: #ffc107; font-weight: bold; }
.priority-low { color: #28a745; font-weight: bold; }

.overdue {
    color: #dc3545;
    font-weight: bold;
}

.text-muted {
    color: #999;
    font-style: italic;
}
</style>

<div class="page-header">
    <h1>📋 المهام الجارية</h1>
    <div>
        <span class="status-badge status-in_progress">المشيك: <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success"><?php echo $success_msg; ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert-error"><?php echo $error_msg; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- إحصائيات سريعة -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number" style="color: #007bff;"><?php echo $stats['total']; ?></div>
        <div class="stat-label">إجمالي المهام الجارية</div>
    </div>
</div>

<!-- جدول المهام -->
<div class="content-section">
    <?php if (count($tasks) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>العنوان</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>الفرع</th>
                        <th>السائق</th>
                        <th>تاريخ التعيين</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars(mb_substr($task['title'], 0, 50)) . (mb_strlen($task['title']) > 50 ? '...' : ''); ?></strong>
                            <?php if ($task['description']): ?>
                                <br><small style="color: #666;"><?php echo htmlspecialchars(mb_substr($task['description'], 0, 40)) . (mb_strlen($task['description']) > 40 ? '...' : ''); ?></small>
                            <?php endif; ?>
                            <?php if ($task['notes']): ?>
                                <br><small style="color: #007bff;">📝 <?php echo htmlspecialchars(mb_substr($task['notes'], 0, 30)) . (mb_strlen($task['notes']) > 30 ? '...' : ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="priority-<?php echo $task['priority']; ?>">
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
                        </td>
                        <td>
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
                        </td>
                        <td><?php echo $task['branch_name'] ? htmlspecialchars($task['branch_name']) : '<span class="text-muted">-</span>'; ?></td>
                        <td><?php echo htmlspecialchars($task['driver_name'] ?? '-'); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($task['assigned_at'])); ?></td>
                        <td class="actions">
                            <button onclick="openStatusModal(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success" title="تأكيد الاستلام">
                                <i class="fas fa-check"></i> تأكيد الاستلام
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div style="font-size: 60px; margin-bottom: 20px;">📋</div>
            <h3>لا توجد مهام معينة لك</h3>
            <p class="text-muted">لم يتم تعيين أي مهام لك حتى الآن</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal تحديث الحالة -->
<div id="statusModal" class="modal">
    <div class="modal-content modal-edit" style="max-width: 480px;">
        <div class="modal-header modal-header-edit">
            <div class="modal-header-content">
                <div class="modal-icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <h3>تأكيد استلام المهمة</h3>
            </div>
            <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
        </div>
        
        <div class="modal-body">
            <form method="POST" action="" id="statusForm">
                <input type="hidden" name="task_id" id="modalTaskId">
                <input type="hidden" name="update_status" value="1">
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        <span>اختر الحالة الجديدة</span>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-clipboard-check"></i>
                            الحالة الجديدة
                            <span class="required">*</span>
                        </label>
                        <select name="status" id="modalStatus" class="form-control" required>
                            <option value="completed" selected>تم الاستلام</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="submit" form="statusForm" class="btn btn-primary">
                <i class="fas fa-check"></i> تأكيد الاستلام
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">
                <i class="fas fa-times"></i> إلغاء
            </button>
        </div>
    </div>
</div>

<script>
// فتح نافذة تأكيد الاستلام
function openStatusModal(taskId) {
    document.getElementById('modalTaskId').value = taskId;
    document.getElementById('statusModal').classList.add('active');
}

// إغلاق النافذة المنبثقة
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// إغلاق النافذة عند الضغط خارجها
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (event.target == modal) {
            modal.classList.remove('active');
        }
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
