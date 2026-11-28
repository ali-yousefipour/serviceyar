-- Cancellation and termination are stateful accounting revisions, never DELETE.
-- The initial approved invoice and accounting documents remain immutable.
ALTER TABLE student_discounts
  ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'active',
  ADD COLUMN ended_at DATE NULL,
  ADD COLUMN updated_at DATETIME NULL,
  ADD INDEX idx_student_discounts_status (agency_id,status,effective_from,effective_to);
