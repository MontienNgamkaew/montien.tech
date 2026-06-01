<?php
declare(strict_types=1);

/* -------------------------------------------------------------
 * PNP GO DYNAMIC CENTRAL PORTAL USER SYNCHRONIZER
 * Safely fetches all users along with calculated organization positions
 * (using user_org_assignments, jobs, and departments) from central portal DB
 * and synchronizes them directly into PNP Go database.
 * Solves MySQL #1142 Select Denied across isolated databases on Hostinger.
 * ------------------------------------------------------------- */

require_once __DIR__ . '/bootstrap.php';

echo '<!doctype html>';
echo '<html lang="th">';
echo '<head><meta charset="utf-8"><title>ระบบซิงค์สมาชิกพอร์ทัลกลาง</title>';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">';
echo '<style>body { font-family: "Sarabun", sans-serif; background-color: #f8f9fa; }</style>';
echo '</head>';
echo '<body>';
echo '<div class="container py-5">';
echo '<div class="card shadow-sm mx-auto" style="max-width: 780px; border-radius: 16px;">';
echo '<div class="card-header bg-success text-white text-center py-4" style="border-radius: 16px 16px 0 0;">';
echo '<h4 class="mb-0">🔄 ระบบซิงค์ข้อมูลและวิเคราะห์สิทธิ์สมาชิกจากพอร์ทัลกลางสู่ PNP Go</h4>';
echo '</div>';
echo '<div class="card-body p-4">';

try {
    // 1. ดึงการเชื่อมต่อแยกแต่ละฐานข้อมูลเพื่อข้ามข้อจำกัด Permission Denied
    $dbPortal = Database::portalConnection();
    $dbGo = Database::connection();
    
    echo '<h5 class="text-secondary border-bottom pb-2 mb-3">📡 เชื่อมต่อฐานข้อมูลสำเร็จ กำลังดึงรายชื่อและตำแหน่งหน้าที่...</h5>';
    
    // 2. ดึงรายชื่อผู้ใช้จาก Portal พร้อมดึงข้อมูลการมอบหมายงานล่าสุด (Assignment)
    $stmtPortal = $dbPortal->query("
        SELECT 
            u.id AS portal_user_id,
            u.username, 
            u.password_hash, 
            u.title, 
            u.first_name, 
            u.last_name, 
            u.email, 
            u.primary_position, 
            u.org_position AS raw_org_position, 
            u.department AS raw_department,
            u.avatar_path,
            u.is_portal_admin,
            a.org_position AS assign_org_position,
            j.name AS job_name,
            d.name AS dept_name
        FROM users u
        LEFT JOIN user_org_assignments a ON a.user_id = u.id
        LEFT JOIN jobs j ON j.id = a.job_id
        LEFT JOIN departments d ON d.id = a.department_id
    ");
    $portalUsers = $stmtPortal->fetchAll();
    
    $totalFound = count($portalUsers);
    $insertedCount = 0;
    $updatedCount = 0;
    
    echo '<p class="text-muted">พบรายชื่อสมาชิกในพอร์ทัลกลางทั้งหมด <b>' . $totalFound . ' ท่าน</b></p>';
    echo '<div style="max-height: 350px; overflow-y: auto;" class="border rounded p-3 mb-4 bg-light shadow-inner">';
    echo '<table class="table table-sm table-striped mb-0" style="font-size: 13px; vertical-align: middle;">';
    echo '<thead class="table-dark"><tr><th>ชื่อ-นามสกุล</th><th>ชื่อผู้ใช้ (ID)</th><th>ตำแหน่งที่วิเคราะห์ได้</th><th>บทบาทสิทธิ์ (Role)</th><th>สถานะดำเนินการ</th></tr></thead>';
    echo '<tbody>';
    
    // 3. วนลูปซิงค์ข้อมูลสมาชิกและวิเคราะห์สิทธิ์ทีละคน
    foreach ($portalUsers as $pu) {
        $fullName = trim(($pu['title'] ?? '') . $pu['first_name'] . ' ' . $pu['last_name']);
        
        // 3.1 ประกอบร่างตำแหน่งหน้าที่ที่ถูกต้องตามความจริง (Assignment Mapping)
        $calculatedOrgPosition = $pu['raw_org_position'] ?: $pu['primary_position'];
        
        if (!empty($pu['assign_org_position'])) {
            if ($pu['assign_org_position'] === 'หัวหน้างาน' && !empty($pu['job_name'])) {
                // ลบคำว่า "งาน" ด้านหน้าแผนกออกเพื่อให้ได้ภาษาที่สวยงาม
                $jobName = $pu['job_name'];
                if (mb_strpos($jobName, 'งาน') === 0) {
                    $jobName = mb_substr($jobName, 3);
                }
                $calculatedOrgPosition = 'หัวหน้างาน' . $jobName;
            } elseif ($pu['assign_org_position'] === 'รองผู้อำนวยการ' && !empty($pu['dept_name'])) {
                $calculatedOrgPosition = 'รองผู้อำนวยการ' . $pu['dept_name'];
            } else {
                $calculatedOrgPosition = $pu['assign_org_position'];
            }
        }
        
        // 3.2 วิเคราะห์สิทธิ์อัตโนมัติตามตำแหน่งหน้าที่ (Role Mapping)
        $calculatedRole = 'user'; // สิทธิ์เริ่มต้น
        
        if ((int)$pu['is_portal_admin'] === 1) {
            $calculatedRole = 'admin';
        }
        elseif (
            ($calculatedOrgPosition === 'ผู้อำนวยการ' || strpos($calculatedOrgPosition, 'ผู้อำนวยการ') !== false) &&
            strpos($calculatedOrgPosition, 'รอง') === false
        ) {
            $calculatedRole = 'director';
        }
        elseif (
            $calculatedOrgPosition === 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร' || 
            strpos($calculatedOrgPosition, 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร') !== false ||
            (strpos($calculatedOrgPosition, 'รองผู้อำนวยการ') !== false && strpos($calculatedOrgPosition, 'บริหารทรัพยากร') !== false)
        ) {
            $calculatedRole = 'deputy_director';
        }
        elseif (
            $calculatedOrgPosition === 'หัวหน้างานพัสดุ' || 
            strpos($calculatedOrgPosition, 'หัวหน้างานพัสดุ') !== false
        ) {
            $calculatedRole = 'supply_head';
        }
        
        // 3.3 บันทึกหรืออัปเดตสิทธิ์ในฐานข้อมูล PNP Go
        $checkStmt = $dbGo->prepare("SELECT id, role, position_title FROM users WHERE username = :username LIMIT 1");
        $checkStmt->execute(['username' => $pu['username']]);
        $localUser = $checkStmt->fetch();
        
        $roleLabel = [
            'user' => '<span class="badge bg-secondary">ครูทั่วไป</span>',
            'supply_head' => '<span class="badge bg-warning text-dark">หัวหน้าพัสดุ</span>',
            'deputy_director' => '<span class="badge bg-primary">รองผู้อำนวยการ</span>',
            'director' => '<span class="badge bg-success">ผู้อำนวยการ</span>',
            'admin' => '<span class="badge bg-danger">ผู้ดูแลระบบ</span>',
        ][$calculatedRole] ?? $calculatedRole;
        
        if (!$localUser) {
            // กรณีไม่มีในระบบ -> ทำการลงทะเบียนล่วงหน้าพร้อมสิทธิ์ที่สมบูรณ์
            $insertStmt = $dbGo->prepare("
                INSERT INTO users (full_name, username, password_hash, role, position_title, avatar_path, is_active, last_login_at)
                VALUES (:full_name, :username, :password_hash, :role, :position_title, :avatar_path, 1, NULL)
            ");
            $insertStmt->execute([
                'full_name' => $fullName,
                'username' => $pu['username'],
                'password_hash' => $pu['password_hash'],
                'role' => $calculatedRole,
                'position_title' => $calculatedOrgPosition,
                'avatar_path' => $pu['avatar_path'] ?: null
            ]);
            $insertedCount++;
            echo '<tr>';
            echo '<td><b>' . htmlspecialchars($fullName) . '</b></td>';
            echo '<td>' . htmlspecialchars($pu['username']) . '</td>';
            echo '<td>' . htmlspecialchars($calculatedOrgPosition) . '</td>';
            echo '<td>' . $roleLabel . '</td>';
            echo '<td class="text-success fw-bold">➕ เพิ่มใหม่</td>';
            echo '</tr>';
        } else {
            // กรณีมีอยู่แล้ว -> ทำการอัปเดตสิทธิ์และตำแหน่งล่าสุดให้สอดคล้องกันอัตโนมัติ
            $updateStmt = $dbGo->prepare("
                UPDATE users 
                SET full_name = :full_name,
                    role = :role,
                    position_title = :position_title,
                    avatar_path = :avatar_path,
                    is_active = 1
                WHERE id = :id
            ");
            $updateStmt->execute([
                'full_name' => $fullName,
                'role' => $calculatedRole,
                'position_title' => $calculatedOrgPosition,
                'avatar_path' => $pu['avatar_path'] ?: null,
                'id' => $localUser['id']
            ]);
            $updatedCount++;
            echo '<tr>';
            echo '<td><b>' . htmlspecialchars($fullName) . '</b></td>';
            echo '<td>' . htmlspecialchars($pu['username']) . '</td>';
            echo '<td>' . htmlspecialchars($calculatedOrgPosition) . '</td>';
            echo '<td>' . $roleLabel . '</td>';
            echo '<td class="text-primary">🔄 อัปเดต</td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    echo '<div class="alert alert-success py-3 mb-4" role="alert">';
    echo '<h5 class="alert-heading mb-2">🎉 ดำเนินการซิงค์และวิเคราะห์ข้อมูลสมาชิกเสร็จสมบูรณ์!</h5>';
    echo '<ul class="mb-0 small">';
    echo '<li>ลงทะเบียนสมาชิกรายใหม่พร้อมจัดสิทธิ์ล่วงหน้า: <b>' . $insertedCount . ' ท่าน</b></li>';
    echo '<li>ปรับปรุงประวัติและซ่อมแซมสิทธิ์สมาชิกเดิมให้ถูกต้อง: <b>' . $updatedCount . ' ท่าน</b></li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<div class="text-center">';
    echo '<p class="text-danger small mb-3">⚠️ เพื่อความปลอดภัยสูงสุด กรุณาลบไฟล์ <b>sync_portal_users.php</b> นี้ออกจากโฮสต์ของคุณหลังจากใช้งานเสร็จสิ้นแล้ว</p>';
    echo '<a href="./dashboard" class="btn btn-success px-4 py-2" style="border-radius: 8px;">เข้าสู่หน้าแดชบอร์ดหลัก</a>';
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger" role="alert">';
    echo '<h5>❌ เกิดข้อผิดพลาดทางเทคนิคในการซิงค์ข้อมูล</h5>';
    echo '<p class="mb-0">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';
echo '</body>';
echo '</html>';
