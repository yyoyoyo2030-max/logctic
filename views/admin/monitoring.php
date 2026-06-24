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

// Auto-cleanup: delete logs older than 7 days
$conn->exec("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");

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
$filter_date = $_GET['date'] ?? date('Y-m-d');

// Statistics
$stats = [];
$stmt = $conn->query("SELECT COUNT(*) as c FROM system_logs WHERE log_type='activity' AND DATE(created_at) = CURDATE()");
$stats['today_activities'] = $stmt->fetch()['c'];

$stmt = $conn->query("SELECT COUNT(*) as c FROM system_logs WHERE log_type='error' AND DATE(created_at) = CURDATE()");
$stats['today_errors'] = $stmt->fetch()['c'];

$stmt = $conn->query("SELECT COUNT(*) as c FROM system_logs WHERE log_type='page_view' AND DATE(created_at) = CURDATE()");
$stats['today_views'] = $stmt->fetch()['c'];

$stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM system_logs WHERE DATE(created_at) = CURDATE() AND user_id IS NOT NULL");
$stats['active_users'] = $stmt->fetch()['c'];

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
    $where[] = 'DATE(sl.created_at) = ?';
    $params[] = $filter_date;
}
$whereStr = implode(' AND ', $where);

$stmt = $conn->prepare("SELECT sl.*, u.full_name, u.username FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id WHERE $whereStr ORDER BY sl.created_at DESC LIMIT 200");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get all users for filter dropdown
$users = $conn->query("SELECT id, full_name, username FROM users ORDER BY full_name")->fetchAll();

require_once '../../includes/header.php';
?>

<style>
/* ========================================
   Monitoring Dashboard — Premium Styles
   ======================================== */

/* Page Title */
.monitoring-title {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
}
.monitoring-title .title-icon {
    width: 52px;
    height: 52px;
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, #0ea5e9, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
    box-shadow: 0 8px 24px rgba(14, 165, 233, 0.35);
}
.monitoring-title h1 {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.monitoring-title p {
    font-size: var(--font-sm);
    color: var(--text-secondary);
    margin: 2px 0 0;
}
.live-dot {
    width: 10px;
    height: 10px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
    animation: livePulse 1.5s ease-in-out infinite;
}
@keyframes livePulse {
    0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
    50% { opacity: 0.7; transform: scale(1.3); box-shadow: 0 0 0 8px rgba(16,185,129,0); }
}

/* ---- Stat Cards ---- */
.monitoring-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.m-stat-card {
    position: relative;
    border-radius: var(--radius-lg);
    padding: 24px 22px;
    color: #fff;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: default;
}
.m-stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 14px 36px rgba(0,0,0,0.18);
}
.m-stat-card::before {
    content: '';
    position: absolute;
    top: -30%;
    left: -30%;
    width: 160%;
    height: 160%;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.18) 0%, transparent 60%);
    pointer-events: none;
}
.m-stat-card::after {
    content: '';
    position: absolute;
    bottom: -20px;
    right: -20px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    pointer-events: none;
}
.m-stat-card.card-blue   { background: linear-gradient(135deg, #0ea5e9, #3b82f6); box-shadow: 0 8px 28px rgba(14,165,233,0.3); }
.m-stat-card.card-red    { background: linear-gradient(135deg, #ef4444, #f97316); box-shadow: 0 8px 28px rgba(239,68,68,0.3); }
.m-stat-card.card-green  { background: linear-gradient(135deg, #10b981, #06b6d4); box-shadow: 0 8px 28px rgba(16,185,129,0.3); }
.m-stat-card.card-orange { background: linear-gradient(135deg, #f59e0b, #f97316); box-shadow: 0 8px 28px rgba(245,158,11,0.3); }

.m-stat-card .card-icon {
    font-size: 28px;
    margin-bottom: 12px;
    opacity: 0.9;
}
.m-stat-card .card-value {
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 6px;
}
.m-stat-card .card-label {
    font-size: var(--font-sm);
    opacity: 0.88;
    font-weight: 500;
}

/* ---- Filter Bar ---- */
.monitoring-filter {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 18px 22px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
}
.monitoring-filter label {
    font-size: var(--font-sm);
    font-weight: 600;
    color: var(--text-secondary);
    white-space: nowrap;
}
.monitoring-filter select,
.monitoring-filter input[type="date"] {
    padding: 9px 14px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: var(--font-sm);
    font-family: 'Cairo', sans-serif;
    min-width: 160px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.monitoring-filter select:focus,
.monitoring-filter input[type="date"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
}
.monitoring-filter .filter-btn {
    padding: 9px 24px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    font-family: 'Cairo', sans-serif;
    font-size: var(--font-sm);
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.monitoring-filter .filter-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(14,165,233,0.35);
}

/* ---- Tabs ---- */
.monitoring-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 0;
    border-bottom: 2px solid var(--border-color);
}
.monitoring-tabs .tab-btn {
    padding: 12px 28px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    font-family: 'Cairo', sans-serif;
    font-size: var(--font-base);
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.25s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -2px;
}
.monitoring-tabs .tab-btn:hover {
    color: var(--primary);
    background: rgba(14,165,233,0.04);
}
.monitoring-tabs .tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.tab-content {
    display: none;
    animation: fadeInTab 0.35s ease;
}
.tab-content.active {
    display: block;
}
@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ---- Logs Table ---- */
.logs-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
}
.logs-table-wrap {
    overflow-x: auto;
}
.logs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--font-sm);
}
.logs-table thead {
    background: var(--bg-secondary);
}
.logs-table thead th {
    padding: 14px 16px;
    text-align: right;
    font-weight: 700;
    color: var(--text-secondary);
    font-size: var(--font-xs);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    border-bottom: 2px solid var(--border-color);
}
.logs-table tbody tr {
    transition: background 0.15s;
}
.logs-table tbody tr:nth-child(even) {
    background: var(--bg-secondary);
}
.logs-table tbody tr:hover {
    background: rgba(14,165,233,0.06);
}
.logs-table tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}
.logs-table .time-cell {
    white-space: nowrap;
    color: var(--text-secondary);
    font-size: var(--font-xs);
}
.logs-table .time-cell .time-relative {
    display: block;
    color: var(--primary);
    font-weight: 600;
    font-size: var(--font-xs);
}
.logs-table .user-cell {
    font-weight: 600;
}
.log-type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-align: center;
}
.log-type-badge.type-activity  { background: rgba(14,165,233,0.12); color: #0284c7; }
.log-type-badge.type-error     { background: rgba(239,68,68,0.12);  color: #dc2626; }
.log-type-badge.type-page_view { background: rgba(16,185,129,0.12); color: #059669; }

[data-theme="dark"] .log-type-badge.type-activity  { background: rgba(56,189,248,0.18); color: #7dd3fc; }
[data-theme="dark"] .log-type-badge.type-error     { background: rgba(248,113,113,0.18); color: #fca5a5; }
[data-theme="dark"] .log-type-badge.type-page_view { background: rgba(52,211,153,0.18); color: #6ee7b7; }

.detail-cell {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-secondary);
    font-size: var(--font-xs);
}
.ip-cell {
    font-family: 'Courier New', monospace;
    font-size: var(--font-xs);
    color: var(--text-secondary);
    direction: ltr;
}
.page-cell {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: var(--font-xs);
    color: var(--text-secondary);
    direction: ltr;
}

/* Empty state */
.logs-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}
.logs-empty i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.25;
    display: block;
}
.logs-empty p {
    font-size: var(--font-base);
}

/* ---- PostHog Tab ---- */
.posthog-section {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
}
.posthog-header {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color);
}
.posthog-header h3 {
    margin: 0;
    font-size: var(--font-lg);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.posthog-header h3 i { color: #f59e0b; }
.posthog-link-btn {
    padding: 8px 20px;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    font-family: 'Cairo', sans-serif;
    font-size: var(--font-sm);
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.posthog-link-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(245,158,11,0.35);
    color: #fff;
}
.posthog-iframe-wrap {
    width: 100%;
    height: 80vh;
    min-height: 500px;
}
.posthog-iframe-wrap iframe {
    width: 100%;
    height: 100%;
    border: none;
}

/* ---- Cleanup Footer ---- */
.monitoring-footer {
    margin-top: 28px;
    padding: 20px 24px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    box-shadow: var(--shadow-sm);
}
.monitoring-footer .footer-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-secondary);
    font-size: var(--font-sm);
}
.monitoring-footer .footer-info i {
    color: var(--warning);
    font-size: 18px;
}
.cleanup-btn {
    padding: 10px 24px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    font-family: 'Cairo', sans-serif;
    font-size: var(--font-sm);
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.cleanup-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(239,68,68,0.35);
}

/* Flash message */
.flash-toast {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    padding: 14px 32px;
    border-radius: var(--radius-lg);
    font-weight: 600;
    font-size: var(--font-base);
    box-shadow: 0 8px 32px rgba(16,185,129,0.4);
    animation: flashSlide 0.4s ease, flashFade 0.5s 3.5s ease forwards;
    display: flex;
    align-items: center;
    gap: 10px;
}
@keyframes flashSlide {
    from { transform: translateX(-50%) translateY(-30px); opacity: 0; }
    to   { transform: translateX(-50%) translateY(0); opacity: 1; }
}
@keyframes flashFade {
    to { opacity: 0; transform: translateX(-50%) translateY(-20px); pointer-events: none; }
}

/* Auto-refresh indicator */
.refresh-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: var(--font-xs);
    color: var(--text-secondary);
    margin-right: auto;
    padding: 0 12px;
}
.refresh-indicator .spinner {
    width: 14px;
    height: 14px;
    border: 2px solid var(--border-color);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    display: none;
}
.refresh-indicator.loading .spinner { display: block; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Responsive */
@media (max-width: 768px) {
    .monitoring-stats { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .m-stat-card { padding: 18px 16px; }
    .m-stat-card .card-value { font-size: 1.6rem; }
    .monitoring-filter { flex-direction: column; align-items: stretch; }
    .monitoring-filter select,
    .monitoring-filter input[type="date"] { min-width: 100%; }
    .monitoring-tabs .tab-btn { padding: 10px 16px; font-size: var(--font-sm); }
    .posthog-iframe-wrap { height: 50vh; min-height: 350px; }
    .monitoring-footer { flex-direction: column; text-align: center; }
}
@media (max-width: 480px) {
    .monitoring-stats { grid-template-columns: 1fr; }
}
</style>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="flash-toast">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?>
    </div>
<?php endif; ?>

<!-- Page Title -->
<div class="monitoring-title">
    <div class="title-icon">
        <i class="fas fa-shield-halved"></i>
    </div>
    <div>
        <h1>مراقبة النظام والسلوكيات</h1>
        <p><span class="live-dot"></span> مراقبة مباشرة — يتم التحديث تلقائياً كل 30 ثانية</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="monitoring-stats">
    <div class="m-stat-card card-blue">
        <div class="card-icon"><i class="fas fa-mouse-pointer"></i></div>
        <div class="card-value" data-stat="today-activities"><?php echo $stats['today_activities']; ?></div>
        <div class="card-label">نشاطات اليوم</div>
    </div>
    <div class="m-stat-card card-red">
        <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="card-value" data-stat="today-errors"><?php echo $stats['today_errors']; ?></div>
        <div class="card-label">أخطاء اليوم</div>
    </div>
    <div class="m-stat-card card-green">
        <div class="card-icon"><i class="fas fa-eye"></i></div>
        <div class="card-value" data-stat="today-views"><?php echo $stats['today_views']; ?></div>
        <div class="card-label">مشاهدات الصفحات</div>
    </div>
    <div class="m-stat-card card-orange">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <div class="card-value" data-stat="active-users"><?php echo $stats['active_users']; ?></div>
        <div class="card-label">مستخدمين نشطين</div>
    </div>
</div>

<!-- Filter Bar -->
<form class="monitoring-filter" method="GET" action="monitoring.php">
    <label><i class="fas fa-filter"></i> تصفية:</label>

    <select name="type">
        <option value="all"       <?php echo $filter_type==='all'?'selected':''; ?>>الكل</option>
        <option value="activity"  <?php echo $filter_type==='activity'?'selected':''; ?>>نشاط</option>
        <option value="error"     <?php echo $filter_type==='error'?'selected':''; ?>>خطأ</option>
        <option value="page_view" <?php echo $filter_type==='page_view'?'selected':''; ?>>مشاهدة صفحة</option>
    </select>

    <select name="user_id">
        <option value="">جميع المستخدمين</option>
        <?php foreach ($users as $u): ?>
            <option value="<?php echo $u['id']; ?>" <?php echo $filter_user==$u['id']?'selected':''; ?>>
                <?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">

    <button type="submit" class="filter-btn">
        <i class="fas fa-search"></i> بحث
    </button>

    <div class="refresh-indicator" id="refresh-indicator">
        <div class="spinner"></div>
        <span>تحديث تلقائي</span>
    </div>
</form>

<!-- Tabs -->
<div class="monitoring-tabs">
    <button class="tab-btn active" data-tab="logs-tab">
        <i class="fas fa-list-alt"></i> سجل النشاطات والأخطاء
    </button>
    <button class="tab-btn" data-tab="posthog-tab">
        <i class="fas fa-video"></i> تسجيلات الجلسات (PostHog)
    </button>
</div>

<!-- Tab 1: Logs -->
<div class="tab-content active" id="logs-tab">
    <div class="logs-card">
        <div class="logs-table-wrap">
            <?php if (count($logs) > 0): ?>
            <table class="logs-table" id="logs-table">
                <thead>
                    <tr>
                        <th>الوقت</th>
                        <th>المستخدم</th>
                        <th>النوع</th>
                        <th>الإجراء</th>
                        <th>التفاصيل</th>
                        <th>الصفحة</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="time-cell">
                            <span class="time-relative"><?php echo timeAgo($log['created_at']); ?></span>
                            <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="user-cell">
                            <?php echo htmlspecialchars($log['full_name'] ?: ($log['username'] ?: '—')); ?>
                        </td>
                        <td>
                            <?php
                                $typeLabels = [
                                    'activity'  => 'نشاط',
                                    'error'     => 'خطأ',
                                    'page_view' => 'مشاهدة'
                                ];
                            ?>
                            <span class="log-type-badge type-<?php echo htmlspecialchars($log['log_type']); ?>">
                                <?php echo $typeLabels[$log['log_type']] ?? $log['log_type']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['action'] ?? '—'); ?></td>
                        <td class="detail-cell" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                            <?php echo htmlspecialchars($log['details'] ?? '—'); ?>
                        </td>
                        <td class="page-cell" title="<?php echo htmlspecialchars($log['page_url'] ?? ''); ?>">
                            <?php echo htmlspecialchars($log['page_url'] ?? '—'); ?>
                        </td>
                        <td class="ip-cell"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="logs-empty">
                <i class="fas fa-inbox"></i>
                <p>لا توجد سجلات مطابقة للفلاتر المحددة</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tab 2: PostHog -->
<div class="tab-content" id="posthog-tab">
    <div class="posthog-section">
        <div class="posthog-header">
            <h3><i class="fas fa-play-circle"></i> تسجيلات جلسات المستخدمين</h3>
            <a href="https://us.posthog.com/project/484728/replay/home" target="_blank" rel="noopener" class="posthog-link-btn">
                <i class="fas fa-external-link-alt"></i> فتح PostHog
            </a>
        </div>
        <div class="posthog-iframe-wrap">
            <iframe src="https://us.posthog.com/project/484728/replay/home" 
                    allow="fullscreen" 
                    loading="lazy"
                    sandbox="allow-same-origin allow-scripts allow-popups allow-forms"></iframe>
        </div>
    </div>
</div>

<!-- Footer / Cleanup -->
<div class="monitoring-footer">
    <div class="footer-info">
        <i class="fas fa-info-circle"></i>
        <span>يتم حذف السجلات التي مضى عليها أكثر من 7 أيام تلقائياً. يمكنك أيضاً الحذف يدوياً.</span>
    </div>
    <form method="POST" id="cleanup-form" style="margin:0;">
        <input type="hidden" name="action" value="cleanup">
        <button type="button" class="cleanup-btn" id="cleanup-btn">
            <i class="fas fa-broom"></i> تنظيف السجلات القديمة
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ---- Tab Switching ----
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.tab;
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // ---- Cleanup Button ----
    const cleanupBtn = document.getElementById('cleanup-btn');
    const cleanupForm = document.getElementById('cleanup-form');
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
                }).then(result => {
                    if (result.isConfirmed) cleanupForm.submit();
                });
            } else {
                if (confirm('سيتم حذف جميع السجلات الأقدم من 7 أيام. هل أنت متأكد؟')) {
                    cleanupForm.submit();
                }
            }
        });
    }

    // ---- Auto-Refresh every 30 seconds ----
    const indicator = document.getElementById('refresh-indicator');
    const tbody = document.getElementById('logs-tbody');

    setInterval(function() {
        // Don't refresh if user is on PostHog tab or interacting with a form
        const activeTab = document.querySelector('.tab-btn.active');
        if (activeTab && activeTab.dataset.tab !== 'logs-tab') return;
        if (document.activeElement && ['INPUT','SELECT','TEXTAREA'].includes(document.activeElement.tagName)) return;

        if (indicator) indicator.classList.add('loading');

        fetch(window.location.href)
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Update logs table body
                const newTbody = doc.getElementById('logs-tbody');
                if (newTbody && tbody && tbody.innerHTML !== newTbody.innerHTML) {
                    tbody.innerHTML = newTbody.innerHTML;
                    tbody.style.opacity = '0.6';
                    setTimeout(() => tbody.style.opacity = '1', 250);
                }

                // Update stat card values
                doc.querySelectorAll('[data-stat]').forEach(newEl => {
                    const key = newEl.dataset.stat;
                    const current = document.querySelector('[data-stat="' + key + '"]');
                    if (current && current.textContent !== newEl.textContent) {
                        current.textContent = newEl.textContent;
                        current.style.transform = 'scale(1.15)';
                        setTimeout(() => current.style.transform = '', 300);
                    }
                });
            })
            .catch(() => {})
            .finally(() => {
                if (indicator) indicator.classList.remove('loading');
            });
    }, 30000);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
