<?php
/* -------------------------------------------------------------
 * USER PROFILE UPDATE API ENDPOINT
 * Allows logged-in users to update their own phone, email, education, avatar, and password
 * Secured via JWT token verification
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';

// 1. ตรวจสอบการยืนยันตัวตน (Authorization)
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
    sendResponse(['error' => 'จำเป็นต้องเข้าสู่ระบบก่อนทำการแก้ไข'], 401);
}

// ถอดรหัส Token
$payload = JWT::decode($token, JWT_SECRET_KEY);
if (!$payload) {
    sendResponse(['error' => 'โทเค็นไม่ถูกต้องหรือหมดอายุการใช้งาน'], 401);
}

$userId = (int)$payload['user_id'];

// 2. ดึงข้อมูลที่ส่งเข้ามาผ่าน JSON Body
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$email = isset($input['email']) ? trim($input['email']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : null;
$education = isset($input['education']) ? trim($input['education']) : null;
$password = isset($input['password']) ? $input['password'] : '';
$avatar = isset($input['avatar']) ? $input['avatar'] : '';
$removeAvatar = isset($input['remove_avatar']) ? (int)$input['remove_avatar'] : 0;

if (empty($email)) {
    sendResponse(['error' => 'กรุณากรอกอีเมลประจำตัวกลาง'], 400);
}

try {
    // 3. ตรวจสอบความถูกต้องของบัญชีผู้ใช้ในระบบ
    $stmtUserCheck = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmtUserCheck->execute([':id' => $userId]);
    $user = $stmtUserCheck->fetch();

    if (!$user) {
        sendResponse(['error' => 'ไม่พบข้อมูลผู้ใช้งานในระบบ'], 404);
    }
    
    if ($user['status'] === 'suspended') {
        sendResponse(['error' => 'บัญชีผู้ใช้งานนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'], 403);
    }

    // 4. ตรวจสอบว่าอีเมลใหม่ไม่ซ้ำกับผู้อื่น
    $stmtEmailCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
    $stmtEmailCheck->execute([':email' => $email, ':id' => $userId]);
    if ($stmtEmailCheck->fetchColumn() > 0) {
        sendResponse(['error' => 'อีเมลประจำตัวนี้ถูกใช้งานโดยบุคลากรรายอื่นแล้ว'], 400);
    }

    $db->beginTransaction();

    // 5. จัดการรูปภาพโปรไฟล์ (Base64)
    $avatarPath = $user['avatar_path'];
    $hasNewAvatar = false;

    if ($removeAvatar === 1) {
        // หากต้องการลบรูปโปรไฟล์ออก
        if ($avatarPath && file_exists(__DIR__ . '/../' . $avatarPath)) {
            @unlink(__DIR__ . '/../' . $avatarPath);
        }
        $avatarPath = null;
        $hasNewAvatar = true;
    } elseif (!empty($avatar) && preg_match('/^data:image\/(\w+);base64,/', $avatar, $type)) {
        // หากมีการอัปโหลดรูปภาพใหม่เข้ามา
        $ext = strtolower($type[1]);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $data = substr($avatar, strpos($avatar, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                // ลบรูปโปรไฟล์เดิมก่อน
                if ($user['avatar_path'] && file_exists(__DIR__ . '/../' . $user['avatar_path'])) {
                    @unlink(__DIR__ . '/../' . $user['avatar_path']);
                }

                $filename = 'avatar_' . uniqid() . '.' . $ext;
                $dir = __DIR__ . '/../uploads/';
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                if (file_put_contents($dir . $filename, $data)) {
                    $avatarPath = 'uploads/' . $filename;
                    $hasNewAvatar = true;
                }
            }
        }
    }

    // 6. เตรียมคิวรีและข้อมูลเพื่ออัปเดตตาราง users
    $sql = "UPDATE users SET email = :email, phone = :phone, education = :education";
    $params = [
        ':email' => $email,
        ':phone' => $phone,
        ':education' => $education,
        ':id' => $userId
    ];

    if ($hasNewAvatar) {
        $sql .= ", avatar_path = :avatar_path";
        $params[':avatar_path'] = $avatarPath;
    }

    if (!empty($password)) {
        if (strlen($password) < 6) {
            sendResponse(['error' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร'], 400);
        }
        $sql .= ", password_hash = :password_hash";
        $params[':password_hash'] = password_hash($password, PASSWORD_BCRYPT);
    }

    $sql .= " WHERE id = :id";
    
    $stmtUpdate = $db->prepare($sql);
    $stmtUpdate->execute($params);

    $db->commit();

    // 7. ดึงข้อมูลที่ได้รับการปรับปรุงล่าสุดเพื่อสร้าง JWT Token ใหม่
    $stmtUpdatedUser = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmtUpdatedUser->execute([':id' => $userId]);
    $updatedUser = $stmtUpdatedUser->fetch();

    // ดึงสิทธิ์ของแต่ละระบบย่อยเพื่อแพ็คเข้า JWT
    $stmtRoles = $db->prepare("SELECT app_id, role FROM app_roles WHERE user_id = :user_id");
    $stmtRoles->execute([':user_id' => $userId]);
    $rolesRows = $stmtRoles->fetchAll();

    $appRoles = [];
    foreach ($rolesRows as $row) {
        $appRoles[$row['app_id']] = $row['role'];
    }

    if ((int)$updatedUser['is_portal_admin'] === 1) {
        $appRoles['pnp-go'] = 'admin';
        $appRoles['pnp-academic'] = 'admin';
        $appRoles['pnp-man'] = 'admin';
    }

    // สร้าง JWT Token ใหม่
    $issuedAt = time();
    $expireAt = $issuedAt + JWT_EXPIRY_SECONDS;

    $newPayload = [
        'user_id' => $userId,
        'username' => $updatedUser['username'],
        'email' => $updatedUser['email'],
        'title' => $updatedUser['title'],
        'first_name' => $updatedUser['first_name'],
        'last_name' => $updatedUser['last_name'],
        'primary_position' => $updatedUser['primary_position'],
        'org_position' => $updatedUser['org_position'],
        'department' => $updatedUser['department'],
        'is_portal_admin' => (int)$updatedUser['is_portal_admin'],
        'roles' => $appRoles,
        'iat' => $issuedAt,
        'exp' => $expireAt
    ];

    $newToken = JWT::encode($newPayload, JWT_SECRET_KEY);

    sendResponse([
        'message' => 'ปรับปรุงข้อมูลส่วนตัวเรียบร้อยแล้ว',
        'token' => $newToken,
        'user' => [
            'username' => $updatedUser['username'],
            'email' => $updatedUser['email'],
            'title' => $updatedUser['title'],
            'first_name' => $updatedUser['first_name'],
            'last_name' => $updatedUser['last_name'],
            'primary_position' => $updatedUser['primary_position'],
            'org_position' => $updatedUser['org_position'],
            'department' => $updatedUser['department'],
            'is_portal_admin' => (int)$updatedUser['is_portal_admin'],
            'roles' => $appRoles,
            'phone' => $updatedUser['phone'],
            'education' => $updatedUser['education'],
            'avatar' => $updatedUser['avatar_path']
        ]
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendResponse(['error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()], 500);
}
