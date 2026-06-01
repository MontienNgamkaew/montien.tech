<?php
declare(strict_types=1);

/* -------------------------------------------------------------
 * PNP GO DATABASE AUTO-REPAIR & ROLE UPDATE SCRIPT
 * Safely updates user roles for central portal synced members
 * Run this via browser: /pnp-go/update_db_roles.php
 * ------------------------------------------------------------- */

require_once __DIR__ . '/bootstrap.php';

echo '<!doctype html>';
echo '<html lang="th">';
echo '<head><meta charset="utf-8"><title>ปรับปรุงฐานข้อมูล PNP Go</title>';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">';
echo '<style>body { font-family: "Sarabun", sans-serif; background-color: #f8f9fa; }</style>';
echo '</head>';
echo '<body>';
echo '<div class="container py-5">';
echo '<div class="card shadow-sm mx-auto" style="max-width: 680px; border-radius: 16px;">';
echo '<div class="card-header bg-primary text-white text-center py-4" style="border-radius: 16px 16px 0 0;">';
echo '<h4 class="mb-0">⚙️ ระบบปรับปรุงสิทธิ์และฐานข้อมูล PNP Go อัตโนมัติ</h4>';
echo '</div>';
echo '<div class="card-body p-4">';

try {
    $db = Database::connection();
    
    echo '<h5 class="text-secondary border-bottom pb-2 mb-3">🛠️ ดำเนินการอัปเดตข้อมูลผู้ใช้งาน</h5>';
    echo '<ul class="list-group mb-4">';

    // 1. อัปเดตสิทธิ์คุณครู ปฏิพาน สีนาบุญ (1470800181781)
    $stmt1 = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt1->execute(['username' => '1470800181781']);
    $userPatiphan = $stmt1->fetch();
    
    if ($userPatiphan) {
        $update1 = $db->prepare("UPDATE users SET role = 'user', position_title = 'ข้าราชการครู' WHERE id = :id");
        $update1->execute(['id' => $userPatiphan['id']]);
        echo '<li class="list-group-item list-group-item-success">✅ อัปเดตสิทธิ์คุณครู <b>ปฏิพาน สีนาบุญ</b> (ID: ' . $userPatiphan['id'] . ') เป็น <b>user</b> (ผู้ใช้ทั่วไป) สำเร็จ</li>';
    } else {
        echo '<li class="list-group-item list-group-item-warning">⚠️ ไม่พบข้อมูลบัญชีคุณครู ปฏิพาน สีนาบุญ (1470800181781) ในตารางของระบบขอใช้รถ (จะซิงค์เมื่อล็อกอินผ่าน SSO ครั้งแรก)</li>';
    }

    // 2. อัปเดตสิทธิ์และข้อมูลรองผู้อำนวยการ มณเฑียร งามแก้ว (1350100238268)
    $stmt2 = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt2->execute(['username' => '1350100238268']);
    $userMontien = $stmt2->fetch();
    
    if ($userMontien) {
        $update2 = $db->prepare("UPDATE users SET role = 'deputy_director', position_title = 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร' WHERE id = :id");
        $update2->execute(['id' => $userMontien['id']]);
        echo '<li class="list-group-item list-group-item-success">✅ อัปเดตสิทธิ์รองผู้อำนวยการ <b>มณเฑียร งามแก้ว</b> (ID: ' . $userMontien['id'] . ') เป็น <b>deputy_director</b> (ผู้อนุมัติระดับ 2) สำเร็จ</li>';
    } else {
        echo '<li class="list-group-item list-group-item-warning">⚠️ ไม่พบข้อมูลบัญชีรองผู้อำนวยการ มณเฑียร งามแก้ว (1350100238268) ในตารางของระบบขอใช้รถ (จะซิงค์เมื่อล็อกอินผ่าน SSO ครั้งแรก)</li>';
    }
    
    // 3. จัดการความปลอดภัยข้อมูลของบัญชีอื่นๆ ที่อาจจะมีค่า role ว่างเปล่า
    $stmtEmpty = $db->prepare("UPDATE users SET role = 'user' WHERE role IS NULL OR role = '' OR role = 'none'");
    $stmtEmpty->execute();
    $affected = $stmtEmpty->rowCount();
    if ($affected > 0) {
        echo '<li class="list-group-item list-group-item-info">ℹ️ ทำการปรับปรุงบทบาทผู้ใช้งานที่มีค่าว่างหรือ NULL จำนวน <b>' . $affected . ' รายการ</b> ให้เป็น <b>user</b> เพื่อความปลอดภัยสำเร็จ</li>';
    }

    echo '</ul>';

    echo '<div class="alert alert-success text-center py-3" role="alert">';
    echo '<h5 class="alert-heading mb-1">🎉 การปรับปรุงฐานข้อมูลเสร็จสมบูรณ์!</h5>';
    echo '<p class="mb-0 small">สิทธิ์การเข้าใช้งานได้รับการจัดสรรอย่างถูกต้องและปลอดภัยแล้ว</p>';
    echo '</div>';
    
    echo '<div class="text-center mt-4">';
    echo '<p class="text-danger small mb-3">⚠️ เพื่อความปลอดภัยสูงสุด กรุณาลบไฟล์ <b>update_db_roles.php</b> นี้ออกจากโฮสต์ของคุณหลังจากใช้งานเสร็จสิ้นแล้ว</p>';
    echo '<a href="./dashboard" class="btn btn-primary px-4 py-2" style="border-radius: 8px;">เข้าสู่หน้าแดชบอร์ดหลัก</a>';
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger" role="alert">';
    echo '<h5>❌ เกิดข้อผิดพลาดทางเทคนิค</h5>';
    echo '<p class="mb-0">' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';
echo '</body>';
echo '</html>';
