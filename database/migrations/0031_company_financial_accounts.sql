-- Company bank metadata and the account used for every new receipt/payment.
ALTER TABLE accounting_accounts
  ADD COLUMN bank_name VARCHAR(120) NULL AFTER title,
  ADD COLUMN bank_account VARCHAR(100) NULL AFTER bank_name,
  ADD COLUMN iban VARCHAR(34) NULL AFTER bank_account;
ALTER TABLE ledger_entries
  ADD COLUMN company_account_id BIGINT UNSIGNED NULL AFTER party_id,
  ADD INDEX idx_ledger_company_account (agency_id,company_account_id),
  ADD CONSTRAINT fk_ledger_company_account FOREIGN KEY (company_account_id) REFERENCES accounting_accounts(id) ON DELETE RESTRICT;
-- Legacy transactions retain NULL company_account_id; new transactions must specify an account in the domain service.
