# อัปเดต: ทางเข้าหน้าแรกสำหรับผู้ดูแล Academix

เพิ่มเมนู “หน้าแรกและเวลาถอยหลัง” บน Dashboard และ “หน้าแรก” ในแถบนำทางแล้ว ผู้ดูแลอย่างเดียวเข้าได้ตามสิทธิ์เดิม ดู [ผลตรวจและไฟล์ที่ปรับ](ACADEMIX-CAT-COUNTDOWN.md) ข้อมูล 31 ตารางเดิมคงเดิมหลังติดตั้ง

# อัปเดต: หน้าแรก Academix นับถอยหลังและแมวทำงาน

เปิดหน้าแรกธีมแก้วเข้ม พร้อมเวลาถอยหลังสามประเภท แมวเร่งมือตามเวลาที่เหลือและมึนหัวเมื่อครบกำหนดแล้ว ข้อมูลเดิมทั้ง 31 ตารางคงเดิม ดู [ผลตรวจและคำสั่งทดสอบ](ACADEMIX-CAT-COUNTDOWN.md)

# อัปเดต: Academix Glass Dashboard

เปิดธีมแก้วโทนน้ำเงิน–ม่วง–ฟ้า พร้อมการ์ดตัวเลข 4 ใบและกราฟเอกสารสามประเภทแล้ว เปลี่ยนเฉพาะไฟล์แสดงผล 3 ไฟล์ ข้อมูลเดิมทั้ง 31 ตารางคงเดิม ดู [ผลตรวจ ไฟล์ที่ปรับ และคำสั่งทดสอบ](ACADEMIX-GLASS-DASHBOARD.md)

# อัปเดต: หน้าผู้ดูแล Academix และการตรวจพิจารณา

เปิดภาพรวมสามประเภท หน้ารายวิชาครู และ Preview พร้อมแบบพิจารณาตามสิทธิ์จริงแล้ว เพิ่มการป้องกันหน้าค้างเขียนทับงานฉบับใหม่ ข้อมูลเดิมทั้ง 31 ตารางคงเดิมหลังติดตั้ง ดู [วิธีใช้ ผลทดสอบ และ backup](ACADEMIX-ADMIN-UI-AND-REVIEW.md)

# อัปเดต: Academix UI และ Preview

เปิดหน้าแรกและหน้าครูรูปแบบใหม่แล้ว แยกสถานะงานสามประเภท พร้อม Preview ที่ตรวจเจ้าของงาน ข้อมูลทั้ง 31 ตารางเดิมคงเดิม ดู [วิธีใช้ ผลทดสอบ และหลักฐานติดตั้ง](ACADEMIX-UI-AND-PREVIEW.md)

# อัปเดต: สิทธิ์ครู Academix อัตโนมัติ

ครูที่ยืนยันแล้วใช้สามงานเดิมและดูผลของตนเองได้อัตโนมัติ เปิดใช้จริงแล้ว ดู [กติกา ผลตรวจ และวิธีทดสอบ](ACADEMIX-AUTOMATIC-TEACHER-ACCESS.md) งานวิจัยรอเพิ่มภายหลัง และ PNP Lesson Plan ยังพักบริการ

# อัปเดต: หลายบทบาทและเจ้าหน้าที่

เปิดใช้งานจริงแล้ว ดู [วิธีใช้/ผลตรวจ/backup/rollback](MULTIROLE-AND-DELEGATION.md) โดยยังคงพัก Lesson Plan

# อัปเดตล่าสุด: Portal V2

เปิด Portal V2 จริงแล้วและพัก Lesson Plan ตามคำสั่งผู้ใช้ ดู [ผลปล่อยและข้อจำกัดปัจจุบัน](DEPLOYED-PORTAL-V2-20261116.md) สถานะด้านล่างเป็นประวัติชุด safety fix ก่อนหน้า

# ผลดำเนินการ 16 พฤศจิกายน 2569

## สถานะล่าสุด

ตั้ง manual GitHub Actions และปล่อย PHP 5 ไฟล์ขึ้น production สำเร็จแล้วด้วย source `9ddd74a` หลัง backup/checksum ผ่าน ตรวจไฟล์ผู้ใช้และ `.env` เทียบกับ backup ไม่เปลี่ยน ดู [ผลปล่อยจริง](DEPLOYED-20261116.md) ส่วนบันทึกด้านล่างเป็นลำดับการเตรียมก่อนปล่อย

## ทำแล้ว

- สำรองฐานข้อมูล production ผ่าน mysqldump โดยไม่โหลด bootstrap ของแอป: Portal 8, PNP Go 7, Academix 6 ตาราง ทุกตารางที่ตรวจเป็น InnoDB
- สำรอง webroot รวม `.env`, Git metadata และข้อมูลเก่าภายในเว็บ
- สำรอง persistent uploads ตามค่าจริงใน `.env`
- ตรวจ gzip, tar listing และ SHA256 บน Hostinger ผ่าน
- ดาวน์โหลดสำเนามาเครื่องผู้ดูแลและตรวจ SHA256 ผ่านทุกไฟล์
- ทดลองแตก archive ในพื้นที่ส่วนตัวบนเครื่องผ่าน: webroot 950 ไฟล์, persistent uploads 194 ไฟล์
- ตรวจ dump ทั้งสามมี CREATE TABLE จำนวนตรง metadata และมีบรรทัดจบ dump (ยังไม่ใช่การตรวจ import)
- เพิ่มเอกสารและเครื่องมือ CLI สำรอง โดยไม่แก้ application runtime

## ตำแหน่งชุดสำรองที่ตรวจผ่าน

- Hostinger: `/home/u651170081/backups/montien-tech/initial-20261116-025625/`
- เครื่องผู้ดูแล: `backups/initial-20261116-025625/` ภายใต้ repo นี้ (ignored, private directory)
- ผลทดลองแตกไฟล์: `private-audit/restore-files-20261116/` (ignored, private directory)

ชุดสำรองมีข้อมูลส่วนบุคคลและ credential ห้ามเผยแพร่ สำเนาในเครื่องยังไม่ได้เพิ่มการเข้ารหัสระดับ archive ต้องใช้พื้นที่/ดิสก์ที่จำกัดสิทธิ์

## ยังไม่ผ่าน/ยังไม่ดำเนินการ

- การทดลองครั้งแรก MySQL 9.6 ใน sandbox ปิดตัวด้วย SIGSEGV; รอบถัดไป initialize datadir ใหม่ภายนอก sandbox ได้และหยุด instance นั้นแล้ว จึงไม่สรุปว่า MySQL เสีย
- รอบติดตามใช้ MariaDB 11.8.9 แยกทดสอบ restore SQL และ functional smoke tests ผ่าน ดู [ผลทดสอบ](RESTORE-TEST-20261116.md)
- พบไฟล์อ้างอิงที่ขาดในต้นทาง 5 รายการ ยังต้องติดตามสำเนาเก่าและยังไม่รับรองความครบทุก workflow
- ชุดนี้เป็น live backup ไม่ใช่ snapshot เดียวกันระหว่างทุก DB และไฟล์
- ยังไม่ตรวจ auto-deploy/backups ใน hPanel หรือกำหนด backup schedule
- ยังไม่ย้าย storage, เปลี่ยน `.env`, schema, JWT, branch บน server หรือ deploy
- มีชุดสำรองที่หยุดก่อนสำเร็จลงท้าย `025420`, `025449`, `025522` เก็บไว้ตรวจสอบเท่านั้น ไม่ใช้กู้คืน และยังไม่ได้ลบ

## ขั้นต่อไป

แก้จุดแทนที่ไฟล์ 5 จุดและทดสอบบน staging ผ่านแล้ว ดู [ผลการรักษาไฟล์เดิม](FILE-REPLACEMENT-SAFETY.md) ยังไม่ push/deploy

ติดตามไฟล์ขาดจากสำเนาเก่า และตรวจ auto-deploy/นโยบาย cleanup ก่อนเริ่มย้าย storage ตาม `REORGANIZATION-PLAN.md` ห้ามย้อน backup ทับ production เพื่อแก้ไฟล์ขาดบางรายการ

ตรวจ hPanel แล้วพบ auto-deploy เปิดอยู่ เตรียม backup ล่าสุดและชุดปล่อย/rollback เฉพาะ 5 PHP ไฟล์ผ่าน preflight แล้ว แต่ยังไม่ได้เปลี่ยนค่า auto-deploy, push หรือ deploy ดู [ชุดปล่อย](RELEASE-20261116.md)

รอบถัดมาปิด native hPanel auto-deploy ตามคำสั่งผู้ใช้แล้ว ยืนยันข้อความปิดใน UI และตรวจ SSH ว่า production ยังเป็น `b56390d` ไม่มี tracked files เปลี่ยน ยังไม่ได้ push/deploy หรือเพิ่ม workflow/credential ใหม่ ภาพหลักฐาน: `private-audit/releases/file-safety-20261116-v1/hpanel-auto-deploy-disabled.jpg`
