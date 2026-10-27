ALTER TABLE messages ADD COLUMN recipient_mobile VARCHAR(20) NULL,
 ADD COLUMN provider_id VARCHAR(100) NULL, ADD COLUMN provider_status VARCHAR(40) NULL,
 ADD COLUMN attempts INT NOT NULL DEFAULT 0, ADD COLUMN last_error VARCHAR(255) NULL;
UPDATE messages m JOIN students s ON m.recipient_type='student' AND m.recipient_id=s.id AND m.agency_id=s.agency_id
 SET m.recipient_mobile=s.mobile WHERE m.channel='sms' AND m.status='queued';
UPDATE messages m JOIN drivers d ON m.recipient_type='driver' AND m.recipient_id=d.id AND m.agency_id=d.agency_id
 SET m.recipient_mobile=d.mobile WHERE m.channel='sms' AND m.status='queued';
CREATE TABLE payment_intents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE,
 agency_id BIGINT UNSIGNED NOT NULL, invoice_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 payload CHAR(64) NOT NULL UNIQUE, chat_id VARCHAR(30) NOT NULL, amount BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'created', transaction_id VARCHAR(100) NULL UNIQUE,
 payment_id BIGINT UNSIGNED NULL UNIQUE, expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME NULL,
 INDEX idx_payment_intents_scope(agency_id,invoice_id,status),
 FOREIGN KEY(agency_id) REFERENCES agencies(id), FOREIGN KEY(invoice_id) REFERENCES invoices(id),
 FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(payment_id) REFERENCES payments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
