<?php
// CORS headers are handled by api/config.php

// นำเข้าการเชื่อมต่อฐานข้อมูลหลักจากพอร์ทัลกลางเพื่อหลีกเลี่ยงรหัสผ่านไม่ตรงกัน

// นำเข้าการเชื่อมต่อฐานข้อมูลหลักจากพอร์ทัลกลางเพื่อหลีกเลี่ยงรหัสผ่านไม่ตรงกัน
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/database.php';
$pdo = $db;

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
