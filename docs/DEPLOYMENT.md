# Deploy ระบบที่เปิดใช้งานแล้ว

## สถานะที่พบ

- repo เครื่องและ production: `b56390d` ณ เริ่มสำรวจ
- production: detached HEAD, มี `uploads/` นอก Git
- ปิด auto-deploy ใน hPanel GIT แล้ววันที่ 16 พฤศจิกายน 2569 ตามคำสั่งผู้ใช้ โดยคง repo, branch `main` และ root `public_html` ไว้ UI ยืนยันว่าการเปลี่ยนแปลงจาก Git จะไม่ถูกปรับใช้โดยอัตโนมัติ
- production CLI เป็น PHP 8.3.36; ชุดปล่อย 5 ไฟล์ผ่าน syntax check ด้วยเวอร์ชันนี้แล้ว
- หลังปล่อย gateway บันทึก live source เป็น `9ddd74a`; Git checkout เดิมยัง HEAD `b56390d` และห้าไฟล์ modified ตามแผน ไม่ต้องทำให้ status สะอาดด้วย reset/checkout

ห้าม push เพื่อทดลองว่า auto-deploy ทำงานหรือไม่ ตรวจ hPanel ก่อนเลือกวิธี deploy หลักเพียงทางเดียว ห้าม `git clean`, `reset --hard`, fresh checkout ทับ runtime หรือ rsync แบบลบปลายทาง

ชุดที่เตรียมล่าสุดและเงื่อนไขก่อนปล่อย: [RELEASE-20261116.md](RELEASE-20261116.md)

ปล่อยผ่าน manual GitHub Actions สำเร็จแล้ว ดู [ผลปล่อยจริง](DEPLOYED-20261116.md) และใช้ [คู่มือ workflow](MANUAL-DEPLOY.md) สำหรับครั้งต่อไป

หลังปิด auto-deploy ให้ใช้ GitHub เก็บเวอร์ชันและตรวจทานโค้ด แล้วสั่ง deploy เองหลัง backup/preflight ปัจจุบันตั้ง workflow แบบ `workflow_dispatch` ให้โปรเจกต์นี้แล้ว ใช้คีย์เฉพาะใหม่ ไม่คัดลอก credential ข้ามโปรเจกต์

## ก่อน deploy

1. ตรวจ diff และ Git status ทั้ง local/remote ไม่ทับงานที่มีอยู่
2. ตรวจ backup ล่าสุดและทดสอบ restore ตามคู่มือ
3. ยืนยันว่ารายการ deploy ไม่แทนที่ `.env`, uploads, storage และข้อมูลนอก Git
4. ทดสอบบน staging ด้วย DB แยก; schema/seed ไม่ใช่คำสั่งติดตั้งสำหรับฐานข้อมูลจริง
5. การแก้ตารางต้องแยกเป็น migration มีเลขเวอร์ชัน ใช้วิธีเพิ่มก่อน แล้วค่อยเลิกของเก่าภายหลัง
6. หากแก้ React ให้ build ในระบบทดสอบและตรวจ diff ของ dist ไม่ build ทับ production ขณะมีผู้ใช้

## ตรวจแบบไม่เขียนข้อมูล

```bash
git status --short --branch
php -l scripts/maintenance/backup.php
ssh hostinger-montien 'git -C /home/u651170081/domains/pnp.montien.tech/public_html status --short --branch'
```

อย่าใช้การเปิดหน้า PHP หรือ require config เป็น health check แบบ read-only เพราะ `api/database.php`, `pnpman/api/db.php`, `pnp-academix/config.php`, `pnp-go/app/Database.php` อาจสร้าง/แก้ตารางหรือเพิ่มข้อมูล

## Rollback

ย้อนเฉพาะโค้ดที่รองรับ schema ปัจจุบันก่อน ห้าม restore DB ทั้งชุดทับข้อมูลใหม่เพื่อย้อน UI หากจำเป็นต้องกู้ข้อมูล ให้สำรองสถานะปัจจุบันก่อนและวางแผนรวมข้อมูลที่เกิดหลัง backup การเปลี่ยน URL/โดเมนต้องคำนึงถึง localStorage ของ Lesson Plan ด้วย
