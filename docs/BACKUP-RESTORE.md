# สำรองและกู้คืน

## เครื่องมือสำรอง

`scripts/maintenance/backup.php` ทำงานเฉพาะ CLI ไม่โหลด config ฐานข้อมูลของแอปที่มี auto-migration ใช้ PDO อ่านเฉพาะ metadata แล้วใช้ mysqldump สำรองสามฐานข้อมูล เก็บ routines/events/triggers ด้วย หากขั้นตอนใดล้มเหลวจะหยุดและเก็บชุดบางส่วนไว้ ไม่ลบต้นฉบับ

ชุดสำรองประกอบด้วย:

- SQL ของ Portal, PNP Go และ Academix
- webroot ทั้งหมด รวม `.git`, `.env`, รูปเดิม และ PNP Go storage
- persistent uploads ตาม `UPLOAD_BASE_PATH`
- commit, Git status, manifest และ SHA256SUMS
- client option files มีรหัสผ่านฐานข้อมูล ต้องป้องกันเหมือน `.env`

ใช้กับ Hostinger นี้เท่านั้นจนกว่าจะทบทวนค่าปริยายสำหรับโฮสต์อื่น เก็บปลายทางนอก webroot และ persistent storage โดย parent มีสิทธิ์ 700 ห้ามบันทึก backup ลง Git หรือพื้นที่เผยแพร่

ตัวอย่างรันจากเครื่องผู้ดูแล โดยสร้างชื่อปลายทางใหม่ทุกครั้ง:

```bash
ssh hostinger-montien 'umask 077; mkdir -p /home/u651170081/backups/montien-tech; php /dev/stdin /home/u651170081/domains/pnp.montien.tech/public_html /home/u651170081/backups/montien-tech/manual-$(date -u +%Y%m%dT%H%M%SZ)' < scripts/maintenance/backup.php
```

ตรวจในไดเรกทอรีชุดสำรองนั้น:

```bash
sha256sum -c SHA256SUMS
gzip -t webroot.tar.gz persistent-uploads.tar.gz
```

สถานะ `archive_checks_passed_restore_not_tested` หมายถึง archive อ่านได้และมี dump เท่านั้น ไม่ได้แปลว่ากู้คืนสำเร็จ

## ข้อจำกัดของการสำรองขณะเปิดบริการ

แต่ละ DB ใช้ `--single-transaction` แยกกัน ความสอดคล้องนี้ใช้กับตาราง transactional เช่น InnoDB เท่านั้น ดู engine ใน manifest ห้ามทำ schema change ระหว่างสำรอง ไฟล์และ DB ไม่ได้จับภาพในวินาทีเดียวกัน และไฟล์อาจเปลี่ยนระหว่าง tar; tar ผิดพลาดถือว่าชุดไม่สมบูรณ์

ก่อนย้ายข้อมูลจริงต้องจัดช่วงหยุดการเขียนที่ครอบคลุมทุกระบบ หรือใช้วิธี snapshot ที่ยืนยันความสอดคล้องได้ แล้วทำชุดสำรองสุดท้าย ไม่ควรนำ live backup ชุดนี้ไปย้อนทับ production ที่มีข้อมูลใหม่

## การทดสอบกู้คืน (ยังต้องทำ)

1. ใช้เครื่อง/บัญชีทดสอบแยกจาก production; ไม่ extract เข้า webroot จริง
2. ตรวจ SHA256 ก่อน extract ตั้งสิทธิ์ส่วนตัว และป้องกันการเข้าถึงผ่านเว็บ
3. SQL มี `CREATE DATABASE`/`USE` ชื่อ production ใช้เซิร์ฟเวอร์ MySQL ทดสอบที่แยกจริงเท่านั้น ห้าม import ลง MySQL production
4. restore ทั้งสาม DB แล้วเทียบตาราง, engine, row counts และความสัมพันธ์จากชุดสำรอง
5. เตรียม `.env` เฉพาะ staging ไม่คัดลอก credential production ไปใช้เชื่อมระบบจริง
6. ตรวจ DB file references กับไฟล์จริง รวมรูปเดิมและ URL rewrite
7. ทดสอบ SSO, เอกสารเก่า, PDF, รูป, สิทธิ์ผู้ใช้ และการส่งงานบน staging ปิดการเรียกบริการภายนอกที่อาจเขียนข้อมูลจริง
8. บันทึกผลและเวลาที่ใช้ก่อนรับรองว่า restore ผ่าน

ต้องมีสำเนานอกบัญชีโฮสต์ด้วย เก็บในพื้นที่ผู้ดูแลที่จำกัดสิทธิ์/เข้ารหัส ห้ามแชร์ archive ในแชต ไม่ตั้งลบ backup อัตโนมัติก่อนกำหนด retention ที่ผู้ใช้ยอมรับ
