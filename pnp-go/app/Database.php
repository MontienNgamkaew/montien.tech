<?php

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Self-Healing DB Schema Integrity: Automatically check and add missing columns
        try {
            // Check 'users' table columns
            $stmt = self::$connection->query("SHOW COLUMNS FROM users");
            $usersCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('avatar_path', $usersCols)) {
                self::$connection->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER signature_path");
            }
            if (!in_array('primary_position', $usersCols)) {
                self::$connection->exec("ALTER TABLE users ADD COLUMN primary_position VARCHAR(150) NULL AFTER position_title");
            }

            // Check 'requisitions' table columns
            $stmt = self::$connection->query("SHOW COLUMNS FROM requisitions");
            $reqsCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('user_id', $reqsCols)) {
                self::$connection->exec("ALTER TABLE requisitions ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id");
                try {
                    self::$connection->exec("ALTER TABLE requisitions ADD CONSTRAINT fk_requisitions_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL");
                } catch (Throwable $fkEx) {
                    // Ignore if FK cannot be added immediately
                }
            }
            if (!in_array('odometer_after', $reqsCols)) {
                self::$connection->exec("ALTER TABLE requisitions ADD COLUMN odometer_after INT UNSIGNED NULL AFTER odometer_before");
            }
            if (!in_array('report_photo_path', $reqsCols)) {
                self::$connection->exec("ALTER TABLE requisitions ADD COLUMN report_photo_path VARCHAR(255) NULL AFTER pdf_path");
            }
            if (!in_array('reported_at', $reqsCols)) {
                self::$connection->exec("ALTER TABLE requisitions ADD COLUMN reported_at DATETIME NULL AFTER report_photo_path");
            }
            
            // Check and auto-create 'system_settings' table
            self::$connection->exec("CREATE TABLE IF NOT EXISTS system_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                system_name VARCHAR(150) NOT NULL DEFAULT 'PNP Go',
                theme_color VARCHAR(50) NOT NULL DEFAULT 'rose'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $stmtSettings = self::$connection->query("SELECT COUNT(*) FROM system_settings WHERE id = 1");
            if ((int)$stmtSettings->fetchColumn() === 0) {
                self::$connection->exec("INSERT IGNORE INTO system_settings (id, system_name, theme_color) VALUES (1, 'PNP Go', 'rose')");
            }
        } catch (Throwable $e) {
            // Fail silently to avoid interrupting execution if db has limited permissions
        }

        return self::$connection;
    }

    public static function portalConnection(): PDO
    {
        static $portalConnection = null;
        if ($portalConnection instanceof PDO) {
            return $portalConnection;
        }

        $config = config('database');
        $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || (php_sapi_name() === 'cli');

        // ข้อมูลเชื่อมต่อฐานข้อมูล Portal กลาง อ่านจาก .env (คีย์ DB_*) เป็นหลัก
        // ค่าเริ่มต้นคงพฤติกรรมเดิมไว้ทั้ง local และ production
        $portalDbName   = env('DB_NAME', $isLocal ? 'pnp_portal' : 'u651170081_pnp_portal');
        $portalUsername = env('DB_USER', $isLocal ? $config['username'] : 'u651170081_pnp_portal');
        $portalPassword = env('DB_PASS', $config['password']);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $portalDbName,
            $config['charset']
        );

        $portalConnection = new PDO($dsn, $portalUsername, $portalPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $portalConnection;
    }
}
