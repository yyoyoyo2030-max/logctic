-- قاعدة بيانات نظام اللوجستيك
CREATE DATABASE IF NOT EXISTS logistic_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE logistic_system;

-- جدول الفروع
CREATE TABLE branches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(200),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول المستخدمين
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'branch_user') DEFAULT 'branch_user',
    branch_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- جدول السائقين
CREATE TABLE drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    license_number VARCHAR(50),
    vehicle_type VARCHAR(50),
    vehicle_number VARCHAR(50),
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول التحويلات
CREATE TABLE transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transfer_number VARCHAR(50) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    file_path VARCHAR(255),
    from_location VARCHAR(200) NOT NULL,
    to_location VARCHAR(200) NOT NULL,
    description TEXT,
    status ENUM('pending', 'assigned', 'in_transit', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- جدول ربط السائقين بالتحويلات
CREATE TABLE driver_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transfer_id INT NOT NULL,
    driver_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pickup_date DATE,
    delivery_date DATE,
    notes TEXT,
    FOREIGN KEY (transfer_id) REFERENCES transfers(id),
    FOREIGN KEY (driver_id) REFERENCES drivers(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- إدراج بيانات تجريبية
INSERT INTO branches (name, location, phone) VALUES 
('الفرع الرئيسي', 'الرياض، شارع الملك فهد', '0112345678'),
('فرع جدة', 'جدة، حي الحمراء', '0122345678'),
('فرع الدمام', 'الدمام، الكورنيش', '0132345678');

-- كلمة المرور: admin123 (مشفرة بـ password_hash)
INSERT INTO users (username, password, full_name, email, role, branch_id) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@logistic.com', 'admin', 1),
('branch1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مستخدم الفرع 1', 'branch1@logistic.com', 'branch_user', 1);

INSERT INTO drivers (name, phone, license_number, vehicle_type, vehicle_number) VALUES 
('أحمد محمد', '0501234567', 'L123456', 'شاحنة', 'أ ب ج 1234'),
('محمد علي', '0509876543', 'L789012', 'نقل خفيف', 'د ه و 5678'),
('خالد سعيد', '0551122334', 'L345678', 'شاحنة كبيرة', 'ز ح ط 9012');
