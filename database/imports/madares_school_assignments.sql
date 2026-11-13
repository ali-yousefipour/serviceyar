-- Import verified school-to-company assignments from a separately restored Madares backup.
-- Requires: migrations 0028 and 0035; source schema `h301194_school` on the SAME MySQL server.
-- Do NOT place the original production SQL dump in Git. Restore it only into an isolated
-- temporary database with restricted access, then remove the temporary database.
-- This importer does NOT modify existing Arta Novin or Arta Novin Yazdan Khorasan assignments.
-- Preview source records before running:
-- SELECT c.title,COUNT(*) FROM h301194_school.schools s
-- JOIN h301194_school.companies c ON c.id=s.company_id
-- WHERE s.is_active=1 GROUP BY c.id,c.title ORDER BY c.title;

START TRANSACTION;
-- Normalize company names so Persian/Arabic yeh/kaf and zero-width joiners do not split matches.
DROP TEMPORARY TABLE IF EXISTS madares_import_companies;
CREATE TEMPORARY TABLE madares_import_companies AS
SELECT c.id AS legacy_company_id,c.title AS legacy_company_name,
 REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(c.title),' ',''),CHAR(9),''),'‌',''),'ي','ی'),'ك','ک') AS normalized_name
FROM h301194_school.companies c
WHERE c.is_active=1;
DELETE FROM madares_import_companies WHERE normalized_name IN
 ('آرتانوین','آرتانوینیزدانخراسان','ارتانوین','ارتانوینیزدانخراسان');

-- Only register agencies that were not already registered under the same normalized name.
INSERT INTO agencies(uuid,name,code,is_active)
SELECT UUID(),m.legacy_company_name,CONCAT('madares-',m.legacy_company_id),1
FROM madares_import_companies m
WHERE NOT EXISTS (
 SELECT 1 FROM agencies a WHERE
 REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(a.name),' ',''),CHAR(9),''),'‌',''),'ي','ی'),'ك','ک')=m.normalized_name
);
-- Preserve all existing tenant records: the source school code is the cross-system identity.
INSERT INTO school_catalog
 (education_code,name,region,gender,shift_name,education_level,address,phone,manager_name,latitude,longitude,photo_path)
SELECT s.code,s.name,d.title,s.gender,s.shift,s.level,s.address,s.phone,s.principal_name,s.lat,s.lng,s.photo_path
FROM h301194_school.schools s
LEFT JOIN h301194_school.districts d ON d.id=s.district_id
JOIN madares_import_companies c ON c.legacy_company_id=s.company_id
WHERE s.is_active=1 AND s.code IS NOT NULL AND TRIM(s.code)<>''
ON DUPLICATE KEY UPDATE
 name=VALUES(name),region=COALESCE(VALUES(region),region),
 gender=COALESCE(VALUES(gender),gender),shift_name=COALESCE(VALUES(shift_name),shift_name),
 education_level=COALESCE(VALUES(education_level),education_level),
 address=COALESCE(VALUES(address),address),phone=COALESCE(VALUES(phone),phone),
 manager_name=COALESCE(VALUES(manager_name),manager_name),
 latitude=COALESCE(VALUES(latitude),latitude),longitude=COALESCE(VALUES(longitude),longitude),
 photo_path=COALESCE(VALUES(photo_path),photo_path);

-- A school remains separately scoped to each company; no overwrites across agencies.
INSERT INTO schools
 (uuid,agency_id,name,code,school_type,manager_name,phone,address,latitude,longitude,region,photo_path,is_active)
SELECT UUID(),a.id,s.name,s.code,s.level,s.principal_name,s.phone,s.address,s.lat,s.lng,d.title,s.photo_path,1
FROM h301194_school.schools s
JOIN madares_import_companies c ON c.legacy_company_id=s.company_id
JOIN agencies a ON REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(a.name),' ',''),CHAR(9),''),'‌',''),'ي','ی'),'ك','ک')=c.normalized_name
LEFT JOIN h301194_school.districts d ON d.id=s.district_id
WHERE s.is_active=1 AND s.code IS NOT NULL AND TRIM(s.code)<>''
AND NOT EXISTS (SELECT 1 FROM schools x WHERE x.agency_id=a.id AND x.code=s.code);

-- Link only schools that actually belong to the matched company.
INSERT INTO agency_school_catalog(agency_id,catalog_school_id,linked_school_id)
SELECT a.id,cat.id,school.id
FROM h301194_school.schools s
JOIN madares_import_companies c ON c.legacy_company_id=s.company_id
JOIN agencies a ON REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(a.name),' ',''),CHAR(9),''),'‌',''),'ي','ی'),'ك','ک')=c.normalized_name
JOIN school_catalog cat ON cat.education_code=s.code
JOIN schools school ON school.agency_id=a.id AND school.code=s.code
WHERE s.is_active=1
ON DUPLICATE KEY UPDATE linked_school_id=COALESCE(linked_school_id,VALUES(linked_school_id));

-- Fill missing photos and GPS coordinates without overwriting any already-entered values.
UPDATE schools school
JOIN agency_school_catalog mapping ON mapping.linked_school_id=school.id
JOIN school_catalog cat ON cat.id=mapping.catalog_school_id
SET school.photo_path=COALESCE(school.photo_path,cat.photo_path),
 school.latitude=COALESCE(school.latitude,cat.latitude),
 school.longitude=COALESCE(school.longitude,cat.longitude);

DROP TEMPORARY TABLE madares_import_companies;
COMMIT;
-- Validate totals by agency before considering the import complete:
-- SELECT a.name,COUNT(*) AS linked_schools FROM agency_school_catalog asc_map
-- JOIN agencies a ON a.id=asc_map.agency_id GROUP BY a.id,a.name ORDER BY a.name;
