-- Vehicle owner is distinct from assigned driver.
ALTER TABLE vehicles ADD COLUMN owner_name VARCHAR(200) NULL AFTER plate;
