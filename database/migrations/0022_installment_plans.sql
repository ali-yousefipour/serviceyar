CREATE TABLE IF NOT EXISTS installment_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL,
 request_key VARCHAR(64) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 result_json LONGTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_installment_request (agency_id,request_key),
 CONSTRAINT fk_installment_agency FOREIGN KEY (agency_id) REFERENCES agencies(id),
 CONSTRAINT fk_installment_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
