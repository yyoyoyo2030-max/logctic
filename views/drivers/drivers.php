<?php
/**
 * نظام إدارة اللوجستيك
 * إدارة السائقين
 * إضافة وتعديل وحذف السائقين
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !isDriversManager()) {
    redirect('views/dashboard/dashboard.php');
}

$success = '';
$error = '';
$edit_driver = null;

// تعديل سائق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_driver'])) {
    $id = clean_input($_POST['id']);
    $name = clean_input($_POST['name']);
    $phone = clean_input($_POST['phone']);
    $license_number = clean_input($_POST['license_number']);
    $vehicle_type = clean_input($_POST['vehicle_type']);
    $vehicle_number = clean_input($_POST['vehicle_number']);
    
    $stmt = $conn->prepare("UPDATE drivers SET name = ?, phone = ?, license_number = ?, vehicle_type = ?, vehicle_number = ? WHERE id = ?");
    
    if ($stmt->execute([$name, $phone, $license_number, $vehicle_type, $vehicle_number, $id])) {
        $success = 'تم تحديث بيانات السائق بنجاح';
    } else {
        $error = 'حدث خطأ أثناء التحديث';
    }
}

// إضافة سائق جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_driver'])) {
    $name = clean_input($_POST['name']);
    $phone = clean_input($_POST['phone']);
    $license_number = clean_input($_POST['license_number']);
    $vehicle_type = clean_input($_POST['vehicle_type']);
    $vehicle_number = clean_input($_POST['vehicle_number']);
    
    try {
        // إضافة السائق
        $stmt = $conn->prepare("INSERT INTO drivers (name, phone, license_number, vehicle_type, vehicle_number) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $license_number, $vehicle_type, $vehicle_number]);
        
        $success = 'تم إضافة السائق بنجاح';
    } catch (Exception $e) {
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// حذف سائق
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $driver_id = $_GET['delete'];
    
    // التحقق من عدم وجود تعيينات للسائق
    $check = $conn->prepare("SELECT COUNT(*) FROM driver_assignments WHERE driver_id = ?");
    $check->execute([$driver_id]);
    $count = $check->fetchColumn();
    
    if ($count > 0) {
        $error = 'لا يمكن حذف السائق لوجود تعيينات مرتبطة به';
    } else {
        $stmt = $conn->prepare("DELETE FROM drivers WHERE id = ?");
        if ($stmt->execute([$driver_id])) {
            $success = 'تم حذف السائق بنجاح';
        } else {
            $error = 'حدث خطأ أثناء الحذف';
        }
    }
}

// جلب بيانات السائق للتعديل
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_driver = $stmt->fetch();
}

// الحصول على السائقين مع حساب الحالة بناءً على التعيينات النشطة
$stmt = $conn->query("
    SELECT d.*, u.username,
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM driver_assignments da 
            JOIN transfers t ON da.transfer_id = t.id 
            WHERE da.driver_id = d.id 
            AND t.status IN ('assigned', 'in_transit')
        ) THEN 0
        ELSE 1
    END as is_available
    FROM drivers d 
    LEFT JOIN users u ON d.user_id = u.id 
    ORDER BY d.created_at DESC
");
$drivers = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>إدارة السائقين</h1>
    <button class="btn btn-primary" onclick="openModal('addDriverModal')">+ إضافة سائق</button>
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
                <th>اسم السائق</th>
                <th>الهاتف</th>
                <th>رقم الرخصة</th>
                <th>نوع المركبة</th>
                <th>رقم المركبة</th>

                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($drivers as $driver): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($driver['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                <td><?php echo htmlspecialchars($driver['vehicle_type']); ?></td>
                <td><?php echo htmlspecialchars($driver['vehicle_number']); ?></td>

                <td>
                    <?php if ($driver['is_available']): ?>
                        <span class="status-badge status-delivered">✓ متاح</span>
                    <?php else: ?>
                        <span class="status-badge status-pending">✗ غير متاح</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="drivers.php?edit=<?php echo $driver['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                    <a href="drivers.php?delete=<?php echo $driver['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا السائق؟')">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- مودال إضافة سائق -->
<div id="addDriverModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> إضافة سائق جديد</h3>
            <button class="modal-close" onclick="closeModal('addDriverModal')">&times;</button>
        </div>
        
        <form method="POST" action="">
            <div class="modal-body">
                <!-- القسم الأول: المعلومات الأساسية -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-id-card"></i>
                        المعلومات الأساسية
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-user"></i>
                                اسم السائق
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="name" class="form-control" placeholder="أدخل الاسم الكامل" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-phone"></i>
                                رقم الهاتف
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="phone" class="form-control" placeholder="05xxxxxxxx" required>
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثاني: معلومات المركبة -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-car"></i>
                        معلومات المركبة
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-id-card-alt"></i>
                                رقم الرخصة
                            </label>
                            <input type="text" name="license_number" class="form-control" placeholder="رقم رخصة القيادة">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-truck"></i>
                                نوع المركبة
                            </label>
                            <input type="text" name="vehicle_type" class="form-control" placeholder="مثال: شاحنة صغيرة">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-hashtag"></i>
                            رقم المركبة
                        </label>
                        <input type="text" name="vehicle_number" class="form-control" placeholder="رقم اللوحة">
                    </div>
                </div>
                
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDriverModal')">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" name="add_driver" class="btn btn-primary">
                    <i class="fas fa-save"></i> إضافة السائق
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
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
</script>

<!-- مودال تعديل سائق -->
<?php if ($edit_driver): ?>
<div id="editDriverModal" class="modal active">
    <div class="modal-content modal-edit">
        <div class="modal-header modal-header-edit">
            <div class="modal-header-content">
                <div class="modal-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <h3>تعديل بيانات السائق</h3>
            </div>
            <button class="modal-close" onclick="window.location.href='drivers.php'">&times;</button>
        </div>
        
        <div class="modal-body">
            <form method="POST" action="" id="editDriverForm">
                <input type="hidden" name="id" value="<?php echo $edit_driver['id']; ?>">
                
                <!-- القسم الأول: المعلومات الأساسية -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-id-card"></i>
                        <span>المعلومات الأساسية</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-user"></i>
                                اسم السائق
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_driver['name']); ?>" placeholder="أدخل الاسم الكامل" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-phone"></i>
                                رقم الهاتف
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($edit_driver['phone']); ?>" placeholder="05xxxxxxxx" required>
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثاني: معلومات المركبة -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-car"></i>
                        <span>معلومات المركبة</span>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-id-card-alt"></i>
                                رقم الرخصة
                            </label>
                            <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($edit_driver['license_number']); ?>" placeholder="رقم رخصة القيادة">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-truck"></i>
                                نوع المركبة
                            </label>
                            <input type="text" name="vehicle_type" class="form-control" value="<?php echo htmlspecialchars($edit_driver['vehicle_type']); ?>" placeholder="نوع السيارة">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-hashtag"></i>
                            رقم المركبة
                        </label>
                        <input type="text" name="vehicle_number" class="form-control" value="<?php echo htmlspecialchars($edit_driver['vehicle_number']); ?>" placeholder="رقم لوحة المركبة">
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <button type="submit" form="editDriverForm" name="edit_driver" class="btn btn-primary">
                <i class="fas fa-check"></i> تحديث البيانات
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='drivers.php'">
                <i class="fas fa-times"></i> إلغاء
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
