-- Preserve historical ownership of financial documents, contracts and audit records.
-- Deletion by the global administrator revokes access and hides the account
-- without removing its original identity or foreign-key references.
ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;
CREATE INDEX idx_users_deleted_at ON users (deleted_at, is_active);
