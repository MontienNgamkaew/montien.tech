<?php
require __DIR__ . '/bootstrap.php';

try {
    $db = Database::connection();
    
    // สร้างแฮชที่ถูกต้องสำหรับรหัสผ่านเริ่มต้น
    $adminHash = password_hash('admin1234', PASSWORD_BCRYPT);
    $userHash = password_hash('pass1234', PASSWORD_BCRYPT);
    
    // อัปเดตรหัสผ่านสำหรับ admin
    $stmt1 = $db->prepare("UPDATE users SET password_hash = :hash WHERE username = 'admin'");
    $stmt1->execute(['hash' => $adminHash]);
    
    // อัปเดตรหัสผ่านสำหรับผู้ใช้อื่นๆ
    $stmt2 = $db->prepare("UPDATE users SET password_hash = :hash WHERE username IN ('supply', 'deputy', 'director')");
    $stmt2->execute(['hash' => $userHash]);
    
    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #d1fae5; border-radius: 8px; background-color: #f0fdf4; color: #14532d;'>";
    echo "<h1 style='margin-top:0;'>สำเร็จ! รีเซ็ตรหัสผ่านฐานข้อมูลเรียบร้อยแล้ว</h1>";
    echo "<p>ระบบได้รีเซ็ตรหัสผ่านเริ่มต้นทั้งหมดให้ตรงตามคู่มือเรียบร้อยแล้ว:</p>";
    echo "<table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>";
    echo "<thead><tr style='background-color:#d1fae5;'>";
    echo "<th style='padding:8px;text-align:left;border-bottom:1px solid #14532d;'>Username</th>";
    echo "<th style='padding:8px;text-align:left;border-bottom:1px solid #14532d;'>Password</th>";
    echo "<th style='padding:8px;text-align:left;border-bottom:1px solid #14532d;'>บทบาท</th>";
    echo "</tr></thead><tbody>";
    echo "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><strong>admin</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><code>admin1234</code></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>ผู้ดูแลระบบ</td></tr>";
    echo "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><strong>supply</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><code>pass1234</code></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>หัวหน้างานพัสดุ</td></tr>";
    echo "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><strong>deputy</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><code>pass1234</code></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>รองผู้อำนวยการ</td></tr>";
    echo "<tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><strong>director</strong></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'><code>pass1234</code></td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>ผู้อำนวยการ</td></tr>";
    echo "</tbody></table>";
    echo "<p style='color:#b91c1c; font-weight:bold;'>⚠️ สำคัญมาก: เพื่อความปลอดภัย โปรดลบไฟล์ <code>reset_password.php</code> นี้ออกจากโฮสต์บน Hostinger ทันทีหลังทำรายการเสร็จ!</p>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #fee2e2; border-radius: 8px; background-color: #fcefef; color: #7f1d1d;'>";
    echo "<h1 style='margin-top:0;'>เกิดข้อผิดพลาด!</h1>";
    echo "<p>ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
