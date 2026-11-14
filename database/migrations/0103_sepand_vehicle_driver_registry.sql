-- Sepand driver and vehicle registry (two independent report feeds).
-- The source reports identify company by national identity, not internal agency ID.
ALTER TABLE agencies ADD COLUMN sepand_national_id VARCHAR(20) NULL;
CREATE UNIQUE INDEX uq_agencies_sepand_national_id ON agencies(sepand_national_id);
ALTER TABLE drivers
 ADD COLUMN gender VARCHAR(20) NULL,
 ADD COLUMN sepand_request_id VARCHAR(32) NULL,
 ADD COLUMN sepand_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
 ADD COLUMN sepand_status_updated_at DATETIME NULL;
CREATE INDEX idx_drivers_sepand_request ON drivers(agency_id,sepand_request_id);
ALTER TABLE vehicles
 ADD COLUMN vin VARCHAR(40) NULL,
 ADD COLUMN plate_part_1 VARCHAR(2) NULL,
 ADD COLUMN plate_letter VARCHAR(8) NULL,
 ADD COLUMN plate_part_3 VARCHAR(3) NULL,
 ADD COLUMN plate_iran VARCHAR(2) NULL,
 ADD COLUMN vehicle_type VARCHAR(80) NULL,
 ADD COLUMN vehicle_system VARCHAR(100) NULL,
 ADD COLUMN vehicle_trim VARCHAR(120) NULL,
 ADD COLUMN make_year SMALLINT UNSIGNED NULL,
 ADD COLUMN fuel_type VARCHAR(80) NULL,
 ADD COLUMN color VARCHAR(70) NULL,
 ADD COLUMN engine_number VARCHAR(100) NULL,
 ADD COLUMN chassis_number VARCHAR(100) NULL,
 ADD COLUMN insurance_number VARCHAR(120) NULL,
 ADD COLUMN sepand_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
 ADD COLUMN sepand_status_updated_at DATETIME NULL;
CREATE INDEX idx_vehicles_sepand_vin ON vehicles(agency_id,vin);
CREATE TABLE IF NOT EXISTS sepand_import_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 file_hash CHAR(64) NOT NULL,
 performed_by BIGINT UNSIGNED NOT NULL,
 source_driver_rows INT UNSIGNED NOT NULL,
 source_vehicle_rows INT UNSIGNED NOT NULL,
 driver_created INT UNSIGNED NOT NULL DEFAULT 0,
 driver_updated INT UNSIGNED NOT NULL DEFAULT 0,
 vehicle_created INT UNSIGNED NOT NULL DEFAULT 0,
 vehicle_updated INT UNSIGNED NOT NULL DEFAULT 0,
 skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
 report_json LONGTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_sepand_import_hash(file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
