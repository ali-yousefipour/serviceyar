-- Workbook-based assignment staging for Mehr 1405.
-- Import school_assignment_import_mehr1405 data separately; never infer a missing driver.
CREATE TABLE IF NOT EXISTS school_assignment_import_mehr1405 (
 student_national_code VARCHAR(20) NOT NULL PRIMARY KEY,
 school_name VARCHAR(255) NOT NULL,
 driver_name VARCHAR(255) NOT NULL,
 vehicle_class VARCHAR(50) NULL,
 distance_km DECIMAL(10,3) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
