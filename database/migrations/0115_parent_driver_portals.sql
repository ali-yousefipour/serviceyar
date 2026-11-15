-- Verified Bale contact is the only automatic portal enrollment path.
-- Every permission is scoped to a specific student/driver and agency.
CREATE TABLE IF NOT EXISTS portal_memberships (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 agency_id BIGINT UNSIGNED NOT NULL,
 party_type ENUM('parent','driver') NOT NULL,
 party_id BIGINT UNSIGNED NOT NULL,
 verified_mobile VARCHAR(15) NOT NULL,
 verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_portal_membership (user_id,agency_id,party_type,party_id),
 INDEX idx_portal_party (agency_id,party_type,party_id),
 CONSTRAINT fk_portal_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_portal_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles(name,title) VALUES
 ('parent_portal','ولی دانش‌آموز'),('driver_portal','راننده سرویس مدارس');

CREATE TABLE IF NOT EXISTS portal_vehicle_change_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 agency_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NOT NULL,
 vehicle_id BIGINT UNSIGNED NOT NULL,
 requested_model VARCHAR(100) NULL,
 requested_plate VARCHAR(50) NULL,
 note VARCHAR(700) NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_portal_vehicle_agency(agency_id,driver_id,status),
 CONSTRAINT fk_portal_vehicle_request_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_portal_vehicle_request_agency FOREIGN KEY(agency_id) REFERENCES agencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
