# หลายบทบาทและการมอบหมายเจ้าหน้าที่

เปิดใช้งานจริงวันที่ 16 พฤศจิกายน 2569 ผ่าน patch `portal-multirole-20261116-v1` (16:07 น.) และ compatibility follow-up `portal-multirole-20261116-v2` (16:11 น. ประเทศไทย)

## วิธีตั้งค่าโดยผู้ดูแลสูงสุด

1. เข้า Portal → **ผู้ดูแลและสิทธิ์** เลือกสมาชิกและระบบย่อย
2. เลือกหลายบทบาท เช่น **ครูผู้สอน + ผู้ดูแลระบบย่อย** ใน Academix
3. กำหนดงานดูแลระบบ ขอบเขตแผนก และ **งานของฉัน** แยกกัน สิทธิ์ส่งงานส่วนตัวไม่จำเป็นต้องเท่ากับสิทธิ์บริหาร
4. หากต้องการให้แต่งตั้งเจ้าหน้าที่ ให้เลือก **อนุญาตผู้ดูแลระบบย่อยแต่งตั้งเจ้าหน้าที่** และเลือกงานที่อนุญาตให้ส่งต่อ โดยไม่เกินงานที่ผู้ดูแลคนนั้นมี
5. บันทึก สมาชิกกลับหน้า Portal จะเห็น **งานของฉัน** และ **งานดูแลระบบ** ของ Academix โดยไม่ Login ใหม่

ผู้ดูแลระบบย่อยที่ได้รับอนุญาตเข้าเมนู **มอบหมายเจ้าหน้าที่** เลือกระบบ สมาชิก และงานภายในเพดาน สามารถถอนการมอบหมายของตนเองได้ การเลือกสมาชิกใช้บัญชีกลางเดิม ไม่มีการสร้างบัญชีซ้ำ

**ยังไม่ได้เพิ่มหลายบทบาทหรือสิทธิ์แต่งตั้งให้สมาชิกจริงคนใดอัตโนมัติ** หลังเปิด patch ตาราง role profiles และ staff assignments ยังว่าง บทบาทและสิทธิ์เดิมใช้เป็นค่า fallback ผู้ดูแลสูงสุดเป็นผู้เริ่มกำหนดจริง

## การบังคับสิทธิ์

- ครู+ผู้ดูแล/เจ้าหน้าที่ใช้งานส่งเอกสารของตนเอง และงานดูแลตามขอบเขตได้พร้อมกัน
- ครูเข้าได้เฉพาะสิทธิ์งานส่วนตัวใน teacher routes; admin routes ตรวจสิทธิ์งานดูแลแยก ไม่รวม create/edit ส่วนตัวเข้ากับสิทธิ์บริหาร
- หน้างานแยกตาม route ไม่ใช้สถานะสลับบทบาทค้างใน session จึงเปิดงานส่วนตัวและงานดูแลคนละแท็บได้
- ผู้มอบหมายต้องเป็น direct admin ที่ได้รับอนุญาตจากผู้ดูแลสูงสุด พร้อมเพดานงานที่ส่งต่อได้
- เจ้าหน้าที่เพิ่มสิทธิ์ให้ตนเอง แต่งตั้งต่อ แต่งตั้งผู้ดูแลสูงสุด แก้ผู้ดูแลโดยตรง หรือแก้การมอบหมายของผู้ดูแลคนอื่นไม่ได้
- ตรวจบัญชีและเพดานของผู้มอบหมายใหม่ทุก request เมื่อถอนสิทธิ์/ระงับผู้มอบหมาย เจ้าหน้าที่ไม่สามารถใช้อำนาจที่อาศัยผู้มอบหมายเดิมได้ แต่สิทธิ์ส่งงานส่วนตัวเดิมยังอยู่
- การมอบหมายจำกัดขอบเขตเท่าหรือแคบกว่าของผู้ดูแล การแต่งตั้งใช้เฉพาะสมาชิก active/approved ในขอบเขตที่รับผิดชอบ
- การแก้สิทธิ์/การมอบหมายใช้ revision ป้องกันหน้าเก่าเขียนทับ พร้อม audit ผู้แต่งตั้ง ผู้รับ ระบบ งาน และขอบเขต
- Academix ห้ามตรวจ/อนุมัติ/ตีกลับงานตนเองด้วย local teacher ID (รวมกรณี username ต่างตัวพิมพ์); Go ปฏิเสธอนุมัติคำขอที่ user_id เป็นตนเอง
- รายวิชาที่เจ้าของเป็น local admin ยังคงให้ผู้ตรวจคนอื่นตรวจได้ โดยไม่ย้ายเจ้าของหรือเปลี่ยน ID
- Lesson Plan **ยังพักบริการ** ทั้งหน้า Portal และ URL ตรง; ไม่มีการเปลี่ยนบริการ Apps Script/Cloudflare
- Go คง `coming_soon` รุ่นนี้ไม่รับชุดบทบาทผู้อนุมัติหลายระดับในคนเดียว ให้เลือก admin หากต้องอนุมัติทุกระดับตาม workflow ที่มี

## การเก็บข้อมูลและ migration

Migration เลข 3 สร้างเพียง `pnp_v2_role_profiles`, `pnp_v2_staff_assignments` และเพิ่ม migration marker ไม่มี seed อำนาจใหม่ ไม่มี DELETE/UPDATE ตารางผู้ใช้ งาน เอกสาร รหัสผ่าน หรือสิทธิ์เดิม

`app_roles.role` เดิมยังเป็น compatibility role หนึ่งค่า เช่น admin แต่ roles_json เก็บหลายบทบาท และ self_rights แยกจากสิทธิ์บริหาร การมอบหมายจากผู้ดูแลเก็บแยกจากสิทธิ์โดยตรงของผู้ดูแลสูงสุด ไม่เขียนทับสิทธิ์ส่งงานเดิม

การเข้า SSO ยังคงผูก local user ด้วย username และรักษา ID หากผู้ใช้เป็นครู+ผู้ดูแลโดยตรง SSO ใช้ local teacher role สำหรับความเข้ากันได้ของหน้ารายวิชา อำนาจดูแลตรวจจาก Portal ทุก request ไม่ใช้ local role เพียงอย่างเดียว

## ผลตรวจและการปล่อยจริง

- Multi-role integration **8 กลุ่มผ่าน**: สอง workspace, เจ้าหน้าที่คงงานส่วนตัว, ห้ามขยายสิทธิ์/ขอบเขต/แต่งตั้งตนเอง, ถอนสิทธิ์ผู้มอบหมาย, ป้องกันตรวจงานตนเองรวม username ต่างตัวพิมพ์, validation, admin-owned courses, ถอนงานดูแลแต่คงงานส่วนตัว
- Regression integration เดิม **7 กลุ่มผ่าน**; migration preservation ผ่าน
- ซ้อม migration 1/Go/3 กับ backup จริงใน DB ใหม่ `pnp_v2_preflight_44e6158d_*`: ทุกแถวเดิมทั้ง 21 ตารางตรงกัน เอกสาร Academix 190/190 พบครบ; 5 missing references เก่ายังคงเดิม
- ซ้อม installer **2 กลุ่มผ่าน**: backup ล้มเหลวต้องคืนโค้ด/สวิตช์ และติดตั้งสำเร็จ/rollback ก่อนมี role assignments จริง
- Chrome ตรวจหน้าผู้ดูแลสูงสุดและผู้ดูแลระบบย่อยด้วย fixture; ฟอร์มเลือกหลายบทบาทและแยก self rights/เพดานส่งต่อแสดงถูกต้อง; ที่ 390px ไม่มีแนวนอนล้น
- Hostinger PHP 8.3.36 lint/preflight ผ่าน และยืนยัน hash โค้ดที่ติดตั้งสุดท้าย 18 ไฟล์ตรงตาม manifest
- ระหว่างปล่อยใช้ maintenance 503 ที่ตรวจผ่าน HTTPS จริง สำรองก่อนทุก patch และเทียบ fingerprint ทุกแถวของทุกตารางเดิมก่อน–หลัง ไม่มี DB restore ทับผู้ใช้
- อนุญาตเฉพาะการเพิ่ม marker 3 ในตาราง migrations; ข้อมูลอื่นของตารางเดิมตรงทั้งหมด `.env` hash ตรง และ Lesson Plan pause คงเดิม
- HTTPS Portal และ bootstrap 200, asset version ใหม่ 200, staff API ที่ไม่ Login 401, Lesson Plan ตรง 403
- ไม่มีการสร้างบัญชีทดสอบ/เปลี่ยนรหัสผ่าน/ส่งงาน/อนุมัติงานจริงใน production การทดสอบที่เขียนข้อมูลทำเฉพาะ fixture

Backups และ release/rollback อยู่บน host นอก webroot:

- `/home/u651170081/backups/montien-tech/portal-multirole-20261116-v1/`
- `/home/u651170081/backups/montien-tech/portal-multirole-20261116-v2/`
- `/home/u651170081/releases/montien-tech/portal-multirole-20261116-v1/`
- `/home/u651170081/releases/montien-tech/portal-multirole-20261116-v2/`

Private rehearsal/screenshot: `private-audit/portal-v2-stage/multirole-permissions.png` และ `private-audit/portal-v2-preflight-44e6158d/report.json` ห้ามนำ backup, `.env` หรือ private evidence ขึ้น Git

Rollback ก่อนมี profile/assignment จริงทำได้โดยคืนเฉพาะโค้ดและสวิตช์เดิม เก็บ schema/ข้อมูลไว้ทั้งหมด หากตั้งหลายบทบาทหรือมอบหมายเจ้าหน้าที่แล้ว เครื่องมือปฏิเสธการย้อนกลับไป authorization รุ่นเก่า ให้ทำ compatible forward fix ห้ามล้างตารางเพื่อฝืน rollback

## ไฟล์หลัก

- `api/v2/core.php`, `grants.php`, `index.php`, `api/portal_access.php`: role profile, workspace, delegation และ validation
- `portal-v2/`: ฟอร์มหลายบทบาท self rights/เพดานส่งต่อ และหน้ามอบหมาย
- `pnp-academix/auth.php`, `sso.php`, teacher dashboard, admin review/list/report: ตรวจตาม workspace, ป้องกันตรวจงานตนเอง และรักษารายวิชา admin เดิม
- `pnp-go/app/Controllers/DashboardController.php`: ป้องกันอนุมัติคำขอตนเอง
- `upload_serve.php`: เจ้าของงานใช้สิทธิ์ส่วนตัวเปิดเอกสารของตนเองได้ แม้ขอบเขตดูแลเป็นอีกแผนก
- `migrate-portal-v2-multirole.php`, `portal_multirole_release.php` และ tests: additive migration/ติดตั้ง/rollback

## คำสั่งทดสอบ

เปิด fixture ตาม `PORTAL-V2-STAGING.md` ก่อน แล้วใช้:

```sh
python3 -m unittest discover -s tests -p 'test_portal_v2_multirole.py' -v
python3 -m unittest discover -s tests -p 'test_portal_v2_integration.py' -v
python3 -m unittest discover -s tests -p 'test_portal_v2_migration.py' -v
python3 -m unittest discover -s tests -p 'test_portal_multirole_release.py' -v
node --check portal-v2/assets/portal.js
php -l api/v2/grants.php
git diff --check
```

Release rehearsal tests ต้องมีชุด private release และ backup rehearsal ที่ระบุ ใช้เฉพาะ MariaDB isolated socket ในเครื่อง ไม่ชี้ไป production
