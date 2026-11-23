-- ServiceYar / read-only financial audit / 2026-11-23
-- For phpMyAdmin against taxisc_syar. No INSERT/UPDATE/DELETE/DDL.
-- Requires migration 0168. All dates stored here are ISO/Gregorian.
-- Monetary differences indicate candidates for investigation, not automatic corrections.
SELECT '01_counts' AS audit_section,
 (SELECT COUNT(*) FROM vehicle_class_changes) changes_count,
 (SELECT COUNT(*) FROM vehicle_class_rate_periods) rate_periods_count,
 (SELECT COUNT(*) FROM vehicle_class_contract_appendices) appendices_count,
 (SELECT COUNT(*) FROM vehicle_class_debt_adjustments) adjustments_count;

-- A) Detect rate records whose initial/revised rates are inconsistent in a
-- single student's successive approved periods; changes may overlap different
-- financial streams, so partition by tenant, subject type and subject ID.
SELECT p.agency_id,p.id AS period_id,p.change_id,p.subject_type,
 p.subject_id,p.service_id,p.effective_date,p.old_monthly_irr,p.new_monthly_irr,
 (SELECT prev.new_monthly_irr FROM vehicle_class_rate_periods prev
  WHERE prev.agency_id=p.agency_id AND prev.subject_type=p.subject_type
    AND prev.subject_id=p.subject_id
    AND (prev.effective_date<p.effective_date OR
      (prev.effective_date=p.effective_date AND prev.id<p.id))
  ORDER BY prev.effective_date DESC,prev.id DESC LIMIT 1) AS preceding_new_rate
FROM vehicle_class_rate_periods p
WHERE EXISTS (
 SELECT 1 FROM vehicle_class_rate_periods prev
 WHERE prev.agency_id=p.agency_id AND prev.subject_type=p.subject_type
 AND prev.subject_id=p.subject_id
 AND (prev.effective_date<p.effective_date OR
 (prev.effective_date=p.effective_date AND prev.id<p.id))
 AND prev.new_monthly_irr<>p.old_monthly_irr
 AND prev.id=(SELECT x.id FROM vehicle_class_rate_periods x
 WHERE x.agency_id=p.agency_id AND x.subject_type=p.subject_type
 AND x.subject_id=p.subject_id
 AND (x.effective_date<p.effective_date OR
 (x.effective_date=p.effective_date AND x.id<p.id))
 ORDER BY x.effective_date DESC,x.id DESC LIMIT 1))
ORDER BY p.agency_id,p.subject_type,p.subject_id,p.effective_date,p.id;

-- B) Flag approval or ownership inconsistencies, without assuming amounts.
SELECT p.id,p.agency_id,p.change_id,p.subject_type,p.subject_id,
 p.effective_date,p.old_monthly_irr,p.new_monthly_irr,p.applied_at,
 ch.driver_id,ch.update_student_debt,ch.update_driver_pay
FROM vehicle_class_rate_periods p
LEFT JOIN vehicle_class_changes ch ON ch.id=p.change_id AND ch.agency_id=p.agency_id
WHERE ch.id IS NULL
 OR (p.subject_type='student_debt' AND ch.update_student_debt<>1)
 OR (p.subject_type='driver_pay' AND ch.update_driver_pay<>1)
 OR (p.subject_type NOT IN ('student_debt','driver_pay'))
 OR (p.applied_at IS NOT NULL AND p.effective_date>DATE(UTC_TIMESTAMP()+INTERVAL 210 MINUTE))
ORDER BY p.id;

-- C) Duplicate candidate adjustments grouped by tenant/change/student/service/year/date.
-- Review legitimate separate invoices before treating duplicates as defects.
SELECT agency_id,change_id,student_id,service_id,academic_year,effective_date,
 COUNT(*) AS row_count, SUM(amount_irr) AS signed_adjustment_irr
FROM vehicle_class_debt_adjustments
GROUP BY agency_id,change_id,student_id,service_id,academic_year,effective_date
HAVING COUNT(*)>1;

-- D) Compare current student rate to most recent due, applied financial rate;
-- an unapplied due period is reported separately, not silently treated as applied.
SELECT p.agency_id,p.subject_id AS student_id,p.subject_type,
 p.id AS rate_period_id,p.effective_date,p.applied_at,
 p.new_monthly_irr AS approved_rate_irr,
 CASE WHEN p.subject_type='student_debt' THEN s.monthly_fee
      ELSE s.driver_base_monthly END AS stored_rate_irr,
 CASE WHEN p.subject_type='student_debt' THEN s.monthly_fee-p.new_monthly_irr
      ELSE s.driver_base_monthly-p.new_monthly_irr END AS rate_difference_irr
FROM vehicle_class_rate_periods p
JOIN students s ON s.id=p.subject_id AND s.agency_id=p.agency_id
WHERE p.effective_date<=DATE(UTC_TIMESTAMP()+INTERVAL 210 MINUTE)
 AND p.id=(SELECT newer.id FROM vehicle_class_rate_periods newer
   WHERE newer.agency_id=p.agency_id AND newer.subject_type=p.subject_type
     AND newer.subject_id=p.subject_id
     AND newer.effective_date<=DATE(UTC_TIMESTAMP()+INTERVAL 210 MINUTE)
   ORDER BY newer.effective_date DESC,newer.id DESC LIMIT 1)
 AND (p.applied_at IS NULL OR
  (p.subject_type='student_debt' AND NOT (s.monthly_fee<=>p.new_monthly_irr))
  OR (p.subject_type='driver_pay' AND NOT (s.driver_base_monthly<=>p.new_monthly_irr)))
ORDER BY p.agency_id,p.subject_type,p.subject_id;

-- E) Reconcile immutable invoice charges + approved due adjustments - real cash
-- in a single company/student grouping. A negative net is a credit.
-- Ledger receipts are reported separately, since these may include refunds.
SELECT s.agency_id,s.id AS student_id,
 COALESCE(i.original_invoices_irr,0) AS original_invoices_irr,
 COALESCE(a.due_adjustments_irr,0) AS due_adjustments_irr,
 COALESCE(i.original_invoices_irr,0)+COALESCE(a.due_adjustments_irr,0) AS effective_charges_irr,
 COALESCE(pay.cash_receipts_irr,0) AS linked_invoice_payments_irr,
 COALESCE(le.ledger_net_receipts_irr,0) AS ledger_net_receipts_irr,
 COALESCE(i.original_invoices_irr,0)+COALESCE(a.due_adjustments_irr,0)
 -COALESCE(pay.cash_receipts_irr,0)-COALESCE(le.ledger_net_receipts_irr,0) AS net_due_irr
FROM students s
LEFT JOIN (
 SELECT agency_id,party_id AS student_id,SUM(amount) AS original_invoices_irr
 FROM invoices WHERE party_type='student' AND status<>'cancelled'
 GROUP BY agency_id,party_id
) i ON i.agency_id=s.agency_id AND i.student_id=s.id
LEFT JOIN (
 SELECT agency_id,student_id,SUM(amount_irr) AS due_adjustments_irr
 FROM vehicle_class_debt_adjustments
 WHERE effective_date<=DATE(UTC_TIMESTAMP()+INTERVAL 210 MINUTE)
 GROUP BY agency_id,student_id
) a ON a.agency_id=s.agency_id AND a.student_id=s.id
LEFT JOIN (
 SELECT inv.agency_id,inv.party_id AS student_id,SUM(p.amount) AS cash_receipts_irr
 FROM payments p JOIN invoices inv ON inv.id=p.invoice_id AND inv.agency_id=p.agency_id
 WHERE inv.party_type='student' GROUP BY inv.agency_id,inv.party_id
) pay ON pay.agency_id=s.agency_id AND pay.student_id=s.id
LEFT JOIN (
 SELECT agency_id,party_id AS student_id,
 SUM(CASE WHEN direction='receipt' THEN amount ELSE -amount END) AS ledger_net_receipts_irr
 FROM ledger_entries WHERE party_type='student' AND is_active=1 GROUP BY agency_id,party_id
) le ON le.agency_id=s.agency_id AND le.student_id=s.id
WHERE COALESCE(a.due_adjustments_irr,0)<>0
ORDER BY s.agency_id,s.id;

-- F) A change with consent to update an account but no corresponding ledger rows.
-- Inspect first: inactive services / no eligible students may be legitimate.
SELECT ch.agency_id,ch.id AS change_id,ch.driver_id,
 ch.update_student_debt,ch.update_driver_pay,
 (SELECT COUNT(*) FROM vehicle_class_rate_periods p WHERE p.agency_id=ch.agency_id
   AND p.change_id=ch.id AND p.subject_type='student_debt') AS debt_rate_rows,
 (SELECT COUNT(*) FROM vehicle_class_rate_periods p WHERE p.agency_id=ch.agency_id
   AND p.change_id=ch.id AND p.subject_type='driver_pay') AS driver_rate_rows,
 (SELECT COUNT(*) FROM vehicle_class_debt_adjustments a WHERE a.agency_id=ch.agency_id
   AND a.change_id=ch.id) AS debt_adjustment_rows,
 (SELECT COUNT(*) FROM vehicle_class_contract_appendices x WHERE x.agency_id=ch.agency_id
   AND x.change_id=ch.id) AS appendix_rows
FROM vehicle_class_changes ch
ORDER BY ch.agency_id,ch.id;

-- G) Missing driver basis; these rows must be reconciled from authoritative
-- tariff or signed agreement before a payroll estimate can be finalized.
SELECT sv.agency_id,sv.id AS service_id,sv.driver_id,
 st.id AS student_id,st.monthly_fee AS parent_monthly_irr,
 st.driver_base_monthly AS driver_monthly_irr
FROM service_students ss
JOIN services sv ON sv.id=ss.service_id
JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
WHERE sv.is_active=1 AND sv.status<>'cancelled' AND st.is_active=1
 AND (st.driver_base_monthly IS NULL OR st.driver_base_monthly<0)
ORDER BY sv.agency_id,sv.id,st.id;

-- H) Driver monthly basis compared with the cached service net.
-- The policy-specific deductions must be evaluated in application code:
-- do NOT interpret raw gross vs net differences as errors.
SELECT sv.agency_id,sv.id AS service_id,sv.driver_id,
 COUNT(st.id) AS students,
 SUM(CASE WHEN st.driver_base_monthly IS NULL THEN 1 ELSE 0 END) AS missing_driver_bases,
 COALESCE(SUM(st.monthly_fee),0) AS parent_gross_monthly_irr,
 COALESCE(SUM(st.driver_base_monthly),0) AS driver_gross_monthly_irr,
 sv.monthly_fee AS cached_driver_net_irr
FROM services sv
JOIN service_students ss ON ss.service_id=sv.id
JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id AND st.is_active=1
WHERE sv.is_active=1 AND sv.status<>'cancelled'
GROUP BY sv.agency_id,sv.id,sv.driver_id,sv.monthly_fee
ORDER BY sv.agency_id,sv.id;

-- I) Multiple contract/invoice mappings for one student-year.
-- A class adjustment MUST NOT be copied to multiple unrelated invoices.
SELECT agency_id,student_id,academic_year,
 COUNT(*) AS mapped_contract_rows,COUNT(DISTINCT contract_id) AS contracts,
 COUNT(DISTINCT invoice_id) AS invoices
FROM student_contract_invoices
GROUP BY agency_id,student_id,academic_year
HAVING COUNT(DISTINCT contract_id)>1 OR COUNT(DISTINCT invoice_id)>1;

-- J) Due rate periods pointing to a service where the student is no longer
-- actively assigned (must be reconciled; never replayed onto a new service).
SELECT p.agency_id,p.id AS period_id,p.change_id,
 p.subject_type,p.subject_id AS student_id,p.service_id,p.effective_date,p.applied_at
FROM vehicle_class_rate_periods p
WHERE p.effective_date<=DATE(UTC_TIMESTAMP()+INTERVAL 210 MINUTE)
 AND NOT EXISTS (
  SELECT 1 FROM service_students ss
  JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=p.agency_id
  JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id
  WHERE ss.student_id=p.subject_id AND ss.service_id=p.service_id
   AND st.is_active=1 AND sv.is_active=1 AND sv.status<>'cancelled')
ORDER BY p.agency_id,p.id;

-- K) Signed debt memos lacking an active issued student contract/invoice.
-- Cancellation after a memo requires a separately approved reversal; do not
-- delete the original memo, move payments, or silently hide the discrepancy.
SELECT d.agency_id,d.id AS adjustment_id,d.change_id,d.student_id,
 d.service_id,d.academic_year,d.effective_date,d.amount_irr
FROM vehicle_class_debt_adjustments d
WHERE NOT EXISTS (
 SELECT 1 FROM student_contract_invoices m
 JOIN contracts c ON c.id=m.contract_id AND c.agency_id=m.agency_id
 JOIN invoices i ON i.id=m.invoice_id AND i.agency_id=m.agency_id
 WHERE m.agency_id=d.agency_id AND m.student_id=d.student_id
  AND REPLACE(m.academic_year,'/','-')=REPLACE(d.academic_year,'/','-')
  AND c.status<>'cancelled' AND i.status<>'cancelled')
ORDER BY d.agency_id,d.student_id,d.id;
