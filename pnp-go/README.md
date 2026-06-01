# 🚗 ระบบขออนุญาตใช้รถยนต์/สั่งซื้อน้ำมันเชื้อเพลิง

> **วิทยาลัยการอาชีพพนมไพร** | งานพัสดุ ฝ่ายบริหารทรัพยากร

ระบบเว็บแอปพลิเคชันสำหรับบุคลากรภายในวิทยาลัย ใช้ยื่นคำขอใช้รถยนต์ราชการและสั่งซื้อน้ำมันเชื้อเพลิง พร้อมระบบอนุมัติ 3 ระดับและออก PDF อัตโนมัติ

---

## 📸 ภาพรวมระบบ

| หน้า | คำอธิบาย |
|------|----------|
| หน้าแรก | เมนูหลัก 4 ปุ่ม ใช้งานง่าย ไม่ต้อง login |
| ยื่นคำขอ | แบบฟอร์มออนไลน์ พร้อม Tracking ID |
| ตรวจสอบสถานะ | ค้นหาด้วย Tracking ID |
| สถานะรถยนต์ | ดู real-time ว่าคันไหนว่าง/ใช้งาน |
| Dashboard | อนุมัติคำขอ, สถิติ, รายงาน |
| รายงาน | พิมพ์รายงานประจำเดือน A4 Landscape |

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.x (MVC Pattern, ไม่ใช้ Framework)
- **Database:** MySQL (via PDO)
- **PDF:** mPDF library (font THSarabunNew)
- **Frontend:** Bootstrap 5.3, Vanilla CSS, SVG Icons
- **Server:** XAMPP (Apache + MySQL)

---

## 📋 ความต้องการระบบ

- PHP >= 8.0
- MySQL >= 5.7
- Apache with `mod_rewrite` enabled
- Composer

---

## 🚀 การติดตั้ง

### 1. Clone โปรเจค

```bash
git clone https://github.com/MontienNgamkaew/carrequest.git
cd carrequest
```

### 2. ติดตั้ง Dependencies

```bash
composer install
```

### 3. สร้างฐานข้อมูล

```bash
# สร้าง database และตาราง
mysql -u root carrequest < database/001_schema.sql

# เพิ่มผู้ใช้เริ่มต้น (admin, supply_head, deputy_director, director)
mysql -u root carrequest < database/002_seed_approvers.sql

# แก้ไข encoding รถยนต์ (ถ้าจำเป็น)
mysql -u root carrequest < database/003_fix_vehicle_utf8.sql
mysql -u root carrequest < database/004_fix_user_utf8.sql

# เพิ่มตารางร้านน้ำมัน
mysql -u root carrequest < database/005_fuel_vendors.sql
```

> ⚠️ ถ้า insert ข้อมูลภาษาไทย ให้ใช้ PHP script แทน MySQL CLI เนื่องจาก encoding บน Windows

### 4. ตั้งค่าระบบ

แก้ไขไฟล์ `config/app.php`:

```php
return [
    'name'      => 'ระบบขออนุญาตใช้รถยนต์',
    'base_path' => '/carrequest',   // ← เปลี่ยนตาม path จริง
    'timezone'  => 'Asia/Bangkok',
];
```

แก้ไขไฟล์ `config/database.php`:

```php
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'carrequest',
    'username' => 'root',
    'password' => '',           // ← ใส่ password ถ้ามี
    'charset'  => 'utf8mb4',
];
```

### 5. สิทธิ์ Folder

```bash
# Windows (XAMPP) — ไม่ต้องทำ
# Linux/Mac
chmod -R 775 storage/
```

### 6. เข้าใช้งาน

```
http://localhost/carrequest/
```

---

## 👥 บัญชีผู้ใช้เริ่มต้น

| Username | Password | บทบาท |
|----------|----------|-------|
| `admin` | `admin1234` | ผู้ดูแลระบบ |
| `supply_head` | `pass1234` | หัวหน้างานพัสดุ (อนุมัติ Level 1) |
| `deputy` | `pass1234` | รองผู้อำนวยการ (อนุมัติ Level 2) |
| `director` | `pass1234` | ผู้อำนวยการ (อนุมัติ Level 3) |

> ⚠️ **กรุณาเปลี่ยน password ทันทีหลังติดตั้ง**

---

## 🗂️ โครงสร้างโปรเจค

```
carrequest/
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php        # Login / Logout
│   │   ├── DashboardController.php   # Dashboard, อนุมัติ
│   │   ├── PublicController.php      # ฟอร์มขอรถ, สถานะ, สถานะรถ
│   │   ├── ReportController.php      # รายงานประจำเดือน
│   │   ├── VehicleController.php     # จัดการรถยนต์ (CRUD)
│   │   └── VendorController.php      # จัดการร้านน้ำมัน (CRUD)
│   ├── Services/
│   │   └── PdfService.php            # Generate PDF (mPDF)
│   ├── Views/
│   │   ├── admin/                    # Dashboard, รายงาน, จัดการรถ/ร้าน
│   │   ├── public/                   # ฟอร์ม, สถานะ, สถานะรถ
│   │   ├── auth/                     # Login
│   │   ├── layout.php                # Layout หลัก
│   │   └── home.php                  # หน้าแรก
│   ├── Database.php                  # PDO Singleton
│   ├── Router.php                    # Simple Router
│   └── helpers.php                   # Helper functions
├── config/
│   ├── app.php                       # ค่าตั้งค่าระบบ
│   ├── database.php                  # ค่า database
│   └── pdf.php                       # ค่า mPDF
├── database/
│   ├── 001_schema.sql                # โครงสร้างตาราง
│   ├── 002_seed_approvers.sql        # ข้อมูลผู้ใช้เริ่มต้น
│   └── 005_fuel_vendors.sql          # ตารางร้านน้ำมัน
├── public/assets/                    # CSS, JS, รูปภาพ
├── storage/
│   ├── fonts/                        # THSarabunNew, Sarabun
│   └── templates/                    # แม่แบบรูปแบบฟอร์ม
├── .htaccess                         # URL Rewriting
├── bootstrap.php                     # Autoload + Session
├── index.php                         # Entry point
└── routes.php                        # ทุก Route ของระบบ
```

---

## ✨ ฟีเจอร์หลัก

### 🔄 Flow การทำงาน

```
บุคลากร → ยื่นคำขอ → Level 1 (กำหนดรถ+คนขับ) → Level 2 → Level 3 → PDF อัตโนมัติ
```

### 📄 PDF เอกสาร (1 หน้า A4)
- **ใบขออนุญาตใช้รถยนต์** — ข้อมูลผู้ขอ, ลายเซ็นผู้อนุมัติ 3 ระดับ
- **ใบสั่งซื้อน้ำมันเชื้อเพลิง** — ชื่อร้าน, ประเภทน้ำมัน, ช่องลงนามผู้จัดการร้าน

### 🆔 Tracking ID Format
```
001 15 05 69
 │   │  │  └─ ปี พ.ศ. 2 หลัก
 │   │  └──── เดือน
 │   └─────── วัน
 └─────────── ลำดับที่ของวันนั้น (รีเซ็ตทุกวัน)
```

### 🚗 Vehicle Status Board
- แสดงสถานะรถ real-time (ว่าง / กำลังใช้งาน)
- SVG icon รถสีสันตามประเภท (รถตู้🔵 / รถหกล้อ🟠 / รถเก๋ง🟢)
- บุคลากรทั่วไปดูได้โดยไม่ต้อง login

### 📊 รายงานประจำเดือน
- เลือกเดือน → แสดงตารางการใช้รถและสั่งซื้อน้ำมัน
- พิมพ์ได้ด้วยปุ่มเดียว (A4 Landscape พร้อมลายเซ็น)

---

## 🗄️ ตารางฐานข้อมูล

| ตาราง | คำอธิบาย |
|-------|----------|
| `users` | ผู้ใช้งานระบบ (role, ลายเซ็น) |
| `vehicles` | รถยนต์ทั้งหมดในระบบ |
| `requisitions` | คำขอใช้รถ (ข้อมูลครบ) |
| `approval_logs` | ประวัติการอนุมัติทุกขั้นตอน |
| `fuel_vendors` | ร้านน้ำมัน (admin จัดการได้) |
| `fuel_requisition_items` | รายการน้ำมันแต่ละใบ |

---

## 🔗 URL หลัก

| URL | คำอธิบาย | Auth |
|-----|----------|------|
| `/` | หน้าแรก | ❌ |
| `/request` | ยื่นคำขอ | ❌ |
| `/status` | ตรวจสอบสถานะ | ❌ |
| `/vehicles` | สถานะรถยนต์ | ❌ |
| `/login` | เข้าสู่ระบบ | ❌ |
| `/dashboard` | แดชบอร์ด | ✅ |
| `/report` | รายงานประจำเดือน | ✅ |
| `/manage/vehicles` | จัดการรถยนต์ | ✅ Admin |
| `/vendors` | จัดการร้านน้ำมัน | ✅ Admin |

---

## 📝 License

สงวนลิขสิทธิ์ © 2026 วิทยาลัยการอาชีพพนมไพร  
พัฒนาโดย งานพัสดุ ฝ่ายบริหารทรัพยากร
