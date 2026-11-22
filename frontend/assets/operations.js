/* Transport operations. All writes use the authenticated API and server-side agency scope. */
'use strict';
const labels={contractStatus:'وضعیت قرارداد',studentCredit:'بستانکاری (ریال)',invoicePaid:'دریافتی مستقیم (ریال)',invoiceAdjustment:'سند تعدیل کلاس (ریال)',invoiceEffective:'مبلغ قابل پرداخت پس از اصلاح (ریال)',invoiceOriginal:'مبلغ اولیه فاکتور (ریال)',invoiceRemaining:'مانده اصلاح‌شده (ریال)',creatorName:'ثبت‌کننده',partyName:'شخص / طرف حساب',fullName:'نام و نام خانوادگی',driverName:'راننده تخصیص‌یافته',ownerNationalCode:'کد ملی مالک خودرو',ownerName:'نام مالک خودرو',paymentStatus:'وضعیت پرداخت',providerStatus:'وضعیت سرویس‌دهنده',attempts:'تعداد تلاش',name:'نام مدرسه',code:'کد',schoolType:'نوع مدرسه',managerName:'نام مدیر',phone:'تلفن',region:'ناحیه',gender:'جنسیت',shift:'شیفت',educationLevels:'مقاطع تحصیلی',firstName:'نام',lastName:'نام خانوادگی',nationalCode:'کد ملی',grade:'پایه تحصیلی',mobile:'شماره همراه',schoolId:'مدرسه',driverId:'راننده',vehicleId:'خودرو',packId:'پک سرویس',plate:'پلاک',model:'مدل خودرو',capacity:'ظرفیت',status:'وضعیت',rating:'امتیاز',title:'عنوان',contractNo:'شماره قرارداد',partyType:'نوع طرف حساب',partyId:'طرف حساب',startDate:'تاریخ شروع',endDate:'تاریخ پایان',amount:'مبلغ (ریال)',workDate:'تاریخ کارکرد',paymentDate:'تاریخ پرداخت',entryDate:'تاریخ',referenceNo:'شماره پیگیری',dueDate:'سررسید',invoiceId:'فاکتور',method:'روش پرداخت',parentId:'حساب بالادست',isActive:'فعال',documentNo:'شماره سند',documentDate:'تاریخ سند',description:'شرح',totalAmount:'مبلغ کل',ownerType:'نوع مالک',ownerId:'مالک',balance:'موجودی',bankName:'نام بانک',bankAccount:'شماره حساب بانکی',iban:'شماره شبا',companyAccountId:'حساب شرکت (مبدأ یا مقصد)',channel:'کانال',recipientType:'نوع گیرنده',recipientId:'گیرنده',subject:'موضوع',body:'متن',serviceId:'سرویس',oldDriverId:'راننده قبلی',newDriverId:'راننده جدید',reason:'دلیل',address:'نشانی',latitude:'عرض جغرافیایی',longitude:'طول جغرافیایی',returnAddress:'نشانی برگشت',startAddress:'نشانی مبدا',endAddress:'نشانی مقصد',guardianFirstName:'نام ولی',guardianLastName:'نام خانوادگی ولی',guardianNationalCode:'کد ملی ولی',guardianRelation:'نسبت ولی',fatherName:'نام پدر',nationality:'ملیت',healthStatus:'وضعیت جسمانی',emergencyPhone:'شماره‌های اضطراری',academicYear:'سال تحصیلی',serviceStatus:'وضعیت سرویس',monthlyFee:'ماهیانه (ریال)',annualFee:'مبلغ سالانه (ریال)',annualMonths:'ماه‌های سال تحصیلی',agreedAmount:'مبلغ توافقی راننده (ریال)',isRepresentative:'نماینده',bankName:'نام بانک',bankAccount:'شماره حساب',iban:'شماره شبا',direction:'نوع عملیات',note:'توضیحات',score:'امتیاز',studentId:'دانش‌آموز',response:'پاسخ',issueDate:'تاریخ صدور',pickupAddress:'نشانی سوار شدن',pickupOrder:'ترتیب توقف'};
const statuses={no_request:'بدون درخواست',pending:'در انتظار تأیید',prepayment:'در انتظار پیش‌پرداخت',waiting:'در انتظار سرویس',assigned:'دارای سرویس',cancelled:'انصراف',active:'فعال',inactive:'غیرفعال',draft:'پیش‌نویس',open:'باز',paid:'پرداخت شده',overdue:'سررسید گذشته',approved:'تأیید شده',rejected:'رد شده',queued:'در صف ارسال',sent:'پذیرفته شده توسط سرویس',uncertain:'نیازمند بررسی',failed:'ناموفق',closed:'بسته شده'};
Object.assign(labels,{sepandStatus:'وضعیت تأیید در سپند',sepandRequestId:'شناسه درخواست سپند',gender:'جنسیت',vin:'شماره VIN',vehicleType:'نوع کاربری خودرو (سواری / ون)',vehicleSystem:'شرکت سازنده',vehicleTrim:'نوع / مدل خودرو (جستجوی لحظه‌ای)',makeYear:'سال ساخت',fuelType:'نوع سوخت',color:'رنگ',engineNumber:'شماره موتور',chassisNumber:'شماره شاسی',insuranceNumber:'شماره بیمه',platePart1:'۲ رقم پلاک',plateLetter:'حرف پلاک',platePart3:'۳ رقم پلاک',plateIran:'کد ایران',invoiceTotal:'جمع فاکتور بدهی (ریال)',tariffDistanceKm:'مسافت تعرفه (کیلومتر)',tariffVehicleClass:'کلاس خودرو در تعرفه',feeSource:'مبنای محاسبه هزینه سرویس',paidTotal:'جمع دریافتی ثبت‌شده (ریال)',outstandingFee:'مانده بدهی دانش‌آموز (ریال)'});
const transportFields={
 vehicles:['plate','ownerName','ownerNationalCode','vin','model','vehicleType','vehicleSystem','vehicleTrim','makeYear','fuelType','color','engineNumber','chassisNumber','insuranceNumber','tariffVehicleClass','capacity','sepandStatus','isActive'],
 schools:['name','code','schoolType','gender','region','managerName','phone','shift','educationLevels','address','latitude','longitude'],
 students:['mobile','guardianFirstName','guardianLastName','guardianNationalCode','guardianRelation','address','returnAddress','latitude','longitude','schoolId','grade','shift','academicYear','firstName','lastName','gender','nationality','nationalCode','fatherName','emergencyPhone','healthStatus','serviceStatus','tariffDistanceKm','tariffVehicleClass','feeSource','monthlyFee'],
 drivers:['firstName','lastName','nationalCode','gender','mobile','vehicleId','sepandRequestId','sepandStatus','status','isRepresentative','address','bankName','bankAccount','iban'],
 services:['title','code','schoolId','driverId','packId','capacity','shift','status','feeSource','monthlyFee','agreedAmount','startAddress','endAddress'],
 contracts:['title','contractNo','partyType','partyId','startDate','endDate','amount','status'],
 'driver-work':['driverId','workDate','serviceId','amount','status','note'],
 'driver-payments':['driverId','paymentDate','amount','referenceNo','note'],
 invoices:['title','partyType','partyId','amount','status','issueDate','dueDate'],
 payments:['invoiceId','amount','paymentDate','method','referenceNo','note'],
 ledger:['partyType','partyId','companyAccountId','direction','amount','entryDate','method','referenceNo','note'],
 'driver-ratings':['driverId','score','note'],
 'parent-reports':['studentId','subject','body','status','response']
};
resources.accounts=['حساب‌ها',['code','title','bankName','bankAccount','iban','parentId','isActive']];resources.ledger=['دریافت و پرداخت',transportFields.ledger];resources['driver-ratings']=['امتیاز و گزارش',transportFields['driver-ratings']];resources['parent-reports']=['گزارشات والدین',transportFields['parent-reports']];
for(const [key,fields] of Object.entries(transportFields))resources[key][1]=fields;
// Orient school photo containers from the loaded image dimensions; no fixed 4:3 crop.
function setSchoolPhotoOrientation(img){
 if(!img||!img.naturalWidth||!img.naturalHeight)return;
 const ratio=img.naturalWidth/img.naturalHeight;
 const frame=img.closest('.sy-school-details-cover,.sy-school-photo-editor-preview,.sy-school-photo-name,.sy-map-school');
 if(!frame)return;
 frame.style.setProperty('--sy-school-aspect',String(ratio));
 frame.classList.toggle('sy-photo-portrait',ratio<.9);
 frame.classList.toggle('sy-photo-landscape',ratio>1.1);
 frame.classList.toggle('sy-photo-square',ratio>=.9&&ratio<=1.1);
}
document.addEventListener('load',event=>{
 const img=event.target;
 if(img instanceof HTMLImageElement&&img.matches('.sy-school-details-image,.sy-school-photo-editor-preview img,.sy-school-photo-name img,.sy-map-school .sy-map-marker-photo')){
  setSchoolPhotoOrientation(img);
 }
},true);
function schoolPhotoUrl(row){
 const path=String(row?.photo_path??row?.photoPath??'').trim();
 if(!path||!row?.id||!currentAgency?.id)return '';
 return '/api/v1/schools/'+encodeURIComponent(row.id)+'/photo?agencyId='+encodeURIComponent(currentAgency.id);
}
const columns={invoices:['title','partyType','partyName','contractNo','invoiceEffective','invoiceOriginal','invoiceAdjustment','invoicePaid','invoiceRemaining','status','issueDate','dueDate'],messages:['channel','recipientType','recipientId','subject','status','providerStatus','attempts'],schools:['code','name','shift','schoolType','region'],students:['fullName','schoolId','driverName','serviceStatus','contractStatus','paymentStatus','mobile','tariffDistanceKm','monthlyFee','invoiceTotal','paidTotal','outstandingFee','studentCredit'],drivers:['firstName','lastName','nationalCode','mobile','sepandStatus','status','vehicleId'],vehicles:['plate','ownerName','driverName','model','vin','tariffVehicleClass','sepandStatus','capacity'],services:['code','title','driverId','schoolId','capacity','monthlyFee','agreedAmount','shift']};
const relations={schoolId:'schools',driverId:'drivers',oldDriverId:'drivers',newDriverId:'drivers',studentId:'students',vehicleId:'vehicles',serviceId:'services',packId:'packs',invoiceId:'invoices',parentId:'accounts'};
const permissionFor={'financial-reconciliation':'finance','debtors':'finance','installments':'finance','school-statement':'finance','daily-schedule':'services','driver-assignment':'services','school-report':'reports',schools:'schools',students:'students',drivers:'drivers',vehicles:'drivers',services:'services',packs:'services',contracts:'services','driver-work':'finance','driver-payments':'finance','school-charges':'finance','service-charges':'finance',invoices:'finance',payments:'finance',accounts:'finance','accounting-documents':'finance',wallets:'finance',ledger:'finance',messages:'messages','driver-changes':'services','driver-ratings':'drivers','portal-vehicle-requests':'drivers','representatives':'drivers','parent-reports':'messages',reports:'reports',monitoring:'monitoring','bale-payments':'finance',settings:'settings',dashboard:'dashboard',grouping:'services',payroll:'finance','finance-report':'finance',distances:'reports',import:'students','previous-students':'students',agreements:'services'};
const menus=[
 ['schools','مدرسه',[['schools/new','مدرسه جدید'],['schools','لیست مدارس'],['school-report','گزارش مدارس']]],
 ['students','دانش‌آموزان',[['students/new','دانش‌آموز جدید'],['import','ثبت دانش‌آموز از اکسل'],['previous-students','دانش‌آموزان سال قبل'],['students','لیست دانش‌آموزان']]],
 ['drivers','رانندگان',[['drivers','مدیریت راننده‌ها'],['representatives','مدیریت نمایندگان'],['driver-ratings','امتیاز و گزارش'],['portal-vehicle-requests','درخواست‌های اصلاح خودرو'],['driver-work','گزارش عملکرد راننده'],['finance-report','دریافتی پرداختی رانندگان']]],
 ['services','سرویس‌ها',[['services/new','سرویس جدید'],['grouping','دسته‌بندی هوشمند'],['packs','پک‌های سرویس'],['services','عملیات سرویس‌ها'],['students/fees','تغییر سریع هزینه سرویس'],['service-charges','حق‌السرویس']]],
 ['accounts','مالی',[['accounts','تعاریف حسابداری'],['accounting-documents','اسناد حسابداری'],['agreements','ثبت مبلغ توافقی راننده'],['payroll','حقوق رانندگان'],['driver-payments','سند پرداخت حقوق راننده'],['finance-report','گزارش دریافتی پرداختی'],['invoices','مدیریت و صدور فاکتورها'],['debtors','گزارش بدهکاران'],['financial-reconciliation','تطبیق و مغایرت‌های مالی'],['wallets','کیف پول'],['installments','پیش‌پرداخت و اقساط']]],
 ['reports','امکانات',[['messages','پیام‌رسانی'],['monitoring','مانیتورینگ'],['distances','بررسی فاصله‌های ناجور'],['reports','خروجی گزارشات'],['settings','تنظیمات'],['vehicles','خودروها'],['driver-assignment','تخصیص پیشنهادی راننده'],['daily-schedule','برنامه روزانه و تداخل شیفت‌ها'],['driver-changes','تغییر راننده'],['contracts','قراردادها'],['accounting-documents/statement','گزارش حساب'],['accounting-documents/quick','سند سریع'],['accounting-documents/defaults','حساب‌های پیش‌فرض'],['accounting-documents/cheques','چک‌های دریافتی و عملیات چک'],['ledger','دریافت و پرداخت'],['school-statement','صورت‌وضعیت سرویس مدارس'],['bale-payments','پرداخت کیف پول بله'],['school-charges','بدهی مدارس'],['payments','پرداخت‌ها'],['parent-reports','گزارشات والدین']]]
];
for(const [k,v] of Object.entries(resources))titles[k]=v[0];
Object.assign(titles,{'financial-reconciliation':'تطبیق و مغایرت‌های مالی','debtors':'گزارش بدهکاران','representatives':'مدیریت نمایندگان','installments':'برنامه پیش‌پرداخت و اقساط','school-statement':'صورت‌وضعیت سرویس مدارس','daily-schedule':'برنامه روزانه سرویس‌ها','driver-assignment':'تخصیص پیشنهادی راننده','school-report':'گزارش مدارس','portal-vehicle-requests':'درخواست‌های اصلاح خودرو','bale-payments':'پرداخت کیف پول بله',import:'ثبت دانش‌آموز از اکسل','previous-students':'دانش‌آموزان سال قبل',grouping:'دسته‌بندی هوشمند',payroll:'حقوق رانندگان','finance-report':'گزارش دریافتی پرداختی',monitoring:'مانیتورینگ',distances:'بررسی فاصله‌های ناجور',agreements:'ثبت مبلغ توافقی راننده'});
let pageToken=0,gridState=null,lookupCache={};
let chequeSearchSequence=0;
let journalRequestSequence=0,initialSearch='',initialStudentStatus='',returnFocus=null;
const can=(key,action='view')=>session?.roles?.includes('super_admin')||session?.permissions?.includes((permissionFor[key]||key)+'.'+action);
const scoped=url=>url+(url.includes('?')?'&':'?')+'agencyId='+encodeURIComponent(currentAgency?.id||'');
const agencyLogoUrl=()=>scoped('/api/v1/agency/logo');
const agencyLogoImage=(label='لوگوی شرکت',size=68)=>'<img class="sy-company-logo" src="'+esc(agencyLogoUrl())+'" alt="'+esc(label)+'" width="'+size+'" height="'+size+'">';
const write=(url,body,method='POST')=>api(scoped(url),{method,headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(body)});
const number=v=>Number(v||0).toLocaleString('fa-IR',{maximumFractionDigits:0,minimumFractionDigits:0});
const km=v=>v===null||v===undefined||v===''?'—':number(Math.round(Number(v)));
const percent=v=>Number(v||0).toLocaleString('fa-IR',{maximumFractionDigits:3});
const defaultDriverRates={supervision:3,coordination:1,overhead:7,insurance:7.8};
const driverFeeBreakdown=(gross,rates=defaultDriverRates)=>{
 const fee=Math.max(0,Number(gross)||0),amounts=Object.keys(defaultDriverRates).map(k=>Math.round(fee*(Number(rates[k])||0)/100));
 const deducted=amounts.reduce((a,b)=>a+b,0);
 return {deducted,net:Math.max(0,Math.round(fee)-deducted),rate:Object.keys(defaultDriverRates).reduce((a,k)=>a+(Number(rates[k])||0),0)};
};
const serviceAcademicMonths=(student,school)=>{const grade=String(student.grade??student.student_grade??'').trim(),type=String(school?.school_type??school?.schoolType??student.school_type??'').trim();if(/متوسطه|دبیرستان|secondary|high.school/i.test(type))return 9;if(/ابتدایی|دبستان|primary|elementary/i.test(type))return 8.5;if(/متوسطه|دبیرستان|secondary|high.school/i.test(grade))return 9;return /ابتدایی|دبستان|primary|elementary/i.test(grade)||/^[۱-۶1-6]$/.test(grade)?8.5:9;};
const persianDigits=v=>String(v??'').replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
const normalizePlate=v=>String(v??'').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).replace(/[\u200c\s]/g,'');
function iranPlate(value){
 const raw=normalizePlate(value).replace(/ایران/gu,'').replace(/[-\/ـ_]/g,'');
 let match=raw.match(/^(\d{2})([آ-یA-Za-z])(\d{3})(\d{2})$/u);
 // Sepand sometimes exports plates as 42-647ط59 (three digits before
 // the letter), while operator forms use 42ط647-59.
 if(!match){
  const old=raw.match(/^(\d{2})(\d{3})([آ-یA-Za-z])(\d{2})$/u);
  if(old)match=[old[0],old[1],old[3],old[2],old[4]];
 }
 if(!match)return '<span class="plate-raw" dir="ltr">'+esc(persianDigits(value||'—'))+'</span>';
 const [,first,letter,middle,region]=match,yellow=letter==='ت';
 return '<span class="iran-plate'+(yellow?' iran-plate-yellow':'')+'" role="img" aria-label="'+esc('پلاک '+persianDigits(first)+' '+letter+' '+persianDigits(middle)+' ایران '+persianDigits(region))+'"><span class="iran-plate-blue"><span class="iran-flag"><i></i><i></i><i></i></span><small>I.R.<br>IRAN</small></span><span class="iran-plate-main"><b>'+persianDigits(first)+'</b><b>'+esc(letter)+'</b><b>'+persianDigits(middle)+'</b></span><span class="iran-plate-region"><small>ایران</small><b>'+persianDigits(region)+'</b></span></span>';
}


function iranPlateInput(kind='filter'){
 const edit=kind==='edit',digit=(segment,len,placeholder,label)=>{
  const name=edit?'data-plate-'+segment:'name="plate'+segment[0].toUpperCase()+segment.slice(1)+'"';
  return '<input type="text" '+(edit?name:name)+' inputmode="numeric" maxlength="'+len+'" pattern="[0-9۰-۹٠-٩]{0,'+len+'}" placeholder="'+placeholder+'" aria-label="'+label+'" dir="ltr">';
 };
 const letters='آ ا ب ج د س ص ط ق ل م ن و ه ی ت ع ف ک گ پ ث ز ش'.split(' ');
 const letter='<select '+(edit?'data-plate-letter':'name="plateLetter"')+' aria-label="حرف پلاک"><option value="">حرف</option>'+letters.map(x=>'<option value="'+x+'">'+x+'</option>').join('')+'</select>';
 return '<div class="iran-plate iran-plate-input" role="group" aria-label="بخش‌های پلاک ایران">'+
  '<span class="iran-plate-blue"><span class="iran-flag"><i></i><i></i><i></i></span><small>I.R.<br>IRAN</small></span>'+
  '<span class="iran-plate-main">'+digit('first',2,'۱۲','دو رقم ابتدایی پلاک')+letter+digit('middle',3,'۱۲۳','سه رقم میانی پلاک')+'</span>'+
  '<span class="iran-plate-region"><small>ایران</small>'+digit('region',2,'۱۱','کد ایران، دو رقم')+'</span></div>';
}

const year=()=>{const p=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',timeZone:'Asia/Tehran'}).formatToParts(new Date()),y=Number(p.find(x=>x.type==='year').value),m=Number(p.find(x=>x.type==='month').value);return (m<7?y-1:y)+'/'+(m<7?y:y+1)};
const todayISO=()=>{const p=new Intl.DateTimeFormat('en-US',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'}).formatToParts(new Date());return ['year','month','day'].map(k=>p.find(x=>x.type===k).value).join('-');};
const dateText=v=>{if(!v)return '—';const raw=String(v).slice(0,10);if(/^1[34]\d{2}[-/]\d{1,2}[-/]\d{1,2}$/.test(raw))return persianDigits(raw.replace(/-/g,'/'));const d=/^\d{4}-\d{2}-\d{2}$/.test(raw)?new Date(raw+'T12:00:00Z'):new Date(v);return Number.isNaN(d.getTime())?'—':new Intl.DateTimeFormat('fa-IR',{dateStyle:'medium',timeZone:'Asia/Tehran'}).format(d);};
const person=r=>[r.first_name||r.firstName,r.last_name||r.lastName].filter(Boolean).join(' ')||r.name||r.title||r.plate||r.code||('#'+r.id);
function notify(message,error=false){let box=$('notice');if(!box){box=document.createElement('div');box.id='notice';box.setAttribute('role','status');document.body.append(box);}box.textContent=message;box.className=error?'notice error':'notice';clearTimeout(notify.timer);notify.timer=setTimeout(()=>box.remove(),6500);}
let sySidebarPhotoRequest=0;
async function syRefreshSidebarAvatar(){
 const avatar=document.getElementById('syAccountAvatar');
 if(!avatar||!session?.user?.id)return;
 const request=++sySidebarPhotoRequest;
 const userId=String(session.user.id);
 const name=[session.user.firstName,session.user.lastName].filter(Boolean).join(' ')||session.user.username||'کاربر';
 const fallback=()=>{avatar.textContent=name.trim().charAt(0)||'ک';avatar.classList.remove('sy-account-avatar-has-photo');};
 fallback();
 try{
  const profile=await api('/api/v1/auth/profile');
  if(request!==sySidebarPhotoRequest||String(session?.user?.id)!==userId||!avatar.isConnected)return;
  if(!Number(profile?.has_photo??profile?.hasPhoto))return;
  const img=document.createElement('img');
  img.className='sy-account-avatar-image';
  img.alt='';
  img.decoding='async';
  img.referrerPolicy='same-origin';
  img.onerror=()=>{if(request===sySidebarPhotoRequest)fallback();};
  img.src='/api/v1/auth/profile/photo?v='+encodeURIComponent(profile.photo_version??profile.photoVersion??Date.now());
  avatar.replaceChildren(img);
  avatar.classList.add('sy-account-avatar-has-photo');
 }catch(_){if(request===sySidebarPhotoRequest)fallback();}
}
// CSP-safe image fallback: never bind executable code in HTML attributes.
document.addEventListener('error',event=>{
 const image=event.target;
 if(image instanceof HTMLImageElement&&image.matches('[data-school-image-fallback]'))image.hidden=true;
},true);
function renderNav(){
 const accountMenu=document.getElementById('syAccountMenu');
 const ownPassword=document.getElementById('ownPassword');
 if(ownPassword)ownPassword.onclick=()=>{if(accountMenu)accountMenu.open=false;passwordDialog({id:session.user.id,username:session.user.username},false,true);};
 const editProfile=document.getElementById('syEditProfile');
 if(editProfile)editProfile.onclick=()=>{if(accountMenu)accountMenu.open=false;ownProfileDialog();};
 if(!$('navigationToggle')){const button=document.createElement('button');button.id='navigationToggle';button.type='button';button.innerHTML='<span aria-hidden="true">☰</span>';button.setAttribute('aria-label','باز کردن منوی اصلی');button.setAttribute('aria-expanded','false');document.querySelector('.header').prepend(button);const shade=document.createElement('button');shade.id='navigationShade';shade.type='button';shade.setAttribute('aria-label','بستن منوی اصلی');shade.hidden=true;document.querySelector('#app').append(shade);const toggle=open=>{document.querySelector('.sidebar').classList.toggle('nav-open',open);shade.hidden=!open;button.setAttribute('aria-expanded',String(open));};button.onclick=()=>toggle(button.getAttribute('aria-expanded')!=='true');shade.onclick=()=>toggle(false);document.addEventListener('keydown',e=>{if(e.key==='Escape'&&button.getAttribute('aria-expanded')==='true'){toggle(false);$('nav').querySelectorAll('details').forEach(d=>d.open=false);button.focus();}});}
 // Desktop compact rail: persisted per browser, independent from the mobile drawer.
 const sidebar=document.querySelector('.sy-command-sidebar');
 if(sidebar&&!document.getElementById('sySidebarCollapse')){
  const toggle=document.createElement('button');
  toggle.type='button';toggle.id='sySidebarCollapse';toggle.className='sy-sidebar-collapse-toggle';
  const apply=collapsed=>{
   sidebar.classList.toggle('sy-collapsed',collapsed);
   document.getElementById('app')?.classList.toggle('sy-sidebar-collapsed',collapsed);
   toggle.setAttribute('aria-expanded',String(!collapsed));
   toggle.setAttribute('aria-label',collapsed?'باز کردن کامل منوی کناری':'جمع کردن منوی کناری');
   toggle.title=collapsed?'باز کردن منو':'جمع کردن منو';
   toggle.innerHTML=collapsed?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg>':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg>';
  };
  let saved=false;
  try{saved=localStorage.getItem('sy-sidebar-collapsed')==='1';}catch(_){}
  apply(saved);
  toggle.addEventListener('click',()=>{
   const collapsed=!sidebar.classList.contains('sy-collapsed');
   apply(collapsed);
   if(collapsed)sidebar.querySelectorAll('.nav-group').forEach(group=>group.open=false);
   try{localStorage.setItem('sy-sidebar-collapsed',collapsed?'1':'0');}catch(_){}
  });
  sidebar.querySelector('.sy-sidebar-brand')?.append(toggle);
  sidebar.addEventListener('click',event=>{
   if(!sidebar.classList.contains('sy-collapsed')||matchMedia('(max-width:920px)').matches)return;
   if(event.target.closest('#sySidebarCollapse'))return;
   if(event.target.closest('#syAccountMenu>summary')){
    apply(false);
    try{localStorage.setItem('sy-sidebar-collapsed','0');}catch(_){}
    return;
   }
   if(event.target.closest('.nav-group>summary,.sy-nav-search')){
    event.preventDefault();
    apply(false);
    try{localStorage.setItem('sy-sidebar-collapsed','0');}catch(_){}
    if(event.target.closest('.sy-nav-search'))requestAnimationFrame(()=>document.getElementById('syNavSearch')?.focus());
   }
  },true);
 }
 const adminMode=session?.roles?.includes('super_admin')&&!session?.impersonation;
 if(adminMode)currentAgency=null;
 $('app').classList.toggle('sy-admin-mode',adminMode);
 renderAgencySelector();
 if(adminMode){
  $('agencyPickerWrap').hidden=true;
  $('nav').innerHTML=
   `<a class="nav" href="#admin-dashboard" title="داشبورد مدیریتی">${samarMenuIcon('dashboard')}<span>داشبورد مدیریتی</span></a>`+
   `<details class="nav-group"><summary title="مدیریت سامانه">${samarMenuIcon('company')}<span>مدیریت سامانه</span></summary><div class="nav-popup" aria-label="مدیریت سامانه"><a href="#admin-agencies">${samarMenuIcon('company')}<span>مدیریت شرکت‌ها</span></a><a href="#admin-users">${samarMenuIcon('students')}<span>مدیریت کاربران</span></a></div></details>`+
   `<details class="nav-group"><summary title="تنظیمات سامانه">${navIcon('settings')}<span>تنظیمات سامانه</span></summary><div class="nav-popup" aria-label="تنظیمات سامانه"><a href="#admin-platform">${navIcon('settings')}<span>کلیدهای سامانه و نقشه</span></a><a href="#admin-sms">${navIcon('messages')}<span>سرویس پیامک نگین</span></a><a href="#admin-bale">${navIcon('messages')}<span>ربات پیام‌رسان بله</span></a><a href="#admin-login">${navIcon('settings')}<span>روش‌های ورود کاربران</span></a><a href="#admin-taxi-tariffs">${navIcon('accounts')}<span>نرخ‌نامه سازمان تاکسیرانی</span></a><a href="#admin-vehicle-classes">${navIcon('vehicles')}<span>کلاس‌های تعرفه خودرو</span></a><a href="#admin-contract-templates">${navIcon('settings')}<span>مدیریت قالب قراردادها</span></a><a href="#admin-sepand">${navIcon('drivers')}<span>ورود اطلاعات سپند</span></a></div></details>`+
   `<button type="button" class="nav sy-admin-impersonation-trigger" id="syOpenImpersonation" title="ورود نیابتی به پنل شرکت">${navIcon('company')}<span>ورود نیابتی به پنل شرکت</span><span class="sy-nav-action-arrow" aria-hidden="true">↗</span></button>`+
   `<button type="button" class="nav sy-admin-impersonation-trigger" id="syOpenParentImpersonation" title="ورود نیابتی به پنل والدین">${navIcon('students')}<span>ورود نیابتی به پنل والدین</span><span class="sy-nav-action-arrow" aria-hidden="true">↗</span></button>`+
   `<button type="button" class="nav sy-admin-impersonation-trigger" id="syOpenDriverImpersonation" title="ورود نیابتی به پنل رانندگان">${navIcon('drivers')}<span>ورود نیابتی به پنل رانندگان</span><span class="sy-nav-action-arrow" aria-hidden="true">↗</span></button>`;
  [['syOpenImpersonation','company'],['syOpenParentImpersonation','parent'],['syOpenDriverImpersonation','driver']].forEach(([id,type])=>{
   $(id).onclick=()=>{document.querySelector('.sidebar')?.classList.remove('nav-open');if($('navigationShade'))$('navigationShade').hidden=true;openAdminImpersonationDialog(type);};
  });
 }else{
  $('nav').innerHTML=`<a class="nav" href="#dashboard" title="داشبورد">${samarMenuIcon('dashboard')}<span>داشبورد</span></a>`+menus.map(([icon,title,items])=>{const visible=items.filter(([key])=>can(key.split('/')[0],(key.endsWith('/new')||key.endsWith('/quick')||key.endsWith('/fees')||key==='driver-assignment')?'manage':'view'));return visible.length?`<details class="nav-group"><summary title="${esc(title)}">${samarMenuIcon(icon)}<span>${esc(title)}</span></summary><div class="nav-popup" aria-label="${esc(title)}">${visible.map(([key,text])=>`<a href="#${key}" aria-label="${esc(text)}">${samarMenuIcon(key)}<span>${esc(text)}</span></a>`).join('')}</div></details>`:''}).join('');
  document.getElementById('quickReceipt')?.remove();
  if(can('ledger','manage')){
   const receiptLink=document.createElement('a');
   receiptLink.className='nav sy-nav-receipt';receiptLink.href='#ledger';receiptLink.dataset.page='ledger';
   receiptLink.title='ثبت دریافت و پرداخت';
   receiptLink.innerHTML=navIcon('payments')+'<span>ثبت دریافت و پرداخت</span>';
   $('nav').querySelector('a[href="#dashboard"]')?.after(receiptLink);
  }
 }
 const navSearch=document.createElement('label');
 navSearch.className='sy-nav-search';navSearch.setAttribute('for','syNavSearch');
 navSearch.innerHTML='<span class="sy-nav-search-label">جست‌وجوی منو</span><span class="sy-nav-search-field"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg><input type="search" id="syNavSearch" placeholder="نام بخش را تایپ کنید…" autocomplete="off" aria-label="فیلتر زنده بخش‌های منو"><kbd>Ctrl K</kbd></span><small id="syNavSearchStatus" role="status" aria-live="polite"></small>';
 $('nav').prepend(navSearch);
 const normalizeMenuText=value=>String(value||'').replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/[آأإ]/g,'ا').replace(/[\u200c\s]+/g,' ').trim().toLocaleLowerCase('fa');
 const navSearchInput=navSearch.querySelector('input');
 if(!window.syInlineMenuShortcut){
  window.syInlineMenuShortcut=true;
  document.addEventListener('keydown',event=>{
   if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='k'&&session){
    event.preventDefault();
    const input=document.getElementById('syNavSearch'),side=document.querySelector('.sy-command-sidebar');
    if(side&&matchMedia('(max-width: 920px)').matches){
     side.classList.add('nav-open');
     document.getElementById('navigationShade')?.removeAttribute('hidden');
     document.getElementById('navigationToggle')?.setAttribute('aria-expanded','true');
    }
    if(side?.classList.contains('sy-collapsed')&&!matchMedia('(max-width:920px)').matches){
     document.getElementById('sySidebarCollapse')?.click();
    }
    input?.focus();input?.select();
   }
   if(event.key==='Escape'&&document.activeElement?.id==='syNavSearch'){
    document.activeElement.value='';
    document.activeElement.dispatchEvent(new Event('input',{bubbles:true}));
    document.activeElement.blur();
   }
  });
 }
 document.getElementById('syMenuPalette')?.remove();
 document.body.classList.remove('sy-menu-palette-open');

 navSearchInput.addEventListener('input',()=>{
  const query=normalizeMenuText(navSearchInput.value);
  let matches=0;
  $('nav').querySelectorAll('.nav-group').forEach(group=>{
   let visible=0;
   group.querySelectorAll('.nav-popup a').forEach(link=>{
    const match=!query||normalizeMenuText(link.textContent).includes(query)||normalizeMenuText(group.querySelector('summary')?.textContent).includes(query);
    link.hidden=!match;if(match){visible++;matches++;}
   });
   group.hidden=visible===0;
   if(query&&visible)group.open=true;
   if(!query)group.open=false;
  });
  $('nav').querySelectorAll(':scope > a.nav, :scope > button.nav').forEach(link=>{link.hidden=!!query&&!normalizeMenuText(link.textContent).includes(query);if(!link.hidden)matches++;});
  navSearch.querySelector('small').textContent=query?(matches?'نتایج مرتبط: '+number(matches):'بخشی با این نام پیدا نشد.'):'';
 });
  $('nav').querySelectorAll('details').forEach(d=>d.addEventListener('toggle',()=>{if(d.open){if(navSearchInput.value.trim())return;const popup=d.querySelector('.nav-popup');popup.style.removeProperty('top');$('nav').querySelectorAll('details').forEach(other=>{if(other!==d)other.open=false;});}}));
 const side=document.querySelector('.sy-command-sidebar');
 if(side){
  side.setAttribute('aria-label','منوی اصلی سرویس‌یار');
  const brand=side.querySelector('.sy-sidebar-brand-copy small');
  if(brand)brand.textContent=adminMode?'مدیریت کل سامانه':(session?.impersonation?'ورود نیابتی · '+(session.impersonation.agencyName||currentAgency?.name||'شرکت'):currentAgency?.name||'مدیریت یکپارچه سرویس مدارس');
  const displayName=[session?.user?.firstName,session?.user?.lastName].filter(Boolean).join(' ')||session?.user?.username||'کاربر سامانه';
  const sideName=document.getElementById('syAccountName');
  if(sideName)sideName.textContent=displayName;
  const avatar=document.getElementById('syAccountAvatar');
  if(avatar)void syRefreshSidebarAvatar();
  const role=document.getElementById('syAccountRole');
  if(role)role.textContent=session?.impersonation?'ورود نیابتی · از طرف مدیر کل':(session?.roles||[]).includes('super_admin')?'مدیر کل سامانه':(session?.roles||[]).some(role=>role==='agency_admin'||role==='school_company_manager')?'مدیر شرکت':'حساب کاربری';
  const returnButton=$('syAdminReturn');if(returnButton){returnButton.hidden=!session?.impersonation;returnButton.title=session?.impersonation?'بازگشت به پنل ادمین کل':'';}
  const logoutButton=$('logout');if(logoutButton)logoutButton.textContent=session?.impersonation?'بازگشت به پنل ادمین':'خروج از حساب';
  if(sideName)sideName.textContent=[session?.user?.firstName,session?.user?.lastName].filter(Boolean).join(' ')||session?.user?.username||'کاربر سامانه';
  side.querySelector('#nav')?.addEventListener('click',event=>{
   if(event.target.closest('a[href^="#"],#syOpenImpersonation'))side.classList.remove('nav-open');
  });
 }
 document.querySelector('.logo').title='سرویس‌یار';
 const workspace=document.querySelector('.main');
 workspace.classList.add('transport-main');
 if(!$('sySkipToContent')){
  const skip=document.createElement('button');
  skip.id='sySkipToContent';skip.className='sy-skip-link';skip.type='button';skip.textContent='رفتن به محتوای صفحه';
  skip.onclick=()=>$('content').focus();
  $('app').prepend(skip);
  $('content').tabIndex=-1;
  $('content').setAttribute('aria-labelledby','pageTitle');
 }
 if(!$('sessionFooter')){const footer=document.createElement('footer');footer.id='sessionFooter';document.querySelector('.main').append(footer);}
 $('sessionFooter').textContent='سال تحصیلی '+year()+' • '+(currentAgency?.name||'مدیریت سامانه')+' • '+(session?.user?.firstName||session?.user?.username||'')+' • '+new Intl.DateTimeFormat('fa-IR',{dateStyle:'full',timeZone:'Asia/Tehran'}).format(new Date());

}
function render(){
 if(!session)return;
 if(!document.getElementById('syMenuPalette')?.hidden)document.querySelector('#syMenuPalette [data-menu-close]')?.click();
 if($('modal')?.firstElementChild)closeDialog();
 disposeMaps();$('dashboardBackdrop')?.remove();$('content').classList.remove('sy-super-admin-workspace');
 const token=++pageToken,[raw,action,recordId]=location.hash.slice(1).split('/');
 const isAdmin=session.roles?.includes('super_admin')&&!session.impersonation;
 if(isAdmin)currentAgency=null;
 const adminTitles={
  'admin-dashboard':'داشبورد مدیریتی',
  'admin-agencies':'مدیریت شرکت‌ها',
  'admin-users':'مدیریت کاربران',
  'admin-platform':'کلیدهای سامانه و نقشه',
  'admin-sms':'تنظیمات پیامک نگین',
  'admin-bale':'تنظیمات ربات بله',
  'admin-login':'روش‌های ورود کاربران',
  'admin-taxi-tariffs':'نرخ‌نامه سازمان تاکسیرانی',
  'admin-vehicle-classes':'مدیریت کلاس‌های تعرفه خودرو',
  'admin-contract-templates':'مدیریت قالب قراردادها',
  'admin-sepand':'ورود اطلاعات سپند'
 };
 Object.assign(titles,adminTitles);
 if(isAdmin){
  let key=raw||'admin-dashboard';
  if(!Object.prototype.hasOwnProperty.call(adminTitles,key)){
   key='admin-dashboard';
   history.replaceState(history.state,'',location.pathname+location.search+'#admin-dashboard');
  }
  document.querySelectorAll('.nav-group').forEach(d=>{const active=!!d.querySelector('a[href="#'+key+'"]');d.classList.toggle('current-group',active);if(active)d.open=true;else if(!$('syNavSearch')?.value.trim())d.open=false;});
  $('nav').querySelectorAll('a[href^="#"]').forEach(link=>{const active=link.getAttribute('href')==='#'+key;link.classList.toggle('active',active);if(active)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});
  $('pageTitle').textContent=adminTitles[key];$('app').classList.toggle('dashboard-active',key==='admin-dashboard');$('content').classList.toggle('dashboard-content',key==='admin-dashboard');
  $('content').innerHTML='<div class="loading sy-page-state" role="status">در حال آماده‌سازی پنل مدیریت کل…</div>';
  document.querySelector('.sidebar').classList.remove('nav-open');if($('navigationShade'))$('navigationShade').hidden=true;if($('navigationToggle'))$('navigationToggle').setAttribute('aria-expanded','false');
  const task=key==='admin-dashboard'?adminDashboardPage(token):adminSectionPage(key,token);
  Promise.resolve(task).catch(error=>{if(token===pageToken)$('content').innerHTML='<div class="error sy-page-state" role="alert">'+esc(error.message||'بارگذاری بخش مدیریت ناموفق بود.')+'</div>';});
  return;
 }
 let key=raw||'dashboard';if(key==='admin')key='dashboard';if(!titles[key])key='dashboard';
 document.querySelectorAll('.nav-group').forEach(d=>{if(!$('syNavSearch')?.value.trim())d.open=false;});
 $('pageTitle').textContent=titles[key];$('app').classList.toggle('dashboard-active',key==='dashboard');$('content').classList.toggle('dashboard-content',key==='dashboard');
 $('content').innerHTML='<div class="loading sy-page-state" role="status">در حال دریافت اطلاعات…</div>';
 document.querySelector('.sidebar').classList.remove('nav-open');if($('navigationShade'))$('navigationShade').hidden=true;if($('navigationToggle'))$('navigationToggle').setAttribute('aria-expanded','false');
 $('nav').querySelectorAll('a').forEach(a=>{const active=a.getAttribute('href')==='#'+(raw||'dashboard')+(action?'/'+action:'');a.classList.toggle('active',active);if(active)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');});
 $('nav').querySelectorAll('details').forEach(d=>{const active=!!d.querySelector('[aria-current=page]');d.classList.toggle('current-group',active);if(active)d.open=true;});
 if(key!=='companies'&&!currentAgency){$('content').innerHTML='<div class="empty sy-page-state" role="status">ابتدا شرکت فعال را انتخاب کنید.</div>';return;}
 if(key!=='companies'&&!can(key)){$('content').innerHTML='<div class="error sy-page-state" role="alert">برای مشاهده این بخش مجوز ندارید.</div>';return;}
 const task=key==='representatives'?representativesPage(token):key==='financial-reconciliation'?financialReconciliationPage(token):key==='debtors'?studentDebtorsPage(token):key==='portal-vehicle-requests'?portalVehicleRequestsPage(token):key==='services'&&action==='new'?newServicePage(token,recordId):key==='installments'?installmentPage():key==='daily-schedule'?dailySchedulePage(token):key==='driver-assignment'?driverAssignmentPage(token):key==='accounting-documents'?(action==='statement'?accountStatementPage(token):action==='quick'?quickJournalPage(token):action==='defaults'?financialAccountsPage(token):action==='cheques'?chequePage(token):journalPage(token)):key==='dashboard'?dashboard(token):key==='companies'?companiesPage():key==='contracts'?contractsPage(token):key==='settings'?settingsPage():key==='reports'?reportsPage(token):key==='import'?importPage(token):key==='bale-payments'?balePaymentsPage(token):key==='school-statement'?schoolStatementPage(token):['payroll','finance-report','distances','monitoring','grouping','school-report'].includes(key)?operationPage(key,token):resourcePage(key==='previous-students'?'students':key==='agreements'?'services':key,token,{previous:key==='previous-students',agreements:key==='agreements'});
 Promise.resolve(task).then(()=>{if(token!==pageToken)return;if(action==='new'&&resources[key]&&!['accounting-documents','services','ledger','contracts'].includes(key))formResource(key,null);if(action==='fees')bulkFeeDialog();}).catch(e=>{if(token===pageToken)$('content').innerHTML='<div class="error">'+esc(e.message)+'</div>';});
}
function accountingTabs(active){const selected=active===true?'statement':active||'journal';return `<nav class="accounting-tabs" aria-label="حسابداری">${[['journal','ثبت سند',''],['statement','گزارش حساب','/statement'],['defaults','حساب‌های پیش‌فرض','/defaults'],['cheques','چک‌های دریافتی','/cheques'],...(can('accounting-documents','manage')?[['quick','سند سریع','/quick']]:[])].map(([key,title,path])=>`<a href="#accounting-documents${path}" ${selected===key?'aria-current="page"':''}>${title}</a>`).join('')}</nav>`;}
const chequeStatuses={received:'دریافت‌شده',collected:'وصول‌شده',bounced:'برگشتی',cancelled:'ابطال‌شده',saved:'ثبت / ویرایش'};
Object.assign(labels,{branchName:'شعبه',serialNo:'شماره / سریال چک',receivedFrom:'دریافت از',accountId:'سرفصل حساب',receivedDate:'تاریخ دریافت',dueDate:'سررسید',amount:'مبلغ (ریال)',description:'بابت / توضیحات'});
async function chequePage(token=pageToken,filters={},page=1){
 const request=++chequeSearchSequence;
 const query=new URLSearchParams({...filters,page}),d=await api(scoped('/api/v1/accounting/cheques?'+query));if(token!==pageToken||request!==chequeSearchSequence)return;
 $('content').innerHTML=`<section class="panel sy-cheque-panel">${accountingTabs('cheques')}<div class="page-intro sy-cheque-intro"><div><h2>چک‌های دریافتی</h2><p>ثبت مشخصات، سررسید و سابقه وصول یا برگشت چک‌ها. هنگام وصول، با انتخاب حساب بانک، سند حسابداری تراز نیز خودکار ثبت و تأیید می‌شود.</p></div>${can('accounting-documents','manage')?'<button class="primary" id="newCheque">ثبت چک دریافتی</button>':''}</div><form id="chequeFilters" class="filters sy-cheque-filters" aria-label="جست‌وجوی چک‌های دریافتی"><label>شماره، بانک یا نام پرداخت‌کننده<input name="search" type="search" autocomplete="off" placeholder="شماره چک، بانک، نام پرداخت‌کننده" value="${esc(filters.search||'')}" maxlength="200"></label><label>وضعیت<select name="status"><option value="">همه وضعیت‌ها</option>${Object.entries(chequeStatuses).filter(([k])=>k!=='saved').map(([k,v])=>`<option value="${k}" ${filters.status===k?'selected':''}>${v}</option>`).join('')}</select></label><label>سررسید از<input name="from" type="date" value="${esc(filters.from||'')}"></label><label>سررسید تا<input name="to" type="date" value="${esc(filters.to||'')}"></label><button class="primary">نمایش نتایج</button><button type="reset" id="chequeClearFilters">پاک‌کردن فیلترها</button></form><div class="sy-cheque-metrics"><div><span>تعداد چک‌ها در بازه و فیلتر</span><strong>${number(d.pagination.total)}</strong></div><div><span>مجموع مبلغ چک‌های فیلترشده</span><strong>${number(d.amount)} ریال</strong></div><div><span>فیلتر وضعیت</span><strong>${esc(chequeStatuses[filters.status]||'همه وضعیت‌ها')}</strong></div></div>${can('reports')?'<button id="chequesExport">خروجی اکسل</button>':''}<div class="table-wrap sy-cheque-table-wrap"><table class="sy-cheque-table"><thead><tr><th scope="col">شماره چک</th><th scope="col">بانک / شعبه</th><th scope="col">دریافت از</th><th scope="col">سررسید</th><th scope="col">مبلغ · ریال</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead><tbody>${d.items.map(r=>`<tr><td data-label="شماره چک"><strong>${esc(r.serial_no)}</strong></td><td data-label="بانک / شعبه">${esc(r.bank_name+' / '+(r.branch_name||'—'))}</td><td data-label="دریافت از">${esc(r.received_from)}</td><td data-label="سررسید">${dateText(r.due_date)}</td><td data-label="مبلغ" class="sy-financial-number">${number(r.amount)}</td><td data-label="وضعیت"><span class="cheque-state ${r.status}">${chequeStatuses[r.status]}</span></td><td data-label="عملیات" class="row-actions"><button data-cheque-view="${r.id}">جزئیات و سوابق</button>${r.status==='received'&&can('accounting-documents','manage')?`${!r.receipt_document_id?'<button data-cheque-edit="'+r.id+'">ویرایش</button>':''}<button data-cheque-action="${r.id}">ثبت عملیات</button>`:''}</td></tr>`).join('')||'<tr><td colspan="7" class="empty">چکی با این شرایط پیدا نشد.</td></tr>'}</tbody></table></div><div class="pager sy-cheque-pager"><button id="chequePrev" ${page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))}</span><button id="chequeNext" ${page>=d.pagination.pages?'disabled':''}>بعدی</button></div></section>`;
 const refresh=()=>chequePage(token,filters,page);
 const chequeForm=$('chequeFilters');
 const runFilters=()=>{
  const inputs=Object.fromEntries(new FormData(chequeForm));
  if(inputs.from&&inputs.to&&inputs.to<inputs.from){notify('سررسید پایان نباید قبل از تاریخ شروع باشد.',true);chequeForm.elements.to.focus();return;}
  chequePage(token,inputs,1).catch(e=>notify(e.message,true));
 };
 chequeForm.onsubmit=e=>{e.preventDefault();runFilters();};
 chequeForm.addEventListener('reset',e=>{e.preventDefault();chequePage(token,{},1).catch(err=>notify(err.message,true));});
 let debounce;chequeForm.elements.search.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(runFilters,450);});$('chequePrev').onclick=()=>chequePage(token,filters,page-1).catch(e=>notify(e.message,true));$('chequeNext').onclick=()=>chequePage(token,filters,page+1).catch(e=>notify(e.message,true));$('newCheque')?.addEventListener('click',()=>chequeEditor(null,token,refresh).catch(e=>notify(e.message,true)));
 $('chequesExport')?.addEventListener('click',()=>downloadFile('/api/v1/reports/export?'+new URLSearchParams({resource:'cheques',search:filters.search||'',status:filters.status||'',dateFrom:filters.from||'',dateTo:filters.to||''}),'چک‌های-دریافتی.xlsx'));
 $('content').querySelectorAll('[data-cheque-edit]').forEach(b=>b.onclick=()=>chequeEditor(Number(b.dataset.chequeEdit),token,refresh).catch(e=>notify(e.message,true)));
 $('content').querySelectorAll('[data-cheque-view]').forEach(b=>b.onclick=async()=>{try{const r=await api(scoped('/api/v1/accounting/cheques/'+b.dataset.chequeView));if(token!==pageToken)return;dialog('چک '+r.serial_no,`<dl class="detail-grid">${[['بانک',r.bank_name],['شعبه',r.branch_name],['شماره حساب',r.bank_account],['دریافت از',r.received_from],['حساب',r.account_code+' · '+r.account_title],['مبلغ',number(r.amount)+' ریال'],['دریافت',dateText(r.received_date)],['سررسید',dateText(r.due_date)],['وضعیت',chequeStatuses[r.status]],['سند ثبت دریافت',r.receipt_document_id?'سند شماره '+number(r.receipt_document_id):'ثبت نشده'],['سند معکوس',r.reversal_document_id?'سند شماره '+number(r.reversal_document_id):'ثبت نشده'],['سند وصول',r.collection_document_id?'سند شماره '+number(r.collection_document_id):'ثبت نشده'],['بابت',r.description]].map(([k,v])=>`<div><dt>${k}</dt><dd>${esc(v||'—')}</dd></div>`).join('')}</dl><h4>سابقه عملیات</h4>${simpleTable(['عملیات','تاریخ','کاربر','شرح'],r.events.map(e=>[chequeStatuses[e.event_type]||e.event_type,dateText(e.event_date),[e.first_name,e.last_name].filter(Boolean).join(' '),e.note||'—']))}<div class="sy-cheque-detail-journals">${[['سند دریافت',r.receipt_document_id],['سند وصول',r.collection_document_id],['سند معکوس',r.reversal_document_id]].filter(([,id])=>id).map(([name,id])=>'<button type="button" data-cheque-journal="'+Number(id)+'">مشاهده '+name+'</button>').join('')}</div>`);$('modal').querySelectorAll('[data-cheque-journal]').forEach(button=>button.onclick=()=>{const journalId=Number(button.dataset.chequeJournal);closeDialog();journalEditor(journalId,token,()=>chequePage(token,filters,page)).catch(err=>notify(err.message,true));});}catch(e){notify(e.message,true);}});
 $('content').querySelectorAll('[data-cheque-action]').forEach(b=>b.onclick=async()=>{let accounts;try{accounts=await lookup('accounts');}catch(e){notify(e.message,true);return;}if(token!==pageToken)return;const row=d.items.find(r=>String(r.id)===b.dataset.chequeAction);dialog('ثبت عملیات چک '+row.serial_no,`<div class="sy-cheque-action-summary"><strong>چک ${esc(row.serial_no)} · ${number(row.amount)} ریال</strong><span>${esc(row.bank_name)} · دریافت از ${esc(row.received_from)} · سررسید ${dateText(row.due_date)}</span></div><p class="sy-cheque-action-warning">این عملیات نهایی است؛ پس از ثبت، وضعیت چک قابل تغییر نیست. در صورت ثبت برگشت یا ابطال، سند معکوس برای سند دریافت قبلی ایجاد می‌شود.</p><form id="chequeActionForm" class="sy-cheque-action-form"><label>عملیات<select name="status" required><option value="">انتخاب کنید</option><option value="collected">وصول</option><option value="bounced">برگشت</option><option value="cancelled">ابطال</option></select></label><label>تاریخ عملیات<input name="date" type="date" required value="${todayISO()}"></label><label>حساب بانک برای سند وصول<select name="bankAccountId"><option value="">فقط ثبت وضعیت، بدون سند حسابداری</option>${accounts.filter(a=>Number(a.id)!==Number(row.account_id)).map(a=>`<option value="${a.id}">${esc(a.code+' · '+a.title)}</option>`).join('')}</select></label><p class="sy-cheque-action-help" id="chequeBankHelp">برای وصول با سند حسابداری، یک حساب بانک فعال انتخاب کنید. در غیر این صورت فقط وضعیت چک ثبت می‌شود.</p><label>شرح و پیگیری<textarea name="note" maxlength="1000" required placeholder="علت و توضیحات عملیات"></textarea></label><p id="chequeActionHint" role="status" aria-live="polite"></p><div class="sy-cheque-action-footer"><button class="primary" type="submit">تأیید و ثبت عملیات</button><button type="button" id="chequeActionCancel">انصراف</button></div></form>`);const actionForm=$('chequeActionForm'),bank=actionForm.elements.bankAccountId,date=actionForm.elements.date;
 date.min=row.received_date;
 if(date.value<date.min)date.value=date.min;
 const actionSummary=()=>{bank.disabled=actionForm.elements.status.value!=='collected';if(bank.disabled)bank.value='';const labels={collected:'وصول',bounced:'برگشت',cancelled:'ابطال'};$('chequeActionHint').textContent=labels[actionForm.elements.status.value]?'عملیات انتخاب‌شده: '+labels[actionForm.elements.status.value]+' · تاریخ: '+dateText(date.value):'ابتدا نوع عملیات را انتخاب کنید.';};
 actionForm.elements.status.onchange=actionSummary;
 date.onchange=actionSummary;
 $('chequeActionCancel').onclick=closeDialog;
 actionSummary();
 actionForm.onsubmit=async e=>{e.preventDefault();
  if(date.value<row.received_date){date.setCustomValidity('تاریخ عملیات نباید قبل از تاریخ دریافت چک باشد.');date.reportValidity();date.setCustomValidity('');return;}
  const body=Object.fromEntries(new FormData(e.target));
  const labels={collected:'وصول',bounced:'برگشت',cancelled:'ابطال'};
  const effect=body.status==='collected'?(body.bankAccountId?'سند حسابداری وصول به‌صورت خودکار ثبت و تأیید می‌شود.':'هیچ سند حسابداری وصول صادر نمی‌شود.'):row.receipt_document_id?'اگر سند دریافت وجود داشته باشد، سند معکوس صادر و تأیید می‌شود.':'برای این چک سند دریافت قبلی وجود ندارد.';
  if(!confirm('آیا '+(labels[body.status]||'عملیات')+' چک '+row.serial_no+' به مبلغ '+number(row.amount)+' ریال نهایی شود؟\n'+effect))return;
  const button=e.target.querySelector('[type=submit]');button.disabled=true;try{await write('/api/v1/accounting/cheques/'+row.id+'/transition',body);closeDialog();notify('عملیات چک ثبت شد.');await refresh();}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;$('formError').focus();button.disabled=false;}};});
}
async function chequeEditor(id,token,refresh){
 const row=id?await api(scoped('/api/v1/accounting/cheques/'+id)):{received_date:todayISO(),due_date:todayISO()},defaults=await api(scoped('/api/v1/accounting/defaults')),accounts=[];let page=1;while(true){const d=await api(scoped('/api/v1/accounts?active=1&limit=200&page='+page++));if(token!==pageToken)return;accounts.push(...d.items);if(page>d.pagination.pages)break;}
 labels.counterAccountId='حساب مقابل سند دریافت (اختیاری)';const fields=['bankName','branchName','serialNo','bankAccount','receivedFrom','accountId','amount','receivedDate','dueDate','description',...(!id?['counterAccountId']:[])];
 dialog(id?'ویرایش چک دریافتی':'ثبت چک دریافتی',`<form id="editForm" class="sy-cheque-editor"><p class="sy-cheque-editor-intro">مشخصات چک را مطابق برگ دریافتی وارد کنید. در صورت ایجاد سند دریافت، ویرایش مشخصات چک قفل می‌شود.</p><div class="form-grid">${fields.map(key=>{const value=val(row,key)||(key==='accountId'?defaults.chequeReceivable||'':'');return `<label>${labels[key]}${['bankName','serialNo','receivedFrom','accountId','amount','receivedDate','dueDate'].includes(key)?' *':''}${['accountId','counterAccountId'].includes(key)?`<select name="${key}" ${key==='accountId'?'required':''}><option value="">انتخاب حساب</option>${accounts.map(a=>`<option value="${a.id}" ${String(value)===String(a.id)?'selected':''}>${esc(a.code+' · '+a.title)}</option>`).join('')}</select>`:key==='description'?`<textarea name="${key}" maxlength="1000">${esc(value)}</textarea>`:`<input name="${key}" value="${esc(value)}" ${['receivedDate','dueDate'].includes(key)?'type="date" required':key==='amount'?'type="text" inputmode="decimal" autocomplete="off" data-cheque-money dir="ltr" required aria-describedby="chequeAmountHelp"':['bankName','serialNo','receivedFrom'].includes(key)?'required maxlength="100"':'maxlength="100"'}>`}</label>`;}).join('')}</div><p id="chequeAmountHelp" class="sy-cheque-editor-help">مبلغ به ریال، با حداکثر دو رقم اعشار؛ برای مقادیر صحیح، اعشار لازم نیست.</p><div id="chequeDateFeedback" role="status" aria-live="polite"></div><div class="sy-cheque-editor-actions"><button type="submit" id="saveRecord" class="primary">ذخیره چک</button><button type="button" data-cancel-cheque>انصراف</button></div></form>`);const form=$('editForm');
 const money=form.querySelector('[data-cheque-money]'),from=form.elements.receivedDate,to=form.elements.dueDate;
 const cleanDigits=value=>String(value??'').trim().replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
 const amountValue=()=>{
  const raw=cleanDigits(money.value);
  const parts=raw.split(/[٫.]/);
  if(parts.length>2||!/^(?:[0-9]{1,12}|[0-9]{1,3}(?:[٬,][0-9]{3})+)$/.test(parts[0])||(parts.length===2&&!/^[0-9]{1,2}$/.test(parts[1])))return null;
  const digits=parts[0].replace(/[٬,]/g,'');
  if(!Number(digits)&&(!parts[1]||!Number(parts[1])))return null;
  return digits+(parts.length===2?'.'+parts[1]:'');
 };
 const validate=()=>{
  const numeric=amountValue();
  money.setCustomValidity(numeric?'':'مبلغ چک باید مثبت و دارای حداکثر دو رقم اعشار باشد.');
  to.setCustomValidity(from.value&&to.value&&to.value<from.value?'سررسید چک نمی‌تواند قبل از تاریخ دریافت باشد.':'');
  $('chequeDateFeedback').textContent=from.value&&to.value?'دریافت: '+dateText(from.value)+' · سررسید: '+dateText(to.value):'تاریخ دریافت و سررسید را انتخاب کنید.';
  $('chequeDateFeedback').classList.toggle('is-invalid',Boolean(to.validationMessage));
  return Boolean(numeric)&&!to.validationMessage;
 };
 money.addEventListener('input',validate);
 for(const input of [from,to]){input.addEventListener('input',validate);input.addEventListener('change',validate);}
 money.addEventListener('blur',()=>{
  const normalized=amountValue();
  if(normalized){
   const [whole,decimal]=normalized.split('.');
   money.value=persianDigits(whole.replace(/\B(?=(\d{3})+(?!\d))/g,'٬')+(decimal===undefined?'':'٫'+decimal));
  }
 });
 form.querySelector('[data-cancel-cheque]').onclick=closeDialog;
 resourceWizard(form,'cheques');validate();
 form.onsubmit=async e=>{e.preventDefault();if(!validate()){form.reportValidity();return;}const button=$('saveRecord');button.disabled=true;try{
  const body=Object.fromEntries(new FormData(form));body.amount=amountValue();
  await write('/api/v1/accounting/cheques'+(id?'/'+id:''),body,id?'PUT':'POST');closeDialog();notify('چک دریافتی ذخیره شد.');await refresh();}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;$('formError').focus();button.disabled=false;}};
}
async function financialAccountsPage(token=pageToken){
 const defaults=await api(scoped('/api/v1/accounting/defaults')),accounts=[];let page=1;while(true){const d=await api(scoped('/api/v1/accounts?active=1&limit=200&page='+page++));if(token!==pageToken)return;accounts.push(...d.items);if(page>d.pagination.pages)break;}
 const names={serviceIncome:'حق سرویس',driverSalary:'حقوق راننده',discount:'تخفیف سرویس',penalty:'جریمه',bonus:'پاداش',cash:'صندوق',bank:'بانک',absence:'غیبت',wallet:'کیف پول',chequeReceivable:'چک‌های دریافتی'};
 $('content').innerHTML=`<section class="panel">${accountingTabs('defaults')}<div class="page-intro"><div><h2>حساب‌های پیش‌فرض</h2><p>برای عملیات پرتکرار حساب تعیین کنید. انتخاب نهایی هر سند را پیش از ثبت بررسی کنید.</p></div><a href="#accounts" class="secondary-link">مدیریت حساب‌ها</a></div>${!accounts.length?'<p class="empty">ابتدا از «مدیریت حساب‌ها» حساب‌های شرکت را ثبت کنید.</p>':''}<form id="financialAccountsForm"><fieldset class="form-grid" ${can('accounting-documents','manage')?'':'disabled'}>${Object.entries(names).map(([key,title])=>{const selected=defaults[key],missing=selected&&!accounts.some(a=>String(a.id)===String(selected));return `<label>${title}<select name="${key}"><option value="">بدون پیش‌فرض</option>${missing?`<option value="${esc(selected)}" selected disabled>حساب غیرفعال · انتخاب جدید لازم است</option>`:''}${accounts.map(a=>`<option value="${a.id}" ${String(a.id)===String(selected)?'selected':''}>${esc(a.code+' · '+a.title)}</option>`).join('')}</select></label>`;}).join('')}</fieldset>${can('accounting-documents','manage')?'<div class="form-actions"><button class="primary">ذخیره حساب‌های پیش‌فرض</button><span id="defaultsStatus" role="status"></span></div>':''}</form></section>`;
 $('financialAccountsForm').onsubmit=async e=>{e.preventDefault();const button=e.currentTarget.querySelector('button');button.disabled=true;$('defaultsStatus').textContent='در حال ذخیره…';try{await write('/api/v1/accounting/defaults',Object.fromEntries(new FormData(e.currentTarget)),'PUT');$('defaultsStatus').textContent='حساب‌های پیش‌فرض ذخیره شدند.';}catch(err){$('defaultsStatus').textContent=err.message;notify(err.message,true);}finally{button.disabled=false;}};
}
async function quickJournalPage(token=pageToken){
 if(!can('accounting-documents','manage'))throw new Error('برای ثبت سند مجوز ندارید.');
 const accounts=[];let ap=1;while(true){const d=await api(scoped('/api/v1/accounts?active=1&limit=200&page='+ap++));if(token!==pageToken)return;accounts.push(...d.items);if(ap>d.pagination.pages)break;}
 const defaults=await api(scoped('/api/v1/accounting/defaults'));if(token!==pageToken)return;
 const options='<option value="">انتخاب حساب</option>'+accounts.map(a=>`<option value="${a.id}">${esc(a.code+' · '+a.title)}</option>`).join('');
 $('content').innerHTML=`<section class="panel sy-quick-journal">${accountingTabs('quick')}<header class="sy-quick-header"><div><span class="sy-operation-kicker">ثبت گروهی اسناد مالی</span><h2>سند سریع</h2><p>ابتدا اشخاص را جست‌وجو کنید، سپس مشخصات سند و ردیف‌های موردنظر را تکمیل کنید. ثبت نهایی پس از بررسی پیش‌نویس انجام می‌شود.</p></div><span class="sy-quick-status">پیش‌نویس</span></header><section class="sy-quick-section" aria-labelledby="quickPeopleTitle"><div class="sy-quick-section-head"><span class="sy-quick-step">۱</span><div><h3 id="quickPeopleTitle">جست‌وجوی اشخاص</h3><p>دانش‌آموزان یا رانندگان فعال را پیدا کنید.</p></div></div><form id="quickPartyFilter" class="filters sy-quick-search"><label>نوع شخص<select name="partyType">${['students','drivers'].filter(k=>can(k)).map(k=>`<option value="${k}">${k==='students'?'دانش‌آموز':'راننده'}</option>`).join('')}</select></label><label>جست‌وجوی نام<input name="search" type="search" placeholder="نام یا نام خانوادگی" autocomplete="off"></label><button type="submit" class="primary">نمایش افراد</button></form></section><form id="quickJournalForm"><section class="sy-quick-section" aria-labelledby="quickDocumentTitle"><div class="sy-quick-section-head"><span class="sy-quick-step">۲</span><div><h3 id="quickDocumentTitle">مشخصات سند</h3><p>حساب مقابل، تاریخ و جهت ثبت را تعیین کنید.</p></div></div><div class="form-grid sy-quick-fields"><label>شماره سند<input name="documentNo" required maxlength="100"></label><label>تاریخ سند<input type="date" name="documentDate" value="${todayISO()}" required></label><label>طرف دوم حساب<select name="counterAccountId" required>${options}</select></label><label>نوع ردیف اشخاص<select name="side"><option value="debit">بدهکار</option><option value="credit">بستانکار</option></select></label><label>کلمه پیگیری<input name="trackingNo" maxlength="100"></label><label>شرح سند<input name="description" maxlength="1000" value="سند سریع"></label></div></section><section class="sy-quick-section" aria-labelledby="quickRowsTitle"><div class="sy-quick-section-head"><span class="sy-quick-step">۳</span><div><h3 id="quickRowsTitle">انتخاب ردیف‌ها و مبلغ</h3><p>حساب هر شخص و مبلغ را وارد کنید. انتخاب‌ها فقط مربوط به صفحه فعلی فهرست هستند.</p></div></div><div id="quickPartyRows" aria-live="polite"><p class="sy-quick-placeholder">برای نمایش فهرست، نوع شخص را انتخاب و «نمایش افراد» را بزنید.</p></div></section><footer class="sy-quick-footer"><div><span class="sy-quick-summary-label">خلاصه سند</span><p id="quickJournalTotal" role="status" aria-live="polite">جمع انتخاب‌شده: ۰ ریال</p><p id="quickJournalHint" class="sy-quick-hint">ابتدا اشخاص و حساب هر ردیف را انتخاب کنید.</p></div><button id="quickJournalSave" class="primary" disabled>ذخیره پیش‌نویس</button></footer></form></section>`;
 const form=$('quickJournalForm');let loadedType='',seq=0,loading=false,filters={};
 const total=()=>{let amount=0,count=0;form.querySelectorAll('[data-quick-party]').forEach(row=>{const chosen=row.querySelector('[name=chosen]').checked;row.querySelectorAll('input:not([name=chosen]),select').forEach(i=>i.disabled=!chosen);if(chosen){count++;amount+=Number(row.querySelector('[name=amount]').value||0);}});$('quickJournalTotal').textContent='جمع انتخاب‌شده: '+number(amount)+' ریال · '+number(count)+' نفر';const selected=[...form.querySelectorAll('[data-quick-party]')].filter(r=>r.querySelector('[name=chosen]').checked);const valid=selected.length>0&&selected.every(r=>{const value=Number(r.querySelector('[name=amount]').value);return r.querySelector('[name=accountId]').value&&Number.isFinite(value)&&value>0&&Math.round(value*100)===value*100;});$('quickJournalSave').disabled=loading||!valid;const hint=$('quickJournalHint');if(hint)hint.textContent=loading?'در حال دریافت اطلاعات…':!selected.length?'برای ثبت، دست‌کم یک شخص را انتخاب کنید.':!valid?'حساب هر شخص را انتخاب و مبلغ مثبت و معتبر وارد کنید.':'آماده ذخیره پیش‌نویس؛ سند پس از بازبینی قابل تأیید خواهد بود.';};
 const load=async(page=1)=>{const current=++seq;loading=true;total();$('quickPartyRows').innerHTML='<p class="loading">در حال دریافت افراد…</p>';try{const query=new URLSearchParams({search:filters.search||'',active:1,limit:25,page});const d=await api(scoped('/api/v1/'+filters.partyType+'?'+query));if(token!==pageToken||current!==seq)return;loadedType=filters.partyType;if(!form.elements.counterAccountId.value){const suggested=defaults[loadedType==='students'?'serviceIncome':'driverSalary'];if(accounts.some(a=>String(a.id)===String(suggested)))form.elements.counterAccountId.value=suggested;}
 $('quickPartyRows').innerHTML=`<p>انتخاب‌ها مربوط به همین صفحه هستند. مبلغ پیشنهادی دانش‌آموز، هزینه ماهیانه ثبت‌شده است؛ حساب هر شخص را خودتان انتخاب کنید.</p><div class="table-wrap sy-quick-table-wrap" tabindex="0" role="region" aria-label="جدول انتخاب اشخاص"><table class="sy-quick-table"><thead><tr><th scope="col">انتخاب</th><th scope="col">نام</th><th scope="col">حساب شخص</th><th scope="col">شرح ردیف</th><th scope="col">مبلغ · ریال</th></tr></thead><tbody>${d.items.map(r=>`<tr data-quick-party="${r.id}"><td data-label="انتخاب"><input type="checkbox" name="chosen" aria-label="انتخاب ${esc(person(r))}"></td><td data-label="نام">${esc(person(r))}</td><td data-label="حساب شخص"><select name="accountId" aria-label="حساب ${esc(person(r))}" required disabled>${options}</select></td><td data-label="شرح ردیف"><input name="rowDescription" aria-label="شرح ${esc(person(r))}" maxlength="700" disabled></td><td data-label="مبلغ · ریال"><input name="amount" aria-label="مبلغ ${esc(person(r))}" type="number" min="0.01" max="999999999999.99" step="0.01" required value="${esc(loadedType==='students'?r.monthly_fee||0:0)}" disabled></td></tr>`).join('')||'<tr><td colspan="5" class="empty">موردی یافت نشد.</td></tr>'}</tbody></table></div><div class="pager"><button type="button" id="quickPrev" ${page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))}</span><button type="button" id="quickNext" ${page>=d.pagination.pages?'disabled':''}>بعدی</button></div>`;
 $('quickPrev').onclick=()=>load(page-1);$('quickNext').onclick=()=>load(page+1);$('quickPartyRows').oninput=total;$('quickPartyRows').onchange=total;
 }catch(e){if(token===pageToken&&current===seq)$('quickPartyRows').innerHTML='<p class="error">'+esc(e.message)+'</p>';}finally{if(token===pageToken&&current===seq){loading=false;total();}}};
 $('quickPartyFilter').onsubmit=e=>{e.preventDefault();filters=Object.fromEntries(new FormData(e.currentTarget));load();};
 form.onsubmit=async e=>{e.preventDefault();if(loading)return;const rows=[...form.querySelectorAll('[data-quick-party]')].filter(r=>r.querySelector('[name=chosen]').checked).map(r=>({partyId:r.dataset.quickParty,accountId:r.querySelector('[name=accountId]').value,amount:r.querySelector('[name=amount]').value,description:r.querySelector('[name=rowDescription]').value}));if(!rows.length)return;if($('quickJournalSave').disabled){notify('برای همه افراد انتخاب‌شده، حساب معتبر و مبلغ بزرگ‌تر از صفر وارد کنید.',true);return;}loading=true;total();try{const body=Object.fromEntries(new FormData(form));body.partyType=loadedType;body.rows=rows;const doc=await write('/api/v1/accounting/quick',body);notify('پیش‌نویس سند سریع ذخیره شد.');if(token===pageToken)await journalEditor(doc.id,token);}catch(err){notify(err.message,true);}finally{if(token===pageToken&&$('quickJournalSave')){loading=false;total();}}};
}

async function accountStatementPage(token=pageToken,initial=null,initialPage=1){
 const accounts=[];let page=1,more=true;while(more){const d=await api(scoped('/api/v1/accounts?limit=200&page='+page++));if(token!==pageToken)return;accounts.push(...d.items);more=page<=d.pagination.pages;}
 $('content').innerHTML=`<section class="panel sy-account-statement">${accountingTabs(true)}<header class="sy-account-statement-heading"><span class="sy-operation-kicker">دفتر گردش حساب‌ها</span><h2>گزارش حساب</h2><p>گردش اسناد تأییدشده، مانده ابتدای دوره و مانده پایان دوره را بر اساس سرفصل حساب بررسی کنید. مانده مثبت بدهکار و مانده منفی بستانکار است.</p></header><form id="statementForm" class="sy-account-statement-filters" aria-label="فیلتر گزارش گردش حساب"><label>جستجوی نام و کد حساب<input id="statementSearch" type="search" autocomplete="off"></label><label>حساب<select name="accountId" required><option value="">انتخاب حساب</option>${accounts.map(a=>`<option value="${esc(a.id)}">${esc(a.code+' · '+a.title+(Number(a.is_active)?'':' (غیرفعال)'))}</option>`).join('')}</select></label><label>از تاریخ<input type="date" name="from"></label><label>تا تاریخ<input type="date" name="to"></label><button class="primary" type="submit">نمایش گردش حساب</button><button type="button" id="statementClear">پاک‌کردن</button></form><div id="statementResult" aria-live="polite"><p class="empty">حساب و بازه دلخواه را انتخاب کنید.</p></div></section>`;
 const form=$('statementForm');let request=0;
 const accountSelect=form.elements.accountId;
 const accountChoices=accounts.map(row=>({id:String(row.id),title:row.code+' · '+row.title+(Number(row.is_active)?'':' (غیرفعال)')}));
 const normalizeAccount=value=>String(value??'').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).replace(/ي|ى/g,'ی').replace(/ك/g,'ک').replace(/[\u200c\s]+/g,' ').trim().toLocaleLowerCase('fa');
 $('statementSearch').oninput=e=>{
  const query=normalizeAccount(e.target.value),selected=accountSelect.value;
  const available=accountChoices.filter(row=>!query||normalizeAccount(row.title).includes(query));
  if(selected&&!available.some(row=>row.id===selected)){
   const row=accountChoices.find(row=>row.id===selected);
   if(row)available.unshift(row);
  }
  accountSelect.replaceChildren(new Option(available.length?'انتخاب حساب':'حسابی پیدا نشد',''),...available.map(row=>new Option(row.title,row.id)));
  accountSelect.value=selected;
 };
 $('statementClear').onclick=()=>{request++;form.reset();$('statementSearch').value='';$('statementSearch').dispatchEvent(new Event('input'));$('statementResult').innerHTML='<p class="sy-account-empty">حساب و بازه دلخواه را انتخاب کنید.</p>';form.elements.accountId.focus();};
 const load=async(filters,page=1)=>{const seq=++request;const query=new URLSearchParams({...filters,page});$('statementResult').innerHTML='<p class="loading" role="status">در حال دریافت گردش حساب…</p>';try{const d=await api(scoped('/api/v1/accounting/statement?'+query));if(token!==pageToken||seq!==request)return;
 $('statementResult').innerHTML=`<header class="sy-account-result-heading"><h3>${esc(d.account.code+' · '+d.account.title)}</h3><p>گردش تأییدشده · مبالغ به ریال</p></header><div class="sy-account-totals"><div><span>مانده ابتدای دوره</span><strong>${number(d.opening)} ریال</strong></div><div><span>جمع بدهکار</span><strong>${number(d.debit)} ریال</strong></div><div><span>جمع بستانکار</span><strong>${number(d.credit)} ریال</strong></div><div class="sy-account-closing"><span>مانده پایان دوره</span><strong>${number(d.closing)} ریال</strong></div></div>${session.permissions?.includes('reports.view')||session.roles?.includes('super_admin')?'<button id="statementExport" type="button">خروجی اکسل (کل بازه)</button>':''}<button id="statementPrint" type="button">چاپ صفحه فعلی</button><p class="sy-account-print-note">چاپ شامل خلاصه مانده‌ها و ردیف‌های همین صفحه است؛ برای دریافت تمام گردش‌ها از خروجی اکسل استفاده کنید.</p><div class="table-wrap sy-account-statement-table-wrap"><table class="sy-account-statement-table"><thead><tr><th scope="col">ردیف</th><th scope="col">تاریخ</th><th scope="col">شماره سند</th><th scope="col">شرح ردیف</th><th scope="col">پیگیری</th><th scope="col">بدهکار</th><th scope="col">بستانکار</th><th scope="col">مانده</th></tr></thead><tbody>${d.items.map(r=>`<tr><td data-label="ردیف">${number(r.row_no)}</td><td data-label="تاریخ">${dateText(r.document_date)}</td><td data-label="سند"><button type="button" data-statement-doc="${r.document_id}">${esc(r.document_no)}</button></td><td data-label="شرح">${esc(r.description)}</td><td data-label="پیگیری">${esc(r.tracking_no)}</td><td data-label="بدهکار" class="sy-financial-number">${number(r.debit)}</td><td data-label="بستانکار" class="sy-financial-number">${number(r.credit)}</td><td data-label="مانده" class="sy-financial-number">${number(r.balance)}</td></tr>`).join('')||'<tr><td colspan="8" class="empty">در این بازه گردش تأییدشده‌ای ثبت نشده است.</td></tr>'}</tbody></table></div><div class="pager"><button id="statementPrev" ${page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))} · ${number(d.pagination.total)} ردیف</span><button id="statementNext" ${page>=d.pagination.pages?'disabled':''}>بعدی</button></div>`;
 $('statementExport')?.addEventListener('click',()=>downloadFile('/api/v1/accounting/statement/export?'+new URLSearchParams(filters),'گردش-حساب.xlsx'));
 $('statementPrint')?.addEventListener('click',()=>window.print());
 $('statementPrev').onclick=()=>load(filters,page-1);$('statementNext').onclick=()=>load(filters,page+1);
 $('statementResult').querySelectorAll('[data-statement-doc]').forEach(b=>b.onclick=()=>journalEditor(Number(b.dataset.statementDoc),token,()=>accountStatementPage(token,filters,page)).catch(e=>notify(e.message,true)));
 }catch(e){if(token===pageToken&&seq===request)$('statementResult').innerHTML='<p class="error" role="alert">'+esc(e.message)+'</p>';}};
 form.onsubmit=e=>{e.preventDefault();
  const filters=Object.fromEntries(new FormData(form));
  if(!filters.accountId){form.elements.accountId.reportValidity();return;}
  if(filters.from&&filters.to&&filters.to<filters.from){form.elements.to.setCustomValidity('تاریخ پایان قبل از شروع است.');form.elements.to.reportValidity();form.elements.to.setCustomValidity('');return;}
  load(filters);
 };
 if(initial){for(const [key,value] of Object.entries(initial))if(form.elements[key])form.elements[key].value=value;form.elements.accountId.value=String(initial.accountId||'');await load(initial,initialPage);}
}
async function journalPage(token=pageToken,page=1,filters={}){
 const sequence=++journalRequestSequence;
 const query=new URLSearchParams({limit:25,page,
  search:String(filters.search||''),status:String(filters.status||''),
  sortBy:filters.order==='amount'?'total_amount':'document_date',
  sortDir:filters.order==='old'?'asc':'desc'});
 const d=await api(scoped('/api/v1/accounting-documents?'+query));if(token!==pageToken||sequence!==journalRequestSequence)return;
 const documents=Array.isArray(d.items)?d.items:[];
 $('content').innerHTML=`<section class="panel sy-journal-list">
  ${accountingTabs(false)}
  <header class="sy-journal-list-header"><div><span class="sy-operation-kicker">دفتر اسناد مالی</span><h2>اسناد حسابداری</h2><p>پیش‌نویس‌ها را بررسی و تکمیل کنید. اسناد تأییدشده قفل هستند و امکان ویرایش ندارند.</p></div><div class="sy-journal-list-actions">${can('accounting-documents','manage')?'<button type="button" id="newJournal" class="primary">ثبت سند جدید</button>':''}<a href="#accounts">تعاریف حساب‌ها</a></div></header>
  <div class="sy-journal-list-filters" role="search" aria-label="جست‌وجو و فیلتر اسناد"><label>جست‌وجو در تمام اسناد شرکت<input id="journalSearch" type="search" placeholder="شماره سند یا شرح" autocomplete="off" aria-controls="journalTable" value="${esc(filters.search||'')}"></label><label>وضعیت<select id="journalStatus"><option value="">همه وضعیت‌ها</option><option value="draft" ${filters.status==='draft'?'selected':''}>پیش‌نویس</option><option value="approved" ${filters.status==='approved'?'selected':''}>تأییدشده</option></select></label><label>مرتب‌سازی<select id="journalOrder"><option value="recent">جدیدترین تاریخ</option><option value="old" ${filters.order==='old'?'selected':''}>قدیمی‌ترین تاریخ</option><option value="amount" ${filters.order==='amount'?'selected':''}>بیشترین مبلغ</option></select></label><button type="button" id="journalResetFilters" class="sy-journal-reset">پاک‌کردن فیلترها</button><span id="journalFilterCount" role="status" aria-live="polite"></span></div>
  <div id="journalTable" class="table-wrap sy-journal-list-table" tabindex="0" role="region" aria-label="فهرست اسناد حسابداری" aria-live="polite"></div>
  <nav class="pager" aria-label="صفحات اسناد"><button type="button" id="journalPrev" ${page<=1?'disabled':''}>صفحه قبل</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))}</span><button type="button" id="journalNext" ${page>=d.pagination.pages?'disabled':''}>صفحه بعد</button></nav><p class="sy-journal-page-hint">جست‌وجو و فیلترها روی تمام اسناد شرکت اعمال می‌شوند. نتایج به‌صورت صفحه‌بندی‌شده نمایش داده می‌شوند.</p></section>`;
 const search=$('journalSearch'),status=$('journalStatus'),order=$('journalOrder'),table=$('journalTable');
 const render=()=>{
  if(token!==pageToken||!table?.isConnected)return;
  const rows=documents;
  $('journalFilterCount').textContent=number(d.pagination.total??rows.length)+' سند مطابق فیلتر';
  table.innerHTML=rows.length?`<table class="sy-journal-table"><thead><tr><th scope="col">شماره سند</th><th scope="col">تاریخ شمسی</th><th scope="col">شرح</th><th scope="col">مبلغ سند (ریال)</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead><tbody>${rows.map(row=>`<tr><td data-label="شماره سند"><strong>${esc(row.document_no)}</strong></td><td data-label="تاریخ">${dateText(row.document_date)}</td><td data-label="شرح">${esc(row.description||'—')}</td><td data-label="مبلغ" class="sy-financial-number">${number(row.total_amount)}</td><td data-label="وضعیت"><span class="sy-journal-status ${row.status==='approved'?'is-approved':'is-draft'}">${esc(statuses[row.status]||row.status)}</span></td><td data-label="عملیات"><button type="button" data-journal="${row.id}">${row.status==='approved'?'مشاهده سند':'بررسی و ویرایش'}</button></td></tr>`).join('')}</tbody></table>`:'<div class="sy-journal-empty" role="status"><strong>سندی مطابق فیلترهای فعلی یافت نشد.</strong><p>فیلترها را پاک کنید یا عبارت جست‌وجو را تغییر دهید.</p><button type="button" id="journalEmptyReset">نمایش همه اسناد</button></div>';
  table.querySelectorAll('[data-journal]').forEach(b=>b.onclick=()=>journalEditor(Number(b.dataset.journal),token).catch(err=>notify(err.message,true)));
  $('journalEmptyReset')?.addEventListener('click',()=>journalPage(token,1,{}).catch(err=>notify(err.message,true)));
 };
 const collect=()=>({search:search.value.trim(),status:status.value,order:order.value});
 const refresh=(restoreSearchFocus=false)=>journalPage(token,1,collect()).then(()=>{
  if(restoreSearchFocus&&token===pageToken&&$('journalSearch')){$('journalSearch').focus();}
 }).catch(err=>notify(err.message,true));
 let searchTimer;
 search.oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>refresh(true),400);};
 status.onchange=()=>refresh();order.onchange=()=>refresh();
 $('journalResetFilters').onclick=()=>journalPage(token,1,{}).catch(err=>notify(err.message,true));render();
 $('newJournal')?.addEventListener('click',()=>journalEditor(null,token).catch(e=>notify(e.message,true)));
 $('journalPrev').onclick=()=>journalPage(token,page-1,collect()).catch(e=>notify(e.message,true));
 $('journalNext').onclick=()=>journalPage(token,page+1,collect()).catch(e=>notify(e.message,true));
}
async function journalEditor(id,token=pageToken,back=null){
 const doc=id?await api(scoped('/api/v1/accounting/journal/'+id)):{document_no:'',document_date:todayISO(),description:'',status:'draft',lines:[{},{}]};
 const accounts=[];let page=1,more=true;while(more){const d=await api(scoped('/api/v1/accounts?limit=200&active=1&page='+page++));accounts.push(...d.items);more=page<=d.pagination.pages;}
 if(token!==pageToken)return;const editable=can('accounting-documents','manage')&&doc.status==='draft';
 // Approved and legacy drafts can still display accounts that have since been deactivated.
 const options=new Map(accounts.map(a=>[String(a.id),a.code+' · '+a.title]));for(const l of doc.lines)if(!options.has(String(l.account_id)))options.set(String(l.account_id),l.code+' · '+l.title);
 $('content').innerHTML=`<section class="panel sy-journal-editor"><header class="sy-journal-editor-heading"><div><span class="sy-operation-kicker">ثبت و کنترل سند مالی</span><h2>${id?'سند '+esc(doc.document_no):'ثبت سند حسابداری'}</h2><p>هر ردیف باید فقط بدهکار یا بستانکار باشد و جمع دو طرف سند قبل از ذخیره برابر شود.</p></div><span class="sy-journal-status ${doc.status==='approved'?'is-approved':'is-draft'}">${esc(statuses[doc.status]||doc.status)}</span></header><form id="journalForm"><fieldset class="sy-journal-fieldset" ${editable?'':'disabled'}><legend class="sy-journal-legend">اطلاعات سند و ردیف‌های حسابداری</legend><div class="form-grid sy-journal-meta"><label>شماره سند<input name="documentNo" maxlength="100" required value="${esc(doc.document_no)}"></label><label>شماره مرجع<input name="referenceNo" maxlength="100" value="${esc(doc.reference_no)}"></label><label>شماره فرعی<input name="subsidiaryNo" maxlength="100" value="${esc(doc.subsidiary_no)}"></label><label>تاریخ سند<input name="documentDate" type="date" required value="${esc(doc.document_date)}"></label><label>شرح<input name="documentDescription" maxlength="10000" value="${esc(doc.description)}"></label></div><div class="sy-journal-lines-head"><h3>ردیف‌های حسابداری</h3><p>هر ردیف فقط یکی از ستون‌های بدهکار یا بستانکار را داشته باشد.</p></div><div class="table-wrap sy-journal-editor-table-wrap" tabindex="0" role="region" aria-label="ردیف‌های سند حسابداری"><table class="sy-journal-editor-table"><thead><tr><th scope="col">حساب</th><th scope="col">شرح ردیف</th><th scope="col">بدهکار · ریال</th><th scope="col">بستانکار · ریال</th><th scope="col">شماره پیگیری</th><th scope="col">عملیات</th></tr></thead><tbody id="journalLines"></tbody></table></div>${editable?'<button type="button" id="journalAdd">افزودن ردیف</button>':''}</fieldset><p id="journalTotals" class="sy-journal-totals" role="status" aria-live="polite"></p><p class="sy-journal-help">ذخیره تنها پس از تکمیل حساب‌ها و برابری جمع بدهکار و بستانکار فعال می‌شود.</p>${editable?'<button class="primary" id="journalSave">ذخیره پیش‌نویس</button>':''}</form>${editable&&id?'<button id="journalApprove">تأیید و قفل سند</button>':''}${id?'<button id="journalPrint">پیش‌نمایش چاپ</button>':''}<button id="journalBack">${back?'بازگشت به گزارش':'بازگشت به فهرست'}</button></section>`;
 $('journalPrint')?.addEventListener('click',()=>{dialog('چاپ سند حسابداری',`<article class="journal-print"><header>${agencyLogoImage("لوگوی "+(currentAgency?.name||"شرکت"),72)}<div><h2>${esc(currentAgency?.name||'سرویس یار')}</h2><p>سند حسابداری · ${esc(statuses[doc.status]||doc.status)}</p></div></header><p>شماره سند: ${esc(doc.document_no)} · تاریخ: ${dateText(doc.document_date)} · مرجع: ${esc(doc.reference_no||'—')} · فرعی: ${esc(doc.subsidiary_no||'—')}</p><p>${esc(doc.description)}</p>${doc.status==='draft'?'<p class="draft-watermark">پیش‌نویس · تأیید نشده</p>':''}<div class="table-wrap"><table><thead><tr><th>ردیف</th><th>کد و نام حساب</th><th>شرح</th><th>پیگیری</th><th>بدهکار · ریال</th><th>بستانکار · ریال</th></tr></thead><tbody>${doc.lines.map((l,i)=>`<tr><td>${number(i+1)}</td><td>${esc(l.code+' · '+l.title)}</td><td>${esc(l.description)}</td><td>${esc(l.tracking_no)}</td><td>${number(l.debit)}</td><td>${number(l.credit)}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="4">جمع</th><td>${number(doc.total_amount)}</td><td>${number(doc.total_amount)}</td></tr></tfoot></table></div><div class="journal-signatures"><span>تنظیم‌کننده</span><span>حسابداری</span><span>تأییدکننده</span></div></article><button id="journalDoPrint" type="button">چاپ / ذخیره PDF</button>`);$('journalDoPrint').onclick=()=>window.print();});
 const add=line=>{if($('journalLines').rows.length>=200){notify('حداکثر ۲۰۰ ردیف برای هر سند مجاز است.',true);return;}const row=document.createElement('tr');row.innerHTML=`<td data-label="حساب"><select name="accountId" aria-label="حساب" required><option value="">انتخاب حساب</option>${[...options].filter(([k])=>k!=='undefined').map(([k,v])=>`<option value="${esc(k)}" ${String(line.account_id)===k?'selected':''}>${esc(v)}</option>`).join('')}</select></td><td data-label="شرح ردیف"><input name="description" aria-label="شرح ردیف" maxlength="1000" value="${esc(line.description)}"></td><td data-label="بدهکار · ریال"><input name="debit" aria-label="بدهکار" type="number" min="0" max="999999999999.99" step="0.01" value="${esc(line.debit||0)}"></td><td data-label="بستانکار · ریال"><input name="credit" aria-label="بستانکار" type="number" min="0" max="999999999999.99" step="0.01" value="${esc(line.credit||0)}"></td><td data-label="پیگیری"><input name="trackingNo" aria-label="شماره پیگیری" maxlength="100" value="${esc(line.tracking_no)}"></td><td data-label="عملیات">${editable?'<button type="button" data-remove>حذف ردیف</button>':''}</td>`;$('journalLines').append(row);row.querySelector('[data-remove]')?.addEventListener('click',()=>{if($('journalLines').rows.length<=2){notify('حداقل دو ردیف بدهکار و بستانکار برای سند لازم است.',true);return;}row.remove();totals();});row.oninput=totals;row.onchange=totals;totals();};
 const totals=()=>{
  let debit=0,credit=0,valid=true;let count=0;
  $('journalLines').querySelectorAll('tr').forEach(row=>{
   count++;
   const account=row.querySelector('[name=accountId]');
   const dInput=row.querySelector('[name=debit]'),cInput=row.querySelector('[name=credit]');
   const parse=input=>{
    const text=String(input.value||'0');
    if(!/^\d{1,12}(?:\.\d{1,2})?$/.test(text))return null;
    const [whole,fraction='']=text.split('.');
    return Number(whole)*100+Number((fraction+'00').slice(0,2));
   };
   const d=parse(dInput),creditValue=parse(cInput);
   const rowValid=!!account.value&&d!==null&&creditValue!==null&&((d>0)!==(creditValue>0));
   if(!rowValid)valid=false;
   if(d!==null)debit+=d;
   if(creditValue!==null)credit+=creditValue;
   row.classList.toggle('sy-journal-line-error',!rowValid);
  });
  valid=valid&&count>=2&&debit>0&&debit===credit;
  const status=$('journalTotals');
  status.classList.toggle('is-unbalanced',!valid);
  status.textContent='جمع بدهکار: '+number(debit/100)+' ریال · جمع بستانکار: '+number(credit/100)+' ریال · اختلاف: '+number(Math.abs(debit-credit)/100)+' ریال'+(!valid?' · سند هنوز آماده ثبت نیست.':' · سند تراز است.');
  const save=$('journalSave');if(save&&editable)save.disabled=!valid;
  const addButton=$('journalAdd');if(addButton)addButton.disabled=count>=200;
  return valid;
 };
 doc.lines.forEach(add);totals();$('journalAdd')?.addEventListener('click',()=>add({}));$('journalBack').onclick=()=>Promise.resolve(back?back():journalPage(token)).catch(e=>notify(e.message,true));
 $('journalForm').onsubmit=async e=>{e.preventDefault();if(!editable)return;if(!totals()){notify('برای ذخیره، حساب هر ردیف و تراز بدهکار/بستانکار را اصلاح کنید.',true);return;}const button=$('journalSave');button.disabled=true;try{const f=e.currentTarget,body={referenceNo:f.elements.referenceNo.value,subsidiaryNo:f.elements.subsidiaryNo.value,documentNo:f.elements.documentNo.value,documentDate:f.elements.documentDate.value,description:f.elements.documentDescription.value,lines:[...$('journalLines').querySelectorAll('tr')].map(r=>Object.fromEntries([...r.querySelectorAll('input,select')].map(i=>[i.name,i.value])))};const saved=await write('/api/v1/accounting/journal'+(id?'/'+id:''),body,id?'PUT':'POST');notify('پیش‌نویس سند ذخیره شد.');if(token===pageToken)await journalEditor(saved.id,token);}catch(err){notify(err.message,true);}finally{if(button.isConnected)button.disabled=false;}};
 $('journalApprove')?.addEventListener('click',async e=>{if(!confirm('سند تأیید و غیرقابل ویرایش شود؟'))return;e.target.disabled=true;try{await write('/api/v1/accounting/journal/'+id+'/approve',{});notify('سند تأیید شد.');if(token===pageToken)await journalEditor(id,token);}catch(err){notify(err.message,true);if(e.target.isConnected)e.target.disabled=false;}});
}

function syLocationSvg(color){
 return '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" fill="'+color+'"><path d="M12 2a8 8 0 0 0-8 8c0 5.6 8 12 8 12s8-6.4 8-12a8 8 0 0 0-8-8zm0 11.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4z"/></svg>';
}
function dashboardCompanyPin(hasLogo){
 const img='<img class="sy-map-marker-photo" alt="" loading="lazy" decoding="async" src="'+esc(hasLogo?agencyLogoUrl():'/assets/company-map-icon.svg')+'">';
 return L.divIcon({className:'sy-map-marker sy-map-company'+(!hasLogo?' sy-company-default-photo':''),
  html:'<span class="sy-map-marker-bubble">'+img+'<span class="sy-map-marker-symbol">'+syLocationSvg('#0b985b')+'</span></span>',
  iconSize:[38,42],iconAnchor:[19,40],popupAnchor:[0,-37]});
}
function dashboardCompanyMarker(map,point,hasLogo){
 const marker=L.marker(point,{icon:dashboardCompanyPin(hasLogo),title:currentAgency?.name||'دفتر شرکت'}).addTo(map).bindPopup('<div class="sy-map-popup"><strong>'+esc(currentAgency?.name||'دفتر شرکت')+'</strong><small>شرکت سرویس مدارس</small></div>',{className:'sy-dashboard-popup',maxWidth:230,minWidth:140});
 const icon=marker.getElement(),img=icon?.querySelector('.sy-map-marker-photo');
 if(img){
  const fallback=()=>{
   const defaultSource='/assets/company-map-icon.svg';
   if(img.getAttribute('src')===defaultSource){img.remove();icon.classList.add('sy-marker-fallback','sy-company-default-photo');}
   else{icon.classList.add('sy-company-default-photo');img.src=defaultSource;}
  };
  img.addEventListener('error',fallback);
  if(img.complete&&img.naturalWidth===0)fallback();
 }
 return marker;
}
function dashboardSchoolPin(sc){
 const url=Number(sc.id)>0&&sc.photo_path?scoped('/api/v1/schools/'+encodeURIComponent(sc.id)+'/photo'):null;
 const img='<img class="sy-map-marker-photo" alt="" loading="lazy" decoding="async" src="'+esc(url||'/assets/school-map-icon.svg')+'">';
 const label=String(sc.name||'مدرسه');
 return L.divIcon({className:'sy-map-marker sy-map-school'+(!url?' sy-school-default-photo':''),
  html:'<span class="sy-map-marker-bubble">'+img+'<span class="sy-map-marker-symbol">'+syLocationSvg('#e5ae0a')+'</span></span><span class="sy-map-school-label" title="'+esc(label)+'">'+esc(label)+'</span>',
  iconSize:[78,59],iconAnchor:[39,40],popupAnchor:[0,-36]});
}
function dashboardSchoolMarker(map,point,sc){
 const marker=L.marker(point,{icon:dashboardSchoolPin(sc),title:String(sc.name||'مدرسه')}).addTo(map).bindPopup('<div class="sy-map-popup"><strong>'+esc(sc.name||'مدرسه')+'</strong><small>مدرسه ثبت‌شده</small></div>',{className:'sy-dashboard-popup',maxWidth:230,minWidth:140});
 const icon=marker.getElement();
 const img=icon?.querySelector('.sy-map-marker-photo');
 if(img){
  const fallback=()=>{
   const placeholder='/assets/school-map-icon.svg';
   if(img.getAttribute('src')===placeholder){
    img.remove();icon.classList.add('sy-marker-fallback','sy-school-default-photo');
   }else{
    icon.classList.add('sy-school-default-photo');
    img.src=placeholder;
   }
  };
  img.addEventListener('error',fallback);
  if(img.complete&&img.naturalWidth===0)fallback();
 }
 return marker;
}

async function portalVehicleRequestsPage(token=pageToken){
 const items=await api(scoped('/api/v1/operations/portal-vehicle-requests'));
 if(token!==pageToken)return;
 const pending=items.filter(row=>row.status==='pending');
 const history=items.filter(row=>row.status!=='pending');
 const driver=row=>[row.first_name,row.last_name].filter(Boolean).join(' ')||'راننده';
 const change=(label,before,after)=>'<div><dt>'+label+'</dt><dd><span>فعلی: '+esc(before||'—')+'</span><strong>درخواستی: '+esc(after||before||'—')+'</strong></dd></div>';
 $('content').innerHTML='<section class="panel sy-vehicle-requests"><header class="page-intro"><div><h2>درخواست‌های اصلاح خودرو</h2><p>مدل و پلاک درخواستی را با اطلاعات فعلی مقایسه کنید.</p></div><b>'+number(pending.length)+' درخواست در انتظار</b></header>'+
 '<section><h3>در انتظار بررسی</h3><div class="sy-vehicle-request-list">'+
 (pending.map(row=>'<article class="sy-vehicle-request"><h4>'+esc(driver(row))+'</h4><dl>'+change('مدل خودرو',row.current_model,row.requested_model)+change('پلاک',row.current_plate,row.requested_plate)+'</dl>'+
 (row.note?'<p>'+esc(row.note)+'</p>':'')+
 (can('drivers','manage')?'<div class="member-actions"><button class="primary" type="button" data-portal-review="'+Number(row.id)+'" data-action="approve">تأیید تغییر</button><button type="button" data-portal-review="'+Number(row.id)+'" data-action="reject">رد درخواست</button></div>':'')+'</article>').join('')||
 '<p class="empty">درخواست در انتظار بررسی وجود ندارد.</p>')+'</div></section>'+
 '<section class="sy-operational-history"><h3>سوابق بررسی</h3>'+
 (history.length?simpleTable(['راننده','مدل درخواستی','پلاک درخواستی','توضیحات','نتیجه'],
  history.map(row=>[driver(row),row.requested_model||'—',row.requested_plate||'—',row.note||'—',row.status==='approved'?'تأییدشده':'ردشده'])):
  '<p class="empty">سابقه‌ای ثبت نشده است.</p>')+'</section></section>';
 $('content').querySelectorAll('[data-portal-review]').forEach(button=>button.onclick=async()=>{
   const action=button.dataset.action,id=Number(button.dataset.portalReview);
   if(!Number.isInteger(id)||id<1)return;
   if(!confirm(action==='approve'?'تغییرات پلاک یا مدل این راننده را پس از بررسی مدارک تأیید می‌کنید؟':'درخواست اصلاح خودرو رد شود؟'))return;
   button.disabled=true;
   try{await write('/api/v1/operations/portal-vehicle-requests/'+id+'/'+action,{},'POST');
     notify('نتیجه بررسی ثبت شد.');await portalVehicleRequestsPage(token);
   }catch(e){notify(e.message,true);if(button.isConnected)button.disabled=false;}
 });
}
async function dashboard(token=pageToken){
 const agencyAtRequest=currentAgency?.id;
 const d=await api(scoped('/api/v1/operations/dashboard'));
 if(token!==pageToken||currentAgency?.id!==agencyAtRequest)return;
 const counts=d.counts||{},metrics=d.metrics||{},schools=Array.isArray(d.schools)?d.schools:[];
 const statusesList=Array.isArray(d.statuses)?d.statuses:[];
 const total=Math.max(0,Number(counts.students)||0);
 const assigned=Math.min(total,Math.max(0,Number(metrics.assignedStudents)||0));
 const unassigned=Math.max(0,total-assigned);
 const percentage=total?Math.round(assigned/total*100):0;
 const activeServices=Math.max(0,Number(metrics.activeServices)||0);
 const employed=Math.max(0,Number(metrics.servingDrivers)||0);
 const capacity=Math.max(0,Number(metrics.serviceCapacity)||0);
 const coverage=capacity?Math.round(Math.min(100,assigned/capacity*100)):0;
 const serviceCoverage=activeServices?Math.round(Math.min(100,Number(metrics.staffedServices||0)/activeServices*100)):0;
 const staleStatuses=Math.max(0,Number(metrics.statusMismatch)||0);
 const fmt=v=>number(Math.max(0,Number(v)||0));
 const ratio=(part,whole)=>whole?Math.round(Math.max(0,Math.min(100,part/whole*100))):0;
 const statusBreakdown=statusesList.map(s=>({key:String(s.service_status||''),value:Math.max(0,Number(s.total)||0)}));
 const statusKeys=new Set(statusBreakdown.map(s=>s.key));
 const statusColor={assigned:'#168660',waiting:'#d19a3b',pending:'#6379d8',prepayment:'#9c68b5',no_request:'#97a3b5',cancelled:'#c36b76'};
 const chartRows=statusBreakdown.slice().sort((a,b)=>b.value-a.value);
 const studentsSection=can('students'),driversSection=can('drivers'),schoolsSection=can('schools'),servicesSection=can('services');
 const kpis=[
  {permission:studentsSection,label:'دانش‌آموزان فعال',metric:'students',value:fmt(total),detail:'دانش‌آموزان ثبت‌شده شرکت',icon:'students',href:'#students',tone:'blue'},
  {permission:studentsSection,label:'تخصیص‌یافته واقعی',metric:'assigned',value:fmt(assigned),detail:fmt(percentage)+'٪ از دانش‌آموزان',icon:'services',href:'#students',tone:'green'},
  {permission:studentsSection,label:'نیازمند تخصیص',metric:'unassigned',value:fmt(unassigned),detail:'بدون سرویس فعال',icon:'students',href:'#students',tone:unassigned?'amber':'green'},
  {permission:servicesSection,label:'سرویس فعال',metric:'services',value:fmt(activeServices),detail:fmt(metrics.staffedServices)+' دارای راننده فعال',icon:'services',href:'#services',tone:'blue'},
  {permission:driversSection,label:'راننده فعال در سرویس',metric:'drivers',value:fmt(employed),detail:'از '+fmt(counts.drivers)+' راننده فعال',icon:'drivers',href:'#drivers',tone:'green'},
  {permission:schoolsSection,label:'مدارس فعال',metric:'schools',value:fmt(counts.schools),detail:'مدارس تحت پوشش شرکت',icon:'schools',href:'#schools',tone:'blue'}
 ].filter(k=>k.permission);
 const schoolBars=schools.slice().sort((a,b)=>Number(b.unassigned||0)-Number(a.unassigned||0)).slice(0,8);
 const schoolRows=schools.map(sc=>[sc.name,fmt(sc.students),fmt(sc.assigned),fmt(sc.unassigned)]);
 $('content').innerHTML=`
 <div class="sy-mgmt-dashboard">
  <section class="dashboard-welcome sy-mgmt-hero">
   <div class="sy-dashboard-identity">${agencyLogoImage('نشان '+(currentAgency?.name||'شرکت'),76)}<div>
    <span class="dashboard-kicker">پنل مدیریتی شرکت · آمار عملیاتی</span>
    <h2>نمای کلی عملکرد شرکت</h2>
    <p>${esc(currentAgency?.name||'سرویس‌یار')} · ${esc(new Intl.DateTimeFormat('fa-IR',{dateStyle:'full',timeZone:'Asia/Tehran'}).format(new Date()))}</p>
   </div></div>
   <div class="dashboard-create"><button type="button" id="dashboardRefresh" class="samar-refresh" aria-label="به‌روزرسانی آمار داشبورد">↻ به‌روزرسانی</button>
   ${[['students','ثبت دانش‌آموز'],['services','تعریف سرویس']].filter(([k])=>can(k,'manage')).map(([k,t])=>`<a href="#${k}/new">${navIcon(k)}${t}</a>`).join('')}</div>
  </section>
  <section class="sy-mgmt-summary" aria-label="شاخص‌های کلیدی عملکرد">
   ${kpis.map(k=>`<a href="${k.href}" data-dashboard-metric="${k.metric}" class="sy-mgmt-kpi sy-mgmt-tone-${k.tone}"><div class="sy-mgmt-kpi-head"><span>${esc(k.label)}</span>${navIcon(k.icon)}</div><strong>${k.value}</strong><small>${esc(k.detail)}</small></a>`).join('')}
  </section>
  <section class="sy-mgmt-grid">
   <article class="panel sy-mgmt-panel sy-mgmt-overview">
    <header class="sy-mgmt-panel-head"><div><h3>پوشش دانش‌آموزان</h3><p>بر اساس تخصیص واقعی به سرویس‌های فعال</p></div><button type="button" class="sy-mgmt-inline-stat sy-dashboard-stat" data-dashboard-metric="assigned">${fmt(percentage)}٪ پوشش</button></header>
    <div class="sy-mgmt-donut-wrap"><div class="sy-mgmt-ring" style="--sy-coverage:${percentage}%" role="img" aria-label="درصد تخصیص واقعی دانش‌آموزان: ${fmt(percentage)} درصد"><div><strong>${fmt(percentage)}٪</strong><small>دارای سرویس</small></div></div>
     <div class="sy-mgmt-legend"><div><i style="background:#168660"></i><span>دارای سرویس فعال</span><button type="button" class="sy-dashboard-stat" data-dashboard-metric="assigned">${fmt(assigned)}</button></div><div><i style="background:#dba557"></i><span>بدون سرویس فعال</span><button type="button" class="sy-dashboard-stat" data-dashboard-metric="unassigned">${fmt(unassigned)}</button></div><p class="muted">اطلاعات این بخش از ارتباط واقعی دانش‌آموز و سرویس محاسبه می‌شود.</p></div></div>
   </article>
   <article class="panel sy-mgmt-panel">
    <header class="sy-mgmt-panel-head"><div><h3>ظرفیت و آمادگی سرویس‌ها</h3><p>شاخص‌های عملیاتی بدون تخمین مالی</p></div></header>
    ${[{label:'سرویس‌های دارای راننده فعال',metric:'staffed',value:serviceCoverage,part:Number(metrics.staffedServices)||0,whole:activeServices},{label:'استفاده از ظرفیت صندلی‌ها',metric:'services',value:coverage,part:assigned,whole:capacity}].map(k=>`<div class="sy-mgmt-progress-row"><div><span>${esc(k.label)}</span><button type="button" class="sy-dashboard-stat" data-dashboard-metric="${k.metric}">${fmt(k.part)} از ${fmt(k.whole)}</button></div><div class="sy-mgmt-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${k.value}" aria-label="${esc(k.label)}"><span style="width:${k.value}%"></span></div><small>${fmt(k.value)}٪</small></div>`).join('')}
    <div class="sy-mgmt-alerts">${Number(metrics.servicesWithoutActiveDriver)>0?`<a href="#services" data-dashboard-metric="unstaffed" class="sy-mgmt-alert">⚠ ${fmt(metrics.servicesWithoutActiveDriver)} سرویس بدون راننده فعال</a>`:''}${staleStatuses>0?`<a href="#students" data-dashboard-metric="mismatch" class="sy-mgmt-alert">⚠ اختلاف ${fmt(staleStatuses)} مورد بین وضعیت ثبت‌شده و تخصیص واقعی</a>`:''}${!(Number(metrics.servicesWithoutActiveDriver)>0||staleStatuses>0)?'<p class="sy-mgmt-ok">مغایرت مشخصی در شاخص‌های اصلی شناسایی نشد.</p>':''}</div>
   </article>
   <article class="panel sy-mgmt-panel">
    <header class="sy-mgmt-panel-head"><div><h3>وضعیت‌های ثبت‌شده دانش‌آموزان</h3><p>تفکیک بر اساس آخرین وضعیت ذخیره‌شده</p></div></header>
    <div class="sy-mgmt-status-bars">${chartRows.map(item=>`<a href="#students" data-dashboard-metric="student-status" data-status="${esc(item.key)}"><span>${esc(statuses[item.key]||item.key||'نامشخص')}</span><div class="sy-mgmt-status-track"><i style="width:${ratio(item.value,total)}%;background:${statusColor[item.key]||'#8296ac'}"></i></div><strong>${fmt(item.value)}</strong></a>`).join('')||'<p class="empty">داده‌ای ثبت نشده است.</p>'}</div>
    ${staleStatuses?'<p class="muted">وضعیت‌های ذخیره‌شده ممکن است با تخصیص واقعی تفاوت داشته باشند؛ هشدار بالا مقدار اختلاف را مشخص می‌کند.</p>':''}
   </article>
   <article class="panel sy-mgmt-panel sy-mgmt-school-chart">
    <header class="sy-mgmt-panel-head"><div><h3>مدارس نیازمند پیگیری</h3><p>۸ مدرسه با بیشترین دانش‌آموز بدون سرویس</p></div><span>${fmt(schools.length)} مدرسه</span></header>
    <div class="sy-mgmt-school-bars">${schoolBars.map(sc=>`<div class="sy-mgmt-school-row"><div><button type="button" class="sy-dashboard-stat" data-dashboard-metric="unassigned" data-school-id="${Number(sc.id)}">${esc(sc.name)}</button><strong>${fmt(sc.unassigned)} بدون سرویس</strong></div><div class="sy-mgmt-school-track"><i style="width:${ratio(Number(sc.unassigned)||0,Number(sc.students)||0)}%"></i></div></div>`).join('')||'<p class="empty">مدرسه‌ای برای نمایش وجود ندارد.</p>'}</div>
   </article>
   ${schoolsSection?`<article class="panel sy-mgmt-panel sy-mgmt-wide"><header class="sy-mgmt-panel-head"><div><h3>جزئیات پوشش مدارس</h3><p>تعدادهای واقعی، فقط برای مدارس فعال شرکت</p></div></header>${simpleTable(['مدرسه','دانش‌آموز فعال','دارای سرویس','بدون سرویس'],schoolRows)}</article>`:''}
   <article class="panel sy-mgmt-panel sy-mgmt-map-panel"><header class="sy-mgmt-panel-head"><div><h3>پراکندگی جغرافیایی مدارس</h3><p>فقط نقاط دارای مختصات معتبر</p></div></header><div id="dashboardSchoolMap" class="dashboard-school-map" aria-label="نقشه مدارس و دفتر شرکت"></div></article>
   <article class="panel sy-mgmt-panel sy-mgmt-side">
     <header class="sy-mgmt-panel-head"><div><h3>جستجو و دسترسی سریع</h3><p>دسترسی به اطلاعات مدیریتی</p></div></header>
     <form id="quickSearch" class="sy-mgmt-search"><input name="q" placeholder="نام، شماره یا شناسه را وارد کنید" aria-label="جستجوی سریع"><select name="resource" aria-label="بخش مورد جستجو">${['students','drivers','schools','services'].filter(k=>can(k)).map(k=>`<option value="${k}">${titles[k]}</option>`).join('')}</select><button class="primary">جستجو</button></form>
     <div class="quick-links">${[['students','دانش‌آموزان'],['drivers','رانندگان'],['schools','مدارس'],['services','سرویس‌ها'],['payroll','حقوق رانندگان'],['monitoring','پایش کاربران']].filter(([k])=>can(k)).map(([k,t])=>`<a href="#${k}">${navIcon(k)}${t}</a>`).join('')}</div>
     ${can('wallets')?'<div class="sy-mgmt-wallet wallet-card"><span>موجودی کیف پول · ریال</span><button id="showWallet" type="button" aria-expanded="false">نمایش موجودی</button><strong id="walletValue">******</strong></div>':''}
   </article>
   ${can('monitoring')?'<article class="panel sy-mgmt-panel"><header class="sy-mgmt-panel-head"><div><h3>وضعیت کاربران شرکت</h3><p>آنلاین و آفلاین، بر اساس پایش سرور</p></div></header><div id="dashboardUsers" role="status" aria-live="polite"><p class="muted">دریافت وضعیت کاربران…</p></div></article>':''}
   <article class="panel sy-mgmt-panel"><header class="sy-mgmt-panel-head"><div><h3>تقویم شمسی</h3><p>برنامه‌ریزی روزانه</p></div></header><div id="calendar"></div></article>
  </section>
  <p class="sy-mgmt-footnote">آمار، نمای لحظه‌ای اطلاعات ثبت‌شده در شرکت است؛ نمودار روند تاریخی و شاخص‌های درآمدی بدون داده دوره‌ای قابل اتکا نمایش داده نمی‌شوند.</p>
 </div>`;
 const dashboardRoot=$('content').querySelector('.sy-mgmt-dashboard');
 const coverageTable=dashboardRoot.querySelector('.sy-mgmt-wide tbody');
 if(schools.length&&coverageTable){
  [...coverageTable.rows].forEach((row,index)=>{
   const school=schools[index];
   [...row.cells].forEach((cell,column)=>{
    const action=document.createElement('button');
    action.type='button';action.className='sy-dashboard-stat';
    action.dataset.dashboardMetric=['students','students','assigned','unassigned'][column];
    action.dataset.schoolId=String(school.id);
    action.textContent=cell.textContent;
    action.setAttribute('aria-label',[school.name,cell.dataset.label,cell.textContent].filter(Boolean).join(' · '));
    cell.replaceChildren(action);
   });
  });
 }
 dashboardRoot.addEventListener('click',event=>{
  const button=event.target.closest('[data-dashboard-metric]');
  if(!button)return;
  event.preventDefault();
  const {dashboardMetric:metric,status='',schoolId}=button.dataset;
  const selectedSchool=schools.find(row=>String(row.id)===schoolId);
  const labels={students:'دانش‌آموزان فعال',assigned:'دانش‌آموزان دارای سرویس',unassigned:'دانش‌آموزان بدون سرویس',services:'سرویس‌ها و ظرفیت',drivers:'رانندگان فعال در سرویس',schools:'مدارس فعال',staffed:'سرویس‌های دارای راننده فعال',unstaffed:'سرویس‌های بدون راننده فعال',mismatch:'مغایرت وضعیت تخصیص','student-status':statuses[status]||status||'وضعیت نامشخص'};
  const resource=['services','staffed','unstaffed'].includes(metric)?'services':['schools','drivers'].includes(metric)?metric:'students';
  if(!can(resource)){notify('برای مشاهده جزئیات این شاخص دسترسی ندارید.',true);return;}
  const modal=document.createElement('dialog');
  modal.className='sy-dashboard-details';
  modal.setAttribute('aria-labelledby','dashboardDetailsTitle');
  modal.innerHTML='<header class="sy-dashboard-dialog-header"><h3 id="dashboardDetailsTitle">'+esc(labels[metric]+(selectedSchool?' · '+selectedSchool.name:''))+'</h3><form method="dialog"><button type="submit" aria-label="بستن جزئیات" autofocus>×</button></form></header><div class="sy-dashboard-dialog-body"><p data-dashboard-message role="status"></p><div data-dashboard-results></div><button type="button" data-dashboard-retry hidden>تلاش دوباره</button></div><footer class="sy-dashboard-dialog-footer"><nav aria-label="صفحه‌بندی جزئیات"><button type="button" data-dashboard-prev hidden>صفحه قبل</button><button type="button" data-dashboard-next hidden>صفحه بعد</button></nav><a href="#'+resource+'">مشاهده فهرست کامل</a></footer>';
  const message=modal.querySelector('[data-dashboard-message]'),results=modal.querySelector('[data-dashboard-results]');
  const previous=modal.querySelector('[data-dashboard-prev]'),next=modal.querySelector('[data-dashboard-next]');
  const retry=modal.querySelector('[data-dashboard-retry]');
  let page=1;
  const onBackdrop=event=>{
   if(event.target!==modal)return false;
   const {left,right,top,bottom}=modal.getBoundingClientRect();
   return event.clientX<left||event.clientX>=right||event.clientY<top||event.clientY>=bottom;
  };
  let startedOutside=false;
  modal.addEventListener('pointerdown',event=>{startedOutside=event.button===0&&onBackdrop(event);});
  modal.addEventListener('pointercancel',()=>{startedOutside=false;});
  modal.addEventListener('click',event=>{
   if(startedOutside&&onBackdrop(event))modal.close();
   startedOutside=false;
  });
  modal.addEventListener('close',()=>{
   modal.remove();
   if(button.isConnected)button.focus({preventScroll:true});
  },{once:true});
  modal.querySelector('a').onclick=()=>{if(metric==='student-status')initialStudentStatus=status;modal.close();};
  dashboardRoot.append(modal);modal.showModal();
  const load=async()=>{
   retry.hidden=true;
   previous.disabled=next.disabled=true;
   if(metric==='schools'){
    results.innerHTML=simpleTable(['مدرسه','دانش‌آموز فعال','دارای سرویس','بدون سرویس'],schoolRows);
    message.textContent=fmt(schoolRows.length)+' مدرسه';
    return;
   }
   results.replaceChildren();
   message.textContent='در حال دریافت جزئیات…';
   const query=new URLSearchParams({metric,status,page:String(page)});
   if(schoolId)query.set('schoolId',schoolId);
   try{
    const detail=await api(scoped('/api/v1/operations/dashboard/details?'+query));
    if(!modal.open)return;
    const headers=resource==='services'?['سرویس','راننده','ظرفیت']:resource==='drivers'?['راننده','تعداد سرویس فعال']:['نام دانش‌آموز','مدرسه','وضعیت ثبت‌شده'];
    results.innerHTML=simpleTable(headers,detail.items.map(item=>resource==='services'?[item.name,item.detail||'—',fmt(item.state)]:resource==='drivers'?[item.name,fmt(item.detail)]:[item.name,item.detail,statuses[item.state]||item.state]));
    message.textContent=fmt(detail.total)+' مورد · صفحه '+fmt(page);
    previous.hidden=page<=1;next.hidden=page*25>=detail.total;
   }catch(error){
    if(modal.open){message.textContent='دریافت جزئیات ناموفق بود. '+error.message;retry.hidden=false;}
   }finally{previous.disabled=next.disabled=false;}
  };
  retry.onclick=()=>void load();
  previous.onclick=()=>{page--;void load();};next.onclick=()=>{page++;void load();};
  void load();
 });
 $('dashboardRefresh').onclick=()=>dashboard(token).catch(e=>notify(e.message,true));
 $('quickSearch').onsubmit=e=>{e.preventDefault();initialSearch=e.target.q.value;location.hash=e.target.resource.value;};
 if($('showWallet')){$('showWallet').setAttribute('aria-expanded','false');$('walletValue').setAttribute('aria-live','polite');$('showWallet').onclick=async()=>{const button=$('showWallet');if(button.getAttribute('aria-expanded')==='true'){$('walletValue').textContent='******';button.textContent='نمایش موجودی';button.setAttribute('aria-expanded','false');return;}button.disabled=true;try{delete lookupCache[currentAgency.id+':wallets'];const w={items:await lookup('wallets')};if(token!==pageToken)return;$('walletValue').textContent=number(w.items.reduce((sum,x)=>sum+Number(x.balance),0));button.textContent='پنهان کردن موجودی';button.setAttribute('aria-expanded','true');}catch(e){notify(e.message,true);}finally{if(button.isConnected)button.disabled=false;}};
 } if(can('monitoring'))api(scoped('/api/v1/monitoring/users')).then(users=>{if(token===pageToken&&currentAgency?.id===agencyAtRequest&&$('dashboardUsers'))$('dashboardUsers').innerHTML=users.length?simpleTable(['کاربر','وضعیت'],users.map(u=>[person(u),Number(u.online)?'آنلاین':'آفلاین'])):'<p class="empty">کاربری برای نمایش وجود ندارد.</p>';}).catch(e=>{if(token===pageToken&&$('dashboardUsers'))$('dashboardUsers').textContent=e.message;});calendar(new Date());let map;try{map=await makeMap($('dashboardSchoolMap'));}catch(e){if(token===pageToken&&$('dashboardSchoolMap'))$('dashboardSchoolMap').textContent='نقشه موقتاً در دسترس نیست.';return;}if(!map)return;const points=[];let config;try{config=await api(scoped('/api/v1/maps/config'));}catch(e){if(token===pageToken)$('dashboardSchoolMap').insertAdjacentHTML('afterend','<p class="muted">تنظیمات نقشه دریافت نشد.</p>');return;}if(token!==pageToken||currentAgency?.id!==agencyAtRequest)return;const office=config.companyLocation;if(office&&Number.isFinite(Number(office.latitude))&&Number.isFinite(Number(office.longitude))){const point=[Number(office.latitude),Number(office.longitude)];points.push(point);dashboardCompanyMarker(map,point,config.hasCustomLogo);}else{const hint=document.createElement('p');hint.className='muted';hint.textContent='موقعیت دفتر شرکت هنوز ثبت نشده است؛ از تنظیمات، موقعیت را روی نشان انتخاب کنید.';$('dashboardSchoolMap').after(hint);}d.schools.forEach(sc=>{if(sc.latitude!==null&&sc.longitude!==null){const point=[Number(sc.latitude),Number(sc.longitude)];points.push(point);dashboardSchoolMarker(map,point,sc);}});if(points.length)map.fitBounds(L.latLngBounds(points).pad(.3),{maxZoom:14});
}
function calendar(date){const fmt=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{month:'long',year:'numeric',timeZone:'Asia/Tehran'}),dayFmt=new Intl.DateTimeFormat('en-US-u-ca-persian',{day:'numeric',timeZone:'Asia/Tehran'}),today=new Date();let first=new Date(date);first.setDate(first.getDate()-Number(dayFmt.format(first))+1);let next=new Date(first);next.setDate(next.getDate()+32);next.setDate(next.getDate()-Number(dayFmt.format(next))+1);const length=Math.round((next-first)/86400000),offset=(first.getDay()+1)%7;
 $('calendar').innerHTML=`<div class="calendar-head"><button id="prevMonth" aria-label="ماه قبل">‹</button><b>${fmt.format(date)}</b><button id="nextMonth" aria-label="ماه بعد">›</button></div><div class="calendar-days">${['ش','ی','د','س','چ','پ','ج'].map(x=>`<b>${x}</b>`).join('')}${'<span></span>'.repeat(offset)}${Array.from({length},(_,i)=>{const d=new Date(first);d.setDate(d.getDate()+i);return `<span class="${d.toDateString()===today.toDateString()?'today':''}">${number(i+1)}</span>`}).join('')}</div>`;$('prevMonth').onclick=()=>{const d=new Date(first);d.setDate(d.getDate()-1);calendar(d);};$('nextMonth').onclick=()=>calendar(next);}
function simpleTable(headers,rows){return `<div class="table-wrap sy-report-table-wrap"><table class="sy-report-table"><thead><tr>${headers.map(h=>`<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.length?rows.map(row=>`<tr>${row.map((v,i)=>`<td data-label="${esc(headers[i]||'اطلاعات')}">${esc(v??'—')}</td>`).join('')}</tr>`).join(''):`<tr class="sy-report-empty"><td colspan="${headers.length}" class="empty">رکوردی یافت نشد.</td></tr>`}</tbody></table></div>`;}
function resourceName(key){return titles[key]||key;}
async function lookup(key,includeInactive=false){const agencyId=currentAgency.id,cacheKey=agencyId+':'+key+(includeInactive?':all':'');if(lookupCache[cacheKey])return lookupCache[cacheKey];const rows=[];let page=1;while(true){const d=await api('/api/v1/'+key+'?limit=200&active='+(includeInactive?'':'1')+'&page='+page+'&agencyId='+encodeURIComponent(agencyId));if(String(currentAgency?.id)!==String(agencyId))throw new Error('شرکت فعال تغییر کرده است؛ دوباره تلاش کنید.');rows.push(...d.items);if(page>=d.pagination.pages)break;page++;}return lookupCache[cacheKey]=rows;}
function ledgerFilterForm(mode='records'){
 const methods=agencyMethodChoices||Object.entries(ledgerMethods).map(([code,name])=>({code,name,active:true}));
 if(mode==='report')return `<form id="gridFilters" class="ledger-filters sy-ledger-filters-v3 sy-ledger-report-filter-form sy-ledger-report-compact" aria-label="فیلترهای گزارش دریافت و پرداخت">
  <section class="sy-ledger-report-filter-section" aria-labelledby="syReportSearchTitle">
   <h3 id="syReportSearchTitle" class="sy-ledger-report-filter-title">جست‌وجوی سریع</h3>
   <div class="sy-ledger-report-search-grid">
    <label>شخص، مدرسه یا شرکت<input type="search" name="partySearch" id="ledgerPartySearch" autocomplete="off" maxlength="200" placeholder="نام طرف حساب را وارد کنید"></label>
    <label>شرح یا شماره پیگیری<input type="search" name="search" autocomplete="off" maxlength="200" placeholder="عنوان، توضیحات یا شماره پیگیری"></label>
   </div>
  </section>
  <section class="sy-ledger-report-filter-section" aria-labelledby="syReportMainTitle">
   <h3 id="syReportMainTitle" class="sy-ledger-report-filter-title">مشخصات تراکنش‌ها</h3>
   <div class="sy-ledger-report-main-grid">
    <label>نوع عملیات<select name="direction"><option value="">همه عملیات</option><option value="receipt">دریافت</option><option value="payment">پرداخت</option></select></label>
    <label>روش دریافت / پرداخت<select name="method"><option value="">همه روش‌ها</option>${methods.map(m=>`<option value="${esc(m.code)}">${esc(m.name)}${m.active===false?' (غیرفعال)':''}</option>`).join('')}</select></label>
    <label>طرف حساب<select name="partyType"><option value="">همه طرف حساب‌ها</option><option value="student">دانش‌آموز</option><option value="driver">راننده</option><option value="school">مدرسه</option><option value="agency">شرکت</option></select></label>
    <label>وضعیت سند<select name="active"><option value="1">فعال</option><option value="0">غیرفعال</option><option value="">همه سوابق</option></select></label>
   </div>
  </section>
  <section class="sy-ledger-report-filter-section" aria-labelledby="syReportDateTitle">
   <h3 id="syReportDateTitle" class="sy-ledger-report-filter-title">بازه زمانی گزارش</h3>
   <div class="sy-ledger-report-date-grid">
    <label>از تاریخ<input name="dateFrom" type="date"></label>
    <label>تا تاریخ<input name="dateTo" type="date"></label>
   </div>
  </section>
  <details class="ledger-filter-more sy-ledger-report-advanced"><summary>فیلترهای پیشرفته <span>محدوده مبلغ</span></summary>
   <div class="ledger-filter-grid">
    <label>حداقل مبلغ (ریال)<input name="amountMin" type="number" min="0" step="1" inputmode="numeric" placeholder="حداقل مبلغ"></label>
    <label>حداکثر مبلغ (ریال)<input name="amountMax" type="number" min="0" step="1" inputmode="numeric" placeholder="حداکثر مبلغ"></label>
   </div>
  </details>
  <div class="sy-ledger-report-submit-row">
   <div class="sy-ledger-report-submit-actions"><button type="submit" class="primary" id="ledgerReportApply">تهیه گزارش</button><button type="reset" class="ledger-filter-clear">پاک‌کردن</button></div>
  </div>
 </form>`;

 return `<form id="gridFilters" class="ledger-filters sy-ledger-filters-v3" aria-label="جست‌وجو و فیلتر دریافت و پرداخت">
  <div class="sy-ledger-searchbar">
   <label>جست‌وجوی طرف حساب<input type="search" name="partySearch" id="ledgerPartySearch" autocomplete="off" maxlength="200" placeholder="نام شخص، مدرسه یا شرکت"></label>
   <label>جست‌وجوی شرح و پیگیری<input type="search" name="search" autocomplete="off" maxlength="200" placeholder="عنوان، شماره پیگیری یا توضیحات"></label>
   <div class="sy-ledger-filter-buttons"><button type="submit" class="primary" ${mode==='report'?'id="ledgerReportApply"':''}>${mode==='report'?'تهیه گزارش':'اعمال فیلتر'}</button><button type="reset" class="ledger-filter-clear">پاک‌کردن</button></div>
  </div>
  <div class="sy-ledger-filter-fields">
   <label>نوع عملیات<select name="direction"><option value="">همه عملیات</option><option value="receipt">دریافت</option><option value="payment">پرداخت</option></select></label>
   <label>روش دریافت / پرداخت<select name="method"><option value="">همه روش‌ها</option>${methods.map(m=>`<option value="${esc(m.code)}">${esc(m.name)}${m.active===false?' (غیرفعال)':''}</option>`).join('')}</select></label>
   <label>طرف حساب<select name="partyType"><option value="">همه طرف حساب‌ها</option><option value="student">دانش‌آموز</option><option value="driver">راننده</option><option value="school">مدرسه</option><option value="agency">شرکت</option></select></label>
   <label>وضعیت سند<select name="active"><option value="1">فعال</option><option value="0">غیرفعال</option><option value="">همه سوابق</option></select></label>
   <label>از تاریخ<input name="dateFrom" type="date"></label>
   <label>تا تاریخ<input name="dateTo" type="date"></label>
  </div>
  <details class="ledger-filter-more"><summary>فیلترهای پیشرفته <span>محدوده مبلغ</span></summary><div class="ledger-filter-grid">
   <label>حداقل مبلغ (ریال)<input name="amountMin" type="number" min="0" step="1" inputmode="numeric" placeholder="حداقل مبلغ"></label>
   <label>حداکثر مبلغ (ریال)<input name="amountMax" type="number" min="0" step="1" inputmode="numeric" placeholder="حداکثر مبلغ"></label>
  </div></details>
  <div class="sy-ledger-filter-footer"><span id="ledgerFilterSummary" class="ledger-filter-count" role="status" aria-live="polite">در حال دریافت سوابق…</span></div>
 </form>`;
}
function ledgerSummaryMarkup(){
 const icon=paths=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+paths+'</svg>';
 const cards=[
  ['records','کل سوابق','سند','<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>'],
  ['receipts','مجموع دریافت‌ها','ریال','<path d="M12 3v13m-5-5 5 5 5-5M4 20h16"/>'],
  ['payments','مجموع پرداخت‌ها','ریال','<path d="M12 21V8m-5 5 5-5 5 5M4 4h16"/>'],
  ['balance','مانده خالص','ریال','<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18M16 14h2"/>']
 ];
 return '<section class="sy-ledger-kpis" aria-label="خلاصه مالی فیلترهای انتخاب‌شده">'+cards.map(([id,title,unit,paths])=>'<article class="sy-ledger-kpi sy-ledger-kpi-'+id+'"><span class="sy-ledger-kpi-icon">'+icon(paths)+'</span><div><span>'+title+'</span><strong id="syLedgerKpi'+id[0].toUpperCase()+id.slice(1)+'">—</strong><small>'+unit+'</small></div></article>').join('')+'</section>';
}

function serviceFilterForm(schoolFilter){
 const relation=(name,label,all)=>`<label>${label}<select name="${name}" disabled><option value="">${all}</option></select></label>`;
 return `<form id="gridFilters" class="service-filters service-filters-v2" aria-label="جست‌وجو و فیلتر سرویس‌ها">
  <div class="service-filter-top"><div><span class="service-filter-eyebrow">جست‌وجوی پیشرفته</span><h3>پیدا کردن سرویس</h3><p>نام، کد سرویس یا مشخصات راننده و مدرسه را وارد کنید.</p></div><span class="service-filter-result" id="serviceFilterSummary" role="status" aria-live="polite">در حال دریافت سرویس‌ها…</span></div>
  <div class="service-filter-search">
   <label class="service-primary-search">جست‌وجوی کلی<input name="search" id="search" type="search" maxlength="200" autocomplete="off" placeholder="عنوان یا کد سرویس، نام راننده یا مدرسه…" value="${esc(initialSearch)}"></label>
   <button type="submit" class="primary service-filter-submit">نمایش نتایج</button>
   <button type="reset" class="service-filter-reset">پاک کردن</button>
  </div>
  <div class="service-filter-fields service-filter-main">
   ${schoolFilter?relation('schoolId','مدرسه','همه مدارس'):''}
   ${can('drivers')?`<div class="service-driver-filter"><label>جست‌وجوی راننده<input id="serviceDriverSearch" type="search" placeholder="نام راننده را تایپ کنید…" autocomplete="off" maxlength="200" disabled></label>${relation('driverId','انتخاب راننده','همه رانندگان')}</div>`:''}
   <label>وضعیت سرویس<select name="status"><option value="">همه وضعیت‌ها</option>${fieldOptions('services','status').map(([key,label])=>`<option value="${key}">${label}</option>`).join('')}</select></label>
  </div>
  <details class="service-filter-advanced"><summary>فیلترهای بیشتر <span>دانش‌آموز، پک، شیفت و سوابق</span></summary><div class="service-filter-fields">
   ${can('students')?'<label>دانش‌آموز<input name="studentSearch" type="search" placeholder="نام، نام خانوادگی یا کد ملی" maxlength="200"></label>':''}
   ${can('packs')?relation('packId','پک سرویس','همه پک‌ها'):''}
   <label>فعال بودن<select name="active"><option value="">همه سوابق</option><option value="1">فعال</option><option value="0">غیرفعال</option></select></label>
   <label>شیفت<input name="shift" maxlength="50" placeholder="نام یا شماره شیفت"></label>
  </div></details>
 </form>`;
}
async function ledgerReportPage(token=pageToken){
 if(!can('reports')||!can('ledger')){notify('مجوز مشاهده گزارش مالی وجود ندارد.',true);return;}
 try{agencyMethodChoices=await api(scoped('/api/v1/finance/payment-methods'));}catch(e){notify(e.message,true);}
 if(token!==pageToken)return;
 const saved=window.__ledgerReportFilters||'';
 $('content').innerHTML=`<section class="toolbar sy-ledger-report-page-header">
 <div class="sy-directory-heading"><span class="sy-directory-eyebrow">امور مالی / گزارش‌گیری</span><h2>گزارش دریافت‌ها و پرداخت‌ها</h2><p>فیلتر، بررسی و دریافت خروجی مالی با اطلاعات واقعی سامانه</p></div>
 <div class="toolbar-buttons"><button type="button" id="ledgerTabRecords" class="sy-ledger-back-to-records">بازگشت به ثبت اسناد</button></div>
 </section>
 <nav class="ledger-report-tabs sy-ledger-report-nav" aria-label="بخش‌های دریافت و پرداخت"><button type="button" data-ledger-records>ثبت و مدیریت اسناد</button><button type="button" class="active" aria-current="page">گزارش‌گیری</button></nav>
 <section class="panel ledger-report-panel sy-ledger-report-page-panel" aria-label="فیلترها و نتایج گزارش مالی">

 ${ledgerFilterForm('report')}
 <div class="sy-ledger-report-output-head"><div class="sy-ledger-report-output-meta"><span class="sy-ledger-report-output-kicker">نتایج گزارش</span><h3>خلاصه و ریز تراکنش‌ها</h3><p id="ledgerReportFreshness" class="sy-ledger-report-freshness" role="status" aria-live="polite">پس از تنظیم فیلترها، «تهیه گزارش» را انتخاب کنید.</p><span id="ledgerFilterSummary" class="sy-ledger-report-count-badge" role="status" aria-live="polite">در حال دریافت سوابق…</span></div><div class="ledger-report-actions"><button type="button" id="ledgerReportPrint" disabled>چاپ گزارش</button><button type="button" id="ledgerReportExcel" disabled>خروجی اکسل</button></div></div>
 <div id="ledgerReportBody" aria-live="polite" aria-busy="false"><div class="sy-ledger-report-state" role="status"><strong>در حال آماده‌سازی گزارش</strong><p>نتایج پس از دریافت اطلاعات نمایش داده می‌شوند.</p></div></div>
 </section>`;
 const form=$('gridFilters');$('ledgerTabRecords').onclick=()=>resourcePage('ledger',token);$('content').querySelector('[data-ledger-records]')?.addEventListener('click',()=>resourcePage('ledger',token));
 if(saved){const values=new URLSearchParams(saved);for(const [key,value] of values){const el=form.elements[key];if(el)el.value=value;}if(['amountMin','amountMax'].some(k=>values.get(k)))form.querySelector('details')?.setAttribute('open','');}
 let ready=false,version=0,exportQuery='';
 const query=()=>{const p=new URLSearchParams(new FormData(form));p.set('agencyId',currentAgency.id);return p;};
 const names={student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت'};
 const methods=Object.fromEntries((agencyMethodChoices||Object.entries(ledgerMethods).map(([code,name])=>({code,name}))).map(x=>[x.code,x.name]));
 const invalidate=()=>{version++;ready=false;exportQuery='';$('ledgerReportPrint').disabled=true;$('ledgerReportExcel').disabled=true;$('ledgerReportBody').setAttribute('aria-busy','false');$('ledgerReportBody').innerHTML='<div class="sy-ledger-report-state" role="status"><strong>فیلترهای گزارش تغییر کرده‌اند.</strong><p>برای نمایش نتیجه مطابق معیارهای جدید، دکمه «تهیه گزارش» را انتخاب کنید.</p></div>';$('ledgerReportFreshness').textContent='فیلترها تغییر کرده‌اند؛ برای دریافت نتیجه جدید، «تهیه گزارش» را بزنید.';$('ledgerFilterSummary').textContent='نیازمند به‌روزرسانی';};
 form.addEventListener('input',invalidate);form.addEventListener('change',invalidate);
 const render=async()=>{
  if(form.elements.dateFrom.value&&form.elements.dateTo.value&&form.elements.dateFrom.value>form.elements.dateTo.value){notify('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.',true);return;}
  if(form.elements.amountMin.value&&form.elements.amountMax.value&&Number(form.elements.amountMin.value)>Number(form.elements.amountMax.value)){notify('حداقل مبلغ از حداکثر بیشتر است.',true);return;}
  const current=++version;ready=false;exportQuery='';$('ledgerReportFreshness').textContent='در حال تهیه گزارش بر اساس فیلترهای انتخابی…';$('ledgerReportPrint').disabled=true;$('ledgerReportExcel').disabled=true;$('ledgerReportBody').setAttribute('aria-busy','true');$('ledgerReportBody').innerHTML='<div class="sy-ledger-report-loading" role="status"><span class="sy-ledger-loading-icon" aria-hidden="true"></span>در حال تهیه گزارش مالی…</div>';
  try{
   const p=query();window.__ledgerReportFilters=p.toString();
   const items=[];let page=1,total=0,totals=null;
   do{
    p.set('page',page);p.set('limit',200);
    const d=await api('/api/v1/reports/ledger?'+p);
    if(current!==version||token!==pageToken)return;
    total=d.pagination.total;totals=d.totals;items.push(...d.items);page++;
    if(total>5000){$('ledgerReportFreshness').textContent='تعداد اسناد از سقف نمایش بیشتر است؛ فیلترها را محدود کنید.';$('ledgerReportBody').innerHTML='<div class="sy-ledger-report-state is-warning" role="alert"><strong>تعداد اسناد بیش از ظرفیت نمایش است.</strong><p>برای گزارش دقیق و کامل، تاریخ یا سایر فیلترها را محدود کنید (حداکثر ۵۰۰۰ سند).</p></div>';return;}
   }while(items.length<total&&items.length<5000&&page<=Math.ceil(total/200));
   exportQuery=query().toString();ready=true;$('ledgerReportFreshness').textContent='گزارش با فیلترهای فعلی آماده است؛ چاپ و خروجی اکسل مطابق همین نتیجه هستند.';
   const totalReceipt=Number(totals?.receipts||0),totalPayment=Number(totals?.payments||0);
   $('ledgerFilterSummary').textContent=number(total)+' سند در گزارش';
   const agencyName=currentAgency?.name||'سرویس‌یار';
   const range=[form.elements.dateFrom.value?dateText(form.elements.dateFrom.value):'ابتدای سوابق',form.elements.dateTo.value?dateText(form.elements.dateTo.value):'پایان سوابق'].join(' تا ');
   $('ledgerReportBody').innerHTML=`<article class="ledger-print-sheet"><div class="ledger-report-letterhead sy-ledger-print-letterhead"><div><strong>سرویس‌یار | ${esc(agencyName)}</strong><h3>گزارش دریافت‌ها و پرداخت‌ها</h3><p>بازه گزارش: ${esc(range)}</p></div><span>گزارش مالی سازمانی</span></div><div class="ledger-report-kpis"><div><span>تعداد اسناد</span><strong>${number(total)}</strong></div><div><span>جمع دریافت‌ها (ریال)</span><strong>${number(totalReceipt)}</strong></div><div><span>جمع پرداخت‌ها (ریال)</span><strong>${number(totalPayment)}</strong></div><div><span>خالص دریافت و پرداخت (ریال)</span><strong>${number(totalReceipt-totalPayment)}</strong></div></div><div class="table-wrap sy-ledger-report-table-wrap" tabindex="0" role="region" aria-label="جدول گزارش دریافت‌ها و پرداخت‌ها"><table class="ledger-report-table"><thead><tr><th scope="col">ردیف</th><th scope="col">تاریخ</th><th scope="col">نوع</th><th scope="col">نام طرف حساب</th><th scope="col">گروه</th><th scope="col">دریافت (ریال)</th><th scope="col">پرداخت (ریال)</th><th scope="col">روش</th><th scope="col">حساب شرکت</th><th scope="col">شماره پیگیری</th><th scope="col">توضیحات</th></tr></thead><tbody>${items.map((r,i)=>`<tr><td data-label="ردیف">${number(i+1)}</td><td data-label="تاریخ">${esc(dateText(r.entry_date))}</td><td data-label="نوع">${r.direction==='receipt'?'دریافت':'پرداخت'}</td><td data-label="نام طرف حساب">${esc(r.party_name||'—')}</td><td data-label="گروه">${esc(names[r.party_type]||r.party_type||'—')}</td><td data-label="دریافت (ریال)" class="sy-ledger-money">${r.direction==='receipt'?number(r.amount):'—'}</td><td data-label="پرداخت (ریال)" class="sy-ledger-money">${r.direction==='payment'?number(r.amount):'—'}</td><td data-label="روش">${esc(methods[r.method]||r.method||'—')}</td><td data-label="حساب شرکت">${esc(r.company_account_name||'در سند قدیمی ثبت نشده')}</td><td data-label="شماره پیگیری">${esc(r.reference_no||'—')}</td><td data-label="توضیحات">${esc(r.note||'—')}</td></tr>`).join('')||'<tr><td colspan="11" class="sy-ledger-no-results">سندی مطابق فیلترها یافت نشد؛ معیارهای جست‌وجو را تغییر دهید.</td></tr>'}</tbody></table></div><footer class="ledger-report-footer"><span>تهیه‌شده در سامانه سرویس‌یار</span><span>تعداد کل: ${number(total)} سند</span></footer></article>`;
   $('ledgerReportPrint').disabled=!total;$('ledgerReportExcel').disabled=!total;
  }catch(e){if(current===version)$('ledgerReportFreshness').textContent='تهیه گزارش با خطا مواجه شد؛ پس از اصلاح یا تلاش مجدد خروجی فعال می‌شود.';if(current===version)$('ledgerReportBody').innerHTML='<div class="sy-ledger-report-state is-error" role="alert"><strong>تهیه گزارش انجام نشد.</strong><p>'+esc(e.message)+'</p><button type="button" id="ledgerReportRetry">تلاش مجدد</button></div>';if(current===version)$('ledgerReportRetry')?.addEventListener('click',render);}
  finally{if(current===version&&token===pageToken)$('ledgerReportBody')?.setAttribute('aria-busy','false');}
 };
 form.onsubmit=e=>{e.preventDefault();render();};
 form.onreset=()=>setTimeout(()=>{window.__ledgerReportFilters='';form.querySelector('details')?.removeAttribute('open');render();},0);
 // The report button submits the filter form, avoiding duplicate API requests.
 $('ledgerReportPrint').onclick=()=>{if(ready)window.print();};
 $('ledgerReportExcel').onclick=()=>{if(ready&&exportQuery)downloadFile('/api/v1/reports/ledger/export?'+exportQuery,'گزارش-دریافت-پرداخت.xlsx');};
 await render();
}
const resourceSortFields={
 schools:[['id','جدیدترین ثبت'],['name','نام مدرسه'],['code','کد مدرسه'],['region','ناحیه']],
 students:[['id','جدیدترین ثبت'],['last_name','نام خانوادگی'],['national_code','کد ملی'],['monthly_fee','شهریه ماهانه'],['school_id','مدرسه']],
 drivers:[['id','جدیدترین ثبت'],['last_name','نام خانوادگی'],['mobile','شماره همراه'],['status','وضعیت']],
 vehicles:[['id','جدیدترین ثبت'],['plate','شماره پلاک'],['model','مدل خودرو'],['capacity','ظرفیت']],
 services:[['id','جدیدترین ثبت'],['title','عنوان سرویس'],['capacity','ظرفیت'],['status','وضعیت']],
 ledger:[['id','جدیدترین ثبت'],['entry_date','تاریخ'],['amount','مبلغ'],['direction','نوع تراکنش']],
 invoices:[['id','جدیدترین ثبت'],['issue_date','تاریخ صدور'],['amount','مبلغ'],['status','وضعیت']],
 payments:[['id','جدیدترین ثبت'],['payment_date','تاریخ پرداخت'],['amount','مبلغ']],
 'driver-work':[['id','جدیدترین ثبت'],['work_date','تاریخ کارکرد'],['amount','مبلغ'],['status','وضعیت']],
 'driver-payments':[['id','جدیدترین ثبت'],['payment_date','تاریخ پرداخت'],['amount','مبلغ']],
 contracts:[['id','جدیدترین ثبت'],['title','عنوان'],['start_date','شروع'],['amount','مبلغ']],
 accounts:[['id','جدیدترین ثبت'],['code','کد حساب'],['title','عنوان حساب']],
 'accounting-documents':[['id','جدیدترین ثبت'],['document_date','تاریخ سند'],['total_amount','مبلغ کل']],
 'school-charges':[['id','جدیدترین ثبت'],['due_date','سررسید'],['amount','مبلغ']],
 'service-charges':[['id','جدیدترین ثبت'],['due_date','سررسید'],['amount','مبلغ']],
 packs:[['id','جدیدترین ثبت'],['title','عنوان پک'],['capacity','ظرفیت'],['status','وضعیت']],
 wallets:[['id','جدیدترین ثبت'],['balance','موجودی']],
 messages:[['id','جدیدترین ثبت'],['subject','موضوع'],['status','وضعیت ارسال']],
 'driver-changes':[['id','جدیدترین ثبت'],['status','وضعیت بررسی']],
 'driver-ratings':[['id','جدیدترین ثبت'],['score','امتیاز']],
 'parent-reports':[['id','جدیدترین ثبت'],['subject','موضوع'],['status','وضعیت']]
};
const sortDataField={fullName:'last_name',firstName:'first_name',lastName:'last_name',nationalCode:'national_code',monthlyFee:'monthly_fee',schoolId:'school_id',entryDate:'entry_date',paymentDate:'payment_date',workDate:'work_date',issueDate:'issue_date',documentDate:'document_date',totalAmount:'total_amount',dueDate:'due_date',contractNo:'contract_no',vehicleId:'vehicle_id'};
async function contractsPage(token=pageToken){
 const root=$('content');
 root.innerHTML='<section class="panel sy-contract-workspace"><header class="sy-settings-section-head"><div><h2>مدیریت قراردادهای شرکت</h2><p>قرارداد جدید با اطلاعات ثبت‌شده در شرکت صادر می‌شود؛ نسخه چاپی قراردادهای صادرشده ثابت می‌ماند.</p></div></header>'+
  '<div class="sy-contract-tabs" role="tablist" aria-label="نوع قرارداد"><button class="primary" type="button" data-contract-kind="parent" role="tab" aria-selected="true">قرارداد با ولی دانش‌آموز</button><button type="button" data-contract-kind="driver" role="tab" aria-selected="false">قرارداد با راننده</button></div>'+
  '<div id="syContractBody" role="tabpanel"></div></section>';
 const tabs=[...root.querySelectorAll('[data-contract-kind]')],body=$('syContractBody');
 let kind='parent',serial=0;
 const render=async()=>{
  const request=++serial;
  tabs.forEach(t=>{const active=t.dataset.contractKind===kind;t.classList.toggle('primary',active);t.setAttribute('aria-selected',active?'true':'false');});
  body.innerHTML='<p class="loading">در حال دریافت قراردادها…</p>';
  try{
   const [docs,people]=await Promise.all([
    api(scoped('/api/v1/contract-documents/'+kind)),
    lookup(kind==='parent'?'students':'drivers')
   ]);
   if(request!==serial||token!==pageToken||!body.isConnected)return;
   const fmtDate=v=>v?dateText(v):'—';
   const available=(people||[]).filter(p=>Number(p.is_active??1)!==0);
   const personLabel=p=>kind==='parent'
     ?[p.first_name,p.last_name].filter(Boolean).join(' ')+(p.national_code?' · '+persianDigits(p.national_code):'')
     :[p.first_name,p.last_name].filter(Boolean).join(' ')+(p.national_code?' · '+persianDigits(p.national_code):'');
   body.innerHTML=(can('contracts','manage')?'<dialog class="sy-contract-new-dialog" aria-labelledby="syContractNewTitle"><header class="sy-contract-new-head"><h3 id="syContractNewTitle">ثبت قرارداد جدید '+(kind==='parent'?'با ولی دانش‌آموز':'با راننده')+'</h3><button type="button" data-close-contract aria-label="بستن">×</button></header>'+
    '<form id="syContractCreateForm" class="form-grid sy-contract-create-grid">'+
    '<label>'+(kind==='parent'?'دانش‌آموز':'راننده')+'<input type="search" id="syContractPersonSearch" placeholder="جستجوی نام یا کد ملی" autocomplete="off"><select name="partyId" required><option value="">انتخاب کنید</option>'+available.map(p=>'<option value="'+Number(p.id)+'">'+esc(personLabel(p))+'</option>').join('')+'</select></label>'+
    '<label>تاریخ شروع<input name="startDate" type="date" required></label><label>تاریخ پایان<input name="endDate" type="date" required></label>'+
    '<label>'+(kind==='driver'?'مبلغ خالص سالانه قرارداد راننده (ریال)':'مبلغ کل قرارداد (ریال)')+'<input name="amount" type="text" inputmode="numeric" required '+'readonly aria-readonly="true" '+'placeholder="'+(kind==='driver'?'محاسبه خودکار پس از انتخاب راننده':'مبلغ صحیح بدون اعشار')+'"></label>'+
    '<section class="sy-contract-draft-data" id="syContractDraftData" aria-live="polite">برای مشاهده و تکمیل اطلاعات طرفین، ابتدا شخص را انتخاب کنید.</section>'+
    '<div class="sy-contract-create-actions"><button class="primary" type="submit">صدور و ثبت قرارداد</button><span id="syContractCreateStatus" aria-live="polite"></span></div></form></dialog>':'')+
    '<header class="sy-settings-section-head"><div><h3>قراردادهای صادرشده</h3><p>مشاهده و چاپ نسخه ذخیره‌شده قراردادها.</p></div>'+(can('contracts','manage')?'<button type="button" class="primary" data-new-contract>＋ قرارداد جدید</button>':'')+'</header>'+
    '<form class="sy-contract-filters"><label>جست‌وجوی قرارداد<input name="search" type="search" autocomplete="off" placeholder="نام، شماره قرارداد، عنوان یا کد ملی"></label><label>وضعیت<select name="status"><option value="">همه وضعیت‌ها</option><option value="active">فعال</option><option value="cancelled">ابطال‌شده</option></select></label><label>نسخه چاپی<select name="printable"><option value="">همه</option><option value="1">دارای نسخه چاپی</option><option value="0">فاقد نسخه چاپی</option></select></label><label>شروع از<input type="date" name="from"></label><label>شروع تا<input type="date" name="to"></label><div class="sy-contract-filter-actions"><button type="reset">پاک کردن فیلترها</button><span data-contract-count role="status"></span></div></form>'+
    '<div class="table-wrap sy-contract-list"><table><thead><tr><th>شماره</th><th>عنوان قرارداد</th><th>تاریخ شروع</th><th>تاریخ پایان</th><th>مبلغ اصلی (ریال)</th><th>تعدیل کلاس (ریال)</th><th>مبلغ اصلاح‌شده (ریال)</th><th>عملیات</th></tr></thead><tbody>'+
    (docs.length?docs.map(c=>'<tr><td>'+esc(persianDigits(c.contract_no||'—'))+'</td><td>'+esc(c.title||'—')+'</td><td>'+esc(fmtDate(c.start_date))+'</td><td>'+esc(fmtDate(c.end_date))+'</td><td>'+number(c.original_amount??c.amount)+'</td><td>'+number(c.class_adjustment_amount||0)+'</td><td><strong>'+number(c.effective_amount??c.amount)+'</strong></td><td><button type="button" data-contract-print="'+Number(c.id)+'" data-contract-legacy="'+(Number(c.printable)?'0':'1')+'">'+'چاپ مجدد'+'</button>'+(c.status==='cancelled'?'<span class="muted">ابطال‌شده</span>':(can('contracts','manage')&&can('finance','manage')?'<button type="button" data-contract-edit="'+Number(c.id)+'">ویرایش</button><button type="button" class="danger" data-contract-delete="'+Number(c.id)+'">حذف</button>':''))+'</td></tr>').join(''):'<tr><td colspan="8">قراردادی در این بخش ثبت نشده است.</td></tr>')+
    '</tbody></table></div>';
   const filters=body.querySelector('.sy-contract-filters');
   const normalize=value=>String(ServiceYarDates.digits(String(value??''))).replace(/[يى]/g,'ی').replace(/ك/g,'ک').replace(/[\s\u200c]+/g,'').toLowerCase();
   const rows=[...body.querySelectorAll('.sy-contract-list tbody tr')];
   const peopleById=new Map(people.map(p=>[Number(p.id),p]));
   const searchable=docs.map(c=>{const p=peopleById.get(Number(c.party_id));return normalize([c.title,c.contract_no,p?.first_name,p?.last_name,p?.national_code].join(' '));});
   const empty=document.createElement('tr');empty.hidden=true;empty.innerHTML='<td colspan="8">قراردادی با این فیلترها پیدا نشد.</td>';body.querySelector('.sy-contract-list tbody').append(empty);
   const updateFilters=()=>{const values=new FormData(filters),term=normalize(values.get('search')),from=values.get('from'),to=values.get('to');let count=0;
    docs.forEach((c,i)=>{const visible=(!term||searchable[i].includes(term))&&(!values.get('status')||c.status===values.get('status'))&&(!values.get('printable')||String(Number(c.printable))===values.get('printable'))&&(!from||String(c.start_date)>=from)&&(!to||String(c.start_date)<=to);rows[i].hidden=!visible;if(visible)count++;});
    empty.hidden=count>0||!docs.length;filters.querySelector('[data-contract-count]').textContent=number(count)+' قرارداد از '+number(docs.length);
   };
   filters.addEventListener('input',updateFilters);filters.addEventListener('change',updateFilters);filters.addEventListener('submit',e=>{e.preventDefault();updateFilters();});filters.addEventListener('reset',()=>setTimeout(updateFilters,0));updateFilters();
   const newDialog=body.querySelector('.sy-contract-new-dialog');
   body.querySelector('[data-new-contract]')?.addEventListener('click',()=>newDialog.showModal());
   newDialog?.querySelector('[data-close-contract]')?.addEventListener('click',()=>newDialog.close());
   const form=body.querySelector('#syContractCreateForm');
   if(form){
    const search=form.querySelector('#syContractPersonSearch'),select=form.elements.partyId;
    search.addEventListener('input',()=>{
     const term=String(ServiceYarDates.digits(search.value)).replace(/\s+/g,'').toLocaleLowerCase('fa');
     [...select.options].forEach(option=>{
      if(!option.value)return;
      const norm=ServiceYarDates.digits(option.textContent).replace(/\s+/g,'').toLocaleLowerCase('fa');
      option.hidden=term!==''&&!norm.includes(term);
     });
    });
    const money=form.elements.amount;
    money.addEventListener('input',()=>{const raw=ServiceYarDates.digits(money.value).replace(/\D/g,'').slice(0,15);money.value=persianDigits(raw.replace(/\B(?=(\d{3})+(?!\d))/g,'٬'));});
    const draftBox=form.querySelector('#syContractDraftData');
    const detailFields=kind==='parent'?[
     ['guardianName','نام و نام خانوادگی ولی'],['guardianNationalCode','کد ملی ولی'],
     ['guardianFatherName','نام پدر ولی'],['guardianMobile','شماره همراه ولی'],
     ['guardianAddress','نشانی ولی'],['managerName','نام مدیر شرکت'],['managerTitle','سمت مدیر شرکت'],
     ['managerNationalCode','کد ملی مدیر شرکت'],['companyRegistration','شماره ثبت شرکت'],
     ['companyNationalId','شناسه ملی شرکت'],['companyAddress','نشانی شرکت'],
     ['companyPhone','تلفن شرکت'],['bankName','نام بانک شرکت'],['bankAccount','شماره حساب شرکت'],
     ['academicYear','سال تحصیلی']
    ]:[
     ['managerName','نام مدیر شرکت'],['managerNationalCode','کد ملی مدیر'],
     ['companyAddress','نشانی شرکت'],['companyPhone','تلفن شرکت']
    ];
    let draftRequest=0;
    const loadDraft=async()=>{
     const partyId=Number(select.value),current=++draftRequest;
     draftBox.replaceChildren();
     if(!partyId){draftBox.textContent='ابتدا شخص را انتخاب کنید.';return;}
     draftBox.textContent='در حال تطبیق اطلاعات پرونده…';
     try{
      const result=await api(scoped('/api/v1/contract-documents/'+kind+'/draft?partyId='+partyId));
      if(current!==draftRequest||!form.isConnected||Number(select.value)!==partyId)return;
      const title=document.createElement('h4');title.textContent='بررسی و تکمیل اطلاعات طرفین قرارداد';
      const description=document.createElement('p');
      description.textContent=result.missing?.length?'برخی اطلاعات اصلی تکمیل نشده‌اند؛ پیش از صدور آن‌ها را بررسی و وارد کنید.':'اطلاعات از پرونده‌ها فراخوانی شد؛ قبل از صدور صحت آن‌ها را بررسی کنید.';
      const grid=document.createElement('div');grid.className='sy-contract-draft-grid';
      for(const [key,label] of detailFields){
       const field=document.createElement('label');field.textContent=label;
       const input=document.createElement('input');input.name='override_'+key;input.maxLength=500;
       input.value=String(result.values?.[key]??'');
       input.placeholder=String(result.missing?.includes(key)?'تکمیل این مورد ضروری است':'در صورت نیاز ویرایش کنید');
       if(result.missing?.includes(key))field.classList.add('is-missing');
       field.append(input);grid.append(field);
      }
      draftBox.replaceChildren(title,description,grid);
      if(kind==='parent'&&result.period){form.elements.startDate.value=result.period.startDate;form.elements.endDate.value=result.period.endDate;}
      if(Number(result.suggestedTotal)>=0)
       money.value=persianDigits(String(result.suggestedTotal).replace(/\B(?=(\d{3})+(?!\d))/g,'٬'));
      if(kind==='driver'&&result.driverAnnual){
       const data=result.driverAnnual,rateText=Object.entries(data.rates||{}).map(([k,v])=>({supervision:'مدیریت، نظارت و بازرسی',coordination:'اطلاع‌رسانی و هماهنگی',overhead:'بالاسری شرکت',insurance:'بیمه'}[k]||k)+': '+persianDigits(v)+'٪').join(' | ');
       const hint=document.createElement('div');hint.className='sy-driver-contract-breakdown';
       const lead=document.createElement('p');lead.textContent='مجموع ناخالص سالانه: '+number(data.grossAnnual)+' ریال | مجموع کسورات: '+number(data.deductionsAnnual)+' ریال | مبلغ خالص قرارداد: '+number(data.netAnnual)+' ریال';
       const rates=document.createElement('p');rates.textContent='کسورات مطابق تنظیمات شرکت: '+rateText+'؛ مبلغ سرویس‌های خالص دوباره مشمول کسر نمی‌شود.';
       hint.append(lead,rates);
       for(const service of data.services||[]){
        const item=document.createElement('p');
        item.textContent=service.serviceTitle+' · '+persianDigits(service.months)+' ماه · '+persianDigits(service.students)+' دانش‌آموز · خالص سالانه '+number(service.netAnnual)+' ریال';
        hint.append(item);
       }
       draftBox.append(hint);
      }
      if(kind==='parent'){
       const hint=document.createElement('p');hint.className='muted';
       hint.id='syContractPeriodHint';hint.textContent='مدت قرارداد: '+number(result.period?.days||0)+' روز مبنای مالی؛ فاکتور پس از صدور خودکار ثبت می‌شود.';
       draftBox.append(hint);
      }
     }catch(error){if(current===draftRequest&&draftBox.isConnected)draftBox.textContent='دریافت اطلاعات قرارداد ناموفق بود: '+error.message;}
    };
    select.addEventListener('change',()=>{money.value='';void loadDraft();});
    let quoteRevision=0;
    const quotePeriod=async()=>{
     if(kind!=='parent'||!select.value||!form.elements.startDate.value||!form.elements.endDate.value)return;
     const revision=++quoteRevision,party=select.value,start=form.elements.startDate.value,end=form.elements.endDate.value;
     money.value='';
     try{
      const result=await api(scoped('/api/v1/contract-documents/parent/draft?'+new URLSearchParams({partyId:party,startDate:start,endDate:end})));
      if(revision!==quoteRevision||!form.isConnected||select.value!==party||form.elements.startDate.value!==start||form.elements.endDate.value!==end)return;
      money.value=number(result.suggestedTotal);
      const hint=form.querySelector('#syContractPeriodHint');if(hint)hint.textContent='مدت قرارداد: '+number(result.period.days)+' روز مبنای مالی؛ فاکتور پس از صدور خودکار ثبت می‌شود.';
     }catch(error){if(revision===quoteRevision&&form.isConnected){const hint=form.querySelector('#syContractPeriodHint');if(hint)hint.textContent=error.message;}}
    };
    if(kind==='parent')for(const f of [form.elements.startDate,form.elements.endDate])f.addEventListener('change',()=>void quotePeriod());

    form.onsubmit=async e=>{
     e.preventDefault();const submit=form.querySelector('[type=submit]'),status=form.querySelector('#syContractCreateStatus');
     const amount=ServiceYarDates.digits(money.value).replace(/\D/g,'');
     if(!form.reportValidity()||!amount)return;
     submit.disabled=true;status.textContent='در حال صدور قرارداد…';
     try{
      const overrides={};
      form.querySelectorAll('[name^="override_"]').forEach(input=>{overrides[input.name.slice(9)]=input.value.trim();});
      if(!Object.keys(overrides).length)throw Error('ابتدا اطلاعات طرفین قرارداد را دریافت و بررسی کنید.');
      const record=await write('/api/v1/contract-documents/'+kind,{partyId:Number(select.value),startDate:form.elements.startDate.value,endDate:form.elements.endDate.value,...(kind==='parent'?{amount}:{}),overrides});
      status.textContent='قرارداد '+persianDigits(record.contractNo)+' صادر شد.'+(record.invoiceId?' فاکتور '+number(record.invoiceId)+' ثبت شد.':'');
      notify(status.textContent);
      newDialog?.close();await render();
     }catch(error){status.textContent=error.message;submit.disabled=false;}
    };
   }
   body.querySelectorAll('[data-contract-delete]').forEach(button=>button.onclick=async()=>{
    const id=Number(button.dataset.contractDelete),record=docs.find(c=>Number(c.id)===id);
    if(!window.confirm('قرارداد '+persianDigits(record?.contract_no||id)+' ابطال شود؟ فاکتور بدون پرداخت مرتبط نیز ابطال می‌شود. نسخه قبلی برای سوابق باقی می‌ماند.'))return;
    button.disabled=true;
    try{let result;
     try{result=await write('/api/v1/contract-documents/'+id,{},'DELETE');}
     catch(error){
      if(!String(error.message).includes('فاکتور قطعی این قرارداد قدیمی مشخص نیست'))throw error;
      if(!window.confirm(error.message+'\nآیا فقط قرارداد ابطال شود؟')){button.disabled=false;return;}
      result=await write('/api/v1/contract-documents/'+id,{contractOnly:true},'DELETE');
     }
     notify(result.legacyInvoicesPreserved?'قرارداد ابطال شد؛ فاکتورها و بدهی قبلی بدون تغییر باقی ماند.':'قرارداد ابطال شد.');await render();
    }catch(error){notify(error.message,true);button.disabled=false;}
   });
   body.querySelectorAll('[data-contract-edit]').forEach(button=>button.onclick=async()=>{
    try{
     const id=Number(button.dataset.contractEdit),record=await api(scoped('/api/v1/contract-documents/'+id+'/edit'));
     const dialog=document.createElement('dialog');dialog.className='sy-contract-edit-dialog';
     dialog.innerHTML='<form class="sy-contract-edit-form"><h3>ویرایش قرارداد '+esc(persianDigits(record.contract_no))+'</h3><label>تاریخ شروع<input type="date" name="startDate" required></label><label>تاریخ پایان<input type="date" name="endDate" required></label><div class="sy-contract-edit-amount-summary"><p><strong>مبلغ اولیه قرارداد:</strong> '+number(record.original_amount??record.amount)+' ریال</p><p><strong>تعدیل تأییدشده کلاس خودرو:</strong> '+number(record.class_adjustment_amount||0)+' ریال</p><p><strong>مبلغ اصلاح‌شده قرارداد:</strong> '+number(record.effective_amount??record.amount)+' ریال</p></div><p class="sy-contract-edit-note">مبلغ اصلاح‌شده از اسناد تعدیل تأییدشده محاسبه می‌شود. اصل قرارداد و پرداخت‌های قبلی برای حسابرسی حفظ شده‌اند.</p><div class="sy-contract-draft-grid"></div><p role="alert" data-edit-error hidden></p><footer class="sy-contract-edit-actions"><button class="primary" type="submit">ذخیره ویرایش</button><button type="button" data-cancel>انصراف</button></footer></form>';
     document.body.append(dialog);const form=dialog.querySelector('form');form.elements.startDate.value=record.start_date;form.elements.endDate.value=record.end_date;
     const fields=record.kind==='parent'?[['guardianName','نام ولی'],['guardianNationalCode','کد ملی ولی'],['guardianFatherName','نام پدر ولی'],['guardianMobile','همراه ولی'],['guardianAddress','نشانی ولی'],['bankName','بانک'],['bankAccount','شماره حساب']]:[];
     fields.push(['companyName','شرکت'],['managerName','مدیر'],['managerTitle','سمت'],['managerNationalCode','کد ملی مدیر'],['companyRegistration','شماره ثبت'],['companyNationalId','شناسه ملی'],['companyAddress','نشانی شرکت'],['companyPhone','تلفن شرکت']);
     for(const [key,label] of fields){const l=document.createElement('label');l.textContent=label;const input=document.createElement('input');input.name='override_'+key;input.value=record.values?.[key]??'';input.maxLength=500;l.append(input);dialog.querySelector('.sy-contract-draft-grid').append(l);}
     dialog.querySelector('[data-cancel]').onclick=()=>dialog.close();dialog.addEventListener('close',()=>dialog.remove(),{once:true});
     form.onsubmit=async e=>{e.preventDefault();const startDate=form.elements.startDate.value,endDate=form.elements.endDate.value;const datesChanged=startDate!==record.start_date||endDate!==record.end_date;const confirmed=!datesChanged||window.confirm('تاریخ قرارداد تغییر کرده است. آیا می‌خواهید مبلغ قرارداد و فاکتور مرتبط با آن نیز براساس مدت جدید محاسبه و اصلاح شود؟\n\nپرداخت‌های قبلی حفظ می‌شوند و نسخه قبلی قرارداد در سوابق باقی می‌ماند.\n\nدر صورت انتخاب انصراف، هیچ تغییری ثبت نمی‌شود.');if(!confirmed)return;const save=form.querySelector('[type=submit]');save.disabled=true;try{const overrides={};for(const input of form.querySelectorAll('[name^="override_"]'))overrides[input.name.slice(9)]=input.value.trim();const result=await write('/api/v1/contract-documents/'+id+'/edit',{startDate,endDate,overrides,confirmInvoiceUpdate:datesChanged&&confirmed},'PUT');dialog.close();notify(datesChanged?'تاریخ و مبلغ قرارداد و فاکتور مرتبط اصلاح شد.':'قرارداد ویرایش شد.');await render();}catch(error){const alert=dialog.querySelector('[data-edit-error]');alert.hidden=false;alert.textContent=error.message;alert.scrollIntoView({block:'nearest'});save.disabled=false;}};
     dialog.showModal();
    }catch(error){notify(error.message,true);}
   });
   body.querySelectorAll('[data-contract-print]').forEach(button=>button.onclick=async()=>{
    const printWindow=window.open('','_blank');
    if(!printWindow){notify('مرورگر پنجره چاپ را مسدود کرده است.',true);return;}
    printWindow.document.write('<html lang="fa" dir="rtl"><meta charset="utf-8"><body>در حال دریافت نسخه ذخیره‌شده قرارداد…</body></html>');printWindow.document.close();
    try{
     const record=await api(scoped('/api/v1/contract-documents/'+encodeURIComponent(button.dataset.contractPrint)+'/print'));
     // Reformat the stored document for display without rewriting its legal text or snapshot.
     const parsed=new DOMParser().parseFromString(record.html,'text/html');
     const parent=kind==='parent';
     parsed.querySelectorAll('style,link[rel="stylesheet"],script').forEach(el=>el.remove());
     const stylesheet=parsed.createElement('link');stylesheet.rel='stylesheet';
     stylesheet.href=new URL('/assets/contract-print.css?v=3',window.location.origin).href;parsed.head.append(stylesheet);
     parsed.body.className=parent?'contract-parent':'contract-driver';
     if(!parsed.querySelector('.contract-sheet')){
      const oldButton=parsed.body.querySelector('button');if(oldButton)oldButton.remove();
      const pages=[parsed.createElement('section')];pages[0].className='contract-sheet';
      for(const child of [...parsed.body.childNodes]){
       if(parent&&child.nodeName==='HR'){const page=parsed.createElement('section');page.className='contract-sheet';pages.push(page);child.remove();continue;}
       pages[pages.length-1].append(child);
      }
      const toolbar=parsed.createElement('div');toolbar.className='contract-toolbar';
      const print=parsed.createElement('button');print.type='button';print.id='syContractPrint';print.textContent='چاپ قرارداد / ذخیره PDF';toolbar.append(print);
      parsed.body.append(toolbar,...pages);
      // The old template placed whole articles in headings: separate heading and text.
      if(parent)for(const heading of parsed.querySelectorAll('.contract-sheet h2')){
       const match=heading.textContent.match(/^(ماده\s*[-–]?\s*(?:8|9|11|12|۸|۹|۱۱|۱۲)\s+[^:：]+[:：])\s*(.+)$/s);
       if(match){heading.textContent=match[1];const paragraph=parsed.createElement('p');paragraph.textContent=match[2];heading.after(paragraph);}
      }
      const top=parsed.querySelector('.contract-sheet .contract-top');
      if(top){const meta=top.querySelector('span');if(meta){const content=meta.textContent;const idx=content.indexOf('تاریخ:');if(idx>=0){meta.replaceChildren(parsed.createTextNode(content.slice(0,idx).trim()),parsed.createElement('br'),parsed.createTextNode(content.slice(idx)));}}}
     }
     for(const el of parsed.querySelectorAll('[onclick]'))el.removeAttribute('onclick');
     parsed.querySelectorAll('link[rel="stylesheet"]').forEach(link=>link.href=new URL('/assets/contract-print.css?v=4',window.location.origin).href);
     printWindow.document.open();printWindow.document.write('<!doctype html>'+parsed.documentElement.outerHTML);printWindow.document.close();
     const printStyle=printWindow.document.querySelector('link[rel="stylesheet"]');
     if(printStyle)printStyle.addEventListener('error',()=>notify('بارگذاری قالب چاپ ناموفق بود؛ صفحه را بازخوانی کنید.',true));
     const printButton=printWindow.document.getElementById('syContractPrint')||printWindow.document.querySelector('button');
     if(printButton)printButton.addEventListener('click',async()=>{await printWindow.document.fonts?.ready;printWindow.focus();printWindow.print();});
     printWindow.focus();
    }catch(error){printWindow.close();notify('چاپ قرارداد: '+error.message,true);}
   });
  }catch(error){if(request===serial&&body.isConnected)body.innerHTML='<p role="alert" class="error">'+esc(error.message)+'</p>';}
 };
 tabs.forEach(t=>t.onclick=()=>{kind=t.dataset.contractKind;void render();});
 await render();
}
async function resourcePage(key,token=pageToken,options={}){
 gridState={key,token,page:1,limit:25,rows:[],selected:new Set(),previous:options.previous};
 if(key==='ledger'){try{agencyMethodChoices=await api(scoped('/api/v1/finance/payment-methods'));}catch(e){notify(e.message,true);}if(token!==pageToken)return;}
 const schoolFilter=['students','services'].includes(key)&&can('schools'),isStudents=key==='students',isDrivers=key==='drivers',isVehicles=key==='vehicles',isLedger=key==='ledger';
 const pageIntroductions={
 students:['پرونده دانش‌آموزان','جست‌وجو بر اساس نام، مدرسه، وضعیت سرویس و سال تحصیلی.'],
 drivers:['ناوگان و رانندگان','بررسی مشخصات رانندگان، وضعیت فعالیت و تخصیص سرویس.'],
 vehicles:['مدیریت خودروها','جست‌وجوی پلاک ایران، مشخصات خودرو و راننده مرتبط.'],
 schools:['مدیریت مدارس','فهرست مدارس، شیفت‌ها و اطلاعات ثبت‌شده.'],
 ledger:['دریافت و پرداخت','مدیریت دریافت‌ها و پرداخت‌های مالی، ثبت، پیگیری و گزارش‌گیری از تراکنش‌ها.'],
 payments:['پرداخت‌ها','پیگیری مبالغ و شماره پیگیری پرداخت‌های ثبت‌شده.'],
 invoices:['مدیریت و صدور فاکتورها','بررسی مطالبات، دریافتی مستقیم و وضعیت فاکتورهای صادرشده.'],
 'driver-payments':['پرداخت به رانندگان','بررسی پرداخت‌های انجام‌شده و اطلاعات سند مالی.'],
 'driver-work':['کارکرد رانندگان','کنترل سوابق کارکرد، تاریخ و وضعیت بررسی.'],
 'school-charges':['مطالبات مدارس','بررسی بدهی‌ها، سررسیدها و وضعیت ثبت.'],
 'service-charges':['هزینه سرویس‌ها','مدیریت هزینه‌های مربوط به هر سرویس.'],
 contracts:['قراردادها','جست‌وجو، بررسی و به‌روزرسانی قراردادهای ثبت‌شده.'],
 accounts:['حساب‌های مالی','بررسی کد حساب، عنوان و ساختار حساب‌های شرکت.'],
 'accounting-documents':['اسناد حسابداری','مشاهده اسناد، وضعیت ثبت و عملیات حسابداری.'],
 wallets:['کیف پول','بررسی مانده و گردش حساب‌های کیف پول.'],
 messages:['پیام‌ها','مشاهده پیام‌ها، وضعیت ارسال و گیرندگان.'],
 packs:['پک‌های سرویس','مدیریت بسته‌های سرویس و ظرفیت آنها.'],
 'driver-changes':['تغییر راننده','بررسی درخواست‌های جایگزینی راننده و نتیجه تصمیم‌ها.'],
 'driver-ratings':['ارزیابی راننده','ثبت و مشاهده امتیازها و گزارش‌های مرتبط.'],
 'parent-reports':['گزارش‌های اولیا','بررسی گزارش‌ها و پیام‌های ثبت‌شده والدین.']
};
 const pageIntro=pageIntroductions[key];
 $('content').innerHTML=`<section class="toolbar ${isLedger?'sy-ledger-page-header':''} ${key==='services'?'services-page-head':''}"><div class="${key==='services'?'services-page-title':'sy-directory-heading'}">${key==='services'?'<span class="services-eyebrow">مدیریت ناوگان و مسیرها</span>':pageIntro?`<span class="sy-directory-eyebrow">${esc(pageIntro[0])}</span>`:''}<h2>${resourceName(key)}</h2>${key==='services'?'<p>جست‌وجو، بررسی و مدیریت یکپارچه سرویس‌های مدارس</p>':pageIntro?`<p>${esc(pageIntro[1])}</p>`:''}</div><div class="toolbar-buttons">${can(key,'manage')?`<button class="primary" id="addBtn">${key==='students'?'دانش‌آموز جدید':key==='drivers'?'ثبت راننده جدید':'ثبت جدید'}</button>`:''}${['schools','students','drivers','services','invoices','payments','driver-work'].includes(key)&&can('reports')?'<button id="gridExport">خروجی Excel</button>':''}${isLedger&&can('reports')?'<button type="button" id="ledgerExport" class="sy-ledger-export">خروجی اکسل</button>':''}<button id="printGrid">چاپ</button></div></section>${isLedger?ledgerSummaryMarkup():''}${isStudents?'<div class="status-tabs" id="studentStatuses" role="group" aria-label="فیلتر وضعیت دانش‌آموز">'+['','no_request','pending','prepayment','waiting','assigned','cancelled'].map(k=>`<button data-status="${k}">${statuses[k]||'همه'}</button>`).join('')+'</div>':''}${isLedger&&can('reports')?'<nav class="ledger-report-tabs" aria-label="بخش‌های دریافت و پرداخت"><button type="button" class="active" aria-current="page">ثبت و مدیریت اسناد</button><button type="button" id="ledgerTabReport">گزارش‌گیری</button></nav>':''}<section class="panel ${isLedger?'sy-ledger-page-panel':''}">${key==='services'?serviceFilterForm(schoolFilter):isLedger?ledgerFilterForm():`<form id="gridFilters" class="filters ${isStudents||isDrivers?'directory-filters':''}" aria-label="جست‌وجو و فیلتر ${resourceName(key)}"><label class="directory-search">جست‌وجوی سریع<input name="search" id="search" type="search" placeholder="${isStudents?'نام، نام خانوادگی یا کد ملی دانش‌آموز':isDrivers?'نام، نام خانوادگی، کد ملی یا شماره همراه راننده':isVehicles?'مدل خودرو، نام مالک یا شماره پلاک':'جست‌وجو'}" aria-label="جست‌وجو" value="${esc(initialSearch)}"></label>${schoolFilter?'<label class="directory-filter-field">مدرسه<select name="schoolId" aria-label="فیلتر مدرسه"><option value="">همه مدارس</option></select></label>':''}${isVehicles?`<label class="vehicle-driver-filter">نام راننده<input type="search" name="driverSearch" placeholder="نام یا نام خانوادگی راننده" autocomplete="off"></label><fieldset class="vehicle-plate-filter"><legend>جست‌وجوی پلاک ایران</legend>${iranPlateInput("filter")}<small>هر بخش را جداگانه وارد کنید؛ بخش‌های خالی در جست‌وجو محدودیتی ایجاد نمی‌کنند.</small></fieldset>`:''}${isStudents?`<label class="directory-filter-field">سال تحصیلی<input name="academicYear" aria-label="سال تحصیلی" placeholder="مثلاً ${year()}" value="${options.previous?esc((Number(year().split('/')[0])-1)+'/'+year().split('/')[0]):''}"></label><label class="directory-filter-field">شماره همراه ولی<input name="mobile" type="search" inputmode="tel" placeholder="جست‌وجو با شماره همراه" aria-label="شماره همراه ولی"></label><input type="hidden" name="serviceStatus">`:''}${isDrivers?'<label>وضعیت راننده<select name="status"><option value="">همه وضعیت‌ها</option><option value="active">تأییدشده</option><option value="pending">در انتظار بررسی</option><option value="inactive">غیرفعال</option></select></label>':''}${key==='schools'?'<input name="schoolType" placeholder="نوع مدرسه" aria-label="نوع مدرسه">':''} ${key==='driver-work'?'<label>وضعیت بررسی<select name="status"><option value="">همه وضعیت‌ها</option><option value="approved">بررسی‌شده</option><option value="pending">نیاز به بررسی</option><option value="cancelled">لغوشده</option></select></label><label>کارکرد از<input name="dateFrom" type="date"></label><label>تا تاریخ<input name="dateTo" type="date"></label>':''}<label class="directory-filter-field">وضعیت فعالیت<select name="active" aria-label="فعال بودن"><option value="1">فعال</option><option value="0">غیرفعال</option><option value="">همه سوابق</option></select></label><button type="submit" class="primary directory-search-button">اعمال جست‌وجو و فیلتر</button><button type="reset" class="directory-reset-button">پاک‌کردن فیلترها</button></form>`}<div class="bulk-toolbar ${isLedger?'sy-ledger-bulk-toolbar':''}">${['students','drivers'].includes(key)&&can('messages','manage')?'<button id="bulkMessage">پیامک به انتخاب‌شده‌ها</button>':''}${isStudents&&can('services','manage')&&can('students','manage')?'<button id="bulkFees">تغییر سریع هزینه سرویس</button>':''}${key==='drivers'&&can('drivers','manage')?'<button class="primary" id="bulkDriverStatus" type="button">عملیات گروهی · فعال / غیرفعال</button>':''}${key==='services'&&can('services','manage')?'<button class="primary" id="bulkServiceStatus" type="button" disabled>تغییر وضعیت گروهی سرویس‌ها</button>':''}<span id="selectedCount"></span></div><div id="table" class="${isLedger?'sy-ledger-results':''}"></div></section><div id="modal"></div>`;
 if(isVehicles&&can('drivers','view')){
  const historyButton=document.createElement('button');
  historyButton.type='button';historyButton.id='vehicleClassHistory';historyButton.textContent='تاریخچه تغییر کلاس خودرو';
  $('content').querySelector('.toolbar-buttons')?.prepend(historyButton);
  historyButton.onclick=()=>void vehicleClassHistoryDialog();
 }
 if(key==='students'&&initialStudentStatus){$('gridFilters').elements.serviceStatus.value=initialStudentStatus;$('studentStatuses')?.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b.dataset.status===initialStudentStatus));initialStudentStatus='';}
 const filterForm=$('gridFilters'),state=gridState;
 let syncInvoiceSolarDates=()=>true;
 if(key==='invoices'){
  syncInvoiceSolarDates=()=>[...filterForm.querySelectorAll('.solar-input')].every(input=>input.checkValidity());
  filterForm.classList.add('sy-invoice-filters','sy-invoice-filter-v2');
  filterForm.innerHTML=`<div class="sy-invoice-filter-head"><div><span class="sy-invoice-eyebrow">مدیریت مطالبات و صورتحساب‌ها</span><h3>جست‌وجوی پیشرفته فاکتورها</h3><small>مبلغ قابل پرداخت شامل تعدیل‌های تأییدشده تغییر کلاس است؛ مبلغ اولیه فاکتور برای حسابرسی حفظ می‌شود.</small></div><span id="syInvoiceResultCount" role="status" aria-live="polite"></span></div>
   <div class="sy-invoice-quick"><label class="sy-invoice-search">جست‌وجوی فاکتور<div class="sy-invoice-search-box"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input name="search" type="search" autocomplete="off" maxlength="200" placeholder="نام طرف حساب، عنوان یا شماره فاکتور / قرارداد" value="${esc(initialSearch)}"></div></label>
   <label>نوع طرف حساب<select name="partyType"><option value="">همه طرف حساب‌ها</option><option value="student">دانش‌آموز</option><option value="driver">راننده</option><option value="school">مدرسه</option><option value="agency">شرکت</option></select></label></div>
   <fieldset class="sy-invoice-state"><legend>وضعیت پرداخت</legend><div class="sy-invoice-state-options">${[['','همه'],['open','باز'],['partial','پرداخت جزئی'],['paid','تسویه'],['overdue','سررسید گذشته'],['cancelled','ابطال‌شده']].map(([value,label])=>'<label><input type="radio" name="paymentState" value="'+value+'" '+(value===''?'checked':'')+'><span>'+label+'</span></label>').join('')}</div></fieldset>
   <details class="sy-invoice-advanced"><summary>فیلترهای پیشرفته <span id="syInvoiceAdvancedCount"></span></summary><div class="sy-invoice-advanced-grid"><fieldset><legend>بازه تاریخ صدور</legend><div class="sy-invoice-pair"><label>از تاریخ شمسی<input name="dateFrom" type="date" aria-label="تاریخ شروع صدور به شمسی"></label><label>تا تاریخ شمسی<input name="dateTo" type="date" aria-label="تاریخ پایان صدور به شمسی"></label></div><small class="sy-invoice-date-hint">تاریخ‌ها را به‌صورت سال/ماه/روز شمسی وارد کنید.</small></fieldset><fieldset><legend>بازه مبلغ (ریال)</legend><div class="sy-invoice-pair"><label>حداقل<input name="amountMin" type="text" inputmode="numeric" placeholder="بدون محدودیت"></label><label>حداکثر<input name="amountMax" type="text" inputmode="numeric" placeholder="بدون محدودیت"></label></div></fieldset><fieldset class="sy-invoice-flags"><legend>محدوده نتایج</legend><label><input type="checkbox" name="contractOnly" value="1"> فقط فاکتورهای قرارداد</label><label><input type="checkbox" name="remainingOnly" value="1"> فقط دارای مانده</label></fieldset></div></details>
   <div class="sy-invoice-sort"><label>مرتب‌سازی<select name="sortBy"><option value="id">شماره فاکتور</option><option value="issue_date">تاریخ صدور</option><option value="due_date">سررسید</option><option value="amount">مبلغ فاکتور</option><option value="title">عنوان فاکتور</option></select></label><label>ترتیب<select name="sortDir"><option value="desc">نزولی (جدیدترین / بیشترین)</option><option value="asc">صعودی (قدیمی‌ترین / کمترین)</option></select></label></div>
   <div class="sy-invoice-filter-footer"><div id="syInvoiceChips" aria-label="فیلترهای اعمال‌شده"></div><div class="sy-invoice-filter-actions"><button type="reset" class="sy-invoice-reset">پاک‌کردن فیلترها</button><button type="submit" class="primary">نمایش نتایج</button></div></div><p class="sy-invoice-filter-error" role="alert" hidden></p>`;
  filterForm.insertAdjacentHTML('beforebegin','<div class="sy-invoice-kpis" id="syInvoiceKpis" aria-live="polite"></div><p class="sy-invoice-finance-note">مانده هر فاکتور بر اساس پرداخت‌های مستقیم همان فاکتور است؛ دریافتی‌های آزاد در حساب مالی شخص محاسبه می‌شوند.</p>');
  const ascii=value=>String(value).replace(/[۰-۹]/g,c=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c))).replace(/[٠-٩]/g,c=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(c)));
  for(const name of ['amountMin','amountMax']){const input=filterForm.elements[name];input.addEventListener('input',()=>{const digits=ascii(input.value).replace(/[^0-9]/g,'').slice(0,15);input.value=digits?persianDigits(digits.replace(/\B(?=(\d{3})+(?!\d))/g,',')):'';});}
  filterForm.addEventListener('formdata',event=>{for(const name of ['amountMin','amountMax'])event.formData.set(name,ascii(filterForm.elements[name].value).replace(/[^0-9]/g,''));});

  filterForm.elements.sortBy.addEventListener('change',()=>applyFilters());
  filterForm.elements.sortDir.addEventListener('change',()=>applyFilters());
  filterForm.addEventListener('change',event=>{if(['partyType','paymentState','contractOnly','remainingOnly'].includes(event.target.name))applyFilters();});
  filterForm.querySelector('#syInvoiceChips').addEventListener('click',event=>{const button=event.target.closest('[data-invoice-clear]');if(!button)return;const field=filterForm.elements[button.dataset.invoiceClear];if(!field)return;if(button.dataset.invoiceClear==='paymentState')field.value='';else if(field.type==='checkbox')field.checked=false;else if(button.dataset.invoiceClear==='dateFrom'||button.dataset.invoiceClear==='dateTo')field.value='';else if(button.dataset.invoiceClear==='sortBy')field.value='id';else if(button.dataset.invoiceClear==='sortDir')field.value='desc';else field.value='';applyFilters();});

 }
 if(isStudents){
  filterForm.classList.add('sy-student-filter-v3');
  const original=filterForm.elements;
  const school=original.schoolId?.closest('label'),yearField=original.academicYear?.closest('label'),mobile=original.mobile?.closest('label'),active=original.active?.closest('label'),status=original.serviceStatus;
  const markup='<div class="sy-student-filter-head"><div><h3>جست‌وجو و فیلتر دانش‌آموزان</h3><small>نام، کد ملی یا مدرسه را جست‌وجو کنید.</small></div><strong id="syStudentResultCount" role="status"></strong></div><div class="sy-student-quick"><label>نام و نام خانوادگی دانش‌آموز<input type="search" name="studentName" autocomplete="off" placeholder="جست‌وجوی نام دانش‌آموز" value="'+esc(initialSearch)+'"></label><label>کد ملی<input type="search" name="nationalCode" inputmode="numeric" autocomplete="off" placeholder="کد ملی دانش‌آموز"></label><div data-student-school></div></div><fieldset class="sy-student-payment-filter"><legend>وضعیت پرداخت</legend><div class="sy-student-payment-options"><label><input type="radio" name="studentPaymentState" value="" checked><span>همه</span></label><label><input type="radio" name="studentPaymentState" value="debt"><span>بدهکار</span></label><label><input type="radio" name="studentPaymentState" value="credit"><span>بستانکار</span></label><label><input type="radio" name="studentPaymentState" value="settled"><span>تسویه‌شده</span></label><label><input type="radio" name="studentPaymentState" value="unbilled"><span>بدون فاکتور</span></label></div></fieldset><fieldset class="sy-student-payment-filter"><legend>وضعیت قرارداد</legend><div class="sy-student-payment-options"><label><input type="radio" name="studentContractState" value="" checked><span>همه</span></label><label><input type="radio" name="studentContractState" value="has_contract"><span>دارای قرارداد</span></label><label><input type="radio" name="studentContractState" value="no_contract"><span>فاقد قرارداد</span></label></div></fieldset><details class="sy-student-advanced"><summary>فیلترهای پیشرفته</summary><div class="sy-student-advanced-grid"><div data-student-year></div><div data-student-mobile></div><div data-student-active></div><label>حداقل مسافت (کیلومتر)<input type="number" name="distanceMin" min="0" max="1000" step="1" placeholder="بدون محدودیت"></label><label>حداکثر مسافت (کیلومتر)<input type="number" name="distanceMax" min="0" max="1000" step="1" placeholder="بدون محدودیت"></label></div></details><input type="hidden" name="sortBy" value="id"><input type="hidden" name="sortDir" value="desc"><div class="sy-student-filter-footer"><span id="syStudentFilterInfo"></span><div><button type="reset">پاک کردن فیلترها</button><button type="submit" class="primary">اعمال فیلترها</button></div></div><button type="button" hidden data-student-unused></button>';
  filterForm.innerHTML=markup;
  for(const [slot,node] of [['school',school],['year',yearField],['mobile',mobile],['active',active]]){const target=filterForm.querySelector('[data-student-'+slot+']');if(node)target.replaceWith(node);else target.remove();}
  if(status)filterForm.append(status);
  attachStudentLookup(filterForm.elements.studentName,'studentName');attachStudentLookup(filterForm.elements.nationalCode,'nationalCode');
 }

 const sortFields=resourceSortFields[key];
 if(sortFields&&filterForm&&!isStudents&&key!=='invoices'){
  const controls=document.createElement('div');controls.className='sy-grid-sort-controls';
  controls.innerHTML='<label>مرتب‌سازی<select name="sortBy" aria-label="ستون مرتب‌سازی">'+sortFields.map(([field,label])=>'<option value="'+field+'">'+esc(label)+'</option>').join('')+'</select></label>'+
   '<label>جهت مرتب‌سازی<select name="sortDir" aria-label="جهت مرتب‌سازی"><option value="desc">نزولی</option><option value="asc">صعودی</option></select></label>';
  if(isStudents){const direction=controls.querySelector('[name=sortDir]');direction.closest('label').hidden=true;direction.hidden=true;}
  const submit=filterForm.querySelector('button[type=submit]')||filterForm.querySelector('button.primary');
  if(isLedger){const advanced=filterForm.querySelector('.ledger-filter-more .ledger-filter-grid');if(advanced)advanced.append(controls);else filterForm.append(controls);}else if(submit)submit.before(controls);else filterForm.append(controls);
 }

 // Compact, accessible mobile filtering for the generic directory forms.
 // Preserve the original form controls and names, so existing API filters,
 // reset behavior and listeners work without any duplicated state.
 if(filterForm&&!['students','services','ledger','invoices'].includes(key)){
  const searchField=filterForm.querySelector('input[name="search"]')?.closest('label');
  const secondary=[...filterForm.children].filter(node=>
   node!==searchField&&node.matches('label,fieldset,.sy-grid-sort-controls'));
  if(searchField&&secondary.length>1){
   const details=document.createElement('details');
   details.className='sy-directory-advanced';
   details.open=!window.matchMedia('(max-width:760px)').matches;
   const summary=document.createElement('summary');
   summary.textContent='فیلترهای بیشتر';
   const fields=document.createElement('div');
   fields.className='sy-directory-advanced-fields';
   secondary.forEach(node=>fields.append(node));
   details.append(summary,fields);
   searchField.after(details);
   const updateSummary=()=>{
    let count=0;
    fields.querySelectorAll('input,select').forEach(control=>{
     if(!control.name||control.disabled||['sortBy','sortDir'].includes(control.name))return;
     if(control.type==='radio'||control.type==='checkbox'){
      if(control.checked&&control.value!=='')count++;
     }else if(String(control.value).trim()!==''&&!(control.name==='active'&&control.value==='1'))count++;
    });
    summary.textContent='فیلترهای بیشتر'+(count?' · '+count.toLocaleString('fa-IR')+' فعال':'');
   };
   updateSummary();
   filterForm.addEventListener('change',updateSummary);
   filterForm.addEventListener('reset',()=>queueMicrotask(updateSummary));
  }
 }
 $('ledgerTabReport')?.addEventListener('click',()=>ledgerReportPage(token));
 if(isVehicles){
  const toAscii=value=>String(value).replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
  const segments=[...filterForm.querySelectorAll('.iran-plate-input input')];
  segments.forEach(input=>{
   input.addEventListener('input',()=>{
    const cleaned=toAscii(input.value).replace(/\D/g,'').slice(0,Number(input.maxLength));
    input.value=persianDigits(cleaned);
    input.setCustomValidity('');
   });

  });
  const driverInput=filterForm.elements.driverSearch;
  let vehicleSearchTimer;
  const scheduleSearch=()=>{
    clearTimeout(vehicleSearchTimer);
    vehicleSearchTimer=setTimeout(()=>{if(filterForm.isConnected&&gridState===state)applyFilters();},400);
  };
  [...segments,driverInput,filterForm.elements.search].forEach(input=>input?.addEventListener('input',scheduleSearch));
  filterForm.elements.plateLetter?.addEventListener('change',scheduleSearch);
 }

 if(isLedger){
  let ledgerSearchTimer;
  const scheduleLedger=()=>{clearTimeout(ledgerSearchTimer);ledgerSearchTimer=setTimeout(()=>{if(filterForm.isConnected&&gridState===state)applyFilters();},500);};
  ['partySearch','search'].forEach(name=>filterForm.elements[name]?.addEventListener('input',scheduleLedger));
  ['direction','partyType','method','active','dateFrom','dateTo','amountMin','amountMax'].forEach(name=>filterForm.elements[name]?.addEventListener('change',scheduleLedger));
 }
 if(key==='services'){
  let serviceFilterTimer;
  const schedule=()=>{clearTimeout(serviceFilterTimer);serviceFilterTimer=setTimeout(()=>{if(filterForm.isConnected&&gridState===state)applyFilters();},450);};
  ['search','studentSearch','shift'].forEach(name=>filterForm.elements[name]?.addEventListener('input',schedule));
  ['schoolId','driverId','packId','status','active'].forEach(name=>filterForm.elements[name]?.addEventListener('change',schedule));
 }
 // Search as the user types on the common directory pages, without
 // triggering an API request for every keystroke.
 if(['students','drivers','schools','payments','invoices','driver-work'].includes(key)){
  let directoryTimer;
  const scheduleDirectory=()=>{
   clearTimeout(directoryTimer);
   directoryTimer=setTimeout(()=>{if(filterForm.isConnected&&gridState===state)applyFilters();},450);
  };
  ['search','mobile','academicYear','schoolType','studentName','nationalCode','distanceMin','distanceMax'].forEach(name=>filterForm.elements[name]?.addEventListener('input',scheduleDirectory));
  ['schoolId','status','active','dateFrom','dateTo'].forEach(name=>filterForm.elements[name]?.addEventListener('change',scheduleDirectory));
  if(isStudents)filterForm.querySelectorAll('[name=studentPaymentState],[name=studentContractState]').forEach(input=>input.addEventListener('change',scheduleDirectory));
  filterForm.addEventListener('reset',()=>clearTimeout(directoryTimer));
 }
 const applyFilters=()=>{
  if(gridState!==state||token!==pageToken)return;
  if(key==='invoices'){
   const datesValid=syncInvoiceSolarDates();
   const values=new FormData(filterForm), min=values.get('amountMin'),max=values.get('amountMax'),from=values.get('dateFrom'),to=values.get('dateTo');
   const error=filterForm.querySelector('.sy-invoice-filter-error');
   const message=!datesValid?'تاریخ شمسی را در قالب ۱۴۰۵/۰۷/۰۱ و با روز معتبر وارد کنید.':from&&to&&from>to?'تاریخ شروع نباید بعد از تاریخ پایان باشد.':min&&max&&Number(min)>Number(max)?'حداقل مبلغ نباید بیشتر از حداکثر باشد.':'';
   error.textContent=message;error.hidden=!message;if(message)return;
  }
  state.page=1;state.selected.clear();
  state.appliedFilters=new URLSearchParams(new FormData(filterForm));
  loadResource(key);
 };
 state.appliedFilters=new URLSearchParams(new FormData(filterForm));
 initialSearch='';$('addBtn')?.addEventListener('click',()=>{if(key==='services')location.hash='services/new';else formResource(key,null);});
 filterForm.onsubmit=e=>{e.preventDefault();applyFilters();};
 filterForm.onreset=()=>setTimeout(()=>{if(key==='invoices'){filterForm.elements.dateFrom.value='';filterForm.elements.dateTo.value='';}if(key==='students'){$('studentStatuses')?.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b.dataset.status===''));}if(isLedger)filterForm.querySelector('.ledger-filter-more')?.removeAttribute('open');if(key==='services'){filterForm.elements.search.value='';const driverSearch=$('serviceDriverSearch');if(driverSearch){driverSearch.value='';driverSearch.dispatchEvent(new Event('input'));}filterForm.querySelector('.service-filter-advanced')?.removeAttribute('open');}applyFilters();},0);
 $('studentStatuses')?.querySelectorAll('button').forEach(b=>{b.type='button';b.setAttribute('aria-pressed',String(b.classList.contains('active')||(!initialStudentStatus&&b.dataset.status==='')));b.onclick=()=>{$('gridFilters').elements.serviceStatus.value=b.dataset.status;$('studentStatuses').querySelectorAll('button').forEach(x=>{x.classList.toggle('active',x===b);x.setAttribute('aria-pressed',String(x===b));});applyFilters();};});
 $('bulkServiceStatus')?.addEventListener('click',bulkServiceStatusDialog);$('bulkDriverStatus')?.addEventListener('click',bulkDriverStatusDialog);$('bulkFees')?.addEventListener('click',bulkFeeDialog);$('bulkMessage')?.addEventListener('click',bulkMessageDialog);$('printGrid').onclick=()=>window.print();
 $('gridExport')?.addEventListener('click',()=>{const query=gridQuery();query.set('resource',key);downloadFile('/api/v1/reports/export?'+query,'گزارش-'+key+'.xlsx');});
 $('ledgerExport')?.addEventListener('click',()=>{const query=gridQuery();query.delete('page');query.delete('limit');downloadFile('/api/v1/reports/ledger/export?'+query,'گزارش-دریافت-پرداخت.xlsx');});
 if(key==='services'){
  await Promise.all(Object.entries({schoolId:'schools',driverId:'drivers',packId:'packs'}).map(async ([name,resource])=>{
   const select=filterForm.elements[name];if(!select)return;
   try{
    const rows=await lookup(resource,true);if(token!==pageToken)return;
    const sorted=[...rows].sort((a,b)=>person(a).localeCompare(person(b),'fa'));
    if(name==='driverId'){
     const search=$('serviceDriverSearch');
     const normalize=value=>value.normalize('NFKC').replace(/ي|ى/g,'ی').replace(/ك/g,'ک').replace(/[\s\u200c]+/g,' ').trim().toLowerCase();
     const drivers=sorted.map(row=>({id:row.id,label:person(row),searchName:normalize(person(row))}));
     const update=()=>{
      const selected=select.value,term=normalize(search.value);
      const matches=drivers.filter(driver=>driver.searchName.includes(term)||String(driver.id)===String(selected));
      select.replaceChildren(new Option(matches.length||!term?'همه رانندگان':'راننده‌ای پیدا نشد',''));
      for(const driver of matches)select.add(new Option(driver.label,driver.id));
      if(matches.some(driver=>String(driver.id)===selected))select.value=selected;
     };
     search.addEventListener('input',update);
     filterForm.addEventListener('reset',()=>setTimeout(update,0));
     search.disabled=false;update();
    }else{
     for(const row of sorted)select.add(new Option(person(row),row.id));
    }
    select.disabled=false;
   }catch(e){if(token===pageToken)notify(e.message,true);}
  }));
  if(token!==pageToken)return;
  }else if(schoolFilter){try{const schools=await lookup('schools');if(token!==pageToken)return;const select=filterForm.elements.schoolId;select.replaceChildren(new Option('همه مدارس',''));for(const school of [...schools].sort((a,b)=>a.name.localeCompare(b.name,'fa')))select.add(new Option(school.name,String(school.id)));if(isStudents)attachStudentSchoolDropdown(select,schools);}catch(e){notify(e.message,true);}}
 await loadResource(key);
}
/** Search within the student directory school filter; the native select
 * remains the sole submitted value and works as a fallback without JS. */
function attachStudentLookup(input,field){
 const label=input.closest('label');if(!label)return;
 label.classList.add('sy-student-lookup');
 const panel=document.createElement('div');panel.className='sy-student-school-dropdown';panel.hidden=true;label.append(panel);
 let timer,version=0;
 const close=()=>{version++;clearTimeout(timer);panel.hidden=true;};
 const search=()=>{
  const term=input.value.trim(),ticket=++version;if(term.length<2){panel.hidden=true;return;}
  timer=setTimeout(async()=>{try{
   const params=new URLSearchParams({[field]:ServiceYarDates.digits(term),limit:25,page:1});
   const result=await api(scoped('/api/v1/students?'+params));if(ticket!==version||!input.isConnected||document.activeElement!==input)return;
   panel.replaceChildren();panel.hidden=false;
   for(const row of result.items||[]){const b=document.createElement('button');b.type='button';b.className='sy-student-school-option';b.textContent=person(row)+' · '+persianDigits(row.national_code||'—');b.onmousedown=e=>e.preventDefault();b.onclick=()=>{input.value=field==='nationalCode'?(row.national_code||''):person(row);close();input.dispatchEvent(new Event('input',{bubbles:true}));close();};panel.append(b);}
   if(!result.items?.length)panel.textContent='دانش‌آموزی یافت نشد.';
  }catch(e){if(ticket===version){panel.hidden=false;panel.textContent=e.message;}}},250);
 };
 input.addEventListener('input',()=>{clearTimeout(timer);search();});
 label.addEventListener('focusout',e=>{if(!label.contains(e.relatedTarget))close();});
 input.addEventListener('keydown',e=>{if(e.key==='Escape'){close();e.preventDefault();}if(e.key==='ArrowDown'&&!panel.hidden){e.preventDefault();panel.querySelector('button')?.focus();}});
 input.form.addEventListener('reset',close);
}
function attachStudentSchoolDropdown(select,schools){
 const form=select.form,outer=select.closest('label');
 if(!form||!outer)return;
 const wrap=document.createElement('div');wrap.className='sy-student-school-picker';
 const button=document.createElement('button');button.type='button';button.className='sy-student-school-trigger';button.setAttribute('aria-label','انتخاب مدرسه');button.setAttribute('aria-expanded','false');button.setAttribute('aria-controls','studentSchoolDropdown');
 const panel=document.createElement('div');panel.className='sy-student-school-dropdown';panel.id='studentSchoolDropdown';panel.hidden=true;
 const search=document.createElement('input');search.type='search';search.placeholder='نام یا کد مدرسه را جستجو کنید…';search.autocomplete='off';search.setAttribute('aria-label','جستجوی لحظه‌ای مدرسه');
 const list=document.createElement('div');list.className='sy-student-school-options';list.setAttribute('role','group');list.setAttribute('aria-label','مدارس قابل انتخاب');
 const status=document.createElement('small');status.setAttribute('role','status');
 panel.append(search,status,list);wrap.append(button,panel);outer.replaceWith(wrap);
 const caption=document.createElement('span');caption.className='sy-student-school-caption';caption.textContent='مدرسه';wrap.prepend(caption);
 select.hidden=true;wrap.append(select);
 const rows=[...schools].sort((a,b)=>a.name.localeCompare(b.name,'fa'));
 const sync=()=>{button.textContent=(select.selectedOptions[0]?.textContent||'همه مدارس')+' ▾';};
 const close=(focus=false)=>{panel.hidden=true;button.setAttribute('aria-expanded','false');if(focus)button.focus();};
 const render=()=>{
  const q=normalizeServiceSearch(search.value),matches=rows.filter(r=>normalizeServiceSearch(r.name+' '+(r.code||'')).includes(q));
  list.replaceChildren();status.textContent=number(matches.length)+' مدرسه';
  const add=(id,label)=>{const item=document.createElement('button');item.type='button';item.className='sy-student-school-option';item.textContent=label;item.setAttribute('aria-pressed',String(select.value===String(id)));item.onclick=()=>{select.value=String(id);sync();close(true);select.dispatchEvent(new Event('change',{bubbles:true}));};list.append(item);};
  add('','همه مدارس');for(const row of matches)add(row.id,row.name+(row.code?' · '+persianDigits(String(row.code)):''));
  if(!matches.length){const empty=document.createElement('p');empty.textContent='مدرسه‌ای یافت نشد.';list.append(empty);}
 };
 button.onclick=()=>{if(!panel.hidden){close();return;}panel.hidden=false;button.setAttribute('aria-expanded','true');search.value='';render();search.focus();};
 search.addEventListener('input',render);
 wrap.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden){e.preventDefault();close(true);}else if(e.key==='ArrowDown'&&e.target===search){e.preventDefault();list.querySelector('button')?.focus();}else if(e.key==='Enter'&&e.target===search){e.preventDefault();}});
 wrap.addEventListener('focusout',e=>{if(e.relatedTarget&&!wrap.contains(e.relatedTarget))close();});
 form.addEventListener('reset',()=>setTimeout(()=>{sync();close();},0));
 select.addEventListener('change',sync);sync();
}
function bulkServiceStatusDialog(){
 if(gridState?.key!=='services')return;
 const ids=[...gridState.selected];
 if(!ids.length){notify('ابتدا سرویس‌ها را از جدول انتخاب کنید.',true);return;}
 if(ids.length>200){notify('در هر عملیات حداکثر ۲۰۰ سرویس را انتخاب کنید.',true);return;}
 dialog('تغییر وضعیت گروهی سرویس‌ها',`<div class="service-bulk-dialog"><p><strong>${number(ids.length)} سرویس</strong> انتخاب شده است. وضعیت جدید برای تمام موارد انتخابی ثبت خواهد شد.</p><label for="bulkServiceTarget">وضعیت مقصد</label><select id="bulkServiceTarget"><option value="active">فعال</option><option value="inactive">غیرفعال</option><option value="draft">پیش‌نویس</option></select><p class="service-bulk-note">غیرفعال‌سازی، سرویس‌ها را از فهرست پیش‌فرض فعال‌ها خارج می‌کند؛ اطلاعات و سوابق حذف نمی‌شوند.</p><div class="bulk-driver-actions"><button class="primary" id="confirmBulkServices" type="button">ثبت تغییرات</button><button id="cancelBulkServices" type="button">انصراف</button></div></div>`);
 $('cancelBulkServices').onclick=closeDialog;
 $('confirmBulkServices').onclick=async()=>{
  const status=$('bulkServiceTarget').value;
  const label=statuses[status]||status;
  if(!confirm('وضعیت '+number(ids.length)+' سرویس به «'+label+'» تغییر کند؟'))return;
  const btn=$('confirmBulkServices');btn.disabled=true;btn.textContent='در حال ثبت…';
  try{
   const result=await write('/api/v1/services/bulk-status',{ids,status});
   if(result.status!==status||result.selected!==ids.length)throw new Error('پاسخ سرور با درخواست مطابقت ندارد.');
   closeDialog();gridState.selected.clear();lookupCache={};
   const filters=$('gridFilters');
   if(filters){filters.elements.status.value=status;filters.elements.active.value=status==='inactive'?'0':'1';gridState.appliedFilters=new URLSearchParams(new FormData(filters));}
   gridState.page=1;await loadResource('services');
   notify(number(result.changed)+' سرویس به وضعیت «'+label+'» تغییر یافت. '+number(result.unchanged)+' مورد بدون تغییر بود.');
  }catch(e){btn.disabled=false;btn.textContent='ثبت تغییرات';notify(e.message,true);}
 };
}
function bulkDriverStatusDialog(){
 if(gridState?.key!=='drivers')return;
 const ids=[...gridState.selected];
 if(!ids.length){notify('ابتدا راننده‌ها را با چک‌باکس انتخاب کنید.',true);return}
 dialog('عملیات گروهی رانندگان','<p>تعداد '+number(ids.length)+' راننده انتخاب شده است. وضعیت جدید را انتخاب کنید:</p><div class="bulk-driver-actions"><button type="button" class="primary" id="bulkDriverActivate">فعال‌سازی گروهی</button><button type="button" class="danger" id="bulkDriverDeactivate">غیرفعال‌سازی گروهی</button></div>');
 for(const [id,active] of [['bulkDriverActivate',true],['bulkDriverDeactivate',false]]){
  $(id).onclick=async()=>{
   if(!confirm('آیا از '+(active?'فعال‌سازی':'غیرفعال‌سازی')+' '+number(ids.length)+' راننده اطمینان دارید؟'))return;
   const buttons=$('modal').querySelectorAll('.bulk-driver-actions button');buttons.forEach(b=>b.disabled=true);
   try{const result=await write('/api/v1/drivers/bulk-status',{ids,active});if(result.active!==active||result.selected!==ids.length)throw new Error('پاسخ عملیات گروهی با درخواست مطابقت ندارد.');closeDialog();gridState.selected.clear();lookupCache={};const activeFilter=$('gridFilters')?.querySelector('[name="active"]');if(activeFilter)activeFilter.value=active?'1':'0';gridState.page=1;await loadResource('drivers');notify(number(result.changed)+' راننده تغییر وضعیت یافت؛ فهرست بر اساس وضعیت جدید نمایش داده شد.');}
   catch(e){buttons.forEach(b=>b.disabled=false);notify(e.message,true)}
  };
 }
}
function gridQuery(){const p=new URLSearchParams(gridState.appliedFilters||new FormData($('gridFilters')));p.set('page',gridState.page);p.set('limit',gridState.limit);p.set('agencyId',currentAgency.id);return p;}
async function loadResource(key){
 const state=gridState;if(!state||state.key!==key)return;const request=state.request=(state.request||0)+1;$('table').setAttribute('aria-busy','true');$('table').innerHTML='<div class="loading" role="status">در حال دریافت اطلاعات…</div>';
 try{const d=await api('/api/v1/'+key+'?'+gridQuery());if(state!==gridState||state.token!==pageToken||request!==state.request)return;state.rows=d.items;const fields=key==='ledger'?['entryDate','direction','partyName','amount','method','creatorName','note']:columns[key]||resources[key][1].slice(0,7),refs=[...new Set(fields.filter(f=>relations[f]).map(f=>relations[f]))];const maps={};await Promise.all(refs.map(async r=>{try{maps[r]=await lookup(r,key==='services');}catch{maps[r]=[];}}));if(state!==gridState||state.token!==pageToken||request!==state.request)return;
 $('table').setAttribute('aria-busy','false');
 if(key==='ledger'){
  const active=[...state.appliedFilters.entries()].filter(([name,value])=>value.trim()!==''&&!(name==='active'&&value==='1')).length;
  $('ledgerFilterSummary').textContent=number(d.pagination.total)+' سند'+(active?' · '+number(active)+' فیلتر فعال':'');
  const countNode=$('syLedgerKpiRecords');if(countNode)countNode.textContent=number(d.pagination.total);
  const kpiNodes=['Receipts','Payments','Balance'].map(k=>$('syLedgerKpi'+k));
  kpiNodes.forEach(node=>{if(node){node.textContent='—';node.title='در حال دریافت جمع مالی مطابق فیلترها';}});
  if(can('reports')){
   const summaryQuery=new URLSearchParams(state.appliedFilters);
   summaryQuery.set('agencyId',String(currentAgency.id));summaryQuery.set('page','1');summaryQuery.set('limit','1');
   void api('/api/v1/reports/ledger?'+summaryQuery).then(report=>{
    if(state!==gridState||state.token!==pageToken||request!==state.request)return;
    for(const [index,keyName] of ['receipts','payments','balance'].entries()){
     const value=Number(report?.totals?.[keyName]);const node=kpiNodes[index];
     if(node){node.textContent=Number.isFinite(value)?number(Math.round(value)):'—';node.title='جمع کل اسناد مطابق فیلترهای انتخاب‌شده';}
    }
   }).catch(()=>{
    if(state!==gridState||state.token!==pageToken||request!==state.request)return;
    kpiNodes.forEach(node=>{if(node)node.title='جمع مالی در دسترس نیست؛ به صفحه گزارش‌گیری مراجعه کنید.';});
   });
  }else kpiNodes.forEach(node=>{if(node)node.title='نمایش جمع مالی نیازمند مجوز گزارش‌گیری است.';});

 }
 if(key==='services'){
  const applied=[...state.appliedFilters.entries()].filter(([name,value])=>!['page','limit','agencyId'].includes(name)&&value.trim()!=='').length;
  $('serviceFilterSummary').textContent=number(d.pagination.total)+' سرویس'+(applied?' · '+number(applied)+' فیلتر اعمال شده':'');
 }
 const display=(row,f)=>{if(key==='invoices'){if(f==='title')return persianDigits(row.title||'—');if(f==='partyName')return persianDigits(row.party_name||'طرف حساب نامشخص');if(f==='partyType')return ({student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت'})[row.party_type]||'سایر';if(f==='invoicePaid')return number(row.paid_amount);if(f==='invoiceOriginal')return number(row.original_amount??row.amount);if(f==='invoiceAdjustment')return number(row.class_adjustment_amount||0);if(f==='invoiceEffective')return number(row.effective_amount??row.amount);if(f==='invoiceRemaining')return number(row.effective_remaining_amount??row.remaining_amount);if(f==='status')return ({open:'باز',partial:'پرداخت جزئی',paid:'تسویه‌شده',overdue:'سررسید گذشته',cancelled:'ابطال‌شده'})[row.effective_display_status||row.display_status]||'نامشخص';if(f==='contractNo')return persianDigits(String(row.contract_no||'—').replace(/\.0+$/,''));}if(f==='sepandStatus')return {approved:'تأیید شده',rejected:'رد شده',pending:'در حال بررسی',unknown:'نامشخص'}[row.sepand_status]||'نامشخص';if(key==='ledger'&&f==='partyName')return row.party_name||'نامشخص';if(key==='ledger'&&f==='creatorName')return row.creator_name||row.creator_username||'ثبت نشده';if(key==='ledger'&&f==='direction')return row.direction==='receipt'?'دریافت':row.direction==='payment'?'پرداخت':persianDigits(row.direction||'—');if(key==='ledger'&&f==='partyType')return ({student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت'})[row.party_type]||persianDigits(row.party_type||'—');if(key==='ledger'&&f==='method')return methodLabel(row.method);if(key==='ledger'&&f==='companyAccountId')return row.company_account_name||'حساب قدیمی نامشخص';if(f==='fullName')return person(row);if(key==='students'&&f==='tariffDistanceKm')return row.tariff_distance_km==null?'—':number(Math.round(Number(row.tariff_distance_km)));if(key==='services'&&f==='monthlyFee')return number(row.driver_net_monthly??row.monthly_fee);if(key==='students'&&f==='annualFee')return row.annual_fee===null||row.annual_fee===undefined?'—':number(row.annual_fee);if(key==='students'&&['invoiceTotal','paidTotal','outstandingFee','studentCredit'].includes(f))return number(row[{invoiceTotal:'invoice_total',paidTotal:'paid_total',outstandingFee:'outstanding_fee',studentCredit:'student_credit'}[f]]??0);if(f==='contractStatus')return Number(row.has_contract)===1?'دارای قرارداد':'فاقد قرارداد';if(f==='paymentStatus')return Number(row.outstanding_fee)>0?'بدهکار':Number(row.student_credit)>0?'بستانکار':Number(row.invoice_total)>0?'تسویه':'بدون فاکتور';const v=val(row,f);if(f==='driverName'&&!v&&row.mehr_driver_name)return row.mehr_driver_name+' (سابقه مهر)';if(f==='plate')return String(v||'—');if(key==='vehicles'&&f==='ownerName')return v||row.effective_owner_name||row.driver_name||'ثبت نشده';if(key==='vehicles'&&f==='driverName')return v||'راننده‌ای تخصیص نیافته';if(f==='vehicleId'){const vehicle=maps.vehicles?.find(x=>String(x.id)===String(v));return vehicle?.plate||v||'—';}if(relations[f])return person(maps[relations[f]]?.find(x=>String(x.id)===String(v))||{id:v||'—'});if(['status','serviceStatus'].includes(f))return statuses[v]||v;if(f==='mobile')return persianDigits(v||'—');if(/Date$/.test(f))return dateText(v);if(/amount|fee|balance|capacity|rating|score/i.test(f))return number(v);return key==='ledger'?persianDigits(v||'—'):v||'—';};
 $('table').innerHTML=`<div class="table-wrap ${key==='services'?'services-table-wrap':''} ${['students','drivers'].includes(key)?'sy-directory-table-wrap':''} ${['ledger','payments','driver-payments','invoices','accounts','driver-work'].includes(key)?'sy-finance-table-wrap':''}"><table class="${key==='services'?'services-data-table':''} ${['students','drivers'].includes(key)?'sy-directory-table':''} ${['ledger','payments','driver-payments','invoices','accounts','driver-work'].includes(key)?'sy-finance-table':''} ${key==='ledger'?'sy-ledger-data-table':''}"><thead><tr><th scope="col"><input type="checkbox" id="selectPage" aria-label="انتخاب همه موارد این صفحه"></th><th scope="col">ردیف</th>${fields.map(f=>{const column=sortDataField[f]||f.replace(/[A-Z]/g,x=>'_'+x.toLowerCase());const enabled=resourceSortFields[key]?.some(([name])=>name===column);const selected=state.appliedFilters?.get('sortBy')===column;return `<th scope="col" ${selected?'aria-sort="'+(state.appliedFilters?.get('sortDir')==='asc'?'ascending':'descending')+'"':''}>${enabled?`<button type="button" class="sy-grid-sort-heading" data-grid-sort="${esc(column)}" title="مرتب‌سازی ${esc(labels[f]||f)}">${esc(labels[f]||f)}<span aria-hidden="true">${selected?(state.appliedFilters?.get('sortDir')==='asc'?'↑':'↓'):'↕'}</span></button>`:esc(labels[f]||f)}</th>`;}).join('')}<th scope="col">عملیات</th></tr></thead><tbody>${d.items.map((r,i)=>`<tr><td data-label="انتخاب"><input type="checkbox" data-select="${r.id}" aria-label="انتخاب ${esc(person(r))}" ${state.selected.has(Number(r.id))?'checked':''}></td><td data-label="ردیف">${number((state.page-1)*state.limit+i+1)}</td>${fields.map(f=>`<td data-label="${esc(labels[f]||f)}">${key==='schools'&&f==='name'&&schoolPhotoUrl(r)?`<span class="sy-school-photo-name"><img src="${esc(schoolPhotoUrl(r))}" alt="" loading="lazy" data-school-image-fallback><span>${esc(display(r,f))}</span></span>`:f==='plate'||f==='vehicleId'?iranPlate(display(r,f)):key==='ledger'&&f==='direction'?'<span class="sy-ledger-direction '+(r.direction==='receipt'?'is-receipt':'is-payment')+'">'+(r.direction==='receipt'?'↓ ':'↑ ')+esc(display(r,f))+'</span>':key==='ledger'&&f==='amount'?'<strong class="sy-ledger-row-amount">'+esc(number(r.amount))+' <small>ریال</small></strong>':key==='ledger'&&f==='method'?'<span class="sy-ledger-method">▣ '+esc(display(r,f))+'</span>':esc(display(r,f))}</td>`).join('')}<td data-label="عملیات" class="row-actions">${key==='vehicles'?'':`<button data-view="${r.id}">جزئیات</button>`}${can(key,'manage')?`<button data-edit="${r.id}">ویرایش</button>${['schools','students','drivers','vehicles','services','accounts','ledger','driver-ratings','parent-reports'].includes(key)?`<button class="danger" data-del="${r.id}">${key==='ledger'?(Number(r.is_active)===0?'بازگردانی سند':'حذف سند'):(Number(r.is_active)===0?'بازیابی':'غیرفعال')}</button>`:''}`:''}${['ledger','payments','driver-payments'].includes(key)&&can(key,'manage')?`<button data-post-financial="${r.id}">ثبت / مشاهده سند حسابداری</button>`:''}${key==='students'&&r.assigned_service_id&&can('services','manage')?`<button type="button" data-student-service="${Number(r.assigned_service_id)}">ارجاع به سرویس</button>`:''}${key==='schools'?`<button data-school-shifts="${r.id}">شیفت، ساعت و تعطیلی</button>`:''}${key==='drivers'?`<button data-documents="${r.id}">مدارک</button>`:''}${key==='drivers'&&canConfigureDriverService()?`<button class="driver-service-action" data-driver-service="${r.id}" aria-label="تخصیص دانش‌آموز و تنظیم سرویس ${esc(person(r))}">تخصیص دانش‌آموز و تنظیم سرویس</button>`:''}${key==='services'?`<button data-service-schools="${r.id}">مدارس سرویس</button><button data-members="${r.id}">دانش‌آموزان سرویس</button><button data-service-map="${r.id}">نقشه سرویس</button>`:''}${key==='driver-changes'&&r.status==='pending'&&can(key,'manage')?`<button data-review="${r.id}" data-status="approved">تأیید</button><button data-review="${r.id}" data-status="rejected">رد</button>`:''}</td></tr>`).join('')||`<tr><td colspan="${fields.length+3}" class="empty"><div class="sy-empty-results"><strong>موردی با این شرایط پیدا نشد</strong><span>عبارت جست‌وجو یا فیلترهای انتخابی را تغییر دهید.</span><button type="button" data-clear-grid-filters>نمایش همه سوابق</button></div></td></tr>`}</tbody></table></div><div class="pager"><button id="prevPage" ${state.page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(state.page)} از ${number(Math.max(1,d.pagination.pages))} · ${number(d.pagination.total)} مورد</span><button id="nextPage" ${state.page>=d.pagination.pages?'disabled':''}>بعدی</button><select id="pageSize" aria-label="تعداد در صفحه">${[25,50,100,200].map(n=>`<option ${state.limit===n?'selected':''}>${n}</option>`).join('')}</select></div>`;
 if(key==='students'){
  const table=$('table').querySelector('table');table?.classList.add('sy-student-list-v3');
  // Only inactive students may be permanently removed, subject to strict backend dependency checks.
  table?.querySelectorAll('tbody tr').forEach((tr,index)=>{
   const student=d.items[index],actions=tr.querySelector('.row-actions');
   if(!student||!actions||Number(student.is_active)!==0||!can('students','manage'))return;
   const button=document.createElement('button');button.type='button';button.className='danger';button.textContent='حذف دائمی';button.dataset.studentPermanentDelete=String(student.id);actions.append(button);
  });
  table?.querySelectorAll('[data-student-permanent-delete]').forEach(button=>button.onclick=async()=>{
   const student=d.items.find(s=>String(s.id)===button.dataset.studentPermanentDelete);if(!student)return;
   const typed=window.prompt('حذف دائمی '+person(student)+' غیرقابل بازگشت است. در صورت وجود قرارداد، فاکتور، پرداخت یا سایر وابستگی‌ها حذف مجاز نیست. برای تأیید عبارت «حذف دائمی» را وارد کنید:');
   if(typed!=='حذف دائمی')return;
   button.disabled=true;
   try{await write('/api/v1/students/'+student.id+'/permanent',{confirmation:'DELETE_STUDENT_PERMANENTLY'},'DELETE');lookupCache={};notify('دانش‌آموز فاقد سابقه مرتبط به‌صورت دائمی حذف شد.');await loadResource('students');}catch(e){notify(e.message,true);button.disabled=false;}
  });
  const count=$('syStudentResultCount');if(count)count.textContent=number(d.pagination.total)+' دانش‌آموز';
  const info=$('syStudentFilterInfo');if(info){const n=[...state.appliedFilters.entries()].filter(([k,v])=>v!==''&&!['sortBy','sortDir'].includes(k)&&!(k==='active'&&v==='1')).length;info.textContent=n?number(n)+' فیلتر فعال':'نمایش نتایج مطابق فیلترها';}
  table?.querySelectorAll('tbody tr').forEach((tr,index)=>{const row=d.items[index];if(!row)return;const contractCell=tr.querySelector('td[data-label="وضعیت قرارداد"]');if(contractCell){const has=Number(row.has_contract)===1;contractCell.innerHTML='<span class="sy-student-pay-status" data-tone="'+(has?'settled':'none')+'">'+esc(display(row,'contractStatus'))+'</span>';}const cell=tr.querySelector('td[data-label="وضعیت پرداخت"]');if(!cell)return;const label=display(row,'paymentStatus'),tone=Number(row.outstanding_fee)>0?'debt':Number(row.student_credit)>0?'credit':Number(row.invoice_total)>0?'settled':'none';cell.innerHTML='<button type="button" class="sy-student-pay-status" data-tone="'+tone+'" data-student-finance="'+Number(row.id)+'" aria-label="گردش مالی '+esc(person(row))+'">'+esc(label)+' <span aria-hidden="true">‹</span></button>';});
  table?.querySelectorAll('[data-student-finance]').forEach(button=>button.onclick=()=>{const id=Number(button.dataset.studentFinance),row=d.items.find(r=>Number(r.id)===id);dialog('گردش مالی '+person(row||{}),'<div id="syStudentQuickAccount"></div>');const panel=$('syStudentQuickAccount');panel?.closest('.modal')?.classList.add('sy-student-finance-modal');if(panel)void studentAccountPanel(panel,id);});
 }
 if(key==='invoices'){
  const summary=d.summary||{};
  const form=$('gridFilters'),chips=$('syInvoiceChips'),applied=state.appliedFilters;
  const names={search:'جست‌وجو',partyType:'طرف حساب',paymentState:'وضعیت',dateFrom:'از تاریخ',dateTo:'تا تاریخ',amountMin:'حداقل مبلغ',amountMax:'حداکثر مبلغ',contractOnly:'فقط قراردادها',remainingOnly:'فقط دارای مانده',sortBy:'مرتب‌سازی',sortDir:'ترتیب'};
  const selected=[...applied.entries()].filter(([key,value])=>names[key]&&value!==''&&!(['sortBy','sortDir'].includes(key)&&value===(key==='sortBy'?'id':'desc')));
  if(chips)chips.innerHTML=selected.map(([key,value])=>{const field=form.elements[key];const label=key==='paymentState'?({open:'باز',partial:'پرداخت جزئی',paid:'تسویه',overdue:'سررسید گذشته',cancelled:'ابطال‌شده'}[value]):key==='partyType'?field.selectedOptions[0]?.textContent:key.startsWith('date')?dateText(value):key.startsWith('amount')?number(value):key.endsWith('Only')?'':key==='sortBy'?({id:'شماره فاکتور',issue_date:'تاریخ صدور',due_date:'سررسید',amount:'مبلغ',title:'عنوان'}[value]||value):key==='sortDir'?(value==='asc'?'صعودی':'نزولی'):persianDigits(value);return '<button type="button" data-invoice-clear="'+esc(key)+'" aria-label="حذف فیلتر '+esc(names[key])+'">'+esc(names[key]+(label?' : '+label:''))+' <span aria-hidden="true">×</span></button>';}).join('');
  const count=$('syInvoiceResultCount');if(count)count.textContent=number(d.pagination.total)+' فاکتور';
  const advanced=$('syInvoiceAdvancedCount');if(advanced){const n=selected.filter(([key])=>!['search','partyType','paymentState'].includes(key)).length;advanced.textContent=n?number(n)+' فیلتر فعال':'';}

  const kpis=$('syInvoiceKpis');if(kpis)kpis.innerHTML=[['تعداد فاکتورها',summary.records],['جمع فاکتور معتبر',summary.amount],['دریافتی مستقیم',summary.paid],['مانده فاکتورها',summary.remaining]].map(([label,value],i)=>'<div class="sy-invoice-kpi"><small>'+label+'</small><strong>'+number(value||0)+'</strong>'+(i?'<span>ریال</span>':'')+'</div>').join('');
  const table=$('table').querySelector('table');table?.classList.add('sy-invoice-table');
  table?.querySelectorAll('tbody tr').forEach((tr,i)=>{const row=d.items[i];if(!row)return;const cells=tr.querySelectorAll('td');const party=cells[4];if(party&&row.party_meta)party.insertAdjacentHTML('beforeend','<small class="sy-invoice-meta">'+esc(persianDigits(row.party_meta))+'</small>');const st=cells[9];if(st)st.innerHTML='<span class="sy-invoice-status" data-state="'+esc(row.display_status)+'">'+esc(display(row,'status'))+'</span>';});
 }
 if(key==='services'){
  const titleOf=r=>String(r.title||r.name||r.code||'سرویس بدون عنوان');
  const detailFields=fields.filter(f=>!['title','name','code','status'].includes(f));
  const action=(id,attr,label,cls='')=>`<button type="button" ${attr}="${id}" class="${cls}">${label}</button>`;
  $('table').innerHTML=`<section class="sy-services-results" aria-label="فهرست سرویس‌ها">
   <div class="sy-services-results-head"><div><h3>فهرست سرویس‌ها</h3><p>اطلاعات هر سرویس بدون پیمایش افقی نمایش داده می‌شود.</p></div><label class="sy-services-select-all"><input type="checkbox" id="selectPage"> انتخاب همه در این صفحه</label></div>
   <div class="sy-services-card-grid">${d.items.map((r,i)=>`<article class="sy-service-card">
    <header class="sy-service-card-head"><label class="sy-service-check"><input type="checkbox" data-select="${r.id}" aria-label="انتخاب سرویس ${esc(titleOf(r))}" ${state.selected.has(Number(r.id))?'checked':''}></label><div class="sy-service-identity"><span class="sy-service-index">سرویس ${number((state.page-1)*state.limit+i+1)}</span><h4>${esc(titleOf(r))}</h4><small>کد سرویس: ${esc(persianDigits(r.code||'ثبت نشده'))}</small></div><span class="sy-service-status" data-status="${esc(r.status||'unknown')}">${esc(statuses[r.status]||'نامشخص')}</span></header>
    <dl class="sy-service-facts sy-service-facts-primary">${detailFields.filter(f=>['driverId','schoolId','capacity','shift'].includes(f)).map(f=>`<div><dt>${esc(labels[f]||f)}</dt><dd>${esc(persianDigits(display(r,f)))}</dd></div>`).join('')}<div><dt>جمع ماهانه دانش‌آموزان (ناخالص)</dt><dd>${number(r.students_monthly_total)} ریال</dd></div><div class="sy-service-net"><dt>خالص ماهانه قابل پرداخت به راننده</dt><dd>${number(r.driver_net_monthly)} ریال</dd></div></dl><details class="sy-service-more"><summary>ریز کسورات و اطلاعات مالی</summary><dl class="sy-service-facts sy-service-facts-secondary"><div><dt>نظارت ${percent(r.supervision_rate)}٪</dt><dd>${number(r.supervision_deduction)} ریال</dd></div><div><dt>هماهنگی ${percent(r.coordination_rate)}٪</dt><dd>${number(r.coordination_deduction)} ریال</dd></div><div><dt>بالاسری ${percent(r.overhead_rate)}٪</dt><dd>${number(r.overhead_deduction)} ریال</dd></div><div><dt>بیمه ${percent(r.insurance_rate)}٪</dt><dd>${number(r.insurance_deduction)} ریال</dd></div><div><dt>جمع کسورات ${percent(r.driver_deduction_rate)}٪</dt><dd>${number(r.driver_deduction_total)} ریال</dd></div><div class="sy-service-annual"><dt>مبلغ سالانه سرویس</dt><dd>${number(r.students_annual_total)} ریال</dd></div></dl></details>
    <footer class="sy-service-actions">${action(r.id,'data-view','جزئیات','sy-service-action-primary')}${can(key,'manage')?action(r.id,'data-edit','ویرایش'):''}${action(r.id,'data-service-schools','مدارس')}${action(r.id,'data-members','دانش‌آموزان')}${action(r.id,'data-service-map','نقشه')}${can(key,'manage')?action(r.id,'data-del',Number(r.is_active)===0?'بازیابی':'غیرفعال','sy-service-action-danger'):''}</footer>
   </article>`).join('')||'<div class="sy-services-empty"><strong>سرویسی پیدا نشد</strong><p>عبارت جست‌وجو یا فیلترها را تغییر دهید.</p><button type="button" data-clear-grid-filters>نمایش همه سرویس‌ها</button></div>'}</div>
   <div class="pager sy-services-pager"><button id="prevPage" ${state.page<=1?'disabled':''}>صفحه قبل</button><span>صفحه ${number(state.page)} از ${number(Math.max(1,d.pagination.pages))} · ${number(d.pagination.total)} سرویس</span><button id="nextPage" ${state.page>=d.pagination.pages?'disabled':''}>صفحه بعد</button><label>تعداد در صفحه <select id="pageSize" aria-label="تعداد سرویس در صفحه">${[10,25,50,100].map(n=>`<option value="${n}" ${state.limit===n?'selected':''}>${number(n)}</option>`).join('')}</select></label></div>
  </section>`;
 }
 if(key==='vehicles'){
  // Keep the sortable desktop table and show a plate-first card layout on
  // narrow screens, so long VINs never hide the actual Iranian plate.
  const wrap=$('table').querySelector('.table-wrap');
  wrap?.insertAdjacentHTML('afterend','<section class="sy-vehicle-mobile-results" aria-label="فهرست خودروها">'+
   d.items.map((vehicle,index)=>'<article class="sy-vehicle-mobile-card"><header><label><input type="checkbox" data-select="'+Number(vehicle.id)+'" '+(state.selected.has(Number(vehicle.id))?'checked':'')+' aria-label="انتخاب خودرو"> انتخاب</label><span>ردیف '+number((state.page-1)*state.limit+index+1)+'</span></header>'+
   '<div class="sy-vehicle-card-plate">'+iranPlate(vehicle.plate)+'</div>'+
   '<h3>'+esc(vehicle.vehicle_trim||vehicle.model||vehicle.vehicle_system||'خودروی بدون مدل')+'</h3>'+
   '<dl><div><dt>کلاس تعرفه</dt><dd>'+esc(vehicle.tariff_vehicle_class?'کلاس '+number(vehicle.tariff_vehicle_class):'نامشخص')+'</dd></div>'+
   '<div><dt>وضعیت سپند</dt><dd>'+esc({approved:'تأیید شده',rejected:'رد شده',pending:'در حال بررسی'}[vehicle.sepand_status]||'نامشخص')+'</dd></div>'+
   '<div><dt>ظرفیت</dt><dd>'+number(vehicle.capacity)+'</dd></div>'+
   '<div><dt>VIN</dt><dd dir="ltr">'+esc(vehicle.vin||'—')+'</dd></div>'+
   '<div><dt>راننده</dt><dd>'+esc(vehicle.driver_name||'—')+'</dd></div></dl>'+
   (can(key,'manage')?'<footer><button type="button" data-edit="'+Number(vehicle.id)+'">ویرایش</button><button type="button" class="danger" data-del="'+Number(vehicle.id)+'">'+(Number(vehicle.is_active)===0?'بازیابی':'غیرفعال')+'</button></footer>':'')+
   '</article>').join('')+'</section>');
 }
 $('table').querySelectorAll('[data-grid-sort]').forEach(button=>button.onclick=()=>{
  const form=$('gridFilters');if(!form||!form.elements.sortBy)return;
  const column=button.dataset.gridSort;
  const same=form.elements.sortBy.value===column;
  form.elements.sortBy.value=column;
  form.elements.sortDir.value=same&&form.elements.sortDir.value==='asc'?'desc':'asc';
  state.page=1;state.selected.clear();
  state.appliedFilters=new URLSearchParams(new FormData(form));
  loadResource(key);
 });
 const emptyReset=$('table').querySelector('[data-clear-grid-filters]');
 if(emptyReset)emptyReset.onclick=()=>{const filters=$('gridFilters');if(!filters)return;filters.reset();if(key==='students')$('studentStatuses')?.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b.dataset.status===''));state.appliedFilters=new URLSearchParams(new FormData(filters));state.page=1;state.selected.clear();loadResource(key);};
 const tableWrap=$('table').querySelector('.table-wrap');if(tableWrap){tableWrap.tabIndex=0;tableWrap.setAttribute('role','region');tableWrap.setAttribute('aria-label','جدول '+resourceName(key)+'؛ برای مشاهده ستون‌های بیشتر به صورت افقی پیمایش کنید');}
 const selected=()=>{const n=state.selected.size;$('selectedCount').textContent=n?number(n)+' مورد انتخاب شده':'برای عملیات گروهی، ردیف‌ها را انتخاب کنید';const action=$('bulkServiceStatus');if(action){action.disabled=n===0;action.textContent=n?'تغییر وضعیت '+number(n)+' سرویس انتخابی':'تغییر وضعیت گروهی سرویس‌ها';}};selected();$('table').querySelectorAll('[data-select]').forEach(b=>b.onchange=()=>{b.checked?state.selected.add(Number(b.dataset.select)):state.selected.delete(Number(b.dataset.select));selected();});$('selectPage').onchange=e=>{$('table').querySelectorAll('[data-select]').forEach(b=>{b.checked=e.target.checked;b.onchange();});};
 $('prevPage').onclick=()=>{state.page--;loadResource(key);};$('nextPage').onclick=()=>{state.page++;loadResource(key);};$('pageSize').onchange=e=>{state.limit=Number(e.target.value);state.page=1;loadResource(key);};
 $('table').querySelectorAll('[data-driver-service]').forEach(button=>button.onclick=()=>driverServiceDialog(d.items.find(row=>String(row.id)===button.dataset.driverService)));
 $('table').querySelectorAll('[data-post-financial]').forEach(b=>b.onclick=()=>financialPostingDialog(key,Number(b.dataset.postFinancial)).catch(e=>notify(e.message,true)));$('table').querySelectorAll('[data-school-shifts]').forEach(b=>b.onclick=()=>schoolShiftsDialog(Number(b.dataset.schoolShifts)).catch(e=>notify(e.message,true)));$('table').querySelectorAll('[data-service-schools]').forEach(b=>b.onclick=()=>serviceSchoolsDialog(Number(b.dataset.serviceSchools)));$('table').querySelectorAll('[data-service-map]').forEach(b=>b.onclick=()=>serviceMap(Number(b.dataset.serviceMap)));$('table').querySelectorAll('[data-documents]').forEach(b=>b.onclick=()=>driverDocuments(Number(b.dataset.documents)));$('table').querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>formResource(key,Number(b.dataset.edit)));$('table').querySelectorAll('[data-view]').forEach(b=>b.onclick=()=>viewRecord(key,Number(b.dataset.view)));$('table').querySelectorAll('[data-members]').forEach(b=>b.onclick=()=>serviceMembers(Number(b.dataset.members)));
 $('table').querySelectorAll('[data-student-service]').forEach(b=>b.onclick=()=>formResource('services',Number(b.dataset.studentService),()=>loadResource('students')));
 $('table').querySelectorAll('[data-del]').forEach(b=>b.onclick=async()=>{const row=state.rows.find(r=>String(r.id)===b.dataset.del);if(!row)return;const ledger=key==='ledger',restore=Number(row.is_active)===0;const message=ledger?(restore?'سند '+(row.direction==='receipt'?'دریافت':'پرداخت')+' به مبلغ '+number(row.amount)+' ریال دوباره فعال شود؟':'سند '+(row.direction==='receipt'?'دریافت':'پرداخت')+' به مبلغ '+number(row.amount)+' ریال حذف شود؟ این عملیات سند را غیرفعال می‌کند و از گزارش‌های فعال کنار می‌گذارد. اگر سند حسابداری به آن متصل باشد، حذف مجاز نیست.'):'وضعیت این رکورد تغییر کند؟';if(!confirm(message))return;try{if(restore)await write('/api/v1/'+key+'/'+row.id,{isActive:1},'PUT');else await api(scoped('/api/v1/'+key+'/'+row.id),{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});lookupCache={};await loadResource(key);if(ledger)notify(restore?'سند مالی بازگردانی شد.':'سند مالی از سوابق فعال حذف شد.');}catch(e){notify(e.message,true);}});
 $('table').querySelectorAll('[data-review]').forEach(b=>b.onclick=async()=>{if(!confirm('این تصمیم برای درخواست تغییر راننده ثبت شود؟'))return;try{await write('/api/v1/operations/driver-changes/'+b.dataset.review+'/review',{status:b.dataset.status});await loadResource(key);}catch(e){notify(e.message,true);}});
 }catch(e){if(state===gridState&&state.token===pageToken){$('table').setAttribute('aria-busy','false');$('table').innerHTML='<div class="sy-grid-error" role="alert"><strong>دریافت اطلاعات انجام نشد</strong><p>'+esc(e.message)+'</p><button type="button" id="retryGrid">تلاش دوباره</button></div>';$('retryGrid').onclick=()=>loadResource(key);}}
}
let modalBackground=[];
function restoreModalBackground(){for(const [node,inert] of modalBackground)if(node.isConnected)node.inert=inert;modalBackground=[];document.body.classList.remove('has-modal');}
function dialog(title,content){
 disposeMaps();restoreModalBackground();
 let host=$('modal');
 if(!host){host=document.createElement('div');host.id='modal';$('content').append(host);}
 if(!host.firstElementChild)returnFocus=document.activeElement;
 host.innerHTML=`<div class="modal-backdrop"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="dialogTitle" tabindex="-1"><div class="modal-head"><h3 id="dialogTitle">${esc(title)}</h3><button type="button" id="closeModal" aria-label="بستن پنجره">×</button></div><div class="modal-body">${content}<div id="formError" class="error" role="alert" tabindex="-1" hidden></div></div></section></div>`;
 document.body.classList.add('has-modal');
 document.querySelectorAll('#sySkipToContent,.sidebar,.header,#sessionFooter,#content > :not(#modal)').forEach(node=>{modalBackground.push([node,node.inert]);node.inert=true;});
 $('closeModal').onclick=closeDialog;
 const backdrop=host.querySelector('.modal-backdrop');
 let startedOutside=false;
 backdrop.addEventListener('pointerdown',event=>{startedOutside=event.button===0&&event.target===backdrop;});
 backdrop.addEventListener('pointercancel',()=>{startedOutside=false;});
 backdrop.addEventListener('click',event=>{
  if(startedOutside&&event.target===backdrop)closeDialog();
  startedOutside=false;
 });
 host.querySelector('.modal').focus();
}
function closeDialog(){
 const opener=returnFocus;returnFocus=null;
 disposeMaps();if($('modal'))$('modal').innerHTML='';restoreModalBackground();
 if(opener?.isConnected)opener.focus({preventScroll:true});
}
document.addEventListener('keydown',e=>{if(e.defaultPrevented)return;const modal=$('modal')?.querySelector('.modal[role="dialog"]');if(!modal)return;if(e.key==='Escape'){if(document.querySelector('.solar-calendar'))return;e.preventDefault();closeDialog();return;}if(e.key==='Tab'){const items=[...modal.querySelectorAll('button,input,select,textarea,a[href],[tabindex="0"]')].filter(x=>!x.disabled&&x.type!=='hidden'&&x.getClientRects().length&&!x.closest('[hidden]'));const first=items[0],last=items.at(-1);if(!first){e.preventDefault();modal.focus();}else if(e.shiftKey&&(document.activeElement===first||document.activeElement===modal)){e.preventDefault();last.focus();}else if(!e.shiftKey&&(document.activeElement===last||document.activeElement===modal)){e.preventDefault();first.focus();}}});
function vehicleClassChangeWizard(id,row,body,onSaved){
 const today=()=>{const p=new Intl.DateTimeFormat('en-US',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'}).formatToParts(new Date());const f=t=>p.find(a=>a.type===t)?.value||'';return f('year')+'-'+f('month')+'-'+f('day');};
 const dateToday=today();
 const values={updateContract:true,contractEffectiveDate:dateToday,updateStudentDebt:false,studentDebtEffectiveDate:dateToday,updateDriverPay:false,driverPayEffectiveDate:dateToday};
 const stages=[
  {name:'قرارداد',title:'اصلاح کلاس خودرو در قرارداد',choice:'updateContract',date:'contractEffectiveDate',question:'آیا کلاس جدید خودرو در قراردادهای مرتبط ثبت شود؟',hint:'این انتخاب فقط کلاس خودرو را در الحاقیه قرارداد ثبت می‌کند. برای اصلاح مبلغ قرارداد و فاکتور قبلی، در مرحله بعد هم گزینه «بله» را انتخاب کنید.'},
  {name:'بدهی و فاکتور',title:'اصلاح مبلغ فاکتور و بدهی دانش‌آموزان',choice:'updateStudentDebt',date:'studentDebtEffectiveDate',question:'آیا مبلغ قابل پرداخت در فاکتور قبلی و تعهد مالی دانش‌آموز مطابق نرخ جدید اصلاح شود؟',hint:'مبلغ اصلاح‌شده در همان فاکتور و قرارداد قبلی نمایش داده می‌شود؛ مبالغ اولیه اسناد صادرشده و پرداخت‌ها برای حسابرسی حفظ می‌شوند.'},
  {name:'حقوق راننده',title:'اصلاح نرخ سرویس و حقوق راننده',choice:'updateDriverPay',date:'driverPayEffectiveDate',question:'آیا نرخ جدید در محاسبه حقوق راننده و مبلغ سرویس اعمال شود؟',hint:'تاریخ اثر این بخش مستقل از تاریخ بدهی است؛ اسناد حقوق قطعی دست‌نخورده باقی می‌مانند.'},
  {name:'تأیید نهایی',title:'بررسی و تأیید تغییرات',hint:'پیش از تأیید نهایی، هیچ‌یک از تصمیم‌های جدید ثبت نمی‌شوند.'}
 ];
 let stage=0,busy=false;
 const safe=x=>esc(String(x??''));
 const fa=x=>persianDigits(String(x??''));
 const shamsi=x=>x?fa(ServiceYarDates.toSolar(x)||x):'—';
 const currentClass=()=>fa(row?.tariff_vehicle_class??'نامشخص');
 const nextClass=()=>fa(body.tariffVehicleClass??'نامشخص');
 const decision=step=>values[step.choice];
 const render=()=>{
  const root=document.querySelector('#modal .modal-body'),s=stages[stage];
  if(!root)return;
  const nav=stages.map((x,i)=>'<li class="'+(i===stage?'is-current':i<stage?'is-done':'')+'" '+(i===stage?'aria-current="step"':'')+'><span class="vcw-index">'+(i<stage?'✓':fa(i+1))+'</span><span>'+safe(x.name)+'</span></li>').join('');
  let content='';
  if(s.choice){
   const yes=decision(s);
   content='<fieldset class="vcw-choice"><legend>'+safe(s.question)+'</legend>'+
    '<label class="vcw-choice-card '+(yes?'selected':'')+'"><input type="radio" name="classConsent" value="yes" '+(yes?'checked':'')+'><span><strong>بله، اعمال شود</strong><small>با تاریخ مؤثر انتخابی</small></span><span class="vcw-tick" aria-hidden="true">✓</span></label>'+
    '<label class="vcw-choice-card '+(!yes?'selected':'')+'"><input type="radio" name="classConsent" value="no" '+(!yes?'checked':'')+'><span><strong>خیر، بدون تغییر</strong><small>اطلاعات این بخش حفظ شود</small></span><span class="vcw-tick" aria-hidden="true">✓</span></label></fieldset>'+
    '<div class="vcw-date" '+(!yes?'hidden':'')+'><label for="vcwEffectiveDate">تاریخ مؤثر تغییر</label><input id="vcwEffectiveDate" type="date" name="classEffectiveDate" value="'+safe(values[s.date])+'" required><p>تاریخ از تقویم شمسی سامانه انتخاب می‌شود.</p></div>';
  }else{
   content='<div class="vcw-review">'+stages.slice(0,3).map(x=>'<div class="vcw-review-item"><span>'+safe(x.name)+'</span><strong class="'+(decision(x)?'vcw-approved':'vcw-unchanged')+'">'+(decision(x)?'بله، از '+safe(shamsi(values[x.date])):'خیر، بدون تغییر')+'</strong></div>').join('')+'</div>'+
    '<div class="vcw-notice"><strong>تأیید نهایی</strong><p>تنها پس از تأیید نهایی، خودرو و تصمیم‌های انتخابی ذخیره می‌شوند. لغو این مرحله تغییری ایجاد نمی‌کند.</p>'+(values.updateStudentDebt?'':'<p><strong>توجه:</strong> با انتخاب «خیر» در مرحله بدهی، مبلغ فاکتور قبلی و تعهد مالی قرارداد تغییر نخواهد کرد.</p>')+'</div>';
  }
  root.innerHTML='<section class="sy-change-wizard vcw" dir="rtl" aria-labelledby="vcwTitle">'+
   '<div class="vcw-headline"><span>فرایند تغییر کلاس خودرو</span><span class="vcw-count">مرحله '+fa(stage+1)+' از '+fa(stages.length)+'</span></div>'+
   '<ol class="vcw-steps" aria-label="مراحل تغییر کلاس خودرو">'+nav+'</ol>'+
   '<div class="vcw-classes"><div><span>کلاس قبلی</span><strong>'+safe(currentClass())+'</strong></div><span class="vcw-arrow" aria-hidden="true">←</span><div><span>کلاس جدید</span><strong>'+safe(nextClass())+'</strong></div></div>'+
   '<div class="vcw-main"><h3 id="vcwTitle" tabindex="-1">'+safe(s.title)+'</h3><p class="vcw-hint">'+safe(s.hint)+'</p>'+content+'<p id="vcwError" class="vcw-error" role="alert" tabindex="-1" hidden></p></div>'+
   '<div class="vcw-actions"><button type="button" class="vcw-button vcw-cancel" id="vcwCancel">انصراف</button><div><button type="button" class="vcw-button" id="vcwBack" '+(stage===0?'disabled':'')+'>مرحله قبل</button><button type="button" class="vcw-button vcw-primary" id="vcwNext">'+(stage===3?'تأیید و ثبت نهایی':'مرحله بعد')+'</button></div></div></section>';
  const date=root.querySelector('#vcwEffectiveDate');
  if(date){date.addEventListener('change',()=>{values[s.date]=date.value;});date.addEventListener('input',()=>{values[s.date]=date.value;});}
  root.querySelectorAll('input[name="classConsent"]').forEach(input=>input.addEventListener('change',()=>{
   values[s.choice]=input.value==='yes';
   root.querySelectorAll('.vcw-choice-card').forEach(card=>card.classList.toggle('selected',card.querySelector('input').checked));
   root.querySelector('.vcw-date').hidden=!values[s.choice];
  }));
  root.querySelector('#vcwCancel').onclick=()=>{if(busy)return;closeDialog();void formResource('vehicles',id,onSaved);};
  root.querySelector('#vcwBack').onclick=()=>{if(busy||stage===0)return;stage--;render();};
  root.querySelector('#vcwNext').onclick=async()=>{
   if(busy)return;
   const error=root.querySelector('#vcwError');
   error.hidden=true;
   if(s.choice&&values[s.choice]){
    const field=root.querySelector('#vcwEffectiveDate');
    if(!field?.value||!/^\d{4}-\d{2}-\d{2}$/.test(field.value)||!field.checkValidity()){
     error.textContent='تاریخ معتبر را از تقویم انتخاب کنید.';error.hidden=false;field?.focus();return;
    }
    values[s.date]=field.value;
   }
   if(stage<stages.length-1){stage++;render();return;}
   busy=true;
   const save=root.querySelector('#vcwNext');save.disabled=true;save.textContent='در حال ثبت تغییرات…';
   root.querySelector('#vcwBack').disabled=true;root.querySelector('#vcwCancel').disabled=true;
   root.querySelector('.vcw').setAttribute('aria-busy','true');
   try{
    const payload={...body,...values};
    for(const x of stages.slice(0,3))if(!values[x.choice])payload[x.date]=null;
    const data=await write('/api/v1/vehicles/'+encodeURIComponent(id)+'/class-change',payload,'PUT');
    lookupCache={};closeDialog();notify('تغییر کلاس خودرو ثبت شد. شناسه: '+number(data.changeId));
    if(onSaved)await onSaved();else await loadResource('vehicles');
   }catch(err){
    error.textContent=err?.message||'عملیات ذخیره انجام نشد. دوباره تلاش کنید.';
    error.hidden=false;error.focus();
    save.disabled=false;save.textContent='تأیید و ثبت نهایی';
    root.querySelector('#vcwBack').disabled=false;root.querySelector('#vcwCancel').disabled=false;
    root.querySelector('.vcw').removeAttribute('aria-busy');
   }finally{busy=false;}
  };
  root.querySelector('#vcwTitle')?.focus();
 };
 dialog('تغییر کلاس خودرو','<div role="status">در حال آماده‌سازی مراحل…</div>');
 render();
}
async function vehicleClassHistoryDialog(){
 dialog('گزارش تغییر کلاس خودرو','<div class="sy-class-report" role="region" aria-label="گزارش تغییر کلاس خودرو"><p role="status">در حال دریافت سوابق…</p></div>');
 const host=document.querySelector('#modal .modal-body');
 if(!host)return;
 host.closest('.modal')?.classList.add('sy-class-report-modal');
 const solar=date=>date?persianDigits(ServiceYarDates.toSolar(String(date).slice(0,10))||String(date)):'—';
 const fmt=value=>number(Math.round(Number(value||0)));
 let rows=[],filters={};
 host.innerHTML='<div class="sy-class-report" dir="rtl">'+
  '<header class="sy-class-report-head"><div><h3>تاریخچه تغییر کلاس خودرو</h3><p>مشاهده تصمیم‌های قراردادی، بدهی دانش‌آموزان و حقوق رانندگان، با حفظ سوابق قطعی</p></div></header>'+
  '<form id="vcHistoryFilters" class="sy-class-report-filters">'+
   '<label>نام راننده، پلاک یا شناسه تغییر<input type="search" name="search" placeholder="جستجو در تغییرات" maxlength="100"></label>'+
   '<label>از تاریخ ثبت<input type="date" name="from"></label><label>تا تاریخ ثبت<input type="date" name="to"></label>'+
   '<label>کلاس قبلی<select name="oldClass"><option value="">همه</option><option value="1">۱</option><option value="2">۲</option><option value="3">۳</option></select></label>'+
   '<label>کلاس جدید<select name="newClass"><option value="">همه</option><option value="1">۱</option><option value="2">۲</option><option value="3">۳</option></select></label>'+
   '<div class="sy-class-report-tools"><button type="submit" class="primary">جستجو و اعمال فیلتر</button><button id="vcHistoryReset" type="button">پاک‌کردن</button><button id="vcHistoryExport" type="button" disabled>خروجی Excel (CSV)</button><button id="vcHistoryPrint" type="button" disabled>چاپ گزارش</button></div></form>'+
  '<div id="vcHistoryCount" role="status" class="sy-class-report-summary"></div>'+
  '<div id="vcHistoryTable" class="table-wrap sy-class-report-table" tabindex="0"><p role="status">در حال بارگذاری…</p></div></div>';
 const form=host.querySelector('#vcHistoryFilters'),grid=host.querySelector('#vcHistoryTable'),count=host.querySelector('#vcHistoryCount');
 let request=0;
 const draw=()=>{
  count.textContent=fmt(rows.length)+' رویداد تغییر کلاس'+(rows.length>=1000?' · حداکثر ۱۰۰۰ رویداد اخیر متناسب با فیلتر نمایش داده شده است.':'');
  host.querySelector('#vcHistoryExport').disabled=!rows.length;
  host.querySelector('#vcHistoryPrint').disabled=!rows.length;
  if(!rows.length){grid.innerHTML='<p class="empty">هیچ تغییر کلاسی با این فیلترها ثبت نشده است.</p>';return;}
  grid.innerHTML='<table><thead><tr><th>ردیف</th><th>تاریخ ثبت</th><th>راننده و پلاک</th><th>کلاس خودرو</th><th>قراردادهای اصلاح‌شده</th><th>دانش‌آموزان</th><th>تاریخ تغییر بدهی</th><th>تاریخ حقوق</th><th>جمع تعدیل (ریال)</th><th>جزئیات</th></tr></thead><tbody>'+
  rows.map((r,i)=>'<tr><td>'+fmt(i+1)+'</td><td>'+esc(solar(r.created_at)+' · '+String(r.created_at||'').slice(11,16))+'</td>'+
   '<td>'+esc(r.driver_name||'—')+'<small class="sy-class-report-plate">'+esc((r.old_plate||'—')+' ← '+(r.new_plate||r.current_plate||'—'))+'</small></td>'+
   '<td>'+esc(persianDigits(String(r.old_vehicle_class||'—')+' ← '+String(r.new_vehicle_class||'—')))+'</td>'+
   '<td>'+fmt(r.amended_contract_count)+'</td><td>'+fmt(r.adjusted_student_count)+'</td>'+
   '<td>'+esc(Number(r.update_student_debt)?solar(r.student_debt_effective_date):'بدون تغییر')+'</td>'+
   '<td>'+esc(Number(r.update_driver_pay)?solar(r.driver_pay_effective_date):'بدون تغییر')+'</td>'+
   '<td>'+fmt(r.total_debt_adjustment)+'</td><td><button type="button" data-class-change-id="'+esc(r.id)+'">نمایش</button></td></tr>').join('')+'</tbody></table>';
  grid.querySelectorAll('[data-class-change-id]').forEach(button=>button.addEventListener('click',()=>void showDetail(button.dataset.classChangeId)));
 };
 const load=async()=>{
  const revision=++request;count.textContent='در حال دریافت تغییرات…';grid.setAttribute('aria-busy','true');
  try{
   const params=new URLSearchParams();
   Object.entries(filters).forEach(([key,value])=>{if(value)params.set(key,value);});
   const result=await api(scoped('/api/v1/vehicles/class-change-history'+(params.size?'?'+params:'')));
   if(revision!==request||!host.isConnected)return;
   rows=Array.isArray(result)?result:[];draw();
  }catch(error){if(revision===request&&host.isConnected){rows=[];count.textContent='خطا در دریافت گزارش';grid.innerHTML='<p class="error" role="alert">'+esc(error.message)+'</p>';}}
  finally{if(revision===request&&host.isConnected)grid.removeAttribute('aria-busy');}
 };
 const showDetail=async id=>{
  const record=rows.find(r=>String(r.id)===String(id));if(!record)return;
  const popup=document.createElement('section');popup.className='sy-class-report-detail';popup.setAttribute('role','region');popup.setAttribute('aria-label','جزئیات تغییر کلاس');
  popup.innerHTML='<p role="status">در حال دریافت جزئیات رویداد '+fmt(id)+'…</p>';
  host.querySelector('.sy-class-report-detail')?.remove();
  grid.after(popup);
  try{
   const data=await api(scoped('/api/v1/vehicles/class-change-history/'+encodeURIComponent(id)));
   if(!popup.isConnected)return;
   const adj=data.adjustments||[],contracts=data.contracts||[],rates=data.rates||[];
   popup.innerHTML='<div class="sy-class-report-detail-head"><h4>جزئیات رویداد '+fmt(id)+'</h4><button type="button" data-close-report-detail>بستن جزئیات</button></div>'+
    '<p>راننده: <strong>'+esc(record.driver_name||'—')+'</strong> | تغییر کلاس: '+esc(persianDigits(String(record.old_vehicle_class)+' ← '+String(record.new_vehicle_class)))+'</p>'+
    '<p>قرارداد: '+esc(record.contract_effective_date?solar(record.contract_effective_date):'بدون تغییر')+' | بدهی: '+esc(record.update_student_debt?solar(record.student_debt_effective_date):'بدون تغییر')+' | حقوق: '+esc(record.update_driver_pay?solar(record.driver_pay_effective_date):'بدون تغییر')+'</p>'+
    '<h5>قراردادهای دارای الحاقیه ('+fmt(contracts.length)+')</h5>'+
    (contracts.length?simpleTable(['شماره قرارداد','نوع','تاریخ اثر'],contracts.map(c=>[c.contract_no||c.contract_id,c.party_type,solar(c.effective_date)])):'<p class="muted">الحاقیه قراردادی ثبت نشده است.</p>')+
    '<h5>اسناد تعدیل بدهی دانش‌آموزان ('+fmt(adj.length)+')</h5>'+
    (adj.length?simpleTable(['دانش‌آموز','سال تحصیلی','تاریخ اثر','مبلغ تعدیل (ریال)'],adj.map(a=>[a.student_name||a.student_id,a.academic_year,solar(a.effective_date),fmt(a.amount_irr)])):'<p class="muted">سند تعدیل بدهی ثبت نشده است.</p>')+
    '<h5>تاریخچه نرخ مالی ('+fmt(rates.length)+')</h5>'+
    (rates.length?simpleTable(['نوع','شناسه دانش‌آموز','تاریخ اثر','نرخ قبلی (ریال)','نرخ جدید (ریال)'],rates.map(p=>[p.subject_type==='driver_pay'?'حقوق راننده':'بدهی دانش‌آموز',p.subject_id,solar(p.effective_date),fmt(p.old_monthly_irr),fmt(p.new_monthly_irr)])):'<p class="muted">نرخی ثبت نشده است.</p>');
   popup.querySelector('[data-close-report-detail]').onclick=()=>popup.remove();
   popup.scrollIntoView({block:'nearest',behavior:'smooth'});
  }catch(e){if(popup.isConnected)popup.innerHTML='<p class="error" role="alert">'+esc(e.message)+'</p>';}
 };
 form.onsubmit=e=>{e.preventDefault();filters=Object.fromEntries(new FormData(form));void load();};
 host.querySelector('#vcHistoryReset').onclick=()=>{form.reset();filters={};void load();};
 host.querySelector('#vcHistoryExport').onclick=()=>{
  if(!rows.length)return;
  const headings=['شناسه','تاریخ ثبت','راننده','پلاک قبلی','پلاک جدید','کلاس قبلی','کلاس جدید','تاریخ قرارداد','تعداد الحاقیه','تاریخ بدهی','تعداد دانش‌آموز','تاریخ حقوق','مبلغ تعدیل ریال','ثبت‌کننده'];
  const csvSafe=value=>{let v=String(value??'');if(/^[\s]*[=+@]/.test(v)||/^[\s]*-(?!\d+(?:\.\d+)?$)/.test(v))v=String.fromCharCode(39)+v;return '"'+v.replace(/"/g,'""')+'"';};
  const content=[headings,...rows.map(r=>[r.id,r.created_at,r.driver_name,r.old_plate,r.new_plate||r.current_plate,r.old_vehicle_class,r.new_vehicle_class,r.contract_effective_date,r.amended_contract_count,r.student_debt_effective_date,r.adjusted_student_count,r.driver_pay_effective_date,r.total_debt_adjustment,r.changed_by])].map(record=>record.map(csvSafe).join(',')).join('\r\n');
  const url=URL.createObjectURL(new Blob(['\ufeff',content],{type:'text/csv;charset=utf-8'}));
  const link=document.createElement('a');link.href=url;link.download='vehicle-class-changes.csv';document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),3000);
 };
 host.querySelector('#vcHistoryPrint').onclick=()=>{
  if(!rows.length)return;
  host.closest('.modal')?.classList.add('sy-class-report-printing');
  const closePrint=()=>host.closest('.modal')?.classList.remove('sy-class-report-printing');
  window.addEventListener('afterprint',closePrint,{once:true});
  window.print();
 };
 await load();
}
function resourceWizard(form,key){
 const definitions={students:[['اطلاعات ولی',['mobile','guardianFirstName','guardianLastName','guardianNationalCode','guardianRelation','emergencyPhone']],['مشخصات دانش‌آموز',['firstName','lastName','gender','nationality','nationalCode','fatherName','healthStatus']],['نشانی و مدرسه',['address','returnAddress','latitude','longitude','schoolId','grade','shift','academicYear']],['وضعیت و هزینه',['serviceStatus','tariffDistanceKm','tariffVehicleClass','monthlyFee']]],drivers:[['مشخصات راننده',['firstName','lastName','nationalCode','mobile','status']],['خودرو و نشانی',['vehicleId','isRepresentative','address']],['اطلاعات بانکی',['bankName','bankAccount','iban']]],services:[['مشخصات سرویس',['title','capacity','shift','status']],['مدرسه و راننده',['schoolId','driverId','vehicleModel','packId']],['مسیر و مبالغ',['startAddress','endAddress','feeSource','agreedAmount']]]};
 definitions.cheques=[['مشخصات چک',['bankName','branchName','serialNo','bankAccount']],['دریافت و سررسید',['receivedFrom','accountId','amount','receivedDate','dueDate','description','counterAccountId']]];
 definitions.vehicles=[['پلاک و فراخوانی اطلاعات',['plate']],['مشخصات خودرو',['ownerName','ownerNationalCode','vehicleType','vehicleSystem','vehicleTrim','model','tariffVehicleClass','capacity']],['اطلاعات تکمیلی',['driverId','vin','makeYear','fuelType','color','engineNumber','chassisNumber','insuranceNumber','sepandStatus','isActive']]];
 const groups=definitions[key];if(!groups)return;const grid=form.querySelector('.form-grid'),fields=[...grid.children],steps=[];form.classList.add('sy-record-wizard',`sy-record-wizard--${key}`);
 const progress=document.createElement('nav');progress.className='wizard-progress';progress.setAttribute('aria-label','مراحل تکمیل فرم');form.prepend(progress);
 for(const [title,names] of groups){const section=document.createElement('section');section.className='wizard-step';const heading=document.createElement('h4');heading.textContent=title;section.append(heading);const content=document.createElement('div');content.className='form-grid sy-wizard-fields';section.append(content);for(const name of names){const field=fields.find(l=>l.querySelector(`[name="${name}"]`));if(field)content.append(field);}steps.push({title,section,fields:names});grid.before(section);}
 const pricing=form.querySelector('.sy-service-price-status');
 if(pricing)steps.at(-1).section.append(pricing);
 const leftovers=[...grid.children];if(leftovers.length){const extra=document.createElement('details');extra.className='sy-wizard-extras';const summary=document.createElement('summary');summary.textContent='سایر مشخصات ('+number(leftovers.length)+' مورد)';const extraGrid=document.createElement('div');extraGrid.className='form-grid sy-wizard-fields';extraGrid.append(...leftovers);extra.append(summary,extraGrid);if(extraGrid.querySelector('[required]'))extra.open=true;steps.at(-1).section.append(extra);}grid.remove();
 const map=form.querySelector('.location-picker');if(map){const address=steps.find(step=>step.fields.includes('latitude'))||steps[0];address.section.append(map);}
 const calculator=form.querySelector('.financial-calculator');if(calculator)steps.at(-1).section.append(calculator);
 const review=document.createElement('section');review.className='wizard-step wizard-review';steps.at(-1).section.after(review);steps.push({title:'بازبینی و ثبت',section:review,fields:[]});
 const actions=document.createElement('div');actions.className='wizard-actions';actions.innerHTML='<button type="button" data-wizard-prev>مرحله قبل</button><span aria-live="polite" data-wizard-position></span><button type="button" class="primary" data-wizard-next>ادامه</button>';form.append(actions);const save=$('saveRecord');actions.append(save);form.noValidate=true;let current=0;
 const show=index=>{current=index;steps.forEach((step,i)=>step.section.hidden=i!==index);progress.innerHTML=steps.map((step,i)=>`<button type="button" class="wizard-marker ${i<index?'complete':''}" ${i===index?'aria-current="step"':''} ${i>index?'disabled':''} data-wizard-step="${i}" aria-label="مرحله ${number(i+1)}: ${esc(step.title)}"><b>${number(i+1)}</b>${esc(step.title)}</button>`).join('');progress.querySelectorAll('[data-wizard-step]').forEach(b=>b.onclick=()=>show(Number(b.dataset.wizardStep)));progress.querySelector('[aria-current="step"]')?.scrollIntoView({block:'nearest',inline:'nearest'});actions.querySelector('[data-wizard-prev]').hidden=index===0;actions.querySelector('[data-wizard-next]').hidden=index===steps.length-1;save.hidden=index!==steps.length-1;actions.querySelector('[data-wizard-position]').textContent='مرحله '+number(index+1)+' از '+number(steps.length);if(pricing&&index!==steps.length-1)steps.at(-2).section.append(pricing);if(index===steps.length-1){
  const valueOf=f=>{
   if(f.type==='hidden'&&f.dataset.solarEnhanced)return persianDigits(ServiceYarDates.toSolar(f.value));
   if(f.tagName==='SELECT')return f.selectedOptions[0]?.textContent||'—';
   if(f.type==='checkbox')return f.checked?'بله':'خیر';
   if(/Date$/.test(f.name))return dateText(f.value);
   if(f.type==='number'&&f.value!=='')return number(f.value)+(/amount|fee|total/i.test(f.name)?' ریال':'');
   return persianDigits(f.value||'—');
  };
  const sections=steps.slice(0,-1).map((step,i)=>{
   const fields=[...step.section.querySelectorAll('label')].map(label=>label.closest('[hidden]')?null:label.querySelector('input[name],select[name],textarea[name]')).filter(f=>f&&f.name!=='amountDisplay'&&f.type!=='hidden');
   return '<section class="sy-wizard-review-group"><div class="sy-wizard-review-heading"><h5>'+esc(step.title)+'</h5><button type="button" data-review-step="'+i+'" aria-label="ویرایش '+esc(step.title)+'">ویرایش این بخش</button></div><dl class="detail-grid">'+fields.map(f=>'<div><dt>'+esc(({vehicleModel:'نوع خودرو',vehicleClass:'کلاس تعرفه'}[f.name]||labels[f.name]||f.name))+'</dt><dd>'+esc(valueOf(f))+'</dd></div>').join('')+'</dl></section>';
  });
  review.innerHTML='<h4>بازبینی نهایی اطلاعات</h4>'+sections.join('');
  if(pricing)review.append(pricing);
  review.querySelectorAll('[data-review-step]').forEach(button=>button.onclick=()=>show(Number(button.dataset.reviewStep)));
}steps[index].section.querySelector('h4')?.setAttribute('tabindex','-1');steps[index].section.querySelector('h4')?.focus();requestAnimationFrame(()=>activeMaps.forEach(m=>m.invalidateSize()));};
 const invalid=section=>{const field=[...section.querySelectorAll('input,select,textarea')].find(f=>f.willValidate&&!f.checkValidity());if(field){const collapsed=field.closest('details.sy-wizard-extras');if(collapsed)collapsed.open=true;const message=field.validity.valueMissing?'این مورد را تکمیل کنید.':field.validity.patternMismatch?(field.title||'قالب مقدار واردشده صحیح نیست.'):field.validationMessage||'مقدار واردشده معتبر نیست.';const id='wizard-error-'+(field.name||'date')+'-'+[...form.elements].indexOf(field);let hint=form.querySelector('[id="'+id+'"]');if(!hint){hint=document.createElement('small');hint.id=id;hint.className='field-error';field.closest('label')?.append(hint);}hint.textContent=message;field.dataset.wizardError=id;field.setAttribute('aria-invalid','true');field.setAttribute('aria-describedby',id);field.setCustomValidity(message);}return field;};
 const clearWizardError=e=>{const field=e.target;if(!field.dataset?.wizardError)return;form.querySelector('[id="'+field.dataset.wizardError+'"]')?.remove();field.setCustomValidity('');field.removeAttribute('aria-invalid');field.removeAttribute('aria-describedby');delete field.dataset.wizardError;};form.addEventListener('input',clearWizardError,true);form.addEventListener('change',clearWizardError,true);
 actions.querySelector('[data-wizard-prev]').onclick=()=>show(current-1);actions.querySelector('[data-wizard-next]').onclick=()=>{const field=invalid(steps[current].section);if(field){field.focus();field.scrollIntoView({block:'center'});return;}show(current+1);};
 form.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.matches('input:not([type=submit]):not([type=button]):not([type=checkbox])')&&current<steps.length-1){e.preventDefault();actions.querySelector('[data-wizard-next]').click();}});form.addEventListener('submit',e=>{if(current<steps.length-1){e.preventDefault();e.stopImmediatePropagation();actions.querySelector('[data-wizard-next]').click();return;}for(let i=0;i<steps.length-1;i++){const field=invalid(steps[i].section);if(field){e.preventDefault();e.stopImmediatePropagation();show(i);field.focus();field.scrollIntoView({block:'center'});return;}}},true);show(0);
}
const ledgerPartyTypes={student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت'};
const ledgerMethods={cash:'نقدی',card:'کارت به کارت',bank:'پایا',transfer:'ساتنا',cheque:'چک',wallet:'کیف پول'};
let agencyMethodChoices=null,iranianBankChoices=null;
const methodLabel=code=>agencyMethodChoices?.find(m=>m.code===code)?.name||ledgerMethods[code]||code||'—';
const ledgerMoney=value=>Number(value||0).toLocaleString('fa-IR',{minimumFractionDigits:0,maximumFractionDigits:0});
function ledgerDateTime(value){
 if(!value)return 'ثبت نشده';
 const str=String(value).trim().replace(' ','T'),hasTime=str.includes('T');
 const date=new Date(str+(hasTime?'+03:30':'T12:00:00+03:30'));
 if(Number.isNaN(date.getTime()))return 'ثبت نشده';
 const options={year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'};
 if(hasTime){options.hour='2-digit';options.minute='2-digit';options.hourCycle='h23';}
 return new Intl.DateTimeFormat('fa-IR-u-ca-persian',options).format(date);
}
function showLedgerDetails(row){
 const receipt=row.direction==='receipt',kind=receipt?'دریافت':'پرداخت',partyRole=receipt?'پرداخت‌کننده':'دریافت‌کننده';
 const party=String(row.party_name||'').trim()||'نام طرف حساب ثبت نشده';
 const creator=String(row.creator_name||'').trim()||String(row.creator_username||'').trim()||'نام ثبت‌کننده موجود نیست';
 const item=(label,value,extra='')=>'<div class="ledger-detail-item"><dt>'+esc(label)+'</dt><dd '+extra+'>'+esc(value||'—')+'</dd></div>';
 dialog('جزئیات '+kind,`<article class="ledger-detail" aria-label="جزئیات سند مالی">
  <div class="ledger-detail-hero ${receipt?'is-receipt':'is-payment'}"><span class="ledger-detail-kind">${receipt?'↙':'↗'} سند ${kind}</span><span class="ledger-detail-state">${Number(row.is_active)===0?'غیرفعال':'فعال'}</span><div class="ledger-detail-amount"><strong dir="ltr">${ledgerMoney(row.amount)}</strong><span>ریال</span></div><p>مبلغ ثبت‌شده برای این تراکنش</p></div>
  <section class="ledger-detail-section"><h4>طرف حساب</h4><dl class="ledger-detail-grid">${item('نام '+partyRole,party)}${item('نوع طرف حساب',ledgerPartyTypes[row.party_type]||'نامشخص')}${item('نوع عملیات',kind)}</dl></section>
  <section class="ledger-detail-section"><h4>مشخصات تراکنش</h4><dl class="ledger-detail-grid">${item('تاریخ '+kind,dateText(row.entry_date))}${item('روش '+kind,methodLabel(row.method))}${item('حساب '+(receipt?'مقصد':'مبدأ')+' شرکت',row.company_account_name||'در اسناد قدیمی ثبت نشده')}${item('بانک شرکت',row.company_bank_name||'—')}${item('شماره حساب',persianDigits(row.company_bank_account||'—'))}${item('شماره شبا',persianDigits(row.company_iban||'—'))}${item('شماره پیگیری',persianDigits(row.reference_no||'ثبت نشده'),'dir="auto"')}${item('مبلغ (ریال)',ledgerMoney(row.amount))}</dl></section>
  <section class="ledger-detail-section"><h4>ثبت و مستندات</h4><dl class="ledger-detail-grid">${item('ثبت‌کننده',creator)}${item('تاریخ و ساعت ثبت',ledgerDateTime(row.created_at))}</dl><div class="ledger-detail-note"><b>توضیحات</b><p>${esc(String(row.note||'').trim()||'توضیحی برای این سند ثبت نشده است.')}</p></div></section>
  <div class="ledger-detail-actions"><button type="button" id="ledgerDetailClose">بستن جزئیات</button></div>
 </article>`);
 $('ledgerDetailClose').onclick=closeDialog;
}
async function viewRecord(key,id){
 try{
  const r=await api(scoped('/api/v1/'+key+'/'+id));
  if(key==='ledger'){showLedgerDetails(r);return;}
  if(key==='invoices'){
   const facts=[['شماره فاکتور',number(r.id)],['طرف حساب',persianDigits(r.party_name||'نامشخص')],['اطلاعات تکمیلی',persianDigits(r.party_meta||'—')],['شماره قرارداد',persianDigits(String(r.contract_no||'—').replace(/\.0+$/,''))],['تاریخ صدور',dateText(r.issue_date)],['سررسید',dateText(r.due_date)],['مبلغ فاکتور',number(r.amount)],['دریافتی مستقیم',number(r.paid_amount)],['مانده فاکتور',number(r.remaining_amount)]];
   dialog('جزئیات فاکتور', '<section class="sy-invoice-detail"><h3>'+esc(persianDigits(r.title))+'</h3><dl>'+facts.map(([k,v])=>'<div><dt>'+esc(k)+'</dt><dd>'+esc(v)+'</dd></div>').join('')+'</dl><h3>پرداخت‌های متصل به فاکتور</h3>'+((r.payments||[]).length?simpleTable(['تاریخ','مبلغ (ریال)','روش','پیگیری'],r.payments.map(p=>[dateText(p.payment_date),number(p.amount),methodLabel(p.method),persianDigits(p.reference_no||'—')])):'<p class="muted">پرداخت مستقیم ثبت نشده است.</p>')+'</section>');return;
  }
  if(key==='schools'){
   const name=String(r.name||'مدرسه'),photo=schoolPhotoUrl(r);
   const lat=Number(r.latitude),lon=Number(r.longitude);
   const coordsValid=r.latitude!==null&&r.longitude!==null&&r.latitude!==''&&r.longitude!==''&&Number.isFinite(lat)&&Number.isFinite(lon)&&Math.abs(lat)<=90&&Math.abs(lon)<=180;
   const text=v=>esc(String(v===null||v===undefined||v===''?'—':v));
   const photoHtml=`<img id="sySchoolDetailsPhoto" class="sy-school-details-image" src="${esc(photo||'/assets/school-placeholder.png')}" alt="تصویر مدرسه ${esc(name)}" loading="lazy">`;
   const info=[['کد آموزشگاه',r.code],['نوع مدرسه',r.school_type],['ناحیه',r.region],['مدیر مدرسه',r.manager_name],['نشانی',r.address],['تلفن',r.phone]];
   dialog('جزئیات مدرسه',`<div class="sy-school-details">
    <div class="sy-school-details-hero"><div class="sy-school-details-cover">${photoHtml}</div>
     <div class="sy-school-details-summary"><span class="sy-school-details-kicker">پرونده مدرسه</span>
      <h2>${esc(name)}</h2><p>${text(r.region)} · ${text(r.school_type)}</p>
      ${can('schools','manage')?`<button type="button" class="primary" id="syDetailsEditSchool">ویرایش اطلاعات و تصویر</button>`:''}
     </div>
    </div>
    <section class="sy-school-details-section"><h3>اطلاعات مدرسه</h3><dl class="sy-school-details-facts">
     ${info.map(([key,value])=>`<div><dt>${esc(key)}</dt><dd>${text(value)}</dd></div>`).join('')}
    </dl></section>
    <section class="sy-school-details-section"><div class="sy-school-details-section-head"><h3>موقعیت مدرسه روی نقشه نشان</h3>${coordsValid?'<span>موقعیت ثبت‌شده</span>':''}</div>
     ${coordsValid?'<div id="sySchoolDetailsMap" class="sy-school-details-map" aria-label="موقعیت مدرسه در نقشه نشان"></div>':'<p class="sy-school-map-empty">برای این مدرسه هنوز موقعیت جغرافیایی ثبت نشده است. از بخش ویرایش، مکان را مشخص کنید.</p>'}
    </section>
   </div>`);
   document.getElementById('syDetailsEditSchool')?.addEventListener('click',()=>formResource('schools',id));
   const image=document.getElementById('sySchoolDetailsPhoto');
   image?.addEventListener('error',()=>{
    if(!image.src.endsWith('/assets/school-placeholder.png'))image.src='/assets/school-placeholder.png';
   });
   if(coordsValid){
    const node=document.getElementById('sySchoolDetailsMap');
    const map=await makeMap(node,[lat,lon],15);
    if(map&&node?.isConnected&&window.L){
     const marker=L.marker([lat,lon],{title:name}).addTo(map);
     marker.bindPopup(document.createTextNode(name)).openPopup();
     requestAnimationFrame(()=>map.invalidateSize());
    }
   }
   return;
  }

  const showFinance=key==='students';
  const detailFields=key==='students'?[...resources[key][1],'annualMonths','annualFee','invoiceTotal','paidTotal','outstandingFee']:resources[key][1],detailGroups={students:[['مشخصات دانش‌آموز',['firstName','lastName','nationalCode','gender','fatherName','nationality','healthStatus']],['اطلاعات ولی',['guardianFirstName','guardianLastName','guardianNationalCode','guardianRelation','mobile','emergencyPhone']],['مدرسه و سرویس',['schoolId','grade','shift','academicYear','serviceStatus','feeSource','monthlyFee','annualMonths','annualFee','invoiceTotal','paidTotal','outstandingFee']],['نشانی و سایر اطلاعات',['address','returnAddress','latitude','longitude']]],drivers:[['مشخصات راننده',['firstName','lastName','nationalCode','mobile','status']],['خودرو و نشانی',['vehicleId','isRepresentative','address']],['اطلاعات بانکی',['bankName','bankAccount','iban']]],services:[['مشخصات سرویس',['title','code','status','capacity','shift']],['مدرسه و راننده',['schoolId','driverId','packId']],['مسیر و مبالغ',['startAddress','endAddress','monthlyFee','agreedAmount']]]};
  const detailValue=f=>{if(key==='services'&&f==='monthlyFee')return number(r.driver_net_monthly??r.monthly_fee)+' ریال';if(key==='students'&&f==='annualFee')return r.annual_fee==null?'—':number(r.annual_fee)+' ریال';if(key==='students'&&['invoiceTotal','paidTotal','outstandingFee'].includes(f))return number(r[{invoiceTotal:'invoice_total',paidTotal:'paid_total',outstandingFee:'outstanding_fee'}[f]]??0)+' ریال';if(key==='students'&&f==='annualMonths')return r.annual_months==null?'—':(Number(r.annual_months)===8.5?'۸٫۵':'۹')+' ماه';const v=val(r,f);return /Date$/.test(f)?dateText(v):/amount|fee|balance|total|capacity|score|rating/i.test(f)?number(v):['status','serviceStatus'].includes(f)?(statuses[v]||v||'—'):fieldOptions(key,f)?.find(x=>String(x[0])===String(v))?.[1]||persianDigits(v||'—');};
  const detailRows=fields=>'<dl class="detail-grid sy-detail-grid">'+fields.map(f=>'<div><dt>'+esc(labels[f]||f)+'</dt><dd>'+esc(detailValue(f))+'</dd></div>').join('')+'</dl>';
  const groups=detailGroups[key],used=new Set(groups?.flatMap(([,fields])=>fields)||[]);
  const content=groups?groups.map(([title,fields])=>{const included=fields.filter(f=>detailFields.includes(f));return included.length?'<section class="sy-detail-section"><h4>'+esc(title)+'</h4>'+detailRows(included)+'</section>':'';}).join('')+(detailFields.some(f=>!used.has(f))?'<section class="sy-detail-section"><h4>سایر مشخصات</h4>'+detailRows(detailFields.filter(f=>!used.has(f)))+'</section>':''):detailRows(detailFields);
  dialog('جزئیات '+resourceName(key),'<div class="sy-record-details sy-detailed-profile">'+content+'</div>'+(showFinance?'<section id="syStudentAccount"></section><section id="studentServiceFinance"></section>':'')+(key==='drivers'?'<section id="syDriverAccount"></section>':''));
  if(showFinance){studentAccountPanel($('syStudentAccount'),id);if(can('school-statement'))serviceFinancePanel($('studentServiceFinance'),{studentId:id,academicYear:r.academic_year||year()});}
  if(key==='drivers')driverAccountPanel($('syDriverAccount'),id);
 }catch(e){notify(e.message,true);}
}
function fieldOptions(key,f){if(f==='sepandStatus')return [['unknown','نامشخص'],['pending','در حال بررسی'],['approved','تأیید شده'],['rejected','رد شده']];if(f==='gender')return [['male','مرد'],['female','زن']];if(f==='fuelType')return [['بنزین','بنزین'],['گاز و بنزین','گاز و بنزین'],['گازوئیل','گازوئیل'],['برقی','برقی'],['هیبریدی','هیبریدی']];if(key==='schools'&&f==='region')return [['1','۱'],['2','۲'],['3','۳'],['4','۴'],['5','۵'],['6','۶'],['7','۷'],['تبادکان','تبادکان']];if(f==='feeSource')return [['taxi','تعرفه مصوب سازمان تاکسیرانی'],['agency','تعرفه اختصاصی شرکت'],['agreed','مبلغ توافقی']];if(f==='tariffVehicleClass')return [['1','کلاس ۱'],['2','کلاس ۲'],['3','کلاس ۳']];if(f==='gender')return [['boy','پسر'],['girl','دختر'],['mixed','مختلط']];if(f==='partyType'||f==='ownerType'||f==='recipientType')return [['student','دانش‌آموز'],['driver','راننده'],['school','مدرسه'],['agency','شرکت']];if(f==='direction')return [['receipt','دریافت'],['payment','پرداخت']];if(f==='method'&&key==='ledger')return (agencyMethodChoices||Object.entries(ledgerMethods).map(([code,name])=>({code,name,active:true}))).filter(m=>m.active).map(m=>[m.code,m.name]);if(f==='channel')return [['internal','داخلی'],['sms','پیامک']];if(f==='nationality')return [['iranian','ایرانی'],['foreign','غیر ایرانی']];if(f==='isActive'||f==='isRepresentative')return [['1','بله'],['0','خیر']];if(f==='serviceStatus')return ['no_request','pending','prepayment','waiting','assigned','cancelled'].map(k=>[k,statuses[k]]);if(f==='status'){const values={drivers:['pending','active','inactive'],services:['draft','active','cancelled'],messages:['queued'],invoices:['open','paid','overdue'],contracts:['draft','active','cancelled'],'driver-work':['pending','approved','cancelled'],'parent-reports':['open','closed'],'driver-changes':['pending']};return (values[key]||['draft','active','cancelled']).map(k=>[k,statuses[k]||k]);}return null;}
async function selectSchoolFromCatalog(){
 dialog('انتخاب مدرسه از فهرست مرکزی',`<p>مدرسه را با نام یا کد آموزش‌وپرورش جستجو کنید. مدرسه انتخاب‌شده به شرکت شما متصل می‌شود و نیازی به ثبت مجدد نیست.</p><label>جستجوی مدرسه<input id="catalogSearch" placeholder="نام یا کد مدرسه" autocomplete="off"></label><div id="catalogResults" aria-live="polite">در حال دریافت مدارس…</div>`);
 let version=0;
 const refresh=async()=>{const token=++version,term=$('catalogSearch')?.value||'';try{const rows=await api(scoped('/api/v1/school-catalog?search='+encodeURIComponent(term)));if(token!==version||!$('catalogResults'))return;$('catalogResults').innerHTML=rows.length?'<div class="table-wrap"><table><thead><tr><th>کد</th><th>مدرسه</th><th>ناحیه</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>'+rows.map(r=>`<tr><td>${esc(r.code)}</td><td>${esc(r.name)}</td><td>${esc(r.region||'—')}</td><td>${Number(r.selected)?'انتخاب شده':'جدید'}</td><td><button type="button" data-catalog-id="${r.id}" ${Number(r.selected)?'disabled':''}>انتخاب مدرسه</button></td></tr>`).join('')+'</tbody></table></div>':'مدرسه‌ای یافت نشد.';$('catalogResults').querySelectorAll('[data-catalog-id]').forEach(button=>button.onclick=async()=>{button.disabled=true;try{await write('/api/v1/school-catalog/select',{catalogSchoolId:Number(button.dataset.catalogId)});closeDialog();lookupCache={};notify('مدرسه به شرکت اضافه شد.');if(gridState?.key==='schools')await loadResource('schools');}catch(e){notify(e.message,true);button.disabled=false;}});}catch(e){if(token===version&&$('catalogResults'))$('catalogResults').textContent=e.message;}};
 let timer;$('catalogSearch').oninput=()=>{clearTimeout(timer);timer=setTimeout(refresh,250);};await refresh();
}

/** Keep the driver wizard mounted while opening the secondary vehicle picker. */
async function attachDriverVehiclePicker(form,row){
 const select=form.elements.vehicleId;
 if(!select)return;
 const outer=select.closest('label');
 if(!outer)return;
 const label=document.createElement('div');
 label.className='sy-driver-plate-control';
 label.innerHTML='<span class="sy-driver-plate-caption">پلاک خودروی راننده</span><div class="sy-driver-plate-inline"><div class="sy-driver-plate-current" aria-live="polite"></div>'+
  '<button type="button" class="sy-driver-plate-edit" aria-label="ویرایش یا جایگزینی خودروی راننده" title="جستجو و تغییر خودرو"><svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L9.5 16.5 5 18l1.5-4.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></button></div><small>پلاک به‌صورت معمول قابل ویرایش نیست؛ برای تغییر، آیکون مداد را انتخاب کنید.</small>';
 outer.replaceWith(label);
 select.hidden=true;select.removeAttribute('data-relation');
 select.removeAttribute('required');
 label.append(select);
 const current=label.querySelector('.sy-driver-plate-current');
 const plateCache=new Map();
 const setVehicle=(vehicle)=>{
  if(vehicle?.id){plateCache.set(String(vehicle.id),vehicle);select.replaceChildren(new Option(vehicle.plate||'خودرو',String(vehicle.id),true,true));}
  else if(!select.value)select.replaceChildren(new Option('فاقد خودرو','',true,true));
  current.innerHTML=vehicle?.plate?iranPlate(vehicle.plate):'<span class="sy-no-driver-vehicle">خودرویی انتخاب نشده است</span>';
  select.dispatchEvent(new Event('change',{bubbles:true}));
 };
 let initial=null;
 try{
  if(select.value){initial=await api(scoped('/api/v1/vehicles/'+encodeURIComponent(select.value)));}
 }catch(e){current.textContent='دریافت پلاک ناموفق بود: '+e.message;}
 if(form.isConnected)setVehicle(initial|| (row.vehicle_id?{id:row.vehicle_id,plate:'پلاک نامشخص'}:null));
 const launch=()=>{
  if(!form.isConnected)return;
  const parent=$('modal')?.querySelector('.modal');
  if(!parent)return;
  const shell=document.createElement('div');shell.className='sy-driver-vehicle-overlay';
  shell.innerHTML='<section class="sy-driver-vehicle-dialog" role="dialog" aria-modal="true" aria-label="جستجو یا ثبت خودروی راننده" tabindex="-1">'+
   '<header><div><span>مدیریت خودروی راننده</span><h3>جستجو و جایگزینی خودرو</h3></div><button type="button" data-vehicle-close aria-label="بستن پنجره">×</button></header>'+
   '<div class="sy-driver-vehicle-content"><div class="sy-driver-vehicle-actions"><label>جستجوی پلاک در خودروهای شرکت و سوابق سپند<div data-vehicle-search>'+iranPlateInput('edit')+'</div></label><button type="button" class="primary" data-vehicle-new>ثبت خودروی جدید</button></div>'+
   '<div data-vehicle-status role="status" aria-live="polite"></div><div data-vehicle-results></div><div data-vehicle-registration hidden></div></div></section>';
  parent.append(shell);
  const popup=shell.querySelector('[role=dialog]'),status=shell.querySelector('[data-vehicle-status]'),results=shell.querySelector('[data-vehicle-results]'),search=shell.querySelector('[data-vehicle-search]'),registration=shell.querySelector('[data-vehicle-registration]');
  const close=()=>{shell.removeEventListener('keydown',keyHandler);shell.remove();label.querySelector('.sy-driver-plate-edit')?.focus();};
  const keyHandler=e=>{if(e.key==='Escape'){e.stopPropagation();e.preventDefault();close();}};
  shell.addEventListener('keydown',keyHandler);
  shell.querySelector('[data-vehicle-close]').onclick=close;
  shell.addEventListener('click',e=>{if(e.target===shell)close();});
  const normalize=v=>ServiceYarDates.digits(String(v??'')).replace(/[\s\-\/ـ]/g,'').replace(/ایران/g,'');
  const toParts=v=>{
   const raw=normalize(v),match=raw.match(/^(\d{2})([آ-یA-Za-z])(\d{3})(\d{2})$/u)||(()=>{const m=raw.match(/^(\d{2})(\d{3})([آ-یA-Za-z])(\d{2})$/u);return m?[m[0],m[1],m[3],m[2],m[4]]:null;})();
   return match?[match[1],match[2],match[3],match[4]]:null;
  };
  const plateFields=['first','letter','middle','region'].map(part=>search.querySelector('[data-plate-'+part+']'));
  const searchParts=()=>plateFields.map(field=>normalize(field.value));
  let sequence=0,wait=0;
  const find=async()=>{
   const ticket=++sequence,parts=searchParts(),term=parts.join('');
   registration.hidden=true;results.hidden=false;
   if(!term.length){status.textContent='حداقل یکی از بخش‌های پلاک را وارد کنید.';results.replaceChildren();return;}
   status.textContent='در حال جستجو در سوابق خودروها…';
   try{
    const found=await api(scoped('/api/v1/vehicles/registry-search?'+new URLSearchParams({plateFirst:parts[0],plateLetter:parts[1],plateMiddle:parts[2],plateRegion:parts[3]})));
    if(ticket!==sequence||!shell.isConnected)return;
    status.textContent=found.length?number(found.length)+' خودرو یافت شد.':'خودرویی یافت نشد؛ می‌توانید خودروی جدید ثبت کنید.';
    results.innerHTML=found.length?'<div class="sy-driver-vehicle-list">'+found.map((vehicle,i)=>
     '<article class="sy-driver-vehicle-item"><div>'+iranPlate(vehicle.plate)+'<p>'+esc(vehicle.model||vehicle.vehicleType||'مدل نامشخص')+
     ' · '+(vehicle.inCurrentAgency?'ثبت‌شده در شرکت جاری':'موجود در سوابق سپند یا سایر شرکت‌ها')+'</p></div>'+
     '<button type="button" data-vehicle-choice="'+i+'">'+(vehicle.inCurrentAgency?'انتخاب خودرو':'فراخوانی و ثبت برای شرکت')+'</button></article>').join('')+'</div>':'<p class="muted">نتیجه‌ای یافت نشد.</p>';
    results.querySelectorAll('[data-vehicle-choice]').forEach(button=>button.onclick=async()=>{
     const v=found[Number(button.dataset.vehicleChoice)];
     if(v.inCurrentAgency){
      if(row?.id){
       button.disabled=true;
       try{
        await write('/api/v1/drivers/'+encodeURIComponent(row.id),{vehicleId:Number(v.id)},'PUT');
        lookupCache={};setVehicle(v);close();notify('خودرو به راننده تخصیص داده شد.');
       }catch(error){status.textContent='تخصیص خودرو انجام نشد: '+error.message;button.disabled=false;}
      }else{setVehicle(v);close();notify('خودرو انتخاب شد؛ پس از ثبت راننده، ارتباط ذخیره می‌شود.');}
      return;
     }
     registrationForm(v); 
    });
   }catch(e){if(ticket===sequence&&shell.isConnected){status.textContent='خطا در جستجوی خودرو: '+e.message;results.replaceChildren();}}
  };
  search.addEventListener('input',()=>{sequence++;clearTimeout(wait);wait=setTimeout(find,300);});
  const registrationForm=(prefill=null)=>{
   results.hidden=true;registration.hidden=false;status.textContent=prefill?'اطلاعات خودرو از سوابق مشترک فراخوانی شد؛ برای اتصال به راننده باید یک نسخه در شرکت جاری ثبت شود.':'مشخصات خودروی جدید را تکمیل کنید.';
   registration.innerHTML='<form class="sy-driver-vehicle-new"><h4>'+(prefill?'ثبت خودروی فراخوانی‌شده':'ثبت خودروی جدید')+'</h4>'+
    '<fieldset class="vehicle-plate-filter sy-vehicle-plate-editor"><legend>پلاک ایران</legend>'+iranPlateInput('edit')+'</fieldset>'+
    '<div class="form-grid">'+
    '<label>نوع خودرو (جستجوی مدل)<input name="vehicleTrim" type="search" placeholder="نام مدل را جستجو کنید"></label>'+
    '<label>کلاس خودرو<select name="tariffVehicleClass" required><option value="">انتخاب کلاس</option><option value="1">کلاس ۱</option><option value="2">کلاس ۲</option><option value="3">کلاس ۳</option></select></label>'+
    '<label>ظرفیت<input name="capacity" type="number" min="1" max="50" value="4" required></label>'+
    '<label>نام مالک<input name="ownerName" maxlength="200"></label>'+
    '<label>شماره شاسی (VIN)<input name="vin" maxlength="40" dir="ltr"></label>'+
    '<label>تیپ یا سیستم خودرو<input name="vehicleSystem" maxlength="100"></label>'+
    '<label>سال ساخت<input name="makeYear" type="number" min="1300" max="2100" inputmode="numeric"></label>'+
    '<label>رنگ خودرو<input name="color" maxlength="70"></label>'+
    '<label>نوع سوخت<select name="fuelType"><option value="">نامشخص</option><option value="بنزین">بنزین</option><option value="گاز و بنزین">گاز و بنزین</option><option value="گازوئیل">گازوئیل</option><option value="هیبریدی">هیبریدی</option><option value="برقی">برقی</option></select></label>'+
    '<label>شماره موتور<input name="engineNumber" maxlength="100"></label>'+
    '<label>شماره شاسی<input name="chassisNumber" maxlength="100"></label>'+
    '<label>شماره بیمه<input name="insuranceNumber" maxlength="120"></label></div>'+
    '<p data-new-vehicle-error role="alert" class="error" hidden></p>'+
    '<footer><button type="button" data-register-back>بازگشت به جستجو</button><button class="primary" type="submit">ثبت خودرو و انتخاب برای راننده</button></footer></form>';
   const newForm=registration.querySelector('form'),plate=registration.querySelector('fieldset');
   const [first,letter,middle,region]=[plate.querySelector('[data-plate-first]'),plate.querySelector('[data-plate-letter]'),plate.querySelector('[data-plate-middle]'),plate.querySelector('[data-plate-region]')];
   const original=prefill?toParts(prefill.plate):searchParts();
   if(original){first.value=persianDigits(original[0]);letter.value=original[1];middle.value=persianDigits(original[2]);region.value=persianDigits(original[3]);}
   if(prefill?.capacity)newForm.elements.capacity.value=prefill.capacity;
   if(prefill?.tariffVehicleClass)newForm.elements.tariffVehicleClass.value=prefill.tariffVehicleClass;
   const picker=window.ServiceYarVehicleModels?window.ServiceYarVehicleModels.attach(newForm.elements.vehicleTrim,newForm.elements.tariffVehicleClass,{initialValue:prefill?.model||''}):Promise.resolve(null);
   newForm.elements.vehicleTrim.required=true;
   newForm.querySelector('[data-register-back]').onclick=()=>{registration.hidden=true;results.hidden=false;status.textContent='خودرو را از نتایج انتخاب کنید یا پلاک جدید وارد کنید.';};
   const digit=v=>normalize(v).replace(/\D/g,'');
   newForm.onsubmit=async e=>{
    e.preventDefault();
    const parts=[digit(first.value),letter.value,digit(middle.value),digit(region.value)];
    if(parts[0].length!==2||!parts[1]||parts[2].length!==3||parts[3].length!==2){registration.querySelector('[data-new-vehicle-error]').hidden=false;registration.querySelector('[data-new-vehicle-error]').textContent='پلاک ایران را کامل کنید.';return;}
    const button=newForm.querySelector('[type=submit]');button.disabled=true;
    const err=registration.querySelector('[data-new-vehicle-error]');err.hidden=true;
    try{
     const modelPicker=await picker;
     if(newForm.elements.vehicleTrim.value.trim()&&modelPicker&&!newForm.elements.vehicleTrim.dataset.vehicleFamily)
      throw Error('مدل خودرو را از فهرست جستجوی مدل‌های تأییدشده انتخاب کنید.');
     const payload={plate:parts[0]+parts[1]+parts[2]+'-'+parts[3],
      vehicleTrim:newForm.elements.vehicleTrim.value.trim(),model:newForm.elements.vehicleTrim.value.trim(),
      tariffVehicleClass:newForm.elements.tariffVehicleClass.value,capacity:Number(newForm.elements.capacity.value),
      ownerName:newForm.elements.ownerName.value.trim(),vin:newForm.elements.vin.value.trim(),
      vehicleSystem:newForm.elements.vehicleSystem.value.trim(),makeYear:newForm.elements.makeYear.value||null,
      color:newForm.elements.color.value.trim(),fuelType:newForm.elements.fuelType.value,
      engineNumber:newForm.elements.engineNumber.value.trim(),chassisNumber:newForm.elements.chassisNumber.value.trim(),
      insuranceNumber:newForm.elements.insuranceNumber.value.trim(),...(row?.id?{driverId:Number(row.id)}:{})};
     const saved=await write('/api/v1/vehicles',payload,'POST');
     lookupCache={};
     setVehicle({id:saved.id,plate:saved.plate||payload.plate});
     close();
     notify(row?.id?'خودرو ثبت و بلافاصله به راننده تخصیص داده شد.':'خودرو ثبت و انتخاب شد؛ با ذخیره راننده، ارتباط نهایی می‌شود.');
    }catch(error){err.hidden=false;err.textContent='ثبت خودرو انجام نشد: '+error.message;button.disabled=false;}
   };
  };
  shell.querySelector('[data-vehicle-new]').onclick=()=>registrationForm();
  popup.focus();
  const initialParts=toParts(plateCache.get(select.value)?.plate);
  if(initialParts)plateFields.forEach((field,i)=>{field.value=initialParts[i];});
  if(searchParts().some(Boolean))void find();
 };
 label.querySelector('.sy-driver-plate-edit').onclick=launch;
}
/**
 * Explicitly apply official Taxi Organization prices to a saved service or
 * every active service of a driver. A calculated quote is not a mutation.
 */
function attachOfficialTaxiTariffActions(form,key,id){
 if(!id||!['services','drivers'].includes(key))return;
 const kind=key==='services'?'service':'driver';
 const box=document.createElement('section');box.className='sy-official-tariff-apply';
 box.innerHTML='<h4>اعمال نرخ‌نامه تاکسیرانی</h4>'+
  '<p>اعمال تعرفه، شهریه و صورتحساب‌های قابل اصلاح را به‌روزرسانی می‌کند.</p>'+
  '<div class="member-actions"><button type="button" data-official-preview>محاسبه و بررسی تعرفه</button>'+
  '<button type="button" class="primary" data-official-apply>تأیید و اعمال تعرفه برای '+(kind==='service'?'این سرویس':'سرویس‌های راننده')+'</button></div>'+
  '<div data-official-status role="status" aria-live="polite"></div>';
 // Keep the action with the pricing fields in step three. The wizard's own
 // navigation moves the user to the final review before it submits edits.
 const steps=[...form.querySelectorAll('.wizard-step:not(.wizard-review)')];
 (steps.at(-1)||form).append(box);
 const preview=box.querySelector('[data-official-preview]'),apply=box.querySelector('[data-official-apply]'),status=box.querySelector('[data-official-status]');
 const endpoint='/api/v1/operations/tariffs/apply-'+kind+'/'+encodeURIComponent(id);
 const isTaxi=()=>kind!=='service'||form.elements.feeSource?.value==='taxi';
 let verified=false,busy=false,unsaved=false,revision=0;
 const syncApply=()=>{
   apply.disabled=busy||!isTaxi()||(!unsaved&&!verified);
   if(kind==='service'&&isTaxi())apply.textContent='اعمال نرخ‌نامه هنگام ذخیره';
   else apply.textContent='تأیید و اعمال تعرفه برای '+(kind==='service'?'این سرویس':'سرویس‌های راننده');
 };
 const invalidate=()=>{
   revision++;unsaved=true;verified=false;
   form.dataset.applyTaxiAfterSave='';
   status.textContent=isTaxi()
      ?'برای ثبت هم‌زمان تغییرات و تعرفه، «اعمال نرخ‌نامه هنگام ذخیره» را انتخاب کنید.'
      :'ابتدا مبنای محاسبه هزینه سرویس را «تعرفه مصوب سازمان تاکسیرانی» انتخاب کنید.';
   syncApply();
 };
 form.addEventListener('input',event=>{if(event.isTrusted&&!box.contains(event.target))invalidate();});
 form.addEventListener('change',event=>{if(event.isTrusted&&!box.contains(event.target))invalidate();});
 const previewRequest=async()=>{
   if(unsaved){
     status.textContent='پیش‌نمایش مبالغ بر اساس اطلاعات فعلی فرم در همین مرحله نمایش داده می‌شود.';
     return false;
   }
   if(!isTaxi()){
     status.textContent='ابتدا گزینه «تعرفه مصوب سازمان تاکسیرانی» را انتخاب کنید.';
     return false;
   }
   const requested=revision;
   verified=false;busy=true;preview.disabled=true;syncApply();
   status.textContent='در حال بررسی مبالغ ثبت‌شده، کلاس خودرو و نرخ‌نامه…';
   try{
     const result=await api(scoped(endpoint));
     if(!box.isConnected||requested!==revision)return false;
     const rows=result.services||[];
     status.innerHTML='<p>سرویس‌ها: '+number(result.serviceCount)+' | دانش‌آموزان: '+number(result.studentCount)+
       ' | تغییرات: '+number(result.changes)+' | موارد مسدودکننده: '+number(result.blocking)+'</p>'+
       rows.map(item=>'<details class="sy-taxi-apply-service"><summary>'+esc(item.serviceTitle||('سرویس '+number(item.serviceId)))+
        ' · '+number(item.studentCount)+' دانش‌آموز'+(item.errors?.length?' · دارای خطا':'')+'</summary>'+
        (item.newServiceFee==null?'':'<p>خالص ثبت‌شده: '+number(item.oldServiceFee)+' ریال · خالص طبق تعرفه: '+number(item.newServiceFee)+' ریال</p>')+
        (item.students?.length?simpleTable(['دانش‌آموز','شهریه فعلی (ریال)','شهریه طبق نرخ‌نامه (ریال)'],
          item.students.map(st=>[st.name,number(st.oldFee),number(st.newFee)])):'')+
        (item.errors?.length?'<ul class="sy-official-tariff-errors">'+item.errors.map(err=>'<li>'+esc(err)+'</li>').join('')+'</ul>':'')+'</details>').join('')+
       (result.canApply?'<p class="sy-official-ready">مبالغ معتبر است؛ با دکمه تأیید، تعرفه روی سرویس ثبت می‌شود.</p>':
        '<p class="error">برای اعمال تعرفه باید ابتدا ایرادات مشخص‌شده را برطرف کنید.</p>');
     verified=Boolean(result.canApply);return verified;
   }catch(error){
     if(requested===revision){status.textContent='پیش‌نمایش تعرفه ناموفق بود: '+error.message;notify(error.message,true);}
     return false;
   }finally{busy=false;if(preview.isConnected)preview.disabled=false;syncApply();}
 };
 preview.addEventListener('click',previewRequest);
 apply.addEventListener('click',async()=>{
   if(busy||!isTaxi())return;
   if(unsaved||kind==='service'){
     if(kind!=='service'){
       status.textContent='ابتدا تغییرات راننده را با دکمه ذخیره ثبت کنید و سپس تعرفه سرویس‌های او را اعمال کنید.';
       return;
     }
     if(!confirm('تغییرات سرویس ذخیره و سپس نرخ‌نامه مصوب سازمان تاکسیرانی بر دانش‌آموزان این سرویس اعمال شود؟ دریافت‌ها و اسناد قطعی گذشته تغییر نمی‌کنند.'))return;
     form.dataset.applyTaxiAfterSave='1';
     status.textContent='اعمال نرخ‌نامه تأیید شد. در مرحله بازبینی نهایی، دکمه «ذخیره» را بزنید تا ذخیره و اعمال تعرفه انجام شود.';
     const next=form.querySelector('[data-wizard-next]');
     if(next&&!next.hidden)next.click();
     else form.querySelector('[data-wizard-step]:last-child')?.click();
     const review=form.querySelector('.wizard-review');
     if(review&&!review.hidden&&!review.querySelector('[data-taxi-save-confirmation]')){
       const message=document.createElement('p');
       message.dataset.taxiSaveConfirmation='1';
       message.className='sy-official-tariff-confirmation';
       message.setAttribute('role','status');
       message.textContent='اعمال نرخ‌نامه مصوب تاکسیرانی انتخاب شده است. با دکمه «ذخیره»، اطلاعات سرویس ثبت و بلافاصله مبالغ تعرفه سازمان اعمال می‌شوند.';
       review.prepend(message);
     }
     return;
   }
   if(!verified&&!(await previewRequest()))return;
   if(!confirm('نرخ‌نامه سازمان تاکسیرانی بر '+(kind==='service'?'این سرویس':'تمام سرویس‌های فعال راننده')+
    ' اعمال شود؟ شهریه دانش‌آموزان و صورتحساب‌های قابل اصلاح به‌روزرسانی می‌شوند و دریافت‌ها و اسناد قطعی قبلی محفوظ می‌مانند.'))return;
   busy=true;preview.disabled=true;syncApply();
   status.textContent='در حال ذخیره نرخ‌نامه و همگام‌سازی صورتحساب‌ها…';
   try{
     const result=await write(endpoint,{confirm:'APPLY_OFFICIAL_TAXI_TARIFF'});
     verified=false;
     status.innerHTML='<p role="status">نرخ سازمان ثبت شد: '+number(result.servicesUpdated)+' سرویس و '+
       number(result.studentsUpdated)+' دانش‌آموز به‌روزرسانی شد.</p>';
     notify('نرخ‌نامه مصوب تاکسیرانی با موفقیت بر سرویس اعمال شد.');
   }catch(error){status.textContent='اعمال تعرفه انجام نشد: '+error.message;notify(error.message,true);}
   finally{busy=false;preview.disabled=false;syncApply();}
 });
 syncApply();
 // A freshly opened edit form is clean: show whether the existing service
 // qualifies for a real apply, instead of leaving the confirmation disabled.
 if(isTaxi())void previewRequest();
}

async function formResource(key,id,onSaved){
 if(!can(key,'manage'))return;if(key==='schools'&&!id){await selectSchoolFromCatalog();return;}const token=pageToken;let row={};try{if(id)row=await api(scoped('/api/v1/'+key+'/'+id));if(token!==pageToken)return;
 let companyAccountOptions=[],bankOptions=[];
 if(key==='ledger'){
   const [methods,accounts]=await Promise.all([api(scoped('/api/v1/finance/payment-methods')),api(scoped('/api/v1/accounts?active=1&limit=200'))]);
   if(token!==pageToken)return;
   agencyMethodChoices=methods;
   const allAccounts=[...(accounts.items||[])];let accountPage=2;
   while(accountPage<=Number(accounts.pagination?.pages||1)){
     const next=await api(scoped('/api/v1/accounts?active=1&limit=200&page='+accountPage++));if(token!==pageToken)return;
     allAccounts.push(...(next.items||[]));
   }
   companyAccountOptions=allAccounts.map(r=>[String(r.id),r.code+' · '+r.title+(r.bank_name?' · '+r.bank_name:'')]);
   if(row.company_account_id&&!companyAccountOptions.some(([id])=>id===String(row.company_account_id))){
     const old=await api(scoped('/api/v1/accounts/'+row.company_account_id));if(token!==pageToken)return;companyAccountOptions.push([String(old.id),old.code+' · '+old.title+' (حساب سابق)']);
   }
 }
 if(key==='accounts'){bankOptions=(await api(scoped('/api/v1/finance/banks'))).map(name=>[name,name]);if(token!==pageToken)return;}
 const required={schools:['name'],students:['firstName','lastName','mobile','schoolId'],drivers:['firstName','lastName'],services:['title'],vehicles:['plate'],ledger:['partyType','partyId','direction','amount','entryDate'],'driver-work':['driverId','workDate','amount'],'driver-payments':['driverId','paymentDate','amount']};
 dialog((id?'ویرایش ':'ثبت ')+resourceName(key),`<form id="editForm" class="${key==='ledger'?'sy-ledger-editor':''}"><div class="form-grid">${resources[key][1].map(f=>{const defaults={academicYear:year(),nationality:'iranian',capacity:4,feeSource:'taxi',serviceStatus:'no_request',isRepresentative:0,direction:'receipt',channel:'internal',monthlyFee:0,agreedAmount:0};const original=val(row,f);const v=original!==''?original:(id?'':defaults[f]??''),options=f==='companyAccountId'&&key==='ledger'?companyAccountOptions:f==='bankName'&&key==='accounts'?bankOptions:f==='method'&&key==='ledger'&&id&&row.method&&!fieldOptions(key,f).some(([code])=>code===row.method)?[...fieldOptions(key,f),[row.method,methodLabel(row.method)+' (روش سابق)']]:fieldOptions(key,f),req=required[key]?.includes(f)||f==='companyAccountId'&&key==='ledger'&&!id?'required':'',text=/address|body|note|reason|description|response/i.test(f),relation=relations[f]||(['partyId','ownerId','recipientId'].includes(f)?'party':null),rialField=f==='amount'&&['invoices','payments','driver-payments','school-charges','service-charges','contracts'].includes(key);return `<label>${labels[f]||esc(f)}${req?' *':''}${f==='amount'&&key==='ledger'?`<div class="sy-money-field"><input type="text" name="amountDisplay" data-money-display inputmode="numeric" autocomplete="off" spellcheck="false" dir="ltr" placeholder="۰" aria-label="مبلغ به ریال" ${req} value="${esc(v!==''?number(v):'')}"><span class="sy-money-unit">ریال</span><input type="hidden" name="amount" value="${esc(v)}"></div><small class="sy-money-hint">مبلغ را بدون اعشار وارد کنید؛ ارقام به‌صورت سه‌رقمی جدا می‌شوند.</small>`:options?`<select name="${f}" ${req}><option value="">انتخاب کنید</option>${options.map(([value,title])=>`<option value="${esc(value)}" ${String(v)===String(value)?'selected':''}>${esc(title)}</option>`).join('')}</select>`:relation?`<input data-lookup-search="${f}" placeholder="جستجوی ${labels[f]}" aria-label="جستجوی ${labels[f]}"><select name="${f}" aria-label="${esc(labels[f]||f)}" data-relation="${relation}" ${req}><option value="">انتخاب کنید</option>${v?`<option value="${esc(v)}" selected>#${esc(v)}</option>`:''}</select>`:rialField?`<input type="text" name="${f}" data-rial-amount inputmode="numeric" autocomplete="off" dir="ltr" ${req} placeholder="۰" value="${esc(v!==''?number(v):'')}" aria-label="${esc(labels[f]||f)} به ریال"><small class="sy-money-hint">مبلغ به ریال؛ بدون اعشار و با جداکننده هزارگان</small>`:text?`<textarea name="${f}" maxlength="6000" ${req}>${esc(v)}</textarea>`:`<input name="${f}" ${req} ${/Date$/.test(f)?'type="date"':/distanceKm/i.test(f)?'type="number" min="0" step="1" inputmode="numeric"':/amount|fee|capacity|score|latitude|longitude|rating|balance/i.test(f)?'type="number" step="any"':/mobile|phone/i.test(f)?'inputmode="tel"':'maxlength="255"'} value="${esc(v)}">`}</label>`}).join('')}</div><p class="muted">مبالغ به ریال و تمام تاریخ‌های ورودی و نمایشی شمسی هستند.</p><button class="primary" id="saveRecord">ذخیره</button></form>`);
 const form=$('editForm');
 if(key==='invoices'&&id&&row.party_type==='student'&&row.class_adjustment_amount!==undefined&&Number(row.class_adjustment_amount)!==0){
  const field=form?.elements?.amount,info=document.createElement('div');
  info.className='sy-invoice-amendment-notice';
  info.setAttribute('role','status');
  info.innerHTML='<strong>فاکتور قبلی دارای اصلاحیه مالی است.</strong><p>مبلغ اولیه: '+number(row.original_amount??row.amount)+' ریال | تعدیل کلاس: '+number(row.class_adjustment_amount)+' ریال | <strong>مبلغ اصلاح‌شده: '+number(row.effective_amount)+' ریال</strong></p><p>نسخه اولیه فاکتور و پرداخت‌های ثبت‌شده برای حسابرسی حفظ می‌شوند.</p>';
  form?.prepend(info);
  if(field){field.value=number(row.effective_amount);field.readOnly=true;field.setAttribute('aria-label','مبلغ اصلاح‌شده فاکتور، فقط خواندنی');}
 }
 if(key==='drivers')await attachDriverVehiclePicker(form,row);
 if(key==='vehicles'){
  // The owner of a vehicle is not necessarily its assigned driver.
  // The canonical relationship is drivers.vehicle_id; this editor sends
  // driverId as a virtual field handled atomically on the backend.
  const parent=form.querySelector('.form-grid');
  const field=document.createElement('label');
  field.className='sy-vehicle-driver-control';
  field.innerHTML='<span>راننده تخصیص‌یافته</span><input type="search" data-vehicle-driver-search placeholder="جستجوی نام، نام خانوادگی یا کد ملی راننده" autocomplete="off">'+
   '<select name="driverId" aria-label="راننده تخصیص‌یافته"><option value="">فاقد راننده (حذف تخصیص)</option></select>'+
   '<small>مالک خودرو و راننده دو مشخصه مستقل هستند. راننده را از شرکت انتخاب کنید یا با کد ملی از سوابق مشترک فراخوانی کنید. خودرو باید فعال باشد.</small>';
  parent?.append(field);
  const select=field.querySelector('select'),search=field.querySelector('input');
  try{
   const drivers=await lookup('drivers',true);
   if(!form.isConnected)return;
   const assigned=drivers.filter(d=>id&&String(d.vehicle_id||'')===String(id)&&Number(d.is_active??1)===1);
   const original=assigned[0]||null;
   let selectedDriverId=String(original?.id||'');
   const fill=term=>{
    const q=normalizeServiceSearch(term);
    const items=drivers.filter(d=>Number(d.is_active??1)===1 && (!q||normalizeServiceSearch(person(d)+' '+(d.national_code||'')+' '+(d.mobile||'')).includes(q)));
    const chosen=selectedDriverId;
    select.replaceChildren(new Option('فاقد راننده (حذف تخصیص)',''));
    for(const d of items.slice(0,250))select.add(new Option(person(d)+' · '+(d.national_code||'بدون کد ملی'),String(d.id)));
    if(chosen&&!Array.from(select.options).some(opt=>opt.value===chosen)){
     const match=drivers.find(d=>String(d.id)===chosen);
     if(match)select.add(new Option(person(match)+' (انتخاب‌شده)',chosen));
    }
    select.value=chosen;
   };
   fill('');
   const tools=document.createElement('div');tools.className='member-actions';
   tools.innerHTML='<button type="button" data-driver-registry>فراخوانی راننده با کد ملی</button><button type="button" class="primary" data-driver-register>ثبت راننده جدید</button>';
   field.append(tools);
   const openRegistration=async(prefill={})=>{
    if(!form.isConnected)return;
    const parent=form.closest('.modal'),shell=document.createElement('div');
    shell.className='sy-driver-vehicle-overlay';
    shell.innerHTML='<section class="sy-driver-vehicle-dialog" role="dialog" aria-modal="true" aria-label="ثبت راننده" tabindex="-1"><header><h3>ثبت راننده برای شرکت</h3><button type="button" data-close-driver aria-label="بستن">×</button></header><div class="sy-driver-vehicle-content"><form><div class="form-grid"><label>نام<input name="firstName" required maxlength="100"></label><label>نام خانوادگی<input name="lastName" required maxlength="100"></label><label>کد ملی<input name="nationalCode" inputmode="numeric" maxlength="10" required></label><label>موبایل<input name="mobile" inputmode="tel" maxlength="11"></label><label>نشانی<textarea name="address"></textarea></label></div><p class="muted">راننده در شرکت جاری ثبت می‌شود؛ تخصیص خودرو با ذخیره فرم اصلی انجام خواهد شد.</p><p data-driver-error role="alert"></p><button class="primary" type="submit">ثبت و انتخاب راننده</button></form></div></section>';
    parent.append(shell);
    const child=shell.querySelector('form'),error=shell.querySelector('[data-driver-error]'),save=child.querySelector('[type=submit]');
    const close=()=>{if(save.disabled)return;shell.remove();tools.querySelector('[data-driver-register]').focus();};
    shell.querySelector('[data-close-driver]').onclick=close;
    shell.onkeydown=e=>{if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close();}};
    for(const key of ['firstName','lastName','nationalCode','mobile'])child.elements[key].value=prefill[key]||'';
    child.onsubmit=async e=>{
     e.preventDefault();e.stopPropagation();save.disabled=true;error.textContent='';
     try{
      const body=Object.fromEntries(new FormData(child));body.nationalCode=ServiceYarDates.digits(body.nationalCode.trim());body.mobile=ServiceYarDates.digits(body.mobile.trim());
      const existing=drivers.find(d=>String(d.national_code)===body.nationalCode);
      if(existing&&Number(existing.is_active??1)!==1)throw Error('راننده قبلاً ثبت شده ولی غیرفعال است؛ ابتدا وضعیت او را بررسی کنید.');
      const saved=existing||await write('/api/v1/drivers',{...body,status:'pending'},'POST');
      if(!saved?.id)throw Error('شناسه راننده ثبت‌شده دریافت نشد.');
      if(!existing)drivers.push({...saved,first_name:body.firstName,last_name:body.lastName,national_code:body.nationalCode,is_active:1});
      selectedDriverId=String(saved.id);lookupCache={};fill('');select.dispatchEvent(new Event('change',{bubbles:true}));shell.remove();notify('راننده ثبت و انتخاب شد؛ خودرو را ذخیره کنید.');
     }catch(e){error.textContent=e.message;}finally{save.disabled=false;}
    };
    child.elements.firstName.focus();
   };
   tools.querySelector('[data-driver-register]').onclick=()=>openRegistration();
   tools.querySelector('[data-driver-registry]').onclick=async()=>{
    const code=ServiceYarDates.digits(search.value.trim());
    if(!/^\d{10}$/.test(code)){notify('کد ملی ده‌رقمی راننده را در کادر جستجو وارد کنید.',true);search.focus();return;}
    const button=tools.querySelector('[data-driver-registry]');button.disabled=true;
    try{
     const local=drivers.find(d=>String(d.national_code)===code);
     if(local){if(Number(local.is_active??1)!==1)throw Error('راننده غیرفعال است.');selectedDriverId=String(local.id);fill('');select.dispatchEvent(new Event('change',{bubbles:true}));return;}
     const preview=await api(scoped('/api/v1/vehicles/owner-driver-preview?nationalCode='+encodeURIComponent(code)));
     if(!form.isConnected)return;
     if(preview.found){if(preview.inCurrentAgency)throw Error('راننده در شرکت وجود دارد؛ فهرست را دوباره باز کنید.');await openRegistration({...preview,nationalCode:code});}
     else{notify('راننده در سوابق یافت نشد؛ اطلاعات را ثبت کنید.');await openRegistration({nationalCode:code});}
    }catch(e){notify(e.message,true);}finally{button.disabled=false;}
   };
   if(assigned.length>1){
    field.querySelector('small').textContent='هشدار: این خودرو به چند راننده مرتبط است؛ ابتدا ارتباط‌های تکراری را اصلاح کنید.';
   }
   let debounce;
   search.addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(()=>fill(search.value),220);});
   select.addEventListener('change',()=>{selectedDriverId=select.value;const a=drivers.find(d=>String(d.id)===select.value);search.value=a?person(a):'';});
  }catch(error){
   field.querySelector('small').textContent='فهرست رانندگان دریافت نشد: '+error.message;
   select.disabled=true;
  }
 }
 if(key==='services'){
  form.elements.monthlyFee?.closest('label')?.remove();
  form.elements.code?.closest('label')?.remove();
  // The service controls the vehicle actually registered to its driver.
  // A selected model/class updates that registered vehicle transactionally.
  const driverField=form.elements.driverId;
  const vehicleBox=document.createElement('div');vehicleBox.className='sy-service-vehicle-selection';
  vehicleBox.innerHTML='<h4>نوع و کلاس خودروی سرویس</h4><div class="form-grid">'+
   '<label>نوع خودرو (جستجوی لحظه‌ای)<input type="search" name="vehicleModel" placeholder="نام خودرو را تایپ و از فهرست انتخاب کنید"></label>'+
   '<label>کلاس تعرفه (قابل تغییر)<select name="vehicleClass"><option value="">کلاس نامشخص</option><option value="1">کلاس ۱</option><option value="2">کلاس ۲</option><option value="3">کلاس ۳</option></select></label></div>'+
   '<small>نوع و کلاس انتخابی روی خودروی ثبت‌شده راننده ذخیره می‌شود؛ تغییر کلاس می‌تواند شهریه سرویس‌های مرتبط را نیز به‌روزرسانی کند.</small>';
  driverField?.closest('label')?.after(vehicleBox);
  if(!driverField)form.querySelector('.form-grid')?.prepend(vehicleBox);
  let serviceModelPicker;
  const refreshVehicle=async()=>{
   try{
    const driverId=String(driverField?.value||row.driver_id||'');
    const drivers=await lookup('drivers');
    const driver=drivers.find(d=>String(d.id)===driverId)||
      (driverId?await api(scoped('/api/v1/drivers/'+encodeURIComponent(driverId))):null);
    if(!driver)return;
    let vehicles=await lookup('vehicles'),vehicle=vehicles.find(v=>String(v.id)===String(driver.vehicle_id));
    if(!vehicle&&driver.vehicle_id)vehicle=await api(scoped('/api/v1/vehicles/'+driver.vehicle_id));
    if(serviceModelPicker&&form.isConnected){serviceModelPicker.setVehicle(vehicle);void refresh();}
   }catch(e){if(form.isConnected)notify('مشخصات خودروی راننده دریافت نشد: '+e.message,true);}
  };
  if(window.ServiceYarVehicleModels)
   void window.ServiceYarVehicleModels.attach(form.elements.vehicleModel,form.elements.vehicleClass,{
    onChange:()=>{if(typeof refresh==='function')void refresh();}
   }).then(picker=>{serviceModelPicker=picker;void refreshVehicle();});
  driverField?.addEventListener('change',()=>{void refreshVehicle();});
  const agreed=form.elements.agreedAmount,source=form.elements.feeSource;
  const status=document.createElement('section');status.className='sy-service-price-status';status.setAttribute('role','status');status.setAttribute('aria-live','polite');
  source?.closest('label')?.after(status);
  let priceRequest=0;
  const refresh=async()=>{
   const version=++priceRequest,mode=source?.value||'taxi';
   if(agreed?.closest('label'))agreed.closest('label').hidden=mode!=='agreed';
   if(mode==='agreed'){status.textContent='مبلغ توافقی راننده را وارد کنید.';return;}
   if(!id){status.textContent='پس از انتخاب دانش‌آموزان مبلغ تعرفه محاسبه می‌شود.';return;}
   status.textContent='در حال دریافت محاسبه بر اساس '+(mode==='taxi'?'نرخ سازمان':'تعرفه شرکت')+'…';
   try{const [members,rates]=await Promise.all([api(scoped('/api/v1/services/'+id+'/students')),api(scoped('/api/v1/operations/driver-deduction-rates'))]);let total=0,officialDriver=0;
    const activeMembers=members.filter(student=>Number(student.is_active)===1);
    if(!activeMembers.length){if(version===priceRequest)status.textContent='دانش‌آموز فعالی به این سرویس متصل نیست.';return;}
    for(const student of activeMembers){
     if(version!==priceRequest||!form.isConnected)return;
     const result=await write('/api/v1/operations/tariffs/quote',{studentId:student.id,driverId:form.elements.driverId?.value||row.driver_id,vehicleClass:form.elements.vehicleClass?.value||undefined,feeSource:mode});
     total+=Number(result.monthlyFee);officialDriver+=Number(result.driverBaseMonthly);
    }
    if(version!==priceRequest||!form.isConnected)return;
    const net=driverFeeBreakdown(total,rates).net;
    status.innerHTML='<h5>مبالغ ماهانه (ریال)</h5><dl class="sy-service-price-summary">'+
      [['جمع شهریه اولیا',total],['حق‌الزحمه مرجع راننده',officialDriver],['خالص قابل پرداخت به راننده',net]]
        .map(([label,value])=>'<div><dt>'+label+'</dt><dd>'+number(value)+'</dd></div>').join('')+'</dl>';
   }catch(e){if(version===priceRequest&&form.isConnected)status.textContent='محاسبه ناموفق: '+e.message;}
  };
  source?.addEventListener('change',refresh);
  form.elements.vehicleClass.addEventListener('change',refresh);
  void refresh();
 }
 if(key==='vehicles'){
  form.classList.add('sy-vehicle-edit-form');
  const plate=form.elements.plate;
  if(plate){
   const label=plate.closest('label');
   const normalized=normalizePlate(plate.value).replace(/[-/ـ]/g,'').replace(/ایران/g,'');
   let match=normalized.match(/^(\d{2})([آ-یA-Za-z])(\d{3})(\d{2})$/u);
   if(!match){const other=normalized.match(/^(\d{2})(\d{3})([آ-یA-Za-z])(\d{2})$/u);if(other)match=[other[0],other[1],other[3],other[2],other[4]];}
   const letters='آ ا ب ج د س ص ط ق ل م ن و ه ی ت ع ف ک گ پ ث ز ش'.split(' ');
   // Reuse the exact plate presentation already used in the vehicle search form.
   const field=document.createElement('fieldset');field.className='vehicle-plate-filter sy-vehicle-plate-editor';
   field.innerHTML='<legend>پلاک خودرو</legend>'+iranPlateInput('edit')+
    '<small class="sy-plate-edit-help" data-plate-hint>هر بخش از پلاک را جداگانه وارد کنید.</small>';
   label.replaceWith(field);field.append(plate);plate.type='hidden';
   const first=field.querySelector('[data-plate-first]'),middle=field.querySelector('[data-plate-middle]'),letter=field.querySelector('[data-plate-letter]'),region=field.querySelector('[data-plate-region]');
   if(match){first.value=persianDigits(match[1]);letter.value=match[2];middle.value=persianDigits(match[3]);region.value=persianDigits(match[4]);}
   const digits=v=>normalizePlate(v).replace(/\D/g,'');
   const sync=()=>{
    [first,middle,region].forEach(input=>{input.value=persianDigits(digits(input.value).slice(0,input.maxLength));});
    const ready=first.value.length===2&&middle.value.length===3&&region.value.length===2&&letter.value!=='';
    const rawFirst=digits(first.value),rawMiddle=digits(middle.value),rawRegion=digits(region.value);
    plate.value=ready?rawFirst+letter.value+rawMiddle+'-'+rawRegion:'';
    field.classList.toggle('sy-plate-invalid',!ready);
    field.querySelector('[data-plate-hint]').textContent=ready?'شماره پلاک: '+persianDigits(first.value)+' '+letter.value+' '+persianDigits(middle.value)+' ایران '+persianDigits(region.value):'پلاک را کامل کنید: ۲ رقم، حرف، ۳ رقم و کد دو رقمی ایران.';
    return ready;
   };
   [first,middle,region].forEach(input=>input.addEventListener('input',sync));
   letter.addEventListener('change',sync);
   form.addEventListener('submit',event=>{if(!sync()){event.preventDefault();event.stopImmediatePropagation();[first,letter,middle,region].find(x=>!x.value||(x!==letter&&x.value.length!==x.maxLength))?.focus();notify('تمام قسمت‌های پلاک ایران را کامل کنید.',true);}},true);
   if(!id){
    const lookupButton=document.createElement('button');
    lookupButton.type='button';lookupButton.className='sy-vehicle-lookup-button primary';
    lookupButton.textContent='فراخوانی اطلاعات خودرو';
    const lookupStatus=document.createElement('p');
    lookupStatus.className='sy-vehicle-lookup-status';lookupStatus.setAttribute('role','status');
    lookupStatus.setAttribute('aria-live','polite');
    field.append(lookupButton,lookupStatus);
    let requestVersion=0;
    [first,middle,letter,region].forEach(el=>el.addEventListener('input',()=>{requestVersion++;lookupStatus.textContent='';}));
    letter.addEventListener('change',()=>{requestVersion++;lookupStatus.textContent='';});
    lookupButton.addEventListener('click',async()=>{
     if(!sync()){lookupStatus.textContent='ابتدا پلاک را کامل کنید.';return;}
     const version=++requestVersion,requestedPlate=plate.value;
     lookupButton.disabled=true;lookupStatus.textContent='در حال جستجوی اطلاعات این پلاک…';
     try{
      const result=await api(scoped('/api/v1/vehicles/lookup-by-plate?plate='+encodeURIComponent(requestedPlate)));
      if(version!==requestVersion||!form.isConnected||plate.value!==requestedPlate)return;
      if(!result.found){lookupStatus.textContent='خودرویی با این پلاک ثبت نشده است؛ اطلاعات خودرو را دستی تکمیل کنید.';return;}
      const vehicle=result.vehicle||{};
      const fields={ownerName:'owner_name',vin:'vin',vehicleType:'vehicle_type',vehicleSystem:'vehicle_system',makeYear:'make_year',fuelType:'fuel_type',color:'color',engineNumber:'engine_number',chassisNumber:'chassis_number',insuranceNumber:'insurance_number',sepandStatus:'sepand_status',capacity:'capacity',tariffVehicleClass:'tariff_vehicle_class'};
      for(const [fieldName,column] of Object.entries(fields)){
       const input=form.elements[fieldName];
       if(input&&vehicle[column]!==null&&vehicle[column]!==undefined&&String(vehicle[column])!==''){
        input.value=String(vehicle[column]);input.dispatchEvent(new Event('change',{bubbles:true}));
       }
      }
      // Keep the approved model picker synchronized with its validation state.
      const model=form.elements.vehicleTrim;
      if(model){
       const picker=await vehicleModelPickerPromise;
       if(version!==requestVersion||!form.isConnected||plate.value!==requestedPlate)return;
       if(picker)picker.setVehicle(vehicle);
       else {model.value=vehicle.vehicle_trim||vehicle.model||'';model.dispatchEvent(new Event('change',{bubbles:true}));}
      }
      // A registry lookup copies facts into this new company record; it does not
      // update or link the other agency's vehicle or its drivers.
      lookupStatus.textContent=(result.source==='current_agency'?'این پلاک قبلاً در شرکت شما وجود دارد. ':'اطلاعات خودرو از سوابق موجود فراخوانی شد. ')+'مشخصات را بررسی و در صورت نیاز ویرایش کنید؛ سپس ثبت را انجام دهید.';
     }catch(error){if(version===requestVersion&&form.isConnected)lookupStatus.textContent='فراخوانی اطلاعات ناموفق بود: '+error.message;}
     finally{if(version===requestVersion&&form.isConnected)lookupButton.disabled=false;}
    });
   }
   if(match)sync();
  }
  for(const [name,css] of [['ownerName','sy-vehicle-owner-field'],['model','sy-vehicle-model-field'],['tariffVehicleClass','sy-vehicle-tariff-class-field'],['capacity','sy-vehicle-capacity-field']]){
   const input=form.elements[name];input?.closest('label')?.classList.add(css);
  }
  const tariffClass=form.elements.tariffVehicleClass;
  // The approved type list is searchable; choosing a model suggests the
  // class but the operator can override it before saving.
  if(form.elements.model){
   form.elements.model.readOnly=true;
   form.elements.model.closest('label').hidden=true;
  }
  const vehicleModelPickerPromise=form.elements.vehicleTrim&&tariffClass&&window.ServiceYarVehicleModels
   ?window.ServiceYarVehicleModels.attach(form.elements.vehicleTrim,tariffClass,{
    modelMirror:form.elements.model,initialValue:row.vehicle_trim||row.model||''
   }):Promise.resolve(null);
  if(tariffClass){tariffClass.required=!id;tariffClass.closest('label')?.append(Object.assign(document.createElement('small'),{textContent:'کلاس خودرو مبنای محاسبه سرویس‌های مرتبط است. در خودروهای قدیمی فاقد کلاس، برای سایر ویرایش‌ها می‌توانید این فیلد را خالی بگذارید؛ در اولین فرصت کلاس معتبر را تعیین کنید.'}));}
  if(!id&&form.elements.ownerNationalCode){
   const codeInput=form.elements.ownerNationalCode;
   codeInput.inputMode='numeric';codeInput.maxLength=10;codeInput.autocomplete='off';
   codeInput.placeholder='کد ملی ۱۰ رقمی مالک خودرو';
   codeInput.setAttribute('aria-label','کد ملی مالک خودرو');
   const status=document.createElement('div');
   status.className='sy-owner-driver-status';
   status.setAttribute('role','status');status.setAttribute('aria-live','polite');
   codeInput.closest('label')?.append(status);
   const retry=document.createElement('button');
   retry.type='button';retry.className='sy-owner-driver-retry';
   retry.textContent='بررسی مجدد و فراخوانی راننده';
   retry.hidden=true;status.after(retry);
   let approvedCode='',lastChecked='',busy=false,seq=0;
   const normalized=()=>ServiceYarDates.digits(codeInput.value).replace(/\D/g,'').slice(0,10);
   const reset=()=>{
    seq++;approvedCode='';lastChecked='';delete form.dataset.importOwnerDriver;
    status.textContent='';retry.hidden=true;
   };
   codeInput.addEventListener('input',()=>{
    const normalizedCode=normalized();
    codeInput.value=persianDigits(normalizedCode);
    reset();
   });
   const check=async()=>{
    const code=normalized();
    if(busy||!code||code===lastChecked)return;
    if(code.length!==10){status.textContent='کد ملی مالک باید ۱۰ رقم باشد.';return;}
    lastChecked=code;const current=++seq;busy=true;
    status.textContent='در حال بررسی سوابق راننده…';
    try{
     const result=await api(scoped('/api/v1/vehicles/owner-driver-exists?nationalCode='+encodeURIComponent(code)));
     if(current!==seq||!form.isConnected||normalized()!==code)return;
     if(!result.found){
      status.textContent='راننده‌ای با این کد ملی در سوابق سامانه پیدا نشد. خودرو بدون فراخوانی راننده ثبت می‌شود.';
      retry.hidden=true;return;
     }
     retry.hidden=false;
     const consent=window.confirm('راننده‌ای با این کد ملی در '+(result.inCurrentAgency?'شرکت جاری':'سوابق سامانه و شرکت‌های دیگر')+' یافت شد. آیا اطلاعات راننده فراخوانی و هنگام ثبت خودرو به فهرست رانندگان شرکت اضافه یا متصل شود؟');
     if(current!==seq||!form.isConnected||normalized()!==code)return;
     if(!consent){status.textContent='فراخوانی راننده لغو شد. فقط اطلاعات خودرو ثبت خواهد شد.';return;}
     const preview=await api(scoped('/api/v1/vehicles/owner-driver-preview?nationalCode='+encodeURIComponent(code)));
     if(current!==seq||!form.isConnected||normalized()!==code)return;
     if(!preview.found){status.textContent='سوابق راننده تغییر کرده است؛ دوباره بررسی کنید.';return;}
     approvedCode=code;form.dataset.importOwnerDriver=code;
     const name=[preview.firstName,preview.lastName].filter(Boolean).join(' ');
     if(form.elements.ownerName&&!form.elements.ownerName.value.trim())form.elements.ownerName.value=name;
     status.textContent='راننده آماده فراخوانی: '+name+(preview.mobile?' · '+persianDigits(preview.mobile):'')+'. پس از ذخیره خودرو، راننده نیز در فهرست شرکت ثبت یا متصل می‌شود.';
     retry.hidden=true;
    }catch(error){
     if(current===seq&&form.isConnected){status.textContent='بررسی راننده ناموفق بود: '+error.message;retry.hidden=false;lastChecked='';}
    }finally{busy=false;}
   };
   codeInput.addEventListener('blur',()=>{void check();});
   retry.addEventListener('click',()=>{lastChecked='';void check();});
   form.addEventListener('submit',event=>{
    const code=normalized();
    if(code&&code.length!==10){event.preventDefault();event.stopImmediatePropagation();codeInput.focus();notify('کد ملی مالک را کامل کنید.',true);return;}
    if(approvedCode!==code)delete form.dataset.importOwnerDriver;
   },true);
  }
  const owner=form.elements.ownerName;
  if(owner&&!owner.value.trim()&&row.effective_owner_name)owner.value=row.effective_owner_name;
  if(owner){const hint=document.createElement('small');hint.className='sy-vehicle-owner-hint';hint.textContent='در نبود مالک اختصاصی، راننده مرتبط مالک پیش‌فرض است.';owner.closest('label')?.append(hint);}
 }
 if(key==='schools'&&id){
  const section=document.createElement('section');section.className='sy-school-photo-editor';
  const photo=schoolPhotoUrl(row);
  section.innerHTML=`<div class="sy-school-photo-editor-preview"><img id="sySchoolEditorPreview" src="${esc(photo||'/assets/school-placeholder.png')}" alt="تصویر مدرسه"></div><div class="sy-school-photo-editor-controls"><h4>تصویر مدرسه</h4><p>می‌توانید تصویر مدرسه را بارگذاری یا با تصویر جدید جایگزین کنید. JPG، PNG یا WebP تا ۵ مگابایت.</p><label class="sy-school-photo-choose">انتخاب تصویر<input type="file" id="sySchoolPhotoUpload" accept="image/jpeg,image/png,image/webp"></label><span id="sySchoolPhotoFileName" role="status"></span></div>`;
  form.querySelector('.form-grid')?.after(section);
  const fileInput=section.querySelector('#sySchoolPhotoUpload');
  fileInput?.addEventListener('change',()=>{
   const file=fileInput.files?.[0];if(!file)return;
   if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5*1024*1024){fileInput.value='';notify('فرمت تصویر نامعتبر یا حجم فایل بیش از ۵ مگابایت است.',true);return;}
   const newSrc=URL.createObjectURL(file);
   let preview=section.querySelector('#sySchoolEditorPreview');
   if(!preview){preview=document.createElement('img');preview.id='sySchoolEditorPreview';preview.alt='پیش‌نمایش تصویر جدید';section.querySelector('.sy-school-photo-editor-preview')?.replaceChildren(preview);}
   const old=preview.dataset.tempUrl;if(old)URL.revokeObjectURL(old);
   preview.src=newSrc;preview.dataset.tempUrl=newSrc;
   section.querySelector('#sySchoolPhotoFileName').textContent=file.name;
  });
 }

 const rialAscii=value=>String(value??'').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).trim();
 const normalizeRialInput=value=>rialAscii(value).replace(/[٬,]/g,'');
 const validRialGrouping=value=>/^(?:[0-9]+|[0-9]{1,3}(?:[٬,][0-9]{3})+)$/.test(rialAscii(value));
 const rialInputs=[...form.querySelectorAll('[data-rial-amount]')];
 const formatRialInput=input=>{
  const ascii=normalizeRialInput(input.value);
  const ok=ascii===''?!input.required:validRialGrouping(input.value)&&/^\d{1,15}$/.test(ascii);
  input.setCustomValidity(ok?'':'مبلغ را به ریال، بدون اعشار و با رقم معتبر وارد کنید.');
  if(ok&&ascii!=='')input.value=persianDigits(ascii.replace(/^0+(?=\d)/,'').replace(/\B(?=(\d{3})+(?!\d))/g,'٬'));
  return ok;
 };
 rialInputs.forEach(input=>input.addEventListener('input',()=>formatRialInput(input)));
 if(key==='ledger'){const display=form.querySelector('[data-money-display]'),raw=form.elements.amount;const normalize=v=>String(v??'').replace(/[۰-۹]/g,c=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c))).replace(/[٠-٩]/g,c=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(c))).replace(/[^0-9]/g,'');const sync=()=>{const digits=normalize(display.value).replace(/^0+(?=\d)/,'');raw.value=digits;display.value=digits?digits.replace(/\B(?=(\d{3})+(?!\d))/g,'٬').replace(/[0-9]/g,c=>'۰۱۲۳۴۵۶۷۸۹'[Number(c)]):'';display.setCustomValidity(digits&&Number(digits)>0?'':'مبلغ معتبر و بزرگ‌تر از صفر وارد کنید.');};display.addEventListener('input',sync);display.addEventListener('blur',sync);sync();form.addEventListener('submit',e=>{sync();if(!display.checkValidity()){e.preventDefault();e.stopImmediatePropagation();display.reportValidity();}},true);}
 if(key==='ledger'){
   const direction=form.elements.direction,account=form.elements.companyAccountId;
   const accountLabel=account?.closest('label');
   const updateCaption=()=>{
     if(accountLabel?.firstChild?.nodeType===Node.TEXT_NODE)accountLabel.firstChild.textContent=(direction.value==='payment'?'حساب مبدأ شرکت':'حساب مقصد شرکت')+(account.required?' *':'');
   };
   direction?.addEventListener('change',updateCaption);updateCaption();
 }
 if(key==='accounts'){
   form.classList.add('sy-company-account-editor');
   const grid=form.querySelector('.form-grid');
   if(grid){
     const financial=document.createElement('fieldset');financial.className='sy-account-fieldset';
     financial.innerHTML='<legend>مشخصات سرفصل حسابداری</legend><p>کد، عنوان و ساختار حساب را تعریف کنید.</p><div class="form-grid sy-account-fields"></div>';
     const banking=document.createElement('fieldset');banking.className='sy-account-fieldset sy-account-banking';
     banking.innerHTML='<legend>اطلاعات بانکی شرکت</legend><p>برای حساب بانکی، نام بانک را انتخاب کنید و شماره حساب و شبا را وارد کنید. این اطلاعات فقط برای شرکت جاری ذخیره می‌شوند.</p><div class="form-grid sy-account-fields"></div>';
     for(const label of [...grid.children]){
       const input=label.querySelector('input[name],select[name]');
       (['bankName','bankAccount','iban'].includes(input?.name)?banking:financial).querySelector('.form-grid').append(label);
     }
     grid.replaceWith(financial,banking);
   }
 }
 if(key==='accounts'){
   for(const name of ['bankAccount','iban']){
     const input=form.elements[name];if(!input)continue;input.dir='ltr';input.placeholder=name==='iban'?'IR000000000000000000000000':'شماره حساب';input.autocomplete='off';
     input.addEventListener('input',()=>{input.value=ServiceYarDates.digits(input.value).replace(/\s+/g,'').toUpperCase();});
   }
 }
if(form.elements.mobile){const mobile=form.elements.mobile;mobile.pattern='09[0-9]{9}';mobile.maxLength=11;mobile.inputMode='tel';mobile.dir='ltr';mobile.placeholder='09xxxxxxxxx';mobile.title='شماره همراه ۱۱ رقمی با ۰۹ شروع می‌شود.';mobile.addEventListener('input',()=>{mobile.value=ServiceYarDates.digits(mobile.value);});}const typeKey=['partyId','ownerId','recipientId'].find(f=>form.elements[f]);
 if(key==='contracts'){
  form.classList.add('sy-contract-editor');
  const start=form.elements.startDate,end=form.elements.endDate;
  const amount=form.elements.amount;
  const info=document.createElement('div');info.className='sy-contract-period-feedback';info.setAttribute('role','status');info.setAttribute('aria-live','polite');
  (end?.closest('label')||form.querySelector('.form-grid')).after(info);
  const validateContract=()=>{
   const from=start?.value||'',to=end?.value||'';
   if(start&&end){
    end.setCustomValidity(from&&to&&to<from?'تاریخ پایان قرارداد نباید قبل از شروع قرارداد باشد.':'');
   }
   info.textContent=from&&to?(to<from?'بازه نامعتبر است؛ تاریخ پایان را اصلاح کنید.':'بازه قرارداد: '+dateText(from)+' تا '+dateText(to)):'شروع و پایان قرارداد را به‌صورت شمسی انتخاب کنید.';
   info.classList.toggle('is-invalid',Boolean(from&&to&&to<from));
  };
  start?.addEventListener('change',validateContract);
  end?.addEventListener('change',validateContract);
  start?.addEventListener('input',validateContract);
  end?.addEventListener('input',validateContract);
  validateContract();
 }
 if(key==='students'){
  attachStudentTariff(form,id);
  const feeField=form.elements.monthlyFee,schoolField=form.elements.schoolId,gradeField=form.elements.grade;
  if(feeField){
   const preview=document.createElement('div');preview.className='sy-student-annual-preview';preview.setAttribute('role','status');preview.setAttribute('aria-live','polite');
   feeField.closest('label')?.after(preview);
   let schoolTypes=new Map();
   const render=()=>{
    const months=serviceAcademicMonths({grade:gradeField?.value||''},{school_type:schoolTypes.get(String(schoolField?.value||''))||''});
    const monthly=Number(String(feeField.value||'0').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))));
    preview.textContent='مبلغ سالانه: '+(Number.isFinite(monthly)&&monthly>=0?number(Math.round(monthly*months))+' ریال':'—')+' · ضریب '+(months===8.5?'۸٫۵':'۹')+' ماه';
   };
   form.addEventListener('input',e=>{if([feeField,schoolField,gradeField].includes(e.target))render();});
   form.addEventListener('change',e=>{if([feeField,schoolField,gradeField].includes(e.target))render();});
   render();
   lookup('schools').then(rows=>{schoolTypes=new Map(rows.map(sc=>[String(sc.id),sc.school_type||sc.schoolType||'']));if(form.isConnected)render();}).catch(()=>{});
  }
 }else if(key==='driver-work'&&can('payroll'))attachFinancialCalculator(form,key,id,row);
 const relationVersion=new WeakMap();
 const populate=async(select,search='')=>{
  const version=(relationVersion.get(select)||0)+1;relationVersion.set(select,version);
  let resource=select.dataset.relation;
  if(resource==='party'){
   const type=form.elements.partyType?.value||form.elements.ownerType?.value||form.elements.recipientType?.value;
   resource={student:'students',driver:'drivers',school:'schools',agency:'agencies'}[type];
  }
  const selected=select.value;
  select.setAttribute('aria-busy','true');
  if(!resource){
   select.replaceChildren(new Option('ابتدا نوع طرف حساب را انتخاب کنید',''));
   select.value='';select.removeAttribute('aria-busy');return;
  }
  try{
   const response=await api(scoped('/api/v1/'+resource+'?limit=200&active=1&search='+encodeURIComponent(search)));
   if(!select.isConnected||relationVersion.get(select)!==version)return;
   const items=Array.isArray(response)?response:response.items||[];
   const options=[new Option(items.length?'انتخاب کنید':'نتیجه‌ای یافت نشد','')];
   for(const row of items)options.push(new Option(person(row),String(row.id)));
   if(selected&&!items.some(row=>String(row.id)===selected)){
    try{
     const detail=await api(scoped('/api/v1/'+resource+'/'+encodeURIComponent(selected)));
     if(!select.isConnected||relationVersion.get(select)!==version)return;
     options.push(new Option(person(detail),selected));
    }catch{
     // Keep the existing selection while the user edits other fields.
     options.push(new Option('مورد انتخاب‌شده ('+persianDigits(selected)+')',selected));
    }
   }
   if(!select.isConnected||relationVersion.get(select)!==version)return;
   select.replaceChildren(...options);select.value=selected;
  }catch(e){
   if(!select.isConnected||relationVersion.get(select)!==version)return;
   notify('خطا در جست‌وجوی '+(labels[select.name]||'اطلاعات')+': '+e.message,true);
  }finally{
   if(select.isConnected&&relationVersion.get(select)===version)select.removeAttribute('aria-busy');
  }
 };
 form.querySelectorAll('[data-relation]').forEach(select=>{
  populate(select);
  const input=form.querySelector(`[data-lookup-search="${select.name}"]`);
  let timer=0;
  input?.setAttribute('type','search');
  input?.setAttribute('autocomplete','off');
  if(input)input.oninput=()=>{clearTimeout(timer);relationVersion.set(select,(relationVersion.get(select)||0)+1);timer=setTimeout(()=>populate(select,input.value.trim()),350);};
 });
 if(form.elements.latitude&&form.elements.longitude)await locationPicker(form,key);if(typeKey){const type=form.elements.partyType||form.elements.ownerType||form.elements.recipientType;type.onchange=()=>{const select=form.elements[typeKey],search=form.querySelector(`[data-lookup-search="${typeKey}"]`);select.value='';if(search)search.value='';populate(select);};}
 resourceWizard(form,key);
 attachOfficialTaxiTariffActions(form,key,id);
 form.onsubmit=async e=>{e.preventDefault();const button=$('saveRecord');button.disabled=true;$('formError').hidden=true;try{const body=Object.fromEntries(new FormData(form));for(const input of rialInputs){if(!formatRialInput(input)){input.reportValidity();button.disabled=false;return;}body[input.name]=normalizeRialInput(input.value);}if(key==='students'&&body.tariffDistanceKm!==undefined&&body.tariffDistanceKm!=='')body.tariffDistanceKm=Math.round(Number(body.tariffDistanceKm));
  if(key==='students'&&id&&body.tariffDistanceKm!==undefined&&body.tariffDistanceKm!==''&&Number(body.tariffDistanceKm)!==Number(row?.tariff_distance_km)){
   if(!window.confirm('مسافت دانش‌آموز تغییر کرده است. آیا می‌خواهید تعرفه جدید محاسبه شود و مبلغ سرویس، قرارداد و فاکتور مرتبط نیز اصلاح گردد؟\n\nپرداخت‌های قبلی حفظ می‌شوند و اصلاحات قرارداد در سوابق ثبت می‌شود. در صورت انصراف، هیچ تغییری ذخیره نخواهد شد.')){button.disabled=false;return;}
   body.confirmDistanceReprice=true;
  }
  if(key==='ledger')delete body.amountDisplay;
  if(key==='vehicles'){
   const typeField=form.elements.vehicleTrim;
   if((!id||typeField?.dataset.vehicleTouched)&&!typeField?.dataset.vehicleFamily)
    throw Error('نوع خودرو را از فهرست جستجوی مدل‌های تأییدشده انتخاب کنید.');
  }
  if(key==='vehicles'&&id&&body.tariffVehicleClass==='')delete body.tariffVehicleClass;
   if(key==='vehicles'&&id&&body.tariffVehicleClass!==undefined){
    let oldClass=row?.tariff_vehicle_class??null;
    // Vehicle replacement: the driver's current registered vehicle is the
    // historic pricing source, not the class currently saved on the new car.
    if(body.driverId){
     const driver=(await lookup('drivers',true)).find(d=>String(d.id)===String(body.driverId));
     if(driver?.vehicle_id&&String(driver.vehicle_id)!==String(id)){
      const registered=(await lookup('vehicles')).find(v=>String(v.id)===String(driver.vehicle_id))||
        await api(scoped('/api/v1/vehicles/'+encodeURIComponent(driver.vehicle_id)));
      if(registered)oldClass=registered.tariff_vehicle_class;
     }
    }
    if(String(body.tariffVehicleClass)!==String(oldClass??'')){
     vehicleClassChangeWizard(id,{...row,tariff_vehicle_class:oldClass},body,onSaved);return;
    }
   }
  if(key==='services'){
   if(!form.elements.vehicleModel?.dataset.vehicleFamily){
    if(form.elements.vehicleModel?.dataset.vehicleTouched)throw Error('برای ثبت نوع خودرو، آن را از فهرست مدل‌های تأییدشده انتخاب کنید.');
    delete body.vehicleModel;delete body.vehicleClass;
   }
  }
  if(key==='services'&&id&&form.dataset.applyTaxiAfterSave==='1'){
   if(body.feeSource!=='taxi')throw Error('برای اعمال نرخ‌نامه، مبنای تاکسیرانی را انتخاب کنید.');
   body.applyOfficialTariff=true;
  }
  if(key==='vehicles'&&!id&&body.ownerNationalCode){
   body.importOwnerDriver=form.dataset.importOwnerDriver===ServiceYarDates.digits(body.ownerNationalCode);
   await write('/api/v1/vehicles/create-with-owner',body,'POST');
  }else await write('/api/v1/'+key+(id?'/'+id:''),body,id?'PUT':'POST');
  if(key==='schools'&&id){
   const file=form.querySelector('#sySchoolPhotoUpload')?.files?.[0];
   if(file){
    const payload=new FormData();payload.append('photo',file);
    await window.ServiceYarLoading.upload('/api/v1/schools/'+encodeURIComponent(id)+'/photo?agencyId='+encodeURIComponent(currentAgency.id),payload,{headers:{'X-CSRF-Token':session.csrfToken}});
   }
  }
  lookupCache={};closeDialog();notify('اطلاعات ذخیره شد.');if(onSaved)onSaved();else if(gridState?.key===key&&gridState.token===pageToken)await loadResource(key);else{location.hash=key;}}catch(err){if($('formError')){$('formError').textContent=err.message;$('formError').hidden=false;$('formError').focus();}}finally{if(button.isConnected)button.disabled=false;}};
 }catch(e){notify(e.message,true);}
}
function bulkFeeDialog(){
 if(!gridState?.selected.size){notify('دانش‌آموزان مورد نظر را انتخاب کنید.',true);return;}
 const ids=[...gridState.selected];
 dialog('تغییر سریع هزینه سرویس',`
  <form id="feeForm" class="sy-bulk-fee-form">
   <p class="sy-bulk-fee-intro">تعداد <strong>${number(ids.length)}</strong> دانش‌آموز انتخاب شده است. مبلغ جدید برای همه موارد انتخابی محاسبه خواهد شد.</p>
   <div class="form-grid">
    <label>نوع تغییر<select name="mode" required><option value="set">تعیین مبلغ ثابت</option><option value="increase">افزایش مبلغ</option><option value="decrease">کاهش مبلغ</option><option value="percent">تغییر درصدی</option></select></label>
    <label>مقدار <span id="bulkFeeUnit">ریال</span><input type="text" id="bulkFeeAmountDisplay" name="amountDisplay" inputmode="numeric" autocomplete="off" spellcheck="false" dir="ltr" required placeholder="۰" aria-describedby="bulkFeeHint"><input type="hidden" name="value"></label>
   </div>
   <p id="bulkFeeHint" class="sy-bulk-fee-hint">مبلغ را به ریال، بدون اعشار وارد کنید. ارقام سه‌رقمی جدا می‌شوند.</p>
   <div class="member-actions"><button class="primary" type="submit">بررسی و ثبت تغییرات</button><button type="button" id="cancelBulkFee">انصراف</button></div>
  </form>`);
 const form=$('feeForm'),mode=form.elements.mode,display=$('bulkFeeAmountDisplay'),raw=form.elements.value;
 const ascii=value=>String(value).replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
 const sync=()=>{
  const percentMode=mode.value==='percent',text=ascii(display.value);
  // Preserve invalid characters for validation: never turn 1.5 into 15 rials
  // or a negative amount into a positive one.
  const normalized=percentMode?text.trim().replace(/[٫,]/g,'.'):text.replace(/[٬,]/g,'').trim();
  const candidate=normalized.replace(/^(-?)0+(?=\d)/,'$1');
  const groupedMoney=/^(?:[0-9]+|[0-9]{1,3}(?:[٬,][0-9]{3})+)$/.test(text.trim());
  const ok=percentMode?/^-?\d+(?:\.\d{0,2})?$/.test(candidate)&&Number(candidate)>=-100&&Number(candidate)<=100:groupedMoney&&/^\d{1,13}$/.test(candidate)&&Number(candidate)<=1000000000000;
  raw.value=ok?String(Number(candidate)):'';
  display.setCustomValidity(ok?'':percentMode?'درصد را بین منفی ۱۰۰ تا مثبت ۱۰۰ با حداکثر دو رقم اعشار وارد کنید.':'مبلغ ریالی معتبر و بدون اعشار وارد کنید.');
  if(ok){
   const digits=percentMode?candidate:candidate.replace(/\B(?=(\d{3})+(?!\d))/g,'٬');
   display.value=persianDigits(digits);
  }
  return ok;
 };
 mode.addEventListener('change',()=>{
  const percentage=mode.value==='percent';
  display.value='';raw.value='';display.setCustomValidity('');
  display.inputMode=percentage?'decimal':'numeric';
  $('bulkFeeUnit').textContent=percentage?'درصد':'ریال';
  $('bulkFeeHint').textContent=percentage?'برای افزایش یا کاهش درصدی، عدد مثبت یا منفی وارد کنید. حداقل منفی ۱۰۰ و حداکثر مثبت ۱۰۰ درصد.':'مبلغ را به ریال، بدون اعشار وارد کنید. ارقام سه‌رقمی جدا می‌شوند.';
  display.focus();
 });
 display.addEventListener('input',sync);
 $('cancelBulkFee').onclick=closeDialog;
 form.onsubmit=async e=>{
  e.preventDefault();
  if(!sync()){display.reportValidity();return;}
  if(!confirm('تغییر هزینه برای '+number(ids.length)+' دانش‌آموز ثبت شود؟'))return;
  const button=form.querySelector('[type=submit]');button.disabled=true;
  try{
   await write('/api/v1/operations/fees',{ids,mode:mode.value,value:raw.value});
   closeDialog();await loadResource('students');notify('هزینه‌های انتخاب‌شده به‌روزرسانی شدند.');
  }catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}
 };
}
function bulkMessageDialog(){if(!gridState?.selected.size){notify('گیرندگان را انتخاب کنید.',true);return;}dialog('پیامک به انتخاب‌شده‌ها',`<form id="messageForm"><p>پیام برای ${number(gridState.selected.size)} گیرنده در صف قرار می‌گیرد؛ تحویل نیازمند اتصال سرویس پیامک است.</p><label>متن پیام<textarea name="body" required maxlength="2000"></textarea></label><button class="primary">ثبت در صف پیامک</button></form>`);$('messageForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{await write('/api/v1/operations/messages',{ids:[...gridState.selected],recipientType:gridState.key==='students'?'student':'driver',body:e.target.body.value});closeDialog();notify('پیام‌ها در صف ثبت شدند.');}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};}
async function serviceMembers(id,back){
 const token=pageToken;
 const previousDialog=$('modal')?.querySelector('.modal');
 try{
  const [service,rows]=await Promise.all([api(scoped('/api/v1/services/'+id)),api(scoped('/api/v1/services/'+id+'/students'))]);
  const memberNet=driverFeeBreakdown(rows.reduce((sum,row)=>sum+Number(row.monthly_fee||0),0),{supervision:Number(service.supervision_rate??3),coordination:Number(service.coordination_rate??1),overhead:Number(service.overhead_rate??7),insurance:Number(service.insurance_rate??7.8)});
  if(token!==pageToken||(previousDialog&&!previousDialog.isConnected))return;
  const canManageMembers=can('services','manage')&&can('students');
  const canAssign=canManageMembers&&Number(service.is_active)===1&&service.status!=='cancelled';
  const free=Math.max(0,Number(service.capacity)-rows.length);
  const monthlyTotal=rows.reduce((total,row)=>total+Number(row.monthly_fee||0),0);
  const annualMonths=row=>serviceAcademicMonths(row);
  const annualTotal=rows.reduce((total,row)=>total+Math.round(Number(row.monthly_fee||0)*annualMonths(row)),0);
  dialog('دانش‌آموزان سرویس '+service.title,`<div class="sy-service-member-header"><div><strong>ظرفیت ${number(service.capacity)} نفر</strong><span>تخصیص ${number(rows.length)} نفر · جای خالی ${number(free)} نفر</span></div>${canAssign&&free>0?'<button type="button" class="primary sy-service-add-student" id="syServiceAddStudent">+ افزودن دانش‌آموز</button>':''}</div><p>ظرفیت ${number(service.capacity)} · تخصیص ${number(rows.length)} · جای خالی ${number(free)}</p>
   <p>جمع شهریه ماهانه دانش‌آموزان: ${number(monthlyTotal)} ریال · جمع سالانه: ${number(annualTotal)} ریال</p><p class="sy-service-net-summary">کسورات راننده ${percent(memberNet.rate)}٪: ${number(memberNet.deducted)} ریال · خالص پرداخت ماهانه راننده: ${number(memberNet.net)} ریال</p>
   ${back?'<button type="button" id="backToDriverServices">بازگشت به سرویس‌های راننده</button>':''}
   ${simpleTable(['نام','مسافت تعرفه','ماهیانه · ریال','مدت قرارداد','سالیانه · ریال','ترتیب توقف','نشانی'],rows.map(r=>[person(r),km(r.tariff_distance_km),number(r.monthly_fee),number(annualMonths(r))+' ماه',number(Math.round(Number(r.monthly_fee||0)*annualMonths(r))),r.pickup_order,r.pickup_address]))}
   ${canManageMembers?`<div class="member-actions sy-member-actions" aria-label="مدیریت دانش‌آموزان سرویس">${rows.map(r=>`<div class="sy-member-action-row"><span>${esc(person(r))}</span>${can('students','manage')?`<button type="button" data-edit-member="${r.id}">ویرایش دانش‌آموز</button>`:''}<button type="button" class="danger" data-remove-member="${r.id}">حذف از سرویس</button></div>`).join('')}</div>`:''}
   ${canAssign?`${free?`
   <form id="assignForm">
    <label>جست‌وجوی دانش‌آموز<input type="search" id="memberSearch" placeholder="نام یا کد ملی" maxlength="200"></label>
    <label>دانش‌آموز<select name="studentId" required></select></label>
    <p id="memberSearchStatus" role="status"></p>
    <p id="memberQuote" role="status"></p>
    <div class="pager" id="memberPager"></div>
    <label>ترتیب توقف<input type="number" name="pickupOrder" min="1"></label>
    <label>نشانی سوار شدن<input name="pickupAddress"></label>
    <button class="primary" disabled>ثبت دانش‌آموز در سرویس</button>
   </form>`:'<p>ظرفیت این سرویس تکمیل است.</p>'}`:''}`);
  const addStudentButton=$('syServiceAddStudent');
  if(addStudentButton)addStudentButton.onclick=()=>{
   const form=$('assignForm');if(!form)return;
   form.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});
   form.querySelector('#memberSearch')?.focus({preventScroll:true});
  };
  if(back)$('backToDriverServices').onclick=back;
  $('modal').querySelectorAll('[data-edit-member]').forEach(button=>button.onclick=()=>formResource('students',Number(button.dataset.editMember),()=>serviceMembers(id,back)));
  $('modal').querySelectorAll('[data-remove-member]').forEach(button=>button.onclick=async()=>{
   if(!confirm('این دانش‌آموز از سرویس حذف شود؟ پرونده دانش‌آموز حذف نخواهد شد.'))return;
   button.disabled=true;
   try{
    await api(scoped('/api/v1/services/'+id+'/students?studentId='+button.dataset.removeMember),{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});
    lookupCache={};if(button.isConnected)await serviceMembers(id,back);
   }catch(err){if(button.isConnected){button.disabled=false;notify(err.message,true);}}
  });
  const form=$('assignForm');if(!form)return;
  const search=$('memberSearch'),status=$('memberSearchStatus'),pager=$('memberPager');
  const select=form.elements.studentId,submit=form.querySelector('[type=submit],.primary');
  const quote=$('memberQuote');
  let revision=0,timer,saving=false;
  const sync=()=>{submit.disabled=saving||!select.value;};
  const load=async(page,version)=>{
   if(!form.isConnected||version!==revision)return;
   try{
    const filter=new URLSearchParams({active:1,unassigned:1,eligibleServiceId:id,search:ServiceYarDates.digits(search.value).trim(),page,limit:50});
    const data=await api(scoped('/api/v1/students?'+filter));
    if(!form.isConnected||version!==revision)return;
    const items=data.items.filter(r=>r.service_status!=='cancelled'&&!rows.some(m=>Number(m.id)===Number(r.id)));
    select.replaceChildren(new Option('انتخاب کنید',''),...items.map(r=>new Option(person(r)+(r.address?' · '+r.address:''),r.id)));
    status.textContent=items.length?'دانش‌آموز مورد نظر را انتخاب کنید.':'در این صفحه دانش‌آموز واجد شرایط پیدا نشد.';
    pager.replaceChildren();
    for(const [label,target,disabled] of [['قبلی',page-1,page<=1],['بعدی',page+1,page>=data.pagination.pages]]){
     if(data.pagination.pages<=1)break;
     const button=document.createElement('button');button.type='button';button.textContent=label;button.disabled=disabled;
     button.onclick=()=>refresh(target);pager.append(button);
    }
    sync();
   }catch(err){if(form.isConnected&&version===revision)status.textContent=err.message;}
  };
  const refresh=(page=1,delay=0)=>{
   clearTimeout(timer);
   const version=++revision;
   select.replaceChildren(new Option('در حال جست‌وجو…',''));
   quote.textContent='';
   pager.replaceChildren();status.textContent='در حال جست‌وجو…';sync();
   timer=setTimeout(()=>load(page,version),delay);
  };
  search.oninput=()=>refresh(1,250);
  select.onchange=async()=>{
   sync();
   const studentId=select.value;
   if(!studentId){quote.textContent='';return;}
   quote.textContent='در حال دریافت مبلغ ماهانه…';
   const current=()=>form.isConnected&&select.value===studentId;
   try{
    const rate=await write('/api/v1/operations/tariffs/quote',{studentId});
    if(current())quote.textContent=rate.monthlyFee===null?'برای این دانش‌آموز نرخ قابل محاسبه پیدا نشد.':'مبلغ ماهانه: '+number(rate.monthlyFee)+' ریال';
   }catch(err){if(current())quote.textContent=err.message;}
  };
  refresh();
  form.onsubmit=async e=>{
   e.preventDefault();if(submit.disabled)return;
   const body=Object.fromEntries(new FormData(form));
   body.studentId=Number(body.studentId);body.pickupOrder=body.pickupOrder?Number(body.pickupOrder):null;
   saving=true;sync();
   try{
    await write('/api/v1/services/'+id+'/students',body);lookupCache={};
    if(form.isConnected){notify('دانش‌آموز به سرویس اضافه شد.');await serviceMembers(id,back);}
   }catch(err){if(form.isConnected){$('formError').textContent=err.message;$('formError').hidden=false;}}
   finally{saving=false;if(form.isConnected)sync();}
  };
 }catch(err){if(token===pageToken)notify(err.message,true);}
}
async function downloadFile(url,name){
 try{await window.ServiceYarLoading.download(scoped(url),name);}
 catch(e){notify(e.message,true);}
}
async function importPage(token){
 $('content').innerHTML=`<section class="panel sy-transfer-page"><header class="page-intro"><div><h2>ثبت دانش‌آموز از اکسل</h2><p>فایل XLSX یا CSV تا ۲۰۰ ردیف و ۲ مگابایت. شماره‌ها و کدهای دارای صفر ابتدایی را در Excel به‌شکل متن ذخیره کنید.</p></div><button type="button" id="templateDownload">دریافت نمونه CSV</button></header><form id="importForm" class="form-grid" aria-label="انتخاب مدرسه و فایل دانش‌آموزان"><label>مدرسه<select name="schoolId" required id="importSchool"></select></label><label>فایل دانش‌آموزان<input type="file" accept=".csv,.xlsx" name="file" required></label><button class="primary">پیش‌نمایش و اعتبارسنجی</button></form><p id="importStatus" role="status"></p><div id="importPreview"></div></section>`;
 const schools=await lookup('schools');if(token!==pageToken)return;$('importSchool').innerHTML='<option value="">انتخاب کنید</option>'+schools.map(s=>`<option value="${s.id}">${esc(s.name)}</option>`).join('');
 $('templateDownload').onclick=()=>{const b=new Blob(['\uFEFFنام,نام خانوادگی,شماره همراه ولی,کد ملی,پایه,آدرس,سال تحصیلی\n'],{type:'text/csv;charset=utf-8'}),a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='student-template.csv';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);};
 let importRevision=0;$('importForm').addEventListener('change',()=>{importRevision++;$('importPreview').innerHTML='';$('importStatus').textContent='';});$('importForm').onsubmit=async e=>{e.preventDefault();const revision=importRevision;const schoolId=e.target.schoolId.value,button=e.target.querySelector('button');button.disabled=true;$('importStatus').textContent='در حال خواندن فایل…';try{const d=await window.ServiceYarLoading.upload(scoped('/api/v1/operations/import/preview'),new FormData(e.target),{headers:{'X-CSRF-Token':session.csrfToken}});if(token!==pageToken||revision!==importRevision)return;const rows=d.data.rows;$('importStatus').textContent=number(rows.length)+' ردیف برای بازبینی آماده است.';$('importPreview').innerHTML='<h3>پیش‌نمایش '+number(rows.length)+' دانش‌آموز برای '+esc(schools.find(s=>String(s.id)===String(schoolId))?.name||'مدرسه انتخاب‌شده')+'</h3>'+simpleTable(['نام','نام خانوادگی','شماره همراه ولی'],rows.map(r=>[r.firstName,r.lastName,r.mobile]))+'<button class="primary" id="commitImport">ثبت همه ردیف‌ها در مدرسه انتخاب‌شده</button>';$('commitImport').onclick=async()=>{$('commitImport').disabled=true;try{const result=await write('/api/v1/operations/import',{schoolId,rows});lookupCache={};if(token!==pageToken)return;$('importPreview').replaceChildren();$('importStatus').textContent=number(result.created)+' دانش‌آموز ثبت شد.';}catch(err){notify(err.message,true);if($('commitImport'))$('commitImport').disabled=false;}};}catch(err){if(token===pageToken&&revision===importRevision)$('importStatus').textContent=err.message;}finally{if(button.isConnected)button.disabled=false;}};
}
async function driverAccountPanel(panel,driverId){
 panel.className='sy-student-account-panel';panel.innerHTML='<h3>کارکرد و حساب راننده</h3><p role="status">در حال دریافت…</p>';
 try{
  const data=await api(scoped('/api/v1/drivers/'+encodeURIComponent(driverId)+'/account'));
  if(!panel.isConnected)return;
  const money=v=>number(Math.round(Number(v||0)))+' ریال';
  panel.innerHTML='<header class="sy-student-account-header"><div><h3>برآورد حقوق و تسویه راننده</h3><p>جمع سرویس‌ها و پرداخت‌های ثبت‌شده</p></div></header>'+
   '<div class="sy-student-account-stats">'+
   [['خالص ماهانه سرویس‌های فعال',data.monthlyEntitlement],['برآورد خالص سالانه',data.estimatedAnnualEntitlement],['جمع پرداخت‌ها',data.paidTotal],['مانده برآوردی',data.remaining]].map(([name,value])=>'<div><span>'+esc(name)+'</span><strong>'+esc(money(value))+'</strong></div>').join('')+
   '</div><p class="muted">'+esc(data.note)+'</p><section class="sy-student-account-section"><h4>پرداخت‌های ثبت‌شده راننده</h4>'+
   (data.payments.length?simpleTable(['تاریخ پرداخت','منبع ثبت','مبلغ · ریال','شماره پیگیری'],data.payments.map(p=>[dateText(p.payment_date),p.source==='ledger'?'دریافت و پرداخت':'پرداخت راننده',number(p.amount),p.reference_no||'—'])):'<p class="muted">پرداخت مستقلی ثبت نشده است.</p>')+'</section>';
 }catch(e){if(panel.isConnected)panel.innerHTML='<h3>حساب راننده</h3><p class="error">'+esc(e.message)+'</p>';}
}
async function studentAccountPanel(panel,studentId){
 panel.className='sy-student-account-panel';panel.innerHTML='<h3>فاکتور بدهی، پرداخت‌ها و مانده حساب</h3><p role="status">در حال دریافت گردش مالی دانش‌آموز…</p>';
 try{
  const data=await api(scoped('/api/v1/students/'+encodeURIComponent(studentId)+'/account'));
  if(!panel.isConnected)return;
  const money=value=>number(Math.round(Number(value||0)))+' ریال',summary=data.totals||{};
  panel.innerHTML='<header class="sy-student-account-header"><div><h3>حساب مالی دانش‌آموز</h3><p>مبلغ و مانده اصلاح‌شده فاکتورهای قبلی پس از اعمال تعدیل کلاس خودرو؛ مبالغ اولیه و تمام دریافتی‌ها نیز محفوظ‌اند.</p></div></header>'+
   '<div class="sy-student-account-stats">'+
   [['کل بدهی شامل تعدیل‌ها',summary.invoiced],['جمع دریافت‌ها',summary.received],['مانده بدهی',summary.balance],['بستانکاری',summary.credit]].map(([label,value])=>'<div><span>'+esc(label)+'</span><strong>'+esc(money(value))+'</strong></div>').join('')+
   '</div><section class="sy-student-account-section"><h4>فاکتورهای صادرشده</h4>'+
   (data.invoices.length?simpleTable(['شماره فاکتور','شرح','تاریخ صدور','مبلغ اولیه','تعدیل کلاس','مبلغ اصلاح‌شده','دریافتی','مانده','وضعیت'],data.invoices.map(i=>[number(i.id),i.title,dateText(i.issue_date),number(i.originalAmount??i.amount),number(i.classAdjustmentAmount||0),number(i.effectiveAmount??i.amount),number(i.paidAmount),number(i.remainingAmount),i.displayStatus==='partial'?'پرداخت جزئی':statuses[i.displayStatus]||i.displayStatus])):'<p class="muted">فاکتور بدهی برای این دانش‌آموز ثبت نشده است.</p>')+
   '</section><section class="sy-student-account-section"><h4>اصلاحیه‌های مالی تغییر کلاس خودرو</h4><p class="muted">فاکتور و قرارداد اصلی ثابت می‌مانند؛ این اسناد تعدیل به مبلغ تعهد مالی اضافه یا از آن کم می‌شوند.</p>'+
   ((data.vehicleClassAdjustments||[]).length?simpleTable(['سند تعدیل','سال تحصیلی','تاریخ اثر','تغییر کلاس','مبلغ تعدیل · ریال','وضعیت'],data.vehicleClassAdjustments.map(a=>[number(a.change_id),a.academic_year,dateText(a.effective_date),number(a.old_vehicle_class)+' ← '+number(a.new_vehicle_class),number(a.amount_irr),String(a.effective_date).slice(0,10)<=new Intl.DateTimeFormat('en-CA',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'}).format(new Date())?'اعمال‌شده':'زمان‌بندی‌شده'])):'<p class="muted">تعدیل مالی تغییر کلاس برای این دانش‌آموز ثبت نشده است.</p>')+
   '</section><section class="sy-student-account-section"><h4>سوابق دریافت و تسویه</h4>'+
   (data.payments.length?simpleTable(['تاریخ','نوع ثبت','مبلغ · ریال','روش پرداخت','شماره پیگیری'],data.payments.map(p=>[dateText(p.payment_date),p.source==='ledger'?(Number(p.amount)<0?'استرداد / پرداخت':'دریافت مستقیم'):'پرداخت فاکتور',number(p.amount),methodLabel(p.method),p.reference_no||'—'])):'<p class="muted">دریافتی ثبت‌شده‌ای وجود ندارد.</p>')+'</section>';
 }catch(error){if(panel.isConnected)panel.innerHTML='<h3>حساب مالی دانش‌آموز</h3><p class="error" role="alert">'+esc(error.message)+'</p>';}
}
async function loadHtml2Pdf(){
 if(typeof window.html2pdf==='function')return;
 await new Promise((resolve,reject)=>{
  let script=document.querySelector('script[data-sy-html2pdf]');
  const pending=Boolean(script);
  if(!script){
   script=document.createElement('script');
   script.dataset.syHtml2pdf='true';
   script.src='/assets/html2pdf.bundle.min.js';
  }
  script.addEventListener('load',resolve,{once:true});
  script.addEventListener('error',()=>{
   script.remove();
   reject(new Error('کتابخانه ساخت PDF بارگذاری نشد.'));
  },{once:true});
  if(!pending)document.head.appendChild(script);
 });
 if(typeof window.html2pdf!=='function')throw new Error('امکان ایجاد PDF در حال حاضر فراهم نیست.');
}

function debtorPdfArchive(files){
 // PDF images are already compressed. Store each PDF with its UTF-8 name.
 const crcTable=Uint32Array.from({length:256},(_,index)=>{
  for(let bit=0;bit<8;bit++)index=(index>>>1)^((index&1)?0xedb88320:0);
  return index;
 });
 const local=[],central=[],encoder=new TextEncoder();
 let offset=0,directorySize=0;
 for(const {name,bytes} of files){
  const filename=encoder.encode(name);
  let crc=0xffffffff;
  for(const byte of new Uint8Array(bytes))crc=(crc>>>8)^crcTable[(crc^byte)&255];
  const header=new DataView(new ArrayBuffer(30));
  header.setUint32(0,0x04034b50,true);
  header.setUint16(4,20,true);
  header.setUint16(6,0x0800,true);
  header.setUint16(12,0x21,true);
  header.setUint32(14,crc^0xffffffff,true);
  header.setUint32(18,bytes.byteLength,true);
  header.setUint32(22,bytes.byteLength,true);
  header.setUint16(26,filename.length,true);
  const entry=new DataView(new ArrayBuffer(46));
  entry.setUint32(0,0x02014b50,true);
  entry.setUint16(4,20,true);
  new Uint8Array(entry.buffer).set(new Uint8Array(header.buffer,4,26),6);
  entry.setUint32(42,offset,true);
  local.push(header.buffer,filename,bytes);
  central.push(entry.buffer,filename);
  offset+=30+filename.length+bytes.byteLength;
  directorySize+=46+filename.length;
 }
 const end=new DataView(new ArrayBuffer(22));
 end.setUint32(0,0x06054b50,true);
 end.setUint16(8,files.length,true);
 end.setUint16(10,files.length,true);
 end.setUint32(12,directorySize,true);
 end.setUint32(16,offset,true);
 return new Blob([...local,...central,end.buffer],{type:'application/zip'});
}

const debtorColumns={"student":{"label":"نام و نام خانوادگی دانش‌آموز","width":25},"school":{"label":"مدرسه","width":20},"service":{"label":"سرویس / راننده","width":24},"basis":{"label":"مبنای بدهی","width":18},"guardian_mobile":{"label":"شماره تماس ولی","width":17},"tariff_distance_km":{"label":"مسافت (کیلومتر)","width":12},"monthly_fee":{"label":"مبلغ ماهیانه سرویس (ریال)","width":19},"annual_fee":{"label":"مبلغ سالانه سرویس (غیرمبنای مانده)","width":19},"paid":{"label":"پرداخت‌شده (ریال)","width":19},"debt":{"label":"بدهی باقی‌مانده (ریال)","width":19},"national_code":{"label":"کد ملی دانش‌آموز","width":16},"father_name":{"label":"نام پدر","width":16},"education_levels":{"label":"مقطع تحصیلی مدرسه","width":20},"grade":{"label":"پایه تحصیلی","width":15},"region":{"label":"ناحیه مدرسه","width":15},"address":{"label":"نشانی منزل","width":32},"guardian_name":{"label":"نام ولی","width":24},"guardian_national_code":{"label":"کد ملی ولی","width":16},"plate":{"label":"شماره پلاک خودرو","width":20},"contract_start":{"label":"تاریخ شروع قرارداد","width":18},"contract_end":{"label":"تاریخ پایان قرارداد","width":18},"contract_no":{"label":"شماره قرارداد","width":20},"guardian_relation":{"label":"نسبت ولی","width":15},"emergency_phone":{"label":"شماره اضطراری","width":20},"return_address":{"label":"نشانی برگشت","width":32},"gender":{"label":"جنسیت","width":12},"nationality":{"label":"ملیت","width":14},"shift":{"label":"شیفت","width":14},"academic_year":{"label":"سال تحصیلی","width":15},"service_status":{"label":"وضعیت سرویس","width":18},"tariff_vehicle_class":{"label":"کلاس خودرو","width":14},"invoice_total":{"label":"جمع فاکتورهای معتبر (ریال)","width":19}};
Object.assign(debtorColumns,{"contract_amount":{"label":"مبلغ قطعی قرارداد (ریال)","width":20},"annual_months":{"label":"مدت استاندارد سال تحصیلی","width":20},"credit":{"label":"بستانکاری (ریال)","width":20},"reconciliation_status":{"label":"وضعیت تطبیق مالی","width":32}});
const debtorDefaultColumns=['student','school','service','basis','guardian_mobile','tariff_distance_km','monthly_fee','invoice_total','paid','debt'];
function debtorColumnValue(row,key){
 const value=row[key];
 if(key==='service')return [row.service_names,row.driver_names].filter(Boolean).join(' / ')||'—';
 if(key==='basis')return row.balance_source==='invoiced'?'فاکتور ثبت‌شده':row.balance_source==='refund_balance'?'مانده ناشی از خالص پرداخت / استرداد':'نیازمند بررسی';
 if(value==null||value==='')return '—';
 if(['monthly_fee','annual_fee','paid','debt','invoice_total','contract_amount','credit','tariff_distance_km'].includes(key))return new Intl.NumberFormat('fa-IR',{maximumFractionDigits:0}).format(value);
 if(['contract_start','contract_end'].includes(key)){const d=new Date(value+'T12:00:00Z');if(!isNaN(d))return new Intl.DateTimeFormat('fa-IR',{timeZone:'Asia/Tehran',year:'numeric',month:'2-digit',day:'2-digit'}).format(d);}
 return String(value).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
}
function editDebtorColumns(selected,onSave){
 const dialog=document.createElement('dialog');dialog.className='sy-debtor-columns';dialog.dir='rtl';
 let order=[...selected,...Object.keys(debtorColumns).filter(k=>!selected.includes(k))], active=new Set(selected), dragged=null;
 dialog.innerHTML='<header><h3>تنظیم ستون‌ها</h3><button type="button" data-close aria-label="بستن">×</button></header><p>ستون‌ها را انتخاب و برای تغییر ترتیب جابه‌جا کنید.</p><input type="search" placeholder="جستجوی ستون‌ها" aria-label="جستجوی ستون‌ها"><div class="sy-debtor-column-actions"><button type="button" data-all>انتخاب همه</button><button type="button" data-none>لغو انتخاب همه</button><button type="button" data-reset>پیش‌فرض</button><span role="status"></span></div><div class="sy-debtor-column-list"></div><footer><button type="button" data-close>انصراف</button><button type="button" class="primary" data-save>ذخیره تنظیمات</button></footer>';
 const list=dialog.querySelector('.sy-debtor-column-list'),search=dialog.querySelector('input'),save=dialog.querySelector('[data-save]');
 const render=()=>{list.innerHTML=order.filter(k=>debtorColumns[k].label.includes(search.value.trim())).map(k=>'<div draggable="true" data-key="'+k+'"><span aria-hidden="true">⠿</span><label><input type="checkbox" '+(active.has(k)?'checked':'')+'> '+esc(debtorColumns[k].label)+'</label><button type="button" data-move="-1" aria-label="انتقال به بالا">↑</button><button type="button" data-move="1" aria-label="انتقال به پایین">↓</button></div>').join('');dialog.querySelector('[role=status]').textContent=number(active.size)+' ستون فعال';save.disabled=!active.size;};
 list.onchange=e=>{const k=e.target.closest('[data-key]')?.dataset.key;if(k){e.target.checked?active.add(k):active.delete(k);dialog.querySelector('[role=status]').textContent=number(active.size)+' ستون فعال';save.disabled=!active.size;}};
 const move=(from,to)=>{if(from===to||to<0||to>=order.length)return;order.splice(to,0,order.splice(from,1)[0]);render();};
 list.onclick=e=>{const b=e.target.closest('[data-move]');if(b){const i=order.indexOf(b.closest('[data-key]').dataset.key);move(i,i+Number(b.dataset.move));}};
 list.ondragstart=e=>{dragged=e.target.closest('[data-key]')?.dataset.key;if(dragged)e.dataTransfer.setData('text/plain',dragged);};
 list.ondragover=e=>{if(dragged&&e.target.closest('[data-key]'))e.preventDefault();};
 list.ondrop=e=>{e.preventDefault();const k=e.target.closest('[data-key]')?.dataset.key;if(dragged&&k)move(order.indexOf(dragged),order.indexOf(k));dragged=null;};
 list.ondragend=()=>{dragged=null;};search.oninput=render;
 dialog.querySelector('[data-all]').onclick=()=>{active=new Set(order);render();};
 dialog.querySelector('[data-none]').onclick=()=>{active.clear();render();};
 dialog.querySelector('[data-reset]').onclick=()=>{order=Object.keys(debtorColumns);active=new Set(debtorDefaultColumns);render();};
 dialog.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>dialog.close());
 save.onclick=()=>{onSave(order.filter(k=>active.has(k)));dialog.close();};
 dialog.addEventListener('close',()=>dialog.remove(),{once:true});document.body.append(dialog);render();dialog.showModal();
}
async function downloadDebtorPdfs(report,onProgress,columns=debtorDefaultColumns){
 await loadHtml2Pdf();
 const groups=new Map();
 for(const row of report.items){
  if(!groups.has(row.school_id))groups.set(row.school_id,[]);
  groups.get(row.school_id).push(row);
 }
 const amount=new Intl.NumberFormat('fa-IR',{maximumFractionDigits:2});
 const date=new Intl.DateTimeFormat('fa-IR',{timeZone:'Asia/Tehran',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 const persian=value=>String(value??'').replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
 const headings=columns.map(k=>debtorColumns[k].label);
 const cell='border:1px solid #cdded2;padding:6px 5px;overflow-wrap:anywhere;';
 const wrap=document.createElement('div');
 wrap.style.cssText='position:fixed;top:0;left:0;z-index:-1;pointer-events:none';
 const sheet=document.createElement('div');
 sheet.dir='rtl';
 sheet.style.cssText='box-sizing:border-box;width:1046px;background:white;color:#182820;font:13px/1.45 Vazirmatn,Tahoma,sans-serif;direction:rtl';
 wrap.appendChild(sheet);
 document.body.appendChild(wrap);
 const files=[],names=new Set();
 const pageSize=columns.length>15?8:14;
 const totalPages=[...groups.values()].reduce((total,rows)=>total+Math.ceil(rows.length/pageSize),0);
 let completedPages=0;
 onProgress(0,totalPages);
 try{
  for(const rows of groups.values()){
   const school=rows[0].school;
   const total=rows.reduce((sum,row)=>sum+Number(row.debt),0);
   const base=persian(school).replace(/[\\/:*?"<>|\u0000-\u001f]/g,'-').trim().slice(0,120)||'مدرسه';
   let name=base;
   for(let suffix=2;names.has(name.toLowerCase());suffix++)name=base+' ('+persian(suffix)+')';
   names.add(name.toLowerCase());
   let pdf;
   for(let start=0;start<rows.length;start+=pageSize){
    const page=rows.slice(start,start+pageSize);
    const last=start+pageSize>=rows.length;
    sheet.innerHTML='<h1 style="background:#005b35;color:white;text-align:center;margin:0;padding:12px;font-size:21px">گزارش مالی بدهکاران مدرسه '+esc(persian(school))+'</h1>'+
     '<p style="text-align:center;margin:7px 0 0">تاریخ گزارش‌گیری '+esc(date)+' · سال تحصیلی '+esc(persian(report.academicYear))+'</p>'+
     '<p style="text-align:center;margin:3px 0 10px;font-weight:bold">'+esc(persian(report.agencyName))+' - سامانه سرویس یار</p>'+
     '<table style="width:100%;border-collapse:collapse;table-layout:fixed;text-align:center"><colgroup>'+columns.map(k=>debtorColumns[k].width*100/columns.reduce((sum,k)=>sum+debtorColumns[k].width,0)).map(width=>'<col style="width:'+width+'%">').join('')+'</colgroup>'+
     '<thead><tr>'+headings.map(label=>'<th style="'+cell+'background:#087447;color:white">'+label+'</th>').join('')+'</tr></thead><tbody>'+
     page.map((row,index)=>{
      const values=columns.map(key=>debtorColumnValue(row,key));
      return '<tr style="background:'+(index%2?'#f3f8f4':'#fff')+'">'+values.map((value,column)=>'<td style="'+cell+(column===0?'text-align:right;':'text-align:center;')+'">'+esc(value)+'</td>').join('')+'</tr>';
     }).join('')+'</tbody>'+(last&&columns.includes('debt')?'<tfoot><tr style="background:#e8f5ed;color:#005b35;font-weight:bold">'+columns.map(k=>'<td style="'+cell+'">'+(k==='debt'?'مجموع: '+amount.format(total):'')+'</td>').join('')+'</tr></tfoot>':'')+'</table>'+
     '<p style="text-align:center;margin:7px 0 0">صفحه '+amount.format(start/pageSize+1)+' از '+amount.format(Math.ceil(rows.length/pageSize))+'</p>';
    await document.fonts?.ready;
    const worker=window.html2pdf().set({
     margin:8,image:{type:'jpeg',quality:0.94},jsPDF:{unit:'mm',format:'a4',orientation:'landscape'},
     html2canvas:{scale:2,backgroundColor:'#fff',scrollX:0,scrollY:0},pagebreak:{mode:[]}
    }).from(sheet);
    if(!pdf){
     pdf=await worker.toPdf().get('pdf');
    }else{
     const canvas=await worker.toCanvas().get('canvas');
     const width=Math.min(281,194*canvas.width/canvas.height);
     pdf.addPage('a4','landscape');
     pdf.addImage(canvas.toDataURL('image/jpeg',0.94),'JPEG',(297-width)/2,8,width,width*canvas.height/canvas.width);
    }
    onProgress(++completedPages,totalPages);
   }
   files.push({name:name+'.pdf',bytes:pdf.output('arraybuffer')});
  }
 }finally{wrap.remove();}
 const link=document.createElement('a');
 link.href=URL.createObjectURL(debtorPdfArchive(files));
 link.download='بدهکاران-همه-مدارس.zip';
 link.click();
 setTimeout(()=>URL.revokeObjectURL(link.href),60000);
}

async function representativesPage(token){
 if(!can('drivers'))throw Error('دسترسی به مدیریت رانندگان لازم است.');
 const info=await api(scoped('/api/v1/representatives'));
 if(token!==pageToken)return;
 const reps=info.representatives||[],drivers=info.drivers||[],schools=info.schools||[],standalone=info.standalone||[];
 $('content').innerHTML='<section class="panel sy-operation-page">'+
  '<header class="sy-operation-header"><div><h2>مدیریت نمایندگان</h2><p>نماینده می‌تواند فقط بدهی دانش‌آموزان مدارس مجاز را مشاهده کند. مدارس پیش‌فرض از سرویس‌های فعال راننده تعیین می‌شوند.</p></div><a href="#drivers/new">ثبت راننده / نماینده جدید</a></header>'+
  '<div class="sy-operation-result-head"><h3>نمایندگان شرکت · '+number(reps.length+standalone.length)+'</h3></div>'+
  simpleTable(['نام نماینده','شماره همراه','محدوده مدارس','تنظیمات'],
   reps.map(r=>[r.first_name+' '+r.last_name,r.mobile||'—',(r.schools||[]).map(sc=>sc.name).join('، ')||'بدون مدرسه',r.customScope?'دسترسی اختصاصی':'مدارس سرویس‌های فعال']))+
  '<section class="sy-representative-standalone"><h3>نمایندگان مستقل (غیرراننده)</h3>'+
  simpleTable(['نام','شماره همراه','مدارس مجاز','وضعیت'],standalone.map(r=>[r.firstName+' '+r.lastName,r.mobile||'—',(r.schools||[]).map(sc=>sc.name).join('، ')||'—',Number(r.isActive)?'فعال':'غیرفعال']))+
  '<form id="repStandaloneEdit" class="filters sy-operation-filters"><label>انتخاب نماینده<select name="userId"><option value="">انتخاب کنید</option>'+standalone.map(r=>'<option value="'+Number(r.userId)+'">'+esc(r.firstName+' '+r.lastName)+'</option>').join('')+'</select></label>'+
  '<label><input name="enabled" type="checkbox" checked> دسترسی فعال باشد</label><fieldset><legend>مدارس مجاز</legend>'+schools.map(sc=>'<label><input type="checkbox" name="schoolIds" value="'+Number(sc.id)+'"> '+esc(sc.name)+'</label>').join('')+'</fieldset>'+
  '<button class="primary" type="submit">ذخیره دسترسی نماینده مستقل</button><span role="status"></span></form>'+
  '<h3>ثبت نماینده جدید به‌عنوان کاربر مستقل</h3><form id="repStandaloneNew" class="filters sy-operation-filters">'+
  '<label>نام<input name="firstName" required maxlength="100"></label><label>نام خانوادگی<input name="lastName" required maxlength="120"></label>'+
  '<label>شماره همراه<input name="mobile" required inputmode="tel" pattern="09[0-9]{9}" maxlength="11"></label>'+
  '<label>رمز اولیه حساب<input name="password" type="password" minlength="10" maxlength="72" autocomplete="new-password" required></label>'+
  '<fieldset><legend>مدارس مجاز</legend>'+schools.map(sc=>'<label><input type="checkbox" name="schoolIds" value="'+Number(sc.id)+'"> '+esc(sc.name)+'</label>').join('')+'</fieldset>'+
  '<button class="primary" type="submit">ایجاد حساب نماینده</button><span role="status"></span></form></section>'+
  '<form id="repCompanyForm" class="sy-operation-filters filters">'+
  '<label>انتخاب راننده / نماینده<select name="driverId" required><option value="">انتخاب کنید</option>'+drivers.map(d=>'<option value="'+Number(d.id)+'">'+esc(d.first_name+' '+d.last_name+' · '+(d.mobile||''))+'</option>').join('')+'</select></label>'+
  '<label><input type="checkbox" name="enabled" checked> نماینده فعال باشد</label>'+
  '<label><input type="checkbox" name="customScope"> مدارس اختصاصی توسط شرکت تعیین شود</label>'+
  '<div class="sy-representative-school-list" id="repSchoolList"><h3>مدارس دارای دسترسی</h3><p>در حالت پیش‌فرض، مدارس از سرویس‌های فعال راننده مشخص می‌شوند.</p>'+
  schools.map(sc=>'<label><input type="checkbox" name="schoolIds" value="'+Number(sc.id)+'"> '+esc(sc.name)+'</label>').join('')+'</div>'+
  '<button type="submit" class="primary">ذخیره دسترسی نماینده</button><span id="repCompanyStatus" role="status"></span></form></section>';
 const standaloneForm=$('repStandaloneNew'),standaloneEdit=$('repStandaloneEdit');
 const schoolIds=form=>[...form.querySelectorAll('input[name="schoolIds"]:checked')].map(x=>Number(x.value));
 standaloneForm.addEventListener('submit',async e=>{
  e.preventDefault();const button=standaloneForm.querySelector('button[type=submit]'),status=standaloneForm.querySelector('[role=status]');
  button.disabled=true;status.textContent='در حال ایجاد حساب…';
  try{const input=Object.fromEntries(new FormData(standaloneForm));input.schoolIds=schoolIds(standaloneForm);
   await write('/api/v1/representatives/standalone',input);
   notify('حساب نماینده مستقل ایجاد شد.');representativesPage(token);
  }catch(err){status.textContent=err.message;}finally{button.disabled=false;}
 });
 const pick=standaloneEdit.elements.userId;
 pick.addEventListener('change',()=>{
   const record=standalone.find(x=>Number(x.userId)===Number(pick.value));
   standaloneEdit.elements.enabled.checked=record?Number(record.isActive)===1:true;
   const selected=new Set((record?.schools||[]).map(x=>Number(x.id)));
   standaloneEdit.querySelectorAll('input[name=schoolIds]').forEach(x=>x.checked=selected.has(Number(x.value)));
 });
 standaloneEdit.addEventListener('submit',async e=>{
   e.preventDefault();const button=standaloneEdit.querySelector('button[type=submit]'),status=standaloneEdit.querySelector('[role=status]');
   if(!Number(pick.value)){status.textContent='نماینده را انتخاب کنید.';return;}
   button.disabled=true;status.textContent='در حال ذخیره…';
   try{await write('/api/v1/representatives/standalone',{userId:Number(pick.value),
     enabled:standaloneEdit.elements.enabled.checked,schoolIds:schoolIds(standaloneEdit)},'PUT');
     notify('دسترسی نماینده به‌روزرسانی شد.');representativesPage(token);
   }catch(err){status.textContent=err.message;}finally{button.disabled=false;}
 });
 const form=$('repCompanyForm'),picker=form.elements.driverId,custom=form.elements.customScope,enabled=form.elements.enabled,box=$('repSchoolList'),status=$('repCompanyStatus');
 function sync(){
   const r=reps.find(x=>Number(x.id)===Number(picker.value));
   enabled.checked=r?Number(r.is_representative)===1:true;
   custom.checked=Boolean(r?.customScope);
   const selected=new Set((r?.schools||[]).map(x=>Number(x.id)));
   box.querySelectorAll('input').forEach(i=>{i.checked=selected.has(Number(i.value));i.disabled=!custom.checked;});
 }
 picker.addEventListener('change',sync);
 custom.addEventListener('change',()=>box.querySelectorAll('input').forEach(i=>{i.disabled=!custom.checked;}));
 sync();
 form.addEventListener('submit',async event=>{
   event.preventDefault();
   const btn=form.querySelector('button[type=submit]');btn.disabled=true;status.textContent='در حال ذخیره…';
   try{
      const body={driverId:Number(picker.value),enabled:enabled.checked,customScope:custom.checked,
       schoolIds:[...box.querySelectorAll('input:checked')].map(x=>Number(x.value))};
      await write('/api/v1/representatives',body);
      notify('دسترسی نماینده ذخیره شد.');
      representativesPage(token);
   }catch(error){status.textContent=error.message||'ثبت دسترسی ناموفق بود.';}
   finally{btn.disabled=false;}
 });
}
function financialReconciliationPage(token){
 if(!can('finance')||!can('students'))throw new Error('مجوز مشاهده مالی و دانش‌آموزان لازم است.');
 $('content').innerHTML=`<section class="panel sy-operation-page sy-reconciliation" dir="rtl">
 <header class="sy-operation-header"><div><h2>تطبیق و مغایرت‌های مالی</h2><p>پیش‌نمایش اختلاف اسناد؛ مبلغ قطعی قرارداد بدون بررسی تغییر نمی‌کند.</p></div></header>
 <form id="reconciliationFilters" class="filters sy-operation-filters"><label>سال تحصیلی<input name="academicYear" required value="${esc(year())}"></label><label>نام دانش‌آموز<input name="student" type="search"></label><label>مدرسه<select name="schoolId"><option value="">همه مدارس</option></select></label><button class="primary">بررسی مغایرت‌ها</button><button type="button" id="reconciliationExport">خروجی اکسل</button></form>
 <div class="sy-operation-actions" id="reconciliationActions" hidden><button type="button" id="reconciliationSelectAll">انتخاب همه موارد قابل اتصال</button><button type="button" class="primary" id="reconciliationLink">اتصال اسناد انتخاب‌شده</button></div><div id="reconciliationSummary" role="status"></div><div id="reconciliationResults" class="sy-operation-result"></div></section>`;
 const form=$('reconciliationFilters'),summary=$('reconciliationSummary'),results=$('reconciliationResults');
 let revision=0,candidates=[],reportYear="";
 const fmt=v=>v==null?'نامشخص':new Intl.NumberFormat('fa-IR',{maximumFractionDigits:0}).format(v);
 const run=async()=>{const current=++revision;summary.textContent='در حال بررسی…';results.replaceChildren();
  const params=new URLSearchParams(new FormData(form));params.set('academicYear',ServiceYarDates.digits(params.get('academicYear').trim()));
  try{const report=await api(scoped('/api/v1/operations/financial-reconciliation?'+params));if(current!==revision||token!==pageToken||!form.isConnected)return;
   const schoolSelect=form.elements.schoolId,selected=schoolSelect.value;
   schoolSelect.replaceChildren(new Option('همه مدارس',''),...(report.schools||[]).map(sc=>new Option(sc.name,String(sc.id))));
   schoolSelect.value=selected;
   reportYear=report.academicYear;
   candidates=report.items.filter(row=>row.contracts.length===1&&row.invoices.length===1&&!row.contracts[0].invoice_id&&row.contracts[0].status==='active'&&Number(row.contracts[0].amount)>0&&Number(row.contracts[0].amount)===Number(row.invoices[0].amount)&&(!row.invoices[0].legacy_year||ServiceYarDates.digits(row.invoices[0].legacy_year).replace('/','-')===reportYear)).map(row=>({studentId:Number(row.student_id),contractId:Number(row.contracts[0].id),invoiceId:Number(row.invoices[0].id),amount:Number(row.contracts[0].amount)}));
   $('reconciliationActions').hidden=!can('finance','manage')||!candidates.length;
   summary.textContent=number(report.scanned)+' پرونده بررسی شد · '+number(report.count)+' پرونده نیازمند بررسی';
   results.innerHTML=report.items.length?report.items.map(row=>'<details class="panel sy-reconciliation-item"><summary><strong>'+esc(row.student)+'</strong> · '+esc(row.school||'مدرسه نامشخص')+'</summary>'+(can('finance','manage')&&candidates.some(c=>c.studentId===Number(row.student_id))?'<label><input type="checkbox" data-reconcile-student="'+Number(row.student_id)+'"> انتخاب برای اتصال قرارداد و فاکتور هم‌مبلغ</label>':'')+'<div class="sy-reconciliation-metrics"><span>تعرفه ماهانه: '+fmt(row.monthly_fee)+'</span><span>برآورد کامل: '+fmt(row.expected)+'</span><span>فاکتورهای نیازمند تطبیق: '+fmt(row.invoice_total)+'</span><span>اختلاف برآورد: '+fmt(row.difference)+'</span></div><p>'+row.issues.map(esc).join(' · ')+'</p><h4>قراردادها</h4>'+simpleTable(['شناسه','مبلغ','شروع','پایان'],row.contracts.map(c=>[number(c.id),fmt(c.amount),debtorColumnValue({contract_start:c.start_date},'contract_start'),debtorColumnValue({contract_end:c.end_date},'contract_end')]))+'<h4>فاکتورها</h4>'+simpleTable(['شناسه','عنوان','مبلغ'],row.invoices.map(i=>[number(i.id),i.title,fmt(i.amount)]))+'</details>').join(''):'<p class="empty">مغایرتی یافت نشد.</p>';
  }catch(e){if(current===revision&&form.isConnected)summary.textContent=e.message;}
 };
 $('reconciliationSelectAll').onclick=()=>{results.querySelectorAll('[data-reconcile-student]').forEach((c,i)=>c.checked=i<100);};
 $('reconciliationLink').onclick=async()=>{const ids=new Set([...results.querySelectorAll('[data-reconcile-student]:checked')].map(c=>Number(c.dataset.reconcileStudent)));const items=candidates.filter(c=>ids.has(c.studentId));if(!items.length||items.length>100){notify('بین یک تا ۱۰۰ پرونده انتخاب کنید.',true);return;}if(!confirm('اتصال '+number(items.length)+' قرارداد به فاکتورهای هم‌مبلغ موجود را تأیید می‌کنید؟ مبلغ و پرداخت‌ها تغییر نمی‌کنند.'))return;const button=$('reconciliationLink');button.disabled=true;try{const result=await write('/api/v1/operations/financial-reconciliation/link',{academicYear:reportYear,items,confirmation:'LINK_EXISTING_DOCUMENTS'});notify(number(result.linked)+' پرونده متصل شد.');await run();}catch(e){summary.textContent=e.message;}finally{button.disabled=false;}};
 $('reconciliationExport').onclick=()=>{const params=new URLSearchParams(new FormData(form));params.set('academicYear',ServiceYarDates.digits(params.get('academicYear').trim()));downloadFile('/api/v1/reports/financial-reconciliation.xlsx?'+params,'مغایرت‌های-مالی.xlsx');};
 form.elements.schoolId.addEventListener('change',()=>void run());
 form.onsubmit=e=>{e.preventDefault();void run();};
 return run();
}
function studentDebtorsPage(token){
 if(!can('students'))throw new Error('مشاهده گزارش بدهکاران به مجوز دانش‌آموزان نیاز دارد.');
 $('content').innerHTML=`<section class="panel sy-operation-page sy-debtors-page">
  <header class="sy-operation-header"><div><h2>گزارش بدهکاران</h2><p>مانده حساب بر اساس فاکتورها و دریافت‌های ثبت‌شده؛ سرویس بدون فاکتور بدهی قطعی محسوب نمی‌شود.</p></div>
   <div class="sy-operation-actions"><button type="button" id="debtorColumnSettings">تنظیم ستون‌ها</button>${can('reports')?'<button type="button" id="debtorFiltered" disabled>اکسل نتایج</button><button type="button" id="debtorFull">اکسل کامل مدارس</button>':''}</div></header>
  <form id="debtorFilters" class="filters sy-operation-filters" aria-label="فیلتر گزارش بدهکاران">
   <label>سال تحصیلی<input name="academicYear" value="${esc(year())}" maxlength="9" required></label>
   <label>نام دانش‌آموز<input type="search" name="student" maxlength="200"></label>
   <div class="sy-debtor-school"><label for="debtorSchoolSearch">مدرسه دارای بدهی</label><input type="hidden" name="schoolId"><div class="sy-debtor-combo"><input id="debtorSchoolSearch" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="debtorSchoolOptions" autocomplete="off" placeholder="همه مدارس · جستجوی نام مدرسه" disabled><button type="button" id="debtorSchoolToggle" aria-label="نمایش فهرست مدارس" disabled>⌄</button><div id="debtorSchoolOptions" role="listbox" hidden></div></div><small id="debtorSchoolStatus" role="status">در حال دریافت مدارس بدهکار…</small></div>
   <label>حداقل بدهی (ریال)<input name="minDebt" inputmode="decimal" maxlength="19"></label>
   <label>حداکثر بدهی (ریال)<input name="maxDebt" inputmode="decimal" maxlength="19"></label>
   <button class="primary">نمایش بدهکاران</button>
   <button type="reset">پاک کردن فیلترها</button>
  </form>
  <div class="sy-operation-result-head"><h3>نتایج گزارش</h3><span id="debtorStatus" role="status"></span></div>
  <div id="debtorResults" class="sy-operation-result"></div>
 </section>`;
 const form=$('debtorFilters'),status=$('debtorStatus'),results=$('debtorResults'),filtered=$('debtorFiltered'),full=$('debtorFull');
 const amount=new Intl.NumberFormat('fa-IR',{maximumFractionDigits:2});
 const kilometers=new Intl.NumberFormat('fa-IR',{maximumFractionDigits:0});
 const schoolSearch=$('debtorSchoolSearch'),schoolSelect=form.elements.schoolId,schoolStatus=$('debtorSchoolStatus'),options=$('debtorSchoolOptions'),toggle=$('debtorSchoolToggle');
 let schools=[],schoolRevision=0,active=-1,visible=[];
 const closeSchools=()=>{options.hidden=true;schoolSearch.setAttribute('aria-expanded','false');schoolSearch.removeAttribute('aria-activedescendant');active=-1;};
 const chooseSchool=id=>{const school=schools.find(s=>String(s.id)===String(id));schoolSelect.value=school?String(school.id):'';schoolSearch.value=school?.name||'';closeSchools();};
 const renderSchools=(all=false)=>{
  const term=all?'':normalizeServiceSearch(schoolSearch.value);
  visible=[{id:'',name:'همه مدارس'},...schools.filter(s=>normalizeServiceSearch(s.name).includes(term))];
  active=-1;schoolSearch.removeAttribute('aria-activedescendant');
  options.innerHTML=visible.map((s,i)=>'<div role="option" id="debtorSchoolOption'+i+'" data-school-index="'+i+'" aria-selected="'+(String(s.id)===schoolSelect.value)+'">'+esc(s.name)+'</div>').join('');
  options.hidden=false;schoolSearch.setAttribute('aria-expanded','true');
 };
 schoolSearch.addEventListener('focus',()=>renderSchools(true));
 schoolSearch.addEventListener('input',()=>{schoolSelect.value='';renderSchools();});
 toggle.onclick=()=>{if(options.hidden){schoolSearch.focus();renderSchools(true);}else closeSchools();};
 options.onpointerdown=e=>e.preventDefault();
 options.onclick=e=>{const item=e.target.closest('[data-school-index]');if(item)chooseSchool(visible[Number(item.dataset.schoolIndex)].id);};
 schoolSearch.onblur=()=>{chooseSchool(schoolSelect.value);};
 schoolSearch.onkeydown=e=>{
  if(e.key==='Escape'){chooseSchool(schoolSelect.value);return;}
  if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(options.hidden)renderSchools(true);active=Math.max(0,Math.min(visible.length-1,active+(e.key==='ArrowDown'?1:-1)));const node=$('debtorSchoolOption'+active);options.querySelectorAll('[data-active]').forEach(n=>n.removeAttribute('data-active'));node?.setAttribute('data-active','true');node?.scrollIntoView({block:'nearest'});schoolSearch.setAttribute('aria-activedescendant','debtorSchoolOption'+active);}
  if(e.key==='Enter'&&!options.hidden){e.preventDefault();if(active>=0)chooseSchool(visible[active].id);}
 };
 const loadSchools=async()=>{
  const current=++schoolRevision,academicYear=ServiceYarDates.digits(form.elements.academicYear.value.trim());
  schools=[];chooseSchool('');schoolSearch.disabled=toggle.disabled=true;schoolStatus.textContent='در حال دریافت مدارس بدهکار…';
  try{
   const data=await api(scoped('/api/v1/operations/debtors?'+new URLSearchParams({academicYear})));
   if(token!==pageToken||current!==schoolRevision||!form.isConnected)return;
   schools=[...new Map(data.items.filter(r=>Number(r.debt)>0&&Number(r.school_id)>0).map(r=>[String(r.school_id),{id:r.school_id,name:r.school}])).values()].sort((a,b)=>a.name.localeCompare(b.name,'fa'));
   schoolSearch.disabled=toggle.disabled=!schools.length;schoolStatus.textContent=number(schools.length)+' مدرسه دارای بدهی';
  }catch(error){if(current===schoolRevision&&form.isConnected)schoolStatus.textContent='دریافت مدارس ناموفق بود: '+error.message;}
 };
 form.elements.academicYear.addEventListener('change',loadSchools);
 let applied=null,revision=0,lastReport=null;
 const storageKey='sy.debtors.columns.v1.'+String(session?.id??session?.user?.id??session?.username??'unknown')+'.'+currentAgency.id;
 let selectedColumns=[...debtorDefaultColumns];
 try{const saved=JSON.parse(localStorage.getItem(storageKey));if(Array.isArray(saved)){const valid=[...new Set(saved.filter(k=>Object.hasOwn(debtorColumns,k)))];if(valid.length)selectedColumns=valid;}}catch(_){}
 const renderResults=()=>{if(!lastReport)return;results.innerHTML=lastReport.items.length?'<div class="sy-debtor-table-scroll">'+simpleTable(selectedColumns.map(k=>debtorColumns[k].label),lastReport.items.map(row=>selectedColumns.map(k=>debtorColumnValue(row,k))))+'</div>':'<p class="empty">بدهکاری مطابق فیلترهای انتخاب‌شده یافت نشد.</p>';};
 $('debtorColumnSettings').onclick=()=>editDebtorColumns(selectedColumns,columns=>{selectedColumns=columns;try{localStorage.setItem(storageKey,JSON.stringify(columns));}catch(_){}renderResults();});
 const exportParams=params=>{const p=new URLSearchParams(params);p.set('columns',selectedColumns.join(','));return p;};
 const request=()=>{
  const params=new URLSearchParams(new FormData(form));

  for(const key of ['academicYear','minDebt','maxDebt'])params.set(key,ServiceYarDates.digits(params.get(key).trim()).replace(/[٬,]/g,'').replace('٫','.'));
  return params;
 };
 const load=async()=>{
  const current=++revision,params=request();
  if(filtered)filtered.disabled=true;
  lastReport=null;results.replaceChildren();status.textContent='در حال دریافت…';
  try{
   const data=await api(scoped('/api/v1/operations/debtors?'+params));
   if(token!==pageToken||current!==revision)return;
   applied=params;
   status.textContent=number(data.items.length)+' بدهکار · مجموع بدهی '+amount.format(data.total)+' ریال'+(data.unbilledCount?' · '+number(data.unbilledCount)+' مورد فاقد فاکتور ('+amount.format(data.unbilledTotal)+' ریال برآوردی)':'');
   lastReport=data;renderResults();
   if(filtered)filtered.disabled=false;
  }catch(err){if(token===pageToken&&current===revision)status.textContent=err.message;}
 };
 form.onsubmit=e=>{e.preventDefault();load();};
 form.onreset=()=>setTimeout(()=>{if(form.isConnected){loadSchools();load();}},0);
 if(filtered)filtered.onclick=()=>downloadFile('/api/v1/reports/debtors.xlsx?'+exportParams(applied),'بدهکاران-نتایج.xlsx');
 if(full)full.onclick=()=>downloadFile('/api/v1/reports/debtors.xlsx?'+exportParams({scope:'all',academicYear:request().get('academicYear')}),'بدهکاران-همه-مدارس.xlsx');
 if(full){
  const pdf=document.createElement('button'),pdfStatus=document.createElement('span');
  pdf.type='button';
  pdf.textContent='خروجی کامل PDF';
  pdfStatus.setAttribute('role','status');
  full.after(pdf,pdfStatus);
  pdf.onclick=async()=>{
   const pdfColumns=[...selectedColumns];
   const task=window.ServiceYarLoading?.begin('ساخت گزارش PDF بدهکاران',{immediate:true});
   pdf.disabled=true;
   pdfStatus.textContent='در حال دریافت بدهکاران مدارس…';
   try{
    const params=new URLSearchParams({academicYear:request().get('academicYear')});
    const report=await api(scoped('/api/v1/operations/debtors?'+params));
    if(!report.items.length){pdfStatus.textContent='در این سال تحصیلی بدهکاری یافت نشد.';return;}
    task?.update({detail:'در حال تبدیل داده‌های مدارس به PDF…'});
    await downloadDebtorPdfs(report,(index,count)=>{
     pdfStatus.textContent='تبدیل صفحه '+number(index)+' از '+number(count)+' به PDF…';
     task?.update({detail:pdfStatus.textContent,percent:count>0?Math.min(99,Math.round(index/count*100)):null});
    },pdfColumns);
    task?.update({detail:'فایل ZIP آماده شد.',percent:100});
    pdfStatus.textContent='فایل ZIP آماده شد.';
   }catch(error){pdfStatus.textContent=error.message||'خطا در ساخت خروجی PDF.';}
   finally{pdf.disabled=false;task?.finish();}
  };
 }
 void loadSchools();return load();
}
function serviceFinanceRange(academicYear){
 const y=ServiceYarDates.digits(String(academicYear)).match(/^(1[34]\d{2})[-/](1[34]\d{2})$/);
 if(!y||Number(y[1])+1!==Number(y[2]))return null;
 const from=ServiceYarDates.toISO(y[1]+'/07/01'),next=ServiceYarDates.toISO(y[2]+'/07/01');
 const end=new Date(next+'T00:00:00Z');end.setUTCDate(end.getUTCDate()-1);
 return {from,to:end.toISOString().slice(0,10)};
}

function serviceFinancePanel(panel,{studentId=null,academicYear=year()}={}){
 const dates=serviceFinanceRange(academicYear);
 panel.innerHTML=`<h3>${studentId?'مبلغ سالانه سرویس و حساب ولی':'صورت‌وضعیت تخصیص‌های فعال'}</h3>
  <p class="muted">برآورد از مبلغ ماهانه × تعداد ماه خدمت به دست می‌آید. دریافت‌ها فقط پرداخت‌های ثبت‌شده برای فاکتورهای همین بازه تا تاریخ پایان هستند.</p>
  <form class="filters">
   <label>سال تحصیلی<input name="academicYear" value="${esc(academicYear)}" maxlength="9" ${studentId?'readonly':''} required></label>
   <label>تعداد ماه خدمت<input type="number" name="months" min="1" max="12" value="${studentId?9:1}" required></label>
   <label>صدور فاکتور از<input type="date" name="from" value="${dates?.from||''}" required></label>
   <label>تا تاریخ<input type="date" name="to" value="${dates?.to||''}" required></label>
   <button class="primary">محاسبه و نمایش</button>
  </form><div data-service-finance-result aria-live="polite"></div>`;
 const form=panel.querySelector('form'),result=panel.querySelector('[data-service-finance-result]');
 const load=async()=>{
  const button=form.querySelector('button');
  button.disabled=true;result.textContent='در حال دریافت صورت‌وضعیت…';
  try{
   const params=new URLSearchParams(new FormData(form));
   params.set('academicYear',ServiceYarDates.digits(params.get('academicYear')));
   const path=studentId?'/api/v1/students/'+studentId+'/service-finance':'/api/v1/operations/service-finance';
   const data=await api(scoped(path+'?'+params));
   if(!panel.isConnected)return;
   const money=v=>v===null?'تعرفه ثبت نشده':number(v);
   const summary=[['برآورد سرویس',money(data.totals.estimated_fee)],['فاکتور صادرشده',money(data.totals.invoiced)],['پرداخت ثبت‌شده',money(data.totals.received)],['مانده فاکتور',money(data.totals.balance)]];
   result.innerHTML='<div class="statement-summary">'+summary.map(([name,value])=>'<span>'+esc(name)+' <b>'+esc(value)+'</b></span>').join('')+'</div>'+
    '<p class="muted">مبالغ به ریال · تعداد ماه: '+number(data.months)+' · بدون مبلغ ماهانه: '+number(data.totals.missing_fees)+'</p>'+
    (data.items.length?simpleTable(['دانش‌آموز','مدرسه','سرویس','راننده','ماهیانه','برآورد','فاکتور','دریافت','مانده'],data.items.map(r=>[r.student,r.school||'—',Number(r.assignment_count)>1?'تداخل تخصیص':r.service||'—',r.driver||'—',money(r.monthly_fee),money(r.estimated_fee),money(r.invoiced),money(r.received),money(r.balance)])):'<p class="empty">تخصیص فعالی برای این سال یافت نشد.</p>');
   if(!studentId&&can('reports')){
    const exportButton=document.createElement('button');
    exportButton.textContent='دریافت اکسل صورت‌وضعیت';
    exportButton.onclick=()=>downloadFile('/api/v1/reports/service-finance.xlsx?'+params,'صورت-وضعیت-سرویس.xlsx');
    result.prepend(exportButton);
   }
  }catch(err){if(panel.isConnected)result.textContent=err.message;}
  finally{button.disabled=false;}
 };
 form.elements.academicYear.onchange=()=>{
  const next=serviceFinanceRange(form.elements.academicYear.value);
  if(next){form.elements.from.value=next.from;form.elements.to.value=next.to;}
 };
 form.onsubmit=e=>{e.preventDefault();load();};
 if(studentId&&dates)load();
}

async function schoolStatementPage(token){
 const parts=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',timeZone:'Asia/Tehran'}).formatToParts(new Date());
 const jy=Number(parts.find(p=>p.type==='year').value),jm=Number(parts.find(p=>p.type==='month').value);
 const months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
 const allowed=can('finance','manage');
 $('content').innerHTML=`<section class="panel sy-statement-panel">
 <div class="sy-statement-heading"><div><span class="sy-statement-kicker">بایگانی مالی سرویس مدارس</span><h2>صورت‌وضعیت‌های ماهانه</h2><p>جست‌وجو، بررسی نسخه‌های نهایی و دریافت خروجی همان ماه ثبت‌شده</p></div><div class="sy-statement-count" id="statementArchiveCount" role="status" aria-live="polite">در حال دریافت سوابق…</div></div>
 <div class="sy-statement-filters" role="search" aria-label="جست‌وجو و فیلتر صورت‌وضعیت‌ها">
  <label>جست‌وجوی ماه یا سال<input id="statementArchiveSearch" type="search" autocomplete="off" placeholder="مثلاً آبان ۱۴۰۵"></label>
  <label>وضعیت<select id="statementArchiveStatus"><option value="final">نهایی و فعال</option><option value="all">همه وضعیت‌ها</option><option value="deleted">حذف‌شده‌ها</option></select></label>
  <label>مرتب‌سازی<select id="statementArchiveOrder"><option value="desc">جدیدترین دوره</option><option value="asc">قدیمی‌ترین دوره</option><option value="amount">بیشترین مبلغ صورت‌وضعیت</option></select></label>
  <button type="button" id="statementArchiveReset" aria-label="پاک‌کردن فیلترهای صورت‌وضعیت">پاک‌کردن فیلترها</button>
 </div>
 <div id="statementArchive" class="table-wrap sy-statement-archive" aria-live="polite" aria-busy="true"></div>
 </section>
 <section class="panel sy-statement-panel sy-statement-create">
 <div class="sy-statement-heading"><div><span class="sy-statement-kicker">مرحله اول · محاسبه و کنترل</span><h2>تهیه صورت‌وضعیت ماهانه</h2><p>ابتدا ماه موردنظر را انتخاب و محاسبات را بررسی کنید؛ ثبت تنها پس از تأیید نهایی انجام می‌شود.</p></div></div>
 <form id="statementMonthForm" class="filters sy-statement-period">
 <label>سال شمسی<input name="year" type="number" inputmode="numeric" min="1400" max="1500" value="${jy}" required></label>
 <label>ماه شمسی<select name="month">${months.map((name,i)=>`<option value="${i+1}" ${jm===i+1?'selected':''}>${name}</option>`).join('')}</select></label>
 <label>شروع دوره<input name="from" type="date" required readonly aria-describedby="statementPeriodHelp"></label>
 <label>پایان دوره<input name="to" type="date" required readonly aria-describedby="statementPeriodHelp"></label>
 <button class="primary" type="submit">محاسبه و نمایش پیش‌نمایش</button>
 </form>
 <p class="sy-statement-period-hint" id="statementPeriodHelp">بازه به‌صورت خودکار از اولین تا آخرین روز همان ماه شمسی محاسبه می‌شود.</p>
 <div id="statementPreviewArea" aria-live="polite"></div>
 </section><div id="modal"></div>`;
 const form=$('statementMonthForm');
 const applyMonth=()=>{const range=persianMonthRange(Number(form.elements.year.value),Number(form.elements.month.value));form.elements.from.value=range?.from||'';form.elements.to.value=range?.to||'';const box=$('statementPreviewArea');if(box?.childElementCount)box.innerHTML='<p class="sy-statement-period-hint" role="status">ماه تغییر کرد؛ برای تأیید این دوره، پیش‌نمایش تازه تهیه کنید.</p>';};
 form.elements.year.addEventListener('change',applyMonth);form.elements.month.addEventListener('change',applyMonth);applyMonth();
 const archive=$('statementArchive'),preview=$('statementPreviewArea');
 const details=d=>{
  const t=d.totals||{},r=d.reference_totals||{},refDrivers=d.reference_drivers||[],errors=d.validation_errors||[],warnings=d.reconciliation_warnings||[];
  const hasReference=Object.keys(r).length>0,missingDrivers=d.missing_driver_errors||[];
  const fmt=v=>number(Number(v||0));
  return `<div class="statement-summary" role="group" aria-label="خلاصه صورت‌وضعیت">
  ${[['دانش‌آموزان',fmt(t.students)],['رانندگان',fmt(t.drivers)],['مدارس',fmt(t.schools)],
    ['حق‌الزحمه رانندگان (ریال)',fmt(hasReference?r.driver_fee:t.gross)],
    ['نظارت ۳٪ (ریال)',fmt(r.oversight_3pct)],
    ['هماهنگی ۱٪ (ریال)',fmt(r.education_1pct)],
    ['سهم شرکت ۷٪ (ریال)',fmt(r.company_7pct)],
    ['گواهی صلاحیت (ریال)',fmt(r.certificate_fee)],
    ['بیمه (ریال)',fmt(r.insurance_fee)],
    ['بهای خدمات اولیا (ریال)',fmt(r.parent_total)],
    ['صورت‌وضعیت بدون بیمه و گواهی (ریال)',fmt(r.statement_excluding_certificate_insurance)]
  ].map(([k,v])=>`<span>${esc(k)}: <b>${esc(v)}</b></span>`).join('')}</div>
  ${errors.length?`<div class="error" role="alert"><strong>ثبت نهایی مسدود است:</strong> نرخ‌نامه ${number(errors.length)} دانش‌آموز معتبر نیست.<div class="table-wrap">${simpleTable(['دانش‌آموز','کلاس خودرو','مسافت','ناحیه','اشکال'],errors.map(x=>[x.student_name,x.vehicle_class||'ثبت نشده',x.distance_km,x.region,x.message]))}</div></div>`:''}
  ${missingDrivers.length?`<div class="error" role="alert">تعداد ${number(missingDrivers.length)} دانش‌آموز به سرویس بدون راننده متصل هستند؛ پیش از ثبت، راننده‌های این سرویس‌ها را مشخص کنید.<div class="table-wrap">${simpleTable(['دانش‌آموز','شناسه سرویس','علت'],missingDrivers.map(x=>[x.student_name,x.service_id,x.message]))}</div></div>`:''}
  ${warnings.length?`<div class="muted" role="status">مبلغ ماهانه ثبت‌شده برای ${number(warnings.length)} دانش‌آموز با نرخ‌نامه مرجع تفاوت دارد؛ پیش از تأیید بررسی شود.<div class="table-wrap">${simpleTable(['دانش‌آموز','مبلغ ثبت‌شده','بهای محاسبه‌شده اولیا'],warnings.map(x=>[x.student_name,fmt(x.recorded_monthly_fee),fmt(x.reference_parent_fee)]))}</div></div>`:''}
  <h3>صورت‌وضعیت رانندگان</h3>
  <div class="table-wrap">${simpleTable(['راننده','دانش‌آموز','حق‌الزحمه راننده','نظارت ۳٪','هماهنگی ۱٪','سهم شرکت ۷٪','جمع صورت‌وضعیت'],(refDrivers.length?refDrivers:d.drivers||[]).map(x=>[x.driver_name,fmt(x.student_count),fmt(x.driver_fee??x.gross),fmt(x.oversight_3pct),fmt(x.education_1pct),fmt(x.company_7pct),fmt(x.statement_excluding_certificate_insurance??x.net)]))}</div>
  <h3>بهای خدمات دانش‌آموزان</h3>
  <div class="table-wrap sy-statement-students">${simpleTable(['دانش‌آموز','مدرسه','راننده','حق‌الزحمه مبنا','بیمه','بهای خدمات اولیا','مبنای تعرفه'],(d.items||[]).map(x=>[x.student_name,x.school_name,x.driver_name,fmt(x.reference?.driver_fee??x.gross),fmt(x.reference?.insurance_fee),fmt(x.reference?.parent_total),x.reference_tariff_verified===false?'نیازمند اصلاح':'نرخ‌نامه مرجع']))}</div>`;
 };
 const exportStatement=async (button,path,options={},filename='صورت-وضعیت.xlsx')=>{
  if(document.querySelector('[data-statement-export-progress]'))return;
  const token=Array.from(crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,'0')).join('');
  const target=new URL(path,location.origin);target.searchParams.set('progressToken',token);
  const box=document.createElement('div');box.dataset.statementExportProgress='1';box.className='panel';
  box.setAttribute('role','status');box.setAttribute('aria-live','polite');
  box.innerHTML='<strong>در حال ساخت فایل اکسل ۱۴ شیتی…</strong><p data-export-stage>آماده‌سازی اطلاعات · ۰٪</p><progress max="100" value="0" aria-label="پیشرفت ساخت فایل اکسل"></progress>';
  button.insertAdjacentElement('afterend',box);
  const bar=box.querySelector('progress'),label=box.querySelector('[data-export-stage]');
  const labels={PREPARE:'آماده‌سازی اطلاعات',TEMPLATE_COPY:'بررسی قالب اصلی',BUILD_SOURCE_ROWS:'آماده‌سازی ردیف‌ها',NORMALIZE_FORMULAS:'تنظیم فرمول‌ها',REPLACE_DATA_SHEETS:'تکمیل شیت‌ها',UPDATE_HEADINGS:'تنظیم عنوان‌ها و جمع‌ها',SYNC_TABLES:'هماهنگ‌سازی جدول‌ها',PRINT_AREAS:'تنظیم چاپ',ZIP_FINALIZE:'بسته‌بندی فایل',DOWNLOAD:'دریافت فایل'};
  let done=false,timer,percent=0;
  const paint=(p,stage)=>{percent=Math.max(percent,Math.min(100,Number(p)||0));bar.value=percent;label.textContent=(labels[stage]||stage)+' · '+number(percent)+'٪';};
  const poll=async()=>{try{const data=await api(scoped('/api/v1/reports/school-statements/progress?token='+token));if(!done)paint(data.percent,data.stage);}catch(_){}finally{if(!done)timer=setTimeout(poll,1000);}};
  button.setAttribute('aria-disabled','true');button.setAttribute('aria-busy','true');if(button.tagName==='BUTTON')button.disabled=true;
  poll();
  try{
   const response=await fetch(target.href,{credentials:'same-origin',...options});
   if(!response.ok){const error=await response.json().catch(()=>({}));throw new Error(error?.error?.message||'تهیه فایل اکسل انجام نشد.');}
   const blob=await response.blob();
   if(blob.size<200||!String(response.headers.get('Content-Type')||'').includes('spreadsheetml'))throw new Error('پاسخ سرور فایل Excel معتبر نیست.');
   paint(100,'DOWNLOAD');
   const url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download=filename;document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);
   notify('فایل اکسل آماده شد.');
  }catch(err){notify(err.message,true);}
  finally{done=true;clearTimeout(timer);box.remove();button.removeAttribute('aria-disabled');button.removeAttribute('aria-busy');if(button.tagName==='BUTTON')button.disabled=false;}
 };
 const bindStatementExports=root=>root.querySelectorAll('a[href*="/reports/school-statements/"][href*=".xlsx"]').forEach(link=>link.onclick=e=>{e.preventDefault();if(link.getAttribute('aria-disabled')==='true')return;exportStatement(link,link.href,{},'صورت-وضعیت-'+(link.href.match(/(\d+)\.xlsx/)?.[1]||'نهایی')+'.xlsx');});
 const showDetail=async id=>{
  const opener=document.activeElement;
  try{
   const d=await api(scoped('/api/v1/operations/school-statements/'+id));if(token!==pageToken)return;
   const modal=$('modal');
   const exportLink=d.status==='final'?`<a class="button primary" href="${esc(scoped('/api/v1/reports/school-statements/'+id+'.xlsx'))}">اکسل قالب مرجع ۱۴ شیتی</a>`:'<span class="sy-statement-muted">این صورت‌وضعیت حذف شده است؛ پس از بازیابی خروجی قابل دریافت خواهد بود.</span>';
   modal.innerHTML=`<div class="modal-backdrop sy-statement-backdrop"><section class="modal-card sy-statement-modal" role="dialog" aria-modal="true" aria-labelledby="statementDetailHeading" tabindex="-1">
    <div class="sy-statement-modal-heading"><div><span class="sy-statement-kicker">جزئیات صورت‌وضعیت ثبت‌شده</span><h2 id="statementDetailHeading">صورت‌وضعیت ${esc(d.month_name)} ${number(d.year)}</h2><p>ثبت در تاریخ ${esc(dateText(d.created_at))} · ${d.status==='deleted'?'حذف‌شده':'نهایی'}</p></div><button type="button" id="statementClose" aria-label="بستن جزئیات">بستن <span aria-hidden="true">×</span></button></div>
    <div class="sy-statement-modal-body">${details(d)}</div><div class="sy-statement-modal-footer">${exportLink}<button type="button" data-close-statement>بازگشت به سوابق</button></div>
   </section></div>`;
   restoreModalBackground();returnFocus=opener;
   document.body.classList.add('has-modal');
   document.querySelectorAll('.sidebar,.header,#sessionFooter,#content > :not(#modal)').forEach(node=>{modalBackground.push([node,node.inert]);node.inert=true;});
   const dialogElement=modal.querySelector('[role="dialog"]');
   const close=()=>{modal.innerHTML='';restoreModalBackground();if(opener?.isConnected)opener.focus();};
   modal.querySelector('#statementClose').onclick=close;
   modal.querySelector('[data-close-statement]').onclick=close;
   modal.querySelector('.modal-backdrop').addEventListener('click',e=>{if(e.target.classList.contains('modal-backdrop'))close();});
   dialogElement.addEventListener('keydown',e=>{
    if(e.key==='Escape'){e.preventDefault();close();return;}
    if(e.key!=='Tab')return;
    const focusables=[...dialogElement.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])')].filter(el=>el.getClientRects().length);
    if(!focusables.length){e.preventDefault();dialogElement.focus();return;}
    const first=focusables[0],last=focusables[focusables.length-1];
    if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}
    else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
   });
   bindStatementExports(modal);
   modal.querySelector('#statementClose').focus();
  }catch(e){notify(e.message,true);}
 };
 const archiveSearch=$('statementArchiveSearch'),archiveStatus=$('statementArchiveStatus'),archiveOrder=$('statementArchiveOrder');
 let storedStatements=[],archiveRequest=0,archiveLoadError='';
 const normalizeStatementQuery=value=>String(value??'').replace(/[۰-۹]/g,x=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(x))).replace(/[٠-٩]/g,x=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(x))).replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/[\u200c\s]+/g,' ').trim().toLocaleLowerCase('fa');
 const statementAmount=r=>r.reference_totals?.statement_excluding_certificate_insurance==null?null:Number(r.reference_totals.statement_excluding_certificate_insurance);
 const paintArchive=()=>{
  if(token!==pageToken||!archive.isConnected)return;
  if(archiveLoadError){archive.innerHTML='<div class="error" role="alert">'+esc(archiveLoadError)+'</div>';$('statementArchiveCount').textContent='بارگذاری ناموفق';archive.removeAttribute('aria-busy');return;}
  const query=normalizeStatementQuery(archiveSearch.value),status=archiveStatus.value,order=archiveOrder.value;
  const filtered=storedStatements.filter(r=>{
   if(status!=='all'&&r.status!==status)return false;
   const period=(months[Number(r.jalali_month)-1]||'')+' '+r.jalali_year+' '+r.jalali_month+' '+r.period_start+' '+r.period_end;
   return !query||normalizeStatementQuery(period).includes(query);
  }).sort((a,b)=>order==='amount'?(statementAmount(b)??-1)-(statementAmount(a)??-1)||Number(b.id)-Number(a.id):
      (order==='asc'?1:-1)*((Number(a.jalali_year)*12+Number(a.jalali_month))-(Number(b.jalali_year)*12+Number(b.jalali_month)))||
      Number(b.id)-Number(a.id));
  $('statementArchiveCount').textContent=number(filtered.length)+' صورت‌وضعیت از '+number(storedStatements.length)+' سابقه';
  archive.innerHTML=filtered.length?`<table class="sy-statement-table"><thead><tr><th scope="col">ردیف</th><th scope="col">ماه</th><th scope="col">بازه</th><th scope="col">دانش‌آموز</th><th scope="col">راننده</th><th scope="col">حق‌الزحمه رانندگان</th><th scope="col">بهای خدمات اولیا</th><th scope="col">جمع صورت‌وضعیت</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead><tbody>${filtered.map((r,i)=>`<tr><td data-label="ردیف">${number(i+1)}</td><td data-label="ماه"><strong>${esc(months[Number(r.jalali_month)-1]||'—')} ${number(r.jalali_year)}</strong></td><td data-label="بازه">${esc(dateText(r.period_start))} تا ${esc(dateText(r.period_end))}</td><td data-label="دانش‌آموز">${number(r.totals?.students)}</td><td data-label="راننده">${number(r.totals?.drivers)}</td><td data-label="حق‌الزحمه رانندگان" class="sy-financial-number">${number(r.reference_totals?.driver_fee??r.totals?.gross)}</td><td data-label="بهای خدمات اولیا" class="sy-financial-number">${r.reference_totals?.parent_total!=null?number(r.reference_totals.parent_total):'—'}</td><td data-label="جمع صورت‌وضعیت" class="sy-financial-number"><strong>${statementAmount(r)===null?'—':number(statementAmount(r))}</strong></td><td data-label="وضعیت"><span class="sy-statement-state ${r.status==='deleted'?'is-deleted':'is-final'}">${r.status==='deleted'?'حذف‌شده':'نهایی'}</span></td><td data-label="عملیات"><div class="member-actions"><button type="button" data-statement-detail="${r.id}">جزئیات</button>${r.status==='final'?'<a href="'+esc(scoped('/api/v1/reports/school-statements/'+r.id+'.xlsx'))+'">خروجی اکسل</a>':''}${allowed?`<button type="button" data-statement-toggle="${r.id}" data-statement-action="${r.status==='deleted'?'restore':'delete'}">${r.status==='deleted'?'بازیابی':'حذف'}</button>`:''}</div></td></tr>`).join('')}</tbody></table>`:'<div class="sy-statement-empty"><strong>صورت‌وضعیتی با این فیلتر پیدا نشد.</strong><p>ماه، سال یا وضعیت انتخابی را تغییر دهید.</p></div>';
  archive.removeAttribute('aria-busy');
  bindStatementExports(archive);
  archive.querySelectorAll('[data-statement-detail]').forEach(b=>b.onclick=()=>showDetail(Number(b.dataset.statementDetail)));
  archive.querySelectorAll('[data-statement-toggle]').forEach(b=>b.onclick=async()=>{
   const restore=b.dataset.statementAction==='restore';
   if(!confirm(restore?'صورت‌وضعیت بازیابی شود؟':'صورت‌وضعیت به بخش حذف‌شده‌ها منتقل شود؟'))return;
   b.disabled=true;
   try{await write('/api/v1/operations/school-statements/'+b.dataset.statementToggle+'/'+b.dataset.statementAction,{},'POST');notify(restore?'بازیابی شد.':'حذف شد.');await load();}
   catch(e){notify(e.message,true);b.disabled=false;}
  });
 };
 const load=async()=>{
  const request=++archiveRequest;archive.setAttribute('aria-busy','true');archive.innerHTML='<p class="loading" role="status">در حال دریافت صورت‌وضعیت‌ها…</p>';
  try{
   const list=await api(scoped('/api/v1/operations/school-statements?includeDeleted=1'));
   if(token!==pageToken||request!==archiveRequest)return;
   storedStatements=Array.isArray(list)?list:[];archiveLoadError='';paintArchive();
  }catch(e){if(token!==pageToken||request!==archiveRequest)return;archiveLoadError=e.message;paintArchive();}
 };
 archiveSearch.addEventListener('input',paintArchive);archiveStatus.addEventListener('change',paintArchive);archiveOrder.addEventListener('change',paintArchive);
 $('statementArchiveReset').onclick=()=>{archiveSearch.value='';archiveStatus.value='final';archiveOrder.value='desc';paintArchive();archiveSearch.focus();};
 form.onsubmit=async e=>{
  e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;preview.innerHTML='<p class="loading">در حال تهیه پیش‌نمایش…</p>';
  const payload={year:Number(form.elements.year.value),month:Number(form.elements.month.value),from:form.elements.from.value,to:form.elements.to.value};
  try{const d=await write('/api/v1/operations/school-statements/preview',payload,'POST');if(token!==pageToken)return;
   if(Number(form.elements.year.value)!==payload.year||Number(form.elements.month.value)!==payload.month){
    preview.innerHTML='<p class="sy-statement-period-hint" role="status">ماه حین محاسبه تغییر کرد؛ پیش‌نمایش جدید تهیه کنید.</p>';return;
   }
   preview.innerHTML=`<div class="toolbar"><h3>پیش‌نمایش ${esc(d.month_name)} ${number(d.year)}</h3><span class="badge">ثبت‌نشده</span></div>${details(d)}<div class="member-actions"><button type="button" id="downloadStatementPreviewXlsx">دریافت اکسل آزمایشی ۱۴ شیتی</button>${allowed&&!d.validation_errors?.length&&!d.missing_driver_errors?.length&&!d.reconciliation_warnings?.length?'<button type="button" class="primary" id="finalizeStatement">تأیید و ثبت نهایی</button>':''}</div><p class="sy-statement-period-hint">فایل آزمایشی فقط برای بررسی است؛ دریافت آن هیچ صورت‌وضعیتی را ثبت یا تأیید نمی‌کند.</p>`;
   $('downloadStatementPreviewXlsx')?.addEventListener('click',async()=>{
    if(Number(form.elements.year.value)!==payload.year||Number(form.elements.month.value)!==payload.month||form.elements.from.value!==payload.from||form.elements.to.value!==payload.to){
     notify('دوره تغییر کرده است؛ پیش‌نمایش تازه تهیه کنید.',true);return;
    }
    await exportStatement($('downloadStatementPreviewXlsx'),scoped('/api/v1/reports/school-statements/preview.xlsx'),{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','X-CSRF-Token':session.csrfToken},
      body:JSON.stringify({...payload,approvalDigest:d.approval_digest})
    },'پیش‌نمایش-صورت‌وضعیت-'+payload.year+'-'+String(payload.month).padStart(2,'0')+'.xlsx');
   });
   $('finalizeStatement')?.addEventListener('click',async()=>{if(Number(form.elements.year.value)!==payload.year||Number(form.elements.month.value)!==payload.month){notify('دوره تغییر کرده است؛ پیش‌نمایش تازه تهیه کنید.',true);return;}if(!confirm('صورت‌وضعیت این ماه با مبالغ نمایش‌داده‌شده نهایی و ثبت شود؟'))return;const b=$('finalizeStatement');b.disabled=true;try{const result=await write('/api/v1/operations/school-statements',{...payload,approvalDigest:d.approval_digest},'POST');preview.innerHTML='<p class="ok">صورت‌وضعیت ماهانه ثبت شد.</p>';notify('صورت‌وضعیت نهایی ثبت شد.');await load();showDetail(result.id);}catch(err){notify(err.message,true);b.disabled=false;}});
  }catch(err){preview.innerHTML='<p class="error">'+esc(err.message)+'</p>';}finally{button.disabled=false;}
 };
 await load();
}
async function operationPage(key,token){
 const dates=['payroll','finance-report'].includes(key),group=key==='grouping';
 $('content').innerHTML=`<section class="panel sy-operation-page"><header class="sy-operation-header"><div><span class="sy-operation-kicker">مرکز عملیات و گزارش‌ها</span><h2>${esc(titles[key])}</h2><p>فیلترهای موردنظر را انتخاب کنید و نتیجه را در همین صفحه بررسی کنید.</p></div><div class="sy-operation-actions"><button type="button" id="printOperation" ${key==='payroll'?'hidden':''} disabled aria-label="چاپ نتیجه گزارش">چاپ گزارش</button></div></header><form id="operationFilters" class="filters sy-operation-filters" aria-label="فیلترهای گزارش">${dates?'<label>از تاریخ<input name="from" type="date" required></label><label>تا تاریخ<input name="to" type="date" required></label>':''}${group?'<label>مدرسه<select name="schoolId" required id="groupSchool"></select></label><label>ظرفیت<input name="capacity" type="number" min="1" max="50" value="4"></label>':''}${key==='payroll'?'<label>روش محاسبه حقوق<select name="calculationMode" id="payrollCalculationMode"><option value="assignment_estimate">حقوق سرویس‌ها با مبنای ۳۰ روز (پیش‌فرض)</option><option value="recorded">فقط کارکرد ثبت‌شده</option></select></label>':''}${key==='distances'?'<label>فاصله مستقیم بیشتر از (کیلومتر)<input type="number" name="km" value="20" min="1" max="1000"></label>':''}<button type="submit" class="primary" id="operationSubmit">${key==='monitoring'?'به‌روزرسانی':'نمایش نتایج'}</button></form><div class="sy-operation-result-head"><h3>نتایج ${esc(titles[key])}</h3><span id="operationResultStatus" role="status" aria-live="polite">در انتظار دریافت اطلاعات</span></div><div id="operationResult" class="sy-operation-result" aria-live="polite"></div></section><div id="modal"></div>`;
 $('printOperation').onclick=()=>window.print();if(dates){const now=new Date(),y=now.getFullYear(),m=now.getMonth();const iso=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');$('operationFilters').elements.from.value=iso(new Date(y,m,1));$('operationFilters').elements.to.value=iso(new Date(y,m+1,0));}
 if(dates){const parts=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',timeZone:'Asia/Tehran'}).formatToParts(new Date()),jy=Number(parts.find(p=>p.type==='year').value),jm=Number(parts.find(p=>p.type==='month').value),label=document.createElement('label');label.textContent='ماه شمسی';label.innerHTML+='<select id="jalaliMonth">'+['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'].map((name,i)=>'<option value="'+(i+1)+'" '+(jm===i+1?'selected':'')+'>'+name+'</option>').join('')+'</select>';const yearLabel=document.createElement('label');yearLabel.textContent='سال شمسی';yearLabel.innerHTML+='<input id="jalaliYear" type="number" min="1300" max="1500" value="'+jy+'">';$('operationFilters').prepend(label,yearLabel);const apply=()=>{const range=persianMonthRange(Number($('jalaliYear').value),Number($('jalaliMonth').value));if(range){$('operationFilters').elements.from.value=range.from;$('operationFilters').elements.to.value=range.to;}};$('jalaliYear').onchange=apply;$('jalaliMonth').onchange=apply;apply();}if(group){const schools=await lookup('schools');if(token!==pageToken)return;$('groupSchool').innerHTML=schools.map(s=>`<option value="${s.id}">${esc(s.name)}</option>`).join('');}
 let request=0;const load=async()=>{const current=++request,result=$('operationResult'),status=$('operationResultStatus'),submit=$('operationSubmit'),print=$('printOperation');submit.disabled=true;print.disabled=true;status.textContent='در حال دریافت اطلاعات…';result.setAttribute('aria-busy','true');result.innerHTML='<div class="sy-operation-loading" role="status"><span class="sy-operation-spinner" aria-hidden="true"></span>در حال دریافت و آماده‌سازی گزارش…</div>';const query=new URLSearchParams(new FormData($('operationFilters'))),path=key==='monitoring'?'/api/v1/monitoring/activity':'/api/v1/operations/'+({'finance-report':'ledger','school-report':'schools'}[key]||key);
 try{const d=await api(scoped(path+'?'+query));if(token!==pageToken||current!==request)return;
 if(key==='payroll')await renderPayroll(d,token);
 if(key==='finance-report'){
  const typeNames={student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت',invoice:'فاکتور'};
  const sourceByType={student:'students',driver:'drivers',school:'schools'};
  const involved=[...new Set((d.items||[]).map(item=>item.party_type))].filter(type=>sourceByType[type]);
  const parties={};
  await Promise.all(involved.map(async type=>{
   try{const rows=await lookup(sourceByType[type],true);parties[type]=new Map(rows.map(row=>[String(row.id),person(row)]));}
   catch{parties[type]=new Map();}
  }));
  if(token!==pageToken||current!==request)return;
  const partyName=row=>row.party_name||(
   row.party_type==='agency'&&String(row.party_id)===String(currentAgency?.id)?currentAgency.name:
   parties[row.party_type]?.get(String(row.party_id))
  )||('شماره '+persianDigits(row.party_id||'—'));
  result.innerHTML=simpleTable(['نوع طرف حساب','نام طرف حساب','دریافت (ریال)','پرداخت (ریال)','خالص دریافت'],
   (d.items||[]).map(row=>[typeNames[row.party_type]||row.party_type,partyName(row),number(row.receipts),number(row.payments),number(Number(row.receipts)-Number(row.payments))]));
 }
 if(key==='school-report')result.innerHTML=simpleTable(['کد مدرسه','مدرسه','شیفت','نوع','دانش‌آموز','دارای سرویس','جمع ماهیانه'],d.items.map(r=>[r.code,r.name,r.shift,r.school_type,number(r.students),number(r.assigned),number(r.monthly_fees)]));if(key==='monitoring'){const users=await api(scoped('/api/v1/monitoring/users'));if(token!==pageToken||current!==request)return;result.innerHTML='<h3>وضعیت فعالیت کاربران شرکت</h3>'+simpleTable(['کاربر','وضعیت','آخرین ورود','آخرین فعالیت'],users.map(u=>[person(u),Number(u.online)?'آنلاین':'آفلاین',dateText(u.last_login_at),dateText(u.last_seen_at)]))+'<h3>رویدادهای عملیاتی</h3>'+simpleTable(['کاربر','عملیات','بخش','شناسه رکورد','تاریخ'],d.map(r=>[r.user_id,r.action,resourceName(r.entity_type),r.entity_id,dateText(r.created_at)]));}
 if(key==='distances')result.innerHTML='<p class="muted">فاصله مستقیم جغرافیایی است؛ فاصله مسیر جاده‌ای نیازمند سرویس نقشه است.</p>'+simpleTable(['دانش‌آموز','مدرسه','کیلومتر'],d.items.map(r=>[person(r),r.school,number(r.km)]));
 if(group){result.innerHTML='<p class="muted">پیشنهاد بر اساس نزدیک‌ترین مختصات و ظرفیت است. گروه‌ها را پیش از تخصیص بررسی کنید. مسیر جاده‌ای محاسبه نشده است.</p>'+d.groups.map((g,i)=>`<section class="group-card"><h3>گروه ${number(i+1)} · ${number(g.length)} نفر</h3>${simpleTable(['دانش‌آموز','نشانی'],g.map(r=>[person(r),r.address]))}<button data-use-group="${i}">تعریف سرویس برای این گروه</button></section>`).join('')+`<p>${number(d.unlocated.length)} دانش‌آموز فاقد مختصات است.</p>`;result.querySelectorAll('[data-use-group]').forEach(b=>{b.disabled=!can('services','manage');b.onclick=async()=>{const title=prompt('عنوان سرویس جدید:');if(!title?.trim())return;b.disabled=true;try{await write('/api/v1/operations/groups',{title,schoolId:query.get('schoolId'),capacity:query.get('capacity'),ids:d.groups[Number(b.dataset.useGroup)].map(st=>Number(st.id))});lookupCache={};notify('سرویس '+title+' ایجاد شد. راننده را از عملیات سرویس‌ها تعیین کنید.');await load();}catch(err){notify(err.message+'؛ سرویس‌های ایجادشده را در عملیات سرویس‌ها بررسی کنید.',true);b.disabled=false;}};});}
 if(token===pageToken&&current===request){status.textContent='گزارش به‌روزرسانی شد';print.disabled=key==='payroll';result.removeAttribute('aria-busy');submit.disabled=false;}
 }catch(e){if(token===pageToken&&current===request){status.textContent='دریافت گزارش ناموفق بود';result.removeAttribute('aria-busy');submit.disabled=false;result.innerHTML='<div class="sy-operation-error" role="alert"><strong>دریافت اطلاعات انجام نشد</strong><p>'+esc(e.message)+'</p><button type="button" id="operationRetry">تلاش مجدد</button></div>';$('operationRetry').onclick=load;}}};
 $('operationFilters').onsubmit=e=>{e.preventDefault();if(key==='payroll')payrollServiceDays={};load();};if(key==='payroll')payrollServiceDays={};if(!group||$('groupSchool').value)await load();
}

async function driverDocuments(id){
 try{const rows=await api(scoped('/api/v1/drivers/'+id+'/documents'));
 dialog('مدارک راننده',simpleTable(['نوع مدرک','شماره','انقضا','وضعیت'],rows.map(r=>[r.document_type,r.document_no,dateText(r.expires_at),({valid:'معتبر',expired:'منقضی',revoked:'لغو شده'})[r.status]]))+`<div class="member-actions">${can('drivers','manage')?rows.map(r=>`<button data-edit-document="${r.id}">ویرایش ${esc(r.document_type)}</button>`).join(''):''}</div>${can('drivers','manage')?'<form id="documentForm" class="form-grid"><label>نوع مدرک<input name="documentType" required maxlength="80"></label><label>شماره مدرک<input name="documentNo" maxlength="120"></label><label>تاریخ انقضا<input name="expiresAt" type="date"></label><label>وضعیت<select name="status"><option value="valid">معتبر</option><option value="expired">منقضی</option><option value="revoked">لغو شده</option></select></label><button class="primary">ذخیره مدرک</button></form>':''}`);
 const form=$('documentForm');if(!form)return;let editing=null;$('modal').querySelectorAll('[data-edit-document]').forEach(b=>b.onclick=()=>{editing=Number(b.dataset.editDocument);const row=rows.find(r=>Number(r.id)===editing);form.elements.documentType.value=row.document_type;form.elements.documentNo.value=row.document_no||'';form.elements.expiresAt.value=row.expires_at||'';form.elements.status.value=row.status;});
 form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('button');button.disabled=true;try{await write('/api/v1/drivers/'+id+'/documents'+(editing?'/'+editing:''),Object.fromEntries(new FormData(form)),editing?'PUT':'POST');await driverDocuments(id);}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
 }catch(e){notify(e.message,true);}
}

function financeInputFields(prefix='quote'){
 // The calculator is independent of calendar storage and historical documents.
 return `<div class="form-grid"><label>مسافت یک‌طرفه (کیلومتر)<input id="${prefix}Km" type="number" min="0" max="1000" step="1" inputmode="numeric" value="5"></label><label>روزهای کارکرد<input id="${prefix}Days" type="number" min="0" max="31" step="1" value="22"></label><label>تعداد مسیر در روز<input id="${prefix}Trips" type="number" min="1" max="4" step="1" value="2"></label></div>`;
}
function userManagementTable(box,rows,global){
 const manage=global||can('users','manage'),selfId=Number(session?.user?.id);
 const norm=value=>ServiceYarDates.digits(String(value??'')).replace(/[يى]/g,'ی').replace(/ك/g,'ک').replace(/\s+/g,' ').trim().toLocaleLowerCase('fa');
 box.innerHTML='<div class="sy-admin-user-toolbar">'+
  '<label class="sy-admin-user-search"><span>جستجوی کاربران</span><input type="search" data-user-search autocomplete="off" placeholder="نام، نام کاربری، نقش، شماره همراه یا کد ملی" aria-label="جستجوی کاربران سامانه"></label>'+
  '<span class="sy-admin-user-count" data-user-count aria-live="polite"></span>'+
  (!global&&manage?'<button type="button" data-new-user>کاربر جدید</button>':'')+'</div>'+
  '<div class="table-wrap sy-admin-user-scroll"><table><thead><tr><th>کاربر</th><th>نام کاربری</th><th>شماره همراه</th><th>کد ملی</th><th>نقش</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody data-user-results></tbody></table></div>';
 const input=box.querySelector('[data-user-search]'),tbody=box.querySelector('[data-user-results]'),counter=box.querySelector('[data-user-count]');
 const render=()=>{
  const term=norm(input.value);
  const visible=rows.filter(r=>!term||norm([r.username,r.first_name,r.last_name,r.mobile,r.national_code,r.email,r.address,person(r),r.roles,r.role_names].join(' ')).includes(term));
  counter.textContent='نمایش '+number(visible.length)+' از '+number(rows.length)+' کاربر';
  tbody.innerHTML=visible.length?visible.map(r=>'<tr><td class="sy-user-cell">'+(Number(r.has_photo)?'<img class="sy-user-avatar-sm" src="'+esc(syUserPhotoUrl(r.id,global))+'" alt="" loading="lazy">':'<span class="sy-user-avatar-fallback" aria-hidden="true">'+esc((person(r)||'ک').trim().charAt(0))+'</span>')+'<span>'+esc(person(r))+'</span></td><td>'+esc(r.username||'—')+'</td><td dir="ltr">'+esc(persianDigits(r.mobile||'—'))+'</td><td dir="ltr">'+esc(persianDigits(r.national_code||'—'))+'</td><td>'+esc(r.roles||r.role_names||'—')+'</td><td>'+(Number(r.is_active)?'فعال':'غیرفعال')+'</td><td class="sy-user-row-actions">'+
   (manage?'<button type="button" data-edit-user="'+Number(r.id)+'">ویرایش</button><button type="button" data-user-password="'+Number(r.id)+'">تغییر رمز</button>'+
   (global&&Number(r.id)!==selfId?'<button type="button" class="sy-user-delete" data-user-delete="'+Number(r.id)+'">حذف</button>':''):'—')+
   '</td></tr>').join(''):'<tr><td colspan="7" class="sy-users-no-results">کاربری مطابق عبارت جستجو پیدا نشد.</td></tr>';
  tbody.querySelectorAll('.sy-user-avatar-sm').forEach(img=>img.addEventListener('error',()=>{const name=img.closest('.sy-user-cell')?.querySelector('span:last-child')?.textContent||'ک';const fallback=document.createElement('span');fallback.className='sy-user-avatar-fallback';fallback.setAttribute('aria-hidden','true');fallback.textContent=name.trim().charAt(0)||'ک';img.replaceWith(fallback);},{once:true}));
  tbody.querySelectorAll('[data-edit-user]').forEach(b=>b.onclick=()=>userEditor(rows.find(r=>String(r.id)===b.dataset.editUser),global));
  tbody.querySelectorAll('[data-user-password]').forEach(b=>b.onclick=()=>passwordDialog(rows.find(r=>String(r.id)===b.dataset.userPassword),global));
  tbody.querySelectorAll('[data-user-delete]').forEach(b=>b.onclick=()=>confirmGlobalUserDelete(rows.find(r=>String(r.id)===b.dataset.userDelete)));
 };
 input.addEventListener('input',render);
 box.querySelector('[data-new-user]')?.addEventListener('click',()=>userEditor(null,false));
 render();
}
function confirmGlobalUserDelete(user){
 if(!user||!session.roles?.includes('super_admin')||Number(user.id)===Number(session.user.id))return;
 dialog('حذف کاربر سامانه','<form id="syDeleteUserForm" class="sy-user-delete-confirm">'+
  '<p>کاربر <strong>'+esc(person(user))+'</strong> با نام کاربری <strong dir="ltr">'+esc(user.username)+'</strong> از فهرست کاربران حذف و دسترسی او بلافاصله قطع خواهد شد.</p>'+
  '<p>اطلاعات مالی، قراردادها و سوابق ثبت‌شده توسط این حساب برای حفظ تاریخچه سامانه باقی می‌مانند. این عملیات از طریق این پنل قابل بازگشت نیست.</p>'+
  '<label>برای تأیید، نام کاربری را دقیقاً وارد کنید<input name="confirmation" autocomplete="off" required placeholder="'+esc(user.username)+'"></label>'+
  '<div class="sy-user-delete-actions"><button type="button" data-cancel-delete>انصراف</button><button type="submit" class="sy-user-delete" disabled data-confirm-delete>حذف نهایی کاربر</button></div></form>');
 const form=$('syDeleteUserForm'),value=form.elements.confirmation,submit=form.querySelector('[data-confirm-delete]');
 form.querySelector('[data-cancel-delete]').onclick=closeDialog;
 value.oninput=()=>{submit.disabled=value.value.trim()!==String(user.username);};
 form.onsubmit=async event=>{
  event.preventDefault();
  if(value.value.trim()!==String(user.username))return;
  submit.disabled=true;
  const error=$('formError');error.hidden=true;
  try{
   const result=await api('/api/v1/admin/users/'+Number(user.id),{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});
   if(!result?.deleted)throw new Error('تأیید حذف کاربر از سرور دریافت نشد.');
   closeDialog();notify('کاربر حذف شد و نشست‌های فعال او بسته شدند.');
   await companiesPage();
  }catch(e){
   if(error){error.textContent=e.message;error.hidden=false;error.focus();}
   submit.disabled=false;
  }
 };
}

function syUserPhotoUrl(id,global=false){
 return global?'/api/v1/admin/users/'+encodeURIComponent(id)+'/photo':scoped('/api/v1/users/'+encodeURIComponent(id)+'/photo');
}
function syPhotoFields(data={}){
 const profile=data&&typeof data==='object'?data:{};
 const value=(snake,camel)=>esc(profile[snake]??profile[camel]??'');
 return '<div class="sy-user-personal-fields">'+
 '<label>شماره همراه<input name="mobile" inputmode="tel" maxlength="11" autocomplete="tel" placeholder="۰۹xxxxxxxxx" value="'+value('mobile','mobile')+'"></label>'+
 '<label>کد ملی<input name="nationalCode" inputmode="numeric" maxlength="10" value="'+value('national_code','nationalCode')+'"></label>'+
 '<label>ایمیل<input name="email" type="email" autocomplete="email" maxlength="190" value="'+value('email','email')+'"></label>'+
 '<label>کد پستی<input name="postalCode" inputmode="numeric" maxlength="10" value="'+value('postal_code','postalCode')+'"></label>'+
 '<label>شماره اضطراری<input name="emergencyPhone" inputmode="tel" maxlength="11" value="'+value('emergency_phone','emergencyPhone')+'"></label>'+
 '<label class="sy-user-address-field">نشانی<textarea name="address" maxlength="1000" autocomplete="street-address">'+value('address','address')+'</textarea></label></div>';
}
function syPhotoWidget(row,url){
 const name=[row?.first_name||row?.firstName,row?.last_name||row?.lastName].filter(Boolean).join(' ')||row?.username||'کاربر';
 return '<div class="sy-user-photo-editor"><div class="sy-profile-avatar">'+
 (url&&Number(row?.has_photo)?'<img alt="تصویر '+esc(name)+'" src="'+esc(url)+'" class="sy-profile-avatar-img">':'<div class="sy-profile-avatar-fallback">'+esc(name.trim().charAt(0)||'ک')+'</div>')+
 '</div><div><h4>تصویر پروفایل</h4><p>تصویر JPG، PNG یا WebP تا ۵ مگابایت. تصویر به شکل مربعی ذخیره می‌شود.</p>'+
 '<label class="sy-user-photo-button">انتخاب یا تغییر تصویر<input name="photo" type="file" accept="image/jpeg,image/png,image/webp"></label>'+
 (url&&Number(row?.has_photo)?'<button type="button" data-remove-photo class="sy-user-remove-photo">حذف عکس</button>':'')+'</div></div>';
}
function syBindPhoto(form){
 const img=form.querySelector('.sy-profile-avatar-img');
 if(img)img.onerror=()=>{
  const fallback=document.createElement('div');fallback.className='sy-profile-avatar-fallback';fallback.textContent=(form.elements.firstName?.value||form.elements.username?.value||'ک').trim().charAt(0)||'ک';
  img.replaceWith(fallback);
 };
 form.elements.photo?.addEventListener('change',()=>{
  const file=form.elements.photo.files?.[0];if(!file)return;
  if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>5*1024*1024){notify('تصویر باید JPG، PNG یا WebP و حداکثر ۵ مگابایت باشد.',true);form.elements.photo.value='';return;}
  const reader=new FileReader();
  reader.onload=()=>{
   if(!form.isConnected||!reader.result)return;
   const holder=form.querySelector('.sy-profile-avatar');if(!holder)return;
   const preview=document.createElement('img');preview.className='sy-profile-avatar-img';preview.alt='پیش‌نمایش عکس';
   preview.onerror=()=>{preview.replaceWith(Object.assign(document.createElement('div'),{className:'sy-profile-avatar-fallback',textContent:'ک'}));};
   preview.src=String(reader.result);holder.replaceChildren(preview);
  };
  reader.onerror=()=>notify('پیش‌نمایش تصویر قابل خواندن نیست.',true);
  reader.readAsDataURL(file);
 });
}
async function syUploadUserPhoto(url,file){
 if(!file||!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>5*1024*1024)throw Error('تصویر باید JPG، PNG یا WebP و حداکثر ۵ مگابایت باشد.');
 const body=new FormData();body.append('photo',file);
 const json=await window.ServiceYarLoading.upload(url,body,{headers:{'X-CSRF-Token':session.csrfToken}});
 return json.data;
}
async function syRemoveUserPhoto(url){
 const resp=await fetch(url,{method:'DELETE',credentials:'same-origin',headers:{'X-CSRF-Token':session.csrfToken,'Accept':'application/json'}});
 const json=await resp.json().catch(()=>({}));
 if(!resp.ok||!json.ok)throw Error(json.error?.message||'حذف تصویر ناموفق بود.');
 return json.data;
}
async function ownProfileDialog(){
 try{
  const self=await api('/api/v1/auth/profile'),url='/api/v1/auth/profile/photo';
  const html='<form id="syOwnProfileForm" class="sy-professional-user-form">'+syPhotoWidget(self,url)+
   '<section class="sy-user-profile-sections"><h4>اطلاعات حساب</h4><div class="form-grid">'+
   '<label>نام کاربری<input value="'+esc(self.username)+'" disabled></label>'+
   '<label>نام<input name="firstName" maxlength="100" required value="'+esc(self.first_name)+'"></label>'+
   '<label>نام خانوادگی<input name="lastName" maxlength="120" value="'+esc(self.last_name)+'"></label></div>'+
   '<h4>اطلاعات هویتی و تماس</h4>'+syPhotoFields(self)+'</section>'+
   '<button class="primary" type="submit">ذخیره پروفایل</button></form>';
  dialog('پروفایل و اطلاعات شخصی',html);
  const form=$('syOwnProfileForm');syBindPhoto(form);
  form.querySelector('[data-remove-photo]')?.addEventListener('click',async()=>{
   if(!confirm('تصویر پروفایل حذف شود؟'))return;
   try{await syRemoveUserPhoto(url);form.querySelector('.sy-profile-avatar').innerHTML='<div class="sy-profile-avatar-fallback">ک</div>';form.elements.photo.value='';notify('تصویر حذف شد.');}
   catch(e){notify(e.message,true);}
  });
  form.onsubmit=async e=>{
   e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;
   try{
    const data=Object.fromEntries(new FormData(form));delete data.photo;
    const saved=await write('/api/v1/auth/profile',data,'PUT');
    let photoError=null;
    if(form.elements.photo.files?.[0])try{await syUploadUserPhoto(url,form.elements.photo.files[0]);}catch(e){photoError=e;}
    session.user.firstName=saved.firstName;session.user.lastName=saved.lastName;
    closeDialog();renderNav();
    notify(photoError?'اطلاعات ذخیره شد؛ عکس ذخیره نشد: '+photoError.message:'پروفایل ذخیره شد.',Boolean(photoError));
   }catch(e){const box=$('formError');if(box){box.textContent=e.message;box.hidden=false;}button.disabled=false;}
  };
 }catch(e){notify('دریافت پروفایل ناموفق بود: '+e.message,true);}
}
function passwordDialog(row,global=false,self=Number(row.id)===Number(session.user.id)){
 dialog('تغییر رمز '+row.username,`<form id="changePasswordForm"><p class="muted">پس از تغییر رمز، همه نشست‌های قبلی این کاربر بسته می‌شوند.</p>${self?'<label>رمز فعلی<input name="currentPassword" type="password" autocomplete="current-password" required></label>':''}<label>رمز جدید<input name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></label><label>تکرار رمز جدید<input name="passwordConfirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></label><button class="primary">ذخیره رمز جدید</button></form>`);
 $('changePasswordForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const url=self?'/api/v1/auth/password':(global?'/api/v1/admin/users/':'/api/v1/users/')+row.id+'/password';const d=await write(url,Object.fromEntries(new FormData(e.target)));e.target.reset();closeDialog();if(d.requiresLogin)location.reload();else notify('رمز تغییر کرد و نشست‌های قبلی بسته شدند.');}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
}
function userEditor(row,global){
 const roles=[['operator','اپراتور']];
 if(global||session.roles?.includes('super_admin'))roles.push(['agency_admin','مدیر شرکت']);
 const self=Number(row?.id)===Number(session.user.id);
 if(global)roles.push(['super_admin','مدیر کل']);
 const url=row?.id?syUserPhotoUrl(row.id,global):'';
 const roleOptions=roles.map(([k,t])=>'<option value="'+k+'">'+t+'</option>').join('');
 const controls=!self?'<label>نقش<select name="role"><option value="">حفظ نقش فعلی</option>'+roleOptions+'</select></label>'+
  '<label>وضعیت<select name="isActive"><option value="1">فعال</option><option value="0" '+(Number(row?.is_active)===0?'selected':'')+'>غیرفعال</option></select></label>':'';
 const html='<form id="userEditor" class="sy-professional-user-form">'+syPhotoWidget(row,url)+
  '<section class="sy-user-profile-sections"><h4>اطلاعات حساب</h4><div class="form-grid">'+
  '<label>نام کاربری<input name="username" required minlength="3" maxlength="100" value="'+esc(row?.username)+'"></label>'+
  '<label>نام<input name="firstName" required maxlength="100" value="'+esc(row?.first_name)+'"></label>'+
  '<label>نام خانوادگی<input name="lastName" maxlength="120" value="'+esc(row?.last_name)+'"></label>'+
  (!row?'<label>رمز اولیه<input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>':'')+
  controls+'</div><h4>مشخصات هویتی و تماس</h4>'+syPhotoFields(row)+'</section>'+
  '<button class="primary" type="submit">ذخیره اطلاعات کاربر</button></form>';
 dialog(row?'ویرایش اطلاعات کاربر':'ثبت کاربر جدید',html);
 const form=$('userEditor');syBindPhoto(form);
 if(!row&&form.elements.role)form.elements.role.value='operator';
 form.querySelector('[data-remove-photo]')?.addEventListener('click',async()=>{
  if(!confirm('تصویر این کاربر حذف شود؟'))return;
  try{
   await syRemoveUserPhoto(url);form.querySelector('.sy-profile-avatar').innerHTML='<div class="sy-profile-avatar-fallback">ک</div>';
   form.elements.photo.value='';notify('تصویر کاربر حذف شد.');
  }catch(e){notify(e.message,true);}
 });
 form.onsubmit=async e=>{
  e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;
  try{
   const body=Object.fromEntries(new FormData(form));delete body.photo;
   if(!body.role)delete body.role;
   const saved=await write((global?'/api/v1/admin/users':'/api/v1/users')+(row?'/'+row.id:''),body,row?'PUT':'POST');
   let photoError=null;
   if(form.elements.photo.files?.[0]){
    try{await syUploadUserPhoto(syUserPhotoUrl(saved.id||row?.id,global),form.elements.photo.files[0]);}
    catch(e){photoError=e;}
   }
   closeDialog();
   notify(photoError?'کاربر ذخیره شد اما عکس بارگذاری نشد: '+photoError.message:'اطلاعات کاربر ذخیره شد.',Boolean(photoError));
   if(global)await companiesPage();else await settingsPage();
  }catch(e){const box=$('formError');if(box){box.textContent=e.message;box.hidden=false;}button.disabled=false;}
 };
}
async function taxiOrganizationAdminPanel(container,token){
 const panel=document.createElement('section');
 panel.id='syAdminTaxiTariffs';panel.className='panel sy-super-admin-card sy-taxi-settings';
 panel.innerHTML=`<header class="sy-settings-section-head"><div><span class="sy-operation-kicker">مدیریت نرخ‌نامه سازمانی</span><h2>محاسبه سرویس بر اساس سازمان تاکسیرانی</h2><p>تعرفه سال تحصیلی ۱۴۰۵–۱۴۰۶ از فایل نرخ‌نامه سازمان بارگذاری شده است. مقادیر هر ناحیه و کلاس خودرو مستقل و قابل ویرایش‌اند.</p></div></header>
 <form id="syTaxiTariffSelector" class="sy-taxi-tariff-selector">
  <label>ناحیه آموزش‌وپرورش<select name="district">${[1,2,3,4,5,6,7,8].map(n=>`<option value="${n}">${n===8?'تبادکان':'ناحیه '+number(n)}</option>`).join('')}</select></label>
  <label>کلاس خودرو<select name="vehicleClass"><option value="economy">پراید، آردی و روآ</option><option value="standard">سمند، اطلس، کوئیک، تیبا و مشابه</option><option value="van">ون و مینی‌بوس</option></select></label>
  <button type="submit" class="primary">نمایش تعرفه‌ها</button>
 </form>
 <p class="sy-taxi-tariff-hint">مبالغ به ریال هستند. مبلغ اولیا و مبلغ پایه راننده هرکدام جداگانه ذخیره می‌شوند؛ تغییر نرخ‌نامه، اسناد مالی گذشته را بازنویسی نمی‌کند.</p>
 <div id="syTaxiTariffTable" aria-live="polite">در حال دریافت نرخ‌های مصوب…</div>
 <div class="sy-taxi-tariff-actions"><button type="button" class="primary" id="syTaxiTariffSave" disabled>ذخیره نرخ‌های این ناحیه و کلاس</button><span id="syTaxiTariffStatus" role="status" aria-live="polite"></span></div>`;
 container.after(panel);
 const migrationPanel=document.createElement('details');migrationPanel.className='sy-taxi-legacy-reprice';migrationPanel.innerHTML='<summary>اصلاح نرخ سرویس‌های قبلی بر اساس نرخ‌نامه سازمان</summary><p>یک شرکت را مشخص کنید و پیش‌نمایش بگیرید. این عملیات شهریه دانش‌آموزان و جمع سرویس‌ها را اصلاح می‌کند، ولی دریافت‌ها، پرداخت‌ها و اسناد قطعی را تغییر نمی‌دهد. نسخه قبلی مقادیر برای ممیزی ذخیره می‌شود.</p><label>شرکت موردنظر<select data-reprice-agency><option value="">انتخاب شرکت</option></select></label><div class="member-actions"><button type="button" data-reprice-preview>بررسی و پیش‌نمایش</button><button type="button" class="primary" data-reprice-apply disabled>تأیید و اعمال اصلاحات</button></div><div data-reprice-output role="status" aria-live="polite"></div>';
 panel.append(migrationPanel);
 const allPanel=document.createElement('details');allPanel.className='sy-taxi-legacy-reprice';
 allPanel.innerHTML='<summary>اصلاح تمام سرویس‌های تمام شرکت‌های سامانه</summary><p>سرویس‌های دارای نرخ معتبر مستقل از سوابق ناقص محاسبه می‌شوند. سرویس‌های بدون دانش‌آموز یا دارای اطلاعات تعرفه ناقص تغییر مبلغ نمی‌کنند و علت هر مورد گزارش می‌شود؛ فاکتورهای بدهی مرتبط همگام‌سازی خواهند شد.</p><div class="member-actions"><button type="button" data-global-preview>پیش‌نمایش کل سامانه</button><button type="button" data-global-apply class="primary" disabled>اعمال اصلاحات کل سامانه</button></div><div data-global-status role="status" aria-live="polite"></div>';
 migrationPanel.after(allPanel);
 // Keep the system-wide repair action visible above the long tariff grid.
 panel.querySelector('.sy-settings-section-head')?.after(allPanel);
 allPanel.open=true;
 const allPreview=allPanel.querySelector('[data-global-preview]'),allApply=allPanel.querySelector('[data-global-apply]'),allStatus=allPanel.querySelector('[data-global-status]');
 const tariffIssueLabel=e=>{
  if(e.studentId)return 'دانش‌آموز: '+(e.studentName||'نام ثبت نشده');
  if(e.serviceId)return 'سرویس: '+(e.serviceTitle||'بدون عنوان')+(e.serviceCode?' · '+e.serviceCode:'');
  if(e.schoolId)return 'مدرسه: '+(e.schoolName||'نام ثبت نشده');
  return 'رکورد نامشخص';
 };
 const tariffIssueRows=errors=>(errors||[]).map(e=>[
  tariffIssueLabel(e),
  e.schoolName||'—',
  (e.serviceNames?.length?e.serviceNames.join('، '):e.serviceTitle?(e.serviceTitle+(e.serviceCode?' · '+e.serviceCode:'')):'—'),
  e.studentNames?.length?e.studentNames.join('، '):e.grade?'پایه: '+e.grade:'—',
  e.studentId?'کلاس: '+(e.vehicleClass||'نامشخص')+' | مسافت: '+(e.distanceKm??'نامشخص')+' کیلومتر':e.driverName?'راننده: '+e.driverName:'—',
  e.reason||'علت نامشخص'
 ]);
 const tariffIssueTable=errors=>(errors?.length?simpleTable(['رکورد دارای خطا','مدرسه','سرویس','دانش‌آموزان / پایه','مشخصات نرخ / راننده','شرح مشکل'],tariffIssueRows(errors)):'<p class="muted">خطایی برای این شرکت گزارش نشده است.</p>');
 const tariffAgenciesReport=agencies=>agencies.map(a=>'<details class="sy-taxi-reprice-company" '+((a.errors?.length||a.error)?'open':'')+'><summary><strong>'+esc(a.agencyName||'شرکت بدون نام')+'</strong> <span class="muted">(شناسه '+number(a.agencyId)+')</span> | سرویس دارای تغییر واقعی: '+number(a.applied?a.servicesUpdated||0:a.servicesToChange||0)+' | بدون تغییر: '+number(a.applied?a.servicesUnchanged||0:a.servicesAlreadyCurrent||0)+' | نیازمند بررسی: '+number(a.servicesSkipped||0)+' | خطا: '+number(a.errors?.length||Number(Boolean(a.error)))+'</summary>'+(a.error?'<p role="alert">'+esc(a.error)+'</p>':'')+(a.serviceChanges?.length?'<details><summary>اختلاف مبلغ سرویس‌ها ('+number(a.serviceChanges.length)+')</summary>'+simpleTable(['شناسه سرویس','مبلغ قبلی (ریال)','مبلغ جدید (ریال)','اختلاف (ریال)'],a.serviceChanges.map(c=>[number(c.serviceId),number(c.oldFee),number(c.newFee),number(c.newFee-c.oldFee)]))+'</details>':'')+tariffIssueTable(a.errors||[])+'</details>').join('');
 allPreview.onclick=async()=>{
  allApply.disabled=true;allStatus.textContent='در حال بررسی شرکت‌ها…';
  try{
   const data=await api('/api/v1/admin/taxi-tariffs/reprice-all');
   if(!allStatus.isConnected)return;
   const summary=data.summary;
   allStatus.innerHTML='<p>شرکت‌ها: '+number(summary.agencies)+' | کل سرویس‌ها: '+number(summary.services)+' | مبلغ نیازمند تغییر: '+number(summary.servicesAmountToChange)+' | فقط تغییر مبنای تعرفه: '+number(summary.servicesSourceOnlyToChange)+' | مبالغ از قبل صحیح: '+number(summary.servicesAlreadyCurrent)+' | نیازمند بررسی: '+number(summary.skipped)+' | کدهای خالی: '+number(summary.missingCodes)+'</p><div class="sy-taxi-reprice-diagnostics">'+tariffAgenciesReport(data.agencies)+'</div>';
   allApply.disabled=!(summary.servicesToChange>0||summary.studentsToChange>0||summary.missingCodes>0);
  }catch(e){allStatus.textContent=e.message;}
 };
 allApply.onclick=async()=>{
  if(!window.confirm('اصلاح تعرفه سازمانی برای تمام شرکت‌های سامانه و تکمیل کد سرویس‌ها انجام شود؟ پیش از اجرا از دیتابیس نسخه پشتیبان تهیه کنید.'))return;
  allApply.disabled=true;allStatus.textContent='در حال اجرای اصلاح گروهی…';
  try{
   const data=await write('/api/v1/admin/taxi-tariffs/reprice-all',{confirm:'REPRICE_ALL_AGENCIES'});
   if(!allStatus.isConnected)return;
   const summary=data.summary;
   allStatus.innerHTML='<p role="status">سرویس‌های دارای تغییر مبلغ: '+number(summary.serviceAmountsChanged)+' | فقط تغییر مبنا: '+number(summary.serviceSourcesOnly)+' | کل سرویس‌های بروزرسانی‌شده: '+number(summary.recalculated)+' | سرویس‌های تأییدشده از دیتابیس: '+number(summary.servicesVerified)+' | دانش‌آموزان تغییرکرده: '+number(summary.studentsUpdated)+' | دانش‌آموزان بدون تغییر: '+number(summary.studentsUnchanged)+' | فاکتورهای بررسی و همگام‌شده: '+number(summary.invoicesSynced)+' | سرویس‌های نیازمند بررسی: '+number(summary.skipped)+' | کدهای تکمیل‌شده: '+number(summary.codesFilled)+' | شرکت‌های دارای خطا: '+number(summary.blocked)+'</p>'+(summary.blocked?'<p role="alert">اصلاحات ناقص است؛ جزئیات خطاهای هر شرکت را در پایین بررسی کنید.</p>':'')+'<div class="sy-taxi-reprice-diagnostics">'+tariffAgenciesReport(data.agencies)+'</div>';
   lookupCache={};
   // A second read-only calculation establishes whether outstanding fee differences remain.
   try{
    const check=await api('/api/v1/admin/taxi-tariffs/reprice-all');
    if(allStatus.isConnected)allStatus.insertAdjacentHTML('afterbegin','<p role="status">کنترل مجدد پس از ثبت: '+number(check.summary.servicesToChange)+' سرویس همچنان دارای اختلاف مبلغ/مبنای تعرفه هستند؛ '+number(check.summary.studentsToChange)+' دانش‌آموز نیازمند اصلاح است.</p>');
   }catch(error){if(allStatus.isConnected)allStatus.insertAdjacentHTML('afterbegin','<p role="alert">بازبینی پس از ثبت ناموفق بود: '+esc(error.message)+'</p>');}
  }catch(e){allStatus.textContent=e.message;}
 };
 const previewBtn=migrationPanel.querySelector('[data-reprice-preview]'),applyBtn=migrationPanel.querySelector('[data-reprice-apply]'),output=migrationPanel.querySelector('[data-reprice-output]');
 const agencySelect=migrationPanel.querySelector('[data-reprice-agency]');
 const selectedRepriceUrl=()=>{
  const id=Number(agencySelect.value);
  if(!Number.isSafeInteger(id)||id<1)throw new Error('ابتدا شرکت موردنظر را انتخاب کنید.');
  return '/api/v1/admin/taxi-tariffs/reprice-legacy?agencyId='+encodeURIComponent(id);
 };
 agencySelect.addEventListener('change',()=>{applyBtn.disabled=true;output.textContent='برای شرکت انتخاب‌شده، پیش‌نمایش تازه دریافت کنید.';});
 previewBtn.disabled=true;
 api('/api/v1/admin/overview').then(data=>{
  if(!agencySelect.isConnected)return;
  const companies=(data.agencies||[]).filter(a=>Number(a.is_active)===1);
  agencySelect.replaceChildren(new Option('انتخاب شرکت',''),...companies.map(a=>new Option(a.name+' · '+number(a.id),String(a.id))));
  previewBtn.disabled=false;
 }).catch(error=>{if(agencySelect.isConnected)output.textContent='دریافت فهرست شرکت‌ها ناموفق بود: '+error.message;});
 previewBtn.onclick=async()=>{
  applyBtn.disabled=true;output.textContent='در حال بررسی سوابق…';
  try{
   const data=await api(selectedRepriceUrl());
   if(!output.isConnected)return;
   output.innerHTML='<p>کل سرویس‌ها: '+number(data.servicesChecked)+' | تغییر مبلغ: '+number(data.servicesAmountToChange)+' | فقط تغییر مبنا: '+number(data.servicesSourceOnlyToChange)+' | بدون تغییر: '+number(data.servicesAlreadyCurrent)+' | سرویس‌های بدون کد: '+number(data.servicesWithoutCode)+' | دانش‌آموز نیازمند اصلاح: '+number(data.studentsToChange)+' | خطای محاسبه: '+number(data.errors.length)+' | اختلاف جمع ماهانه: '+number(data.monthlyDifference)+' ریال</p>'+tariffIssueTable(data.errors);
   applyBtn.disabled=!data.servicesChecked;
  }catch(e){output.textContent=e.message;}
 };
 applyBtn.onclick=async()=>{
  if(!window.confirm('آیا اصلاح گروهی شهریه تمام سرویس‌های قدیمی شرکت انتخاب‌شده را تأیید می‌کنید؟ اسناد مالی گذشته بدون تغییر باقی می‌مانند.'))return;
  applyBtn.disabled=true;output.textContent='در حال ثبت اصلاحات همراه با سابقه ممیزی…';
  try{
   const data=await api(selectedRepriceUrl(),{method:'POST',headers:{'X-CSRF-Token':session.csrfToken,'Content-Type':'application/json'},body:JSON.stringify({confirm:'REPRICE_LEGACY_TAXI'})});
   output.innerHTML='<p>'+esc(data.applied?'سرویس‌های اصلاح‌شده: '+number(data.servicesUpdated)+' | دانش‌آموزان اصلاح‌شده: '+number(data.studentsUpdated)+' | شناسه ممیزی: '+data.runId:'اعمال کامل انجام نشد؛ تعداد خطا: '+number(data.errors.length))+'</p>'+tariffIssueTable(data.errors);
  }catch(e){output.textContent='اصلاح اعمال نشد: '+e.message;}
 };
 const form=panel.querySelector('#syTaxiTariffSelector'),table=panel.querySelector('#syTaxiTariffTable'),status=panel.querySelector('#syTaxiTariffStatus'),save=panel.querySelector('#syTaxiTariffSave');
 let snapshot=null,revision=0;
 const load=async()=>{
  const version=++revision;snapshot=null;save.disabled=true;status.textContent='';
  table.textContent='در حال بارگذاری جدول تعرفه…';
  const district=Number(form.elements.district.value),vehicleClass=form.elements.vehicleClass.value;
  try{
   const data=await api('/api/v1/admin/taxi-tariffs?district='+district+'&vehicleClass='+encodeURIComponent(vehicleClass));
   if(token!==pageToken||version!==revision||!panel.isConnected)return;
   snapshot=data;table.innerHTML=`<div class="table-wrap sy-taxi-rates-table-wrap" tabindex="0"><table class="sy-taxi-rates-table">
    <thead><tr><th>مسافت رفت‌وبرگشت (کیلومتر)</th><th>مبنای ماهانه راننده (ریال)</th><th>مبلغ ماهانه اولیا (ریال)</th><th>وضعیت نرخ</th></tr></thead>
    <tbody>${data.rows.map(r=>`<tr data-distance="${Number(r.distanceKm)}"><td>${number(r.distanceKm)}</td>
      <td><input type="number" name="driverMonthly" min="0" max="999999999999" step="1" inputmode="numeric" aria-label="مبلغ راننده در ${number(r.distanceKm)} کیلومتر" value="${Number(r.driverMonthly)}"></td>
      <td><input type="number" name="parentMonthly" min="0" max="999999999999" step="1" inputmode="numeric" aria-label="مبلغ اولیا در ${number(r.distanceKm)} کیلومتر" value="${Number(r.parentMonthly)}"></td>
      <td>${r.overridden?'ویرایش‌شده':'نرخ پیش‌فرض'}</td></tr>`).join('')}</tbody></table></div>`;
   status.textContent='ضریب ترافیک این ناحیه در نرخ‌های پیش‌فرض: '+number(data.trafficPercent)+'٪';
   save.disabled=false;
  }catch(error){if(version===revision&&panel.isConnected){table.textContent='امکان نمایش نرخ‌ها وجود ندارد.';status.textContent=error.message;}}
 };
 form.onsubmit=e=>{e.preventDefault();load();};
 save.onclick=async()=>{
  if(!snapshot)return;
  let rows;
  try{
   rows=[...table.querySelectorAll('tbody tr')].map(tr=>{
    const driver=tr.querySelector('[name=driverMonthly]'),parent=tr.querySelector('[name=parentMonthly]');
    for(const input of [driver,parent])if(!input.reportValidity()||!/^\d+$/.test(input.value))throw new Error('مبلغ هر ردیف باید عدد صحیح ریالی معتبر باشد.');
    return {distanceKm:Number(tr.dataset.distance),driverMonthly:Number(driver.value),parentMonthly:Number(parent.value)};
   });
  }catch(error){status.textContent=error.message;return;}
  save.disabled=true;status.textContent='در حال ذخیره نرخ‌های جدید…';
  try{
   await write('/api/v1/admin/taxi-tariffs',{district:Number(form.elements.district.value),vehicleClass:form.elements.vehicleClass.value,rows},'PUT');
   await load();
   status.textContent='تعرفه‌ها ذخیره شدند؛ مبلغ جدید در محاسبه بعدی اعمال می‌شود.';
  }catch(error){status.textContent=error.message;save.disabled=false;}
 };
 await load();
}
const originalCompaniesPage=companiesPage;
companiesPage=async function(){
 const token=pageToken;
 await originalCompaniesPage();
 if(token!==pageToken||!session?.roles?.includes('super_admin'))return;
 try{
  const [rows,platform]=await Promise.all([api('/api/v1/admin/users'),api('/api/v1/admin/platform-settings')]);
  if(token!==pageToken)return;
  const section=[...$('content').querySelectorAll('section')].find(s=>s.querySelector('h2')?.textContent==='کاربران سامانه');
  if(section)userManagementTable(section.querySelector('.table-wrap'),rows,true);
  const adminPanel=document.createElement('section');adminPanel.className='panel sy-platform-settings';
  adminPanel.innerHTML='<header class="sy-settings-section-head"><div><h2>تنظیمات سامانه · سرویس‌دهنده‌ها</h2><p>این کلیدها در سطح سامانه تعریف می‌شوند و در تنظیمات شرکت‌ها نمایش داده نمی‌شوند.</p></div></header>'+
   '<div class="sy-platform-status"><span>کلید سرویس نشان: '+(platform.neshanServiceConfigured?'تنظیم شده':'تنظیم نشده')+'</span><span>کلید وب نشان: '+(platform.neshanWebConfigured?'تنظیم شده':'تنظیم نشده')+'</span><span>پیامک نگین: '+(platform.smsConfigured?'فعال':'نیازمند تنظیم سرور')+'</span><span>درگاه بله: '+(platform.baleConfigured?'فعال':'نیازمند تنظیم سرور')+'</span></div>'+
   '<form id="platformSettingsForm" class="form-grid sy-settings-grid"><label>کلید سرویس نشان<input name="neshan_service_key" type="password" autocomplete="off" placeholder="برای حفظ کلید فعلی خالی بگذارید"></label><label>کلید وب نشان<input name="neshan_web_key" type="password" autocomplete="off" placeholder="برای حفظ کلید فعلی خالی بگذارید"></label><div class="sy-platform-explanation">اطلاعات محرمانه پیامک نگین و درگاه بله در تنظیمات امن سرور نگهداری می‌شوند؛ وضعیت اتصال از این بخش قابل بررسی است.</div><button class="primary" type="submit">ذخیره کلیدهای نشان</button><p role="status" id="platformSaveStatus"></p></form>';
  adminPanel.classList.add('sy-admin-service-card');
  adminPanel.querySelector('.sy-platform-status')?.setAttribute('role','list');
  adminPanel.querySelectorAll('.sy-platform-status span').forEach(item=>item.setAttribute('role','listitem'));
  const platformForm=adminPanel.querySelector('#platformSettingsForm');
  if(platformForm){
   platformForm.classList.add('sy-admin-service-form');
   const note=document.createElement('p');note.className='sy-admin-security-note';
   note.textContent='کلیدهای قبلی حفظ می‌شوند؛ فقط برای جایگزینی، کلید جدید وارد کنید.';
   platformForm.prepend(note);
   platformForm.querySelectorAll('label').forEach(label=>{
    label.classList.add('sy-admin-secret-field');
    const input=label.querySelector('input[type=password]');if(!input)return;
    const button=document.createElement('button');button.type='button';button.className='sy-admin-show-secret';button.textContent='نمایش';
    input.after(button);
    button.onclick=()=>{const visible=input.type==='password';input.type=visible?'text':'password';button.textContent=visible?'پنهان':'نمایش';};
   });
  }
  $('content').classList.add('sy-super-admin-workspace');
  $('content').querySelectorAll('section.panel').forEach(panel=>panel.classList.add('sy-super-admin-card'));
  $('content').prepend(adminPanel);
  const workspace=$('content');
  const agencySection=workspace.querySelector('.sy-admin-agency-panel');
  const userSection=[...workspace.querySelectorAll('section.panel')].find(node=>node.querySelector('h2')?.textContent==='کاربران سامانه');
  if(agencySection)agencySection.id='syAdminAgencies';
  if(userSection){userSection.id='syAdminUsers';userSection.classList.add('sy-admin-users-panel');}
  adminPanel.id='syAdminIntegrations';
  const nav=document.createElement('nav');nav.className='sy-admin-section-nav';nav.setAttribute('aria-label','دسترسی سریع به تنظیمات ادمین کل');
  nav.innerHTML='<a href="#syAdminIntegrations" data-admin-jump="syAdminIntegrations">ارتباطات و سرویس‌ها</a><a href="#syVehicleClassCatalog" data-admin-jump="syVehicleClassCatalog">مدیریت کلاس‌های خودرو</a><a href="#syAdminSepand" data-admin-jump="syAdminSepand">ورود اطلاعات رانندگان و خودروهای سپند</a><a href="#syAdminTaxiTariffs" data-admin-jump="syAdminTaxiTariffs">محاسبه سرویس بر اساس سازمان تاکسیرانی</a><a href="#syAdminAgencies" data-admin-jump="syAdminAgencies">مدیریت شرکت‌ها</a><a href="#syAdminUsers" data-admin-jump="syAdminUsers">کاربران سامانه</a>';
  nav.querySelectorAll('[data-admin-jump]').forEach(link=>link.addEventListener('click',event=>{
   const target=workspace.querySelector('#'+link.dataset.adminJump);if(!target)return;
   event.preventDefault();target.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});
   target.setAttribute('tabindex','-1');target.focus({preventScroll:true});
  }));
  workspace.prepend(nav);
   const panelTasks=[taxiOrganizationAdminPanel(adminPanel,token)];
   if(typeof sepandAdminPanel==="function")panelTasks.push(sepandAdminPanel(adminPanel));
   if(typeof vehicleClassAdminPanel==="function")panelTasks.push(vehicleClassAdminPanel(adminPanel));
   const panelResults=await Promise.allSettled(panelTasks);
   if(token!==pageToken)return;
   panelResults.forEach(result=>{if(result.status==='rejected')notify('آماده‌سازی یکی از بخش‌های مدیریتی ناموفق بود: '+(result.reason?.message||'خطای نامشخص'),true);});

  const form=$('platformSettingsForm');
  form.onsubmit=async e=>{
   e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;
   try{const payload=Object.fromEntries(new FormData(form));await write('/api/v1/admin/platform-settings',payload,'PUT');form.reset();$('platformSaveStatus').textContent='کلیدهای سامانه ذخیره شدند.';}
   catch(err){$('platformSaveStatus').textContent=err.message;}
   finally{button.disabled=false;}
  };
 }catch(e){if(token===pageToken)notify('دریافت تنظیمات سامانه: '+e.message,true);}
};
function financeInput(prefix){return {distanceKm:Math.round(Number($(prefix+'Km').value||0)),days:$(prefix+'Days').value,tripsPerDay:$(prefix+'Trips').value};}
function financeResult(d){return simpleTable(['عنوان','مبلغ · ریال'],[['هزینه سرویس',number(d.monthlyFee)],['سهم ناخالص راننده',number(d.driverGross)],['کسورات راننده',number(d.deductions)],['خالص راننده',number(d.driverNet)],['سهم شرکت پیش از کسورات',number(d.companyShare)]]);}
function financialSettings(setting){
 const defaults={mode:'monthly_distance',baseAmount:0,perKm:0,includedKm:0,minimumAmount:0,vehicleFactor:1,discountPercent:0,driverPercent:100,deductionPercent:0,fixedDeduction:0,nominalDays:22,rounding:1};let saved;try{saved=JSON.parse(setting.finance_rules||'{}');}catch{saved={};}const rules={...defaults,...saved};
 const labels={baseAmount:'مبلغ پایه ماهانه · ریال',perKm:'نرخ هر کیلومتر · ریال',includedKm:'مسافت مشمول مبلغ پایه · کیلومتر',minimumAmount:'حداقل مبلغ ماهانه · ریال',vehicleFactor:'ضریب خودرو',discountPercent:'تخفیف اولیا · درصد',driverPercent:'سهم راننده · درصد',deductionPercent:'کسورات سهم راننده · درصد',fixedDeduction:'کسورات ثابت ماهانه · ریال',nominalDays:'روزهای مبنای ماه',rounding:'گرد کردن هزینه به مضرب · ریال'};
 const section=document.createElement('section');section.className='panel';section.innerHTML=`<h2>قواعد محاسبات مالی</h2><p class="muted">تعرفه هر شرکت را تعیین کنید. تغییر قواعد، مبالغ و اسناد قبلی را تغییر نمی‌دهد؛ نتیجه محاسبه در فرم دانش‌آموز، سرویس و کارکرد راننده با انتخاب شما اعمال می‌شود.</p><form id="financialRulesForm"><fieldset ${can('settings','manage')?'':'disabled'}><div class="form-grid"><label>نحوه محاسبه<select name="mode"><option value="fixed">مبلغ ثابت ماهانه</option><option value="monthly_distance">پایه + نرخ مسافت ماهانه</option><option value="daily_distance">پایه + مسافت × مسیر روزانه × روزهای مبنا</option></select></label>${Object.entries(labels).map(([k,t])=>`<label>${t}<input type="number" name="${k}" min="0" step="${['baseAmount','minimumAmount','fixedDeduction','nominalDays','rounding'].includes(k)?'1':'0.001'}" required value="${esc(rules[k])}"></label>`).join('')}</div></fieldset><p class="muted">ابتدا ضریب خودرو و حداقل مبلغ، سپس نسبت روزهای کارکرد، تخفیف و گردکردن اعمال می‌شود. سهم راننده از هزینه نهایی محاسبه و کسورات از آن کسر می‌شود. کسور ثابت نیز متناسب با روزهای کارکرد است.</p>${can('settings','manage')?'<button class="primary">ذخیره قواعد مالی</button>':''}</form><details open><summary>پیش‌نمایش محاسبه بدون ثبت مالی</summary>${financeInputFields('rulePreview')}<button type="button" id="previewRules">محاسبه آزمایشی</button><div id="rulesResult" aria-live="polite"></div></details>`;$('transportSettings').closest('section').after(section);
 const form=$('financialRulesForm');form.elements.mode.value=rules.mode;$('rulePreviewDays').value=rules.nominalDays;const values=()=>Object.fromEntries([...form.querySelectorAll('[name]')].map(i=>[i.name,i.value]));
 form.oninput=()=>{$('rulesResult').textContent='قواعد تغییر کرده‌اند؛ پیش‌نمایش را دوباره محاسبه کنید.';};form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('button');button.disabled=true;try{await write('/api/v1/settings',{finance_rules:values()},'PUT');notify('قواعد مالی ذخیره شد؛ مبالغ قبلی تغییر نکرد.');}catch(err){notify(err.message,true);}finally{button.disabled=false;}};
 $('previewRules').onclick=async e=>{const button=e.currentTarget;button.disabled=true;try{const d=await write('/api/v1/operations/financial-preview',{rules:values(),input:financeInput('rulePreview')});if(button.isConnected)$('rulesResult').innerHTML=financeResult(d);}catch(err){if(button.isConnected)$('rulesResult').textContent=err.message;}finally{button.disabled=false;}};
}
function attachFinancialCalculator(form,key,id=null,record={}){
 const box=document.createElement('details');box.className='financial-calculator';box.innerHTML=`<summary>محاسبه با قواعد مالی شرکت</summary><p class="muted">مسافت و روزهای مربوط به همین ${key==='students'?'دانش‌آموز':key==='services'?'سرویس':'کارکرد'} را وارد کنید. مبلغ فقط پس از ذخیره فرم ثبت خواهد شد.</p>${financeInputFields('formQuote')}<button type="button" data-calculate>محاسبه و پیش‌نمایش</button><div data-result aria-live="polite"></div><button type="button" data-apply hidden>انتقال نتیجه به فیلدهای فرم</button>`;form.querySelector('.form-grid').after(box);let quote=null,revision=0;
 const invalidate=()=>{revision++;quote=null;box.querySelector('[data-apply]').hidden=true;box.querySelector('[data-result]').textContent='';};box.querySelectorAll('input').forEach(i=>i.oninput=invalidate);
 box.querySelector('[data-calculate]').onclick=async e=>{invalidate();const requestRevision=revision,button=e.currentTarget;button.disabled=true;try{if(key==='services'&&id&&String(record.code||'').startsWith('mehr1405-d')){const members=await api(scoped('/api/v1/services/'+id+'/students'));if(!box.isConnected||revision!==requestRevision)return;if(!Array.isArray(members)||!members.length)throw new Error('دانش‌آموزی برای این سرویس ثبت نشده است.');const missing=members.filter(m=>!Number.isFinite(Number(m.monthly_fee))||Number(m.monthly_fee)<=0);if(missing.length)throw new Error('مبلغ ماهیانه '+number(missing.length)+' دانش‌آموز ثبت نشده است؛ ابتدا فایل مبالغ مهر ۱۴۰۵ را ایمپورت کنید.');const total=members.reduce((sum,m)=>sum+Number(m.monthly_fee),0);quote={monthlyFee:Math.round(total),driverNet:Number(record.agreed_amount||0),imported:true};box.querySelector('[data-result]').innerHTML='<p>جمع هزینه ماهیانه '+number(members.length)+' دانش‌آموز طبق مبالغ ثبت‌شده: '+number(quote.monthlyFee)+' ریال</p><p>مبلغ توافقی راننده از اطلاعات ثبت‌شده سرویس حفظ می‌شود.</p>';box.querySelector('[data-apply]').hidden=false;}else{const d=await write('/api/v1/operations/financial-preview',{input:financeInput('formQuote')});if(!box.isConnected||revision!==requestRevision)return;quote=d;box.querySelector('[data-result]').innerHTML=financeResult(d);box.querySelector('[data-apply]').hidden=false;}}catch(err){if(box.isConnected)box.querySelector('[data-result]').textContent=err.message;}finally{button.disabled=false;}};
 box.querySelector('[data-apply]').onclick=()=>{if(!quote)return;if(key==='driver-work')form.elements.amount.value=quote.driverNet;else form.elements.monthlyFee.value=quote.monthlyFee;if(key==='services'&&!quote.imported)form.elements.agreedAmount.value=quote.driverNet;notify('نتیجه به فرم منتقل شد؛ برای ثبت، فرم را ذخیره کنید.');};
}
async function agencyPaymentMethodSettings(){
 if(!can('ledger'))return;
 const token=pageToken,agency=Number(currentAgency?.id);
 const methods=await api(scoped('/api/v1/finance/payment-methods'));
 if(token!==pageToken||agency!==Number(currentAgency?.id))return;
 const editable=can('settings','manage');
 const panel=document.createElement('section');panel.className='panel sy-settings-panel sy-payment-method-settings';panel.dataset.settingsTab='methods';
 panel.innerHTML='<header class="sy-settings-section-head"><div><h2>روش‌های دریافت و پرداخت شرکت</h2><p>روش‌های فعال در فرم ثبت دریافت و پرداخت همین شرکت نمایش داده می‌شوند. غیرفعال‌کردن یک روش، سوابق قبلی را حذف نمی‌کند.</p></div></header><div id="agencyMethodRows" class="sy-method-settings-rows"></div>'+(editable?'<div class="sy-method-settings-actions"><button type="button" id="addAgencyMethod">افزودن روش جدید</button><button type="button" class="primary" id="saveAgencyMethods">ذخیره روش‌های شرکت</button></div>':'')+'<p id="agencyMethodsStatus" role="status" aria-live="polite"></p>';
 $('transportSettings').closest('section').after(panel);
 panel.hidden=$('content').querySelector('.sy-settings-tabs [aria-selected="true"]')?.dataset.settingsSwitch!=='methods';
 const rows=methods.map(item=>({...item}));
 const render=()=>{
  const host=panel.querySelector('#agencyMethodRows');
  host.innerHTML=rows.map((m,i)=>'<div class="sy-method-setting"><span class="sy-method-code">'+esc(m.code)+'</span><label>عنوان روش<input type="text" data-method-name="'+i+'" value="'+esc(m.name)+'" maxlength="80" required '+(editable?'':'disabled')+'></label><span class="sy-method-state">'+(m.active?'فعال':'غیرفعال')+'</span>'+(editable?'<button type="button" data-method-toggle="'+i+'">'+(m.active?'حذف از فهرست فعال':'فعال‌سازی')+'</button>':'')+'</div>').join('');
  host.querySelectorAll('[data-method-name]').forEach(input=>input.oninput=()=>{rows[Number(input.dataset.methodName)].name=input.value;});
  host.querySelectorAll('[data-method-toggle]').forEach(button=>button.onclick=()=>{rows[Number(button.dataset.methodToggle)].active=!rows[Number(button.dataset.methodToggle)].active;render();});
 };
 render();
 panel.querySelector('#addAgencyMethod')?.addEventListener('click',()=>{
  let n=1;while(rows.some(r=>r.code==='custom_'+n))n++;
  rows.push({code:'custom_'+n,name:'',active:true});render();panel.querySelector('[data-method-name="'+(rows.length-1)+'"]')?.focus();
 });
 panel.querySelector('#saveAgencyMethods')?.addEventListener('click',async event=>{
  const button=event.currentTarget;
  if(rows.some(r=>!r.name.trim())){panel.querySelector('#agencyMethodsStatus').textContent='عنوان تمام روش‌ها را تکمیل کنید.';return;}
  if(!rows.some(r=>r.active)){panel.querySelector('#agencyMethodsStatus').textContent='حداقل یک روش فعال لازم است.';return;}
  button.disabled=true;
  try{
   await write('/api/v1/settings',{finance_payment_methods:rows},'PUT');
   agencyMethodChoices=rows.map(r=>({...r}));
   panel.querySelector('#agencyMethodsStatus').textContent='روش‌های دریافت و پرداخت این شرکت ذخیره شدند.';
  }catch(e){panel.querySelector('#agencyMethodsStatus').textContent=e.message;}
  finally{button.disabled=false;}
 });
}
async function settingsPage(){
 const token=pageToken;const setting=await api(scoped('/api/v1/settings'));if(token!==pageToken)return;
 const fields={company_manager_name:'نام مدیر شرکت',company_ceo_mobile:'تلفن همراه مدیر (داخلی)',company_phone:'تلفن دفتر شرکت',company_address:'نشانی دفتر شرکت',academic_year:'سال تحصیلی',support_phone:'شماره پشتیبانی'};
 const saveLogo=async(form)=>{
  const file=form.querySelector('[name=companyLogo]').files?.[0];
  if(!file)throw new Error('ابتدا تصویر لوگو را انتخاب کنید.');
  if(file.size>1048576||!['image/png','image/jpeg','image/webp'].includes(file.type))throw new Error('تصویر باید PNG، JPEG یا WebP و حداکثر یک مگابایت باشد.');
  const data=new FormData();data.append('file',file);
  const result=await window.ServiceYarLoading.upload(agencyLogoUrl(),data,{headers:{'X-CSRF-Token':session.csrfToken}});
  return result.data;
 };

 const settingsSection=(title,description)=>`<header class="sy-settings-section-head"><div><h2>${title}</h2><p>${description}</p></div></header>`;
 $('content').innerHTML=`<section class="panel sy-settings-panel">${settingsSection('اطلاعات شرکت','مشخصات عمومی و اطلاعات تماس شرکت')}<form id="transportSettings" class="form-grid sy-settings-grid">${Object.entries(fields).map(([k,t])=>`<label>${t}<input name="${k}" value="${esc(setting[k]||'')}" maxlength="500"></label>`).join('')}${can('settings','manage')?'<button class="primary">ذخیره تنظیمات</button>':''}</form><p class="muted">کلیدهای اتصال و سرویس‌دهنده‌ها تنها در تنظیمات سامانه و توسط ادمین کل مدیریت می‌شوند.</p></section>${can('holidays')?'<section class="panel"><h2>تعطیلات</h2><div id="holidayRows"></div><div id="holidayEditor"></div></section>':''}${can('users')?'<section class="panel"><h2>کاربران شرکت</h2><div id="companyUserRows"></div></section>':''}<div id="modal"></div>`;

 const logoPanel=document.createElement('section');logoPanel.className='panel sy-settings-panel sy-agency-logo-settings';
 logoPanel.innerHTML='<header class="sy-settings-section-head"><div><h2>لوگوی اختصاصی شرکت</h2><p>لوگو در داشبورد، سربرگ اسناد و خروجی چاپ و PDF شرکت استفاده می‌شود.</p></div></header><div class="sy-agency-logo-layout">'+agencyLogoImage('نشان شرکت '+(currentAgency?.name||''),100)+'<form id="agencyLogoForm"><label>انتخاب تصویر (PNG، JPG یا WebP؛ حداکثر ۱ مگابایت)<input type="file" name="companyLogo" accept="image/png,image/jpeg,image/webp" '+(can('settings','manage')?'':'disabled')+'></label>'+(can('settings','manage')?'<div class="sy-agency-logo-actions"><button class="primary" type="submit">ثبت لوگو</button><button type="button" id="removeAgencyLogo">حذف لوگوی اختصاصی</button></div>':'')+'<p id="agencyLogoStatus" role="status" aria-live="polite">در صورت ثبت‌نشدن لوگو، نشان پیش‌فرض سرویس‌یار استفاده می‌شود.</p></form></div>';
 $('transportSettings').closest('section').after(logoPanel);

 // Each company controls whether its public contact is the manager mobile (default)
 // or the office landline. The saved choice is applied by the public API.
 const publicPanel=document.createElement('section');
 publicPanel.className='panel sy-settings-panel sy-public-company-settings';
 publicPanel.innerHTML='<header class="sy-settings-section-head"><div><h2>شماره تماس قابل نمایش در نقشه عمومی</h2><p>شماره موبایل مدیر به‌صورت پیش‌فرض نمایش داده می‌شود. برای جایگزینی آن، «تلفن ثابت شرکت» را انتخاب کنید.</p></div></header>'+
 '<form id="syPublicCompanyForm" class="sy-public-contact-form">'+
 '<fieldset class="sy-public-contact-options"><legend>شماره‌ای که بازدیدکنندگان مشاهده می‌کنند</legend>'+
 '<label class="sy-public-contact-option"><input type="radio" name="public_contact_type" value="mobile" checked><span><strong>شماره همراه مدیر (پیش‌فرض)</strong><small id="syContactMobilePreview">در حال دریافت اطلاعات…</small></span></label>'+
 '<label class="sy-public-contact-option"><input type="radio" name="public_contact_type" value="landline"><span><strong>شماره ثابت دفتر شرکت</strong><small id="syContactOfficePreview">در حال دریافت اطلاعات…</small></span></label></fieldset>'+
 '<div class="form-grid sy-settings-grid"><label>نشانی دفتر برای نمایش در نقشه<input name="public_office_address" maxlength="450" placeholder="در صورت نیاز نشانی عمومی را تکمیل کنید"></label>'+
 '<label>نام مدیر شرکت<input name="public_manager_name" maxlength="120" placeholder="در صورت نیاز نام مدیر را تکمیل کنید"></label>'+
 '<label>شماره همراه مدیر (اختیاری برای اصلاح)<input name="public_manager_mobile" maxlength="25" inputmode="tel" placeholder="مثلاً ۰۹۱۵…"></label></div>'+
 (can('settings','manage')?'<button class="primary" type="submit">ذخیره تنظیمات نمایش شماره تماس</button>':'')+
 '<p id="syPublicCompanyStatus" role="status" aria-live="polite"></p></form>';
 logoPanel.after(publicPanel);
 const publicForm=publicPanel.querySelector('form');
 if(!can('settings','manage'))publicForm.querySelectorAll('input').forEach(input=>input.disabled=true);
 api(scoped('/api/v1/agency/public-profile')).then(data=>{
  if(!publicForm.isConnected)return;
  for(const key of ['public_office_address','public_manager_name','public_manager_mobile']){
   if(publicForm.elements[key])publicForm.elements[key].value=data?.[key]||'';
  }
  const type=data?.public_contact_type==='landline'?'landline':'mobile';
  publicForm.querySelector('input[name="public_contact_type"][value="'+type+'"]').checked=true;
  publicPanel.querySelector('#syContactMobilePreview').textContent=data?.effective_mobile||'شماره همراه ثبت نشده است';
  publicPanel.querySelector('#syContactOfficePreview').textContent=data?.effective_office_phone||'شماره ثابت ثبت نشده است';
 }).catch(error=>{if(publicForm.isConnected)publicForm.querySelector('#syPublicCompanyStatus').textContent=error.message;});
 if(can('settings','manage'))publicForm.onsubmit=async event=>{
  event.preventDefault();const button=publicForm.querySelector('[type=submit]');button.disabled=true;
  const data=Object.fromEntries(new FormData(publicForm));
  const type=data.public_contact_type||'mobile';
  const profilePhone=type==='landline'?publicPanel.querySelector('#syContactOfficePreview')?.textContent:'';
  if(type==='landline'&&(!profilePhone||profilePhone==='شماره ثابت ثبت نشده است')){
   publicForm.querySelector('#syPublicCompanyStatus').textContent='ابتدا شماره تلفن ثابت شرکت را در بخش اطلاعات شرکت ثبت کنید.';
   button.disabled=false;return;
  }
  try{
   await write('/api/v1/agency/public-profile',data,'PUT');
   publicForm.querySelector('#syPublicCompanyStatus').textContent='تنظیمات نمایش شماره تماس ذخیره شد.';
   notify('شماره تماس انتخابی در نقشه عمومی نمایش داده می‌شود.');
  }catch(error){publicForm.querySelector('#syPublicCompanyStatus').textContent=error.message;}
  finally{button.disabled=false;}
 };
 const logoForm=logoPanel.querySelector('#agencyLogoForm');
 if(can('settings','manage')){
  logoForm.onsubmit=async event=>{
   event.preventDefault();const button=logoForm.querySelector('[type=submit]');button.disabled=true;
   try{await saveLogo(logoForm);logoPanel.querySelector('img').src=agencyLogoUrl()+'&revision='+Date.now();logoForm.reset();$('agencyLogoStatus').textContent='لوگوی شرکت ذخیره شد.';notify('لوگوی شرکت ثبت شد.');}
   catch(err){$('agencyLogoStatus').textContent=err.message;}
   finally{button.disabled=false;}
  };
  $('removeAgencyLogo').onclick=async event=>{
   const button=event.currentTarget;button.disabled=true;
   try{await write('/api/v1/agency/logo',{},'DELETE');logoPanel.querySelector('img').src=agencyLogoUrl()+'&revision='+Date.now();$('agencyLogoStatus').textContent='لوگوی اختصاصی حذف شد؛ نشان پیش‌فرض نمایش داده می‌شود.';}
   catch(err){$('agencyLogoStatus').textContent=err.message;}
   finally{button.disabled=false;}
  };
 }
 if(can('settings','manage')){
 let office=setting.company_location;if(typeof office==='string'){try{office=JSON.parse(office||'null');}catch{office=null;notify('مختصات ذخیره‌شده دفتر شرکت معتبر نیست؛ می‌توانید موقعیت را دوباره ثبت کنید.',true);}}
 const panel=document.createElement('section');panel.className='panel';panel.innerHTML=`<h2>موقعیت دفتر شرکت</h2><form id="companyLocationForm"><div class="form-grid"><label>عرض جغرافیایی<input name="latitude" type="number" step="any" min="-90" max="90" required value="${esc(office?.latitude??'')}"></label><label>طول جغرافیایی<input name="longitude" type="number" step="any" min="-180" max="180" required value="${esc(office?.longitude??'')}"></label><label>نشانی<input name="address" value="${esc(setting.company_address||'')}"></label></div><button class="primary" type="submit">ذخیره موقعیت شرکت</button><p role="status" data-office-result></p></form>`;$('transportSettings').closest('section').after(panel);
 const form=panel.querySelector('form');form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;try{await write('/api/v1/settings',{company_location:{latitude:form.elements.latitude.value,longitude:form.elements.longitude.value},company_address:form.elements.address.value},'PUT');panel.querySelector('[data-office-result]').textContent='موقعیت شرکت ذخیره شد؛ در داشبورد نمایش داده می‌شود.';}catch(err){panel.querySelector('[data-office-result]').textContent=err.message;}finally{button.disabled=false;}};
 locationPicker(form,'company').catch(err=>notify(err.message,true));
 }
 financialSettings(setting);
 agencyPaymentMethodSettings().catch(e=>notify('دریافت روش‌های پرداخت شرکت ناموفق بود: '+e.message,true));
 if(can('agencies','manage')){const f=$('transportSettings'),label=document.createElement('label');label.textContent='نام شرکت';const input=document.createElement('input');input.value=currentAgency.name;input.required=true;label.append(input);const button=document.createElement('button');button.type='button';button.textContent='ذخیره نام شرکت';button.onclick=async()=>{button.disabled=true;try{await write('/api/v1/agencies/'+currentAgency.id,{name:input.value,code:currentAgency.code,cityId:currentAgency.city_id,isActive:currentAgency.is_active},'PUT');currentAgency.name=input.value;renderNav();notify('نام شرکت ذخیره شد.');}catch(err){notify(err.message,true);}finally{button.disabled=false;}};label.append(button);f.prepend(label);}
$('transportSettings').onsubmit=async e=>{e.preventDefault();try{await write('/api/v1/settings',Object.fromEntries(new FormData(e.target)),'PUT');notify('تنظیمات ذخیره شد.');}catch(err){notify(err.message,true);}};
 if(can('holidays')){try{const rows=await api(scoped('/api/v1/holidays'));if(token!==pageToken)return;$('holidayRows').innerHTML=simpleTable(['عنوان','تاریخ شمسی','وضعیت'],rows.map(r=>[r.title,r.date_jalali,Number(r.is_active)?'فعال':'غیرفعال']))+(can('holidays','manage')?'<div class="member-actions"><button id="newHoliday">ثبت تعطیلی</button>'+rows.map(r=>`<button data-holiday="${r.id}">ویرایش ${esc(r.title)}</button>`).join('')+'</div>':'');
 const edit=row=>{dialog(row?'ویرایش تعطیلی':'ثبت تعطیلی',`<form id="holidayForm" class="form-grid"><label>عنوان<input name="title" required value="${esc(row?.title||'')}"></label><label>تاریخ<input name="dateGregorian" type="date" required value="${esc(row?.date_gregorian||'')}"></label><label>تاریخ شمسی<input name="dateJalali" required pattern="[0-9]{4}/[0-9]{2}/[0-9]{2}" placeholder="1405/08/04" value="${esc(row?.date_jalali||'')}"></label><label>فعال<select name="isActive"><option value="1">بله</option><option value="0" ${row&&Number(row.is_active)===0?'selected':''}>خیر</option></select></label><button class="primary">ذخیره</button></form>`);const form=$('holidayForm');form.elements.dateGregorian.onchange=()=>{const parts=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'}).formatToParts(new Date(form.elements.dateGregorian.value+'T12:00:00Z'));form.elements.dateJalali.value=['year','month','day'].map(k=>parts.find(p=>p.type===k).value).join('/');};form.onsubmit=async e=>{e.preventDefault();try{await write('/api/v1/holidays'+(row?'/'+row.id:''),Object.fromEntries(new FormData(form)),row?'PUT':'POST');closeDialog();await settingsPage();}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;}};};$('newHoliday')?.addEventListener('click',()=>edit(null));$('holidayRows').querySelectorAll('[data-holiday]').forEach(b=>b.onclick=()=>edit(rows.find(r=>String(r.id)===b.dataset.holiday)));
 }catch(e){if(token===pageToken)$('holidayRows').innerHTML='<div class="error">'+esc(e.message)+'</div>';}}
 if(can('users')){try{const rows=await api(scoped('/api/v1/users'));if(token!==pageToken)return;userManagementTable($('companyUserRows'),rows,false);}catch(e){if(token===pageToken)$('companyUserRows').innerHTML='<div class="error">'+esc(e.message)+'</div>';}}

 // Settings are separated into clear tabs; financial deduction rates are company-specific.
 const defaults={supervision:3,coordination:1,overhead:7,insurance:7.8};
 let savedRates={};
 try{const parsed=typeof setting.driver_deduction_rates==='string'?JSON.parse(setting.driver_deduction_rates):setting.driver_deduction_rates;if(parsed&&typeof parsed==='object')savedRates=parsed;}catch{}
 const rates={...defaults,...savedRates};
 const deductionPanel=document.createElement('section');
 deductionPanel.className='panel sy-settings-panel sy-deductions-panel';
 deductionPanel.dataset.settingsTab='deductions';
 deductionPanel.innerHTML=`<header class="sy-settings-section-head"><div><h2>کسورات قراردادی رانندگان</h2><p>این درصدها صرفاً سیاست تسویه شرکت با راننده هستند و با سهم‌های ۳٪ نظارت، ۱٪ هماهنگی و ۷٪ بالاسری مندرج در بهای خدمات اولیا یکسان فرض نمی‌شوند. سهم هماهنگی اولیا بدون مصوبه شورای ترافیک قابل توزیع نیست.</p></div></header>
 <form id="driverDeductionSettings" class="sy-deduction-form">
 <div class="form-grid sy-settings-grid">
 ${Object.entries({supervision:'مدیریت، نظارت و بازرسی',coordination:'اطلاع‌رسانی و هماهنگی',overhead:'بالاسری شرکت',insurance:'بیمه راننده'}).map(([key,label])=>`<label>${label} (درصد)<input type="number" name="${key}" inputmode="decimal" min="0" max="100" step="0.001" required value="${esc(rates[key])}" ${can('settings','manage')?'':'disabled'}></label>`).join('')}
 </div>
 <div class="sy-deduction-summary" aria-live="polite"><span>جمع کسورات</span><strong id="deductionTotal"></strong><span>خالص راننده از هر ۱۰۰ ریال</span><strong id="deductionNet"></strong></div>
 <p class="muted">درصدهای قدیمی کسورات قراردادی: ۳٪ + ۱٪ + ۷٪ + ۷٫۸٪ = ۱۸٫۸٪؛ این مقادیر از گذشته برای حفظ سازگاری قراردادهای ثبت‌شده باقی مانده‌اند و نباید به معنای دستور نرخ‌نامه تاکسیرانی برای کسر سهم اولیا از حقوق راننده تلقی شوند. تغییرات فقط بر محاسبات آتی اثر دارد و اسناد نهایی گذشته تغییر نمی‌کنند.</p>
 ${can('settings','manage')?'<div class="member-actions"><button type="submit" class="primary">ذخیره درصد کسورات</button><button type="button" id="resetDeductionRates">بازگردانی مقادیر پیش‌فرض</button></div>':''}
 <p id="deductionSaveStatus" role="status"></p>
 </form>`;
 $('content').append(deductionPanel);
 const nav=document.createElement('nav');nav.className='sy-settings-tabs';nav.setAttribute('aria-label','تب‌های تنظیمات');
 nav.innerHTML='<button type="button" data-settings-switch="tariffs" aria-selected="false">نمایش نرخنامه سازمان</button><button type="button" data-settings-switch="general" class="active" aria-selected="true">مشخصات شرکت</button><button type="button" data-settings-switch="finance" aria-selected="false">قواعد مالی</button><button type="button" data-settings-switch="deductions" aria-selected="false">کسورات</button><button type="button" data-settings-switch="methods" aria-selected="false">روش‌های پرداخت</button><button type="button" data-settings-switch="people" aria-selected="false">کاربران و تعطیلات</button>';
 $('content').prepend(nav);
 const tariffPanel=document.createElement('section');tariffPanel.className='panel sy-settings-panel sy-tariff-readonly';tariffPanel.dataset.settingsTab='tariffs';
 tariffPanel.innerHTML='<header class="sy-settings-section-head"><div><h2>نمایش نرخنامه سازمان تاکسیرانی</h2><p>نرخ‌های مصوب ثبت‌شده توسط ادمین کل؛ فقط جهت مشاهده و بدون امکان ویرایش در پنل شرکت.</p></div></header><div class="form-grid"><label>ناحیه<select id="syCompanyTariffDistrict">'+[1,2,3,4,5,6,7,8].map(n=>'<option value="'+n+'">'+(n===8?'تبادکان':'ناحیه '+number(n))+'</option>').join('')+'</select></label><label>کلاس خودرو<select id="syCompanyTariffClass"><option value="1">کلاس ۱</option><option value="2">کلاس ۲</option><option value="3">کلاس ۳</option></select></label></div><div id="syCompanyTariffResult" role="status" aria-live="polite"></div>';
 $('content').append(tariffPanel);
 const showTariff=async()=>{const target=tariffPanel.querySelector('#syCompanyTariffResult');target.textContent='در حال دریافت نرخنامه…';try{const d=await api(scoped('/api/v1/operations/taxi-tariffs?district='+tariffPanel.querySelector('#syCompanyTariffDistrict').value+'&vehicleClass='+tariffPanel.querySelector('#syCompanyTariffClass').value));if(!target.isConnected)return;target.innerHTML=simpleTable(['مسافت · کیلومتر','سهم راننده · ریال','شهریه اولیا · ریال'],d.rows.map(r=>[number(r.distanceKm),number(r.driverMonthly),number(r.parentMonthly)]));}catch(e){if(target.isConnected)target.textContent=e.message;}};
 tariffPanel.querySelectorAll('select').forEach(el=>el.addEventListener('change',showTariff));void showTariff();
 [...$('content').children].forEach(child=>{
  if(child===nav||child===deductionPanel||child===tariffPanel||child.id==='modal')return;
  child.dataset.settingsTab=child.classList.contains('sy-payment-method-settings')?'methods':child.querySelector('#financialRulesForm')?'finance':child.querySelector('#companyUserRows,#holidayRows')?'people':'general';
 });
 const selectTab=tab=>{nav.querySelectorAll('button').forEach(btn=>{const selected=btn.dataset.settingsSwitch===tab;btn.classList.toggle('active',selected);btn.setAttribute('aria-selected',String(selected));});$('content').querySelectorAll('[data-settings-tab]').forEach(panel=>panel.hidden=panel.dataset.settingsTab!==tab);};
 nav.querySelectorAll('button').forEach(btn=>btn.onclick=()=>selectTab(btn.dataset.settingsSwitch));
 selectTab('general');
 const deductionForm=$('driverDeductionSettings');
 const updateDeductionTotal=()=>{
  const values=Object.keys(defaults).map(k=>Number(deductionForm.elements[k].value||0));
  const total=values.reduce((a,b)=>a+b,0);
  $('deductionTotal').textContent=percent(Math.round(total*1000)/1000)+'٪';
  $('deductionNet').textContent=percent(Math.round((100-total)*1000)/1000)+' ریال';
  deductionForm.querySelector('button[type=submit]')?.toggleAttribute('disabled',total>100||values.some(x=>!Number.isFinite(x)||x<0||x>100));
 };
 deductionForm.addEventListener('input',updateDeductionTotal);
 $('resetDeductionRates')?.addEventListener('click',()=>{Object.entries(defaults).forEach(([k,v])=>deductionForm.elements[k].value=v);updateDeductionTotal();});
 deductionForm.onsubmit=async e=>{
  e.preventDefault();if(!can('settings','manage'))return;
  const payload=Object.fromEntries(Object.keys(defaults).map(k=>[k,Number(deductionForm.elements[k].value)]));
  const button=deductionForm.querySelector('[type=submit]');button.disabled=true;
  try{await write('/api/v1/settings',{driver_deduction_rates:payload},'PUT');$('deductionSaveStatus').textContent='درصد کسورات شرکت ذخیره شد.';notify('درصدهای کسورات ذخیره شدند.');}
  catch(err){$('deductionSaveStatus').textContent=err.message;notify(err.message,true);}
  finally{updateDeductionTotal();}
 };
 updateDeductionTotal();

}

let activeMaps=[];
function persianMonthRange(year,month){
 if(!Number.isInteger(year)||year<1300||year>1600||month<1||month>12)return null;
 const formatter=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',timeZone:'UTC'});let first=null,last=null;
 for(let i=0;i<380;i++){const date=new Date(Date.UTC(year+621,2,15+i,12)),parts=formatter.formatToParts(date),y=Number(parts.find(p=>p.type==='year').value),m=Number(parts.find(p=>p.type==='month').value);if(y===year&&m===month){first??=date.toISOString().slice(0,10);last=date.toISOString().slice(0,10);}else if(first)break;}
 return first?{from:first,to:last}:null;
}
function disposeMaps(){activeMaps.forEach(map=>map.remove());activeMaps=[];}
let districtBoundariesPromise=null;
function loadDistrictBoundaries(){
 if(!districtBoundariesPromise)districtBoundariesPromise=fetch('/assets/school-district-boundaries.geojson',{credentials:'same-origin'})
  .then(response=>{if(!response.ok)throw new Error('دریافت مرزبندی نواحی ناموفق بود');return response.json();})
  .then(data=>{
   if(data?.type!=='FeatureCollection'||!Array.isArray(data.features)||data.features.length!==8)throw new Error('اطلاعات مرزبندی نواحی نامعتبر است');
   return data;
  }).catch(error=>{districtBoundariesPromise=null;throw error;});
 return districtBoundariesPromise;
}
async function addDistrictBoundaries(map){
 if(!map||typeof L?.geoJSON!=='function')return;
 try{
  const data=await loadDistrictBoundaries();
  if(!map._container?.isConnected)return;
  const colors=['#0a7652','#c89b3b','#397b61','#b78625','#5b9a76','#e0b963','#176047','#9a7738'];
  const layer=L.geoJSON(data,{
   style:feature=>{
    const index=data.features.indexOf(feature);
    const color=colors[index<0?0:index%colors.length];
    return {color,weight:2.5,opacity:.9,fillColor:color,fillOpacity:.12};
   },
   onEachFeature:(feature,polygon)=>{
    const name=feature?.properties?.name||'ناحیه آموزش‌وپرورش';
    polygon.bindTooltip(name,{sticky:true,direction:'top'});
    polygon.bindPopup('<strong>'+name+'</strong>');
   }
  }).addTo(map);
  const control=L.control({position:'topright'});
  control.onAdd=()=>{
   const container=L.DomUtil.create('div','leaflet-bar school-district-control');
   const label=L.DomUtil.create('label','school-district-toggle',container);
   const checkbox=L.DomUtil.create('input','',label);
   checkbox.type='checkbox';checkbox.checked=true;checkbox.setAttribute('aria-label','نمایش مرزبندی نواحی آموزش‌وپرورش');
   const caption=L.DomUtil.create('span','',label);caption.textContent='مرزبندی نواحی';
   checkbox.addEventListener('change',()=>{if(checkbox.checked)layer.addTo(map);else map.removeLayer(layer);});
   L.DomEvent.disableClickPropagation(container);L.DomEvent.disableScrollPropagation(container);
   return container;
  };
  control.addTo(map);
 }catch(error){console.warn('School district boundaries unavailable:',error?.message||error);}
}
async function makeMap(element,center=[36.3,59.6],zoom=12){
 try{const company=currentAgency?.id,config=await api(scoped('/api/v1/maps/config'));if(!element?.isConnected||company!==currentAgency?.id)return null;
 if(!config.webKey)throw new Error('کلید نمایش نقشه نشان در تنظیمات شرکت ثبت نشده است.');
 await window.ServiceYarNeshanSDK.load();if(!element.isConnected||company!==currentAgency?.id)return null;
 const map=new L.Map(element,{key:config.webKey,maptype:'dreamy',center,zoom,scrollWheelZoom:false});element.dataset.provider='neshan';activeMaps.push(map);void addDistrictBoundaries(map);requestAnimationFrame(()=>map.invalidateSize());return map;
 }catch(e){if(element?.isConnected){const message=document.createElement('p');message.className='error';message.setAttribute('role','status');message.textContent=e.message;element.replaceChildren(message);}return null;}
}
async function locationPicker(form,key){
 const wrap=document.createElement('section');wrap.className='location-picker';wrap.setAttribute('aria-label',key==='students'?'موقعیت منزل دانش‌آموز در نشان':'موقعیت مدرسه در نشان');
 wrap.innerHTML='<h4>'+ (key==='students'?'موقعیت منزل دانش‌آموز':key==='company'?'موقعیت دفتر شرکت':'موقعیت مدرسه')+'</h4><p class="muted">روی نقشه نشان نقطه را انتخاب کنید یا نام خیابان و مکان را جستجو کنید. ثبت نهایی با دکمه ذخیره فرم انجام می‌شود.</p><div class="location-search"><label>جستجوی مکان در نشان<input type="search" data-place-term autocomplete="off" maxlength="200" placeholder="مثلاً مشهد، میدان آزادی…"></label><button type="button" data-place-search>جستجوی نشان</button></div><div data-place-results class="location-results"></div><div id="addressPicker" class="map-picker" aria-label="نقشه انتخاب موقعیت نشان"></div><p data-place-status role="status" aria-live="polite"></p><div class="member-actions"><button type="button" data-place-center>انتخاب مرکز نقشه</button><button type="button" data-place-address>دریافت نشانی نقطه از نشان</button>'+(key==='students'?'<button type="button" data-place-route>محاسبه مسیر منزل تا مدرسه با نشان</button>':'')+'</div><div data-place-route-result role="status" aria-live="polite"></div>';
 form.prepend(wrap);const lat=form.elements.latitude,lon=form.elements.longitude,valid=()=>lat.value!==''&&lon.value!==''&&Number.isFinite(Number(lat.value))&&Number.isFinite(Number(lon.value)),status=wrap.querySelector('[data-place-status]'),hasPoint=valid();
 const map=await makeMap(wrap.querySelector('.map-picker'),hasPoint?[Number(lat.value),Number(lon.value)]:[36.3,59.6],hasPoint?15:12);if(!wrap.isConnected)return;
 let marker=null,line=null,revision=0;const choose=(latitude,longitude,pan=false)=>{revision++;lat.value=Number(latitude).toFixed(7);lon.value=Number(longitude).toFixed(7);lat.dispatchEvent(new Event('input',{bubbles:true}));lon.dispatchEvent(new Event('input',{bubbles:true}));if(map){if(marker)marker.setLatLng([latitude,longitude]);else marker=L.circleMarker([latitude,longitude],{radius:9,color:'#005b35',fillOpacity:.8}).addTo(map);if(pan)map.setView([latitude,longitude],16);if(line){map.removeLayer(line);line=null;}}status.textContent='نقطه انتخاب شد: '+lat.value+'، '+lon.value;wrap.querySelector('[data-place-route-result]').replaceChildren();};
 if(hasPoint)choose(Number(lat.value),Number(lon.value));map?.on('click',e=>choose(e.latlng.lat,e.latlng.lng));
 for(const field of [lat,lon])field.addEventListener('change',()=>{if(valid())choose(Number(lat.value),Number(lon.value),true);});
 const busy=async(button,fn)=>{button.disabled=true;try{await fn();}catch(e){if(wrap.isConnected)status.textContent=e.message;}finally{if(button.isConnected)button.disabled=false;}};
 wrap.querySelector('[data-place-center]').disabled=!map;wrap.querySelector('[data-place-center]').onclick=()=>{const c=map.getCenter();choose(c.lat,c.lng);};
 wrap.querySelector('[data-place-search]').disabled=!map;wrap.querySelector('[data-place-search]').onclick=e=>busy(e.currentTarget,async()=>{const company=currentAgency?.id,center=map.getCenter(),term=wrap.querySelector('[data-place-term]').value.trim();if(term.length<2)throw new Error('نام خیابان یا مکان را وارد کنید.');status.textContent='در حال جستجو در نشان…';const d=await write('/api/v1/maps/search',{term,latitude:center.lat,longitude:center.lng});if(!wrap.isConnected||company!==currentAgency?.id)return;const results=wrap.querySelector('[data-place-results]');results.replaceChildren();status.textContent=d.items.length?'مکان مورد نظر را از نتایج انتخاب کنید.':'مکانی پیدا نشد.';d.items.forEach(item=>{const button=document.createElement('button');button.type='button';button.textContent=item.title+' · '+item.address;button.onclick=()=>{choose(item.latitude,item.longitude,true);results.replaceChildren();};results.append(button);});});
 wrap.querySelector('[data-place-term]').onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();wrap.querySelector('[data-place-search]').click();}};
 wrap.querySelector('[data-place-address]').onclick=e=>busy(e.currentTarget,async()=>{if(!valid())throw new Error('ابتدا نقطه را انتخاب کنید.');const version=revision,previousAddress=form.elements.address?.value,d=await write('/api/v1/maps/reverse',{latitude:lat.value,longitude:lon.value});if(!wrap.isConnected||version!==revision)return;if(form.elements.address&&form.elements.address.value===previousAddress)form.elements.address.value=d.address;status.textContent=d.address;});
 if(key==='students')form.elements.schoolId.addEventListener('change',()=>{revision++;if(line&&map){map.removeLayer(line);line=null;}wrap.querySelector('[data-place-route-result]').replaceChildren();});
 if(key==='students')wrap.querySelector('[data-place-route]').onclick=e=>busy(e.currentTarget,async()=>{if(!valid()||!form.elements.schoolId.value)throw new Error('موقعیت منزل و مدرسه را انتخاب کنید.');const version=revision,school=form.elements.schoolId.value;status.textContent='در حال محاسبه مسیر با نشان…';const route=await write('/api/v1/maps/route',{latitude:lat.value,longitude:lon.value,schoolId:school});if(!wrap.isConnected||version!==revision||school!==form.elements.schoolId.value)return;wrap.querySelector('[data-place-route-result]').textContent='مسیر نشان: '+number(route.distanceMeters/1000)+' کیلومتر · '+number(parseFloat(route.duration)/60)+' دقیقه';status.textContent='مسیر محاسبه شد.';if(map&&route.geometry?.length){if(line)map.removeLayer(line);line=L.polyline(route.geometry,{color:'#005b35',weight:5}).addTo(map);map.fitBounds(line.getBounds().pad(.15));}});
}
async function serviceMap(id){
 const token=pageToken;try{const d=await api(scoped('/api/v1/services/'+id+'/map'));if(token!==pageToken)return;
 dialog('نقشه سرویس '+d.service.title,'<div id="serviceMap" class="service-map" aria-label="نقشه موقعیت دانش‌آموزان و مدرسه"></div><div class="member-actions">'+d.students.map(s=>`<button data-route-student="${s.id}">مسیریابی ${esc(person(s))}</button>`).join('')+'</div><div id="routeResult"></div>');
 const map=await makeMap($('serviceMap'));if(!map)return;let routeLine=null;const points=[];const add=(r,title,color)=>{if(r?.latitude===null||r?.latitude===undefined||r?.longitude===null||r?.longitude===undefined)return;const point=[Number(r.latitude),Number(r.longitude)];points.push(point);L.circleMarker(point,{radius:9,color,fillOpacity:.8}).addTo(map).bindPopup(esc(title));};
 if(d.schools?.length)d.schools.forEach(sc=>add(sc,sc.name,'#1c9a7e'));else add(d.school,d.school?.name||'مدرسه','#1c9a7e');d.students.forEach(s=>add(s,person(s),'#5c50cf'));if(points.length)map.fitBounds(L.latLngBounds(points).pad(.2),{maxZoom:16});else $('routeResult').textContent='برای نمایش موقعیت‌ها، ابتدا مختصات مدرسه و دانش‌آموزان را ثبت کنید.';
 $('modal').querySelectorAll('[data-route-student]').forEach(button=>button.onclick=async()=>{button.disabled=true;const result=$('routeResult');try{const route=await write('/api/v1/maps/route',{studentId:Number(button.dataset.routeStudent)});if(!result?.isConnected||!activeMaps.includes(map))return;result.textContent='مسیر نشان: '+number(route.distanceMeters/1000)+' کیلومتر · زمان تقریبی '+number(parseFloat(route.duration)/60)+' دقیقه';if(route.geometry?.length){if(routeLine)map.removeLayer(routeLine);routeLine=L.polyline(route.geometry,{color:'#005b35',weight:5}).addTo(map);map.fitBounds(routeLine.getBounds().pad(.15));}}catch(e){if(result?.isConnected)result.textContent=e.message;}finally{if(button.isConnected)button.disabled=false;}});

 }catch(e){notify(e.message,true);}
}
async function balePaymentsPage(token){
 const rows=await api(scoped('/api/v1/integrations/bale/invoices'));if(token!==pageToken)return;
 $('content').innerHTML=`<section class="panel sy-transfer-page"><header class="page-intro"><div><h2>پرداخت با کیف پول بله</h2><p>درخواست پرداخت به گفتگوی بله پرداخت‌کننده ارسال می‌شود. وصول پس از استعلام بله ثبت می‌شود.</p></div></header>${can('bale-payments','manage')?'<form id="baleInvoiceForm" class="form-grid"><label>فاکتور<select name="invoiceId" required id="baleInvoiceId"></select></label><label>شناسه عددی گفتگوی بله<input name="chatId" inputmode="numeric" required pattern="[1-9][0-9]{0,18}"></label><button class="primary">ارسال درخواست پرداخت به بله</button></form>':''}<p id="balePaymentResult" role="status"></p>${simpleTable(['شناسه','فاکتور','مبلغ (ریال)','وضعیت','تاریخ'],rows.map(r=>[r.id,r.invoice_id,number(r.amount),({created:'ایجاد شده',sending:'در حال ارسال',pending:'در انتظار پرداخت',paid:'پرداخت تأیید شده',uncertain:'نیازمند بررسی'})[r.status]||r.status,dateText(r.created_at)]))}</section>`;
 if(!$('baleInvoiceForm'))return;const invoices=await lookup('invoices');if(token!==pageToken)return;$('baleInvoiceId').innerHTML='<option value="">انتخاب کنید</option>'+invoices.map(r=>`<option value="${r.id}">${esc(r.title)} · ${number(r.amount)} ریال</option>`).join('');
 $('baleInvoiceForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const result=await write('/api/v1/integrations/bale/invoices',Object.fromEntries(new FormData(e.target)));if(token!==pageToken)return;$('balePaymentResult').textContent=result.alreadyPending?'برای این فاکتور درخواست فعال وجود دارد؛ وضعیت را بررسی کنید.':'درخواست پرداخت در بله ایجاد شد؛ وصول پس از تأیید پرداخت ثبت خواهد شد.';}catch(err){notify(err.message,true);}finally{if(button.isConnected)button.disabled=false;}};
}

let payrollServiceDays={};
async function renderPayroll(data,token){
 const result=$('operationResult');
 const drivers=(Array.isArray(data.items)?data.items:[]).filter(r=>data.calculationMode!=='assignment_estimate'||(r.service_details||[]).length>0||Number(r.recorded_work_count||0)>0||Number(r.paid||0)!==0);
 const gross=drivers.reduce((sum,r)=>sum+Number(r.earned||0),0),netTotal=drivers.reduce((sum,r)=>sum+Number(r.net_earned||0),0);
 const referenceTotal=drivers.reduce((sum,r)=>sum+Number(r.assigned_monthly_net||0),0);
 const noRecordedWork=drivers.length>0&&drivers.every(r=>Number(r.recorded_earned??r.earned??0)===0);
 const estimatedRows=drivers.filter(r=>r.estimate_requires_confirmation).length;
 const hasAssignedRates=drivers.some(r=>Number(r.assigned_monthly_base||0)>0);
 const rowHtml=drivers.map(r=>{
  const id=Number(r.id),name=person(r),label=esc(name);
  return '<tr data-payroll-driver="'+id+'" data-driver-name="'+label+'"><td data-label="انتخاب"><input name="payrollSelected" type="checkbox" value="'+id+'" aria-label="انتخاب '+label+'"></td><td data-label="راننده"><strong>'+label+'</strong></td><td data-label="ناخالص">'+number(r.earned)+'</td><td data-label="کسورات">'+number(r.total_deductions)+'</td><td data-label="خالص" class="sy-payroll-money">'+number(r.net_earned)+'</td><td data-label="مبنای ماهانه فعلی (برآوردی)" class="sy-payroll-money">'+number(r.assigned_monthly_net)+'</td><td data-label="مبلغ سند"><input data-salary="'+id+'" type="number" inputmode="numeric" min="0" max="1000000000000" step="1" value="'+Math.max(0,Number(r.net_earned||0))+'" aria-label="حقوق '+label+'" '+(can('payroll','manage')?'':'readonly')+'></td><td data-label="عملیات" class="sy-payroll-row-actions"><button type="button" data-preview-driver="'+id+'" class="primary">مشاهده فیش</button><button type="button" data-details-driver="'+id+'" aria-expanded="false">جزئیات سرویس‌ها</button></td></tr>'+
  '<tr data-detail-for="'+id+'" class="sy-payroll-detail-row" hidden><td colspan="8"><div class="sy-payroll-detail-grid"><div class="sy-payroll-services"><strong>سرویس‌ها و روزهای خدمت (پیش‌فرض ۳۰ روز)</strong>'+(r.service_details||[]).map(service=>'<label class="sy-payroll-service-days">'+esc(service.title||service.code||String(service.serviceId))+' · '+number(service.studentCount)+' دانش‌آموز · پایه ماهانه '+number(service.monthlyGross)+' ریال <input type="number" data-payroll-service-days="'+Number(service.serviceId)+'" min="0" max="30" step="1" value="'+Number(service.days)+'" aria-label="تعداد روزهای سرویس '+esc(service.title||String(service.serviceId))+'"> روز · کارکرد '+number(service.earnedGross)+' ریال</label>').join('')+'</div>'+ 
  [['نظارت',r.supervision_deduction],['هماهنگی',r.coordination_deduction],['بالاسری شرکت',r.overhead_deduction],['بیمه',r.insurance_deduction],['مبلغ توافقی سرویس‌ها',r.agreed],['مبنای ناخالص ماهانه تخصیص‌های فعال (صرفاً مقایسه)',r.assigned_monthly_base],['خالص ماهانه تخصیص‌های فعال (صرفاً مقایسه)',r.assigned_monthly_net],['اختلاف کارکرد ثبت‌شده دوره با مبنای ماهانه (نیازمند بررسی)',r.work_vs_assigned_monthly_delta],['تعداد دانش‌آموز تخصیص‌یافته فعال',r.assigned_student_count],['پرداخت ثبت‌شده',r.paid],['مانده',r.balance]].map(item=>'<span>'+item[0]+': <b>'+number(item[1])+'</b></span>').join('')+
  '<span>مبنای ردیف: <b>'+(r.estimate_requires_confirmation?'برآورد از تخصیص فعلی':'کارکرد ثبت‌شده')+'</b></span><span>حساب: <b>'+esc(r.bank_account||'—')+'</b></span><span>شبا: <b>'+esc(r.iban||'—')+'</b></span></div></td></tr>';
 }).join('');
 result.innerHTML='<div class="sy-payroll-workspace"><header class="sy-payroll-overview"><div><span class="sy-operation-kicker">مدیریت حقوق و کارکرد رانندگان</span><h3>حقوق رانندگان</h3><p>مبنای پیش‌فرض فیش، مجموع شهریه دانش‌آموزان سرویس‌های فعال با ۳۰ روز خدمت و کسورات شرکت است. از «جزئیات سرویس‌ها» روزهای هر سرویس را از ۰ تا ۳۰ تغییر دهید. تغییرات تاریخی راننده و تخصیص دانش‌آموز باید پیش از تأیید توسط حسابدار کنترل شود. مبنای ماهانه فعلی مربوط به وضعیت فعلی است، الزاماً متعلق به بازه انتخابی نیست. اختلاف آن بدهی قطعی محسوب نمی‌شود.</p></div><div class="sy-payroll-summary"><div><span>تعداد رانندگان</span><strong>'+number(drivers.length)+'</strong></div><div><span>کارکرد ناخالص</span><strong>'+number(gross)+' ریال</strong></div><div><span>خالص کارکرد</span><strong>'+number(netTotal)+' ریال</strong></div><div><span>خالص ماهانه فعلی (برآوردی)</span><strong>'+number(referenceTotal)+' ریال</strong></div></div></header>'+
 '<nav class="sy-payroll-tabs" role="tablist" aria-label="مدیریت حقوق"><button type="button" id="payrollDriversTab" role="tab" aria-controls="payrollDriversPane" aria-selected="true">فیش رانندگان</button><button type="button" id="payrollDocsTab" role="tab" aria-controls="payrollDocsPane" aria-selected="false">اسناد حقوق ثبت‌شده</button><button type="button" id="payrollArchiveTab" role="tab" aria-controls="payrollDocsPane" aria-selected="false">آرشیو</button></nav>'+
 '<section id="payrollDriversPane" role="tabpanel" aria-labelledby="payrollDriversTab"><div class="sy-payroll-filterbar"><label>جست‌وجوی راننده<input id="payrollSearch" type="search" placeholder="نام راننده" autocomplete="off"></label><p>برای بررسی کسورات و بانک، «جزئیات» را انتخاب کنید.</p></div>'+
 (estimatedRows?'<p class="sy-payroll-legacy-warning" role="status">تعداد '+number(estimatedRows)+' راننده به دلیل نداشتن کارکرد ثبت‌شده با نرخ دانش‌آموزان فعال به‌صورت برآوردی محاسبه شدند. این مبالغ فقط برای یک ماه انتخابی هستند و بدون تاریخچه حضور، تغییر راننده و تخصیص، حقوق قطعی محسوب نمی‌شوند. پیش از تأیید مالی صحت اطلاعات باید بررسی شود.</p>':'')+
 (noRecordedWork&&!estimatedRows?'<p class="sy-payroll-legacy-warning" role="status">'+(hasAssignedRates?'برای بازه انتخابی کارکرد ثبت‌شده‌ای پیدا نشد. مبنای ماهانه فعلی در ستون جداگانه فقط برای مقایسه نمایش داده می‌شود و نباید بدون بررسی دوره و کارکرد به‌عنوان حقوق قطعی ثبت شود.':'برای بازه انتخابی کارکرد ثبت‌شده و نرخ پایه ماهانه تخصیص‌یافته یافت نشد. تخصیص دانش‌آموزان، نرخ پایه راننده و ثبت کارکرد را بررسی کنید.')+'</p>':'')+
 '<div class="sy-payroll-driver-scroll" tabindex="0" role="region" aria-label="فهرست فیش رانندگان"><table class="sy-payroll-driver-table"><thead><tr><th scope="col"><input type="checkbox" id="payrollSelectAll" aria-label="انتخاب همه رانندگان نمایان"></th><th scope="col">راننده</th><th scope="col">کارکرد ناخالص</th><th scope="col">کسورات</th><th scope="col">خالص کارکرد</th><th scope="col">مبنای ماهانه فعلی (برآوردی)</th><th scope="col">مبلغ حقوق سند</th><th scope="col">عملیات</th></tr></thead><tbody>'+
 (rowHtml||'<tr><td colspan="8" class="sy-payroll-empty">راننده‌ای در بازه انتخابی یافت نشد.</td></tr>')+'</tbody></table></div><p id="payrollNoDrivers" class="sy-payroll-empty" hidden>راننده‌ای مطابق جست‌وجو یافت نشد.</p>'+
 '<footer class="sy-payroll-selection"><p id="payrollTotal" role="status" aria-live="polite"></p>'+(can('payroll','manage')?'<button class="primary" type="button" id="createPayrollDocument">ثبت سند حقوق انتخاب‌شده</button>':'')+'</footer></section>'+
 '<section id="payrollDocsPane" role="tabpanel" aria-labelledby="payrollDocsTab" hidden><div id="payrollDocuments" aria-live="polite"></div></section></div>';
 result.querySelectorAll('[data-payroll-service-days]').forEach(input=>input.addEventListener('change',async()=>{
  const id=String(input.dataset.payrollServiceDays),days=Number(input.value);
  if(!Number.isInteger(days)||days<0||days>30){notify('روزهای خدمت باید از صفر تا ۳۰ باشد.',true);return;}
  payrollServiceDays[id]=days;input.disabled=true;
  try{
   const query='?from='+encodeURIComponent(data.from)+'&to='+encodeURIComponent(data.to)+'&calculationMode='+encodeURIComponent(data.calculationMode)+'&serviceDays='+encodeURIComponent(JSON.stringify(payrollServiceDays));
   const fresh=await api(scoped('/api/v1/operations/payroll'+query));
   if(token===pageToken)await renderPayroll(fresh,token);
  }catch(e){notify('محاسبه روزهای سرویس: '+e.message,true);input.disabled=false;}
 }));
 let payrollTab='drivers',listRevision=0;
 const tabs={drivers:$('payrollDriversTab'),docs:$('payrollDocsTab'),archive:$('payrollArchiveTab')};
 const selectTab=tab=>{
  payrollTab=tab;
  const show=tab==='drivers';$('payrollDriversPane').hidden=!show;$('payrollDocsPane').hidden=show;
  for(const [key,button] of Object.entries(tabs)){
   button.setAttribute('aria-selected',String(tab===key));button.tabIndex=tab===key?0:-1;
  }
  if(!show){$('payrollDocsPane').setAttribute('aria-labelledby',tabs[tab].id);list().catch(e=>notify(e.message,true));}
 };
 const tabKeys=Object.keys(tabs);
 for(const [index,key] of tabKeys.entries()){
  const button=tabs[key];button.tabIndex=index===0?0:-1;
  button.onclick=()=>selectTab(key);
  button.onkeydown=event=>{
   const rtl=getComputedStyle(button).direction==='rtl';
   const steps={ArrowRight:rtl?-1:1,ArrowLeft:rtl?1:-1};
   const target=event.key==='Home'?0:event.key==='End'?tabKeys.length-1:
    steps[event.key]?(index+steps[event.key]+tabKeys.length)%tabKeys.length:null;
   if(target===null)return;
   event.preventDefault();
   const next=tabKeys[target];selectTab(next);tabs[next].focus();
  };
 }

 result.querySelectorAll('[data-details-driver]').forEach(button=>button.onclick=()=>{
  const detail=result.querySelector('[data-detail-for="'+button.dataset.detailsDriver+'"]');
  detail.hidden=!detail.hidden;button.setAttribute('aria-expanded',String(!detail.hidden));
 });
 result.querySelectorAll('[data-preview-driver]').forEach(button=>button.onclick=async()=>{
  const driverId=Number(button.dataset.previewDriver),row=drivers.find(d=>Number(d.id)===driverId);
  if(!row)return;button.disabled=true;
  try{
   const input=result.querySelector('[data-salary="'+driverId+'"]'),amount=Number(input?.value);
   const preview={...row,salary:Number.isFinite(amount)&&amount>=0?amount:Number(row.net_earned||0),expected_net_salary:Number(row.net_earned||0),deductions_historical:false};
   showDriverPayslip({status:'preview',preview_only:true,document_no:'پیش‌نمایش',date_from:data.from,date_to:data.to,agency_name:currentAgency?.name},preview);
  }catch(e){notify('مشاهده فیش: '+e.message,true);}finally{if(button.isConnected)button.disabled=false;}
 });
 const total=()=>{let value=0,count=0;const eligible=[...result.querySelectorAll('[data-payroll-driver]:not([hidden]) input[name="payrollSelected"]')];result.querySelectorAll('input[name="payrollSelected"]:checked').forEach(c=>{const amount=Number(result.querySelector(`[data-salary="${c.value}"]`)?.value);if(Number.isFinite(amount)&&amount>=0)value+=amount;count++;});$('payrollTotal').textContent=number(count)+' راننده · جمع حقوق انتخابی '+number(value)+' ریال';const all=$('payrollSelectAll');all.checked=eligible.length>0&&eligible.every(c=>c.checked);all.indeterminate=eligible.some(c=>c.checked)&&!all.checked;};total();
 $('payrollSelectAll').onchange=e=>{result.querySelectorAll('input[name="payrollSelected"]').forEach(c=>{if(!c.closest('tr').hidden)c.checked=e.target.checked;});total();};result.querySelectorAll('input[name="payrollSelected"],[data-salary]').forEach(input=>input.oninput=total);
 $('payrollSearch').oninput=e=>{
  const q=e.target.value.trim().toLocaleLowerCase('fa');let visible=0;
  result.querySelectorAll('[data-payroll-driver]').forEach(row=>{
   const show=row.dataset.driverName.toLocaleLowerCase('fa').includes(q);row.hidden=!show;
   if(!show){const detail=result.querySelector('[data-detail-for="'+row.dataset.payrollDriver+'"]');if(detail)detail.hidden=true;const checkbox=row.querySelector('input[name="payrollSelected"]');if(checkbox)checkbox.checked=false;}
   if(show)visible++;
  });$('payrollNoDrivers').hidden=visible!==0;total();
 };
 const documentBox=$('payrollDocuments');const list=async()=>{
 const revision=++listRevision,archive=payrollTab==='archive';
 documentBox.textContent='در حال دریافت اسناد حقوق…';
 const query=archive?'scope=archive':new URLSearchParams({from:data.from,to:data.to}).toString();
 const docs=await api(scoped('/api/v1/operations/payroll-documents?'+query));
 if(token!==pageToken||revision!==listRevision||!documentBox.isConnected)return;
 $('payrollDocuments').innerHTML='<section class="sy-payroll-documents"><header class="sy-payroll-list-heading"><div><h4>'+(archive?'آرشیو حقوق تأییدشده':'اسناد حقوق دوره')+'</h4><p>مشاهده فیش انفرادی، وضعیت سند و خروجی‌های حقوقی</p></div><strong>'+number(docs.length)+' سند</strong></header><div class="sy-payroll-list-controls"><label>جست‌وجوی شماره سند<input id="payrollDocumentSearch" type="search" placeholder="شماره سند…" autocomplete="off"></label><label>وضعیت سند<select id="payrollDocumentStatus"><option value="">همه وضعیت‌ها</option><option value="draft">پیش‌نویس</option><option value="approved">تأییدشده</option></select></label></div>'+(docs.length?'<div class="table-wrap sy-payroll-list-wrap" tabindex="0" role="region" aria-label="فهرست اسناد حقوق"><table class="sy-payroll-list-table"><thead><tr><th>شماره سند</th><th>دوره</th><th>وضعیت</th><th>تعداد رانندگان</th><th>مبلغ سند (ریال)</th><th>عملیات</th></tr></thead><tbody>'+docs.map(d=>'<tr data-payroll-doc-row data-status="'+esc(d.status)+'" data-doc-number="'+esc(d.document_no)+'"><td data-label="شماره سند"><strong>'+esc(d.document_no)+'</strong></td><td data-label="دوره">'+esc(dateText(d.date_from)+' تا '+dateText(d.date_to))+'</td><td data-label="وضعیت"><span class="sy-payroll-status '+(d.status==='approved'?'is-approved':'is-draft')+'">'+(d.status==='approved'?'تأییدشده':'پیش‌نویس')+'</span></td><td data-label="تعداد رانندگان">'+number(d.driver_count||0)+'</td><td data-label="مبلغ سند (ریال)">'+number(d.total_amount)+'</td><td data-label="عملیات" class="sy-payroll-list-actions"><button data-document-view="'+Number(d.id)+'">فیش رانندگان</button>'+(d.status==='draft'&&can('payroll','manage')?'<button data-document-approve="'+Number(d.id)+'">تأیید سند</button>':'')+(d.status==='approved'?'<button data-document-bank="'+Number(d.id)+'">CSV واریز گروهی</button>':'')+(d.status==='approved'&&can('drivers')?'<button data-document-xlsx="'+Number(d.id)+'">اکسل قالب مرجع</button><button data-document-standard-xlsx="'+Number(d.id)+'">اکسل استاندارد</button>':'')+(can('payroll','manage')?(d.status==='approved'?'<button data-document-reopen="'+Number(d.id)+'">بازگشایی برای ویرایش</button>':'<button data-document-delete="'+Number(d.id)+'">حذف پیش‌نویس</button>'):'')+'</td></tr>').join('')+'</tbody></table></div><p class="sy-payroll-empty" id="payrollDocumentNoMatches" hidden>سندی مطابق جست‌وجو یافت نشد.</p>':'<p class="sy-payroll-empty">هنوز سند حقوقی برای این بازه ثبت نشده است.</p>')+'</section>';
 const applyListFilters=()=>{
  const q=String($('payrollDocumentSearch')?.value||'').trim().toLocaleLowerCase('fa'),status=$('payrollDocumentStatus')?.value||'';
  let visible=0;
  documentBox.querySelectorAll('[data-payroll-doc-row]').forEach(row=>{const matches=(!q||row.dataset.docNumber.toLocaleLowerCase('fa').includes(q))&&(!status||row.dataset.status===status);row.hidden=!matches;if(matches)visible++;});
  if($('payrollDocumentNoMatches'))$('payrollDocumentNoMatches').hidden=visible!==0;
 };
 $('payrollDocumentSearch')?.addEventListener('input',applyListFilters);$('payrollDocumentStatus')?.addEventListener('change',applyListFilters);
 $('payrollDocuments').querySelectorAll('[data-document-view]').forEach(b=>b.onclick=()=>payrollDocument(Number(b.dataset.documentView)));
 $('payrollDocuments').querySelectorAll('[data-document-approve]').forEach(b=>b.onclick=async()=>{const d=docs.find(row=>String(row.id)===b.dataset.documentApprove);if(!d)return;if(!confirm(String(d.document_no||'').startsWith('EST-')?'این سند حاوی برآورد براساس تخصیص‌های فعلی است. تأیید شما به معنای راستی‌آزمایی دستی روزهای خدمت، نرخ و تاریخچه رانندگان است. پس از تأیید سند قفل می‌شود. ادامه می‌دهید؟':'مبالغ سند بررسی شده‌اند و سند تأیید شود؟ پس از تأیید، مبالغ قابل تغییر نیستند.'))return;b.disabled=true;try{await write('/api/v1/operations/payroll-documents/'+b.dataset.documentApprove+'/approve',{confirmEstimate:String(d.document_no||'').startsWith('EST-')});await list();}catch(e){notify(e.message,true);b.disabled=false;}});
 documentBox.querySelectorAll('[data-document-reopen],[data-document-delete]').forEach(button=>button.onclick=async()=>{
  const deleting=Boolean(button.dataset.documentDelete),id=Number(button.dataset.documentDelete||button.dataset.documentReopen);
  if(!confirm(deleting?'پیش‌نویس و ردیف‌های آن حذف شوند؟':'سند به پیش‌نویس برگردد؟ اگر فایل بانکی قبلاً ارسال شده، وضعیت پرداخت آن را بررسی کنید.'))return;
  button.disabled=true;
  try{
   await write('/api/v1/operations/payroll-documents/'+id+(deleting?'':'/reopen'),{},deleting?'DELETE':'POST');
   await list();
   if(!deleting)await payrollDocument(id);
  }catch(e){notify(e.message,true);button.disabled=false;}
 });
 $('payrollDocuments').querySelectorAll('[data-document-xlsx]').forEach(b=>b.onclick=()=>downloadFile('/api/v1/operations/payroll-documents/'+b.dataset.documentXlsx+'/xlsx','payroll.xlsx'));
 $('payrollDocuments').querySelectorAll('[data-document-standard-xlsx]').forEach(b=>b.onclick=()=>downloadFile('/api/v1/operations/payroll-documents/'+b.dataset.documentStandardXlsx+'/xlsx?workbookFormat=standard','payroll-standard.xlsx'));
 $('payrollDocuments').querySelectorAll('[data-document-bank]').forEach(b=>b.onclick=()=>downloadFile('/api/v1/operations/payroll-documents/'+b.dataset.documentBank+'/bank','payroll-bank.csv'));
 };
 $('createPayrollDocument')?.addEventListener('click',async()=>{const lines=[...result.querySelectorAll('input[name="payrollSelected"]:checked')].map(c=>({driverId:Number(c.value),amount:result.querySelector(`[data-salary="${c.value}"]`).value}));if(!lines.length){notify('رانندگان را انتخاب کنید.',true);return;}if(lines.some(line=>line.amount.trim()===''||!Number.isSafeInteger(Number(line.amount))||Number(line.amount)<0||Number(line.amount)>1000000000000)){notify('مبلغ حقوق باید عدد صحیح و غیرمنفی به ریال باشد.',true);return;}const selectedEstimated=lines.some(line=>drivers.find(r=>Number(r.id)===line.driverId)?.estimate_requires_confirmation);
 if(selectedEstimated&&!confirm('بخشی از حقوق از تخصیص‌های فعلی دانش‌آموزان برآورد شده است؛ تاریخ شروع/پایان و تغییرات تاریخی تخصیص و تعداد روزهای حضور مشخص نیست. آیا مبالغ را شخصاً بررسی کرده‌اید و مایلید سند پیش‌نویس برآوردی ایجاد شود؟'))return;
 $('createPayrollDocument').disabled=true;try{await write('/api/v1/operations/payroll-documents',{from:data.from,to:data.to,lines,serviceDays:payrollServiceDays,calculationMode:data.calculationMode,confirmEstimate:selectedEstimated});selectTab('docs');notify('سند پیش‌نویس حقوق ذخیره شد؛ هیچ انتقال وجهی انجام نشد.');}catch(e){notify(e.message,true);}finally{if($('createPayrollDocument'))$('createPayrollDocument').disabled=false;}});
}
function showDriverPayslip(doc,line){
 const amount=v=>number(v);
 const isPreview=doc.preview_only===true,net=Number(line.expected_net_salary||0),saved=Number(line.salary||0),difference=saved-net;
 const data=[['کارکرد ناخالص',line.earned],['کسور نظارت و بازرسی',line.supervision_deduction],['کسور اطلاع‌رسانی و هماهنگی',line.coordination_deduction],['کسور بالاسری شرکت',line.overhead_deduction],['حق بیمه راننده',line.insurance_deduction]];
 const item=(label,value)=>'<div class="sy-payslip-field"><dt>'+esc(label)+'</dt><dd>'+esc(value||'—')+'</dd></div>';
 const sumRow=(label,value,cls='')=>'<tr class="'+cls+'"><th scope="row">'+esc(label)+'</th><td>'+amount(value)+'</td></tr>';
 const agency=doc.agency_name||currentAgency?.name||'سرویس‌یار';
 dialog('فیش حقوق انفرادی · '+person(line),'<div class="sy-payslip-actions"><p>'+(isPreview?'پیش‌نمایش محاسباتی بر اساس فیلتر فعلی است و سند مالی تأییدشده محسوب نمی‌شود.':'اطلاعات این فیش از سند حقوق ذخیره‌شده تهیه شده است.')+' برای چاپ روی «چاپ فیش» و برای دریافت فایل روی «ذخیره PDF» بزنید.</p><button type="button" class="primary" id="payslipPrint">چاپ فیش</button><button type="button" id="payslipDownloadPdf">ذخیره PDF</button><button type="button" id="payslipBack">'+(isPreview?'بازگشت به فهرست رانندگان':'بازگشت به سند حقوق')+'</button></div>'+
 '<article class="sy-payslip-sheet" dir="rtl" aria-label="فیش حقوق '+esc(person(line))+'">'+
 '<header class="sy-payslip-header">'+agencyLogoImage('لوگوی '+agency,56).replace('class="sy-company-logo"','class="sy-company-logo sy-payslip-logo" style="display:block;width:56px;height:56px;max-width:56px;max-height:56px;object-fit:contain;flex:none"')+'<div><strong>'+esc(agency)+'</strong><h2>فیش حقوق و کارکرد راننده</h2><p>سرویس حمل‌ونقل دانش‌آموزی · مبالغ به ریال</p></div><span class="sy-payslip-document-state">'+(isPreview?'پیش‌نمایش محاسباتی':doc.status==='approved'?'سند تأییدشده':'پیش‌نویس / تأییدنشده')+'</span></header>'+
 (doc.has_estimated_lines||line.estimate_requires_confirmation?'<div class="sy-payslip-draft sy-payslip-preview-alert">مبنای برآوردی تخصیص فعلی: نیازمند تأیید انسانی تعداد روزهای خدمت و تاریخچه تخصیص است.</div>':'')+
 (isPreview?'<div class="sy-payslip-draft sy-payslip-preview-alert">نمونه محاسباتی ـ هنوز هیچ سند حقوقی برای این راننده در این دوره ثبت نشده است. این برگه سند حقوق تأییدشده یا رسید پرداخت نیست.</div>':doc.status!=='approved'?'<div class="sy-payslip-draft">پیش‌نویس ـ فاقد تأیید نهایی حسابداری</div>':'')+
 (!line.deductions_historical&&!isPreview?'<div class="sy-payslip-draft">توجه: کسورات این سند قدیمی با نرخ‌های فعلی برآورد شده‌اند؛ مقدار تاریخی کسورات ثبت نشده است.</div>':'')+
 '<section class="sy-payroll-service-breakdown"><h3>جزئیات سرویس‌ها و روزهای خدمت</h3>'+(doc.service_details?.[Number(line.driver_id||line.id)]||line.service_details||[]).map(s=>'<p>'+esc(s.title||s.code||String(s.serviceId))+' · '+number(s.days)+' روز از ۳۰ · مبلغ ماهانه '+number(s.monthlyGross)+' ریال · کارکرد '+number(s.earnedGross)+' ریال</p>').join('')+'</section>'+ 
 '<dl class="sy-payslip-meta">'+item('نام و نام خانوادگی راننده',person(line))+item('شماره سند',doc.document_no)+item('تاریخ شروع دوره',dateText(doc.date_from))+item('تاریخ پایان دوره',dateText(doc.date_to))+item('نام بانک',line.bank_name)+item('شماره حساب',line.bank_account)+item('شماره شبا',line.iban)+'</dl>'+
 '<div class="sy-payslip-columns"><section><h3>مزایا و کارکرد</h3><table><thead><tr><th scope="col">شرح</th><th scope="col">مبلغ (ریال)</th></tr></thead><tbody>'+sumRow('کارکرد ناخالص',line.earned)+'</tbody><tfoot>'+sumRow('جمع کارکرد',line.earned)+'</tfoot></table></section>'+
 '<section><h3>کسورات</h3><table><thead><tr><th scope="col">شرح</th><th scope="col">مبلغ (ریال)</th></tr></thead><tbody>'+data.slice(1).map(([k,v])=>sumRow(k,v)).join('')+'</tbody><tfoot>'+sumRow('جمع کسورات',line.total_deductions)+'</tfoot></table></section></div>'+
 '<section class="sy-payslip-settlement"><h3>خلاصه تسویه</h3><div><span>خالص محاسباتی پس از کسورات</span><strong>'+amount(net)+' ریال</strong></div><div class="sy-payslip-net"><span>مبلغ حقوق ثبت‌شده در سند</span><strong>'+amount(saved)+' ریال</strong></div>'+
 (Math.abs(difference)>=1?'<p class="sy-payslip-discrepancy">توجه: مبلغ ثبت‌شده با خالص محاسباتی '+amount(Math.abs(difference))+' ریال مغایرت دارد. علت اختلاف باید توسط واحد مالی بررسی شود.</p>':'')+'</section>'+
 '<footer class="sy-payslip-signatures"><div>تنظیم‌کننده سند<span></span></div><div>مسئول مالی<span></span></div><div>مهر و امضای شرکت<span></span></div></footer><p class="sy-payslip-footnote">'+(isPreview?'این برگه صرفاً پیش‌نمایش غیررسمی از محاسبات بازه انتخابی است و سند ثبت‌شده محسوب نمی‌شود.':'این فیش فقط بر اساس سند حقوق ثبت‌شده تهیه شده و به‌تنهایی به معنای انجام انتقال وجه نیست.')+'</p></article>');
 $('payslipPrint').onclick=()=>{
  const slip=document.querySelector('#modal .sy-payslip-sheet');
  if(!slip){notify('فیش برای چاپ آماده نیست.',true);return;}
  const popup=window.open('','_blank');
  if(!popup){notify('اجازه باز شدن پنجره جدید را برای چاپ فیش در مرورگر فعال کنید.',true);return;}
  const title='فیش حقوق '+person(line);
  const toolbar='<div class="sy-print-toolbar"><span>پیش‌نمایش '+esc(title)+'</span><button id="printSlipNow" type="button">چاپ فیش</button><button id="closeSlipWindow" type="button">بستن</button></div>';
  const script="(function(){var btn=document.getElementById('printSlipNow');btn.disabled=true;btn.onclick=function(){window.focus();window.print();};document.getElementById('closeSlipWindow').onclick=function(){window.close();};var unlock=function(){btn.disabled=false;};setTimeout(unlock,3500);Promise.all([document.fonts&&document.fonts.ready?document.fonts.ready:Promise.resolve(),Promise.all(Array.from(document.images).map(function(img){if(img.complete)return Promise.resolve();return new Promise(function(done){img.addEventListener('load',done,{once:true});img.addEventListener('error',done,{once:true});});}))]).then(unlock).catch(unlock);})();";
  popup.document.open();
  popup.document.write('<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'+esc(title)+'</title><link rel="stylesheet" href="/assets/app.css"><link rel="stylesheet" href="/assets/operations.css"><style>.sy-print-toolbar{position:sticky;top:0;z-index:10;display:flex;gap:10px;align-items:center;padding:12px;background:#eef7f0;border-bottom:1px solid #d1e2d5;font:13px Tahoma,sans-serif}.sy-print-toolbar span{margin-inline-end:auto}.sy-print-toolbar button{padding:10px 12px;border:1px solid #bfd6c4;border-radius:9px;background:#fff;cursor:pointer}.sy-print-toolbar #printSlipNow{background:#165f3c;color:white}.sy-print-toolbar button:disabled{opacity:.5}@page{size:A4 portrait;margin:12mm}body.sy-payslip-print-window{margin:0;direction:rtl;min-width:0}body.sy-payslip-print-window .sy-payslip-sheet{box-sizing:border-box;max-width:190mm;margin:12px auto;padding:12mm;background:#fff}body.sy-payslip-print-window .sy-payslip-header{display:grid;grid-template-columns:56px minmax(0,1fr) minmax(0,145px);gap:12px;align-items:center}body.sy-payslip-print-window .sy-payslip-header img.sy-company-logo{width:56px!important;height:56px!important;max-width:56px!important;max-height:56px!important;object-fit:contain!important}body.sy-payslip-print-window .sy-payslip-header>div{min-width:0}body.sy-payslip-print-window .sy-payslip-header :is(strong,h2,p){word-break:normal;overflow-wrap:normal}@media print{.sy-print-toolbar{display:none!important}body.sy-payslip-print-window .sy-payslip-sheet{width:100%!important;max-width:none!important;padding:0!important;margin:0!important;border:0!important;box-shadow:none!important}body.sy-payslip-print-window .sy-payslip-header img.sy-company-logo{width:50px!important;height:50px!important;max-width:50px!important;max-height:50px!important}}</style></head><body class="sy-payslip-print-window">'+toolbar+slip.outerHTML+'<script>'+script+'</script></body></html>');
  popup.document.close();
 };
 $('payslipDownloadPdf').onclick=async()=>{
  const button=$('payslipDownloadPdf'),slip=document.querySelector('#modal .sy-payslip-sheet');
  if(!slip){notify('فیش برای ذخیره آماده نیست.',true);return;}
  button.disabled=true;
  const label=button.textContent;
  button.textContent='در حال ساخت PDF…';
  let wrap;
  const task=window.ServiceYarLoading?.begin('ساخت فایل PDF فیش حقوقی',{immediate:true});
  try{
   task?.update({detail:'بارگذاری موتور تولید PDF…'});
   await loadHtml2Pdf();
   wrap=document.createElement('div');
   wrap.dir='rtl';
   wrap.className='sy-payslip-pdf-render';
   // html2canvas does not faithfully reproduce the app's responsive grid in
   // all browsers. Use a dedicated, deterministic document layout instead.
   wrap.style.cssText='position:absolute;top:0;left:0;z-index:-1;box-sizing:border-box;width:718px;max-width:718px;padding:0;background:#fff;direction:rtl;pointer-events:none';
   const copy=slip.cloneNode(true);
   copy.style.cssText='box-sizing:border-box;display:block;width:718px;min-width:718px;max-width:718px;margin:0;padding:18px;border:0;box-shadow:none;overflow:visible;font-family:Vazirmatn,Tahoma,sans-serif;font-size:12px;line-height:1.8;direction:rtl';
   const docStyle=document.createElement('style');
   docStyle.textContent='.sy-payslip-pdf-render .sy-payslip-header{display:flex!important;flex-direction:row!important;align-items:center!important;gap:12px!important;min-width:0!important}.sy-payslip-pdf-render .sy-payslip-header>img{flex:0 0 50px!important;width:50px!important;height:50px!important;max-width:50px!important;max-height:50px!important}.sy-payslip-pdf-render .sy-payslip-header>div{flex:1 1 auto!important;min-width:0!important}.sy-payslip-pdf-render .sy-payslip-document-state{flex:0 0 116px!important;min-width:116px!important;max-width:116px!important}.sy-payslip-pdf-render .sy-payslip-columns{display:block!important;margin:12px 0!important}.sy-payslip-pdf-render .sy-payslip-columns>section{display:block!important;width:100%!important;max-width:100%!important;margin:0 0 14px!important;break-inside:avoid!important}.sy-payslip-pdf-render .sy-payslip-columns table{table-layout:fixed!important;width:100%!important;border-collapse:collapse!important}.sy-payslip-pdf-render .sy-payslip-columns :is(th,td){display:table-cell!important;width:50%!important;max-width:50%!important;box-sizing:border-box!important;white-space:normal!important;overflow:visible!important;word-break:normal!important}.sy-payslip-pdf-render .sy-payslip-meta{display:block!important;width:100%!important;overflow:visible!important}.sy-payslip-pdf-render .sy-payslip-field{box-sizing:border-box!important;display:flex!important;width:100%!important;min-height:28px!important;padding:4px 10px!important;border-bottom:1px solid #d9e3db!important}.sy-payslip-pdf-render .sy-payslip-field dt{flex:0 0 45%!important}.sy-payslip-pdf-render .sy-payslip-field dd{flex:1 1 auto!important;overflow-wrap:anywhere!important}.sy-payslip-pdf-render .sy-payslip-signatures{display:flex!important;gap:16px!important;margin-top:22px!important}.sy-payslip-pdf-render .sy-payslip-signatures>div{flex:1 1 0!important}.sy-payslip-pdf-render :is(h2,h3,p,td,th,dt,dd,span,strong){letter-spacing:normal!important;text-transform:none!important}';
   wrap.appendChild(docStyle);
   // The PDF library clones only the sheet, not its wrapper. Apply all
   // critical layout overrides inline so they survive that clone.
   const inlineStyle=(selector,value)=>copy.querySelectorAll(selector).forEach(element=>{element.style.cssText+=';'+value;});
   inlineStyle('.sy-payslip-header','display:flex!important;flex-direction:row!important;align-items:center!important;gap:12px!important;min-width:0!important');
   inlineStyle('.sy-payslip-header>img','flex:0 0 50px!important;width:50px!important;height:50px!important;max-width:50px!important;max-height:50px!important');
   inlineStyle('.sy-payslip-header>div','flex:1 1 auto!important;min-width:0!important');
   inlineStyle('.sy-payslip-document-state','flex:0 0 116px!important;min-width:116px!important;max-width:116px!important');
   inlineStyle('.sy-payslip-columns','display:block!important;width:100%!important;margin:12px 0!important');
   inlineStyle('.sy-payslip-columns>section','display:block!important;width:100%!important;max-width:100%!important;margin:0 0 14px!important');
   inlineStyle('.sy-payslip-columns table','table-layout:fixed!important;width:100%!important;border-collapse:collapse!important');
   inlineStyle('.sy-payslip-columns th,.sy-payslip-columns td','display:table-cell!important;box-sizing:border-box!important;white-space:normal!important;overflow:visible!important');
   inlineStyle('.sy-payslip-columns td','width:35%!important;max-width:35%!important');
   inlineStyle('.sy-payslip-meta','display:block!important;width:100%!important;overflow:visible!important');
   inlineStyle('.sy-payslip-field','box-sizing:border-box!important;display:flex!important;width:100%!important;min-height:28px!important;padding:4px 10px!important;border-bottom:1px solid #d9e3db!important');
   inlineStyle('.sy-payslip-field dt','flex:0 0 45%!important');
   inlineStyle('.sy-payslip-field dd','flex:1 1 auto!important;overflow-wrap:anywhere!important');
   inlineStyle('.sy-payslip-signatures','display:flex!important;gap:16px!important;margin-top:22px!important');
   inlineStyle('.sy-payslip-signatures>div','flex:1 1 0!important');
   wrap.appendChild(copy);
   document.body.appendChild(wrap);
   await Promise.all(Array.from(copy.querySelectorAll('img')).map(img=>img.complete?Promise.resolve():new Promise(resolve=>{img.onload=resolve;img.onerror=resolve;})));
   if(document.fonts?.ready)await document.fonts.ready;
   const name=person(line).replace(/[\\/:*?"<>|]/g,'-').trim()||'راننده';
   task?.update({detail:'پردازش صفحه و آماده‌سازی فایل…'});
   await window.html2pdf().set({
    margin:[10,10,10,10],filename:'فیش-حقوق-'+name+'.pdf',
    image:{type:'jpeg',quality:0.98},
    html2canvas:{scale:2.5,useCORS:true,backgroundColor:'#ffffff',scrollX:0,scrollY:0,windowWidth:794},
    jsPDF:{unit:'mm',format:'a4',orientation:'portrait'},
    pagebreak:{mode:['css','legacy'],avoid:['.sy-payslip-header','.sy-payslip-signatures','tr']}
   }).from(copy).save();
  }catch(error){notify(error?.message||'خطا در ساخت فایل PDF',true);}
  finally{task?.finish();wrap?.remove();button.disabled=false;button.textContent=label;}
 };
 $('payslipBack').onclick=()=>{if(isPreview){closeDialog();return;}payrollDocument(Number(doc.id));};
}
async function payrollDocument(id){
 try{
  const doc=await api(scoped('/api/v1/operations/payroll-documents/'+id));
  const editable=doc.status==='draft'&&can('payroll','manage');
  const lineHtml=doc.lines.map(r=>{
   const id=Number(r.driver_id),name=esc(person(r));
   return '<tr data-doc-driver="'+id+'" data-driver-name="'+name+'"><td data-label="راننده"><strong>'+name+'</strong></td><td data-label="ناخالص">'+number(r.earned)+'</td><td data-label="کسورات">'+number(r.total_deductions)+'</td><td data-label="خالص محاسباتی">'+number(r.expected_net_salary)+'</td><td data-label="حقوق سند"><input data-doc-salary="'+id+'" type="number" min="0" step="0.01" value="'+esc(r.salary)+'" '+(editable?'':'readonly')+' aria-label="حقوق '+name+'"></td><td data-label="عملیات"><button type="button" data-driver-payslip="'+id+'" class="primary">فیش انفرادی / PDF</button><button type="button" data-expand-payroll="'+id+'" aria-expanded="false">جزئیات حقوق</button></td></tr>'+
   '<tr class="sy-payroll-detail-row" data-doc-details="'+id+'" hidden><td colspan="6"><div class="sy-payroll-detail-grid">'+
   [['نظارت',r.supervision_deduction],['هماهنگی',r.coordination_deduction],['بالاسری',r.overhead_deduction],['بیمه',r.insurance_deduction],['پرداخت ثبت‌شده',r.paid],['ماهیانه توافق‌شده',r.agreed]].map(item=>'<span>'+item[0]+': <b>'+number(item[1])+'</b></span>').join('')+
   '<div class="sy-payroll-services">'+(doc.service_details?.[id]||[]).map(s=>'<p>'+esc(s.title)+' · '+number(s.days)+' روز · مبلغ '+number(s.earnedGross)+' ریال</p>').join('')+'</div><span>بانک: <b>'+esc(r.bank_name||'—')+'</b></span><span>حساب: <b>'+esc(r.bank_account||'—')+'</b></span></div></td></tr>';
  }).join('');
  dialog('سند حقوق '+doc.document_no,'<section class="sy-payroll-doc-detail"><header class="sy-payroll-doc-intro"><div><span class="sy-operation-kicker">سند حقوق رانندگان</span><h3>'+esc(doc.document_no)+'</h3><p>دوره: '+dateText(doc.date_from)+' تا '+dateText(doc.date_to)+' · '+number(doc.lines.length)+' راننده</p></div><span class="sy-payroll-status '+(doc.status==='approved'?'is-approved':'is-draft')+'">'+(doc.status==='approved'?'تأییدشده':'پیش‌نویس')+'</span></header>'+
  (doc.has_estimated_lines?'<p class="sy-payroll-legacy-warning">این سند برآوردی است و برای تأیید نهایی نیازمند بررسی دستی تاریخچه تخصیص و روزهای خدمت است.</p>':'')+
  '<p class="sy-payroll-doc-guide">برای چاپ فیش هر راننده، گزینه «فیش انفرادی / PDF» را انتخاب کنید. نمایش جزئیات کسورات به‌صورت اختیاری است.</p>'+
  (doc.lines.some(r=>r.salary_differs_from_calculated_net)?'<p class="sy-payroll-legacy-warning">مبلغ برخی ردیف‌ها با خالص محاسباتی تفاوت دارد؛ قبل از تأیید بررسی کنید.</p>':'')+
  '<label class="sy-payroll-doc-search">جست‌وجوی راننده<input id="payrollDocumentDriverSearch" type="search" placeholder="نام راننده" autocomplete="off"></label>'+
  '<form id="payrollDocumentForm"><div class="table-wrap sy-payroll-detail-table-wrap" role="region" aria-label="فیش‌های رانندگان سند" tabindex="0"><table class="sy-payroll-detail-table"><thead><tr><th scope="col">راننده</th><th scope="col">ناخالص</th><th scope="col">کسورات</th><th scope="col">خالص</th><th scope="col">حقوق سند</th><th scope="col">عملیات</th></tr></thead><tbody>'+lineHtml+'</tbody></table></div>'+
  '<p id="payrollDocumentDriversEmpty" class="sy-payroll-empty" hidden>راننده‌ای مطابق جست‌وجو پیدا نشد.</p>'+(editable?'<footer class="sy-payroll-doc-save"><button class="primary" type="submit">ذخیره مبالغ سند</button></footer>':'')+'</form></section>');
  $('payrollDocumentDriverSearch').oninput=e=>{
   const q=e.target.value.trim().toLocaleLowerCase('fa');let visible=0;
   $('payrollDocumentForm').querySelectorAll('[data-doc-driver]').forEach(tr=>{
    const show=tr.dataset.driverName.toLocaleLowerCase('fa').includes(q);tr.hidden=!show;
    if(!show){const extra=$('payrollDocumentForm').querySelector('[data-doc-details="'+tr.dataset.docDriver+'"]');if(extra)extra.hidden=true;}if(show)visible++;
   });
   $('payrollDocumentDriversEmpty').hidden=visible!==0;
  };
  $('payrollDocumentForm').querySelectorAll('[data-expand-payroll]').forEach(button=>button.onclick=()=>{
   const detail=$('payrollDocumentForm').querySelector('[data-doc-details="'+button.dataset.expandPayroll+'"]');
   detail.hidden=!detail.hidden;button.setAttribute('aria-expanded',String(!detail.hidden));
  });
  $('payrollDocumentForm').querySelectorAll('[data-driver-payslip]').forEach(button=>button.onclick=()=>{
   const line=doc.lines.find(r=>Number(r.driver_id)===Number(button.dataset.driverPayslip));
   if(line)showDriverPayslip(doc,line);
  });
  if(editable)$('payrollDocumentForm').onsubmit=async e=>{
   e.preventDefault();const button=e.currentTarget.querySelector('[type=submit]');button.disabled=true;
   try{
    const lines=[...e.target.querySelectorAll('[data-doc-salary]')].map(input=>({driverId:Number(input.dataset.docSalary),amount:input.value}));
    await write('/api/v1/operations/payroll-documents/'+id,{lines},'PUT');
    notify('مبالغ سند ذخیره شدند.');await payrollDocument(id);
   }catch(err){const box=$('formError');if(box){box.textContent=err.message;box.hidden=false;}if(button.isConnected)button.disabled=false;}
  };
 }catch(e){notify(e.message,true);}
}

async function serviceSchoolsDialog(id){
 const token=pageToken;try{const [selected,all]=await Promise.all([api(scoped('/api/v1/services/'+id+'/schools')),lookup('schools')]);if(token!==pageToken)return;
 const choices=[...all,...selected.filter(s=>!all.some(a=>Number(a.id)===Number(s.id)))],ids=new Set(selected.map(s=>Number(s.id)));
 dialog('مدارس سرویس',`<p>یک یا چند مدرسه انتخاب کنید. مدرسه دارای دانش‌آموز تخصیص‌یافته تا حذف آن تخصیص قابل کنارگذاشتن نیست.</p><form id="serviceSchoolsForm"><div class="school-checkboxes">${choices.map(s=>`<label><input type="checkbox" name="schoolIds" value="${s.id}" ${ids.has(Number(s.id))?'checked':''} ${can('services','manage')?'':'disabled'}>${esc(s.name)}</label>`).join('')}</div>${can('services','manage')?'<button class="primary">ذخیره مدارس سرویس</button>':''}</form>`);
 $('serviceSchoolsForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const schoolIds=[...new FormData(e.target).getAll('schoolIds')].map(Number);await write('/api/v1/services/'+id+'/schools',{schoolIds},'PUT');closeDialog();lookupCache={};await loadResource('services');notify('مدارس سرویس ذخیره شدند.');}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
 }catch(e){notify(e.message,true);}
}

$('logout').addEventListener('click',()=>{disposeMaps();$('dashboardBackdrop')?.remove();$('app').classList.remove('dashboard-active');});

async function schoolShiftsDialog(id){
 const token=pageToken,rows=await api(scoped('/api/v1/schools/'+id+'/shifts'));if(token!==pageToken)return;
 const patterns={all:'ایام هفته',even_days:'روزهای زوج',odd_days:'روزهای فرد',even_weeks:'هفته‌های زوج',odd_weeks:'هفته‌های فرد',sat:'شنبه',sun:'یکشنبه',mon:'دوشنبه',tue:'سه‌شنبه',wed:'چهارشنبه',thu:'پنج‌شنبه',fri:'جمعه'},days=['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه'];
 dialog('شیفت، ساعت و تعطیلی',`<p class="muted">برنامه شیفت مدرسه را تعریف کنید. برنامه روزانه را از پیش‌نمایش تقویم بررسی کنید. تخصیص فعلی راننده و سرویس حفظ می‌شود.</p><form id="shiftPlanForm"><div id="shiftPlanRows"></div><button type="button" id="addShiftPlan">افزودن شیفت</button>${can('schools','manage')?'<button class="primary">ذخیره برنامه</button>':''}</form>`);
 const form=$('shiftPlanForm'),editable=can('schools','manage');
 const preview=document.createElement('form');preview.innerHTML='<h4>برنامه روز مدرسه</h4><div class="form-grid"><label>تاریخ بررسی<input name="date" type="date" required value="'+todayISO()+'"></label><label>شنبه آغاز هفته اول<input name="weekAnchor" type="date"></label></div><p class="muted">روزهای زوج: شنبه، دوشنبه، چهارشنبه؛ هفته‌ها از شنبه انتخاب‌شده با شماره ۱ محاسبه می‌شوند. پیش‌نمایش از برنامه ذخیره‌شده است.</p><button>نمایش برنامه ذخیره‌شده</button><div id="shiftCalendarResult" aria-live="polite"></div>';
 form.after(preview);preview.onsubmit=async e=>{e.preventDefault();const button=preview.querySelector('button');button.disabled=true;try{const d=await api(scoped('/api/v1/schools/'+id+'/shifts/calendar?'+new URLSearchParams(new FormData(preview))));$('shiftCalendarResult').innerHTML=simpleTable(['شیفت','ورود','خروج'],d.shifts.map(r=>[r.name,r.start,r.end]))+(d.needsWeekAnchor.length?'<p class="error">برای شیفت‌های هفتگی، شنبه آغاز هفته اول را انتخاب کنید.</p>':d.shifts.length?'':'<p>در این تاریخ شیفت فعالی وجود ندارد.</p>');}catch(err){$('shiftCalendarResult').textContent=err.message;}finally{button.disabled=false;}};

 const add=(r={name:'',pattern:'all',start:'07:00',end:'12:30',holidays:[6]})=>{const box=document.createElement('fieldset');box.className='shift-plan';box.innerHTML=`<legend>شیفت مدرسه</legend><div class="form-grid"><label>نام شیفت<input name="shiftName" required maxlength="80" value="${esc(r.name)}"></label><label>الگوی حضور<select name="pattern">${Object.entries(patterns).map(([k,v])=>`<option value="${k}" ${r.pattern===k?'selected':''}>${v}</option>`).join('')}</select></label><label>ساعت ورود<input name="start" type="time" required value="${esc(r.start)}"></label><label>ساعت خروج<input name="end" type="time" required value="${esc(r.end)}"></label></div><fieldset><legend>روزهای تعطیل</legend>${days.map((d,i)=>`<label class="shift-day"><input type="checkbox" name="holiday" value="${i}" ${r.holidays.includes(i)?'checked':''}>${d}</label>`).join('')}</fieldset><button type="button" data-remove-shift>حذف این شیفت</button>`;box.disabled=!editable;box.querySelector('[data-remove-shift]').onclick=()=>box.remove();$('shiftPlanRows').append(box);};
 rows.forEach(add);$('addShiftPlan').hidden=!editable;$('addShiftPlan').onclick=()=>add();
 form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('.primary');if(!button)return;button.disabled=true;try{const shifts=[...form.querySelectorAll('.shift-plan')].map(box=>({name:box.querySelector('[name=shiftName]').value,pattern:box.querySelector('[name=pattern]').value,start:box.querySelector('[name=start]').value,end:box.querySelector('[name=end]').value,holidays:[...box.querySelectorAll('[name=holiday]:checked')].map(x=>Number(x.value))}));await write('/api/v1/schools/'+id+'/shifts',{shifts},'PUT');closeDialog();notify('برنامه شیفت ذخیره شد.');}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
}

const originalReportsPage=reportsPage;
reportsPage=async function(token=pageToken){
 await originalReportsPage();if(token!==pageToken||!$('reportSchoolId'))return;
 const choices=[['reportSchoolId','schools','مدرسه'],['reportDriverId','drivers','راننده'],['reportServiceId','services','سرویس']];
 await Promise.all(choices.map(async([id,key,title])=>{
  const old=$(id);if(!old)return;const select=document.createElement('select');select.id=id;select.disabled=true;select.innerHTML='<option value="">در حال دریافت '+title+'…</option>';old.parentElement.firstChild.textContent=title;old.replaceWith(select);
  try{const rows=await lookup(key);if(token!==pageToken||!select.isConnected)return;select.innerHTML='<option value="">همه موارد</option>'+rows.map(r=>`<option value="${r.id}">${esc(r.name||r.title||person(r))}${r.code?' · '+esc(r.code):''}</option>`).join('');select.disabled=false;}catch(e){if(token!==pageToken||!select.isConnected)return;select.innerHTML='<option value="">فهرست دریافت نشد</option>';$('reportError').textContent=e.message;$('reportError').hidden=false;}
 }));
};

async function driverAssignmentPage(token=pageToken){
 if(!can('services','manage'))throw new Error('مجوز مدیریت سرویس لازم است.');
 const d=await api(scoped('/api/v1/operations/driver-assignment'));if(token!==pageToken)return;
 $('content').innerHTML=`<section class="panel sy-assignment-page"><div class="page-intro"><div><h2>تخصیص پیشنهادی راننده</h2><p>سرویس بدون راننده به راننده فعال با خودروی دارای ظرفیت کافی پیشنهاد می‌شود. راننده و خودرو در سرویس فعال دیگری نباید استفاده شده باشند. این پیشنهاد بر پایه ظرفیت است؛ زمان‌بندی شیفت و مسیر جاده‌ای را محاسبه نمی‌کند.</p></div><button id="refreshAssignments">تازه‌سازی</button></div><form id="driverAssignmentForm"><div class="table-wrap sy-report-table-wrap"><table class="sy-report-table"><thead><tr><th>انتخاب</th><th>سرویس</th><th>راننده</th><th>ظرفیت خودرو</th></tr></thead><tbody>${d.items.map((r,i)=>`<tr><td data-label="انتخاب"><input type="checkbox" name="assignment" value="${i}" aria-label="تخصیص ${esc(r.serviceTitle)}" checked></td><td data-label="سرویس">${esc(r.serviceTitle)}</td><td data-label="راننده">${esc(r.driverName)}</td><td data-label="ظرفیت خودرو">${number(r.capacity)}</td></tr>`).join('')||'<tr class="sy-report-empty"><td colspan="4">پیشنهاد قابل ثبت وجود ندارد.</td></tr>'}</tbody></table></div>${d.items.length?'<button type="submit" class="primary">ثبت تخصیص‌های انتخاب‌شده</button>':''}<p id="assignmentSelection" role="status"></p><p id="assignmentFeedback" role="alert"></p></form><h3>سرویس‌های بدون پیشنهاد</h3>${simpleTable(['سرویس'],d.unmatched.map(r=>[r.title]))}</section>`;
 $('refreshAssignments').onclick=()=>driverAssignmentPage(token).catch(e=>notify(e.message,true));
 const assignmentForm=$('driverAssignmentForm');
 const updateSelection=()=>{
  const selected=assignmentForm.querySelectorAll('[name="assignment"]:checked').length;
  $('assignmentSelection').textContent=d.items.length?number(selected)+' پیشنهاد انتخاب شده':'';
  const save=assignmentForm.querySelector('button[type="submit"]');
  if(save)save.disabled=selected===0||assignmentForm.getAttribute('aria-busy')==='true';
 };
 assignmentForm.onchange=updateSelection;updateSelection();
 $('driverAssignmentForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button'),items=[...e.target.querySelectorAll('[name=assignment]:checked')].map(x=>({serviceId:d.items[Number(x.value)].serviceId,driverId:d.items[Number(x.value)].driverId}));if(!items.length){$('assignmentFeedback').textContent='حداقل یک پیشنهاد انتخاب کنید.';return;}assignmentForm.setAttribute('aria-busy','true');button.disabled=true;try{const result=await write('/api/v1/operations/driver-assignment',{items});lookupCache={};notify(number(result.assigned)+' تخصیص ثبت شد.');await driverAssignmentPage(token);}catch(err){if($('assignmentFeedback'))$('assignmentFeedback').textContent=err.message;}finally{if(assignmentForm.isConnected){assignmentForm.setAttribute('aria-busy','false');updateSelection();}}};
}

async function financialPostingDialog(key,id){
 const token=pageToken,[row,defaults,accounts]=await Promise.all([api(scoped('/api/v1/'+key+'/'+id)),api(scoped('/api/v1/accounting/defaults')),lookup('accounts')]);if(token!==pageToken)return;
 const incoming=key==='payments'||(key==='ledger'&&row.direction==='receipt'),money=defaults[row.method==='cash'?'cash':'bank'],counter=defaults[key==='driver-payments'?'driverSalary':'serviceIncome'];
 dialog('ثبت سند حسابداری',`<p>مبلغ ${number(row.amount)} ریال. ثبت، یک سند تراز تأییدشده می‌سازد و ویرایش سابقه مالی را قفل می‌کند. انتقال وجه انجام نمی‌شود.</p><form id="postFinancialForm"><div class="form-grid">${[['debitAccountId','حساب بدهکار',incoming?money:counter],['creditAccountId','حساب بستانکار',incoming?counter:money]].map(([name,title,value])=>`<label>${title}<select name="${name}" required><option value="">انتخاب کنید</option>${accounts.map(a=>`<option value="${a.id}" ${String(value)===String(a.id)?'selected':''}>${esc(a.code+' · '+a.title)}</option>`).join('')}</select></label>`).join('')}</div><button class="primary">ثبت سند / مشاهده سند قبلی</button></form>`);
 $('postFinancialForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const d=await write('/api/v1/accounting/post/'+key+'/'+id,Object.fromEntries(new FormData(e.target)));closeDialog();await journalEditor(d.documentId,pageToken);}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
}

async function dailySchedulePage(token=pageToken){
 $('content').innerHTML=`<section class="panel sy-daily-schedule">
  <header class="sy-operation-header"><div><span class="sy-operation-kicker">کنترل برنامه رانندگان</span><h2>برنامه روزانه و تداخل شیفت‌ها</h2><p>شیفت‌های فعال، هم‌پوشانی زمانی سرویس‌ها و اطلاعات ناقص در یک گزارش مشخص نمایش داده می‌شوند.</p></div><button type="button" id="dailyScheduleRefresh">تازه‌سازی</button></header>
  <form id="dailyScheduleFilters" class="filters sy-operation-filters" aria-label="فیلتر گزارش برنامه روزانه">
   <label>تاریخ بررسی<input type="date" name="date" required value="${todayISO()}"></label>
   <label>شنبه آغاز هفته اول<input type="date" name="weekAnchor"></label>
   <button type="submit" class="primary" id="dailyScheduleSubmit">محاسبه برنامه</button>
  </form>
  <p class="sy-daily-schedule-note">گزارش بر اساس زمان ثبت‌شده شیفت‌های مدرسه و سرویس است. زمان واقعی مسیر یا ترافیک محاسبه نمی‌شود و هیچ تخصیصی تغییر نمی‌کند.</p>
  <div id="dailyScheduleResult" aria-live="polite"></div>
 </section>`;
 let request=0;
 const load=async()=>{
  const version=++request,box=$('dailyScheduleResult'),button=$('dailyScheduleSubmit');
  if(!box||!button)return;
  button.disabled=true;box.setAttribute('aria-busy','true');box.innerHTML='<p class="loading" role="status">در حال دریافت برنامه و بررسی تداخل‌ها…</p>';
  try{
   const d=await api(scoped('/api/v1/operations/daily-schedule?'+new URLSearchParams(new FormData($('dailyScheduleFilters')))));
   if(token!==pageToken||version!==request||!box.isConnected)return;
   const items=Array.isArray(d.items)?d.items:[],conflicts=Array.isArray(d.conflicts)?d.conflicts:[],issues=Array.isArray(d.issues)?d.issues:[];
   box.innerHTML=`<div class="sy-daily-summary"><div><button type="button" data-schedule-section="active"><span>شیفت‌های برنامه‌ریزی‌شده</span><strong>${number(items.length)}</strong></button></div><div class="${conflicts.length?'sy-daily-alert':''}"><button type="button" data-schedule-section="conflicts"><span>تداخل زمانی</span><strong>${number(conflicts.length)}</strong></button></div><div class="${issues.length?'sy-daily-caution':''}"><button type="button" data-schedule-section="issues"><span>اطلاعات نیازمند تکمیل</span><strong>${number(issues.length)}</strong></button></div></div>
   <section class="sy-daily-section"><h3 id="schedule-active" tabindex="-1">شیفت‌های فعال</h3>${simpleTable(['سرویس','شیفت','ورود','خروج','راننده'],items.map(row=>[row.service,row.shift,row.start,row.end,row.driver||'تخصیص نیافته']))}</section>
   <section class="sy-daily-section"><h3 id="schedule-conflicts" tabindex="-1">تداخل‌های نیازمند بررسی</h3>${conflicts.length?simpleTable(['سرویس اول','سرویس دوم','علت'],conflicts.map(row=>[row.first,row.second,row.reason])):'<p class="sy-daily-empty">تداخل زمانی ثبت نشده است.</p>'}</section>
   <section class="sy-daily-section"><h3 id="schedule-issues" tabindex="-1">موارد ناقص</h3>${issues.length?simpleTable(['سرویس','علت'],issues.map(row=>[row.service,row.reason])):'<p class="sy-daily-empty">مورد ناقصی در اطلاعات برنامه پیدا نشد.</p>'}</section>`;
   box.querySelectorAll('[data-schedule-section]').forEach(button=>button.onclick=()=>{
    const section=$('schedule-'+button.dataset.scheduleSection);
    section.focus({preventScroll:true});
    section.scrollIntoView({block:'start'});
   });
  }catch(err){
   if(token===pageToken&&version===request&&box.isConnected)box.innerHTML='<div class="sy-operation-error" role="alert"><strong>دریافت برنامه ناموفق بود</strong><p>'+esc(err.message)+'</p><button type="button" id="dailyScheduleRetry">تلاش مجدد</button></div>';
   $('dailyScheduleRetry')?.addEventListener('click',load);
  }finally{if(token===pageToken&&version===request&&button.isConnected){button.disabled=false;box.removeAttribute('aria-busy');}}
 };
 $('dailyScheduleFilters').onsubmit=e=>{e.preventDefault();load();};
 $('dailyScheduleRefresh').onclick=load;
 await load();
}

async function installmentPage(){
 const token=pageToken,allowed=can('installments','manage');
 $('content').innerHTML=`<section class="panel sy-installment-panel">
  <header class="sy-installment-heading"><div><span class="sy-operation-kicker">برنامه مالی دانش‌آموزان</span><h2>پیش‌پرداخت و اقساط</h2><p>ابتدا شرایط قرارداد را وارد کنید، جدول اقساط را بررسی کنید و در مرحله آخر ایجاد فاکتورها را تأیید کنید.</p></div><span class="sy-installment-step">مرحله ۱ از ۲ · تنظیم برنامه</span></header>
  <form id="installmentPreview" class="sy-installment-form" aria-label="محاسبه برنامه اقساط">
   <div class="sy-installment-fields">
    <label class="sy-installment-student">جست‌وجوی دانش‌آموز<input id="installmentStudentSearch" type="search" autocomplete="off" placeholder="نام یا نام خانوادگی دانش‌آموز"></label>
    <label class="sy-installment-student">دانش‌آموز<select name="studentId" id="installmentStudent" required><option value="">در حال دریافت دانش‌آموزان…</option></select></label>
    <label>تاریخ صدور فاکتورها<input name="issueDate" type="date" required value="${todayISO()}"></label>
    <label>مبلغ کل قرارداد (ریال)<input name="total" type="text" data-installment-money inputmode="numeric" required autocomplete="off" placeholder="مثلاً ۱۲۰٬۰۰۰٬۰۰۰" dir="ltr"></label>
    <label>پیش‌پرداخت (ریال)<input name="advance" type="text" data-installment-money inputmode="numeric" required autocomplete="off" value="۰" dir="ltr"></label>
    <label>تعداد اقساط<input name="count" type="number" min="1" max="24" value="9" required></label>
    <label class="sy-installment-dates">سررسید اقساط به تاریخ شمسی<textarea name="dates" rows="5" required placeholder="۱۴۰۵/۰۸/۰۱&#10;۱۴۰۵/۰۹/۰۱&#10;۱۴۰۵/۱۰/۰۱" aria-describedby="installmentDatesHelp"></textarea><small id="installmentDatesHelp">برای هر قسط یک تاریخ شمسی در خط جدا وارد کنید؛ تعداد تاریخ‌ها باید با تعداد اقساط برابر باشد.</small></label>
   </div>
   <div class="sy-installment-form-footer"><p>ثبت برنامه، فاکتورهای پرداخت‌نشده ایجاد می‌کند و به معنای دریافت وجه نیست.</p><button class="primary" type="submit" id="installmentCalculate">محاسبه و پیش‌نمایش اقساط</button></div>
  </form>
  <section id="installmentResult" class="sy-installment-result" role="status" aria-live="polite"></section>
  </section>`;
 const form=$('installmentPreview'),select=$('installmentStudent'),search=$('installmentStudentSearch'),result=$('installmentResult'),calculate=$('installmentCalculate');
 let approved=null,busy=false,sequence=0,requestKey=crypto.randomUUID();
 const normalize=value=>String(value??'').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).trim();
 const monetary=value=>{
  const raw=normalize(value);
  if(!/^(?:[0-9]{1,12}|[0-9]{1,3}(?:[٬,][0-9]{3}){1,4})$/.test(raw))return null;
  const digits=raw.replace(/[٬,]/g,'');
  return digits.length<=12?digits:null;
 };
 const invalidate=()=>{
  sequence++;approved=null;
  if(result.isConnected)result.innerHTML='<p class="sy-installment-hint" role="status">اطلاعات تغییر کرده است؛ برای ثبت باید پیش‌نمایش جدید تهیه کنید.</p>';
 };
 form.addEventListener('input',invalidate);
 form.addEventListener('change',invalidate);
 for(const input of form.querySelectorAll('[data-installment-money]')){
  input.addEventListener('input',()=>{
   const numeric=monetary(input.value);
   input.setCustomValidity(numeric===null?'مبلغ را به ریال، بدون اعشار و با جداکننده هزارگان صحیح وارد کنید.':'');
   if(numeric!==null)input.value=persianDigits(numeric.replace(/\B(?=(\d{3})+(?!\d))/g,'٬'));
  });
 }
 let students;
 try{students=await lookup('students');}
 catch(err){
  if(token===pageToken&&select.isConnected){
   select.innerHTML='<option value="">دریافت فهرست دانش‌آموزان ناموفق بود</option>';
   result.innerHTML='<div class="sy-operation-error" role="alert"><strong>دانش‌آموزان بارگذاری نشدند</strong><p>'+esc(err.message)+'</p><button type="button" id="installmentRetryStudents">تلاش مجدد</button></div>';
   $('installmentRetryStudents').onclick=()=>installmentPage();
  }
  return;
 }
 if(token!==pageToken||!select.isConnected)return;
 const sorted=[...students].sort((a,b)=>person(a).localeCompare(person(b),'fa'));
 const normalizeSearch=value=>String(value??'').replace(/ي|ى/g,'ی').replace(/ك/g,'ک').replace(/[\u200c\s]+/g,' ').trim().toLocaleLowerCase('fa');
 const filterStudents=()=>{
  const selected=select.value,term=normalizeSearch(search.value);
  const list=sorted.filter(row=>!term||normalizeSearch(person(row)).includes(term)).slice(0,250);
  select.replaceChildren(new Option(list.length?'انتخاب کنید':'دانش‌آموزی پیدا نشد',''));
  for(const row of list)select.add(new Option(person(row),String(row.id)));
  if(selected&&!list.some(row=>String(row.id)===selected)){
   const row=sorted.find(x=>String(x.id)===selected);
   if(row)select.add(new Option(person(row),selected));
  }
  select.value=selected;
 };
 search.addEventListener('input',filterStudents);
 filterStudents();
 const payload=()=>{
  const total=monetary(form.elements.total.value),advance=monetary(form.elements.advance.value);
  if(total===null||advance===null)throw new Error('مبالغ قرارداد و پیش‌پرداخت را به ریال، بدون اعشار وارد کنید.');
  const count=Number(form.elements.count.value);
  const dates=String(form.elements.dates.value).trim().split(/\s+/).filter(Boolean);
  if(!Number.isInteger(count)||count<1||count>24||dates.length!==count)throw new Error('تعداد سررسیدهای شمسی باید دقیقاً با تعداد اقساط (۱ تا ۲۴) برابر باشد.');
  if(!form.elements.studentId.value)throw new Error('دانش‌آموز را انتخاب کنید.');
  if(!form.elements.issueDate.value)throw new Error('تاریخ صدور فاکتورها الزامی است.');
  return {studentId:Number(form.elements.studentId.value),issueDate:form.elements.issueDate.value,
   total,advance,count,dates:dates.map(date=>ServiceYarDates.toISO(date))};
 };
 const showPreview=(d,body)=>{
  const studentName=select.selectedOptions[0]?.textContent||'دانش‌آموز';
  result.innerHTML=`<div class="sy-installment-preview-head"><div><span class="sy-operation-kicker">مرحله ۲ از ۲ · بازبینی و تأیید</span><h3>پیش‌نمایش برنامه ${esc(studentName)}</h3></div><span class="sy-installment-status">ثبت‌نشده</span></div>
  <div class="sy-installment-summary"><div><span>مبلغ قرارداد</span><strong>${number(d.total)} ریال</strong></div><div><span>پیش‌پرداخت</span><strong>${number(d.advance)} ریال</strong></div><div><span>مانده قابل تقسیط</span><strong>${number(d.remaining)} ریال</strong></div><div><span>تعداد اقساط</span><strong>${number(d.items.length)}</strong></div></div>
  ${simpleTable(['شماره قسط','تاریخ سررسید شمسی','مبلغ (ریال)'],d.items.map(row=>[number(row.number),dateText(row.dueDate),number(row.amount)]))}
  <div class="sy-installment-confirm"><p>با تأیید، برای پیش‌پرداخت (در صورت وجود) و هر قسط، فاکتور باز صادر می‌شود. هیچ دریافتی ثبت نخواهد شد.</p>${allowed?'<button type="button" class="primary" id="installmentFinalize">تأیید و ایجاد فاکتورها</button>':'<span>حساب شما مجوز ثبت نهایی ندارد.</span>'}</div>`;
  $('installmentFinalize')?.addEventListener('click',async()=>{
   if(busy||!approved)return;
   let current;
   try{current=payload();}catch(e){notify(e.message,true);invalidate();return;}
   if(JSON.stringify(current)!==approved.key){invalidate();notify('اطلاعات تغییر کرده‌اند؛ پیش‌نمایش تازه تهیه کنید.',true);return;}
   if(!confirm('برای '+studentName+'، '+number(d.items.length+(d.advance>0?1:0))+' فاکتور ایجاد شود؟'))return;
   const button=$('installmentFinalize');button.disabled=true;busy=true;
   try{
    const created=await write('/api/v1/operations/installments',{...approved.body,approvalDigest:d.approvalDigest,requestKey},'POST');
    if(token!==pageToken||!result.isConnected)return;
    approved=null;
    result.innerHTML='<div class="sy-installment-success" role="status"><strong>برنامه اقساط ثبت شد.</strong><p>'+number(created.invoiceIds?.length||0)+' فاکتور برای دانش‌آموز ایجاد شد. دریافت وجه انجام نشده است.</p><a href="#invoices">مشاهده فاکتورها</a></div>';
    notify('برنامه اقساط با موفقیت ثبت شد.');
    requestKey=crypto.randomUUID();
   }catch(e){notify(e.message,true);if(button.isConnected)button.disabled=false;}
   finally{busy=false;}
  });
 };
 form.onsubmit=async e=>{
  e.preventDefault();if(busy)return;
  let body;
  try{body=payload();}catch(err){notify(err.message,true);result.innerHTML='<p class="error" role="alert">'+esc(err.message)+'</p>';return;}
  busy=true;approved=null;calculate.disabled=true;
  const version=++sequence;result.innerHTML='<p class="loading" role="status">در حال محاسبه برنامه اقساط…</p>';
  try{
   const d=await write('/api/v1/operations/installments/preview',body,'POST');
   if(token!==pageToken||version!==sequence||!result.isConnected)return;
   if(!d.approvalDigest)throw new Error('تأییدیه پیش‌نمایش از سرور دریافت نشد؛ ثبت امکان‌پذیر نیست.');
   approved={body,key:JSON.stringify(body)};
   showPreview(d,body);
  }catch(err){
   if(token===pageToken&&version===sequence&&result.isConnected)
    result.innerHTML='<p class="error" role="alert">'+esc(err.message)+'</p>';
  }finally{busy=false;if(calculate.isConnected)calculate.disabled=false;}
 };
}

function attachStudentTariff(form,id){
 const box=document.createElement('section');box.className='financial-calculator sy-fee-source-preview';
 box.innerHTML='<div class="sy-fee-preview-header"><strong>محاسبه هزینه سرویس</strong><button type="button" data-tariff-quote>محاسبه مجدد</button></div><p role="status" aria-live="polite">مبنای پیش‌فرض: تعرفه سازمان تاکسیرانی</p>';
 form.querySelector('.form-grid').after(box);
 const button=box.querySelector('button'),status=box.querySelector('p');
 const fee=form.elements.monthlyFee,source=form.elements.feeSource;
 if(!fee||!source)return;
 const input=()=>({linkedStudentId:id||undefined,schoolId:form.elements.schoolId?.value||'',academicYear:form.elements.academicYear?.value||'1405-1406',
  distanceKm:form.elements.tariffDistanceKm?.value||'',vehicleClass:form.elements.tariffVehicleClass?.value||'',
  feeSource:source.value||'taxi',monthlyFee:fee.value||'0'});
 let request=0,timer;
 const sync=()=>{
  const agreed=source.value==='agreed';fee.readOnly=!agreed;
  fee.closest('label')?.classList.toggle('sy-pricing-agreed',agreed);
  if(agreed)status.textContent='مبلغ ماهانه توافقی را وارد کنید؛ هزینه سالانه بر اساس مقطع محاسبه می‌شود.';
 };
 const calculate=async()=>{
  const current=++request,data=input();
  sync();if(data.feeSource==='agreed')return;
  if(!data.schoolId||(!data.vehicleClass&&!id)||!data.distanceKm){
   status.textContent='برای محاسبه خودکار، مدرسه، مسافت و کلاس خودرو را مشخص کنید.';return;
  }
  button.disabled=true;status.textContent='در حال محاسبه '+(data.feeSource==='taxi'?'تعرفه تاکسیرانی':'تعرفه شرکت')+'…';
  try{
   const result=await write('/api/v1/operations/tariffs/quote',data);
   if(current!==request||!form.isConnected)return;
   if(result.assignedVehicleClass!=null){
    form.elements.tariffVehicleClass.value=String(result.assignedVehicleClass);
    form.elements.tariffVehicleClass.disabled=true;
   }
   fee.value=String(result.monthlyFee);fee.dispatchEvent(new Event('input',{bubbles:true}));
   const shares=result.parentFareComponents;
   status.textContent='مبلغ ماهانه اولیا: '+number(result.monthlyFee)+' ریال · مبنای راننده: '+number(result.driverBaseMonthly)+' ریال'
    +(shares?' · سهم‌های بهای اولیا: نظارت '+number(shares.supervisionParentShare)
      +'، هماهنگی '+number(shares.coordinationParentShare)
      +'، بالاسری '+number(shares.overheadParentShare)
      +'، گواهی '+number(shares.certificateParentShare)
      +'، بیمه '+number(shares.insuranceParentShare)
      +' ریال. سهم هماهنگی تا مصوبه شورای ترافیک قابل توزیع نیست؛ هیچ‌یک از این سهم‌ها به‌تنهایی کسورات حقوق راننده نیستند.':'');
  }catch(e){if(current===request&&form.isConnected)status.textContent=e.message;}
  finally{if(current===request&&form.isConnected)button.disabled=false;}
 };
 const schedule=()=>{request++;button.disabled=false;clearTimeout(timer);timer=setTimeout(calculate,320);};
 for(const name of ['schoolId','academicYear','tariffDistanceKm','tariffVehicleClass','feeSource']){
  form.elements[name]?.addEventListener('change',schedule);
 }
 source.addEventListener('change',sync);
 form.elements.tariffDistanceKm?.addEventListener('input',schedule);
 button.onclick=calculate;
 sync();schedule();
}
function canConfigureDriverService(){
 return can('services','manage')&&can('services')&&can('students')&&can('schools')&&can('drivers');
}
function normalizeServiceSearch(value){
 return ServiceYarDates.digits(String(value??'')).normalize('NFKC').replace(/ي|ى/g,'ی').replace(/ك/g,'ک').replace(/[\s\u200c\u200d]+/g,'').toLowerCase();
}
function driverServiceDialog(driver){
 if(!driver)return;
 const token=pageToken;
 dialog('سرویس‌های '+person(driver),`
  <div class="driver-service-intro">
   <p>برای تخصیص دانش‌آموز، یک سرویس موجود را انتخاب کنید یا سرویس جدید بسازید.</p>
   ${Number(driver.is_active)===1&&driver.status==='active'
    ?`<a class="route-link" href="#services/new/${driver.id}">ساخت سرویس برای این راننده</a>`
    :'<p class="muted">برای ساخت سرویس، راننده باید فعال و تأیید شده باشد.</p>'}
  </div>
  <div id="driverServiceList" aria-live="polite"></div>`);
 const list=$('driverServiceList');
 const load=async(page=1)=>{
  list.textContent='در حال دریافت سرویس‌ها…';
  try{
   const data=await api(scoped('/api/v1/services?'+new URLSearchParams({driverId:driver.id,page,limit:25})));
   if(token!==pageToken||!list.isConnected)return;
   list.innerHTML=data.items.length?`<div class="driver-service-list">${data.items.map(row=>`
    <section class="driver-service-item">
     <div><strong>${esc(row.title)}</strong><p>ظرفیت ${number(row.capacity)} · ${esc(statuses[row.status]||row.status)}${Number(row.is_active)===0?' · غیرفعال':''}</p></div>
     <div class="member-actions">
      <button type="button" ${Number(row.is_active)===1&&row.status!=='cancelled'?'class="primary"':''} data-driver-members="${row.id}">${Number(row.is_active)===1&&row.status!=='cancelled'?'تخصیص دانش‌آموز':'مشاهده دانش‌آموزان'}</button>
      <button type="button" data-driver-edit-service="${row.id}">تنظیم سرویس</button>
     </div>
    </section>`).join('')}</div>`:'<p class="empty">برای این راننده هنوز سرویسی ثبت نشده است.</p>';
   if(data.pagination.pages>1){
    list.insertAdjacentHTML('beforeend',`<div class="pager"><button type="button" data-driver-page="${page-1}" ${page===1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(data.pagination.pages)}</span><button type="button" data-driver-page="${page+1}" ${page>=data.pagination.pages?'disabled':''}>بعدی</button></div>`);
   }
   list.querySelectorAll('[data-driver-page]').forEach(button=>button.onclick=()=>load(Number(button.dataset.driverPage)));
   list.querySelectorAll('[data-driver-members]').forEach(button=>button.onclick=()=>serviceMembers(Number(button.dataset.driverMembers),()=>driverServiceDialog(driver)));
   list.querySelectorAll('[data-driver-edit-service]').forEach(button=>button.onclick=()=>formResource('services',Number(button.dataset.driverEditService),()=>driverServiceDialog(driver)));
  }catch(err){
   if(list.isConnected)list.textContent=err.message;
  }
 };
 load();
}
async function newServicePage(token,initialDriverId){
 if(!canConfigureDriverService())throw new Error('برای ثبت سرویس به مجوز مدرسه، راننده و دانش‌آموز نیاز است.');
 $('pageTitle').textContent='ثبت سرویس';
 const [schools,drivers,students,vehicles,feeSettings]=await Promise.all([lookup('schools'),lookup('drivers'),lookup('students',true),lookup('vehicles'),api(scoped('/api/v1/operations/driver-deduction-rates'))]);if(token!==pageToken)return;
 const driverRates={...defaultDriverRates,...feeSettings};
 $('content').innerHTML=`<form id="newServiceSetup" class="sy-new-service"><div class="sy-new-service-heading"><div><span class="sy-operation-kicker">راه‌اندازی سرویس</span><h2>تعریف سرویس جدید</h2><p>مدرسه و راننده را تعیین کنید، دانش‌آموزان واجد شرایط را انتخاب کنید و پس از کنترل ظرفیت و مبالغ، سرویس را ثبت کنید.</p></div><span id="serviceSetupProgress" class="sy-new-service-progress" role="status" aria-live="polite">مرحله ۱ از ۳ · انتخاب مدرسه و راننده</span></div><div class="toolbar sy-new-service-toolbar"><button type="button" id="showServiceStudents" disabled>افزودن دانش‌آموز</button><button type="button" id="quoteServiceStudents" disabled>دریافت قیمت و مسیر</button></div><div class="dashboard-grid sy-new-service-cards"><section class="panel"><h2>۱. مدرسه سرویس</h2><p id="serviceSchoolSummary">برای شروع، مدرسه یا مدارس سرویس را انتخاب کنید.</p><button type="button" id="pickServiceSchools">انتخاب مدرسه</button></section><section class="panel"><h2>۲. راننده سرویس</h2><p id="serviceDriverSummary">راننده را انتخاب کنید تا قیمت‌ها محاسبه شوند.</p><button type="button" id="pickServiceDriver">انتخاب راننده</button> <button type="button" id="editServiceVehicle" disabled>نمایش اطلاعات خودرو و ویرایش ظرفیت</button></section></div><section class="panel sy-new-service-students" id="serviceStudentSelection" hidden><div class="sy-new-service-section-heading"><h2>۳. دانش‌آموزان سرویس</h2><span id="serviceStudentsCount" role="status"></span></div><p id="serviceCapacityWarning" role="alert" hidden></p><div class="sy-inline-student-selected" id="selectedServiceStudents"></div><button type="button" class="primary" id="changeServiceStudents">+ افزودن دانش‌آموز</button><div id="inlineStudentPicker" class="sy-inline-student-picker" hidden><div class="service-picker-tools"><label>نام یا کد ملی<input type="search" id="studentPickerName" placeholder="جستجوی لحظه‌ای دانش‌آموز" autocomplete="off"></label><label>نشانی منزل<input type="search" id="studentPickerAddress" placeholder="جستجوی نشانی"></label><label class="service-picker-toggle"><input type="checkbox" id="studentPickerMehr"> فقط دارای سابقه مهر این راننده</label></div><p id="studentPickerCount" role="status"></p><div class="sy-inline-student-results" id="studentPickerRows"></div><button type="button" id="closeInlineStudents">بستن فهرست جستجو</button></div></section><section class="panel sy-new-service-details" id="serviceSetupDetails" hidden><h2>۴. مشخصات و هزینه سرویس</h2><div class="form-grid"><label class="sy-service-pricing-source">مبنای محاسبه هزینه سرویس<select name="feeSource"><option value="taxi" selected>نرخ سازمان تاکسیرانی (پیش‌فرض)</option><option value="agency">تعرفه اختصاصی شرکت</option><option value="agreed">مبالغ توافقی ثبت‌شده دانش‌آموزان</option></select><small>مبلغ اولیا و مبنای پرداخت راننده مطابق روش انتخابی محاسبه می‌شوند.</small></label><label>نوع خودروی سرویس (جستجوی لحظه‌ای)<input type="search" name="vehicleModel" placeholder="نام مدل خودرو را جستجو کنید" required></label><label>کلاس تعرفه خودرو (قابل تغییر)<select name="vehicleClass" required><option value="">ابتدا خودرو را انتخاب کنید</option><option value="1">کلاس ۱</option><option value="2">کلاس ۲</option><option value="3">کلاس ۳</option></select><small>کلاس پیشنهادی از نوع خودرو تعیین می‌شود و می‌توانید آن را اصلاح کنید. تغییر کلاس روی خودروی ثبت‌شده راننده ذخیره می‌شود.</small></label><label>عنوان سرویس<input name="title" maxlength="200" required></label><label>ظرفیت<input name="capacity" type="number" min="1" max="200" value="4" required></label><label>شیفت<input name="shift" maxlength="50"></label><label>مبلغ توافقی راننده (ریال)<input name="agreedAmountDisplay" type="text" inputmode="numeric" dir="ltr" autocomplete="off" value="۰" aria-describedby="serviceAgreedHelp"><input name="agreedAmount" type="hidden" value="0"><small id="serviceAgreedHelp">مبلغ ریالی بدون اعشار، با جداکننده هزارگان</small></label><p class="sy-service-year-policy">مبلغ سالانه بر اساس مقطع هر دانش‌آموز محاسبه می‌شود: ابتدایی ۸٫۵ ماه، متوسطه و دبیرستان ۹ ماه.</p></div><div id="serviceQuoteResult" role="status" aria-live="polite"></div><div class="sy-new-service-actions"><span>اطلاعات مدرسه، راننده، دانش‌آموزان و ظرفیت پیش از ثبت کنترل می‌شوند.</span><button type="submit" class="primary" id="createService" disabled>تأیید و ثبت سرویس</button></div></section></form>`;
 if(initialDriverId){const back=document.createElement('a');back.href='#drivers';back.className='route-link';back.textContent='بازگشت به مدیریت رانندگان';$('newServiceSetup').before(back);}
 const form=$('newServiceSetup'),chosen=new Set(),show=$('showServiceStudents'),quote=$('quoteServiceStudents'),result=$('serviceQuoteResult'),save=$('createService');
 let selectedSchools=[],driverId=null,revision=0,saving=false,newModelPicker=null;
 if(window.ServiceYarVehicleModels)
  void window.ServiceYarVehicleModels.attach(form.elements.vehicleModel,form.elements.vehicleClass,{
   onChange:()=>{revision++;refreshSave();if(chosen.size)result.textContent='نوع یا کلاس خودرو تغییر کرده است؛ پیش‌نمایش تعرفه را دوباره دریافت کنید.';}
  }).then(picker=>{
   newModelPicker=picker;
   if(driverId&&picker)picker.setVehicle(vehicleFor(drivers.find(d=>Number(d.id)===driverId)));
   refreshSave();
  }).catch(error=>{if(form.isConnected){refreshSave();if(!vehicleFor(drivers.find(d=>Number(d.id)===driverId)))saveFeedback.textContent='دریافت فهرست خودروها ناموفق بود: '+error.message;}});
 const driverVehicleCache=new Map();
 const vehicleFor=driver=>driverVehicleCache.get(Number(driver?.vehicle_id))||vehicles.find(v=>Number(v.id)===Number(driver?.vehicle_id));
 const loadDriverVehicle=async driver=>{
  const id=Number(driver?.vehicle_id);
  if(!id||vehicleFor(driver))return vehicleFor(driver);
  const record=await api(scoped('/api/v1/vehicles/'+id));
  if(record)driverVehicleCache.set(id,record);
  return record;
 };
 const vehicleClass=driver=>vehicleFor(driver)?.tariff_vehicle_class;
 const classLabel=driver=>vehicleClass(driver)?' · کلاس تعرفه '+number(vehicleClass(driver)):' · کلاس تعرفه نامشخص؛ از قسمت خودروها تکمیل کنید';
 const driverCaption=driver=>[person(driver),vehicleFor(driver)?.model].filter(Boolean).join(' · ')+classLabel(driver);
 const hasMehrDriver=student=>Number(student.mehr_driver_id)===Number(driverId);
 const schoolIds=()=>selectedSchools;

 const normalizeAgreed=value=>String(value??'').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).trim();
 const validAgreed=value=>/^(?:[0-9]{1,15}|[0-9]{1,3}(?:[٬,][0-9]{3})+)$/.test(normalizeAgreed(value));
 const syncAgreed=()=>{
  const input=form.elements.agreedAmountDisplay,raw=normalizeAgreed(input.value);
  const value=raw.replace(/[٬,]/g,'');
  const valid=validAgreed(input.value)&&value.length<=15;
  input.setCustomValidity(valid?'':'مبلغ توافقی باید عدد صحیح ریالی و فاقد اعشار باشد.');
  form.elements.agreedAmount.value=valid?value:'';
  if(valid)input.value=persianDigits(value.replace(/\B(?=(\d{3})+(?!\d))/g,'٬'));
  return valid;
 };
 const validationIssues=()=>{
  const issues=[],driver=drivers.find(d=>Number(d.id)===driverId),vehicle=vehicleFor(driver);
  const model=form.elements.vehicleModel.value.trim(),cls=String(form.elements.vehicleClass.value);
  const registered=String(vehicle?.vehicle_trim||vehicle?.model||vehicle?.vehicle_system||'').trim();
  const unchanged=!!vehicle&&!!registered&&model===registered&&cls===String(vehicle.tariff_vehicle_class);
  if(!selectedSchools.length)issues.push('مدرسه سرویس را انتخاب کنید.');
  if(!driverId)issues.push('راننده سرویس را انتخاب کنید.');
  else if(!vehicle)issues.push('اطلاعات خودروی راننده دریافت نشده یا خودرو ثبت نشده است.');
  if(vehicle&&Number(vehicle.is_active??1)!==1)issues.push('خودروی راننده غیرفعال است.');
  if(!model)issues.push('نوع خودرو را مشخص کنید.');
  else if(!unchanged&&!newModelPicker?.valid)issues.push('مدل جدید خودرو را از نتایج فهرست مدل‌های تأییدشده انتخاب کنید.');
  if(!['1','2','3'].includes(cls))issues.push('کلاس تعرفه خودرو را انتخاب کنید.');
  if(!chosen.size)issues.push('حداقل یک دانش‌آموز انتخاب کنید.');
  if(!form.elements.title.value.trim())issues.push('عنوان سرویس را وارد کنید.');
  if(!form.elements.capacity.checkValidity())issues.push('ظرفیت باید عدد صحیح بین ۱ تا ۲۰۰ باشد.');
  else if(chosen.size>Number(form.elements.capacity.value))issues.push('تعداد دانش‌آموزان بیشتر از ظرفیت سرویس است.');
  if(form.elements.feeSource.value==='agreed'&&!syncAgreed())issues.push('مبلغ توافقی راننده را به‌صورت عدد صحیح ریالی وارد کنید.');
  return issues;
 };
 const saveFeedback=document.createElement('div');
 saveFeedback.id='serviceSaveFeedback';saveFeedback.className='sy-service-save-feedback';saveFeedback.setAttribute('role','status');saveFeedback.setAttribute('aria-live','polite');
 save.parentElement.before(saveFeedback);save.setAttribute('aria-describedby',saveFeedback.id);
 const refreshSave=()=>{
  const issues=validationIssues();
  save.disabled=saving||issues.length>0;
  saveFeedback.textContent=saving?'در حال ثبت سرویس…':issues.length?'موارد لازم برای فعال شدن ثبت: '+issues.join(' · '):'اطلاعات لازم تکمیل است؛ سرویس آماده ثبت است.';
  saveFeedback.dataset.state=saving?'busy':issues.length?'warning':'ready';
 };
 const sync=()=>{
  const agreedMode=form.elements.feeSource.value==='agreed';
  form.elements.agreedAmountDisplay.closest('label').hidden=!agreedMode;
  form.elements.agreedAmountDisplay.required=agreedMode;
  if(!agreedMode){form.elements.agreedAmountDisplay.setCustomValidity('');form.elements.agreedAmount.value='0';}
  for(const s of students)if(!selectedSchools.includes(Number(s.school_id)))chosen.delete(Number(s.id));
  if(!form.elements.title.value.trim()&&driverId&&selectedSchools.length){const driver=drivers.find(d=>Number(d.id)===driverId);const school=schools.find(sc=>Number(sc.id)===selectedSchools[0]);form.elements.title.value=['سرویس',school?.name,driver?person(driver):''].filter(Boolean).join(' - ').slice(0,200);}
  show.disabled=!selectedSchools.length||!driverId;quote.disabled=show.disabled||!chosen.size; $('editServiceVehicle').disabled=!vehicleFor(drivers.find(d=>Number(d.id)===driverId))||!can('vehicles','manage')||saving;refreshSave();
  $('serviceStudentSelection').hidden=false;$('changeServiceStudents').disabled=show.disabled;$('serviceSetupDetails').hidden=show.disabled;
  renderInlineStudents();
  const capacity=Number(form.elements.capacity.value),exceeded=chosen.size>capacity;
  const warning=$('serviceCapacityWarning');const registeredVehicle=vehicleFor(drivers.find(d=>Number(d.id)===driverId)||{});
  const expands=registeredVehicle&&capacity>Number(registeredVehicle.capacity);
  warning.hidden=!exceeded&&!expands;
  warning.textContent=exceeded?'تعداد دانش‌آموزان ('+number(chosen.size)+') بیشتر از ظرفیت سرویس ('+number(capacity)+') است.':expands?'با ثبت سرویس، ظرفیت ثبت‌شده خودرو از '+number(registeredVehicle.capacity)+' به '+number(capacity)+' نفر افزایش می‌یابد.':'';
  $('serviceStudentsCount').textContent=number(chosen.size)+' دانش‌آموز انتخاب شده · ظرفیت '+number(capacity);
  $('serviceSetupProgress').textContent=!driverId||!selectedSchools.length?'مرحله ۱ از ۳ · انتخاب مدرسه و راننده':!chosen.size?'مرحله ۲ از ۳ · انتخاب دانش‌آموزان':'مرحله ۳ از ۳ · بازبینی مشخصات و مبلغ';
  revision++;result.textContent='برای مشاهده برآورد هزینه و مسیر، «دریافت قیمت و مسیر» را انتخاب کنید. ثبت سرویس بدون دریافت پیش‌نمایش نیز امکان‌پذیر است.';
 };
 const matches=(value,term)=>normalizeServiceSearch(value).includes(normalizeServiceSearch(term));
 const checklist=(rows,selected,caption)=>rows.map(r=>`<label><input type="checkbox" value="${r.id}" ${selected.has(Number(r.id))?'checked':''}>${esc(caption(r))}</label>`).join('')||'<p class="empty">موردی برای انتخاب پیدا نشد.</p>';
 const togglePick=(event,selected)=>{if(event.target.type!=='checkbox')return;const id=Number(event.target.value);if(event.target.checked)selected.add(id);else selected.delete(id);};
 $('pickServiceSchools').onclick=()=>{
  const pending=new Set(selectedSchools);
  dialog('انتخاب مدرسه','<label>جست‌وجو در نام مدرسه<input type="search" id="schoolPickerSearch"></label><div class="service-picker-list" id="schoolPickerRows"></div><div class="member-actions"><button type="button" id="selectAllSchools">انتخاب همه</button><button type="button" class="primary" id="confirmSchools">تأیید انتخاب</button></div>');
  const search=$('schoolPickerSearch'),rows=$('schoolPickerRows'),filtered=()=>schools.filter(s=>matches(s.name,search.value));
  const render=()=>{rows.innerHTML=checklist(filtered(),pending,s=>[s.name,s.address].filter(Boolean).join(' · '));};
  search.oninput=render;rows.onchange=e=>togglePick(e,pending);$('selectAllSchools').onclick=()=>{for(const s of filtered())pending.add(Number(s.id));render();};
  $('confirmSchools').onclick=()=>{selectedSchools=[...pending];$('serviceSchoolSummary').textContent=schools.filter(s=>pending.has(Number(s.id))).map(s=>s.name).join('، ')||'برای شروع، مدرسه یا مدارس سرویس را انتخاب کنید.';$('pickServiceSchools').textContent=pending.size?'تغییر مدرسه':'انتخاب مدرسه';closeDialog();sync();};render();search.focus();
 };
 const eligibleSchool=s=>selectedSchools.includes(Number(s.school_id));
 const blocked=s=>!!s.assigned_service_id||!!s.driver_name||s.service_status==='assigned'||Number(s.is_active)===0||s.service_status==='cancelled';
 let quoteTimer;
 const selectDriver=driver=>{
  const vehicle=vehicleFor(driver);
  driverId=Number(driver.id);
  const vehicleMax=vehicle&&Number.isInteger(Number(vehicle.capacity))&&Number(vehicle.capacity)>0?Math.min(200,Number(vehicle.capacity)):200;
  form.elements.capacity.max=200;
  form.elements.capacity.value=vehicle?vehicleMax:4;
  if(newModelPicker)newModelPicker.setVehicle(vehicle);
  else{
   form.elements.vehicleModel.value=vehicle?.vehicle_trim||vehicle?.model||'';
   form.elements.vehicleClass.value=vehicleClass(driver)||'';
  }
  $('serviceDriverSummary').textContent=driverCaption(driver)+(vehicle?' · ظرفیت '+number(vehicle.capacity):' · در حال دریافت خودرو…');
  $('pickServiceDriver').textContent='تغییر راننده';
  sync();
  if(!vehicle&&driver.vehicle_id)void loadDriverVehicle(driver).then(loaded=>{
   if(!form.isConnected||Number(driver.id)!==driverId)return;
   if(newModelPicker)newModelPicker.setVehicle(loaded);
   const vehicleMax=loaded&&Number(loaded.capacity)>0?Math.min(200,Number(loaded.capacity)):200;
   form.elements.capacity.max=200;form.elements.capacity.value=vehicleMax;
   $('serviceDriverSummary').textContent=driverCaption(driver)+' · ظرفیت '+number(vehicleMax);
   sync();
  }).catch(e=>{if(form.isConnected)notify('دریافت مشخصات خودرو ناموفق بود: '+e.message,true);});
 };
 $('editServiceVehicle').onclick=()=>{
  const selectedId=driverId,driver=drivers.find(d=>Number(d.id)===selectedId),vehicle=vehicleFor(driver);
  if(!vehicle||saving||!can('vehicles','manage'))return;
  const vehicleId=Number(vehicle.id);
  void formResource('vehicles',vehicleId,async()=>{
   try{
    const updated=await api(scoped('/api/v1/vehicles/'+vehicleId));
    if(!form.isConnected||token!==pageToken||driverId!==selectedId)return;
    if(!updated||Number(updated.id)!==vehicleId)throw Error('اطلاعات به‌روز خودرو دریافت نشد.');
    driverVehicleCache.set(vehicleId,updated);
    selectDriver(driver);
    scheduleStudentQuote();
   }catch(error){if(form.isConnected){notify('خودرو ذخیره شد اما همگام‌سازی فرم ناموفق بود: '+error.message,true);}}
  });
 };
 if(initialDriverId){
  const driver=drivers.find(row=>String(row.id)===initialDriverId&&row.status==='active');
  if(!driver)throw new Error('رانندهٔ فعال و تأییدشده در این شرکت پیدا نشد.');
  selectDriver(driver);
 }
 $('pickServiceDriver').onclick=()=>{
  dialog('انتخاب راننده','<p>رانندگان تأییدشده و فعال نمایش داده می‌شوند.</p><label>جست‌وجوی راننده<input type="search" id="driverPickerSearch"></label><div class="service-picker-list" id="driverPickerRows"></div>');
  const search=$('driverPickerSearch'),rows=$('driverPickerRows');
  const render=()=>{rows.innerHTML=drivers.filter(d=>d.status==='active'&&[driverCaption(d),d.mobile].some(v=>matches(v,search.value))).map(d=>`<button type="button" data-driver="${d.id}">${esc(driverCaption(d))}</button>`).join('')||'<p class="empty">راننده واجد شرایط پیدا نشد.</p>';};
  search.oninput=render;
  rows.onclick=e=>{
   const button=e.target.closest('[data-driver]');if(!button)return;
   selectDriver(drivers.find(d=>String(d.id)===button.dataset.driver));
   closeDialog();
  };render();search.focus();
 };
 function scheduleStudentQuote(){
  clearTimeout(quoteTimer);
  if(chosen.size)quoteTimer=setTimeout(()=>{if(form.isConnected&&!saving&&!quote.disabled)quote.click();},350);
  else{disposeMaps();result.textContent='برای محاسبه هزینه، دانش‌آموز اضافه کنید.';}
 }
 function renderInlineStudents(){
  const selected=$('selectedServiceStudents'),picker=$('inlineStudentPicker');
  if(!selected||!picker)return;
  const ready=selectedSchools.length&&driverId;
  if(!ready)picker.hidden=true;
  const current=students.filter(s=>chosen.has(Number(s.id)));
  selected.innerHTML=current.length?'<div class="sy-inline-student-table">'+current.map((s,i)=>'<article><span class="sy-inline-student-index">'+number(i+1)+'</span><div><strong>'+esc(person(s))+'</strong><small>'+esc(schools.find(sc=>Number(sc.id)===Number(s.school_id))?.name||'')+' · '+esc(s.address||'نشانی ثبت نشده')+'</small></div><span>'+esc(s.tariff_distance_km==null?'مسافت نامشخص':km(s.tariff_distance_km)+' کیلومتر')+'</span><button type="button" class="danger" data-inline-remove="'+Number(s.id)+'">حذف</button></article>').join('')+'</div>':'<p class="empty">'+(ready?'هنوز دانش‌آموزی اضافه نشده است.':'ابتدا مدرسه و راننده را انتخاب کنید.')+'</p>';
  if(picker.hidden)return;
  const term=$('studentPickerName').value,address=$('studentPickerAddress').value,history=$('studentPickerMehr').checked;
  const filtered=students.filter(s=>eligibleSchool(s)&&!blocked(s)&&!chosen.has(Number(s.id))&&(!history||hasMehrDriver(s))&&[person(s),s.national_code].some(v=>matches(v,term))&&matches(s.address,address));
  const visible=filtered.slice(0,100),full=chosen.size>=Number(form.elements.capacity.value);
  $('studentPickerCount').textContent=number(filtered.length)+' دانش‌آموز قابل افزودن'+(filtered.length>visible.length?' · برای نتایج بیشتر جستجو را دقیق‌تر کنید.':'');
  $('studentPickerRows').innerHTML=visible.map(s=>'<article><div><strong>'+esc(person(s))+'</strong><small>'+esc(schools.find(sc=>Number(sc.id)===Number(s.school_id))?.name||'')+' · '+esc(s.address||'نشانی ثبت نشده')+(hasMehrDriver(s)?' · سابقه مهر':'')+'</small></div><button type="button" data-inline-add="'+Number(s.id)+'" '+(full?'disabled':'')+'>+ اضافه</button></article>').join('')||'<p class="empty">دانش‌آموز واجد شرایط دیگری یافت نشد.</p>';
 }
 const openInline=()=>{
  if(show.disabled)return;
  $('inlineStudentPicker').hidden=false;renderInlineStudents();$('studentPickerName').focus();
  $('inlineStudentPicker').scrollIntoView({block:'nearest'});
 };
 show.onclick=openInline;
 $('changeServiceStudents').onclick=openInline;
 $('closeInlineStudents').onclick=()=>{$('inlineStudentPicker').hidden=true;};
 for(const id of ['studentPickerName','studentPickerAddress','studentPickerMehr'])$(id).addEventListener(id==='studentPickerMehr'?'change':'input',renderInlineStudents);
 $('studentPickerRows').onclick=e=>{
  const b=e.target.closest('[data-inline-add]');if(!b||saving)return;
  const student=students.find(s=>Number(s.id)===Number(b.dataset.inlineAdd));
  if(!student||!eligibleSchool(student)||blocked(student)||chosen.has(Number(student.id)))return;
  if(chosen.size>=Number(form.elements.capacity.value)){notify('ابتدا ظرفیت سرویس را افزایش دهید.',true);return;}
  chosen.add(Number(student.id));sync();scheduleStudentQuote();
 };
 $('selectedServiceStudents').onclick=e=>{
  const b=e.target.closest('[data-inline-remove]');if(!b||saving)return;
  chosen.delete(Number(b.dataset.inlineRemove));sync();scheduleStudentQuote();
 };
 form.elements.title.addEventListener('input',refreshSave);
 form.elements.vehicleModel.addEventListener('input',refreshSave);
 form.elements.vehicleClass.addEventListener('change',refreshSave);
 form.elements.agreedAmountDisplay.addEventListener('input',()=>{syncAgreed();refreshSave();});


 form.elements.capacity.oninput=()=>{sync();scheduleStudentQuote();};form.elements.feeSource.onchange=()=>{sync();if(chosen.size)quote.click();};
 quote.onclick=async()=>{

  const version=++revision,rows=students.filter(s=>chosen.has(Number(s.id)));
  refreshSave();
  if(!form.elements.capacity.reportValidity()){result.textContent='ظرفیت معتبر وارد کنید.';return;}
  if(rows.length>Number(form.elements.capacity.value)){result.textContent='تعداد دانش‌آموزان از ظرفیت بیشتر است.';return;}
  quote.disabled=true;disposeMaps();result.textContent='در حال دریافت قیمت و مسیر…';
  const current=()=>form.isConnected&&version===revision;
  try{
   const lines=[],routes=[];let total=0,driverBaseTotal=0,annualTotal=0,missing=0;
   for(const s of rows){
    if(!current())return;
    let route,routeText='مختصات منزل یا مدرسه ثبت نشده است.';
    const school=schools.find(row=>Number(row.id)===Number(s.school_id));
    if(s.latitude!=null&&s.longitude!=null&&school?.latitude!=null&&school.longitude!=null){
     try{route=await write('/api/v1/maps/route',{studentId:s.id});routeText=km(route.distanceMeters/1000)+' کیلومتر';if(route.geometry?.length)routes.push(route.geometry);}catch(e){routeText=e.message;}
    }
    if(!current())return;
    const distance=s.tariff_distance_km;
    const rate=await write('/api/v1/operations/tariffs/quote',{studentId:s.id,driverId,vehicleClass:form.elements.vehicleClass.value,feeSource:form.elements.feeSource.value});
    const months=serviceAcademicMonths(s,school);if(rate.monthlyFee===null)missing++;else{total+=Number(rate.monthlyFee);driverBaseTotal+=Number(rate.driverBaseMonthly??rate.monthlyFee);annualTotal+=Math.round(Number(rate.monthlyFee)*months);}
    lines.push([person(s),km(distance),routeText,rate.monthlyFee===null?'نرخ ثبت نشده':number(rate.monthlyFee),rate.monthlyFee===null?'—':number(Math.round(Number(rate.monthlyFee)*months)),rate.saved?'مبلغ ثبت‌شده':rate.monthlyFee===null?'—':can('students','manage')?'نرخ‌نامه · ذخیره با ثبت سرویس':'نرخ‌نامه · پیش‌نمایش']);
   }
   if(!current())return;
   const breakdown=driverFeeBreakdown(total,driverRates);
   result.innerHTML=
    '<section class="sy-service-quote-head"><h3>برآورد مالی سرویس</h3><span>بر اساس تعرفهٔ جاری مدرسه، مسافت و کلاس خودرو</span></section>'+
    '<div class="sy-service-quote-metrics">'+
    '<div><span>جمع ماهانه اولیا</span><strong>'+number(total)+' ریال</strong></div><div><span>حق‌الزحمه مرجع راننده در نرخ‌نامه</span><strong>'+number(driverBaseTotal)+' ریال</strong></div>'+
    '<div><span>کسورات شرکت از شهریه اولیا ('+percent(breakdown.rate)+'٪)</span><strong>'+number(breakdown.deducted)+' ریال</strong></div>'+
    '<div class="sy-service-quote-net"><span>خالص ماهانه راننده</span><strong>'+number(breakdown.net)+' ریال</strong></div>'+
    '<div><span>برآورد مبلغ سالانه</span><strong>'+number(annualTotal)+' ریال</strong></div>'+
    '</div>'+
    (missing?'<p class="sy-service-quote-warning" role="alert">برای '+number(missing)+' دانش‌آموز نرخ تعرفه یافت نشد. پیش از ثبت نهایی، اطلاعات شهریه و نرخ‌نامه را بررسی کنید.</p>':'')+
    '<div class="sy-service-quote-table">'+simpleTable(['دانش‌آموز','کیلومتر تعرفه','مسیر نشان','ماهیانه · ریال','برآورد سالانه · ریال','مبنای مبلغ'],lines)+'</div>'+
    '<p class="sy-service-quote-footnote">برآورد سالانه متناسب با مقطع تحصیلی است: ابتدایی ۸٫۵ ماه و متوسطه و دبیرستان ۹ ماه. مبالغ به ریال هستند.</p>'+
    (routes.length?'<div class="service-map" id="serviceQuoteMap" aria-label="نقشه مسیرهای نشان"></div>':'');
   refreshSave();
   if(routes.length){
    const map=await makeMap($('serviceQuoteMap'));
    if(!map||!current())return;
    const group=L.featureGroup(routes.map(geometry=>L.polyline(geometry,{color:'#005b35',weight:4}))).addTo(map);
    map.fitBounds(group.getBounds().pad(.15),{maxZoom:16});
   }
  }catch(e){if(current())result.textContent='دریافت برآورد امکان‌پذیر نشد: '+e.message+'؛ در صورت تکمیل مشخصات می‌توانید سرویس را ثبت کنید.';}finally{if(current()){quote.disabled=false;refreshSave();}}
 };
 sync();
 form.onsubmit=async e=>{
  e.preventDefault();refreshSave();if(save.disabled||saving)return;
  const body=Object.fromEntries(new FormData(form));body.driverId=driverId;body.schoolIds=schoolIds();body.studentIds=[...chosen];
  if(form.elements.feeSource.value!=='agreed')body.agreedAmount='0';
  clearTimeout(quoteTimer);const version=revision;saving=true;refreshSave();
  try{await write('/api/v1/services',body);lookupCache={};if(form.isConnected){notify('سرویس و دانش‌آموزان ثبت شدند؛ در صورت افزایش ظرفیت، مشخصات خودرو نیز به‌روزرسانی شد.');location.hash=initialDriverId?'drivers':'services';}}
  catch(err){if(form.isConnected){notify(err.message,true);saveFeedback.textContent='ثبت سرویس انجام نشد: '+err.message;saveFeedback.dataset.state='warning';}}
  finally{saving=false;if(form.isConnected){save.disabled=validationIssues().length>0;}}
 };
}

/* Agency branded printed report headers: one company, one document. */
let companyPrintHeader=null;
window.addEventListener('beforeprint',()=>{
 companyPrintHeader?.remove();companyPrintHeader=null;
 const visible=element=>element&&!element.closest('[hidden]')&&element.getClientRects().length>0;
 const selectors=['#modal .sy-payslip-sheet','#modal .journal-print','.ledger-print-sheet','.sy-account-statement','#content .sy-operation-page','#content .sy-record-details','#content > .panel'];
 const target=selectors.map(selector=>[...document.querySelectorAll(selector)].find(visible)).find(Boolean);
 if(!target||target.querySelector('.sy-company-logo'))return;
 const header=document.createElement('header');header.className='sy-report-company-letterhead';
 header.innerHTML=agencyLogoImage('لوگوی '+(currentAgency?.name||'شرکت'),64)+'<div><strong>'+esc(currentAgency?.name||'شرکت')+'</strong><p>سرویس‌یار · گزارش رسمی شرکت</p></div>';
 target.prepend(header);companyPrintHeader=header;
});
window.addEventListener('afterprint',()=>{companyPrintHeader?.remove();companyPrintHeader=null;});


async function adminDashboardPage(token){
 $('content').classList.add('sy-super-admin-workspace');
 $('content').innerHTML='<div class="loading">در حال دریافت آمار کل سامانه…</div>';
 try{
  const data=await api('/api/v1/admin/overview');if(token!==pageToken)return;
  const counts=data.counts||{},companies=data.agencies||[],active=companies.filter(a=>Number(a.is_active)===1);
  const metrics=[
   ['شرکت‌های فعال',active.length,'شرکت'],['کل کاربران',counts.users||0,'حساب کاربری'],
   ['مدارس',counts.schools||0,'مدرسه'],['دانش‌آموزان',counts.students||0,'دانش‌آموز'],
   ['رانندگان',counts.drivers||0,'راننده'],['خودروها',counts.vehicles||0,'خودرو'],
   ['سرویس‌ها',counts.services||0,'سرویس']
  ];
  const quick=[
   ['admin-agencies','مدیریت شرکت‌ها','ثبت، ویرایش و مشاهده وضعیت شرکت‌ها',samarMenuIcon('company')],
   ['admin-users','مدیریت کاربران','جستجو، ویرایش، تخصیص نقش و مدیریت حساب‌ها',samarMenuIcon('students')],
   ['admin-platform','تنظیمات سامانه','کلیدهای نقشه و سرویس‌دهنده‌ها',navIcon('settings')],
   ['admin-sms','سرویس پیامک نگین','تنظیم اتصال و وضعیت ارسال پیامک',navIcon('messages')]
  ];
  const recent=active.slice(0,6);
  $('content').innerHTML=
   '<section class="sy-admin-hero"><div><span class="sy-admin-eyebrow">مرکز فرماندهی سرویس‌یار</span><h2>داشبورد مدیریت کل</h2><p>نمای کلی وضعیت سامانه و دسترسی مستقیم به مدیریت شرکت‌ها، کاربران و تنظیمات مرکزی.</p></div><button type="button" class="sy-admin-hero-action" id="syDashboardImpersonate">'+navIcon('drivers')+'<span>ورود نیابت به پنل شرکت</span><b>←</b></button></section>'+
   '<section class="sy-admin-metric-grid">'+metrics.map(([label,value,unit],index)=>'<article class="sy-admin-metric sy-admin-metric-'+index+'"><span class="sy-admin-metric-icon" aria-hidden="true">'+['▦','♙','⌂','◉','♧','▣','⇄'][index]+'</span><span class="sy-admin-metric-label">'+label+'</span><strong>'+number(value)+'</strong><small>'+unit+' ثبت‌شده در سامانه</small></article>').join('')+'</section>'+
   '<section class="sy-admin-dashboard-grid"><div class="sy-admin-quick-panel"><header><div><span class="sy-admin-eyebrow">دسترسی سریع</span><h3>مدیریت بخش‌های اصلی</h3></div></header><div class="sy-admin-quick-grid">'+quick.map(([key,label,desc,icon])=>'<a class="sy-admin-quick-card" href="#'+key+'"><span class="sy-admin-quick-icon">'+icon+'</span><span><b>'+label+'</b><small>'+desc+'</small></span><i aria-hidden="true">←</i></a>').join('')+'</div></div>'+
   '<div class="sy-admin-companies-panel"><header><div><span class="sy-admin-eyebrow">وضعیت سازمان‌ها</span><h3>شرکت‌های فعال</h3></div><a href="#admin-agencies">مشاهده همه</a></header>'+
   (recent.length?'<div class="sy-admin-recent-companies">'+recent.map(a=>'<div><span class="sy-admin-company-mark">'+esc((a.name||'ش').trim().slice(0,1))+'</span><span><b>'+esc(a.name)+'</b><small>'+(a.code?'کد '+esc(a.code):'شناسه '+number(a.id))+'</small></span><em>فعال</em></div>').join('')+'</div>':'<div class="empty">هنوز شرکت فعالی ثبت نشده است.</div>')+
   '<div class="sy-admin-company-summary"><span>کل شرکت‌ها</span><b>'+number(companies.length)+'</b><span>غیرفعال</span><b>'+number(companies.length-active.length)+'</b></div></div></section>';
  $('syDashboardImpersonate').onclick=openAdminImpersonationDialog;
  const maintenance=document.createElement('section');maintenance.className='panel';
  maintenance.innerHTML='<h3>آمادگی دیتابیس قراردادها</h3><p data-contract-schema-status aria-live="polite">در حال بررسی ساختار…</p><button type="button" class="primary" data-contract-schema-repair hidden>آماده‌سازی دیتابیس قراردادها</button>';
  $('content').append(maintenance);
  const status=maintenance.querySelector('[data-contract-schema-status]'),repair=maintenance.querySelector('[data-contract-schema-repair]');
  const showSchema=data=>{status.textContent=data.ready?'ساختار قراردادها آماده است.':'ساختار قراردادها ناقص است: '+(data.missing||[]).join('، ');repair.hidden=!!data.ready;};
  api('/api/v1/admin/contracts/schema').then(showSchema).catch(error=>{status.textContent=error.message;});
  repair.onclick=async()=>{if(!window.confirm('پس از تهیه نسخه پشتیبان، ایجاد جداول موردنیاز قراردادها را تأیید می‌کنید؟'))return;repair.disabled=true;try{showSchema(await write('/api/v1/admin/contracts/schema',{confirm:'REPAIR_CONTRACT_SCHEMA'},'POST'));notify('ساختار قراردادها آماده شد.');}catch(error){status.textContent=error.message;}finally{repair.disabled=false;}};

 }catch(error){if(token===pageToken)$('content').innerHTML='<div class="error">'+esc(error.message)+'</div>';}
}

function adminContractTemplatePage(token){
 if(!session?.roles?.includes('super_admin')||session.impersonation)return;
 const root=$('content');root.replaceChildren();root.classList.add('sy-super-admin-workspace');
 const editor=document.createElement('section');editor.className='panel sy-contract-template-admin sy-template-studio';
 const tools=[['undo','↶','واگرد'],['redo','↷','بازانجام'],['bold','B','درشت'],['italic','I','مورب'],['underline','U','زیرخط'],['strikeThrough','S','خط‌خورده'],['justifyRight','≡','راست‌چین'],['justifyCenter','≡','وسط‌چین'],['justifyLeft','≡','چپ‌چین'],['justifyFull','≡','تراز دوطرفه'],['insertUnorderedList','•','فهرست نشانه‌دار'],['insertOrderedList','۱.','فهرست شماره‌دار'],['indent','⇥','افزایش تورفتگی'],['outdent','⇤','کاهش تورفتگی'],['removeFormat','Tx','حذف قالب‌بندی']];
 editor.innerHTML='<header class="sy-settings-section-head"><div><h2>استودیو طراحی قرارداد</h2><p>صفحه‌آرایی قراردادهای سامانه، درج لوگو و متغیرها؛ هر انتشار یک نسخه جدید می‌سازد.</p></div><span id="syContractTemplateVersion" role="status"></span></header>'+
 '<div class="sy-contract-tabs"><button type="button" class="primary" data-template-kind="parent">قرارداد اولیا</button><button type="button" data-template-kind="driver">قرارداد رانندگان</button></div>'+
 '<form id="syContractTemplateForm"><label>عنوان قالب<input name="title" required maxlength="200"></label>'+
 '<div class="sy-template-studio-toolbar"><div class="sy-template-tool-group"><button type="button" data-editor-mode="visual" class="primary">ویرایش دیداری</button><button type="button" data-editor-mode="source">HTML</button></div>'+
 '<div class="sy-template-tool-group">'+tools.map(([cmd,icon,label])=>'<button type="button" data-command="'+cmd+'" title="'+label+'" aria-label="'+label+'">'+icon+' <small>'+label+'</small></button>').join('')+'</div>'+
 '<div class="sy-template-tool-group"><label>نوع متن<select data-select-command="formatBlock"><option value="p">متن معمولی</option><option value="h1">عنوان اصلی</option><option value="h2">عنوان ماده</option><option value="h3">زیرعنوان</option></select></label><label>فونت<select data-select-command="fontName"><option>Vazirmatn</option><option>Tahoma</option><option>B Nazanin</option><option>Arial</option></select></label><label>اندازه<select data-select-command="fontSize">'+[1,2,3,4,5,6,7].map((v,i)=>'<option value="'+v+'">'+[8,10,12,14,18,22,28][i]+'</option>').join('')+'</select></label><label>رنگ متن<input type="color" data-select-command="foreColor" value="#161616"></label><label>رنگ زمینه<input type="color" data-select-command="hiliteColor" value="#ffffff"></label></div>'+
 '<div class="sy-template-tool-group"><button type="button" data-insert="fixed">لوگوی ثابت سامانه</button><button type="button" data-insert="company">لوگوی پویای شرکت</button><button type="button" data-insert="upload">بارگذاری لوگوی ثابت</button><input type="file" accept="image/png,image/jpeg,image/webp" id="syTemplateLogoFile" hidden><label>عرض لوگو<select id="syTemplateLogoWidth"><option value="60">کوچک</option><option value="100" selected>متوسط</option><option value="150">بزرگ</option><option value="220">خیلی بزرگ</option></select></label><button type="button" data-insert="table">جدول ۲×۲</button><button type="button" data-insert="page">شکست صفحه</button></div>'+
 '<div class="sy-template-tool-group"><label>افزودن متغیر<select id="syTemplateVariable"><option value="">انتخاب کنید</option><option value="studentName">نام دانش‌آموز</option><option value="guardianName">نام ولی</option><option value="guardianNationalCode">کد ملی ولی</option><option value="driverName">نام راننده</option><option value="managerName">نام مدیر</option><option value="companyName">نام شرکت</option><option value="totalAmount">مبلغ قرارداد</option><option value="contractNo">شماره قرارداد</option><option value="issueDate">تاریخ صدور</option><option value="startDate">تاریخ شروع</option><option value="endDate">تاریخ پایان</option><option value="durationMonths">مدت قرارداد</option><option value="academicYear">سال تحصیلی</option><option value="studentNationalCode">کد ملی دانش‌آموز</option><option value="schoolName">مدرسه</option><option value="schoolRegion">ناحیه</option><option value="guardianFatherName">نام پدر ولی</option><option value="guardianMobile">موبایل ولی</option><option value="guardianAddress">نشانی ولی</option><option value="companyRegistration">شماره ثبت شرکت</option><option value="companyNationalId">شناسه ملی شرکت</option><option value="managerTitle">سمت مدیر</option><option value="managerNationalCode">کد ملی مدیر</option><option value="companyAddress">نشانی شرکت</option><option value="companyPhone">تلفن شرکت</option><option value="bankName">بانک</option><option value="bankAccount">شماره حساب</option><option value="distanceKm">مسافت</option><option value="payment40">پیش‌پرداخت ۴۰ درصد</option><option value="payment20">قسط ۲۰ درصد</option><option value="driverNationalCode">کد ملی راننده</option><option value="driverMobile">موبایل راننده</option><option value="grossAnnual">ناخالص سالانه راننده</option><option value="deductionsAnnual">کسورات سالانه</option><option value="driverDeductionRate">درصد کسورات</option><option value="driverServicesSummary">ریز سرویس راننده</option></select></label><button type="button" id="syContractTemplatePreviewBtn">پیش‌نمایش چاپ A4</button></div></div>'+
 '<div class="sy-logo-position-tools" hidden><strong>تنظیم تصویر انتخاب‌شده</strong><label>قرارگیری<select id="syLogoWrap"><option value="inline">هم‌سطح متن</option><option value="right">کنار متن ـ راست</option><option value="left">کنار متن ـ چپ</option><option value="above">بالای متن</option><option value="below">پایین متن</option><option value="front">روی متن</option><option value="behind">پشت متن</option></select></label><label>عرض (پیکسل)<input id="syLogoSize" type="number" min="24" max="300" value="100"></label><label>موقعیت افقی (%)<input id="syLogoX" type="number" min="0" max="100" step="1"></label><label>موقعیت عمودی (%)<input id="syLogoY" type="number" min="0" max="100" step="1"></label><button type="button" id="syLogoRemove">حذف تصویر</button><small>تصویر را بکشید؛ در حالت روی متن و پشت متن موقعیت آزاد است.</small></div><div class="sy-template-paper-area"><label id="syTemplateSourceLabel" hidden>کد HTML<textarea name="html" rows="24" spellcheck="false" dir="ltr"></textarea></label><div id="syTemplateVisualEditor" class="sy-template-visual-editor" contenteditable="true" role="textbox" aria-label="ویرایش متن قرارداد" aria-multiline="true"></div></div>'+
 '<iframe id="syContractTemplatePreview" title="پیش‌نمایش چاپ قرارداد" sandbox hidden></iframe><footer class="sy-template-studio-footer"><span id="syContractTemplateStatus" role="status"></span><button type="submit" class="primary" disabled>انتشار نسخه جدید قالب</button></footer></form>';
 root.append(editor);
 const form=editor.querySelector('form'),source=form.elements.html,visual=editor.querySelector('#syTemplateVisualEditor'),sourceLabel=editor.querySelector('#syTemplateSourceLabel'),status=editor.querySelector('#syContractTemplateStatus'),submit=form.querySelector('[type=submit]'),buttons=[...editor.querySelectorAll('[data-template-kind]')],preview=editor.querySelector('iframe');
 let kind='parent',serial=0,visualMode=true,loaded=false,dirty=false,savedRange=null;
 const safeFragment=html=>{
  const doc=new DOMParser().parseFromString(html,'text/html');
  doc.querySelectorAll('script,style,iframe,object,embed,svg,math,link,meta,base,form,template').forEach(n=>n.remove());
  const allowed=new Set(['h1','h2','h3','h4','p','div','span','strong','b','em','i','u','s','strike','sub','sup','ul','ol','li','table','thead','tbody','tfoot','tr','td','th','br','hr','img','blockquote','font']);
  for(const n of [...doc.body.querySelectorAll('*')]){
   if(!allowed.has(n.localName)){n.replaceWith(...n.childNodes);continue;}
   for(const attr of [...n.attributes]){
    if(!['class','style','align','dir','colspan','rowspan','width','alt','src','data-contract-logo','data-logo-wrap','data-logo-x','data-logo-y','face','size','color'].includes(attr.name))n.removeAttribute(attr.name);
    else if(attr.name==='style'&&/url\s*\(|expression\s*\(|@import|position\s*:|behavior\s*:|javascript:/i.test(attr.value))n.removeAttribute('style');
   }
   if(n.localName==='img'&&!/^data:image\/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$/.test(n.getAttribute('src')||'')&&n.getAttribute('src')!=='/assets/logo.png')n.remove();
  }
  return doc.body.innerHTML;
 };
 const logoTools=editor.querySelector('.sy-logo-position-tools'),wrapInput=editor.querySelector('#syLogoWrap'),sizeInput=editor.querySelector('#syLogoSize'),xInput=editor.querySelector('#syLogoX'),yInput=editor.querySelector('#syLogoY');
 let selectedLogo=null,dragLogo=null;
 const bounded=(v,min,max)=>Math.max(min,Math.min(max,Number(v)||0));
 const floating=img=>['front','behind'].includes(img.dataset.logoWrap);
 const applyLogoPosition=img=>{
  if(!img)return;
  if(floating(img)){img.style.left=bounded(img.dataset.logoX,0,100)+'%';img.style.top=bounded(img.dataset.logoY,0,100)+'%';}
  else{img.style.removeProperty('left');img.style.removeProperty('top');}
 };
 const hydrateLogos=()=>visual.querySelectorAll('img').forEach(applyLogoPosition);
 const selectLogo=img=>{
  selectedLogo?.classList.remove('sy-logo-selected');selectedLogo=img;
  logoTools.hidden=!img;if(!img)return;
  img.classList.add('sy-logo-selected');wrapInput.value=img.dataset.logoWrap||'inline';sizeInput.value=Number(img.getAttribute('width'))||100;
  xInput.value=Number(img.dataset.logoX)||0;yInput.value=Number(img.dataset.logoY)||0;
  xInput.disabled=yInput.disabled=!floating(img);
 };
 const updateLogo=()=>{
  if(!selectedLogo||!visual.contains(selectedLogo))return;
  const img=selectedLogo,wasFloating=floating(img),before=img.getBoundingClientRect(),paper=visual.getBoundingClientRect();
  img.dataset.logoWrap=wrapInput.value;img.setAttribute('width',String(bounded(sizeInput.value,24,300)));
  if(floating(img)){
   if(!wasFloating){xInput.value=Math.round(bounded((before.left-paper.left+visual.scrollLeft)/visual.clientWidth*100,0,100));yInput.value=Math.round(bounded((before.top-paper.top+visual.scrollTop)/Math.max(visual.scrollHeight,visual.clientHeight)*100,0,100));}
   img.dataset.logoX=String(bounded(xInput.value,0,100));img.dataset.logoY=String(bounded(yInput.value,0,100));
  }
  xInput.disabled=yInput.disabled=!floating(img);applyLogoPosition(img);dirty=true;
 };
 [wrapInput,sizeInput,xInput,yInput].forEach(input=>input.addEventListener('change',updateLogo));
 editor.querySelector('#syLogoRemove').onclick=()=>{if(selectedLogo){selectedLogo.remove();selectLogo(null);dirty=true;}};
 visual.addEventListener('click',e=>{const img=e.target.closest('img');selectLogo(img&&visual.contains(img)?img:null);});
 visual.addEventListener('pointerdown',e=>{
  const img=e.target.closest('img');if(!img||!loaded||e.button!==0)return;
  selectLogo(img);e.preventDefault();img.setPointerCapture(e.pointerId);
  const rect=img.getBoundingClientRect();dragLogo={img,id:e.pointerId,x:e.clientX,y:e.clientY,dx:e.clientX-rect.left,dy:e.clientY-rect.top,moved:false};
 });
 visual.addEventListener('pointermove',e=>{
  if(!dragLogo||dragLogo.id!==e.pointerId)return;
  const drag=dragLogo;if(Math.hypot(e.clientX-drag.x,e.clientY-drag.y)<5&&!drag.moved)return;drag.moved=true;
  if(!floating(drag.img))return;
  const paper=visual.getBoundingClientRect(),w=visual.clientWidth,H=Math.max(visual.scrollHeight,visual.clientHeight);
  drag.img.dataset.logoX=String(Math.round(bounded((e.clientX-paper.left+visual.scrollLeft-drag.dx)/w*100,0,Math.max(0,100-drag.img.offsetWidth/w*100))));
  drag.img.dataset.logoY=String(Math.round(bounded((e.clientY-paper.top+visual.scrollTop-drag.dy)/H*100,0,Math.max(0,100-drag.img.offsetHeight/H*100))));
  applyLogoPosition(drag.img);xInput.value=drag.img.dataset.logoX;yInput.value=drag.img.dataset.logoY;dirty=true;
 });
 visual.addEventListener('pointerup',e=>{
  if(!dragLogo||dragLogo.id!==e.pointerId)return;
  const drag=dragLogo;dragLogo=null;
  if(!drag.moved||floating(drag.img))return;
  const target=document.elementFromPoint(e.clientX,e.clientY);
  const block=target?.closest('p,h1,h2,h3,h4,li,td,th,blockquote');
  if(block&&visual.contains(block)&&!block.contains(drag.img)){
   const mode=drag.img.dataset.logoWrap||'inline';
   if(mode==='above')block.parentNode.insertBefore(drag.img,block);
   else if(mode==='below')block.after(drag.img);
   else block.prepend(drag.img);
   dirty=true;
  }
 });
 visual.addEventListener('pointercancel',()=>{dragLogo=null;});
 visual.addEventListener('dragstart',e=>{if(e.target.closest('img'))e.preventDefault();});
 const exportVisual=()=>{const copy=visual.cloneNode(true);copy.querySelectorAll('.sy-logo-selected').forEach(n=>n.classList.remove('sy-logo-selected'));return copy.innerHTML;};
 const remember=()=>{const sel=window.getSelection();if(sel?.rangeCount&&visual.contains(sel.anchorNode)&&visual.contains(sel.focusNode))savedRange=sel.getRangeAt(0).cloneRange();};
 visual.addEventListener('mouseup',remember);visual.addEventListener('keyup',remember);visual.addEventListener('input',()=>{dirty=true;remember();});
 visual.addEventListener('paste',e=>{e.preventDefault();document.execCommand('insertText',false,e.clipboardData?.getData('text/plain')||'');});
 source.oninput=()=>{dirty=true;};form.elements.title.oninput=()=>{dirty=true;};
 const focusSelection=()=>{visual.focus();if(savedRange&&visual.contains(savedRange.startContainer)){const sel=window.getSelection();sel.removeAllRanges();sel.addRange(savedRange);}};
 const switchMode=mode=>{const want=mode==='visual';if(want===visualMode)return;if(want){visual.innerHTML=safeFragment(source.value);selectLogo(null);hydrateLogos();savedRange=null;}else{source.value=exportVisual();selectLogo(null);}visualMode=want;visual.hidden=!want;sourceLabel.hidden=want;editor.querySelectorAll('[data-editor-mode]').forEach(b=>b.classList.toggle('primary',b.dataset.editorMode===mode));};
 const command=(cmd,value=null)=>{if(!loaded)return;if(!visualMode)switchMode('visual');focusSelection();document.execCommand(cmd,false,value);dirty=true;remember();};
 editor.querySelectorAll('[data-command],[data-insert]').forEach(b=>b.addEventListener('mousedown',e=>e.preventDefault()));
 editor.querySelectorAll('[data-command]').forEach(b=>b.onclick=()=>command(b.dataset.command));
 editor.querySelectorAll('[data-select-command]').forEach(el=>{el.addEventListener('pointerdown',remember);el.onchange=()=>command(el.dataset.selectCommand,el.value);});
 editor.querySelectorAll('[data-editor-mode]').forEach(b=>b.onclick=()=>switchMode(b.dataset.editorMode));
 const insert=html=>command('insertHTML',html);
 const logo=(type,src)=>insert('<img '+(type?'data-contract-logo="'+type+'" ':'')+'src="'+esc(src)+'" alt="'+(type==='company'?'لوگوی شرکت':'لوگو')+'" width="'+Number(editor.querySelector('#syTemplateLogoWidth').value)+'" class="contract-logo" data-logo-wrap="inline">');
 editor.querySelectorAll('[data-insert]').forEach(b=>b.onclick=()=>{if(!loaded)return;const type=b.dataset.insert;if(type==='fixed'||type==='company')logo(type,'/assets/logo.png');else if(type==='upload')editor.querySelector('#syTemplateLogoFile').click();else if(type==='table')insert('<table><tbody><tr><td>متن</td><td>متن</td></tr><tr><td>متن</td><td>متن</td></tr></tbody></table><p><br></p>');else insert('<hr><p><br></p>');});
 editor.querySelector('#syTemplateLogoFile').onchange=e=>{const file=e.target.files?.[0];if(!file)return;if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>500000){status.textContent='لوگو باید PNG، JPG یا WebP و حداکثر ۵۰۰ کیلوبایت باشد.';e.target.value='';return;}const reader=new FileReader();reader.onload=()=>{logo('',String(reader.result));e.target.value='';};reader.readAsDataURL(file);};
 const picker=editor.querySelector('#syTemplateVariable');picker.onchange=()=>{if(!picker.value)return;const val='{{'+picker.value+'}}';if(visualMode)command('insertText',val);else{source.setRangeText(val,source.selectionStart,source.selectionEnd,'end');dirty=true;}picker.value='';};
 const currentHtml=()=>visualMode?exportVisual():source.value;
 const request=async(path,body)=>api(path,{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(body)});
 editor.querySelector('#syContractTemplatePreviewBtn').onclick=async()=>{if(!loaded)return;try{const data=await request('/api/v1/contract-templates/'+kind+'?preview=1',{html:currentHtml()});const doc=new DOMParser().parseFromString(data.html,'text/html');doc.querySelector('.contract-toolbar')?.remove();doc.querySelectorAll('link[rel="stylesheet"]').forEach(link=>{link.href=new URL(link.getAttribute('href'),window.location.origin).href;});doc.querySelectorAll('img[src^="/"]').forEach(img=>{img.src=new URL(img.getAttribute('src'),window.location.origin).href;});preview.srcdoc='<!doctype html>'+doc.documentElement.outerHTML;preview.hidden=false;preview.scrollIntoView({block:'nearest'});}catch(error){status.textContent=error.message;}};
 const load=async()=>{loaded=false;submit.disabled=true;const current=++serial;buttons.forEach(b=>b.classList.toggle('primary',b.dataset.templateKind===kind));status.textContent='در حال دریافت قالب…';try{const data=await api('/api/v1/contract-templates/'+kind);if(current!==serial||token!==pageToken||!form.isConnected)return;form.elements.title.value=data.title||'';source.value=data.html||'';visual.innerHTML=safeFragment(source.value);savedRange=null;visualMode=true;visual.hidden=false;sourceLabel.hidden=true;editor.querySelectorAll('[data-editor-mode]').forEach(b=>b.classList.toggle('primary',b.dataset.editorMode==='visual'));preview.hidden=true;editor.querySelector('#syContractTemplateVersion').textContent='نسخه فعال '+number(data.version);status.textContent='قالب آماده ویرایش است.';loaded=true;dirty=false;submit.disabled=false;}catch(error){status.textContent=error.message;}};
 buttons.forEach(b=>b.onclick=()=>{if(b.dataset.templateKind===kind)return;if(dirty&&!window.confirm('تغییرات ذخیره‌نشده کنار گذاشته شود؟'))return;kind=b.dataset.templateKind;void load();});
 form.onsubmit=async e=>{e.preventDefault();if(!loaded||!window.confirm('نسخه جدید قالب منتشر شود؟'))return;submit.disabled=true;buttons.forEach(b=>b.disabled=true);try{const data=await request('/api/v1/contract-templates/'+kind,{title:form.elements.title.value,html:currentHtml()});dirty=false;status.textContent='نسخه '+number(data.version)+' منتشر شد.';editor.querySelector('#syContractTemplateVersion').textContent='نسخه فعال '+number(data.version);}catch(error){status.textContent=error.message;}finally{submit.disabled=false;buttons.forEach(b=>b.disabled=false);}};
 void load();
}

async function adminSectionPage(key,token){
 if(key==='admin-contract-templates')return adminContractTemplatePage(token);
 const meta={
  'admin-agencies':['مدیریت شرکت‌ها','تعریف، ویرایش، فعال‌سازی و مدیریت ارتباط شرکت‌ها با کاربران.','syAdminAgencies'],
  'admin-users':['مدیریت کاربران','جستجو و مدیریت حساب‌های کاربری، نقش‌ها و دسترسی‌های سراسری.','syAdminUsers'],
  'admin-platform':['کلیدهای سامانه و نقشه','مدیریت کلیدهای محرمانه و سرویس‌های نقشه در سطح کل سامانه.','syAdminIntegrations'],
  'admin-sms':['تنظیمات سرویس پیامک نگین','تنظیم اطلاعات پنل پیامک و بررسی آمادگی سرویس ارسال کدها.','syAdminNeginSms'],
  'admin-bale':['تنظیمات ربات پیام‌رسان بله','مدیریت توکن ربات، وب‌هوک، ورود کاربران و درگاه کیف پول.','syAdminBale'],
  'admin-login':['مدیریت روش‌های ورود کاربران','تعیین روش‌های مجاز ورود و بازیابی حساب‌های کاربران.','syAdminLoginMethods'],
  'admin-taxi-tariffs':['محاسبه سرویس بر اساس سازمان تاکسیرانی','مدیریت نرخ‌نامه ناحیه‌ها و کلاس‌های خودرو و بازبینی محاسبات.','syAdminTaxiTariffs'],
  'admin-vehicle-classes':['مدیریت کلاس‌های تعرفه خودرو','مدیریت خانواده‌ها و نام‌های جایگزین خودرو برای تشخیص کلاس تعرفه.','syVehicleClassCatalog'],
  'admin-sepand':['ورود اطلاعات رانندگان و خودروهای سپند','به‌روزرسانی اطلاعات سپند، تطبیق شرکت‌ها و بازیابی کلاس خودروها.','syAdminSepand']
 };
 const item=meta[key];if(!item)throw new Error('بخش مدیریتی موردنظر تعریف نشده است.');
 $('content').innerHTML='<div class="loading">در حال بارگذاری تنظیمات و اطلاعات مدیریتی…</div>';
 await companiesPage();if(token!==pageToken)return;
 const content=$('content');
 content.querySelector('.sy-admin-section-nav')?.remove();
 const bale=content.querySelector('#syAdminIntegrations .sy-bale-admin-panel');
 if(bale){bale.id='syAdminBale';content.append(bale);}
 const target=content.querySelector('#'+item[2]);
 if(!target)throw new Error('محتوای این بخش بارگذاری نشد؛ صفحه را تازه‌سازی کنید.');
 [...content.children].forEach(child=>{child.hidden=true;});
 if(target.parentElement!==content)content.append(target);
 target.hidden=false;
 target.classList.add('sy-admin-page-card');
 const heading=document.createElement('header');heading.className='sy-admin-page-heading';
 heading.innerHTML='<div><span class="sy-admin-eyebrow">تنظیمات و مدیریت مرکزی</span><h2>'+esc(item[0])+'</h2><p>'+esc(item[1])+'</p></div><a href="#admin-dashboard" class="sy-admin-back-dashboard">بازگشت به داشبورد ←</a>';
 content.prepend(heading);
 content.classList.add('sy-super-admin-workspace');
}

async function openAdminImpersonationDialog(panelType='company'){
 if(!['company','parent','driver'].includes(panelType))return;
 const panelInfo={
  company:{title:'ورود نیابتی به پنل شرکت',description:'ابتدا شرکت را انتخاب کنید؛ سپس یکی از کاربران مدیریتی همان شرکت را برای ورود نیابتی برگزینید.',userTitle:'کاربر شرکت',placeholder:'نام، نام خانوادگی یا نام کاربری مدیر شرکت…',empty:'کاربر مدیریتی فعالی در این شرکت پیدا نشد.',button:'ورود به پنل شرکت'},
  parent:{title:'ورود نیابتی به پنل والدین',description:'ولی دانش‌آموز را حتی بدون حساب تأییدشده انتخاب کنید؛ پنل اصلی در حالت مشاهده باز می‌شود.',userTitle:'ولی دانش‌آموز',placeholder:'نام ولی، شماره همراه یا نام کاربری…',empty:'ولی دانش‌آموز فعالی برای این شرکت یافت نشد.',button:'مشاهده پنل ولی'},
  driver:{title:'ورود نیابتی به پنل رانندگان',description:'همه رانندگان فعال شرکت نمایش داده می‌شوند؛ پنل واقعی آن‌ها حتی بدون تأیید حساب، در حالت مشاهده نیابتی قابل بررسی است.',userTitle:'راننده',placeholder:'نام راننده یا شماره همراه…',empty:'راننده فعالی برای این شرکت یافت نشد.',button:'مشاهده پنل راننده'}
 }[panelType];
 if(!session?.roles?.includes('super_admin')||session?.impersonation)return;
 const returnFocus=document.activeElement instanceof HTMLElement?document.activeElement:null;
 const previous=document.getElementById('syAdminImpersonationModal');previous?.__syClose?.();previous?.remove();
 const overlay=document.createElement('div');overlay.id='syAdminImpersonationModal';overlay.className='sy-admin-impersonation-overlay sy-admin-impersonation-'+panelType;overlay.dataset.panelType=panelType;
 overlay.innerHTML=`<section class="sy-admin-impersonation-dialog" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="syImpersonationTitle" aria-describedby="syImpersonationDescription"><header><div><span class="sy-admin-eyebrow">دسترسی مدیریتی امن</span><h2 id="syImpersonationTitle">${esc(panelInfo.title)}</h2><p id="syImpersonationDescription">${esc(panelInfo.description)}</p></div><button type="button" class="sy-admin-modal-close" aria-label="بستن پنجره">×</button></header><div class="sy-impersonation-steps"><span class="active" data-step-indicator="company">۱. انتخاب شرکت</span><i></i><span data-step-indicator="user">۲. انتخاب کاربر</span></div><div class="sy-impersonation-body"><div data-company-step><label class="sy-impersonation-search"><span>جستجوی شرکت</span><input type="search" id="syImpersonationCompanySearch" placeholder="نام شرکت، کد یا شناسه را وارد کنید…" autocomplete="off"></label><div id="syImpersonationCompanies" class="sy-impersonation-results" aria-live="polite">در حال دریافت فهرست شرکت‌ها…</div></div><div data-user-step hidden><button type="button" id="syImpersonationBack" class="sy-impersonation-back">→ انتخاب شرکت دیگر</button><div class="sy-impersonation-selected-company" id="syImpersonationSelectedCompany"></div><label class="sy-impersonation-search"><span>جستجوی ${esc(panelInfo.userTitle)}</span><input type="search" id="syImpersonationUserSearch" placeholder="${esc(panelInfo.placeholder)}" autocomplete="off"></label><div class="sy-impersonation-fixed-role" role="note">${esc(panelInfo.userTitle)} · فهرست محدود به همین نقش</div><div id="syImpersonationUsers" class="sy-impersonation-results" aria-live="polite"></div><div id="syImpersonationConfirm" class="sy-impersonation-confirm" hidden><div><b id="syImpersonationChosenName"></b><p id="syImpersonationChosenDetails"></p><small>${panelType==='company'?'عملیات بر اساس مجوزهای کاربر شرکت انجام می‌شود.':'پنل صرفاً برای مشاهده است و امکان ویرایش یا انجام تراکنش وجود ندارد.'} ورود نیابتی ثبت و قابل پیگیری است.</small></div><button type="button" class="primary" id="syImpersonationStart">${esc(panelInfo.button)}</button></div></div><p class="sy-impersonation-error" id="syImpersonationError" role="alert" hidden></p></div><footer><span>نشست اصلی ادمین کل حفظ می‌شود و هر زمان می‌توانید بازگردید.</span><button type="button" class="secondary" data-close>انصراف</button></footer></section>`;
 document.body.append(overlay);document.body.classList.add('sy-admin-modal-open');
 const dialog=overlay.querySelector('.sy-admin-impersonation-dialog');
 const focusableSelector='button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href],[tabindex]:not([tabindex="-1"])';
 let keyHandler=null;
 const close=()=>{
  overlay.remove();document.body.classList.remove('sy-admin-modal-open');
  if(keyHandler)document.removeEventListener('keydown',keyHandler);
  if(returnFocus?.isConnected)returnFocus.focus();
 };
 overlay.__syClose=close;
 const focusable=()=>[...overlay.querySelectorAll(focusableSelector)].filter(el=>!el.closest('[hidden]')&&el.getClientRects().length>0);
 keyHandler=e=>{
  if(e.key==='Escape'){e.preventDefault();close();return;}
  if(e.key!=='Tab')return;
  const items=focusable();
  if(!items.length){e.preventDefault();dialog.focus();return;}
  const first=items[0],last=items[items.length-1];
  if(e.shiftKey&&(document.activeElement===first||!overlay.contains(document.activeElement))){e.preventDefault();last.focus();}
  else if(!e.shiftKey&&(document.activeElement===last||!overlay.contains(document.activeElement))){e.preventDefault();first.focus();}
 };
 document.addEventListener('keydown',keyHandler);
 overlay.querySelector('.sy-admin-modal-close').onclick=close;overlay.querySelector('[data-close]').onclick=close;
 overlay.addEventListener('click',event=>{if(event.target===overlay)close();});
 const companyStep=overlay.querySelector('[data-company-step]'),userStep=overlay.querySelector('[data-user-step]'),
  companySearch=overlay.querySelector('#syImpersonationCompanySearch'),companiesBox=overlay.querySelector('#syImpersonationCompanies'),
  userSearch=overlay.querySelector('#syImpersonationUserSearch'),usersBox=overlay.querySelector('#syImpersonationUsers'),
  errorBox=overlay.querySelector('#syImpersonationError'),confirmBox=overlay.querySelector('#syImpersonationConfirm'),
  startButton=overlay.querySelector('#syImpersonationStart');
 const preview=document.createElement('section');
 preview.id='syDriverImpersonationPreview';preview.hidden=true;preview.className='sy-impersonation-driver-preview';
 overlay.querySelector('.sy-impersonation-body').append(preview);
 const openDriverPreview=async driver=>{
   preview.hidden=false;userStep.hidden=true;preview.textContent='در حال بارگذاری پنل واقعی راننده…';
   const companyId=Number(selectedCompany?.id);
   try{
     const result=await api('/api/v1/admin/impersonation/driver-preview?agencyId='+encodeURIComponent(companyId)+'&driverId='+encodeURIComponent(driver.driverId));
     if(!overlay.isConnected||Number(selectedCompany?.id)!==companyId)return;
     if(typeof window.serviceyarPortal?.mountAdminDriverInspection!=='function')
       throw Error('ماژول پنل رانندگان بارگذاری نشده است.');
     await window.serviceyarPortal.mountAdminDriverInspection(result);close();
   }catch(error){
     preview.innerHTML='<button type="button" class="secondary" data-preview-back>بازگشت به فهرست رانندگان</button>'+
       '<p role="alert">'+esc(error.message||'بارگذاری پنل راننده ناموفق بود.')+'</p>';
     preview.querySelector('[data-preview-back]').onclick=()=>{preview.hidden=true;userStep.hidden=false;preview.replaceChildren();};
   }
 };
 const openParentInspection=async guardian=>{
   const agencyId=Number(selectedCompany?.id);
   preview.hidden=false;userStep.hidden=true;preview.textContent='در حال بارگذاری پنل ولی دانش‌آموز…';
   try{
     const result=await api('/api/v1/admin/impersonation/parent-preview?agencyId='+
       encodeURIComponent(agencyId)+'&studentId='+encodeURIComponent(guardian.studentId)+'&guardianId='+encodeURIComponent(guardian.guardianId));
     if(!overlay.isConnected||Number(selectedCompany?.id)!==agencyId)return;
     if(typeof window.serviceyarPortal?.mountAdminParentInspection!=='function')
       throw Error('ماژول پنل والدین بارگذاری نشده است.');
     await window.serviceyarPortal.mountAdminParentInspection(result);close();
   }catch(error){
     preview.innerHTML='<button type="button" class="secondary" data-preview-back>بازگشت به فهرست اولیا</button>'+
       '<p role="alert">'+esc(error.message||'نمایش پنل ولی انجام نشد.')+'</p>';
     preview.querySelector('[data-preview-back]').onclick=()=>{preview.hidden=true;userStep.hidden=false;preview.replaceChildren();};
   }
 };
 const normalize=value=>String(value||'').replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/[\u200c\s]+/g,' ').trim().toLocaleLowerCase('fa');
 const fa=value=>Number(value||0).toLocaleString('fa-IR');
 let companies=[],selectedCompany=null,users=[],selectedUser=null,companyLoadSequence=0;
 const showError=message=>{errorBox.textContent=message||'';errorBox.hidden=!message;};
 const renderCompanies=()=>{
  const term=normalize(companySearch.value);
  const list=companies.filter(a=>normalize([a.name,a.code,a.manager_name,a.office_phone,a.id].join(' ')).includes(term));
  companiesBox.innerHTML=list.length?list.map(a=>'<button type="button" class="sy-impersonation-result" data-company="'+Number(a.id)+'"><span class="sy-impersonation-result-avatar">'+esc((a.name||'ش').trim().slice(0,1))+'</span><span><b>'+esc(a.name)+'</b><small>'+(a.code?'کد شرکت '+esc(a.code):'شناسه '+fa(a.id))+' · '+esc(a.manager_name||'بدون نام مدیر')+'</small></span><em>فعال</em><i aria-hidden="true">←</i></button>').join(''):'<div class="sy-impersonation-empty">شرکتی با این مشخصات پیدا نشد.</div>';
  companiesBox.querySelectorAll('[data-company]').forEach(button=>button.onclick=()=>selectCompany(Number(button.dataset.company)));
 };
 const renderUsers=()=>{
  const term=normalize(userSearch.value);
  // The server already restricts results to the selected agency and panelType.
  // Do not re-classify users by translated role titles, which may vary.
  const list=users.filter(u=>normalize([u.username,u.first_name,u.last_name,u.roles,u.studentFirstName,u.studentLastName].join(' ')).includes(term));
  usersBox.innerHTML=list.length?list.map(u=>'<button type="button" class="sy-impersonation-result" data-user="'+Number(u.userKey??u.id)+'"><span class="sy-impersonation-result-avatar sy-user-avatar">'+esc((u.first_name||u.username||'ک').trim().slice(0,1))+'</span><span><b>'+esc(((u.first_name||'')+' '+(u.last_name||'')).trim()||u.username)+'</b><small>'+esc(u.username)+' · '+esc(u.roles||'بدون نقش')+'</small></span><em>'+((panelType==='driver'||panelType==='parent')&&!Number(u.portalReady)?'بدون حساب':'فعال')+'</em><i aria-hidden="true">←</i></button>').join(''):'<div class="sy-impersonation-empty">'+esc(panelInfo.empty)+'</div>';
  usersBox.querySelectorAll('[data-user]').forEach(button=>button.onclick=()=>chooseUser(Number(button.dataset.user)));
 };
 const selectCompany=async id=>{
  selectedCompany=companies.find(a=>Number(a.id)===id);if(!selectedCompany)return;
  const requestSequence=++companyLoadSequence;
  showError('');companyStep.hidden=true;userStep.hidden=false;
  overlay.querySelector('[data-step-indicator="company"]').classList.remove('active');
  overlay.querySelector('[data-step-indicator="user"]').classList.add('active');
  overlay.querySelector('#syImpersonationSelectedCompany').textContent='شرکت انتخاب‌شده: '+selectedCompany.name;
  users=[];usersBox.textContent='در حال دریافت '+panelInfo.userTitle+'…';userSearch.value='';confirmBox.hidden=true;selectedUser=null;
  try{
   const data=await api('/api/v1/admin/impersonation/users?agencyId='+encodeURIComponent(id)+'&panelType='+encodeURIComponent(panelType));
   if(!overlay.isConnected||requestSequence!==companyLoadSequence||Number(selectedCompany?.id)!==id)return;
   users=(data.users||[]).map(u=>({...u,userKey:(panelType==='driver'||panelType==='parent')?Number(panelType==='driver'?u.driverId:u.guardianId)*-1:Number(u.id)}));renderUsers();userSearch.focus();
  }catch(error){
   if(!overlay.isConnected||requestSequence!==companyLoadSequence)return;
   usersBox.textContent='دریافت کاربران ناموفق بود.';showError(error.message);
  }
 };
 const chooseUser=id=>{
  selectedUser=users.find(u=>Number(u.userKey??u.id)===id);if(!selectedUser)return;
  overlay.querySelector('#syImpersonationChosenName').textContent=((selectedUser.first_name||'')+' '+(selectedUser.last_name||'')).trim()||selectedUser.username;
  overlay.querySelector('#syImpersonationChosenDetails').textContent='نام کاربری: '+selectedUser.username+' · نقش: '+(selectedUser.roles||'بدون نقش')+' · شرکت: '+selectedCompany.name;
  startButton.textContent=panelType==='driver'?'مدیریت پنل راننده':panelInfo.button;
  confirmBox.hidden=false;showError('');
  confirmBox.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'nearest'});
 };
 overlay.querySelector('#syImpersonationBack').onclick=()=>{
  companyLoadSequence++;selectedCompany=null;selectedUser=null;users=[];userStep.hidden=true;companyStep.hidden=false;
  overlay.querySelector('[data-step-indicator="company"]').classList.add('active');
  overlay.querySelector('[data-step-indicator="user"]').classList.remove('active');
  confirmBox.hidden=true;showError('');renderCompanies();companySearch.focus();
 };
 companySearch.addEventListener('input',renderCompanies);userSearch.addEventListener('input',()=>{confirmBox.hidden=true;selectedUser=null;renderUsers();});
 companySearch.focus();
 startButton.onclick=async()=>{
  if(!selectedCompany||!selectedUser)return;
  if(panelType==='driver'){await openDriverPreview(selectedUser);return;}
  if(panelType==='parent'){await openParentInspection(selectedUser);return;}
  startButton.disabled=true;startButton.textContent='در حال ورود امن به پنل کاربر…';showError('');
  try{
   await api('/api/v1/admin/impersonation/start',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({agencyId:Number(selectedCompany.id),userId:Number(selectedUser.id),panelType})});
   close();history.replaceState(history.state,'',location.pathname+location.search+'#dashboard');await boot();
  }catch(error){showError(error.message||'ورود نیابتی ناموفق بود.');startButton.disabled=false;startButton.textContent=panelInfo.button;}
 };
 try{
  const data=await api('/api/v1/admin/overview');
  if(!overlay.isConnected)return;
  companies=(data.agencies||[]).filter(a=>Number(a.is_active)===1);renderCompanies();
  if(!companies.length)companiesBox.textContent='هیچ شرکت فعالی برای ورود نیابتی وجود ندارد.';
 }catch(error){if(overlay.isConnected){companiesBox.textContent='دریافت فهرست شرکت‌ها ناموفق بود.';showError(error.message);}}
}
