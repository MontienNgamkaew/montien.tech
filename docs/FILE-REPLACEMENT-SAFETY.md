# การรักษาไฟล์เดิมระหว่างแทนที่ — 16 พฤศจิกายน 2569

## การเปลี่ยนแปลง

| ไฟล์ | ผลที่เปลี่ยน |
|---|---|
| `api/update_profile.php` | ไม่ unlink รูปเก่าตอนแทนที่/เอารูปออกจากโปรไฟล์ ตรวจรหัสผ่านสั้นก่อนเขียนไฟล์ ตรวจว่าเขียน base64 ได้ครบ และใช้ชื่อสุ่มแยกไฟล์ใหม่ |
| `pnpman/api/manage_personnel.php` | ส่วน update บุคลากรไม่ลบรูปเดิม ใช้ชื่อไฟล์ใหม่แบบสุ่ม; delete-photo เปลี่ยนเฉพาะ reference |
| `pnpman/api/update_settings.php` | ไม่ลบโลโก้เก่า เตรียม upload directory หากยังไม่มี ใช้ชื่อสุ่มและจัดการข้อผิดพลาดการเตรียมโฟลเดอร์ |
| `pnp-academix/teacher/submit.php` | ไม่ลบเอกสารเดิมเมื่อส่งใหม่ ใช้ชื่อสุ่ม บันทึกด้วย transaction และ lock รายวิชา ตรวจสถานะอนุมัติซ้ำหลัง lock |
| `pnp-academix/admin/settings.php` | ไม่ลบโลโก้เดิม ใช้ชื่อสุ่ม และบันทึก branding ทุกคีย์ใน transaction เดียว |

คงพาธฐานข้อมูล, URL เดิม, schema และ `.env` ไม่มี migration สำหรับชุดนี้ ยังไม่ push หรือ deploy

## การเก็บไฟล์

ไฟล์เดิมคงอยู่ตำแหน่งเดิม แม้เปลี่ยนไฟล์สำเร็จแล้วหรือเอารูปออกจากหน้าจอ ไม่มีการลบอัตโนมัติในจุดที่แก้ และไม่ได้เพิ่มหน้าประวัติ/ปุ่มกู้คืน หาก SQL ปฏิเสธหลังอัปโหลด ไฟล์ใหม่ที่ยังไม่ถูกอ้างอิงก็เก็บไว้ก่อน ต้องสำรอง/ทำ inventory ก่อนพิจารณา cleanup

การเอารูปออกจึงเป็นการถอด reference ไม่ใช่ลบ bytes ออกจากดิสก์ หากไฟล์เดิมเปิดได้ผ่าน URL เดิม ก็ยังอาจเปิดได้ ต้องกำหนดนโยบาย retention และสิทธิ์เข้าถึงแยกก่อนใช้เป็นระบบลบข้อมูลถาวร

เมนูล้างไฟล์ Academix และการลบบัญชี/รายวิชา/รายการส่งโดยตรงยังเป็นงานคนละส่วน ไม่ได้รับการปรับครบในชุดนี้ โดยเฉพาะ cleanup อาจมองไฟล์เก่าที่ไม่ได้อ้างอิงเป็น orphan ห้ามใช้เพื่อจัดระเบียบก่อนมี inventory และอนุมัติรายการลบ

## ผลทดสอบ

ใช้ MariaDB 11.8.9 ที่ restore แยกและ PHP staging บน localhost ไม่มี production `.env` ไม่ใช้บัญชีจริงในการส่งคำขอ

ทั้ง 5 จุดผ่าน:

1. ปิดสิทธิ์เขียนที่โฟลเดอร์ปลายทางชั่วคราวใน staging → DB reference และ checksum ไฟล์เดิมไม่เปลี่ยน
2. เปิด trigger จำลอง SQL UPDATE ล้มเหลวหลังไฟล์ใหม่มาถึงดิสก์ → DB reference และ checksum ไฟล์เดิมไม่เปลี่ยน
3. ปลด fault แล้วส่งใหม่ → DB อ้างไฟล์ใหม่ที่มี bytes ถูกต้อง ไฟล์เดิมยังอยู่
4. ส่งซ้ำ → ได้คนละชื่อไฟล์ และไฟล์จากครั้งก่อนยังอยู่
5. กรณีรูป/โลโก้: ถอด reference ขณะ SQL ล้มเหลวและเมื่อสำเร็จ → ไม่มีการลบไฟล์เดิม

เพิ่มเติม: branding Academix ย้อนค่าทุกคีย์เมื่อ logo-path UPDATE ล้มเหลว และไม่เขียนทับเอกสารที่สถานะ approved ในการทดสอบ HTTP

ผลส่วนตัว: `private-audit/staging-20261116/file-replacement-results.json` ถูก ignore จาก Git

## รันทดสอบซ้ำ

```bash
php -l api/update_profile.php
php -l pnpman/api/manage_personnel.php
php -l pnpman/api/update_settings.php
php -l pnp-academix/teacher/submit.php
php -l pnp-academix/admin/settings.php
git diff --check
```

Integration test ต้องเปิด MariaDB datadir ทดสอบตาม `RESTORE-TEST-20261116.md` ก่อน:

```bash
python3 scripts/maintenance/test-file-replacement.py
```

ตัวทดสอบยืนยัน datadir และปิด TCP ก่อน SQL ทุกครั้ง เริ่ม/หยุด PHP staging เอง เพิ่ม fixture และ trigger **เฉพาะฐานข้อมูลทดสอบ** จึงไม่ควรรันเพื่อเทียบ baseline row counts เดิม ใช้พื้นที่ `private-audit/` เดิม และต้องไม่วาง production `.env` ลง staging ตัว trigger fault ถูกปิดเมื่อจบ ไม่ลบ fixture หรือไฟล์

## ข้อจำกัดก่อน production

ยังไม่ทดสอบโหลดพร้อมกันหลาย worker, UI ด้วยเบราว์เซอร์ หรือ workflow ครบทุกสิทธิ์ การ lock รายวิชาช่วย serialize การส่งรายวิชาเดียวกัน แต่ไม่ใช่การรับรอง concurrency ทุกการทำงานของระบบ

ต้องตรวจ hPanel auto-deploy, สำรอง production ใหม่ และวางแผนรักษา runtime files ก่อนปล่อย การกู้ไฟล์ที่ขาดอยู่ก่อน 5 รายการยังเป็นงานค้างแยก ไม่ควร restore DB ทั้งชุดทับข้อมูลใหม่เพื่อแก้ไฟล์เหล่านั้น
