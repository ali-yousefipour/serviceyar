'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../frontend/assets/operations.js'),'utf8');
const start=source.indexOf(' const normalizeAgreed=',source.indexOf('async function newServicePage('));
const end=source.indexOf(' const canSubmit=',start);
assert(start>=0&&end>start,'New-service amount validators are missing');
const ctx={form:{elements:{agreedAmountDisplay:{value:'',setCustomValidity(message){this.error=message;}},agreedAmount:{value:''}}},persianDigits:value=>String(value).replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)])};
vm.runInNewContext(source.slice(start,end)+'\nthis.syncAgreed=syncAgreed;this.validAgreed=validAgreed;',ctx);
const agreed=ctx.form.elements.agreedAmountDisplay;
agreed.value='۱٬۲۵۰٬۰۰۰';
assert.equal(ctx.syncAgreed(),true);
assert.equal(ctx.form.elements.agreedAmount.value,'1250000');
assert.equal(agreed.value,'۱٬۲۵۰٬۰۰۰');
for(const bad of ['۱۲٫۵','1,5','-10','۱x۲','۱٬۲۳۴٬۵۶']){
 agreed.value=bad;
 assert.equal(ctx.syncAgreed(),false,'Invalid rial string accepted: '+bad);
 assert.equal(ctx.form.elements.agreedAmount.value,'');
 assert.notEqual(agreed.error,'');
}
agreed.value='۰';assert.equal(ctx.syncAgreed(),true);assert.equal(ctx.form.elements.agreedAmount.value,'0');
assert(source.includes('id="serviceCapacityWarning"'),'Missing capacity feedback');
assert(source.includes("visible=filtered.slice(0,100)"),'Student picker needs render limit');
assert(source.includes('if(pending.size>Number(form.elements.capacity.value))'),'Student picker needs capacity validation');
console.log('New-service wizard financial and capacity checks passed');
