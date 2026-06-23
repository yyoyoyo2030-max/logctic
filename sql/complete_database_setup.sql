-- ================================================
-- نظام إدارة اللوجستيك - قاعدة البيانات الكاملة
-- ================================================
-- هذا الملف يحتوي على جميع جداول النظام والبيانات الأساسية
-- يُستخدم للتثبيت الأولي أو إعادة بناء قاعدة البيانات
-- ================================================

USE logistic_system;

-- ================================================
-- 1. جدول الفروع
-- ================================================
CREATE TABLE IF NOT EXISTS branches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(200),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 2. جدول المستخدمين
-- ================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'branch_user', 'driver') DEFAULT 'branch_user',
    branch_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 3. جدول التحويلات
-- ================================================
CREATE TABLE IF NOT EXISTS transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transfer_number VARCHAR(50) UNIQUE NOT NULL,
    branch_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    file_path VARCHAR(255),
    from_location VARCHAR(200),
    to_location VARCHAR(200),
    description TEXT,
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_transfer_number (transfer_number),
    INDEX idx_status (status),
    INDEX idx_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 4. جدول السائقين
-- ================================================
CREATE TABLE IF NOT EXISTS drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    license_number VARCHAR(50),
    vehicle_type VARCHAR(50),
    vehicle_number VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_phone (phone),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 5. جدول تعيين السائقين للتحويلات
-- ================================================
CREATE TABLE IF NOT EXISTS driver_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transfer_id INT NOT NULL,
    driver_id INT NOT NULL,
    assigned_by INT NOT NULL,
    pickup_date DATE,
    delivery_date DATE,
    notes TEXT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_transfer_driver (transfer_id, driver_id),
    INDEX idx_driver (driver_id),
    INDEX idx_transfer (transfer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 6. جدول المهام
-- ================================================
CREATE TABLE IF NOT EXISTS tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    assigned_to INT COMMENT 'حقل قديم - لم يعد مستخدماً، المهام الآن مرتبطة بالسائقين عبر task_driver_assignments',
    assigned_by INT NOT NULL,
    branch_id INT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    due_date DATE COMMENT 'حقل قديم - لم يعد مستخدماً',
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assigned_by (assigned_by),
    INDEX idx_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 7. جدول تعيين السائقين للمهام
-- ================================================
CREATE TABLE IF NOT EXISTS task_driver_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    driver_id INT NOT NULL,
    assigned_by INT NOT NULL,
    notes TEXT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_task_driver (task_id, driver_id),
    INDEX idx_driver (driver_id),
    INDEX idx_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- البيانات الأساسية (لأول تثبيت)
-- ================================================

-- إضافة مستخدم مدير افتراضي (كلمة المرور: admin123)
-- يمكن حذف هذا السطر إذا كان لديك مستخدمين بالفعل
-- INSERT INTO users (username, password, full_name, role) 
-- VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'المدير العام', 'admin')
-- ON DUPLICATE KEY UPDATE username=username;

-- ================================================
-- ملاحظات مهمة:
-- ================================================
-- 1. المهام الآن مرتبطة بالسائقين فقط (مثل التحويلات)
-- 2. حقل assigned_to في جدول tasks لم يعد مستخدماً
-- 3. حقل due_date في جدول tasks لم يعد مستخدماً
-- 4. يمكن حذف هذه الحقول لاحقاً إذا أردت، لكن الاحتفاظ بها أفضل للسجلات القديمة
-- ================================================
