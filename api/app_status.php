<?php
/* -------------------------------------------------------------
 * APP STATUS API
 * GET  : (public) คืนสถานะของทุกระบบย่อย เพื่อให้หน้า Portal แสดงผล
 * POST : (admin)  ตั้งสถานะระบบย่อย — active / disabled / coming_soon
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';

$VALID_APPS    = ['pnp-go', 'pnp-man', 'pnp-academic', 'pnp-lesson-plan'];
$VALID_STATUS  = ['active', 'disabled', 'coming_soon'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------- READ (public) ----------
if ($method === 'GET') {
    try {
        $rows = $db->query('SELECT app_id, status FROM app_status')->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        $rows = [];
    }
    // เติมค่า default active ให้แอปที่ยังไม่มีแถว
    foreach ($VALID_APPS as $aid) {
        if (!isset($rows[$aid])) {
            $rows[$aid] = 'active';
        }
    }
    sendResponse(['status' => 'success', 'data' => $rows]);
}

// ---------- WRITE (admin only) ----------
if ($method === 'POST') {
    // ตรวจ JWT ระดับผู้ดูแลพอร์ทัล
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';
    }
    $token = '';
    if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $m)) {
        $token = $m[1];
    }
    if (empty($token)) {
        sendResponse(['error' => 'จำเป็นต้องลงชื่อเข้าใช้งานระดับผู้ดูแลระบบ'], 401);
    }

    $payload = JWT::decode($token, JWT_SECRET_KEY);
    if (!$payload || (int) ($payload['is_portal_admin'] ?? 0) !== 1) {
        sendResponse(['error' => 'ไม่มีสิทธิ์การใช้งาน: เฉพาะผู้ดูแลระบบพอร์ทัลกลางเท่านั้น'], 403);
    }

    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $appId  = trim($input['app_id'] ?? '');
    $status = trim($input['status'] ?? '');

    if (!in_array($appId, $VALID_APPS, true) || !in_array($status, $VALID_STATUS, true)) {
        sendResponse(['error' => 'ข้อมูลแอปหรือสถานะไม่ถูกต้อง'], 400);
    }

    $stmt = $db->prepare('INSERT INTO app_status (app_id, status) VALUES (:a, :s)
                          ON DUPLICATE KEY UPDATE status = :s2');
    $stmt->execute([':a' => $appId, ':s' => $status, ':s2' => $status]);

    sendResponse(['status' => 'success', 'message' => 'อัปเดตสถานะระบบเรียบร้อย', 'app_id' => $appId, 'app_status' => $status]);
}

sendResponse(['error' => 'Method not allowed'], 405);
