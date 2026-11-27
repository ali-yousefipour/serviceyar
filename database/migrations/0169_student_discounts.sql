-- Discount rate is thousandths of one percent (20000 = 20%).
-- Application must lock the student row before checking overlapping periods.
-- effective_to is exclusive; NULL means through the contract end.
CREATE TABLE IF NOT EXISTS student_discounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 student_id BIGINT UNSIGNED NOT NULL,
 contract_id BIGINT UNSIGNED NOT NULL,
 rate_milli_percent INT UNSIGNED NOT NULL,
 reason TEXT NOT NULL,
 effective_from DATE NOT NULL,
 effective_to DATE NULL,
 apply_to_student TINYINT(1) NOT NULL,
 apply_to_driver TINYINT(1) NOT NULL,
 request_key CHAR(36) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_student_discount_request (agency_id, request_key),
 INDEX idx_student_discount_period (agency_id, student_id, effective_from, effective_to),
 INDEX idx_student_discount_contract (contract_id),
 CONSTRAINT fk_sd_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE RESTRICT,
 CONSTRAINT fk_sd_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 CONSTRAINT fk_sd_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE RESTRICT,
 CONSTRAINT ck_sd_rate CHECK (rate_milli_percent BETWEEN 1 AND 100000),
 CONSTRAINT ck_sd_scope CHECK (apply_to_student IN (0,1) AND apply_to_driver IN (0,1) AND (apply_to_student=1 OR apply_to_driver=1)),
 CONSTRAINT ck_sd_period CHECK (effective_to IS NULL OR effective_to > effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_discount_audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 discount_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(32) NOT NULL,
 before_snapshot JSON NULL,
 after_snapshot JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_sd_audit (discount_id,id),
 CONSTRAINT fk_sd_audit_discount FOREIGN KEY (discount_id) REFERENCES student_discounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
