-- Effective-dated vehicle class changes. Financial snapshots are append-only;
-- never rewrite an issued contract, paid invoice, or finalized statement.
CREATE TABLE IF NOT EXISTS vehicle_class_changes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agency_id BIGINT UNSIGNED NOT NULL,
  driver_id BIGINT UNSIGNED NOT NULL,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  old_vehicle_model VARCHAR(255) NULL,
  new_vehicle_model VARCHAR(255) NULL,
  old_vehicle_class TINYINT UNSIGNED NULL,
  new_vehicle_class TINYINT UNSIGNED NOT NULL,
  contract_effective_date DATE NULL,
  update_student_debt TINYINT(1) NOT NULL DEFAULT 0,
  student_debt_effective_date DATE NULL,
  update_driver_pay TINYINT(1) NOT NULL DEFAULT 0,
  driver_pay_effective_date DATE NULL,
  old_vehicle_snapshot JSON NOT NULL,
  new_vehicle_snapshot JSON NOT NULL,
  decision_snapshot JSON NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vehicle_class_change_driver (agency_id, driver_id, contract_effective_date, id),
  INDEX idx_vehicle_class_change_vehicle (agency_id, vehicle_id, id),
  CONSTRAINT fk_vehicle_class_change_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_class_rate_periods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agency_id BIGINT UNSIGNED NOT NULL,
  change_id BIGINT UNSIGNED NOT NULL,
  subject_type ENUM('student_debt','driver_pay') NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  effective_date DATE NOT NULL,
  old_class TINYINT UNSIGNED NULL,
  new_class TINYINT UNSIGNED NOT NULL,
  old_monthly_irr BIGINT NOT NULL,
  new_monthly_irr BIGINT NOT NULL,
  fee_source VARCHAR(24) NOT NULL,
  calculation_snapshot JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicle_change_subject (change_id, subject_type, subject_id, service_id),
  INDEX idx_vehicle_class_period_subject (agency_id, subject_type, subject_id, effective_date),
  CONSTRAINT fk_vehicle_class_period_change FOREIGN KEY (change_id) REFERENCES vehicle_class_changes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financial and contract appendices are independent of original issued records.
CREATE TABLE IF NOT EXISTS vehicle_class_contract_appendices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agency_id BIGINT UNSIGNED NOT NULL,
  change_id BIGINT UNSIGNED NOT NULL,
  contract_id BIGINT UNSIGNED NOT NULL,
  effective_date DATE NOT NULL,
  old_class TINYINT UNSIGNED NULL,
  new_class TINYINT UNSIGNED NOT NULL,
  original_contract_snapshot JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicle_class_contract_appendix (change_id,contract_id),
  INDEX idx_vehicle_contract_appendix (agency_id,contract_id),
  CONSTRAINT fk_vehicle_appendix_change FOREIGN KEY (change_id) REFERENCES vehicle_class_changes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_class_debt_adjustments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agency_id BIGINT UNSIGNED NOT NULL,
  change_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  academic_year VARCHAR(15) NOT NULL,
  effective_date DATE NOT NULL,
  amount_irr BIGINT NOT NULL,
  rate_snapshot JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicle_debt_change_student (change_id,student_id,service_id),
  INDEX idx_vehicle_debt_student_year (agency_id,student_id,academic_year),
  CONSTRAINT fk_vehicle_debt_change FOREIGN KEY (change_id) REFERENCES vehicle_class_changes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
