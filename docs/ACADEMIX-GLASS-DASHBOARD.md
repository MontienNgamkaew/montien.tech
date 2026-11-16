# Academix: Dashboard สไตล์แก้วสี

รุ่น `academix-glass-ui-20261116-v1` เพิ่มธีมแก้วโปร่งโทนน้ำเงิน–ม่วง–ฟ้า และ Dashboard ที่มีการ์ดตัวเลข/กราฟวงแหวน โดยใช้ข้อมูลและขอบเขตสิทธิ์เดิม ไม่มีการเปลี่ยน controller, SQL, การอนุมัติ, Preview, schema หรือที่เก็บไฟล์

## รูปแบบใหม่

- พื้นหลังไล่สีอ่อน การ์ดโปร่งพร้อมขอบแสง เงา และ backdrop blur มี fallback สำหรับ browser ที่ไม่รองรับและ prefers-reduced-transparency
- ส่วนหัว Dashboard แสดงภาคเรียนและจำนวนครูที่ผ่านเกณฑ์อนุมัติเดิมครบสามประเภท
- การ์ดตัวเลขสี่รายการ: บุคลากรในความดูแล รายวิชาในภาคเรียน เอกสารที่ส่งแล้ว และงานรอตรวจ คำนวณจาก teacherData/totals ที่กรองสิทธิ์แล้วเท่านั้น
- การ์ดเอกสารสามประเภทแยกสีน้ำเงิน ม่วง และฟ้า พร้อมกราฟอนุมัติ/รอตรวจ/ต้องแก้ไข/ยังไม่ส่ง ใช้ SVG ในหน้า ไม่มี chart library หรือ CDN เพิ่ม
- เปอร์เซ็นต์ตรงกลางวงแหวนหมายถึงเอกสารที่อนุมัติเทียบกับรายวิชาทั้งหมดในขอบเขต ไม่ใช่เปอร์เซ็นต์ครูผ่านเกณฑ์ขั้นต่ำ มีชื่อสถานะ/จำนวน/คำอธิบายและ accessible label แยกจากสี
- ปุ่มคิวตรวจยังใช้ data attribute และตัวกรองเดิม ธีมร่วมแสดงบนหน้าครู รายครู และ Preview ที่ใช้ `ac_start` ด้วย หน้า legacy และแบบพิมพ์เดิมไม่ได้เปลี่ยน flow
- มือถือใช้การ์ดตัวเลขสองคอลัมน์และกราฟเรียงลง พร้อมสีข้อความ/สถานะที่อ่านบนพื้นแก้วได้

## ไฟล์ที่เปลี่ยน

Runtime มีเพียง 3 ไฟล์:

1. `pnp-academix/assets/academix-glass.css`: stylesheet ใหม่ ไม่มี JavaScript หรือ network fetch
2. `pnp-academix/includes/academix-ui.php`: โหลด stylesheet ใหม่ด้วย `?v=glass-20261116-v1` ต่อจาก CSS เดิม
3. `pnp-academix/includes/admin-overview-view.php`: การ์ด Dashboard และ SVG จากตัวแปรแสดงผลเดิม

ปรับ allowlist ใน `scripts/deploy/portal_multirole_release.php` ให้รับเฉพาะ CSS ใหม่ และเพิ่มบันทึกนี้ ไม่แตะงาน Lesson Plan ที่ค้างอยู่ ไม่แก้ `.env` หรือไฟล์เอกสารใด

## การตรวจสอบ

PHP lint ของ helper/template ผ่าน Admin integration 6 กลุ่มผ่านในฐานข้อมูลจำลอง ครอบคลุม fingerprint ทุกตารางก่อน–หลังดูงาน, สิทธิ์และ scope, Preview และการบันทึกผลเดิม Installer rehearsal 2 กลุ่มผ่านทั้ง backup failure และ install/rollback รวม 8 กลุ่มสำหรับรุ่นธีมนี้ ไม่เพิ่ม test ใหม่สำหรับ CSS

คำสั่ง (เตรียม fixture และเปิด localhost ตาม `scripts/maintenance/serve-portal-v2.py` ก่อน ห้ามชี้ไป production):

```sh
python3 -m unittest discover -s tests -p test_academix_admin_review.py
PNP_PATCH_FIXTURE_RELEASE=academix-glass-ui-20261116-v1 python3 -m unittest discover -s tests -p test_portal_multirole_release.py
php -l pnp-academix/includes/academix-ui.php
php -l pnp-academix/includes/admin-overview-view.php
```

ตรวจ browser ด้วยข้อมูลจำลอง: Dashboard มีการ์ดตัวเลข 4 ใบ กราฟ 3 วง ตัวกรองคิวทำงาน หน้าตรวจมี Preview และโหลด stylesheet ใหม่ได้ภายใต้ CSP เดิม ที่ความกว้าง 1440px และ 390px ไม่มี horizontal overflow ภาพตัวอย่าง `private-audit/portal-v2-stage/academix-glass-dashboard.png` (ignored)

## การติดตั้ง

ตรวจ baseline SHA256 และ PHP 8.3.36 บน Hostinger ก่อนติดตั้ง ใช้ maintenance และ full backup ใหม่ตาม installer เดิม เปรียบเทียบข้อมูลทุกตารางและ migration marker ก่อน–หลัง ไม่มี schema ใหม่ มีเพียง migration 3 เดิมแบบ idempotent

- Release: `/home/u651170081/releases/academix-glass-ui-20261116-v1/`
- Backup: `/home/u651170081/backups/montien-tech/academix-glass-ui-20261116-v1/`
- หลักฐานส่วนตัวในเครื่อง: `private-audit/releases/academix-glass-ui-20261116-v1/`

Rollback คืนเฉพาะโค้ด ไม่ restore/delete ฐานข้อมูลหรือ uploads เงื่อนไข role profiles/staff assignments ของ installer เดิมยังคงอยู่ ให้ใช้ compatible forward fix หาก manual rollback ถูกปฏิเสธ


## ผลติดตั้งจริง

เปิดใช้วันที่ 16 พฤศจิกายน 2569 เวลา 17:34:08 น. (10:34:08 UTC) ตรวจ SHA256 ของไฟล์ติดตั้งครบ 3 ไฟล์และ CSS ผ่าน HTTP ตรงกับ source ทุก byte ข้อมูลเดิมทั้ง 31 ตารางก่อน–หลังติดตั้งเท่ากัน รวม migration marker สองรายการ และ `.env` ไม่เปลี่ยน ชุด backup ใหม่ตรวจ SHA256 ของ dump และ archives ผ่าน

HTTP: Portal/ธีมใหม่ 200, Preview raw ที่ไม่ล็อกอิน 401, helper template และ Lesson Plan 403 ไม่มีการแก้ผลตรวจงานจริงหรือเข้าสู่บัญชีครูจริงในการติดตั้งครั้งนี้
