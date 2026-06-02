<?php
/* -------------------------------------------------------------
 * CONFIGURATION & CORS SETUP FOR PNP CENTRAL AUTH API
 * ------------------------------------------------------------- */

// โหลดค่าลับจากไฟล์ .env (ไม่ commit ขึ้น git) — ดู .env.example
require_once __DIR__ . '/env.php';

// คีย์ลับสำหรับเซ็น JWT — ต้องตั้งค่าจริงใน .env (โดยเฉพาะบน production)
// ค่าเริ่มต้นด้านล่างใช้เฉพาะตอนพัฒนาเครื่อง local ที่ยังไม่มี .env เท่านั้น
define('JWT_SECRET_KEY', env('JWT_SECRET_KEY', 'pnp_local_dev_only_change_me_in_env'));
define('JWT_EXPIRY_SECONDS', (int) env('JWT_EXPIRY_SECONDS', 86400 * 7)); // อายุโทเค็น (วินาที) ค่าเริ่มต้น 7 วัน

// ตั้งค่า CORS — จำกัด origin ตามรายการใน .env (CORS_ALLOWED_ORIGINS)
// หากเว้นว่างหรือกำหนดเป็น * จะอนุญาตทุก origin (พฤติกรรมเดิม เพื่อความเข้ากันได้ย้อนหลัง)
$corsAllowed = trim((string) env('CORS_ALLOWED_ORIGINS', '*'));
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($corsAllowed === '' || $corsAllowed === '*') {
    header('Access-Control-Allow-Origin: *');
} else {
    $allowedList = array_map('trim', explode(',', $corsAllowed));
    if ($requestOrigin !== '' && in_array($requestOrigin, $allowedList, true)) {
        header('Access-Control-Allow-Origin: ' . $requestOrigin);
        header('Vary: Origin');
    }
    // หาก origin ไม่อยู่ในรายการ จะไม่ส่งส่วนหัว ACAO (คำขอข้าม origin จะถูกบล็อก)
    // คำขอจาก origin เดียวกัน (same-origin) ไม่ได้รับผลกระทบ
}
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

// จัดการ Preflight Requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ฟังก์ชันสำหรับตอบกลับข้อมูลแบบ JSON
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
