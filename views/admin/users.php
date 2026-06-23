<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('views/dashboard/dashboard.php');
}

$success = '';
$error = '';
$edit_user = null;

// تعديل مستخدم
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    try {
        $id = clean_input($_POST['id']);
        $username = clean_input($_POST['username']);
        $full_name = clean_input($_POST['full_name']);
        $phone = clean_input($_POST['phone']);
        $role = clean_input($_POST['role']);
        $branch_id = !empty($_POST['branch_id']) ? clean_input($_POST['branch_id']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // تحديث كلمة المرور إذا تم إدخالها
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, phone = ?, role = ?, branch_id = ?, is_active = ? WHERE id = ?");
            $result = $stmt->execute([$username, $password, $full_name, $phone, $role, $branch_id, $is_active, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, phone = ?, role = ?, branch_id = ?, is_active = ? WHERE id = ?");
            $result = $stmt->execute([$username, $full_name, $phone, $role, $branch_id, $is_active, $id]);
        }
        
        if ($result) {
            $success = 'تم تحديث بيانات المستخدم بنجاح';
        } else {
            $error = 'حدث خطأ أثناء التحديث';
        }
    } catch (Exception $e) {
        error_log('Edit User Error: ' . $e->getMessage());
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// إضافة مستخدم جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    try {
        $username = clean_input($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = clean_input($_POST['full_name']);
        $phone = clean_input($_POST['phone']);
        $role = clean_input($_POST['role']);
        $branch_id = !empty($_POST['branch_id']) ? clean_input($_POST['branch_id']) : null;
        
        // التحقق من عدم تكرار اسم المستخدم
        $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->execute([$username]);
        
        if ($check->fetchColumn() > 0) {
            $error = 'اسم المستخدم موجود مسبقاً';
        } else {
            // التحقق من وجود الفرع إذا تم تحديده
            if ($branch_id !== null) {
                $checkBranch = $conn->prepare("SELECT COUNT(*) FROM branches WHERE id = ?");
                $checkBranch->execute([$branch_id]);
                
                if ($checkBranch->fetchColumn() == 0) {
                    $error = 'الفرع المحدد غير موجود. يرجى اختيار فرع صحيح أو ترك الحقل فارغاً';
                    throw new Exception($error);
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, phone, role, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $password, $full_name, $phone, $role, $branch_id])) {
                $success = 'تم إضافة المستخدم بنجاح';
                // إعادة تعيين القيم
                $_POST = [];
            } else {
                $error = 'حدث خطأ أثناء الإضافة';
            }
        }
    } catch (Exception $e) {
        error_log('Add User Error: ' . $e->getMessage());
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// حذف مستخدم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $user_id = $_GET['delete'];
        
        // منع حذف المستخدم الحالي
        if ($user_id == $_SESSION['user_id']) {
            $error = 'لا يمكنك حذف حسابك الخاص';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'تم حذف المستخدم بنجاح';
            } else {
                $error = 'حدث خطأ أثناء الحذف';
            }
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $error = 'لا يمكن حذف هذا المستخدم لارتباطه بعمليات (تحويلات/مهام) في النظام. يرجى "تعطيل" الحساب بدلاً من حذفه.';
        } else {
            error_log('Delete User Error: ' . $e->getMessage());
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        error_log('Delete User Error: ' . $e->getMessage());
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// تغيير حالة المستخدم
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    try {
        $user_id = $_GET['toggle'];
        
        if ($user_id == $_SESSION['user_id']) {
            $error = 'لا يمكنك تعطيل حسابك الخاص';
        } else {
            $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$user_id]);
            redirect('views/admin/users.php');
        }
    } catch (Exception $e) {
        error_log('Toggle User Error: ' . $e->getMessage());
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// جلب بيانات المستخدم للتعديل
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

// الحصول على المستخدمين
$stmt = $conn->query("SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

// الحصول على الفروع
$branches = $conn->query("SELECT * FROM branches ORDER BY name")->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>إدارة المستخدمين</h1>
    <button class="btn btn-primary" onclick="openAddModal()">+ إضافة مستخدم</button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>اسم المستخدم</th>
                <th>الاسم الكامل</th>
                <th>رقم الهاتف</th>
                <th>الدور</th>
                <th>الفرع</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($user['username'] ?? ''); ?></strong></td>
                <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                <td dir="ltr" style="text-align: right;"><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                <td>
                    <span class="status-badge <?php echo $user['role'] == 'admin' ? 'status-delivered' : 'status-assigned'; ?>">
                        <?php
                        $roleNames = [
                            'admin' => 'مدير النظام',
                            'logistics_manager' => 'مدير لوجستك',
                            'warehouse_manager' => 'مسئول مستودعات',
                            'drivers_manager' => 'مسؤول سائقين',
                            'branch_entry' => 'مدخل بيانات فرع',
                            'warehouse_entry' => 'مدخل بيانات مستودع',
                            'driver' => 'مشيك'
                        ];
                        echo isset($roleNames[$user['role']]) ? $roleNames[$user['role']] : $user['role'];
                        ?>
                    </span>
                </td>
                <td><?php echo $user['branch_name'] ? htmlspecialchars($user['branch_name']) : '-'; ?></td>
                <td>
                    <?php if ($user['is_active']): ?>
                        <span class="status-badge status-assigned">نشط</span>
                    <?php else: ?>
                        <span class="status-badge status-cancelled">معطل</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="users.php?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                    <a href="users.php?toggle=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                        <?php echo $user['is_active'] ? 'تعطيل' : 'تفعيل'; ?>
                    </a>
                    <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- مودال إضافة مستخدم -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> إضافة مستخدم جديد</h3>
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        
        <form method="POST" action="">
            <div class="modal-body">
                <!-- القسم الأول: بيانات تسجيل الدخول -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-lock"></i>
                        بيانات تسجيل الدخول
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-user-circle"></i>
                                اسم المستخدم
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="username" class="form-control" placeholder="اسم تسجيل الدخول" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-key"></i>
                                كلمة المرور
                                <span class="required">*</span>
                            </label>
                            <input type="password" name="password" class="form-control" placeholder="كلمة المرور" required>
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثاني: المعلومات الشخصية -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-id-card"></i>
                        المعلومات الشخصية
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-user"></i>
                                الاسم الكامل
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control" placeholder="الاسم الكامل" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-phone"></i>
                                رقم الهاتف
                            </label>
                            <input type="tel" name="phone" class="form-control" placeholder="05xxxxxxxx" pattern="^0[0-9]{9}$" title="يجب أن يبدأ بـ 0 ويتكون من 10 أرقام (مثل: 05xxxxxxxx)">
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثالث: الصلاحيات -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user-shield"></i>
                        الصلاحيات والفرع
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-shield-alt"></i>
                                الدور
                                <span class="required">*</span>
                            </label>
                            <select name="role" class="form-control" required onchange="toggleBranchSelect(this.value)">
                                <option value="">-- اختر الدور --</option>
                                <option value="admin">مدير النظام</option>
                                <option value="logistics_manager">مدير لوجستك</option>
                                <option value="warehouse_manager">مسئول مستودعات</option>
                                <option value="drivers_manager">مسؤول سائقين</option>
                                <option value="branch_entry">مدخل بيانات فرع</option>
                                <option value="warehouse_entry">مدخل بيانات مستودع</option>
                                <option value="driver">مشيك</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="branchSelectGroup" style="display: none;">
                            <label>
                                <i class="fas fa-building"></i>
                                الفرع
                                <span class="required">*</span>
                            </label>
                            <select name="branch_id" id="branch_id_select" class="form-control">
                                <option value="">-- بدون فرع --</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fas fa-save"></i> إضافة المستخدم
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    const modal = document.getElementById('addUserModal');
    // تفريغ جميع حقول النموذج
    document.querySelector('#addUserModal form').reset();
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAddModal() {
    const modal = document.getElementById('addUserModal');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

// إغلاق عند النقر خارج Modal
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// إغلاق المودال تلقائياً عند النجاح
<?php if ($success && !isset($_GET['edit'])): ?>
closeAddModal();
setTimeout(function() {
    window.location.href = 'users.php';
}, 1500);
<?php endif; ?>
function toggleBranchSelect(role, isEdit = false) {
    const rolesWithBranch = ['warehouse_entry', 'branch_entry', 'driver'];
    const branchGroup = document.getElementById(isEdit ? 'editBranchSelectGroup' : 'branchSelectGroup');
    const branchSelect = document.getElementById(isEdit ? 'edit_branch_id_select' : 'branch_id_select');
    
    if (rolesWithBranch.includes(role)) {
        branchGroup.style.display = 'block';
        branchSelect.setAttribute('required', 'required');
    } else {
        branchGroup.style.display = 'none';
        branchSelect.removeAttribute('required');
        branchSelect.value = '';
    }
}
</script>

<!-- مودال تعديل مستخدم -->
<?php if ($edit_user): ?>
<div id="editUserModal" class="modal" style="display: block;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> تعديل بيانات المستخدم</h3>
            <button class="modal-close" onclick="window.location.href='users.php'">&times;</button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
            
            <div class="modal-body">
                <!-- القسم الأول: بيانات تسجيل الدخول -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-lock"></i>
                        بيانات تسجيل الدخول
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-user-circle"></i>
                                اسم المستخدم
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-key"></i>
                                كلمة المرور الجديدة
                            </label>
                            <input type="password" name="password" class="form-control" placeholder="اتركها فارغة للإبقاء على القديمة">
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثاني: المعلومات الشخصية -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-id-card"></i>
                        المعلومات الشخصية
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-user"></i>
                                الاسم الكامل
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-phone"></i>
                                رقم الهاتف
                            </label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($edit_user['phone'] ?? ''); ?>" placeholder="05xxxxxxxx" pattern="^0[0-9]{9}$" title="يجب أن يبدأ بـ 0 ويتكون من 10 أرقام (مثل: 05xxxxxxxx)">
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثالث: الصلاحيات -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user-shield"></i>
                        الصلاحيات والفرع
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-shield-alt"></i>
                                الدور
                                <span class="required">*</span>
                            </label>
                            <select name="role" class="form-control" required onchange="toggleBranchSelect(this.value, true)">
                                <option value="admin" <?php echo $edit_user['role'] == 'admin' ? 'selected' : ''; ?>>مدير النظام</option>
                                <option value="logistics_manager" <?php echo $edit_user['role'] == 'logistics_manager' ? 'selected' : ''; ?>>مدير لوجستك</option>
                                <option value="warehouse_manager" <?php echo $edit_user['role'] == 'warehouse_manager' ? 'selected' : ''; ?>>مسئول مستودعات</option>
                                <option value="drivers_manager" <?php echo $edit_user['role'] == 'drivers_manager' ? 'selected' : ''; ?>>مسؤول سائقين</option>
                                <option value="branch_entry" <?php echo $edit_user['role'] == 'branch_entry' ? 'selected' : ''; ?>>مدخل بيانات فرع</option>
                                <option value="warehouse_entry" <?php echo $edit_user['role'] == 'warehouse_entry' ? 'selected' : ''; ?>>مدخل بيانات مستودع</option>
                                <option value="driver" <?php echo $edit_user['role'] == 'driver' ? 'selected' : ''; ?>>مشيك</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="editBranchSelectGroup" style="display: <?php echo in_array($edit_user['role'], ['warehouse_entry', 'branch_entry', 'driver']) ? 'block' : 'none'; ?>;">
                            <label>
                                <i class="fas fa-building"></i>
                                الفرع
                                <span class="required">*</span>
                            </label>
                            <select name="branch_id" id="edit_branch_id_select" class="form-control" <?php echo in_array($edit_user['role'], ['warehouse_entry', 'branch_entry', 'driver']) ? 'required' : ''; ?>>
                                <option value="">-- اختر الفرع --</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>" <?php echo $edit_user['branch_id'] == $branch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group full-width" style="margin-top: 15px;">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="is_active" <?php echo $edit_user['is_active'] ? 'checked' : ''; ?>>
                            <span>حساب نشط (يمكنه تسجيل الدخول للنظام)</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='users.php'">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" name="edit_user" class="btn btn-primary">
                    <i class="fas fa-save"></i> تحديث بيانات المستخدم
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
