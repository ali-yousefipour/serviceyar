CREATE TABLE IF NOT EXISTS contract_number_sequence (
 id TINYINT UNSIGNED PRIMARY KEY,
 next_number BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO contract_number_sequence(id,next_number) VALUES(1,100000);
CREATE TABLE IF NOT EXISTS contract_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 contract_id BIGINT UNSIGNED NOT NULL,
 edited_by BIGINT UNSIGNED NOT NULL,
 previous_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_contract_revision (agency_id,contract_id),
 CONSTRAINT fk_contract_revision_contract FOREIGN KEY(contract_id) REFERENCES contracts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
