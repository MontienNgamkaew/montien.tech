<?php
/* -------------------------------------------------------------
 * SHARED SSO AUTHENTICATION SDK
 * -------------------------------------------------------------
 * ตัวช่วยกลางสำหรับให้ทุกระบบย่อย (pnp-go, pnpman, ฯลฯ) ตรวจสอบ
 * JWT ที่ออกโดย Portal กลางด้วยวิธีเดียวกัน  เพื่อเลี่ยงการ copy
 * โค้ดถอด token ไปกระจายในแต่ละระบบ
 *
 * ฟังก์ชันหลัก:
 *   pnp_bearer_token()  -> ดึง token จาก Authorization header
 *   pnp_auth_payload()  -> ถอด + ตรวจ JWT แล้วคืน payload (หรือ null)
 *   pnp_auth_user_id()  -> คืน user_id ของผู้ใช้ที่ผ่านการตรวจ (หรือ null)
 * ------------------------------------------------------------- */

require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/config.php'; // นิยาม JWT_SECRET_KEY + ตั้งค่า CORS

if (!function_exists('pnp_bearer_token')) {
    /**
     * ดึง JWT จากส่วนหัว Authorization: Bearer <token>
     * รองรับทั้ง Apache, Nginx และกรณีที่ header ถูก redirect
     */
    function pnp_bearer_token(): ?string
    {
        $authHeader = '';

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
        }
        if ($authHeader === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if ($authHeader === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if ($authHeader !== '' && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

if (!function_exists('pnp_auth_payload')) {
    /**
     * ถอดรหัสและตรวจสอบ JWT จากคำขอปัจจุบัน
     *
     * @return array<string,mixed>|null  payload หากถูกต้อง มิฉะนั้น null
     */
    function pnp_auth_payload(): ?array
    {
        $token = pnp_bearer_token();
        if ($token === null) {
            return null;
        }

        $payload = JWT::decode($token, JWT_SECRET_KEY);
        return is_array($payload) ? $payload : null;
    }
}

if (!function_exists('pnp_auth_user_id')) {
    /**
     * คืนค่า user_id ของผู้ใช้ที่ผ่านการยืนยันตัวตน
     */
    function pnp_auth_user_id(): ?int
    {
        $payload = pnp_auth_payload();
        if ($payload === null || !isset($payload['user_id'])) {
            return null;
        }
        return (int) $payload['user_id'];
    }
}
