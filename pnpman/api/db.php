<?php
require_once __DIR__ . '/../../api/portal_access.php';
if (pnp_portal_v2()) {
    $page=basename($_SERVER['SCRIPT_NAME']??'');$post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    $right=match($page){'update_settings.php','manage_structure.php'=>'manage_settings','import_csv.php','assign_job.php'=>'create','manage_personnel.php'=>'manage_users','csv_template.php'=>'export',default=>$post?'edit':'view'};
    $context=pnp_portal_guard('pnp-man',$right,true);
    if($page==='change_password.php')pnp_portal_fail(new PortalProblem(410,'เปลี่ยนรหัสผ่านที่ Portal กลางเท่านั้น'),true);
    if($post){
        try{PortalV2::requireCsrf(false);}catch(PortalProblem $e){pnp_portal_fail($e,true);}
        $body=str_contains($_SERVER['CONTENT_TYPE']??'','application/json')?(json_decode(file_get_contents('php://input'),true)?:[]):$_POST;
        if(in_array($page,['clear_assignments.php','copy_year.php','remove_assignment.php'],true)||($page==='manage_personnel.php'&&in_array($body['action']??'',['add','delete'],true))||($page==='manage_structure.php'&&in_array($body['action']??'',['delete_department','delete_job'],true)))pnp_portal_fail(new PortalProblem(403,'รุ่นใหม่ยังไม่เปิดการลบหรือเขียนทับชุดข้อมูลเดิม ใช้ Portal กลางจัดการบัญชี'),true);
        if($context['access']['scope']!=='all'&&in_array($page,['update_settings.php','manage_structure.php','import_csv.php','reorder_assignments.php'],true))pnp_portal_fail(new PortalProblem(403,'การตั้งค่าโครงสร้างและนำเข้าต้องมีขอบเขตทั้งองค์กร'),true);
        $target=(int)($body['personnel_id']??($page==='manage_personnel.php'?($body['id']??0):0));
        if($target){$u=PortalV2::query('SELECT username,is_portal_admin FROM users WHERE id=?',[$target])->fetch();if(!$u)pnp_portal_fail(new PortalProblem(404,'ไม่พบสมาชิก'),true);if((int)$u['is_portal_admin']===1&&(int)$context['user']['is_portal_admin']!==1)pnp_portal_fail(new PortalProblem(403,'แก้ไขบัญชีผู้ดูแลสูงสุดไม่ได้'),true);pnp_portal_scope($context,$u['username'],true);}
    }
    $GLOBALS['pnp_man_context']=$context;
}
// CORS headers are handled by api/config.php

// นำเข้าการเชื่อมต่อฐานข้อมูลหลักจากพอร์ทัลกลางเพื่อหลีกเลี่ยงรหัสผ่านไม่ตรงกัน

// นำเข้าการเชื่อมต่อฐานข้อมูลหลักจากพอร์ทัลกลางเพื่อหลีกเลี่ยงรหัสผ่านไม่ตรงกัน
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/database.php';
$pdo = $db;

// Auto-create college_settings and assignments tables for PNP Man if they do not exist (Self-healing DB)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS college_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        college_name VARCHAR(255) NOT NULL DEFAULT 'วิทยาลัยการอาชีพพนมไพร',
        logo_path VARCHAR(255) NULL,
        theme_preset VARCHAR(50) NOT NULL DEFAULT 'rose'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM college_settings WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT IGNORE INTO college_settings (id, college_name, logo_path, theme_preset) VALUES (1, 'วิทยาลัยการอาชีพพนมไพร', '', 'rose')");
    }
} catch (PDOException $e) {
    // Silently continue or log if needed
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        personnel_id INT NOT NULL,
        job_id INT NOT NULL,
        role VARCHAR(100) NOT NULL,
        academic_year INT NOT NULL DEFAULT 2569,
        sort_order INT NOT NULL DEFAULT 0,
        comment VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (personnel_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignment_v2 (personnel_id, job_id, role, academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Silently continue or log if needed
}

// Make sure jobs.department_id is nullable (to support Director position)
try {
    $pdo->exec("ALTER TABLE jobs MODIFY department_id INT NULL");
} catch (PDOException $e) {
    // Silently continue or log if needed
}

// Auto-insert Director/Deputy Director positions into jobs table if missing (Self-healing positions)
try {
    $stmt = $pdo->prepare("
        INSERT INTO jobs (id, department_id, name, sort_order) VALUES
        (900, NULL, 'ผู้อำนวยการวิทยาลัย', 0),
        (901, 1, 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร', 0),
        (902, 2, 'รองผู้อำนวยการฝ่ายยุทธศาสตร์และแผนงาน', 0),
        (903, 3, 'รองผู้อำนวยการฝ่ายกิจการนักเรียน นักศึกษา', 0),
        (904, 4, 'รองผู้อำนวยการฝ่ายวิชาการ', 0)
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name), 
            department_id = VALUES(department_id)
    ");
    $stmt->execute();
} catch (PDOException $e) {
    // Silently continue or log if needed
}

function parseFullName($fullName) {
    $fullName = trim($fullName);
    $titles = ['นาย', 'นางสาว', 'นาง', 'ดร.', 'ศาสตราจารย์', 'รองศาสตราจารย์', 'ผู้ช่วยศาสตราจารย์', 'ศ.', 'รศ.', 'ผศ.', 'ว่าที่ร้อยตรี', 'ว่าที่ ร.ต.', 'จ่าสิบเอก'];
    $title = '';
    $rest = $fullName;
    
    foreach ($titles as $t) {
        if (strpos($fullName, $t) === 0) {
            $title = $t;
            $rest = trim(substr($fullName, strlen($t)));
            break;
        }
    }
    
    $parts = preg_split('/\s+/', $rest);
    $first_name = $parts[0] ?? '';
    $last_name = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
    
    return [
        'title' => $title,
        'first_name' => $first_name,
        'last_name' => $last_name
    ];
}

function isCurrentAdmin($pdo) {
    if(pnp_portal_v2())return ($GLOBALS['pnp_man_context']['access']['role']??'none')==='admin' && (bool)array_intersect(['create','edit','manage_users','manage_settings'],$GLOBALS['pnp_man_context']['access']['rights']??[]);
    // ใช้ตัวช่วย SSO กลางในการตรวจ JWT (ออกโดย Portal กลาง)
    require_once __DIR__ . '/../../api/sso_auth.php';

    $payload = pnp_auth_payload();
    if ($payload === null || !isset($payload['user_id'])) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT u.id
        FROM users u
        LEFT JOIN app_roles r ON u.id = r.user_id AND r.app_id = 'pnp-man'
        WHERE u.id = ?
          AND u.status = 'active'
          AND (u.is_portal_admin = 1 OR r.role = 'admin')
    ");
    $stmt->execute([$payload['user_id']]);
    return (bool)$stmt->fetch();
}
?>
