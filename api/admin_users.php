<?php
/* -------------------------------------------------------------
 * ADMIN USER & ROLE MANAGEMENT API (FULL CRUD)
 * Allows Portal Admins to:
 * - View all users with roles, dual positions, and departments
 * - Create new users with standard default roles
 * - Edit existing user details, positions, and departments
 * - Delete users securely (with protection against self-deletion and locking core admin)
 * Secured via Administrator JWT verification
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';

// 1. ตรวจสอบการยืนยันตัวตนระดับผู้ดูแลระบบ (Admin Authorization)
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
    sendResponse(['error' => 'จำเป็นต้องลงชื่อเข้าใช้งานระดับผู้ดูแลระบบ'], 401);
}

// ถอดรหัส Token และตรวจสิทธิ์การเป็นแอดมินพอร์ทัลหลัก
$adminPayload = JWT::decode($token, JWT_SECRET_KEY);
if (!$adminPayload || (int)$adminPayload['is_portal_admin'] !== 1) {
    sendResponse(['error' => 'ไม่มีสิทธิ์การใช้งาน: เฉพาะผู้ดูแลระบบพอร์ทัลกลางเท่านั้น'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------------
// GET: ดึงข้อมูลสมาชิกทุกคนพร้อมตารางสิทธิ์แยกตามแอปพลิเคชัน
// -------------------------------------------------------------
if ($method === 'GET') {
    try {
        // ดึงรายชื่อผู้ใช้ทั้งหมดพร้อมตำแหน่งทั้ง 2 และฝ่าย/แผนก
        $stmtUsers = $db->query("SELECT id, username, title, first_name, last_name, email, primary_position, org_position, department, job, status, is_portal_admin FROM users ORDER BY id ASC");
        $users = $stmtUsers->fetchAll();

        // แนบสิทธิ์รายระบบเข้าไปกับผู้ใช้แต่ละคน
        $stmtRoles = $db->prepare("SELECT app_id, role FROM app_roles WHERE user_id = :user_id");
        
        foreach ($users as &$u) {
            $stmtRoles->execute([':user_id' => $u['id']]);
            $rolesRows = $stmtRoles->fetchAll();
            
            // ตั้งค่าเริ่มต้นสิทธิ์แต่ละระบบเป็น 'none'
            $u['roles'] = [
                'pnp-go' => 'none',
                'pnp-academic' => 'none',
                'pnp-man' => 'none'
            ];
            
            foreach ($rolesRows as $row) {
                // สำหรับสคีมาแบบเดิม หากเก็บในฐานข้อมูลแอปย่อยเป็นชื่อคีย์ที่เหลื่อมล้ำ
                $appKey = $row['app_id'];
                // ดักจับการแปลงชื่อระบบหากจำเป็น
                if ($appKey === 'pnp-academic' || $appKey === 'pnp-academic') {
                    $u['roles']['pnp-academic'] = $row['role'];
                } else {
                    $u['roles'][$appKey] = $row['role'];
                }
            }
        }

        sendResponse(['users' => $users]);

    } catch (PDOException $e) {
        sendResponse(['error' => 'ไม่สามารถดึงข้อมูลสมาชิกได้: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// POST: การดำเนินการ CRUD (เพิ่ม ลบ อัปเดต และสลับสิทธิ์)
// -------------------------------------------------------------
if ($method === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    $action = isset($input['action']) ? trim($input['action']) : '';

    // -------------------------------------------------------------
    // ACTION: CREATE_USER (เพิ่มผู้ใช้ใหม่)
    // -------------------------------------------------------------
    if ($action === 'create_user') {
        $username = isset($input['username']) ? trim($input['username']) : '';
        $password = isset($input['password']) ? trim($input['password']) : '';
        $title = isset($input['title']) ? trim($input['title']) : '';
        $firstName = isset($input['first_name']) ? trim($input['first_name']) : '';
        $lastName = isset($input['last_name']) ? trim($input['last_name']) : '';
        $email = isset($input['email']) ? trim($input['email']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';
        $primaryPos = isset($input['primary_position']) ? trim($input['primary_position']) : '';
        $orgPos = isset($input['org_position']) ? trim($input['org_position']) : '';
        $dept = isset($input['department']) ? trim($input['department']) : '';
        $job = isset($input['job']) ? trim($input['job']) : '';
        $isPortalAdmin = isset($input['is_portal_admin']) ? (int)$input['is_portal_admin'] : 0;

        if (empty($username) || empty($firstName) || empty($lastName)) {
            sendResponse(['error' => 'กรุณากรอกชื่อผู้ใช้ (เลขบัตรประชาชน) และชื่อ-นามสกุลจริง ให้ครบถ้วน'], 400);
        }

        // ตั้งค่าความปลอดภัยเริ่มต้นแบบง่าย
        if (empty($password)) {
            $password = $username;
        }

        if (empty($email)) {
            $email = $username . '@pnp.ac.th';
        }

        if (empty($primaryPos)) {
            $primaryPos = 'เจ้าหน้าที่';
        }

        if ($primaryPos !== 'ผู้ดูแลระบบ') {
            if (!preg_match('/^\d{13}$/', $username)) {
                sendResponse(['error' => 'เลขประจำตัวประชาชน (Username) ต้องเป็นตัวเลข 13 หลักเท่านั้น'], 400);
            }
            if (!preg_match('/^\d{13}$/', $password)) {
                sendResponse(['error' => 'รหัสผ่านเริ่มต้นต้องเป็นเลขประจำตัวประชาชน 13 หลักเท่านั้น'], 400);
            }
        }

        try {
            // ตรวจสอบชื่อผู้ใช้งานซ้ำ
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmtCheck->execute([':username' => $username]);
            if ($stmtCheck->fetchColumn() > 0) {
                sendResponse(['error' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาใช้ชื่ออื่น'], 400);
            }

            // ตรวจสอบอีเมลซ้ำ
            $stmtCheckEmail = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmtCheckEmail->execute([':email' => $email]);
            if ($stmtCheckEmail->fetchColumn() > 0) {
                sendResponse(['error' => 'อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น'], 400);
            }

            $db->beginTransaction();

            // 1. บันทึกลงตาราง users หลัก
            $stmtInsert = $db->prepare("
                INSERT INTO users (username, password_hash, title, first_name, last_name, email, primary_position, org_position, department, job, is_portal_admin)
                VALUES (:username, :password_hash, :title, :first_name, :last_name, :email, :primary_position, :org_position, :department, :job, :is_portal_admin)
            ");
            $stmtInsert->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ':title' => $title,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':primary_position' => $primaryPos,
                ':org_position' => $orgPos,
                ':department' => $dept,
                ':job' => $job,
                ':is_portal_admin' => $isPortalAdmin
            ]);

            $newUserId = $db->lastInsertId();

            // 2. แต่งตั้งตำแหน่งในโครงสร้างงานตาราง user_org_assignments
            if (!empty($orgPos) && $primaryPos !== 'ผู้อำนวยการ') {
                // ค้นหา ID ของฝ่ายงาน
                $stmtDept = $db->prepare("SELECT id FROM departments WHERE name = :name");
                $stmtDept->execute([':name' => $dept]);
                $deptId = $stmtDept->fetchColumn();

                $jobId = null;
                if (!empty($job)) {
                    // ค้นหา ID ของงานย่อย
                    $stmtJob = $db->prepare("SELECT id FROM jobs WHERE name = :name");
                    $stmtJob->execute([':name' => $job]);
                    $jobId = $stmtJob->fetchColumn();
                }

                // ดักจับขัดแย้ง: เช็คว่างาน/แผนกย่อยนี้มีหัวหน้างานแล้วหรือไม่
                if ($orgPos === 'หัวหน้างาน' && $jobId) {
                    $stmtCheckHead = $db->prepare("SELECT COUNT(*) FROM user_org_assignments WHERE org_position = 'หัวหน้างาน' AND job_id = :job_id");
                    $stmtCheckHead->execute([':job_id' => $jobId]);
                    if ($stmtCheckHead->fetchColumn() > 0) {
                        throw new PDOException("แผนกวิชาหรือสายงานย่อยนี้มีตำแหน่งหัวหน้างานอยู่แล้วในระบบ (จำกัด 1 แผนกวิชาต่อหัวหน้างาน 1 คน)");
                    }
                }

                $stmtAssign = $db->prepare("
                    INSERT INTO user_org_assignments (user_id, org_position, department_id, job_id)
                    VALUES (:user_id, :org_position, :department_id, :job_id)
                ");
                $stmtAssign->execute([
                    ':user_id' => $newUserId,
                    ':org_position' => $orgPos,
                    ':department_id' => $deptId ? $deptId : null,
                    ':job_id' => $jobId ? $jobId : null
                ]);
            }

            // 3. ตั้งค่าสิทธิ์แอปทั้ง 3 เป็น 'none'
            $apps = ['pnp-go', 'pnp-academic', 'pnp-man'];
            $stmtRoleInit = $db->prepare("INSERT INTO app_roles (user_id, app_id, role) VALUES (:user_id, :app_id, 'none')");
            foreach ($apps as $app) {
                $stmtRoleInit->execute([
                    ':user_id' => $newUserId,
                    ':app_id' => $app
                ]);
            }

            $db->commit();

            sendResponse([
                'success' => true,
                'message' => "สร้างบัญชีผู้ใช้งาน {$username} เรียบร้อยแล้ว"
            ]);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            sendResponse(['error' => 'ล้มเหลวในการบันทึกบัญชี: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------
    // ACTION: UPDATE_USER_DETAILS (แก้ไขข้อมูลผู้ใช้)
    // -------------------------------------------------------------
    elseif ($action === 'update_user_details') {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
        $title = isset($input['title']) ? trim($input['title']) : '';
        $firstName = isset($input['first_name']) ? trim($input['first_name']) : '';
        $lastName = isset($input['last_name']) ? trim($input['last_name']) : '';
        $email = isset($input['email']) ? trim($input['email']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';
        $isPortalAdmin = isset($input['is_portal_admin']) ? (int)$input['is_portal_admin'] : 0;

        if ($userId <= 0 || empty($firstName) || empty($lastName)) {
            sendResponse(['error' => 'กรุณากรอกข้อมูลส่วนตัวให้ครบถ้วน'], 400);
        }

        // ป้องกันแอดมินลบสิทธิ์แอดมินตัวเอง
        if ($userId === (int)$adminPayload['user_id'] && $isPortalAdmin === 0) {
            sendResponse(['error' => 'ไม่สามารถถอนสิทธิ์แอดมินพอร์ทัลกลางของตนเองได้'], 400);
        }

        if (empty($email)) {
            $email = $userId . '@pnp.ac.th';
        }

        try {
            // ตรวจสอบอีเมลซ้ำกับคนอื่น
            $stmtCheckEmail = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
            $stmtCheckEmail->execute([':email' => $email, ':id' => $userId]);
            if ($stmtCheckEmail->fetchColumn() > 0) {
                sendResponse(['error' => 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว'], 400);
            }

            // อัปเดตตาราง users หลัก (เฉพาะข้อมูลส่วนบุคคลพื้นฐาน)
            $stmtUpdate = $db->prepare("
                UPDATE users 
                SET title = :title, first_name = :first_name, last_name = :last_name, email = :email, 
                    phone = :phone, is_portal_admin = :is_portal_admin
                WHERE id = :id
            ");
            
            $stmtUpdate->execute([
                ':title' => $title,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':phone' => $phone,
                ':is_portal_admin' => $isPortalAdmin,
                ':id' => $userId
            ]);

            sendResponse([
                'success' => true,
                'message' => "อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว"
            ]);

        } catch (PDOException $e) {
            sendResponse(['error' => 'ล้มเหลวในการอัปเดตข้อมูล: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------
    // ACTION: UPDATE_USER_POSITIONS (จัดการตำแหน่งหน้าที่และความรับผิดชอบ)
    // -------------------------------------------------------------
    elseif ($action === 'update_user_positions') {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
        $primaryPos = isset($input['primary_position']) ? trim($input['primary_position']) : '';
        $orgPos = isset($input['org_position']) ? trim($input['org_position']) : '';
        $dept = isset($input['department']) ? trim($input['department']) : '';
        $job = isset($input['job']) ? trim($input['job']) : '';
        $roles = isset($input['roles']) ? $input['roles'] : [];

        if ($userId <= 0 || empty($primaryPos)) {
            sendResponse(['error' => 'กรุณากรอกตำแหน่งงานหลักให้ครบถ้วน'], 400);
        }

        try {
            $db->beginTransaction();

            // 1. อัปเดตตาราง users หลัก
            $stmtUpdate = $db->prepare("
                UPDATE users 
                SET primary_position = :primary_position, org_position = :org_position, 
                    department = :department, job = :job
                WHERE id = :id
            ");
            
            $stmtUpdate->execute([
                ':primary_position' => $primaryPos,
                ':org_position' => $orgPos,
                ':department' => $dept,
                ':job' => $job,
                ':id' => $userId
            ]);

            // 2. ปรับปรุงข้อมูลตำแหน่งในโครงสร้างงานตาราง user_org_assignments
            $stmtClearAssign = $db->prepare("DELETE FROM user_org_assignments WHERE user_id = :user_id");
            $stmtClearAssign->execute([':user_id' => $userId]);

            if (!empty($orgPos) && $primaryPos !== 'ผู้อำนวยการ') {
                // ค้นหา ID ของฝ่ายงาน
                $stmtDept = $db->prepare("SELECT id FROM departments WHERE name = :name");
                $stmtDept->execute([':name' => $dept]);
                $deptId = $stmtDept->fetchColumn();

                $jobId = null;
                if (!empty($job)) {
                    // ค้นหา ID ของงานย่อย
                    $stmtJob = $db->prepare("SELECT id FROM jobs WHERE name = :name");
                    $stmtJob->execute([':name' => $job]);
                    $jobId = $stmtJob->fetchColumn();
                }

                // ดักจับขัดแย้ง: เช็คว่างาน/แผนกย่อยนี้มีหัวหน้างานแล้วหรือไม่ (ที่ไม่ใช่ของตัวเอง)
                if ($orgPos === 'หัวหน้างาน' && $jobId) {
                    $stmtCheckHead = $db->prepare("SELECT COUNT(*) FROM user_org_assignments WHERE org_position = 'หัวหน้างาน' AND job_id = :job_id AND user_id != :user_id");
                    $stmtCheckHead->execute([':job_id' => $jobId, ':user_id' => $userId]);
                    if ($stmtCheckHead->fetchColumn() > 0) {
                        throw new PDOException("แผนกวิชาหรือสายงานย่อยนี้มีตำแหน่งหัวหน้างานอยู่แล้วในระบบ (จำกัด 1 แผนกวิชาต่อหัวหน้างาน 1 คน)");
                    }
                }

                $stmtAssign = $db->prepare("
                    INSERT INTO user_org_assignments (user_id, org_position, department_id, job_id)
                    VALUES (:user_id, :org_position, :department_id, :job_id)
                ");
                $stmtAssign->execute([
                    ':user_id' => $userId,
                    ':org_position' => $orgPos,
                    ':department_id' => $deptId ? $deptId : null,
                    ':job_id' => $jobId ? $jobId : null
                ]);
            }

            // 3. ปรับปรุงบทบาทสิทธิ์แอปพลิเคชันย่อย (App Roles)
            if (!empty($roles)) {
                $stmtUpdateRole = $db->prepare("
                    INSERT INTO app_roles (user_id, app_id, role) 
                    VALUES (:user_id, :app_id, :role)
                    ON DUPLICATE KEY UPDATE role = :role
                ");
                foreach ($roles as $appId => $role) {
                    $stmtUpdateRole->execute([
                        ':user_id' => $userId,
                        ':app_id' => $appId,
                        ':role' => $role
                    ]);
                }
            }

            $db->commit();

            sendResponse([
                'success' => true,
                'message' => "บันทึกข้อมูลตำแหน่งและสิทธิ์เรียบร้อยแล้ว"
            ]);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            sendResponse(['error' => 'ล้มเหลวในการบันทึกตำแหน่งหน้าที่: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------
    // ACTION: DELETE_USER (ลบผู้ใช้หลักและสิทธิ์ CASCADE)
    // -------------------------------------------------------------
    elseif ($action === 'delete_user') {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

        if ($userId <= 0) {
            sendResponse(['error' => 'ข้อมูลรหัสผู้ใช้ไม่ถูกต้อง'], 400);
        }

        // ป้องกันแอดมินลบตัวเอง
        if ($userId === (int)$adminPayload['user_id']) {
            sendResponse(['error' => 'ไม่สามารถลบบัญชีผู้ใช้งานที่ตนเองกำลังล็อกอินอยู่ได้'], 400);
        }

        try {
            // ตรวจหาบัญชีที่จะลบ
            $stmtTarget = $db->prepare("SELECT username FROM users WHERE id = :id");
            $stmtTarget->execute([':id' => $userId]);
            $username = $stmtTarget->fetchColumn();

            if (!$username) {
                sendResponse(['error' => 'ไม่พบผู้ใช้ที่ต้องการลบในระบบ'], 404);
            }

            // บัญชี `admin` หลัก (id = 1) ล็อกไว้ห้ามลบเด็ดขาดเพื่อความปลอดภัยขั้นสูงสุด
            if ($userId === 1 || $username === 'admin') {
                sendResponse(['error' => 'บัญชีผู้สร้างระบบหลัก (admin) ได้รับการป้องกันสูงสุด ไม่สามารถลบออกจากระบบได้'], 400);
            }

            // ลบจากตาราง users (เนื่องจาก SQLite ตั้ง CASCADE สิทธิ์ย่อยในตาราง app_roles จะถูกลบออกอัตโนมัติ)
            $stmtDelete = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmtDelete->execute([':id' => $userId]);

            sendResponse([
                'success' => true,
                'message' => "ลบบัญชีผู้ใช้งาน {$username} และสิทธิ์ในแอปพลิเคชันทั้งหมดสำเร็จเรียบร้อยแล้ว"
            ]);

        } catch (PDOException $e) {
            sendResponse(['error' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------
    // ACTION: UPDATE_APP_ROLE (อัปเดตสิทธิ์แอปย่อยของสมาชิก)
    // -------------------------------------------------------------
    elseif ($action === 'update_app_role') {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
        $appId = isset($input['app_id']) ? trim($input['app_id']) : '';
        $role = isset($input['role']) ? trim($input['role']) : '';

        $validApps = ['pnp-go', 'pnp-academic', 'pnp-man'];
        $validRoles = ['admin', 'user', 'driver', 'teacher', 'none'];

        if ($userId <= 0 || !in_array($appId, $validApps) || !in_array($role, $validRoles)) {
            sendResponse(['error' => 'ข้อมูลสิทธิ์หรือระบบย่อยไม่ถูกต้อง'], 400);
        }

        try {
            $stmtUpdate = $db->prepare("
                INSERT INTO app_roles (user_id, app_id, role) 
                VALUES (:user_id, :app_id, :role)
                ON DUPLICATE KEY UPDATE role = :role
            ");
            $stmtUpdate->execute([
                ':user_id' => $userId,
                ':app_id' => $appId,
                ':role' => $role
            ]);

            sendResponse([
                'success' => true,
                'message' => "อัปเดตบทบาทสำเร็จ"
            ]);

        } catch (PDOException $e) {
            sendResponse(['error' => 'เกิดข้อผิดพลาดในการปรับเปลี่ยนบทบาท: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------
    // ACTION: TOGGLE_PORTAL_ADMIN (สลับสิทธิ์แอดมินพอร์ทัลกลาง)
    // -------------------------------------------------------------
    elseif ($action === 'toggle_portal_admin') {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

        if ($userId <= 0) {
            sendResponse(['error' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 400);
        }

        if ($userId === (int)$adminPayload['user_id']) {
            sendResponse(['error' => 'ไม่สามารถปลดสิทธิ์ตนเองได้'], 400);
        }

        try {
            $stmtStatus = $db->prepare("SELECT is_portal_admin FROM users WHERE id = :id");
            $stmtStatus->execute([':id' => $userId]);
            $currentStatus = (int)$stmtStatus->fetchColumn();

            $newStatus = $currentStatus === 1 ? 0 : 1;

            $stmtToggle = $db->prepare("UPDATE users SET is_portal_admin = :status WHERE id = :id");
            $stmtToggle->execute([
                ':status' => $newStatus,
                ':id' => $userId
            ]);

            sendResponse([
                'success' => true,
                'message' => "อัปเดตสิทธิ์ผู้ดูแลเรียบร้อย"
            ]);

        } catch (PDOException $e) {
            sendResponse(['error' => 'เกิดข้อผิดพลาดในการเปลี่ยนระดับสิทธิ์: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------
    // ACTION: TOGGLE_USER_STATUS (สลับสถานะการใช้งานสมาชิก)
    // -------------------------------------------------------------
    elseif ($action === 'toggle_user_status') {
        $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

        if ($userId <= 0) {
            sendResponse(['error' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], 400);
        }

        if ($userId === (int)$adminPayload['user_id']) {
            sendResponse(['error' => 'ไม่สามารถระงับการใช้งานบัญชีตนเองได้'], 400);
        }

        try {
            $stmtStatus = $db->prepare("SELECT status FROM users WHERE id = :id");
            $stmtStatus->execute([':id' => $userId]);
            $currentStatus = $stmtStatus->fetchColumn();

            $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';

            $stmtToggle = $db->prepare("UPDATE users SET status = :status WHERE id = :id");
            $stmtToggle->execute([
                ':status' => $newStatus,
                ':id' => $userId
            ]);

            sendResponse([
                'success' => true,
                'message' => "อัปเดตสถานะการใช้งานสมาชิกเรียบร้อยแล้ว"
            ]);

        } catch (PDOException $e) {
            sendResponse(['error' => 'เกิดข้อผิดพลาดในการปรับเปลี่ยนสถานะ: ' . $e->getMessage()], 500);
        }
    }

    else {
        sendResponse(['error' => 'คำขอปฏิบัติงานไม่ถูกต้อง'], 400);
    }
}

sendResponse(['error' => 'Method Not Allowed'], 405);
