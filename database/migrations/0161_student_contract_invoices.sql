
-- Contract tuition binding. Legacy invoices remain intact until the next explicit issuance.
CREATE TABLE IF NOT EXISTS student_contract_invoices (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL,
 academic_year VARCHAR(20) NOT NULL,
 contract_id BIGINT UNSIGNED NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_contract_student_year (agency_id,student_id,academic_year),
 UNIQUE KEY uq_contract_invoice_contract (contract_id),
 UNIQUE KEY uq_contract_invoice_invoice (invoice_id),
 CONSTRAINT fk_contract_billing_contract FOREIGN KEY(contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_contract_billing_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
 CONSTRAINT fk_contract_billing_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
