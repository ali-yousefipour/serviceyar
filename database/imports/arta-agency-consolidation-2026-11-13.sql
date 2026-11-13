-- Safe explicit maintenance script for the two Arta agencies, based on 2026-11-13 snapshot.
-- Back up production before import. No cross-database privileges required.
-- Agency id=1 remains operational with all schools, students, staff, finance and tariffs.
-- Empty duplicate id=6 has one agency_users membership: back it up and detach it.
-- Never grant the orphaned account access to agency 1 automatically.

CREATE TABLE IF NOT EXISTS arta_duplicate_agency_backup_20261113 LIKE agencies;
CREATE TABLE IF NOT EXISTS arta_duplicate_membership_backup_20261113 (
 old_agency_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (old_agency_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$
DROP PROCEDURE IF EXISTS sy_consolidate_arta_20261113$$
CREATE PROCEDURE sy_consolidate_arta_20261113()
main: BEGIN
 DECLARE finished INT DEFAULT 0;
 DECLARE current_table VARCHAR(128);
 DECLARE operational_rows INT DEFAULT 0;
 DECLARE duplicate_rows INT DEFAULT 0;
 DECLARE membership_rows INT DEFAULT 0;
 DECLARE dependent_rows BIGINT DEFAULT 0;
 DECLARE dependencies CURSOR FOR
  SELECT c.TABLE_NAME
  FROM information_schema.COLUMNS c
  JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME
  WHERE c.TABLE_SCHEMA=DATABASE() AND c.COLUMN_NAME='agency_id'
    AND t.TABLE_TYPE='BASE TABLE' AND c.TABLE_NAME<>'agency_users';
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished=1;
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

 SELECT COUNT(*) INTO operational_rows FROM agencies
 WHERE id=1 AND name IN ('آرتانوین','آرتا نوین','آرتا نوین یزدان خراسان');
 IF operational_rows<>1 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operational Arta company 1 unexpected; aborting';
 END IF;
 SELECT COUNT(*) INTO duplicate_rows FROM agencies WHERE id=6 AND name='آرتا نوین یزدان خراسان';
 IF duplicate_rows=0 THEN
  SELECT COUNT(*) INTO operational_rows FROM agencies WHERE id=1 AND name='آرتا نوین یزدان خراسان';
  IF operational_rows=1 THEN LEAVE main; END IF;
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate Arta company 6 unexpected; aborting';
 END IF;
 SELECT COUNT(*) INTO membership_rows FROM agency_users WHERE agency_id=6;
 IF membership_rows>1 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate has additional user memberships; inspect before deletion';
 END IF;
 OPEN dependencies;
 dependency_loop: LOOP
  FETCH dependencies INTO current_table;
  IF finished=1 THEN LEAVE dependency_loop; END IF;
  SET @sy_rows=0;
  SET @sy_sql=CONCAT('SELECT COUNT(*) INTO @sy_rows FROM ',
   CHAR(96),REPLACE(current_table,CHAR(96),CONCAT(CHAR(96),CHAR(96))),CHAR(96),' WHERE agency_id=6');
  PREPARE sy_stmt FROM @sy_sql;
  EXECUTE sy_stmt;
  DEALLOCATE PREPARE sy_stmt;
  SET dependent_rows=@sy_rows;
  IF dependent_rows>0 THEN
   CLOSE dependencies;
   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Company 6 has other dependent rows; deletion blocked';
  END IF;
 END LOOP;
 CLOSE dependencies;

 START TRANSACTION;
 INSERT IGNORE INTO arta_duplicate_agency_backup_20261113 SELECT * FROM agencies WHERE id=6;
 INSERT IGNORE INTO arta_duplicate_membership_backup_20261113(old_agency_id,user_id)
  SELECT agency_id,user_id FROM agency_users WHERE agency_id=6;
 DELETE FROM agency_users WHERE agency_id=6;
 UPDATE agencies SET name='آرتا نوین یزدان خراسان' WHERE id=1;
 DELETE FROM agencies WHERE id=6 AND name='آرتا نوین یزدان خراسان';
 IF ROW_COUNT()<>1 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Duplicate deletion did not affect exactly one row';
 END IF;
 COMMIT;
END main$$
DELIMITER ;

CALL sy_consolidate_arta_20261113();
DROP PROCEDURE IF EXISTS sy_consolidate_arta_20261113;
SELECT id,name,code,is_active FROM agencies WHERE id IN (1,6);
SELECT COUNT(*) AS operational_schools FROM schools WHERE agency_id=1;
