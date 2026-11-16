# Academix: หน้าครูและ Preview

รุ่น `academix-ui-preview-20261116-v1` ปรับหน้าแรก หน้าสถานะครู และรูปแบบหน้าส่งงานเป็นโทนกรมท่า–ขาว โดยไม่เปลี่ยนกติกาส่งงาน ฐานข้อมูล หรือที่เก็บเอกสาร

## การใช้งาน

เข้าสู่ Portal → PNP Academix → ดูสถานะงานของฉัน แสดงโครงการสอน แผนการสอน และสื่อการสอนแยกกัน พร้อมจำนวนส่ง ผลอนุมัติ รอตรวจ ต้องแก้ไข กำหนดส่ง และสถานะเปิดรับงาน

รายวิชาแต่ละรายการแสดงสถานะทั้งสามประเภท ค้นหารหัส/ชื่อวิชาและกรองประเภท/สถานะได้ ปุ่มดูงานที่ส่งเปิดหน้า Preview พร้อมเวลาและข้อเสนอแนะของผู้ตรวจ ยังเปิดดูงานที่อนุมัติแล้วหรือปิดรับส่งแล้วได้ ปุ่มแก้ไขแสดงตามสิทธิ์และกติกาเดิม

เกณฑ์เดิม: โครงการสอนส่งทุกรายวิชา แผนการสอนและสื่อการสอนอย่างน้อยประเภทละหนึ่งวิชา จำนวนส่งครบไม่ได้หมายถึงอนุมัติแล้ว หน้าจอแยกสองความหมายนี้ชัดเจน งานวิจัยยังไม่เพิ่ม

## การปกป้องข้อมูล

- Preview รับเฉพาะ GET/HEAD ตรวจบัญชีกลางที่ยังใช้งานได้และสิทธิ์ self ทุกครั้ง แล้วค้น submission โดยผูกกับ local teacher ID ของบัญชีเจ้าของงาน แม้เป็น master ก็เปิด Preview ของคนอื่นผ่านหน้านี้ไม่ได้
- ใช้ transaction อ่านอย่างเดียว ไม่โหลด bootstrap ที่ทำ self-healing DDL หรือ SSO sync และไม่แก้ submission/สถานะ/เวลา/ข้อเสนอแนะ
- PDF เปิดจากไฟล์เดิมแบบ `rb` ภายใน legacy/persistent upload roots เท่านั้น ตรวจ realpath และ MIME รองรับ Range/HEAD ใช้ no-store และ same-origin framing ไม่ย้ายหรือเขียนทับไฟล์
- ลิงก์ Drive/YouTube ที่ตรง host/รูปแบบที่อนุญาตจะแปลงเฉพาะ URL ของ iframe โดยไม่บันทึกทับลิงก์เดิม ไม่มี server-side fetch ลิงก์อื่นที่ถูกต้องแสดงปุ่มต้นฉบับ และปฏิเสธ javascript/data URL
- การแสดง Drive/YouTube ขึ้นกับสิทธิ์เอกสารและบริการภายนอก หากแสดงไม่ได้ให้เปิดต้นฉบับ ไม่มีการเปลี่ยน sharing
- ถ้าต้นฉบับหาย/ไม่ใช่ PDF จะแสดงคำอธิบายโดยเก็บรายการส่งงานเดิมไว้
- เอาปุ่มลบงานที่อนุมัติแล้วออกจากหน้าส่งงานให้ตรงกับนโยบาย V2 ที่ห้ามลบอยู่แล้ว และปรับข้อความให้ติดต่อผู้ดูแล
- `.env`, upload directories, ผู้ใช้ และรหัสผ่าน ไม่อยู่ในแพ็กเกจติดตั้ง

## ไฟล์สำคัญ

- `pnp-academix/index.php`: หน้าแรกและเลือกพื้นที่ทำงาน
- `pnp-academix/teacher/dashboard.php`: query เดิมที่จำกัดครู เพิ่มคอลัมน์แสดงเวลา/ข้อเสนอแนะ/เอกสาร แล้วใช้ template ใหม่
- `pnp-academix/includes/teacher-dashboard-view.php`: สรุปสามประเภท รายวิชา ตัวกรอง และฟอร์มเพิ่มรายวิชาเดิม
- `pnp-academix/teacher/submit.php`: CSS และลิงก์ Preview โดยคงการส่ง/แทนที่เดิม
- `pnp-academix/teacher/preview.php`, `includes/preview-support.php`: ตรวจเจ้าของงานและเปิดเอกสารอ่านอย่างเดียว
- `includes/academix-ui.php`, `assets/academix.css`, `assets/academix.js`, `includes/.htaccess`: UI ร่วมและปิด HTTP access ต่อ helper
- `scripts/deploy/portal_multirole_release.php`: เพิ่ม allowlist เฉพาะไฟล์ใหม่ สร้าง public parent directory เป็น 0755 แม้ใช้ private umask และปฏิเสธ symlink ในเส้นทาง

## การทดสอบ

ใช้ฐานข้อมูล `pnp_v2_stage_*` บน MariaDB socket ที่ปิด network เท่านั้น ห้ามชี้คำสั่งเหล่านี้ไป production เริ่ม fixture/server ตาม `scripts/maintenance/serve-portal-v2.py` ก่อน:

```sh
python3 -m unittest discover -s tests -p test_academix_teacher_access.py
python3 -m unittest discover -s tests -p test_academix_preview.py
python3 -m unittest discover -s tests -p test_academix_preview_sources.py
python3 -m unittest discover -s tests -p test_portal_v2_integration.py
python3 -m unittest discover -s tests -p test_portal_v2_multirole.py
PNP_PATCH_FIXTURE_RELEASE=academix-ui-preview-20261116-v1 python3 -m unittest discover -s tests -p test_portal_multirole_release.py
```

ผลทดสอบ: teacher access 8 กลุ่ม, Preview integration 1 กลุ่ม, URL/range validation 1 กลุ่ม, Portal integration 7 กลุ่ม, multi-role 8 กลุ่ม และ installer rehearsal 2 กลุ่ม ผ่าน ครอบคลุม PDF bytes/Range/HEAD, wrong owner/master/anonymous, POST, งานปิดรับส่ง, ไฟล์ hash/mtime เดิม และ fingerprint ทุกตารางก่อน–หลัง Preview

ตรวจหน้าจอจริงด้วยข้อมูลจำลอง: desktop, ตัวกรองงาน, มือถือกว้าง 390px ไม่มี horizontal overflow และ PDF แสดงใน browser viewer ภาพอยู่ใน `private-audit/portal-v2-stage/academix-desktop.png` และ `academix-preview.png` ไม่ทดสอบส่งเอกสารใหม่หรือ impersonate ครูจริงบน production

## การติดตั้งและย้อนโค้ด

แพ็กเกจมี 10 ไฟล์ ตรวจ baseline SHA256 ของไฟล์จริงก่อนติดตั้ง เปิด maintenance (HTTP 503) ระหว่างทำ full backup และติดตั้ง เปรียบเทียบทุกตารางเดิมก่อนเปิดบริการ เก็บ package/rollback/ผลตรวจนอก webroot:

- Release: `/home/u651170081/releases/academix-ui-preview-20261116-v1/`
- Backup: `/home/u651170081/backups/montien-tech/academix-ui-preview-20261116-v1/`
- สำเนา evidence: `private-audit/releases/academix-ui-preview-20261116-v1/` (ignored)

ไม่มี schema ใหม่ ใช้ migration 3 แบบ idempotent ใน installer เดิม ต้องตรวจ marker ก่อน–หลังเท่ากันด้วย การ rollback คืนเฉพาะไฟล์เดิม ไม่ restore/delete DB หรือ uploads ไฟล์ helper ใหม่ยังอยู่แต่หน้ารุ่นเก่าไม่มีลิงก์เรียก หากมีการใช้งาน role profiles แล้ว installer จะปฏิเสธ rollback ให้ทำ compatible forward fix

PNP Lesson Plan ยังคงปิดใช้งาน และไม่รวมงาน Lesson Plan ที่ค้างใน working tree

## ผลติดตั้งจริง

เปิดใช้งาน 16 พฤศจิกายน 2569 เวลา 17:02:31 น. (10:02:31 UTC) ตรวจ installed SHA256 ครบ 10 ไฟล์ โฟลเดอร์ assets/includes เป็น 0755 และ maintenance ถูกยกออกแล้ว เปรียบเทียบข้อมูล 31 ตารางเดิมเท่ากันทุกตาราง รวม migration marker ทั้งสองรายการ และ `.env` ไม่เปลี่ยน

HTTP หลังติดตั้ง: Portal/CSS/JS 200, Preview raw ที่ไม่ล็อกอิน 401, includes/runtime config 403, Lesson Plan 403 ชุด backup ใหม่ตรวจ SHA256 ผ่านทั้ง dump และ archives ไม่มีการเข้าสู่บัญชีครูจริงหรือทดสอบส่ง/แก้ไขงานจริงในขั้นตอนนี้
