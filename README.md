# PNP e-Service — montien.tech

Portal และระบบย่อยที่เปิดใช้งานแล้ว การจัดระเบียบต้องรักษาฐานข้อมูล ไฟล์ผู้ใช้ URL และ SSO เดิม

- [โครงสร้างระบบ](docs/ARCHITECTURE.md)
- [ตำแหน่งข้อมูลและข้อควรระวัง](docs/DATA-STORAGE.md)
- [สำรองและกู้คืน](docs/BACKUP-RESTORE.md)
- [Deploy และตรวจสอบ](docs/DEPLOYMENT.md)
- [แผนปรับปรุงทีละขั้น](docs/REORGANIZATION-PLAN.md)
- [ผลสำรองและงานที่ยังต้องตรวจ](docs/OPERATIONS-STATUS.md)
- [ผลทดสอบกู้คืน](docs/RESTORE-TEST-20261116.md)
- [การรักษาไฟล์เดิมและผลทดสอบ](docs/FILE-REPLACEMENT-SAFETY.md)
- [Deploy แบบกดเองผ่าน GitHub Actions](docs/MANUAL-DEPLOY.md)
- [ผลปล่อยขึ้น production](docs/DEPLOYED-20261116.md)
- แนวทางโค้ดเดิม: [CLAUDE.md](CLAUDE.md), [AI_INSTRUCTIONS.md](AI_INSTRUCTIONS.md)

เริ่มจากเอกสารข้างต้นก่อนรันระบบกับข้อมูลจริง ห้ามใช้ production `.env` กับเครื่องทดสอบ และห้ามรัน schema/seed เพื่อเตรียมระบบที่มีข้อมูลแล้ว

ตรวจไวยากรณ์เครื่องมือสำรองโดยไม่เชื่อมฐานข้อมูล:

```bash
php -l scripts/maintenance/backup.php
```
