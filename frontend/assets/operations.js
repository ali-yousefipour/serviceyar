/* Transport operations. All writes use the authenticated API and server-side agency scope. */
'use strict';
const labels={partyName:'نام دریافت‌کننده / پرداخت‌کننده',fullName:'نام و نام خانوادگی',driverName:'راننده تخصیص‌یافته',ownerName:'نام مالک خودرو',paymentStatus:'وضعیت پرداخت',providerStatus:'وضعیت سرویس‌دهنده',attempts:'تعداد تلاش',name:'نام مدرسه',code:'کد',schoolType:'نوع مدرسه',managerName:'نام مدیر',phone:'تلفن',region:'ناحیه',gender:'جنسیت',shift:'شیفت',educationLevels:'مقاطع تحصیلی',firstName:'نام',lastName:'نام خانوادگی',nationalCode:'کد ملی',grade:'پایه تحصیلی',mobile:'شماره همراه',schoolId:'مدرسه',driverId:'راننده',vehicleId:'خودرو',packId:'پک سرویس',plate:'پلاک',model:'مدل خودرو',capacity:'ظرفیت',status:'وضعیت',rating:'امتیاز',title:'عنوان',contractNo:'شماره قرارداد',partyType:'نوع طرف حساب',partyId:'طرف حساب',startDate:'تاریخ شروع',endDate:'تاریخ پایان',amount:'مبلغ (ریال)',workDate:'تاریخ کارکرد',paymentDate:'تاریخ پرداخت',entryDate:'تاریخ',referenceNo:'شماره پیگیری',dueDate:'سررسید',invoiceId:'فاکتور',method:'روش پرداخت',parentId:'حساب بالادست',isActive:'فعال',documentNo:'شماره سند',documentDate:'تاریخ سند',description:'شرح',totalAmount:'مبلغ کل',ownerType:'نوع مالک',ownerId:'مالک',balance:'موجودی',channel:'کانال',recipientType:'نوع گیرنده',recipientId:'گیرنده',subject:'موضوع',body:'متن',serviceId:'سرویس',oldDriverId:'راننده قبلی',newDriverId:'راننده جدید',reason:'دلیل',address:'نشانی',latitude:'عرض جغرافیایی',longitude:'طول جغرافیایی',returnAddress:'نشانی برگشت',startAddress:'نشانی مبدا',endAddress:'نشانی مقصد',guardianFirstName:'نام ولی',guardianLastName:'نام خانوادگی ولی',guardianNationalCode:'کد ملی ولی',guardianRelation:'نسبت ولی',fatherName:'نام پدر',nationality:'ملیت',healthStatus:'وضعیت جسمانی',emergencyPhone:'شماره‌های اضطراری',academicYear:'سال تحصیلی',serviceStatus:'وضعیت سرویس',monthlyFee:'ماهیانه (ریال)',annualFee:'مبلغ سالانه (ریال)',annualMonths:'ماه‌های سال تحصیلی',agreedAmount:'مبلغ توافقی راننده (ریال)',isRepresentative:'نماینده',bankName:'نام بانک',bankAccount:'شماره حساب',iban:'شماره شبا',direction:'نوع عملیات',note:'توضیحات',score:'امتیاز',studentId:'دانش‌آموز',response:'پاسخ',issueDate:'تاریخ صدور',pickupAddress:'نشانی سوار شدن',pickupOrder:'ترتیب توقف'};
const statuses={no_request:'بدون درخواست',pending:'در انتظار تأیید',prepayment:'در انتظار پیش‌پرداخت',waiting:'در انتظار سرویس',assigned:'دارای سرویس',cancelled:'انصراف',active:'فعال',inactive:'غیرفعال',draft:'پیش‌نویس',open:'باز',paid:'پرداخت شده',overdue:'سررسید گذشته',approved:'تأیید شده',rejected:'رد شده',queued:'در صف ارسال',sent:'پذیرفته شده توسط سرویس',uncertain:'نیازمند بررسی',failed:'ناموفق',closed:'بسته شده'};
Object.assign(labels,{tariffDistanceKm:'مسافت تعرفه (کیلومتر)',tariffVehicleClass:'کلاس خودرو در تعرفه'});
const transportFields={
 schools:['name','code','schoolType','gender','region','managerName','phone','shift','educationLevels','address','latitude','longitude'],
 students:['mobile','guardianFirstName','guardianLastName','guardianNationalCode','guardianRelation','address','returnAddress','latitude','longitude','schoolId','grade','shift','academicYear','firstName','lastName','gender','nationality','nationalCode','fatherName','emergencyPhone','healthStatus','serviceStatus','tariffDistanceKm','tariffVehicleClass','monthlyFee'],
 drivers:['firstName','lastName','nationalCode','mobile','status','vehicleId','isRepresentative','address','bankName','bankAccount','iban'],
 services:['title','code','schoolId','driverId','packId','capacity','shift','status','monthlyFee','agreedAmount','startAddress','endAddress'],
 contracts:['title','contractNo','partyType','partyId','startDate','endDate','amount','status'],
 'driver-work':['driverId','workDate','serviceId','amount','status','note'],
 'driver-payments':['driverId','paymentDate','amount','referenceNo','note'],
 invoices:['title','partyType','partyId','amount','status','issueDate','dueDate'],
 payments:['invoiceId','amount','paymentDate','method','referenceNo','note'],
 ledger:['partyType','partyId','direction','amount','entryDate','method','referenceNo','note'],
 'driver-ratings':['driverId','score','note'],
 'parent-reports':['studentId','subject','body','status','response']
};
resources.ledger=['دریافت و پرداخت',transportFields.ledger];resources['driver-ratings']=['امتیاز و گزارش',transportFields['driver-ratings']];resources['parent-reports']=['گزارشات والدین',transportFields['parent-reports']];
for(const [key,fields] of Object.entries(transportFields))resources[key][1]=fields;
const columns={messages:['channel','recipientType','recipientId','subject','status','providerStatus','attempts'],schools:['code','name','shift','schoolType','region'],students:['fullName','schoolId','driverName','serviceStatus','paymentStatus','mobile','monthlyFee','annualFee','address'],drivers:['firstName','lastName','mobile','status','vehicleId','bankAccount'],vehicles:['plate','ownerName','driverName','model','capacity'],services:['code','title','driverId','schoolId','capacity','monthlyFee','agreedAmount','shift']};
const relations={schoolId:'schools',driverId:'drivers',oldDriverId:'drivers',newDriverId:'drivers',studentId:'students',vehicleId:'vehicles',serviceId:'services',packId:'packs',invoiceId:'invoices',parentId:'accounts'};
const permissionFor={'installments':'finance','school-statement':'finance','daily-schedule':'services','driver-assignment':'services','school-report':'reports',schools:'schools',students:'students',drivers:'drivers',vehicles:'drivers',services:'services',packs:'services',contracts:'services','driver-work':'finance','driver-payments':'finance','school-charges':'finance','service-charges':'finance',invoices:'finance',payments:'finance',accounts:'finance','accounting-documents':'finance',wallets:'finance',ledger:'finance',messages:'messages','driver-changes':'services','driver-ratings':'drivers','parent-reports':'messages',reports:'reports',monitoring:'monitoring','bale-payments':'finance',settings:'settings',dashboard:'dashboard',grouping:'services',payroll:'finance','finance-report':'finance',distances:'reports',import:'students','previous-students':'students',agreements:'services'};
const menus=[
 ['schools','مدرسه',[['schools/new','مدرسه جدید'],['schools','لیست مدارس'],['school-report','گزارش مدارس']]],
 ['students','دانش‌آموزان',[['students/new','دانش‌آموز جدید'],['import','ثبت دانش‌آموز از اکسل'],['previous-students','دانش‌آموزان سال قبل'],['students','لیست دانش‌آموزان']]],
 ['drivers','رانندگان',[['drivers','مدیریت راننده‌ها'],['driver-ratings','امتیاز و گزارش'],['driver-work','گزارش عملکرد راننده'],['finance-report','دریافتی پرداختی رانندگان']]],
 ['services','سرویس‌ها',[['services/new','سرویس جدید'],['grouping','دسته‌بندی هوشمند'],['packs','پک‌های سرویس'],['services','عملیات سرویس‌ها'],['students/fees','تغییر سریع هزینه سرویس'],['service-charges','حق‌السرویس']]],
 ['accounts','مالی',[['accounts','تعاریف حسابداری'],['accounting-documents','حسابداری حسیبان'],['agreements','ثبت مبلغ توافقی راننده'],['payroll','فیش حقوق راننده'],['driver-payments','سند پرداخت حقوق راننده'],['finance-report','گزارش دریافتی پرداختی'],['invoices','گزارش وضعیت کلی پرداخت'],['wallets','کیف پول'],['installments','پیش‌پرداخت و اقساط']]],
 ['reports','امکانات',[['messages','پیام‌رسانی'],['monitoring','مانیتورینگ'],['distances','بررسی فاصله‌های ناجور'],['reports','خروجی گزارشات'],['settings','تنظیمات'],['vehicles','خودروها'],['driver-assignment','تخصیص پیشنهادی راننده'],['daily-schedule','برنامه روزانه و تداخل شیفت‌ها'],['driver-changes','تغییر راننده'],['contracts','قراردادها'],['accounting-documents/statement','گزارش حساب'],['accounting-documents/quick','سند سریع'],['accounting-documents/defaults','حساب‌های پیش‌فرض'],['accounting-documents/cheques','چک‌های دریافتی و عملیات چک'],['ledger','دریافت و پرداخت'],['school-statement','صورت‌وضعیت سرویس مدارس'],['bale-payments','پرداخت کیف پول بله'],['school-charges','بدهی مدارس'],['payments','پرداخت‌ها'],['parent-reports','گزارشات والدین']]]
];
for(const [k,v] of Object.entries(resources))titles[k]=v[0];
Object.assign(titles,{'installments':'برنامه پیش‌پرداخت و اقساط','school-statement':'صورت‌وضعیت سرویس مدارس','daily-schedule':'برنامه روزانه سرویس‌ها','driver-assignment':'تخصیص پیشنهادی راننده','school-report':'گزارش مدارس','bale-payments':'پرداخت کیف پول بله',import:'ثبت دانش‌آموز از اکسل','previous-students':'دانش‌آموزان سال قبل',grouping:'دسته‌بندی هوشمند',payroll:'فیش حقوق راننده','finance-report':'گزارش دریافتی پرداختی',monitoring:'مانیتورینگ',distances:'بررسی فاصله‌های ناجور',agreements:'ثبت مبلغ توافقی راننده'});
let pageToken=0,gridState=null,lookupCache={},initialSearch='',initialStudentStatus='',returnFocus=null;
const can=(key,action='view')=>session?.roles?.includes('super_admin')||session?.permissions?.includes((permissionFor[key]||key)+'.'+action);
const scoped=url=>url+(url.includes('?')?'&':'?')+'agencyId='+encodeURIComponent(currentAgency?.id||'');
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
 const raw=normalizePlate(value),match=raw.match(/^(\d{2})([آ-یA-Za-z])(\d{3})[-\/]?(\d{2})$/);
 if(!match)return '<span class="plate-raw" dir="ltr">'+esc(persianDigits(value||'—'))+'</span>';
 const [,first,letter,middle,region]=match,yellow=letter==='ت';
 return '<span class="iran-plate'+(yellow?' iran-plate-yellow':'')+'" role="img" aria-label="'+esc('پلاک '+persianDigits(first)+' '+letter+' '+persianDigits(middle)+' ایران '+persianDigits(region))+'"><span class="iran-plate-blue"><span class="iran-flag"><i></i><i></i><i></i></span><small>I.R.<br>IRAN</small></span><span class="iran-plate-main"><b>'+persianDigits(first)+'</b><b>'+esc(letter)+'</b><b>'+persianDigits(middle)+'</b></span><span class="iran-plate-region"><small>ایران</small><b>'+persianDigits(region)+'</b></span></span>';
}

const year=()=>{const p=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',timeZone:'Asia/Tehran'}).formatToParts(new Date()),y=Number(p.find(x=>x.type==='year').value),m=Number(p.find(x=>x.type==='month').value);return (m<7?y-1:y)+'/'+(m<7?y:y+1)};
const todayISO=()=>{const p=new Intl.DateTimeFormat('en-US',{year:'numeric',month:'2-digit',day:'2-digit',timeZone:'Asia/Tehran'}).formatToParts(new Date());return ['year','month','day'].map(k=>p.find(x=>x.type===k).value).join('-');};
const dateText=v=>{if(!v)return '—';const raw=String(v).slice(0,10);if(/^1[34]\d{2}[-/]\d{1,2}[-/]\d{1,2}$/.test(raw))return persianDigits(raw.replace(/-/g,'/'));const d=/^\d{4}-\d{2}-\d{2}$/.test(raw)?new Date(raw+'T12:00:00Z'):new Date(v);return Number.isNaN(d.getTime())?'—':new Intl.DateTimeFormat('fa-IR',{dateStyle:'medium',timeZone:'Asia/Tehran'}).format(d);};
const person=r=>[r.first_name||r.firstName,r.last_name||r.lastName].filter(Boolean).join(' ')||r.name||r.title||r.plate||r.code||('#'+r.id);
function notify(message,error=false){let box=$('notice');if(!box){box=document.createElement('div');box.id='notice';box.setAttribute('role','status');document.body.append(box);}box.textContent=message;box.className=error?'notice error':'notice';clearTimeout(notify.timer);notify.timer=setTimeout(()=>box.remove(),6500);}
function renderNav(){
 document.querySelector('.eyebrow').innerHTML='<img class="site-wordmark" src="/assets/logo.png" alt="سرویس یار">';
 if(!$('ownPassword')){const b=document.createElement('button');b.id='ownPassword';b.type='button';b.className='account-password';b.textContent='تغییر رمز من';b.onclick=()=>passwordDialog({id:session.user.id,username:session.user.username},false,true);document.querySelector('.header-actions').append(b);}
 if(!$('navigationToggle')){const button=document.createElement('button');button.id='navigationToggle';button.type='button';button.textContent='☰';button.setAttribute('aria-label','باز کردن منوی اصلی');button.setAttribute('aria-expanded','false');document.querySelector('.header').prepend(button);const shade=document.createElement('button');shade.id='navigationShade';shade.type='button';shade.setAttribute('aria-label','بستن منوی اصلی');shade.hidden=true;document.querySelector('#app').append(shade);const toggle=open=>{document.querySelector('.sidebar').classList.toggle('nav-open',open);shade.hidden=!open;button.setAttribute('aria-expanded',String(open));};button.onclick=()=>toggle(button.getAttribute('aria-expanded')!=='true');shade.onclick=()=>toggle(false);document.addEventListener('keydown',e=>{if(e.key==='Escape'&&button.getAttribute('aria-expanded')==='true'){toggle(false);$('nav').querySelectorAll('details').forEach(d=>d.open=false);button.focus();}});}
 renderAgencySelector();$('nav').innerHTML=`<a class="nav" href="#dashboard" title="داشبورد">${samarMenuIcon('dashboard')}<span>داشبورد</span></a>`+menus.map(([icon,title,items])=>{const visible=items.filter(([key])=>can(key.split('/')[0],(key.endsWith('/new')||key.endsWith('/quick')||key.endsWith('/fees')||key==='driver-assignment')?'manage':'view'));return visible.length?`<details class="nav-group"><summary title="${esc(title)}">${samarMenuIcon(icon)}<span>${esc(title)}</span></summary><div class="nav-popup" aria-label="${esc(title)}">${visible.map(([key,text])=>`<a href="#${key}" aria-label="${esc(text)}">${samarMenuIcon(key)}<span>${esc(text)}</span></a>`).join('')}</div></details>`:''}).join('')+(session?.roles?.includes('super_admin')?`<a class="nav" href="#companies" title="تنظیمات سامانه">${navIcon('settings')}<span>تنظیمات سامانه</span></a>`:'');
 const navSearchButton=document.createElement('button');navSearchButton.type='button';navSearchButton.id='syMenuSearchTrigger';navSearchButton.className='sy-menu-search-trigger';navSearchButton.title='جست‌وجوی منو (Ctrl + K)';navSearchButton.setAttribute('aria-label','جست‌وجوی صفحات و منوهای سامانه');navSearchButton.innerHTML='<span aria-hidden="true">⌕</span><small>جست‌وجوی منو</small>';$('nav').prepend(navSearchButton);
 const navSearch=document.createElement('label');navSearch.className='sy-nav-search';navSearch.innerHTML='<span>جست‌وجوی بخش‌های سامانه</span><input type="search" id="syNavSearch" placeholder="نام صفحه یا عملیات…" autocomplete="off" aria-label="جست‌وجوی منو"><small id="syNavSearchStatus" role="status"></small>';
 $('nav').prepend(navSearch);
 const normalizeMenuText=value=>String(value||'').replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/[\u200c\s]+/g,' ').trim().toLocaleLowerCase('fa');
 const navSearchInput=navSearch.querySelector('input');
 let menuPalette=document.getElementById('syMenuPalette');
 if(!menuPalette){menuPalette=document.createElement('div');menuPalette.id='syMenuPalette';menuPalette.className='sy-menu-palette';menuPalette.hidden=true;menuPalette.innerHTML='<div class="sy-menu-palette-shade" data-menu-close></div><section class="sy-menu-palette-dialog" role="dialog" aria-modal="true" aria-labelledby="syMenuPaletteTitle"><div class="sy-menu-palette-head"><h2 id="syMenuPaletteTitle">جست‌وجوی بخش‌های سامانه</h2><button type="button" data-menu-close aria-label="بستن جست‌وجو">×</button></div><input type="search" id="syMenuPaletteInput" autocomplete="off" placeholder="نام صفحه، عملیات یا بخش را بنویسید…" aria-label="جست‌وجوی بخش‌های سامانه"><div id="syMenuPaletteResults" class="sy-menu-palette-results" role="listbox" aria-label="نتایج جست‌وجوی منو"></div><p class="sy-menu-palette-help">↑ ↓ انتخاب نتیجه · Enter بازکردن صفحه · Esc بستن</p></section>';document.body.append(menuPalette);}
 const paletteInput=menuPalette.querySelector('#syMenuPaletteInput'),paletteResults=menuPalette.querySelector('#syMenuPaletteResults');
 const normalizePalette=value=>String(value||'').replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/[\u200c\s]+/g,' ').trim().toLocaleLowerCase('fa');
 const getPaletteItems=()=>[...$('nav').querySelectorAll('a[href^="#"]')].map(a=>({href:a.getAttribute('href'),title:a.getAttribute('aria-label')||a.querySelector('span')?.textContent||a.textContent.trim(),group:a.closest('.nav-group')?.querySelector('summary span')?.textContent||'دسترسی سریع'})).filter(a=>a.title);
 let paletteActive=0;
 const paintPalette=()=>{
  const query=normalizePalette(paletteInput.value);const items=getPaletteItems().filter(x=>!query||normalizePalette(x.title+' '+x.group).includes(query)).slice(0,30);
  paletteResults.innerHTML=items.length?items.map((item,i)=>'<a role="option" aria-selected="'+(i===paletteActive)+'" class="sy-menu-palette-result '+(i===paletteActive?'selected':'')+'" href="'+esc(item.href)+'" data-palette-index="'+i+'"><span><strong>'+esc(item.title)+'</strong><small>'+esc(item.group)+'</small></span><span aria-hidden="true">↵</span></a>').join(''):'<p class="sy-menu-palette-empty">موردی با این عنوان پیدا نشد.</p>';
  paletteResults.querySelectorAll('a').forEach(a=>a.onclick=()=>closePalette());
 };
 const closePalette=()=>{menuPalette.hidden=true;document.body.classList.remove('sy-menu-palette-open');navSearchButton.focus();};
 const openPalette=()=>{paletteActive=0;paletteInput.value='';menuPalette.hidden=false;document.body.classList.add('sy-menu-palette-open');paintPalette();paletteInput.focus();};
 navSearchButton.onclick=openPalette;
 menuPalette.querySelectorAll('[data-menu-close]').forEach(b=>b.onclick=closePalette);
 paletteInput.oninput=()=>{paletteActive=0;paintPalette();};
 paletteInput.onkeydown=e=>{
  const items=[...paletteResults.querySelectorAll('a')];
  if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(!items.length)return;paletteActive=(paletteActive+(e.key==='ArrowDown'?1:-1)+items.length)%items.length;paintPalette();paletteResults.querySelector('.selected')?.scrollIntoView({block:'nearest'});}
  if(e.key==='Enter'&&items.length){e.preventDefault();location.hash=items[paletteActive]?.getAttribute('href')||items[0].getAttribute('href');closePalette();}
 };
 if(!window.syMenuPaletteKeys){window.syMenuPaletteKeys=true;document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();document.getElementById('syMenuSearchTrigger')?.click();}else if(e.key==='Escape'&&!document.getElementById('syMenuPalette')?.hidden){document.querySelector('#syMenuPalette [data-menu-close]')?.click();}});}

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
  $('nav').querySelectorAll(':scope > a.nav').forEach(link=>{link.hidden=!!query&&!normalizeMenuText(link.textContent).includes(query);if(!link.hidden)matches++;});
  navSearch.querySelector('small').textContent=query?(matches?'نتایج مرتبط: '+number(matches):'بخشی با این نام پیدا نشد.'):'';
 });
  $('nav').querySelectorAll('details').forEach(d=>d.addEventListener('toggle',()=>{if(d.open){if(navSearchInput.value.trim())return;const popup=d.querySelector('.nav-popup'),rect=d.getBoundingClientRect();popup.style.top=Math.max(10,Math.min(rect.top,innerHeight-Math.min(popup.scrollHeight,innerHeight*.75)-12))+'px';$('nav').querySelectorAll('details').forEach(other=>{if(other!==d)other.open=false;});}}));
 if(!$('quickReceipt')){const button=document.createElement('button');button.id='quickReceipt';button.title='ثبت دریافت و پرداخت';button.innerHTML=navIcon('payments');button.onclick=()=>{if(location.hash!=='#ledger')location.hash='ledger';else resourcePage('ledger',pageToken);};$('nav').before(button);} $('quickReceipt').hidden=!can('ledger','manage');
 document.querySelector('.logo').title='سرویس‌یار';document.querySelector('.main').classList.add('transport-main');
 if(!$('sessionFooter')){const footer=document.createElement('footer');footer.id='sessionFooter';document.querySelector('.main').append(footer);}
 $('sessionFooter').textContent='سال تحصیلی '+year()+' • '+(currentAgency?.name||'مدیریت سامانه')+' • '+(session?.user?.firstName||session?.user?.username||'')+' • '+new Intl.DateTimeFormat('fa-IR',{dateStyle:'full',timeZone:'Asia/Tehran'}).format(new Date());
 $('userName').textContent=[session?.user?.firstName,session?.user?.lastName].filter(Boolean).join(' ')||session?.user?.username||'';
}
function render(){
 if(!session)return;if(!document.getElementById('syMenuPalette')?.hidden)document.querySelector('#syMenuPalette [data-menu-close]')?.click();if($('modal')?.firstElementChild)closeDialog();disposeMaps();$('dashboardBackdrop')?.remove();const token=++pageToken;const [raw,action,recordId]=location.hash.slice(1).split('/');let key=raw||'dashboard';if(key==='admin')key='companies';if(!titles[key])key='dashboard';
 document.querySelectorAll('.nav-group').forEach(d=>{if(!$('syNavSearch')?.value.trim())d.open=false;});$('pageTitle').textContent=titles[key];$('app').classList.toggle('dashboard-active',key==='dashboard');$('content').classList.toggle('dashboard-content',key==='dashboard');
 $('content').innerHTML='<div class="loading">در حال دریافت اطلاعات…</div>';
 document.querySelector('.sidebar').classList.remove('nav-open');if($('navigationShade'))$('navigationShade').hidden=true;if($('navigationToggle'))$('navigationToggle').setAttribute('aria-expanded','false');
 $('nav').querySelectorAll('a').forEach(a=>{const active=a.getAttribute('href')==='#'+(raw||'dashboard')+(action?'/'+action:'');a.classList.toggle('active',active);if(active)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');});$('nav').querySelectorAll('details').forEach(d=>d.classList.toggle('current-group',!!d.querySelector('[aria-current=page]')));
 if(key!=='companies'&&!currentAgency){if(key==='dashboard'&&session.roles?.includes('super_admin')){companiesPage();return;}$('content').innerHTML='<div class="empty">ابتدا شرکت فعال را انتخاب کنید.</div>';return;}
 if(key!=='companies'&&!can(key)){ $('content').innerHTML='<div class="error">برای مشاهده این بخش مجوز ندارید.</div>';return;}
 const task=key==='services'&&action==='new'?newServicePage(token,recordId):key==='installments'?installmentPage():key==='daily-schedule'?dailySchedulePage(token):key==='driver-assignment'?driverAssignmentPage(token):key==='accounting-documents'?(action==='statement'?accountStatementPage(token):action==='quick'?quickJournalPage(token):action==='defaults'?financialAccountsPage(token):action==='cheques'?chequePage(token):journalPage(token)):key==='dashboard'?dashboard(token):key==='companies'?companiesPage():key==='settings'?settingsPage():key==='reports'?reportsPage(token):key==='import'?importPage(token):key==='bale-payments'?balePaymentsPage(token):key==='school-statement'?schoolStatementPage(token):['payroll','finance-report','distances','monitoring','grouping','school-report'].includes(key)?operationPage(key,token):resourcePage(key==='previous-students'?'students':key==='agreements'?'services':key,token,{previous:key==='previous-students',agreements:key==='agreements'});
 Promise.resolve(task).then(()=>{if(token!==pageToken)return;if(action==='new'&&resources[key]&&!['accounting-documents','services','ledger'].includes(key))formResource(key,null);if(action==='fees')bulkFeeDialog();}).catch(e=>{if(token===pageToken)$('content').innerHTML='<div class="error">'+esc(e.message)+'</div>';});
}
function accountingTabs(active){const selected=active===true?'statement':active||'journal';return `<nav class="accounting-tabs" aria-label="حسابداری">${[['journal','ثبت سند',''],['statement','گزارش حساب','/statement'],['defaults','حساب‌های پیش‌فرض','/defaults'],['cheques','چک‌های دریافتی','/cheques'],...(can('accounting-documents','manage')?[['quick','سند سریع','/quick']]:[])].map(([key,title,path])=>`<a href="#accounting-documents${path}" ${selected===key?'aria-current="page"':''}>${title}</a>`).join('')}</nav>`;}
const chequeStatuses={received:'دریافت‌شده',collected:'وصول‌شده',bounced:'برگشتی',cancelled:'ابطال‌شده',saved:'ثبت / ویرایش'};
Object.assign(labels,{branchName:'شعبه',serialNo:'شماره / سریال چک',receivedFrom:'دریافت از',accountId:'سرفصل حساب',receivedDate:'تاریخ دریافت',dueDate:'سررسید',amount:'مبلغ (ریال)',description:'بابت / توضیحات'});
async function chequePage(token=pageToken,filters={},page=1){
 const query=new URLSearchParams({...filters,page}),d=await api(scoped('/api/v1/accounting/cheques?'+query));if(token!==pageToken)return;
 $('content').innerHTML=`<section class="panel">${accountingTabs('cheques')}<div class="page-intro"><div><h2>چک‌های دریافتی</h2><p>ثبت مشخصات، سررسید و سابقه وصول یا برگشت چک‌ها. هنگام وصول، با انتخاب حساب بانک، سند حسابداری تراز نیز خودکار ثبت و تأیید می‌شود.</p></div>${can('accounting-documents','manage')?'<button class="primary" id="newCheque">ثبت چک دریافتی</button>':''}</div><form id="chequeFilters" class="filters"><label>شماره، بانک یا نام پرداخت‌کننده<input name="search" value="${esc(filters.search||'')}" maxlength="200"></label><label>وضعیت<select name="status"><option value="">همه وضعیت‌ها</option>${Object.entries(chequeStatuses).filter(([k])=>k!=='saved').map(([k,v])=>`<option value="${k}" ${filters.status===k?'selected':''}>${v}</option>`).join('')}</select></label><label>سررسید از<input name="from" type="date" value="${esc(filters.from||'')}"></label><label>سررسید تا<input name="to" type="date" value="${esc(filters.to||'')}"></label><button>جستجو</button></form><div class="statement-summary"><span>تعداد: <b>${number(d.pagination.total)}</b></span><span>جمع مبلغ: <b>${number(d.amount)} ریال</b></span></div>${can('reports')?'<button id="chequesExport">خروجی اکسل</button>':''}<div class="table-wrap"><table><thead><tr><th>شماره چک</th><th>بانک / شعبه</th><th>دریافت از</th><th>سررسید</th><th>مبلغ · ریال</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>${d.items.map(r=>`<tr><td>${esc(r.serial_no)}</td><td>${esc(r.bank_name+' / '+(r.branch_name||'—'))}</td><td>${esc(r.received_from)}</td><td>${dateText(r.due_date)}</td><td>${number(r.amount)}</td><td><span class="cheque-state ${r.status}">${chequeStatuses[r.status]}</span></td><td class="row-actions"><button data-cheque-view="${r.id}">جزئیات و سوابق</button>${r.status==='received'&&can('accounting-documents','manage')?`<button data-cheque-edit="${r.id}">ویرایش</button><button data-cheque-action="${r.id}">ثبت عملیات</button>`:''}</td></tr>`).join('')||'<tr><td colspan="7" class="empty">چکی با این شرایط پیدا نشد.</td></tr>'}</tbody></table></div><div class="pager"><button id="chequePrev" ${page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))}</span><button id="chequeNext" ${page>=d.pagination.pages?'disabled':''}>بعدی</button></div></section>`;
 const refresh=()=>chequePage(token,filters,page);$('chequeFilters').onsubmit=e=>{e.preventDefault();chequePage(token,Object.fromEntries(new FormData(e.target))).catch(e=>notify(e.message,true));};$('chequePrev').onclick=()=>chequePage(token,filters,page-1).catch(e=>notify(e.message,true));$('chequeNext').onclick=()=>chequePage(token,filters,page+1).catch(e=>notify(e.message,true));$('newCheque')?.addEventListener('click',()=>chequeEditor(null,token,refresh).catch(e=>notify(e.message,true)));
 $('chequesExport')?.addEventListener('click',()=>downloadFile('/api/v1/reports/export?'+new URLSearchParams({resource:'cheques',search:filters.search||'',status:filters.status||'',dateFrom:filters.from||'',dateTo:filters.to||''}),'چک‌های-دریافتی.xlsx'));
 $('content').querySelectorAll('[data-cheque-edit]').forEach(b=>b.onclick=()=>chequeEditor(Number(b.dataset.chequeEdit),token,refresh).catch(e=>notify(e.message,true)));
 $('content').querySelectorAll('[data-cheque-view]').forEach(b=>b.onclick=async()=>{try{const r=await api(scoped('/api/v1/accounting/cheques/'+b.dataset.chequeView));if(token!==pageToken)return;dialog('چک '+r.serial_no,`<dl class="detail-grid">${[['بانک',r.bank_name],['شعبه',r.branch_name],['شماره حساب',r.bank_account],['دریافت از',r.received_from],['حساب',r.account_code+' · '+r.account_title],['مبلغ',number(r.amount)+' ریال'],['دریافت',dateText(r.received_date)],['سررسید',dateText(r.due_date)],['وضعیت',chequeStatuses[r.status]],['شناسه سند دریافت',r.receipt_document_id],['شناسه سند برگشت',r.reversal_document_id],['شناسه سند وصول',r.collection_document_id],['بابت',r.description]].map(([k,v])=>`<div><dt>${k}</dt><dd>${esc(v||'—')}</dd></div>`).join('')}</dl><h4>سابقه عملیات</h4>${simpleTable(['عملیات','تاریخ','کاربر','شرح'],r.events.map(e=>[chequeStatuses[e.event_type]||e.event_type,dateText(e.event_date),[e.first_name,e.last_name].filter(Boolean).join(' '),e.note||'—']))}`);}catch(e){notify(e.message,true);}});
 $('content').querySelectorAll('[data-cheque-action]').forEach(b=>b.onclick=async()=>{let accounts;try{accounts=await lookup('accounts');}catch(e){notify(e.message,true);return;}if(token!==pageToken)return;const row=d.items.find(r=>String(r.id)===b.dataset.chequeAction);dialog('ثبت عملیات چک '+row.serial_no,`<p>مبلغ ${number(row.amount)} ریال · ${esc(row.bank_name)}. پس از ثبت عملیات، مشخصات چک قابل تغییر نیست.</p><form id="chequeActionForm"><label>عملیات<select name="status" required><option value="">انتخاب کنید</option><option value="collected">وصول</option><option value="bounced">برگشت</option><option value="cancelled">ابطال</option></select></label><label>تاریخ عملیات<input name="date" type="date" required value="${todayISO()}"></label><label>حساب بانک برای سند وصول<select name="bankAccountId"><option value="">فقط ثبت وضعیت، بدون سند حسابداری</option>${accounts.filter(a=>Number(a.id)!==Number(row.account_id)).map(a=>`<option value="${a.id}">${esc(a.code+' · '+a.title)}</option>`).join('')}</select></label><p class="muted">انتخاب بانک فقط برای وصول است؛ بانک بدهکار و حساب چک بستانکار می‌شود.</p><label>شرح و پیگیری<textarea name="note" maxlength="1000" required></textarea></label><button class="primary">ثبت عملیات</button></form>`);$('chequeActionForm').elements.status.onchange=e=>{const bank=$('chequeActionForm').elements.bankAccountId;bank.disabled=e.target.value!=='collected';if(bank.disabled)bank.value='';};$('chequeActionForm').elements.bankAccountId.disabled=true;$('chequeActionForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{await write('/api/v1/accounting/cheques/'+row.id+'/transition',Object.fromEntries(new FormData(e.target)));closeDialog();notify('عملیات چک ثبت شد.');await refresh();}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;$('formError').focus();button.disabled=false;}};});
}
async function chequeEditor(id,token,refresh){
 const row=id?await api(scoped('/api/v1/accounting/cheques/'+id)):{received_date:todayISO(),due_date:todayISO()},defaults=await api(scoped('/api/v1/accounting/defaults')),accounts=[];let page=1;while(true){const d=await api(scoped('/api/v1/accounts?active=1&limit=200&page='+page++));if(token!==pageToken)return;accounts.push(...d.items);if(page>d.pagination.pages)break;}
 labels.counterAccountId='حساب مقابل سند دریافت (اختیاری)';const fields=['bankName','branchName','serialNo','bankAccount','receivedFrom','accountId','amount','receivedDate','dueDate','description',...(!id?['counterAccountId']:[])];
 dialog(id?'ویرایش چک دریافتی':'ثبت چک دریافتی',`<form id="editForm"><div class="form-grid">${fields.map(key=>{const value=val(row,key)||(key==='accountId'?defaults.chequeReceivable||'':'');return `<label>${labels[key]}${['bankName','serialNo','receivedFrom','accountId','amount','receivedDate','dueDate'].includes(key)?' *':''}${['accountId','counterAccountId'].includes(key)?`<select name="${key}" ${key==='accountId'?'required':''}><option value="">انتخاب حساب</option>${accounts.map(a=>`<option value="${a.id}" ${String(value)===String(a.id)?'selected':''}>${esc(a.code+' · '+a.title)}</option>`).join('')}</select>`:key==='description'?`<textarea name="${key}" maxlength="1000">${esc(value)}</textarea>`:`<input name="${key}" value="${esc(value)}" ${['receivedDate','dueDate'].includes(key)?'type="date" required':key==='amount'?'type="number" min="0.01" max="999999999999.99" step="0.01" required':['bankName','serialNo','receivedFrom'].includes(key)?'required maxlength="100"':'maxlength="100"'}>`}</label>`;}).join('')}</div><button id="saveRecord" class="primary">ذخیره چک</button></form>`);const form=$('editForm');resourceWizard(form,'cheques');form.onsubmit=async e=>{e.preventDefault();const button=$('saveRecord');button.disabled=true;try{await write('/api/v1/accounting/cheques'+(id?'/'+id:''),Object.fromEntries(new FormData(form)),id?'PUT':'POST');closeDialog();notify('چک دریافتی ذخیره شد.');await refresh();}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;$('formError').focus();button.disabled=false;}};
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
 $('content').innerHTML=`<section class="panel">${accountingTabs('quick')}<h2>سند سریع</h2><p>برای افراد انتخاب‌شده، حساب و مبلغ را تعیین کنید. حساب مقابل به‌صورت خودکار تراز می‌شود. سند ابتدا پیش‌نویس است.</p><form id="quickPartyFilter" class="filters"><label>نوع شخص<select name="partyType">${['students','drivers'].filter(k=>can(k)).map(k=>`<option value="${k}">${k==='students'?'دانش‌آموز':'راننده'}</option>`).join('')}</select></label><label>جستجوی نام<input name="search"></label><button type="submit">شروع / جستجو</button></form><form id="quickJournalForm"><div class="form-grid"><label>شماره سند<input name="documentNo" required maxlength="100"></label><label>تاریخ سند<input type="date" name="documentDate" value="${todayISO()}" required></label><label>طرف دوم حساب<select name="counterAccountId" required>${options}</select></label><label>نوع ردیف اشخاص<select name="side"><option value="debit">بدهکار</option><option value="credit">بستانکار</option></select></label><label>کلمه پیگیری<input name="trackingNo" maxlength="100"></label><label>شرح سند<input name="description" maxlength="1000" value="سند سریع"></label></div><div id="quickPartyRows" aria-live="polite"></div><p id="quickJournalTotal" role="status">جمع انتخاب‌شده: ۰ ریال</p><button id="quickJournalSave" class="primary" disabled>ذخیره پیش‌نویس</button></form></section>`;
 const form=$('quickJournalForm');let loadedType='',seq=0,loading=false,filters={};
 const total=()=>{let amount=0,count=0;form.querySelectorAll('[data-quick-party]').forEach(row=>{const chosen=row.querySelector('[name=chosen]').checked;row.querySelectorAll('input:not([name=chosen]),select').forEach(i=>i.disabled=!chosen);if(chosen){count++;amount+=Number(row.querySelector('[name=amount]').value||0);}});$('quickJournalTotal').textContent='جمع انتخاب‌شده: '+number(amount)+' ریال · '+number(count)+' نفر';$('quickJournalSave').disabled=loading||!count;};
 const load=async(page=1)=>{const current=++seq;loading=true;total();$('quickPartyRows').innerHTML='<p class="loading">در حال دریافت افراد…</p>';try{const query=new URLSearchParams({search:filters.search||'',active:1,limit:25,page});const d=await api(scoped('/api/v1/'+filters.partyType+'?'+query));if(token!==pageToken||current!==seq)return;loadedType=filters.partyType;if(!form.elements.counterAccountId.value){const suggested=defaults[loadedType==='students'?'serviceIncome':'driverSalary'];if(accounts.some(a=>String(a.id)===String(suggested)))form.elements.counterAccountId.value=suggested;}
 $('quickPartyRows').innerHTML=`<p>انتخاب‌ها مربوط به همین صفحه هستند. مبلغ پیشنهادی دانش‌آموز، هزینه ماهیانه ثبت‌شده است؛ حساب هر شخص را خودتان انتخاب کنید.</p><div class="table-wrap"><table><thead><tr><th>انتخاب</th><th>نام</th><th>حساب شخص</th><th>شرح ردیف</th><th>مبلغ · ریال</th></tr></thead><tbody>${d.items.map(r=>`<tr data-quick-party="${r.id}"><td><input type="checkbox" name="chosen" aria-label="انتخاب ${esc(person(r))}"></td><td>${esc(person(r))}</td><td><select name="accountId" aria-label="حساب ${esc(person(r))}" required disabled>${options}</select></td><td><input name="rowDescription" aria-label="شرح ${esc(person(r))}" maxlength="700" disabled></td><td><input name="amount" aria-label="مبلغ ${esc(person(r))}" type="number" min="0.01" max="999999999999.99" step="0.01" required value="${esc(loadedType==='students'?r.monthly_fee||0:0)}" disabled></td></tr>`).join('')||'<tr><td colspan="5" class="empty">موردی یافت نشد.</td></tr>'}</tbody></table></div><div class="pager"><button type="button" id="quickPrev" ${page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))}</span><button type="button" id="quickNext" ${page>=d.pagination.pages?'disabled':''}>بعدی</button></div>`;
 $('quickPrev').onclick=()=>load(page-1);$('quickNext').onclick=()=>load(page+1);$('quickPartyRows').oninput=total;
 }catch(e){if(token===pageToken&&current===seq)$('quickPartyRows').innerHTML='<p class="error">'+esc(e.message)+'</p>';}finally{if(token===pageToken&&current===seq){loading=false;total();}}};
 $('quickPartyFilter').onsubmit=e=>{e.preventDefault();filters=Object.fromEntries(new FormData(e.currentTarget));load();};
 form.onsubmit=async e=>{e.preventDefault();if(loading)return;const rows=[...form.querySelectorAll('[data-quick-party]')].filter(r=>r.querySelector('[name=chosen]').checked).map(r=>({partyId:r.dataset.quickParty,accountId:r.querySelector('[name=accountId]').value,amount:r.querySelector('[name=amount]').value,description:r.querySelector('[name=rowDescription]').value}));if(!rows.length)return;loading=true;total();try{const body=Object.fromEntries(new FormData(form));body.partyType=loadedType;body.rows=rows;const doc=await write('/api/v1/accounting/quick',body);notify('پیش‌نویس سند سریع ذخیره شد.');if(token===pageToken)await journalEditor(doc.id,token);}catch(err){notify(err.message,true);}finally{if(token===pageToken&&$('quickJournalSave')){loading=false;total();}}};
}

async function accountStatementPage(token=pageToken,initial=null,initialPage=1){
 const accounts=[];let page=1,more=true;while(more){const d=await api(scoped('/api/v1/accounts?limit=200&page='+page++));if(token!==pageToken)return;accounts.push(...d.items);more=page<=d.pagination.pages;}
 $('content').innerHTML=`<section class="panel">${accountingTabs(true)}<h2>گزارش حساب</h2><p>گردش اسناد تأییدشده؛ مانده مثبت بدهکار و مانده منفی بستانکار است. مبالغ به ریال هستند.</p><form id="statementForm" class="form-grid"><label>جستجوی نام و کد حساب<input id="statementSearch" type="search" autocomplete="off"></label><label>حساب<select name="accountId" required><option value="">انتخاب حساب</option>${accounts.map(a=>`<option value="${esc(a.id)}">${esc(a.code+' · '+a.title+(Number(a.is_active)?'':' (غیرفعال)'))}</option>`).join('')}</select></label><label>از تاریخ<input type="date" name="from"></label><label>تا تاریخ<input type="date" name="to"></label><button class="primary">گزارش</button></form><div id="statementResult" aria-live="polite"><p class="empty">حساب و بازه دلخواه را انتخاب کنید.</p></div></section>`;
 const form=$('statementForm');let request=0;
 $('statementSearch').oninput=e=>{const query=e.target.value.trim().toLocaleLowerCase('fa');form.elements.accountId.querySelectorAll('option').forEach(o=>o.hidden=!!o.value&&!o.textContent.toLocaleLowerCase('fa').includes(query));};
 const load=async(filters,page=1)=>{const seq=++request;const query=new URLSearchParams({...filters,page});$('statementResult').innerHTML='<p class="loading" role="status">در حال دریافت گردش حساب…</p>';try{const d=await api(scoped('/api/v1/accounting/statement?'+query));if(token!==pageToken||seq!==request)return;
 $('statementResult').innerHTML=`<h3>${esc(d.account.code+' · '+d.account.title)}</h3><div class="statement-summary"><span>مانده ابتدای بازه: <b>${number(d.opening)}</b></span><span>جمع بدهکار: <b>${number(d.debit)}</b></span><span>جمع بستانکار: <b>${number(d.credit)}</b></span><span>مانده پایان: <b>${number(d.closing)}</b></span></div>${session.permissions?.includes('reports.view')||session.roles?.includes('super_admin')?'<button id="statementExport">خروجی اکسل</button>':''}<div class="table-wrap"><table><thead><tr><th>ردیف</th><th>تاریخ</th><th>شماره سند</th><th>شرح ردیف</th><th>پیگیری</th><th>بدهکار</th><th>بستانکار</th><th>مانده</th></tr></thead><tbody>${d.items.map(r=>`<tr><td>${number(r.row_no)}</td><td>${dateText(r.document_date)}</td><td><button data-statement-doc="${r.document_id}">${esc(r.document_no)}</button></td><td>${esc(r.description)}</td><td>${esc(r.tracking_no)}</td><td>${number(r.debit)}</td><td>${number(r.credit)}</td><td>${number(r.balance)}</td></tr>`).join('')||'<tr><td colspan="8" class="empty">در این بازه گردش تأییدشده‌ای ثبت نشده است.</td></tr>'}</tbody></table></div><div class="pager"><button id="statementPrev" ${page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))} · ${number(d.pagination.total)} ردیف</span><button id="statementNext" ${page>=d.pagination.pages?'disabled':''}>بعدی</button></div>`;
 $('statementExport')?.addEventListener('click',()=>downloadFile('/api/v1/accounting/statement/export?'+new URLSearchParams(filters),'گردش-حساب.xlsx'));
 $('statementPrev').onclick=()=>load(filters,page-1);$('statementNext').onclick=()=>load(filters,page+1);
 $('statementResult').querySelectorAll('[data-statement-doc]').forEach(b=>b.onclick=()=>journalEditor(Number(b.dataset.statementDoc),token,()=>accountStatementPage(token,filters,page)).catch(e=>notify(e.message,true)));
 }catch(e){if(token===pageToken&&seq===request)$('statementResult').innerHTML='<p class="error" role="alert">'+esc(e.message)+'</p>';}};
 form.onsubmit=e=>{e.preventDefault();load(Object.fromEntries(new FormData(form)));};
 if(initial){for(const [key,value] of Object.entries(initial))if(form.elements[key])form.elements[key].value=value;await load(initial,initialPage);}
}
async function journalPage(token=pageToken,page=1){
 const d=await api(scoped('/api/v1/accounting-documents?limit=25&page='+page));if(token!==pageToken)return;
 $('content').innerHTML=`<section class="panel">${accountingTabs(false)}<h2>حسابداری · ثبت سند</h2><p>اسناد تراز به‌صورت پیش‌نویس ذخیره می‌شوند و پس از تأیید قابل تغییر نیستند.</p>${can('accounting-documents','manage')?'<button id="newJournal" class="primary">سند جدید</button>':''}<a href="#accounts">تعاریف حسابداری</a><div class="table-wrap"><table><thead><tr><th>شماره</th><th>تاریخ</th><th>شرح</th><th>جمع بدهکار / بستانکار</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>${d.items.map(r=>`<tr><td>${esc(r.document_no)}</td><td>${dateText(r.document_date)}</td><td>${esc(r.description)}</td><td>${number(r.total_amount)}</td><td>${esc(statuses[r.status]||r.status)}</td><td><button data-journal="${r.id}">مشاهده سند</button></td></tr>`).join('')}</tbody></table></div><div class="pager"><button id="journalPrev" ${page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(page)} از ${number(Math.max(1,d.pagination.pages))}</span><button id="journalNext" ${page>=d.pagination.pages?'disabled':''}>بعدی</button></div></section>`;
 $('newJournal')?.addEventListener('click',()=>journalEditor(null,token).catch(e=>notify(e.message,true)));
 $('content').querySelectorAll('[data-journal]').forEach(b=>b.onclick=()=>journalEditor(Number(b.dataset.journal),token).catch(e=>notify(e.message,true)));
 $('journalPrev').onclick=()=>journalPage(token,page-1).catch(e=>notify(e.message,true));$('journalNext').onclick=()=>journalPage(token,page+1).catch(e=>notify(e.message,true));
}
async function journalEditor(id,token=pageToken,back=null){
 const doc=id?await api(scoped('/api/v1/accounting/journal/'+id)):{document_no:'',document_date:todayISO(),description:'',status:'draft',lines:[{},{}]};
 const accounts=[];let page=1,more=true;while(more){const d=await api(scoped('/api/v1/accounts?limit=200&active=1&page='+page++));accounts.push(...d.items);more=page<=d.pagination.pages;}
 if(token!==pageToken)return;const editable=can('accounting-documents','manage')&&doc.status==='draft';
 // Approved and legacy drafts can still display accounts that have since been deactivated.
 const options=new Map(accounts.map(a=>[String(a.id),a.code+' · '+a.title]));for(const l of doc.lines)if(!options.has(String(l.account_id)))options.set(String(l.account_id),l.code+' · '+l.title);
 $('content').innerHTML=`<section class="panel"><h2>${id?'سند '+esc(doc.document_no):'ثبت سند حسابداری'}</h2><form id="journalForm"><fieldset ${editable?'':'disabled'}><div class="form-grid"><label>شماره سند<input name="documentNo" maxlength="100" required value="${esc(doc.document_no)}"></label><label>شماره مرجع<input name="referenceNo" maxlength="100" value="${esc(doc.reference_no)}"></label><label>شماره فرعی<input name="subsidiaryNo" maxlength="100" value="${esc(doc.subsidiary_no)}"></label><label>تاریخ سند<input name="documentDate" type="date" required value="${esc(doc.document_date)}"></label><label>شرح<input name="documentDescription" maxlength="10000" value="${esc(doc.description)}"></label></div><div class="table-wrap"><table><thead><tr><th>حساب</th><th>شرح ردیف</th><th>بدهکار · ریال</th><th>بستانکار · ریال</th><th>شماره پیگیری</th><th></th></tr></thead><tbody id="journalLines"></tbody></table></div>${editable?'<button type="button" id="journalAdd">افزودن ردیف</button>':''}</fieldset><p id="journalTotals" aria-live="polite"></p>${editable?'<button class="primary" id="journalSave">ذخیره پیش‌نویس</button>':''}</form>${editable&&id?'<button id="journalApprove">تأیید و قفل سند</button>':''}${id?'<button id="journalPrint">پیش‌نمایش چاپ</button>':''}<button id="journalBack">${back?'بازگشت به گزارش':'بازگشت به فهرست'}</button></section>`;
 $('journalPrint')?.addEventListener('click',()=>{dialog('چاپ سند حسابداری',`<article class="journal-print"><header><img src="/assets/logo.png" width="72" height="72" alt="سرویس یار"><div><h2>${esc(currentAgency?.name||'سرویس یار')}</h2><p>سند حسابداری · ${esc(statuses[doc.status]||doc.status)}</p></div></header><p>شماره سند: ${esc(doc.document_no)} · تاریخ: ${dateText(doc.document_date)} · مرجع: ${esc(doc.reference_no||'—')} · فرعی: ${esc(doc.subsidiary_no||'—')}</p><p>${esc(doc.description)}</p>${doc.status==='draft'?'<p class="draft-watermark">پیش‌نویس · تأیید نشده</p>':''}<div class="table-wrap"><table><thead><tr><th>ردیف</th><th>کد و نام حساب</th><th>شرح</th><th>پیگیری</th><th>بدهکار · ریال</th><th>بستانکار · ریال</th></tr></thead><tbody>${doc.lines.map((l,i)=>`<tr><td>${number(i+1)}</td><td>${esc(l.code+' · '+l.title)}</td><td>${esc(l.description)}</td><td>${esc(l.tracking_no)}</td><td>${number(l.debit)}</td><td>${number(l.credit)}</td></tr>`).join('')}</tbody><tfoot><tr><th colspan="4">جمع</th><td>${number(doc.total_amount)}</td><td>${number(doc.total_amount)}</td></tr></tfoot></table></div><div class="journal-signatures"><span>تنظیم‌کننده</span><span>حسابداری</span><span>تأییدکننده</span></div></article><button id="journalDoPrint" type="button">چاپ / ذخیره PDF</button>`);$('journalDoPrint').onclick=()=>window.print();});
 const add=line=>{const row=document.createElement('tr');row.innerHTML=`<td><select name="accountId" aria-label="حساب" required><option value="">انتخاب حساب</option>${[...options].filter(([k])=>k!=='undefined').map(([k,v])=>`<option value="${esc(k)}" ${String(line.account_id)===k?'selected':''}>${esc(v)}</option>`).join('')}</select></td><td><input name="description" aria-label="شرح ردیف" maxlength="1000" value="${esc(line.description)}"></td><td><input name="debit" aria-label="بدهکار" type="number" min="0" max="999999999999.99" step="0.01" value="${esc(line.debit||0)}"></td><td><input name="credit" aria-label="بستانکار" type="number" min="0" max="999999999999.99" step="0.01" value="${esc(line.credit||0)}"></td><td><input name="trackingNo" aria-label="شماره پیگیری" maxlength="100" value="${esc(line.tracking_no)}"></td><td>${editable?'<button type="button" data-remove>حذف ردیف</button>':''}</td>`;$('journalLines').append(row);row.querySelector('[data-remove]')?.addEventListener('click',()=>{row.remove();totals();});row.oninput=totals;};
 const totals=()=>{let debit=0,credit=0;$('journalLines').querySelectorAll('tr').forEach(r=>{debit+=Math.round(Number(r.querySelector('[name=debit]').value||0)*100);credit+=Math.round(Number(r.querySelector('[name=credit]').value||0)*100);});$('journalTotals').textContent=`بدهکار: ${number(debit/100)} · بستانکار: ${number(credit/100)} · اختلاف: ${number((debit-credit)/100)}`;};
 doc.lines.forEach(add);totals();$('journalAdd')?.addEventListener('click',()=>add({}));$('journalBack').onclick=()=>Promise.resolve(back?back():journalPage(token)).catch(e=>notify(e.message,true));
 $('journalForm').onsubmit=async e=>{e.preventDefault();if(!editable)return;const button=$('journalSave');button.disabled=true;try{const f=e.currentTarget,body={referenceNo:f.elements.referenceNo.value,subsidiaryNo:f.elements.subsidiaryNo.value,documentNo:f.elements.documentNo.value,documentDate:f.elements.documentDate.value,description:f.elements.documentDescription.value,lines:[...$('journalLines').querySelectorAll('tr')].map(r=>Object.fromEntries([...r.querySelectorAll('input,select')].map(i=>[i.name,i.value])))};const saved=await write('/api/v1/accounting/journal'+(id?'/'+id:''),body,id?'PUT':'POST');notify('پیش‌نویس سند ذخیره شد.');if(token===pageToken)await journalEditor(saved.id,token);}catch(err){notify(err.message,true);}finally{if(button.isConnected)button.disabled=false;}};
 $('journalApprove')?.addEventListener('click',async e=>{if(!confirm('سند تأیید و غیرقابل ویرایش شود؟'))return;e.target.disabled=true;try{await write('/api/v1/accounting/journal/'+id+'/approve',{});notify('سند تأیید شد.');if(token===pageToken)await journalEditor(id,token);}catch(err){notify(err.message,true);if(e.target.isConnected)e.target.disabled=false;}});
}
async function dashboard(token=pageToken){
 const d=await api(scoped('/api/v1/operations/dashboard'));if(token!==pageToken)return;
 const totalStudents=Math.max(0,Number(d.counts?.students)||0),assignedStudents=Math.max(0,Number(d.statuses?.find(s=>s.service_status==='assigned')?.total)||0),assignedPercent=totalStudents?Math.max(0,Math.min(100,assignedStudents/totalStudents*100)):0;
 $('content').innerHTML=`<section class="dashboard-welcome"><div><span class="dashboard-kicker">مرکز مدیریت سرویس مدارس</span><h2>مدیریت امروز، در یک نگاه</h2><p>${esc(currentAgency?.name||'سرویس‌یار')} · ${esc(new Intl.DateTimeFormat('fa-IR',{dateStyle:'full',timeZone:'Asia/Tehran'}).format(new Date()))}</p></div><div class="dashboard-create"><button type="button" id="dashboardRefresh" class="samar-refresh" aria-label="به‌روزرسانی اطلاعات داشبورد">↻ به‌روزرسانی</button>${[['students','ثبت دانش‌آموز'],['services','تعریف سرویس']].filter(([k])=>can(k,'manage')).map(([k,t])=>`<a href="#${k}/new">${navIcon(k)}${t}</a>`).join('')}</div></section><section class="quick-search panel"><form id="quickSearch"><input name="q" placeholder="جستجوی سریع" aria-label="جستجوی سریع"><select name="resource" aria-label="انتخاب صفحه جستجو">${['students','drivers','schools','services'].filter(k=>can(k)).map(k=>`<option value="${k}">${titles[k]}</option>`).join('')}</select><button class="primary">جستجو</button></form><div class="quick-links">${['students','drivers','schools','services'].filter(k=>can(k)).map(k=>`<a href="#${k}">${navIcon(k)}${titles[k]}</a>`).join('')}</div></section>
 <div class="stats transport-stats">${[['students','دانش‌آموز'],['services','سرویس ثبت‌شده'],['drivers','راننده فعال'],['schools','مدرسه']].filter(([k])=>can(k)).map(([k,t])=>`<a href="#${k}" aria-label="${esc(t)}: ${number(d.counts[k])}"><article>${navIcon(k)}<strong>${number(d.counts[k])}</strong><span>${t}</span></article></a>`).join('')}<article class="wallet-card"><span>کیف پول · ریال</span><button id="showWallet">نمایش موجودی</button><strong id="walletValue">******</strong></article></div>
 <div class="dashboard-grid"><section class="panel samar-chart-panel"><div class="dashboard-panel-head"><h2>توزیع دانش‌آموزان</h2><span>بر اساس وضعیت ثبت‌شده</span></div><div class="samar-donut-layout"><div class="samar-donut" style="--assigned:${assignedPercent}%" role="img" aria-label="نسبت دانش‌آموزان دارای سرویس"><span><b>${number(d.counts.students||0)}</b><small>کل دانش‌آموزان</small></span></div><div class="samar-donut-legend"><span><i class="samar-dot assigned"></i>دارای سرویس <b>${number(d.statuses.find(s=>s.service_status==='assigned')?.total||0)}</b></span><span><i class="samar-dot other"></i>سایر وضعیت‌ها <b>${number(Math.max(0,Number(d.counts.students||0)-Number(d.statuses.find(s=>s.service_status==='assigned')?.total||0)))}</b></span></div></div></section><section class="panel samar-chart-panel"><div class="dashboard-panel-head"><h2>نمودار تخصیص سرویس</h2><span>آمار لحظه‌ای</span></div><div class="samar-allocation-bar" style="--assigned:${assignedPercent}%" role="img" aria-label="سهم دانش‌آموزان دارای سرویس"><span></span></div><p class="muted">برای نمودار روند تاریخی، داده‌های تاریخ‌دار لازم است.</p></section><section class="panel samar-status-details"><h2>وضعیت دانش‌آموزان</h2><div class="status-chart">${['no_request','pending','prepayment','waiting','assigned','cancelled'].map(k=>{const value=Number(d.statuses.find(s=>s.service_status===k)?.total||0),width=totalStudents?Math.max(0,Math.min(100,value/totalStudents*100)):0;return `<div><span>${statuses[k]}</span><progress class="chart-track" max="100" value="${width}" aria-label="${statuses[k]}"></progress><b>${number(value)}</b></div>`}).join('')}</div></section><section class="panel dashboard-attention"><div class="dashboard-panel-head"><h2>پیگیری وضعیت‌ها</h2><span>داده‌های فعلی</span></div><div class="distribution">${d.statuses.map(s=>`<a href="#students" data-status="${esc(s.service_status)}"><b>${number(s.total)}</b>${esc(statuses[s.service_status]||s.service_status)}</a>`).join('')||'<p class="empty">دانش‌آموزی ثبت نشده است.</p>'}</div></section><section class="panel"><h2>کارتابل راه‌اندازی</h2><div class="setup-list">${[['schools','ثبت مدرسه'],['students','ثبت دانش‌آموز'],['drivers','ثبت راننده'],['services','تعریف سرویس']].filter(([k])=>can(k)).map(([k,t])=>`<a href="#${k}"><span>${d.counts[k]?'✓':'○'} ${t}</span><b>${number(d.counts[k])}</b></a>`).join('')}</div></section><section class="panel samar-users-panel"><div class="dashboard-panel-head"><h2>وضعیت فعالیت کاربران شرکت</h2><span>آخرین اطلاعات سامانه</span></div><div id="dashboardUsers" role="status" aria-live="polite"><p class="muted">در حال دریافت وضعیت کاربران…</p></div></section><section class="panel samar-schools-panel"><div class="dashboard-panel-head"><h2>مدارس شرکت</h2><span>${number(d.schools.length)} مدرسه</span></div>${simpleTable(['مدرسه','دانش‌آموز','بدون سرویس'],d.schools.map(s=>[s.name,number(s.students),number(s.unassigned)]))}</section><section class="panel dashboard-map-panel" aria-label="نقشه موقعیت مدارس و شرکت"><div class="dashboard-panel-head"><h2>نقشه مدارس و دفتر شرکت</h2><span>موقعیت‌های ثبت‌شده در سامانه</span></div><div id="dashboardSchoolMap" class="dashboard-school-map" aria-label="نقشه مدارس شرکت"></div></section><section class="panel"><h2>تقویم</h2><div id="calendar"></div></section><section class="panel"><h2>دسترسی‌های کاربردی</h2><div class="quick-links">${[['monitoring','وضعیت فعالیت کاربران'],['parent-reports','گزارشات والدین'],['payroll','فیش حقوق راننده']].filter(([k])=>can(k)).map(([k,t])=>`<a href="#${k}">${t}</a>`).join('')}</div></section></div>`;
 $('content').querySelectorAll('[data-status]').forEach(a=>a.onclick=()=>{initialStudentStatus=a.dataset.status;});$('dashboardRefresh').onclick=()=>dashboard(token).catch(e=>notify(e.message,true));
 $('quickSearch').onsubmit=e=>{e.preventDefault();initialSearch=e.target.q.value;location.hash=e.target.resource.value;};
 $('showWallet').closest('.wallet-card').hidden=!can('wallets');$('showWallet').setAttribute('aria-expanded','false');$('walletValue').setAttribute('aria-live','polite');$('showWallet').onclick=async()=>{const button=$('showWallet');if(button.getAttribute('aria-expanded')==='true'){$('walletValue').textContent='******';button.textContent='نمایش موجودی';button.setAttribute('aria-expanded','false');return;}button.disabled=true;try{delete lookupCache[currentAgency.id+':wallets'];const w={items:await lookup('wallets')};if(token!==pageToken)return;$('walletValue').textContent=number(w.items.reduce((sum,x)=>sum+Number(x.balance),0));button.textContent='پنهان کردن موجودی';button.setAttribute('aria-expanded','true');}catch(e){notify(e.message,true);}finally{if(button.isConnected)button.disabled=false;}};
 if(can('monitoring'))api(scoped('/api/v1/monitoring/users')).then(users=>{if(token===pageToken&&$('dashboardUsers'))$('dashboardUsers').innerHTML=users.length?simpleTable(['کاربر','وضعیت'],users.map(u=>[person(u),Number(u.online)?'آنلاین':'آفلاین'])):'<p class="empty">کاربری برای نمایش وجود ندارد.</p>';}).catch(e=>{if(token===pageToken&&$('dashboardUsers'))$('dashboardUsers').textContent=e.message;});else $('dashboardUsers').textContent='دسترسی به پایش کاربران مجاز نیست.';calendar(new Date());let map;try{map=await makeMap($('dashboardSchoolMap'));}catch(e){if(token===pageToken&&$('dashboardSchoolMap'))$('dashboardSchoolMap').textContent='نقشه موقتاً در دسترس نیست.';return;}if(!map)return;const points=[];let config;try{config=await api(scoped('/api/v1/maps/config'));}catch(e){if(token===pageToken)$('dashboardSchoolMap').insertAdjacentHTML('afterend','<p class="muted">تنظیمات نقشه دریافت نشد.</p>');return;}if(token!==pageToken)return;const office=config.companyLocation;if(office&&Number.isFinite(Number(office.latitude))&&Number.isFinite(Number(office.longitude))){const point=[Number(office.latitude),Number(office.longitude)];points.push(point);L.marker(point,{icon:L.divIcon({className:'company-map-pin',html:navIcon('company'),iconSize:[40,48],iconAnchor:[20,48]})}).addTo(map).bindPopup(esc(currentAgency?.name||'دفتر شرکت')).openPopup();}else{const hint=document.createElement('p');hint.className='muted';hint.textContent='موقعیت دفتر شرکت هنوز ثبت نشده است؛ از تنظیمات، موقعیت را روی نشان انتخاب کنید.';$('dashboardSchoolMap').after(hint);}d.schools.forEach(sc=>{if(sc.latitude!==null&&sc.longitude!==null){const point=[Number(sc.latitude),Number(sc.longitude)];points.push(point);L.circleMarker(point,{radius:8,color:'#1c9a7e'}).addTo(map).bindPopup(esc(sc.name));}});if(points.length)map.fitBounds(L.latLngBounds(points).pad(.3),{maxZoom:14});
}
function calendar(date){const fmt=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{month:'long',year:'numeric',timeZone:'Asia/Tehran'}),dayFmt=new Intl.DateTimeFormat('en-US-u-ca-persian',{day:'numeric',timeZone:'Asia/Tehran'}),today=new Date();let first=new Date(date);first.setDate(first.getDate()-Number(dayFmt.format(first))+1);let next=new Date(first);next.setDate(next.getDate()+32);next.setDate(next.getDate()-Number(dayFmt.format(next))+1);const length=Math.round((next-first)/86400000),offset=(first.getDay()+1)%7;
 $('calendar').innerHTML=`<div class="calendar-head"><button id="prevMonth" aria-label="ماه قبل">‹</button><b>${fmt.format(date)}</b><button id="nextMonth" aria-label="ماه بعد">›</button></div><div class="calendar-days">${['ش','ی','د','س','چ','پ','ج'].map(x=>`<b>${x}</b>`).join('')}${'<span></span>'.repeat(offset)}${Array.from({length},(_,i)=>{const d=new Date(first);d.setDate(d.getDate()+i);return `<span class="${d.toDateString()===today.toDateString()?'today':''}">${number(i+1)}</span>`}).join('')}</div>`;$('prevMonth').onclick=()=>{const d=new Date(first);d.setDate(d.getDate()-1);calendar(d);};$('nextMonth').onclick=()=>calendar(next);}
function simpleTable(headers,rows){return `<div class="table-wrap sy-report-table-wrap"><table class="sy-report-table"><thead><tr>${headers.map(h=>`<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.length?rows.map(row=>`<tr>${row.map((v,i)=>`<td data-label="${esc(headers[i]||'اطلاعات')}">${esc(v??'—')}</td>`).join('')}</tr>`).join(''):`<tr class="sy-report-empty"><td colspan="${headers.length}" class="empty">رکوردی یافت نشد.</td></tr>`}</tbody></table></div>`;}
function resourceName(key){return titles[key]||key;}
async function lookup(key,includeInactive=false){const agencyId=currentAgency.id,cacheKey=agencyId+':'+key+(includeInactive?':all':'');if(lookupCache[cacheKey])return lookupCache[cacheKey];const rows=[];let page=1;while(true){const d=await api('/api/v1/'+key+'?limit=200&active='+(includeInactive?'':'1')+'&page='+page+'&agencyId='+encodeURIComponent(agencyId));if(String(currentAgency?.id)!==String(agencyId))throw new Error('شرکت فعال تغییر کرده است؛ دوباره تلاش کنید.');rows.push(...d.items);if(page>=d.pagination.pages)break;page++;}return lookupCache[cacheKey]=rows;}
function ledgerFilterForm(){
 return `<form id="gridFilters" class="ledger-filters" aria-label="جست‌وجو و فیلتر دریافت و پرداخت">
  <div class="ledger-filter-header"><div><span class="ledger-filter-kicker">مدیریت تراکنش‌های مالی</span><h3>جست‌وجوی دریافت‌ها و پرداخت‌ها</h3><p>بر اساس نام و نام خانوادگی طرف حساب، تاریخ، مبلغ و نوع تراکنش جست‌وجو کنید.</p></div><span id="ledgerFilterSummary" class="ledger-filter-count" role="status" aria-live="polite">در حال دریافت اطلاعات…</span></div>
  <div class="ledger-filter-primary">
   <label>نام دریافت‌کننده یا پرداخت‌کننده<input type="search" name="partySearch" id="ledgerPartySearch" autocomplete="off" maxlength="200" placeholder="نام و نام خانوادگی، نام مدرسه یا شرکت"></label>
   <label>شماره پیگیری یا توضیحات<input type="search" name="search" autocomplete="off" maxlength="200" placeholder="شماره پیگیری یا بخشی از توضیحات"></label>
   <button type="submit" class="primary">نمایش نتایج</button>
   <button type="reset" class="ledger-filter-clear">پاک کردن</button>
  </div>
  <div class="ledger-filter-grid">
   <label>نوع عملیات<select name="direction"><option value="">دریافت و پرداخت</option><option value="receipt">دریافت</option><option value="payment">پرداخت</option></select></label>
   <label>طرف حساب<select name="partyType"><option value="">همه طرف حساب‌ها</option><option value="student">دانش‌آموز</option><option value="driver">راننده</option><option value="school">مدرسه</option><option value="agency">شرکت</option></select></label>
   <label>روش پرداخت<select name="method"><option value="">همه روش‌ها</option><option value="cash">نقدی</option><option value="card">کارت به کارت</option><option value="bank">پایا</option><option value="transfer">ساتنا</option><option value="cheque">چک</option><option value="wallet">کیف پول</option></select></label>
   <label>وضعیت سند<select name="active"><option value="1">فعال</option><option value="0">غیرفعال</option><option value="">همه سوابق</option></select></label>
  </div>
  <details class="ledger-filter-more"><summary>فیلتر بازه زمانی و مبلغ <span>تاریخ شروع و پایان، حداقل و حداکثر مبلغ</span></summary><div class="ledger-filter-grid">
   <label>از تاریخ<input name="dateFrom" type="date"></label>
   <label>تا تاریخ<input name="dateTo" type="date"></label>
   <label>حداقل مبلغ (ریال)<input name="amountMin" type="number" min="0" step="any" inputmode="decimal" placeholder="از مبلغ"></label>
   <label>حداکثر مبلغ (ریال)<input name="amountMax" type="number" min="0" step="any" inputmode="decimal" placeholder="تا مبلغ"></label>
  </div></details>
 </form>`;
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
 const saved=window.__ledgerReportFilters||'';
 $('content').innerHTML=`<section class="toolbar"><div><h2>دریافت و پرداخت</h2><p>گزارش مالی بر اساس فیلترهای انتخابی</p></div></section><nav class="ledger-report-tabs" aria-label="بخش‌های دریافت و پرداخت"><button type="button" id="ledgerTabRecords">ثبت و مدیریت اسناد</button><button type="button" class="active" aria-current="page">گزارش‌گیری</button></nav><section class="panel ledger-report-panel">${ledgerFilterForm()}<div class="ledger-report-actions"><button type="button" class="primary" id="ledgerReportApply">تهیه گزارش</button><button type="button" id="ledgerReportPrint" disabled>چاپ گزارش</button><button type="button" id="ledgerReportExcel" disabled>خروجی Excel</button></div><div id="ledgerReportBody" aria-live="polite"><p class="muted">برای تهیه گزارش، فیلترها را انتخاب کنید.</p></div></section>`;
 const form=$('gridFilters');$('ledgerTabRecords').onclick=()=>resourcePage('ledger',token);
 if(saved){const values=new URLSearchParams(saved);for(const [key,value] of values){const el=form.elements[key];if(el)el.value=value;}if(['dateFrom','dateTo','amountMin','amountMax'].some(k=>values.get(k)))form.querySelector('details')?.setAttribute('open','');}
 let ready=false,version=0,reportRows=[];
 const query=()=>{const p=new URLSearchParams(new FormData(form));p.set('agencyId',currentAgency.id);return p;};
 const names={student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت'};
 const methods=ledgerMethods;
 const render=async()=>{
  if(form.elements.dateFrom.value&&form.elements.dateTo.value&&form.elements.dateFrom.value>form.elements.dateTo.value){notify('تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.',true);return;}
  if(form.elements.amountMin.value&&form.elements.amountMax.value&&Number(form.elements.amountMin.value)>Number(form.elements.amountMax.value)){notify('حداقل مبلغ از حداکثر بیشتر است.',true);return;}
  const current=++version;ready=false;$('ledgerReportPrint').disabled=true;$('ledgerReportExcel').disabled=true;$('ledgerReportBody').innerHTML='<div class="loading">در حال تهیه گزارش مالی…</div>';
  try{
   const p=query();window.__ledgerReportFilters=p.toString();
   const items=[];let page=1,total=0,totals=null;
   do{
    p.set('page',page);p.set('limit',200);
    const d=await api('/api/v1/reports/ledger?'+p);
    if(current!==version||token!==pageToken)return;
    total=d.pagination.total;totals=d.totals;items.push(...d.items);page++;
    if(total>5000){$('ledgerReportBody').innerHTML='<div class="error">تعداد اسناد بیش از ۵۰۰۰ مورد است؛ بازه گزارش را محدودتر کنید.</div>';return;}
   }while(items.length<total&&items.length<5000&&page<=Math.ceil(total/200));
   reportRows=items;ready=true;
   const totalReceipt=Number(totals?.receipts||0),totalPayment=Number(totals?.payments||0);
   $('ledgerFilterSummary').textContent=number(total)+' سند در گزارش';
   const agencyName=currentAgency?.name||'سرویس‌یار';
   const range=[form.elements.dateFrom.value||'ابتدای سوابق',form.elements.dateTo.value||'پایان سوابق'].join(' تا ');
   $('ledgerReportBody').innerHTML=`<article class="ledger-print-sheet"><div class="ledger-report-letterhead"><div><strong>سرویس‌یار | ${esc(agencyName)}</strong><h3>گزارش دریافت‌ها و پرداخت‌ها</h3><p>بازه گزارش: ${esc(range)}</p></div><span>گزارش مالی سازمانی</span></div><div class="ledger-report-kpis"><div><span>تعداد اسناد</span><strong>${number(total)}</strong></div><div><span>جمع دریافت‌ها (ریال)</span><strong>${number(totalReceipt)}</strong></div><div><span>جمع پرداخت‌ها (ریال)</span><strong>${number(totalPayment)}</strong></div><div><span>خالص دریافت و پرداخت (ریال)</span><strong>${number(totalReceipt-totalPayment)}</strong></div></div><div class="table-wrap" tabindex="0"><table class="ledger-report-table"><thead><tr><th>ردیف</th><th>تاریخ</th><th>نوع</th><th>نام طرف حساب</th><th>گروه</th><th>دریافت (ریال)</th><th>پرداخت (ریال)</th><th>روش</th><th>شماره پیگیری</th><th>توضیحات</th></tr></thead><tbody>${items.map((r,i)=>`<tr><td>${number(i+1)}</td><td>${esc(dateText(r.entry_date))}</td><td>${r.direction==='receipt'?'دریافت':'پرداخت'}</td><td>${esc(r.party_name||'—')}</td><td>${esc(names[r.party_type]||r.party_type)}</td><td>${r.direction==='receipt'?number(r.amount):'—'}</td><td>${r.direction==='payment'?number(r.amount):'—'}</td><td>${esc(methods[r.method]||r.method||'—')}</td><td>${esc(r.reference_no||'—')}</td><td>${esc(r.note||'—')}</td></tr>`).join('')||'<tr><td colspan="10">سندی مطابق فیلترها یافت نشد.</td></tr>'}</tbody></table></div><footer class="ledger-report-footer"><span>تهیه‌شده در سامانه سرویس‌یار</span><span>تعداد کل: ${number(total)} سند</span></footer></article>`;
   $('ledgerReportPrint').disabled=false;$('ledgerReportExcel').disabled=false;
  }catch(e){if(current===version)$('ledgerReportBody').innerHTML='<div class="error">'+esc(e.message)+'</div>';}
 };
 form.onsubmit=e=>{e.preventDefault();render();};
 form.onreset=()=>setTimeout(()=>{window.__ledgerReportFilters='';form.querySelector('details')?.removeAttribute('open');render();},0);
 $('ledgerReportApply').onclick=render;
 $('ledgerReportPrint').onclick=()=>{if(ready)window.print();};
 $('ledgerReportExcel').onclick=()=>{if(ready)downloadFile('/api/v1/reports/ledger/export?'+query(),'گزارش-دریافت-پرداخت.xlsx');};
 await render();
}
async function resourcePage(key,token=pageToken,options={}){
 gridState={key,token,page:1,limit:25,rows:[],selected:new Set(),previous:options.previous};
 const schoolFilter=['students','services'].includes(key)&&can('schools'),isStudents=key==='students',isDrivers=key==='drivers',isVehicles=key==='vehicles',isLedger=key==='ledger';
 const pageIntroductions={students:['پرونده دانش‌آموزان','دانش‌آموزان را با نام، مدرسه، وضعیت سرویس و سال تحصیلی پیدا و مدیریت کنید.'],drivers:['ناوگان و رانندگان','اطلاعات رانندگان، وضعیت فعالیت و تخصیص سرویس را یکجا بررسی کنید.'],vehicles:['مدیریت خودروها','خودروها را با مشخصات راننده و اجزای پلاک ایران جست‌وجو کنید.'],ledger:['امور مالی و حسابداری','دریافت‌ها و پرداخت‌ها را ثبت، پیگیری و در تب گزارش‌گیری تحلیل کنید.'],schools:['مدیریت مدارس','فهرست مدارس و اطلاعات ثبت‌شده را بررسی و به‌روزرسانی کنید.'],payments:['پرداخت‌های ثبت‌شده','سوابق پرداخت و شماره پیگیری را بررسی کنید.']};
 const pageIntro=pageIntroductions[key];
 $('content').innerHTML=`<section class="toolbar ${key==='services'?'services-page-head':''}"><div class="${key==='services'?'services-page-title':'sy-directory-heading'}">${key==='services'?'<span class="services-eyebrow">مدیریت ناوگان و مسیرها</span>':pageIntro?`<span class="sy-directory-eyebrow">${esc(pageIntro[0])}</span>`:''}<h2>${resourceName(key)}</h2>${key==='services'?'<p>جست‌وجو، بررسی و مدیریت یکپارچه سرویس‌های مدارس</p>':pageIntro?`<p>${esc(pageIntro[1])}</p>`:''}</div><div class="toolbar-buttons">${can(key,'manage')?`<button class="primary" id="addBtn">${key==='students'?'دانش‌آموز جدید':key==='drivers'?'ثبت راننده جدید':'ثبت جدید'}</button>`:''}${['schools','students','drivers','services','invoices','payments','driver-work'].includes(key)&&can('reports')?'<button id="gridExport">خروجی Excel</button>':''}<button id="printGrid">چاپ</button></div></section>${isStudents?'<div class="status-tabs" id="studentStatuses" role="group" aria-label="فیلتر وضعیت دانش‌آموز">'+['','no_request','pending','prepayment','waiting','assigned','cancelled'].map(k=>`<button data-status="${k}">${statuses[k]||'همه'}</button>`).join('')+'</div>':''}${isLedger&&can('reports')?'<nav class="ledger-report-tabs" aria-label="بخش‌های دریافت و پرداخت"><button type="button" class="active" aria-current="page">ثبت و مدیریت اسناد</button><button type="button" id="ledgerTabReport">گزارش‌گیری</button></nav>':''}<section class="panel">${key==='services'?serviceFilterForm(schoolFilter):isLedger?ledgerFilterForm():`<form id="gridFilters" class="filters ${isStudents||isDrivers?'directory-filters':''}" aria-label="جست‌وجو و فیلتر ${resourceName(key)}"><label class="directory-search">جست‌وجوی سریع<input name="search" id="search" type="search" placeholder="${isStudents?'نام، نام خانوادگی یا کد ملی دانش‌آموز':isDrivers?'نام، نام خانوادگی، کد ملی یا شماره همراه راننده':isVehicles?'مدل خودرو، نام مالک یا شماره پلاک':'جست‌وجو'}" aria-label="جست‌وجو" value="${esc(initialSearch)}"></label>${schoolFilter?'<select name="schoolId" aria-label="فیلتر مدرسه"><option value="">همه مدارس</option></select>':''}${isVehicles?`<label class="vehicle-driver-filter">نام راننده<input type="search" name="driverSearch" placeholder="نام یا نام خانوادگی راننده" autocomplete="off"></label><fieldset class="vehicle-plate-filter"><legend>جست‌وجوی پلاک ایران</legend><div class="vehicle-plate-entry" role="group" aria-label="بخش‌های شماره پلاک"><span class="vehicle-plate-iran">ایران <small>IR</small></span><input name="plateRegion" inputmode="numeric" maxlength="2" pattern="[0-9۰-۹٠-٩]{0,2}" placeholder="۱۱" aria-label="کد ایران، دو رقم" dir="ltr"><input name="plateMiddle" inputmode="numeric" maxlength="3" pattern="[0-9۰-۹٠-٩]{0,3}" placeholder="۱۲۳" aria-label="سه رقم وسط پلاک" dir="ltr"><select name="plateLetter" aria-label="حرف پلاک"><option value="">حرف</option>${'آ ا ب ج د س ص ط ق ل م ن و ه ی ت ع ف ک گ پ ث ز ش'.split(' ').map(letter=>`<option value="${letter}">${letter}</option>`).join('')}</select><input name="plateFirst" inputmode="numeric" maxlength="2" pattern="[0-9۰-۹٠-٩]{0,2}" placeholder="۱۲" aria-label="دو رقم ابتدایی پلاک" dir="ltr"><span class="vehicle-plate-flag" aria-hidden="true">IR</span></div><small>هر بخش را جداگانه وارد کنید؛ بخش‌های خالی در جست‌وجو محدودیتی ایجاد نمی‌کنند.</small></fieldset>`:''}${isStudents?`<label class="directory-filter-field">سال تحصیلی<input name="academicYear" aria-label="سال تحصیلی" placeholder="مثلاً ${year()}" value="${options.previous?esc((Number(year().split('/')[0])-1)+'/'+year().split('/')[0]):''}"></label><label class="directory-filter-field">شماره همراه ولی<input name="mobile" type="search" inputmode="tel" placeholder="جست‌وجو با شماره همراه" aria-label="شماره همراه ولی"></label><input type="hidden" name="serviceStatus">`:''}${isDrivers?'<label>وضعیت راننده<select name="status"><option value="">همه وضعیت‌ها</option><option value="active">تأییدشده</option><option value="pending">در انتظار بررسی</option><option value="inactive">غیرفعال</option></select></label>':''}${key==='schools'?'<input name="schoolType" placeholder="نوع مدرسه" aria-label="نوع مدرسه">':''} ${key==='driver-work'?'<label>وضعیت بررسی<select name="status"><option value="">همه وضعیت‌ها</option><option value="approved">بررسی‌شده</option><option value="pending">نیاز به بررسی</option><option value="cancelled">لغوشده</option></select></label><label>کارکرد از<input name="dateFrom" type="date"></label><label>تا تاریخ<input name="dateTo" type="date"></label>':''}<label class="directory-filter-field">وضعیت فعالیت<select name="active" aria-label="فعال بودن"><option value="1">فعال</option><option value="0">غیرفعال</option><option value="">همه سوابق</option></select></label><button type="submit" class="primary directory-search-button">اعمال جست‌وجو و فیلتر</button><button type="reset" class="directory-reset-button">پاک‌کردن فیلترها</button></form>`}<div class="bulk-toolbar">${['students','drivers'].includes(key)&&can('messages','manage')?'<button id="bulkMessage">پیامک به انتخاب‌شده‌ها</button>':''}${isStudents&&can('services','manage')&&can('students','manage')?'<button id="bulkFees">تغییر سریع هزینه سرویس</button>':''}${key==='drivers'&&can('drivers','manage')?'<button class="primary" id="bulkDriverStatus" type="button">عملیات گروهی · فعال / غیرفعال</button>':''}${key==='services'&&can('services','manage')?'<button class="primary" id="bulkServiceStatus" type="button" disabled>تغییر وضعیت گروهی سرویس‌ها</button>':''}<span id="selectedCount"></span></div><div id="table"></div></section><div id="modal"></div>`;
 if(key==='students'&&initialStudentStatus){$('gridFilters').elements.serviceStatus.value=initialStudentStatus;$('studentStatuses')?.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b.dataset.status===initialStudentStatus));initialStudentStatus='';}
 const filterForm=$('gridFilters'),state=gridState;
 $('ledgerTabReport')?.addEventListener('click',()=>ledgerReportPage(token));
 if(isVehicles){
  const toAscii=value=>String(value).replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
  const segments=[...filterForm.querySelectorAll('.vehicle-plate-entry input')];
  segments.forEach(input=>{
   input.addEventListener('input',()=>{
    const cleaned=toAscii(input.value).replace(/\D/g,'').slice(0,Number(input.maxLength));
    input.value=cleaned;
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
 const applyFilters=()=>{
  if(gridState!==state||token!==pageToken)return;
  state.page=1;state.selected.clear();
  state.appliedFilters=new URLSearchParams(new FormData(filterForm));
  loadResource(key);
 };
 state.appliedFilters=new URLSearchParams(new FormData(filterForm));
 initialSearch='';$('addBtn')?.addEventListener('click',()=>{if(key==='services')location.hash='services/new';else formResource(key,null);});
 filterForm.onsubmit=e=>{e.preventDefault();applyFilters();};
 filterForm.onreset=()=>setTimeout(()=>{if(key==='students'){$('studentStatuses')?.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b.dataset.status===''));}if(isLedger)filterForm.querySelector('.ledger-filter-more')?.removeAttribute('open');if(key==='services'){filterForm.elements.search.value='';const driverSearch=$('serviceDriverSearch');if(driverSearch){driverSearch.value='';driverSearch.dispatchEvent(new Event('input'));}filterForm.querySelector('.service-filter-advanced')?.removeAttribute('open');}applyFilters();},0);
 $('studentStatuses')?.querySelectorAll('button').forEach(b=>b.onclick=()=>{$('gridFilters').elements.serviceStatus.value=b.dataset.status;$('studentStatuses').querySelectorAll('button').forEach(x=>x.classList.toggle('active',x===b));applyFilters();});
 $('bulkServiceStatus')?.addEventListener('click',bulkServiceStatusDialog);$('bulkDriverStatus')?.addEventListener('click',bulkDriverStatusDialog);$('bulkFees')?.addEventListener('click',bulkFeeDialog);$('bulkMessage')?.addEventListener('click',bulkMessageDialog);$('printGrid').onclick=()=>window.print();
 $('gridExport')?.addEventListener('click',()=>{const query=gridQuery();query.set('resource',key);downloadFile('/api/v1/reports/export?'+query,'گزارش-'+key+'.xlsx');});
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
 }else if(schoolFilter){try{const schools=await lookup('schools');if(token!==pageToken)return;filterForm.elements.schoolId.innerHTML='<option value="">همه مدارس</option>'+schools.map(r=>`<option value="${r.id}">${esc(r.name)}</option>`).join('');}catch(e){notify(e.message,true);}}
 await loadResource(key);
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
 try{const d=await api('/api/v1/'+key+'?'+gridQuery());if(state!==gridState||state.token!==pageToken||request!==state.request)return;state.rows=d.items;const fields=key==='ledger'?['entryDate','direction','partyName','partyType','amount','method','referenceNo','note']:columns[key]||resources[key][1].slice(0,7),refs=[...new Set(fields.filter(f=>relations[f]).map(f=>relations[f]))];const maps={};await Promise.all(refs.map(async r=>{try{maps[r]=await lookup(r,key==='services');}catch{maps[r]=[];}}));if(state!==gridState||state.token!==pageToken||request!==state.request)return;
 $('table').setAttribute('aria-busy','false');
 if(key==='ledger'){
  const active=[...state.appliedFilters.entries()].filter(([name,value])=>value.trim()!==''&&!(name==='active'&&value==='1')).length;
  $('ledgerFilterSummary').textContent=number(d.pagination.total)+' سند'+(active?' · '+number(active)+' فیلتر فعال':'');
 }
 if(key==='services'){
  const applied=[...state.appliedFilters.entries()].filter(([name,value])=>!['page','limit','agencyId'].includes(name)&&value.trim()!=='').length;
  $('serviceFilterSummary').textContent=number(d.pagination.total)+' سرویس'+(applied?' · '+number(applied)+' فیلتر اعمال شده':'');
 }
 const display=(row,f)=>{if(key==='ledger'&&f==='partyName')return row.party_name||'نامشخص';if(key==='ledger'&&f==='direction')return row.direction==='receipt'?'دریافت':row.direction==='payment'?'پرداخت':persianDigits(row.direction||'—');if(key==='ledger'&&f==='partyType')return ({student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت'})[row.party_type]||persianDigits(row.party_type||'—');if(key==='ledger'&&f==='method')return ({cash:'نقدی',card:'کارت به کارت',bank:'پایا',transfer:'ساتنا',cheque:'چک',wallet:'کیف پول'})[row.method]||persianDigits(row.method||'—');if(f==='fullName')return person(row);if(key==='services'&&f==='monthlyFee')return number(row.driver_net_monthly??row.monthly_fee);if(key==='students'&&f==='annualFee')return row.annual_fee===null||row.annual_fee===undefined?'—':number(row.annual_fee);if(f==='paymentStatus')return Number(row.invoice_total)===0?'فاقد فاکتور':Number(row.paid_total)>=Number(row.invoice_total)?'تسویه':'دارای مانده';const v=val(row,f);if(f==='driverName'&&!v&&row.mehr_driver_name)return row.mehr_driver_name+' (سابقه مهر)';if(f==='plate')return String(v||'—');if(key==='vehicles'&&f==='ownerName')return v||'ثبت نشده';if(key==='vehicles'&&f==='driverName')return v||'راننده‌ای تخصیص نیافته';if(f==='vehicleId'){const vehicle=maps.vehicles?.find(x=>String(x.id)===String(v));return vehicle?.plate||v||'—';}if(relations[f])return person(maps[relations[f]]?.find(x=>String(x.id)===String(v))||{id:v||'—'});if(['status','serviceStatus'].includes(f))return statuses[v]||v;if(f==='mobile')return persianDigits(v||'—');if(/Date$/.test(f))return dateText(v);if(/amount|fee|balance|capacity|rating|score/i.test(f))return number(v);return key==='ledger'?persianDigits(v||'—'):v||'—';};
 $('table').innerHTML=`<div class="table-wrap ${key==='services'?'services-table-wrap':''} ${['students','drivers'].includes(key)?'sy-directory-table-wrap':''} ${['ledger','payments','driver-payments','invoices','accounts','driver-work'].includes(key)?'sy-finance-table-wrap':''}"><table class="${key==='services'?'services-data-table':''} ${['students','drivers'].includes(key)?'sy-directory-table':''} ${['ledger','payments','driver-payments','invoices','accounts','driver-work'].includes(key)?'sy-finance-table':''}"><thead><tr><th scope="col"><input type="checkbox" id="selectPage" aria-label="انتخاب همه موارد این صفحه"></th><th scope="col">ردیف</th>${fields.map(f=>`<th scope="col">${esc(labels[f]||f)}</th>`).join('')}<th scope="col">عملیات</th></tr></thead><tbody>${d.items.map((r,i)=>`<tr><td data-label="انتخاب"><input type="checkbox" data-select="${r.id}" aria-label="انتخاب ${esc(person(r))}" ${state.selected.has(Number(r.id))?'checked':''}></td><td data-label="ردیف">${number((state.page-1)*state.limit+i+1)}</td>${fields.map(f=>`<td data-label="${esc(labels[f]||f)}">${f==='plate'||f==='vehicleId'?iranPlate(display(r,f)):esc(display(r,f))}</td>`).join('')}<td data-label="عملیات" class="row-actions"><button data-view="${r.id}">جزئیات</button>${can(key,'manage')?`<button data-edit="${r.id}">ویرایش</button>${['schools','students','drivers','vehicles','services','accounts','ledger','driver-ratings','parent-reports'].includes(key)?`<button class="danger" data-del="${r.id}">${key==='ledger'?(Number(r.is_active)===0?'بازگردانی سند':'حذف سند'):(Number(r.is_active)===0?'بازیابی':'غیرفعال')}</button>`:''}`:''}${['ledger','payments','driver-payments'].includes(key)&&can(key,'manage')?`<button data-post-financial="${r.id}">ثبت / مشاهده سند حسابداری</button>`:''}${key==='schools'?`<button data-school-shifts="${r.id}">شیفت، ساعت و تعطیلی</button>`:''}${key==='drivers'?`<button data-documents="${r.id}">مدارک</button>`:''}${key==='drivers'&&canConfigureDriverService()?`<button class="driver-service-action" data-driver-service="${r.id}" aria-label="تخصیص دانش‌آموز و تنظیم سرویس ${esc(person(r))}">تخصیص دانش‌آموز و تنظیم سرویس</button>`:''}${key==='services'?`<button data-service-schools="${r.id}">مدارس سرویس</button><button data-members="${r.id}">دانش‌آموزان سرویس</button><button data-service-map="${r.id}">نقشه سرویس</button>`:''}${key==='driver-changes'&&r.status==='pending'&&can(key,'manage')?`<button data-review="${r.id}" data-status="approved">تأیید</button><button data-review="${r.id}" data-status="rejected">رد</button>`:''}</td></tr>`).join('')||`<tr><td colspan="${fields.length+3}" class="empty"><div class="sy-empty-results"><strong>موردی با این شرایط پیدا نشد</strong><span>عبارت جست‌وجو یا فیلترهای انتخابی را تغییر دهید.</span><button type="button" data-clear-grid-filters>نمایش همه سوابق</button></div></td></tr>`}</tbody></table></div><div class="pager"><button id="prevPage" ${state.page<=1?'disabled':''}>قبلی</button><span>صفحه ${number(state.page)} از ${number(Math.max(1,d.pagination.pages))} · ${number(d.pagination.total)} مورد</span><button id="nextPage" ${state.page>=d.pagination.pages?'disabled':''}>بعدی</button><select id="pageSize" aria-label="تعداد در صفحه">${[25,50,100,200].map(n=>`<option ${state.limit===n?'selected':''}>${n}</option>`).join('')}</select></div>`;
 if(key==='services'){
  const titleOf=r=>String(r.title||r.name||r.code||'سرویس بدون عنوان');
  const detailFields=fields.filter(f=>!['title','name','code','status'].includes(f));
  const action=(id,attr,label,cls='')=>`<button type="button" ${attr}="${id}" class="${cls}">${label}</button>`;
  $('table').innerHTML=`<section class="sy-services-results" aria-label="فهرست سرویس‌ها">
   <div class="sy-services-results-head"><div><h3>فهرست سرویس‌ها</h3><p>اطلاعات هر سرویس بدون پیمایش افقی نمایش داده می‌شود.</p></div><label class="sy-services-select-all"><input type="checkbox" id="selectPage"> انتخاب همه در این صفحه</label></div>
   <div class="sy-services-card-grid">${d.items.map((r,i)=>`<article class="sy-service-card">
    <header class="sy-service-card-head"><label class="sy-service-check"><input type="checkbox" data-select="${r.id}" aria-label="انتخاب سرویس ${esc(titleOf(r))}" ${state.selected.has(Number(r.id))?'checked':''}></label><div class="sy-service-identity"><span class="sy-service-index">سرویس ${number((state.page-1)*state.limit+i+1)}</span><h4>${esc(titleOf(r))}</h4><small>کد سرویس: ${esc(persianDigits(r.code||'ثبت نشده'))}</small></div><span class="sy-service-status" data-status="${esc(r.status||'unknown')}">${esc(statuses[r.status]||'نامشخص')}</span></header>
    <dl class="sy-service-facts">${detailFields.map(f=>`<div><dt>${esc(labels[f]||f)}</dt><dd>${esc(persianDigits(display(r,f)))}</dd></div>`).join('')}<div><dt>جمع ماهانه دانش‌آموزان (ناخالص)</dt><dd>${number(r.students_monthly_total)} ریال</dd></div><div><dt>نظارت ${percent(r.supervision_rate)}٪</dt><dd>${number(r.supervision_deduction)} ریال</dd></div><div><dt>هماهنگی ${percent(r.coordination_rate)}٪</dt><dd>${number(r.coordination_deduction)} ریال</dd></div><div><dt>بالاسری ${percent(r.overhead_rate)}٪</dt><dd>${number(r.overhead_deduction)} ریال</dd></div><div><dt>بیمه ${percent(r.insurance_rate)}٪</dt><dd>${number(r.insurance_deduction)} ریال</dd></div><div><dt>جمع کسورات ${percent(r.driver_deduction_rate)}٪</dt><dd>${number(r.driver_deduction_total)} ریال</dd></div><div class="sy-service-net"><dt>مبلغ ماهانه قابل پرداخت به راننده</dt><dd>${number(r.driver_net_monthly)} ریال</dd></div><div class="sy-service-annual"><dt>مبلغ سالانه سرویس</dt><dd>${number(r.students_annual_total)} ریال</dd></div></dl>
    <footer class="sy-service-actions">${action(r.id,'data-view','جزئیات','sy-service-action-primary')}${can(key,'manage')?action(r.id,'data-edit','ویرایش'):''}${action(r.id,'data-service-schools','مدارس')}${action(r.id,'data-members','دانش‌آموزان')}${action(r.id,'data-service-map','نقشه')}${can(key,'manage')?action(r.id,'data-del',Number(r.is_active)===0?'بازیابی':'غیرفعال','sy-service-action-danger'):''}</footer>
   </article>`).join('')||'<div class="sy-services-empty"><strong>سرویسی پیدا نشد</strong><p>عبارت جست‌وجو یا فیلترها را تغییر دهید.</p><button type="button" data-clear-grid-filters>نمایش همه سرویس‌ها</button></div>'}</div>
   <div class="pager sy-services-pager"><button id="prevPage" ${state.page<=1?'disabled':''}>صفحه قبل</button><span>صفحه ${number(state.page)} از ${number(Math.max(1,d.pagination.pages))} · ${number(d.pagination.total)} سرویس</span><button id="nextPage" ${state.page>=d.pagination.pages?'disabled':''}>صفحه بعد</button><label>تعداد در صفحه <select id="pageSize" aria-label="تعداد سرویس در صفحه">${[10,25,50,100].map(n=>`<option value="${n}" ${state.limit===n?'selected':''}>${number(n)}</option>`).join('')}</select></label></div>
  </section>`;
 }
 const emptyReset=$('table').querySelector('[data-clear-grid-filters]');
 if(emptyReset)emptyReset.onclick=()=>{const filters=$('gridFilters');if(!filters)return;filters.reset();if(key==='students')$('studentStatuses')?.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b.dataset.status===''));state.appliedFilters=new URLSearchParams(new FormData(filters));state.page=1;state.selected.clear();loadResource(key);};
 const tableWrap=$('table').querySelector('.table-wrap');if(tableWrap){tableWrap.tabIndex=0;tableWrap.setAttribute('role','region');tableWrap.setAttribute('aria-label','جدول '+resourceName(key)+'؛ برای مشاهده ستون‌های بیشتر به صورت افقی پیمایش کنید');}
 const selected=()=>{const n=state.selected.size;$('selectedCount').textContent=n?number(n)+' مورد انتخاب شده':'برای عملیات گروهی، ردیف‌ها را انتخاب کنید';const action=$('bulkServiceStatus');if(action){action.disabled=n===0;action.textContent=n?'تغییر وضعیت '+number(n)+' سرویس انتخابی':'تغییر وضعیت گروهی سرویس‌ها';}};selected();$('table').querySelectorAll('[data-select]').forEach(b=>b.onchange=()=>{b.checked?state.selected.add(Number(b.dataset.select)):state.selected.delete(Number(b.dataset.select));selected();});$('selectPage').onchange=e=>{$('table').querySelectorAll('[data-select]').forEach(b=>{b.checked=e.target.checked;b.onchange();});};
 $('prevPage').onclick=()=>{state.page--;loadResource(key);};$('nextPage').onclick=()=>{state.page++;loadResource(key);};$('pageSize').onchange=e=>{state.limit=Number(e.target.value);state.page=1;loadResource(key);};
 $('table').querySelectorAll('[data-driver-service]').forEach(button=>button.onclick=()=>driverServiceDialog(d.items.find(row=>String(row.id)===button.dataset.driverService)));
 $('table').querySelectorAll('[data-post-financial]').forEach(b=>b.onclick=()=>financialPostingDialog(key,Number(b.dataset.postFinancial)).catch(e=>notify(e.message,true)));$('table').querySelectorAll('[data-school-shifts]').forEach(b=>b.onclick=()=>schoolShiftsDialog(Number(b.dataset.schoolShifts)).catch(e=>notify(e.message,true)));$('table').querySelectorAll('[data-service-schools]').forEach(b=>b.onclick=()=>serviceSchoolsDialog(Number(b.dataset.serviceSchools)));$('table').querySelectorAll('[data-service-map]').forEach(b=>b.onclick=()=>serviceMap(Number(b.dataset.serviceMap)));$('table').querySelectorAll('[data-documents]').forEach(b=>b.onclick=()=>driverDocuments(Number(b.dataset.documents)));$('table').querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>formResource(key,Number(b.dataset.edit)));$('table').querySelectorAll('[data-view]').forEach(b=>b.onclick=()=>viewRecord(key,Number(b.dataset.view)));$('table').querySelectorAll('[data-members]').forEach(b=>b.onclick=()=>serviceMembers(Number(b.dataset.members)));
 $('table').querySelectorAll('[data-del]').forEach(b=>b.onclick=async()=>{const row=state.rows.find(r=>String(r.id)===b.dataset.del);if(!row)return;const ledger=key==='ledger',restore=Number(row.is_active)===0;const message=ledger?(restore?'سند '+(row.direction==='receipt'?'دریافت':'پرداخت')+' به مبلغ '+number(row.amount)+' ریال دوباره فعال شود؟':'سند '+(row.direction==='receipt'?'دریافت':'پرداخت')+' به مبلغ '+number(row.amount)+' ریال حذف شود؟ این عملیات سند را غیرفعال می‌کند و از گزارش‌های فعال کنار می‌گذارد. اگر سند حسابداری به آن متصل باشد، حذف مجاز نیست.'):'وضعیت این رکورد تغییر کند؟';if(!confirm(message))return;try{if(restore)await write('/api/v1/'+key+'/'+row.id,{isActive:1},'PUT');else await api(scoped('/api/v1/'+key+'/'+row.id),{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});lookupCache={};await loadResource(key);if(ledger)notify(restore?'سند مالی بازگردانی شد.':'سند مالی از سوابق فعال حذف شد.');}catch(e){notify(e.message,true);}});
 $('table').querySelectorAll('[data-review]').forEach(b=>b.onclick=async()=>{if(!confirm('این تصمیم برای درخواست تغییر راننده ثبت شود؟'))return;try{await write('/api/v1/operations/driver-changes/'+b.dataset.review+'/review',{status:b.dataset.status});await loadResource(key);}catch(e){notify(e.message,true);}});
 }catch(e){if(state===gridState&&state.token===pageToken){$('table').setAttribute('aria-busy','false');$('table').innerHTML='<div class="sy-grid-error" role="alert"><strong>دریافت اطلاعات انجام نشد</strong><p>'+esc(e.message)+'</p><button type="button" id="retryGrid">تلاش دوباره</button></div>';$('retryGrid').onclick=()=>loadResource(key);}}
}
let modalBackground=[];
function restoreModalBackground(){for(const [node,inert] of modalBackground)if(node.isConnected)node.inert=inert;modalBackground=[];document.body.classList.remove('has-modal');}
function dialog(title,content){disposeMaps();restoreModalBackground();returnFocus=document.activeElement;if(!$('modal')){const box=document.createElement('div');box.id='modal';$('content').append(box);}$('modal').innerHTML=`<div class="modal-backdrop"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="dialogTitle" tabindex="-1"><div class="modal-head"><div><span class="modal-eyebrow">سرویس یار</span><h3 id="dialogTitle">${esc(title)}</h3></div><button type="button" id="closeModal" aria-label="بستن پنجره">×</button></div><div class="modal-body">${content}<div id="formError" class="error" role="alert" tabindex="-1" hidden></div></div></section></div>`;
 document.body.classList.add('has-modal');document.querySelectorAll('.sidebar,.header,#sessionFooter,#content > :not(#modal)').forEach(node=>{modalBackground.push([node,node.inert]);node.inert=true;});$('closeModal').onclick=closeDialog;const backdrop=$('modal').querySelector('.modal-backdrop');backdrop.addEventListener('click',event=>{if(event.target===backdrop)closeDialog();});$('modal').querySelector('section').focus();}
function closeDialog(){disposeMaps();if($('modal'))$('modal').innerHTML='';restoreModalBackground();if(returnFocus?.isConnected)returnFocus.focus();}
document.addEventListener('keydown',e=>{const modal=$('modal')?.querySelector('.modal[role="dialog"]');if(!modal)return;if(e.key==='Escape'){if(document.querySelector('.solar-calendar'))return;e.preventDefault();closeDialog();return;}if(e.key==='Tab'){const items=[...modal.querySelectorAll('button,input,select,textarea,a[href],[tabindex="0"]')].filter(x=>!x.disabled&&x.type!=='hidden'&&x.getClientRects().length&&!x.closest('[hidden]'));const first=items[0],last=items.at(-1);if(!first){e.preventDefault();modal.focus();}else if(e.shiftKey&&(document.activeElement===first||document.activeElement===modal)){e.preventDefault();last.focus();}else if(!e.shiftKey&&(document.activeElement===last||document.activeElement===modal)){e.preventDefault();first.focus();}}});
function resourceWizard(form,key){
 const definitions={students:[['اطلاعات ولی',['mobile','guardianFirstName','guardianLastName','guardianNationalCode','guardianRelation','emergencyPhone']],['مشخصات دانش‌آموز',['firstName','lastName','gender','nationality','nationalCode','fatherName','healthStatus']],['نشانی و مدرسه',['address','returnAddress','latitude','longitude','schoolId','grade','shift','academicYear']],['وضعیت و هزینه',['serviceStatus','tariffDistanceKm','tariffVehicleClass','monthlyFee']]],drivers:[['مشخصات راننده',['firstName','lastName','nationalCode','mobile','status']],['خودرو و نشانی',['vehicleId','isRepresentative','address']],['اطلاعات بانکی',['bankName','bankAccount','iban']]],services:[['مشخصات سرویس',['title','code','capacity','shift','status']],['مدرسه و راننده',['schoolId','driverId','packId']],['مسیر و مبالغ',['startAddress','endAddress','monthlyFee','agreedAmount']]]};
 definitions.cheques=[['مشخصات چک',['bankName','branchName','serialNo','bankAccount']],['دریافت و سررسید',['receivedFrom','accountId','amount','receivedDate','dueDate','description','counterAccountId']]];
 const groups=definitions[key];if(!groups)return;const grid=form.querySelector('.form-grid'),fields=[...grid.children],steps=[];form.classList.add('sy-record-wizard',`sy-record-wizard--${key}`);
 const progress=document.createElement('nav');progress.className='wizard-progress';progress.setAttribute('aria-label','مراحل تکمیل فرم');form.prepend(progress);
 for(const [title,names] of groups){const section=document.createElement('section');section.className='wizard-step';const heading=document.createElement('h4');heading.textContent=title;section.append(heading);const content=document.createElement('div');content.className='form-grid sy-wizard-fields';section.append(content);for(const name of names){const field=fields.find(l=>l.querySelector(`[name="${name}"]`));if(field)content.append(field);}steps.push({title,section,fields:names});grid.before(section);}
 const leftovers=[...grid.children];if(leftovers.length){const extra=document.createElement('details');extra.className='sy-wizard-extras';const summary=document.createElement('summary');summary.textContent='سایر مشخصات ('+number(leftovers.length)+' مورد)';const extraGrid=document.createElement('div');extraGrid.className='form-grid sy-wizard-fields';extraGrid.append(...leftovers);extra.append(summary,extraGrid);steps.at(-1).section.append(extra);}grid.remove();
 const map=form.querySelector('.location-picker');if(map){const address=steps.find(step=>step.fields.includes('latitude'))||steps[0];address.section.append(map);}
 const calculator=form.querySelector('.financial-calculator');if(calculator)steps.at(-1).section.append(calculator);
 const review=document.createElement('section');review.className='wizard-step wizard-review';steps.at(-1).section.after(review);steps.push({title:'بازبینی و ثبت',section:review,fields:[]});
 const actions=document.createElement('div');actions.className='wizard-actions';actions.innerHTML='<button type="button" data-wizard-prev>مرحله قبل</button><span aria-live="polite" data-wizard-position></span><button type="button" class="primary" data-wizard-next>ادامه</button>';form.append(actions);const save=$('saveRecord');actions.append(save);form.noValidate=true;let current=0;
 const show=index=>{current=index;steps.forEach((step,i)=>step.section.hidden=i!==index);progress.innerHTML=steps.map((step,i)=>`<button type="button" class="wizard-marker ${i<index?'complete':''}" ${i===index?'aria-current="step"':''} ${i>index?'disabled':''} data-wizard-step="${i}" aria-label="مرحله ${number(i+1)}: ${esc(step.title)}"><b>${number(i+1)}</b>${esc(step.title)}</button>`).join('');progress.querySelectorAll('[data-wizard-step]').forEach(b=>b.onclick=()=>show(Number(b.dataset.wizardStep)));progress.querySelector('[aria-current="step"]')?.scrollIntoView({block:'nearest',inline:'nearest'});actions.querySelector('[data-wizard-prev]').hidden=index===0;actions.querySelector('[data-wizard-next]').hidden=index===steps.length-1;save.hidden=index!==steps.length-1;actions.querySelector('[data-wizard-position]').textContent='مرحله '+number(index+1)+' از '+number(steps.length);if(index===steps.length-1){review.innerHTML='<h4>اطلاعات را پیش از ذخیره بررسی کنید</h4><dl class="detail-grid">'+[...form.querySelectorAll('label')].filter(l=>l.querySelector('input[name],select[name],textarea[name]')).map(l=>{const f=l.querySelector('input[name],select[name],textarea[name]');const text=f.type==='hidden'&&f.dataset.solarEnhanced?persianDigits(ServiceYarDates.toSolar(f.value)):f.tagName==='SELECT'?f.selectedOptions[0]?.textContent:/Date$/.test(f.name)?dateText(f.value):f.type==='number'&&f.value!==''?number(f.value):persianDigits(f.value);return '<div><dt>'+esc(labels[f.name]||f.name)+'</dt><dd>'+esc(text||'—')+'</dd></div>';}).join('')+'</dl>';}steps[index].section.querySelector('h4')?.setAttribute('tabindex','-1');steps[index].section.querySelector('h4')?.focus();requestAnimationFrame(()=>activeMaps.forEach(m=>m.invalidateSize()));};
 const invalid=section=>{const field=[...section.querySelectorAll('input,select,textarea')].find(f=>f.willValidate&&!f.checkValidity());if(field){const collapsed=field.closest('details.sy-wizard-extras');if(collapsed)collapsed.open=true;const message=field.validity.valueMissing?'این مورد را تکمیل کنید.':field.validity.patternMismatch?(field.title||'قالب مقدار واردشده صحیح نیست.'):field.validationMessage||'مقدار واردشده معتبر نیست.';const id='wizard-error-'+(field.name||'date')+'-'+[...form.elements].indexOf(field);let hint=form.querySelector('[id="'+id+'"]');if(!hint){hint=document.createElement('small');hint.id=id;hint.className='field-error';field.closest('label')?.append(hint);}hint.textContent=message;field.dataset.wizardError=id;field.setAttribute('aria-invalid','true');field.setAttribute('aria-describedby',id);field.setCustomValidity(message);}return field;};
 const clearWizardError=e=>{const field=e.target;if(!field.dataset?.wizardError)return;form.querySelector('[id="'+field.dataset.wizardError+'"]')?.remove();field.setCustomValidity('');field.removeAttribute('aria-invalid');field.removeAttribute('aria-describedby');delete field.dataset.wizardError;};form.addEventListener('input',clearWizardError,true);form.addEventListener('change',clearWizardError,true);
 actions.querySelector('[data-wizard-prev]').onclick=()=>show(current-1);actions.querySelector('[data-wizard-next]').onclick=()=>{const field=invalid(steps[current].section);if(field){field.focus();field.scrollIntoView({block:'center'});return;}show(current+1);};
 form.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.matches('input:not([type=submit]):not([type=button]):not([type=checkbox])')&&current<steps.length-1){e.preventDefault();actions.querySelector('[data-wizard-next]').click();}});form.addEventListener('submit',e=>{if(current<steps.length-1){e.preventDefault();e.stopImmediatePropagation();actions.querySelector('[data-wizard-next]').click();return;}for(let i=0;i<steps.length-1;i++){const field=invalid(steps[i].section);if(field){e.preventDefault();e.stopImmediatePropagation();show(i);field.focus();field.scrollIntoView({block:'center'});return;}}},true);show(0);
}
const ledgerPartyTypes={student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت'};
const ledgerMethods={cash:'نقدی',card:'کارت به کارت',bank:'پایا',transfer:'ساتنا',cheque:'چک',wallet:'کیف پول'};
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
  <section class="ledger-detail-section"><h4>مشخصات تراکنش</h4><dl class="ledger-detail-grid">${item('تاریخ '+kind,dateText(row.entry_date))}${item('روش '+kind,ledgerMethods[row.method]||'ثبت نشده')}${item('شماره پیگیری',persianDigits(row.reference_no||'ثبت نشده'),'dir="auto"')}${item('مبلغ (ریال)',ledgerMoney(row.amount))}</dl></section>
  <section class="ledger-detail-section"><h4>ثبت و مستندات</h4><dl class="ledger-detail-grid">${item('ثبت‌کننده',creator)}${item('تاریخ و ساعت ثبت',ledgerDateTime(row.created_at))}</dl><div class="ledger-detail-note"><b>توضیحات</b><p>${esc(String(row.note||'').trim()||'توضیحی برای این سند ثبت نشده است.')}</p></div></section>
  <div class="ledger-detail-actions"><button type="button" id="ledgerDetailClose">بستن جزئیات</button></div>
 </article>`);
 $('ledgerDetailClose').onclick=closeDialog;
}
async function viewRecord(key,id){
 try{
  const r=await api(scoped('/api/v1/'+key+'/'+id));
  if(key==='ledger'){showLedgerDetails(r);return;}
  const showFinance=key==='students'&&can('school-statement');
  const detailFields=key==='students'?[...resources[key][1],'annualMonths','annualFee']:resources[key][1],detailGroups={students:[['مشخصات دانش‌آموز',['firstName','lastName','nationalCode','gender','fatherName','nationality','healthStatus']],['اطلاعات ولی',['guardianFirstName','guardianLastName','guardianNationalCode','guardianRelation','mobile','emergencyPhone']],['مدرسه و سرویس',['schoolId','grade','shift','academicYear','serviceStatus','monthlyFee','annualMonths','annualFee']],['نشانی و سایر اطلاعات',['address','returnAddress','latitude','longitude']]],drivers:[['مشخصات راننده',['firstName','lastName','nationalCode','mobile','status']],['خودرو و نشانی',['vehicleId','isRepresentative','address']],['اطلاعات بانکی',['bankName','bankAccount','iban']]],services:[['مشخصات سرویس',['title','code','status','capacity','shift']],['مدرسه و راننده',['schoolId','driverId','packId']],['مسیر و مبالغ',['startAddress','endAddress','monthlyFee','agreedAmount']]]};
  const detailValue=f=>{if(key==='services'&&f==='monthlyFee')return number(r.driver_net_monthly??r.monthly_fee)+' ریال';if(key==='students'&&f==='annualFee')return r.annual_fee==null?'—':number(r.annual_fee)+' ریال';if(key==='students'&&f==='annualMonths')return r.annual_months==null?'—':(Number(r.annual_months)===8.5?'۸٫۵':'۹')+' ماه';const v=val(r,f);return /Date$/.test(f)?dateText(v):/amount|fee|balance|total|capacity|score|rating/i.test(f)?number(v):['status','serviceStatus'].includes(f)?(statuses[v]||v||'—'):fieldOptions(key,f)?.find(x=>String(x[0])===String(v))?.[1]||persianDigits(v||'—');};
  const detailRows=fields=>'<dl class="detail-grid sy-detail-grid">'+fields.map(f=>'<div><dt>'+esc(labels[f]||f)+'</dt><dd>'+esc(detailValue(f))+'</dd></div>').join('')+'</dl>';
  const groups=detailGroups[key],used=new Set(groups?.flatMap(([,fields])=>fields)||[]);
  const content=groups?groups.map(([title,fields])=>{const included=fields.filter(f=>detailFields.includes(f));return included.length?'<section class="sy-detail-section"><h4>'+esc(title)+'</h4>'+detailRows(included)+'</section>':'';}).join('')+(detailFields.some(f=>!used.has(f))?'<section class="sy-detail-section"><h4>سایر مشخصات</h4>'+detailRows(detailFields.filter(f=>!used.has(f)))+'</section>':''):detailRows(detailFields);
  dialog('جزئیات '+resourceName(key),'<div class="sy-record-details">'+content+'</div>'+(showFinance?'<section id="studentServiceFinance"></section>':''));
  if(showFinance)serviceFinancePanel($('studentServiceFinance'),{studentId:id,academicYear:r.academic_year||year()});
 }catch(e){notify(e.message,true);}
}
function fieldOptions(key,f){if(f==='tariffVehicleClass')return [['1','کلاس ۱'],['2','کلاس ۲'],['3','کلاس ۳']];if(f==='gender')return [['boy','پسر'],['girl','دختر'],['mixed','مختلط']];if(f==='partyType'||f==='ownerType'||f==='recipientType')return [['student','دانش‌آموز'],['driver','راننده'],['school','مدرسه'],['agency','شرکت']];if(f==='direction')return [['receipt','دریافت'],['payment','پرداخت']];if(f==='method'&&key==='ledger')return [['cash','نقدی'],['card','کارت به کارت'],['bank','پایا'],['transfer','ساتنا'],['cheque','چک']];if(f==='channel')return [['internal','داخلی'],['sms','پیامک']];if(f==='nationality')return [['iranian','ایرانی'],['foreign','غیر ایرانی']];if(f==='isActive'||f==='isRepresentative')return [['1','بله'],['0','خیر']];if(f==='serviceStatus')return ['no_request','pending','prepayment','waiting','assigned','cancelled'].map(k=>[k,statuses[k]]);if(f==='status'){const values={drivers:['pending','active','inactive'],services:['draft','active','cancelled'],messages:['queued'],invoices:['open','paid','overdue'],contracts:['draft','active','cancelled'],'driver-work':['pending','approved','cancelled'],'parent-reports':['open','closed'],'driver-changes':['pending']};return (values[key]||['draft','active','cancelled']).map(k=>[k,statuses[k]||k]);}return null;}
async function selectSchoolFromCatalog(){
 dialog('انتخاب مدرسه از فهرست مرکزی',`<p>مدرسه را با نام یا کد آموزش‌وپرورش جستجو کنید. مدرسه انتخاب‌شده به شرکت شما متصل می‌شود و نیازی به ثبت مجدد نیست.</p><label>جستجوی مدرسه<input id="catalogSearch" placeholder="نام یا کد مدرسه" autocomplete="off"></label><div id="catalogResults" aria-live="polite">در حال دریافت مدارس…</div>`);
 let version=0;
 const refresh=async()=>{const token=++version,term=$('catalogSearch')?.value||'';try{const rows=await api(scoped('/api/v1/school-catalog?search='+encodeURIComponent(term)));if(token!==version||!$('catalogResults'))return;$('catalogResults').innerHTML=rows.length?'<div class="table-wrap"><table><thead><tr><th>کد</th><th>مدرسه</th><th>ناحیه</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>'+rows.map(r=>`<tr><td>${esc(r.code)}</td><td>${esc(r.name)}</td><td>${esc(r.region||'—')}</td><td>${Number(r.selected)?'انتخاب شده':'جدید'}</td><td><button type="button" data-catalog-id="${r.id}" ${Number(r.selected)?'disabled':''}>انتخاب مدرسه</button></td></tr>`).join('')+'</tbody></table></div>':'مدرسه‌ای یافت نشد.';$('catalogResults').querySelectorAll('[data-catalog-id]').forEach(button=>button.onclick=async()=>{button.disabled=true;try{await write('/api/v1/school-catalog/select',{catalogSchoolId:Number(button.dataset.catalogId)});closeDialog();lookupCache={};notify('مدرسه به شرکت اضافه شد.');if(gridState?.key==='schools')await loadResource('schools');}catch(e){notify(e.message,true);button.disabled=false;}});}catch(e){if(token===version&&$('catalogResults'))$('catalogResults').textContent=e.message;}};
 let timer;$('catalogSearch').oninput=()=>{clearTimeout(timer);timer=setTimeout(refresh,250);};await refresh();
}
async function formResource(key,id,onSaved){
 if(!can(key,'manage'))return;if(key==='schools'&&!id){await selectSchoolFromCatalog();return;}const token=pageToken;let row={};try{if(id)row=await api(scoped('/api/v1/'+key+'/'+id));if(token!==pageToken)return;const required={schools:['name'],students:['firstName','lastName','mobile','schoolId'],drivers:['firstName','lastName'],services:['title'],vehicles:['plate'],ledger:['partyType','partyId','direction','amount','entryDate'],'driver-work':['driverId','workDate','amount'],'driver-payments':['driverId','paymentDate','amount']};
 dialog((id?'ویرایش ':'ثبت ')+resourceName(key),`<form id="editForm" class="${key==='ledger'?'sy-ledger-editor':''}"><div class="form-grid">${resources[key][1].map(f=>{const defaults={academicYear:year(),nationality:'iranian',capacity:4,serviceStatus:'no_request',isRepresentative:0,direction:'receipt',channel:'internal',monthlyFee:0,agreedAmount:0};const original=val(row,f);const v=original!==''?original:(id?'':defaults[f]??''),options=fieldOptions(key,f),req=required[key]?.includes(f)?'required':'',text=/address|body|note|reason|description|response/i.test(f),relation=relations[f]||(['partyId','ownerId','recipientId'].includes(f)?'party':null);return `<label>${labels[f]||esc(f)}${req?' *':''}${f==='amount'&&key==='ledger'?`<div class="sy-money-field"><input type="text" name="amountDisplay" data-money-display inputmode="numeric" autocomplete="off" spellcheck="false" dir="ltr" placeholder="۰" aria-label="مبلغ به ریال" ${req} value="${esc(v!==''?number(v):'')}"><span class="sy-money-unit">ریال</span><input type="hidden" name="amount" value="${esc(v)}"></div><small class="sy-money-hint">مبلغ را بدون اعشار وارد کنید؛ ارقام به‌صورت سه‌رقمی جدا می‌شوند.</small>`:options?`<select name="${f}" ${req}><option value="">انتخاب کنید</option>${options.map(([value,title])=>`<option value="${esc(value)}" ${String(v)===String(value)?'selected':''}>${esc(title)}</option>`).join('')}</select>`:relation?`<input data-lookup-search="${f}" placeholder="جستجوی ${labels[f]}" aria-label="جستجوی ${labels[f]}"><select name="${f}" aria-label="${esc(labels[f]||f)}" data-relation="${relation}" ${req}><option value="">انتخاب کنید</option>${v?`<option value="${esc(v)}" selected>#${esc(v)}</option>`:''}</select>`:text?`<textarea name="${f}" maxlength="6000" ${req}>${esc(v)}</textarea>`:`<input name="${f}" ${req} ${/Date$/.test(f)?'type="date"':/distanceKm/i.test(f)?'type="number" min="0" step="1" inputmode="numeric"':/amount|fee|capacity|score|latitude|longitude|rating|balance/i.test(f)?'type="number" step="any"':/mobile|phone/i.test(f)?'inputmode="tel"':'maxlength="255"'} value="${esc(v)}">`}</label>`}).join('')}</div><p class="muted">مبالغ به ریال و تمام تاریخ‌های ورودی و نمایشی شمسی هستند.</p><button class="primary" id="saveRecord">ذخیره</button></form>`);
 const form=$('editForm');if(key==='ledger'){const display=form.querySelector('[data-money-display]'),raw=form.elements.amount;const normalize=v=>String(v??'').replace(/[۰-۹]/g,c=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c))).replace(/[٠-٩]/g,c=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(c))).replace(/[^0-9]/g,'');const sync=()=>{const digits=normalize(display.value).replace(/^0+(?=\d)/,'');raw.value=digits;display.value=digits?digits.replace(/\B(?=(\d{3})+(?!\d))/g,'٬').replace(/[0-9]/g,c=>'۰۱۲۳۴۵۶۷۸۹'[Number(c)]):'';display.setCustomValidity(digits&&Number(digits)>0?'':'مبلغ معتبر و بزرگ‌تر از صفر وارد کنید.');};display.addEventListener('input',sync);display.addEventListener('blur',sync);sync();form.addEventListener('submit',e=>{sync();if(!display.checkValidity()){e.preventDefault();e.stopImmediatePropagation();display.reportValidity();}},true);}
 if(form.elements.mobile){const mobile=form.elements.mobile;mobile.pattern='09[0-9]{9}';mobile.maxLength=11;mobile.inputMode='tel';mobile.dir='ltr';mobile.placeholder='09xxxxxxxxx';mobile.title='شماره همراه ۱۱ رقمی با ۰۹ شروع می‌شود.';mobile.addEventListener('input',()=>{mobile.value=ServiceYarDates.digits(mobile.value);});}const typeKey=['partyId','ownerId','recipientId'].find(f=>form.elements[f]);
 if(key==='students'){
  attachStudentTariff(form);
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
 }else if(['services','driver-work'].includes(key)&&can('payroll'))attachFinancialCalculator(form,key,id,row);
 const populate=async(select,search='')=>{let resource=select.dataset.relation;if(resource==='party'){const type=form.elements.partyType?.value||form.elements.ownerType?.value||form.elements.recipientType?.value;resource={student:'students',driver:'drivers',school:'schools',agency:'agencies'}[type];if(!resource)return;}try{const value=select.value,d=await api(scoped('/api/v1/'+resource+'?limit=200&active=1&search='+encodeURIComponent(search)));if(!select.isConnected)return;const items=d.items||d;select.innerHTML='<option value="">انتخاب کنید</option>'+items.map(r=>`<option value="${r.id}">${esc(person(r))}</option>`).join('');if(value&&!items.some(r=>String(r.id)===String(value))){const detail=await api(scoped('/api/v1/'+resource+'/'+value));select.add(new Option(person(detail),value));}select.value=value;}catch(e){notify(e.message,true);}};
 form.querySelectorAll('[data-relation]').forEach(select=>{populate(select);const input=form.querySelector(`[data-lookup-search="${select.name}"]`);let timer=0;input.oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>populate(select,input.value),300);};});
 if(form.elements.latitude&&form.elements.longitude)await locationPicker(form,key);if(typeKey){const type=form.elements.partyType||form.elements.ownerType||form.elements.recipientType;type.onchange=()=>{form.elements[typeKey].value='';populate(form.elements[typeKey]);};}
 resourceWizard(form,key);
 form.onsubmit=async e=>{e.preventDefault();const button=$('saveRecord');button.disabled=true;$('formError').hidden=true;try{const body=Object.fromEntries(new FormData(form));if(key==='students'&&body.tariffDistanceKm!==undefined&&body.tariffDistanceKm!=='')body.tariffDistanceKm=Math.round(Number(body.tariffDistanceKm));if(key==='ledger')delete body.amountDisplay;await write('/api/v1/'+key+(id?'/'+id:''),body,id?'PUT':'POST');lookupCache={};closeDialog();notify('اطلاعات ذخیره شد.');if(onSaved)onSaved();else if(gridState?.key===key&&gridState.token===pageToken)await loadResource(key);else{location.hash=key;}}catch(err){if($('formError')){$('formError').textContent=err.message;$('formError').hidden=false;$('formError').focus();}}finally{if(button.isConnected)button.disabled=false;}};
 }catch(e){notify(e.message,true);}
}
function bulkFeeDialog(){if(!gridState?.selected.size){notify('دانش‌آموزان مورد نظر را انتخاب کنید.',true);return;}dialog('تغییر سریع هزینه سرویس',`<form id="feeForm"><p>${number(gridState.selected.size)} دانش‌آموز انتخاب شده است.</p><label>نوع تغییر<select name="mode"><option value="set">تعیین مبلغ</option><option value="increase">افزایش مبلغ</option><option value="decrease">کاهش مبلغ</option><option value="percent">درصد تغییر (منفی برای کاهش)</option></select></label><label>مقدار<input type="number" step="0.01" name="value" required></label><button class="primary">ثبت تغییر هزینه‌ها</button></form>`);$('feeForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{await write('/api/v1/operations/fees',{ids:[...gridState.selected],...Object.fromEntries(new FormData(e.target))});closeDialog();await loadResource('students');notify('هزینه‌ها به‌روزرسانی شدند.');}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};}
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
  dialog('دانش‌آموزان سرویس '+service.title,`<p>ظرفیت ${number(service.capacity)} · تخصیص ${number(rows.length)} · جای خالی ${number(free)}</p>
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
    <button class="primary" disabled>افزودن به سرویس</button>
   </form>`:'<p>ظرفیت این سرویس تکمیل است.</p>'}`:''}`);
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
async function downloadFile(url,name){try{const response=await fetch(scoped(url),{credentials:'same-origin'});if(!response.ok){const d=await response.json();throw new Error(d?.error?.message||'دریافت فایل انجام نشد.');}const blob=await response.blob(),link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download=name;link.click();setTimeout(()=>URL.revokeObjectURL(link.href),1000);}catch(e){notify(e.message,true);}}
async function importPage(token){
 $('content').innerHTML=`<section class="panel"><h2>ثبت دانش‌آموز از اکسل</h2><p>فایل XLSX یا CSV، حداکثر ۲۰۰ ردیف و ۲ مگابایت. شماره‌ها و کدهای دارای صفر ابتدایی را در Excel به شکل متن ذخیره کنید.</p><button id="templateDownload">دریافت نمونه CSV</button><form id="importForm" class="form-grid"><label>مدرسه<select name="schoolId" required id="importSchool"></select></label><label>فایل<input type="file" accept=".csv,.xlsx" name="file" required></label><button class="primary">پیش‌نمایش و اعتبارسنجی</button></form><div id="importPreview"></div></section>`;
 const schools=await lookup('schools');if(token!==pageToken)return;$('importSchool').innerHTML='<option value="">انتخاب کنید</option>'+schools.map(s=>`<option value="${s.id}">${esc(s.name)}</option>`).join('');
 $('templateDownload').onclick=()=>{const b=new Blob(['\uFEFFنام,نام خانوادگی,شماره همراه ولی,کد ملی,پایه,آدرس,سال تحصیلی\n'],{type:'text/csv;charset=utf-8'}),a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='student-template.csv';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);};
 let importRevision=0;$('importForm').addEventListener('change',()=>{importRevision++;$('importPreview').innerHTML='';});$('importForm').onsubmit=async e=>{e.preventDefault();const revision=importRevision;const schoolId=e.target.schoolId.value,button=e.target.querySelector('button');button.disabled=true;try{const r=await fetch(scoped('/api/v1/operations/import/preview'),{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':session.csrfToken},body:new FormData(e.target)}),d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error?.message||'فایل خوانده نشد.');if(token!==pageToken||revision!==importRevision)return;const rows=d.data.rows;$('importPreview').innerHTML='<h3>پیش‌نمایش '+number(rows.length)+' دانش‌آموز برای '+esc(schools.find(s=>String(s.id)===String(schoolId))?.name||'مدرسه انتخاب‌شده')+'</h3>'+simpleTable(['نام','نام خانوادگی','شماره همراه ولی'],rows.map(r=>[r.firstName,r.lastName,r.mobile]))+'<button class="primary" id="commitImport">ثبت همه ردیف‌ها در مدرسه انتخاب‌شده</button>';$('commitImport').onclick=async()=>{$('commitImport').disabled=true;try{const result=await write('/api/v1/operations/import',{schoolId,rows});lookupCache={};$('importPreview').innerHTML='<p class="ok">'+number(result.created)+' دانش‌آموز ثبت شد.</p>';}catch(err){notify(err.message,true);if($('commitImport'))$('commitImport').disabled=false;}};}catch(err){notify(err.message,true);}finally{if(button.isConnected)button.disabled=false;}};
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
 const showDetail=async id=>{
  const opener=document.activeElement;
  try{
   const d=await api(scoped('/api/v1/operations/school-statements/'+id));if(token!==pageToken)return;
   const modal=$('modal');
   const exportLink=d.status==='final'?`<a class="button primary" href="${esc(scoped('/api/v1/reports/school-statements/'+id+'.xlsx'))}">دریافت اکسل ۱۴ شیتی</a>`:'<span class="sy-statement-muted">این صورت‌وضعیت حذف شده است؛ پس از بازیابی خروجی قابل دریافت خواهد بود.</span>';
   modal.innerHTML=`<div class="modal-backdrop sy-statement-backdrop"><section class="modal-card sy-statement-modal" role="dialog" aria-modal="true" aria-labelledby="statementDetailHeading" tabindex="-1">
    <div class="sy-statement-modal-heading"><div><span class="sy-statement-kicker">جزئیات صورت‌وضعیت ثبت‌شده</span><h2 id="statementDetailHeading">صورت‌وضعیت ${esc(d.month_name)} ${number(d.year)}</h2><p>ثبت در تاریخ ${esc(dateText(d.created_at))} · ${d.status==='deleted'?'حذف‌شده':'نهایی'}</p></div><button type="button" id="statementClose" aria-label="بستن جزئیات">بستن <span aria-hidden="true">×</span></button></div>
    <div class="sy-statement-modal-body">${details(d)}</div><div class="sy-statement-modal-footer">${exportLink}<button type="button" data-close-statement>بازگشت به سوابق</button></div>
   </section></div>`;
   const dialogElement=modal.querySelector('[role="dialog"]');
   const close=()=>{modal.innerHTML='';if(opener?.isConnected)opener.focus();};
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
   modal.querySelector('#statementClose').focus();
  }catch(e){notify(e.message,true);}
 };
 const archiveSearch=$('statementArchiveSearch'),archiveStatus=$('statementArchiveStatus'),archiveOrder=$('statementArchiveOrder');
 let storedStatements=[],archiveRequest=0,archiveLoadError='';
 const normalizeStatementQuery=value=>String(value??'').replace(/[۰-۹]/g,x=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(x))).replace(/[٠-٩]/g,x=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(x))).replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/[\\u200c\\s]+/g,' ').trim().toLocaleLowerCase('fa');
 const statementAmount=r=>Number(r.reference_totals?.statement_excluding_certificate_insurance??r.totals?.net??0);
 const paintArchive=()=>{
  if(token!==pageToken||!archive.isConnected)return;
  if(archiveLoadError){archive.innerHTML='<div class="error" role="alert">'+esc(archiveLoadError)+'</div>';$('statementArchiveCount').textContent='بارگذاری ناموفق';archive.removeAttribute('aria-busy');return;}
  const query=normalizeStatementQuery(archiveSearch.value),status=archiveStatus.value,order=archiveOrder.value;
  const filtered=storedStatements.filter(r=>{
   if(status!=='all'&&r.status!==status)return false;
   const period=(months[Number(r.jalali_month)-1]||'')+' '+r.jalali_year+' '+r.jalali_month+' '+r.period_start+' '+r.period_end;
   return !query||normalizeStatementQuery(period).includes(query);
  }).sort((a,b)=>order==='amount'?statementAmount(b)-statementAmount(a)||Number(b.id)-Number(a.id):
      (order==='asc'?1:-1)*((Number(a.jalali_year)*12+Number(a.jalali_month))-(Number(b.jalali_year)*12+Number(b.jalali_month)))||
      Number(b.id)-Number(a.id));
  $('statementArchiveCount').textContent=number(filtered.length)+' صورت‌وضعیت از '+number(storedStatements.length)+' سابقه';
  archive.innerHTML=filtered.length?`<table class="sy-statement-table"><thead><tr><th scope="col">ردیف</th><th scope="col">ماه</th><th scope="col">بازه</th><th scope="col">دانش‌آموز</th><th scope="col">راننده</th><th scope="col">حق‌الزحمه رانندگان</th><th scope="col">بهای خدمات اولیا</th><th scope="col">جمع صورت‌وضعیت</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead><tbody>${filtered.map((r,i)=>`<tr><td data-label="ردیف">${number(i+1)}</td><td data-label="ماه"><strong>${esc(months[Number(r.jalali_month)-1]||'—')} ${number(r.jalali_year)}</strong></td><td data-label="بازه">${esc(dateText(r.period_start))} تا ${esc(dateText(r.period_end))}</td><td data-label="دانش‌آموز">${number(r.totals?.students)}</td><td data-label="راننده">${number(r.totals?.drivers)}</td><td data-label="حق‌الزحمه رانندگان" class="sy-financial-number">${number(r.reference_totals?.driver_fee??r.totals?.gross)}</td><td data-label="بهای خدمات اولیا" class="sy-financial-number">${r.reference_totals?.parent_total!=null?number(r.reference_totals.parent_total):'—'}</td><td data-label="جمع صورت‌وضعیت" class="sy-financial-number"><strong>${number(statementAmount(r))}</strong></td><td data-label="وضعیت"><span class="sy-statement-state ${r.status==='deleted'?'is-deleted':'is-final'}">${r.status==='deleted'?'حذف‌شده':'نهایی'}</span></td><td data-label="عملیات"><div class="member-actions"><button type="button" data-statement-detail="${r.id}">جزئیات</button>${r.status==='final'?'<a href="'+esc(scoped('/api/v1/reports/school-statements/'+r.id+'.xlsx'))+'">خروجی اکسل</a>':''}${allowed?`<button type="button" data-statement-toggle="${r.id}" data-statement-action="${r.status==='deleted'?'restore':'delete'}">${r.status==='deleted'?'بازیابی':'حذف'}</button>`:''}</div></td></tr>`).join('')}</tbody></table>`:'<div class="sy-statement-empty"><strong>صورت‌وضعیتی با این فیلتر پیدا نشد.</strong><p>ماه، سال یا وضعیت انتخابی را تغییر دهید.</p></div>';
  archive.removeAttribute('aria-busy');
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
   preview.innerHTML=`<div class="toolbar"><h3>پیش‌نمایش ${esc(d.month_name)} ${number(d.year)}</h3><span class="badge">ثبت‌نشده</span></div>${details(d)}${allowed&&!d.validation_errors?.length&&!d.missing_driver_errors?.length&&!d.reconciliation_warnings?.length?'<div class="member-actions"><button type="button" class="primary" id="finalizeStatement">تأیید و ثبت نهایی</button></div>':''}`;
   $('finalizeStatement')?.addEventListener('click',async()=>{if(!confirm('صورت‌وضعیت این ماه با مبالغ نمایش‌داده‌شده نهایی و ثبت شود؟'))return;const b=$('finalizeStatement');b.disabled=true;try{const result=await write('/api/v1/operations/school-statements',{...payload,approvalDigest:d.approval_digest},'POST');preview.innerHTML='<p class="ok">صورت‌وضعیت ماهانه ثبت شد.</p>';notify('صورت‌وضعیت نهایی ثبت شد.');await load();showDetail(result.id);}catch(err){notify(err.message,true);b.disabled=false;}});
  }catch(err){preview.innerHTML='<p class="error">'+esc(err.message)+'</p>';}finally{button.disabled=false;}
 };
 await load();
}
async function operationPage(key,token){
 const dates=['payroll','finance-report'].includes(key),group=key==='grouping';
 $('content').innerHTML=`<section class="panel sy-operation-page"><header class="sy-operation-header"><div><span class="sy-operation-kicker">مرکز عملیات و گزارش‌ها</span><h2>${esc(titles[key])}</h2><p>فیلترهای موردنظر را انتخاب کنید و نتیجه را در همین صفحه بررسی کنید.</p></div><div class="sy-operation-actions"><button type="button" id="printOperation" disabled aria-label="چاپ نتیجه گزارش">چاپ گزارش</button></div></header><form id="operationFilters" class="filters sy-operation-filters" aria-label="فیلترهای گزارش">${dates?'<label>از تاریخ<input name="from" type="date" required></label><label>تا تاریخ<input name="to" type="date" required></label>':''}${group?'<label>مدرسه<select name="schoolId" required id="groupSchool"></select></label><label>ظرفیت<input name="capacity" type="number" min="1" max="50" value="4"></label>':''}${key==='distances'?'<label>فاصله مستقیم بیشتر از (کیلومتر)<input type="number" name="km" value="20" min="1" max="1000"></label>':''}<button type="submit" class="primary" id="operationSubmit">${key==='monitoring'?'به‌روزرسانی':'نمایش نتایج'}</button></form><div class="sy-operation-result-head"><h3>نتایج ${esc(titles[key])}</h3><span id="operationResultStatus" role="status" aria-live="polite">در انتظار دریافت اطلاعات</span></div><div id="operationResult" class="sy-operation-result" aria-live="polite"></div></section><div id="modal"></div>`;
 $('printOperation').onclick=()=>window.print();if(dates){const now=new Date(),y=now.getFullYear(),m=now.getMonth();const iso=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');$('operationFilters').elements.from.value=iso(new Date(y,m,1));$('operationFilters').elements.to.value=iso(new Date(y,m+1,0));}
 if(dates){const parts=new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',timeZone:'Asia/Tehran'}).formatToParts(new Date()),jy=Number(parts.find(p=>p.type==='year').value),jm=Number(parts.find(p=>p.type==='month').value),label=document.createElement('label');label.textContent='ماه شمسی';label.innerHTML+='<select id="jalaliMonth">'+['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'].map((name,i)=>'<option value="'+(i+1)+'" '+(jm===i+1?'selected':'')+'>'+name+'</option>').join('')+'</select>';const yearLabel=document.createElement('label');yearLabel.textContent='سال شمسی';yearLabel.innerHTML+='<input id="jalaliYear" type="number" min="1300" max="1500" value="'+jy+'">';$('operationFilters').prepend(label,yearLabel);const apply=()=>{const range=persianMonthRange(Number($('jalaliYear').value),Number($('jalaliMonth').value));if(range){$('operationFilters').elements.from.value=range.from;$('operationFilters').elements.to.value=range.to;}};$('jalaliYear').onchange=apply;$('jalaliMonth').onchange=apply;apply();}if(group){const schools=await lookup('schools');if(token!==pageToken)return;$('groupSchool').innerHTML=schools.map(s=>`<option value="${s.id}">${esc(s.name)}</option>`).join('');}
 let request=0;const load=async()=>{const current=++request,result=$('operationResult'),status=$('operationResultStatus'),submit=$('operationSubmit'),print=$('printOperation');submit.disabled=true;print.disabled=true;status.textContent='در حال دریافت اطلاعات…';result.setAttribute('aria-busy','true');result.innerHTML='<div class="sy-operation-loading" role="status"><span class="sy-operation-spinner" aria-hidden="true"></span>در حال دریافت و آماده‌سازی گزارش…</div>';const query=new URLSearchParams(new FormData($('operationFilters'))),path=key==='monitoring'?'/api/v1/monitoring/activity':'/api/v1/operations/'+({'finance-report':'ledger','school-report':'schools'}[key]||key);
 try{const d=await api(scoped(path+'?'+query));if(token!==pageToken||current!==request)return;
 if(key==='payroll')await renderPayroll(d,token);
 if(key==='finance-report')result.innerHTML=simpleTable(['نوع طرف حساب','شناسه','دریافت (ریال)','پرداخت (ریال)','خالص دریافت'],d.items.map(r=>[({student:'دانش‌آموز',driver:'راننده',school:'مدرسه',agency:'شرکت',invoice:'فاکتور'})[r.party_type],r.party_id,number(r.receipts),number(r.payments),number(Number(r.receipts)-Number(r.payments))]));
 if(key==='school-report')result.innerHTML=simpleTable(['کد مدرسه','مدرسه','شیفت','نوع','دانش‌آموز','دارای سرویس','جمع ماهیانه'],d.items.map(r=>[r.code,r.name,r.shift,r.school_type,number(r.students),number(r.assigned),number(r.monthly_fees)]));if(key==='monitoring'){const users=await api(scoped('/api/v1/monitoring/users'));if(token!==pageToken||current!==request)return;result.innerHTML='<h3>وضعیت فعالیت کاربران شرکت</h3>'+simpleTable(['کاربر','وضعیت','آخرین ورود','آخرین فعالیت'],users.map(u=>[person(u),Number(u.online)?'آنلاین':'آفلاین',dateText(u.last_login_at),dateText(u.last_seen_at)]))+'<h3>رویدادهای عملیاتی</h3>'+simpleTable(['کاربر','عملیات','بخش','شناسه رکورد','تاریخ'],d.map(r=>[r.user_id,r.action,resourceName(r.entity_type),r.entity_id,dateText(r.created_at)]));}
 if(key==='distances')result.innerHTML='<p class="muted">فاصله مستقیم جغرافیایی است؛ فاصله مسیر جاده‌ای نیازمند سرویس نقشه است.</p>'+simpleTable(['دانش‌آموز','مدرسه','کیلومتر'],d.items.map(r=>[person(r),r.school,number(r.km)]));
 if(group){result.innerHTML='<p class="muted">پیشنهاد بر اساس نزدیک‌ترین مختصات و ظرفیت است. گروه‌ها را پیش از تخصیص بررسی کنید. مسیر جاده‌ای محاسبه نشده است.</p>'+d.groups.map((g,i)=>`<section class="group-card"><h3>گروه ${number(i+1)} · ${number(g.length)} نفر</h3>${simpleTable(['دانش‌آموز','نشانی'],g.map(r=>[person(r),r.address]))}<button data-use-group="${i}">تعریف سرویس برای این گروه</button></section>`).join('')+`<p>${number(d.unlocated.length)} دانش‌آموز فاقد مختصات است.</p>`;result.querySelectorAll('[data-use-group]').forEach(b=>{b.disabled=!can('services','manage');b.onclick=async()=>{const title=prompt('عنوان سرویس جدید:');if(!title?.trim())return;b.disabled=true;try{await write('/api/v1/operations/groups',{title,schoolId:query.get('schoolId'),capacity:query.get('capacity'),ids:d.groups[Number(b.dataset.useGroup)].map(st=>Number(st.id))});lookupCache={};notify('سرویس '+title+' ایجاد شد. راننده را از عملیات سرویس‌ها تعیین کنید.');await load();}catch(err){notify(err.message+'؛ سرویس‌های ایجادشده را در عملیات سرویس‌ها بررسی کنید.',true);b.disabled=false;}};});}
 if(token===pageToken&&current===request){status.textContent='گزارش به‌روزرسانی شد';print.disabled=false;result.removeAttribute('aria-busy');submit.disabled=false;}
 }catch(e){if(token===pageToken&&current===request){status.textContent='دریافت گزارش ناموفق بود';result.removeAttribute('aria-busy');submit.disabled=false;result.innerHTML='<div class="sy-operation-error" role="alert"><strong>دریافت اطلاعات انجام نشد</strong><p>'+esc(e.message)+'</p><button type="button" id="operationRetry">تلاش مجدد</button></div>';$('operationRetry').onclick=load;}}};
 $('operationFilters').onsubmit=e=>{e.preventDefault();load();};if(!group||$('groupSchool').value)await load();
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
 const manage=global||can('users','manage');box.innerHTML=`${!global&&manage?'<button data-new-user>کاربر جدید</button>':''}<div class="table-wrap"><table><thead><tr><th>نام کاربری</th><th>نام</th><th>نقش</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${esc(r.username)}</td><td>${esc(person(r))}</td><td>${esc(r.roles||r.role_names)}</td><td>${Number(r.is_active)?'فعال':'غیرفعال'}</td><td>${manage?`<button data-edit-user="${r.id}">ویرایش</button><button data-user-password="${r.id}">تغییر رمز</button>`:''}</td></tr>`).join('')}</tbody></table></div>`;
 box.querySelector('[data-new-user]')?.addEventListener('click',()=>userEditor(null,false));box.querySelectorAll('[data-edit-user]').forEach(b=>b.onclick=()=>userEditor(rows.find(r=>String(r.id)===b.dataset.editUser),global));box.querySelectorAll('[data-user-password]').forEach(b=>b.onclick=()=>passwordDialog(rows.find(r=>String(r.id)===b.dataset.userPassword),global));
}
function passwordDialog(row,global=false,self=Number(row.id)===Number(session.user.id)){
 dialog('تغییر رمز '+row.username,`<form id="changePasswordForm"><p class="muted">پس از تغییر رمز، همه نشست‌های قبلی این کاربر بسته می‌شوند.</p>${self?'<label>رمز فعلی<input name="currentPassword" type="password" autocomplete="current-password" required></label>':''}<label>رمز جدید<input name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></label><label>تکرار رمز جدید<input name="passwordConfirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required></label><button class="primary">ذخیره رمز جدید</button></form>`);
 $('changePasswordForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const url=self?'/api/v1/auth/password':(global?'/api/v1/admin/users/':'/api/v1/users/')+row.id+'/password';const d=await write(url,Object.fromEntries(new FormData(e.target)));e.target.reset();closeDialog();if(d.requiresLogin)location.reload();else notify('رمز تغییر کرد و نشست‌های قبلی بسته شدند.');}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
}
function userEditor(row,global){
 const roles=[['operator','اپراتور'],['agency_admin','مدیر شرکت']];const self=Number(row?.id)===Number(session.user.id);if(global)roles.push(['super_admin','مدیر کل']);
 dialog(row?'ویرایش کاربر':'کاربر جدید',`<form id="userEditor"><div class="form-grid"><label>نام کاربری<input name="username" required minlength="3" maxlength="100" value="${esc(row?.username)}"></label><label>نام<input name="firstName" value="${esc(row?.first_name)}"></label><label>نام خانوادگی<input name="lastName" value="${esc(row?.last_name)}"></label>${!row?'<label>رمز اولیه<input name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="72"></label>':''}${!self?`<label>نقش<select name="role"><option value="">حفظ نقش فعلی</option>${roles.map(([k,t])=>`<option value="${k}">${t}</option>`).join('')}</select></label><label>وضعیت<select name="isActive"><option value="1">فعال</option><option value="0" ${Number(row?.is_active)===0?'selected':''}>غیرفعال</option></select></label>`:''}</div><button class="primary">ذخیره کاربر</button></form>`);
 if(!row&&$('userEditor').elements.role)$('userEditor').elements.role.value='operator';
 $('userEditor').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const body=Object.fromEntries(new FormData(e.target));if(!body.role)delete body.role;await write((global?'/api/v1/admin/users':'/api/v1/users')+(row?'/'+row.id:''),body,row?'PUT':'POST');closeDialog();notify('کاربر ذخیره شد.');if(global)await companiesPage();else await settingsPage();}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
}
const originalCompaniesPage=companiesPage;
companiesPage=async function(){await originalCompaniesPage();if(!session.roles?.includes('super_admin'))return;const token=pageToken;const rows=await api('/api/v1/admin/users');if(token!==pageToken)return;const section=[...$('content').querySelectorAll('section')].find(s=>s.querySelector('h2')?.textContent==='کاربران سامانه');if(section)userManagementTable(section.querySelector('.table-wrap'),rows,true);};
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
async function settingsPage(){
 const token=pageToken;const setting=await api(scoped('/api/v1/settings'));if(token!==pageToken)return;
 const fields={company_phone:'تلفن شرکت',company_address:'نشانی شرکت',academic_year:'سال تحصیلی',support_phone:'شماره پشتیبانی'};
 const settingsSection=(title,description)=>`<header class="sy-settings-section-head"><div><h2>${title}</h2><p>${description}</p></div></header>`;
 $('content').innerHTML=`<section class="panel sy-settings-panel">${settingsSection('اتصال سرویس‌دهنده‌ها','وضعیت ارتباط سرویس‌های جانبی سامانه')}<div id="providerStatus"></div></section><section class="panel sy-settings-panel">${settingsSection('اطلاعات شرکت','مشخصات عمومی و اطلاعات تماس شرکت')}<form id="transportSettings" class="form-grid sy-settings-grid">${Object.entries(fields).map(([k,t])=>`<label>${t}<input name="${k}" value="${esc(setting[k]||'')}" maxlength="500"></label>`).join('')}${can('settings','manage')?'<button class="primary">ذخیره تنظیمات</button>':''}</form><p class="muted">اتصال پیامک نگین، کیف پول بله و Google Maps از تنظیمات محرمانه سرور خوانده می‌شود.</p></section>${can('holidays')?'<section class="panel"><h2>تعطیلات</h2><div id="holidayRows"></div><div id="holidayEditor"></div></section>':''}${can('users')?'<section class="panel"><h2>کاربران شرکت</h2><div id="companyUserRows"></div></section>':''}<div id="modal"></div>`;
 const mapsPanel=document.createElement('section');mapsPanel.className='panel sy-settings-panel';mapsPanel.innerHTML=`${settingsSection('نقشه و مسیریابی نشان','تنظیم سرویس مسیریابی و کلید نمایش نقشه')}<p>کلید service روی سرور نگهداری می‌شود؛ نقشه تعاملی به کلید web جداگانه نیاز دارد. خالی گذاشتن کلید سرویس، مقدار قبلی را حفظ می‌کند.</p><form id="neshanSettings" class="form-grid sy-settings-grid"><label>نقشه پیش‌فرض<select name="map_provider"><option value="neshan" selected>نشان</option></select></label><label>کلید سرویس نشان<input name="neshan_service_key" type="password" autocomplete="new-password" placeholder="service.…"></label><label>کلید نمایش نقشه وب<input name="neshan_web_key" value="${esc(setting.neshan_web_key||'')}" placeholder="web.…"></label>${can('settings','manage')?'<button class="primary">ذخیره تنظیمات نقشه</button>':''}<p id="neshanConfigStatus" role="status"></p></form>`;$('content').prepend(mapsPanel);
 if(can('settings','manage')){
 let office=setting.company_location;if(typeof office==='string'){try{office=JSON.parse(office||'null');}catch{office=null;notify('مختصات ذخیره‌شده دفتر شرکت معتبر نیست؛ می‌توانید موقعیت را دوباره ثبت کنید.',true);}}
 const panel=document.createElement('section');panel.className='panel';panel.innerHTML=`<h2>موقعیت دفتر شرکت</h2><form id="companyLocationForm"><div class="form-grid"><label>عرض جغرافیایی<input name="latitude" type="number" step="any" min="-90" max="90" required value="${esc(office?.latitude??'')}"></label><label>طول جغرافیایی<input name="longitude" type="number" step="any" min="-180" max="180" required value="${esc(office?.longitude??'')}"></label><label>نشانی<input name="address" value="${esc(setting.company_address||'')}"></label></div><button class="primary" type="submit">ذخیره موقعیت شرکت</button><p role="status" data-office-result></p></form>`;mapsPanel.after(panel);
 const form=panel.querySelector('form');form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;try{await write('/api/v1/settings',{company_location:{latitude:form.elements.latitude.value,longitude:form.elements.longitude.value},company_address:form.elements.address.value},'PUT');panel.querySelector('[data-office-result]').textContent='موقعیت شرکت ذخیره شد؛ در داشبورد نمایش داده می‌شود.';}catch(err){panel.querySelector('[data-office-result]').textContent=err.message;}finally{button.disabled=false;}};
 locationPicker(form,'company').catch(err=>notify(err.message,true));
 }
 const updateMapStatus=async()=>{const c=await api(scoped('/api/v1/maps/config'));if(token!==pageToken)return;if(!Object.prototype.hasOwnProperty.call(setting,'neshan_web_key'))$('neshanSettings').elements.neshan_web_key.value=c.webKey||'';$('neshanConfigStatus').textContent='کلید سرویس: '+(c.serviceConfigured?'تنظیم شده':'تنظیم نشده')+' · کلید نمایش: '+(c.webKey?'تنظیم شده':'تنظیم نشده');};updateMapStatus().catch(e=>notify(e.message,true));
 $('neshanSettings').onsubmit=async e=>{e.preventDefault();if(!can('settings','manage'))return;const button=e.target.querySelector('button');button.disabled=true;try{await write('/api/v1/settings',Object.fromEntries(new FormData(e.target)),'PUT');e.target.elements.neshan_service_key.value='';await updateMapStatus();notify('تنظیمات نقشه ذخیره شد.');}catch(e){notify(e.message,true);}finally{button.disabled=false;}};
 financialSettings(setting);
 if(can('agencies','manage')){const f=$('transportSettings'),label=document.createElement('label');label.textContent='نام شرکت';const input=document.createElement('input');input.value=currentAgency.name;input.required=true;label.append(input);const button=document.createElement('button');button.type='button';button.textContent='ذخیره نام شرکت';button.onclick=async()=>{button.disabled=true;try{await write('/api/v1/agencies/'+currentAgency.id,{name:input.value,code:currentAgency.code,cityId:currentAgency.city_id,isActive:currentAgency.is_active},'PUT');currentAgency.name=input.value;renderNav();notify('نام شرکت ذخیره شد.');}catch(err){notify(err.message,true);}finally{button.disabled=false;}};label.append(button);f.prepend(label);}
 api(scoped('/api/v1/integrations/status')).then(d=>{if(token===pageToken&&$('providerStatus'))$('providerStatus').innerHTML=simpleTable(['سرویس','وضعیت'],[['پیامک نگین',d.sms?'تنظیم شده':'نیازمند تنظیمات سرور'],['کیف پول بله',d.bale?'تنظیم شده':'نیازمند تنظیمات سرور'],['Google Maps',d.google?'کلید تنظیم شده':'نیازمند کلید سرور'],['OpenStreetMap','آماده']]);}).catch(e=>notify(e.message,true));$('transportSettings').onsubmit=async e=>{e.preventDefault();try{await write('/api/v1/settings',Object.fromEntries(new FormData(e.target)),'PUT');notify('تنظیمات ذخیره شد.');}catch(err){notify(err.message,true);}};
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
 deductionPanel.innerHTML=`<header class="sy-settings-section-head"><div><h2>کسورات رانندگان</h2><p>درصدهای کسرشونده از مجموع شهریه ماهانه سرویس؛ مبنای محاسبه مبلغ خالص قابل پرداخت به راننده</p></div></header>
 <form id="driverDeductionSettings" class="sy-deduction-form">
 <div class="form-grid sy-settings-grid">
 ${Object.entries({supervision:'مدیریت، نظارت و بازرسی',coordination:'اطلاع‌رسانی و هماهنگی',overhead:'بالاسری شرکت',insurance:'بیمه راننده'}).map(([key,label])=>`<label>${label} (درصد)<input type="number" name="${key}" inputmode="decimal" min="0" max="100" step="0.001" required value="${esc(rates[key])}" ${can('settings','manage')?'':'disabled'}></label>`).join('')}
 </div>
 <div class="sy-deduction-summary" aria-live="polite"><span>جمع کسورات</span><strong id="deductionTotal"></strong><span>خالص راننده از هر ۱۰۰ ریال</span><strong id="deductionNet"></strong></div>
 <p class="muted">درصدهای پیش‌فرض: ۳٪ + ۱٪ + ۷٪ + ۷٫۸٪ = ۱۸٫۸٪. تغییر درصدها بر محاسبات بعدی و نمایش محاسباتی سرویس‌ها اثر می‌گذارد؛ اسناد حقوق قطعی قبلی بدون اصلاحیه بازنویسی نمی‌شوند.</p>
 ${can('settings','manage')?'<div class="member-actions"><button type="submit" class="primary">ذخیره درصد کسورات</button><button type="button" id="resetDeductionRates">بازگردانی مقادیر پیش‌فرض</button></div>':''}
 <p id="deductionSaveStatus" role="status"></p>
 </form>`;
 $('content').append(deductionPanel);
 const nav=document.createElement('nav');nav.className='sy-settings-tabs';nav.setAttribute('aria-label','تب‌های تنظیمات');
 nav.innerHTML='<button type="button" data-settings-switch="general" class="active" aria-selected="true">تنظیمات عمومی</button><button type="button" data-settings-switch="deductions" aria-selected="false">کسورات</button>';
 $('content').prepend(nav);
 [...$('content').children].forEach(child=>{if(child!==nav&&child!==deductionPanel&&child.id!=='modal')child.dataset.settingsTab='general';});
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
let neshanSdkPromise=null;
async function makeMap(element,center=[36.3,59.6],zoom=12){
 try{const company=currentAgency?.id,config=await api(scoped('/api/v1/maps/config'));if(!element?.isConnected||company!==currentAgency?.id)return null;
 if(!config.webKey)throw new Error('کلید نمایش نقشه نشان در تنظیمات شرکت ثبت نشده است.');
 if(!neshanSdkPromise)neshanSdkPromise=new Promise((resolve,reject)=>{let settled=false;const timer=setTimeout(()=>finish(new Error('زمان دریافت نقشه نشان به پایان رسید.')),15000);const style=document.createElement('link');style.rel='stylesheet';style.href='https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.css';document.head.append(style);const script=document.createElement('script');script.src='https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.js';const finish=error=>{if(settled)return;settled=true;clearTimeout(timer);if(error){script.remove();style.remove();neshanSdkPromise=null;reject(error);}else resolve();};script.onload=()=>finish();script.onerror=()=>finish(new Error('کتابخانه نقشه نشان دریافت نشد؛ اتصال شبکه را بررسی کنید.'));document.head.append(script);});
 await neshanSdkPromise;if(!element.isConnected||company!==currentAgency?.id)return null;
 const map=new L.Map(element,{key:config.webKey,maptype:'dreamy',center,zoom,scrollWheelZoom:false});element.dataset.provider='neshan';activeMaps.push(map);requestAnimationFrame(()=>map.invalidateSize());return map;
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
 $('content').innerHTML=`<section class="panel"><h2>پرداخت با کیف پول بله</h2><p>درخواست پرداخت به گفتگوی بله پرداخت‌کننده ارسال می‌شود. ثبت وصول فقط پس از استعلام بله انجام می‌شود.</p>${can('bale-payments','manage')?'<form id="baleInvoiceForm" class="form-grid"><label>فاکتور<select name="invoiceId" required id="baleInvoiceId"></select></label><label>شناسه عددی گفتگوی بله<input name="chatId" inputmode="numeric" required pattern="[1-9][0-9]{0,18}"></label><button class="primary">ارسال درخواست پرداخت به بله</button></form>':''}<div id="balePaymentResult"></div>${simpleTable(['شناسه','فاکتور','مبلغ (ریال)','وضعیت','تاریخ'],rows.map(r=>[r.id,r.invoice_id,number(r.amount),({created:'ایجاد شده',sending:'در حال ارسال',pending:'در انتظار پرداخت',paid:'پرداخت تأیید شده',uncertain:'نیازمند بررسی'})[r.status]||r.status,dateText(r.created_at)]))}</section>`;
 if(!$('baleInvoiceForm'))return;const invoices=await lookup('invoices');if(token!==pageToken)return;$('baleInvoiceId').innerHTML='<option value="">انتخاب کنید</option>'+invoices.map(r=>`<option value="${r.id}">${esc(r.title)} · ${number(r.amount)} ریال</option>`).join('');
 $('baleInvoiceForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const result=await write('/api/v1/integrations/bale/invoices',Object.fromEntries(new FormData(e.target)));if(token!==pageToken)return;$('balePaymentResult').textContent=result.alreadyPending?'برای این فاکتور درخواست فعال وجود دارد؛ وضعیت را بررسی کنید.':'درخواست پرداخت در بله ایجاد شد؛ وصول پس از تأیید پرداخت ثبت خواهد شد.';}catch(err){notify(err.message,true);}finally{if(button.isConnected)button.disabled=false;}};
}

async function renderPayroll(data,token){
 const result=$('operationResult');
 result.innerHTML=`<p class="muted">کسورات پیش‌فرض راننده: مدیریت و نظارت ۳٪، هماهنگی ۱٪، بالاسری شرکت ۷٪ و بیمه ۷٫۸٪؛ مجموع ۱۸٫۸٪ از کارکرد ناخالص. مبلغ خالص و مانده پس از کسورات محاسبه می‌شوند. اسناد تأییدشده قبلی بدون اصلاحیه تغییر نمی‌کنند.</p><div class="filters"><input id="payrollSearch" placeholder="جستجوی راننده" aria-label="جستجوی راننده"><label><input type="checkbox" id="showBankColumns"> نمایش مشخصات بانکی</label></div><div class="table-wrap"><table><thead><tr><th><input type="checkbox" id="payrollSelectAll" aria-label="انتخاب همه رانندگان"></th><th>راننده</th><th>کارکرد ناخالص</th><th>نظارت ۳٪</th><th>هماهنگی ۱٪</th><th>بالاسری ۷٪</th><th>بیمه ۷٫۸٪</th><th>جمع کسورات ۱۸٫۸٪</th><th>خالص کارکرد</th><th>ماهیانه سرویس</th><th>پرداخت ثبت‌شده</th><th>مانده</th><th>مبلغ حقوق (ریال)</th><th class="bank-column" hidden>شبا</th><th class="bank-column" hidden>شماره حساب</th></tr></thead><tbody>${data.items.map(r=>`<tr data-payroll-driver="${r.id}"><td><input type="checkbox" name="payrollSelected" value="${r.id}" aria-label="انتخاب ${esc(person(r))}"></td><td>${esc(person(r))}</td><td>${number(r.earned)}</td><td>${number(r.supervision_deduction)}</td><td>${number(r.coordination_deduction)}</td><td>${number(r.overhead_deduction)}</td><td>${number(r.insurance_deduction)}</td><td>${number(r.total_deductions)}</td><td>${number(r.net_earned)}</td><td>${number(r.agreed)}</td><td>${number(r.paid)}</td><td>${number(r.balance)}</td><td><input type="number" min="0" max="1000000000000" step="0.01" data-salary="${r.id}" value="${Math.max(0,Number(r.net_earned))}" aria-label="حقوق ${esc(person(r))}"></td><td class="bank-column" hidden>${esc(r.iban||'—')}</td><td class="bank-column" hidden>${esc(r.bank_account||'—')}</td></tr>`).join('')||'<tr><td colspan="15" class="empty">راننده‌ای یافت نشد.</td></tr>'}</tbody></table></div><p id="payrollTotal"></p>${can('payroll','manage')?'<button class="primary" id="createPayrollDocument">ثبت سند فیش حقوق</button>':''}<h3>اسناد حقوق این بازه</h3><div id="payrollDocuments"></div>`;
 const total=()=>{let value=0,count=0;result.querySelectorAll('input[name="payrollSelected"]:checked').forEach(c=>{value+=Number(result.querySelector(`[data-salary="${c.value}"]`).value);count++;});$('payrollTotal').textContent=number(count)+' راننده · جمع حقوق انتخابی '+number(value)+' ریال';};total();
 $('payrollSelectAll').onchange=e=>{result.querySelectorAll('input[name="payrollSelected"]').forEach(c=>{if(!c.closest('tr').hidden)c.checked=e.target.checked;});total();};result.querySelectorAll('input[name="payrollSelected"],[data-salary]').forEach(input=>input.oninput=total);
 $('showBankColumns').onchange=e=>result.querySelectorAll('.bank-column').forEach(cell=>cell.hidden=!e.target.checked);$('payrollSearch').oninput=e=>result.querySelectorAll('[data-payroll-driver]').forEach(row=>row.hidden=!row.innerText.includes(e.target.value.trim()));
 const documentBox=$('payrollDocuments');const list=async()=>{const docs=await api(scoped('/api/v1/operations/payroll-documents?from='+data.from+'&to='+data.to));if(token!==pageToken||!documentBox.isConnected)return;$('payrollDocuments').innerHTML=docs.length?docs.map(d=>`<div class="payroll-doc"><span>${esc(d.document_no)} · ${number(d.total_amount)} ریال · ${d.status==='approved'?'تأییدشده':'پیش‌نویس'}</span><button data-document-view="${d.id}">مشاهده${d.status==='draft'?' / ویرایش':''}</button>${d.status==='draft'&&can('payroll','manage')?`<button data-document-approve="${d.id}">تأیید سند</button>`:''}${d.status==='approved'?`<button data-document-bank="${d.id}">خروجی بانک CSV</button>`:''}${d.status==='approved'&&can('drivers')?`<button data-document-xlsx="${d.id}">اکسل صورت‌وضعیت حقوق</button>`:''}</div>`).join(''):'<p class="empty">سند حقوقی ثبت نشده است.</p>';
 $('payrollDocuments').querySelectorAll('[data-document-view]').forEach(b=>b.onclick=()=>payrollDocument(Number(b.dataset.documentView)));
 $('payrollDocuments').querySelectorAll('[data-document-approve]').forEach(b=>b.onclick=async()=>{if(!confirm('مبالغ سند بررسی شده‌اند و سند تأیید شود؟ پس از تأیید، مبالغ قابل تغییر نیستند.'))return;b.disabled=true;try{await write('/api/v1/operations/payroll-documents/'+b.dataset.documentApprove+'/approve',{});await list();}catch(e){notify(e.message,true);b.disabled=false;}});
 $('payrollDocuments').querySelectorAll('[data-document-xlsx]').forEach(b=>b.onclick=()=>downloadFile('/api/v1/operations/payroll-documents/'+b.dataset.documentXlsx+'/xlsx','payroll.xlsx'));
 $('payrollDocuments').querySelectorAll('[data-document-bank]').forEach(b=>b.onclick=()=>downloadFile('/api/v1/operations/payroll-documents/'+b.dataset.documentBank+'/bank','payroll-bank.csv'));
 };
 $('createPayrollDocument')?.addEventListener('click',async()=>{const lines=[...result.querySelectorAll('input[name="payrollSelected"]:checked')].map(c=>({driverId:Number(c.value),amount:result.querySelector(`[data-salary="${c.value}"]`).value}));if(!lines.length){notify('رانندگان را انتخاب کنید.',true);return;}$('createPayrollDocument').disabled=true;try{await write('/api/v1/operations/payroll-documents',{from:data.from,to:data.to,lines});await list();notify('سند پیش‌نویس حقوق ذخیره شد؛ هیچ انتقال وجهی انجام نشد.');}catch(e){notify(e.message,true);}finally{if($('createPayrollDocument'))$('createPayrollDocument').disabled=false;}});
 await list();
}
async function payrollDocument(id){
 try{const doc=await api(scoped('/api/v1/operations/payroll-documents/'+id)),editable=doc.status==='draft'&&can('payroll','manage');dialog('سند حقوق '+doc.document_no,`<p>${dateText(doc.date_from)} تا ${dateText(doc.date_to)}</p><p class="muted">${esc(doc.deduction_policy||'')}</p>${doc.lines.some(r=>r.salary_differs_from_calculated_net)?'<p class="sy-payroll-legacy-warning">توجه: حقوق ثبت‌شده در برخی ردیف‌ها با خالص محاسباتی تفاوت دارد؛ اسناد قدیمی بدون اصلاحیه تغییر نمی‌کنند.</p>':''}<form id="payrollDocumentForm"><div class="table-wrap"><table><thead><tr><th>راننده</th><th>کارکرد ناخالص</th><th>نظارت ۳٪</th><th>هماهنگی ۱٪</th><th>بالاسری ۷٪</th><th>بیمه ۷٫۸٪</th><th>جمع کسورات</th><th>خالص محاسباتی</th><th>حقوق سند</th></tr></thead><tbody>${doc.lines.map(r=>`<tr><td>${esc(person(r))}</td><td>${number(r.earned)}</td><td>${number(r.supervision_deduction)}</td><td>${number(r.coordination_deduction)}</td><td>${number(r.overhead_deduction)}</td><td>${number(r.insurance_deduction)}</td><td>${number(r.total_deductions)}</td><td>${number(r.expected_net_salary)}</td><td><input data-doc-salary="${r.driver_id}" type="number" min="0" step="0.01" value="${esc(r.salary)}" ${editable?'':'readonly'} aria-label="حقوق ${esc(person(r))}"></td></tr>`).join('')}</tbody></table></div>${editable?'<button class="primary">ذخیره مبالغ</button>':''}<button type="button" id="printPayrollDocument">چاپ</button></form>`);$('printPayrollDocument').onclick=()=>window.print();if(editable)$('payrollDocumentForm').onsubmit=async e=>{e.preventDefault();try{const lines=[...e.target.querySelectorAll('[data-doc-salary]')].map(input=>({driverId:Number(input.dataset.docSalary),amount:input.value}));await write('/api/v1/operations/payroll-documents/'+id,{lines},'PUT');closeDialog();render();}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;}};}catch(e){notify(e.message,true);}
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
 $('content').innerHTML=`<section class="panel"><div class="page-intro"><div><h2>تخصیص پیشنهادی راننده</h2><p>سرویس بدون راننده به راننده فعال با خودروی دارای ظرفیت کافی پیشنهاد می‌شود. راننده و خودرو در سرویس فعال دیگری نباید استفاده شده باشند. این پیشنهاد بر پایه ظرفیت است؛ زمان‌بندی شیفت و مسیر جاده‌ای را محاسبه نمی‌کند.</p></div><button id="refreshAssignments">تازه‌سازی</button></div><form id="driverAssignmentForm"><div class="table-wrap"><table><thead><tr><th>انتخاب</th><th>سرویس</th><th>راننده</th><th>ظرفیت خودرو</th></tr></thead><tbody>${d.items.map((r,i)=>`<tr><td><input type="checkbox" name="assignment" value="${i}" aria-label="تخصیص ${esc(r.serviceTitle)}" checked></td><td>${esc(r.serviceTitle)}</td><td>${esc(r.driverName)}</td><td>${number(r.capacity)}</td></tr>`).join('')||'<tr><td colspan="4">پیشنهاد قابل ثبت وجود ندارد.</td></tr>'}</tbody></table></div>${d.items.length?'<button class="primary">ثبت تخصیص‌های انتخاب‌شده</button>':''}<p id="assignmentFeedback" role="status"></p></form><h3>سرویس‌های بدون پیشنهاد</h3>${simpleTable(['سرویس'],d.unmatched.map(r=>[r.title]))}</section>`;
 $('refreshAssignments').onclick=()=>driverAssignmentPage(token).catch(e=>notify(e.message,true));
 $('driverAssignmentForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button'),items=[...e.target.querySelectorAll('[name=assignment]:checked')].map(x=>({serviceId:d.items[Number(x.value)].serviceId,driverId:d.items[Number(x.value)].driverId}));if(!items.length){$('assignmentFeedback').textContent='حداقل یک پیشنهاد انتخاب کنید.';return;}button.disabled=true;try{const result=await write('/api/v1/operations/driver-assignment',{items});lookupCache={};notify(number(result.assigned)+' تخصیص ثبت شد.');await driverAssignmentPage(token);}catch(err){if($('assignmentFeedback'))$('assignmentFeedback').textContent=err.message;if(button.isConnected)button.disabled=false;}};
}

async function financialPostingDialog(key,id){
 const token=pageToken,[row,defaults,accounts]=await Promise.all([api(scoped('/api/v1/'+key+'/'+id)),api(scoped('/api/v1/accounting/defaults')),lookup('accounts')]);if(token!==pageToken)return;
 const incoming=key==='payments'||(key==='ledger'&&row.direction==='receipt'),money=defaults[row.method==='cash'?'cash':'bank'],counter=defaults[key==='driver-payments'?'driverSalary':'serviceIncome'];
 dialog('ثبت سند حسابداری',`<p>مبلغ ${number(row.amount)} ریال. ثبت، یک سند تراز تأییدشده می‌سازد و ویرایش سابقه مالی را قفل می‌کند. انتقال وجه انجام نمی‌شود.</p><form id="postFinancialForm"><div class="form-grid">${[['debitAccountId','حساب بدهکار',incoming?money:counter],['creditAccountId','حساب بستانکار',incoming?counter:money]].map(([name,title,value])=>`<label>${title}<select name="${name}" required><option value="">انتخاب کنید</option>${accounts.map(a=>`<option value="${a.id}" ${String(value)===String(a.id)?'selected':''}>${esc(a.code+' · '+a.title)}</option>`).join('')}</select></label>`).join('')}</div><button class="primary">ثبت سند / مشاهده سند قبلی</button></form>`);
 $('postFinancialForm').onsubmit=async e=>{e.preventDefault();const button=e.target.querySelector('button');button.disabled=true;try{const d=await write('/api/v1/accounting/post/'+key+'/'+id,Object.fromEntries(new FormData(e.target)));closeDialog();await journalEditor(d.documentId,pageToken);}catch(err){$('formError').textContent=err.message;$('formError').hidden=false;button.disabled=false;}};
}

async function dailySchedulePage(token=pageToken){
 $('content').innerHTML=`<section class="panel"><h2>برنامه روزانه و تداخل شیفت‌ها</h2><p>برنامه از شیفت مدرسه و نام دقیق شیفت سرویس محاسبه می‌شود. بازه ورود تا خروج برای تشخیص تداخل استفاده می‌شود؛ زمان سفر و توقف محاسبه نمی‌شود. این گزارش تخصیص‌ها را تغییر نمی‌دهد.</p><form id="dailyScheduleFilters" class="filters"><label>تاریخ<input type="date" name="date" required value="${todayISO()}"></label><label>شنبه آغاز هفته اول<input type="date" name="weekAnchor"></label><button>نمایش برنامه</button></form><div id="dailyScheduleResult" aria-live="polite"></div></section>`;
 let request=0;const load=async()=>{const version=++request,box=$('dailyScheduleResult');box.textContent='در حال محاسبه برنامه…';try{const d=await api(scoped('/api/v1/operations/daily-schedule?'+new URLSearchParams(new FormData($('dailyScheduleFilters')))));if(token!==pageToken||version!==request)return;box.innerHTML='<h3>شیفت‌های فعال</h3>'+simpleTable(['سرویس','شیفت','ورود','خروج','راننده'],d.items.map(r=>[r.service,r.shift,r.start,r.end,r.driver||'تخصیص نیافته']))+'<h3>تداخل‌ها · '+number(d.conflicts.length)+'</h3>'+simpleTable(['سرویس اول','سرویس دوم','علت'],d.conflicts.map(r=>[r.first,r.second,r.reason]))+'<h3>موارد نیازمند تکمیل</h3>'+simpleTable(['سرویس','علت'],d.issues.map(r=>[r.service,r.reason]));}catch(e){if(token===pageToken&&version===request)box.textContent=e.message;}};
 $('dailyScheduleFilters').onsubmit=e=>{e.preventDefault();load();};await load();
}

async function installmentPage(){
 $('content').innerHTML=`<section class="panel"><h2>برنامه پیش‌پرداخت و اقساط</h2><p>پیش‌نمایش را بررسی کنید؛ ثبت نهایی برای پیش‌پرداخت و هر قسط فاکتور باز می‌سازد. پیش‌پرداخت به معنی دریافت وجه نیست.</p><form id="installmentPreview" class="form-grid"><label>دانش‌آموز<select name="studentId" id="installmentStudent"></select></label><label>تاریخ صدور<input name="issueDate" type="date"></label><label>مبلغ کل قرارداد · ریال<input name="total" inputmode="numeric" required pattern="[0-9]{1,12}"></label><label>پیش‌پرداخت · ریال<input name="advance" inputmode="numeric" required value="0" pattern="[0-9]{1,12}"></label><label>تعداد اقساط<input name="count" type="number" min="1" max="24" value="9" required></label><label>سررسیدهای شمسی، هر تاریخ در یک خط<textarea name="dates" required placeholder="1405/08/01"></textarea></label><button>محاسبه برنامه اقساط</button>${can('installments','manage')?'<button name="createPlan" value="1">ثبت نهایی و ایجاد فاکتورها</button>':''}</form><div id="installmentResult" role="status" aria-live="polite"></div></section>`;
 const requestKey=crypto.randomUUID(),select=$('installmentStudent');const students=await lookup('students');if(!select.isConnected)return;select.innerHTML='<option value="">انتخاب کنید</option>'+students.map(r=>`<option value="${r.id}">${esc(person(r))}</option>`).join('');
 $('installmentPreview').onsubmit=async e=>{e.preventDefault();const buttons=[...e.target.querySelectorAll('button:not([type=button])')],result=$('installmentResult');buttons.forEach(b=>b.disabled=true);try{const body=Object.fromEntries(new FormData(e.target));body.dates=body.dates.trim().split(/\s+/).map(d=>ServiceYarDates.toISO(d));const creating=e.submitter?.name==='createPlan';if(creating&&!confirm('فاکتورهای پیش‌پرداخت و اقساط ایجاد شوند؟'))return;body.requestKey=requestKey;const d=await write(creating?'/api/v1/operations/installments':'/api/v1/operations/installments/preview',body);if(creating){if(result.isConnected)result.textContent='برنامه '+number(d.planId)+' ثبت شد؛ '+number(d.invoiceIds.length)+' فاکتور در بخش فاکتورها قابل پرداخت است.';return;}if(!result.isConnected)return;result.innerHTML='<p>پیش‌پرداخت: '+number(d.advance)+' ریال · مانده: '+number(d.remaining)+' ریال</p>'+simpleTable(['قسط','سررسید','مبلغ · ریال'],d.items.map(r=>[number(r.number),dateText(r.dueDate),number(r.amount)]));}catch(err){if(result.isConnected)result.textContent=err.message;}finally{buttons.forEach(b=>{if(b.isConnected)b.disabled=false;});}};
}

function attachStudentTariff(form){
 const box=document.createElement('div');box.className='financial-calculator';
 box.innerHTML='<button type="button">محاسبه با نرخ‌نامه</button><p role="status"></p>';form.querySelector('.form-grid').after(box);
 const button=box.querySelector('button'),status=box.querySelector('p');
 const input=()=>({schoolId:form.elements.schoolId.value,academicYear:form.elements.academicYear.value,distanceKm:form.elements.tariffDistanceKm.value===''?'':Math.round(Number(form.elements.tariffDistanceKm.value)),vehicleClass:form.elements.tariffVehicleClass.value});
 button.onclick=async()=>{const data=input();button.disabled=true;try{const r=await write('/api/v1/operations/tariffs/quote',data);if(!form.isConnected||JSON.stringify(data)!==JSON.stringify(input()))return;if(r.monthlyFee===null){status.textContent='برای این مدرسه، سال، کلاس و مسافت نرخ ثبت نشده است.';return;}form.elements.monthlyFee.value=r.monthlyFee;status.textContent=number(r.monthlyFee)+' ریال؛ با ذخیره فرم ثبت می‌شود.';}catch(e){status.textContent=e.message;}finally{button.disabled=false;}};
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
 const [schools,drivers,students,vehicles,feeSettings]=await Promise.all([lookup('schools'),lookup('drivers'),lookup('students',true),lookup('vehicles'),api(scoped('/api/v1/operations/driver-deduction-rates')).catch(()=>({...defaultDriverRates}))]);if(token!==pageToken)return;
 const driverRates={...defaultDriverRates,...feeSettings};
 $('content').innerHTML=`<form id="newServiceSetup"><div class="toolbar"><button type="button" id="showServiceStudents" disabled>افزودن دانش‌آموز</button><button type="button" id="quoteServiceStudents" disabled>دریافت قیمت و مسیر</button></div><div class="dashboard-grid"><section class="panel"><h2>مدرسه سرویس</h2><p id="serviceSchoolSummary">برای شروع، مدرسه یا مدارس سرویس را انتخاب کنید.</p><button type="button" id="pickServiceSchools">انتخاب مدرسه</button></section><section class="panel"><h2>راننده سرویس</h2><p id="serviceDriverSummary">راننده را انتخاب کنید تا قیمت‌ها محاسبه شوند.</p><button type="button" id="pickServiceDriver">انتخاب راننده</button></section></div><section class="panel" id="serviceStudentSelection" hidden><h2>دانش‌آموزان سرویس</h2><p id="selectedServiceStudents"></p></section><section class="panel" id="serviceSetupDetails" hidden><h2>مشخصات و هزینه سرویس</h2><div class="form-grid"><label>عنوان سرویس<input name="title" maxlength="200" required></label><label>ظرفیت<input name="capacity" type="number" min="1" max="200" value="4" required></label><label>شیفت<input name="shift" maxlength="50"></label><label>مبلغ توافقی راننده · ریال<input name="agreedAmount" type="number" min="0" step="1" value="0"></label><p class="sy-service-year-policy">مبلغ سالانه بر اساس مقطع هر دانش‌آموز محاسبه می‌شود: ابتدایی ۸٫۵ ماه، متوسطه و دبیرستان ۹ ماه.</p></div><div id="serviceQuoteResult" role="status" aria-live="polite"></div><button class="primary" id="createService" disabled>ثبت سرویس</button></section></form>`;
 if(initialDriverId){const back=document.createElement('a');back.href='#drivers';back.className='route-link';back.textContent='بازگشت به مدیریت رانندگان';$('newServiceSetup').before(back);}
 const form=$('newServiceSetup'),chosen=new Set(),show=$('showServiceStudents'),quote=$('quoteServiceStudents'),result=$('serviceQuoteResult'),save=$('createService');
 let selectedSchools=[],driverId=null,revision=0,saving=false;
 const vehicleFor=driver=>vehicles.find(v=>Number(v.id)===Number(driver.vehicle_id));
 const driverCaption=driver=>[person(driver),vehicleFor(driver)?.model].filter(Boolean).join(' · ');
 const hasMehrDriver=student=>Number(student.mehr_driver_id)===Number(driverId);
 const schoolIds=()=>selectedSchools;

 const canSubmit=()=>Boolean(driverId&&selectedSchools.length&&chosen.size&&!saving&&form.elements.title.value.trim()&&form.elements.capacity.checkValidity()&&chosen.size<=Number(form.elements.capacity.value)&&form.elements.agreedAmount.checkValidity());
 const refreshSave=()=>{save.disabled=!canSubmit();};
 const sync=()=>{
  for(const s of students)if(!selectedSchools.includes(Number(s.school_id)))chosen.delete(Number(s.id));
  if(!form.elements.title.value.trim()&&driverId&&selectedSchools.length){const driver=drivers.find(d=>Number(d.id)===driverId);const school=schools.find(sc=>Number(sc.id)===selectedSchools[0]);form.elements.title.value=['سرویس',school?.name,driver?person(driver):''].filter(Boolean).join(' - ').slice(0,200);}
  show.disabled=!selectedSchools.length||!driverId;quote.disabled=show.disabled||!chosen.size;refreshSave();
  $('serviceStudentSelection').hidden=show.disabled;$('serviceSetupDetails').hidden=!chosen.size;
  $('selectedServiceStudents').textContent=chosen.size?students.filter(s=>chosen.has(Number(s.id))).map(person).join('، '):'دانش‌آموزان این سرویس را انتخاب کنید.';
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
 const selectDriver=driver=>{
  const vehicle=vehicleFor(driver);
  driverId=Number(driver.id);
  form.elements.capacity.max=vehicle?Math.min(200,Number(vehicle.capacity)):200;
  form.elements.capacity.value=vehicle?Math.min(200,Number(vehicle.capacity)):4;
  $('serviceDriverSummary').textContent=driverCaption(driver)+(vehicle?' · ظرفیت '+number(vehicle.capacity):' · ظرفیت خودرو ثبت نشده');
  $('pickServiceDriver').textContent='تغییر راننده';
  sync();
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
 show.onclick=()=>{
  const pending=new Set([...chosen].map(Number));
  dialog('افزودن دانش‌آموز','<div class="service-picker-tools"><label>نام یا کد ملی<input type="search" id="studentPickerName" placeholder="جست‌وجوی دانش‌آموز"></label><label>نشانی منزل<input type="search" id="studentPickerAddress" placeholder="جست‌وجوی نشانی"></label><label>وضعیت تخصیص<select id="studentPickerStatus"><option value="all">همه دانش‌آموزان مدرسه</option><option value="unassigned">فقط بدون سرویس فعال</option><option value="available">به‌جز لغوشده</option></select></label></div><label class="service-picker-toggle"><input type="checkbox" id="studentPickerMehr"> فقط دارای سابقه مهر این راننده</label><p id="studentPickerCount" role="status"></p><div class="service-picker-list" id="studentPickerRows"></div><div class="service-picker-actions"><button type="button" id="studentPickerSelectAll">انتخاب نتایج</button><button type="button" id="studentPickerClear">لغو انتخاب‌ها</button><button type="button" class="primary" id="confirmStudents">تأیید انتخاب</button></div>');
  const name=$('studentPickerName'),address=$('studentPickerAddress'),rows=$('studentPickerRows');
  const history=$('studentPickerMehr'),status=$('studentPickerStatus');
  const eligibleSchool=s=>selectedSchools.includes(Number(s.school_id));
  const blocked=s=>s.driver_name||Number(s.is_active)===0||s.service_status==='cancelled';
  const matching=()=>students.filter(s=>eligibleSchool(s)&&(status.value==='all'||(status.value==='available'?s.service_status!=='cancelled':!blocked(s)))&&(!history.checked||hasMehrDriver(s))&&[person(s),s.national_code].some(v=>matches(v,name.value))&&matches(s.address,address.value));
  const render=()=>{const filtered=matching();rows.innerHTML=filtered.map(s=>`<label class="service-picker-student ${blocked(s)?'service-picker-assigned':''}"><input type="checkbox" value="${Number(s.id)}" ${pending.has(Number(s.id))?'checked':''} ${blocked(s)?'disabled':''}><span><strong>${esc(person(s))}</strong><small>${esc(s.address||'نشانی ثبت نشده')}${hasMehrDriver(s)?' · سابقه مهر':''}</small>${s.driver_name?`<small class="service-picker-warning">دارای سرویس فعال با راننده ${esc(s.driver_name)}؛ ابتدا تخصیص قبلی را حذف کنید.</small>`:''}${Number(s.is_active)===0?'<small class="service-picker-warning">پرونده دانش‌آموز غیرفعال است؛ ابتدا آن را فعال کنید.</small>':''}${s.service_status==='cancelled'?'<small class="service-picker-warning">وضعیت دانش‌آموز انصراف است؛ ابتدا وضعیت را اصلاح کنید.</small>':''}</span></label>`).join('');$('studentPickerCount').textContent='دانش‌آموزان مدرسه: '+number(students.filter(s=>selectedSchools.includes(Number(s.school_id))).length)+' · نتایج: '+number(filtered.length)+' · انتخاب‌شده: '+number(pending.size);if(!filtered.length){const other=students.filter(s=>!eligibleSchool(s)&&[person(s),s.national_code].some(v=>matches(v,name.value))&&name.value.trim());rows.innerHTML='<p class="empty">دانش‌آموزی با این فیلترها در مدارس انتخاب‌شده یافت نشد.</p>'+(other.length?'<p class="service-picker-warning">'+number(other.length)+' دانش‌آموز با این نام در مدرسه دیگری ثبت شده‌اند. مدرسه انتخابی را بررسی کنید.</p>':'');}};
  name.oninput=address.oninput=history.onchange=status.onchange=render;rows.onchange=e=>{togglePick(e,pending);render();};
  $('studentPickerSelectAll').onclick=()=>{matching().filter(s=>!blocked(s)).forEach(s=>pending.add(Number(s.id)));render();};
  $('studentPickerClear').onclick=()=>{pending.clear();render();};
  $('confirmStudents').onclick=()=>{chosen.clear();for(const s of students)if(pending.has(Number(s.id))&&!blocked(s))chosen.add(Number(s.id));closeDialog();sync();};render();name.focus();
 };
 form.elements.title.addEventListener('input',refreshSave);
 form.elements.agreedAmount.addEventListener('input',refreshSave);

 form.elements.capacity.oninput=sync;
 quote.onclick=async()=>{

  const version=++revision,rows=students.filter(s=>chosen.has(Number(s.id)));
  refreshSave();
  if(!form.elements.capacity.reportValidity()){result.textContent='ظرفیت معتبر وارد کنید.';return;}
  if(rows.length>Number(form.elements.capacity.value)){result.textContent='تعداد دانش‌آموزان از ظرفیت بیشتر است.';return;}
  quote.disabled=true;disposeMaps();result.textContent='در حال دریافت قیمت و مسیر…';
  const current=()=>form.isConnected&&version===revision;
  try{
   const lines=[],routes=[];let total=0,annualTotal=0,missing=0;
   for(const s of rows){
    if(!current())return;
    let route,routeText='مختصات منزل یا مدرسه ثبت نشده است.';
    const school=schools.find(row=>Number(row.id)===Number(s.school_id));
    if(s.latitude!=null&&s.longitude!=null&&school?.latitude!=null&&school.longitude!=null){
     try{route=await write('/api/v1/maps/route',{studentId:s.id});routeText=km(route.distanceMeters/1000)+' کیلومتر';if(route.geometry?.length)routes.push(route.geometry);}catch(e){routeText=e.message;}
    }
    if(!current())return;
    const distance=s.tariff_distance_km;
    const rate=await write('/api/v1/operations/tariffs/quote',{studentId:s.id});
    const months=serviceAcademicMonths(s,school);if(rate.monthlyFee===null)missing++;else{total+=Number(rate.monthlyFee);annualTotal+=Math.round(Number(rate.monthlyFee)*months);}
    lines.push([person(s),km(distance),routeText,rate.monthlyFee===null?'نرخ ثبت نشده':number(rate.monthlyFee),rate.monthlyFee===null?'—':number(Math.round(Number(rate.monthlyFee)*months)),rate.saved?'مبلغ ثبت‌شده':rate.monthlyFee===null?'—':can('students','manage')?'نرخ‌نامه · ذخیره با ثبت سرویس':'نرخ‌نامه · پیش‌نمایش']);
   }
   if(!current())return;
   result.innerHTML=simpleTable(['دانش‌آموز','کیلومتر تعرفه','مسیر نشان','ماهیانه · ریال','برآورد سالانه · ریال','مبنای مبلغ'],lines)+'<p>جمع ماهانه: '+number(total)+' ریال · کسورات راننده '+percent(driverFeeBreakdown(total,driverRates).rate)+'٪: '+number(driverFeeBreakdown(total,driverRates).deducted)+' ریال · خالص ماهانه راننده: '+number(driverFeeBreakdown(total,driverRates).net)+' ریال · برآورد سالانه (ابتدایی ۸٫۵ / متوسطه ۹ ماه): '+number(annualTotal)+' ریال · بدون نرخ: '+number(missing)+'</p>'+(routes.length?'<div class="service-map" id="serviceQuoteMap" aria-label="نقشه مسیرهای نشان"></div>':'');
   refreshSave();
   if(routes.length){
    const map=await makeMap($('serviceQuoteMap'));
    if(!map||!current())return;
    const group=L.featureGroup(routes.map(geometry=>L.polyline(geometry,{color:'#005b35',weight:4}))).addTo(map);
    map.fitBounds(group.getBounds().pad(.15),{maxZoom:16});
   }
  }catch(e){if(current())result.textContent='دریافت برآورد امکان‌پذیر نشد: '+e.message+'؛ در صورت تکمیل مشخصات می‌توانید سرویس را ثبت کنید.';}finally{if(current()){quote.disabled=false;refreshSave();}}
 };
 form.onsubmit=async e=>{
  e.preventDefault();refreshSave();if(save.disabled||saving)return;
  const body=Object.fromEntries(new FormData(form));body.driverId=driverId;body.schoolIds=schoolIds();body.studentIds=[...chosen];
  const version=revision;saving=true;save.disabled=true;
  try{await write('/api/v1/services',body);lookupCache={};if(form.isConnected){notify('سرویس و دانش‌آموزان ثبت شدند.');location.hash=initialDriverId?'drivers':'services';}}
  catch(err){if(form.isConnected&&version===revision){notify(err.message,true);save.disabled=false;}}
  finally{saving=false;}
 };
}
