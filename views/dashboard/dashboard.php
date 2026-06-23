<?php
/**
 * نظام إدارة اللوجستيك
 * لوحة التحكم الررئيسية
 * عرض الإحصائيات وآخر التحويلات
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn()) {
    redirect('views/auth/login.php');
}

// إذا كان المستخدم سائق، توجيهه لصفحة التحويلات الخاصة به
if ($_SESSION['role'] == 'driver') {
    redirect('views/drivers/driver_transfers.php');
}

// إحصائيات لوحة التحكم
$stats = [];

// عدد التحويلات
if (canManageAllBranches()) {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transfers");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transfers WHERE branch_id = ?");
    $stmt->execute([$_SESSION['branch_id']]);
}
$stats['total_transfers'] = $stmt->fetch()['total'];

// عدد التحويلات جاري التوصيل
if (canManageAllBranches()) {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transfers WHERE status = 'in_transit'");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transfers WHERE branch_id = ? AND status = 'in_transit'");
    $stmt->execute([$_SESSION['branch_id']]);
}
$stats['in_transit_transfers'] = $stmt->fetch()['total'];

// عدد السائقين المتاحين (الذين ليس لديهم تحويلات نشطة)
$stmt = $conn->query("
    SELECT COUNT(*) as total FROM drivers d
    WHERE NOT EXISTS (
        SELECT 1 FROM driver_assignments da 
        JOIN transfers t ON da.transfer_id = t.id 
        WHERE da.driver_id = d.id 
        AND t.status IN ('assigned', 'in_transit')
    )
");
$stats['available_drivers'] = $stmt->fetch()['total'];

// عدد التحويلات قيد التنفيذ
if (canManageAllBranches()) {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transfers WHERE status IN ('assigned', 'in_transit')");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transfers WHERE branch_id = ? AND status IN ('assigned', 'in_transit')");
    $stmt->execute([$_SESSION['branch_id']]);
}
$stats['active_transfers'] = $stmt->fetch()['total'];

// عدد التحويلات المكتملة (تم التوصيل)
if (canManageAllBranches()) {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transfers WHERE status = 'delivered'");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transfers WHERE branch_id = ? AND status = 'delivered'");
    $stmt->execute([$_SESSION['branch_id']]);
}
$stats['delivered_transfers'] = $stmt->fetch()['total'];

// عدد التحويلات بدون سائق (لم يتم تعيين سائق)
if (canManageAllBranches()) {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM transfers t WHERE NOT EXISTS (SELECT 1 FROM driver_assignments da WHERE da.transfer_id = t.id)");
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transfers t WHERE t.branch_id = ? AND NOT EXISTS (SELECT 1 FROM driver_assignments da WHERE da.transfer_id = t.id)");
    $stmt->execute([$_SESSION['branch_id']]);
}
$stats['no_driver_transfers'] = $stmt->fetch()['total'];

// أحدث التحويلات
if (canManageAllBranches()) {
    $stmt = $conn->query("
        SELECT t.*, b.name as branch_name, u.full_name as uploader_name 
        FROM transfers t 
        LEFT JOIN branches b ON t.branch_id = b.id 
        LEFT JOIN users u ON t.uploaded_by = u.id 
        ORDER BY t.created_at DESC LIMIT 8
    ");
} else {
    $stmt = $conn->prepare("
        SELECT t.*, b.name as branch_name, u.full_name as uploader_name 
        FROM transfers t 
        LEFT JOIN branches b ON t.branch_id = b.id 
        LEFT JOIN users u ON t.uploaded_by = u.id 
        WHERE t.branch_id = ?
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $stmt->execute([$_SESSION['branch_id']]);
}
$recent_transfers = $stmt->fetchAll();

include '../../includes/header.php';
?>

<div class="dashboard-stats" data-page="dashboard">
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-info">
            <h3 class="stat-value" data-stat="transfers"><?php echo $stats['total_transfers']; ?></h3>
            <p>إجمالي التحويلات</p>
        </div>
    </div>
    
    <div class="stat-card info">
        <div class="stat-icon">🚚</div>
        <div class="stat-info">
            <h3 class="stat-value" data-stat="inprogress-transfers"><?php echo $stats['in_transit_transfers']; ?></h3>
            <p>جاري التوصيل</p>
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon">🚗</div>
        <div class="stat-info">
            <h3 class="stat-value" data-stat="drivers"><?php echo $stats['available_drivers']; ?></h3>
            <p>سائقين متاحين</p>
        </div>
    </div>
    
    <div class="stat-card info">
        <div class="stat-icon">🚚</div>
        <div class="stat-info">
            <h3 class="stat-value" data-stat="completed-transfers"><?php echo $stats['active_transfers']; ?></h3>
            <p>قيد التنفيذ</p>
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <h3 class="stat-value" data-stat="completed-transfers"><?php echo $stats['delivered_transfers']; ?></h3>
            <p>تم التوصيل</p>
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-icon">👤</div>
        <div class="stat-info">
            <h3 class="stat-value" data-stat="pending-transfers"><?php echo $stats['no_driver_transfers']; ?></h3>
            <p>بدون سائق</p>
        </div>
    </div>
</div>

<div class="content-section">
    <div class="section-header">
        <h2>أحدث التحويلات</h2>
        <a href="../transfers/transfers.php" class="btn btn-primary">عرض الكل</a>
    </div>
    
    <div class="table-responsive">
        <table class="data-table recent-transfers" data-transfers-list>
            <thead>
                <tr>
                    <th>رقم التحويل</th>

                    <th>من</th>
                    <th>إلى</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_transfers as $transfer): ?>
                <tr data-transfer-id="<?php echo $transfer['id']; ?>">
                    <td><?php echo htmlspecialchars($transfer['transfer_number']); ?></td>

                    <td><?php echo htmlspecialchars($transfer['from_location']); ?></td>
                    <td><?php echo htmlspecialchars($transfer['to_location']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $transfer['status']; ?>">
                            <?php 
                            $statuses = [
                                'pending' => 'قيد الانتظار',
                                'assigned' => 'جاري التوصيل',
                                'in_transit' => 'قيد التوصيل',
                                'delivered' => 'تم التوصيل',
                                'cancelled' => 'ملغي'
                            ];
                            echo $statuses[$transfer['status']];
                            ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($transfer['created_at'])); ?></td>
                    <td class="actions">
                        <a href="../transfers/view_transfer.php?id=<?php echo $transfer['id']; ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> عرض
                        </a>
                        <?php if ($transfer['status'] == 'pending'): ?>
                        <a href="../transfers/assign_driver.php?id=<?php echo $transfer['id']; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-user-plus"></i> تعيين
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
