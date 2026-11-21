'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = filename => fs.readFileSync(path.join(__dirname, '..', filename), 'utf8');
const operations = read('frontend/assets/operations.js');
const solar = read('frontend/assets/solar-date.js');
const backend = read('backend/src/Domain/DomainService.php');
const contracts = read('backend/src/Domain/ContractDocuments.php');
const api = read('backend/public/index.php');
const migration = read('database/migrations/0164_restore_active_students_service_status.sql');

assert.match(solar, /closest\('dialog\[open\]'\)/, 'Calendar must detect a native modal dialog');
assert.match(solar, /showPopover\(\)/, 'Native dialog calendar must reach top layer');
assert.match(operations, /sy-contract-edit-dialog/, 'Contract editor must use the shared modal');
assert.match(operations, /name="startDate" required/, 'Contract start date missing');
assert.match(operations, /name="endDate" required/, 'Contract end date missing');
assert.match(operations, /confirmInvoiceUpdate:datesChanged&&confirmed/, 'Contract date changes must require confirmed invoice update');
assert.match(contracts, /CONTRACT_INVOICE_CONFIRMATION_REQUIRED/, 'Backend contract confirmation guard missing');

assert.match(operations, /sy-invoice-filter-v2/, 'Invoice filters not rendered');
assert.match(operations, /name="dateFrom" type="date"/, 'Invoice date from must use shared Jalali calendar');
assert.match(operations, /name="dateTo" type="date"/, 'Invoice date to must use shared Jalali calendar');
assert.match(operations, /name="sortBy"/, 'Invoice sort dropdown missing');
assert.match(backend, /'invoices'=>\['id','title','issue_date','due_date','amount','status'\]/, 'Invoice sort allowlist mismatch');
assert.match(backend, /\$term=strtr\(\$term/, 'Persian invoice search digit normalization missing');

assert.match(backend, /DISTANCE_REPRICE_CONFIRMATION_REQUIRED/, 'Distance repricing must require user confirmation');
assert.match(backend, /repriceStudentAfterDistanceChange/, 'Approved distance edit must synchronize contracts/invoices');
assert.match(contracts, /contract_revisions/, 'Contract revision audit missing');
assert.match(contracts, /student_contract_invoices/, 'Contract invoice linkage must remain explicit');
assert.match(contracts, /\$old\['end_date'\]<\$currentStart/, 'Historical contracts must be skipped before mapping validation');
assert.match(backend, /STUDENT_HAS_DEPENDENCIES/, 'Hard delete must reject student financial dependencies');
assert.match(api, /DELETE_STUDENT_PERMANENTLY/, 'Hard delete endpoint missing explicit confirmation');
assert.match(migration, /service_status = 'waiting'/, 'Legacy restored student migration missing');
assert.match(backend, /'assigned':'waiting'/, 'Student restore must restore service selection status');

const dates = require('../frontend/assets/solar-date.js');
for (const [solarDate, iso] of [
 ['۱۴۰۵/۰۷/۰۱','2026-09-23'],
 ['۱۴۰۵/۰۸/۳۰','2026-11-21'],
 ['1405/01/01','2026-03-21']
]) {
 assert.equal(dates.toISO(solarDate), iso);
 assert.equal(dates.toSolar(iso), dates.digits(solarDate));
}
for (const invalid of ['۱۴۰۵/۱۲/۳۰', '۱۴۰۵/۱۳/۰۱', '۱۴۰۵/۰۷/۳۱']) {
 assert.equal(dates.toISO(invalid), '');
}
console.log('Recent invoice filters, contract calendar, student restoration and finance guard checks passed');
