-- ==========================================
-- PNP Go (ระบบขออนุญาตใช้รถยนต์และสั่งซื้อน้ำมัน)
-- สคริปต์สร้างฐานข้อมูลแบบรวมในไฟล์เดียว (Unified SQL Setup)
-- วิทยาลัยการอาชีพพนมไพร
-- ==========================================

-- 1. สร้างตาราง users
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('supply_head', 'deputy_director', 'director', 'admin') NOT NULL,
    position_title VARCHAR(150) NOT NULL,
    signature_path VARCHAR(255) NULL,
    avatar_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. สร้างตาราง vehicles
CREATE TABLE IF NOT EXISTS vehicles (
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

-- 3. สร้างตาราง requisitions
CREATE TABLE IF NOT EXISTS requisitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
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
    odometer_after INT UNSIGNED NULL,
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
    report_photo_path VARCHAR(255) NULL,
    reported_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requisitions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
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

-- 4. สร้างตาราง approval_logs
CREATE TABLE IF NOT EXISTS approval_logs (
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

-- 5. สร้างตาราง fuel_requisition_items
CREATE TABLE IF NOT EXISTS fuel_requisition_items (
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

-- 6. สร้างตาราง fuel_vendors
CREATE TABLE IF NOT EXISTS fuel_vendors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    address    VARCHAR(400) NOT NULL DEFAULT '',
    phone      VARCHAR(50)  NOT NULL DEFAULT '',
    is_default TINYINT(1)   NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- ข้อมูลเริ่มต้น (Seeding Initial Data with UTF-8 Safety)
-- ==========================================

-- เพิ่มข้อมูลรถยนต์
INSERT INTO vehicles (id, vehicle_name, license_plate, vehicle_type, fuel_type)
VALUES
    (1, CONVERT(UNHEX('e0b982e0b895e0b982e0b8a2e0b895e0b989e0b8b220e0b8a3e0b896e0b895e0b8b9e0b989') USING utf8mb4), CONVERT(UNHEX('3730393320e0b881e0b897e0b8a1') USING utf8mb4), CONVERT(UNHEX('e0b8a3e0b896e0b895e0b8b9e0b989') USING utf8mb4), 'diesel'),
    (2, CONVERT(UNHEX('e0b982e0b895e0b982e0b8a2e0b895e0b989e0b8b220e0b8a3e0b896e0b882e0b8b2e0b8a7') USING utf8mb4), CONVERT(UNHEX('e0b881e0b88234353938') USING utf8mb4), CONVERT(UNHEX('e0b8a3e0b896e0b882e0b8b2e0b8a7') USING utf8mb4), 'diesel'),
    (3, CONVERT(UNHEX('e0b8ade0b8b5e0b88be0b8b9e0b88be0b8b820e0b8a3e0b896e0b980e0b8abe0b8a5e0b8b7e0b8ade0b88720e0b983e0b8abe0b8a1e0b988') USING utf8mb4), CONVERT(UNHEX('34302d30343132') USING utf8mb4), CONVERT(UNHEX('e0b8a3e0b896e0b8abe0b881e0b8a5e0b989e0b8ad') USING utf8mb4), 'diesel'),
    (4, CONVERT(UNHEX('e0b982e0b895e0b982e0b8a2e0b895e0b989e0b8b220e0b8a3e0b896e0b8aae0b8b5e0b988e0b89be0b8a3e0b8b0e0b895e0b8b920e0b983e0b8abe0b8a1e0b988') USING utf8mb4), CONVERT(UNHEX('e0b881e0b89933383832') USING utf8mb4), CONVERT(UNHEX('e0b8a3e0b896e0b8aae0b8b5e0b988e0b89be0b8a3e0b8b0e0b895e0b8b9') USING utf8mb4), 'diesel'),
    (5, CONVERT(UNHEX('e0b899e0b8b4e0b8aae0b8aae0b8b1e0b89920e0b8a3e0b896e0b897e0b8ade0b887') USING utf8mb4), CONVERT(UNHEX('e0b89ae0b89b35303838') USING utf8mb4), CONVERT(UNHEX('e0b8a3e0b896e0b897e0b8ade0b887') USING utf8mb4), 'diesel'),
    (6, CONVERT(UNHEX('e0b8ade0b8b5e0b88be0b8b9e0b88be0b8b820e0b8a3e0b896e0b8abe0b881e0b8a5e0b989e0b8ad20e0b980e0b881e0b988e0b8b2') USING utf8mb4), CONVERT(UNHEX('34302d30313939') USING utf8mb4), CONVERT(UNHEX('e0b8a3e0b896e0b8abe0b881e0b8a5e0b989e0b8ad') USING utf8mb4), 'diesel')
ON DUPLICATE KEY UPDATE
    vehicle_name = VALUES(vehicle_name),
    license_plate = VALUES(license_plate),
    vehicle_type = VALUES(vehicle_type);

-- เพิ่มข้อมูลผู้ใช้อนุมัติเริ่มต้น
INSERT INTO users (full_name, username, password_hash, role, position_title)
VALUES
    (
        CONVERT(UNHEX('e0b899e0b8b2e0b8a2e0b8a7e0b8b4e0b899e0b8b1e0b8a220e0b888e0b8b1e0b899e0b897e0b8a3e0b98ce0b884e0b8b3') USING utf8mb4),
        'supply',
        '$2y$10$bjRcGmg8j5eSPkajABBO/.8ciIwBzrVeCXE5cJ3FP/fpmnjIBkEou',
        'supply_head',
        CONVERT(UNHEX('e0b8abe0b8b1e0b8a7e0b8abe0b899e0b989e0b8b2e0b887e0b8b2e0b899e0b89ee0b8b1e0b8aae0b894e0b8b8') USING utf8mb4)
    ),
    (
        CONVERT(UNHEX('e0b899e0b8b2e0b8a2e0b8a1e0b893e0b980e0b891e0b8b5e0b8a2e0b8a320e0b887e0b8b2e0b8a1e0b981e0b881e0b989e0b8a7') USING utf8mb4),
        'deputy',
        '$2y$10$bjRcGmg8j5eSPkajABBO/.8ciIwBzrVeCXE5cJ3FP/fpmnjIBkEou',
        'deputy_director',
        CONVERT(UNHEX('e0b8a3e0b8ade0b887e0b89ce0b8b9e0b989e0b8ade0b8b3e0b899e0b8a7e0b8a2e0b881e0b8b2e0b8a3e0b89de0b988e0b8b2e0b8a2e0b89ae0b8a3e0b8b4e0b8abe0b8b2e0b8a3e0b897e0b8a3e0b8b1e0b89ee0b8a2e0b8b2e0b881e0b8a3') USING utf8mb4)
    ),
    (
        CONVERT(UNHEX('e0b899e0b8b2e0b8a2e0b89ae0b8b1e0b88de0b88ae0b8b220e0b982e0b884e0b895e0b8a3e0b981e0b881e0b989e0b8a7') USING utf8mb4),
        'director',
        '$2y$10$bjRcGmg8j5eSPkajABBO/.8ciIwBzrVeCXE5cJ3FP/fpmnjIBkEou',
        'director',
        CONVERT(UNHEX('e0b89ce0b8b9e0b989e0b8ade0b8b3e0b899e0b8a7e0b8a2e0b881e0b8b2e0b8a3') USING utf8mb4)
    ),
    (
        CONVERT(UNHEX('e0b89ce0b8b9e0b989e0b894e0b8b9e0b981e0b8a5e0b8a3e0b8b0e0b89ae0b89a') USING utf8mb4),
        'admin',
        '$2y$10$bjRcGmg8j5eSPkajABBO/.8ciIwBzrVeCXE5cJ3FP/fpmnjIBkEou',
        'admin',
        CONVERT(UNHEX('e0b89ce0b8b9e0b989e0b894e0b8b9e0b981e0b8a5e0b8a3e0b8b0e0b89ae0b89a') USING utf8mb4)
    )
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    position_title = VALUES(position_title),
    is_active = 1;

-- เพิ่มร้านค้าน้ำมันเชื้อเพลิงเริ่มต้น
INSERT INTO fuel_vendors (id, name, address, phone, is_default) VALUES
(1, 'หจก.โพลีพัฒนกิจ', '91 ม.1 ต.สระแก้ว อ.พนมไพร จ.ร้อยเอ็ด', '043-590619', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    address = VALUES(address),
    phone = VALUES(phone),
    is_default = VALUES(is_default);

-- 8. สร้างตาราง system_settings (ค่าคอนฟิกธีมสีและชื่อระบบขอรถย่อย)
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    system_name VARCHAR(150) NOT NULL DEFAULT 'PNP Go',
    theme_color VARCHAR(50) NOT NULL DEFAULT 'rose'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (id, system_name, theme_color) VALUES (1, 'PNP Go', 'rose');
