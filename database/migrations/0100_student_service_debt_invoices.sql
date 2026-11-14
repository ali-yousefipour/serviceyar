-- A unique invoice binding for each student / academic year; avoids duplicates on reassignment.
CREATE TABLE IF NOT EXISTS student_service_debt_invoices (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 agency_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL,
 academic_year VARCHAR(20) NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL,
 service_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_student_service_year (agency_id,student_id,academic_year),
 UNIQUE KEY uq_student_service_invoice (invoice_id),
 KEY idx_service_billing_service (service_id),
 CONSTRAINT fk_service_billing_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
 CONSTRAINT fk_service_billing_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 CONSTRAINT fk_service_billing_service FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
