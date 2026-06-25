<?php
/**
 * نظام إدارة اللوجستيك
 * تعيين وتعديل السائق للتحويل
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !isDriversManager()) {
    redirect('views/dashboard/dashboard.php');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('views/transfers/transfers.php');
}

$transfer_id = $_GET['id'];
$success = '';
$error = '';

// الحصول على بيانات التحويل
$stmt = $conn->prepare("
    SELECT t.*, b.name as branch_name, u.full_name as uploader_name
    FROM transfers t 
    LEFT JOIN branches b ON t.branch_id = b.id 
    LEFT JOIN users u ON t.uploaded_by = u.id 
    WHERE t.id = ?
");
$stmt->execute([$transfer_id]);
$transfer = $stmt->fetch();

if (!$transfer) {
    redirect('views/transfers/transfers.php');
}

// التحقق من الصلاحيات
if (!canManageAllBranches() && $transfer['branch_id'] != $_SESSION['branch_id']) {
    redirect('views/transfers/transfers.php');
}

// معالجة تعيين السائق
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $driver_id = $_POST['driver_id'];
    $pickup_date = $_POST['pickup_date'];
    $notes = clean_input($_POST['notes']);
    
    try {
        // التحقق من وجود تعيين سابق
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM driver_assignments WHERE transfer_id = ?");
        $stmt->execute([$transfer_id]);
        $exists = $stmt->fetch()['count'];
        
        if ($exists > 0) {
            // تحديث التعيين الموجود
            $stmt = $conn->prepare("
                UPDATE driver_assignments 
                SET driver_id = ?, pickup_date = ?, notes = ?, assigned_by = ?, assigned_at = NOW()
                WHERE transfer_id = ?
            ");
            $stmt->execute([$driver_id, $pickup_date, $notes, $_SESSION['user_id'], $transfer_id]);
            $success = 'تم تحديث السائق بنجاح';
        } else {
            // إضافة التعيين الجديد
            $stmt = $conn->prepare("
                INSERT INTO driver_assignments (transfer_id, driver_id, assigned_by, pickup_date, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$transfer_id, $driver_id, $_SESSION['user_id'], $pickup_date, $notes]);
            $success = 'تم تعيين السائق بنجاح';
        }
        
        // تحديث حالة التحويل والسائق
        $stmt = $conn->prepare("UPDATE transfers SET status = 'in_transit' WHERE id = ?");
        $stmt->execute([$transfer_id]);
        
        $stmt = $conn->prepare("UPDATE drivers SET is_available = 0 WHERE id = ?");
        $stmt->execute([$driver_id]);
        
        // إعادة تحميل بيانات التحويل
        $stmt = $conn->prepare("
            SELECT t.*, b.name as branch_name, u.full_name as uploader_name
            FROM transfers t 
            LEFT JOIN branches b ON t.branch_id = b.id 
            LEFT JOIN users u ON t.uploaded_by = u.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$transfer_id]);
        $transfer = $stmt->fetch();
        
        // إعادة تحميل بيانات التعيين
        $stmt = $conn->prepare("
            SELECT da.*, d.name as driver_name, d.phone as driver_phone, 
                   d.vehicle_type, d.vehicle_number, u.full_name as assigned_by_name
            FROM driver_assignments da
            LEFT JOIN drivers d ON da.driver_id = d.id
            LEFT JOIN users u ON da.assigned_by = u.id
            WHERE da.transfer_id = ?
        ");
        $stmt->execute([$transfer_id]);
        $assignment = $stmt->fetch();
        
        // إرسال إشعار للسائق عبر الواتساب
        if ($assignment && !empty($assignment['driver_phone'])) {
            require_once '../../api/whatsapp.php';
            $msg = "🚚 *إشعار تعيين جديد*\n\n";
            $msg .= "تم تعيينك لتحويل جديد رقم: {$transfer['transfer_number']}\n";
            $msg .= "من: {$transfer['from_location']}\n";
            $msg .= "إلى: {$transfer['to_location']}\n";
            if (!empty($notes)) {
                $msg .= "ملاحظات: {$notes}\n";
            }
            $msg .= "\nالرجاء التوجه للاستلام وتوصيل التحويل.";
            
            // تجاهل النتيجة لأن الغرض إعلامي
            sendWhatsAppMessage($assignment['driver_phone'], $msg);
            notifyCustomRoutes('driver_assigned', $msg);
        }
    } catch(PDOException $e) {
        $error = 'حدث خطأ أثناء التعيين';
    }
}

// الحصول على السائقين المتاحين
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
$drivers = $stmt->fetchAll();

// الحصول على تعيين السائق إذا وجد
$stmt = $conn->prepare("
    SELECT da.*, d.name as driver_name, d.phone as driver_phone, 
           d.vehicle_type, d.vehicle_number, u.full_name as assigned_by_name
    FROM driver_assignments da
    LEFT JOIN drivers d ON da.driver_id = d.id
    LEFT JOIN users u ON da.assigned_by = u.id
    WHERE da.transfer_id = ?
");
$stmt->execute([$transfer_id]);
$assignment = $stmt->fetch();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><?php echo $assignment ? 'تعديل السائق للتحويل' : 'تعيين سائق للتحويل'; ?></h1>
    <a href="transfers.php" class="btn btn-secondary">عودة</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="content-grid">
    <!-- بيانات التحويل -->
    <div class="info-card" style="padding: 20px;">
        <h3 style="margin-bottom: 15px; font-size: 1.2em;">بيانات التحويل</h3>
        <div class="info-row" style="padding: 8px 0;">
            <span class="label" style="width: 120px; font-size: 0.9em;">رقم التحويل:</span>
            <span class="value"><?php echo htmlspecialchars($transfer['transfer_number']); ?></span>
        </div>
        <div class="info-row" style="padding: 8px 0;">
            <span class="label" style="width: 120px; font-size: 0.9em;">الفرع:</span>
            <span class="value"><?php echo htmlspecialchars($transfer['branch_name']); ?></span>
        </div>
        <div class="info-row" style="padding: 8px 0;">
            <span class="label" style="width: 120px; font-size: 0.9em;">من:</span>
            <span class="value"><?php echo htmlspecialchars($transfer['from_location']); ?></span>
        </div>
        <div class="info-row" style="padding: 8px 0;">
            <span class="label" style="width: 120px; font-size: 0.9em;">إلى:</span>
            <span class="value"><?php echo htmlspecialchars($transfer['to_location']); ?></span>
        </div>
        <div class="info-row" style="padding: 8px 0;">
            <span class="label" style="width: 120px; font-size: 0.9em;">الحالة:</span>
            <span class="value">
                <span class="status-badge status-<?php echo $transfer['status']; ?>">
                    <?php 
                    $statuses = [
                        'pending' => 'قيد الانتظار',
                        'assigned' => 'تم التعيين',
                        'in_transit' => 'قيد التوصيل',
                        'delivered' => 'تم التوصيل',
                        'cancelled' => 'ملغي'
                    ];
                    echo $statuses[$transfer['status']];
                    ?>
                </span>
            </span>
        </div>
        <?php if ($transfer['description']): ?>
        <div class="info-row" style="padding: 8px 0;">
            <span class="label" style="width: 120px; font-size: 0.9em;">الوصف:</span>
            <span class="value"><?php echo nl2br(htmlspecialchars($transfer['description'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- تعيين السائق -->
    <div class="form-card" style="padding: 20px;">
        <?php if ($assignment): ?>
            <h3 style="margin-bottom: 15px; font-size: 1.2em;">السائق المعين</h3>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">اسم السائق:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['driver_name']); ?></span>
            </div>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">الهاتف:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['driver_phone']); ?></span>
            </div>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">نوع المركبة:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['vehicle_type']); ?></span>
            </div>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">رقم المركبة:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['vehicle_number']); ?></span>
            </div>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">تاريخ الاستلام:</span>
                <span class="value"><?php echo $assignment['pickup_date'] ? date('Y-m-d', strtotime($assignment['pickup_date'])) : '-'; ?></span>
            </div>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">تم التعيين بواسطة:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></span>
            </div>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">تاريخ التعيين:</span>
                <span class="value"><?php echo date('Y-m-d H:i', strtotime($assignment['assigned_at'])); ?></span>
            </div>
            <?php if ($assignment['notes']): ?>
            <div class="info-row" style="padding: 8px 0;">
                <span class="label" style="width: 120px; font-size: 0.9em;">ملاحظات:</span>
                <span class="value"><?php echo nl2br(htmlspecialchars($assignment['notes'])); ?></span>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                <button onclick="document.getElementById('editDriverForm').style.display='block'" class="btn btn-primary" style="width: 100%;">
                    تعديل السائق
                </button>
            </div>
            
            <!-- نموذج تعديل السائق (مخفي) -->
            <div id="editDriverForm" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid #e2e8f0;">
                <!-- رأس النموذج -->
                <div class="modal-header-edit" style="margin-bottom: 20px;">
                    <div class="modal-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h4 style="margin: 0;">تعديل تعيين السائق</h4>
                </div>
                
                <?php if (count($drivers) > 0): ?>
                <form method="POST" action="">
                    <!-- قسم معلومات السائق -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user-tie"></i> معلومات السائق
                        </div>
                        
                        <div class="form-group full-width">
                            <label><i class="fas fa-id-card"></i> اختر السائق الجديد *</label>
                            <select name="driver_id" class="form-control" required>
                                <option value="">-- اختر السائق --</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <?php 
                                        $isInTransit = in_array($driver['current_status'], ['assigned', 'in_transit']); 
                                        $lastTo = htmlspecialchars($driver['last_to_location'] ?? '');
                                        $isRecommended = false;
                                        
                                        $lastToTrimmed = trim($driver['last_to_location']);
                                        if (!empty($lastToTrimmed) && (
                                            $lastToTrimmed == trim($transfer['from_location']) || 
                                            $lastToTrimmed == trim($transfer['branch_name']) ||
                                            $lastToTrimmed == trim($transfer['to_location'])
                                        )) {
                                            $isRecommended = true;
                                        }
                                        
                                        $label = htmlspecialchars($driver['name']) . ' (' . htmlspecialchars($driver['vehicle_number']) . ')';
                                        
                                        if ($isInTransit) {
                                            $label .= ' - 🚚 متجه إلى: ' . ($lastTo ?: 'غير محدد');
                                        } else {
                                            if ($lastTo) {
                                                $label .= ' - ✅ متاح (في: ' . $lastTo . ')';
                                            } else {
                                                $label .= ' - ✅ متاح';
                                            }
                                        }
                                        
                                        if ($isRecommended) {
                                            $label .= ' ⭐ [موصى به]';
                                        }
                                    ?>
                                    <option value="<?php echo $driver['id']; ?>" data-in-transit="<?php echo $isInTransit ? 'true' : 'false'; ?>" <?php echo ($assignment && $assignment['driver_id'] == $driver['id']) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- قسم تفاصيل التعيين -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-calendar-check"></i> تفاصيل التعيين
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> تاريخ الاستلام</label>
                                <input type="date" name="pickup_date" class="form-control" value="<?php echo $assignment['pickup_date'] ? date('Y-m-d', strtotime($assignment['pickup_date'])) : date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="أضف ملاحظاتك هنا..."><?php echo htmlspecialchars($assignment['notes']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ التعديل
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editDriverForm').style.display='none'">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        لا يوجد سائقين متاحين حالياً.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- رأس النموذج بالتصميم الحديث -->
            <div class="modal-header-edit">
                <div class="modal-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3>اختيار سائق</h3>
            </div>
            
            <?php if (count($drivers) > 0): ?>
            <form method="POST" action="" style="margin-top: 20px;">
                <!-- قسم معلومات السائق -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user-tie"></i> معلومات السائق
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-id-card"></i> اختر السائق *</label>
                        <select name="driver_id" class="form-control" required>
                            <option value="">-- اختر السائق --</option>
                            <?php foreach ($drivers as $driver): ?>
                                <?php 
                                    $isInTransit = in_array($driver['current_status'], ['assigned', 'in_transit']); 
                                    $lastTo = htmlspecialchars($driver['last_to_location'] ?? '');
                                    $isRecommended = false;
                                    
                                    $lastToTrimmed = trim($driver['last_to_location']);
                                    if (!empty($lastToTrimmed) && (
                                        $lastToTrimmed == trim($transfer['from_location']) || 
                                        $lastToTrimmed == trim($transfer['branch_name']) ||
                                        $lastToTrimmed == trim($transfer['to_location'])
                                    )) {
                                        $isRecommended = true;
                                    }
                                    
                                    $label = htmlspecialchars($driver['name']) . ' (' . htmlspecialchars($driver['vehicle_number']) . ')';
                                    
                                    if ($isInTransit) {
                                        $label .= ' - 🚚 متجه إلى: ' . ($lastTo ?: 'غير محدد');
                                    } else {
                                        if ($lastTo) {
                                            $label .= ' - ✅ متاح (في: ' . $lastTo . ')';
                                        } else {
                                            $label .= ' - ✅ متاح';
                                        }
                                    }
                                    
                                    if ($isRecommended) {
                                        $label .= ' ⭐ [موصى به]';
                                    }
                                ?>
                                <option value="<?php echo $driver['id']; ?>" data-in-transit="<?php echo $isInTransit ? 'true' : 'false'; ?>">
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- قسم تفاصيل التعيين -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-calendar-check"></i> تفاصيل التعيين
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> تاريخ الاستلام</label>
                            <input type="date" name="pickup_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="أضف ملاحظاتك هنا..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> تعيين السائق
                    </button>
                    <a href="transfers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    لا يوجد سائقين متاحين حالياً. يرجى <a href="../drivers/drivers.php">إضافة سائق جديد</a>.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
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
