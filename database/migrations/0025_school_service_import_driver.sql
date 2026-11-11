ALTER TABLE school_service_import_fees
 ADD COLUMN driver_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_school_import_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL;
