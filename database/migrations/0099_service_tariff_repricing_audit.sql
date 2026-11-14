-- Run once before using the super-admin legacy tariff repricing operation.
-- Keeps original school/student/service values for reconciliation or rollback.
CREATE TABLE IF NOT EXISTS service_tariff_repricing_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  agency_id BIGINT UNSIGNED NOT NULL,
  entity_type VARCHAR(20) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  old_data JSON NOT NULL,
  new_data JSON NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_tariff_reprice_run_entity (run_id,entity_type,entity_id),
  KEY idx_tariff_reprice_agency (agency_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
