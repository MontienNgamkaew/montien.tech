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

// Make sure jobs.department_id is nullable (to support Director position)
try {
    $pdo->exec("ALTER TABLE jobs MODIFY department_id INT NULL");
} catch (PDOException $e) {
    // Silently continue or log if needed
}

// Auto-insert Director/Deputy Director positions into jobs table if missing (Self-healing positions)
try {
    $stmt = $pdo->prepare("
        INSERT INTO jobs (id, department_id, name, sort_order) VALUES
        (900, NULL, 'ผู้อำนวยการวิทยาลัย', 0),
        (901, 1, 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร', 0),
        (902, 2, 'รองผู้อำนวยการฝ่ายยุทธศาสตร์และแผนงาน', 0),
        (903, 3, 'รองผู้อำนวยการฝ่ายกิจการนักเรียน นักศึกษา', 0),
        (904, 4, 'รองผู้อำนวยการฝ่ายวิชาการ', 0)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name), 
            department_id = VALUES(department_id)
    ");
    $stmt->execute();
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
    // ใช้ตัวช่วย SSO กลางในการตรวจ JWT (ออกโดย Portal กลาง)
    require_once __DIR__ . '/../../api/sso_auth.php';

    $payload = pnp_auth_payload();
    if ($payload === null || !isset($payload['user_id'])) {
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
