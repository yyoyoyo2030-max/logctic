<div align="center">

# 🚚 Logistic Pro

### نظام إدارة لوجستيك احترافي متكامل

[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-Private-red?style=flat-square)](LICENSE)

</div>

---

## ✨ المميزات

| الميزة | الوصف |
|--------|-------|
| 📦 إدارة التحويلات | رفع وتتبع الشحنات بين الفروع |
| 🚛 إدارة السائقين | تعيين سائقين متعددين لكل شحنة |
| 📋 نظام المهام | إنشاء وتوزيع المهام على السائقين |
| 📊 التقارير | تقارير شاملة ومتقدمة |
| 💬 إشعارات واتساب | ربط Evolution API لإرسال إشعارات فورية |
| 📈 مراقبة النظام | تتبع حركة المستخدمين (PostHog) |
| 🌙 الوضع الليلي | دعم كامل للوضع المظلم |
| 🔐 صلاحيات متعددة | 8 أدوار مختلفة للمستخدمين |

---

## 📋 المتطلبات

- PHP 7.4+ مع إضافات: `pdo_mysql`, `curl`, `mbstring`
- MySQL 5.7+ أو MariaDB 10.3+
- Apache أو Nginx

---

## 🚀 التثبيت السريع (Hostinger / cPanel)

### الخطوة 1: إنشاء قاعدة البيانات

1. ادخل إلى **phpMyAdmin** من لوحة تحكم الاستضافة
2. أنشئ قاعدة بيانات جديدة (مثلاً: `logistic_system`)
3. اضغط على تبويب **Import** واستورد ملف:
   ```
   sql/complete_database_setup.sql
   ```

### الخطوة 2: رفع الملفات

1. ارفع جميع ملفات المشروع إلى مجلد `public_html` عبر **File Manager** أو **FTP**
2. تأكد من أن مجلد `uploads/` لديه صلاحيات `755`

### الخطوة 3: ضبط المتغيرات

أنشئ ملف `.env` في المجلد الرئيسي بالمحتوى التالي:

```env
ENVIRONMENT=hostinger
DB_HOST=localhost
DB_USER=اسم_مستخدم_قاعدة_البيانات
DB_PASS=كلمة_مرور_قاعدة_البيانات
DB_NAME=اسم_قاعدة_البيانات
SITE_URL=https://yourdomain.com
```

### جدول متغيرات البيئة (Environment Variables)

| المتغير | الوصف | مثال | مطلوب |
|---------|-------|------|-------|
| `ENVIRONMENT` | بيئة العمل | `hostinger` أو `local` | ✅ |
| `DB_HOST` | عنوان خادم قاعدة البيانات | `localhost` | ✅ |
| `DB_USER` | اسم مستخدم قاعدة البيانات | `u123456789_admin` | ✅ |
| `DB_PASS` | كلمة مرور قاعدة البيانات | `MyP@ssw0rd!` | ✅ |
| `DB_NAME` | اسم قاعدة البيانات | `u123456789_logistic` | ✅ |
| `DB_PORT` | منفذ قاعدة البيانات | `3306` (افتراضي) | ❌ |
| `SITE_URL` | رابط الموقع الكامل | `https://yourdomain.com` | ✅ |
| `DB_SSL` | تفعيل SSL لقاعدة البيانات | `true` أو `false` | ❌ |

### الخطوة 4: تسجيل الدخول

افتح موقعك في المتصفح وسجل الدخول:

| الحقل | القيمة |
|-------|--------|
| اسم المستخدم | `admin` |
| كلمة المرور | `admin123` |

> ⚠️ **مهم جداً:** غيّر كلمة المرور فوراً بعد أول تسجيل دخول!

---

## 🔐 أدوار المستخدمين

| الدور | الصلاحيات |
|-------|-----------|
| `admin` | مدير النظام - صلاحيات كاملة |
| `logistics_manager` | مدير لوجستك - إدارة التحويلات والمهام |
| `warehouse_manager` | مدير مستودعات - استلام وتسليم |
| `drivers_manager` | مسؤول سائقين - تعيين السائقين |
| `warehouse_entry` | إدخال مستودعات |
| `branch_entry` | إدخال فروع |
| `branch_user` | مستخدم فرع |
| `driver` | سائق |

---

## 📁 هيكل المشروع

```
logctic/
├── api/                    # واجهات API (واتساب، إشعارات)
├── assets/
│   ├── css/               # ملفات التنسيق
│   └── js/                # ملفات جافاسكريبت
├── config/
│   └── config.php         # إعدادات النظام الرئيسية
├── includes/
│   ├── header.php         # رأس الصفحة
│   └── footer.php         # ذيل الصفحة
├── sql/
│   └── complete_database_setup.sql  # ⭐ قاعدة البيانات الكاملة
├── uploads/               # ملفات التحويلات المرفوعة
├── views/
│   ├── admin/             # لوحة الإدارة
│   ├── auth/              # تسجيل الدخول
│   ├── dashboard/         # الرئيسية
│   ├── drivers/           # إدارة السائقين
│   ├── tasks/             # إدارة المهام
│   └── transfers/         # إدارة التحويلات
├── .env                   # متغيرات البيئة (أنشئه يدوياً)
├── .gitignore
├── Dockerfile
└── README.md
```

---

## 💬 ربط إشعارات الواتساب (اختياري)

1. قم بتثبيت [Evolution API](https://github.com/EvolutionAPI/evolution-api)
2. من لوحة التحكم اذهب إلى **إعدادات الواتساب**
3. أدخل رابط API ومفتاح API
4. امسح كود QR لربط رقمك

---

<div align="center">

**صنع بـ ❤️ | تطوير: المهندس هارون الأهدل**

© 2026 Logistic Pro - جميع الحقوق محفوظة

</div>
