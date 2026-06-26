<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !isWarehouseManager()) {
    redirect('views/dashboard/dashboard.php');
}

$success = '';
$error = '';
$edit_branch = null;

// تعديل فرع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_branch'])) {
    if ($_SESSION['role'] === 'warehouse_manager') {
        $error = 'ليس لديك صلاحية لتعديل الفروع';
    } else {
    $id = clean_input($_POST['id']);
    $name = clean_input($_POST['name']);
    $location = clean_input($_POST['location']);
    $phone = clean_input($_POST['phone']);
    
    $stmt = $conn->prepare("UPDATE branches SET name = ?, location = ?, phone = ? WHERE id = ?");
    
    if ($stmt->execute([$name, $location, $phone, $id])) {
        $success = 'تم تحديث بيانات الفرع بنجاح';
    } else {
        $error = 'حدث خطأ أثناء التحديث';
    }
    }
}

// إضافة فرع جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_branch'])) {
    if ($_SESSION['role'] === 'warehouse_manager') {
        $error = 'ليس لديك صلاحية لإضافة فروع';
    } else {
    $name = clean_input($_POST['name']);
    $location = clean_input($_POST['location']);
    $phone = clean_input($_POST['phone']);
    
    $stmt = $conn->prepare("INSERT INTO branches (name, location, phone) VALUES (?, ?, ?)");
    
    if ($stmt->execute([$name, $location, $phone])) {
        $success = 'تم إضافة الفرع بنجاح';
    } else {
        $error = 'حدث خطأ أثناء الإضافة';
    }
    }
}

// حذف فرع
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_SESSION['role'] === 'warehouse_manager') {
        $error = 'ليس لديك صلاحية لحذف الفروع';
    } else {
    $branch_id = $_GET['delete'];
    
    // التحقق من عدم وجود مستخدمين أو تحويلات مرتبطة بالفرع
    $check_users = $conn->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
    $check_users->execute([$branch_id]);
    $users_count = $check_users->fetchColumn();
    
    $check_transfers = $conn->prepare("SELECT COUNT(*) FROM transfers WHERE branch_id = ?");
    $check_transfers->execute([$branch_id]);
    $transfers_count = $check_transfers->fetchColumn();
    
    if ($users_count > 0 || $transfers_count > 0) {
        $error = 'لا يمكن حذف الفرع لوجود مستخدمين أو تحويلات مرتبطة به';
    } else {
        $stmt = $conn->prepare("DELETE FROM branches WHERE id = ?");
        if ($stmt->execute([$branch_id])) {
            $success = 'تم حذف الفرع بنجاح';
        } else {
            $error = 'حدث خطأ أثناء الحذف';
        }
    }
}
}

// جلب بيانات الفرع للتعديل
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_branch = $stmt->fetch();
}

// الحصول على الفروع مع إحصائيات
$stmt = $conn->query("
    SELECT b.*, 
           COUNT(DISTINCT u.id) as users_count,
           (SELECT COUNT(*) FROM transfers t2 
            WHERE t2.branch_id = b.id 
               OR (t2.branch_id IS NULL AND LOWER(TRIM(t2.from_location)) = LOWER(TRIM(b.name)))
           ) as transfers_count
    FROM branches b 
    LEFT JOIN users u ON b.id = u.branch_id
    GROUP BY b.id
    ORDER BY b.created_at DESC
");
$branches = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>إدارة الفروع</h1>
    <?php if ($_SESSION['role'] !== 'warehouse_manager'): ?>
    <button class="btn btn-primary" onclick="openModal('addBranchModal')">+ إضافة فرع</button>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="table-responsive">
    <?php if (empty($branches)): ?>
    <!-- حالة فارغة: لا توجد فروع -->
    <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-building" style="font-size: 32px; color: #6366f1;"></i>
        </div>
        <h3 style="margin: 0 0 8px; color: #1e293b; font-size: 1.15rem;">لا توجد فروع بعد</h3>
        <p style="color: #94a3b8; font-size: 0.9rem; margin: 0 0 20px;">ابدأ بإضافة أول فرع لنظامك لتتمكن من إدارة التحويلات والمهام.</p>
        <?php if ($_SESSION['role'] !== 'warehouse_manager'): ?>
        <button class="btn btn-primary" onclick="openModal('addBranchModal')" style="border-radius: 10px; padding: 10px 28px;">
            <i class="fas fa-plus"></i> إضافة أول فرع
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>اسم الفرع</th>
                <th>الموقع</th>
                <th>الهاتف</th>
                <th>جروب الواتساب</th>
                <th>عدد المستخدمين</th>
                <th>عدد التحويلات</th>
                <th>تاريخ الإنشاء</th>
                <?php if ($_SESSION['role'] !== 'warehouse_manager'): ?>
                <th>إجراءات</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($branches as $branch): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($branch['name']); ?></strong></td>
                <td>
                    <?php if (!empty($branch['location'])): ?>
                        <?php echo htmlspecialchars($branch['location']); ?>
                    <?php else: ?>
                        <span style="color: #cbd5e1; font-size: 0.85rem;">لم يُحدد</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($branch['phone'])): ?>
                        <?php echo htmlspecialchars($branch['phone']); ?>
                    <?php else: ?>
                        <span style="color: #cbd5e1; font-size: 0.85rem;">لم يُحدد</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($branch['whatsapp_group_id'])): ?>
                        <span class="status-badge status-delivered"><i class="fab fa-whatsapp"></i> مخصص</span>
                    <?php else: ?>
                        <span class="status-badge status-pending">عام</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($branch['users_count'] > 0): ?>
                        <span class="status-badge status-assigned"><?php echo $branch['users_count']; ?></span>
                    <?php else: ?>
                        <span style="color: #cbd5e1; font-size: 0.82rem;">لا يوجد</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($branch['transfers_count'] > 0): ?>
                        <span class="status-badge status-in_transit"><?php echo $branch['transfers_count']; ?></span>
                    <?php else: ?>
                        <span style="color: #cbd5e1; font-size: 0.82rem;">لا يوجد</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('Y-m-d', strtotime($branch['created_at'])); ?></td>
                <?php if ($_SESSION['role'] !== 'warehouse_manager'): ?>
                <td class="actions">
                    <a href="branches.php?edit=<?php echo $branch['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                    <a href="branches.php?delete=<?php echo $branch['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الفرع؟')">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- مودال إضافة فرع -->
<div id="addBranchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-building"></i> إضافة فرع جديد</h3>
            <span class="close" onclick="closeModal('addBranchModal')">&times;</span>
        </div>
        
        <div class="modal-body">
            <form method="POST" action="" id="addBranchForm">
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        <span>معلومات الفرع</span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> اسم الفرع <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> الموقع <span class="required">*</span></label>
                            <input type="text" name="location" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="submit" form="addBranchForm" name="add_branch" class="btn btn-primary">
                <i class="fas fa-save"></i> حفظ
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('addBranchModal')">
                <i class="fas fa-times"></i> إلغاء
            </button>
        </div>
    </div>
</div>

<!-- مودال تعديل فرع -->
<?php if ($edit_branch): ?>
<div id="editBranchModal" class="modal active">
    <div class="modal-content modal-edit">
        <div class="modal-header modal-header-edit">
            <div class="modal-header-content">
                <div class="modal-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3>تعديل بيانات الفرع</h3>
            </div>
            <button class="modal-close" onclick="window.location.href='branches.php'">&times;</button>
        </div>
        
        <div class="modal-body">
            <form method="POST" action="" id="editBranchForm">
                <input type="hidden" name="id" value="<?php echo $edit_branch['id']; ?>">
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        <span>معلومات الفرع</span>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> اسم الفرع <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_branch['name']); ?>" placeholder="اسم الفرع" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> الموقع <span class="required">*</span></label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($edit_branch['location']); ?>" placeholder="موقع الفرع" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> رقم الهاتف</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($edit_branch['phone']); ?>" placeholder="رقم التواصل">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="submit" form="editBranchForm" name="edit_branch" class="btn btn-primary">
                <i class="fas fa-check"></i> تحديث البيانات
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='branches.php'">
                <i class="fas fa-times"></i> إلغاء
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// إغلاق المودال عند النقر خارجه
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
