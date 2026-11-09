CREATE TABLE IF NOT EXISTS agency_school_tariffs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 academic_year VARCHAR(20) NOT NULL,
 region VARCHAR(100) NOT NULL DEFAULT '',
 vehicle_class TINYINT UNSIGNED NOT NULL,
 distance_km SMALLINT UNSIGNED NOT NULL,
 amount_irr BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_agency_tariff(agency_id,academic_year,region,vehicle_class,distance_km),
 CONSTRAINT fk_tariff_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE students
 ADD COLUMN tariff_distance_km DECIMAL(10,3) NULL,
 ADD COLUMN tariff_vehicle_class TINYINT UNSIGNED NULL,
 ADD COLUMN tariff_calculated_at DATETIME NULL;
-- Preserve historical fees and import distances only when unambiguous.
UPDATE students s JOIN (
 SELECT agency_id,student_national_code,MIN(distance_km) distance_km
 FROM school_service_import_fees WHERE distance_km IS NOT NULL AND distance_km>0
 GROUP BY agency_id,student_national_code HAVING COUNT(DISTINCT distance_km)=1
) d ON d.agency_id=s.agency_id AND d.student_national_code=s.national_code
SET s.tariff_distance_km=d.distance_km WHERE s.tariff_distance_km IS NULL;
