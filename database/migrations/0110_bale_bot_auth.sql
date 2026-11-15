-- Bale bot, expiring account links and OTP challenges. Run after platform settings.
CREATE TABLE IF NOT EXISTS bale_user_links (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 chat_id VARCHAR(80) NOT NULL UNIQUE,
 linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_bale_user_links_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS bale_auth_challenges (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 purpose ENUM('link','login') NOT NULL,
 code_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 consumed_at DATETIME NULL,
 attempt_count INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_bale_auth_user (user_id,purpose,expires_at),
 CONSTRAINT fk_bale_auth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS bale_webhook_updates (
 update_id VARCHAR(80) NOT NULL PRIMARY KEY,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS bale_otp_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ip_address VARCHAR(45) NOT NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_bale_otp_ip (ip_address,requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
