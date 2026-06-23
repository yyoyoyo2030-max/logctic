-- إنشاء جدول الإشعارات
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `message` text NOT NULL,
  `related_type` varchar(50) DEFAULT NULL COMMENT 'transfer, task, driver, etc',
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `show_toast` tinyint(1) DEFAULT 1,
  `play_sound` tinyint(1) DEFAULT 0,
  `browser_notification` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إنشاء فهارس لتحسين الأداء
CREATE INDEX idx_user_unread ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_related ON notifications(related_type, related_id);

-- إضافة عمود updated_at للجداول إذا لم يكن موجوداً
ALTER TABLE `transfers` 
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `tasks` 
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Trigger لإنشاء إشعار عند تحديث حالة الشحنة
DELIMITER $$

CREATE TRIGGER after_transfer_status_update
AFTER UPDATE ON transfers
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        -- إشعار للمدير
        INSERT INTO notifications (user_id, type, message, related_type, related_id, show_toast, play_sound)
        SELECT u.id, 'info', 
               CONCAT('تم تحديث حالة الشحنة #', NEW.tracking_number, ' إلى: ', NEW.status),
               'transfer', NEW.id, 1, 1
        FROM users u
        WHERE u.role = 'admin';
        
        -- إشعار للسائق إذا كان معين
        IF NEW.driver_id IS NOT NULL THEN
            INSERT INTO notifications (user_id, type, message, related_type, related_id, show_toast, play_sound)
            SELECT d.user_id, 'info',
                   CONCAT('تم تحديث حالة الشحنة #', NEW.tracking_number, ' إلى: ', NEW.status),
                   'transfer', NEW.id, 1, 1
            FROM drivers d
            WHERE d.id = NEW.driver_id AND d.user_id IS NOT NULL;
        END IF;
    END IF;
END$$

-- Trigger لإنشاء إشعار عند تحديث حالة المهمة
CREATE TRIGGER after_task_status_update
AFTER UPDATE ON tasks
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        -- إشعار للمدير
        INSERT INTO notifications (user_id, type, message, related_type, related_id, show_toast)
        SELECT u.id, 'info',
               CONCAT('تم تحديث حالة المهمة "', NEW.title, '" إلى: ', NEW.status),
               'task', NEW.id, 1
        FROM users u
        WHERE u.role = 'admin';
        
        -- إشعار للسائقين المعينين
        INSERT INTO notifications (user_id, type, message, related_type, related_id, show_toast)
        SELECT d.user_id, 'info',
               CONCAT('تم تحديث حالة المهمة "', NEW.title, '" إلى: ', NEW.status),
               'task', NEW.id, 1
        FROM task_driver_assignments tda
        JOIN drivers d ON d.id = tda.driver_id
        WHERE tda.task_id = NEW.id AND d.user_id IS NOT NULL;
    END IF;
END$$

-- Trigger لإنشاء إشعار عند تعيين سائق جديد
CREATE TRIGGER after_driver_assignment
AFTER UPDATE ON transfers
FOR EACH ROW
BEGIN
    IF (OLD.driver_id IS NULL AND NEW.driver_id IS NOT NULL) OR (OLD.driver_id != NEW.driver_id) THEN
        INSERT INTO notifications (user_id, type, message, related_type, related_id, show_toast, play_sound, browser_notification)
        SELECT d.user_id, 'success',
               CONCAT('تم تعيينك على الشحنة #', NEW.tracking_number),
               'transfer', NEW.id, 1, 1, 1
        FROM drivers d
        WHERE d.id = NEW.driver_id AND d.user_id IS NOT NULL;
    END IF;
END$$

DELIMITER ;

-- بيانات تجريبية (اختياري)
INSERT INTO notifications (user_id, type, message, related_type, related_id, show_toast) 
SELECT id, 'info', 'مرحباً بك في نظام التحديث اللحظي!', NULL, NULL, 1
FROM users 
WHERE role = 'admin'
LIMIT 1;
