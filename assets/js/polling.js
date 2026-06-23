/**
 * نظام AJAX Polling للتحديث اللحظي
 * يقوم بجلب البيانات الحديثة من السيرفر بشكل دوري
 */

class RealtimePoller {
    constructor(config = {}) {
        // إعدادات افتراضية
        this.config = {
            statsInterval: config.statsInterval || 5000,        // كل 5 ثواني
            notificationsInterval: config.notificationsInterval || 3000,  // كل 3 ثواني
            tasksInterval: config.tasksInterval || 8000,        // كل 8 ثواني
            transfersInterval: config.transfersInterval || 10000, // كل 10 ثواني
            onStatsUpdate: config.onStatsUpdate || null,
            onNotificationUpdate: config.onNotificationUpdate || null,
            onTasksUpdate: config.onTasksUpdate || null,
            onTransfersUpdate: config.onTransfersUpdate || null,
            enableVisibilityCheck: config.enableVisibilityCheck !== false, // تفعيل فحص ظهور الصفحة
            ...config
        };
        
        this.intervals = {};
        this.lastUpdate = {
            stats: Date.now(),
            notifications: Date.now(),
            tasks: Date.now(),
            transfers: Date.now()
        };
        
        this.isPageVisible = true;
        this.init();
    }
    
    init() {
        // مراقبة ظهور/إخفاء الصفحة
        if (this.config.enableVisibilityCheck) {
            document.addEventListener('visibilitychange', () => {
                this.isPageVisible = !document.hidden;
                if (this.isPageVisible) {
                    console.log('🔄 الصفحة ظهرت - استئناف التحديثات');
                    this.resumeAll();
                } else {
                    console.log('⏸️ الصفحة مخفية - إيقاف التحديثات مؤقتاً');
                    this.pauseAll();
                }
            });
        }
        
        console.log('✅ نظام AJAX Polling جاهز');
    }
    
    // ================================================
    // 1. تحديث الإحصائيات
    // ================================================
    startStatsPolling() {
        this.stopStatsPolling(); // إيقاف أي polling سابق
        
        const pollStats = async () => {
            if (!this.isPageVisible && this.config.enableVisibilityCheck) return;
            
            try {
                const response = await fetch('../../api/stats.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) throw new Error('فشل جلب الإحصائيات');
                
                const data = await response.json();
                
                if (data.success) {
                    this.lastUpdate.stats = Date.now();
                    this.updateStatsUI(data.stats);
                    
                    // استدعاء callback إذا كان موجود
                    if (typeof this.config.onStatsUpdate === 'function') {
                        this.config.onStatsUpdate(data.stats);
                    }
                }
            } catch (error) {
                console.error('❌ خطأ في تحديث الإحصائيات:', error);
            }
        };
        
        // تنفيذ فوري ثم تكرار
        pollStats();
        this.intervals.stats = setInterval(pollStats, this.config.statsInterval);
        console.log(`📊 بدأ تحديث الإحصائيات كل ${this.config.statsInterval/1000} ثانية`);
    }
    
    stopStatsPolling() {
        if (this.intervals.stats) {
            clearInterval(this.intervals.stats);
            delete this.intervals.stats;
        }
    }
    
    updateStatsUI(stats) {
        // تحديث عداد التحويلات الإجمالي
        const totalTransfersEl = document.querySelector('[data-stat="total-transfers"]');
        if (totalTransfersEl && stats.total_transfers !== undefined) {
            this.animateNumber(totalTransfersEl, stats.total_transfers);
        }
        
        // تحديث التحويلات قيد التنفيذ
        const inProgressEl = document.querySelector('[data-stat="inprogress-transfers"]');
        if (inProgressEl && stats.inprogress_transfers !== undefined) {
            this.animateNumber(inProgressEl, stats.inprogress_transfers);
        }
        
        // تحديث التحويلات المكتملة
        const completedEl = document.querySelector('[data-stat="completed-transfers"]');
        if (completedEl && stats.completed_transfers !== undefined) {
            this.animateNumber(completedEl, stats.completed_transfers);
        }
        
        // تحديث التحويلات المعلقة
        const pendingEl = document.querySelector('[data-stat="pending-transfers"]');
        if (pendingEl && stats.pending_transfers !== undefined) {
            this.animateNumber(pendingEl, stats.pending_transfers);
        }
        
        // تحديث السائقين
        const driversEl = document.querySelector('[data-stat="total-drivers"]');
        if (driversEl && stats.total_drivers !== undefined) {
            this.animateNumber(driversEl, stats.total_drivers);
        }
        
        // تحديث المهام
        const tasksEl = document.querySelector('[data-stat="total-tasks"]');
        if (tasksEl && stats.total_tasks !== undefined) {
            this.animateNumber(tasksEl, stats.total_tasks);
        }
        
        const completedTasksEl = document.querySelector('[data-stat="completed-tasks"]');
        if (completedTasksEl && stats.completed_tasks !== undefined) {
            this.animateNumber(completedTasksEl, stats.completed_tasks);
        }
        
        // إضافة تأثير تحديث بصري
        document.querySelectorAll('[data-stat]').forEach(el => {
            el.classList.add('stat-updated');
            setTimeout(() => el.classList.remove('stat-updated'), 500);
        });
    }
    
    // ================================================
    // 2. تحديث الإشعارات
    // ================================================
    startNotificationsPolling() {
        this.stopNotificationsPolling();
        
        const pollNotifications = async () => {
            if (!this.isPageVisible && this.config.enableVisibilityCheck) return;
            
            try {
                const response = await fetch(`../../api/notifications.php?last=${this.lastUpdate.notifications}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) throw new Error('فشل جلب الإشعارات');
                
                const data = await response.json();
                
                if (data.success) {
                    // تحديث عدد غير المقروء
                    this.updateNotificationBadge(data.unread_count);
                    
                    // معالجة الإشعارات الجديدة
                    if (data.notifications && data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            this.handleNewNotification(notification);
                        });
                        this.lastUpdate.notifications = Date.now();
                    }
                    
                    // استدعاء callback
                    if (typeof this.config.onNotificationUpdate === 'function') {
                        this.config.onNotificationUpdate(data);
                    }
                }
            } catch (error) {
                console.error('❌ خطأ في تحديث الإشعارات:', error);
            }
        };
        
        pollNotifications();
        this.intervals.notifications = setInterval(pollNotifications, this.config.notificationsInterval);
        console.log(`🔔 بدأ تحديث الإشعارات كل ${this.config.notificationsInterval/1000} ثانية`);
    }
    
    stopNotificationsPolling() {
        if (this.intervals.notifications) {
            clearInterval(this.intervals.notifications);
            delete this.intervals.notifications;
        }
    }
    
    updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
                badge.classList.add('pulse-animation');
                setTimeout(() => badge.classList.remove('pulse-animation'), 1000);
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    handleNewNotification(notification) {
        // عرض Toast للإشعار الجديد
        if (notification.show_toast && typeof window.showToast === 'function') {
            const type = this.getNotificationType(notification.type);
            window.showToast(notification.message, type, 5000);
        }
        
        // تشغيل صوت إذا كان مطلوب
        if (notification.play_sound) {
            this.playNotificationSound();
        }
        
        // إشعار المتصفح
        if (notification.browser_notification && 'Notification' in window) {
            this.showBrowserNotification(notification);
        }
    }
    
    getNotificationType(type) {
        const typeMap = {
            'transfer_assigned': 'info',
            'transfer_completed': 'success',
            'task_assigned': 'info',
            'task_completed': 'success',
            'warning': 'warning',
            'error': 'error'
        };
        return typeMap[type] || 'info';
    }
    
    playNotificationSound() {
        try {
            const audio = new Audio('../../assets/sounds/notification.mp3');
            audio.volume = 0.5;
            audio.play().catch(e => console.log('لم يتم تشغيل الصوت:', e));
        } catch (error) {
            console.log('صوت الإشعار غير متاح');
        }
    }
    
    showBrowserNotification(notification) {
        if (Notification.permission === 'granted') {
            new Notification('نظام اللوجستيك', {
                body: notification.message,
                icon: '../../assets/images/logo.png',
                tag: `notification-${notification.id}`,
                requireInteraction: false
            });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    this.showBrowserNotification(notification);
                }
            });
        }
    }
    
    // ================================================
    // 3. تحديث حالة المهام
    // ================================================
    startTasksPolling() {
        this.stopTasksPolling();
        
        const pollTasks = async () => {
            if (!this.isPageVisible && this.config.enableVisibilityCheck) return;
            
            try {
                const response = await fetch('../../api/tasks-status.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) throw new Error('فشل جلب حالة المهام');
                
                const data = await response.json();
                
                if (data.success) {
                    this.updateTasksUI(data.tasks);
                    
                    if (typeof this.config.onTasksUpdate === 'function') {
                        this.config.onTasksUpdate(data.tasks);
                    }
                }
            } catch (error) {
                console.error('❌ خطأ في تحديث المهام:', error);
            }
        };
        
        pollTasks();
        this.intervals.tasks = setInterval(pollTasks, this.config.tasksInterval);
        console.log(`📋 بدأ تحديث المهام كل ${this.config.tasksInterval/1000} ثانية`);
    }
    
    stopTasksPolling() {
        if (this.intervals.tasks) {
            clearInterval(this.intervals.tasks);
            delete this.intervals.tasks;
        }
    }
    
    updateTasksUI(tasks) {
        const tasksContainer = document.querySelector('[data-tasks-list]');
        if (!tasksContainer || !tasks || tasks.length === 0) return;
        
        tasks.forEach(task => {
            const taskRow = document.querySelector(`[data-task-id="${task.id}"]`);
            if (taskRow) {
                // تحديث حالة المهمة
                const statusBadge = taskRow.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.className = `status-badge status-${task.status}`;
                    statusBadge.textContent = this.getStatusText(task.status);
                }
                
                // تأثير بصري
                taskRow.classList.add('row-updated');
                setTimeout(() => taskRow.classList.remove('row-updated'), 1000);
            }
        });
    }
    
    // ================================================
    // 4. تحديث حالة التحويلات + جلب الجديدة
    // ================================================
    startTransfersPolling() {
        this.stopTransfersPolling();
        
        const pollTransfers = async () => {
            if (!this.isPageVisible && this.config.enableVisibilityCheck) return;
            
            try {
                // جلب التحويلات الجديدة أو المحدثة
                const response = await fetch(`../../api/recent-transfers.php?last=${this.lastUpdate.transfers}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) throw new Error('فشل جلب حالة التحويلات');
                
                const data = await response.json();
                
                if (data.success && data.transfers && data.transfers.length > 0) {
                    this.lastUpdate.transfers = Date.now();
                    this.updateTransfersUI(data.transfers);
                    
                    if (typeof this.config.onTransfersUpdate === 'function') {
                        this.config.onTransfersUpdate(data.transfers);
                    }
                }
            } catch (error) {
                console.error('❌ خطأ في تحديث التحويلات:', error);
            }
        };
        
        pollTransfers();
        this.intervals.transfers = setInterval(pollTransfers, this.config.transfersInterval);
        console.log(`🚚 بدأ تحديث التحويلات كل ${this.config.transfersInterval/1000} ثانية`);
    }
    
    stopTransfersPolling() {
        if (this.intervals.transfers) {
            clearInterval(this.intervals.transfers);
            delete this.intervals.transfers;
        }
    }
    
    updateTransfersUI(transfers) {
        const transfersTable = document.querySelector('[data-transfers-list] tbody');
        if (!transfersTable || !transfers || transfers.length === 0) return;
        
        let hasNewTransfers = false;
        
        transfers.forEach(transfer => {
            const existingRow = document.querySelector(`[data-transfer-id="${transfer.id}"]`);
            
            if (existingRow) {
                // تحديث الصف الموجود
                const statusBadge = existingRow.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.className = `status-badge status-${transfer.status}`;
                    statusBadge.textContent = this.getStatusText(transfer.status);
                }
                
                const driverCell = existingRow.querySelector('[data-driver]');
                if (driverCell && transfer.driver_name) {
                    driverCell.textContent = transfer.driver_name;
                }
                
                existingRow.classList.add('row-updated');
                setTimeout(() => existingRow.classList.remove('row-updated'), 1000);
            } else {
                // إضافة صف جديد
                hasNewTransfers = true;
                this.addNewTransferRow(transfer, transfersTable);
            }
        });
        
        if (hasNewTransfers && typeof window.showToast === 'function') {
            window.showToast('📦 تحويل جديد تم إضافته!', 'success', 4000);
        }
    }
    
    addNewTransferRow(transfer, tbody) {
        const isAdmin = document.querySelector('[data-transfers-list] th:nth-child(2)')?.textContent.includes('الفرع');
        
        const row = document.createElement('tr');
        row.setAttribute('data-transfer-id', transfer.id);
        row.classList.add('new-row-highlight');
        
        let rowHTML = `<td>${transfer.transfer_number || transfer.tracking_number || '-'}</td>`;
        
        if (isAdmin && transfer.branch_name) {
            rowHTML += `<td>${transfer.branch_name}</td>`;
        }
        
        rowHTML += `
            <td>${transfer.from_location || '-'}</td>
            <td>${transfer.to_location || '-'}</td>
            <td>
                <span class="status-badge status-${transfer.status}">
                    ${this.getStatusText(transfer.status)}
                </span>
            </td>
            <td>${this.formatDateTime(transfer.created_at)}</td>
            <td class="actions">
                <a href="../transfers/view_transfer.php?id=${transfer.id}" class="btn btn-sm btn-info">
                    <i class="fas fa-eye"></i> عرض
                </a>
                ${transfer.status === 'pending' ? `
                <a href="../transfers/assign_driver.php?id=${transfer.id}" class="btn btn-sm btn-success">
                    <i class="fas fa-user-plus"></i> تعيين
                </a>` : ''}
            </td>
        `;
        
        row.innerHTML = rowHTML;
        tbody.insertBefore(row, tbody.firstChild);
        
        setTimeout(() => row.classList.remove('new-row-highlight'), 2000);
        
        // حذف الصف الأخير إذا تجاوز 8 صفوف
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 8) {
            tbody.removeChild(rows[rows.length - 1]);
        }
    }
    
    formatDateTime(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}`;
    }
    
    // ================================================
    // مساعدات
    // ================================================
    animateNumber(element, targetValue) {
        const currentValue = parseInt(element.textContent) || 0;
        if (currentValue === targetValue) return;
        
        const duration = 500;
        const steps = 20;
        const stepValue = (targetValue - currentValue) / steps;
        const stepDuration = duration / steps;
        
        let step = 0;
        const timer = setInterval(() => {
            step++;
            if (step >= steps) {
                element.textContent = targetValue;
                clearInterval(timer);
            } else {
                element.textContent = Math.round(currentValue + (stepValue * step));
            }
        }, stepDuration);
    }
    
    getStatusText(status) {
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
    // التحكم في التشغيل
    // ================================================
    startAll() {
        this.startStatsPolling();
        this.startNotificationsPolling();
        this.startTasksPolling();
        this.startTransfersPolling();
        console.log('▶️ بدأ جميع خدمات التحديث اللحظي');
    }
    
    stopAll() {
        this.stopStatsPolling();
        this.stopNotificationsPolling();
        this.stopTasksPolling();
        this.stopTransfersPolling();
        console.log('⏹️ توقفت جميع خدمات التحديث اللحظي');
    }
    
    pauseAll() {
        // الإيقاف المؤقت - يحفظ الحالة لكن يوقف الطلبات
        Object.keys(this.intervals).forEach(key => {
            if (this.intervals[key]) {
                clearInterval(this.intervals[key]);
            }
        });
    }
    
    resumeAll() {
        // استئناف التحديثات
        if (this.intervals.stats !== undefined) this.startStatsPolling();
        if (this.intervals.notifications !== undefined) this.startNotificationsPolling();
        if (this.intervals.tasks !== undefined) this.startTasksPolling();
        if (this.intervals.transfers !== undefined) this.startTransfersPolling();
    }
    
    // تحديث فوري لخدمة معينة
    refreshStats() {
        this.stopStatsPolling();
        this.startStatsPolling();
    }
    
    refreshNotifications() {
        this.stopNotificationsPolling();
        this.startNotificationsPolling();
    }
    
    refreshTasks() {
        this.stopTasksPolling();
        this.startTasksPolling();
    }
    
    refreshTransfers() {
        this.stopTransfersPolling();
        this.startTransfersPolling();
    }
}

// تصدير للاستخدام العالمي
window.RealtimePoller = RealtimePoller;

// إنشاء instance عالمي إذا كان يوجد عنصر dashboard
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-realtime-dashboard]')) {
        window.realtimePoller = new RealtimePoller({
            statsInterval: 5000,
            notificationsInterval: 3000,
            tasksInterval: 8000,
            transfersInterval: 10000
        });
        
        // قراءة حالة التشغيل من localStorage
        const isAutoStartEnabled = localStorage.getItem('realtimePolling') !== 'disabled';
        
        // إعداد زر التحكم
        const toggleBtn = document.getElementById('realtime-toggle-btn');
        if (toggleBtn) {
            // تطبيق الحالة المحفوظة
            if (isAutoStartEnabled) {
                window.realtimePoller.startAll();
                toggleBtn.classList.add('active');
                toggleBtn.querySelector('.toggle-status').textContent = 'مُفعّل';
                toggleBtn.title = 'إيقاف التحديث اللحظي';
            } else {
                toggleBtn.querySelector('.toggle-status').textContent = 'موقف';
                toggleBtn.title = 'تفعيل التحديث اللحظي';
            }
            
            // معالج النقر
            toggleBtn.addEventListener('click', () => {
                const isActive = toggleBtn.classList.contains('active');
                
                if (isActive) {
                    // إيقاف
                    window.realtimePoller.stopAll();
                    toggleBtn.classList.remove('active');
                    toggleBtn.querySelector('.toggle-status').textContent = 'موقف';
                    toggleBtn.title = 'تفعيل التحديث اللحظي';
                    localStorage.setItem('realtimePolling', 'disabled');
                    
                    if (typeof window.showToast === 'function') {
                        window.showToast('⏸️ تم إيقاف التحديث اللحظي', 'warning', 2000);
                    }
                } else {
                    // تشغيل
                    window.realtimePoller.startAll();
                    toggleBtn.classList.add('active');
                    toggleBtn.querySelector('.toggle-status').textContent = 'مُفعّل';
                    toggleBtn.title = 'إيقاف التحديث اللحظي';
                    localStorage.setItem('realtimePolling', 'enabled');
                    
                    if (typeof window.showToast === 'function') {
                        window.showToast('▶️ تم تفعيل التحديث اللحظي', 'success', 2000);
                    }
                }
            });
        } else if (isAutoStartEnabled) {
            // إذا لم يكن هناك زر، ابدأ تلقائياً إذا كان مفعّلاً
            window.realtimePoller.startAll();
        }
        
        console.log('✅ نظام التحديث اللحظي جاهز');
    }
});
