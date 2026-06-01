<?php
/* -------------------------------------------------------------
 * SECURE PERSONNEL EXPORT TO CSV API
 * Exports all unified user profiles into a downloadable CSV file.
 * Retains Thai character compatibility via UTF-8 BOM.
 * Secured via Administrator JWT verification.
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';

// 1. JWT Administrator Authorization Check
$token = '';
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

if (empty($token) && isset($_GET['token'])) {
    $token = $_GET['token'];
}

if (empty($token)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'จำเป็นต้องลงชื่อเข้าใช้งานระดับผู้ดูแลระบบก่อนส่งออกข้อมูล']);
    exit;
}

$adminPayload = JWT::decode($token, JWT_SECRET_KEY);
if (!$adminPayload || (int)$adminPayload['is_portal_admin'] !== 1) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ไม่มีสิทธิ์การใช้งาน: เฉพาะผู้ดูแลระบบพอร์ทัลกลางเท่านั้น']);
    exit;
}

try {
    // 2. Query all active and suspended users (ordered by primary position sorting, then age if needed, or simply id)
    $stmt = $db->query("SELECT id, username, title, first_name, last_name, email, phone, birthdate, nickname, gender, education, primary_position, org_position, department, job, status, is_portal_admin FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="personnel_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // 4. Output UTF-8 BOM to prevent Excel garbled Thai text
    echo "\xEF\xBB\xBF";

    // 5. Open output buffer stream for writing CSV rows
    $output = fopen('php://output', 'w');

    // Define CSV Headers in Thai/English
    fputcsv($output, [
        'ชื่อผู้ใช้ (Username)',
        'คำนำหน้าชื่อ (Title)',
        'ชื่อจริง (Firstname)',
        'นามสกุล (Lastname)',
        'อีเมล (Email)',
        'เบอร์โทรศัพท์ (Phone)',
        'วุฒิการศึกษา (Education)',
        'วัน/เดือน/ปีเกิด (Birthdate)',
        'ชื่อเล่น (Nickname)',
        'เพศ (Gender)',
        'ตำแหน่งหลัก (Primary Position)',
        'ตำแหน่งโครงสร้าง (Org Position)',
        'ฝ่ายงาน (Department)',
        'กลุ่มงาน/แผนกวิชา (Job)',
        'สถานะบัญชี (Status)',
        'ผู้ดูแลระบบกลาง (Portal Admin)'
    ]);

    // 6. Write rows
    foreach ($users as $user) {
        fputcsv($output, [
            $user['username'],
            $user['title'],
            $user['first_name'],
            $user['last_name'],
            $user['email'],
            $user['phone'],
            $user['education'],
            $user['birthdate'],
            $user['nickname'],
            $user['gender'],
            $user['primary_position'],
            $user['org_position'],
            $user['department'],
            $user['job'],
            $user['status'] === 'active' ? 'เปิดใช้งาน' : 'ระงับการใช้งาน',
            $user['is_portal_admin'] == 1 ? 'เป็นแอดมิน' : 'บุคลากรทั่วไป'
        ]);
    }

    fclose($output);
    exit;

} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'เกิดข้อผิดพลาดในการส่งออกข้อมูล: ' . $e->getMessage()]);
    exit;
}
