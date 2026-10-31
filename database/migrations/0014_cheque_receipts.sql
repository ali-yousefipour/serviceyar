CREATE TABLE IF NOT EXISTS cheque_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 bank_name VARCHAR(100) NOT NULL,
 branch_name VARCHAR(100) NULL,
 serial_no VARCHAR(100) NOT NULL,
 bank_account VARCHAR(100) NULL,
 received_from VARCHAR(200) NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(18,2) NOT NULL,
 received_date DATE NOT NULL,
 due_date DATE NOT NULL,
 description VARCHAR(1000) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'received',
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cheque_serial(agency_id,bank_name,serial_no),
 KEY ix_cheque_status_date(agency_id,status,due_date),
 CONSTRAINT fk_cheque_agency FOREIGN KEY(agency_id) REFERENCES agencies(id),
 CONSTRAINT fk_cheque_account FOREIGN KEY(account_id) REFERENCES accounting_accounts(id),
 CONSTRAINT fk_cheque_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cheque_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cheque_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(20) NOT NULL,
 event_date DATE NOT NULL,
 note VARCHAR(1000) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_cheque_event_receipt FOREIGN KEY(cheque_id) REFERENCES cheque_receipts(id),
 CONSTRAINT fk_cheque_event_actor FOREIGN KEY(actor_id) REFERENCES users(id),
 KEY ix_cheque_events(cheque_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
