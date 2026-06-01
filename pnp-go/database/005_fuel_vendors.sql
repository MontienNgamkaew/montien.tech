-- Migration 005: fuel_vendors table
CREATE TABLE IF NOT EXISTS fuel_vendors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    address    VARCHAR(400) NOT NULL DEFAULT '',
    phone      VARCHAR(50)  NOT NULL DEFAULT '',
    is_default TINYINT(1)   NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลเริ่มต้น
INSERT INTO fuel_vendors (name, address, phone, is_default) VALUES
('หจก.โพลีพัฒนกิจ', '91 ม.1 ต.สระแก้ว อ.พนมไพร จ.ร้อยเอ็ด', '043-590619', 1);
