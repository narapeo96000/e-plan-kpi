# บันทึกการปรับปรุงแก้ไข (Changelog)

บันทึกนี้จะถูกอัปเดตทุกครั้งที่มีการปรับปรุง/แก้ไขระบบ พร้อมวันเวลา (เวลาไทย UTC+7) และรายละเอียดการแก้ไข แล้ว commit + push ขึ้น GitHub

## 2026-08-07 12:07 — กำหนดความสอดคล้องตัวชี้วัดร่วม KPI ของจังหวัด (1 โครงการ = หลาย KPI)
- เพิ่มตาราง `project_kpis` (project_id ↔ kpi_id, unique ต่อคู่) รองรับ 1 โครงการสอดคล้องได้หลาย KPI — ใน `migration_upgrade.sql` + `office_budget_edu_db.sql`
- `project_form.php`: หมวดใหม่ "🎯 ความสอดคล้องตัวชี้วัดร่วม KPI ของจังหวัด" เลือกแบบ checkbox ได้หลายรายการ (โหลดเฉพาะ KPI ที่ active ในปีงบประมาณของโครงการ) + แสดงใน Preview
- `project_save.php`: sync ความสัมพันธ์ KPI (ลบเก่า + เพิ่มใหม่ใน transaction เดียวกัน) + บันทึก `kpi_ids` ใน audit log
- `pview_project.php`: แสดง badge รายชื่อ KPI ที่โครงการสอดคล้องในหน้า "ภาพรวมโครงการ"
- `projects.php`: แสดงป้าย 📐 KPI ในรายการโครงการ + ค้นหาด้วยชื่อ KPI ได้

## 2026-08-07 11:56 — จัดทำบันทึกการปรับปรุง (Changelog) + เปิด GitHub Pages
- สร้างไฟล์ `CHANGELOG.md` เพื่อบันทึกทุกการปรับปรุง/แก้ไขพร้อมวันเวลาและรายละเอียด
- เปิดใช้งาน GitHub Pages สำเร็จ: `https://narapeo96000.github.io/e-plan-kpi/` (source = `main` / `docs`)
- แก้สาเหตุ 404: Pages ถูกชี้ source ผิด (root) → ตั้งเป็น `main/docs` + rebuild ด้วย commit `cb94277`

## 2026-08-07 — เชื่อมต่อ GitHub และจัดทำ landing page
- สร้าง repo `narapeo96000/e-plan-kpi` (สาธารณะ), commit เริ่มต้น `209a24c` + landing page `d8d481b`
- สร้าง `docs/index.html` static landing page ภาษาไทย (แนะนำระบบ, บทบาทผู้ใช้, เทคโนโลยี)
- `.gitignore` กันไม่ให้ commit `db.php` (credentials จริง), `office_budget_edu_db.sql` (ข้อมูลจริง), `uploads/`, `*.log`
- สร้าง `db.example.php` เป็นเทมเพลต config แบบไม่มี secret

## 2026-08-07 — ระบบระดับผู้ใช้ 4 บทบาท + KPI ร่วม + on-behalf แก้ไข
- เพิ่มบทบาท: `admin` / `plan` / `office` / `user` (users.role ENUM ผ่าน `migration_upgrade.sql`)
- สิทธิ์: `plan` กำหนด KPI ร่วม, `office` แก้ไข/รายงานโครงการหน่วยงานตัวเอง + บันทึก "แก้ไขแทนเจ้าของ", `user` เฉพาะโครงการตัวเอง, `admin` ทำได้ทุกอย่าง
- เพิ่มคอลัมน์ `projects.edited_on_behalf` + ตาราง `kpi_definitions` + seed KPI ปี 2569
- สร้าง `kpi_management.php` CRUD KPI (plan/admin เท่านั้น); ป้ายบทบาทใน `menu.php`/`users.php`
- กันสิทธิ์ทุกจุด: `project_form.php`, `project_save.php` (คงเจ้าของเดิม), `pview_project.php`, `document_upload/delete.php`, `okr_project_form.php`

## 2026-08-07 — กราฟสัดส่วนการดำเนินการโครงการแบบแท่ง
- เปลี่ยนกราฟบน `index.php` จาก sunburst เป็นกราฟแท่งแนวนอนสีพาสเทล
- สี: บรรลุ `#86efac`, ระหว่างดำเนินการ `#93c5fd`, ไม่บรรลุ `#fdba74`, ยังไม่ระบุ `#e2e8f0`
- แท่ง gradient มันวาว + แรงเงา + แทร็กเข้มขึ้น; เส้นอ้างอิงค่าร้อยละ `.bar-ref-line` + ป้าย `.bar-pct-label` (อิงสัดส่วนข้อมูลจริง)
