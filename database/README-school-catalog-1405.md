# School catalog / 1405 import

1. Back up production database.
2. Run migration `0028_school_catalog.sql`.
3. Import `serviceyar_school_catalog_1405.sql` (1162 central school records).
4. Import `serviceyar_companies_school_links_1405.sql` (65 agencies; school-company selections except Arta Novin; materialize local school rows).
5. Import `serviceyar_company_accounts_1405.sql` to create a separate limited-permission account for each company.
6. Store `serviceyar_company_credentials_1405.csv` securely, distribute each password only to the corresponding authorized representative and require a password change at first login.
7. Verify counts and unmatched links before enabling access. Do not commit credentials or SQL password hashes to GitHub.

Existing tenant school/student/service IDs are preserved. Central school records are matched by education code, not name. Selecting a school from the UI creates/reuses the tenant-local school and stores the catalog link. The file's company-to-school mapping excludes Arta Novin to protect its existing assignments. Missing company names must not be guessed.

IMPORTANT: Files listed above are generated from the user's workbook and delivered as private downloadable artifacts; they are not deployed automatically. The production database is not changed by a GitHub commit.
