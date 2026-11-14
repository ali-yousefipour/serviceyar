-- Safely backfill a registered vehicle's tariff class only when every
-- currently assigned student for that vehicle has the same valid class.
-- Conflicting or incomplete classes remain NULL for manual review.
UPDATE vehicles v
JOIN (
 SELECT d.agency_id, d.vehicle_id,
        MIN(st.tariff_vehicle_class) AS agreed_class,
        COUNT(*) AS assigned_students,
        COUNT(DISTINCT st.tariff_vehicle_class) AS distinct_classes,
        SUM(CASE WHEN st.tariff_vehicle_class IS NULL OR st.tariff_vehicle_class NOT IN (1,2,3) THEN 1 ELSE 0 END) AS invalid_count
 FROM drivers d
 JOIN services sv ON sv.driver_id=d.id AND sv.agency_id=d.agency_id AND sv.is_active=1
 JOIN service_students ss ON ss.service_id=sv.id
 JOIN students st ON st.id=ss.student_id AND st.agency_id=d.agency_id AND st.is_active=1
 WHERE d.vehicle_id IS NOT NULL
 GROUP BY d.agency_id,d.vehicle_id
) classes ON classes.vehicle_id=v.id AND classes.agency_id=v.agency_id
SET v.tariff_vehicle_class=classes.agreed_class
WHERE v.tariff_vehicle_class IS NULL AND classes.assigned_students>0
 AND classes.invalid_count=0 AND classes.distinct_classes=1;
