# หน้าแรก Academix: ธีมเข้ม เวลาถอยหลัง และแมวทำงาน

รุ่น `academix-cat-countdown-20261116-v1` เปลี่ยนเฉพาะหน้า `pnp-academix/index.php` เป็นแก้วสีเข้มม่วง–ฟ้า พร้อมแมวนั่งทำงานที่โต๊ะคอมและเวลาถอยหลังสามประเภท ไม่เปลี่ยนสิทธิ์ กำหนดส่ง ข้อมูลการส่งงาน หรือเงื่อนไขรับงานล่าช้า

## พฤติกรรม

- นับถอยหลังโครงการสอน แผนการสอน และสื่อการสอนจาก `system_settings` ของภาคเรียนที่เปิดอยู่ อ่านกำหนดส่งเมื่อเปิดหน้า
- แปลง DATETIME เป็น epoch ด้วย Asia/Bangkok ฝั่ง PHP ไม่ให้ browser ตีความ timezone เอง ใช้เวลาฝั่ง server เป็นฐานและ performance.now สำหรับการเดินของนาฬิกา
- แมวแต่ละการ์ดขยับเร็วขึ้นต่อเนื่องตามเวลาที่เหลือ ตั้งแต่รอบละ 1.8 วินาทีจนถึงประมาณ 0.26 วินาทีเมื่อใกล้กำหนด เมื่อครบกำหนดหยุดพิมพ์ เปลี่ยนตาหมุนและดาววนพร้อมโยกหัว
- แมวตัวใหญ่เลือกกำหนดส่งถัดไปที่ยังเปิดรับ เมื่อประเภทหนึ่งหมดเวลาแต่ประเภทอื่นยังเหลือเวลา จะไปลุยประเภทถัดไป หากทุกประเภทหมดเวลา แมวตัวใหญ่จะมึนหัว
- ปิดรับงานแสดงสถานะปิดและขีดแทนตัวเลข ไม่มีเวลา/ไม่มีรอบส่งแสดงข้อความให้ชัด ไม่คำนวณจากวันที่ผิดรูปแบบ
- เมื่อครบกำหนดไม่ปิดสิทธิ์ส่งงานอัตโนมัติ ยังคงกติกาเดิมที่รับงานล่าช้าได้ถ้าผู้ดูแลเปิดรับอยู่ หน้าแรกพาไปพื้นที่ทำงานเดิมตามสิทธิ์
- ปุ่มพักหยุดเฉพาะแอนิเมชัน เวลาเดินต่อ เคารพ prefers-reduced-motion และพัก animation เมื่อซ่อนแท็บ หลังเครื่องหลับ/นาฬิกากระโดด หรือกลับจาก BFCache จะโหลดหน้าอ่านอย่างเดียวใหม่เพื่อรับเวลาปัจจุบัน
- SVG แมววาดใน source มี ID ของ gradient แยกแต่ละตัว ไม่ใช้รูป/CDN/ไลบรารีแอนิเมชันภายนอก และไม่มีการส่งข้อมูลไปบริการภายนอก

## ไฟล์ runtime 6 ไฟล์

1. `pnp-academix/index.php`: คง query/auth เดิมและเรียก template หน้าแรก
2. `pnp-academix/includes/academix-ui.php`: เพิ่มตัวเลือก landing แบบ optional เฉพาะหน้าแรก หน้าอื่นคงธีมเดิม
3. `pnp-academix/includes/landing-view.php`: หน้าแรก การ์ดกำหนดส่ง และลิงก์ตามสิทธิ์
4. `pnp-academix/includes/landing-support.php`: ตัวอ่านเวลาไทยแบบ strict และ SVG แมว
5. `pnp-academix/assets/academix-landing.css`: dark glass และ CSS motion
6. `pnp-academix/assets/academix-landing.js`: ตัวนับเวลา/เลือกภารกิจ/ความเร็ว/ปุ่มพัก ไม่มี request หรือการเขียนข้อมูล

เพิ่ม allowlist เฉพาะไฟล์ใน installer เดิมและ `tests/test_academix_countdown.py` ไม่รวมงาน Lesson Plan ที่ค้างอยู่ใน working tree ไม่แก้ `.env`, uploads หรือ controller การส่ง/ตรวจงาน

## ผลทดสอบ

Countdown 3 กลุ่ม, Portal integration 7 กลุ่ม และ installer rehearsal 2 กลุ่ม รวม 12 กลุ่มผ่าน พร้อม PHP lint และ JavaScript syntax check

ทดสอบ timezone ไทย วันที่ผิดรูปแบบ เวลา 0/past/future/closed/undated ความเร็วที่เพิ่มตามเวลา การเลือกกำหนดส่งถัดไป และ fingerprint ทุกตารางก่อน–หลังเปิดหน้าแรกกับฐานจำลอง

ตรวจ browser ด้วยข้อมูลจำลอง: แมวปกติรอบพิมพ์ 1.241s เทียบกับใกล้กำหนด 0.268s, นับจากสถานะใกล้กำหนดจนถึงศูนย์แล้วเปลี่ยนตาหมุนจริง, ทุกประเภทหมดเวลาแล้วแมวตัวใหญ่เปลี่ยนท่า, ปุ่มพักหยุด animation โดยเวลาเดินต่อ และ viewport 1440/390px ไม่มี horizontal overflow กำหนดส่งจำลองที่ใช้ตรวจถูกคืนค่าหลังทดสอบ ไม่เคยแก้กำหนดส่งจริง

ภาพหลักฐาน: `private-audit/portal-v2-stage/academix-cat-working.png` และ `academix-cat-expired.png` (ignored, ข้อมูลจำลอง)

คำสั่งทดสอบ (เตรียม fixture/localhost ตาม `scripts/maintenance/serve-portal-v2.py` ก่อน ห้ามใช้ production):

```sh
python3 -m unittest discover -s tests -p test_academix_countdown.py
python3 -m unittest discover -s tests -p test_portal_v2_integration.py
PNP_PATCH_FIXTURE_RELEASE=academix-cat-countdown-20261116-v1 python3 -m unittest discover -s tests -p test_portal_multirole_release.py
node --check pnp-academix/assets/academix-landing.js
php -l pnp-academix/includes/landing-view.php
```

## การติดตั้ง

ตรวจ baseline และ PHP 8.3.36 ก่อนติดตั้ง ใช้ maintenance/full backup และตรวจ fingerprint ทุกตารางตาม installer เดิม ไม่มี schema ใหม่ ใช้ migration 3 เดิมแบบ idempotent ต้องตรวจ marker ก่อน–หลังเท่ากันด้วย

- Release: `/home/u651170081/releases/academix-cat-countdown-20261116-v1/`
- Backup: `/home/u651170081/backups/montien-tech/academix-cat-countdown-20261116-v1/`
- หลักฐานส่วนตัวในเครื่อง: `private-audit/releases/academix-cat-countdown-20261116-v1/`

Rollback คืนเฉพาะโค้ด ไม่ restore/delete DB หรือ uploads เงื่อนไข role profiles/staff assignments ของ installer เดิมยังคงอยู่ ให้ใช้ compatible forward fix หาก manual rollback ถูกปฏิเสธ


## ผลติดตั้งจริง

เปิดใช้ 17 พฤศจิกายน 2569 เวลา 16:09:07 น. (09:09:07 UTC) ใช้ชื่อรุ่นที่เตรียมไว้วันที่ 16 ตรวจ SHA256 ครบ 6 ไฟล์ ข้อมูลเดิมทั้ง 31 ตารางก่อน–หลังติดตั้งเท่ากัน รวม migration marker สองรายการ และ `.env` ไม่เปลี่ยน ชุด backup ใหม่ตรวจ SHA256 ของ dump และ archives ผ่าน

HTTP ของ CSS/JS รุ่นใหม่ตอบ 200 และตรง source ทุก byte หน้าแรกที่ยังไม่ล็อกอินกลับ Portal ตามเดิม Preview raw ที่ไม่ล็อกอินตอบ 401, helper และ Lesson Plan ตอบ 403 ไม่แก้กำหนดส่งหรือผลตรวจงานจริงระหว่างทดสอบและติดตั้ง


## ผู้ดูแลเข้าถึงหน้าแรกได้โดยตรง — 24 พฤศจิกายน 2569

รุ่น `academix-admin-home-link-20261124-v1` เพิ่ม “หน้าแรก” ใน navigation ร่วม และ “หน้าแรกและเวลาถอยหลัง” ในเมนู Dashboard ผู้ดูแล (`includes/academix-ui.php`, `includes/admin-support.php`) ผู้ดูแลมีสิทธิ์เปิดหน้าแรกผ่านบัญชี Portal เดิมอยู่แล้ว จึงไม่แก้ auth หรือ grants และซ่อน “งานของฉัน” หากบัญชีนั้นไม่มีสิทธิ์ self

ตรวจด้วยบัญชีจำลองตำแหน่งเจ้าหน้าที่ roles=[admin], admin rights=[view,approve], self_rights=[]: เปิดหน้าแมว 200, พบลิงก์บน Dashboard, self workspace ยัง 403 และสิทธิ์ approve ยังคงอยู่ Countdown suite 3 กลุ่มและ PHP lint 2 ไฟล์ผ่าน คำสั่งทดสอบใช้ `python3 -m unittest discover -s tests -p test_academix_countdown.py` ตามเงื่อนไข fixture ข้างต้น

แพ็กเกจมีเฉพาะเมนู 2 ไฟล์ ตรวจ baseline บน PHP 8.3.37 ก่อนติดตั้ง ใช้ full backup และเปรียบเทียบข้อมูลเดิมตาม installer เดิม ไม่รวมงาน Lesson Plan ที่พักไว้

Release/backup: `/home/u651170081/releases/academix-admin-home-link-20261124-v1/` และ `/home/u651170081/backups/montien-tech/academix-admin-home-link-20261124-v1/` หลักฐานส่วนตัว `private-audit/releases/academix-admin-home-link-20261124-v1/`

เปิดใช้จริง 24 พฤศจิกายน 2569 เวลา 10:21:51 น. (03:21:51 UTC) ตรวจ hash 2 ไฟล์และชุด backup ผ่าน ข้อมูลเดิม 31 ตารางรวม migration marker เท่าเดิม และ `.env` ไม่เปลี่ยน ไม่มีการแก้สิทธิ์บัญชีจริง
