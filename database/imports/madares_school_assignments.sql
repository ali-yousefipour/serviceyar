-- Reviewed against actual SQL dumps dated 2026-11-13:
-- Source: h301194_school.sql (schools, companies).
-- Target: challi_ali (1).sql (schools, agencies, school_catalog, agency_school_catalog).
--
-- Validation: all 1,125 existing assigned-school links in the target already match
-- source school/company pairs. Do NOT insert new agencies or schools.
-- The original source table company_school_current_assignments is empty:
-- use schools.company_id as source ownership.
--
-- Run migration 0035_school_photo_paths.sql before this script.
-- Restore the source dump separately as schema h301194_school on the same MySQL server.
-- Execute only against a backed-up staging database first.
-- The source contains personal data/secrets: never commit the full SQL dump.
--
-- PREVIEW matching and photo availability:
-- SELECT COUNT(*) AS matches, SUM(m.photo_path IS NOT NULL AND m.photo_path <> '') AS photos
-- FROM schools s JOIN agencies a ON a.id=s.agency_id
-- JOIN h301194_school.companies c ON
--   REPLACE(REPLACE(REPLACE(REPLACE(TRIM(c.title),' ',''),'‌',''),'ي','ی'),'ك','ک') =
--   REPLACE(REPLACE(REPLACE(REPLACE(TRIM(a.name),' ',''),'‌',''),'ي','ی'),'ك','ک')
-- JOIN h301194_school.schools m ON m.company_id=c.id AND m.code=s.code
-- WHERE s.agency_id NOT IN (1,6);
--
-- IDs 1 and 6 are the Arta agencies in the provided target dump. The name
-- filters below also protect them if IDs change.

START TRANSACTION;

-- Preserve company allocation and existing school data. Fill missing media only.
UPDATE schools AS target
JOIN agencies AS agency ON agency.id=target.agency_id
JOIN h301194_school.companies AS legacy_company
 ON REPLACE(REPLACE(REPLACE(REPLACE(TRIM(legacy_company.title),' ',''),'‌',''),'ي','ی'),'ك','ک') =
    REPLACE(REPLACE(REPLACE(REPLACE(TRIM(agency.name),' ',''),'‌',''),'ي','ی'),'ك','ک')
JOIN h301194_school.schools AS legacy_school
 ON legacy_school.company_id=legacy_company.id
 AND legacy_school.code=target.code
SET target.photo_path=legacy_school.photo_path,
 target.latitude=COALESCE(target.latitude,legacy_school.lat),
 target.longitude=COALESCE(target.longitude,legacy_school.lng)
WHERE legacy_school.is_active=1
 AND legacy_school.photo_path IS NOT NULL
 AND legacy_school.photo_path<>''
 AND (target.photo_path IS NULL OR target.photo_path='')
 AND REPLACE(REPLACE(REPLACE(REPLACE(TRIM(agency.name),' ',''),'‌',''),'ي','ی'),'ك','ک')
 NOT IN ('آرتانوین','ارتانوین','آرتانوینیزدانخراسان','ارتانوینیزدانخراسان');

-- Synchronize catalog photographs using the established ownership links, without
-- changing ownership, catalog codes, names, tariffs or existing images.
UPDATE school_catalog AS catalog
JOIN agency_school_catalog AS link ON link.catalog_school_id=catalog.id
JOIN schools AS school ON school.id=link.linked_school_id
JOIN agencies AS agency ON agency.id=link.agency_id
SET catalog.photo_path=school.photo_path
WHERE school.photo_path IS NOT NULL AND school.photo_path<>''
 AND (catalog.photo_path IS NULL OR catalog.photo_path='')
 AND REPLACE(REPLACE(REPLACE(REPLACE(TRIM(agency.name),' ',''),'‌',''),'ي','ی'),'ك','ک')
 NOT IN ('آرتانوین','ارتانوین','آرتانوینیزدانخراسان','ارتانوینیزدانخراسان');

COMMIT;
-- AFTER: SELECT COUNT(*) FROM schools WHERE photo_path IS NOT NULL;
-- Expected: <= 582 populated photos for these pre-linked non-Arta schools
-- when applied to the exact provided snapshots (not a guaranteed live total).
