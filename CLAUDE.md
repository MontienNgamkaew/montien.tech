# PNP e-Service — คู่มือสถาปัตยกรรมและแนวทางพัฒนา

ระบบบริการดิจิทัล **ศูนย์บริการดิจิทัล วิทยาลัยการอาชีพพนมไพร** (PNP e-Service)
เป็นสถาปัตยกรรมแบบ **Portal กลาง + ระบบย่อยหลายระบบ** ที่เข้าใช้งานผ่าน Single Sign-On (SSO)

> เอกสารนี้สำหรับนักพัฒนา (และ Claude) อ่านก่อนเริ่มงาน เพื่อให้ทุกระบบใหม่ทำตามแนวทางเดียวกัน

---

## 1. ภาพรวมระบบ

| ส่วน | ที่อยู่ | หน้าที่ | เทคโนโลยี | ฐานข้อมูล |
|------|--------|---------|-----------|-----------|
| **Portal** | `/` (`index.html`, `script.js`, `admin.html`) | ล็อกอินกลาง + จัดการผู้ใช้/สิทธิ์ | HTML + Vanilla JS | MySQL `pnp_portal` |
| **Central Auth API** | `/api/` | ออก/ตรวจ JWT, login, users, app_roles | PHP (ไม่มี framework) | MySQL `pnp_portal` |
| **pnp-go** | `/pnp-go/` | ขอใช้รถยนต์/น้ำมัน อนุมัติ 3 ระดับ + PDF | PHP MVC | MySQL `carrequest` (+ อ่าน `pnp_portal`) |
| **pnpman** | `/pnpman/` | จัดโครงสร้างบุคลากร/ผังภาระงาน (drag&drop) | React + Vite (frontend) + PHP (api) | MySQL `pnp_portal` (ตารางร่วม) |
| **pnp-academic** | external `pnp-edu.montien.tech` | (กำลังพัฒนา) | — | — |

**สภาพแวดล้อม:** XAMPP (Apache + MySQL + PHP 8.x) บนเครื่อง local / Hostinger บน production
โดเมน production: `pnp-portal.montien.tech`, `pnp-go.montien.tech`, `pnp-man.montien.tech`, `pnp-edu.montien.tech`

---

## 2. การยืนยันตัวตน (SSO ด้วย JWT) — สำคัญที่สุด

ทุกระบบย่อย **ต้อง** ใช้รูปแบบเดียวกันนี้ ห้ามสร้างระบบ login แยกของตัวเอง

### ขั้นตอน
1. ผู้ใช้ล็อกอินที่ Portal → `api/login.php` ตรวจรหัสผ่าน แล้วออก **JWT** (เซ็นด้วย `JWT_SECRET_KEY`)
   payload ประกอบด้วย: `user_id`, `username`, `email`, `title`, `first_name`, `last_name`,
   `primary_position`, `org_position`, `roles` (map ราย app), `is_portal_admin`, `avatar`, `exp`
2. Portal (`script.js`) สร้างลิงก์ไประบบย่อยพร้อมแนบ token: `<base>?token=<JWT>`
3. ระบบย่อยรับ token แล้วตรวจด้วย **คีย์ลับเดียวกัน** (`JWT::decode($token, JWT_SECRET_KEY)`)
   - **pnp-go**: `sso.php` ตรวจ token จาก URL → สร้าง PHP session → redirect เข้า dashboard
   - **pnpman**: `App.jsx` อ่าน `?token=` → เก็บ `localStorage.adminToken` → แนบ `Authorization: Bearer <JWT>`
     ทุกคำขอ; ฝั่ง API ตรวจด้วย `isCurrentAdmin()` ใน `pnpman/api/db.php`

### ตัวช่วยกลาง (Shared SSO SDK) — `api/sso_auth.php`
ใช้ไฟล์นี้แทนการ copy โค้ดถอด JWT ไปแต่ละระบบ:
- `pnp_bearer_token()` — ดึง token จาก `Authorization` header (รองรับ Apache/Nginx/redirect)
- `pnp_auth_payload()` — ถอด + ตรวจ JWT คืน payload (หรือ `null`)
- `pnp_auth_user_id()` — คืน `user_id` ที่ยืนยันแล้ว (หรือ `null`)

JWT engine อยู่ที่ `api/jwt.php` (HS256, dependency-free)

### การแมปสิทธิ์
- ตาราง `app_roles (user_id, app_id, role)` เก็บสิทธิ์รายระบบ — `app_id` เช่น `pnp-go`, `pnp-man`, `pnp-academic`
- `is_portal_admin = 1` → เป็น admin ของทุกระบบโดยอัตโนมัติ
- **pnp-go** แมประดับอนุมัติเพิ่มจาก `primary_position`/`org_position` (ดู `pnp-go/sso.php`):
  `director` → `deputy_director` (ฝ่ายบริหารทรัพยากร) → `supply_head` (หัวหน้างานพัสดุ) → `user`
  > ⚠️ การแมปนี้อ่อนไหวต่อการจับคู่สตริงภาษาไทย (เช่น "รองผู้อำนวยการ" vs "ผู้อำนวยการ") — แก้ไขด้วยความระมัดระวัง และทดสอบทุกระดับ
- **ผู้อนุมัติ pnp-go มาจากบอร์ด pnpman อัตโนมัติ:** `api/login.php` คำนวณ `org_position` จากตาราง `assignments` (pnpman) ปีล่าสุดก่อน แล้ว fallback ไป `user_org_assignments` (หน้า admin portal) → การแต่งตั้งในบอร์ด pnpman กำหนดผู้อนุมัติ 3 ระดับของ pnp-go:
  `ผู้อำนวยการวิทยาลัย`→director, งาน `รองผู้อำนวยการฝ่ายบริหารทรัพยากร`→deputy_director, `หัวหน้างาน` ของงาน `งานพัสดุ`→supply_head
  (JWT คำนวณตอน login → เปลี่ยนตำแหน่งแล้วผู้ใช้ต้อง login ใหม่จึงมีผล)
- pnp-go เข้าใช้งานผ่าน SSO เท่านั้น (ไม่มี local login แล้ว)

---

## 3. ความลับและการตั้งค่า (Secrets / .env)

**ห้าม hardcode รหัสผ่าน/คีย์ลับลงในไฟล์ที่ commit ขึ้น git เด็ดขาด**

- ค่าลับทั้งหมดอยู่ในไฟล์ `.env` ที่ราก repo (ถูก `.gitignore` แล้ว) — ดูแม่แบบที่ `.env.example`
- โหลดผ่านตัวช่วย `api/env.php`: `env('KEY', $default)`
  ลำดับความสำคัญ: ตัวแปรสภาพแวดล้อมจริง → ไฟล์ `.env` → ค่า default
- `api/config.php` และ `api/database.php` อ่านค่าลับจาก `.env` (JWT key, CORS, รหัส DB)
- **pnp-go** อ่านจาก `.env` (คีย์ `PNPGO_DB_*`, `DB_*`) และยังรองรับ `pnp-go/config/database.local.php` เป็น override

คีย์สำคัญใน `.env`:
- `JWT_SECRET_KEY` — ต้องเป็นค่าเดียวกันทุกระบบ (มิฉะนั้น SSO พัง)
- `DB_HOST/DB_NAME/DB_USER/DB_PASS` — ฐานข้อมูล Portal กลาง
- `PNPGO_DB_*` — ฐานข้อมูล pnp-go (`carrequest`)
- `CORS_ALLOWED_ORIGINS` — รายการ origin คั่นจุลภาค (ว่าง/`*` = อนุญาตทุก origin)
- `MAINTENANCE_KEY` — คีย์ลับสำหรับสคริปต์บำรุงรักษา (เช่น `pnp-go/sync_portal_users.php?key=...`)

---

## 4. การ Deploy (Production = git pull บน Hostinger)

> ⚠️ **ก่อน deploy ครั้งแรกหลังเปลี่ยนมาใช้ `.env`:** ต้องสร้างไฟล์ `.env` ที่ราก `pnp-portal/` บนเซิร์ฟเวอร์ก่อน (ไฟล์นี้ไม่อยู่ใน git) มิฉะนั้นระบบจะเชื่อมต่อฐานข้อมูลไม่ได้

ขั้นตอน:
1. สร้าง/อัปเดต `.env` บนเซิร์ฟเวอร์ (คัดลอกจาก `.env.example` แล้วกรอกค่าจริงของ production)
   - `JWT_SECRET_KEY=` (ค่าปัจจุบันบน production)
   - `DB_PASS=` และ `PNPGO_DB_PASS=` (รหัสผ่าน Hostinger จริง)
   - แนะนำตั้ง `CORS_ALLOWED_ORIGINS` เป็นรายการโดเมน `*.montien.tech`
2. `git pull` บนเซิร์ฟเวอร์
3. ถ้าแก้ frontend ของ pnpman: build ใหม่ใน `pnpman/frontend/` (`npm run build`) — ผลลัพธ์อยู่ใน `dist/` (track ใน git)
4. **pnp-go ใช้ Composer (mPDF):** `vendor/` ถูก `.gitignore` (ไม่ขึ้น git) — ต้องรัน `composer install` บนเซิร์ฟเวอร์เอง มิฉะนั้น PDF จะพัง (`Class "Mpdf\…" not found`)
   - ครั้งแรก/หลังย้ายเซิร์ฟเวอร์: `cd <path>/pnp-go && composer install --no-dev --optimize-autoloader`
   - `composer.json` + `composer.lock` track ใน git → ติดตั้ง version ตรงกับ local
   - `git pull` ปกติ **ไม่ต้อง** รัน composer ซ้ำ — ยกเว้นเมื่อแก้ `composer.json` (เพิ่ม/เปลี่ยน dependency)

### การหมุน (rotate) ความลับ — ทำเมื่อพร้อม
- เปลี่ยน `JWT_SECRET_KEY` → ผู้ใช้ทุกคนจะถูก logout (token เดิมใช้ไม่ได้) — แจ้งผู้ใช้ก่อน
- เปลี่ยนรหัสผ่าน DB → ต้องเปลี่ยนใน hPanel แล้วอัปเดต `.env` ให้ตรง
- หมายเหตุ: ความลับเดิมยังอยู่ในประวัติ git (ไม่ได้ rewrite) — การหมุนคีย์ทำให้ของเก่าใช้ไม่ได้

---

## 5. แนวทางสำหรับระบบย่อยใหม่ (Conventions)

1. **ห้ามสร้าง login เอง** — รับ JWT จาก Portal ผ่าน `?token=` แล้วตรวจด้วย `api/sso_auth.php`
2. เพิ่ม `app_id` ใหม่ในตาราง `app_roles` และเพิ่มการ์ดใน `script.js` (`appLinks`) + `index.html`
3. อ่านความลับจาก `.env` ผ่าน `api/env.php` เท่านั้น
4. รูปแบบ response API: JSON `{ "status": "success"|"error", "message": ..., ... }`
5. ฐานข้อมูลใช้ MySQL/PDO + prepared statements เสมอ (กัน SQL injection)
6. ผู้ใช้ทั้งหมดเป็นข้อมูลร่วมในตาราง `pnp_portal.users` — อย่าสร้างตารางผู้ใช้ซ้ำ
7. **ห้ามวางสคริปต์บำรุงรักษา/ดีบักที่เข้าถึงผ่านเว็บโดยไม่มีการป้องกัน** — สคริปต์ที่แก้ DB/รหัสผ่านต้องมีการ์ด (CLI-only หรือ `MAINTENANCE_KEY`)

### การจัดการสคีมาฐานข้อมูล
- สคีมาอ้างอิงอยู่ใน `pnp-go/database/*.sql` และ `pnpman/database/schema.sql` (ไม่เปิดผ่านเว็บ)
- ตารางถูกสร้าง/ซ่อมอัตโนมัติแบบ **self-healing** ตอนเชื่อม DB (ดู `api/database.php`, `pnp-go/app/Database.php`, `pnpman/api/db.php`) — เพิ่มคอลัมน์/ตารางใหม่ที่นี่ ไม่ต้องเขียนสคริปต์ migrate แยก

---

## 6. หนี้ทางเทคนิคที่ค้างอยู่ (TODO / รู้ไว้)

- **JWT ส่งผ่าน URL** (`?token=`) — เสี่ยงรั่วใน log/history ควรเปลี่ยนเป็น one-time code แลก session (ยังไม่ทำ เพราะกระทบ flow ทุกระบบ)
- **ไม่มีกลไก revoke JWT** และอายุ token ยาว 7 วัน
- **การแมปสิทธิ์จากสตริงตำแหน่ง** ใน `pnp-go/sso.php` เปราะบาง ควรย้ายมาใช้ตารางสิทธิ์ให้หมด
- มีคอลัมน์ `users.auth_token` (จากระบบ login เก่าของ pnpman ที่ถอดออกแล้ว) — เลิกใช้ ปล่อยไว้ได้ ไม่กระทบ
- (เก็บกวาดแล้ว) สคริปต์ migrate/repair/debug แบบ one-off ที่เข้าถึงผ่านเว็บถูกลบออก และ `sync_portal_users.php` ใส่การ์ด `MAINTENANCE_KEY` แล้ว
