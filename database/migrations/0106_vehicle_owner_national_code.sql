-- Owner national code is agency-scoped. The driver registry itself remains tenant-isolated.
ALTER TABLE vehicles ADD COLUMN owner_national_code VARCHAR(10) NULL AFTER owner_name;
CREATE INDEX idx_vehicles_owner_national_code ON vehicles (agency_id, owner_national_code);
