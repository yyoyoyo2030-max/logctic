-- ================================================================
-- 🚀 نظام Logistic Pro - قاعدة البيانات الكاملة
-- ================================================================
-- الإصدار: 2.0
-- التاريخ: 2026-06-27
-- ================================================================
-- ⚠️ ملاحظة: قم باستيراد هذا الملف مرة واحدة فقط عند التثبيت الأولي
-- إذا كانت قاعدة البيانات موجودة مسبقاً، لا تستورد هذا الملف
-- ================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ================================================================
-- 1. جدول الفروع
-- ================================================================
CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(200),
    `phone` VARCHAR(20),
    `whatsapp_group_id` VARCHAR(100) DEFAULT NULL COMMENT 'معرف جروب الواتساب الخاص بالفرع',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 2. جدول المستخدمين
-- ================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100),
    `phone` VARCHAR(20),
    `role` ENUM('admin', 'logistics_manager', 'warehouse_manager', 'drivers_manager', 'warehouse_entry', 'branch_entry', 'branch_user', 'driver') DEFAULT 'branch_user',
    `branch_id` INT,
    `is_active` BOOLEAN DEFAULT TRUE,
    `remember_token` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 3. جدول التحويلات (الشحنات)
-- ================================================================
CREATE TABLE IF NOT EXISTS `transfers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `transfer_number` VARCHAR(50) NOT NULL COMMENT 'يمكن أن يتكرر بين فروع مختلفة',
    `branch_id` INT NOT NULL,
    `uploaded_by` INT NOT NULL,
    `file_path` VARCHAR(255),
    `from_location` VARCHAR(200),
    `to_location` VARCHAR(200),
    `description` TEXT,
    `status` ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_transfer_number` (`transfer_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 4. جدول السائقين
-- ================================================================
CREATE TABLE IF NOT EXISTS `drivers` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `license_number` VARCHAR(50),
    `vehicle_type` VARCHAR(50),
    `vehicle_number` VARCHAR(20),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_phone` (`phone`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 5. جدول تعيين السائقين للتحويلات
-- ================================================================
CREATE TABLE IF NOT EXISTS `driver_assignments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `transfer_id` INT NOT NULL,
    `driver_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `pickup_date` DATE,
    `delivery_date` DATE,
    `notes` TEXT,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transfer_id`) REFERENCES `transfers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`driver_id`) REFERENCES `drivers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_transfer_driver` (`transfer_id`, `driver_id`),
    INDEX `idx_driver` (`driver_id`),
    INDEX `idx_transfer` (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 6. جدول المهام
-- ================================================================
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `assigned_to` INT DEFAULT NULL COMMENT 'حقل قديم - المهام مرتبطة بالسائقين عبر task_driver_assignments',
    `assigned_by` INT NOT NULL,
    `branch_id` INT,
    `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    `due_date` DATE DEFAULT NULL COMMENT 'حقل قديم',
    `completed_at` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_assigned_by` (`assigned_by`),
    INDEX `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 7. جدول تعيين السائقين للمهام
-- ================================================================
CREATE TABLE IF NOT EXISTS `task_driver_assignments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `task_id` INT NOT NULL,
    `driver_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `notes` TEXT,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`driver_id`) REFERENCES `drivers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_task_driver` (`task_id`, `driver_id`),
    INDEX `idx_driver` (`driver_id`),
    INDEX `idx_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 8. جدول إعدادات الواتساب
-- ================================================================
CREATE TABLE IF NOT EXISTS `whatsapp_settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `api_url` VARCHAR(255) NOT NULL,
    `api_key` VARCHAR(255) NOT NULL,
    `instance_name` VARCHAR(100) NOT NULL DEFAULT 'logistic_system',
    `webhook_url` VARCHAR(255),
    `drivers_manager_group_id` VARCHAR(100),
    `warehouse_manager_group_id` VARCHAR(100),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 9. جدول سجلات الواتساب
-- ================================================================
CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `recipient_type` ENUM('group', 'driver', 'other') NOT NULL,
    `recipient_id` VARCHAR(100) NOT NULL,
    `message_type` VARCHAR(50),
    `message_content` TEXT,
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `response_data` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 10. جدول التوجيه المخصص للواتساب
-- ================================================================
CREATE TABLE IF NOT EXISTS `whatsapp_custom_routes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `event_type` VARCHAR(50) NOT NULL,
    `phone_number` VARCHAR(20) NOT NULL,
    `description` VARCHAR(255),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 11. جدول سجل حركة النظام
-- ================================================================
CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(255) NOT NULL,
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `page_url` VARCHAR(500),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 12. جدول الإشعارات
-- ================================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    `message` TEXT NOT NULL,
    `related_type` VARCHAR(50),
    `related_id` INT,
    `is_read` BOOLEAN DEFAULT FALSE,
    `show_toast` BOOLEAN DEFAULT FALSE,
    `play_sound` BOOLEAN DEFAULT FALSE,
    `browser_notification` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_unread` (`user_id`, `is_read`, `created_at`),
    INDEX `idx_related` (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- 13. جدول إعدادات النظام
-- ================================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- البيانات الأساسية (التثبيت الأولي)
-- ================================================================

-- إضافة مستخدم مدير افتراضي
-- كلمة المرور: admin123
INSERT INTO `users` (`username`, `password`, `full_name`, `role`)
SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');

-- إعدادات واتساب افتراضية
INSERT INTO `whatsapp_settings` (`api_url`, `api_key`, `instance_name`)
SELECT 'http://localhost:8080', 'YOUR_API_KEY', 'logistic_system'
WHERE NOT EXISTS (SELECT 1 FROM `whatsapp_settings` LIMIT 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
-- ✅ تم! قاعدة البيانات جاهزة
-- ================================================================
