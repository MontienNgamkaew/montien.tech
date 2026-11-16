# ผลทดสอบกู้คืน 16 พฤศจิกายน 2569

## ขอบเขต

ทดสอบจากชุด `initial-20261116-025625` บนเครื่องผู้ดูแลเท่านั้น ไม่ import, ย้าย, ลบ หรือแก้ข้อมูล production ชุดสำรองยังเป็น live backup จึงไม่รับรอง snapshot พร้อมกันทุกระบบ

ใช้ MariaDB **11.8.9** ตรงกับเวอร์ชันใน dump ติดตั้งผ่าน Homebrew แต่เรียก executable โดยตรงจาก `/opt/homebrew/Cellar/mariadb@11.8/11.8.9_1/bin/` ไม่แทนที่คำสั่ง MySQL เดิม ไม่เปิด Homebrew service และข้าม post-install เพื่อไม่ initialize datadir ปกติ

Homebrew ติดตั้ง dependency เพิ่มและอัปเดต dependency บางตัว คำสั่ง `mysql` ยังคงชี้ MySQL เดิม และคืน symlink คำสั่ง OpenSSL ให้ชี้ `openssl@3` ตามเดิมแล้ว

## การแยกสภาพแวดล้อม

- datadir: `private-audit/mariadb-restore-20261116/data/`
- socket: `/private/tmp/montien-restore-20261116/mariadb.sock`
- ปิด TCP, event scheduler และ binary log
- staging: `private-audit/staging-20261116/` ไม่มี production `.env`, Git metadata หรือ session เดิม
- PHP staging bind เฉพาะ `127.0.0.1:18765` ใช้ JWT ใหม่และบัญชีผู้ดูแลสังเคราะห์
- ฐานข้อมูล staging ใช้ชื่อจาก dump ได้เพราะเป็น server/datadir ที่แยกจริง
- หลังตรวจ restore baseline แล้ว functional tests เพิ่มบัญชีทดสอบและสร้าง PDF เฉพาะ staging; ฐานข้อมูลทดสอบปัจจุบันจึงต่างจาก baseline โดยตั้งใจ
- หยุด PHP staging และ MariaDB ทดสอบแล้วหลังจบงาน ตรวจไม่พบ listener ของ staging หรือ socket MariaDB ค้างอยู่

## ผล import ก่อน functional tests

| ฐานข้อมูล | ตาราง | แถวรวม | จำนวนแถวเทียบ dump | Foreign key orphan |
|---|---:|---:|---|---:|
| Portal / PNP Man | 8 | 384 | ตรงทุกตาราง | 0 |
| PNP Go | 7 | 58 | ตรงทุกตาราง | 0 |
| Academix | 6 | 447 | ตรงทุกตาราง | 0 |

จำนวนแถวใน dump นับจาก VALUES โดยคำนึงถึง quote, escape และวงเล็บในข้อความ ไม่ใช้แค่จำนวนบรรทัด ไม่พบ orphan ตาม foreign key ที่ประกาศจริง; ไม่ใช่การรับรองทุกความสัมพันธ์ทางธุรกิจที่ไม่ได้กำหนด FK

เอกสาร Academix ที่ DB อ้างถึง **190/190** พบใน persistent storage ที่แตกจาก backup

## Functional smoke tests

- Portal login ออก JWT ให้บัญชีสังเคราะห์ได้
- PNP Man data/settings API สำเร็จ และอ่านสิทธิ์ admin ได้
- Academix SSO และหน้าผู้ดูแลสำเร็จ
- PNP Go SSO และ dashboard สำเร็จ
- เปิด PDF Academix 3 ตัวอย่างผ่าน HTTP staging แล้ว SHA256 ตรงไฟล์สำรอง
- สร้าง PDF ผ่าน `PdfService` จากคำขอที่กู้คืนได้ ขนาด 163,118 bytes และมี PDF header

ยังไม่ได้ทดสอบ UI ด้วยเบราว์เซอร์, workflow ทุกบทบาท, การส่งงาน/อนุมัติครบวงจร, concurrency, failover หรือบริการภายนอกของ Lesson Plan จึงยังไม่ถือว่าพร้อมเปลี่ยน production ทันที

## ข้อมูลอ้างไฟล์ที่ต้องติดตาม

| ประเภท | จำนวนที่ไม่พบ |
|---|---:|
| รูปโปรไฟล์ Portal/Man | 1 |
| โลโก้ Man | 1 |
| PDF PNP Go | 2 |
| รูปรายงาน PNP Go | 1 |

ตรวจทั้งใน backup, ตำแหน่งปัจจุบันบนโฮสต์ และค้น basename ในโฟลเดอร์โดเมนแล้วไม่พบ (ข้าม `.git`, dependencies และ build caches) ไม่ใช่หลักฐานว่าไม่มีสำเนาใน hPanel backup, เครื่องเก่า หรือ Git history เลย ห้ามลบแถวอ้างอิงเหล่านี้เพื่อล้างรายงาน

PDF อย่างน้อยหนึ่งรายการสร้างใหม่จากข้อมูลที่กู้คืนได้ในการทดสอบ แต่ไฟล์สร้างใหม่ไม่ใช่ต้นฉบับเดิม และไม่ใช่การกู้รูปแนบ/ลายเซ็น ต้องรักษาหมายเลขเอกสารและทบทวนก่อนนำไปแทน production

## เครื่องมือและผลตรวจส่วนตัว

- `scripts/maintenance/dump-counts.py`: สร้าง expected row counts จาก SQL โดยไม่แสดงเนื้อหาข้อมูล
- `scripts/maintenance/verify-restored.php`: ตรวจ datadir และการปิด TCP ก่อน SELECT โดยไม่โหลด bootstrap; exit 2 หาก row counts/FK ไม่ผ่าน, exit 3 หากพบพาธไฟล์ขาด/ไม่ปลอดภัย
- `private-audit/mariadb-restore-20261116/verification.json`: baseline ก่อน functional tests
- `private-audit/mariadb-restore-20261116/remote-reference-result.json` และ `missing-file-search.json`: ผลตรวจต้นทางแบบอ่านอย่างเดียว
- `private-audit/staging-20261116/http-smoke-results.json` และ `pdf-smoke-results.json`: ผล functional tests

ทดสอบ verifier เพิ่มเติมแล้ว: จับ row-count mismatch หลังเพิ่มบัญชีสังเคราะห์ได้ และปฏิเสธ datadir ที่ไม่ตรง ผ่าน `php -l` ของ PHP scripts ทั้งสอง, parser edge cases และ `git diff --check`

คำสั่งตรวจไวยากรณ์ที่รันซ้ำได้โดยไม่เปิดบริการหรือเชื่อมฐานข้อมูล:

```bash
php -l scripts/maintenance/backup.php
php -l scripts/maintenance/verify-restored.php
git diff --check
```

ทั้งหมดใน `private-audit/` ถูก ignore และจำกัดสิทธิ์ระดับ parent ไม่ควร commit หรือเผยแพร่ มี PHP 8.5 deprecation warnings ระหว่างทดสอบ จึงควรทบทวนความเข้ากันได้แยกจากการย้ายข้อมูล

## ขั้นต่อไป

1. ตรวจสำเนาเก่าใน hPanel/เครื่องเก่าสำหรับไฟล์ขาดทั้ง 5 รายการ โดยไม่ restore ทับ production
2. แก้การแทนที่ไฟล์ให้รักษาของเดิมเมื่อ DB บันทึกล้มเหลวบน staging พร้อม failure tests
3. ตรวจวิธี auto-deploy ใน hPanel ก่อน push การเปลี่ยนใด ๆ
4. ก่อนย้าย storage ต้องทำชุดสำรองใหม่ในช่วงหยุดเขียนที่วางแผนไว้ และตรวจไฟล์ครบอีกครั้ง

แนวทางแยก datadir/socket และปิด TCP อ้างอิง [MariaDB: Installing alongside MySQL](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/installing-mariadb/binary-packages/installing-mariadb-alongside-mysql) และ [mariadb-install-db](https://mariadb.com/docs/server/clients-and-utilities/deployment-tools/mariadb-install-db)
