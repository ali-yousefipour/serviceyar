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
 let schoolMap=null,schoolMarkers=null,publicSchoolList=[],selectedSchool=null,schoolSearchTimer=null;
 const faNumber=value=>new Intl.NumberFormat('fa-IR').format(value);
 function selectPublicSchool(school){
  selectedSchool=school;
  if(schoolMap&&Number.isFinite(school.latitude)&&Number.isFinite(school.longitude))schoolMap.setView([school.latitude,school.longitude],15);
  root.querySelectorAll('[data-school-id]').forEach(el=>el.classList.toggle('sy-selected-school',String(school.id)===el.dataset.schoolId));
 }
 function drawPublicSchools(rows){
  const result=document.getElementById('sySchoolResults');
  if(!result)return;
  result.replaceChildren();
  if(!rows.length){result.textContent='مدرسه‌ای با این مشخصات یافت نشد.';return;}
  rows.forEach(school=>{
   const button=document.createElement('button');
   button.type='button';button.className='sy-school-choice';button.dataset.schoolId=school.id;
   const title=document.createElement('strong');title.textContent=school.name;
   const company=document.createElement('small');company.textContent='شرکت سرویس‌دهنده: '+school.company;
   const region=document.createElement('span');region.textContent=school.region||'ناحیه ثبت نشده';
   if(school.photoUrl){
    const img=document.createElement('img');img.className='sy-school-thumbnail';img.src=school.photoUrl;
    img.alt='';img.loading='lazy';img.onerror=()=>img.remove();button.append(img);
   }
   button.append(title,company,region);button.addEventListener('click',()=>selectPublicSchool(school));
   result.append(button);
  });
  if(!schoolMap||!window.L)return;
  schoolMarkers?.clearLayers();
  const positions=[];
  rows.forEach(school=>{
   if(!Number.isFinite(school.latitude)||!Number.isFinite(school.longitude))return;
   const marker=L.marker([school.latitude,school.longitude]).addTo(schoolMarkers);
   const popup=document.createElement('div');
   const name=document.createElement('strong');name.textContent=school.name;
   const company=document.createElement('p');company.textContent='شرکت: '+school.company;
   popup.append(name,company);
   if(school.photoUrl){
    const img=document.createElement('img');img.src=school.photoUrl;img.alt='تصویر '+school.name;
    img.loading='lazy';img.style.cssText='max-width:190px;max-height:130px;object-fit:cover;display:block;margin:8px auto;border-radius:6px';
    img.onerror=()=>img.remove();popup.append(img);
   }
   const action=document.createElement('button');action.type='button';action.textContent='انتخاب برای محاسبه';
   action.addEventListener('click',()=>selectPublicSchool(school));popup.append(action);
   marker.bindPopup(popup);marker.on('click',()=>selectPublicSchool(school));positions.push([school.latitude,school.longitude]);
  });
  if(positions.length)schoolMap.fitBounds(L.latLngBounds(positions).pad(.12),{maxZoom:13});
 }
 async function searchPublicSchools(term=''){
  const status=document.getElementById('sySchoolResults');if(!status)return;
  status.textContent='در حال جستجو…';
  try{
   const response=await fetch('/api/v1/public/schools?q='+encodeURIComponent(term),{credentials:'omit'});
   const body=await response.json();
   if(!response.ok||!body.ok)throw Error(body.error?.message||'خطا در دریافت اطلاعات');
   publicSchoolList=body.data.schools||[];
   if(!term){status.textContent='برای مشاهده عکس و موقعیت مدارس، حداقل سه حرف از نام مدرسه یا شرکت را وارد کنید.';return;}
   drawPublicSchools(publicSchoolList);
  }catch(error){status.textContent=error.message||'خطا در دریافت مدارس';}
 }
 async function setupPublicSchools(){
  const mapNode=document.getElementById('sySchoolsMap');
  if(!mapNode||mapNode.dataset.initialized)return;
  mapNode.dataset.initialized='1';
  if(window.L){
   schoolMap=L.map(mapNode,{scrollWheelZoom:false}).setView([36.3,59.6],11);
   // Prefer a light, readable basemap. Fail over once if tile requests are blocked
   // by a hosting/network policy; district outlines alone are not a basemap.
   const base=L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',{
    subdomains:'abcd',maxZoom:19,attribution:'© OpenStreetMap contributors · © CARTO'
   }).addTo(schoolMap);
   let fallbackUsed=false;
   base.on('tileerror',()=>{
    if(fallbackUsed||!schoolMap)return;
    fallbackUsed=true;schoolMap.removeLayer(base);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
     maxZoom:19,attribution:'© OpenStreetMap contributors'
    }).addTo(schoolMap);
   });
   schoolMarkers=L.layerGroup().addTo(schoolMap);
   fetch('/assets/school-district-boundaries.geojson',{credentials:'omit'}).then(r=>r.ok?r.json():null).then(geo=>{
    if(!geo||!schoolMap)return;
    const layer=L.geoJSON(geo,{style:()=>({color:'#0c7662',weight:2,fillOpacity:.06}),onEachFeature:(feature,shape)=>shape.bindTooltip(feature.properties.name)});
    L.control.layers(null,{'مرزبندی نواحی آموزش‌وپرورش':layer},{collapsed:true}).addTo(schoolMap);
    layer.addTo(schoolMap);
   }).catch(()=>{});
   // Leaflet must remeasure after the public page becomes visible.
   requestAnimationFrame(()=>schoolMap?.invalidateSize(true));
  }else mapNode.textContent='نمایش نقشه در دسترس نیست.';
  const search=document.getElementById('sySchoolSearch');
  search?.addEventListener('input',()=>{
   clearTimeout(schoolSearchTimer);
   schoolSearchTimer=setTimeout(()=>searchPublicSchools(search.value.trim()),350);
  });
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
