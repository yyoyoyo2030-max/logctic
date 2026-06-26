<?php
/**
 * صفحة تعديل مهمة
 */
require_once '../../config/config.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/views/auth/login.php');
    exit;
}

// مسئول السائقين لا يملك صلاحية الوصول لهذه الصفحة
if ($_SESSION['role'] === 'drivers_manager') {
    header('Location: ' . SITE_URL . '/views/dashboard/dashboard.php');
    exit;
}

$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$task_id) {
    header('Location: tasks.php');
    exit;
}

// الحصول على معلومات المهمة
$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: tasks.php');
    exit;
}

// التحقق من الصلاحيات (المدير أو من قام بإنشاء المهمة فقط يمكنه التعديل)
if (!canManageAllBranches() && $task['assigned_by'] != $_SESSION['user_id']) {
    header('Location: tasks.php');
    exit;
}

$errors = [];
$success_msg = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
    $priority = $_POST['priority'];
    $status = $_POST['status'];

    // التحقق من صحة البيانات
    if (empty($title)) {
        $errors[] = "عنوان المهمة مطلوب";
    }

    if (empty($priority)) {
        $errors[] = "أولوية المهمة مطلوبة";
    }

    if (empty($status)) {
        $errors[] = "حالة المهمة مطلوبة";
    }

    // إذا لم تكن هناك أخطاء، نحدث المهمة
    if (empty($errors)) {
        try {
            // إذا تم تغيير الحالة إلى مكتملة، نضيف تاريخ الإكمال
            if ($status == 'completed' && $task['status'] != 'completed') {
                $stmt = $conn->prepare("UPDATE tasks 
                                      SET title = ?, description = ?, branch_id = ?, 
                                          priority = ?, status = ?, completed_at = NOW() 
                                      WHERE id = ?");
                $stmt->execute([
                    $title,
                    $description,
                    $branch_id,
                    $priority,
                    $status,
                    $task_id
                ]);
            } else {
                $stmt = $conn->prepare("UPDATE tasks 
                                      SET title = ?, description = ?, branch_id = ?, 
                                          priority = ?, status = ? 
                                      WHERE id = ?");
                $stmt->execute([
                    $title,
                    $description,
                    $branch_id,
                    $priority,
                    $status,
                    $task_id
                ]);
            }

            $success_msg = "تم تحديث المهمة بنجاح";
            
            // تحديث بيانات المهمة المعروضة
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            // إعادة توجيه بعد 2 ثانية
            header("refresh:2;url=tasks.php");
        } catch (PDOException $e) {
            $errors[] = "حدث خطأ أثناء تحديث المهمة: " . $e->getMessage();
        }
    }
}

// الحصول على قائمة المستخدمين
$users_stmt = $conn->query("SELECT id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name");
$users = $users_stmt->fetchAll();

// الحصول على قائمة الفروع
$branches_stmt = $conn->query("SELECT id, name FROM branches ORDER BY name");
$branches = $branches_stmt->fetchAll();

include '../../includes/header.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group label .required {
    color: #dc3545;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    font-family: 'Cairo', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.priority-options, .status-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

.priority-option, .status-option {
    position: relative;
}

.priority-option input[type="radio"],
.status-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.priority-option label,
.status-option label {
    display: block;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.priority-option input[type="radio"]:checked + label,
.status-option input[type="radio"]:checked + label {
    border-color: #007bff;
    background: #e7f3ff;
}

.priority-option.urgent input[type="radio"]:checked + label {
    border-color: #dc3545;
    background: #ffe7e7;
    color: #dc3545;
}

.priority-option.high input[type="radio"]:checked + label {
    border-color: #fd7e14;
    background: #fff3e7;
    color: #fd7e14;
}

.priority-option.medium input[type="radio"]:checked + label {
    border-color: #ffc107;
    background: #fffbe7;
    color: #c79100;
}

.priority-option.low input[type="radio"]:checked + label {
    border-color: #28a745;
    background: #e7ffe7;
    color: #28a745;
}

.status-option.pending input[type="radio"]:checked + label {
    border-color: #ffc107;
    background: #fffbe7;
    color: #c79100;
}

.status-option.in_progress input[type="radio"]:checked + label {
    border-color: #17a2b8;
    background: #e7f7f9;
    color: #17a2b8;
}

.status-option.completed input[type="radio"]:checked + label {
    border-color: #28a745;
    background: #e7ffe7;
    color: #28a745;
}

.status-option.cancelled input[type="radio"]:checked + label {
    border-color: #6c757d;
    background: #f0f0f0;
    color: #6c757d;
}

.form-help {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

/* دعم الوضع الليلي لخيارات الأولوية والحالة */
[data-theme="dark"] .priority-option.urgent input[type="radio"]:checked + label {
    background: rgba(220, 53, 69, 0.15);
}
[data-theme="dark"] .priority-option.high input[type="radio"]:checked + label {
    background: rgba(253, 126, 20, 0.15);
}
[data-theme="dark"] .priority-option.medium input[type="radio"]:checked + label {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
}
[data-theme="dark"] .priority-option.low input[type="radio"]:checked + label {
    background: rgba(40, 167, 69, 0.15);
}
[data-theme="dark"] .status-option.pending input[type="radio"]:checked + label {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
}
[data-theme="dark"] .status-option.in_progress input[type="radio"]:checked + label {
    background: rgba(23, 162, 184, 0.15);
}
[data-theme="dark"] .status-option.completed input[type="radio"]:checked + label {
    background: rgba(40, 167, 69, 0.15);
}
[data-theme="dark"] .status-option.cancelled input[type="radio"]:checked + label {
    background: rgba(108, 117, 125, 0.15);
}
[data-theme="dark"] .form-help {
    color: #94a3b8;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
}

.alert-error {
    background: #ffe7e7;
    color: #dc3545;
    border: 1px solid #ffc1c1;
}

.alert-success {
    background: #e7ffe7;
    color: #28a745;
    border: 1px solid #b3ffb3;
}

.alert ul {
    margin: 0;
    padding-right: 20px;
}
</style>

<div class="form-container">
    <div class="page-header">
        <h1>✏️ تعديل المهمة</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>⚠️ يرجى تصحيح الأخطاء التالية:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <strong>✅ <?php echo $success_msg; ?></strong>
            <p>سيتم إعادة توجيهك إلى صفحة المهام...</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>
                عنوان المهمة <span class="required">*</span>
            </label>
            <input type="text" name="title" class="form-control" 
                   placeholder="أدخل عنوان المهمة" 
                   value="<?php echo htmlspecialchars($task['title']); ?>"
                   required>
        </div>

        <div class="form-group">
            <label>وصف المهمة</label>
            <textarea name="description" class="form-control" 
                      placeholder="أدخل تفاصيل المهمة والمتطلبات..."><?php echo htmlspecialchars($task['description']); ?></textarea>
            <div class="form-help">اكتب وصفاً تفصيلياً للمهمة والخطوات المطلوبة</div>
        </div>

        <div class="form-group">
            <label>
                الأولوية <span class="required">*</span>
            </label>
            <div class="priority-options">
                <div class="priority-option urgent">
                    <input type="radio" name="priority" id="priority_urgent" value="urgent" 
                           <?php echo $task['priority'] == 'urgent' ? 'checked' : ''; ?>>
                    <label for="priority_urgent">🔴 عاجل</label>
                </div>
                <div class="priority-option high">
                    <input type="radio" name="priority" id="priority_high" value="high"
                           <?php echo $task['priority'] == 'high' ? 'checked' : ''; ?>>
                    <label for="priority_high">🟠 عالية</label>
                </div>
                <div class="priority-option medium">
                    <input type="radio" name="priority" id="priority_medium" value="medium"
                           <?php echo $task['priority'] == 'medium' ? 'checked' : ''; ?>>
                    <label for="priority_medium">🟡 متوسطة</label>
                </div>
                <div class="priority-option low">
                    <input type="radio" name="priority" id="priority_low" value="low"
                           <?php echo $task['priority'] == 'low' ? 'checked' : ''; ?>>
                    <label for="priority_low">🟢 منخفضة</label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>
                الحالة <span class="required">*</span>
            </label>
            <div class="status-options">
                <div class="status-option pending">
                    <input type="radio" name="status" id="status_pending" value="pending" 
                           <?php echo $task['status'] == 'pending' ? 'checked' : ''; ?>>
                    <label for="status_pending">⏳ قيد الانتظار</label>
                </div>
                <div class="status-option in_progress">
                    <input type="radio" name="status" id="status_in_progress" value="in_progress"
                           <?php echo $task['status'] == 'in_progress' ? 'checked' : ''; ?>>
                    <label for="status_in_progress">🔄 قيد التنفيذ</label>
                </div>
                <div class="status-option completed">
                    <input type="radio" name="status" id="status_completed" value="completed"
                           <?php echo $task['status'] == 'completed' ? 'checked' : ''; ?>>
                    <label for="status_completed">✅ مكتملة</label>
                </div>
                <div class="status-option cancelled">
                    <input type="radio" name="status" id="status_cancelled" value="cancelled"
                           <?php echo $task['status'] == 'cancelled' ? 'checked' : ''; ?>>
                    <label for="status_cancelled">❌ ملغية</label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>الفرع</label>
            <select name="branch_id" class="form-control">
                <option value="">-- اختر الفرع --</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['id']; ?>"
                            <?php echo $task['branch_id'] == $branch['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-help">اختر الفرع المرتبط بهذه المهمة (اختياري)</div>
        </div>

        <div class="form-actions">
            <a href="tasks.php" class="btn btn-secondary">❌ إلغاء</a>
            <button type="submit" class="btn btn-primary">💾 حفظ التعديلات</button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
