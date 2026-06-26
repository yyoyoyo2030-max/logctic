<?php
/**
 * صفحة إدارة المهام
 */
require_once '../../config/config.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/views/auth/login.php');
    exit;
}

// معالجة حذف مهمة
if (isset($_POST['delete_task'])) {
    if ($_SESSION['role'] === 'drivers_manager') {
        $error_msg = "ليس لديك صلاحية حذف المهام";
    } else {
        $task_id = $_POST['task_id'];
        
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        
        $success_msg = "تم حذف المهمة بنجاح";
    }
}

// معالجة تحديث حالة المهمة
if (isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status'];
    
    $update_data = [$new_status, $task_id];
    $update_query = "UPDATE tasks SET status = ?";
    
    // إذا تم إكمال المهمة، نضيف تاريخ الإكمال
    if ($new_status == 'completed') {
        $update_query .= ", completed_at = NOW()";
    }
    
    $update_query .= " WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->execute($update_data);
    
    $success_msg = "تم تحديث حالة المهمة بنجاح";
}

// الحصول على الفلاتر
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';

// بناء استعلام المهام - مع السائقين المعينين (مثل التحويلات)
$query = "SELECT t.*, 
          u.full_name as assigned_by_name,
          b.name as branch_name,
          GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as driver_names,
          COUNT(DISTINCT tda.driver_id) as drivers_count
          FROM tasks t
          LEFT JOIN users u ON t.assigned_by = u.id
          LEFT JOIN branches b ON t.branch_id = b.id
          LEFT JOIN task_driver_assignments tda ON t.id = tda.task_id
          LEFT JOIN drivers d ON tda.driver_id = d.id
          WHERE 1=1";

$params = [];

// إضافة الفلاتر
if (!empty($status_filter)) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
}

if (!empty($priority_filter)) {
    $query .= " AND t.priority = ?";
    $params[] = $priority_filter;
}

// إذا لم يكن مدير، نعرض فقط المهام التي أنشأها أو الخاصة بفرعه
if (!canManageAllBranches()) {
    $query .= " AND (t.assigned_by = ? OR t.branch_id = ?)";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['branch_id'];
}

$query .= " GROUP BY t.id
            ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            t.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// إحصائيات المهام
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
    FROM tasks";

if (!canManageAllBranches()) {
    $stats_query .= " WHERE branch_id = ? OR assigned_by = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$_SESSION['branch_id'], $_SESSION['user_id']]);
} else {
    $stmt = $conn->prepare($stats_query);
    $stmt->execute();
}
$stats = $stmt->fetch();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>إدارة المهام</h1>
    <?php if ($_SESSION['role'] !== 'drivers_manager'): ?>
    <a href="add_task.php" class="btn btn-primary">+ إضافة مهمة</a>
    <?php endif; ?>
</div>

<?php if (isset($success_msg)): ?>
    <div class="alert alert-success"><?php echo $success_msg; ?></div>
<?php endif; ?>

<!-- الإحصائيات -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number" style="color: #007bff;"><?php echo $stats['total']; ?></div>
        <div class="stat-label">إجمالي المهام</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="color: #ffc107;"><?php echo $stats['pending']; ?></div>
        <div class="stat-label">قيد الانتظار</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="color: #17a2b8;"><?php echo $stats['in_progress']; ?></div>
        <div class="stat-label">قيد التنفيذ</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="color: #28a745;"><?php echo $stats['completed']; ?></div>
        <div class="stat-label">مكتملة</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="color: #dc3545;"><?php echo $stats['urgent']; ?></div>
        <div class="stat-label">عاجلة</div>
    </div>
</div>

<!-- شريط الفلاتر -->
<div class="filters-section">
    <form method="GET" class="filters-form">
        <div class="filter-group">
            <label>الحالة:</label>
            <select name="status">
                <option value="">الكل</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>مكتملة</option>
                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>ملغية</option>
            </select>
        </div>

        <div class="filter-group">
            <label>الأولوية:</label>
            <select name="priority">
                <option value="">الكل</option>
                <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>عاجل</option>
                <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>عالية</option>
                <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>متوسطة</option>
                <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>منخفضة</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">تصفية</button>
        <a href="tasks.php" class="btn btn-secondary">إعادة تعيين</a>
    </form>
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
                        <th>السائقين</th>

                        <?php if (canManageAllBranches()): ?>
                        <th>الفرع</th>
                        <?php endif; ?>
                        <th>تاريخ الإنشاء</th>
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
                        <td>
                            <?php if ($task['driver_names']): ?>
                                <span class="status-badge status-assigned">
                                    🚗 <?php echo htmlspecialchars($task['driver_names']); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-pending">⚠️ لم يُعيّن</span>
                            <?php endif; ?>
                        </td>
                        <?php if (canManageAllBranches()): ?>
                        <td><?php echo $task['branch_name'] ? htmlspecialchars($task['branch_name']) : '<span class="text-muted">-</span>'; ?></td>
                        <?php endif; ?>
                        <td><?php echo date('Y-m-d', strtotime($task['created_at'])); ?></td>
                        <td class="actions">
                            <a href="assign_driver_to_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info" title="تعيين سائق">
                                🚗 سائق
                            </a>
                            
                            <?php if (canManageAllBranches() || $task['assigned_by'] == $_SESSION['user_id']): ?>
                                <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary" title="تعديل المهمة">
                                    ✏️ تعديل
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($task['status'] != 'completed' && $task['status'] != 'cancelled'): ?>
                                <form method="POST" style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" class="btn btn-sm btn-warning" onchange="this.form.submit()" title="تغيير الحالة" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; font-weight: 600;">
                                        <option value="">🔄 الحالة</option>
                                        <option value="in_progress">قيد التنفيذ</option>
                                        <option value="completed">مكتملة</option>
                                        <option value="cancelled">ملغية</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (($_SESSION['role'] !== 'drivers_manager') && (isLogisticsManager() || $task['assigned_by'] == $_SESSION['user_id'])): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذه المهمة؟');">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <button type="submit" name="delete_task" class="btn btn-sm btn-danger">حذف</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <img src="../../assets/images/empty-tasks.svg" alt="لا توجد مهام" style="width: 120px; opacity: 0.5; margin-bottom: 20px;">
            <h3 class="text-secondary">لا توجد مهام</h3>
            <p class="text-muted">لم يتم العثور على أي مهام حسب الفلاتر المحددة</p>
            <?php if ($_SESSION['role'] !== 'drivers_manager'): ?>
            <a href="add_task.php" class="btn btn-primary" style="margin-top: 15px;">+ إضافة مهمة</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
