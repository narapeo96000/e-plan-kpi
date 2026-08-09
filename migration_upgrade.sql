-- migration_upgrade.sql
-- Combined, idempotent upgrade for EXISTING databases (safe to run multiple times).
-- Includes:
--   1. `projects` media-link columns (video_links, document_links, report_links)
--   2. `okr_projects` / `okr_key_results` normalized columns
--   3. Central `logfile` audit table
--   4. `project_documents` upload table + `project_strategic_issues`
-- For a fresh install, use `office_budget_edu_db.sql` instead (it already contains all of this).
-- Run with:  mysql -u root -p office_budget_edu_db < migration_upgrade.sql

SET NAMES utf8mb4;
SET time_zone = '+07:00';

USE `office_budget_edu_db`;

-- -----------------------------------------------------------------------------
-- 1) projects: add media/link columns if missing
-- -----------------------------------------------------------------------------

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'projects'
     AND COLUMN_NAME = 'video_links') = 0,
  'ALTER TABLE `projects` ADD COLUMN `video_links` TEXT DEFAULT NULL AFTER `images`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'projects'
     AND COLUMN_NAME = 'document_links') = 0,
  'ALTER TABLE `projects` ADD COLUMN `document_links` TEXT DEFAULT NULL AFTER `video_links`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'projects'
     AND COLUMN_NAME = 'report_links') = 0,
  'ALTER TABLE `projects` ADD COLUMN `report_links` TEXT DEFAULT NULL AFTER `document_links`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'projects'
     AND COLUMN_NAME = 'result_status') = 0,
  'ALTER TABLE `projects` ADD COLUMN `result_status` VARCHAR(20) DEFAULT NULL AFTER `report_links`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1b) projects: add edited_on_behalf flag (records "แก้ไขแทนเจ้าของโครงการ")
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'projects'
     AND COLUMN_NAME = 'edited_on_behalf') = 0,
  'ALTER TABLE `projects` ADD COLUMN `edited_on_behalf` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_updated_by`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1c) users: extend role enum to support admin / user / office / plan
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'users'
     AND COLUMN_NAME = 'role'
     AND (COLUMN_TYPE NOT LIKE '%office%' OR COLUMN_TYPE NOT LIKE '%plan%')) = 0,
  'SELECT 1',
  'ALTER TABLE `users` MODIFY COLUMN `role` ENUM(''admin'',''user'',''office'',''plan'') NOT NULL DEFAULT ''user'''));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) okr_projects: add normalized columns if missing
-- -----------------------------------------------------------------------------

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_projects'
     AND COLUMN_NAME = 'strategic_issue_id') = 0,
  'ALTER TABLE `okr_projects` ADD COLUMN `strategic_issue_id` INT UNSIGNED DEFAULT NULL AFTER `fiscal_year`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_projects'
     AND COLUMN_NAME = 'title') = 0,
  'ALTER TABLE `okr_projects` ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `strategic_issue_id`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_projects'
     AND COLUMN_NAME = 'objective') = 0,
  'ALTER TABLE `okr_projects` ADD COLUMN `objective` TEXT DEFAULT NULL AFTER `title`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_projects'
     AND COLUMN_NAME = 'budget_allocated') = 0,
  'ALTER TABLE `okr_projects` ADD COLUMN `budget_allocated` DECIMAL(14,2) DEFAULT 0.00 AFTER `objective`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_projects'
     AND COLUMN_NAME = 'owner_id') = 0,
  'ALTER TABLE `okr_projects` ADD COLUMN `owner_id` INT UNSIGNED DEFAULT NULL AFTER `owner_user_id`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- okr_key_results: add normalized columns if missing
-- -----------------------------------------------------------------------------

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_key_results'
     AND COLUMN_NAME = 'kr_title') = 0,
  'ALTER TABLE `okr_key_results` ADD COLUMN `kr_title` TEXT DEFAULT NULL AFTER `project_id`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_key_results'
     AND COLUMN_NAME = 'target_value') = 0,
  'ALTER TABLE `okr_key_results` ADD COLUMN `target_value` DECIMAL(14,4) DEFAULT NULL AFTER `kr_title`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_key_results'
     AND COLUMN_NAME = 'current_value') = 0,
  'ALTER TABLE `okr_key_results` ADD COLUMN `current_value` DECIMAL(14,4) DEFAULT 0.0000 AFTER `target_value`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'okr_key_results'
     AND COLUMN_NAME = 'initiative_name') = 0,
  'ALTER TABLE `okr_key_results` ADD COLUMN `initiative_name` TEXT DEFAULT NULL AFTER `initiative_text`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3) logfile: central audit table
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `logfile` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL DEFAULT '',
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(100) NOT NULL DEFAULT '',
  `record_id` VARCHAR(100) DEFAULT NULL,
  `detail` TEXT DEFAULT NULL,
  `old_values` TEXT DEFAULT NULL,
  `new_values` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_module` (`module`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3b) Extend logfile with unified audit columns (idempotent)
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'logfile'
     AND COLUMN_NAME = 'user_id') = 0,
  'ALTER TABLE `logfile` ADD COLUMN `user_id` INT DEFAULT NULL AFTER `username`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'logfile'
     AND COLUMN_NAME = 'user_agent') = 0,
  'ALTER TABLE `logfile` ADD COLUMN `user_agent` TEXT DEFAULT NULL AFTER `ip_address`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'logfile'
     AND COLUMN_NAME = 'old_values') = 0,
  'ALTER TABLE `logfile` ADD COLUMN `old_values` TEXT DEFAULT NULL AFTER `detail`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'logfile'
     AND COLUMN_NAME = 'new_values') = 0,
  'ALTER TABLE `logfile` ADD COLUMN `new_values` TEXT DEFAULT NULL AFTER `old_values`',
  'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 4a) project_documents: uploaded เอกสาร/ร่องรอย files (max 5 per project)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `project_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT UNSIGNED DEFAULT 0,
  `mime_type` VARCHAR(120) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `uploaded_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projdoc_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4) project_strategic_issues: 1-to-many strategy links for projects and OKRs
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `project_strategic_issues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` ENUM('project','okr') NOT NULL DEFAULT 'project',
  `project_id` INT UNSIGNED NOT NULL,
  `strategic_issue_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_source_project_issue` (`source`,`project_id`,`strategic_issue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `project_strategic_issues` (`source`,`project_id`,`strategic_issue_id`)
SELECT 'project', `id`, `strategy_id` FROM `projects` WHERE `strategy_id` IS NOT NULL;

INSERT IGNORE INTO `project_strategic_issues` (`source`,`project_id`,`strategic_issue_id`)
SELECT 'okr', `id`, `strategic_issue_id` FROM `okr_projects` WHERE `strategic_issue_id` IS NOT NULL;

-- -----------------------------------------------------------------------------
-- 5) setting: update system name (idempotent)
-- -----------------------------------------------------------------------------

UPDATE `setting`
SET `office_name` = 'ระบบติดตาม และรายงานผลการขับเคลื่อนตามแผนพัฒนาการศึกษาจังหวัดนราธิวาส'
WHERE `id` = 1;

-- -----------------------------------------------------------------------------
-- 6) kpi_definitions: shared KPI indicators defined by the `plan` / `admin` roles
--    (ชื่อ KPI, ค่าเป้าหมายร้อยละ, ตัวชี้วัดความสำเร็จ) for the province & all agencies
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `kpi_definitions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fiscal_year` VARCHAR(4) NOT NULL,
  `kpi_name` VARCHAR(255) NOT NULL,
  `target_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `success_indicator` TEXT DEFAULT NULL,
  `scope_type` VARCHAR(20) NOT NULL DEFAULT 'province',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_by` VARCHAR(50) DEFAULT NULL,
  `updated_by` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kpi_year` (`fiscal_year`),
  KEY `idx_kpi_scope` (`scope_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7) project_kpis: 1-to-many mapping between projects and shared KPI indicators
--    (1 project can be aligned with many KPI definitions)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `project_kpis` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `kpi_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_kpi` (`project_id`,`kpi_id`),
  KEY `idx_pk_kpi` (`kpi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8) Replace FY2569 seeds with the provincial education development plan
--    (เป้าประสงค์ / ตัวชี้วัด / ค่าเป้าหมาย ตามประเด็นยุทธศาสตร์ 5 ยุทธศาสตร์)
--    Idempotent: run ได้หลายครั้ง, แมปด้วย fiscal_year + issue_no ไม่ผูกกับ id
-- -----------------------------------------------------------------------------

-- 8a) strategic_issues: ตั้งชื่อ 5 ยุทธศาสตร์ปี 2569 ตามแผนฯ ใหม่ + ลบแถวเกิน (issue_no > 5)
UPDATE `strategic_issues`
SET `issue_name` = 'ยุทธศาสตร์ที่ 1: การพัฒนาผู้เรียนเป็นศูนย์กลางและการศึกษาที่ครอบคลุมทุกกลุ่ม'
WHERE `fiscal_year` = '2569' AND `issue_no` = 1;

UPDATE `strategic_issues`
SET `issue_name` = 'ยุทธศาสตร์ที่ 2: การส่งเสริมผู้ประกอบการเพื่อสังคมและการใช้ทุนวัฒนธรรมในพื้นที่'
WHERE `fiscal_year` = '2569' AND `issue_no` = 2;

UPDATE `strategic_issues`
SET `issue_name` = 'ยุทธศาสตร์ที่ 3: การพัฒนาครูและผู้บริหารสถานศึกษาในฐานะผู้นำการเปลี่ยนแปลง'
WHERE `fiscal_year` = '2569' AND `issue_no` = 3;

UPDATE `strategic_issues`
SET `issue_name` = 'ยุทธศาสตร์ที่ 4: การนิเทศ ติดตาม และตรวจราชการเชิงพัฒนา'
WHERE `fiscal_year` = '2569' AND `issue_no` = 4;

UPDATE `strategic_issues`
SET `issue_name` = 'ยุทธศาสตร์ที่ 5: การสร้างความร่วมมือและการมีส่วนร่วมของทุกภาคส่วน'
WHERE `fiscal_year` = '2569' AND `issue_no` = 5;

DELETE FROM `strategic_issues` WHERE `fiscal_year` = '2569' AND `issue_no` > 5;

-- 8b) kpi_definitions: ลบ KPI ปี 2569 ชุดเดิม แล้วนำเข้า 10 ตัวชี้วัดใหม่ตามแผนฯ
--     (project_kpis ไม่มี FK กับ kpi_definitions → ลบ/แทนที่ได้ปลอดภัย;
--      โครงการที่เคยผูก KPI เดิมไว้ต้องไปผูก KPI ชุดใหม่ผ่านระบบอีกครั้ง)
DELETE FROM `kpi_definitions` WHERE `fiscal_year` = '2569';

INSERT INTO `kpi_definitions` (`id`, `fiscal_year`, `kpi_name`, `target_percent`, `success_indicator`, `scope_type`, `status`, `created_by`) VALUES
(1, '2569', 'ร้อยละของผู้เรียนที่มีระดับการพัฒนาสมรรถนะตามช่วงวัยตามเกณฑ์ที่กำหนด', 60.00, 'ผู้เรียนได้รับการประเมินสมรรถนะตามช่วงวัยและมีผลการพัฒนาตามเกณฑ์ที่กำหนด', 'province', 'active', 'admin'),
(2, '2569', 'อัตราการคงอยู่ในระบบการศึกษาของผู้เรียนในแต่ละปีการศึกษา', 60.00, 'ผู้เรียนคงอยู่ในระบบการศึกษาในแต่ละปีการศึกษาตามเกณฑ์ที่กำหนด', 'province', 'active', 'admin'),
(3, '2569', 'ร้อยละของผู้เรียนที่มีคุณลักษณะที่พึงประสงค์ตามกรอบคุณลักษณะของจังหวัด อยู่ในระดับ "ดี" ขึ้นไป', 70.00, 'ผู้เรียนมีคุณลักษณะที่พึงประสงค์ตามกรอบคุณลักษณะของจังหวัดอยู่ในระดับ "ดี" ขึ้นไป', 'province', 'active', 'admin'),
(4, '2569', 'ร้อยละของผู้เรียนที่ผ่านการประเมินสมรรถนะอาชีพตามสาขาอาชีพหรือทักษะอาชีพที่สอดคล้องกับบริบทพื้นที่จังหวัดนราธิวาส', 60.00, 'ผู้เรียนผ่านการประเมินสมรรถนะอาชีพตามสาขาอาชีพหรือทักษะอาชีพที่สอดคล้องกับบริบทพื้นที่จังหวัดนราธิวาส', 'province', 'active', 'admin'),
(5, '2569', 'ร้อยละของสถานศึกษาที่จัดโครงงานหรือกิจกรรมผู้ประกอบการเพื่อสังคม (Social Entrepreneurship Project/Activity) อย่างน้อย 1 กิจกรรมต่อปีการศึกษา', 60.00, 'สถานศึกษาจัดโครงงานหรือกิจกรรมผู้ประกอบการเพื่อสังคมอย่างน้อย 1 กิจกรรมต่อปีการศึกษา', 'province', 'active', 'admin'),
(6, '2569', 'ร้อยละของสถานศึกษาที่บูรณาการทุนวัฒนธรรมท้องถิ่นเข้าในหลักสูตรหรือกิจกรรมการเรียนการสอนอย่างเป็นรูปธรรม', 60.00, 'สถานศึกษาบูรณาการทุนวัฒนธรรมท้องถิ่นเข้าในหลักสูตรหรือกิจกรรมการเรียนการสอนอย่างเป็นรูปธรรม', 'province', 'active', 'admin'),
(7, '2569', 'ร้อยละของผู้เรียนที่ได้รับการประเมินสมรรถนะจากการปฏิบัติจริง ภาระงาน หรือแฟ้มสะสมงาน (Portfolio)', 60.00, 'ผู้เรียนได้รับการประเมินสมรรถนะจากการปฏิบัติจริง ภาระงาน หรือแฟ้มสะสมงาน (Portfolio)', 'province', 'active', 'admin'),
(8, '2569', 'ร้อยละของสถานศึกษาที่รายงานผลลัพธ์เชิงพื้นที่ (Impact) ต่อชุมชน ผู้เรียน หรือพื้นที่โดยรอบ อย่างน้อย 1 เรื่องต่อปีการศึกษา', 60.00, 'สถานศึกษารายงานผลลัพธ์เชิงพื้นที่ (Impact) ต่อชุมชน ผู้เรียน หรือพื้นที่โดยรอบอย่างน้อย 1 เรื่องต่อปีการศึกษา', 'province', 'active', 'admin'),
(9, '2569', 'ร้อยละของสถานศึกษาที่จัดการเรียนรู้ร่วมกับชุมชนอย่างน้อย 1 รูปแบบต่อปีการศึกษา', 60.00, 'สถานศึกษาจัดการเรียนรู้ร่วมกับชุมชนอย่างน้อย 1 รูปแบบต่อปีการศึกษา', 'province', 'active', 'admin'),
(10, '2569', 'ร้อยละของสถานศึกษาที่สนับสนุนกิจกรรมการเรียนรู้ตลอดชีวิตในชุมชนอย่างน้อย 1 รูปแบบต่อปีการศึกษา', 60.00, 'สถานศึกษาสนับสนุนกิจกรรมการเรียนรู้ตลอดชีวิตในชุมชนอย่างน้อย 1 รูปแบบต่อปีการศึกษา', 'province', 'active', 'admin')
ON DUPLICATE KEY UPDATE
  `kpi_name` = VALUES(`kpi_name`),
  `target_percent` = VALUES(`target_percent`),
  `success_indicator` = VALUES(`success_indicator`),
  `scope_type` = VALUES(`scope_type`),
  `status` = VALUES(`status`);

SELECT 'migration_upgrade.sql applied successfully' AS status;
