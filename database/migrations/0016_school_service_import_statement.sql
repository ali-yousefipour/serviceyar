-- Historical source-backed school service statement, not an invoice or ledger.
CREATE TABLE IF NOT EXISTS school_service_import_fees (
 agency_id BIGINT UNSIGNED NOT NULL,
 academic_year VARCHAR(12) NOT NULL,
 period VARCHAR(20) NOT NULL,
 student_national_code VARCHAR(20) NOT NULL,
 student_name VARCHAR(255), driver_name VARCHAR(255), school_name VARCHAR(255),
 distance_km DECIMAL(18,4), service_days DECIMAL(18,4),
 driver_fee DECIMAL(20,4), oversight_fee DECIMAL(20,4),
 municipality_fee DECIMAL(20,4), municipality_vat DECIMAL(20,4),
 road_police_fee DECIMAL(20,4), education_fee DECIMAL(20,4),
 company_fee DECIMAL(20,4), certificate_fee DECIMAL(20,4),
 insurance_fee DECIMAL(20,4), parent_total DECIMAL(20,4),
 statement_total DECIMAL(20,4), guardian_iban VARCHAR(34),
 PRIMARY KEY(agency_id,academic_year,period,student_national_code),
 INDEX idx_school_import_student(agency_id,student_national_code),
 CONSTRAINT fk_school_import_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
