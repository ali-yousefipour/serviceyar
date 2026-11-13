# Local Excel workbook writer

Vendored **SimpleXLSXGen 1.5.17** from
https://github.com/shuchkin/simplexlsxgen (MIT license).
Source: `SimpleXLSXGen.php`; license: `SimpleXLSXGen.LICENSE.md`.

The server-side export in `../Reports/ExportService.php` uses this library
for ordinary tabular XLSX reports when PHP `mbstring` and `zlib` are enabled.
The existing ZipArchive exporter remains a compatibility fallback.

Exports preserve UTF-8 Persian text, RTL sheet orientation, filtered/frozen
headers, and numeric amounts. Account identifiers, dates, national codes,
IBANs and reference numbers are kept as text.

Monthly school statements (14-sheet template) and template-based payroll
reports use their established formats; this dependency does not replace or
change those templates.

Run `php tests/xlsx-writer.php` with PHP extensions mbstring, zlib and zip
to verify XLSX generation and text preservation.
