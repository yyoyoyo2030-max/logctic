<?php
/**
 * صفحة إضافة مهمة جديدة
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

// المراقب لا يستطيع إضافة مهام


$errors = [];
$success_msg = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : $_SESSION['branch_id'];
    $priority = $_POST['priority'];

    // التحقق من صحة البيانات
    if (empty($title)) {
        $errors[] = "عنوان المهمة مطلوب";
    }

    if (empty($priority)) {
        $errors[] = "أولوية المهمة مطلوبة";
    }

    // إذا لم تكن هناك أخطاء، نضيف المهمة
    if (empty($errors)) {
        try {
            // إضافة المهمة بدون assigned_to (سيتم تعيين السائقين لاحقاً)
            $stmt = $conn->prepare("INSERT INTO tasks (title, description, assigned_by, branch_id, priority, status) 
                                    VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([
                $title,
                $description,
                $_SESSION['user_id'],
                $branch_id,
                $priority
            ]);

            // إرسال إشعار لمسؤولي السائقين
            require_once '../../api/whatsapp.php';
            $stmt_managers = $conn->query("SELECT phone FROM users WHERE role = 'drivers_manager' AND phone IS NOT NULL AND phone != ''");
            $managers = $stmt_managers->fetchAll();
            if (count($managers) > 0) {
                $priority_labels = [
                    'urgent' => '🔴 عاجل',
                    'high' => '🟠 عالية',
                    'medium' => '🟡 متوسطة',
                    'low' => '🟢 منخفضة'
                ];
                $msg = "🌟 *إشعار نظام اللوجستيات* 🌟\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
                $msg .= "📋 *مهمة جديدة بانتظار التعيين*\n\n";
                $msg .= "📌 *عنوان المهمة:* {$title}\n";
                $msg .= "⚠️ *درجة الأولوية:* {$priority_labels[$priority]}\n\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "👨‍💻 الرجاء الدخول للنظام لتعيين سائق في أسرع وقت.";
                foreach ($managers as $manager) {
                    sendWhatsAppMessage($manager['phone'], $msg);
                }
                notifyCustomRoutes('task_created', $msg);
            }

            $success_msg = "تم إضافة المهمة بنجاح. يمكنك الآن تعيين سائقين لها.";
            
            // إعادة توجيه بعد 2 ثانية
            header("refresh:2;url=tasks.php");
        } catch (PDOException $e) {
            $errors[] = "حدث خطأ أثناء إضافة المهمة: " . $e->getMessage();
        }
    }
}

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

.priority-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

.priority-option {
    position: relative;
}

.priority-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.priority-option label {
    display: block;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.priority-option input[type="radio"]:checked + label {
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

.form-help {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

/* دعم الوضع الليلي لخيارات الأولوية */
body.dark-mode .priority-option.urgent input[type="radio"]:checked + label {
    background: rgba(220, 53, 69, 0.15);
}
body.dark-mode .priority-option.high input[type="radio"]:checked + label {
    background: rgba(253, 126, 20, 0.15);
}
body.dark-mode .priority-option.medium input[type="radio"]:checked + label {
    background: rgba(255, 193, 7, 0.15);
    color: #ffc107;
}
body.dark-mode .priority-option.low input[type="radio"]:checked + label {
    background: rgba(40, 167, 69, 0.15);
}
body.dark-mode .form-help {
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
        <h1>➕ إضافة مهمة جديدة</h1>
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
                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                   required>
        </div>

        <div class="form-group">
            <label>وصف المهمة</label>
            <textarea name="description" class="form-control" 
                      placeholder="أدخل تفاصيل المهمة والمتطلبات..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            <div class="form-help">اكتب وصفاً تفصيلياً للمهمة والخطوات المطلوبة</div>
        </div>

        <div class="form-group">
            <label>
                الأولوية <span class="required">*</span>
            </label>
            <div class="priority-options">
                <div class="priority-option urgent">
                    <input type="radio" name="priority" id="priority_urgent" value="urgent" 
                           <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'checked' : ''; ?>>
                    <label for="priority_urgent">🔴 عاجل</label>
                </div>
                <div class="priority-option high">
                    <input type="radio" name="priority" id="priority_high" value="high"
                           <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'checked' : ''; ?>>
                    <label for="priority_high">🟠 عالية</label>
                </div>
                <div class="priority-option medium">
                    <input type="radio" name="priority" id="priority_medium" value="medium"
                           <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'checked' : ''; ?>>
                    <label for="priority_medium">🟡 متوسطة</label>
                </div>
                <div class="priority-option low">
                    <input type="radio" name="priority" id="priority_low" value="low"
                           <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'checked' : ''; ?>>
                    <label for="priority_low">🟢 منخفضة</label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>الفرع</label>
            <select name="branch_id" class="form-control">
                <option value="">-- اختر الفرع --</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo $branch['id']; ?>"
                            <?php echo (isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($branch['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-help">اختر الفرع المرتبط بهذه المهمة (اختياري)</div>
        </div>

        <div class="form-actions">
            <a href="tasks.php" class="btn btn-secondary">❌ إلغاء</a>
            <button type="submit" class="btn btn-primary">✅ حفظ المهمة</button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
