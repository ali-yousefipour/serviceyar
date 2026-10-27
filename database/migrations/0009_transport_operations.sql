ALTER TABLE schools ADD COLUMN gender VARCHAR(20) NULL, ADD COLUMN shift VARCHAR(50) NULL, ADD COLUMN education_levels VARCHAR(255) NULL;
ALTER TABLE students
 ADD COLUMN guardian_first_name VARCHAR(100) NULL, ADD COLUMN guardian_last_name VARCHAR(120) NULL,
 ADD COLUMN guardian_national_code VARCHAR(20) NULL, ADD COLUMN guardian_relation VARCHAR(40) NULL,
 ADD COLUMN father_name VARCHAR(100) NULL, ADD COLUMN gender VARCHAR(20) NULL,
 ADD COLUMN nationality VARCHAR(40) NOT NULL DEFAULT 'iranian', ADD COLUMN health_status VARCHAR(255) NULL,
 ADD COLUMN emergency_phone VARCHAR(100) NULL, ADD COLUMN return_address TEXT NULL,
 ADD COLUMN academic_year VARCHAR(20) NULL, ADD COLUMN shift VARCHAR(50) NULL,
 ADD COLUMN service_status VARCHAR(30) NOT NULL DEFAULT 'no_request',
 ADD COLUMN monthly_fee DECIMAL(18,2) NOT NULL DEFAULT 0,
 ADD INDEX idx_student_status(agency_id,service_status,academic_year);
ALTER TABLE drivers ADD COLUMN is_representative TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN bank_name VARCHAR(100) NULL, ADD COLUMN bank_account VARCHAR(50) NULL, ADD COLUMN iban VARCHAR(34) NULL;
ALTER TABLE services ADD COLUMN pack_id BIGINT UNSIGNED NULL, ADD COLUMN shift VARCHAR(50) NULL,
 ADD COLUMN monthly_fee DECIMAL(18,2) NOT NULL DEFAULT 0, ADD COLUMN agreed_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 ADD CONSTRAINT fk_services_pack FOREIGN KEY(pack_id) REFERENCES packs(id) ON DELETE SET NULL;
CREATE TABLE ledger_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL,
 party_type VARCHAR(30) NOT NULL, party_id BIGINT UNSIGNED NOT NULL,
 direction VARCHAR(10) NOT NULL, amount DECIMAL(18,2) NOT NULL, entry_date DATE NOT NULL,
 method VARCHAR(40) NULL, reference_no VARCHAR(120) NULL, note TEXT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_ledger_party(agency_id,party_type,party_id,entry_date),
 FOREIGN KEY(agency_id) REFERENCES agencies(id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE driver_ratings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NOT NULL, score DECIMAL(3,1) NOT NULL, note TEXT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(agency_id) REFERENCES agencies(id), FOREIGN KEY(driver_id) REFERENCES drivers(id),
 INDEX idx_rating_driver(agency_id,driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE parent_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL, subject VARCHAR(255) NOT NULL, body TEXT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'open', response TEXT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(agency_id) REFERENCES agencies(id), FOREIGN KEY(student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='super_admin';
INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r JOIN permissions p
 WHERE r.name='agency_admin' AND p.name IN ('messages.view','messages.manage','finance.view','finance.manage','settings.view','settings.manage','holidays.view','holidays.manage','monitoring.view','ai.use');
