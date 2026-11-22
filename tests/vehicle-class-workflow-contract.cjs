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
assert.ok(contracts.includes('vehicleClassAppendixPrint'),'Missing printable contract appendix');
console.log('Vehicle class workflow static contract: OK');
