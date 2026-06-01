-- =============================================================
-- SQL SCRIPT FOR UPDATING PNP GO USER ROLES (phpMyAdmin)
-- Instructions:
-- 1. Open phpMyAdmin on Hostinger.
-- 2. Select the database 'u651170081_pnpgo' (or local 'carrequest').
-- 3. Click the 'SQL' tab or Import this file.
-- 4. Click 'Go' to execute the queries.
-- =============================================================

-- 1. อัปเดตบทบาทของคุณครู ปฏิพาน สีนาบุญ (ID 6) ให้เป็น 'user' และกำหนดตำแหน่งให้ถูกต้อง
UPDATE users 
SET role = 'user', 
    position_title = 'ข้าราชการครู',
    is_active = 1
WHERE username = '1470800181781';

-- 2. อัปเดตบทบาทของรองผู้อำนวยการ มณเฑียร งามแก้ว (ID 5) ให้เป็น 'deputy_director' 
UPDATE users 
SET role = 'deputy_director', 
    position_title = 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร',
    is_active = 1
WHERE username = '1350100238268';

-- 3. อัปเดตค่าความปลอดภัยสำหรับผู้ใช้งานทุกคนที่ role มีค่าว่างเปล่า (NULL หรือว่าง) ให้เป็น 'user'
UPDATE users 
SET role = 'user' 
WHERE role IS NULL OR role = '' OR role = 'none';
