CREATE TABLE IF NOT EXISTS admin_impersonation_sessions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 admin_user_id BIGINT UNSIGNED NOT NULL,
 target_user_id BIGINT UNSIGNED NOT NULL,
 agency_id BIGINT UNSIGNED NOT NULL,
 admin_session_hash CHAR(64) NOT NULL,
 target_session_hash CHAR(64) NOT NULL UNIQUE,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ended_at DATETIME NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(500) NULL,
 INDEX idx_admin_impersonation_admin_active (admin_user_id, ended_at),
 INDEX idx_admin_impersonation_admin_session (admin_session_hash, ended_at),
 INDEX idx_admin_impersonation_target (target_user_id, ended_at),
 CONSTRAINT fk_admin_impersonation_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_admin_impersonation_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_admin_impersonation_agency FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
