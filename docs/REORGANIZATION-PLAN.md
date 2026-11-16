# แผนจัดระเบียบโดยรักษาข้อมูล

## ระยะ 1: เอกสารและชุดสำรอง

- เพิ่ม README, แผนผังระบบ, inventory พื้นที่เก็บ และคู่มือ deploy/restore
- ทำ backup นอกเว็บ พร้อม manifest และ checksum
- ยังไม่เปลี่ยน PHP runtime, schema, `.env`, URL หรือไฟล์ผู้ใช้
- การตรวจ archive ไม่ใช่การทดสอบ restore; ต้องมีสำเนานอกโฮสต์

## ระยะ 2: ยืนยันการกู้คืนและข้อมูลอ้างอิง

- เตรียม staging แยกและ DB ทดสอบทั้งสาม
- ตรวจตาราง/engine จริงโดยใช้ SELECT ผ่าน connection ที่ไม่โหลด bootstrap
- ทำรายงาน missing files/orphan files แบบไม่ลบ รายงานส่วนบุคคลเก็บนอก Git
- ตรวจ auto-deploy จาก hPanel และ recovery window ก่อนเปลี่ยนระบบจริง

## ระยะ 3: แก้โค้ดเสี่ยงสูญหายบน staging ก่อน

- `api/update_profile.php`: เก็บรูปเดิมจนรูปใหม่และ DB บันทึกสำเร็จ
- `pnp-academix/teacher/submit.php`: หลีกเลี่ยงลบไฟล์ก่อน UPDATE สำเร็จ เพิ่ม version/history
- การ cleanup: เพิ่ม dry run, quarantine และ audit log แทน unlink ทันที
- การลบบัญชี: ประเมิน cascade กับตารางจริง เปลี่ยนเป็นปิดใช้งานตาม workflow ที่ยืนยันแล้ว
- เพิ่ม test กรณี DB update ล้มเหลวแล้วเอกสารเดิมยังเปิดได้ และการเขียนพร้อมกัน

## ระยะ 4: รวมพื้นที่เก็บถาวร

- Portal และ PNP Go ย้ายจุดเขียนออกจาก deployment zone
- รูป PNP Man เดิมต้องเปิดได้ระหว่างเปลี่ยนผ่าน
- ใช้ copy + checksum + อ่านย้อนหลัง ห้ามลบต้นทางในขั้นตอนย้าย
- ก่อนสลับต้องมี backup ที่สอดคล้องกันระหว่าง DB กับไฟล์

## ระยะ 5: จัดโค้ดและกระบวนการระยะยาว

- คง repo เดียวและ URL เดิมในช่วงแรก
- แยก migrations, shared helpers และ scripts ที่เป็น CLI-only
- เพิ่ม CI สำหรับ PHP syntax และ frontend build โดยไม่ใช้ production secrets
- แยก static assets ออกจาก user uploads ลดไฟล์ส่วนบุคคลใน Git ด้วยแผนแยก (ไม่ rewrite history ตอนนี้)
- ทบทวน SSO และสิทธิ์อ่านไฟล์ โดยไม่เปลี่ยน authentication พร้อมกับการย้าย storage

เกณฑ์ผ่านแต่ละระยะ: เอกสารเก่าเปิดได้, ข้อมูลไม่ลดโดยไม่ตั้งใจ, สิทธิ์ถูกต้อง, rollback ไม่ทับข้อมูลใหม่ และมีผลตรวจที่ทบทวนได้
