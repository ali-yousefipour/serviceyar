-- Each confirmed payroll draft keeps the service/day breakdown used to derive
-- its driver gross. Financial history must not change when students move later.
CREATE TABLE IF NOT EXISTS payroll_service_day_snapshots (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 service_title VARCHAR(200) NOT NULL,
 fee_source VARCHAR(12) NOT NULL,
 student_count INT UNSIGNED NOT NULL DEFAULT 0,
 monthly_gross BIGINT UNSIGNED NOT NULL,
 service_days TINYINT UNSIGNED NOT NULL DEFAULT 30,
 earned_gross BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_payroll_service_snapshot (document_id,driver_id,service_id),
 KEY idx_payroll_service_driver (driver_id,service_id),
 CONSTRAINT fk_payroll_service_snapshot_document
   FOREIGN KEY (document_id) REFERENCES payroll_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
