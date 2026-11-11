-- Central school catalog. Existing tenant schools remain intact.
CREATE TABLE IF NOT EXISTS school_catalog (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 education_code VARCHAR(80) NOT NULL,
 name VARCHAR(255) NOT NULL,
 region VARCHAR(100) NULL,
 gender VARCHAR(50) NULL,
 shift_name VARCHAR(80) NULL,
 education_level VARCHAR(100) NULL,
 address TEXT NULL,
 phone VARCHAR(40) NULL,
 manager_name VARCHAR(200) NULL,
 latitude DECIMAL(10,7) NULL,
 longitude DECIMAL(10,7) NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_school_catalog_code (education_code),
 INDEX idx_school_catalog_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_school_catalog (
 agency_id BIGINT UNSIGNED NOT NULL,
 catalog_school_id BIGINT UNSIGNED NOT NULL,
 linked_school_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (agency_id,catalog_school_id),
 CONSTRAINT fk_asc_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
 CONSTRAINT fk_asc_catalog FOREIGN KEY (catalog_school_id) REFERENCES school_catalog(id) ON DELETE CASCADE,
 CONSTRAINT fk_asc_school FOREIGN KEY (linked_school_id) REFERENCES schools(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
