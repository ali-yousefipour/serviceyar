'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../frontend/assets/operations.js'),'utf8');
const start=source.indexOf(' const totals=()=>{',source.indexOf('async function journalEditor('));
const finish=source.indexOf(' doc.lines.forEach(add);',start);
assert(start>=0&&finish>start,'Journal validation implementation was not found');
const rows=[];
const summary={textContent:'',classList:{values:new Set(),toggle(name,value){if(value)this.values.add(name);else this.values.delete(name);}}};
const button={disabled:false};
const account=value=>({value:String(value)});
const field=value=>({value:String(value)});
const row=(a,d,c)=>({
 values:{accountId:account(a),debit:field(d),credit:field(c)},
 classList:{values:new Set(),toggle(name,value){if(value)this.values.add(name);else this.values.delete(name);}},
 querySelector(selector){return this.values[selector.match(/name=([^\]]+)/)[1]];}
});
const ctx={
 $:id=>id==='journalLines'?{querySelectorAll:()=>rows}:id==='journalTotals'?summary:id==='journalSave'?button:null,
 number:value=>String(value),
 editable:true
};
vm.runInNewContext(source.slice(start,finish)+'\nthis.totals=totals;',ctx);
rows.push(row(3,'150000','0'),row(5,'0','150000'));
assert.equal(ctx.totals(),true,'Balanced journal rejected');
assert.equal(button.disabled,false);
assert(summary.textContent.includes('سند تراز است.'));
rows[1].values.credit.value='149999';
assert.equal(ctx.totals(),false,'Unbalanced journal accepted');
assert.equal(button.disabled,true);
rows[1].values.credit.value='150000';
rows[0].values.debit.value='150000.999';
assert.equal(ctx.totals(),false,'Three fractional digits accepted');
rows[0].values.debit.value='150000';
rows[1].values.credit.value='0';
assert.equal(ctx.totals(),false,'Double zero line accepted');
rows[1].values.credit.value='150000';
rows[0].values.accountId.value='';
assert.equal(ctx.totals(),false,'Missing account accepted');
rows[0].values.accountId.value='3';
rows[0].values.credit.value='20';
assert.equal(ctx.totals(),false,'Simultaneous debit and credit accepted');
console.log('Journal balanced-entry checks passed');
