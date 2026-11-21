-- Repair students reactivated before the service-status restoration fix.
-- Only active students still marked cancelled and without an active assignment
-- are returned to the unassigned/waiting service pool. Financial data is untouched.
UPDATE students st
SET st.service_status = 'waiting'
WHERE st.is_active = 1
  AND st.service_status = 'cancelled'
  AND NOT EXISTS (
    SELECT 1 FROM service_students ss
    JOIN services sv ON sv.id = ss.service_id
    WHERE ss.student_id = st.id
      AND sv.agency_id = st.agency_id
      AND sv.is_active = 1
      AND sv.status <> 'cancelled'
  );
