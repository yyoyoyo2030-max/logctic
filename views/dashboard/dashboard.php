<?php
/**
 * نظام إدارة اللوجستيك
 * لوحة التحكم الرئيسية
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

// فلتر الفترة الزمنية
$period = $_GET['period'] ?? 'all';
$date_condition = '';
$date_params = [];

switch ($period) {
    case 'today':
        $date_condition = ' AND t.created_at >= CURDATE()';
        break;
    case 'week':
        $date_condition = ' AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
        break;
    case 'month':
        $date_condition = ' AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        break;
    default:
        $date_condition = '';
        $period = 'all';
        break;
}

// إحصائيات لوحة التحكم
$stats = [];
$branch_cond = '';
$branch_params = [];

if (!canManageAllBranches()) {
    $branch_cond = ' AND t.branch_id = ?';
    $branch_params = [$_SESSION['branch_id']];
}

// استعلام واحد مُحسَّن لإحصائيات الفترة الزمنية
$sql = "SELECT 
    COUNT(*) as total_transfers,
    SUM(CASE WHEN t.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM driver_assignments da WHERE da.transfer_id = t.id) THEN 1 ELSE 0 END) as no_driver
    FROM transfers t WHERE 1=1 $date_condition $branch_cond";

$stmt = $conn->prepare($sql);
$stmt->execute($branch_params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$stats['total_transfers'] = (int)($row['total_transfers'] ?? 0);
$stats['delivered_transfers'] = (int)($row['delivered'] ?? 0);
$stats['no_driver_transfers'] = (int)($row['no_driver'] ?? 0);

// استعلام منفصل للحالات الفعلية الحالية (لا يتأثر بفلتر التاريخ)
$live_sql = "SELECT 
    SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN t.status IN ('assigned', 'in_transit') THEN 1 ELSE 0 END) as active
    FROM transfers t WHERE 1=1 $branch_cond";

$live_stmt = $conn->prepare($live_sql);
$live_stmt->execute($branch_params);
$live_row = $live_stmt->fetch(PDO::FETCH_ASSOC);

$stats['pending_transfers'] = (int)($live_row['pending'] ?? 0);
$stats['active_transfers'] = (int)($live_row['active'] ?? 0);

// عدد السائقين المتاحين (لا يتأثر بالفلتر الزمني)
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

// أحدث التحويلات
if (canManageAllBranches()) {
    $stmt = $conn->prepare("
        SELECT t.*, b.name as branch_name, u.full_name as uploader_name 
        FROM transfers t 
        LEFT JOIN branches b ON t.branch_id = b.id 
        LEFT JOIN users u ON t.uploaded_by = u.id 
        WHERE 1=1 $date_condition
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("
        SELECT t.*, b.name as branch_name, u.full_name as uploader_name 
        FROM transfers t 
        LEFT JOIN branches b ON t.branch_id = b.id 
        LEFT JOIN users u ON t.uploaded_by = u.id 
        WHERE t.branch_id = ? $date_condition
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $stmt->execute([$_SESSION['branch_id']]);
}
$recent_transfers = $stmt->fetchAll();

include '../../includes/header.php';
?>

<style>
.dash-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.dash-filter-bar h2 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
}
.dash-period-tabs {
    display: flex;
    gap: 6px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 4px;
}
.dash-period-tabs a {
    padding: 7px 18px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--text-secondary);
    transition: all 0.2s;
    font-family: 'Cairo', sans-serif;
}
.dash-period-tabs a:hover {
    color: var(--text-primary);
    background: rgba(99,102,241,0.08);
}
.dash-period-tabs a.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    box-shadow: 0 2px 8px rgba(99,102,241,0.3);
}
.dashboard-stats .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.dashboard-stats .stat-card { padding: 20px; }
.dashboard-stats .stat-card .stat-icon { background: rgba(99,102,241,0.12); color: #6366f1; }
.dashboard-stats .stat-card.info .stat-icon { background: rgba(14,165,233,0.12); color: #0ea5e9; }
.dashboard-stats .stat-card.success .stat-icon { background: rgba(16,185,129,0.12); color: #10b981; }
.dashboard-stats .stat-card.warning .stat-icon { background: rgba(245,158,11,0.12); color: #f59e0b; }

/* بطاقات قابلة للنقر */
.stat-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    border-radius: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card-link:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}
.stat-card-link:hover .stat-card {
    border-color: rgba(99,102,241,0.3);
}
.stat-card-link:active {
    transform: translateY(-1px);
}
.stat-card-link .stat-card {
    cursor: pointer;
    position: relative;
}
.stat-card-link .stat-card::after {
    content: '\f061';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 12px;
    left: 12px;
    font-size: 11px;
    color: var(--text-muted, #94a3b8);
    opacity: 0;
    transition: opacity 0.2s ease, transform 0.2s ease;
    transform: translateX(4px) rotate(180deg);
}
.stat-card-link:hover .stat-card::after {
    opacity: 1;
    transform: translateX(0) rotate(180deg);
}
</style>

<!-- فلتر الفترة الزمنية -->
<div class="dash-filter-bar">
    <h2><i class="fas fa-chart-line" style="color: #6366f1; margin-left: 8px;"></i> لوحة القيادة</h2>
    <div class="dash-period-tabs">
        <a href="dashboard.php?period=today" class="<?php echo $period === 'today' ? 'active' : ''; ?>">اليوم</a>
        <a href="dashboard.php?period=week" class="<?php echo $period === 'week' ? 'active' : ''; ?>">الأسبوع</a>
        <a href="dashboard.php?period=month" class="<?php echo $period === 'month' ? 'active' : ''; ?>">الشهر</a>
        <a href="dashboard.php?period=all" class="<?php echo $period === 'all' ? 'active' : ''; ?>">الكل</a>
    </div>
</div>

<div class="dashboard-stats" data-page="dashboard">
    <a href="../transfers/transfers.php" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes-stacked"></i></div>
            <div class="stat-info">
                <h3 class="stat-value" data-stat="transfers"><?php echo $stats['total_transfers']; ?></h3>
                <p>إجمالي التحويلات</p>
            </div>
        </div>
    </a>
    
    <a href="../transfers/transfers.php?status=active" class="stat-card-link">
        <div class="stat-card info">
            <div class="stat-icon"><i class="fas fa-truck-fast"></i></div>
            <div class="stat-info">
                <h3 class="stat-value" data-stat="inprogress-transfers"><?php echo $stats['active_transfers']; ?></h3>
                <p>جاري التوصيل</p>
            </div>
        </div>
    </a>
    
    <a href="../drivers/drivers.php" class="stat-card-link">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-id-badge"></i></div>
            <div class="stat-info">
                <h3 class="stat-value" data-stat="drivers"><?php echo $stats['available_drivers']; ?></h3>
                <p>سائقين متاحين</p>
            </div>
        </div>
    </a>
    
    <a href="../transfers/transfers.php?status=pending" class="stat-card-link">
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            <div class="stat-info">
                <h3 class="stat-value" data-stat="completed-transfers"><?php echo $stats['pending_transfers']; ?></h3>
                <p>قيد الانتظار</p>
            </div>
        </div>
    </a>
    
    <a href="../transfers/transfers.php?status=delivered" class="stat-card-link">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <div class="stat-info">
                <h3 class="stat-value" data-stat="completed-transfers"><?php echo $stats['delivered_transfers']; ?></h3>
                <p>تم التوصيل</p>
            </div>
        </div>
    </a>
    
    <a href="../transfers/transfers.php?no_driver=1" class="stat-card-link">
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
            <div class="stat-info">
                <h3 class="stat-value" data-stat="pending-transfers"><?php echo $stats['no_driver_transfers']; ?></h3>
                <p>بدون سائق</p>
            </div>
        </div>
    </a>
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
