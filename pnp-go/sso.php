<?php
declare(strict_types=1);

/* -------------------------------------------------------------
 * PNP GO SINGLE SIGN-ON (SSO) ENTRYPOINT
 * Handles JWT verification from central Portal, maps user roles,
 * synchronizes user data, and logs users in.
 * ------------------------------------------------------------- */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../api/jwt.php';
require_once __DIR__ . '/../api/config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    // If no token is provided, redirect back to the Portal Home
    header('Location: /pnp-portal/');
    exit;
}

// Decode and verify the JWT Token using shared Portal Secret Key
$payload = JWT::decode($token, JWT_SECRET_KEY);
if (!$payload) {
    echo '<!doctype html>';
    echo '<html lang="th">';
    echo '<head><meta charset="utf-8"><title>ข้อผิดพลาด SSO</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">';
    echo '<div class="card shadow-sm p-4 text-center" style="max-width: 480px; border-radius: 16px;">';
    echo '<div class="text-danger fs-1 mb-3">⚠️</div>';
    echo '<h4 class="text-dark mb-3">โทเค็นการเชื่อมต่อไม่ถูกต้องหรือหมดอายุ</h4>';
    echo '<p class="text-muted mb-4">กรุณากลับไปที่พอร์ทัลกลางเพื่อลงชื่อเข้าใช้งานใหม่อีกครั้ง</p>';
    echo '<a href="/pnp-portal/" class="btn btn-primary w-100 py-2.5" style="border-radius: 10px;">กลับสู่หน้าแรกพอร์ทัลกลาง</a>';
    echo '</div></body></html>';
    exit;
}

// Extract user information from JWT Payload
$userId = (int)$payload['user_id'];
$username = $payload['username'];
$email = $payload['email'] ?? '';
$title = $payload['title'] ?? '';
$firstName = $payload['first_name'];
$lastName = $payload['last_name'];
$fullName = trim($title . $firstName . ' ' . $lastName);
$primaryPosition = $payload['primary_position'] ?? '';
$orgPosition = $payload['org_position'] ?? '';
$roles = $payload['roles'] ?? [];
$isPortalAdmin = (int)($payload['is_portal_admin'] ?? 0);

// Check if user has access to pnp-go subsystem
$pnpGoRole = $roles['pnp-go'] ?? 'none';

if ($pnpGoRole === 'none') {
    echo '<!doctype html>';
    echo '<html lang="th">';
    echo '<head><meta charset="utf-8"><title>ระงับสิทธิ์เข้าใช้งาน</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">';
    echo '<div class="card shadow-sm p-4 text-center" style="max-width: 480px; border-radius: 16px;">';
    echo '<div class="text-warning fs-1 mb-3">🛡️</div>';
    echo '<h4 class="text-dark mb-3">คุณไม่มีสิทธิ์เข้าใช้งานระบบ PNP Go</h4>';
    echo '<p class="text-muted mb-4">ระบบขอใช้รถยนต์ส่วนกลางจำกัดสิทธิ์เฉพาะบุคลากรที่ได้รับมอบหมายเท่านั้น หากต้องการใช้งาน กรุณาติดต่อผู้ดูแลระบบเพื่อขออนุมัติสิทธิ์</p>';
    echo '<a href="/pnp-portal/" class="btn btn-outline-secondary w-100 py-2.5" style="border-radius: 10px;">กลับสู่หน้าแรกพอร์ทัลกลาง</a>';
    echo '</div></body></html>';
    exit;
}

// -------------------------------------------------------------
// USER ROLE MAPPING (จับคู่สิทธิ์เข้าอนุมัติ 3 ระดับ)
// -------------------------------------------------------------
$localRole = 'user'; // สิทธิ์เริ่มต้นสำหรับยื่นคำขอทั่วไป

if ($isPortalAdmin === 1 || $pnpGoRole === 'admin') {
    $localRole = 'admin';
}
// อนุมัติระดับ 2: รองผู้อำนวยการฝ่ายบริหารทรัพยากร (หรือตำแหน่งหลักเป็น รองผู้อำนวยการ ตามการคัดกรองระบบ)
elseif ($orgPosition === 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร' || $primaryPosition === 'รองผู้อำนวยการ') {
    $localRole = 'deputy_director';
}
// อนุมัติระดับ 3: ผู้อำนวยการ
elseif ($primaryPosition === 'ผู้อำนวยการ') {
    $localRole = 'director';
}
// อนุมัติระดับ 1: หัวหน้างานพัสดุ
elseif ($orgPosition === 'หัวหน้างานพัสดุ' || strpos($orgPosition, 'หัวหน้างานพัสดุ') !== false) {
    $localRole = 'supply_head';
}

try {
    $avatar = $payload['avatar'] ?? '';

    $db = Database::connection();
    
    // Check if the user already exists in carrequest DB
    $stmt = $db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $localUser = $stmt->fetch();
    
    if ($localUser) {
        // Update user profile details and role dynamically
        $updateStmt = $db->prepare('
            UPDATE users 
            SET full_name = :full_name, 
                role = :role, 
                position_title = :position_title, 
                avatar_path = :avatar_path,
                is_active = 1,
                last_login_at = NOW() 
            WHERE id = :id
        ');
        $updateStmt->execute([
            'full_name' => $fullName,
            'role' => $localRole,
            'position_title' => $orgPosition ?: $primaryPosition,
            'avatar_path' => $avatar ?: null,
            'id' => $localUser['id']
        ]);
        $localUserId = (int)$localUser['id'];
    } else {
        // Automatically register (Sync) user into local carrequest DB
        $randomPass = bin2hex(random_bytes(16));
        $insertStmt = $db->prepare('
            INSERT INTO users (full_name, username, password_hash, role, position_title, avatar_path, is_active, last_login_at)
            VALUES (:full_name, :username, :password_hash, :role, :position_title, :avatar_path, 1, NOW())
        ');
        $insertStmt->execute([
            'full_name' => $fullName,
            'username' => $username,
            'password_hash' => password_hash($randomPass, PASSWORD_BCRYPT),
            'role' => $localRole,
            'position_title' => $orgPosition ?: $primaryPosition,
            'avatar_path' => $avatar ?: null
        ]);
        $localUserId = (int)$db->lastInsertId();
    }
    
    // Regenerate PHP Session for security and log in the user
    session_regenerate_id(true);
    $_SESSION['user_id'] = $localUserId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // Successful login! Redirect to PNP Go dashboard
    header('Location: ./dashboard');
    exit;

} catch (Exception $e) {
    echo '<!doctype html>';
    echo '<html lang="th">';
    echo '<head><meta charset="utf-8"><title>เกิดข้อผิดพลาดในการเชื่อมต่อ</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">';
    echo '<div class="card shadow-sm p-4 text-center" style="max-width: 480px; border-radius: 16px;">';
    echo '<div class="text-danger fs-1 mb-3">❌</div>';
    echo '<h4 class="text-dark mb-3">ไม่สามารถเชื่อมโยงฐานข้อมูลย่อยได้</h4>';
    echo '<p class="text-muted mb-4">พบปัญหาทางเทคนิค: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="/pnp-portal/" class="btn btn-primary w-100 py-2.5" style="border-radius: 10px;">กลับสู่หน้าแรกพอร์ทัลกลาง</a>';
    echo '</div></body></html>';
    exit;
}
