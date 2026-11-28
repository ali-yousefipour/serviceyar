/* Student discounts: one authorized, backend-driven five-stage wizard. */
'use strict';
window.studentDiscountsPage=async function studentDiscountsPage(token){
 const host=document.getElementById('content');
 const money=v=>number(v)+' ریال';
 const isCurrent=()=>token===pageToken&&location.hash.slice(1).split('/')[0]==='student-discounts';
 let search='';
 const renderList=async()=>{
  const payload=await api(scoped('/api/v1/student-discounts?q='+encodeURIComponent(search)));
  if(!isCurrent())return;
  const rows=payload.items||[],stats=payload.summary||{};
  host.innerHTML='<section class="panel sy-discounts"><div class="page-intro"><div><h2>مدیریت تخفیف‌ها</h2><p>ثبت مستقل تخفیف دانش‌آموز و تعدیل حق سرویس راننده، همراه با سند حسابداری</p></div>'+
   (can('student-discounts','manage')?'<button type="button" class="primary" id="sdNew">تخفیف جدید</button>':'')+'</div>'+
   '<div class="sy-discount-stats">'+[
    ['تخفیف‌های فعال',stats.active||0],['تخفیف دانش‌آموزان',money(stats.studentDiscountIrr||0)],
    ['کسورات رانندگان',money(stats.driverDiscountIrr||0)],
    ['تغییر سهم ناخالص شرکت',money(stats.companyShareImpactIrr||0)],
    ['تخفیف‌های خاتمه‌یافته',stats.ended||0]
   ].map(([label,value])=>'<div><small>'+label+'</small><strong>'+esc(value)+'</strong></div>').join('')+'</div>'+
   '<div class="sy-discount-filters"><label>جستجوی دانش‌آموز<input id="sdSearch" type="search" maxlength="120" value="'+esc(search)+'" placeholder="نام و نام خانوادگی"></label><button id="sdFilter" type="button">جستجو</button></div>'+
   '<div class="table-wrap"><table><thead><tr><th>دانش‌آموز</th><th>مدرسه</th><th>درصد</th><th>شروع</th><th>نوع</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>'+
   (rows.length?rows.map(x=>'<tr><td>'+esc(x.student_name)+'</td><td>'+esc(x.school_name||'—')+'</td><td>'+esc((Number(x.rate_milli_percent)/1000).toLocaleString('fa-IR'))+'٪</td><td>'+esc(window.ServiceYarDates?.toSolar(x.effective_from)||x.effective_from)+'</td><td>'+esc(x.apply_to_student?'دانش‌آموز':'')+(x.apply_to_student&&x.apply_to_driver?' و ':'')+esc(x.apply_to_driver?'راننده':'')+'</td><td>'+esc(({active:'فعال',future:'زمان‌بندی‌شده',ended:'پایان‌یافته'})[x.status]||x.status)+'</td><td><button type="button" data-sd-details="'+Number(x.id)+'">جزئیات</button></td></tr>').join(''):'<tr><td colspan="7">تخفیفی یافت نشد.</td></tr>')+
   '</tbody></table></div></section>';
  document.getElementById('sdFilter').onclick=()=>{search=document.getElementById('sdSearch').value;renderList().catch(err=>notify(err.message,true));};
  document.getElementById('sdSearch').onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();document.getElementById('sdFilter').click();}};
  document.getElementById('sdNew')?.addEventListener('click',()=>openWizard());
  host.querySelectorAll('[data-sd-details]').forEach(button=>button.onclick=async()=>{
   try{
    const data=await api(scoped('/api/v1/student-discounts/'+button.dataset.sdDetails));
    if(!isCurrent())return;
    const item=data.discount;
    document.getElementById('modal').innerHTML='<div class="modal-backdrop"><div class="modal sy-discount-dialog" role="dialog" aria-modal="true" aria-label="جزئیات تخفیف"><h3>سوابق تخفیف #'+number(item.id)+'</h3>'+
     '<p>دانش‌آموز: '+esc(item.student_name)+'</p><p>علت: '+esc(item.reason)+'</p>'+
     '<p>اسناد مالی: '+esc((data.effects||[]).map(x=>'#'+x.journal_document_id).join('، ')||'—')+'</p>'+
     '<p>الحاقیه‌ها: '+esc((data.appendices||[]).map(x=>x.appendix_number).join('، ')||'—')+'</p>'+
     '<p>تاریخچه: '+number((data.history||[]).length)+' رویداد</p><button id="sdCloseDetails" type="button">بستن</button></div></div>';
    document.getElementById('sdCloseDetails').onclick=closeDialog;
   }catch(err){notify(err.message,true);}
  });
 };
 async function openWizard(){
  if(!can('student-discounts','manage'))return;
  const selected=await lookup('students');
  if(!isCurrent())return;
  const today=new Intl.DateTimeFormat('en-CA',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'}).format(new Date());
  const values={studentId:'',percent:'20',reason:'دانش آموز تنها از یک مسیر برای سرویس استفاده می‌کند',
   applyToStudent:true,applyToDriver:true,effectiveFrom:today,effectiveTo:'',requestKey:crypto.randomUUID()};
  let stage=0,preview=null,saving=false;
  const steps=['دانش‌آموز و درصد','علت تخفیف','بدهی دانش‌آموز','حق سرویس راننده','پیش‌نمایش'];
  const state=document.getElementById('modal');
  const field=(label,input)=>'<label>'+label+input+'</label>';
  const show=()=>{
   let body='';
   if(stage===0){
    const options=selected.filter(x=>Number(x.is_active??1)!==0).map(x=>'<option value="'+Number(x.id)+'">'+esc([x.first_name,x.last_name].filter(Boolean).join(' '))+' · '+esc(x.national_code||'')+'</option>').join('');
    body=field('جستجوی دانش‌آموز','<input id="sdStudentSearch" type="search" placeholder="نام، نام خانوادگی یا کد ملی" autocomplete="off">')+field('دانش‌آموز','<select id="sdStudent" required><option value="">انتخاب دانش‌آموز…</option>'+options+'</select>')+
     field('درصد تخفیف','<input id="sdPercent" inputmode="decimal" required value="'+esc(values.percent)+'">');
   }
   if(stage===1)body=field('علت تخفیف','<textarea id="sdReason" rows="5" maxlength="4000" required>'+esc(values.reason)+'</textarea>');
   if(stage===2)body='<p>آیا تخفیف در بدهکاری دانش‌آموز اعمال شود؟</p>'+field('انتخاب اثر مالی','<select id="sdParent"><option value="yes">بله، کاهش بدهکاری</option><option value="no">خیر، بدون تغییر</option></select>')+
     field('تاریخ شروع تخفیف','<input type="date" id="sdDate" required value="'+esc(values.effectiveFrom)+'">');
   if(stage===3)body='<p>آیا تخفیف از حق سرویس راننده نیز کسر شود؟</p>'+field('انتخاب اثر مالی','<select id="sdDriver"><option value="yes">بله، تعدیل حقوق راننده</option><option value="no">خیر، بدون تغییر</option></select>')+
     '<p>تاریخ مؤثر برای هر دو طرف یکسان است.</p>';
   if(stage===4){
    const a=preview?.amounts||{};
    body='<div class="sy-discount-stats">'+[
      ['تعهد اولیه دانش‌آموز',money(a.studentBase||0)],['تخفیف دانش‌آموز',money(a.studentDiscount||0)],
      ['تعهد پس از تخفیف',money(a.studentNet||0)],['مبنای راننده',money(a.driverBase||0)],
      ['کسر راننده',money(a.driverDiscount||0)],['مبنای نهایی راننده',money(a.driverNet||0)],
      ['اختلاف ناخالص',money(a.companyShare||0)]
    ].map(([k,v])=>'<div><small>'+k+'</small><strong>'+esc(v)+'</strong></div>').join('')+'</div>'+
    '<p>قرارداد: '+esc(preview?.contractNo||'—')+' | تاریخ مؤثر: '+esc(window.ServiceYarDates?.toSolar(values.effectiveFrom)||values.effectiveFrom)+'</p>'+
    '<p>مانده قبل: '+money(preview?.accountBefore?.balance||0)+' | پرداخت قبلی: '+money(preview?.accountAfter?.paid||0)+' | مانده بعد: '+money(preview?.accountAfter?.balance||0)+' | بستانکاری احتمالی: '+money(preview?.accountAfter?.credit||0)+'</p>'+
    '<p class="sy-discount-warning">این پیش‌نمایش از API مالی دریافت شده است. سند اصلی و پرداخت‌های ثبت‌شده حفظ می‌شوند.</p>';
   }
   state.innerHTML='<div class="modal-backdrop"><div class="modal sy-discount-dialog" role="dialog" aria-modal="true" aria-label="ثبت تخفیف"><header><h3>ثبت تخفیف دانش‌آموز</h3><p>مرحله '+number(stage+1)+' از '+number(5)+'</p></header>'+
    '<ol class="sy-discount-steps">'+steps.map((text,i)=>'<li class="'+(i===stage?'active':'')+'">'+esc(text)+'</li>').join('')+'</ol>'+
    '<div class="sy-discount-body">'+body+'</div><p id="sdError" role="alert" hidden></p>'+
    '<footer class="sy-discount-footer"><button type="button" id="sdCancel">انصراف</button><button type="button" id="sdBack" '+(stage===0?'disabled':'')+'>قبلی</button>'+
    '<button class="primary" type="button" id="sdNext">'+(stage===4?'تأیید و ثبت تخفیف':'مرحله بعد')+'</button></footer></div></div>';
   document.getElementById('sdCancel').onclick=closeDialog;
   document.getElementById('sdBack').onclick=()=>{if(stage>0){stage--;show();}};
   if(stage===0){
    document.getElementById('sdStudent').value=values.studentId;
    document.getElementById('sdPercent').oninput=e=>{values.percent=e.target.value;};
    document.getElementById('sdStudent').onchange=e=>values.studentId=e.target.value;
    document.getElementById('sdStudentSearch').oninput=e=>{
     const q=String(e.target.value).trim().replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
     const selector=document.getElementById('sdStudent');
     [...selector.options].forEach((option,index)=>{if(index>0)option.hidden=Boolean(q)&&!option.textContent.includes(q);});
    };
   }
   if(stage===1)document.getElementById('sdReason').oninput=e=>values.reason=e.target.value;
   if(stage===2){
    document.getElementById('sdParent').value=values.applyToStudent?'yes':'no';
    document.getElementById('sdParent').onchange=e=>values.applyToStudent=e.target.value==='yes';
    document.getElementById('sdDate').onchange=e=>values.effectiveFrom=e.target.value;
   }
   if(stage===3){
    document.getElementById('sdDriver').value=values.applyToDriver?'yes':'no';
    document.getElementById('sdDriver').onchange=e=>values.applyToDriver=e.target.value==='yes';
   }
   document.getElementById('sdNext').onclick=async()=>{
    if(saving)return;
    const error=document.getElementById('sdError');
    try{
     if(stage===0&&(!values.studentId||!Number.isFinite(Number(window.ServiceYarDates?.digits(values.percent).replace('٪','').replace('٫','.')||values.percent))))
      throw new Error('دانش‌آموز و درصد معتبر انتخاب کنید.');
     if(stage===1&&!values.reason.trim())throw new Error('علت تخفیف الزامی است.');
     if(stage===3&&!values.applyToStudent&&!values.applyToDriver)throw new Error('حداقل یکی از تخفیف‌ها باید اعمال شود.');
     saving=true;document.getElementById('sdNext').disabled=true;
     if(stage===3){
      preview=await write('/api/v1/student-discounts/preview',values);
      if(!isCurrent())return;
     }else if(stage===4){
      await write('/api/v1/student-discounts',values);
      if(!isCurrent())return;
      closeDialog();notify('تخفیف و سند مالی با موفقیت ثبت شدند.');await renderList();return;
     }
     stage++;show();
    }catch(err){error.textContent=err.message||'ثبت تخفیف ناموفق بود.';error.hidden=false;document.getElementById('sdNext').disabled=false;}
    finally{saving=false;}
   };
  };
  show();
 }
 try{await renderList();}catch(err){if(isCurrent())host.innerHTML='<div class="error" role="alert">'+esc(err.message)+'</div>';}
};
