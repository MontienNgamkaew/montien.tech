# Deploy ผ่าน GitHub Actions แบบกดเอง

Workflow: `.github/workflows/hostinger.yml` ชื่อ **Deploy to Hostinger (manual)**

ใช้ `workflow_dispatch` เท่านั้น ไม่มี push/PR/schedule trigger; native hPanel auto-deploy ปิดแล้ว Push GitHub จึงแยกจากการปล่อยเว็บ

## วิธีใช้

ใน GitHub ไปที่ Actions → Deploy to Hostinger (manual) → Run workflow → branch `main`

| mode | การทำงาน |
|---|---|
| `check` (ค่าเริ่มต้น) | ส่งเฉพาะ code payload ไปนอก webroot ตรวจ hash ฐานและ lint บนโฮสต์ ไม่ติดตั้งและไม่เขียน DB |
| `deploy` | ตรวจ → สำรอง DB ทั้งสาม/webroot/persistent uploads → ตรวจ checksum backup → ติดตั้ง 5 ไฟล์ → ยืนยัน hash/version |
| `rollback` | ระบุ release ID ที่ติดตั้งอยู่ ย้อนเฉพาะ 5 ไฟล์เดิม ไม่ย้อน DB หรือ `.env` |

ใช้ CLI ได้เช่นกัน:

```bash
gh workflow run hostinger.yml --repo MontienNgamkaew/montien.tech --ref main -f mode=check
gh workflow run hostinger.yml --repo MontienNgamkaew/montien.tech --ref main -f mode=deploy
# ระบุ ID จริงจาก workflow summary เมื่อต้องการย้อนโค้ด
gh workflow run hostinger.yml --repo MontienNgamkaew/montien.tech --ref main -f mode=rollback -f rollback_release=RELEASE_ID
```

แต่ละ run ใช้ ID รูปแบบ `run-RUN_ID-ATTEMPT-COMMIT12` ไม่เขียนทับชุดปล่อยเดิม ค่าเริ่มต้น check ไม่ใช่ deploy

## ขอบเขตชุดปล่อยแรก

1. `api/update_profile.php`
2. `pnpman/api/manage_personnel.php`
3. `pnpman/api/update_settings.php`
4. `pnp-academix/teacher/submit.php`
5. `pnp-academix/admin/settings.php`

นอก allowlist นี้จะไม่ถูกส่งไปติดตั้ง รวม `.env`, uploads, storage, vendor, schema, workflow, เอกสาร และตัว gateway เอง ไม่มี git checkout, rsync --delete, DB migration หรือการล้างไฟล์

การเพิ่มไฟล์ที่จะ deploy ในอนาคตต้องทบทวน allowlist ทั้ง client และ gateway พร้อม tests แล้วติดตั้ง gateway ใหม่ด้วยคีย์ผู้ดูแล ไม่ปล่อยให้ CI เขียนทับตัวควบคุมตัวเอง

## คีย์และ environment

- Environment: `hostinger-production` จำกัด branch `main` แยกจาก environment `Production` เดิม
- Secrets: `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_USER`, `HOSTINGER_SSH_PORT`, `HOSTINGER_SSH_PRIVATE_KEY`, `HOSTINGER_SSH_KNOWN_HOSTS`
- ใช้คีย์ใหม่เฉพาะงานนี้ ไม่คัดลอกคีย์ส่วนตัวของเครื่องผู้ดูแลหรือคีย์คืนสู่เหย้า
- Public key ถูกบังคับผ่าน `restrict,command=...gateway.php` ไม่รับ shell, scp หรือ arbitrary SSH commands และปิด forwarding/PTY ด้วยข้อจำกัด OpenSSH
- Gateway/config/backup helper อยู่ `/home/u651170081/.config/montien-tech-deploy/` นอกเว็บ สิทธิ์ส่วนตัว คีย์เดิมถูกเก็บไว้ทั้งหมด
- ยังคงต้องเชื่อถือผู้แก้โค้ดบน main: การแก้ PHP ที่ได้รับอนุญาตย่อมมีสิทธิ์ของแอป ข้อจำกัด gateway ช่วยกัน deploy ผิดขอบเขต ไม่ใช่ sandbox สำหรับ PHP ที่เป็นอันตราย

## สถานะและการย้อนกลับ

Git checkout เดิมบน production คงไว้เพื่อไม่ใช้ hPanel fresh checkout; หลังปล่อย PHP ห้าไฟล์ Git status จะรายงานไฟล์เปลี่ยนตามปกติ เวอร์ชันที่ปล่อยจริงดูจาก gateway `state.json` และ Actions summary ไม่ตีความ Git HEAD เดิมว่าเป็นเวอร์ชันเว็บปัจจุบัน

ผู้ดูแลตรวจสถานะแบบอ่านอย่างเดียวด้วยคีย์เดิมได้:

```bash
ssh hostinger-montien 'SSH_ORIGINAL_COMMAND="pnp-deploy status" php /home/u651170081/.config/montien-tech-deploy/gateway.php'
```

Gateway ตรวจ hash ของโค้ดปัจจุบันกับสถานะที่บันทึก หากมี hotfix นอกกระบวนการจะหยุดโดยไม่ทับ ห้ามแก้ state เพื่อข้าม guard โดยไม่ตรวจ diff

เก็บชุดปล่อยและโค้ดเดิมที่ `/home/u651170081/releases/montien-tech/actions/RELEASE_ID/` และ backup ก่อน deploy ที่ `/home/u651170081/backups/montien-tech/RELEASE_ID/` ต้องคง backup ตามนโยบายผู้ดูแล ไม่มีการลบหรือหมดอายุอัตโนมัติ

ล็อกการปล่อยด้วย GitHub concurrency และ flock บนโฮสต์ เตรียมสำเนาทุกไฟล์ก่อนแทนที่ทีละไฟล์แบบ rename หากแทนที่ล้มเหลวจะพยายามคืนไฟล์ที่เปลี่ยนไปแล้วจากสำเนาเดิม แต่ไม่ใช่ atomic switch ทั้งห้าไฟล์พร้อมกัน หากผู้ดูแลแก้ไฟล์แทรกระหว่างทางจะหยุดให้ตรวจเพื่อไม่ทับงานนั้น

Backup เป็น live backup ไม่ใช่ snapshot พร้อมกันทุก DB/ไฟล์; ไม่ส่ง SQL, `.env` หรือ user files กลับไป Actions/artifacts และ rollback ไม่ restore DB ทับรายการใหม่

## ตรวจในเครื่อง

```bash
php -l scripts/deploy/gateway.php
python3 -m unittest discover -s tests -p 'test_deploy_gateway.py' -v
git diff --check
```

Tests ใช้ disposable fixture ไม่มี production credential/network/DB ตรวจการจำกัดไฟล์, ปฏิเสธ symlink/คำสั่งนอก protocol, โค้ดเปลี่ยน, PHP ผิด, ไม่มี/ล้มเหลว/หมดอายุ backup และการ deploy/rollback ที่รักษาไฟล์ส่วนตัว

อ้างอิง: [GitHub workflow_dispatch](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax), [GitHub deployment environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments), [OpenSSH authorized_keys restrictions](https://man.openbsd.org/sshd)
