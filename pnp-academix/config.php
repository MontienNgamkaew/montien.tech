<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

// Dynamic Environment Configuration (XAMPP Local vs Hostinger Production)
if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'montien.tech') !== false || $_SERVER['HTTP_HOST'] === 'pnp-edu.montien.tech')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u651170081_pnp_academix');
    define('DB_USER', 'u651170081_pnp_academix');
    define('DB_PASS', 'a1d9GH10%');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pnp_academix');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
    
    // Auto-initialize branding settings
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS branding_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meta_key VARCHAR(50) UNIQUE NOT NULL,
            meta_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("INSERT IGNORE INTO branding_settings (meta_key, meta_value) VALUES 
            ('system_name', 'PNP Academix | ระบบบริหารงานวิชาการ'),
            ('college_name', 'วิทยาลัยการอาชีพพนมไพร'),
            ('logo_path', ''),
            ('logo_text', 'PNP'),
            ('theme_color', 'dark-blue')
        ");
        
        // Auto-migrate: check if 'department' column exists in 'users' table, if not add it
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'department'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN department VARCHAR(255) NULL AFTER fullname");
        }
        
        // Auto-update system name from old default to 'PNP Academix | ระบบบริหารงานวิชาการ' if it hasn't been changed yet
        $stmt = $pdo->prepare("SELECT meta_value FROM branding_settings WHERE meta_key = 'system_name' LIMIT 1");
        $stmt->execute();
        $currentName = $stmt->fetchColumn();
        if ($currentName === 'ระบบบริหารงานวิชาการ' || $currentName === 'PNP Academix') {
            $pdo->exec("UPDATE branding_settings SET meta_value = 'PNP Academix | ระบบบริหารงานวิชาการ' WHERE meta_key = 'system_name'");
        }
    } catch (Exception $e) {
        // Fail silently
    }
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ');
}

// Global helper for branding settings
function get_branding_settings(bool $force_reload = false): array {
    global $pdo;
    static $settings = null;
    if ($settings !== null && !$force_reload) {
        return $settings;
    }
    
    try {
        $rows = $pdo->query("SELECT meta_key, meta_value FROM branding_settings")->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['meta_key']] = $row['meta_value'];
        }
        
        // Fill defaults if missing
        $defaults = [
            'system_name' => 'PNP Academix | ระบบบริหารงานวิชาการ',
            'college_name' => 'วิทยาลัยการอาชีพพนมไพร',
            'logo_path' => '',
            'logo_text' => 'PNP',
            'theme_color' => 'dark-blue'
        ];
        foreach ($defaults as $k => $v) {
            if (!isset($settings[$k])) {
                $settings[$k] = $v;
            }
        }
    } catch (Exception $e) {
        $settings = [
            'system_name' => 'PNP Academix | ระบบบริหารงานวิชาการ',
            'college_name' => 'วิทยาลัยการอาชีพพนมไพร',
            'logo_path' => '',
            'logo_text' => 'PNP',
            'theme_color' => 'dark-blue'
        ];
    }
    return $settings;
}

// Security escape helper
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Helper to extract YouTube video ID
function get_youtube_id(?string $url): ?string {
    if (empty($url)) return null;
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|e\/|embed\/|user\/[^\/]+\/|u\/\d+\/|apps\/|shorts\/)|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/|youtube\.com\/watch\?v=|youtube\.com\/watch\?.+&v=)([^"&?\/ ]{11})/i';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    }
    return null;
}

// Helper to get connection to portal DB
function get_portal_db_connection(): ?PDO {
    global $portalPdoInstance;
    if (isset($portalPdoInstance)) {
        return $portalPdoInstance;
    }
    
    // Path to central env.php
    $envPath = dirname(__DIR__) . '/api/env.php';
    if (!is_file($envPath)) {
        $envPath = dirname(dirname(__DIR__)) . '/api/env.php';
    }
    
    if (is_file($envPath)) {
        require_once $envPath;
    }
    
    $isHostinger = isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'montien.tech') !== false || $_SERVER['HTTP_HOST'] === 'pnp-portal.montien.tech');
    
    if ($isHostinger) {
        $dbHost = function_exists('env') ? env('DB_HOST', 'localhost') : 'localhost';
        $dbName = function_exists('env') ? env('DB_NAME', 'u651170081_pnp_portal') : 'u651170081_pnp_portal';
        $dbUser = function_exists('env') ? env('DB_USER', 'u651170081_pnp_portal') : 'u651170081_pnp_portal';
        $dbPass = function_exists('env') ? env('DB_PASS', '') : '';
    } else {
        $dbHost = function_exists('env') ? env('DB_HOST', 'localhost') : 'localhost';
        $dbName = function_exists('env') ? env('DB_NAME', 'pnp_portal') : 'pnp_portal';
        $dbUser = function_exists('env') ? env('DB_USER', 'root') : 'root';
        $dbPass = function_exists('env') ? env('DB_PASS', '') : '';
    }
    
    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $portalPdoInstance = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        return $portalPdoInstance;
    } catch (Exception $e) {
        error_log('Portal DB Connection failed: ' . $e->getMessage());
        return null;
    }
}

// Helper to query all assigned departments for a teacher according to pnpman
function get_teacher_departments(string $username): array {
    $dbPortal = get_portal_db_connection();
    if (!$dbPortal) {
        return [];
    }
    try {
        $stmt = $dbPortal->prepare("
            SELECT DISTINCT j.name AS dept_name
            FROM assignments a
            INNER JOIN jobs j ON j.id = a.job_id
            INNER JOIN users u ON u.id = a.personnel_id
            WHERE u.username = :username
              AND j.name LIKE 'แผนกวิชา%'
            ORDER BY j.name ASC
        ");
        $stmt->execute(['username' => $username]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log('get_teacher_departments failed: ' . $e->getMessage());
        return [];
    }
}

// Helper to query curriculum personnel from pnpman (job_id = 27)
function get_curriculum_personnel(string $roleType): string {
    $dbPortal = get_portal_db_connection();
    if (!$dbPortal) {
        return '';
    }
    try {
        $stmt = $dbPortal->prepare("
            SELECT u.first_name, u.last_name, a.role, a.comment
            FROM assignments a
            INNER JOIN users u ON u.id = a.personnel_id
            WHERE a.job_id = 27
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        $headName = '';
        $officerName = '';
        $anyName = '';
        
        foreach ($rows as $row) {
            $fullname = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (empty($anyName)) {
                $anyName = $fullname;
            }
            
            $role = $row['role'] ?? '';
            $comment = $row['comment'] ?? '';
            
            if (mb_strpos($role, 'หัวหน้า') !== false || mb_strpos($comment, 'หัวหน้า') !== false) {
                $headName = $fullname;
            } elseif (mb_strpos($role, 'เจ้าหน้าที่') !== false || mb_strpos($comment, 'เจ้าหน้าที่') !== false) {
                $officerName = $fullname;
            }
        }
        
        if ($roleType === 'head') {
            return !empty($headName) ? $headName : $anyName;
        } else { // officer
            if (!empty($officerName)) {
                return $officerName;
            }
            // Fallback to any assignment that is not the head
            foreach ($rows as $row) {
                $fullname = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if ($fullname !== $headName) {
                    return $fullname;
                }
            }
            return $anyName;
        }
    } catch (Exception $e) {
        error_log('get_curriculum_personnel failed: ' . $e->getMessage());
        return '';
    }
}

// Helper to query head of academic resources from pnpman (job_id = 30)
function get_academic_resources_head(): string {
    $dbPortal = get_portal_db_connection();
    if (!$dbPortal) {
        return '';
    }
    try {
        $stmt = $dbPortal->prepare("
            SELECT u.first_name, u.last_name, a.role, a.comment
            FROM assignments a
            INNER JOIN users u ON u.id = a.personnel_id
            WHERE a.job_id = 30
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        $headName = '';
        $anyName = '';
        
        foreach ($rows as $row) {
            $fullname = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (empty($anyName)) {
                $anyName = $fullname;
            }
            
            $role = $row['role'] ?? '';
            $comment = $row['comment'] ?? '';
            
            if (mb_strpos($role, 'หัวหน้า') !== false || mb_strpos($comment, 'หัวหน้า') !== false) {
                $headName = $fullname;
            }
        }
        
        return !empty($headName) ? $headName : $anyName;
    } catch (Exception $e) {
        error_log('get_academic_resources_head failed: ' . $e->getMessage());
        return '';
    }
}





