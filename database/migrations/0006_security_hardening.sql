CREATE TABLE IF NOT EXISTS auth_login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(100) NOT NULL,
 ip_address VARCHAR(45) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 succeeded TINYINT(1) NOT NULL DEFAULT 0,
 INDEX idx_login_attempts_ip_time(ip_address,attempted_at),
 INDEX idx_login_attempts_user_time(username,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_idempotency_keys (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 key_value CHAR(64) NOT NULL,
 request_method VARCHAR(10) NOT NULL,
 request_path VARCHAR(255) NOT NULL,
 response_code SMALLINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_idempotency_user_key(user_id,key_value),
 INDEX idx_idempotency_created(created_at),
 CONSTRAINT fk_idempotency_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
