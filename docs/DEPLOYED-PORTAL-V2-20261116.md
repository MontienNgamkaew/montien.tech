# Portal V2 เปิดใช้งานจริง — 16 พฤศจิกายน 2569

Portal ใหม่เปิดที่ https://pnp.montien.tech/portal-v2/ และหน้าแรก redirect มาที่นี่แล้ว ผู้ใช้สั่งให้พัก Lesson Plan ก่อน จึงไม่นำส่วนเชื่อม Apps Script/Cloudflare ที่ยังไม่ครบขึ้น production

## สถานะบริการ

| ระบบ | สถานะหลังปล่อย |
|---|---|
| Portal / สมาชิก / ผู้ดูแลสูงสุด | เปิดใช้งาน |
| Academix | เปิดใช้งานตามสิทธิ์เดิม |
| Man | เปิดใช้งานตามสิทธิ์เดิม |
| Go | `coming_soon` ตามค่าจริงก่อนปล่อย ไม่เปิดให้สมาชิกโดยอัตโนมัติ |
| Lesson Plan | พักบริการ; ปิดทั้งการ launch และ URL ตรง รวมผู้ดูแลสูงสุด |

Lesson Plan เก็บไฟล์ สิทธิ์ และข้อมูลเดิมไว้ทั้งหมด ไม่ล้าง localStorage ของผู้ใช้ ไม่เรียกหรือลบข้อมูล Google Apps Script/Cloudflare ภายนอก

## ชุดปล่อยและข้อมูล

- Release: `portal-v2-20261116-v1`, ติดตั้ง 36 ไฟล์ตาม SHA256 manifest
- เปิดใช้งานเวลา `2026-11-16T08:39:25Z` (15:39 น. ประเทศไทย)
- Host release/rollback: `/home/u651170081/releases/montien-tech/portal-v2-20261116-v1/`
- Backup ล่าสุดภายใต้ช่วงบำรุงรักษา: `/home/u651170081/backups/montien-tech/portal-v2-20261116-v1/`
- Backup ก่อน rehearsal ที่ตรวจ checksum และ restore แล้ว: `pre-portal-v2-20261116-0800`
- เปรียบเทียบทุกแถวของ **21 ตารางเดิม รวม 889 แถว** ก่อนและหลัง migration บน production: hash ตรงทั้งหมด ก่อนเปลี่ยน `app_status` ของ Lesson Plan จาก active เป็น disabled ตามคำสั่ง
- ไม่เปลี่ยน ID / password hash / บทบาทเดิม / เจ้าของงาน / เอกสารเดิมจาก migration และไม่ restore ฐานข้อมูลทับผู้ใช้
- `.env` SHA256 ก่อน–หลังตรงกัน; ใช้ไฟล์สวิตช์ `api/v2/runtime.local.json` ที่ generated และถูกปิดการเข้าถึงจากเว็บ
- ไม่มีการเขียน payload เข้า uploads, storage, vendor หรือโฟลเดอร์ backup; ไม่ลบไฟล์ build เก่า
- Rehearsal พบ Academix PDF 190/190; 5 missing references เก่ายังคงเป็นรายการเดิม ไม่ได้อ้างว่ากู้ไฟล์เหล่านั้นสำเร็จ

หลักฐานส่วนตัว (ห้าม commit): `private-audit/releases/portal-v2-20261116-v1/manifest.json`, `before-data.json`, `after-data.json`, `installed.json`, `migration.log`, `live-login.png`

## ผลตรวจ

- Hostinger PHP 8.3.36 lint/preflight 36 ไฟล์ผ่าน
- Integration 7 กลุ่มและ migration preservation 1 กลุ่มผ่านบนฐานข้อมูลจำลองใหม่
- Release rehearsal 2 กลุ่มผ่าน: backup failure/recovery และ install/rollback; regression ตรวจ directory mode 0755 ผ่าน
- พบ public code directory ถูก umask ทำให้เป็น 0700 หลังติดตั้ง แก้เฉพาะ `api/v2`, `portal-v2`, `portal-v2/assets` เป็น 0755 แล้ว และแก้ installer พร้อม regression test ไม่มีการ chmod private backup/.env
- HTTPS root, Portal HTML, CSS, JS และ bootstrap ตอบ 200; bootstrap แสดง Lesson Plan disabled+locked และ Go coming_soon ถูกต้อง
- Man API ที่ไม่ login ตอบ 401; Academix admin ที่ไม่ login redirect ไป Portal
- `.env`, `.git/config`, backups และ runtime switch ตอบ 403
- Lesson Plan URL ตรงตอบ 403 จาก Apache deny rule
- ตรวจ Chrome หน้า login จริงแล้วและบันทึกภาพ ไม่มีการสร้างสมาชิกทดสอบหรือเปลี่ยนรหัสผ่านจริงบน production

ยังไม่ได้ทดสอบการส่ง/อนุมัติรายการจริงด้วยบัญชีบุคคลจริงหลังเปิด จึงไม่อ้างว่าทุก workflow ใช้งานจริงผ่านครบ การเข้าสู่ระบบและสิทธิ์ทดสอบด้วย fixture ใน staging แล้ว บัญชีเดิมใช้รหัสผ่านเดิมและต้อง login Portal ใหม่หนึ่งครั้ง

## การปล่อยและ rollback ต่อไป

ชุดนี้ใช้คีย์ผู้ดูแลกับเครื่องมือ release ที่ตรวจทานแยกจาก CI gateway เดิม ไม่มีการขยาย allowlist หรือเปลี่ยน dedicated CI key; gateway/workflow ห้าไฟล์เดิม **ไม่ใช่เครื่องมือปล่อย Portal V2** ห้ามใช้เพื่อปล่อยรุ่นใหม่โดยไม่มีแผนปรับ workflow

เครื่องมือ `scripts/deploy/portal_v2_release.php` ใช้ deployment lock เดียวกับ gateway, ตรวจฐานโค้ด, สำรอง, migration แบบ additive, เทียบข้อมูล และเปิดสวิตช์ตอนท้าย การแก้สิทธิ์โฟลเดอร์หลังปล่อยถูกเก็บใน source ของ installer สำหรับรุ่นถัดไป; package ที่ปล่อยจริงยังคงเป็นหลักฐานเดิม

Rollback ย้อนโค้ดและปิด feature flag โดยคง Lesson Plan ปิด ไม่กู้ DB ทับข้อมูลใหม่ และปฏิเสธ legacy authorization rollback หากมีการเปลี่ยนบัญชี/สิทธิ์/ตั้งค่าหลังเปิดใช้แล้ว ให้ใช้ compatible forward fix หรือวางแผนแก้สิทธิ์อย่างชัดเจน ไม่ใช้ `git reset --hard`, `git clean` หรือ rsync --delete

## คำสั่งทดสอบ

```sh
php -l scripts/deploy/portal_v2_release.php
node --check portal-v2/assets/portal.js
python3 -m unittest discover -s tests -p 'test_portal_v2_release.py' -v
python3 -m unittest discover -s tests -p 'test_portal_v2_integration.py' -v
python3 -m unittest discover -s tests -p 'test_portal_v2_migration.py' -v
git diff --check
```

Python integration/release tests ต้องเปิด staging fixture ที่แยกจาก production ตาม `PORTAL-V2-STAGING.md` เท่านั้น
