'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../frontend/assets/operations.js'),'utf8');
const start=source.indexOf(' const relationVersion=new WeakMap();');
const finish=source.indexOf(" form.querySelectorAll('[data-relation]').forEach",start);
assert(start>=0&&finish>start,'Reference lookup handler not found in formResource');
const requests=[],notifications=[];
class Option{constructor(text,value){this.text=text;this.value=String(value);}}
const context={
 WeakMap,Option,
 api:url=>new Promise((resolve,reject)=>requests.push({url,resolve,reject})),
 scoped:url=>url,
 person:row=>row.name,
 persianDigits:value=>String(value),
 labels:{schoolId:'مدرسه'},
 notify:(message,error)=>notifications.push({message,error})
};
vm.runInNewContext(source.slice(start,finish)+'\nthis.populate=populate;',context);
const select={
 dataset:{relation:'schools'},value:'17',isConnected:true,options:[],
 attrs:new Map(),
 setAttribute(name,value){this.attrs.set(name,value);},
 removeAttribute(name){this.attrs.delete(name);},
 replaceChildren(...options){this.options=options;this.value=options[0]?.value||'';}
};
(async()=>{
 const first=context.populate(select,'old');
 const second=context.populate(select,'new');
 assert.equal(requests.length,2);
 assert.match(requests[0].url,/search=old/);
 assert.match(requests[1].url,/search=new/);
 requests[1].resolve({items:[{id:17,name:'مدرسه جدید'}]});
 await second;
 assert.equal(select.value,'17','Selected school must remain selected');
 assert.equal(select.options[1].text,'مدرسه جدید');
 requests[0].resolve({items:[{id:3,name:'مدرسه قدیمی'}]});
 await first;
 assert.equal(select.options[1].text,'مدرسه جدید','Stale search must not overwrite new results');
 assert.equal(select.attrs.has('aria-busy'),false,'Loading flag should be cleared');
 assert.deepEqual(notifications,[]);
 console.log('Wizard relation lookup race regression passed');
})().catch(err=>{console.error(err);process.exitCode=1;});
