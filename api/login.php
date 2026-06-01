<?php
/* -------------------------------------------------------------
 * LOGIN API ENDPOINT
 * Receives username & password, returns JWT token & user details
 * Includes primary_position, org_position, and department in payload
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';

// ดึงข้อมูลที่ส่งเข้ามาผ่าน JSON Body
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($username) || empty($password)) {
    sendResponse(['error' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน'], 400);
}

try {
    // 1. ค้นหาผู้ใช้งานจากฐานข้อมูล SQLite
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // 2. ตรวจสอบบัญชีผู้ใช้และตรวจสอบรหัสผ่าน (password_verify)
    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendResponse(['error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'], 401);
    }

    // 2.1 ตรวจสอบสถานะการใช้งานบัญชี (หากโดนระงับ ให้ล็อกไม่ให้เข้าใช้งาน)
    if ($user['status'] === 'suspended') {
        sendResponse(['error' => 'บัญชีผู้ใช้งานนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'], 403);
    }

    // 3. ดึงสิทธิ์แยกแต่ละระบบย่อย (App Roles)
    $stmtRoles = $db->prepare("SELECT app_id, role FROM app_roles WHERE user_id = :user_id");
    $stmtRoles->execute([':user_id' => $user['id']]);
    $rolesRows = $stmtRoles->fetchAll();

    $appRoles = [];
    foreach ($rolesRows as $row) {
        $appRoles[$row['app_id']] = $row['role'];
    }

    // หากเป็นผู้ดูแลระบบพอร์ทัลกลางสูงสุด ให้สิทธิ์แอดมินทุกระบบย่อยโดยอัตโนมัติ
    if ((int)$user['is_portal_admin'] === 1) {
        $appRoles['pnp-go'] = 'admin';
        $appRoles['pnp-academic'] = 'admin';
        $appRoles['pnp-man'] = 'admin';
    }

    // 4. จัดเตรียมข้อมูล Payload ของ JWT
    $issuedAt = time();
    $expireAt = $issuedAt + JWT_EXPIRY_SECONDS;

    $payload = [
        'user_id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'title' => $user['title'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'primary_position' => $user['primary_position'],
        'org_position' => $user['org_position'],
        'department' => $user['department'],
        'is_portal_admin' => (int)$user['is_portal_admin'],
        'roles' => $appRoles,
        'iat' => $issuedAt,
        'exp' => $expireAt
    ];

    // 5. สร้าง JWT Token และลงนามด้วยคีย์ลับ
    $token = JWT::encode($payload, JWT_SECRET_KEY);

    // 6. ส่งผลลัพธ์กลับไปยังแอป
    sendResponse([
        'message' => 'เข้าสู่ระบบสำเร็จ',
        'token' => $token,
        'user' => [
            'username' => $user['username'],
            'email' => $user['email'],
            'title' => $user['title'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'primary_position' => $user['primary_position'],
            'org_position' => $user['org_position'],
            'department' => $user['department'],
            'is_portal_admin' => (int)$user['is_portal_admin'],
            'roles' => $appRoles
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(['error' => 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูล: ' . $e->getMessage()], 500);
}
