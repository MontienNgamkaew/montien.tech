# โครงสร้างระบบ

สำรวจ 16 พฤศจิกายน 2569 จากโค้ดและ Hostinger; สถานะอาจเปลี่ยนภายหลัง

| ส่วน | โค้ด | ข้อมูล/การเชื่อมต่อ |
|---|---|---|
| Portal | HTML/JS ที่ราก + `api/` | MySQL กลาง, ผู้ใช้และสิทธิ์ SSO |
| PNP Man | `pnpman/api`, `pnpman/frontend` | ใช้ MySQL กลางร่วม Portal; React/Vite |
| PNP Go | `pnp-go/` | MySQL แยก, PHP MVC, mPDF, รับ SSO |
| Academix | `pnp-academix/` | MySQL แยก, เอกสารและการตรวจงาน |
| Lesson Plan | `pnp-lessonplan/` | React/Vite, localStorage และบริการภายนอก |

Hostinger ใช้ repo `MontienNgamkaew/montien.tech` ที่ `/home/u651170081/domains/pnp.montien.tech/public_html` ตรวจพบ commit `b56390d` และ detached HEAD ห้ามสรุปว่าเป็นความผิดปกติหรือเปลี่ยน branch ก่อนตรวจการตั้งค่า hPanel

Portal ออก JWT และระบบย่อยรับ SSO; คีย์ต้องสัมพันธ์กับระบบเดิม ห้ามหมุนคีย์หรือเปลี่ยนชื่อพารามิเตอร์ระหว่างจัดโฟลเดอร์

## ส่วนที่ต้องรักษาความเข้ากันได้

- URL ระบบย่อยและ URL เอกสารเดิม
- primary key ผู้ใช้ รายวิชา ภาคเรียน คำขอ และความสัมพันธ์ข้ามระบบ
- พาธไฟล์ที่บันทึกในฐานข้อมูล
- การตั้งค่า `.env`, rewrite rules, PHP session และสิทธิ์ไฟล์
- `pnp-go/vendor` ที่ติดตามใน Git เพื่อ deploy mPDF
- `pnpman/frontend/dist` ที่เอกสารเดิมระบุว่าใช้ deploy; ตรวจผล build ก่อน commit

## สิ่งที่ยังไม่ยืนยัน

การตั้งค่า auto-deploy, รอบสำรองใน hPanel, recovery point ที่ยอมรับได้, การกู้คืนจริง และบริการภายนอกของ Lesson Plan ไม่ได้รับการตรวจครบในการสำรวจนี้
