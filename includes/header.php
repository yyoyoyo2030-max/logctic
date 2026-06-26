<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Logistic Pro | نظام إدارة اللوجستيات المتقدم</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg">
    
    <!-- DNS Prefetch للتحميل الأسرع -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- CSS - تحميل أساسي -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/style.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <?php require_once __DIR__ . '/posthog_config.php'; ?>
    
    <!-- PostHog Tracking -->
    <script>
        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys onSessionId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
        posthog.init('<?php echo getPosthogSetting("api_key"); ?>', {
            api_host: '<?php echo getPosthogSetting("host"); ?>',
            person_profiles: 'identified_only',
            session_recording: {
                maskAllInputs: false // Disable masking to see what users actually type (except passwords automatically)
            }
        });
    </script>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <script>
        // Identify the logged-in user in PostHog
        posthog.identify(
            '<?php echo $_SESSION['user_id']; ?>',
            {
                name: '<?php echo addslashes($_SESSION['full_name'] ?? "مستخدم"); ?>',
                role: '<?php echo addslashes($_SESSION['role'] ?? ""); ?>'
            }
        );
    </script>
    <?php endif; ?>
    
    <!-- JavaScript - تحميل مؤجل -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php' || basename($_SERVER['PHP_SELF']) == 'reports.php'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous" defer></script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/umd/simple-datatables.min.js" crossorigin="anonymous" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" crossorigin="anonymous" defer></script>
</head>
<body>
    
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="فتح القائمة">
        <span></span>
        <span></span>
        <span></span>
    </button>
    
    <!-- Mobile Home Button -->
    <a href="<?php echo SITE_URL; ?>/views/dashboard/dashboard.php" class="mobile-home-btn" id="mobile-home-btn" title="القائمة الرئيسية">
        <i class="fas fa-home"></i>
        <span>الرئيسية</span>
    </a>
    
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="three-body">
            <div class="three-body__dot"></div>
            <div class="three-body__dot"></div>
            <div class="three-body__dot"></div>
        </div>
    </div>
    

    
    <div class="wrapper">
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="أسواق الرشيد" class="app-logo" style="width: 80px; height: auto; margin-bottom: 10px;">
                <h2>أسواق الرشيد</h2>
                <p><?php echo $_SESSION['full_name']; ?></p>
                <small><?php 
                    $role_labels = [
                        'admin' => 'مدير النظام',
                        'logistics_manager' => 'مدير لوجستك',
                        'warehouse_manager' => 'مسئول مستودعات',
                        'drivers_manager' => 'مسئول سائقين',
                        'warehouse_entry' => 'مدخل مستودع',
                        'branch_entry' => 'مدخل فرع',
                        'driver' => 'مشيك'
                    ];
                    echo $role_labels[$_SESSION['role']] ?? 'مستخدم';
                ?></small>
                <button id="theme-toggle" class="theme-toggle" title="تبديل الوضع">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
            
            <ul class="sidebar-menu">
                <?php if ($_SESSION['role'] == 'driver'): ?>
                    <li><a href="<?php echo SITE_URL; ?>/views/drivers/driver_transfers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'driver_transfers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i> <span>التحويلات الجارية</span>
                    </a></li>
                    <li><a href="<?php echo SITE_URL; ?>/views/drivers/driver_tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'driver_tasks.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tasks"></i> <span>المهام الجارية</span>
                    </a></li>
                <?php else: ?>
                    <li><a href="<?php echo SITE_URL; ?>/views/dashboard/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                    </a></li>
                    
                    <li><a href="<?php echo SITE_URL; ?>/views/transfers/transfers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'transfers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-truck-loading"></i> <span>إدارة الشحنات</span>
                    </a></li>
                    
                    <li><a href="<?php echo SITE_URL; ?>/views/tasks/tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' || basename($_SERVER['PHP_SELF']) == 'add_task.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list-check"></i> <span>إدارة المهام</span>
                    </a></li>
                    
                    <?php if (isDriversManager()): ?>
                    <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/drivers/drivers.php') !== false ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/views/drivers/drivers.php">
                            <i class="fas fa-id-card"></i> <span>إدارة السائقين</span>
                        </a></li>
                    <?php endif; ?>
                    
                    <?php if (isAdmin()): ?>
                    <li><a href="<?php echo SITE_URL; ?>/views/admin/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> <span>إدارة المستخدمين</span>
                    </a></li>
                    <?php endif; ?>

                    <?php if (isWarehouseManager()): ?>
                    <li><a href="<?php echo SITE_URL; ?>/views/admin/branches.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'branches.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i> <span>الفروع والمواقع</span>
                    </a></li>
                    <?php endif; ?>
                    
                    <?php if (isAdmin() || isLogisticsManager()): ?>
                    <li><a href="<?php echo SITE_URL; ?>/views/admin/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie"></i> <span>التقارير والإحصائيات</span>
                    </a></li>
                    <?php endif; ?>

                    <?php if (isAdmin()): ?>
                    <li><a href="<?php echo SITE_URL; ?>/views/admin/whatsapp_settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'whatsapp_settings.php' ? 'active' : ''; ?>">
                        <i class="fab fa-whatsapp"></i> <span>إعدادات الواتساب</span>
                    </a></li>
                    
                    <li><a href="<?php echo SITE_URL; ?>/views/admin/monitoring.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'monitoring.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shield-halved"></i> <span>مراقبة النظام</span>
                    </a></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <li><a href="<?php echo SITE_URL; ?>/views/auth/logout.php">
                    <i class="fas fa-right-from-bracket"></i> <span>Logout</span>
                </a></li>
            </ul>
        </nav>
        
        <div class="main-content">
            <div class="container">
