-- Private per-user profile details and photo metadata; existing users remain valid.
ALTER TABLE users
 ADD COLUMN mobile VARCHAR(15) NULL,
 ADD COLUMN national_code VARCHAR(10) NULL,
 ADD COLUMN email VARCHAR(190) NULL,
 ADD COLUMN address VARCHAR(1000) NULL,
 ADD COLUMN postal_code VARCHAR(10) NULL,
 ADD COLUMN emergency_phone VARCHAR(15) NULL,
 ADD COLUMN photo_version INT UNSIGNED NOT NULL DEFAULT 0;
CREATE INDEX idx_users_national_code ON users (national_code);
CREATE INDEX idx_users_mobile ON users (mobile);
