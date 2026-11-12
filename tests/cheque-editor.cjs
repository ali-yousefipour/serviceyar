'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../frontend/assets/operations.js'),'utf8');
const base=source.indexOf('async function chequeEditor(');
const start=source.indexOf(' const cleanDigits=value=>',base);
const end=source.indexOf(' const validate=()=>',start);
assert(start>base&&end>start,'Cheque monetary validation source not found');
const money={value:''};
const context={money};
vm.runInNewContext(source.slice(start,end)+'\nthis.amountValue=amountValue;',context);
for(const [raw,expected] of [
 ['1234','1234'],['1234.50','1234.50'],
 ['۱٬۲۳۴٫۵۰','1234.50'],['۱٬۲۳۴','1234'],
 ['0.01','0.01'],['۱,۲۳۴.۵','1234.5']]){
 money.value=raw;assert.equal(context.amountValue(),expected,raw);
}
for(const value of ['0','۰','0.00','۱,۵','1.123','-100','۱٫','12a3','1.5.2']){
 money.value=value;assert.equal(context.amountValue(),null,'Invalid monetary input accepted: '+value);
}
assert(source.includes("date.min=row.received_date"),'Operation date must not precede cheque receipt');
assert(source.includes('r.receipt_document_id?'), 'Posted cheque edit button must be hidden');
assert(source.includes('data-cheque-journal'), 'Audit history should link to journal');
console.log('Cheque monetary precision and operational constraints passed');
