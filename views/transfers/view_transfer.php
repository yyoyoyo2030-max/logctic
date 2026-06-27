<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn()) {
    redirect('views/auth/login.php');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('views/transfers/transfers.php');
}

$transfer_id = $_GET['id'];

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

// الحصول على تعيين السائق
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
    <h1><i class="fas fa-eye"></i> تفاصيل التحويل</h1>
    <a href="transfers.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> عودة</a>
</div>

<div class="content-grid">
    <!-- بيانات التحويل -->
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-shipping-fast"></i> معلومات التحويل</h3>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span class="label"><i class="fas fa-hashtag"></i> رقم التحويل:</span>
                <span class="value"><strong><?php echo htmlspecialchars($transfer['transfer_number']); ?></strong></span>
            </div>
            

            <div class="info-row">
                <span class="label"><i class="fas fa-user"></i> رفع بواسطة:</span>
                <span class="value"><?php echo htmlspecialchars($transfer['uploader_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-map-marker-alt"></i> من الموقع:</span>
                <span class="value"><?php echo htmlspecialchars($transfer['from_location']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-map-marker-alt"></i> إلى الموقع:</span>
                <span class="value"><?php echo htmlspecialchars($transfer['to_location']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-info-circle"></i> الحالة:</span>
                <span class="value">
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
                </span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-calendar"></i> تاريخ الرفع:</span>
                <span class="value"><?php echo date('Y-m-d H:i', strtotime($transfer['created_at'])); ?></span>
            </div>
            
            <?php if ($transfer['description']): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-comment-alt"></i> الوصف:</span>
                <span class="value"><?php echo nl2br(htmlspecialchars($transfer['description'])); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($transfer['file_path']): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-paperclip"></i> الملف المرفق:</span>
                <span class="value">
                    <a href="<?php echo SITE_URL; ?>/uploads/<?php echo $transfer['file_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                        <i class="fas fa-download"></i> تحميل الملف
                    </a>
                </span>
            </div>
            <?php endif; ?>
            

        </div>
    </div>
    
    <!-- معلومات السائق -->
    <?php if ($assignment): ?>
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-user-tie"></i> معلومات السائق</h3>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span class="label"><i class="fas fa-user"></i> اسم السائق:</span>
                <span class="value"><strong><?php echo htmlspecialchars($assignment['driver_name']); ?></strong></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-phone"></i> رقم الهاتف:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['driver_phone']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-car"></i> نوع المركبة:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['vehicle_type']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-id-card"></i> رقم المركبة:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['vehicle_number']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-calendar-check"></i> تاريخ الاستلام:</span>
                <span class="value"><?php echo $assignment['pickup_date'] ? date('Y-m-d', strtotime($assignment['pickup_date'])) : '-'; ?></span>
            </div>
            
            <?php if ($assignment['delivery_date']): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-calendar-alt"></i> تاريخ التسليم:</span>
                <span class="value"><?php echo date('Y-m-d', strtotime($assignment['delivery_date'])); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-user-cog"></i> تم التعيين بواسطة:</span>
                <span class="value"><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></span>
            </div>
            
            <div class="info-row">
                <span class="label"><i class="fas fa-clock"></i> تاريخ التعيين:</span>
                <span class="value"><?php echo date('Y-m-d H:i', strtotime($assignment['assigned_at'])); ?></span>
            </div>
            
            <?php if ($assignment['notes']): ?>
            <div class="info-row">
                <span class="label"><i class="fas fa-sticky-note"></i> ملاحظات:</span>
                <span class="value"><?php echo nl2br(htmlspecialchars($assignment['notes'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="info-card">
        <div class="card-header">
            <h3><i class="fas fa-user-tie"></i> معلومات السائق</h3>
        </div>
        <div class="card-body">
            <p class="text-muted"><i class="fas fa-exclamation-circle"></i> لم يتم تعيين سائق بعد</p>
            <?php if ($transfer['status'] == 'pending' && isAdmin()): ?>
            <a href="assign_driver.php?id=<?php echo $transfer['id']; ?>" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> تعيين سائق الآن
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
