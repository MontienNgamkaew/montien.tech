<?php
/* -------------------------------------------------------------
 * TOKEN VERIFICATION API ENDPOINT
 * Validates JWT from headers or query string, returns user details
 * Includes primary_position, org_position, and department
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jwt.php';

$token = '';

// 1. ดึง Token จาก HTTP Authorization Header
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

if (!empty($authHeader)) {
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

// 2. ดึง Token จาก URL Query String หรือ POST Parameters
if (empty($token)) {
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
    } elseif (isset($_POST['token'])) {
        $token = $_POST['token'];
    }
}

if (empty($token)) {
    sendResponse([
        'valid' => false,
        'error' => 'ไม่พบข้อมูลยืนยันตัวตน (Token) ในคำขอ'
    ], 401);
}

// 3. ถอดรหัสและตรวจสอบความถูกต้องด้วย JWT Secret
$payload = JWT::decode($token, JWT_SECRET_KEY);

if (!$payload) {
    sendResponse([
        'valid' => false,
        'error' => 'โทเค็นยืนยันตัวตนไม่ถูกต้องหรือหมดอายุการใช้งานแล้ว'
    ], 401);
}

// 4. ส่งผลยืนยันการทำรายการสำเร็จและคืนข้อมูลผู้ใช้กลับไป
sendResponse([
    'valid' => true,
    'message' => 'ยืนยันตัวตนสำเร็จ',
    'user' => [
        'user_id' => $payload['user_id'],
        'username' => $payload['username'],
        'email' => $payload['email'],
        'first_name' => $payload['first_name'],
        'last_name' => $payload['last_name'],
        'primary_position' => isset($payload['primary_position']) ? $payload['primary_position'] : '',
        'org_position' => isset($payload['org_position']) ? $payload['org_position'] : '',
        'department' => isset($payload['department']) ? $payload['department'] : '',
        'is_portal_admin' => $payload['is_portal_admin'],
        'roles' => $payload['roles'],
        'exp' => $payload['exp']
    ]
]);
