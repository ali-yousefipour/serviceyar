-- Normalize existing service titles to school name - driver full name.
-- Preserve records with missing/cross-agency links or blank names.
-- Keep the complete driver name where possible within services.title (200 chars).
UPDATE services AS sv
JOIN schools AS sc ON sc.id = sv.school_id AND sc.agency_id = sv.agency_id
JOIN drivers AS d ON d.id = sv.driver_id AND d.agency_id = sv.agency_id
SET sv.title = CONCAT(
    LEFT(TRIM(sc.name), GREATEST(1, 197 - CHAR_LENGTH(LEFT(TRIM(CONCAT_WS(' ', TRIM(d.first_name), TRIM(d.last_name))), 196)))),
    ' - ',
    LEFT(TRIM(CONCAT_WS(' ', TRIM(d.first_name), TRIM(d.last_name))), 196)
)
WHERE NULLIF(TRIM(sc.name), '') IS NOT NULL
  AND NULLIF(TRIM(CONCAT_WS(' ', TRIM(d.first_name), TRIM(d.last_name))), '') IS NOT NULL
  AND BINARY sv.title <> BINARY CONCAT(
    LEFT(TRIM(sc.name), GREATEST(1, 197 - CHAR_LENGTH(LEFT(TRIM(CONCAT_WS(' ', TRIM(d.first_name), TRIM(d.last_name))), 196)))),
    ' - ',
    LEFT(TRIM(CONCAT_WS(' ', TRIM(d.first_name), TRIM(d.last_name))), 196)
  );
