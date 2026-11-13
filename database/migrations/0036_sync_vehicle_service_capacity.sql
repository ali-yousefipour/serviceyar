-- Restore capacity consistency for existing services whose linked vehicle was expanded
-- before automatic synchronization existed. Never shrink a service or alter memberships.
UPDATE services AS sv
JOIN drivers AS d
  ON d.id=sv.driver_id AND d.agency_id=sv.agency_id AND d.is_active=1
JOIN vehicles AS v
  ON v.id=d.vehicle_id AND v.agency_id=sv.agency_id AND v.is_active=1
SET sv.capacity=v.capacity
WHERE sv.is_active=1
  AND v.capacity>sv.capacity;
