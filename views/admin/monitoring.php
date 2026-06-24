<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('views/auth/login.php');
}

// Handle cleanup POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup') {
    $deleted = $conn->exec("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $_SESSION['flash_message'] = "تم حذف $deleted سجل قديم بنجاح";
    header('Location: monitoring.php');
    exit;
}

// Auto-cleanup - ONLY 5% probability to avoid locking the DB on every request
if (rand(1, 100) <= 5) {
    $conn->exec("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
}

// Relative time helper
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->d > 0) return 'منذ ' . $diff->d . ' يوم';
    if ($diff->h > 0) return 'منذ ' . $diff->h . ' ساعة';
    if ($diff->i > 0) return 'منذ ' . $diff->i . ' دقيقة';
    return 'الآن';
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$filter_user = $_GET['user_id'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Statistics (optimized single query to prevent 502s)
$stats = [
    'today_activities' => 0,
    'today_errors' => 0,
    'today_views' => 0,
    'active_users' => 0,
    'total_logs' => 0
];

$stmt = $conn->query("
    SELECT 
        SUM(CASE WHEN log_type = 'activity' AND created_at >= CURDATE() THEN 1 ELSE 0 END) as today_act,
        SUM(CASE WHEN log_type = 'error' AND created_at >= CURDATE() THEN 1 ELSE 0 END) as today_err,
        SUM(CASE WHEN log_type = 'page_view' AND created_at >= CURDATE() THEN 1 ELSE 0 END) as today_view,
        COUNT(DISTINCT CASE WHEN created_at >= CURDATE() THEN user_id ELSE NULL END) as act_users,
        COUNT(*) as total
    FROM system_logs
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $stats['today_activities'] = (int)$row['today_act'];
    $stats['today_errors'] = (int)$row['today_err'];
    $stats['today_views'] = (int)$row['today_view'];
    $stats['active_users'] = (int)$row['act_users'];
    $stats['total_logs'] = (int)$row['total'];
}

// Build query for logs
$where = ['1=1'];
$params = [];
if ($filter_type !== 'all') {
    $where[] = 'sl.log_type = ?';
    $params[] = $filter_type;
}
if ($filter_user) {
    $where[] = 'sl.user_id = ?';
    $params[] = $filter_user;
}
if ($filter_date) {
    $where[] = 'sl.created_at >= ? AND sl.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $filter_date;
    $params[] = $filter_date;
}
$whereStr = implode(' AND ', $where);

// إعطاء الأولوية للأخطاء للظهور في أعلى الجدول، ثم ترتيب الباقي حسب الأحدث
$stmt = $conn->prepare("SELECT sl.*, u.full_name, u.username FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id WHERE $whereStr ORDER BY CASE WHEN sl.log_type = 'error' THEN 1 ELSE 2 END ASC, sl.created_at DESC LIMIT 200");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get all users for filter dropdown
$users = $conn->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll();

// Most active users today
$stmt = $conn->query("SELECT u.full_name, u.username, COUNT(*) as cnt FROM system_logs sl JOIN users u ON sl.user_id = u.id WHERE sl.created_at >= CURDATE() GROUP BY sl.user_id ORDER BY cnt DESC LIMIT 5");
$active_users_list = $stmt->fetchAll();

// Recent errors
$stmt = $conn->query("SELECT sl.*, u.full_name FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id WHERE sl.log_type = 'error' ORDER BY sl.created_at DESC LIMIT 5");
$recent_errors = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<style>
/* ========================================
   Monitoring Dashboard V2 — Premium Styles
   ======================================== */

/* Page Header */
.mon-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.mon-header-right {
    display: flex;
    align-items: center;
    gap: 14px;
}
.mon-header-right .title-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0ea5e9, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.35);
}
.mon-header-right h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.mon-header-right p {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin: 2px 0 0;
}
.live-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}
.live-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: livePulse 1.5s ease-in-out infinite;
}
@keyframes livePulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
    50% { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
}
.mon-header-left {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* ---- Stat Cards ---- */
.mon-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.mon-stat {
    position: relative;
    border-radius: 14px;
    padding: 20px;
    color: #fff;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}
.mon-stat:hover {
    transform: translateY(-4px);
}
.mon-stat::before {
    content: '';
    position: absolute;
    top: -30%; left: -30%;
    width: 160%; height: 160%;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.15) 0%, transparent 60%);
    pointer-events: none;
}
.mon-stat.s-blue   { background: linear-gradient(135deg, #0ea5e9, #3b82f6); box-shadow: 0 6px 20px rgba(14,165,233,0.3); }
.mon-stat.s-red    { background: linear-gradient(135deg, #ef4444, #f97316); box-shadow: 0 6px 20px rgba(239,68,68,0.3); }
.mon-stat.s-green  { background: linear-gradient(135deg, #10b981, #06b6d4); box-shadow: 0 6px 20px rgba(16,185,129,0.3); }
.mon-stat.s-orange { background: linear-gradient(135deg, #f59e0b, #f97316); box-shadow: 0 6px 20px rgba(245,158,11,0.3); }
.mon-stat .s-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.mon-stat .s-top i { font-size: 24px; opacity: 0.85; }
.mon-stat .s-val {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
}
.mon-stat .s-lbl {
    font-size: 0.82rem;
    opacity: 0.9;
    font-weight: 500;
}

/* ---- Quick Panels (Errors + Active Users) ---- */
.mon-panels {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}
.mon-panel {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.mon-panel-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-primary);
}
.mon-panel-head i {
    font-size: 16px;
}
.mon-panel-head .error-icon { color: #ef4444; }
.mon-panel-head .users-icon { color: #f59e0b; }
.mon-panel-body {
    padding: 0;
}
.panel-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.82rem;
    transition: background 0.15s;
}
.panel-item:last-child { border-bottom: none; }
.panel-item:hover { background: rgba(14,165,233,0.04); }
.panel-item .item-name {
    font-weight: 600;
    color: var(--text-primary);
}
.panel-item .item-info {
    color: var(--text-secondary);
    font-size: 0.75rem;
    max-width: 50%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.panel-item .item-count {
    background: rgba(14,165,233,0.1);
    color: #0284c7;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.75rem;
}
.panel-item .item-time {
    color: var(--text-secondary);
    font-size: 0.72rem;
}
.panel-empty {
    padding: 30px 18px;
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.85rem;
}
.panel-empty i {
    font-size: 28px;
    opacity: 0.2;
    display: block;
    margin-bottom: 8px;
}

/* ---- Filter + Tabs Wrapper ---- */
.mon-controls {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    margin-bottom: 0;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.mon-filter {
    padding: 14px 18px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--border-color);
}
.mon-filter .f-group {
    display: flex;
    align-items: center;
    gap: 6px;
}
.mon-filter .f-group label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-secondary);
    white-space: nowrap;
}
.mon-filter select,
.mon-filter input[type="date"] {
    padding: 7px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.8rem;
    font-family: 'Cairo', sans-serif;
    min-width: 140px;
    transition: border-color 0.2s;
}
.mon-filter select:focus,
.mon-filter input[type="date"]:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14,165,233,0.12);
}
.mon-filter .f-btn {
    padding: 7px 18px;
    background: linear-gradient(135deg, #0ea5e9, #3b82f6);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-family: 'Cairo', sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.mon-filter .f-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(14,165,233,0.3);
}
.mon-filter .f-reset {
    padding: 7px 14px;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-family: 'Cairo', sans-serif;
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background 0.2s;
}
.mon-filter .f-reset:hover { background: var(--border-color); color: var(--text-primary); }
.mon-filter .f-spacer { flex: 1; }
.mon-filter .f-count {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 5px;
}
.mon-filter .f-count strong {
    color: var(--primary);
}

<!-- Filter + Content Wrapper -->
<div class="mon-controls">

/* ---- Logs Table ---- */
.mon-table-wrap { overflow-x: auto; }
.mon-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.mon-table thead { background: var(--bg-secondary); }
.mon-table thead th {
    padding: 12px 14px;
    text-align: right;
    font-weight: 700;
    color: var(--text-secondary);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
    border-bottom: 2px solid var(--border-color);
}
.mon-table tbody tr {
    transition: background 0.15s;
}
.mon-table tbody tr:nth-child(even) {
    background: var(--bg-secondary);
}
.mon-table tbody tr:hover {
    background: rgba(14,165,233,0.06);
}
.mon-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

/* Table cell types */
.t-time {
    white-space: nowrap;
    min-width: 80px;
}
.t-time .t-rel {
    display: block;
    color: #0ea5e9;
    font-weight: 600;
    font-size: 0.75rem;
}
.t-time .t-abs {
    color: var(--text-secondary);
    font-size: 0.7rem;
}
.t-user {
    font-weight: 600;
    white-space: nowrap;
}
.t-user i { color: var(--text-secondary); margin-left: 4px; font-size: 0.7rem; }

/* Type badges */
.t-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.7rem;
    font-weight: 700;
}
.t-badge.b-activity  { background: rgba(14,165,233,0.1); color: #0284c7; }
.t-badge.b-error     { background: rgba(239,68,68,0.1);  color: #dc2626; }
.t-badge.b-page_view { background: rgba(16,185,129,0.1); color: #059669; }
[data-theme="dark"] .t-badge.b-activity  { background: rgba(56,189,248,0.15); color: #7dd3fc; }
[data-theme="dark"] .t-badge.b-error     { background: rgba(248,113,113,0.15); color: #fca5a5; }
[data-theme="dark"] .t-badge.b-page_view { background: rgba(52,211,153,0.15); color: #6ee7b7; }

.t-action {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.t-detail {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-secondary);
    font-size: 0.72rem;
}
.t-page {
    font-family: monospace;
    font-size: 0.72rem;
    color: var(--text-secondary);
    direction: ltr;
    text-align: left;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.t-ip {
    font-family: 'Courier New', monospace;
    font-size: 0.72rem;
    color: var(--text-secondary);
    direction: ltr;
}

/* Empty */
.mon-empty {
    text-align: center;
    padding: 50px 20px;
    color: var(--text-secondary);
}
.mon-empty i { font-size: 40px; opacity: 0.2; display: block; margin-bottom: 12px; }

/* Video link */
.btn-video {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: rgba(245,158,11,0.1);
    color: #f59e0b;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-video:hover {
    background: #f59e0b;
    color: #fff;
}

/* Footer */
.mon-footer {
    margin-top: 20px;
    padding: 14px 18px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.mon-footer .f-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 0.8rem;
}
.mon-footer .f-info i { color: #f59e0b; }
.mon-footer .f-clean {
    padding: 8px 18px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-family: 'Cairo', sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: transform 0.2s;
}
.mon-footer .f-clean:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(239,68,68,0.3); }

/* Flash */
.mon-flash {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    padding: 12px 28px;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 8px 28px rgba(16,185,129,0.4);
    animation: flashIn 0.3s ease, flashOut 0.4s 3s ease forwards;
    display: flex;
    align-items: center;
    gap: 8px;
}
@keyframes flashIn { from { opacity: 0; transform: translateX(-50%) translateY(-20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
@keyframes flashOut { to { opacity: 0; transform: translateX(-50%) translateY(-20px); pointer-events: none; } }

/* Responsive */
@media (max-width: 900px) {
    .mon-stats { grid-template-columns: repeat(2, 1fr); }
    .mon-panels { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .mon-stats { grid-template-columns: 1fr; }
    .mon-filter { flex-direction: column; align-items: stretch; }
    .mon-filter select, .mon-filter input[type="date"] { min-width: 100%; }
    .mon-footer { flex-direction: column; text-align: center; }
}
</style>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="mon-flash">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="mon-header">
    <div class="mon-header-right">
        <div class="title-icon">
            <i class="fas fa-shield-halved"></i>
        </div>
        <div>
            <h1>مراقبة النظام والسلوكيات</h1>
            <p><span class="live-badge"><span class="live-dot"></span> مراقبة مباشرة</span> — يتم التحديث كل 30 ثانية</p>
        </div>
    </div>
    <div class="mon-header-left">
        <span style="font-size:0.78rem; color:var(--text-secondary);"><i class="fas fa-database"></i> إجمالي السجلات: <strong style="color:var(--primary)"><?php echo number_format($stats['total_logs']); ?></strong></span>
    </div>
</div>

<!-- Stat Cards -->
<div class="mon-stats">
    <div class="mon-stat s-blue">
        <div class="s-top"><i class="fas fa-mouse-pointer"></i><span class="s-lbl">اليوم</span></div>
        <div class="s-val" data-stat="today-activities"><?php echo $stats['today_activities']; ?></div>
        <div class="s-lbl">نشاطات</div>
    </div>
    <div class="mon-stat s-red">
        <div class="s-top"><i class="fas fa-exclamation-triangle"></i><span class="s-lbl">اليوم</span></div>
        <div class="s-val" data-stat="today-errors"><?php echo $stats['today_errors']; ?></div>
        <div class="s-lbl">أخطاء</div>
    </div>
    <div class="mon-stat s-green">
        <div class="s-top"><i class="fas fa-eye"></i><span class="s-lbl">اليوم</span></div>
        <div class="s-val" data-stat="today-views"><?php echo $stats['today_views']; ?></div>
        <div class="s-lbl">مشاهدات صفحات</div>
    </div>
    <div class="mon-stat s-orange">
        <div class="s-top"><i class="fas fa-users"></i><span class="s-lbl">اليوم</span></div>
        <div class="s-val" data-stat="active-users"><?php echo $stats['active_users']; ?></div>
        <div class="s-lbl">مستخدمين نشطين</div>
    </div>
</div>

<!-- Quick Panels: Recent Errors + Active Users -->
<div class="mon-panels">
    <!-- Recent Errors -->
    <div class="mon-panel">
        <div class="mon-panel-head"><i class="fas fa-bug error-icon"></i> آخر الأخطاء</div>
        <div class="mon-panel-body">
            <?php if (count($recent_errors) > 0): ?>
                <?php foreach ($recent_errors as $err): ?>
                <div class="panel-item">
                    <div>
                        <span class="item-name"><?php echo htmlspecialchars($err['full_name'] ?: 'مجهول'); ?></span>
                        <span class="item-info" style="display:block; margin-top:2px;"><?php echo htmlspecialchars(mb_substr($err['action'], 0, 60)); ?></span>
                    </div>
                    <span class="item-time"><?php echo timeAgo($err['created_at']); ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="panel-empty"><i class="fas fa-check-circle" style="color:#10b981;opacity:0.5;"></i> لا توجد أخطاء — النظام يعمل بكفاءة!</div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Active Users -->
    <div class="mon-panel">
        <div class="mon-panel-head"><i class="fas fa-user-clock users-icon"></i> المستخدمون الأكثر نشاطاً اليوم</div>
        <div class="mon-panel-body">
            <?php if (count($active_users_list) > 0): ?>
                <?php foreach ($active_users_list as $au): ?>
                <div class="panel-item">
                    <span class="item-name"><i class="fas fa-user-circle" style="color:var(--text-secondary);margin-left:6px;"></i><?php echo htmlspecialchars($au['full_name'] ?: $au['username']); ?></span>
                    <span class="item-count"><?php echo $au['cnt']; ?> إجراء</span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="panel-empty"><i class="fas fa-users"></i> لا يوجد نشاط بعد اليوم</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Controls: Filter + Tabs + Content -->
<div class="mon-controls">
    <!-- Cleanup Bar -->
    <div class="mon-footer" style="margin-bottom:0;border-radius:12px 12px 0 0;">
        <div class="f-info">
            <i class="fas fa-info-circle"></i>
            <span>يتم حذف السجلات الأقدم من 7 أيام تلقائياً. يمكنك الحذف يدوياً أيضاً.</span>
        </div>
        <form method="POST" id="cleanup-form" style="margin:0;">
            <input type="hidden" name="action" value="cleanup">
            <button type="button" class="f-clean" id="cleanup-btn">
                <i class="fas fa-broom"></i> تنظيف السجلات القديمة
            </button>
        </form>
    </div>
    <!-- Filter -->
    <form class="mon-filter" method="GET" action="monitoring.php" id="filter-form">
        <div class="f-group">
            <label><i class="fas fa-filter"></i> النوع:</label>
            <select name="type" id="f-type">
                <option value="all"       <?php echo $filter_type==='all'?'selected':''; ?>>الكل</option>
                <option value="activity"  <?php echo $filter_type==='activity'?'selected':''; ?>>نشاط</option>
                <option value="error"     <?php echo $filter_type==='error'?'selected':''; ?>>خطأ</option>
                <option value="page_view" <?php echo $filter_type==='page_view'?'selected':''; ?>>مشاهدة صفحة</option>
            </select>
        </div>
        <div class="f-group">
            <label><i class="fas fa-user"></i> المستخدم:</label>
            <select name="user_id" id="f-user">
                <option value="">الجميع</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $filter_user==$u['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="f-group">
            <label><i class="fas fa-calendar"></i> التاريخ:</label>
            <input type="date" name="date" id="f-date" value="<?php echo htmlspecialchars($filter_date); ?>">
        </div>
        <button type="submit" class="f-btn"><i class="fas fa-search"></i> بحث</button>
        <a href="monitoring.php" class="f-reset"><i class="fas fa-undo"></i> إعادة تعيين</a>
        <div class="f-spacer"></div>
        <div class="f-count"><i class="fas fa-list"></i> النتائج: <strong><?php echo count($logs); ?></strong></div>
    </form>

    <!-- Logs Table -->
    <div style="padding: 10px 0;">
        <div class="mon-table-wrap">
            <?php if (count($logs) > 0): ?>
            <table class="mon-table" id="mon-table">
                <thead>
                    <tr>
                        <th>الوقت</th>
                        <th>المستخدم</th>
                        <th>النوع</th>
                        <th>الإجراء</th>
                        <th>التفاصيل</th>
                        <th>الصفحة</th>
                        <th>التسجيل</th>
                    </tr>
                </thead>
                <tbody id="mon-tbody">
                    <?php foreach ($logs as $log):
                        $typeLabels = ['activity'=>'نشاط','error'=>'خطأ','page_view'=>'مشاهدة'];
                        $typeIcons  = ['activity'=>'fa-bolt','error'=>'fa-bug','page_view'=>'fa-eye'];
                    ?>
                    <tr>
                        <td class="t-time">
                            <span class="t-rel"><?php echo timeAgo($log['created_at']); ?></span>
                            <span class="t-abs"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                        </td>
                        <td class="t-user"><?php echo htmlspecialchars($log['full_name'] ?: ($log['username'] ?: '—')); ?></td>
                        <td>
                            <span class="t-badge b-<?php echo $log['log_type']; ?>">
                                <i class="fas <?php echo $typeIcons[$log['log_type']] ?? 'fa-circle'; ?>"></i>
                                <?php echo $typeLabels[$log['log_type']] ?? $log['log_type']; ?>
                            </span>
                        </td>
                        <td class="t-action" title="<?php echo htmlspecialchars($log['action']); ?>"><?php echo htmlspecialchars($log['action']); ?></td>
                        <td class="t-detail" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>"><?php echo htmlspecialchars($log['details'] ?? '—'); ?></td>
                        <td class="t-page" title="<?php echo htmlspecialchars($log['page_url'] ?? ''); ?>"><?php echo htmlspecialchars($log['page_url'] ?? '—'); ?></td>
                        <td>
                            <?php 
                            $ph_session_id = null;
                            if (!empty($log['details'])) {
                                $parsed = json_decode($log['details'], true);
                                if (is_array($parsed) && !empty($parsed['ph_session_id'])) {
                                    $ph_session_id = $parsed['ph_session_id'];
                                }
                            }
                            
                            if ($ph_session_id): ?>
                                <a href="https://us.posthog.com/project/484728/replay/<?php echo urlencode($ph_session_id); ?>" target="_blank" class="btn-video" title="مشاهدة تسجيل الجلسة مباشرة">
                                    <i class="fas fa-play"></i> تشغيل
                                </a>
                            <?php elseif ($log['user_id']): ?>
                                <a href="https://us.posthog.com/project/484728/person/<?php echo urlencode($log['user_id']); ?>#recordings" target="_blank" class="btn-video" title="بحث عن تسجيلات المستخدم">
                                    <i class="fas fa-video"></i> مستخدم
                                </a>
                            <?php else: ?>
                                <span style="color:var(--text-secondary);font-size:0.7rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="mon-empty">
                <i class="fas fa-inbox"></i>
                <p>لا توجد سجلات مطابقة للتصفية المحددة</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---- Instant filter on select change ----
    ['f-type', 'f-user'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function() {
                document.getElementById('filter-form').submit();
            });
        }
    });
    var dateEl = document.getElementById('f-date');
    if (dateEl) {
        dateEl.addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
    }

    // ---- Cleanup ----
    var cleanupBtn = document.getElementById('cleanup-btn');
    var cleanupForm = document.getElementById('cleanup-form');
    if (cleanupBtn && cleanupForm) {
        cleanupBtn.addEventListener('click', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'تنظيف السجلات؟',
                    text: 'سيتم حذف جميع السجلات الأقدم من 7 أيام نهائياً.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then(function(result) {
                    if (result.isConfirmed) cleanupForm.submit();
                });
            } else {
                if (confirm('سيتم حذف جميع السجلات الأقدم من 7 أيام. هل أنت متأكد؟')) {
                    cleanupForm.submit();
                }
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
