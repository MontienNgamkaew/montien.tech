<?php
/* -------------------------------------------------------------
 * CONFIGURATION & CORS SETUP FOR PNP CENTRAL AUTH API
 * ------------------------------------------------------------- */

// ในสถานการณ์จริง คีย์นี้ควรเก็บไว้ในตัวแปรสภาพแวดล้อม (Environment Variables) หรือไฟล์ .env
define('JWT_SECRET_KEY', 'pnp_platform_super_secure_key_2026_!!');
define('JWT_EXPIRY_SECONDS', 86400 * 7); // อายุโทเค็น 7 วัน เพื่อไม่ให้ผู้ใช้งานต้องกรอกรหัสผ่านบ่อยๆ ตามประสงค์

// ตั้งค่า CORS เพื่ออนุญาตให้ Subdomain ต่างๆ เข้าถึง API นี้ได้
header('Access-Control-Allow-Origin: *'); // สามารถเปลี่ยนเป็นโดเมนหลัก เช่น *.yourdomain.com ในการใช้งานจริง
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
