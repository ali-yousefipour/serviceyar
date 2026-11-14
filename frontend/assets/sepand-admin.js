/* Super-admin Sepand registry file import. Uses the two official XLSX files. */
async function sepandAdminPanel(anchor){
 const panel=document.createElement('section');panel.id='syAdminSepand';panel.className='panel sy-super-admin-card sy-sepand-admin';
 panel.innerHTML='<header class="sy-settings-section-head"><div><h2>به‌روزرسانی رانندگان و خودروها از سپند</h2><p>ابتدا فایل رانندگان و خودروها را انتخاب کنید؛ پیش‌نمایش هیچ داده‌ای را تغییر نمی‌دهد. پس از بررسی شرکت‌های دارای اختلاف نام، عملیات را تأیید کنید.</p></div></header>'+
 '<section class="sy-sepand-db-health" aria-label="وضعیت ساختار پایگاه داده"><div id="sySepandDbStatus" role="status">در حال بررسی آماده‌بودن دیتابیس سپند…</div><button type="button" id="sySepandDbRepair" hidden>آماده‌سازی دیتابیس سپند</button><p class="muted">قبل از تغییر ساختار دیتابیس، از اطلاعات هاست نسخه پشتیبان تهیه کنید. آماده‌سازی صرفاً ستون‌ها و جدول‌های لازم را اضافه می‌کند.</p></section>'+ 
 '<form id="sySepandImport"><div class="form-grid"><label>فایل رانندگان سپند (XLSX)<input type="file" name="drivers" accept=".xlsx" required></label><label>فایل خودروهای سپند (XLSX)<input type="file" name="vehicles" accept=".xlsx" required></label></div><div id="sySepandMapping" class="sy-sepand-mappings"></div><div class="member-actions"><button type="button" id="sySepandPreview" class="primary">پیش‌نمایش و تطبیق شرکت‌ها</button><button type="button" id="sySepandApply" disabled>تأیید و اعمال به‌روزرسانی</button></div></form><div id="sySepandResult" aria-live="polite" role="status"></div>';
 anchor.after(panel);
 const form=panel.querySelector('form'),preview=panel.querySelector('#sySepandPreview'),apply=panel.querySelector('#sySepandApply'),mapping=panel.querySelector('#sySepandMapping'),result=panel.querySelector('#sySepandResult');
 const dbStatus=panel.querySelector('#sySepandDbStatus'),dbRepair=panel.querySelector('#sySepandDbRepair');
 const checkSchema=async()=>{
  dbStatus.textContent='در حال بررسی ساختار دیتابیس سپند…';
  try{
   const state=await api('/api/v1/admin/sepand-registry/schema');
   if(!panel.isConnected)return;
   dbStatus.textContent=state.ready?'ساختار دیتابیس سپند آماده است. می‌توانید پیش‌نمایش تهیه کنید.':'ساختار دیتابیس سپند ناقص است؛ '+number(state.missingColumns?.length||0)+' ستون، '+number(state.missingIndexes?.length||0)+' ایندکس'+(state.missingAuditTable?' و جدول سوابق':'')+' باید آماده شود.';
   dbRepair.hidden=!!state.ready;
  }catch(e){
   if(!panel.isConnected)return;
   dbStatus.textContent='بررسی وضعیت دیتابیس پاسخ معتبر نداد: '+e.message+'؛ می‌توانید بررسی فایل را اجرا کنید تا خطای دقیق مشخص شود یا پس از پشتیبان‌گیری، آماده‌سازی ساختار را امتحان کنید.';
   // A broken health endpoint must never lock both the repair operation
   // and the read-only file preview. The server independently validates
   // database readiness before any write.
   dbRepair.hidden=false;
  }finally{
   preview.disabled=false;
  }
 };
 dbRepair.onclick=async()=>{
  if(!confirm('پس از تهیه نسخه پشتیبان، ستون‌ها و جدول‌های سپند در دیتابیس ساخته شوند؟ هیچ داده راننده یا خودرویی حذف نمی‌شود.'))return;
  dbRepair.disabled=true;dbStatus.textContent='در حال آماده‌سازی ساختار دیتابیس سپند…';
  try{
   await api('/api/v1/admin/sepand-registry/schema',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({confirm:'REPAIR_SEPAND_SCHEMA'})});
   await checkSchema();
  }catch(e){dbStatus.textContent='آماده‌سازی دیتابیس انجام نشد: '+e.message+'؛ ابتدا مجوز ALTER/CREATE پایگاه داده و وجود مهاجرت 0103 را بررسی کنید.';dbRepair.hidden=false;}
  finally{dbRepair.disabled=false;preview.disabled=false;}
 };
 void checkSchema();
 let digest='',report=null;
 const mappingValue=()=>Object.fromEntries([...mapping.querySelectorAll('[data-national]')].filter(s=>s.value).map(s=>[s.dataset.national,Number(s.value)]));
 const refreshAllowed=()=>{const ss=[...mapping.querySelectorAll('[data-national]')],v=ss.map(s=>s.value).filter(Boolean);apply.disabled=!digest||v.length!==ss.length||new Set(v).size!==v.length;};
 const summary=d=>{
  const c=d.counts||{};
  result.innerHTML=(d.alreadyImported?'<p>این دو فایل قبلاً وارد شده‌اند و مجدداً تغییری اعمال نشد.</p>':'')+
   '<div class="sy-sepand-stats">'+[['ردیف رانندگان',c.driversSource],['ردیف خودروها',c.vehiclesSource],['راننده جدید',c.driversCreated],['راننده به‌روزرسانی',c.driversUpdated],['خودروی جدید',c.vehiclesCreated],['خودروی به‌روزرسانی',c.vehiclesUpdated],['راننده نیازمند بررسی',c.driversSkipped],['خودروی نیازمند بررسی',c.vehiclesSkipped]].map(([l,n])=>'<div><span>'+esc(l)+'</span><b>'+number(n||0)+'</b></div>').join('')+'</div>'+
   (d.issues?.length?'<details open><summary>موارد نیازمند بررسی</summary>'+simpleTable(['گزارش','ردیف','علت'],d.issues.map(i=>[i.type==='drivers'?'رانندگان':'خودروها',number(i.line),i.message]))+'</details>':'<p>خطای ردیف گزارش نشده است.</p>');
 };
 const send=async mode=>{
  if(!form.reportValidity())return;
  const button=mode==='apply'?apply:preview;button.disabled=true;result.textContent=mode==='apply'?'در حال به‌روزرسانی اطلاعات…':'در حال تحلیل فایل‌ها…';
  try{
   const body=new FormData(form);body.set('mode',mode);body.set('digest',digest);body.set('companyMapping',JSON.stringify(mappingValue()));
   const response=await fetch('/api/v1/admin/sepand-registry/import',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':session.csrfToken,'Accept':'application/json'},body});
   const packet=await response.json().catch(()=>({}));if(!response.ok||!packet.ok)throw new Error(packet?.error?.message||'خطا در تحلیل اطلاعات سپند');
   const data=packet.data;summary(data);
   if(mode==='preview'){
    digest=data.digest;report=data;
    const opts=(data.companies||[]).map(c=>'<option value="'+esc(c.id)+'">'+esc(c.name)+(c.nationalId?' ('+esc(c.nationalId)+')':'')+'</option>').join('');
    mapping.innerHTML=data.unmatched?.length?'<h3>تطبیق نام شرکت‌ها</h3><p>نام این شرکت‌ها با اطلاعات سامانه تطبیق قطعی ندارد. برای جلوگیری از انتقال اطلاعات به شرکت اشتباه، مقصد هرکدام را مشخص کنید.</p>'+data.unmatched.map(c=>'<label>'+esc(c.name)+' · '+esc(c.nationalId)+'<select data-national="'+esc(c.nationalId)+'"><option value="">انتخاب شرکت متناظر</option>'+opts+'</select></label>').join(''):'<p>شرکت‌های گزارش با سامانه تطبیق داده شدند.</p>';
    mapping.querySelectorAll('select').forEach(el=>el.onchange=refreshAllowed);refreshAllowed();
   }else{digest='';apply.disabled=true;lookupCache={};}
  }catch(e){result.textContent='عملیات انجام نشد: '+e.message;if(mode==='apply')apply.disabled=false;}
  finally{if(mode==='preview')preview.disabled=false;}
 };
 form.querySelectorAll('input[type=file]').forEach(input=>input.addEventListener('change',()=>{digest='';report=null;apply.disabled=true;mapping.textContent='';result.textContent='فایل تغییر کرد؛ ابتدا پیش‌نمایش جدید بگیرید.';}));
 preview.onclick=()=>send('preview');
 apply.onclick=()=>{if(!digest||!report)return;if(confirm('اطلاعات رانندگان و خودروهای تطبیق‌داده‌شده به‌روز شود؟ وضعیت فعالیت و سوابق مالی رانندگان تغییر نمی‌کند.'))send('apply');};
}
