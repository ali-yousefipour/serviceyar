CREATE TABLE IF NOT EXISTS school_monthly_statements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 jalali_year SMALLINT UNSIGNED NOT NULL,
 jalali_month TINYINT UNSIGNED NOT NULL,
 period_start DATE NOT NULL,
 period_end DATE NOT NULL,
 academic_year VARCHAR(15) NOT NULL,
 status ENUM('final','deleted') NOT NULL DEFAULT 'final',
 snapshot_json LONGTEXT NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_by BIGINT UNSIGNED NULL,
 deleted_at DATETIME NULL,
 restored_by BIGINT UNSIGNED NULL,
 restored_at DATETIME NULL,
 INDEX idx_school_statement_agency_period (agency_id,jalali_year,jalali_month,status),
 CONSTRAINT fk_school_statement_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
