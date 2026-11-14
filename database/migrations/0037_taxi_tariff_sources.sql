-- Global Taxi Organization tariff overrides; immutable bundled Excel defaults are used when no override exists.
-- Execute once through the existing migration runner. Historical student and service amounts remain unchanged.
CREATE TABLE IF NOT EXISTS taxi_organization_tariffs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 academic_year VARCHAR(20) NOT NULL,
 district TINYINT UNSIGNED NOT NULL,
 vehicle_class VARCHAR(16) NOT NULL,
 distance_km SMALLINT UNSIGNED NOT NULL,
 driver_monthly_irr BIGINT UNSIGNED NOT NULL,
 parent_monthly_irr BIGINT UNSIGNED NOT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_taxi_tariff(academic_year,district,vehicle_class,distance_km),
 KEY ix_taxi_tariff_editor(academic_year,vehicle_class,district,distance_km)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Explicit source selection. Existing historical amounts are not recalculated by this migration.
ALTER TABLE students ADD COLUMN fee_source VARCHAR(12) NOT NULL DEFAULT 'taxi';
ALTER TABLE services ADD COLUMN fee_source VARCHAR(12) NOT NULL DEFAULT 'taxi';
