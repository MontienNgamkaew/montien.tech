-- ระบบขออนุญาตใช้รถยนต์/สั่งซื้อน้ำมันเชื้อเพลิง
-- หมายเหตุ: ไม่มี CREATE DATABASE เพราะ Hostinger สร้าง DB ให้แล้ว
-- กรุณาเลือก database ที่ถูกต้องใน phpMyAdmin ก่อน Import

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('supply_head', 'deputy_director', 'director', 'admin') NOT NULL,
    position_title VARCHAR(150) NOT NULL,
    signature_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_name VARCHAR(120) NOT NULL,
    license_plate VARCHAR(50) NOT NULL UNIQUE,
    vehicle_type VARCHAR(80) NULL,
    fuel_type ENUM('gasoline_91', 'gasoline_95', 'diesel', 'engine_oil', 'other') NOT NULL DEFAULT 'diesel',
    default_driver_name VARCHAR(150) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vehicles_active (is_active),
    INDEX idx_vehicles_fuel_type (fuel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requisitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_id VARCHAR(30) NOT NULL UNIQUE,
    document_no VARCHAR(50) NULL,
    requester_name VARCHAR(150) NOT NULL,
    requester_position VARCHAR(150) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    destination_subdistrict VARCHAR(120) NULL,
    destination_district VARCHAR(120) NULL,
    destination_province VARCHAR(120) NULL,
    distance_km DECIMAL(8,2) NULL,
    odometer_before INT UNSIGNED NULL,
    travel_start_at DATETIME NOT NULL,
    travel_end_at DATETIME NOT NULL,
    purpose TEXT NOT NULL,
    passenger_count INT UNSIGNED NOT NULL DEFAULT 0,
    passenger_names TEXT NULL,
    requested_vehicle_id BIGINT UNSIGNED NULL,
    assigned_vehicle_id BIGINT UNSIGNED NULL,
    assigned_driver_name VARCHAR(150) NULL,
    fuel_requested TINYINT(1) NOT NULL DEFAULT 0,
    fuel_purchase_requested TINYINT(1) NOT NULL DEFAULT 0,
    fuel_not_requested TINYINT(1) NOT NULL DEFAULT 0,
    fuel_type ENUM('gasoline_91', 'gasoline_95', 'diesel', 'engine_oil', 'other') NULL,
    fuel_quantity DECIMAL(10,2) NULL,
    fuel_unit VARCHAR(30) NULL,
    fuel_unit_price DECIMAL(10,2) NULL,
    fuel_total_amount DECIMAL(10,2) NULL,
    fuel_amount_text VARCHAR(255) NULL,
    status ENUM(
        'submitted',
        'pending_level_1',
        'pending_level_2',
        'pending_level_3',
        'approved',
        'rejected',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_level_1',
    current_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    level1_approved_by BIGINT UNSIGNED NULL,
    level1_approved_at DATETIME NULL,
    level2_approved_by BIGINT UNSIGNED NULL,
    level2_approved_at DATETIME NULL,
    level3_approved_by BIGINT UNSIGNED NULL,
    level3_approved_at DATETIME NULL,
    rejected_by BIGINT UNSIGNED NULL,
    rejected_at DATETIME NULL,
    rejection_reason TEXT NULL,
    pdf_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requisitions_requested_vehicle
        FOREIGN KEY (requested_vehicle_id) REFERENCES vehicles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_requisitions_assigned_vehicle
        FOREIGN KEY (assigned_vehicle_id) REFERENCES vehicles(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_requisitions_level1_user
        FOREIGN KEY (level1_approved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_requisitions_level2_user
        FOREIGN KEY (level2_approved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_requisitions_level3_user
        FOREIGN KEY (level3_approved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_requisitions_rejected_user
        FOREIGN KEY (rejected_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_requisitions_tracking_id (tracking_id),
    INDEX idx_requisitions_status_level (status, current_level),
    INDEX idx_requisitions_travel_start (travel_start_at),
    INDEX idx_requisitions_created_at (created_at),
    INDEX idx_requisitions_assigned_vehicle (assigned_vehicle_id),
    INDEX idx_requisitions_fuel_summary (fuel_requested, fuel_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id BIGINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NULL,
    approval_level TINYINT UNSIGNED NOT NULL,
    action ENUM('submitted', 'approved', 'rejected', 'returned', 'cancelled') NOT NULL,
    status_from VARCHAR(40) NULL,
    status_to VARCHAR(40) NOT NULL,
    comment TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_logs_requisition
        FOREIGN KEY (requisition_id) REFERENCES requisitions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_approval_logs_user
        FOREIGN KEY (approver_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_approval_logs_requisition (requisition_id),
    INDEX idx_approval_logs_approver (approver_id),
    INDEX idx_approval_logs_level_action (approval_level, action),
    INDEX idx_approval_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fuel_requisition_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id BIGINT UNSIGNED NOT NULL,
    fuel_type ENUM('gasoline_91', 'gasoline_95', 'diesel', 'engine_oil', 'other') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'ลิตร',
    unit_price DECIMAL(10,2) NULL,
    total_amount DECIMAL(10,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fuel_items_requisition
        FOREIGN KEY (requisition_id) REFERENCES requisitions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_fuel_items_requisition (requisition_id),
    INDEX idx_fuel_items_type (fuel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vehicles (vehicle_name, license_plate, vehicle_type, fuel_type)
VALUES
    ('โตโยต้า รถตู้', '7093 กทม', 'รถตู้', 'diesel'),
    ('โตโยต้า รถขาว', 'กข4598', 'รถขาว', 'diesel'),
    ('อีซูซุ รถเหลือง ใหม่', '40-0412', 'รถหกล้อ', 'diesel'),
    ('โตโยต้า รถสี่ประตู ใหม่', 'กน3882', 'รถสี่ประตู', 'diesel'),
    ('นิสสัน รถทอง', 'บป5088', 'รถทอง', 'diesel'),
    ('อีซูซุ รถหกล้อ เก่า', '40-0199', 'รถหกล้อ', 'diesel');
