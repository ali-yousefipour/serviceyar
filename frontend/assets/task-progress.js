'use strict';
/* Shared, truthful progress UI: never derive percentages from elapsed time. */
(() => {
 let sequence=0;
 const pending=new Map();
 let root, title, detail, meter, percent;
 const fa=value=>Number(value).toLocaleString('fa-IR',{maximumFractionDigits:0});
 function ensure(){
  if(root)return;
  root=document.createElement('section');
  root.className='sy-task-progress';
  root.setAttribute('role','status');
  root.setAttribute('aria-live','polite');
  root.setAttribute('aria-label','وضعیت پردازش سامانه');
  root.hidden=true;
  root.innerHTML='<div class="sy-task-progress-heading"><span class="sy-task-progress-spinner" aria-hidden="true"></span><strong data-task-title></strong><b data-task-percent hidden></b></div><p data-task-detail></p><progress data-task-bar max="100" aria-label="درصد پیشرفت دریافت فایل" hidden></progress><div data-task-indeterminate class="sy-task-indeterminate" aria-hidden="true"></div>';
  document.body.append(root);
  title=root.querySelector('[data-task-title]');
  detail=root.querySelector('[data-task-detail]');
  meter=root.querySelector('[data-task-bar]');
  percent=root.querySelector('[data-task-percent]');
 }
 function paint(){
  ensure();
  const entries=[...pending.values()].filter(t=>t.visible);
  if(!entries.length){root.hidden=true;return;}
  const task=entries[entries.length-1];
  root.hidden=false;
  title.textContent=task.label;
  detail.textContent=task.detail||'در حال پردازش درخواست…';
  const measured=Number.isFinite(task.progress);
  meter.hidden=!measured;
  root.querySelector('[data-task-indeterminate]').hidden=measured;
  percent.hidden=!measured;
  if(measured){meter.value=Math.max(0,Math.min(100,task.progress));percent.textContent=fa(Math.round(meter.value))+'٪';}
 }
 function begin(label,options={}){
  const id=++sequence;
  const task={label:String(label||'در حال دریافت اطلاعات…'),detail:'',progress:null,visible:false,timer:null};
  pending.set(id,task);
  task.timer=setTimeout(()=>{if(pending.has(id)){task.visible=true;paint();}},options.immediate?0:250);
  return {
   update({label:nextLabel,detail:nextDetail,percent:nextPercent}={}){
    if(!pending.has(id))return;
    if(nextLabel!==undefined)task.label=String(nextLabel);
    if(nextDetail!==undefined)task.detail=String(nextDetail);
    if(nextPercent===null)task.progress=null;
    else if(typeof nextPercent==='number'&&Number.isFinite(nextPercent))task.progress=Math.max(0,Math.min(100,nextPercent));
    paint();
   },
   finish(){clearTimeout(task.timer);pending.delete(id);paint();}
  };
 }
 async function track(promise,label){
  const task=begin(label);
  try{return await promise;}finally{task.finish();}
 }
 // Content-Length is the only supported download percentage source. When it is
 // unavailable or transformed by compression, retain an indeterminate bar.
 async function download(url,filename,options={}){
  const task=begin('دریافت و آماده‌سازی فایل',{immediate:true});
  try{
   task.update({detail:'در انتظار پاسخ سرور و تولید فایل…'});
   const response=await fetch(url,{credentials:'same-origin',...options});
   const type=response.headers.get('content-type')||'';
   if(!response.ok||type.includes('application/json')){
    const data=await response.json().catch(()=>({}));
    throw new Error(data?.error?.message||'ساخت یا دریافت فایل ناموفق بود.');
   }
   const length=Number(response.headers.get('content-length'));
   const measurable=Number.isSafeInteger(length)&&length>0&&!response.headers.get('content-encoding');
   let blob;
   if(response.body&&typeof response.body.getReader==='function'){
    const reader=response.body.getReader(),chunks=[];
    let received=0;
    while(true){
     const {done,value}=await reader.read();
     if(done)break;
     chunks.push(value);received+=value.byteLength;
     task.update({detail:'دریافت '+fa(received)+' بایت'+(measurable?' از '+fa(length)+' بایت':'')+'…',percent:measurable?Math.min(99,received/length*100):null});
    }
    blob=new Blob(chunks,{type:type||'application/octet-stream'});
   }else{
    task.update({detail:'در حال دریافت فایل…',percent:null});
    blob=await response.blob();
   }
   if(!blob.size)throw new Error('فایل دریافت‌شده خالی است.');
   task.update({detail:'فایل آماده دریافت است.',percent:100});
   const objectUrl=URL.createObjectURL(blob);
   try{
    const anchor=document.createElement('a');
    anchor.href=objectUrl;anchor.download=filename||'report.xlsx';
    document.body.append(anchor);anchor.click();anchor.remove();
   }finally{setTimeout(()=>URL.revokeObjectURL(objectUrl),60000);}
   return blob.size;
  }finally{task.finish();}
 }
 window.ServiceYarLoading={begin,track,download};
})();
