-- Versioned contract templates and immutable issuance snapshots.
CREATE TABLE IF NOT EXISTS contract_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kind VARCHAR(20) NOT NULL,
 version INT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 body_html LONGTEXT NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_contract_template_kind_version (kind,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE contracts
 ADD COLUMN template_id BIGINT UNSIGNED NULL,
 ADD COLUMN rendered_html LONGTEXT NULL,
 ADD COLUMN snapshot_json LONGTEXT NULL,
 ADD COLUMN issued_at DATETIME NULL;
CREATE INDEX idx_contracts_agency_party ON contracts(agency_id,party_type,party_id);
