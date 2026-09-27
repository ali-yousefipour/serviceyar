INSERT IGNORE INTO permissions(name,title) VALUES ('messages.view','مشاهده پیام‌ها'),('reports.manage','تولید گزارش'),('monitoring.manage','مدیریت پایش'),('ai.use','استفاده از دستیار هوشمند');

CREATE TABLE IF NOT EXISTS student_contracts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL, student_id BIGINT UNSIGNED NOT NULL, contract_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'active', amount DECIMAL(18,2) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_student_contract(student_id,contract_id), INDEX idx_student_contracts_agency(agency_id),
 CONSTRAINT fk_student_contracts_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
 CONSTRAINT fk_student_contracts_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
 CONSTRAINT fk_student_contracts_contract FOREIGN KEY(contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_contracts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL, driver_id BIGINT UNSIGNED NOT NULL, contract_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'active', amount DECIMAL(18,2) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_driver_contract(driver_id,contract_id), INDEX idx_driver_contracts_agency(agency_id),
 CONSTRAINT fk_driver_contracts_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
 CONSTRAINT fk_driver_contracts_driver FOREIGN KEY(driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
 CONSTRAINT fk_driver_contracts_contract FOREIGN KEY(contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL,
 action VARCHAR(150) NOT NULL, entity_type VARCHAR(100) NULL, entity_id BIGINT UNSIGNED NULL, metadata JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_activity_agency_date(agency_id,created_at),
 CONSTRAINT fk_activity_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE SET NULL,
 CONSTRAINT fk_activity_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_locations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL, service_id BIGINT UNSIGNED NOT NULL,
 latitude DECIMAL(10,7) NOT NULL, longitude DECIMAL(10,7) NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_service_locations(service_id,recorded_at), INDEX idx_service_locations_agency(agency_id),
 CONSTRAINT fk_service_locations_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
 CONSTRAINT fk_service_locations_service FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_conversations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, agency_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_ai_conversations_scope(agency_id,user_id),
 CONSTRAINT fk_ai_conv_agency FOREIGN KEY(agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
 CONSTRAINT fk_ai_conv_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, conversation_id BIGINT UNSIGNED NOT NULL, role VARCHAR(20) NOT NULL, content TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_ai_messages_conversation(conversation_id),
 CONSTRAINT fk_ai_messages_conversation FOREIGN KEY(conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
