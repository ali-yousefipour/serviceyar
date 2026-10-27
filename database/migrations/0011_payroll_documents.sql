CREATE TABLE payroll_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL,
 document_no VARCHAR(80) NOT NULL, date_from DATE NOT NULL, date_to DATE NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft', total_amount DECIMAL(18,2) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_payroll_period(agency_id,date_from,date_to),
 FOREIGN KEY(agency_id) REFERENCES agencies(id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE payroll_document_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NOT NULL, earned DECIMAL(18,2) NOT NULL, agreed DECIMAL(18,2) NOT NULL,
 paid DECIMAL(18,2) NOT NULL, salary DECIMAL(18,2) NOT NULL,
 UNIQUE KEY uq_payroll_driver(document_id,driver_id),
 FOREIGN KEY(document_id) REFERENCES payroll_documents(id) ON DELETE CASCADE,
 FOREIGN KEY(driver_id) REFERENCES drivers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
