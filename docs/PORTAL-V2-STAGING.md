# Portal V2 — local staging, not deployed

> อัปเดต: ผู้ใช้สั่งพัก Lesson Plan แล้ว และเปิด Portal V2 สำหรับระบบที่เหลือ ดู [ผลปล่อยจริง](DEPLOYED-PORTAL-V2-20261116.md) ข้อความด้านล่างเป็นประวัติ staging/preflight ก่อนเปลี่ยนขอบเขต

สถานะ 16 พฤศจิกายน 2569: พัฒนาบน branch `codex/portal-v2-staging` เท่านั้น ไม่มีการแก้ `.env` จริง ไม่มีการ deploy หรือ migrate ฐานข้อมูล production

## สิ่งที่ทำ

- UI ทางการสีกรมท่า/ขาว/เขียว รองรับมือถือ: ภาพรวม บัญชี ระบบย่อย สิทธิ์ ประวัติ ตั้งค่า และบัญชีส่วนตัว
- สมัครสมาชิกแบบรออนุมัติ ไม่รับสิทธิ์ admin จากฟอร์มสมัคร
- ผู้ดูแลสูงสุดเพิ่ม/แก้ไข/ระงับ/เก็บเข้าคลัง/กู้คืนบัญชี อนุมัติสมาชิก และตั้งรหัสผ่านชั่วคราว
- เก็บเข้าคลังแทนลบแถว จึงรักษา ID และประวัติงาน; ป้องกันระงับตนเองและการถอนผู้ดูแลสูงสุดคนสุดท้าย
- รีเซ็ตรหัสผ่านบังคับเปลี่ยนครั้งถัดไป พร้อมยกเลิก session เดิม; ไม่อ่านหรือแสดงรหัสผ่านเดิม
- คำขอลืมรหัสผ่านเข้าคิวให้ผู้ดูแลตรวจสอบ ยังไม่มีบริการส่งอีเมลอัตโนมัติ
- สิทธิ์ view/create/edit/approve/export/manage_users/manage_settings แยกตามระบบ พร้อม revision ป้องกันการเขียนทับสิทธิ์จากหน้าจอเก่า
- ขอบเขตทั้งองค์กร/แผนกใน Academix และ Man; Go ปฏิเสธขอบเขตแผนกจนกว่าจะรองรับ workflow ครบ
- opaque cookie session ส่วนกลาง เก็บเฉพาะ hash ใน DB, HttpOnly/SameSite, CSRF, rate limit, บันทึก audit; ตรวจบัญชี/สิทธิ์ใหม่ทุก request ในระบบ PHP ที่ผูก adapter
- Academix/Go ใช้ cookie เข้า SSO ไม่ส่ง JWT ใน URL; ผูกบัญชีเดิมด้วย username และรักษา local ID
- Man API ตรวจสิทธิ์/CSRF และกรองบุคลากรตามขอบเขต; ย้ายการจัดการบัญชีไป Portal
- ปิดงานลบถาวร/ล้าง/เขียนทับชุดข้อมูลในโหมด V2 รวมถึง legacy bulk Go sync
- upload proxy ตรวจบัญชีและเจ้าของ/ขอบเขต ปิดการเข้าถึงตรงของไฟล์เดิมผ่าน rewrite
- `PORTAL_V2_ENABLED` ปิดโดยค่าเริ่มต้น เพื่อให้ระบบเดิมทำงานระหว่างพัฒนา

## ขอบเขตไฟล์

- `portal-v2/`: หน้าและสไตล์ใหม่
- `api/v2/`: authentication, สมาชิก, สิทธิ์, สถานะระบบ, audit
- `api/portal_access.php`: adapter สำหรับระบบย่อย
- `pnp-academix/`, `pnp-go/`, `pnpman/`: ตรวจ cookie ส่วนกลาง สิทธิ์ และขอบเขต
- `pnp-lessonplan/src/`: ใช้ session กลางและตรวจสิทธิ์ก่อนเรียก AI ผ่าน hooks ที่ปรับแล้ว
- `.htaccess`, `upload_serve.php`: ปิดไฟล์ภายในและป้องกันไฟล์อัปโหลด
- `scripts/maintenance/migrate-portal-v2*.php`: migration แบบ preview ก่อน apply
- `scripts/maintenance/serve-portal-v2.py`, `build-portal-v2-stage.mjs`: staging ส่วนตัว
- `tests/test_portal_v2_*.py`: integration และ migration preservation

## วิธีทดสอบในเครื่อง

ต้องมี private MariaDB instance ที่ restore ไว้เดิม (Unix socket, TCP ปิด) และ manifest backup เดิม สคริปต์ setup ตรวจ datadir ก่อนสร้างฐานข้อมูลใหม่ชื่อ `pnp_v2_stage_<random>_*`; คัดลอกเฉพาะโครงสร้าง ไม่คัดลอกข้อมูลบุคคล ไม่ drop ฐานข้อมูลเดิม ทุกครั้งที่ setup เก็บ config เดิมไว้

```sh
python3 scripts/maintenance/serve-portal-v2.py --setup
node scripts/maintenance/build-portal-v2-stage.mjs
python3 scripts/maintenance/serve-portal-v2.py
```

เปิด `http://127.0.0.1:18767/pnp-portal/portal-v2/` บัญชีทดสอบ `stage_admin`, `stage_member`, `stage_reviewer`, `stage_other`, `stage_viewer` รหัสผ่านสุ่มอยู่ในไฟล์ ignored `private-audit/portal-v2-stage/stage.json` เท่านั้น อย่าส่งไฟล์นี้ขึ้น Git

```sh
python3 -m unittest discover -s tests -p 'test_portal_v2_integration.py' -v
python3 -m unittest discover -s tests -p 'test_portal_v2_migration.py' -v
python3 -m unittest discover -s tests -p 'test_deploy_gateway.py'
node --check portal-v2/assets/portal.js
```

การรัน integration หลายครั้งติดกันอาจติด rate limit จริงของระบบ ให้สร้าง fixture ชุดใหม่ด้วย `--setup` และ restart preview แทนการลดการป้องกัน login; ห้ามรันทดสอบกับ production

Build ลง private staging โดยไม่ล้าง tracked dist และไม่อ่าน `.env` จริง; ต้อง build artifacts ที่ผ่านตรวจใหม่ก่อน release จริง

## ผลตรวจ

- Integration 7 กลุ่มผ่าน: CSRF/auth, master isolation/self protection, signup/approval/grant/revision/reset/forced change/archive/restore, view-only, app disabled, audit ไม่มี password hash, SSO/no URL JWT/Man department filtering/legacy admin API disabled/logout ข้ามระบบ
- Migration preservation 1 กลุ่มผ่าน: apply ซ้ำแล้วแถว users ของทั้งสามฐานข้อมูล (รวม ID และ password hash) ไม่เปลี่ยน
- Gateway เดิม 12 tests ผ่าน
- React builds ของ Man และ Lesson Plan ผ่าน; มีคำเตือน chunk ใหญ่, Browserslist เก่า และ static/dynamic import ซ้ำซึ่งต้องจัดการด้าน performance ภายหลัง
- UI ตรวจด้วย Chrome ที่ desktop และ 390×844; หน้าสิทธิ์ไม่ล้นแนวนอน เมนูมือถือเปิดได้
- PHP lint และ JS syntax ผ่านสำหรับชุดที่แก้ในรอบตรวจ

## ยังไม่อนุญาตให้ใช้ workflow deploy เดิมกับ V2

Gateway production ปัจจุบัน allowlist เฉพาะ PHP safety fix 5 ไฟล์ ไม่รองรับชุดนี้ การ deploy ชุด V2 ต้องเป็น release แยกพร้อม backup ใหม่, schema preview, manifest ครบทุกไฟล์, build artifacts, ตรวจ diff และแผน rollback ที่รักษาข้อมูลใหม่หลังเปิดใช้

ก่อนเปิดจริงต้องตรวจเพิ่มเติม:

1. Lesson Plan ใช้บริการ AI/บริการจัดการภายนอกบางส่วน การซ่อนหน้าหรือเช็กสิทธิ์ใน browser ยังไม่เท่ากับ authorization ฝั่งบริการ ต้องย้าย/เชื่อม API ภายนอกเข้าตัวตรวจสิทธิ์ส่วนกลางก่อนประกาศ SSO ครบถ้วน ห้ามฝัง provider secret ใน bundle
2. ทดสอบไฟล์จริงแบบ synthetic ทั้งเจ้าของ/นอกแผนก/URL ตรง/ทุกประเภทไฟล์ และ rewrite ด้วย Apache/Hostinger จริง; PHP preview router ไม่ใช่ Apache
3. ทดสอบลำดับอนุมัติ Go ทุกบทบาท การส่งเอกสาร Academix ทุก action และผลกระทบหน้างานจริง; driver ใช้ local Go role user ใน V2 เพื่อไม่รับสิทธิ์ approver จากการตรวจ role แบบเดิม
4. ตรวจ UI ของระบบย่อยให้ซ่อนปุ่มที่ไม่มีสิทธิ์อย่างสอดคล้อง ขณะนี้ server guard อาจปฏิเสธเมื่อกดปุ่มเดิมบางปุ่ม
5. ย้าย self-healing DDL จาก request path ของระบบเดิมออกเป็น explicit migration, ตรวจ maintenance/diagnostic endpoints และ Apache restrictions ให้ครบ
6. ตรวจข้อมูล username ซ้ำ/ผูกบัญชีเดิม/department และทดสอบ migration กับสำเนาข้อมูลจริงที่แยกจาก production ก่อนวันย้าย
7. ทดสอบ legacy mode เมื่อ flag ปิดและ rollback rehearsal ก่อนเปิดใช้งาน; flag ปิดไม่ลบตาราง V2 แต่คืน legacy authority semantics จึงต้องทบทวนการใช้ rollback หลังมีการเปลี่ยนสิทธิ์/เก็บบัญชีเข้าคลังแล้ว
8. เพิ่ม MFA สำหรับผู้ดูแลสูงสุด/ช่องทางยืนยันตัวตนก่อน reset password และแนวทางส่งรหัสชั่วคราวก่อนใช้งานจริงตามนโยบายหน่วยงาน

Migration ส่วนกลางเป็น additive: สร้างตาราง metadata ใหม่และ INSERT IGNORE ไม่มี DELETE/UPDATE users เดิม Migration Go เพิ่มค่า enum ที่ขาดโดยรักษาค่าเดิมและ default/nullability ไม่มีการเปลี่ยนแถวผู้ใช้ แต่ ALTER อาจ lock ตาราง ต้องทำในหน้าต่างบำรุงรักษาหลัง backup
