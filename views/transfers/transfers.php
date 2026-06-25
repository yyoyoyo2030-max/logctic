<?php
/**
 * نظام إدارة اللوجستيك
 * إدارة التحويلات
 * عرض ورفع التحويلات
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn()) {
    redirect('views/auth/login.php');
}



$success = '';
$error = '';

// معالجة حذف تحويل
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $transfer_id = $_GET['delete'];
        
        // التحقق من الصلاحيات
        if (isLogisticsManager()) {
            // جلب معلومات الملف قبل الحذف
            $stmt = $conn->prepare("SELECT file_path FROM transfers WHERE id = ?");
            $stmt->execute([$transfer_id]);
            $transfer = $stmt->fetch();
            
            // حذف الملف من السيرفر إذا كان موجوداً
            if ($transfer && $transfer['file_path']) {
                $file_to_delete = UPLOAD_DIR . $transfer['file_path'];
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
            }
            
            // حذف تعيينات السائقين المرتبطة
            $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE transfer_id = ?");
            $stmt->execute([$transfer_id]);
            
            // حذف التحويل
            $stmt = $conn->prepare("DELETE FROM transfers WHERE id = ?");
            if ($stmt->execute([$transfer_id])) {
                $success = 'تم حذف التحويل بنجاح';
            } else {
                $error = 'حدث خطأ أثناء الحذف';
            }
        } else {
            $error = 'ليس لديك صلاحية الحذف';
        }
    } catch (Exception $e) {
        error_log('Delete Transfer Error: ' . $e->getMessage());
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// إنشاء مجلد uploads إذا لم يكن موجوداً
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// معالجة إضافة تحويل جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_transfer'])) {
    $transfer_number = clean_input($_POST['transfer_number']);
    $from_location = clean_input($_POST['from_location']);
    $to_location = clean_input($_POST['to_location']);
    $description = clean_input($_POST['description']);
    
    // معالجة رفع الملف
    $file_path = null;
    if (isset($_FILES['transfer_file']) && $_FILES['transfer_file']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        $filename = $_FILES['transfer_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '_' . $filename;
            $upload_path = UPLOAD_DIR . $new_filename;
            
            if (move_uploaded_file($_FILES['transfer_file']['tmp_name'], $upload_path)) {
                $file_path = $new_filename;
            } else {
                $error = 'فشل رفع الملف';
            }
        } else {
            $error = 'نوع الملف غير مسموح';
        }
    }
    
    if (!$error) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO transfers (transfer_number, branch_id, uploaded_by, file_path, from_location, to_location, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $transfer_number,
                $_SESSION['branch_id'],
                $_SESSION['user_id'],
                $file_path,
                $from_location,
                $to_location,
                $description
            ]);
            
            $success = 'تم رفع التحويل بنجاح';
            
            // إرسال إشعار عبر الواتساب لمسؤولي السائقين
            require_once '../../api/whatsapp.php';
            $stmt_managers = $conn->query("SELECT phone FROM users WHERE role = 'drivers_manager' AND phone IS NOT NULL AND phone != ''");
            $managers = $stmt_managers->fetchAll();
            if (count($managers) > 0) {
                $msg = "📦 *تحويل جديد متاح للتعيين!*\n\n";
                $msg .= "رقم التحويل: {$transfer_number}\n";
                $msg .= "من: {$from_location}\n";
                $msg .= "إلى: {$to_location}\n\n";
                $msg .= "الرجاء الدخول للنظام لتعيين سائق.";
                foreach ($managers as $manager) {
                    sendWhatsAppMessage($manager['phone'], $msg);
                }
                notifyCustomRoutes('transfer_created', $msg);
            }
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'رقم التحويل موجود مسبقاً';
            } else {
                $error = 'حدث خطأ أثناء الحفظ';
            }
        }
    }
}

// الحصول على التحويلات مع Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // عدد السجلات في كل صفحة
$offset = ($page - 1) * $per_page;

$where = canManageAllBranches() ? '' : 'WHERE t.branch_id = ' . $_SESSION['branch_id'];

// حساب إجمالي السجلات
$count_stmt = $conn->query("SELECT COUNT(*) as total FROM transfers t $where");
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// جلب البيانات للصفحة الحالية
$stmt = $conn->query("
    SELECT t.*, b.name as branch_name, u.full_name as uploader_name,
           d.name as driver_name
    FROM transfers t 
    LEFT JOIN branches b ON t.branch_id = b.id 
    LEFT JOIN users u ON t.uploaded_by = u.id 
    LEFT JOIN driver_assignments da ON t.id = da.transfer_id
    LEFT JOIN drivers d ON da.driver_id = d.id
    $where
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$transfers = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>إدارة التحويلات</h1>
    <button onclick="openModal('addTransferModal')" class="btn btn-primary">+ رفع تحويل جديد</button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="content-section">
    <div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم التحويل</th>

                <th>من</th>
                <th>إلى</th>
                <th>السائق</th>
                <th>الحالة</th>
                <th>التاريخ والوقت</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transfers as $transfer): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($transfer['transfer_number']); ?></strong></td>

                <td><?php echo htmlspecialchars($transfer['from_location']); ?></td>
                <td><?php echo htmlspecialchars($transfer['to_location']); ?></td>
                <td><?php echo $transfer['driver_name'] ? htmlspecialchars($transfer['driver_name']) : '<span class="text-muted">لم يتم التعيين</span>'; ?></td>
                <td>
                    <span class="status-badge status-<?php echo $transfer['status']; ?>">
                        <?php 
                        $statuses = [
                            'pending' => 'قيد الانتظار',
                            'assigned' => 'تم التعيين',
                            'in_transit' => 'قيد التوصيل',
                            'delivered' => 'تم الاستلام',
                            'cancelled' => 'ملغي'
                        ];
                        echo $statuses[$transfer['status']];
                        ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <strong style="color: #0ea5e9; font-size: 14px;">
                            <i class="fas fa-calendar-alt"></i> 
                            <?php echo date('Y-m-d', strtotime($transfer['created_at'])); ?>
                        </strong>
                        <small style="color: #64748b; font-size: 12px;">
                            <i class="fas fa-clock"></i> 
                            <?php 
                            $time = date('H:i', strtotime($transfer['created_at']));
                            echo $time . ' (توقيت السعودية)';
                            ?>
                        </small>
                    </div>
                </td>
                <td class="actions">
                    <a href="view_transfer.php?id=<?php echo $transfer['id']; ?>" class="btn btn-sm btn-info" title="عرض التفاصيل">
                        <i class="fas fa-eye"></i> عرض
                    </a>
                    <?php if ($transfer['status'] == 'pending' && isDriversManager()): ?>
                    <a href="assign_driver.php?id=<?php echo $transfer['id']; ?>" class="btn btn-sm btn-success" title="تعيين سائق">
                        <i class="fas fa-user-plus"></i> تعيين
                    </a>
                    <?php elseif (($transfer['status'] == 'assigned' || $transfer['status'] == 'in_transit') && isDriversManager()): ?>
                    <a href="assign_driver.php?id=<?php echo $transfer['id']; ?>" class="btn btn-sm btn-warning" title="تعديل السائق">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                    <?php endif; ?>
                    <?php if ($transfer['file_path']): ?>
                    <a href="<?php echo SITE_URL; ?>/uploads/<?php echo $transfer['file_path']; ?>" target="_blank" class="btn btn-sm btn-primary" title="عرض الملف">
                        <i class="fas fa-file-download"></i> ملف
                    </a>
                    <?php endif; ?>
                    <?php if (isLogisticsManager()): ?>
                    <a href="?delete=<?php echo $transfer['id']; ?>" 
                       class="btn btn-sm btn-danger" 
                       title="حذف التحويل"
                       onclick="return confirm('هل أنت متأكد من حذف هذا التحويل؟ سيتم حذف الملف والتعيينات المرتبطة به.')">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1" class="page-link">الأولى</a>
            <a href="?page=<?php echo $page - 1; ?>" class="page-link">السابقة</a>
        <?php endif; ?>
        
        <?php
        // عرض أرقام الصفحات
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++):
        ?>
            <a href="?page=<?php echo $i; ?>" 
               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>" class="page-link">التالية</a>
            <a href="?page=<?php echo $total_pages; ?>" class="page-link">الأخيرة</a>
        <?php endif; ?>
        
        <span class="page-info">
            صفحة <?php echo $page; ?> من <?php echo $total_pages; ?> 
            (إجمالي: <?php echo $total_records; ?> سجل)
        </span>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Modal إضافة تحويل جديد -->
<div id="addTransferModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> رفع تحويل جديد</h3>
            <button class="modal-close" onclick="closeModal('addTransferModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="add_transfer" value="1">
                
                <!-- القسم الأول: معلومات التحويل الأساسية -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-file-alt"></i>
                        معلومات التحويل
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-hashtag"></i>
                                رقم التحويل
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="transfer_number" class="form-control" placeholder="مثال: 1233" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-upload"></i>
                                ملف التحويل
                            </label>
                            <div class="file-input-wrapper">
                                <input type="file" name="transfer_file" id="transfer_file" 
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                       onchange="displayFileName(this)">
                                <label for="transfer_file" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>اختر الملف</span>
                                </label>
                            </div>
                            <div class="file-name-display" id="file-name-display">
                                <i class="fas fa-check-circle"></i>
                                <span id="file-name-text"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثاني: الموقع -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        تفاصيل الموقع
                    </div>
                    
                    <div class="form-row">
                        <?php
                        // جلب الفروع لعرضها في القوائم المنسدلة
                        $stmt_branches = $conn->query("SELECT id, name FROM branches ORDER BY name");
                        $all_branches_list = $stmt_branches->fetchAll();
                        
                        $user_branch_name = '';
                        if (!canManageAllBranches()) {
                            foreach ($all_branches_list as $b) {
                                if ($b['id'] == $_SESSION['branch_id']) {
                                    $user_branch_name = $b['name'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="form-group">
                            <label>
                                <i class="fas fa-location-arrow"></i>
                                من الموقع
                                <span class="required">*</span>
                            </label>
                            <?php if (canManageAllBranches()): ?>
                                <select name="from_location" class="form-control" required>
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach ($all_branches_list as $branch): ?>
                                        <option value="<?php echo htmlspecialchars($branch['name']); ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <select name="from_location" class="form-control" readonly required>
                                    <option value="<?php echo htmlspecialchars($user_branch_name); ?>" selected><?php echo htmlspecialchars($user_branch_name); ?></option>
                                </select>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-map-pin"></i>
                                إلى الموقع
                                <span class="required">*</span>
                            </label>
                            <select name="to_location" class="form-control" required>
                                <option value="">-- اختر الفرع --</option>
                                <?php foreach ($all_branches_list as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch['name']); ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- القسم الثالث: الملاحظات -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-comment-dots"></i>
                        ملاحظات إضافية
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-align-right"></i>
                            الوصف / ملاحظات
                        </label>
                        <textarea name="description" class="form-control" rows="3" placeholder="أدخل أي تفاصيل أو ملاحظات إضافية هنا (اختياري)"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTransferModal')">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ التحويل
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function displayFileName(input) {
    const fileDisplay = document.getElementById('file-name-display');
    const fileNameText = document.getElementById('file-name-text');
    
    if (input.files && input.files[0]) {
        fileNameText.textContent = input.files[0].name;
        fileDisplay.classList.add('active');
    } else {
        fileDisplay.classList.remove('active');
    }
}

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

// إغلاق المودال عند الضغط خارجه
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// فتح المودال تلقائياً إذا كان هناك خطأ
<?php if ($error || (isset($_POST['add_transfer']) && !$success)): ?>
openModal('addTransferModal');
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>
