'use strict';
(() => {
 const el=id=>document.getElementById(id);
 const fa=n=>Number(n||0).toLocaleString('fa-IR',{maximumFractionDigits:0});
 const money=n=>fa(Math.round(Number(n)||0))+' ریال';
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const normal=v=>String(v||'').trim().replace(/[۰-۹]/g,c=>String(c.charCodeAt(0)-1776)).replace(/[٠-٩]/g,c=>String(c.charCodeAt(0)-1632));
 const date=v=>{if(!v)return '—';const d=new Date(String(v).substring(0,10)+'T12:00:00Z');return Number.isNaN(d.getTime())?esc(v):new Intl.DateTimeFormat('fa-IR-u-ca-persian',{dateStyle:'medium',timeZone:'Asia/Tehran'}).format(d)};
 let csrf='',members=[],current=null,data=null,section='overview',impersonated=false,representativeSchools=[];
 const note=(msg,error=false)=>{
   let target=el('portalToast');
   if(!target){target=document.createElement('div');target.id='portalToast';target.setAttribute('role','status');document.body.append(target);}
   target.textContent=msg;target.className=msg?(error?'visible error':'visible'):'';
 };
 async function api(path,body,verb='GET'){
   const r=await fetch(path,{method:verb,credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json'}:{}),...(csrf&&verb!=='GET'?{'X-CSRF-Token':csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
   const j=await r.json().catch(()=>({}));
   if(!r.ok||!j.ok)throw Error(j.error?.message||'پاسخ سرویس معتبر نیست. دوباره تلاش کنید.');
   return j.data;
 }
 function menuLinks(){
   if(current.portalType==='representative')return [['overview','نمای کلی','◈'],['debtors','گزارش بدهکاران','▦']];
   return [['overview','نمای کلی','◈'],['services',current.portalType==='parent'?'سرویس فرزند':'مدارس و دانش‌آموزان','▤'],...(current.portalType==='driver'&&representativeSchools.length?[['debtors','گزارش بدهکاران','▦']]:[]),['finance','امور مالی','﷼'],['profile',impersonated?'مشاهده مشخصات':'اطلاعات و ویرایش','♧'],...(!impersonated?[['password','تنظیم رمز عبور','◇']]:[])];
 }
 function widget(title,value,hint,link=''){
   return '<article class="portal-kpi"><small>'+esc(title)+'</small><strong>'+esc(value)+'</strong><span>'+esc(hint||'')+'</span></article>';
 }
 function table(headers,rows){
   return '<div class="portal-table-scroll"><table><thead><tr>'+headers.map(x=>'<th>'+esc(x)+'</th>').join('')+'</tr></thead><tbody>'+
     (rows.length?rows.map(r=>'<tr>'+r.map(c=>'<td>'+esc(c??'—')+'</td>').join('')+'</tr>').join(''):
     '<tr><td colspan="'+headers.length+'" class="empty">اطلاعاتی ثبت نشده است.</td></tr>')+'</tbody></table></div>';
 }
 function person(p){return [p?.first_name,p?.last_name].filter(Boolean).join(' ')||'—'}
 function renderOverview(){
   if(current.portalType==='representative')return '<div class="portal-section-title"><h1>پنل نماینده مدارس</h1><p>مشاهده بدهکاران مدارس دارای مجوز</p></div>'+
    '<div class="portal-kpis">'+widget('مدارس تحت پوشش',fa(representativeSchools.length),'دسترسی اختصاصی شرکت')+'</div>'+
    '<section class="portal-panel"><h2>گزارش بدهکاران</h2><p>برای مشاهده وضعیت مالی دانش‌آموزان، مدرسه مورد نظر را انتخاب کنید.</p><button type="button" data-go="debtors" class="primary">مشاهده گزارش بدهکاران</button></section>';
   const parent=current.portalType==='parent',f=data.finance||{};
   const who=parent?person(data.student):person(data.driver);
   const cards=parent?[
     ['بدهی کل ثبت‌شده',money(f.total),'بر اساس فاکتورهای رسمی'],
     ['پرداخت‌شده به شرکت',money(f.paid),'پرداخت‌ها و دریافتی‌های ثبت‌شده'],
     ['مانده بدهی',money(f.balance),'پس از کسر پرداخت‌های ثبت‌شده'],
     ['سرویس‌های فعال',fa(data.services?.length),'متصل به دانش‌آموز']
   ]:[
     ['درآمد ماهانه سرویس‌ها',money(f.monthlyServicesTotal),'مبالغ فعلی ذخیره‌شده'],
     ['خالص کارکرد ثبت‌شده',money(f.recordedNet),'پس از کسورات قراردادی'],
     ['پرداخت‌های دریافتی',money(f.paid),'پرداخت‌های ثبت‌شده شرکت'],
     ['مانده کارکرد ثبت‌شده',money(f.balance),'محاسبه بر اساس کارکرد موجود']
   ];
   const percent=parent?(Number(f.total)>0?Math.min(100,Math.max(0,Number(f.paid)/Number(f.total)*100)):0):
       (Number(f.recordedNet)>0?Math.min(100,Math.max(0,Number(f.paid)/Number(f.recordedNet)*100)):0);
   return '<section class="portal-welcome"><div><small>داشبورد اختصاصی '+(parent?'والدین':'رانندگان')+'</small><h1>سلام، '+esc(who)+'</h1><p>'+esc(data.agency?.name||'سرویس‌یار')+' · اطلاعات ثبت‌شده در سامانه</p></div><span class="portal-welcome-ornament">✦</span></section>'+
   '<div class="portal-kpis">'+cards.map(x=>widget(...x)).join('')+'</div>'+
   '<div class="portal-panels"><section class="portal-panel"><h2>خلاصه مالی</h2><p class="muted">نسبت مبالغ پرداخت‌شده به مبالغ ثبت‌شده</p><div class="portal-ring-wrap"><div class="portal-ring" style="--pct:'+percent+'%"><div><strong>'+fa(percent)+'٪</strong><small>پرداخت‌شده</small></div></div><div class="portal-ring-legend"><div><b>ثبت‌شده</b><span>'+money(parent?f.total:f.recordedNet)+'</span></div><div><b>پرداخت‌شده</b><span>'+money(f.paid)+'</span></div><div><b>باقی‌مانده</b><span>'+money(f.balance)+'</span></div></div></div></section>'+
   '<section class="portal-panel"><h2>دسترسی‌های سریع</h2><div class="portal-shortcuts">'+menuLinks().filter(x=>x[0]!=='overview').map(x=>'<button type="button" data-go="'+x[0]+'"><b>'+x[2]+'</b>'+esc(x[1])+'<span>‹</span></button>').join('')+'</div></section></div>';
 }
 function renderServices(){
   if(current.portalType==='parent'){
     const student=data.student||{};
     return '<div class="portal-section-title"><h1>مشخصات فرزند و سرویس</h1><p>اطلاعات دانش‌آموز و راننده مربوط به همین حساب</p></div>'+
     '<section class="portal-panel"><h2>'+esc(person(student))+'</h2><div class="portal-details">'+
       [['مدرسه',student.schoolName],['پایه',student.grade],['نشانی منزل',student.address],['نشانی برگشت',student.return_address],['نشانی مدرسه',student.schoolAddress],['مبلغ ماهانه سرویس',money(student.monthly_fee)]].map(([k,v])=>'<div><span>'+esc(k)+'</span><b>'+esc(v||'—')+'</b></div>').join('')+'</div></section>'+
     (data.services||[]).map(s=>'<section class="portal-panel"><h2>'+esc(s.title||s.code||'سرویس')+'</h2><div class="portal-details">'+
       [['راننده',[s.driverFirstName,s.driverLastName].filter(Boolean).join(' ')],['تلفن راننده',s.driverMobile],['خودرو',s.model],['پلاک',s.plate],['ظرفیت',fa(s.capacity)],['نشانی سوارشدن',s.pickup_address]].map(([k,v])=>'<div><span>'+esc(k)+'</span><b>'+esc(v||'—')+'</b></div>').join('')+'</div></section>').join('')||'<p class="portal-panel">سرویس فعالی به دانش‌آموز تخصیص داده نشده است.</p>';
   }
   const grouped={};
   for(const s of data.students||[]){
     const key=String(s.id);if(!grouped[key])grouped[key]={title:s.title||s.code||'سرویس',monthly:s.monthly_fee,schools:{}};
     const sc=String(s.schoolId);(grouped[key].schools[sc]??={name:s.schoolName,students:[]}).students.push(s);
   }
   return '<div class="portal-section-title"><h1>مدارس و دانش‌آموزان سرویس</h1><p>فقط دانش‌آموزان تخصیص‌یافته به سرویس‌های فعال شما</p></div>'+
     (Object.values(grouped).map(g=>'<section class="portal-panel"><div class="portal-panel-heading"><h2>'+esc(g.title)+'</h2><strong>'+money(g.monthly)+' / ماه</strong></div>'+
       Object.values(g.schools).map(sc=>'<h3>مدرسه '+esc(sc.name)+'</h3>'+table(['دانش‌آموز','نشانی منزل','نشانی برگشت','محل سوارشدن'],sc.students.map(s=>[person(s),s.address,s.return_address,s.pickup_address]))).join('')+'</section>').join('')||'<section class="portal-panel">هنوز سرویس فعالی به شما اختصاص نیافته است.</section>');
 }
 function renderFinance(){
   const parent=current.portalType==='parent',f=data.finance||{};
   let heading=parent?'شهریه، فاکتورها و پرداخت‌ها':'حق‌الزحمه و دریافتی‌های راننده';
   let kpis=parent?[
      ['جمع فاکتورهای سالانه',money(f.total),'فاکتورهای ثبت‌شده'],['جمع پرداختی',money(f.paid),'تا آخرین ثبت'],['مانده بدهی',money(f.balance),'باقیمانده']
    ]:[['درآمد ماهانه سرویس‌ها',money(f.monthlyServicesTotal),'برآورد فعلی'],['خالص کارکرد',money(f.recordedNet),'ثبت‌شده'],['دریافتی',money(f.paid),'ثبت‌شده']];
   let bills=parent?'<section class="portal-panel"><h2>صورتحساب‌ها و فاکتورها</h2>'+
     table(['عنوان','مبلغ','پرداخت‌شده','باقی‌مانده','تاریخ'],(f.invoices||[]).map(x=>[x.title,money(x.amount),money(x.paidAmount),money(x.remainingAmount),date(x.issue_date)]))+
     '<p class="muted">مبلغ سالانه مطابق فاکتور ثبت‌شده شرکت نمایش داده می‌شود.</p></section>'+
     '<section class="portal-panel"><h2>اقساط و سررسیدها</h2>'+
      table(['عنوان قسط','مبلغ','سررسید','وضعیت'],(f.installments||[]).map(x=>[x.title,money(x.amount),date(x.due_date),x.status]))+
      '<p class="muted">فقط اقساط ثبت‌شده در صورتحساب شرکت نمایش داده می‌شوند؛ اقساط پیشنهادی یا هنوز تأییدنشده نمایش داده نمی‌شوند.</p></section>':
     '<section class="portal-panel"><h2>کارکرد راننده</h2>'+
     table(['تاریخ','مبلغ ثبت‌شده','وضعیت'],(f.earnings||[]).map(x=>[date(x.work_date),money(x.amount),x.status]))+
     '<p class="muted">'+esc(f.note||'')+'</p></section>';
   return '<div class="portal-section-title"><h1>'+heading+'</h1><p>مبالغ به ریال؛ بدون محاسبه دوباره یا تغییر سندهای ثبت‌شده</p></div>'+
     '<div class="portal-kpis">'+kpis.map(x=>widget(...x)).join('')+'</div>'+bills+
     '<section class="portal-panel"><h2>'+(parent?'فیش‌های پرداخت به شرکت':'پرداخت‌های شرکت به راننده')+'</h2>'+
     table(['تاریخ','مبلغ','روش','شماره پیگیری'],(f.payments||[]).map(x=>[date(x.payment_date),money(x.amount),x.method||'—',x.reference_no||'—']))+'</section>';
 }
 function renderDebtors(){
   if(!['driver','representative'].includes(current.portalType)||!representativeSchools.length)return '<section class="portal-panel">دسترسی نمایندگی فعال نیست.</section>';
   const yr=Number(normal(new Intl.DateTimeFormat('fa-IR-u-ca-persian',{year:'numeric',timeZone:'Asia/Tehran'}).format(new Date())));
   return '<div class="portal-section-title"><h1>گزارش بدهکاران</h1><p>صرفاً مدارس مجاز در شرکت انتخاب‌شده</p></div>'+
     '<section class="portal-panel"><form id="representativeDebtorsForm" class="portal-profile-form">'+
     '<label>انتخاب مدرسه<select name="schoolId" required>'+representativeSchools.map(s=>'<option value="'+Number(s.id)+'">'+esc(s.name)+'</option>').join('')+'</select></label>'+
     '<label>سال تحصیلی<input name="academicYear" required value="'+esc(String(yr)+'/'+String(yr+1))+'" placeholder="۱۴۰۵/۱۴۰۶"></label>'+
     '<label>نام دانش‌آموز<input name="student" type="search"></label>'+
     '<label>حداقل بدهی (ریال)<input name="minDebt" inputmode="numeric"></label>'+ 
     '<label>حداکثر بدهی (ریال)<input name="maxDebt" inputmode="numeric"></label>'+
     '<button type="submit" class="primary">نمایش بدهکاران</button></form><div id="repDebtorsSummary" role="status"></div><div id="repDebtorsTable"></div></section>';
 }
 async function loadDebtors(){
   const form=el('representativeDebtorsForm');if(!form)return;
   const status=el('repDebtorsSummary'),target=el('repDebtorsTable');
   const params=new URLSearchParams(new FormData(form));
   for(const key of ['academicYear','minDebt','maxDebt'])params.set(key,normal(params.get(key)).replace(/\s/g,'').replace(/[٬,]/g,''));
   status.textContent='در حال دریافت اطلاعات…';target.replaceChildren();
   try{
     const report=await api('/api/v1/portal/'+current.portalType+'/'+current.agencyId+'/'+current.partyId+'/debtors?'+params);
     if(!form.isConnected)return;
     status.textContent=fa(report.items.length)+' بدهکار · مجموع بدهی '+money(report.total);
     target.innerHTML=table(['دانش‌آموز','مدرسه','تلفن ولی','شهریه ماهانه','شهریه سالانه','پرداخت‌شده','بدهی باقی‌مانده'],
       report.items.map(row=>[row.student,row.school,row.guardian_mobile||'—',money(row.monthly_fee),money(row.annual_fee),money(row.paid),money(row.debt)]));
   }catch(error){status.textContent=error.message;}
 }
 function inputField(name,label,value,extra=''){
   return '<label>'+esc(label)+'<input name="'+name+'" value="'+esc(value||'')+'" '+extra+'></label>';
 }
 function renderProfile(){
   if(impersonated){
     const parent=current.portalType==='parent',person=parent?(data.guardian||{}):(data.driver||{});
     const fields=parent?[['نام ولی',person.name],['نسبت',person.relation_title],['نشانی دانش‌آموز',data.student?.address],['نشانی برگشت',data.student?.return_address]]:
       [['نام راننده',[person.first_name,person.last_name].filter(Boolean).join(' ')],['کد ملی',person.national_code],
       ['نشانی',person.address],['بانک',person.bank_name],['شماره حساب',person.bank_account],['شبا',person.iban],['خودرو',person.model],['پلاک',person.plate]];
     return '<section class="portal-panel"><h2>مشخصات ثبت‌شده · مشاهده نیابتی</h2><p class="muted">این اطلاعات فقط قابل مشاهده هستند. هیچ ویرایش یا تغییر رمز در این نشست انجام نمی‌شود.</p><div class="portal-details">'+
       [['شرکت',data.agency?.name],['شماره همراه',data.profile?.mobile],...fields].map(([k,v])=>'<div><span>'+esc(k)+'</span><b>'+esc(v||'—')+'</b></div>').join('')+'</div></section>';
   }
   const parent=current.portalType==='parent',p=parent?(data.guardian||{}):(data.driver||{});
   const address=parent?data.student?.address:p.address;
   return '<div class="portal-section-title"><h1>اطلاعات حساب و پروفایل</h1><p>ویرایش فقط روی اطلاعات مرتبط با حساب تأییدشده شما انجام می‌شود.</p></div>'+
   '<section class="portal-panel"><h2>حساب و شرکت سرویس‌دهنده</h2><div class="portal-details">'+
     [['شرکت',data.agency?.name],['کد شرکت',data.agency?.code],['نام مدیر',data.agency?.managerName],['تلفن دفتر',data.agency?.phone],
       ['تلفن مدیر',data.agency?.managerMobile],['آدرس دفتر',data.agency?.address],
       ['نام کاربری',data.profile?.username],['شماره همراه تأییدشده',data.profile?.mobile],['نوع حساب',parent?'ولی دانش‌آموز':'راننده سرویس']].map(([k,v])=>'<div><span>'+esc(k)+'</span><b>'+esc(v||'—')+'</b></div>').join('')+'</div></section>'+
   '<section class="portal-panel"><h2>ویرایش مشخصات</h2><form id="portalProfileForm" class="portal-profile-form">'+
      (parent?inputField('guardianName','نام ولی',p.name)+inputField('guardianRelation','نسبت با دانش‌آموز',p.relation_title):
       inputField('firstName','نام',p.first_name,'required maxlength="100"')+inputField('lastName','نام خانوادگی',p.last_name,'required maxlength="100"')+
       inputField('nationalCode','کد ملی',p.national_code,'inputmode="numeric" maxlength="10"')+
       inputField('bankName','بانک',p.bank_name)+inputField('bankAccount','شماره حساب',p.bank_account)+inputField('iban','شماره شبا',p.iban,'dir="ltr" maxlength="26"'))+
      inputField('address','نشانی',address)+
      (parent?inputField('returnAddress','نشانی برگشت',data.student?.return_address):'')+
      inputField('email','پست الکترونیکی',data.profile?.email,'type="email"')+
      '<button type="submit" class="primary">ثبت اطلاعات</button></form></section>'+
      (parent?'':'<section class="portal-panel"><h2>مشخصات خودرو و درخواست اصلاح</h2><p class="muted">برای حفظ صحت پلاک و مشخصات ثبت‌شده، تغییر خودرو باید توسط شرکت تأیید شود.</p><div class="portal-details"><div><span>مدل فعلی</span><b>'+esc(p.model||'—')+'</b></div><div><span>پلاک فعلی</span><b>'+esc(p.plate||'—')+'</b></div></div><form id="portalVehicleForm" class="portal-profile-form">'+inputField('model','درخواست مدل جدید','')+inputField('plate','درخواست پلاک جدید','')+inputField('note','توضیح درخواست','')+'<button type="submit" class="primary">ارسال درخواست اصلاح برای شرکت</button></form></section>');
 }
 function renderPassword(){
   return '<div class="portal-section-title"><h1>تنظیم رمز ورود</h1><p>نام کاربری شما شماره همراه تأییدشده '+esc(data.profile?.username||'')+' است.</p></div>'+
    '<section class="portal-panel portal-password-panel"><h2>'+(data.hasPassword?'تغییر رمز عبور':'ثبت اولین رمز عبور')+'</h2><p class="muted">رمز باید حداقل ۱۰ کاراکتر باشد. برای ورودهای بعدی می‌توانید از رمز یا کد ربات بله استفاده کنید.</p>'+
    '<form id="portalSetPasswordForm" class="portal-profile-form">'+
    (data.hasPassword?'<label>رمز فعلی<input name="currentPassword" type="password" autocomplete="current-password" required></label>':'')+
    '<label>رمز جدید<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>'+
    '<label>تکرار رمز جدید<input name="confirm" type="password" autocomplete="new-password" minlength="10" required></label>'+
    '<button class="primary" type="submit">ذخیره رمز عبور</button></form></section>';
 }
 function selectSection(next){
   if(impersonated&&next==='password')next='overview';
   section=next;
   const name=menuLinks().find(x=>x[0]===section)?.[1]||'داشبورد';
   const heading=el('pageTitle');if(heading)heading.textContent=name;
   document.querySelectorAll('[data-page]').forEach(x=>x.classList.toggle('active',x.dataset.page===section));
   el('portalContent').innerHTML=({overview:renderOverview,services:renderServices,finance:renderFinance,debtors:renderDebtors,profile:renderProfile,password:renderPassword}[section]||renderOverview)();
   el('portalContent').querySelectorAll('[data-go]').forEach(x=>x.addEventListener('click',()=>selectSection(x.dataset.go)));
   const repForm=el('representativeDebtorsForm');if(repForm){repForm.addEventListener('submit',e=>{e.preventDefault();loadDebtors();});loadDebtors();}
   const profile=el('portalProfileForm');
   if(profile)profile.addEventListener('submit',async e=>{
     e.preventDefault();const body=Object.fromEntries(new FormData(profile));
     const button=profile.querySelector('button[type=submit]');button.disabled=true;
     try{data=await api('/api/v1/portal/'+current.portalType+'/'+current.agencyId+'/'+current.partyId,body,'PATCH');
       note('اطلاعات شما ذخیره شد.');selectSection('profile');}catch(err){note(err.message,true);}finally{button.disabled=false;}
   });
   const pass=el('portalSetPasswordForm');
   if(pass)pass.addEventListener('submit',async e=>{
     e.preventDefault();const body=Object.fromEntries(new FormData(pass));const button=pass.querySelector('button');button.disabled=true;
     try{await api('/api/v1/portal/password',body,'POST');data.hasPassword=true;
       note('رمز عبور ثبت شد. از این پس شماره همراه، نام کاربری شماست.');selectSection('password');}catch(err){note(err.message,true);}finally{button.disabled=false;}
   });
   const vehicle=el('portalVehicleForm');
   if(vehicle)vehicle.addEventListener('submit',async e=>{
     e.preventDefault();const body=Object.fromEntries(new FormData(vehicle));const button=vehicle.querySelector('button');button.disabled=true;
     try{const r=await api('/api/v1/portal/driver/'+current.agencyId+'/'+current.partyId+'/vehicle-request',body,'POST');
       note(r.message);vehicle.reset();}catch(err){note(err.message,true);}finally{button.disabled=false;}
   });
 }

 async function openMembership(m){
   current=m;
   data=await api('/api/v1/portal/'+m.portalType+'/'+m.agencyId+'/'+m.partyId);
   representativeSchools=m.portalType==='representative'?await api('/api/v1/portal/representative/'+m.agencyId+'/'+m.partyId+'/schools'):m.portalType==='driver'?await api('/api/v1/portal/driver/'+m.agencyId+'/'+m.partyId+'/representative-schools'):[];
   const parent=m.portalType==='parent',name=parent?'والدین':m.portalType==='representative'?'نمایندگان':'رانندگان';
   const sidebar=el('nav');sidebar.replaceChildren();
   const links=menuLinks();
   links.forEach(([key,title,icon])=>{
      const button=document.createElement('button');button.type='button';
      button.className='nav sy-portal-nav';button.dataset.page=key;
      const symbol=document.createElement('span');symbol.className='sy-portal-nav-icon';symbol.textContent=icon;
      const caption=document.createElement('span');caption.textContent=title;
      button.append(symbol,caption);
      button.onclick=()=>{
        history.replaceState(history.state,'',location.pathname+location.search+'#portal-'+key);
        selectSection(key);document.querySelector('.sidebar')?.classList.remove('nav-open');
        const shade=el('navigationShade');if(shade)shade.hidden=true;
        const toggle=el('navigationToggle');if(toggle)toggle.setAttribute('aria-expanded','false');
      };
      sidebar.append(button);
   });
   // Use the same responsive navigation drawer as the company dashboard.
   if(!el('navigationToggle')){
     const button=document.createElement('button');button.type='button';button.id='navigationToggle';
     button.setAttribute('aria-label','باز کردن منوی اصلی');button.setAttribute('aria-expanded','false');
     button.textContent='☰';document.querySelector('.header')?.prepend(button);
     const shade=document.createElement('button');shade.type='button';shade.id='navigationShade';
     shade.setAttribute('aria-label','بستن منوی اصلی');shade.hidden=true;el('app').append(shade);
     const toggle=open=>{
       document.querySelector('.sidebar')?.classList.toggle('nav-open',open);
       shade.hidden=!open;button.setAttribute('aria-expanded',String(open));
     };
     button.onclick=()=>toggle(button.getAttribute('aria-expanded')!=='true');
     shade.onclick=()=>toggle(false);
     document.addEventListener('keydown',e=>{if(e.key==='Escape'&&button.getAttribute('aria-expanded')==='true')toggle(false);});
   }
   const brand=document.querySelector('.sy-sidebar-brand-copy small');
   if(brand)brand.textContent='پنل '+name+' · '+m.agencyName;
   const sideName=el('syAccountName');if(sideName)sideName.textContent=data.profile?.username||'حساب شخصی';
   const role=el('syAccountRole');if(role)role.textContent=impersonated?'مشاهده نیابتی ادمین کل':('پنل '+name);
   const avatar=el('syAccountAvatar');if(avatar){avatar.textContent=parent?'و':m.portalType==='representative'?'ن':'ر';avatar.classList.remove('sy-account-avatar-has-photo');}
   const account=el('syAccountMenu');if(account){account.open=false;account.hidden=true;}
   const agencyPicker=el('agencyPickerWrap');if(agencyPicker)agencyPicker.hidden=true;
   const returnButton=el('syAdminReturn');if(returnButton)returnButton.hidden=!impersonated;
   const logout=el('logout');if(logout)logout.textContent=impersonated?'بازگشت به پنل ادمین کل':'خروج از حساب';
   el('app').classList.add('sy-portal-mode');
   const content=el('content');
   const selector=members.length>1?'<label class="sy-portal-identity-switch">انتخاب فرزند، شرکت یا نقش<select id="portalSwitch">'+members.map((x,i)=>'<option value="'+i+'"'+(x===m?' selected':'')+'>'+(x.portalType==='parent'?'والدین':x.portalType==='representative'?'نمایندگان':'رانندگان')+' · '+esc(x.agencyName)+' · '+fa(x.partyId)+'</option>').join('')+'</select></label>':'';
   content.innerHTML='<div class="sy-portal-content">'+
     (impersonated?'<div class="sy-portal-impersonation-notice">در حال مشاهده نیابتی پنل '+name+' هستید؛ ویرایش اطلاعات و تغییر رمز غیرفعال است.</div>':'')+
     selector+'<div id="portalContent"></div></div>';
   if(el('portalSwitch'))el('portalSwitch').onchange=e=>openMembership(members[Number(e.target.value)]).catch(err=>note(err.message,true));
   const route=location.hash.slice(1);
   const allowed=['overview','services','finance','profile','password','debtors'];
   section=route.startsWith('portal-')&&allowed.includes(route.slice(7))?route.slice(7):'overview';
   if(!menuLinks().some(x=>x[0]===section))section='overview';
   if(impersonated&&section==='password')section='overview';
   selectSection(section);
   if(!data.hasPassword&&m.portalType!=='representative'&&!impersonated)note('می‌توانید از بخش «تنظیم رمز عبور» رمز اختصاصی برای شماره همراه خود تعریف کنید.');
 }
 function selectPortalRoute(raw){
   const pages={'portal-debtors':'debtors',dashboard:'overview','portal-dashboard':'overview','portal-services':'services','portal-finance':'finance','portal-profile':'profile','portal-password':'password'};
   if(pages[raw])selectSection(pages[raw]);
 }
 async function mount(authSession){
   impersonated=Boolean(authSession?.impersonation);
   const auth=authSession||await api('/api/v1/auth/me');
   csrf=auth.csrfToken;members=await api('/api/v1/portal/memberships');
   if(!members.length)throw Error('برای این کاربر عضویت معتبر والدین یا رانندگان ثبت نشده است.');
   const agency=Number(auth.impersonation?.agencyId||0);
   const chosen=members.find(x=>agency>0&&Number(x.agencyId)===agency)||
      members.find(x=>x.portalType==='parent')||members[0];
   await openMembership(chosen);
 }
 window.serviceyarPortal={mount,selectPortalRoute};
})();