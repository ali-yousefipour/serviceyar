/* Administrator-managed vehicle models grouped by official tariff classes. */
async function vehicleClassAdminPanel(anchor){
 const section=document.createElement('section');section.className='panel sy-super-admin-card sy-vehicle-class-catalog';section.id='syVehicleClassCatalog';
 section.innerHTML='<header class="sy-settings-section-head"><div><h2>مدیریت کلاس‌های تعرفه خودرو</h2><p>خودروهای هر کلاس را مدیریت کنید. نام‌های مشابه مانند انواع پراید زیر یک خانواده قرار می‌گیرند؛ نام‌های مبهم بدون تأیید خودکار طبقه‌بندی نمی‌شوند.</p></div></header>'+
 '<div id="syVehicleClassCards" class="sy-vehicle-class-cards">در حال دریافت فهرست کلاس‌ها…</div>'+
 '<form id="syVehicleClassAdd"><div class="form-grid"><label>کلاس خودرو<select name="vehicleClass" required><option value="1">کلاس ۱</option><option value="2">کلاس ۲</option><option value="3">کلاس ۳</option></select></label>'+
 '<label>خانواده خودرو<input name="familyName" maxlength="100" placeholder="مثلاً پراید" required></label>'+
 '<label>نام یا عبارت جایگزین<input name="alias" maxlength="120" placeholder="مثلاً Pride یا پراید ۱۳۱" required></label></div><button type="submit" class="primary">افزودن نام به کلاس</button></form>'+
 '<div class="sy-vehicle-class-detect"><h3>تشخیص خودکار کلاس خودروهای نامشخص</h3><p>فقط کلاس خودروهای خالی تکمیل می‌شود. کلاس‌های قبلی و محاسبات مالی تغییر نمی‌کنند؛ تطبیق متعارض برای بررسی دستی باقی می‌ماند.</p>'+
 '<div class="member-actions"><button type="button" id="syModelDetectPreview">پیش‌نمایش تشخیص کلاس‌ها</button><button type="button" id="syModelDetectApply" disabled>اعمال کلاس‌های تأییدشده</button></div>'+
 '<div id="syModelDetectReport" aria-live="polite"></div></div>';
 anchor.after(section);
 const cards=section.querySelector('#syVehicleClassCards'),form=section.querySelector('#syVehicleClassAdd'),
 preview=section.querySelector('#syModelDetectPreview'),apply=section.querySelector('#syModelDetectApply'),report=section.querySelector('#syModelDetectReport');
 const familySummary=items=>{
  const groups=new Map();
  items.forEach(item=>{const key=String(item.family_name);if(!groups.has(key))groups.set(key,[]);groups.get(key).push(item);});
  return [...groups].map(([family,models])=>'<details><summary>'+esc(family)+' <span class="muted">('+number(models.length)+' نام)</span></summary><div class="sy-model-aliases">'+models.map(item=>
   '<div class="sy-model-alias"><span>'+esc(item.alias)+'</span><button type="button" data-remove-vehicle-alias="'+Number(item.id)+'" title="حذف نام از کلاس">حذف</button></div>').join('')+'</div></details>').join('');
 };
 const reload=async()=>{
  const data=await api('/api/v1/admin/vehicle-class-catalog');
  if(!section.isConnected)return;
  cards.innerHTML=[1,2,3].map(c=>'<article class="sy-vehicle-class-card"><h3>کلاس '+number(c)+'</h3>'+familySummary(data.models.filter(i=>Number(i.class_number)===c))+'</article>').join('');
  cards.querySelectorAll('[data-remove-vehicle-alias]').forEach(button=>button.onclick=async()=>{
   if(!confirm('این نام از فهرست تشخیص کلاس حذف شود؟ کلاس خودروهای قبلاً تعیین‌شده تغییر نمی‌کند.'))return;
   button.disabled=true;
   try{await api('/api/v1/admin/vehicle-class-catalog/'+button.dataset.removeVehicleAlias,{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});await reload();apply.disabled=true;}
   catch(e){button.disabled=false;notify(e.message,true);}
  });
 };
 form.onsubmit=async event=>{
  event.preventDefault();const button=form.querySelector('button[type=submit]');button.disabled=true;
  try{
   await api('/api/v1/admin/vehicle-class-catalog',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(Object.fromEntries(new FormData(form)))});
   form.elements.alias.value='';apply.disabled=true;await reload();
  }catch(e){notify(e.message,true);}finally{button.disabled=false;}
 };
 const showResults=data=>{
  const stats='<div class="sy-sepand-stats">'+[['خودروهای بدون کلاس',data.checked],['قابل تکمیل',data.recoverable],['نیازمند بررسی',data.needReview]]
   .map(([label,n])=>'<div><span>'+esc(label)+'</span><b>'+number(n||0)+'</b></div>').join('')+'</div>';
  const ready=data.classified?.length?'<details><summary>خودروهای قابل تکمیل (نمونه)</summary>'+simpleTable(['شرکت','پلاک','خودرو','خانواده','کلاس'],
   data.classified.map(v=>[number(v.agencyId),v.plate,v.trim||v.system||v.model,(v.familyNames||[]).join('، '),number(v.vehicleClass)]))+'</details>':'';
  const pending=data.unresolved?.length?'<details><summary>نامشخص یا متعارض (تا ۱۲۰ رکورد)</summary>'+simpleTable(['شرکت','پلاک','سازنده','مدل / تیپ','علت'],
   data.unresolved.map(v=>[number(v.agencyId),v.plate,v.system,v.trim||v.model,v.reason]))+'</details>':'';
  report.innerHTML=stats+ready+pending;
 };
 preview.onclick=async()=>{
  preview.disabled=true;apply.disabled=true;report.textContent='در حال تطبیق نام خودروها با کلاس‌های تأییدشده…';
  try{const data=await api('/api/v1/admin/vehicle-class-detection');if(!section.isConnected)return;showResults(data);apply.disabled=!data.recoverable;}
  catch(e){report.textContent=e.message;}finally{preview.disabled=false;}
 };
 apply.onclick=async()=>{
  if(!confirm('پس از تهیه پشتیبان، کلاس فقط خودروهای خالی دارای تطبیق یکتا ذخیره شود؟'))return;
  apply.disabled=true;report.textContent='در حال تکمیل کلاس‌های خودرو…';
  try{
   const data=await api('/api/v1/admin/vehicle-class-detection',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({confirm:'APPLY_APPROVED_VEHICLE_CLASSES'})});
   report.innerHTML='<p>کلاس '+number(data.updated)+' خودرو تکمیل شد.</p>';showResults(data);
  }catch(e){report.textContent=e.message;}
 };
 void reload().catch(e=>cards.textContent='بارگذاری فهرست کلاس‌ها انجام نشد: '+e.message);
}
