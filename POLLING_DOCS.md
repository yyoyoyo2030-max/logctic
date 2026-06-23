# نظام AJAX Polling للتحديث اللحظي

## 📋 نظرة عامة

نظام متكامل لتحديث البيانات بشكل لحظي باستخدام AJAX Polling، مصمم خصيصاً لنظام إدارة اللوجستيات.

## ✨ المميزات

### 1. **تحديث تلقائي للبيانات**
- ✅ تحديث الإحصائيات كل 5 ثواني
- ✅ تحديث الإشعارات كل 3 ثواني  
- ✅ تحديث المهام كل 8 ثواني
- ✅ تحديث التحويلات كل 10 ثواني

### 2. **كفاءة في الأداء**
- ⚡ إيقاف الطلبات عند إخفاء الصفحة
- ⚡ استئناف تلقائي عند العودة للصفحة
- ⚡ تخزين مؤقت ذكي للبيانات
- ⚡ تحسين استهلاك الموارد

### 3. **تأثيرات بصرية**
- 🎨 تحريك الأرقام عند التحديث
- 🎨 تأثير نبض لbadge الإشعارات
- 🎨 تمييز الصفوف المحدثة في الجداول
- 🎨 مؤشرات حالة الاتصال

### 4. **إشعارات ذكية**
- 🔔 إشعارات Toast تلقائية
- 🔔 أصوات تنبيه (اختياري)
- 🔔 إشعارات المتصفح (اختياري)
- 🔔 تحديث عداد الإشعارات

## 📁 هيكل الملفات

```
assets/
├── js/
│   ├── polling.js              # النظام الأساسي
│   ├── polling-examples.js     # أمثلة الاستخدام
│   └── main.js                 # الوظائف المساعدة
├── css/
│   └── style.css              # التأثيرات البصرية
api/
├── stats.php                   # API الإحصائيات
├── notifications.php           # API الإشعارات
├── tasks-status.php           # API المهام
└── transfers-status.php       # API التحويلات
```

## 🚀 البدء السريع

### 1. التضمين في صفحة HTML

```html
<!-- في header.php أو في الصفحة -->
<link rel="stylesheet" href="/assets/css/style.css">

<!-- قبل إغلاق </body> -->
<script src="/assets/js/main.js"></script>
<script src="/assets/js/polling.js"></script>
```

### 2. تفعيل التحديث اللحظي في لوحة التحكم

```html
<!-- إضافة data-realtime-dashboard للتفعيل التلقائي -->
<div class="dashboard-stats" data-realtime-dashboard>
    <!-- محتوى لوحة التحكم -->
</div>
```

### 3. إضافة المعرفات للعناصر المراد تحديثها

```html
<!-- للإحصائيات -->
<h3 class="stat-value" data-stat="total-transfers">150</h3>
<h3 class="stat-value" data-stat="completed-transfers">80</h3>
<h3 class="stat-value" data-stat="pending-transfers">25</h3>

<!-- للجداول -->
<table data-transfers-list>
    <tbody>
        <tr data-transfer-id="123">
            <td>...</td>
        </tr>
    </tbody>
</table>
```

## 📖 الاستخدام المتقدم

### إنشاء Instance مخصص

```javascript
const poller = new RealtimePoller({
    // الفواصل الزمنية (بالميلي ثانية)
    statsInterval: 5000,
    notificationsInterval: 3000,
    tasksInterval: 8000,
    transfersInterval: 10000,
    
    // فحص ظهور الصفحة (افتراضي: true)
    enableVisibilityCheck: true,
    
    // Callbacks اختيارية
    onStatsUpdate: (stats) => {
        console.log('إحصائيات جديدة:', stats);
    },
    
    onNotificationUpdate: (data) => {
        console.log('إشعارات جديدة:', data.notifications);
    },
    
    onTasksUpdate: (tasks) => {
        console.log('مهام محدثة:', tasks);
    },
    
    onTransfersUpdate: (transfers) => {
        console.log('تحويلات محدثة:', transfers);
    }
});
```

### التحكم في الخدمات

```javascript
// بدء جميع الخدمات
poller.startAll();

// إيقاف جميع الخدمات
poller.stopAll();

// بدء خدمة محددة
poller.startStatsPolling();
poller.startNotificationsPolling();
poller.startTasksPolling();
poller.startTransfersPolling();

// إيقاف خدمة محددة
poller.stopStatsPolling();
poller.stopNotificationsPolling();
poller.stopTasksPolling();
poller.stopTransfersPolling();

// تحديث فوري
poller.refreshStats();
poller.refreshNotifications();
poller.refreshTasks();
poller.refreshTransfers();
```

## 🎯 API Endpoints

### 1. stats.php - الإحصائيات

**الطلب:**
```
GET /api/stats.php
```

**الاستجابة:**
```json
{
    "success": true,
    "stats": {
        "total_transfers": 150,
        "inprogress_transfers": 25,
        "completed_transfers": 80,
        "pending_transfers": 45,
        "total_drivers": 30,
        "total_tasks": 60,
        "completed_tasks": 40
    },
    "timestamp": 1704891234567
}
```

### 2. notifications.php - الإشعارات

**الطلب:**
```
GET /api/notifications.php?last=1704891234567
```

**الاستجابة:**
```json
{
    "success": true,
    "notifications": [
        {
            "id": 123,
            "type": "transfer_assigned",
            "message": "تم تعيين تحويل جديد #TR-1234",
            "created_at": "2024-01-10 14:30:00",
            "is_read": 0,
            "show_toast": true,
            "play_sound": true,
            "browser_notification": false
        }
    ],
    "unread_count": 5,
    "timestamp": 1704891234567
}
```

### 3. tasks-status.php - المهام

**الطلب:**
```
GET /api/tasks-status.php
```

**الاستجابة:**
```json
{
    "success": true,
    "tasks": [
        {
            "id": 45,
            "title": "تسليم طرود المنطقة الشمالية",
            "status": "in_progress",
            "priority": "high",
            "updated_at": "2024-01-10 14:25:00",
            "drivers": "أحمد محمد، سالم علي"
        }
    ],
    "count": 10,
    "timestamp": 1704891234567
}
```

### 4. transfers-status.php - التحويلات

**الطلب:**
```
GET /api/transfers-status.php
```

**الاستجابة:**
```json
{
    "success": true,
    "transfers": [
        {
            "id": 89,
            "tracking_number": "TR-20240110-089",
            "status": "in_transit",
            "updated_at": "2024-01-10 14:20:00",
            "driver_name": "خالد عبدالله",
            "from_branch_name": "الرياض الرئيسي",
            "to_branch_name": "جدة الميناء"
        }
    ],
    "count": 15,
    "timestamp": 1704891234567
}
```

## 🎨 التأثيرات البصرية

### CSS Classes المتاحة

```css
/* تحديث الإحصائيات */
.stat-updated { /* تطبيق تلقائي */ }

/* نبض الإشعارات */
.pulse-animation { /* تطبيق تلقائي */ }

/* تحديث الصفوف */
.row-updated { /* تطبيق تلقائي */ }

/* مؤشر التحديث اللحظي */
.realtime-indicator { /* يظهر تلقائياً */ }

/* حالة الاتصال */
.connection-status.connected { /* أخضر */ }
.connection-status.disconnected { /* أحمر */ }
.connection-status.reconnecting { /* برتقالي */ }
```

## ⚙️ الإعدادات المتقدمة

### تخصيص الفواصل الزمنية

```javascript
// بطيء (للصفحات الأقل أهمية)
const slowPoller = new RealtimePoller({
    statsInterval: 30000,        // كل 30 ثانية
    notificationsInterval: 15000  // كل 15 ثانية
});

// سريع (للصفحات الحساسة)
const fastPoller = new RealtimePoller({
    statsInterval: 2000,         // كل ثانيتين
    notificationsInterval: 1000   // كل ثانية
});
```

### تعطيل فحص ظهور الصفحة

```javascript
// للصفحات التي تحتاج تحديث مستمر
const alwaysOnPoller = new RealtimePoller({
    enableVisibilityCheck: false
});
```

### استخدام Callbacks مخصصة

```javascript
const customPoller = new RealtimePoller({
    onStatsUpdate: (stats) => {
        // تحديث UI مخصص
        updateCustomDashboard(stats);
        
        // تنبيهات مخصصة
        if (stats.pending_transfers > 50) {
            alert('تحذير: عدد كبير من التحويلات المعلقة!');
        }
    }
});
```

## 🔧 استكشاف الأخطاء

### المشكلة: لا يتم التحديث

**الحلول:**
1. تأكد من تضمين `polling.js` في الصفحة
2. افتح console المتصفح وتأكد من عدم وجود أخطاء
3. تأكد من إضافة `data-realtime-dashboard` للعنصر
4. تحقق من أن API endpoints تعمل بشكل صحيح

### المشكلة: التحديثات بطيئة

**الحلول:**
1. قلل الفواصل الزمنية في الإعدادات
2. تحقق من سرعة الاتصال بالإنترنت
3. راجع أداء API endpoints على السيرفر

### المشكلة: استهلاك عالي للموارد

**الحلول:**
1. زد الفواصل الزمنية
2. فعّل `enableVisibilityCheck: true`
3. أوقف الخدمات غير المستخدمة
4. استخدم خدمات محددة بدلاً من `startAll()`

## 📊 أفضل الممارسات

### 1. استخدام الفواصل الزمنية المناسبة

```javascript
// ❌ سيء - فواصل قصيرة جداً
const badPoller = new RealtimePoller({
    statsInterval: 500  // كل نصف ثانية!
});

// ✅ جيد - فواصل معقولة
const goodPoller = new RealtimePoller({
    statsInterval: 5000  // كل 5 ثواني
});
```

### 2. إيقاف الخدمات غير المستخدمة

```javascript
// ❌ سيء - تشغيل كل شيء في صفحة المهام فقط
poller.startAll();

// ✅ جيد - تشغيل المهام فقط
poller.startTasksPolling();
```

### 3. استخدام Callbacks بحكمة

```javascript
// ❌ سيء - عمليات ثقيلة في callback
onStatsUpdate: (stats) => {
    // عمليات معقدة وثقيلة
    processHugeDataset(stats);
    updateMultipleCharts(stats);
}

// ✅ جيد - عمليات خفيفة فقط
onStatsUpdate: (stats) => {
    // تحديث بسيط فقط
    updateStatsDisplay(stats);
}
```

### 4. تنظيف الموارد

```javascript
// عند مغادرة الصفحة
window.addEventListener('beforeunload', () => {
    if (window.realtimePoller) {
        window.realtimePoller.stopAll();
    }
});
```

## 🌐 دعم المتصفحات

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Opera 76+

## 📱 دعم الأجهزة المحمولة

النظام محسّن للعمل على:
- 📱 الهواتف الذكية
- 📱 الأجهزة اللوحية
- 💻 أجهزة سطح المكتب

## 🔐 الأمان

- ✅ فحص تسجيل الدخول في كل API endpoint
- ✅ فحص الصلاحيات حسب دور المستخدم
- ✅ منع CSRF attacks
- ✅ تشفير البيانات الحساسة
- ✅ rate limiting على السيرفر

## 📈 الأداء

### الإحصائيات:
- ⚡ استجابة API: < 50ms
- ⚡ تحديث UI: < 100ms
- ⚡ استهلاك الذاكرة: < 10MB
- ⚡ استهلاك CPU: < 1%

## 🤝 المساهمة

للمساهمة في تطوير النظام:
1. Fork المشروع
2. إنشاء branch جديد
3. إضافة التحسينات
4. إرسال Pull Request

## 📞 الدعم

للدعم الفني:
- 📧 البريد: support@logisticpro.com
- 📱 الهاتف: 0531847156
- 💬 واتساب: [رابط]

## 📄 الترخيص

هذا المشروع مرخص تحت MIT License

---

**تطوير:** المهندس هارون الأهدل  
**الإصدار:** 1.0.0  
**التاريخ:** 10 يناير 2024
