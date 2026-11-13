ALTER TABLE payroll_document_lines
 ADD COLUMN supervision_deduction DECIMAL(18,2) NULL,
 ADD COLUMN coordination_deduction DECIMAL(18,2) NULL,
 ADD COLUMN overhead_deduction DECIMAL(18,2) NULL,
 ADD COLUMN insurance_deduction DECIMAL(18,2) NULL,
 ADD COLUMN total_deductions DECIMAL(18,2) NULL;
-- Existing documents intentionally remain NULL: they cannot be retroactively assigned historical rates.
