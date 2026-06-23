/**
 * أمثلة استخدام نظام AJAX Polling
 * يمكنك نسخ هذه الأمثلة واستخدامها في صفحاتك
 */

// ================================================
// مثال 1: استخدام أساسي في لوحة التحكم
// ================================================
document.addEventListener('DOMContentLoaded', () => {
    // إنشاء instance من RealtimePoller
    const poller = new RealtimePoller({
        statsInterval: 5000,           // تحديث الإحصائيات كل 5 ثواني
        notificationsInterval: 3000,   // تحديث الإشعارات كل 3 ثواني
        tasksInterval: 8000,           // تحديث المهام كل 8 ثواني
        transfersInterval: 10000       // تحديث التحويلات كل 10 ثواني
    });
    
    // بدء جميع خدمات التحديث
    poller.startAll();
});

// ================================================
// مثال 2: استخدام مع callbacks مخصصة
// ================================================
const customPoller = new RealtimePoller({
    statsInterval: 5000,
    
    // عند تحديث الإحصائيات
    onStatsUpdate: (stats) => {
        console.log('📊 إحصائيات محدثة:', stats);
        
        // يمكنك إضافة منطق مخصص هنا
        if (stats.total_transfers > 100) {
            console.log('تحذير: عدد التحويلات تجاوز 100');
        }
    },
    
    // عند تحديث الإشعارات
    onNotificationUpdate: (data) => {
        console.log('🔔 إشعارات جديدة:', data.notifications);
        
        // تشغيل صوت مخصص أو إظهار رسالة
        if (data.notifications.length > 0) {
            document.title = `(${data.unread_count}) نظام اللوجستيك`;
        }
    },
    
    // عند تحديث المهام
    onTasksUpdate: (tasks) => {
        console.log('📋 مهام محدثة:', tasks);
    },
    
    // عند تحديث التحويلات
    onTransfersUpdate: (transfers) => {
        console.log('🚚 تحويلات محدثة:', transfers);
    }
});

// بدء خدمات محددة فقط
customPoller.startStatsPolling();
customPoller.startNotificationsPolling();

// ================================================
// مثال 3: التحكم في التشغيل والإيقاف
// ================================================
const controlledPoller = new RealtimePoller();

// بدء كل الخدمات
controlledPoller.startAll();

// إيقاف كل الخدمات
// controlledPoller.stopAll();

// إيقاف خدمة محددة
// controlledPoller.stopStatsPolling();

// تحديث فوري لخدمة محددة
document.getElementById('refresh-btn')?.addEventListener('click', () => {
    controlledPoller.refreshStats();
    window.showToast('تم تحديث الإحصائيات', 'success');
});

// ================================================
// مثال 4: استخدام بدون فحص ظهور الصفحة
// (مفيد للصفحات التي تحتاج تحديث مستمر حتى عند إخفائها)
// ================================================
const alwaysOnPoller = new RealtimePoller({
    enableVisibilityCheck: false,  // تعطيل فحص ظهور الصفحة
    statsInterval: 10000
});
alwaysOnPoller.startStatsPolling();

// ================================================
// مثال 5: استخدام في صفحة المهام فقط
// ================================================
if (document.querySelector('.tasks-page')) {
    const tasksPoller = new RealtimePoller({
        tasksInterval: 5000,
        onTasksUpdate: (tasks) => {
            // تحديث جدول المهام
            updateTasksTable(tasks);
        }
    });
    
    tasksPoller.startTasksPolling();
}

function updateTasksTable(tasks) {
    tasks.forEach(task => {
        const row = document.querySelector(`[data-task-id="${task.id}"]`);
        if (row) {
            // تحديث الحالة
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = `status-badge status-${task.status}`;
                statusBadge.textContent = getStatusText(task.status);
            }
        }
    });
}

// ================================================
// مثال 6: استخدام في صفحة التحويلات فقط
// ================================================
if (document.querySelector('.transfers-page')) {
    const transfersPoller = new RealtimePoller({
        transfersInterval: 7000,
        onTransfersUpdate: (transfers) => {
            updateTransfersTable(transfers);
        }
    });
    
    transfersPoller.startTransfersPolling();
}

function updateTransfersTable(transfers) {
    transfers.forEach(transfer => {
        const row = document.querySelector(`[data-transfer-id="${transfer.id}"]`);
        if (row) {
            // تحديث الحالة
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = `status-badge status-${transfer.status}`;
                statusBadge.textContent = getStatusText(transfer.status);
            }
            
            // تحديث اسم السائق إذا تم تعيينه
            const driverCell = row.querySelector('[data-driver]');
            if (driverCell && transfer.driver_name) {
                driverCell.textContent = transfer.driver_name;
            }
        }
    });
}

// ================================================
// مثال 7: مراقبة حالة الاتصال
// ================================================
const monitoredPoller = new RealtimePoller({
    statsInterval: 5000,
    onStatsUpdate: (stats) => {
        showConnectionStatus('connected');
    }
});

let connectionCheckInterval;

function startConnectionMonitoring() {
    connectionCheckInterval = setInterval(() => {
        // فحص آخر تحديث
        const lastUpdate = Date.now() - monitoredPoller.lastUpdate.stats;
        
        if (lastUpdate > 15000) { // أكثر من 15 ثانية بدون تحديث
            showConnectionStatus('disconnected');
        } else if (lastUpdate > 8000) { // بين 8-15 ثانية
            showConnectionStatus('reconnecting');
        } else {
            showConnectionStatus('connected');
        }
    }, 2000);
}

function showConnectionStatus(status) {
    let statusEl = document.querySelector('.connection-status');
    
    if (!statusEl) {
        statusEl = document.createElement('div');
        statusEl.className = 'connection-status';
        document.body.appendChild(statusEl);
    }
    
    const statusTexts = {
        connected: '🟢 متصل',
        disconnected: '🔴 غير متصل',
        reconnecting: '🟡 إعادة الاتصال...'
    };
    
    statusEl.textContent = statusTexts[status];
    statusEl.className = `connection-status ${status} show`;
    
    // إخفاء بعد 3 ثواني إذا كان متصل
    if (status === 'connected') {
        setTimeout(() => {
            statusEl.classList.remove('show');
        }, 3000);
    }
}

monitoredPoller.startAll();
startConnectionMonitoring();

// ================================================
// مثال 8: إيقاف التحديثات عند مغادرة الصفحة
// ================================================
window.addEventListener('beforeunload', () => {
    if (window.realtimePoller) {
        window.realtimePoller.stopAll();
        console.log('✅ تم إيقاف جميع خدمات التحديث');
    }
});

// ================================================
// مثال 9: إنشاء مؤشر مرئي للتحديث اللحظي
// ================================================
function createRealtimeIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'realtime-indicator';
    indicator.innerHTML = '<span>تحديث لحظي</span>';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.classList.add('active');
    }, 100);
    
    return indicator;
}

// عرض المؤشر عند بدء التحديثات
if (document.querySelector('[data-realtime-dashboard]')) {
    createRealtimeIndicator();
}

// ================================================
// مثال 10: دمج مع نظام Toast الموجود
// ================================================
const integratedPoller = new RealtimePoller({
    statsInterval: 5000,
    notificationsInterval: 3000,
    
    onStatsUpdate: (stats) => {
        // عرض toast فقط عند تغيير كبير
        const previousTotal = parseInt(
            document.querySelector('[data-stat="total-transfers"]')?.textContent || '0'
        );
        
        if (stats.total_transfers > previousTotal) {
            const diff = stats.total_transfers - previousTotal;
            if (typeof window.showToast === 'function') {
                window.showToast(`✨ ${diff} تحويل جديد`, 'info', 3000);
            }
        }
    },
    
    onNotificationUpdate: (data) => {
        if (data.notifications && data.notifications.length > 0) {
            data.notifications.forEach(notification => {
                if (notification.show_toast && typeof window.showToast === 'function') {
                    window.showToast(notification.message, 'info', 5000);
                }
            });
        }
    }
});

// ================================================
// دالة مساعدة لترجمة الحالات
// ================================================
function getStatusText(status) {
    const statusTexts = {
        'pending': 'معلق',
        'assigned': 'تم التعيين',
        'in_progress': 'جاري التنفيذ',
        'in_transit': 'قيد التوصيل',
        'completed': 'مكتمل',
        'delivered': 'تم التوصيل',
        'cancelled': 'ملغي'
    };
    return statusTexts[status] || status;
}

// ================================================
// تنظيف عند إلغاء تحميل الصفحة
// ================================================
window.addEventListener('unload', () => {
    if (window.realtimePoller) {
        window.realtimePoller.stopAll();
    }
});

console.log('✅ أمثلة AJAX Polling جاهزة للاستخدام');
