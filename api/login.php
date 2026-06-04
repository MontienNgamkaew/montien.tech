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
        $appRoles['pnp-lesson-plan'] = 'admin';
    }

    // 3.1 คำนวณตำแหน่งในโครงสร้างงาน (org_position) เพื่อส่งใน JWT Payload
    //
    // แหล่งความจริงหลัก: บอร์ดจัดภาระงานของ pnpman (ตาราง assignments) ของปีการศึกษาล่าสุด
    //   → เมื่อแต่งตั้งหัวหน้างาน/รองผอ./ผอ. ใน pnpman จะถูกใช้เป็นผู้อนุมัติของ pnp-go อัตโนมัติ
    // แหล่งสำรอง: หน้า admin ของ portal (ตาราง user_org_assignments) — ใช้เมื่อยังไม่ถูกจัดในบอร์ด
    $calculatedOrgPosition = $user['org_position'];
    $calculatedDepartment = $user['department'];

    try {
        // (ก) ลองอ่านจากบอร์ด pnpman ก่อน — เฉพาะบทบาทระดับผู้บริหาร/หัวหน้างานที่เกี่ยวกับการอนุมัติ
        //     หากมีหลายตำแหน่ง เลือกตามลำดับความสำคัญ: ผอ. > รองผอ. > หัวหน้างาน
        $stmtPnpman = $db->prepare("
            SELECT a.role, j.name AS job_name, d.name AS dept_name
            FROM assignments a
            LEFT JOIN jobs j ON j.id = a.job_id
            LEFT JOIN departments d ON d.id = j.department_id
            WHERE a.personnel_id = :user_id
              AND a.academic_year = (SELECT MAX(academic_year) FROM assignments WHERE personnel_id = :uid)
              AND a.role IN ('ผู้อำนวยการวิทยาลัย', 'รองผู้อำนวยการฝ่าย', 'หัวหน้างาน')
            ORDER BY FIELD(a.role, 'ผู้อำนวยการวิทยาลัย', 'รองผู้อำนวยการฝ่าย', 'หัวหน้างาน')
            LIMIT 1
        ");
        $stmtPnpman->execute([':user_id' => $user['id'], ':uid' => $user['id']]);
        $pnpmanAssign = $stmtPnpman->fetch();

        if ($pnpmanAssign) {
            $role = $pnpmanAssign['role'];
            $jobName = $pnpmanAssign['job_name'] ?? '';

            if ($role === 'หัวหน้างาน' && $jobName !== '') {
                // job เช่น "งานพัสดุ" → ลบคำว่า "งาน" นำหน้า แล้วประกอบเป็น "หัวหน้างานพัสดุ"
                if (mb_strpos($jobName, 'งาน') === 0) {
                    $jobName = mb_substr($jobName, 3);
                }
                $calculatedOrgPosition = 'หัวหน้างาน' . $jobName;
            } elseif ($role === 'รองผู้อำนวยการฝ่าย') {
                // ชื่อ job ของรองฯ เก็บชื่อเต็มอยู่แล้ว เช่น "รองผู้อำนวยการฝ่ายบริหารทรัพยากร"
                $calculatedOrgPosition = $jobName !== '' ? $jobName : 'รองผู้อำนวยการ';
            } elseif ($role === 'ผู้อำนวยการวิทยาลัย') {
                $calculatedOrgPosition = 'ผู้อำนวยการวิทยาลัย';
            }
            $calculatedDepartment = $pnpmanAssign['dept_name'] ?: $calculatedDepartment;
        } else {
            // (ข) ค่าสำรอง: อ่านจากหน้า admin ของ portal (user_org_assignments)
            $stmtAssign = $db->prepare("
                SELECT a.org_position, j.name AS job_name, d.name AS dept_name
                FROM user_org_assignments a
                LEFT JOIN jobs j ON j.id = a.job_id
                LEFT JOIN departments d ON d.id = a.department_id
                WHERE a.user_id = :user_id
                LIMIT 1
            ");
            $stmtAssign->execute([':user_id' => $user['id']]);
            $assignment = $stmtAssign->fetch();

            if ($assignment) {
                if ($assignment['org_position'] === 'หัวหน้างาน' && !empty($assignment['job_name'])) {
                    $jobName = $assignment['job_name'];
                    if (mb_strpos($jobName, 'งาน') === 0) {
                        $jobName = mb_substr($jobName, 3);
                    }
                    $calculatedOrgPosition = 'หัวหน้างาน' . $jobName;
                } elseif ($assignment['org_position'] === 'รองผู้อำนวยการ' && !empty($assignment['dept_name'])) {
                    $calculatedOrgPosition = 'รองผู้อำนวยการ' . $assignment['dept_name'];
                } else {
                    $calculatedOrgPosition = $assignment['org_position'];
                }
                $calculatedDepartment = $assignment['dept_name'];
            }
        }
    } catch (PDOException $exAssign) {
        // ข้ามอย่างปลอดภัยในกรณีโครงสร้างตารางไม่พร้อม
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
        'org_position' => $calculatedOrgPosition,
        'department' => $calculatedDepartment,
        'is_portal_admin' => (int)$user['is_portal_admin'],
        'roles' => $appRoles,
        'phone' => $user['phone'],
        'education' => $user['education'],
        'avatar' => $user['avatar_path'],
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
            'roles' => $appRoles,
            'phone' => $user['phone'],
            'education' => $user['education'],
            'avatar' => $user['avatar_path']
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(['error' => 'เกิดข้อผิดพลาดในการตรวจสอบข้อมูล: ' . $e->getMessage()], 500);
}
