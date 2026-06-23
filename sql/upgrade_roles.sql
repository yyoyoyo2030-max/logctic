-- تحديث جدول المستخدمين لإضافة الأدوار الجديدة
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'logistics_manager', 'warehouse_manager', 'drivers_manager', 'warehouse_entry', 'branch_entry', 'branch_user', 'driver') DEFAULT 'branch_user';

-- إضافة جدول إعدادات الواتساب
CREATE TABLE IF NOT EXISTS whatsapp_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    instance_name VARCHAR(100) NOT NULL,
    webhook_url VARCHAR(255),
    drivers_manager_group_id VARCHAR(100),
    warehouse_manager_group_id VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة جدول سجلات الواتساب لتتبع الرسائل
CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_type ENUM('group', 'driver', 'other') NOT NULL,
    recipient_id VARCHAR(100) NOT NULL,
    message_type VARCHAR(50),
    message_content TEXT,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    response_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج إعدادات افتراضية للواتساب
INSERT INTO whatsapp_settings (api_url, api_key, instance_name) 
SELECT 'http://localhost:8080', 'YOUR_API_KEY', 'default_instance'
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_settings LIMIT 1);
