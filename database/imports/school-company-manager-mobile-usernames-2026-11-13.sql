-- Safe one-time migration. Run after importing verified Madares agency profile settings.
-- A backup of users is required. Existing ID, password hash, roles, agency memberships remain intact.
-- Company Arta Novin Yazdan Khorasan (operational agency 1, duplicate agency 6) is excluded.
-- Invalid, duplicate, already occupied or unverified mobile numbers are skipped.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP TEMPORARY TABLE IF EXISTS sy_mobile_username_candidates;
CREATE TEMPORARY TABLE sy_mobile_username_candidates (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 old_username VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 new_username VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 UNIQUE KEY uq_new_username(new_username)
) ENGINE=InnoDB;
INSERT IGNORE INTO sy_mobile_username_candidates(user_id,agency_id,old_username,new_username)
SELECT u.id,a.id,u.username,
 REPLACE(REPLACE(REPLACE(TRIM(st.setting_value),' ',''),'-',''),'‌','') AS mobile
FROM agencies a
JOIN agency_users au ON au.agency_id=a.id
JOIN users u ON u.id=au.user_id AND u.is_active=1
JOIN user_roles ur ON ur.user_id=u.id
JOIN roles r ON r.id=ur.role_id AND r.name='school_company_manager'
JOIN agency_settings st ON st.agency_id=a.id AND st.setting_key='company_ceo_mobile'
LEFT JOIN users occupied ON occupied.username=REPLACE(REPLACE(REPLACE(TRIM(st.setting_value),' ',''),'-',''),'‌','') AND occupied.id<>u.id
WHERE a.id NOT IN (1,6)
 AND a.name NOT LIKE '%آرتا نوین یزدان خراسان%'
 AND a.code REGEXP '^sr1405_[0-9]{3}$'
 AND u.username=CONCAT('schoolco_',SUBSTRING(a.code,8))
 AND REPLACE(REPLACE(REPLACE(TRIM(st.setting_value),' ',''),'-',''),'‌','') REGEXP '^09[0-9]{9}$'
 AND occupied.id IS NULL
 AND (SELECT COUNT(*) FROM agency_users other_link WHERE other_link.user_id=u.id)=1
 AND (SELECT COUNT(*) FROM agency_settings st2 WHERE st2.setting_key='company_ceo_mobile'
      AND REPLACE(REPLACE(REPLACE(TRIM(st2.setting_value),' ',''),'-',''),'‌','')=
          REPLACE(REPLACE(REPLACE(TRIM(st.setting_value),' ',''),'-',''),'‌',''))=1;
SELECT c.agency_id,a.name AS company,c.user_id,c.old_username,c.new_username
 FROM sy_mobile_username_candidates c JOIN agencies a ON a.id=c.agency_id ORDER BY a.name;
SELECT COUNT(*) AS eligible_username_changes FROM sy_mobile_username_candidates;
CREATE TABLE IF NOT EXISTS sy_company_username_backup_20261113 (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 old_username VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 new_username VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
START TRANSACTION;
INSERT IGNORE INTO sy_company_username_backup_20261113(user_id,agency_id,old_username,new_username)
 SELECT user_id,agency_id,old_username,new_username FROM sy_mobile_username_candidates;
UPDATE users u JOIN sy_mobile_username_candidates c ON c.user_id=u.id
 SET u.username=c.new_username
 WHERE u.username=c.old_username AND c.new_username<>c.old_username;
SELECT ROW_COUNT() AS usernames_updated;
COMMIT;
SELECT COUNT(*) AS remaining_generated_accounts FROM users WHERE username REGEXP '^schoolco_[0-9]{3}$';
