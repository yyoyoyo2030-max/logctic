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

// إحصائيات السائقين مع عدد التوصيلات المكتملة
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
    LEFT JOIN transfers t ON da.transfer_id = t.id
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

<div class="page-header">
    <h1>📊 تقرير أداء السائقين</h1>
</div>

<!-- كروت الإحصائيات العامة -->
<div class="dashboard-stats">
    <div class="stat-card success">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <h3><?php echo $total_delivered; ?></h3>
            <p>إجمالي التوصيلات المكتملة</p>
        </div>
    </div>
    
    <div class="stat-card info">
        <div class="stat-icon">🚚</div>
        <div class="stat-info">
            <h3><?php echo $total_in_transit; ?></h3>
            <p>جاري التوصيل</p>
        </div>
    </div>
    

    
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
            <h3><?php echo count($drivers_stats); ?></h3>
            <p>إجمالي السائقين</p>
        </div>
    </div>
</div>

<!-- جدول تفاصيل السائقين -->
<div class="content-section">
    <div class="section-header">
        <h2>📋 تفاصيل أداء السائقين</h2>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>اسم السائق</th>
                    <th>الهاتف</th>
                    <th>نوع المركبة</th>
                    <th>رقم المركبة</th>
                    <th>✅ تم التوصيل</th>
                    <th>🚚 جاري التوصيل</th>
                    <th>📊 الإجمالي</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($drivers_stats)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: #718096;">
                        <div style="font-size: 2.5em; margin-bottom: 10px;">📭</div>
                        لا يوجد سائقين مسجلين في النظام
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
                        <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                        <td><?php echo htmlspecialchars($driver['vehicle_type']); ?></td>
                        <td>
                            <span class="badge-vehicle">
                                <?php echo htmlspecialchars($driver['vehicle_number']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-delivered" style="font-size: 1.1em; padding: 8px 14px;">
                                <?php echo $driver['delivered_count']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-in_transit" style="font-size: 1.1em; padding: 8px 14px;">
                                <?php echo $driver['in_transit_count']; ?>
                            </span>
                        </td>
                        <td>
                            <strong style="font-size: 1.15em;">
                                <?php echo $driver['total_assignments']; ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($driver['is_available']): ?>
                                <span class="status-badge status-delivered">✓ متاح</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">✗ غير متاح</span>
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
<div class="content-section">
    <div class="section-header">
        <h2>🏆 أفضل السائقين أداءً</h2>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <?php 
        $top_drivers = array_slice($drivers_stats, 0, 3);
        $medals = ['🥇', '🥈', '🥉'];
        $colors = ['#FFD700', '#C0C0C0', '#CD7F32'];
        foreach ($top_drivers as $index => $driver): 
            if ($driver['delivered_count'] > 0):
        ?>
        <div class="top-driver-card" style="border-top: 4px solid <?php echo $colors[$index]; ?>;">
            <div style="text-align: center;">
                <div style="font-size: 3em; margin-bottom: 10px;"><?php echo $medals[$index]; ?></div>
                <h3 style="font-size: 1.3em; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($driver['name']); ?>
                </h3>
                <div style="font-size: 2em; font-weight: 700; color: <?php echo $colors[$index]; ?>; margin: 15px 0;">
                    <?php echo $driver['delivered_count']; ?>
                </div>
                <p class="text-muted" style="font-size: 0.95em;">تحويلة مكتملة</p>
            </div>
        </div>
        <?php 
            endif;
        endforeach; 
        ?>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
