# Academix: หน้าผู้ดูแลและ Preview เพื่อตรวจพิจารณา

รุ่น `academix-admin-ui-20261116-v1` ปรับหน้าภาพรวม หน้ารายวิชาครู และหน้าตรวจพิจารณาให้ใช้รูปแบบขาว–กรมท่าเดียวกับหน้าครู โดยใช้บัญชีและสิทธิ์ Portal เดิม ไม่เพิ่ม schema หรือเปลี่ยนสิทธิ์บัญชีจริง

## วิธีใช้

Portal → PNP Academix → งานดูแลระบบ

- ภาพรวมแสดงงานล่าสุดของแต่ละรายวิชา แยกโครงการสอน แผนการสอน และสื่อการสอน พร้อมจำนวนรอตรวจ อนุมัติ ต้องแก้ไข และยังไม่ส่ง เฉพาะครูใน Academix ที่อยู่ในขอบเขตสิทธิ์
- กด “ดูรายการรอตรวจ” เพื่อกรองประเภทและสถานะพร้อมเปิดรายการเอกสาร หรือค้นหาครู/กรองแผนกได้
- กด “ดูรายวิชาและงานทั้งหมด” เพื่อดูสถานะทั้งสามประเภทในแต่ละรายวิชา รวมเวลาและข้อเสนอแนะเดิม
- กด “ตรวจพิจารณา” เพื่อเปิด Preview ข้างแบบบันทึกผล บนมือถือแสดงเอกสารก่อนและแบบพิจารณาต่อด้านล่าง มีปุ่มเปิดต้นฉบับ และสลับ PDF/ลิงก์เมื่อมีทั้งสองแบบ
- ผู้ตรวจยืนยันว่าได้ตรวจเอกสารแล้วจึงบันทึกอนุมัติหรือส่งกลับแก้ไข การส่งกลับต้องมีเหตุผล บันทึกแล้วกลับหน้าครูพร้อมข้อความสำเร็จ
- เมนูรายงาน/ตั้งค่า/นำเข้าจะแสดงตามสิทธิ์และขอบเขตเดิม หน้า history/settings/import และแบบพิมพ์ยังใช้ระบบเดิม

เกณฑ์เดิมคงอยู่: โครงการสอนอนุมัติทุกรายวิชา แผนการสอนและสื่อการสอนอนุมัติอย่างน้อยประเภทละหนึ่งรายวิชา จำนวน “ยังไม่ส่ง” นับระดับรายวิชา จึงแยกจากการผ่านเกณฑ์ขั้นต่ำของครูอย่างชัดเจน ไม่มีงานวิจัยเพิ่ม และ PNP Lesson Plan ยังคงพักบริการ

## สิทธิ์และความปลอดภัยของข้อมูล

`admin/preview.php` เป็น GET/HEAD อ่านอย่างเดียว ใช้ Portal admin workspace + view และตรวจขอบเขตของครูเจ้าของเอกสารทุกครั้ง รวมถึง raw PDF/Range ไม่ใช้สิทธิ์ self ของครูเพื่อเปิดเอกสารคนอื่น ไม่ sync บัญชีหรือโหลด bootstrap ที่ทำ self-healing DDL ใช้ Academic transaction แบบ READ ONLY และ rollback ก่อนส่งไฟล์

หน้า Preview สำหรับครูยังจำกัดเจ้าของงานเหมือนเดิม ผู้มีสิทธิ์ดูงานผู้ดูแลเปิดเอกสารในขอบเขตได้ แต่ไม่มีปุ่มอนุมัติหากไม่มี approve การ POST ปลอมถูกปฏิเสธฝั่ง server เช่นเดียวกับการตรวจงานของตนเอง (เทียบ local teacher ID) หรือข้อมูลนอกขอบเขต เมนูที่ซ่อนไม่ใช่กลไกป้องกันหลัก

ไฟล์ PDF อ่านจากต้นฉบับภายใน upload roots เดิม ตรวจ realpath/MIME, ใช้ no-store และรองรับ HEAD/Range ผ่าน helper รุ่นหน้าครู ลิงก์ Drive/YouTube ใช้ host ที่อนุญาตและไม่เปลี่ยนลิงก์หรือ sharing เดิม หากแสดงไม่ได้ให้เปิดต้นฉบับ ลิงก์ภายนอกอื่นแสดงปุ่มต้นฉบับโดยไม่มี server-side fetch ส่วนไฟล์หายจะแจ้งข้อความและเก็บรายการส่งงานไว้

การตรวจผลคง CSRF และ action approve/reject เดิม เพิ่ม revision จากสถานะ ลิงก์ ไฟล์ ข้อเสนอแนะ และเวลาของรายการที่เปิดอ่าน หากครูส่งฉบับใหม่หรือผู้ตรวจคนอื่นบันทึกผลระหว่างนั้น จะตอบ 409 และให้โหลดใหม่ พร้อม conditional UPDATE เพื่อป้องกันการแข่งขันหลังตรวจ revision อีกชั้น ไม่เขียนทับฉบับใหม่จากหน้าค้าง

เฉพาะการกดบันทึกผลโดยผู้มีสิทธิ์จึงเปลี่ยน `status` และ `feedback` ของ submission นั้น พร้อม `updated_at` อัตโนมัติจาก schema เดิม ไม่เปลี่ยน ID, course_id, ประเภทงาน, submitted_at, timing, file_path, drive_link หรือไฟล์ต้นฉบับ การเปิดหน้าและ Preview ไม่แก้รายการส่งงาน

## ไฟล์ที่เปลี่ยน

แพ็กเกจ runtime มี 12 ไฟล์:

- `admin/overview.php`, `admin/review_teacher.php`, `admin/review.php`: controller เส้นทางเดิมและใช้ template ใหม่ เพิ่มเฉพาะคอลัมน์แสดงผลและการตรวจ revision ใน review
- `admin/preview.php`: endpoint สำหรับผู้ตรวจโดยตรวจสิทธิ์แยกจาก Preview ของครู
- `includes/admin-overview-view.php`, `admin-teacher-view.php`, `admin-review-view.php`: หน้าแสดงผลสามส่วน
- `includes/admin-support.php`, `admin-document-frame.php`: สถิติ UI, revision และส่วนแสดงเอกสาร
- `assets/academix-admin.js`, `assets/academix.css`, `includes/academix-ui.php`: ตัวกรอง รูปแบบ responsive และ cache version

นอก runtime เพิ่ม `tests/test_academix_admin_review.py`, allowlist เฉพาะไฟล์ข้างต้นใน `scripts/deploy/portal_multirole_release.php` และเอกสารนี้ ไม่รวม `.env`, uploads, ฐานข้อมูล หรือไฟล์ Lesson Plan ที่ค้างใน working tree

## ผลทดสอบและคำสั่ง

ใช้ MariaDB fixture `pnp_v2_stage_*` แยกฐานจริง มี socket และ datadir guard และปิด network ของ MariaDB ห้ามนำคำสั่งทดสอบไปชี้ production ต้องเตรียม fixture และเปิด localhost ด้วย `scripts/maintenance/serve-portal-v2.py` ก่อน

```sh
python3 -m unittest discover -s tests -p test_academix_admin_review.py
python3 -m unittest discover -s tests -p test_academix_preview.py
python3 -m unittest discover -s tests -p test_academix_teacher_access.py
python3 -m unittest discover -s tests -p test_portal_v2_multirole.py
python3 -m unittest discover -s tests -p test_portal_v2_integration.py
python3 -m unittest discover -s tests -p test_academix_preview_sources.py
PNP_PATCH_FIXTURE_RELEASE=academix-admin-ui-20261116-v1 python3 -m unittest discover -s tests -p test_portal_multirole_release.py
```

ผ่าน 33 กลุ่ม: admin 6, teacher Preview 1, teacher access 8, multi-role 8, Portal 7, source validation 1 และ installer rehearsal 2 รวม PHP lint และ JavaScript syntax check

Admin integration ตรวจ fingerprint ทุกตารางก่อน–หลังเปิดดู, PDF bytes/mtime/Range/HEAD, scope แผนก, การเปลี่ยน scope ขณะเปิดหน้า, บัญชีดูอย่างเดียว/ครู/anonymous, self-approval, CSRF, การยืนยันตรวจเอกสาร, เหตุผลส่งกลับ, stale revision และอนุมัติ/ส่งกลับที่เปลี่ยนเฉพาะผลกับ updated_at เดิม

ตรวจด้วย browser บนข้อมูลจำลอง: ภาพรวม ค้นหาครู คิวรอตรวจ หน้ารายวิชา Preview PDF พร้อมแบบพิจารณา และ viewport 390px ของภาพรวม/หน้าตรวจไม่มี horizontal overflow ภาพอยู่ใน `private-audit/portal-v2-stage/academix-admin-overview.png`, `academix-admin-review.png`, `academix-admin-mobile.png` (ignored)

## การติดตั้ง

ใช้ admin SSH และแพ็กเกจ hash ระบุไฟล์ชัดเจน ตรวจ baseline และ PHP 8.3.36 บน Hostinger ก่อนติดตั้ง เปิด maintenance ระหว่าง full backup และตรวจข้อมูลเดิมทุกตาราง ใช้ migration 3 แบบ idempotent ของ installer เดิม ไม่มี schema ใหม่ ต้องตรวจ marker ก่อน–หลังเท่ากัน

- Release: `/home/u651170081/releases/academix-admin-ui-20261116-v1/`
- Backup: `/home/u651170081/backups/montien-tech/academix-admin-ui-20261116-v1/`
- หลักฐานส่วนตัวในเครื่อง: `private-audit/releases/academix-admin-ui-20261116-v1/`

Rollback คืนเฉพาะโค้ดเดิม ไม่ restore/delete ฐานข้อมูลหรือ uploads ไฟล์ endpoint ใหม่ยังอยู่และยังตรวจสิทธิ์ตามเดิม หากมี role profiles/staff assignments ใช้งานแล้ว installer เดิมจะปฏิเสธ manual rollback ให้ใช้ compatible forward fix ไม่มีการเปิด auto deploy หรือเปลี่ยน main


## ผลติดตั้งจริง

เปิดใช้ 16 พฤศจิกายน 2569 เวลา 17:21:16 น. (10:21:16 UTC) ตรวจ SHA256 ครบ 12 ไฟล์และ permissions 0644 หลังติดตั้ง maintenance ถูกยกออกแล้ว ข้อมูลทั้ง 31 ตารางเดิมก่อน–หลังติดตั้งเท่ากัน รวม migration marker เดิมทั้งสองรายการ และ `.env` ไม่เปลี่ยน ชุดสำรองใหม่ตรวจ SHA256 ของ dump และ archives ผ่าน

HTTP: Portal และ assets ที่มี cache version ใหม่ตอบ 200, Preview raw ทั้งครูและผู้ดูแลที่ไม่ล็อกอินตอบ 401, includes/runtime config และ Lesson Plan ตอบ 403, หน้าผู้ดูแลที่ไม่ล็อกอินกลับ Portal ตามเดิม ตรวจ CSS/JS ด้วย `?v=admin-review-20261116` ตาม URL ที่หน้าใหม่เรียกจริง (URL CSS ไม่ระบุ version อาจยังมี cache รุ่นก่อนหน้า)

ทดสอบการอนุมัติ/ส่งกลับและภาพหน้าจอเฉพาะข้อมูลจำลอง ไม่เข้าสู่บัญชีครูจริง ไม่ impersonate และไม่เปลี่ยนผลตรวจงานใดบน production ระหว่างติดตั้ง
