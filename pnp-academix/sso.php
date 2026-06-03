<?php
declare(strict_types=1);

/* -------------------------------------------------------------
 * PNP ACADEMIC SINGLE SIGN-ON (SSO) ENTRYPOINT
 * Handles JWT verification from central Portal, maps user roles,
 * synchronizes user data, and logs users in.
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../api/jwt.php';
require_once __DIR__ . '/../api/config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    // If no token is provided, redirect back to the Portal Home
    header('Location: ' . get_portal_url());
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
    echo '<a href="' . get_portal_url() . '" class="btn btn-primary w-100 py-2.5" style="border-radius: 10px;">กลับสู่หน้าแรกพอร์ทัลกลาง</a>';
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

// Check if user has access to pnp-academic subsystem
$pnpAcademicRole = $roles['pnp-academic'] ?? 'none';

if ($pnpAcademicRole === 'none') {
    echo '<!doctype html>';
    echo '<html lang="th">';
    echo '<head><meta charset="utf-8"><title>ระงับสิทธิ์เข้าใช้งาน</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">';
    echo '<div class="card shadow-sm p-4 text-center" style="max-width: 480px; border-radius: 16px;">';
    echo '<div class="text-warning fs-1 mb-3">🛡️</div>';
    echo '<h4 class="text-dark mb-3">คุณไม่มีสิทธิ์เข้าใช้งานระบบวิชาการ</h4>';
    echo '<p class="text-muted mb-4">ระบบบริหารงานวิชาการจำกัดสิทธิ์เฉพาะบุคลากรที่ได้รับมอบหมายเท่านั้น หากต้องการใช้งาน กรุณาติดต่อผู้ดูแลระบบเพื่อขออนุมัติสิทธิ์</p>';
    echo '<a href="' . get_portal_url() . '" class="btn btn-outline-secondary w-100 py-2.5" style="border-radius: 10px;">กลับสู่หน้าแรกพอร์ทัลกลาง</a>';
    echo '</div></body></html>';
    exit;
}

// -------------------------------------------------------------
// USER ROLE MAPPING (ระบบวิชาการมีเพียง 2 บทบาท: admin และ teacher)
// -------------------------------------------------------------
$localRole = 'teacher'; // สิทธิ์เริ่มต้น

if ($isPortalAdmin === 1 || $pnpAcademicRole === 'admin') {
    $localRole = 'admin';
}

try {
    // Check if the user already exists in pnp_academic DB
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $localUser = $stmt->fetch();
    
    // Query all assigned departments from pnpman
    $pnpmanDepts = get_teacher_departments($username);
    
    $calculatedDepartment = $payload['department'] ?? '';
    
    // If user belongs to multiple departments and local department is one of them, keep it!
    if ($localUser && !empty($localUser['department']) && count($pnpmanDepts) > 1 && in_array($localUser['department'], $pnpmanDepts, true)) {
        $calculatedDepartment = $localUser['department'];
    } elseif (count($pnpmanDepts) === 1) {
        // If they only have 1 department in pnpman, force sync to that 1 department
        $calculatedDepartment = $pnpmanDepts[0];
    } elseif (count($pnpmanDepts) > 1 && !in_array($calculatedDepartment, $pnpmanDepts, true)) {
        // If the payload's department is not in pnpman list, default to the first one in pnpman
        $calculatedDepartment = $pnpmanDepts[0];
    }
    
    if ($localUser) {
        // Update user profile details and role dynamically (Dynamic Role & Dept Sync)
        $updateStmt = $pdo->prepare('
            UPDATE users 
            SET fullname = :fullname, 
                role = :role, 
                department = :department,
                status = "active",
                updated_at = NOW() 
            WHERE id = :id
        ');
        $updateStmt->execute([
            'fullname' => $fullName,
            'role' => $localRole,
            'department' => $calculatedDepartment,
            'id' => $localUser['id']
        ]);
        $localUserId = (int)$localUser['id'];
    } else {
        // Automatically register (Sync) user into local pnp_academic DB
        $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $insertStmt = $pdo->prepare('
            INSERT INTO users (username, password, fullname, role, department, status, created_at, updated_at)
            VALUES (:username, :password, :fullname, :role, :department, "active", NOW(), NOW())
        ');
        $insertStmt->execute([
            'username' => $username,
            'password' => $randomPass,
            'fullname' => $fullName,
            'role' => $localRole,
            'department' => $calculatedDepartment
        ]);
        $localUserId = (int)$pdo->lastInsertId();
    }
    
    // Regenerate PHP Session for security and log in the user (matching auth.php)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $localUserId;
    $_SESSION['username'] = $username;
    $_SESSION['fullname'] = $fullName;
    $_SESSION['role'] = $localRole;
    $_SESSION['login_at'] = time();
    
    // Redirect to Academix landing page (index.php) after SSO login
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    echo '<!doctype html>';
    echo '<html lang="th">';
    echo '<head><meta charset="utf-8"><title>เกิดข้อผิดพลาดในการเชื่อมต่อ</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">';
    echo '<div class="card shadow-sm p-4 text-center" style="max-width: 480px; border-radius: 16px;">';
    echo '<div class="text-danger fs-1 mb-3">❌</div>';
    echo '<h4 class="text-dark mb-3">ไม่สามารถเชื่อมโยงฐานข้อมูลวิชาการได้</h4>';
    echo '<p class="text-muted mb-4">พบปัญหาทางเทคนิค: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="' . get_portal_url() . '" class="btn btn-primary w-100 py-2.5" style="border-radius: 10px;">กลับสู่หน้าแรกพอร์ทัลกลาง</a>';
    echo '</div></body></html>';
    exit;
}
