# ตำแหน่งข้อมูล

ค่าที่ตรวจบน production วันที่ 16 พฤศจิกายน 2569:

```text
/home/u651170081/domains/pnp.montien.tech/
├── public_html/                  # โค้ด + .env + ข้อมูลเก่าบางส่วน
│   ├── uploads/                  # รูป Portal (เขียนที่นี่อยู่)
│   ├── pnpman/uploads/           # รูปเดิมที่ติด Git
│   ├── pnp-academix/uploads/     # branding เดิมที่ติด Git
│   └── pnp-go/storage/           # ฟอนต์/แม่แบบ + runtime
└── pnp_uploads/                  # UPLOAD_BASE_PATH ที่ใช้งานจริง
    ├── pnp-academix/uploads/     # เอกสารและ branding ใหม่
    └── pnpman/                   # จุดเขียนใหม่ของ PNP Man
```

อย่าสับสนกับ `/home/u651170081/pnp_uploads/` ซึ่งมีโฟลเดอร์เก่าแต่ไม่ใช่ค่าที่ `.env` ของเว็บนี้ใช้อยู่

| ตำแหน่ง | ผลสำรวจเบื้องต้น |
|---|---|
| persistent Academix | 194 ไฟล์ ประมาณ 219 MB |
| persistent PNP Man | 0 ไฟล์ |
| Portal `uploads/` | 1 ไฟล์ ประมาณ 1.5 MB |
| Academix ใน webroot | 3 ไฟล์ รวม `.gitkeep` |
| PNP Man ใน webroot | 11 ไฟล์ ติดตามใน Git |
| PNP Go storage | 7 ไฟล์ รวมไฟล์โค้ด/แม่แบบ/ฟอนต์/session; จำนวนเปลี่ยนได้ |

จำนวนเหล่านี้ไม่ใช่หลักฐานว่า DB อ้างไฟล์ครบ ต้องตรวจการอ้างอิงแยกต่างหาก และไม่ควรแสดงชื่อไฟล์ส่วนบุคคลในรายงานสาธารณะ

## ข้อมูลนอกไฟล์บนโฮสต์

- MySQL: Portal/Man, PNP Go, Academix ต้องสำรองทั้งสาม
- Lesson Plan: `usePersistedState` บันทึกแบบฟอร์มและผลลัพธ์หลายส่วนในเบราว์เซอร์ ผู้ใช้ต้องส่งออกข้อมูลก่อนย้าย origin/ล้าง storage; backup โฮสต์ไม่ครอบคลุม
- Lesson Plan มี Google Apps Script สำหรับสถิติและ proxy/training services ภายนอก ต้องแยกตรวจเจ้าของและวิธีสำรอง โดยไม่คัดลอก token ลงเอกสาร

## หลักการย้ายในระยะถัดไป

1. สร้าง inventory พาธและ checksum ทั้งต้นทาง/ปลายทาง รวมรายการจาก DB แบบ SELECT
2. คัดลอกแบบไม่เขียนทับเมื่อชื่อชน; ห้ามใช้ `mv`, `rsync --delete` หรือ cleanup
3. จุดอ่านต้องรองรับไฟล์เดิมก่อนเปลี่ยนจุดเขียน และตรวจสิทธิ์ดาวน์โหลด
4. หากพบชื่อเหมือนแต่ checksum ต่างกัน ให้หยุดรายการนั้นเพื่อหาความสัมพันธ์กับ DB
5. ทดสอบการเปิด URL เก่าและไฟล์ใหม่บน staging
6. เก็บไฟล์ต้นทางไว้จนพ้นช่วงตรวจรับ; การลบต้องได้รับอนุญาตแยก

`.gitignore` ไม่หยุดติดตามไฟล์ที่เคย commit แล้ว ห้ามใช้ `git rm` กับรูปเดิมก่อนมีแผนรองรับไฟล์และ URL
