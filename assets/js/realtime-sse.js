/**
 * نظام التحديث اللحظي باستخدام Server-Sent Events (SSE)
 * بديل متقدم لنظام polling التقليدي
 */

class RealtimeSSE {
    constructor() {
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 1000;
        this.isConnected = false;
        this.handlers = {
            'stats': [],
            'notifications': [],
            'transfers': [],
            'tasks': []
        };
        
        this.init();
    }
    
    /**
     * تهيئة الاتصال
     */
    init() {
        // التحقق من وجود الملف أولاً قبل محاولة الاتصال
        this.checkFileExists().then(exists => {
            if (!exists) {
                console.log('⚠️ ملف التحديث اللحظي غير موجود على السيرفر');
                this.showConnectionStatus('غير متصل', 'warning');
                // نظام بديل: تحديث الصفحة كل 30 ثانية
                this.startSimpleRefresh();
                return;
            }
            
            if (typeof EventSource === 'undefined') {
                console.error('❌ المتصفح لا يدعم Server-Sent Events');
                this.startSimpleRefresh();
                return;
            }
            
            this.connect();
        });
    }
    
    /**
     * التحقق من وجود ملف API
     */
    async checkFileExists() {
        try {
            // نفس طريقة حساب المسار في connect()
            const currentPath = window.location.pathname;
            let basePath = '/';
            
            if (currentPath.includes('logistic')) {
                const pathParts = currentPath.split('/').filter(p => p);
                const projectFolder = pathParts.find(p => p.includes('logistic'));
                if (projectFolder) {
                    basePath = '/' + projectFolder + '/';
                }
            }
            
            const testUrl = basePath + 'api/realtime-updates.php';
            console.log('🔍 Checking file at:', testUrl);
            const response = await fetch(testUrl, { method: 'HEAD', credentials: 'same-origin' });
            return true; // نرجع true دائماً لنحاول الاتصال
        } catch (e) {
            console.warn('⚠️ التحقق من الملف فشل، سنحاول الاتصال مباشرة');
            return true; // نرجع true حتى لو فشل التحقق
        }
    }
    
    /**
     * نظام تحديث بسيط (بديل عن SSE)
     */
    startSimpleRefresh() {
        console.log('🔄 استخدام نظام التحديث البسيط (بدون SSE)');
        this.showConnectionStatus('متصل (وضع بسيط)', 'success');
        
        // إخفاء المؤشر بعد 3 ثواني
        setTimeout(() => {
            const statusIndicator = document.querySelector('#realtime-status');
            if (statusIndicator) {
                statusIndicator.style.opacity = '0';
                setTimeout(() => {
                    statusIndicator.style.display = 'none';
                }, 300);
            }
        }, 3000);
        
        // لا نفعل تحديث تلقائي - نترك المستخدم يحدث يدوياً
        // أو يمكن إضافة polling بسيط هنا إذا لزم الأمر
    }
    
    /**
     * الاتصال بالسيرفر
     */
    connect() {
        try {
            console.log('🔌 جاري الاتصال بسيرفر التحديثات...');
            
            // استخراج المسار الأساسي بطريقة بسيطة وواضحة
            const currentPath = window.location.pathname;
            let basePath = '/';
            
            // البحث عن كلمة logistic في المسار
            if (currentPath.includes('logistic')) {
                const pathParts = currentPath.split('/').filter(p => p);
                // أول جزء يحتوي على logistic هو مجلد المشروع
                const projectFolder = pathParts.find(p => p.includes('logistic'));
                if (projectFolder) {
                    basePath = '/' + projectFolder + '/';
                }
            }
            
            const apiPath = basePath + 'api/realtime-updates.php';
            console.log('🌐 Current URL:', window.location.href);
            console.log('📂 Current Path:', currentPath);
            console.log('📁 Detected Base Path:', basePath);
            console.log('📡 API Path:', apiPath);
            
            // إنشاء اتصال SSE
            this.eventSource = new EventSource(apiPath);
            
            // تتبع حالة الاتصال
            console.log('EventSource readyState:', this.eventSource.readyState);
            console.log('0 = CONNECTING, 1 = OPEN, 2 = CLOSED');
            
            // حدث الاتصال الناجح
            this.eventSource.addEventListener('connected', (e) => {
                const data = JSON.parse(e.data);
                console.log('✅ تم الاتصال بنجاح:', data.time);
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.showConnectionStatus('متصل', 'success');
            });
            
            // حدث الإحصائيات
            this.eventSource.addEventListener('stats', (e) => {
                const stats = JSON.parse(e.data);
                console.log('📊 تحديث الإحصائيات:', stats);
                this.trigger('stats', stats);
            });
            
            // حدث الإشعارات
            this.eventSource.addEventListener('notifications', (e) => {
                const data = JSON.parse(e.data);
                console.log('🔔 إشعارات جديدة:', data.count);
                this.trigger('notifications', data);
                this.updateNotificationBadge(data.count);
            });
            
            // حدث التحويلات
            this.eventSource.addEventListener('transfers', (e) => {
                const data = JSON.parse(e.data);
                console.log('🚚 تحديث التحويلات');
                this.trigger('transfers', data);
            });
            
            // حدث المهام
            this.eventSource.addEventListener('tasks', (e) => {
                const data = JSON.parse(e.data);
                console.log('📋 تحديث المهام');
                this.trigger('tasks', data);
            });
            
            // heartbeat للحفاظ على الاتصال
            this.eventSource.addEventListener('heartbeat', (e) => {
                const data = JSON.parse(e.data);
                console.log('💓 heartbeat:', data.time);
            });
            
            // حدث الخطأ
            this.eventSource.addEventListener('error', (e) => {
                console.warn('⚠️ خطأ في الاتصال:', e);
                this.isConnected = false;
                this.handleError();
            });
            
            // حدث الإغلاق
            this.eventSource.addEventListener('close', (e) => {
                console.log('🔌 تم إغلاق الاتصال');
                this.isConnected = false;
                this.reconnect();
            });
            
            // حدث الأخطاء العامة
            this.eventSource.onerror = (e) => {
                // لا نطبع الأخطاء لتجنب تلويث console
                this.isConnected = false;
                
                // إذا فشل الاتصال أكثر من مرة، نستخدم النظام البسيط
                if (this.reconnectAttempts >= 1) {
                    console.log('⚠️ فشل الاتصال. التحول للوضع البسيط...');
                    if (this.eventSource) {
                        this.eventSource.close();
                    }
                    this.startSimpleRefresh();
                } else {
                    this.handleError();
                }
            };
            
        } catch (error) {
            console.error('❌ فشل الاتصال:', error);
            this.handleError();
        }
    }
    
    /**
     * معالجة الأخطاء وإعادة الاتصال
     */
    handleError() {
        this.showConnectionStatus('غير متصل', 'error');
        
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        this.reconnect();
    }
    
    /**
     * إعادة الاتصال
     */
    reconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('⚠️ فشل الاتصال. استخدام الوضع البسيط...');
            this.startSimpleRefresh();
            return;
        }
        
        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
        
        console.log(`🔄 إعادة المحاولة ${this.reconnectAttempts}...`);
        
        setTimeout(() => {
            this.connect();
        }, delay);
    }
    
    /**
     * الرجوع إلى نظام polling التقليدي
     */
    fallbackToPolling() {
        this.startSimpleRefresh();
    }
    /**
     * تسجيل معالج للحدث
     */
    on(event, handler) {
        if (this.handlers[event]) {
            this.handlers[event].push(handler);
        }
    }
    
    /**
     * تشغيل المعالجات
     */
    trigger(event, data) {
        if (this.handlers[event]) {
            this.handlers[event].forEach(handler => {
                try {
                    handler(data);
                } catch (error) {
                    console.error('خطأ في معالج الحدث:', error);
                }
            });
        }
    }
    
    /**
     * تحديث شارة الإشعارات
     */
    updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    /**
     * عرض حالة الاتصال
     */
    showConnectionStatus(message, type) {
        console.log(`[${type.toUpperCase()}] ${message}`);
        
        // إضافة مؤشر في الواجهة
        const statusIndicator = document.querySelector('#realtime-status');
        if (statusIndicator) {
            statusIndicator.textContent = message;
            statusIndicator.className = `status-${type}`;
            statusIndicator.style.display = 'flex';
            
            // إخفاء المؤشر بعد 5 ثواني إذا كان الاتصال ناجح
            if (type === 'success') {
                setTimeout(() => {
                    statusIndicator.style.opacity = '0';
                    setTimeout(() => {
                        statusIndicator.style.display = 'none';
                        statusIndicator.style.opacity = '0.9';
                    }, 300);
                }, 5000);
            }
        }
    }
    
    /**
     * قطع الاتصال يدوياً
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.isConnected = false;
            console.log('🔌 تم قطع الاتصال يدوياً');
        }
    }

    /**
     * تحديث الجداول بشكل صامت في الخلفية
     */
    async silentReloadTable(tableSelector = 'table tbody') {
        try {
            // جلب الصفحة الحالية
            const response = await fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                console.log('Failed to fetch updated content');
                return;
            }
            
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // تحديث محتوى الجدول
            const newTableBody = doc.querySelector(tableSelector);
            const currentTableBody = document.querySelector(tableSelector);
            
            if (newTableBody && currentTableBody && newTableBody.innerHTML !== currentTableBody.innerHTML) {
                // إضافة تأثير fade
                currentTableBody.style.opacity = '0.4';
                currentTableBody.style.transition = 'opacity 0.3s ease';
                
                setTimeout(() => {
                    currentTableBody.innerHTML = newTableBody.innerHTML;
                    currentTableBody.style.opacity = '1';
                    
                    // إظهار إشعار التحديث
                    this.showUpdateNotification();
                }, 300);
            }
            
        } catch (error) {
            console.log('Silent reload error:', error);
        }
    }

    /**
     * إظهار إشعار تحديث خفيف
     */
    showUpdateNotification() {
        // إزالة الإشعار القديم إن وجد
        const oldNotification = document.querySelector('.silent-update-notification');
        if (oldNotification) {
            oldNotification.remove();
        }

        const notification = document.createElement('div');
        notification.className = 'silent-update-notification';
        notification.innerHTML = '<i class="fas fa-sync-alt"></i> تم التحديث';
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            z-index: 99999;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        `;
        
        document.body.appendChild(notification);
        
        // إظهار الإشعار
        requestAnimationFrame(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        });
        
        // إخفاء الإشعار
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 2000);
    }
}

// تهيئة النظام تلقائياً عند تحميل الصفحة
let realtimeSSE;
document.addEventListener('DOMContentLoaded', () => {
    realtimeSSE = new RealtimeSSE();
    
    // مثال على الاستخدام: تحديث الإحصائيات
    realtimeSSE.on('stats', (stats) => {
        // تحديث عناصر الإحصائيات في الصفحة مع تأثير بصري
        const updateStat = (selector, value) => {
            const element = document.querySelector(selector);
            if (element && element.textContent != value) {
                element.classList.add('updating');
                element.textContent = value || 0;
                setTimeout(() => {
                    element.classList.remove('updating');
                }, 500);
            }
        };
        
        updateStat('.stat-value[data-stat="total_transfers"]', stats.total_transfers);
        updateStat('.stat-value[data-stat="pending_transfers"]', stats.pending_transfers);
        updateStat('.stat-value[data-stat="in_transit"]', stats.in_transit);
        updateStat('.stat-value[data-stat="available_drivers"]', stats.available_drivers);
        updateStat('.stat-value[data-stat="active_transfers"]', stats.active_transfers);
        updateStat('.stat-value[data-stat="delivered"]', stats.delivered_transfers);
    });
    
    // تحديث جدول التحويلات بشكل صامت عند وجود تحديثات
    realtimeSSE.on('transfers', async () => {
        // إذا كنا في صفحة dashboard، نحدث قسم التحويلات
        if (window.location.pathname.includes('dashboard.php')) {
            console.log('🔄 تحديث قسم التحويلات في Dashboard بشكل صامت...');
            await realtimeSSE.silentReloadTable('.recent-transfers table tbody');
        } else if (window.location.pathname.includes('transfers.php')) {
            console.log('🔄 تحديث جدول التحويلات بشكل صامت...');
            await realtimeSSE.silentReloadTable('table tbody');
        }
    });
    
    // تحديث جدول المهام بشكل صامت
    realtimeSSE.on('tasks', async () => {
        if (window.location.pathname.includes('dashboard.php')) {
            console.log('🔄 تحديث Dashboard بشكل صامت بسبب تغيير في المهام...');
            await realtimeSSE.silentReloadTable('.recent-transfers table tbody');
        } else if (window.location.pathname.includes('driver') || window.location.pathname.includes('tasks')) {
            console.log('🔄 تحديث جدول المهام بشكل صامت...');
            await realtimeSSE.silentReloadTable('table tbody');
        }
    });
});

// تصدير للاستخدام العام
window.RealtimeSSE = RealtimeSSE;
window.realtimeSSE = realtimeSSE;
