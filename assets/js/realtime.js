/**
 * نظام التحديث اللحظي في الخلفية
 * Real-time Updates System
 */

// ================================================
// 1. إعدادات النظام
// ================================================
const UPDATE_CONFIG = {
    interval: 3000,        // 3 ثواني
    transfersInterval: 3000, // 3 ثواني للشحنات
    endpoints: {
        notifications: '/logctic/api/notifications.php',
        stats: '/logctic/api/stats.php',
        transfers: '/logctic/api/transfers-status.php',
        tasks: '/logctic/api/tasks-status.php',
        markRead: '/logctic/api/mark_notification_read.php'
    },
    maxRetries: 3,
    retryDelay: 5000
};

// ================================================
// 2. حالة النظام
// ================================================
let updateIntervals = {};
let retryCount = 0;
let isOnline = true;
let lastUpdate = {};

// ================================================
// 3. نظام الإشعارات اللحظية
// ================================================
class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.lastCheck = Date.now();
        this.unreadCount = 0;
    }

    async checkNew() {
        try {
            const response = await fetch(UPDATE_CONFIG.endpoints.notifications + '?last=' + this.lastCheck);
            const data = await response.json();

            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notif => {
                    this.addNotification(notif);
                });
                this.lastCheck = Date.now();
            }
        } catch (error) {
            console.error('فشل جلب الإشعارات:', error);
        }
    }

    addNotification(notif) {
        this.notifications.unshift(notif);
        this.unreadCount++;
        
        // تحديث الواجهة
        this.updateBadge();
        
        // عرض Toast
        if (notif.show_toast) {
            showToast(notif.message, notif.type || 'info');
        }
        
        // صوت تنبيه
        if (notif.play_sound) {
            this.playSound();
        }
        
        // إشعار المتصفح
        if (notif.browser_notification) {
            this.showBrowserNotification(notif);
        }
    }

    updateBadge() {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.textContent = this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'block' : 'none';
        }
    }

    playSound() {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZizcIGGm98OScTgwOUKnl87FjHAU7k9n0yHgrBSh+zPLaizsKGGS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsKGmS57OihUhELTKXh8bllHgU2jdXzzn0vBSh+zPDajDsK');
        audio.volume = 0.3;
        audio.play().catch(() => {}); // تجاهل الأخطاء إذا كان الصوت معطلاً
    }

    showBrowserNotification(notif) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Logistic Pro', {
                body: notif.message,
                icon: '/assets/images/logo.png',
                badge: '/assets/images/badge.png'
            });
        }
    }

    async markAsRead(id) {
        try {
            const response = await fetch(UPDATE_CONFIG.endpoints.markRead, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const notif = this.notifications.find(n => n.id === id);
                if (notif && !notif.is_read) {
                    notif.is_read = 1;
                    this.unreadCount--;
                    this.updateBadge();
                    this.renderNotifications();
                }
            }
        } catch (error) {
            console.error('فشل تحديد الإشعار كمقروء:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch(UPDATE_CONFIG.endpoints.markRead, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: 'all' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.notifications.forEach(n => n.is_read = 1);
                this.unreadCount = 0;
                this.updateBadge();
                this.renderNotifications();
                showToast('تم تحديد جميع الإشعارات كمقروءة', 'success');
            }
        } catch (error) {
            console.error('فشل تحديد جميع الإشعارات كمقروءة:', error);
        }
    }
    
    renderNotifications() {
        const list = document.getElementById('notification-list');
        if (!list) return;
        
        if (this.notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>لا توجد إشعارات جديدة</p>
                </div>
            `;
            return;
        }
        
        list.innerHTML = this.notifications.map(notif => {
            const iconType = notif.type || 'info';
            const iconMap = {
                'success': 'fa-circle-check',
                'warning': 'fa-triangle-exclamation',
                'error': 'fa-circle-xmark',
                'info': 'fa-circle-info'
            };
            
            const timeAgo = this.getTimeAgo(notif.created_at);
            
            return `
                <div class="notification-item ${notif.is_read ? '' : 'unread'}" data-id="${notif.id}">
                    <div class="notification-icon ${iconType}">
                        <i class="fas ${iconMap[iconType]}"></i>
                    </div>
                    <div class="notification-content">
                        <p class="message">${notif.message}</p>
                        <span class="time">
                            <i class="fas fa-clock"></i>
                            ${timeAgo}
                        </span>
                    </div>
                </div>
            `;
        }).join('');
        
        // إضافة مستمعي الأحداث
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = parseInt(item.dataset.id);
                this.markAsRead(id);
            });
        });
    }
    
    getTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = Math.floor((now - time) / 1000); // بالثواني
        
        if (diff < 60) return 'الآن';
        if (diff < 3600) return `منذ ${Math.floor(diff / 60)} دقيقة`;
        if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} ساعة`;
        return `منذ ${Math.floor(diff / 86400)} يوم`;
    }
}

// ================================================
// 4. تحديث الإحصائيات اللحظية
// ================================================
class StatsUpdater {
    async update() {
        try {
            const response = await fetch(UPDATE_CONFIG.endpoints.stats);
            const data = await response.json();

            if (data.success) {
                this.updateDashboard(data.stats);
                lastUpdate.stats = Date.now();
            }
        } catch (error) {
            console.error('فشل تحديث الإحصائيات:', error);
        }
    }

    updateDashboard(stats) {
        // تحديث عدد الشحنات
        const totalElement = document.getElementById('total-transfers');
        if (totalElement) {
            this.animateNumber(totalElement, stats.total_transfers);
        }

        // تحديث الشحنات المكتملة
        const completedElement = document.getElementById('completed-transfers');
        if (completedElement) {
            this.animateNumber(completedElement, stats.completed_transfers);
        }

        // تحديث الشحنات قيد التنفيذ
        const inProgressElement = document.getElementById('inprogress-transfers');
        if (inProgressElement) {
            this.animateNumber(inProgressElement, stats.inprogress_transfers);
        }

        // تحديث الشحنات المعلقة
        const pendingElement = document.getElementById('pending-transfers');
        if (pendingElement) {
            this.animateNumber(pendingElement, stats.pending_transfers);
        }
    }

    animateNumber(element, targetValue) {
        const currentValue = parseInt(element.textContent) || 0;
        if (currentValue === targetValue) return;

        const duration = 1000; // 1 ثانية
        const steps = 20;
        const stepValue = (targetValue - currentValue) / steps;
        let currentStep = 0;

        const interval = setInterval(() => {
            currentStep++;
            const newValue = Math.round(currentValue + (stepValue * currentStep));
            element.textContent = newValue;

            if (currentStep >= steps) {
                clearInterval(interval);
                element.textContent = targetValue;
            }
        }, duration / steps);
    }
}

// ================================================
// 5. تحديث حالة الشحنات
// ================================================
class TransfersUpdater {
    async update() {
        try {
            const response = await fetch(UPDATE_CONFIG.endpoints.transfers);
            const data = await response.json();

            if (data.success && data.updates.length > 0) {
                data.updates.forEach(update => {
                    this.updateTransferRow(update);
                });
                lastUpdate.transfers = Date.now();
            }
        } catch (error) {
            console.error('فشل تحديث الشحنات:', error);
        }
    }

    updateTransferRow(update) {
        const row = document.querySelector(`#transfer-${update.id}`);
        if (!row) return;

        // تحديث الحالة
        const statusBadge = row.querySelector('.status-badge');
        if (statusBadge && statusBadge.textContent !== update.status) {
            statusBadge.textContent = update.status;
            statusBadge.className = `status-badge status-${update.status}`;
            
            // تأثير وميض
            row.style.backgroundColor = '#e0f2fe';
            setTimeout(() => {
                row.style.backgroundColor = '';
            }, 2000);

            // إشعار Toast
            showToast(`تم تحديث حالة الشحنة #${update.id} إلى: ${update.status}`, 'info');
        }

        // تحديث السائق
        const driverCell = row.querySelector('.driver-name');
        if (driverCell && update.driver_name) {
            driverCell.textContent = update.driver_name;
        }
    }
}

// ================================================
// 6. تحديث حالة المهام
// ================================================
class TasksUpdater {
    async update() {
        try {
            const response = await fetch(UPDATE_CONFIG.endpoints.tasks);
            const data = await response.json();

            if (data.success && data.updates.length > 0) {
                data.updates.forEach(update => {
                    this.updateTaskRow(update);
                });
                lastUpdate.tasks = Date.now();
            }
        } catch (error) {
            console.error('فشل تحديث المهام:', error);
        }
    }

    updateTaskRow(update) {
        const row = document.querySelector(`#task-${update.id}`);
        if (!row) return;

        // تحديث الحالة
        const statusBadge = row.querySelector('.status-badge');
        if (statusBadge && statusBadge.textContent !== update.status) {
            statusBadge.textContent = update.status;
            statusBadge.className = `status-badge status-${update.status}`;
            
            // تأثير وميض
            row.classList.add('row-highlight');
            setTimeout(() => {
                row.classList.remove('row-highlight');
            }, 2000);
        }
    }
}

// ================================================
// 7. مراقبة الاتصال بالإنترنت
// ================================================
class ConnectionMonitor {
    constructor() {
        window.addEventListener('online', () => this.onOnline());
        window.addEventListener('offline', () => this.onOffline());
    }

    onOnline() {
        isOnline = true;
        retryCount = 0;
        showToast('تم استعادة الاتصال بالإنترنت', 'success');
        
        // استئناف التحديثات
        startAllUpdates();
    }

    onOffline() {
        isOnline = false;
        showToast('انقطع الاتصال بالإنترنت - وضع Offline', 'warning');
        
        // إيقاف التحديثات
        stopAllUpdates();
    }
}

// ================================================
// 8. إنشاء كائنات النظام
// ================================================
const notificationSystem = new NotificationSystem();
const statsUpdater = new StatsUpdater();
const transfersUpdater = new TransfersUpdater();
const tasksUpdater = new TasksUpdater();
const connectionMonitor = new ConnectionMonitor();

// ================================================
// 9. بدء وإيقاف التحديثات
// ================================================
function startAllUpdates() {
    if (!isOnline) return;

    // تحديث الإشعارات كل 3 ثواني
    updateIntervals.notifications = setInterval(() => {
        notificationSystem.checkNew();
    }, UPDATE_CONFIG.interval);

    // تحديث الإحصائيات كل 3 ثواني
    updateIntervals.stats = setInterval(() => {
        statsUpdater.update();
    }, UPDATE_CONFIG.interval);

    // تحديث الشحنات كل 3 ثواني
    updateIntervals.transfers = setInterval(() => {
        transfersUpdater.update();
    }, UPDATE_CONFIG.transfersInterval);

    // تحديث المهام كل 3 ثواني
    updateIntervals.tasks = setInterval(() => {
        tasksUpdater.update();
    }, UPDATE_CONFIG.interval);

    console.log('✅ نظام التحديث اللحظي مفعّل');
}

function stopAllUpdates() {
    Object.keys(updateIntervals).forEach(key => {
        clearInterval(updateIntervals[key]);
    });
    updateIntervals = {};
    console.log('⏸️ نظام التحديث اللحظي متوقف');
}

// ================================================
// 10. التحكم في الصفحة
// ================================================
// إيقاف التحديثات عند مغادرة الصفحة
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        stopAllUpdates();
    } else {
        startAllUpdates();
    }
});

// إيقاف عند إغلاق الصفحة
window.addEventListener('beforeunload', () => {
    stopAllUpdates();
});

// ================================================
// 11. طلب إذن الإشعارات
// ================================================
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showToast('تم تفعيل إشعارات المتصفح', 'success');
            }
        });
    }
}

// ================================================
// 12. API عامة
// ================================================
window.realtimeSystem = {
    start: startAllUpdates,
    stop: stopAllUpdates,
    notifications: notificationSystem,
    stats: statsUpdater,
    transfers: transfersUpdater,
    tasks: tasksUpdater,
    requestNotificationPermission
};

// ================================================
// 13. بدء النظام تلقائياً
// ================================================
document.addEventListener('DOMContentLoaded', () => {
    // إضافة مستمعي أحداث الزر والقائمة
    const bellBtn = document.getElementById('notification-bell');
    const dropdown = document.getElementById('notification-dropdown');
    const markAllBtn = document.getElementById('mark-all-read');
    
    if (bellBtn && dropdown) {
        // فتح/إغلاق القائمة
        bellBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            
            // عرض الإشعارات عند الفتح
            if (dropdown.classList.contains('show')) {
                notificationSystem.renderNotifications();
            }
        });
        
        // إغلاق عند النقر خارج القائمة
        document.addEventListener('click', (e) => {
            if (!bellBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    }
    
    // زر تحديد الكل كمقروء
    if (markAllBtn) {
        markAllBtn.addEventListener('click', () => {
            notificationSystem.markAllAsRead();
        });
    }
    
    // بدء التحديثات
    startAllUpdates();
    
    // طلب إذن الإشعارات بعد 5 ثواني
    setTimeout(requestNotificationPermission, 5000);
    
    console.log('🔄 نظام التحديث اللحظي جاهز');
});
