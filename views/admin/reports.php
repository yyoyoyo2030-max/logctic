<?php
/**
 * نظام إدارة اللوجستيك
 * تقارير أداء السائقين
 * عرض إحصائيات التوصيلات
 */

header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !canManageAllBranches()) {
    redirect('views/dashboard/dashboard.php');
}

// فلتر الفترة الزمنية
$period = $_GET['period'] ?? 'all';
$date_condition = '';

switch ($period) {
    case 'today':
        $date_condition = ' AND t.created_at >= CURDATE()';
        $date_condition_assigned = ' AND t2.created_at >= CURDATE()';
        break;
    case 'week':
        $date_condition = ' AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
        $date_condition_assigned = ' AND t2.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
        break;
    case 'month':
        $date_condition = ' AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        $date_condition_assigned = ' AND t2.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        break;
    default:
        $date_condition = '';
        $date_condition_assigned = '';
        $period = 'all';
        break;
}

// إحصائيات السائقين مع عدد التوصيلات المكتملة بناءً على الفلتر الزمني
$stmt = $conn->query("
    SELECT 
        d.id,
        d.name,
        d.phone,
        d.vehicle_type,
        d.vehicle_number,
        COUNT(CASE WHEN t.status = 'delivered' THEN 1 END) as delivered_count,
        COUNT(CASE WHEN t.status IN ('in_transit', 'assigned') THEN 1 END) as in_transit_count,
        COUNT(da.id) as total_assignments,
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM driver_assignments da2 
                JOIN transfers t2 ON da2.transfer_id = t2.id 
                WHERE da2.driver_id = d.id 
                AND t2.status IN ('assigned', 'in_transit')
            ) THEN 0
            ELSE 1
        END as is_available
    FROM drivers d
    LEFT JOIN driver_assignments da ON d.id = da.driver_id
    LEFT JOIN transfers t ON da.transfer_id = t.id $date_condition
    GROUP BY d.id, d.name, d.phone, d.vehicle_type, d.vehicle_number
    ORDER BY delivered_count DESC, d.name
");
$drivers_stats = $stmt->fetchAll();

// إحصائيات عامة
$total_delivered = 0;
$total_in_transit = 0;
foreach ($drivers_stats as $driver) {
    $total_delivered += $driver['delivered_count'];
    $total_in_transit += $driver['in_transit_count'];
}

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
.dash-filter-bar h1 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
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
.dashboard-stats .stat-card.success .stat-icon { background: rgba(16,185,129,0.12); color: #10b981; }
.dashboard-stats .stat-card.info .stat-icon { background: rgba(14,165,233,0.12); color: #0ea5e9; }

/* قائمة أفضل السائقين */
.top-drivers-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 600px;
    margin: 0 auto;
}
.driver-rank-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-primary);
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.driver-rank-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.rank-info-wrapper {
    display: flex;
    align-items: center;
    gap: 16px;
}
.rank-badge {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: bold;
    color: #fff;
}
.rank-1 .rank-badge { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
.rank-2 .rank-badge { background: linear-gradient(135deg, #94a3b8, #64748b); box-shadow: 0 2px 8px rgba(100,116,139,0.3); }
.rank-3 .rank-badge { background: linear-gradient(135deg, #d97706, #b45309); box-shadow: 0 2px 8px rgba(180,83,9,0.3); }
.driver-details h4 {
    margin: 0 0 4px;
    color: #1e293b;
    font-size: 1rem;
}
.driver-details p {
    margin: 0;
    font-size: 0.8rem;
    color: #64748b;
}
.score-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: rgba(16,185,129,0.1);
    color: #059669;
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: bold;
}
.score-badge span {
    font-size: 1.3rem;
    line-height: 1;
}
.score-badge small {
    font-size: 0.7rem;
    font-weight: normal;
    margin-top: 4px;
}
</style>

<!-- فلتر الفترة الزمنية -->
<div class="dash-filter-bar" style="margin-top: 20px;">
    <h1><i class="fas fa-chart-pie" style="color: #6366f1;"></i> تقرير أداء السائقين</h1>
    <div class="dash-period-tabs">
        <a href="reports.php?period=today" class="<?php echo $period === 'today' ? 'active' : ''; ?>">اليوم</a>
        <a href="reports.php?period=week" class="<?php echo $period === 'week' ? 'active' : ''; ?>">الأسبوع</a>
        <a href="reports.php?period=month" class="<?php echo $period === 'month' ? 'active' : ''; ?>">الشهر</a>
        <a href="reports.php?period=all" class="<?php echo $period === 'all' ? 'active' : ''; ?>">الكل</a>
    </div>
</div>

<!-- كروت الإحصائيات العامة -->
<div class="dashboard-stats">
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_delivered; ?></h3>
            <p>إجمالي التوصيلات المكتملة</p>
        </div>
    </div>
    
    <div class="stat-card info">
        <div class="stat-icon"><i class="fas fa-truck-fast"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_in_transit; ?></h3>
            <p>جاري التوصيل</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo count($drivers_stats); ?></h3>
            <p>إجمالي السائقين</p>
        </div>
    </div>
</div>

<!-- جدول تفاصيل السائقين -->
<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-list"></i> تفاصيل أداء السائقين</h2>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>اسم السائق</th>
                    <th>الهاتف</th>
                    <th>نوع المركبة</th>
                    <th>رقم المركبة</th>
                    <th><i class="fas fa-check" style="color:#10b981;"></i> تم التوصيل</th>
                    <th><i class="fas fa-truck" style="color:#0ea5e9;"></i> جاري التوصيل</th>
                    <th><i class="fas fa-chart-simple"></i> الإجمالي</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($drivers_stats)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                        <div style="font-size: 2.5em; margin-bottom: 10px;"><i class="fas fa-box-open"></i></div>
                        لا توجد بيانات متاحة لهذه الفترة
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($drivers_stats as $driver): ?>
                    <tr>
                        <td>
                            <strong>
                                <?php echo htmlspecialchars($driver['name']); ?>
                            </strong>
                        </td>
                        <td><span dir="ltr"><?php echo htmlspecialchars($driver['phone']); ?></span></td>
                        <td><?php echo htmlspecialchars($driver['vehicle_type']); ?></td>
                        <td>
                            <span class="badge-vehicle">
                                <?php echo htmlspecialchars($driver['vehicle_number']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($driver['delivered_count'] > 0): ?>
                                <span class="status-badge status-delivered" style="font-size: 1rem; padding: 6px 12px;">
                                    <?php echo $driver['delivered_count']; ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #cbd5e1; font-size: 0.85rem;">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($driver['in_transit_count'] > 0): ?>
                                <span class="status-badge status-in_transit" style="font-size: 1rem; padding: 6px 12px;">
                                    <?php echo $driver['in_transit_count']; ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #cbd5e1; font-size: 0.85rem;">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="font-size: 1.05em; color: #334155;">
                                <?php echo $driver['total_assignments']; ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($driver['is_available']): ?>
                                <span class="status-badge status-delivered"><i class="fas fa-check"></i> متاح</span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><i class="fas fa-times"></i> غير متاح</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ملخص الأداء -->
<?php if (!empty($drivers_stats)): ?>
<div class="content-section" style="background: transparent; box-shadow: none; padding: 0;">
    <div class="section-header" style="justify-content: center; margin-bottom: 24px;">
        <h2 style="font-size: 1.3rem;"><i class="fas fa-trophy" style="color: #fbbf24;"></i> أفضل السائقين أداءً</h2>
    </div>
    
    <div class="top-drivers-list">
        <?php 
        $top_drivers = array_slice($drivers_stats, 0, 3);
        $rank = 1;
        foreach ($top_drivers as $index => $driver): 
            if ($driver['delivered_count'] > 0):
        ?>
        <div class="driver-rank-item rank-<?php echo $rank; ?>">
            <div class="rank-info-wrapper">
                <div class="rank-badge">
                    <?php echo $rank; ?>
                </div>
                <div class="driver-details">
                    <h4><?php echo htmlspecialchars($driver['name']); ?></h4>
                    <p><i class="fas fa-truck-pickup" style="margin-left: 4px;"></i> <?php echo htmlspecialchars($driver['vehicle_type']); ?> - <?php echo htmlspecialchars($driver['vehicle_number']); ?></p>
                </div>
            </div>
            <div class="score-badge">
                <span><?php echo $driver['delivered_count']; ?></span>
                <small>مكتملة</small>
            </div>
        </div>
        <?php 
            $rank++;
            endif;
        endforeach; 
        ?>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
