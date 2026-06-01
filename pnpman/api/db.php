<?php
// CORS headers are handled by api/config.php

// นำเข้าการเชื่อมต่อฐานข้อมูลหลักจากพอร์ทัลกลางเพื่อหลีกเลี่ยงรหัสผ่านไม่ตรงกัน

// นำเข้าการเชื่อมต่อฐานข้อมูลหลักจากพอร์ทัลกลางเพื่อหลีกเลี่ยงรหัสผ่านไม่ตรงกัน
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/database.php';
$pdo = $db;

// Auto-create college_settings and assignments tables for PNP Man if they do not exist (Self-healing DB)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS college_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        college_name VARCHAR(255) NOT NULL DEFAULT 'วิทยาลัยการอาชีพพนมไพร',
        logo_path VARCHAR(255) NULL,
        theme_preset VARCHAR(50) NOT NULL DEFAULT 'rose'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM college_settings WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT IGNORE INTO college_settings (id, college_name, logo_path, theme_preset) VALUES (1, 'วิทยาลัยการอาชีพพนมไพร', '', 'rose')");
    }
} catch (PDOException $e) {
    // Silently continue or log if needed
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        personnel_id INT NOT NULL,
        job_id INT NOT NULL,
        role VARCHAR(100) NOT NULL,
        academic_year INT NOT NULL DEFAULT 2569,
        sort_order INT NOT NULL DEFAULT 0,
        comment VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (personnel_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignment_v2 (personnel_id, job_id, role, academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Silently continue or log if needed
}

function parseFullName($fullName) {
    $fullName = trim($fullName);
    $titles = ['นาย', 'นางสาว', 'นาง', 'ดร.', 'ศาสตราจารย์', 'รองศาสตราจารย์', 'ผู้ช่วยศาสตราจารย์', 'ศ.', 'รศ.', 'ผศ.', 'ว่าที่ร้อยตรี', 'ว่าที่ ร.ต.', 'จ่าสิบเอก'];
    $title = '';
    $rest = $fullName;
    
    foreach ($titles as $t) {
        if (strpos($fullName, $t) === 0) {
            $title = $t;
            $rest = trim(substr($fullName, strlen($t)));
            break;
        }
    }
    
    $parts = preg_split('/\s+/', $rest);
    $first_name = $parts[0] ?? '';
    $last_name = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
    
    return [
        'title' => $title,
        'first_name' => $first_name,
        'last_name' => $last_name
    ];
}

function isCurrentAdmin($pdo) {
    require_once __DIR__ . '/../../api/jwt.php';
    require_once __DIR__ . '/../../api/config.php';

    $authHeader = '';
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';
    }
    
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return false;
    }

    $token = $matches[1];
    $payload = JWT::decode($token, JWT_SECRET_KEY);
    if (!$payload) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT u.id 
        FROM users u 
        LEFT JOIN app_roles r ON u.id = r.user_id AND r.app_id = 'pnp-man'
        WHERE u.id = ? 
          AND u.status = 'active'
          AND (u.is_portal_admin = 1 OR r.role = 'admin')
    ");
    $stmt->execute([$payload['user_id']]);
    return (bool)$stmt->fetch();
}
?>
