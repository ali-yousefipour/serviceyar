/* Public marketing pages are deliberately separate from authenticated app UI. */
(() => {
 const root=document.getElementById('publicSite');
 const login=document.getElementById('login');
 const pages=new Set(['home','features','about','contact','faq']);
 let hydrated=false, statsLoaded=false;
 const requestedPage=()=>decodeURIComponent(location.hash.slice(1)||'home');
 async function mount(){
  if(hydrated)return;
  const response=await fetch('/assets/public-site-content.html',{credentials:'omit'});
  if(!response.ok)throw new Error('صفحه معرفی در دسترس نیست.');
  root.innerHTML=await response.text();
  hydrated=true;
  const menuButton=document.getElementById('syMenuButton'),menu=document.getElementById('syMobileMenu');
  menuButton?.addEventListener('click',()=>{const open=menu.hasAttribute('hidden');menu.hidden=!open;menuButton.setAttribute('aria-expanded',String(open));});
  menu?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{menu.hidden=true;menuButton.setAttribute('aria-expanded','false');}));
 }
 function renderPage(){
  if(!hydrated||root.hidden)return;
  const page=requestedPage();
  if(page==='login'){root.hidden=true;login.hidden=false;document.getElementById('username')?.focus();return;}
  const active=pages.has(page)?page:'home';
  root.querySelectorAll('[data-public-page]').forEach(node=>node.hidden=node.dataset.publicPage!==active);
  root.querySelectorAll('.sy-site-nav a,.sy-mobile-menu a').forEach(link=>{
   const selected=link.getAttribute('href')==='#'+active;
   if(selected)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');
  });
  document.title=(active==='home'?'سرویس‌یار | سامانه مدیریت سرویس مدارس':({
   features:'امکانات سامانه',about:'درباره ما',contact:'ارتباط با ما',faq:'پرسش‌های متداول'
  }[active]+' | سرویس‌یار'));
  if(active!=='home')window.scrollTo?.(0,0);
 }
 async function loadStats(){
  if(statsLoaded)return;statsLoaded=true;
  const status=document.getElementById('syStatsStatus');
  try{
   const response=await fetch('/api/v1/public/overview',{credentials:'omit',headers:{Accept:'application/json'}});
   const body=await response.json();
   if(!response.ok||body.ok!==true||!body.data?.counts)throw new Error('stats unavailable');
   const counts=body.data.counts;
   root.querySelectorAll('[data-stat]').forEach(element=>{
    const n=counts[element.dataset.stat];
    element.textContent=Number.isSafeInteger(n)&&n>=0?n.toLocaleString('fa-IR'):'—';
   });
   if(status)status.textContent='آمار به‌روز سامانه';
  }catch(e){if(status)status.textContent='آمار موقتاً در دسترس نیست';}
 }
 async function show(){
  try{
   await mount();
   if(requestedPage()==='login'){
    root.hidden=true;login.hidden=false;return;
   }
   login.hidden=true;root.hidden=false;
   renderPage();void loadStats();
  }catch(e){
   // Keep login reachable if the public static content cannot be loaded.
   root.hidden=true;login.hidden=false;
  }
 }
 function hide(){root.hidden=true;login.hidden=true;}
 addEventListener('hashchange',()=>{
  if(window.serviceyarSessionActive)return;
  if(requestedPage()==='login'){root.hidden=true;login.hidden=false;document.getElementById('username')?.focus();}
  else {void show();}
 });
 window.serviceyarPublic={show,hide};
})();