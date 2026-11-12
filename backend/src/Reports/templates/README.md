# Reference XLSX for school-service monthly statements

The exporter loads **school-statement-reference.xlsx** from this directory when present.
The file must be a *data-scrubbed, macro-free* copy of the user's 14-sheet
reference XLSM. Do not commit the original XLSM, student data, bank details,
pivot-cache records, hidden personal data, or VBA macros to this repository.

The GitHub code connector cannot upload a sandbox binary automatically.
The sanitized template is available separately as a conversation artifact
and must be added to this path using a binary-capable GitHub upload.

The exact-template exporter is **disabled by default**, even if the XLSX is installed.
After validation by finance/operations, set `SCHOOL_STATEMENT_REFERENCE_TEMPLATE_ENABLED=1`
in the hosting environment. This requires an explicit release decision; the old
synthetic exporter continues to work while the flag is absent.

Until then, MonthlyStatementWorkbook retains the legacy synthetic 14-sheet
output. A successful CI/deploy DOES NOT indicate the exact visual template
is installed. The optional template exporter is ReferenceMonthlyStatementWorkbook.

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
