# บันทึกการปรับปรุงแก้ไข (Changelog)

บันทึกนี้จะถูกอัปเดตทุกครั้งที่มีการปรับปรุง/แก้ไขระบบ พร้อมวันเวลา (เวลาไทย UTC+7) และรายละเอียดการแก้ไข แล้ว commit + push ขึ้น GitHub

## 2026-08-10 — แก้ฟอร์มแนบไฟล์เอกสาร + เปลี่ยนจัดการ KPI/ยุทธศาสตร์/เป้าประสงค์/หน่วยงาน เป็น popup
- `project_form.php`: แก้บั๊กแนบไฟล์เอกสารไม่ทำงาน (สาเหตุ: form ซ้อน form — เบราว์เซอร์เพิกเฉย form ด้านใน) — คืน form หลักเป็นชิ้นเดียว, ย้าย `#docUploadForm` ออกนอก form หลัก, ใช้ HTML5 `form="docUploadForm"` attribute, upload ผ่าน FormData + XHR
- `kpi_management.php`: เพิ่ม/แก้ไข KPI เป็น popup modal (`kpiModal` + `openKpiModal()`/`saveKpi()`, AJAX endpoint `?ajax=kpi&id=X` / `?ajax=objectives&year=X`, รองรับ `$isAjax` + JSON response) — ลบฟอร์มฝั่งขวา col-xl-5
- `strategies.php`: เพิ่ม/แก้ไขยุทธศาสตร์เป็น popup modal (`strategyModal`, `?ajax=strategy&id=X`)
- `objectives.php`: เพิ่ม/แก้ไขเป้าประสงค์เป็น popup modal (`objectiveModal`, `?ajax=objective&id=X` / `?ajax=strategies&year=X`)
- `schools.php`: เพิ่ม/แก้ไขหน่วยงานเป็น popup modal (`schoolModal`, `?ajax=school&id=X`) + เพิ่ม modal รีเซ็ตรหัสผ่าน (`resetModal`) — ลบฟอร์ม/หน้าตั้งค่ารหัสผ่านฝั่งขวา
- pattern เดียวกันทั้ง 4 หน้า: POST + `X-Requested-With: XMLHttpRequest` + CSRF → JSON `{success, message/error}` → ปิด modal + reload
- Deploy ขึ้น server แล้วทั้งหมด: `project_form.php`, `kpi_management.php`, `strategies.php`, `objectives.php`, `schools.php`

## 2026-08-10 — โครงการใช้ข้อมูลหน่วยงาน (เจ้าของโครงการ) เป็นหลัก
- `pview_project.php`: หน้าแสดงรายละเอียดโครงการเดิมแสดง "หน่วยงาน" จากคอลัมน์ `department` ซึ่งส่วนใหญ่เป็นค่าว่าง → แสดง "-" — แก้ให้ใช้ข้อมูลหน่วยงานเจ้าของโครงการ (`agency_id` → `agencies.agency_name`) เป็นหลัก, fallback เป็น `department` เฉพาะเมื่อไม่มีหน่วยงาน — สอดคล้องกับหลักที่ใช้ใน `projects.php`, `report.php`, `export_excel.php`, `export_pdf.php`, `index.php` อยู่แล้ว
- ตรวจบน server: โครงการ narasci (id=69) แสดง "ศูนย์วิทยาศาสตร์เพื่อการศึกษานราธิวาส" (ชื่อเต็มจาก agencies) แทน code "narasci", โครงการ id=97 แสดง "สพป.นธ.1" (เดิมเป็น "-"), โครงการที่ไม่มีหน่วยงานจริงยังคงแสดง "-"
- Deploy ขึ้น server แล้ว: `pview_project.php`

## 2026-08-09 — เพิ่มหน้าเอกสารดาวน์โหลด + เริ่มโครงสร้าง เป้าประสงค์ (objectives)
- เพิ่มหน้า `download_docs.php`: แสดงรายการเอกสารให้ทุกคนดาวน์โหลด (PDF/Word/Excel/PowerPoint/zip/rar) — admin อัปโหลด/ซ่อน/ลบเอกสารได้ (CSRF + logfile), ไฟล์เก็บใน `uploads/docs/` จำกัด 10 MB
- เพิ่มตาราง `download_docs` (title, description, file, status, sort_order) ใน `office_budget_edu_db.sql` + `migration_upgrade.sql` และรัน migration บน server แล้ว
- `menu.php`: เพิ่มเมนู "📁 เอกสารดาวน์โหลด" (desktop sidebar + mobile nav) ต่อจากเมนู ยุทธศาสตร์
- เพิ่มตาราง `objectives` (เป้าประสงค์: 1 ยุทธศาสตร์ มีได้หลายเป้าประสงค์) + คอลัมน์ `kpi_definitions.objective_id` (ตัวชี้วัดผูกกับเป้าประสงค์) — schema + migration + seed objectives เริ่มต้นปี 2569 (6 ข้อ) + map KPI เดิม 10 ตัวเข้ากับ objective แล้ว
- `menu.php`: ซ่อนเมนู "ติดตาม OKR" และ "บันทึกการเบิกจ่าย" (ยังไม่ใช้) — คงเมนูบริหารงบประมาณอื่นไว้

## 2026-08-09 — นำเข้าข้อมูลแผนปฏิบัติการปี 2569 (43 โครงการ) + ลบหน่วยงานได้
- ลบข้อมูลโครงการเดิมทั้งหมด (64 โครงการ) ออกจาก server และนำเข้าเฉพาะ 43 โครงการใหม่ (PRJ2569-001..043) จาก `seed_eplan_2569.sql` — map `agency_id`/`strategy_id` ไป id จริงบน server (seed ใช้ id 8-17 แต่ server มีหน่วยงานอยู่ที่ id อื่น เช่น narasci=16, naracity=14, kolokcity=15, pnu=20, ncc=24, technicbangnara=22, mol=23; ยุทธศาสตร์ 2569 อยู่ที่ id 1,3,7,8,9)
- ลบข้อมูลลูกของโครงการเดิม: `project_kpis` (13), `project_strategic_issues` (20), `project_documents`, `budget_transactions` (2) — ไม่แตะข้อมูล OKR
- `schools.php`: เพิ่มปุ่ม "ลบ" หน่วยงานทางการศึกษา (POST + CSRF + confirm) — FK `fk_projects_agencies`/`fk_users_agencies` เป็น `ON DELETE SET NULL` ทำให้โครงการ/ผู้ใช้ที่ผูกยังคงอยู่ เพียงแต่ไม่มีหน่วยงานกำกับ + logfile

## 2026-08-09 20:15 — เรียงลำดับยุทธศาสตร์และกำหนดลำดับหน่วยงาน
- `index.php`: เรียง "โครงการแยกตามยุทธศาสตร์" ตาม `issue_no` (1,2,3,...n) บน Dashboard
- เพิ่มคอลัมน์ `sort_order` ในตาราง `agencies` (เรียงหน่วยงานบน Dashboard/รายงาน/ฟอร์ม/dropdown ทุกจุด: `index.php`, `schools.php`, `db.php` (getSchools), `projects.php`, `project_form.php`, `users.php`, `export_excel.php`, `export_pdf.php`)
- `schools.php`: เพิ่มช่อง "ลำดับแสดงผล" (ตัวเลข, 0=ค่าเริ่มต้น, น้อย = แสดงก่อน) ในฟอร์มเพิ่ม/แก้ไข + แสดงคอลัมน์ลำดับในตาราง + `ensureAgencyTable()` รองรับคอลัมน์ใหม่อัตโนมัติ
- `office_budget_edu_db.sql`: เพิ่มคอลัมน์ `sort_order` + seed ลำดับเริ่มต้น 1-9 ตามหน่วยงานที่มีอยู่
- `migration_upgrade.sql`: เพิ่ม section 9 (idempotent) เพิ่มคอลัมน์ `sort_order` + set ค่าเริ่มต้นตาม `agency_code`

## 2026-08-09 19:17 — ปรับปรุงความปลอดภัย (Security hardening) + ตัวกรองผลการดำเนินการ
- `db.php`: เพิ่ม security headers (X-Content-Type-Options / X-Frame-Options / Referrer-Policy), harden session cookie (httponly+secure, PHP 5.6 compatible positional), helpers CSRF `csrfToken()`, `csrfField()`, `csrfCheck($redirect)` พร้อม fallback PHP 5.6; `db.example.php` ตามให้ตรงกัน
- CSRF (ป้องกัน Cross-Site Request Forgery) ในทุกฟอร์ม/โพสต์: `kpi_management.php`, `users.php`, `strategies.php`, `schools.php`, `budget_sources.php`, `budget_transactions.php` (add/edit/delete + JSON), `setting.php`, `profile.php`, `okr_agency_targets.php`, `submit_okr.php`, `okr_project_save.php`, `okr_project_form.php`, `project_edit.php`, `okr_form.php`; ลิงก์ toggle (GET) ตรวจ `csrf` param
- `login.php`: `session_regenerate_id(true)` หลังล็อกอิน (กัน session fixation); `logout.php`: ล้าง session cookie + `session_destroy()`
- `index.php`: ปิด `display_errors` ใน production; เพิ่ม KPI overview section ใน dashboard (รวมจำนวนตัวชี้วัด + จำนวนโครงการที่ผูก)
- `export_excel.php`: เพิ่ม `xlsSafe()` กัน Excel formula injection ในทุก cell ข้อความ
- `export_pdf.php` / `export_excel.php` / `report.php`: เปลี่ยน scope เป็น `!isAdminOrPlan()` (ผู้ใช้ระดับ plan เห็นข้อมูลจังหวัดทั้งหมด)
- `projects.php`: เพิ่มตัวกรอง "ผลการดำเนินการ" (บรรลุ/ระหว่างดำเนินการ/ไม่บรรลุ/ยังไม่ระบุ) ใช้กับรายการ + pagination + ค้นหา

## 2026-08-09 18:30 — กำหนดเป้าประสงค์/ตัวชี้วัด/ค่าเป้าหมายตามประเด็นยุทธศาสตร์ ปี 2569 (ชุดใหม่แทนที่ของเดิม)
- `office_budget_edu_db.sql`: แก้ seed `strategic_issues` ปี 2569 เป็น 5 ยุทธศาสตร์ตามแผนพัฒนาการศึกษาจังหวัดนราธิวาส (ผู้เรียนเป็นศูนย์กลาง / ผู้ประกอบการเพื่อสังคมและทุนวัฒนธรรม / ครูและผู้บริหารผู้นำการเปลี่ยนแปลง / นิเทศ ติดตาม ตรวจราชการเชิงพัฒนา / ความร่วมมือทุกภาคส่วน) โดยเก็บยุทธศาสตร์ปี 2568 ไว้ (เลื่อน id เป็น 6-7)
- ปรับ `projects` seed ที่ชี้ `strategy_id` เดิมไม่ถูกต้อง: "การจัดงานฉลองวันเด็กแห่งชาติ" → ยุทธศาสตร์ที่ 5, "การเงินสัญจร" → ยุทธศาสตร์ที่ 4 (เดิมชี้ id 6 ซึ่งคือยุทธศาสตร์ปี 2568)
- แทนที่ seed `kpi_definitions` ปี 2569 ด้วย 10 ตัวชี้วัดใหม่ตามเป้าประสงค์ของ 5 ยุทธศาสตร์ (ค่าเป้าหมาย 60% ยกเว้นคุณลักษณะที่พึงประสงค์ 70%) ลบ KPI เดิม 3 ตัว (บรรลุตามแผน/ใช้จ่ายงบฯ/รายงานผล)
- `migration_upgrade.sql`: เพิ่ม section 8 (idempotent) แมปยุทธศาสตร์ด้วย `fiscal_year + issue_no` (ไม่ผูก id), ลบยุทธศาสตร์ปี 2569 ที่เกินข้อ 5, ลบ KPI ปี 2569 เดิมแล้วนำเข้าชุดใหม่
- หมายเหตุ: โครงการที่เคยผูก KPI เดิมไว้ใน `project_kpis` ต้องผูกกับ KPI ชุดใหม่ผ่านระบบอีกครั้ง (ไม่มี FK ระหว่างตาราง)

## 2026-08-09 17:45 — นำเข้าข้อมูลโครงการจริง 13 โครงการ (แผนพัฒนาการศึกษาจ.นราธิวาส ปี 2569)
- นำเข้าจาก `projec_all.html` ตามหลักการออกแบบฐานข้อมูล โดยไม่แก้ schema/โปรแกรมเดิม (ใช้โครงสร้าง `project_strategic_issues` + `project_kpis` ที่มีอยู่)
- แก้ข้อมูล `strategic_issues`: ปรับ 5 ยุทธศาสตร์ปี 2569 ให้เป็นชื่อตามแผนฯ ถูกต้อง (ยุทธศาสตร์ที่ 1-5) และลบแถวที่ซ้ำ/ผิด (issue_no 6) ที่ไม่มีข้อมูลอ้างอิง
- เพิ่มหน่วยงานใหม่ 5 แห่งใน `agencies`: ศูนย์วิทยาศาสตร์ฯ, สสจ.นราธิวาส, สถาบันพัฒนาฝีมือแรงงาน 25 นธ., ม.นราธิวาสราชนครินทร์, วิทยาลัยชุมชนนราธิวาส (แมปกับที่มีอยู่ 4 แห่ง: เทศบาลเมือง, สกร., ศธจ., สพป.3)
- เพิ่ม KPI ระดับจังหวัด 8 ตัวชี้วัดใน `kpi_definitions` (จาก kpiMain ของแต่ละยุทธศาสตร์) และผูก 13 โครงการผ่าน `project_kpis`
- mapping ฟิลด์: `name→title`, `strategy→strategic_issues`(+junction), `target→target_quantitative`, `indicator→target_qualitative`, `activities→operated_activities`, `outcome→operation_results`, `budget→budget_allocated`, `agency→agency_id`
- ค่าเริ่มต้น: `fiscal_year=2569`, `status=ยังไม่เริ่ม`, `progress=0`, `budget_used=0`, `result_status=''`, `owner_name=ชื่อหน่วยงาน`, เจ้าของ `username=wirat` (รหัสโครงการ `PRJPLAN001`–`PRJPLAN043`)
- โครงการ "มหกรรมสัปดาห์วิทยาศาสตร์" บนเครื่องจริงมีอยู่เดิมแต่ข้อมูลไม่ครบ → เติมข้อมูล + ผูกยุทธศาสตร์/KPI ให้สมบูรณ์
- ทดสอบ local + remote (HTTP 200, ค้นหา KPI ได้, pview แสดงครบ), ลบสคริปต์นำเข้าทิ้งหลังใช้งานเสร็จ

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
