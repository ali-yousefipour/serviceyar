-- Vehicle is the source of truth for its tariff class; legacy vehicles
-- remain nullable until their exact class is confirmed in the vehicle editor.
ALTER TABLE vehicles
 ADD COLUMN tariff_vehicle_class TINYINT UNSIGNED NULL AFTER model;
