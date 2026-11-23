const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const ui=read('frontend/assets/operations.js');
const api=read('backend/public/index.php');
const domain=read('backend/src/Domain/DomainService.php');
const flow=read('backend/src/Domain/VehicleClassChangeWorkflow.php');
const schema=read('database/migrations/0168_vehicle_class_change_history.sql');
const balance=read('backend/src/Domain/StudentBalance.php');
const payroll=read('backend/src/Domain/MonthlyStatements.php');
const contracts=read('backend/src/Domain/ContractDocuments.php');

for(const marker of ['vehicleClassChangeWizard','updateContract','contractEffectiveDate',
 'updateStudentDebt','studentDebtEffectiveDate','updateDriverPay','driverPayEffectiveDate',
 'type="date"','vehicleClassHistoryDialog'])
 assert.ok(ui.includes(marker),'Missing wizard/history field: '+marker);
assert.ok(api.includes('/api/v1/vehicles/')&&api.includes('/class-change$#'),'No vehicle class workflow route');
assert.ok(api.includes('VehicleClassChangeWorkflow::apply'),'Class changes are not routed');
assert.ok((domain.match(/VEHICLE_CLASS_WORKFLOW_REQUIRED/g)||[]).length>=2,'Both vehicle edits and driver vehicle swaps must block unconsented class changes');
for(const table of ['vehicle_class_changes','vehicle_class_rate_periods',
 'vehicle_class_contract_appendices','vehicle_class_debt_adjustments'])
 assert.ok(schema.includes('CREATE TABLE IF NOT EXISTS '+table),'Missing migration '+table);
for(const marker of ['Authorization::requireAgencyAccess','Authorization::requirePermission',
 'OperationsService::transaction','VehicleClassSchema::ensure','FOR UPDATE',
 'DRIVER_NOT_AVAILABLE','VEHICLE_ASSIGNED_TO_OTHER_DRIVER',
 'VEHICLE_CAPACITY_TOO_SMALL','student_contract_invoices','updateStudentDebt',
 'updateDriverPay','vehicle_class_debt_adjustments','vehicle_class_contract_appendices'])
 assert.ok(flow.includes(marker),'Missing transition invariant: '+marker);
assert.ok(!flow.includes('UPDATE invoices SET'),'Issued invoices must never be overwritten');
assert.ok(!flow.includes('UPDATE contracts SET'),'Issued contracts must never be overwritten');
assert.ok(balance.includes('vehicle_class_debt_adjustments'),'Debt adjustments not in balances');
assert.ok(payroll.includes('VehicleClassEffectiveDates::blendedRate'),'Payroll missing date split');
assert.ok(payroll.includes('parentRateHistory'),'Parent-side annual reconciliation must not borrow driver consent history');
assert.ok(payroll.includes('$from,$to,$parentPeriods'),'Reference parent class must follow debt-approved history');
assert.ok(payroll.includes('old_class,p.new_class'),'Historical statement weighting needs original class from ledger');

const rateApplication=read('backend/src/Domain/VehicleClassRateApplication.php');
assert.ok(!rateApplication.includes('COALESCE(st.driver_base_monthly,st.monthly_fee)'),'Driver payroll may not silently fall back to parent fees');
assert.ok(rateApplication.includes('DRIVER_BASE_MONTHLY_REQUIRED'),'Deferred driver rate must fail closed on missing payroll basis');
assert.ok(!payroll.includes("row['driver_base_monthly']??$row['monthly_fee']"),'Statement preview must not use parent tuition as driver rate');
assert.ok(payroll.includes('invalidDriverRate'),'Statement preview must flag missing driver bases');
assert.ok(flow.includes('DRIVER_BASE_MONTHLY_REQUIRED'),'Class change approval must validate driver base');
assert.ok(flow.includes('VEHICLE_CLASS_RATE_PERIOD_OVERLAP'),'Backdated changes must not silently overwrite later approved periods');
assert.ok(flow.includes('AMBIGUOUS_STUDENT_CONTRACT'),'More than one contract for a student-year must not be amended arbitrarily');
assert.ok(domain.includes('vehicle_class_debt_adjustments'),'Student contract list must include signed amendments');
assert.ok(domain.includes('studentContractAmountSql'),'Student list contract amount must use shared financial reporting');
assert.ok(contracts.includes('VehicleClassEffectiveDates::annualEntitlement'),'Driver contract estimates must honor historical rate periods');
assert.ok(operations.includes('ServiceFinancialBasis::grossOfService'),'Service recalculation must use shared driver gross');

const operations=read('backend/src/Domain/OperationsService.php');
const finance=read('backend/src/Domain/ServiceFinancialBasis.php');
const accounts=read('backend/src/Domain/StudentServiceBilling.php');
assert.ok(!finance.includes('SUM(st.monthly_fee)'),'Driver service gross must use driver base only');
assert.ok(finance.includes('SUM(st.driver_base_monthly)'),'Driver service gross must derive from explicit remuneration');
assert.ok(!accounts.includes('COALESCE(st.driver_base_monthly,st.monthly_fee)'),'Driver account cannot substitute parent tuition');
assert.ok(!operations.includes("(int)round((float)$member['monthly_fee'])"),'Driver payroll cannot use student tuition as its non-historical basis');

assert.ok(contracts.includes('vehicleClassAppendixPrint'),'Missing printable contract appendix');
console.log('Vehicle class workflow static contract: OK');
