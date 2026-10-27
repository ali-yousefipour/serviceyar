CREATE TABLE IF NOT EXISTS accounting_document_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 description VARCHAR(1000) NULL,
 tracking_no VARCHAR(100) NULL,
 debit DECIMAL(18,2) NOT NULL DEFAULT 0,
 credit DECIMAL(18,2) NOT NULL DEFAULT 0,
 CONSTRAINT fk_journal_document FOREIGN KEY(document_id) REFERENCES accounting_documents(id) ON DELETE CASCADE,
 CONSTRAINT fk_journal_account FOREIGN KEY(account_id) REFERENCES accounting_accounts(id),
 KEY ix_journal_account(account_id,document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
