# ServiceYar database snapshot reference — 2026-11-13

Source: user-provided `challi_ali (1).sql`, phpMyAdmin 5.2.3, MySQL 8.0.46, generated 2026-11-13 17:33.

**Do not commit the raw production dump.** It contains personal information, password hashes, sessions, bank/finance data and other sensitive operational records. Retain that original only in a restricted encrypted backup. This document records non-sensitive structural facts for future development.

## Inventory

69 tables:

- `accounting_accounts`
- `accounting_documents`
- `accounting_document_lines`
- `activity_logs`
- `agencies`
- `agency_school_catalog`
- `agency_school_tariffs`
- `agency_settings`
- `agency_users`
- `ai_conversations`
- `ai_messages`
- `api_idempotency_keys`
- `arta_before_20261104_agencies`
- `arta_before_20261104_agency_settings`
- `arta_before_20261104_drivers`
- `arta_before_20261104_schools`
- `arta_before_20261104_students`
- `arta_before_20261104_vehicles`
- `arta_before_vehicle_fix_drivers`
- `arta_before_vehicle_fix_vehicles`
- `arta_import_sources`
- `audit_logs`
- `auth_login_attempts`
- `cheque_events`
- `cheque_receipts`
- `contracts`
- `drivers`
- `driver_changes`
- `driver_contracts`
- `driver_documents`
- `driver_payments`
- `driver_ratings`
- `driver_work`
- `files`
- `guardians`
- `holidays`
- `invoices`
- `ledger_entries`
- `mehr1405_financial_import`
- `messages`
- `migrations`
- `packs`
- `parent_reports`
- `payments`
- `payment_intents`
- `payroll_documents`
- `payroll_document_lines`
- `permissions`
- `platform_settings`
- `roles`
- `role_permissions`
- `schools`
- `school_assignment_import_mehr1405`
- `school_catalog`
- `school_charges`
- `school_monthly_statements`
- `school_service_import_fees`
- `services`
- `service_charges`
- `service_locations`
- `service_schools`
- `service_students`
- `students`
- `student_contracts`
- `users`
- `user_roles`
- `user_sessions`
- `vehicles`
- `wallets`

## Schools and agencies

| Table | Records in snapshot | Purpose |
|---|---:|---|
| `agencies` | 66 | Registered companies; 46 active |
| `schools` | 1,165 | Tenant schools; 12 without education code |
| `school_catalog` | 1,162 | Central education-code-based catalog |
| `agency_school_catalog` | 1,125 | Actual links from central catalog to company schools |
| `agency_school_tariffs` | 36 | Rate rows in snapshot |

Madares source snapshot: `h301194_school.sql`, 37 tables, 65 companies and 3,472 school records, of which 1,162 are active. Source `company_school_current_assignments` has no INSERT rows; source `schools.company_id` is the relevant school-company link.

All 65 source company names match existing ServiceYar agencies after Persian spelling and whitespace normalization. All active source school-company pairs already exist in ServiceYar's tenant `schools` table. **Do not blindly re-import schools or create duplicate agencies.**

In the two compared snapshots, 1,125 non-Arta linked source schools are matched to ServiceYar agency schools and 582 have source photo paths. The original ServiceYar `schools` and `school_catalog` tables have no `photo_path` until migration `0035_school_photo_paths.sql` is applied.

## Data integrity and deployment

- Preserve existing Arta Novin (agency ID 1 in this snapshot) and Arta Novin Yazdan Khorasan (ID 6) school allocations.
- Use the verified `database/imports/madares_school_assignments.sql` to populate missing school photo paths without changing assignments, names, codes or tariffs.
- Before running the import, create an encrypted database backup, apply migration 0035 and restore the original Madares source into a **restricted temporary database** only.
- The public school listing requires schools to have coordinates, an active agency and an active school; it intentionally does not publish student/driver details.
- Imported image paths follow `uploads/schools/YYYY/MM/name.jpg`; the web URL is `/schools/YYYY/MM/name.jpg`. Hosting must serve that path.
- The snapshot has 610 tenant schools with latitude/longitude and 597 catalog schools with coordinates. Missing locations cannot be inferred safely.
- Verify the actual production migration state and public API responses before marking deployment complete.
