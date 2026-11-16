-- School-scoped debtor reports for company representatives.
-- Custom scope overrides the default schools served by the driver.
CREATE TABLE IF NOT EXISTS representative_settings (
 agency_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NOT NULL,
 custom_school_scope TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY(agency_id,driver_id),
 CONSTRAINT fk_rep_settings_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
 CONSTRAINT fk_rep_settings_driver FOREIGN KEY(driver_id) REFERENCES drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS representative_school_access (
 agency_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NOT NULL,
 school_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(agency_id,driver_id,school_id),
 INDEX idx_rep_school(agency_id,school_id),
 CONSTRAINT fk_rep_access_driver FOREIGN KEY(driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
 CONSTRAINT fk_rep_access_school FOREIGN KEY(school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A standalone representative is a portal user, NOT a fictional driver.
CREATE TABLE IF NOT EXISTS representative_accounts (
 agency_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY(agency_id,user_id),
 CONSTRAINT fk_rep_account_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
 CONSTRAINT fk_rep_account_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS representative_account_schools (
 agency_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 school_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(agency_id,user_id,school_id),
 CONSTRAINT fk_rep_account_scoped FOREIGN KEY(agency_id,user_id) REFERENCES representative_accounts(agency_id,user_id) ON DELETE CASCADE,
 CONSTRAINT fk_rep_account_school FOREIGN KEY(school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles(name,title) VALUES ('representative_portal','نماینده مدارس');
