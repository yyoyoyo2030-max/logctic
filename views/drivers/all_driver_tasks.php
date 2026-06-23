<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !isDriversManager()) {
    redirect('views/dashboard/dashboard.php');
}

// الحصول على جميع مهام السائقين مع التصفية
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$filter_driver = isset($_GET['driver_id']) ? clean_input($_GET['driver_id']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// بناء الاستعلام
$sql = "
    SELECT t.*, b.name as branch_name, u.full_name as uploader_name,
           d.name as driver_name, d.phone as driver_phone, d.vehicle_number,
           da.pickup_date, da.delivery_date, da.notes, da.assigned_at
    FROM transfers t 
    LEFT JOIN branches b ON t.branch_id = b.id 
    LEFT JOIN users u ON t.uploaded_by = u.id 
    INNER JOIN driver_assignments da ON t.id = da.transfer_id
    INNER JOIN drivers d ON da.driver_id = d.id
    WHERE 1=1
";

$params = [];

// إضافة الفلاتر
if (!empty($filter_status)) {
    $sql .= " AND t.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_driver)) {
    $sql .= " AND d.id = ?";
    $params[] = $filter_driver;
}

if (!empty($search)) {
    $sql .= " AND (t.transfer_number LIKE ? OR d.name LIKE ? OR t.from_location LIKE ? OR t.to_location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// إضافة التصفية حسب الفرع لمستخدمي الفروع
if (!canManageAllBranches()) {
    $sql .= " AND t.branch_id = ?";
    $params[] = $_SESSION['branch_id'];
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// الحصول على قائمة السائقين للفلتر
$drivers_sql = "SELECT id, name FROM drivers WHERE is_active = 1 ORDER BY name";
$drivers_stmt = $conn->prepare($drivers_sql);
$drivers_stmt->execute();
$drivers = $drivers_stmt->fetchAll();

// إحصائيات سريعة
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN t.status = 'assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN t.status = 'in_transit' THEN 1 ELSE 0 END) as in_transit,
        SUM(CASE WHEN t.status = 'delivered' THEN 1 ELSE 0 END) as delivered
    FROM transfers t
    INNER JOIN driver_assignments da ON t.id = da.transfer_id
    WHERE 1=1
";

if (!canManageAllBranches()) {
    $stats_sql .= " AND t.branch_id = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute([$_SESSION['branch_id']]);
} else {
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute();
}
$stats = $stats_stmt->fetch();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>📋 مهام السائقين</h1>
    <div>
        <a href="drivers.php" class="btn btn-secondary">عودة للسائقين</a>
    </div>
</div>

<!-- الإحصائيات -->
<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total']; ?></div>
        <div class="stat-label">إجمالي المهام</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
        <div class="stat-number"><?php echo $stats['assigned']; ?></div>
        <div class="stat-label">تم التعيين</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
        <div class="stat-number"><?php echo $stats['in_transit']; ?></div>
        <div class="stat-label">قيد التوصيل</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
        <div class="stat-number"><?php echo $stats['delivered']; ?></div>
        <div class="stat-label">تم التوصيل</div>
    </div>
</div>

<!-- الفلاتر -->
<div class="filter-card">
    <form method="GET" class="filter-form">
        <div class="form-row">
            <div class="form-group">
                <label>بحث</label>
                <input type="text" name="search" placeholder="رقم التحويل، السائق، الموقع..." 
                       value="<?php echo htmlspecialchars($search); ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label>السائق</label>
                <select name="driver_id" class="form-control">
                    <option value="">الكل</option>
                    <?php foreach ($drivers as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo $filter_driver == $d['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>الحالة</label>
                <select name="status" class="form-control">
                    <option value="">الكل</option>
                    <option value="assigned" <?php echo $filter_status == 'assigned' ? 'selected' : ''; ?>>تم التعيين</option>
                    <option value="in_transit" <?php echo $filter_status == 'in_transit' ? 'selected' : ''; ?>>قيد التوصيل</option>
                    <option value="delivered" <?php echo $filter_status == 'delivered' ? 'selected' : ''; ?>>تم التوصيل</option>
                </select>
            </div>
            
            <div class="form-group" style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">🔍 بحث</button>
                <a href="all_driver_tasks.php" class="btn btn-secondary">إعادة تعيين</a>
            </div>
        </div>
    </form>
</div>

<!-- قائمة المهام -->
<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم التحويل</th>
                <th>السائق</th>
                <th>الهاتف</th>
                <th>المركبة</th>
                <th>من</th>
                <th>إلى</th>
                <th>تاريخ الاستلام</th>
                <th>تاريخ التسليم</th>
                <th>الحالة</th>
                <th>الفرع</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tasks)): ?>
            <tr>
                <td colspan="11" style="text-align: center; padding: 30px;">
                    <p style="font-size: 1.2em; color: #718096;">لا توجد مهام متاحة</p>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($tasks as $task): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($task['transfer_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($task['driver_name']); ?></td>
                <td><?php echo htmlspecialchars($task['driver_phone']); ?></td>
                <td><?php echo htmlspecialchars($task['vehicle_number']); ?></td>
                <td><?php echo htmlspecialchars($task['from_location']); ?></td>
                <td><?php echo htmlspecialchars($task['to_location']); ?></td>
                <td><?php echo $task['pickup_date'] ? date('Y-m-d', strtotime($task['pickup_date'])) : '-'; ?></td>
                <td><?php echo $task['delivery_date'] ? date('Y-m-d', strtotime($task['delivery_date'])) : '-'; ?></td>
                <td>
                    <span class="status-badge status-<?php echo $task['status']; ?>">
                        <?php 
                        $statuses = [
                            'pending' => 'قيد الانتظار',
                            'assigned' => 'تم التعيين',
                            'in_transit' => 'قيد التوصيل',
                            'delivered' => 'تم التوصيل',
                            'cancelled' => 'ملغي'
                        ];
                        echo $statuses[$task['status']];
                        ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($task['branch_name']); ?></td>
                <td>
                    <a href="../transfers/view_transfer.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">عرض</a>
                    <?php if ($task['status'] != 'delivered' && $task['status'] != 'cancelled'): ?>
                    <a href="../transfers/assign_driver.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary">تعديل</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.filter-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
}

.filter-form .form-row {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1.5fr auto;
    gap: 15px;
    align-items: end;
}

.filter-form .form-group {
    display: flex;
    flex-direction: column;
}

.filter-form label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 8px;
    font-size: 0.9em;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

@media (max-width: 768px) {
    .filter-form .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
