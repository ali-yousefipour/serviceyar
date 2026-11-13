/* ServiceYar public experience: accessible, motion-aware, no third-party JS. */
(() => {
 'use strict';
 const root=document.getElementById('publicSite');
 const login=document.getElementById('login');
 const pages=new Set(['home','features','about','contact','faq']);
 const titles={home:'سرویس‌یار | سامانه مدیریت سرویس مدارس',features:'امکانات سامانه | سرویس‌یار',about:'درباره ما | سرویس‌یار',contact:'ارتباط با ما | سرویس‌یار',faq:'پرسش‌های متداول | سرویس‌یار'};
 const reduced=window.matchMedia?.('(prefers-reduced-motion: reduce)');
 let hydrated=false, pendingMount=null, statsLoaded=false, observer=null, scrollScheduled=false;
 let activePage='', pageGeneration=0, statistics=null, countersPlayed=false;
 const requestedPage=()=>decodeURIComponent(location.hash.slice(1)||'home');
 const animate=()=>!reduced?.matches;
 function setMenu(open){
  const button=document.getElementById('syMenuButton');
  const menu=document.getElementById('syMobileMenu');
  if(!button||!menu)return;
  menu.hidden=!open;button.setAttribute('aria-expanded',String(open));
  button.setAttribute('aria-label',open?'بستن فهرست':'فهرست صفحات');
 }
 function currentNav(page){
  root.querySelectorAll('.sy-site-nav a,.sy-mobile-menu a').forEach(link=>{
   if(link.getAttribute('href')==='#'+page)link.setAttribute('aria-current','page');
   else link.removeAttribute('aria-current');
  });
 }
 function updateScroll(){
  scrollScheduled=false;
  if(root.hidden)return;
  const doc=document.documentElement;
  const max=Math.max(0,doc.scrollHeight-window.innerHeight);
  const progress=max?Math.max(0,Math.min(1,window.scrollY/max)):0;
  const el=document.querySelector('#syScrollProgress span');
  if(el)el.style.transform='scaleX('+progress.toFixed(4)+')';
  root.classList.toggle('sy-scrolled',window.scrollY>24);
  document.getElementById('syBackToTop')?.classList.toggle('sy-visible',window.scrollY>500);
  if(animate()&&activePage==='home'){
   const visual=root.querySelector('.sy-hero-visual');
   if(visual && window.scrollY<900){
    visual.style.setProperty('--sy-parallax',Math.min(45,window.scrollY*.065).toFixed(1)+'px');
   }
  }
 }
 function scheduleScroll(){if(!scrollScheduled){scrollScheduled=true;requestAnimationFrame(updateScroll);}}
 function setupReveals(){
  observer?.disconnect();
  const nodes=root.querySelectorAll('.sy-site-page:not([hidden]) [data-reveal],.sy-site-page:not([hidden]) [data-reveal-group] > *,.sy-site-page:not([hidden]) .sy-section-heading,.sy-site-page:not([hidden]) .sy-stat-grid > *');
  if(!animate()||!('IntersectionObserver' in window)){
   nodes.forEach(node=>node.classList.add('sy-in-view'));return;
  }
  observer=new IntersectionObserver(entries=>{
   for(const entry of entries){
    if(!entry.isIntersecting)continue;
    const el=entry.target;
    el.classList.add('sy-in-view');
    observer.unobserve(el);
   }
  },{rootMargin:'0px 0px -36px 0px',threshold:0.08});
  nodes.forEach((node,index)=>{
   // Stagger siblings rather than delaying whole sections.
   const sibling=Array.from(node.parentElement?.children||[]).indexOf(node);
   node.style.setProperty('--sy-stagger',Math.min(sibling,5)*65+'ms');
   observer.observe(node);
  });
 }
 function countTo(node,value){
  if(!animate()||value===0){node.textContent=value.toLocaleString('fa-IR');return;}
  const duration=1150,started=performance.now();
  const formatter=new Intl.NumberFormat('fa-IR');
  function frame(now){
   if(root.hidden)return;
   const t=Math.min(1,(now-started)/duration),ease=1-Math.pow(1-t,3);
   node.textContent=formatter.format(Math.round(value*ease));
   if(t<1)requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
 }
 function showStatistics(){
  if(!statistics||countersPlayed)return;
  const section=document.getElementById('syNumbers');
  if(!section||section.closest('[hidden]'))return;
  const display=()=>{
   if(countersPlayed)return;countersPlayed=true;
   root.querySelectorAll('[data-stat]').forEach(el=>{
    const value=statistics[el.dataset.stat];
    el.textContent='—';
    if(Number.isSafeInteger(value)&&value>=0)countTo(el,value);
   });
  };
  if(!animate()||!('IntersectionObserver' in window)){display();return;}
  const watch=new IntersectionObserver(entries=>{
   if(entries.some(item=>item.isIntersecting)){watch.disconnect();display();}
  },{threshold:.16});
  watch.observe(section);
 }
 async function loadStats(){
  if(statsLoaded)return;
  statsLoaded=true;
  const status=document.getElementById('syStatsStatus');
  try{
   const r=await fetch('/api/v1/public/overview',{credentials:'omit',headers:{Accept:'application/json'}});
   const body=await r.json();
   if(!r.ok||!body.ok||!body.data?.counts)throw new Error('no data');
   statistics=body.data.counts;
   if(status)status.textContent='آمار ثبت‌شده و به‌روز';
   showStatistics();
  }catch(_){
   if(status)status.textContent='آمار موقتاً در دسترس نیست';
  }
 }

 let schoolMap=null,publicCompanyMarkers=null,schoolMarkers=null,publicSchoolList=[],publicCompanies=[],selectedSchool=null,schoolSearchTimer=null,schoolSearchSequence=0;
 const faNumber=value=>new Intl.NumberFormat('fa-IR').format(value);
 const DEFAULT_SCHOOL_IMAGE='/assets/school-placeholder.png';
 const DEFAULT_COMPANY_IMAGE='/assets/company-placeholder.png';
 const validPoint=row=>row&&Number.isFinite(Number(row.latitude))&&Number.isFinite(Number(row.longitude))&&row.latitude!==null&&row.longitude!==null;
 function publicMapStatus(message){
  const el=document.getElementById('syPublicMapStatus');if(el)el.textContent=message;
 }
 function publicModal(title,items,imageUrl,fallbackUrl=DEFAULT_COMPANY_IMAGE){
  const modal=document.getElementById('syPublicSchoolDialog');
  if(!modal)return;
  const heading=document.getElementById('sySchoolDialogTitle');
  const details=document.getElementById('syPublicDetails');
  heading.textContent=title;details.replaceChildren();
  const visual=document.getElementById('syPublicModalVisual');visual.replaceChildren();
  const img=document.createElement('img');
  img.src=imageUrl||fallbackUrl;img.alt=title;img.loading='lazy';
  img.addEventListener('error',()=>{
   if(img.src.endsWith(fallbackUrl)){img.remove();visual.textContent='تصویر در دسترس نیست';}
   else img.src=fallbackUrl;
  });
  visual.append(img);
  for(const [key,value] of items){
   if(value===null||value===undefined||String(value).trim()==='')continue;
   const row=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');
   dt.textContent=key;dd.textContent=String(value);row.append(dt,dd);details.append(row);
  }
  if(!modal.open)modal.showModal();
 }
 function showCompany(company){
  publicModal(company.name,[
   ['نام شرکت',company.name],['مدیر شرکت',company.managerName],
   ['نشانی دفتر',company.address],['تلفن دفتر',company.officePhone],
   ['شماره همراه مدیر (با مجوز انتشار)',company.managerMobile]
  ],company.logoUrl,DEFAULT_COMPANY_IMAGE);
 }
 function showSchool(school){
  publicModal(school.name,[
   ['نام مدرسه',school.name],['ناحیه آموزش‌وپرورش',school.region],['نوع مدرسه',school.type],
   ['نشانی مدرسه',school.address],['تلفن مدرسه',school.phone],['مدیر مدرسه',school.managerName],
   ['شرکت سرویس‌دهنده',school.company||'هنوز شرکتی مشخص نشده است']
  ],school.photoUrl,DEFAULT_SCHOOL_IMAGE);
 }
 function publicPin(type,photo){
  const color=type==='agency'?'green':'yellow';
  const imageUrl=photo||(type==='agency'?DEFAULT_COMPANY_IMAGE:DEFAULT_SCHOOL_IMAGE);
  const content='<img alt="" src="'+imageUrl.replace(/&/g,'&amp;').replace(/"/g,'&quot;')+'">';
  return L.divIcon({className:'sy-public-pin sy-public-pin-'+color,html:'<span class="sy-public-pin-face '+(!photo?'sy-public-pin-fallback':'')+'">'+content+'<span class="sy-public-pin-symbol">⌖</span></span>',
   iconSize:[54,58],iconAnchor:[27,56],popupAnchor:[0,-50]});
 }
 function attachPinFallback(marker){
  const icon=marker.getElement();
  const img=icon?.querySelector('img');
  if(!img)return;
  const fallback=()=>{img.remove();icon.querySelector('.sy-public-pin-face')?.classList.add('sy-public-pin-fallback');};
  if(img.complete&&img.naturalWidth===0)fallback();
  else img.addEventListener('error',fallback,{once:true});
 }
 function focusPoints(points){
  if(!schoolMap||!points.length)return;
  if(points.length===1)schoolMap.setView(points[0],14);
  else schoolMap.fitBounds(L.latLngBounds(points).pad(.22),{maxZoom:14});
 }
 function renderCompanyDirectory(){
  const root=document.getElementById('syCompanyDirectoryItems');
  const counter=document.getElementById('syCompanyDirectoryCount');
  if(!root)return;
  root.replaceChildren();
  const located=publicCompanies.filter(validPoint).length;
  if(counter)counter.textContent='('+faNumber(located)+' موقعیت از '+faNumber(publicCompanies.length)+' شرکت)';
  for(const company of publicCompanies){
   const item=document.createElement('button');
   item.type='button';item.className='sy-company-directory-row';
   item.setAttribute('role','listitem');
   const name=document.createElement('strong');name.textContent=company.name;
   const state=document.createElement('span');
   const hasLocation=validPoint(company);
   state.textContent=hasLocation?'نمایش موقعیت':'موقعیت ثبت نشده';
   state.className=hasLocation?'sy-company-located':'sy-company-not-located';
   item.append(name,state);
   item.addEventListener('click',()=>{
    showCompany(company);
    if(hasLocation&&schoolMap)schoolMap.setView([Number(company.latitude),Number(company.longitude)],15);
   });
   root.append(item);
  }
 }
 function drawCompanies(){
  if(!publicCompanyMarkers||!schoolMap)return;
  schoolMarkers?.clearLayers();publicCompanyMarkers.clearLayers();selectedSchool=null;
  const points=[];
  publicCompanies.forEach(company=>{
   if(!validPoint(company))return;
   const position=[Number(company.latitude),Number(company.longitude)];
   const marker=L.marker(position,{icon:publicPin('agency',company.logoUrl),title:company.name});
   marker.on('click',()=>showCompany(company));marker.addTo(publicCompanyMarkers);attachPinFallback(marker);points.push(position);
  });
  if(points.length)focusPoints(points);
  publicMapStatus(points.length?faNumber(points.length)+' شرکت دارای مختصات معتبر از '+faNumber(publicCompanies.length)+' شرکت فعال نمایش داده می‌شوند.':'هنوز مختصات معتبری برای شرکت‌ها ثبت نشده است.');
 }
 function clearMapForSearch(){
  publicCompanyMarkers?.clearLayers();schoolMarkers?.clearLayers();
  publicMapStatus('یک مدرسه را از نتایج جستجو انتخاب کنید.');
 }
 async function selectPublicSchool(school){
  selectedSchool=school;
  root.querySelectorAll('[data-school-id]').forEach(el=>el.classList.toggle('sy-selected-school',String(school.id)===el.dataset.schoolId));
  publicCompanyMarkers?.clearLayers();schoolMarkers?.clearLayers();
  const points=[];
  if(validPoint(school)&&schoolMap){
   const p=[Number(school.latitude),Number(school.longitude)];
   const schoolMarker=L.marker(p,{icon:publicPin('school',school.photoUrl),title:school.name}).on('click',()=>showSchool(school)).addTo(schoolMarkers);attachPinFallback(schoolMarker);
   points.push(p);
  }
  let company=publicCompanies.find(c=>Number(c.id)===Number(school.companyId));
  if(!company&&school.companyId){
   try{
    const res=await fetch('/api/v1/public/company?id='+encodeURIComponent(school.companyId),{credentials:'omit'});
    const json=await res.json();
    if(res.ok&&json.ok)company=json.data;
   }catch(_){}
  }
  if(selectedSchool!==school)return;
  if(company&&validPoint(company)){
   const p=[Number(company.latitude),Number(company.longitude)];
   const companyMarker=L.marker(p,{icon:publicPin('agency',company.logoUrl),title:company.name}).on('click',()=>showCompany(company)).addTo(schoolMarkers);attachPinFallback(companyMarker);
   points.push(p);
  }
  focusPoints(points);
  publicMapStatus(company?'مدرسه و شرکت سرویس‌دهنده انتخاب‌شده':'مدرسه انتخاب شد؛ برای شرکت سرویس‌دهنده موقعیت ثبت‌شده‌ای یافت نشد.');
 }
 function drawPublicSchools(rows){
  const results=document.getElementById('sySchoolResults');
  if(!results)return;
  results.replaceChildren();
  if(!rows.length){results.textContent='مدرسه‌ای با این مشخصات یافت نشد.';return;}
  rows.forEach(school=>{
   const button=document.createElement('button');button.type='button';button.className='sy-school-choice';button.dataset.schoolId=school.id;
   const content=document.createElement('span');content.className='sy-school-choice-body';
   const name=document.createElement('strong');name.textContent=school.name;
   const provider=document.createElement('small');provider.textContent=school.company||'بدون شرکت مشخص';
   const region=document.createElement('span');region.textContent=school.region||'ناحیه نامشخص';
   content.append(name,provider,region);
   const visual=document.createElement('span');visual.className='sy-school-choice-visual';
   const img=document.createElement('img');img.src=school.photoUrl||DEFAULT_SCHOOL_IMAGE;img.alt='';img.loading='lazy';
   img.onerror=()=>{if(!img.src.endsWith(DEFAULT_SCHOOL_IMAGE))img.src=DEFAULT_SCHOOL_IMAGE;else {img.remove();visual.textContent='⌖';}};
   visual.append(img);
   button.append(visual,content);
   button.addEventListener('click',()=>selectPublicSchool(school));results.append(button);
  });
 }
 async function searchPublicSchools(term=''){
  const result=document.getElementById('sySchoolResults');if(!result)return;
  const version=++schoolSearchSequence;
  if(!term){result.textContent='برای مشاهده مدارس نام مدرسه یا شرکت را جستجو کنید.';drawCompanies();return;}
  clearMapForSearch();
  if([...term].length<3){result.textContent='برای جستجو حداقل سه حرف وارد کنید.';return;}
  result.textContent='در حال یافتن مدارس…';
  try{
   const r=await fetch('/api/v1/public/schools?q='+encodeURIComponent(term),{credentials:'omit'});
   const json=await r.json();
   if(version!==schoolSearchSequence)return;
   if(!r.ok||!json.ok)throw new Error(json.error?.message||'خطا در دریافت مدارس');
   publicSchoolList=json.data.schools||[];
   drawPublicSchools(publicSchoolList);
   publicMapStatus('از فهرست مدرسه‌ای را انتخاب کنید تا موقعیت مدرسه و شرکت آن نمایش داده شود.');
  }catch(error){if(version===schoolSearchSequence)result.textContent=error.message||'دریافت اطلاعات ناموفق بود.';}
 }
 async function initNeshanMap(mapNode){
  const response=await fetch('/api/v1/public/map-config',{credentials:'omit'});
  const result=await response.json();
  if(!response.ok||!result.ok||!result.data?.webKey)throw new Error('کلید نمایش نقشه نشان در تنظیمات ادمین کل ثبت نشده یا معتبر نیست.');
  // Use the configured Neshan WEB key; the private routing/service key never reaches browsers.
  if(!window.__syPublicNeshanPromise){
   window.__syPublicNeshanPromise=new Promise((resolve,reject)=>{
    const stylesheet=document.createElement('link');stylesheet.rel='stylesheet';
    stylesheet.href='https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.css';
    document.head.append(stylesheet);
    const script=document.createElement('script');script.src='https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.js';
    script.onload=resolve;script.onerror=()=>reject(new Error('کتابخانه نقشه نشان بارگذاری نشد.'));
    document.head.append(script);
   });
  }
  await window.__syPublicNeshanPromise;
  schoolMap=new L.Map(mapNode,{key:result.data.webKey,maptype:'dreamy',center:[36.3,59.6],zoom:12,scrollWheelZoom:false});
  publicCompanyMarkers=L.layerGroup().addTo(schoolMap);schoolMarkers=L.layerGroup().addTo(schoolMap);
  requestAnimationFrame(()=>schoolMap?.invalidateSize(true));
 }
 async function setupPublicSchools(){
  const mapNode=document.getElementById('sySchoolsMap');
  if(!mapNode||mapNode.dataset.initialized)return;
  mapNode.dataset.initialized='1';
  const status=document.getElementById('sySchoolResults');
  const search=document.getElementById('sySchoolSearch');
  search?.addEventListener('input',()=>{
   clearTimeout(schoolSearchTimer);
   schoolSearchTimer=setTimeout(()=>searchPublicSchools(search.value.trim()),300);
  });
  document.getElementById('syPublicSchoolDialog')?.querySelector('.sy-school-dialog-close')?.addEventListener('click',()=>document.getElementById('syPublicSchoolDialog').close());
  document.getElementById('syPublicSchoolDialog')?.addEventListener('click',event=>{
   if(event.target===event.currentTarget)event.currentTarget.close();
  });
  try{
   await initNeshanMap(mapNode);
   const companiesResponse=await fetch('/api/v1/public/companies',{credentials:'omit'});
   const companiesBody=await companiesResponse.json();
   if(!companiesResponse.ok||!companiesBody.ok)throw new Error(companiesBody.error?.message||'فهرست شرکت‌ها در دسترس نیست');
   publicCompanies=companiesBody.data.companies||[];
   renderCompanyDirectory();
   if(search?.value.trim())await searchPublicSchools(search.value.trim());else drawCompanies();
   if(status&&!search?.value.trim())status.textContent='شرکت‌ها روی نقشه مشخص‌اند؛ با جستجوی مدرسه می‌توانید موقعیت مدرسه و شرکت سرویس‌دهنده آن را ببینید.';
  }catch(error){
   publicMapStatus(error.message||'نمایش نقشه در دسترس نیست.');
   if(mapNode&&!schoolMap)mapNode.textContent=error.message||'نقشه نشان در دسترس نیست.';
  }
  document.getElementById('syServiceQuote')?.addEventListener('submit',async event=>{
   event.preventDefault();
   const form=event.currentTarget,status=document.getElementById('syQuoteResult');
   const district=form.elements.district.value;
   const distance=form.elements.distanceKm.value.replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
   const vehicleClass=form.elements.vehicleClass.value;
   const button=form.querySelector('button[type=submit]');
   if(!district||!vehicleClass||!Number.isFinite(Number(distance))||Number(distance)<=0){
    status.textContent='ناحیه، نوع خودرو و مسافت معتبر را وارد کنید.';return;
   }
   status.textContent='در حال محاسبه تعرفه مصوب…';
   if(button)button.disabled=true;
   try{
    const params=new URLSearchParams({district,vehicleClass,distanceKm:distance});
    const response=await fetch('/api/v1/public/service-quote?'+params,{credentials:'omit'});
    const body=await response.json();
    if(!response.ok||!body.ok)throw Error(body.error?.message||'محاسبه انجام نشد');
    status.replaceChildren();
    const amount=document.createElement('strong');amount.textContent=faNumber(body.data.monthlyFee)+' ریال';
    const detail=document.createElement('span');
    detail.textContent='مبلغ ماهانه · مسافت مبنا: '+faNumber(body.data.distanceAppliedKm)+' کیلومتر · ضریب ترافیک: '+faNumber(body.data.trafficPercent)+'٪';
    status.append(amount,detail);status.classList.add('sy-quote-success');
   }catch(error){status.classList.remove('sy-quote-success');status.textContent=error.message||'خطا در محاسبه مبلغ';}
   finally{if(button)button.disabled=false;}
  });
  await searchPublicSchools();
 }
 function goTop(smooth=false){
  if(window.scrollY>0)window.scrollTo({top:0,behavior:smooth&&animate()?'smooth':'instant'});
 }
 function renderPage({initial=false}={}){
  if(!hydrated||root.hidden)return;
  const page=requestedPage();
  if(page==='login'){root.hidden=true;login.hidden=false;document.getElementById('username')?.focus();return;}
  const selected=pages.has(page)?page:'home';
  if(selected===activePage&&!initial){currentNav(selected);return;}
  const before=activePage;
  activePage=selected;const generation=++pageGeneration;
  currentNav(selected);setMenu(false);
  root.querySelectorAll('[data-public-page]').forEach(node=>{
   const match=node.dataset.publicPage===selected;
   node.hidden=!match;
   node.classList.remove('sy-page-enter');
   if(match)node.classList.add('sy-page-enter');
  });
  document.title=titles[selected];
  if(before!==selected){goTop(false);root.querySelectorAll('.sy-hero-visual').forEach(v=>v.style.setProperty('--sy-parallax','0px'));}
  if(!initial&&before!==selected){
   const main=document.getElementById('publicMain');
   main?.focus({preventScroll:true});
  }
  requestAnimationFrame(()=>{
   if(generation!==pageGeneration)return;
   setupReveals();showStatistics();updateScroll();if(selected==='home')schoolMap?.invalidateSize(true);
  });
 }
 async function mount(){
  if(hydrated)return;
  if(pendingMount)return pendingMount;
  pendingMount=(async()=>{
   const r=await fetch('/assets/public-site-content.html',{credentials:'omit',cache:'no-cache'});
   if(!r.ok)throw new Error('Public content unavailable');
   root.innerHTML=await r.text();
   hydrated=true;root.classList.add('sy-motion-ready');
   document.getElementById('syMenuButton')?.addEventListener('click',()=>{
    const button=document.getElementById('syMenuButton');
    setMenu(button?.getAttribute('aria-expanded')!=='true');
   });
   document.getElementById('syBackToTop')?.addEventListener('click',()=>goTop(true));
   root.querySelectorAll('.sy-mobile-menu a').forEach(el=>el.addEventListener('click',()=>setMenu(false)));
   document.addEventListener('keydown',event=>{if(event.key==='Escape')setMenu(false);});
   document.addEventListener('pointerdown',event=>{
    if(!event.target.closest('.sy-public-header'))setMenu(false);
   },{passive:true});
   root.querySelectorAll('.sy-faq details').forEach(details=>details.addEventListener('toggle',()=>{
    if(!details.open)return;
    root.querySelectorAll('.sy-faq details').forEach(other=>{if(other!==details)other.open=false;});
   }));
   // Scroll targets remain in the current page; route links switch pages.
   root.querySelectorAll('[data-scroll-target]').forEach(link=>link.addEventListener('click',event=>{
    const target=document.getElementById(link.dataset.scrollTarget);
    if(!target||target.closest('[hidden]'))return;
    event.preventDefault();
    target.scrollIntoView({behavior:animate()?'smooth':'instant',block:'start'});
    setMenu(false);
   }));
   // Public-page only deterrents; browsers/OS can still capture visible content.
   document.addEventListener('contextmenu',event=>{
    if(!root.hidden&&root.contains(event.target))event.preventDefault();
   });
   document.addEventListener('keydown',event=>{
    if(root.hidden||requestedPage()==='login')return;
    const key=event.key.toLowerCase();
    if((event.ctrlKey||event.metaKey)&&['s','p','u'].includes(key)){event.preventDefault();event.stopPropagation();}
   },true);
   addEventListener('beforeprint',()=>{if(!root.hidden)root.classList.add('sy-print-blocked');});
   addEventListener('afterprint',()=>root.classList.remove('sy-print-blocked'));
   addEventListener('scroll',scheduleScroll,{passive:true});
   addEventListener('resize',scheduleScroll,{passive:true});
   reduced?.addEventListener?.('change',()=>{setupReveals();updateScroll();});
  })();
  try{await pendingMount;}finally{pendingMount=null;}
 }
 async function show(){
  try{
   await mount();
   if(requestedPage()==='login'){
    root.hidden=true;login.hidden=false;return;
   }
   login.hidden=true;root.hidden=false;
   renderPage({initial:!activePage});
   void loadStats();
   void setupPublicSchools();
  }catch(_){
   // Preserve authentication access on static asset or API failure.
   root.hidden=true;login.hidden=false;
  }
 }
 function hide(){
  root.hidden=true;login.hidden=true;observer?.disconnect();setMenu(false);
 }
 addEventListener('hashchange',()=>{
  if(window.serviceyarSessionActive)return;
  if(requestedPage()==='login'){
   root.hidden=true;login.hidden=false;setMenu(false);document.getElementById('username')?.focus();
  }else void show();
 });
 window.serviceyarPublic={show,hide};
})();
