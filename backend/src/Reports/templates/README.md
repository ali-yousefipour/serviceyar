# Reference XLSX for school-service monthly statements

The exporter loads **school-statement-reference.xlsx** from this directory when present.
The file must be a *data-scrubbed, macro-free* copy of the user's 14-sheet
reference XLSM. Do not commit the original XLSM, student data, bank details,
pivot-cache records, hidden personal data, or VBA macros to this repository.

The sanitized reference template is committed to this path as a 56,060-byte,
14-sheet macro-free XLSX (Git blob SHA `4e3a5f2c6bc2581b818b4ecda0cb13d2b8c619ff`).
The CI checks sheet order, external/unsafe attachments, relevant table parts,
and produces a two-student synthetic reference export to test formulas.

Once deployed, the workbook export uses the validated reference format.
Emergency rollback: set `SCHOOL_STATEMENT_REFERENCE_TEMPLATE_ENABLED=0`
in the hosting environment. The synthetic 14-sheet fallback is retained.

**Operational caveat:** Successful structural tests do not prove that financial
account codes, insurance, eligibility, service days or historical assignments
are correct on real production records. The XLSX is an exported statement, not
an automatically posted or approved accounting journal.

## Financial audit requirements before production sign-off

- Validate 14 sheet names/order, formulas, print area, column/row dimensions,
  source macro removal and no personal data in the blank template.
- Verify every preview is based on the 1405/1406 class/distance/region tariff,
  not on monthly parent fee as if it were a driver base fee.
- Validate per-student service days (currently 30 by default), driver eligibility,
  qualification certificates, insurer enrollment and previous-month premium.
- Configure company contract identifiers and all accounting account mappings
  before using the journal voucher as a posting source.
- Confirm monthly input matches a historical assignment effective-date snapshot.
  Current active assignments alone are not reliable for past months.
- Cross-check student, driver, parent due and final driver statement totals in
  Excel itself against a test cohort and the source workbook.

Do not store or publish the private source workbook in a public repository.
