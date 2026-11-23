const assert=require('node:assert/strict');
const fs=require('node:fs');
const source=fs.readFileSync(require('node:path').join(__dirname,'../frontend/assets/operations.js'),'utf8');
const start=source.indexOf("  if(key==='invoices'){",source.indexOf('async function viewRecord('));
const end=source.indexOf("  if(key==='schools'){",start);
assert(start>0&&end>start);
const render=new Function('r','dialog','number','persianDigits','dateText','esc','simpleTable',"const key='invoices';"+source.slice(start,end));
function html(row){let result;render(row,(_,body)=>result=body,String,String,String,String,()=> '');return result;}
const base={id:300,title:'Invoice',amount:270353421,remaining_amount:270353421,paid_amount:0};
const revised=html({...base,original_amount:270353421,class_adjustment_amount:41880296,effective_amount:312233717,effective_remaining_amount:312233717});
assert(revised.includes('<dt>مبلغ اولیه فاکتور</dt><dd>270353421</dd>'));
assert(revised.includes('<dt>سند تعدیل کلاس</dt><dd>41880296</dd>'));
assert(revised.includes('<dt>مبلغ قابل پرداخت پس از اصلاح</dt><dd>312233717</dd>'));
assert(revised.includes('<dt>مانده اصلاح‌شده فاکتور</dt><dd>312233717</dd>'));
const zero=html({...base,class_adjustment_amount:-270353421,effective_amount:0,effective_remaining_amount:0});
assert(zero.includes('<dt>مبلغ قابل پرداخت پس از اصلاح</dt><dd>0</dd>'));
assert(zero.includes('<dt>مانده اصلاح‌شده فاکتور</dt><dd>0</dd>'));
assert(html(base).includes('<dt>مانده اصلاح‌شده فاکتور</dt><dd>270353421</dd>'));
console.log('Invoice detail backend adjustment amounts: OK');
