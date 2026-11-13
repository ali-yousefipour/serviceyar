const $=id=>document.getElementById(id);
let session=null, agencies=[], currentAgency=null;
const resources={
 schools:['مدارس',['name','code','schoolType','managerName','phone','region']],
 students:['دانش‌آموزان',['firstName','lastName','nationalCode','grade','mobile','schoolId']],
 drivers:['رانندگان',['firstName','lastName','nationalCode','mobile','status','rating','vehicleId']],
 vehicles:['خودروها',['plate','ownerName','model','capacity']],
 services:['سرویس‌ها',['title','code','schoolId','driverId','capacity','status']],
 packs:['بسته‌ها',['title','status','capacity']], contracts:['قراردادها',['title','contractNo','partyType','amount','status']],
 'driver-work':['کارکرد رانندگان',['driverId','workDate','serviceId','amount','status']],
 'driver-payments':['پرداخت رانندگان',['driverId','paymentDate','amount','referenceNo']],
 'school-charges':['بدهی مدارس',['schoolId','title','amount','dueDate','status']],
 'service-charges':['بدهی سرویس‌ها',['serviceId','title','amount','dueDate','status']],
 invoices:['فاکتورها',['title','partyType','amount','status','issueDate','dueDate']],
 payments:['پرداخت‌ها',['invoiceId','amount','paymentDate','method','referenceNo']],
 accounts:['حساب‌ها',['code','title','parentId','isActive']],
 'accounting-documents':['اسناد حسابداری',['documentNo','documentDate','description','status','totalAmount']],
 wallets:['کیف پول',['ownerType','ownerId','balance']],
 messages:['پیام‌ها',['channel','recipientType','subject','body','status']],
 'driver-changes':['تغییر راننده',['serviceId','oldDriverId','newDriverId','status','reason']]
};
const nav=[
 ['dashboard','داشبورد'],['schools','مدارس'],['students','دانش‌آموزان'],['drivers','رانندگان'],['vehicles','خودروها'],['services','سرویس‌ها'],
 ['packs','بسته‌ها'],['contracts','قراردادها'],['driver-work','کارکرد'],['driver-payments','پرداخت رانندگان'],['school-charges','بدهی مدارس'],
 ['service-charges','بدهی سرویس‌ها'],['invoices','فاکتورها'],['payments','پرداخت‌ها'],['accounts','حسابداری'],['messages','پیام‌ها'],
 ['driver-changes','تغییر راننده'],['reports','گزارش‌ها'],['settings','تنظیمات'],['companies','تنظیمات سامانه']
];
const titles=Object.fromEntries(nav);titles.admin='تنظیمات سامانه';
async function api(url,options={}){const headers={Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})};const r=await fetch(url,{...options,credentials:'same-origin',headers});const d=await r.json().catch(()=>({}));if(!r.ok||!d.ok){const message=d?.error?.message||(r.status===401?'نشست معتبر نیست یا ورود انجام نشده است.':r.status===419?'توکن امنیتی منقضی شده است؛ صفحه را تازه‌سازی و دوباره وارد شوید.':r.status>=500?'خطای سرور یا پایگاه داده؛ وضعیت مهاجرت‌های دیتابیس بررسی شود.':'پاسخ نامعتبر از سرور');throw new Error(message)}return d.data}
function val(row,f){if(row[f]!==undefined)return row[f];const snake=f.replace(/[A-Z]/g,m=>'_'+m.toLowerCase());return row[snake]??''}
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
const iconPaths={dashboard:'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="13" width="7" height="8" rx="1.5"/>',schools:'<path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/>',students:'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v2"/>',drivers:'<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',vehicles:'<path d="m5 11 2-5h10l2 5M3 11h18v7H3zM6 18v2m12-2v2"/><circle cx="7" cy="15" r="1"/><circle cx="17" cy="15" r="1"/>',services:'<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M7 6V4h10v2M7 19v2m10-2v2M3 12h18"/>',packs:'<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5M12 13v8"/>',contracts:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>','driver-work':'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','driver-payments':'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>','school-charges':'<path d="M12 3v18M17 7H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>','service-charges':'<path d="M12 3v18M17 7H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',invoices:'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2Z"/><path d="M9 8h6M9 12h6"/>',payments:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M8 15h4"/>',accounts:'<path d="M3 7h18M5 3h14v18H5zM8 11h8M8 15h5"/>',messages:'<path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.6L3 21l1.9-5.1A8.5 8.5 0 1 1 21 11.5Z"/>','driver-changes':'<path d="M4 7h13l-3-3M20 17H7l3 3M17 4l3 3-3 3M7 14l-3 3 3 3"/>',reports:'<path d="M4 19V5M4 19h17"/><path d="m7 15 4-4 3 2 5-6"/>',settings:'<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.6.9L16 20.5h-2.8l-.3-1.8a8 8 0 0 1-1.8-.8l-1.6.7-1.4-2.4 1.4-1.2a7 7 0 0 1 0-1.9l-1.4-1.2 1.4-2.4 1.7.7a8 8 0 0 1 1.6-.9l.3-1.8H16l.3 1.8a8 8 0 0 1 1.8.8l1.6-.7 1.4 2.4-1.4 1.2a7 7 0 0 1-.3 2Z"/>'};
Object.assign(iconPaths,{
 dashboard:'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="11" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="18" width="7" height="3" rx="1.5"/>',
 schools:'<path d="m2 9 10-6 10 6-10 6Z"/><path d="M6 12v6c4 3 8 3 12 0v-6M22 9v8"/>',
 drivers:'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M3 12h6m6 0h6m-9 3v6"/>',
 services:'<rect x="5" y="3" width="14" height="17" rx="3"/><path d="M5 10h14M8 20v2m8-2v2M9 6h6"/><circle cx="9" cy="15" r="1"/><circle cx="15" cy="15" r="1"/>',
 company:'<path d="M5 21V7l7-4 7 4v14M3 21h18M9 8h1m4 0h1M9 12h1m4 0h1m-5 9v-5h4v5"/>',
 accounts:'<path d="M3 10h18L12 3ZM5 10v8m5-8v8m4-8v8m5-8v8M3 21h18"/>',
 reports:'<path d="M4 3v18h17M8 16v-4m5 4V8m5 8V5"/>'
});
const samarMenuPaths={
 dashboard:'M3 3h8v8H3zM13 3h8v5h-8zM3 13h8v8H3zM13 10h8v11h-8z',
 schools:'M12 3 1 9l11 6 9-4.91V17h2V9L12 3zm-7 10v4l7 4 7-4v-4l-7 4-7-4z',
 students:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-4.42 0-8 2.24-8 5v2h16v-2c0-2.76-3.58-5-8-5z',
 drivers:'M5 5h14l3 8v7h-2v-2H4v2H2v-7l3-8zm1.4 2L5 11h14l-1.4-4H6.4zM6 13a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm12 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z',
 services:'M6 7h11V4l5 4-5 4V9H6v4H4V7h2zm12 10H7v3l-5-4 5-4v3h11v-4h2v6z',
 accounts:'M4 2h16a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm1 5v2h6V7H5zm10-2-1.4 1.4L15 8l-1.4 1.6L15 11l1.5-1.6L18 11l1.4-1.4L18 8l1.4-1.6L18 5l-1.5 1.6L15 5zM7 13v2H5v2h2v2h2v-2h2v-2H9v-2H7zm7 1v2h5v-2h-5zm0 3v2h5v-2h-5z',
 reports:'M3 2h14a2 2 0 0 1 2 2v2H7v12H3a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm6 6h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2zm1 3v2h10v-2H10zm0 4v2h3v-2h-3zm5 0v2h5v-2h-5z'
};
const samarMenuAliases={'schools/new':'schools','students/new':'students','previous-students':'students','services/new':'services','accounting-documents':'accounts','installments':'accounts','finance-report':'reports','school-report':'reports','vehicles':'drivers'};
function samarMenuIcon(key){
 const path=samarMenuPaths[samarMenuAliases[key]||key];
 return path?`<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" fill-rule="evenodd"><path d="${path}"/></svg>`:navIcon(key.split('/')[0]);
}
function navIcon(key){return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${iconPaths[key]||iconPaths.dashboard}</svg>`}
function renderAgencySelector(){const wrap=$('agencyPickerWrap'),select=$('agencySelect');if(!wrap||!select)return;const canSwitch=session?.roles?.includes('super_admin')||(agencies||[]).length>1;wrap.hidden=!canSwitch;select.innerHTML=(agencies||[]).map(a=>`<option value="${esc(a.id)}">${esc(a.name)}${Number(a.is_active)===0?' (غیرفعال)':''}</option>`).join('');if(currentAgency)select.value=String(currentAgency.id);}
function renderNav(){ renderAgencySelector(); const perms=session?.permissions||[];const access={dashboard:'dashboard.view',schools:'schools.view',students:'students.view',drivers:'drivers.view',vehicles:'services.view',services:'services.view',packs:'services.view',contracts:'finance.view','driver-work':'finance.view','driver-payments':'finance.view','school-charges':'finance.view','service-charges':'finance.view',invoices:'finance.view',payments:'finance.view',accounts:'finance.view','accounting-documents':'finance.view',wallets:'finance.view',messages:'messages.manage','driver-changes':'services.manage',reports:'reports.view',settings:'settings.view'};const visible=nav.filter(([k])=>k==='companies'?session?.roles?.includes('super_admin'):session?.roles?.includes('super_admin')||perms.includes(access[k]));$('nav').innerHTML=visible.map(([k,t])=>`<a class="nav" href="#${k}" data-page="${k}">${navIcon(k)}<span>${t}</span></a>`).join('') }
function render(){let key=location.hash.slice(1)||'dashboard';if(key==='admin')key='companies';if(!titles[key])key='dashboard';document.querySelectorAll('.nav').forEach(x=>x.classList.toggle('active',x.dataset.page===key));$('pageTitle').textContent=titles[key]; if(key==='dashboard')dashboard();else if(key==='companies')companiesPage();else if(resources[key])resourcePage(key);else if(key==='settings')settingsPage();else reportsPage()}
async function dashboard(){
 $('content').innerHTML='<div class="loading">در حال دریافت آمار و اطلاعات داشبورد...</div>';
 try{
  if(session?.roles?.includes('super_admin')){
   const d=await api('/api/v1/admin/overview'),c=d.counts||{},items=[['agencies','شرکت‌ها'],['users','کاربران'],['schools','مدارس'],['students','دانش‌آموزان'],['drivers','رانندگان'],['vehicles','خودروها'],['services','سرویس‌ها']],max=Math.max(1,...items.map(([k])=>Number(c[k]||0)));
   $('content').innerHTML='<section class="welcome"><h2>داشبورد مدیریتی سرویس‌یار</h2><p>نمای کلی آمار سامانه؛ تعریف شرکت‌ها و تخصیص کاربران در بخش «تنظیمات سامانه» انجام می‌شود.</p></section><div class="stats">'+items.map(([k,t])=>'<article><span>'+t+'</span><strong>'+Number(c[k]||0).toLocaleString('fa-IR')+'</strong><small>کل سامانه</small></article>').join('')+'</div><section class="panel"><h2>مقایسه اطلاعات ثبت‌شده</h2><div class="dashboard-bars">'+items.map(([k,t])=>'<div class="dashboard-bar-row"><div class="dashboard-bar-label"><span>'+t+'</span><b>'+Number(c[k]||0).toLocaleString('fa-IR')+'</b></div><div class="dashboard-bar-track"><span style="width:'+Math.max(Number(c[k]||0)>0?2:0,Math.round(Number(c[k]||0)/max*100))+'%"></span></div></div>').join('')+'</div></section>';
   return;
  }
  if(!currentAgency){$('content').innerHTML='<div class="error">برای این حساب، شرکت فعالی تعریف نشده است.</div>';return}
  const d=await api('/api/v1/dashboard/summary');
  $('content').innerHTML='<section class="welcome"><h2>نمای کلی شرکت '+esc(currentAgency?.name||'')+'</h2><p>آمار عملیاتی در محدوده دسترسی فعلی.</p></section><div class="stats">'+[['schools','مدارس'],['students','دانش‌آموزان'],['drivers','رانندگان'],['services','سرویس‌ها']].map(([k,t])=>'<article><span>'+t+'</span><strong>'+Number(d.counts?.[k]??0).toLocaleString('fa-IR')+'</strong><small>تعداد فعال</small></article>').join('')+'</div><section class="panel"><h2>خلاصه عملیاتی</h2><div class="rows"><div><span>شرکت فعال</span><b>'+esc(currentAgency?.name||'—')+'</b></div><div><span>وضعیت نشست</span><b class="ok">معتبر</b></div></div></section>';
 }catch(e){$('content').innerHTML='<div class="error">'+esc(e.message)+'</div>'}
}
const driverSelections=new Set();
async function resourcePage(key){
 if(!currentAgency){$('content').innerHTML='<div class="error">ابتدا شرکت فعال را انتخاب کنید.</div>';return}
 const [title]=resources[key];
 if(key==='drivers')driverSelections.clear();
 $('content').innerHTML=`<section class="toolbar"><div><h2>${title}</h2><p>فهرست، جست‌وجو و مدیریت اطلاعات شرکت.</p></div><div class="filters"><button class="primary" id="addBtn">ثبت جدید</button>${key==='drivers'?'<button id="bulkDriversBtn" type="button">عملیات گروهی</button>':''}</div></section><section class="panel"><div class="filters"><input id="search" placeholder="جست‌وجو..." autocomplete="off"><button id="searchBtn">جست‌وجو</button></div>${key==='drivers'?'<div id="driverSelectionCount" aria-live="polite"></div>':''}<div id="table"><div class="loading">در حال دریافت...</div></div></section><div id="modal"></div>`;
 $('searchBtn').onkeydown=e=>{if(e.key==='Enter')loadResource(key)};
 $('searchBtn').onclick=()=>loadResource(key);
 $('addBtn').onclick=()=>formResource(key,null);
 if(key==='drivers')$('bulkDriversBtn').onclick=bulkDriverDialog;
 await loadResource(key);
}
function driverSelectionLabel(){
 const el=$('driverSelectionCount');
 if(el)el.textContent=driverSelections.size.toLocaleString('fa-IR')+' راننده انتخاب شده (انتخاب‌ها هنگام جست‌وجو و تغییر صفحه حفظ می‌شوند)';
}
function bulkDriverDialog(){
 if(!driverSelections.size){alert('ابتدا رانندگان موردنظر را انتخاب کنید.');return}
 $('modal').innerHTML=`<div class="modal-backdrop"><div class="modal-card" role="dialog" aria-modal="true" aria-label="عملیات گروهی رانندگان"><h2>عملیات گروهی رانندگان</h2><p>${driverSelections.size.toLocaleString('fa-IR')} راننده انتخاب شده است.</p><div class="filters"><button type="button" id="bulkEnable" class="primary">فعال‌سازی گروهی</button><button type="button" id="bulkDisable" class="danger">غیرفعال‌سازی گروهی</button><button type="button" id="bulkClose">انصراف</button></div></div></div>`;
 $('bulkClose').onclick=()=>$('modal').innerHTML='';
 $('bulkEnable').onclick=()=>applyDriverBulk(true);
 $('bulkDisable').onclick=()=>applyDriverBulk(false);
}
async function applyDriverBulk(active){
 const ids=[...driverSelections];
 if(!ids.length)return;
 if(!confirm(`آیا از ${active?'فعال‌سازی':'غیرفعال‌سازی'} ${ids.length.toLocaleString('fa-IR')} راننده اطمینان دارید؟`))return;
 const enable=$('bulkEnable'),disable=$('bulkDisable');enable.disabled=true;disable.disabled=true;
 try{
  const result=await api('/api/v1/drivers/bulk-status?agencyId='+currentAgency.id,{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({ids,active})});
  driverSelections.clear();$('modal').innerHTML='';
  await loadResource('drivers');
  alert(`عملیات انجام شد. ${Number(result.changed).toLocaleString('fa-IR')} راننده تغییر وضعیت یافت.`);
 }catch(e){alert(e.message);enable.disabled=false;disable.disabled=false}
}
async function loadResource(key){
 try{
  const d=await api('/api/v1/'+key+'?agencyId='+currentAgency.id+'&search='+encodeURIComponent($('search')?.value||''));
  const [,fields]=resources[key],drivers=key==='drivers';
  $('table').innerHTML=d.items.length?`<div class="table-wrap"><table><thead><tr>${drivers?'<th><input type="checkbox" id="driverSelectAll" aria-label="انتخاب همه رانندگان این صفحه"></th>':''}<th>#</th>${fields.map(f=>`<th>${esc(f)}</th>`).join('')}${drivers?'<th>وضعیت</th>':''}<th>عملیات</th></tr></thead><tbody>${d.items.map(row=>`<tr>${drivers?`<td><input type="checkbox" class="driver-select" data-id="${Number(row.id)}" aria-label="انتخاب راننده ${esc(val(row,'firstName'))} ${esc(val(row,'lastName'))}" ${driverSelections.has(Number(row.id))?'checked':''}></td>`:''}<td>${row.id}</td>${fields.map(f=>`<td>${esc(val(row,f))}</td>`).join('')}${drivers?`<td>${Number(val(row,'isActive'))===1?'فعال':'غیرفعال'}</td>`:''}<td><button class="link" data-edit="${row.id}">ویرایش</button><button class="link danger" data-del="${row.id}">غیرفعال</button></td></tr>`).join('')}</tbody></table></div><div class="pager">صفحه ${d.pagination.page} از ${d.pagination.pages} — ${d.pagination.total} رکورد</div>`:'<div class="empty">رکوردی یافت نشد.</div>';
  document.querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>formResource(key,Number(b.dataset.edit)));
  document.querySelectorAll('[data-del]').forEach(b=>b.onclick=async()=>{if(!confirm('این رکورد غیرفعال شود؟'))return;try{await api('/api/v1/'+key+'/'+b.dataset.del+'?agencyId='+currentAgency.id,{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});driverSelections.delete(Number(b.dataset.del));await loadResource(key)}catch(e){alert(e.message)}});
  if(drivers){
   const boxes=[...document.querySelectorAll('.driver-select')],all=$('driverSelectAll');
   boxes.forEach(b=>b.onchange=()=>{const id=Number(b.dataset.id);b.checked?driverSelections.add(id):driverSelections.delete(id);all.checked=boxes.every(x=>x.checked);driverSelectionLabel()});
   all.checked=boxes.every(b=>b.checked);
   all.onchange=()=>{boxes.forEach(b=>{b.checked=all.checked;const id=Number(b.dataset.id);all.checked?driverSelections.add(id):driverSelections.delete(id)});driverSelectionLabel()};
   driverSelectionLabel();
  }
 }catch(e){$('table').innerHTML='<div class="error">'+esc(e.message)+'</div>'}
}
async function formResource(key,id){const [title,fields]=resources[key];let row={};if(id){const d=await api('/api/v1/'+key+'?agencyId='+currentAgency.id);row=d.items.find(x=>Number(x.id)===id)||{}}$('modal').innerHTML=`<div class="modal-backdrop"><form class="modal" id="editForm"><div class="modal-head"><h3>${id?'ویرایش':'ثبت'} ${title}</h3><button type="button" id="closeModal">×</button></div><div class="form-grid">${fields.map(f=>`<label>${f}<input name="${f}" value="${esc(val(row,f))}"></label>`).join('')}</div><button class="primary">ذخیره</button><div id="formError" class="error" hidden></div></form></div>`;$('closeModal').onclick=()=>$('modal').innerHTML='';$('editForm').onsubmit=async e=>{e.preventDefault();const b=Object.fromEntries(new FormData(e.target).entries());try{await api('/api/v1/'+key+(id?'/'+id:''),{method:id?'PUT':'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(b)});$('modal').innerHTML='';loadResource(key)}catch(x){$('formError').textContent=x.message;$('formError').hidden=false}}}

async function companiesPage(){
 if(!session?.roles?.includes('super_admin')){$('content').innerHTML='<div class="error">دسترسی به تنظیمات سامانه مجاز نیست.</div>';return}
 $('content').innerHTML='<div class="loading">در حال دریافت شرکت‌ها و کاربران...</div>';
 try{
  const d=await api('/api/v1/admin/overview');
  const rows=(d.agencies||[]).map(a=>'<tr><td>'+esc(a.id)+'</td><td>'+esc(a.name)+'</td><td>'+esc(a.code||'—')+'</td><td>'+esc(a.manager_name||'—')+'</td><td>'+esc(a.office_phone||'—')+'</td><td>'+esc(a.office_address||'—')+'</td><td>'+(a.latitude!=null&&a.longitude!=null?esc(Number(a.latitude).toFixed(6)+' ، '+Number(a.longitude).toFixed(6)):'ثبت نشده')+'</td><td><span class="'+(Number(a.is_active)===1?'status-active':'status-inactive')+'">'+(Number(a.is_active)===1?'فعال':'غیرفعال')+'</span></td><td><button class="link" data-company-edit="'+esc(a.id)+'">ویرایش</button><button class="link" data-company-users="'+esc(a.id)+'">تعیین کاربران</button>'+(Number(a.is_active)===1?'<button class="link danger" data-company-disable="'+esc(a.id)+'">غیرفعال‌سازی</button>':'')+'</td></tr>').join('');
  $('content').innerHTML='<section class="welcome"><h2>تنظیمات سامانه</h2><p>تعریف و مدیریت شرکت‌ها و تعیین کاربران هر شرکت.</p></section><section class="panel"><div class="toolbar"><div><h2>مدیریت شرکت‌ها</h2><p>برای حفظ سوابق، حذف به‌صورت غیرفعال‌سازی انجام می‌شود.</p></div><button class="primary" id="newCompanyBtn">تعریف شرکت جدید</button></div><div class="table-wrap"><table><thead><tr><th>شناسه</th><th>نام شرکت</th><th>کد شرکت</th><th>نام مدیر</th><th>تلفن دفتر</th><th>نشانی</th><th>مختصات جغرافیایی</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>'+(rows||'<tr><td colspan="9">شرکتی ثبت نشده است.</td></tr>')+'</tbody></table></div><div id="companyEditor"></div></section><section class="panel"><div class="toolbar"><div><h2>کاربران سامانه</h2><p class="muted">می‌توانید حساب جدید بسازید یا کاربران موجود را به شرکت‌ها تخصیص دهید.</p></div><button class="primary" id="newUserBtn" >ایجاد کاربر جدید</button></div><div class="table-wrap"><table><thead><tr><th>نام کاربری</th><th>نام</th><th>نقش</th><th>شرکت‌های مرتبط</th><th>وضعیت</th></tr></thead><tbody>'+(d.users||[]).map(u=>'<tr><td>'+esc(u.username)+'</td><td>'+esc(((u.first_name||'')+' '+(u.last_name||'')).trim()||'—')+'</td><td>'+esc(u.roles||'بدون نقش')+'</td><td>'+esc(u.agencies||'بدون شرکت')+'</td><td>'+(Number(u.is_active)===1?'فعال':'غیرفعال')+'</td></tr>').join('')+'</tbody></table></div></section>';
  const companyPanel=$('newCompanyBtn').closest('section'),allCompanies=d.agencies||[];
  companyPanel.classList.add('sy-admin-agency-panel');
  companyPanel.querySelector('.table-wrap')?.classList.add('sy-agency-table-scroll');
  const fa=n=>Number(n).toLocaleString('fa-IR');
  const activeCount=allCompanies.filter(a=>Number(a.is_active)===1).length;
  const summary=document.createElement('div');summary.className='sy-agency-kpis';
  summary.innerHTML=[['کل شرکت‌ها',allCompanies.length],['شرکت‌های فعال',activeCount],['شرکت‌های غیرفعال',allCompanies.length-activeCount]].map(([label,value])=>'<article><span>'+label+'</span><strong>'+fa(value)+'</strong></article>').join('');
  companyPanel.querySelector('.toolbar').after(summary);
  const filters=document.createElement('div');filters.className='sy-agency-controls';
  filters.innerHTML='<label>جستجو<input id="syAgencySearch" type="search" placeholder="نام شرکت، مدیر، کد یا نشانی"></label><label>وضعیت<select id="syAgencyStatus"><option value="all">همه شرکت‌ها</option><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label><button type="button" id="syAgencyExcel">خروجی Excel</button><button type="button" id="syAgencyPrint">چاپ فهرست</button>';
  summary.after(filters);
  const count=document.createElement('p');count.className='sy-agency-result-count';filters.after(count);
  const table=companyPanel.querySelector('table');table.classList.add('sy-agency-data-table');
  const trs=[...table.querySelectorAll('tbody tr')];
  trs.forEach((tr,i)=>{
   const agency=allCompanies[i];if(!agency)return;
   tr.dataset.status=Number(agency.is_active)===1?'active':'inactive';
   tr.dataset.query=[agency.name,agency.code,agency.manager_name,agency.office_phone,agency.office_address].join(' ').replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').toLowerCase();
   if(tr.cells[0])tr.cells[0].textContent=fa(agency.id);
   if(tr.dataset.status==='inactive'){
    const button=document.createElement('button');button.type='button';button.className='sy-agency-activate';button.textContent='فعال‌سازی مجدد';
    tr.lastElementChild?.append(button);
    button.onclick=async()=>{
     if(!confirm('شرکت «'+agency.name+'» دوباره فعال شود؟'))return;
     button.disabled=true;
     try{await api('/api/v1/admin/agencies/'+agency.id+'/activate',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken}});await companiesPage();}
     catch(error){alert(error.message);button.disabled=false;}
    };
   }
  });
  const search=filters.querySelector('#syAgencySearch'),status=filters.querySelector('#syAgencyStatus');
  const refresh=()=>{
   const term=search.value.trim().replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').toLowerCase();
   let shown=0;
   for(const tr of trs){const visible=(!term||tr.dataset.query?.includes(term))&&(status.value==='all'||status.value===tr.dataset.status);tr.hidden=!visible;if(visible)shown++;}
   count.textContent='نمایش '+fa(shown)+' از '+fa(allCompanies.length)+' شرکت';
  };
  search.oninput=refresh;status.onchange=refresh;refresh();
  filters.querySelector('#syAgencyExcel').onclick=async event=>{
   const button=event.currentTarget;button.disabled=true;
   try{
    const url='/api/v1/admin/agencies/export.xlsx?'+new URLSearchParams({search:search.value.trim(),status:status.value});
    const response=await fetch(url,{credentials:'same-origin'});
    if(!response.ok){const error=await response.json().catch(()=>null);throw new Error(error?.error?.message||'ساخت فایل ناموفق بود.');}
    const blob=await response.blob(),uri=URL.createObjectURL(blob),link=document.createElement('a');
    link.href=uri;link.download='serviceyar-agencies.xlsx';document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(uri),1000);
   }catch(error){alert(error.message);}finally{button.disabled=false;}
  };
  filters.querySelector('#syAgencyPrint').onclick=()=>{
   const win=window.open('','_blank','width=1100,height=800');
   if(!win){alert('باز شدن پنجره چاپ مسدود است.');return;}
   const safe=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
   const selected=trs.filter(tr=>!tr.hidden);
   const headers=['ردیف','شرکت','کد','مدیر','تلفن','نشانی','موقعیت','وضعیت'];
   const body=selected.map((tr,i)=>'<tr><td>'+fa(i+1)+'</td>'+[1,2,3,4,5,6,7].map(j=>'<td>'+safe(tr.cells[j]?.textContent?.trim())+'</td>').join('')+'</tr>').join('');
   win.document.open();win.document.write('<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>شرکت‌های سرویس‌یار</title><style>@page{size:A4 landscape;margin:12mm}body{font:12px Tahoma;color:#153b2c}h1{font-size:21px}table{border-collapse:collapse;width:100%;font-size:10px}th,td{border:1px solid #c7d9cf;padding:7px;text-align:right}th{background:#145d3d;color:#fff}tr:nth-child(even){background:#f0f7f3}thead{display:table-header-group}tr{break-inside:avoid}</style></head><body><h1>سرویس‌یار | گزارش شرکت‌ها</h1><p>تعداد: '+fa(selected.length)+'</p><table><thead><tr>'+headers.map(h=>'<th>'+h+'</th>').join('')+'</tr></thead><tbody>'+body+'</tbody></table></body></html>');win.document.close();win.focus();win.addEventListener('load',()=>win.print(),{once:true});
  };
  $('newCompanyBtn').onclick=()=>companyForm(null);
  $('newUserBtn').onclick=()=>newUserForm(d.agencies||[],d.roles||[]);
  function newUserForm(companyList,roleList){
   const box=$('companyEditor'),activeCompanies=companyList.filter(a=>Number(a.is_active)===1);
   const availableRoles=roleList.filter(r=>r.name!=='super_admin');
   if(!activeCompanies.length){box.innerHTML='<div class="error">برای ایجاد کاربر ابتدا یک شرکت فعال ایجاد کنید.</div>';return}
   if(!availableRoles.length){box.innerHTML='<div class="error">هیچ نقش قابل تخصیصی در سامانه تعریف نشده است.</div>';return}
   box.innerHTML='<form id="newUserForm" class="company-editor"><h3>ایجاد کاربر جدید</h3><p class="muted">حساب جدید به شرکت انتخاب‌شده متصل می‌شود. رمز عبور باید حداقل ۸ نویسه باشد.</p><div class="form-grid"><label>شرکت<input type="hidden" name="agencyId"><select name="agencySelect" required>'+activeCompanies.map(a=>'<option value="'+esc(a.id)+'">'+esc(a.name)+'</option>').join('')+'</select></label><label>نام کاربری<input name="username" required maxlength="100" autocomplete="off"></label><label>نام<input name="firstName" maxlength="100"></label><label>نام خانوادگی<input name="lastName" maxlength="100"></label><label>رمز عبور اولیه<input name="password" type="password" required minlength="8" autocomplete="new-password"></label><label>نقش کاربر<select name="role" required>'+availableRoles.map(r=>'<option value="'+esc(r.name)+'">'+esc(r.title||r.name)+'</option>').join('')+'</select></label></div><div class="toolbar"><button class="primary">ایجاد حساب کاربری</button><button type="button" class="secondary" id="cancelNewUser">انصراف</button></div><div id="newUserError" class="error" hidden></div></form>';
   const form=$('newUserForm');form.elements.agencyId.value=activeCompanies[0].id;
   form.elements.agencySelect.onchange=()=>form.elements.agencyId.value=form.elements.agencySelect.value;
   $('cancelNewUser').onclick=()=>box.innerHTML='';
   form.onsubmit=async e=>{e.preventDefault();const f=Object.fromEntries(new FormData(form).entries());delete f.agencySelect;const agencyId=f.agencyId;delete f.agencyId;try{await api('/api/v1/users?agencyId='+encodeURIComponent(agencyId),{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(f)});await companiesPage();await companyUsers(agencyId)}catch(err){$('newUserError').textContent=err.message;$('newUserError').hidden=false}};
   box.scrollIntoView({behavior:'smooth',block:'nearest'});
  }
  document.querySelectorAll('[data-company-edit]').forEach(b=>b.onclick=()=>companyForm((d.agencies||[]).find(a=>String(a.id)===b.dataset.companyEdit)));
  document.querySelectorAll('[data-company-users]').forEach(b=>b.onclick=()=>companyUsers(b.dataset.companyUsers));
  document.querySelectorAll('[data-company-disable]').forEach(b=>b.onclick=async()=>{const a=(d.agencies||[]).find(x=>String(x.id)===b.dataset.companyDisable);if(!a||!confirm('شرکت «'+a.name+'» غیرفعال شود؟ سوابق آن حذف نمی‌شود.'))return;try{await api('/api/v1/agencies/'+a.id,{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});await companiesPage()}catch(e){alert(e.message)}});
  function companyForm(company){
   const box=$('companyEditor'),editing=!!company;
   box.innerHTML='<form id="companyForm" class="company-editor"><h3>'+(editing?'ویرایش شرکت':'تعریف شرکت جدید')+'</h3><div class="form-grid"><label>نام شرکت<input name="name" required maxlength="255" value="'+esc(company?.name||'')+'"></label><label>کد شرکت<input name="code" maxlength="50" value="'+esc(company?.code||'')+'"></label><label>شناسه شهر (اختیاری)<input name="cityId" type="number" min="1" value="'+esc(company?.city_id||'')+'"></label><label>نام مدیر شرکت<input name="company_manager_name" maxlength="150" value="'+esc(company?.manager_name||'')+'"></label><label>شماره همراه مدیر<input name="company_ceo_mobile" inputmode="tel" maxlength="25" value="'+esc(company?.manager_mobile||'')+'"></label><label>تلفن دفتر<input name="company_phone" inputmode="tel" maxlength="30" value="'+esc(company?.office_phone||'')+'"></label><label>نشانی دفتر<input name="company_address" maxlength="450" value="'+esc(company?.office_address||'')+'"></label><label>عرض جغرافیایی<input name="latitude" type="number" min="-90" max="90" step="any" value="'+esc(company?.latitude??'')+'"></label><label>طول جغرافیایی<input name="longitude" type="number" min="-180" max="180" step="any" value="'+esc(company?.longitude??'')+'"></label>'+(editing?'<label class="checkbox-field"><input type="checkbox" name="isActive" '+(Number(company.is_active)===1?'checked':'')+'> شرکت فعال باشد</label>':'')+'</div><div class="toolbar"><button class="primary">'+(editing?'ذخیره تغییرات':'ثبت شرکت')+'</button><button type="button" class="secondary" id="cancelCompany">انصراف</button></div><div id="companyFormError" class="error" hidden></div></form>';
   $('cancelCompany').onclick=()=>box.innerHTML='';
   $('companyForm').onsubmit=async e=>{e.preventDefault();const f=Object.fromEntries(new FormData(e.target).entries());if(editing)f.isActive=e.target.elements.isActive.checked?1:0;try{await api('/api/v1/agencies'+(editing?'/'+company.id:''),{method:editing?'PUT':'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(f)});await companiesPage()}catch(err){$('companyFormError').textContent=err.message;$('companyFormError').hidden=false}};
   box.scrollIntoView({behavior:'smooth',block:'nearest'});
  }
  async function companyUsers(companyId){
   const box=$('companyEditor'),company=(d.agencies||[]).find(a=>String(a.id)===String(companyId));box.innerHTML='<div class="loading">در حال دریافت کاربران شرکت...</div>';
   try{const result=await api('/api/v1/agencies/'+companyId+'/users'),assigned=new Set((result.assignedUserIds||[]).map(String));
    box.innerHTML='<form id="companyUsersForm" class="company-editor"><h3>تعیین کاربران شرکت '+esc(company?.name||('#'+companyId))+'</h3><p class="muted">این عملیات ارتباط کاربر با شرکت را تغییر می‌دهد و نقش سراسری کاربر را عوض نمی‌کند.</p><div class="company-user-list">'+(result.users||[]).map(u=>'<label class="company-user"><input type="checkbox" name="userIds" value="'+esc(u.id)+'" '+(assigned.has(String(u.id))?'checked':'')+'><span><b>'+esc(u.username)+'</b><small>'+esc(((u.first_name||'')+' '+(u.last_name||'')).trim()||'بدون نام')+' · '+(Number(u.is_active)===1?'فعال':'غیرفعال')+'</small></span></label>').join('')+'</div><div class="toolbar"><button class="primary">ذخیره تخصیص کاربران</button><button type="button" class="secondary" id="cancelCompanyUsers">انصراف</button></div><div id="companyUsersError" class="error" hidden></div></form>';
    $('cancelCompanyUsers').onclick=()=>box.innerHTML='';
    $('companyUsersForm').onsubmit=async e=>{e.preventDefault();const userIds=[...e.target.querySelectorAll('input[name="userIds"]:checked')].map(x=>Number(x.value));try{await api('/api/v1/agencies/'+companyId+'/users',{method:'PUT',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({userIds})});await companiesPage()}catch(err){$('companyUsersError').textContent=err.message;$('companyUsersError').hidden=false}};
    box.scrollIntoView({behavior:'smooth',block:'nearest'});
   }catch(e){box.innerHTML='<div class="error">'+esc(e.message)+'</div>'}
  }
 }catch(e){$('content').innerHTML='<div class="error">'+esc(e.message)+'</div>'}
}
async function settingsPage(){ if(!currentAgency){$('content').innerHTML='<div class="error">برای نمایش تنظیمات شرکتی، ابتدا یک شرکت فعال ایجاد یا انتخاب کنید.</div>';return} $('content').innerHTML='<div class="loading">در حال دریافت تنظیمات...</div>';try{const [set,hol,users]=await Promise.all([api('/api/v1/settings?agencyId='+currentAgency.id),api('/api/v1/holidays?agencyId='+currentAgency.id),api('/api/v1/users?agencyId='+currentAgency.id)]);$('content').innerHTML=`<div class="tabs"><button class="tab active" data-tab="org">شرکت</button><button class="tab" data-tab="users">کاربران</button><button class="tab" data-tab="holidays">تعطیلات</button><button class="tab" data-tab="messages">پیام و SMS</button></div><section class="panel tab-pane" id="tab-org"><h2>اطلاعات و تنظیمات شرکت</h2><form id="settingsForm"><div class="form-grid">${Object.entries(set).map(([k,v])=>`<label>${k}<input name="${k}" value="${esc(v)}"></label>`).join('')}</div><button class="primary">ذخیره تنظیمات</button></form></section><section class="panel tab-pane" id="tab-users" hidden><h2>کاربران شرکت</h2><div class="table-wrap"><table><thead><tr><th>کاربر</th><th>نام</th><th>نقش</th><th>وضعیت</th></tr></thead><tbody>${users.map(u=>`<tr><td>${esc(u.username)}</td><td>${esc((u.first_name||'')+' '+(u.last_name||''))}</td><td>${esc(u.roles)}</td><td>${u.is_active?'فعال':'غیرفعال'}</td></tr>`).join('')}</tbody></table></div></section><section class="panel tab-pane" id="tab-holidays" hidden><h2>تعطیلات</h2><div class="table-wrap"><table><thead><tr><th>عنوان</th><th>تاریخ جلالی</th><th>تاریخ میلادی</th><th>وضعیت</th></tr></thead><tbody>${hol.map(h=>`<tr><td>${esc(h.title)}</td><td>${esc(h.date_jalali)}</td><td>${esc(h.date_gregorian)}</td><td>${h.is_active?'فعال':'غیرفعال'}</td></tr>`).join('')}</tbody></table></div></section><section class="panel tab-pane" id="tab-messages" hidden><h2>تنظیمات پیام و SMS</h2><p>کلیدهای تنظیمات پیام در همین بخش نگهداری می‌شوند؛ Secretها از API عمومی بازگردانده نمی‌شوند.</p></section>`;document.querySelectorAll('.tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.tab-pane').forEach(x=>x.hidden=true);b.classList.add('active');$('tab-'+b.dataset.tab).hidden=false});$('settingsForm').onsubmit=async e=>{e.preventDefault();const b=Object.fromEntries(new FormData(e.target).entries());await api('/api/v1/settings?agencyId='+currentAgency.id,{method:'PUT',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(b)});alert('تنظیمات ذخیره شد.')}}catch(e){$('content').innerHTML='<div class="error">'+esc(e.message)+'</div>'}}
const exportResources=[['schools','گزارش مدارس'],['students','گزارش دانش‌آموزان'],['drivers','گزارش رانندگان'],['services','گزارش سرویس‌ها'],['driver-work','گزارش کارکرد رانندگان'],['invoices','گزارش فاکتورها'],['payments','گزارش پرداخت‌ها']];
async function downloadReport(resource,button){const label=button.textContent;button.disabled=true;button.textContent='در حال ساخت فایل...';const box=$('reportError');box.hidden=true;try{const filters={dateFrom:$('reportDateFrom')?.value||'',dateTo:$('reportDateTo')?.value||'',search:$('reportSearch')?.value.trim()||'',status:$('reportStatus')?.value.trim()||'',active:$('reportActive')?.value||'',schoolId:$('reportSchoolId')?.value.trim()||'',driverId:$('reportDriverId')?.value.trim()||'',serviceId:$('reportServiceId')?.value.trim()||'',amountMin:$('reportAmountMin')?.value.trim()||'',amountMax:$('reportAmountMax')?.value.trim()||''};if(filters.dateFrom&&filters.dateTo&&filters.dateFrom>filters.dateTo)throw new Error('تاریخ شروع نباید بعد از تاریخ پایان باشد.');if(filters.amountMin&&filters.amountMax&&Number(filters.amountMin)>Number(filters.amountMax))throw new Error('حداقل مبلغ نباید از حداکثر مبلغ بیشتر باشد.');const params=new URLSearchParams({agencyId:String(currentAgency.id),resource});Object.entries(filters).forEach(([key,value])=>{if(value!=='')params.set(key,value)});const response=await fetch('/api/v1/reports/export?'+params.toString(),{method:'GET',credentials:'same-origin',headers:{Accept:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json'}});const type=response.headers.get('content-type')||'';if(!response.ok||type.includes('application/json')){const body=await response.json().catch(()=>({}));throw new Error(body?.error?.message||'ساخت فایل Excel ناموفق بود.')}const blob=await response.blob();if(!blob.size)throw new Error('فایل گزارش خالی دریافت شد.');const disposition=response.headers.get('content-disposition')||'';const match=disposition.match(/filename="?([^";]+)"?/i);const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=match?.[1]||resource+'-report.xlsx';document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000)}catch(error){box.textContent=error.message||'دریافت گزارش ناموفق بود.';box.hidden=false}finally{button.disabled=false;button.textContent=label}}
async function reportsPage(){ if(!currentAgency){$('content').innerHTML='<div class="error">برای تهیه گزارش، ابتدا یک شرکت فعال ایجاد یا انتخاب کنید.</div>';return} $('content').innerHTML='<div class="loading">در حال آماده‌سازی گزارش‌ها...</div>';let summary=null;try{summary=await api('/api/v1/reports/summary?agencyId='+currentAgency.id)}catch(e){}$('content').innerHTML=`<section class="toolbar"><div><h2>گزارش‌ها و خروجی Excel</h2><p>بازه زمانی و فیلترهای انتخاب‌شده روی فایل خروجی اعمال می‌شوند.</p></div></section><div class="stats">${[['schools','مدارس'],['students','دانش‌آموزان'],['drivers','رانندگان'],['services','سرویس‌ها']].map(([key,title])=>`<article><span>${title}</span><strong>${Number(summary?.[key]??0).toLocaleString('fa-IR')}</strong><small>${summary?'تعداد فعال':'آمار در دسترس نیست'}</small></article>`).join('')}</div><section class="panel"><h3>فیلتر گزارش</h3><p class="report-filter-hint">تاریخ‌ها به‌صورت شمسی انتخاب می‌شوند. فیلترهای نامرتبط با نوع گزارش خودکار نادیده گرفته می‌شوند. سقف خروجی ۵۰۰۰ رکورد منطبق است.</p><div class="report-filters"><label>از تاریخ<input type="date" id="reportDateFrom"></label><label>تا تاریخ<input type="date" id="reportDateTo"></label><label class="wide">جست‌وجو در اطلاعات<input id="reportSearch" placeholder="نام، کد، شناسه، توضیحات و..."></label><label>وضعیت دقیق<input id="reportStatus" placeholder="مثلاً active یا pending"></label><label>فعال بودن<select id="reportActive"><option value="">همه</option><option value="1">فعال</option><option value="0">غیرفعال</option></select></label><label>شناسه مدرسه<input type="number" min="1" id="reportSchoolId" placeholder="اختیاری"></label><label>شناسه راننده<input type="number" min="1" id="reportDriverId" placeholder="اختیاری"></label><label>شناسه سرویس<input type="number" min="1" id="reportServiceId" placeholder="اختیاری"></label><label>حداقل مبلغ<input type="number" min="0" step="any" id="reportAmountMin" placeholder="اختیاری"></label><label>حداکثر مبلغ<input type="number" min="0" step="any" id="reportAmountMax" placeholder="اختیاری"></label></div><button class="secondary" id="clearReportFilters" type="button">پاک کردن فیلترها</button></section><section class="panel"><h3>دریافت گزارش‌ها</h3><div class="report-grid">${exportResources.map(([key,title])=>`<article class="report-item"><div class="report-icon">${navIcon(key==='driver-work'?'driver-work':key)}</div><div class="report-title">${title}</div><div class="report-desc">Excel با سرستون‌های فارسی و فیلترهای بالا</div><button class="primary" data-export="${key}">دریافت Excel</button></article>`).join('')}</div><div id="reportError" class="error" hidden></div></section>`;$('content').querySelectorAll('[data-export]').forEach(button=>button.addEventListener('click',()=>downloadReport(button.dataset.export,button)));$('clearReportFilters').addEventListener('click',()=>{['reportDateFrom','reportDateTo','reportSearch','reportStatus','reportActive','reportSchoolId','reportDriverId','reportServiceId','reportAmountMin','reportAmountMax'].forEach(id=>$(id).value='');$('reportError').hidden=true})}
async function boot(){try{session=await api('/api/v1/auth/me');agencies=session.agencies||[];currentAgency=agencies[0]||null;if(!currentAgency&&!session.roles?.includes('super_admin'))throw new Error('شرکت فعالی برای کاربر تعریف نشده است.');$('userName').textContent=[session.user.firstName,session.user.lastName].filter(Boolean).join(' ')||session.user.username;$('avatar').textContent=$('userName').textContent.charAt(0);window.serviceyarSessionActive=true;window.serviceyarPublic?.hide();renderNav();$('login').hidden=true;$('app').hidden=false;render()}catch(e){session=null;window.serviceyarSessionActive=false;$('app').hidden=true;await window.serviceyarPublic?.show()}}
// Remove legacy credential query parameters before any app routing or navigation.
(function clearLeakedLoginQuery(){
 const params=new URLSearchParams(location.search);
 if(![...params.keys()].some(key=>/^(username|password|passwd|pass)$/i.test(key)))return;
 for(const key of [...params.keys()])if(/^(username|password|passwd|pass)$/i.test(key))params.delete(key);
 const clean=location.pathname+(params.toString()?'?'+params.toString():'')+location.hash;
 history.replaceState(history.state,'',clean);
})();
$('loginForm').addEventListener('submit',async e=>{e.preventDefault();const username=$('username').value.trim(),password=$('password').value,button=$('loginSubmit'),error=$('loginError');error.hidden=true;error.textContent='';$('username').removeAttribute('aria-invalid');$('password').removeAttribute('aria-invalid');if(!username||!password){error.textContent=!username?'نام کاربری را وارد کنید.':'رمز عبور را وارد کنید.';error.hidden=false;(!username?$('username'):$('password')).setAttribute('aria-invalid','true');(!username?$('username'):$('password')).focus();return}button.disabled=true;button.classList.add('is-loading');button.querySelector('span').textContent='در حال بررسی اطلاعات...';try{const d=await api('/api/v1/auth/login',{method:'POST',body:JSON.stringify({username,password})});session={user:d.user,csrfToken:d.csrfToken};const m=await api('/api/v1/auth/me');session={...session,...m};agencies=m.agencies||[];currentAgency=agencies[0]||null;$('password').value='';window.serviceyarSessionActive=true;window.serviceyarPublic?.hide();$('app').hidden=false;if(['login','home','about','contact','features','faq'].includes(location.hash.slice(1)))history.replaceState(history.state,'',location.pathname+location.search+'#dashboard');renderNav();render()}catch(e){error.textContent=e.message||'ورود انجام نشد؛ اطلاعات واردشده را بررسی کنید.';error.hidden=false;if(/نام کاربری/.test(error.textContent)){$('username').setAttribute('aria-invalid','true');$('username').focus()}else if(/رمز عبور|حساب کاربری غیرفعال/.test(error.textContent)){$('password').setAttribute('aria-invalid','true');$('password').focus()}else $('username').focus()}finally{button.disabled=false;button.classList.remove('is-loading');button.querySelector('span').textContent='ورود به سامانه'}});
$('logout').addEventListener('click',async()=>{try{await api('/api/v1/auth/logout',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken}})}finally{session=null;window.serviceyarSessionActive=false;$('app').hidden=true;history.replaceState(history.state,'',location.pathname+location.search+'#home');await window.serviceyarPublic?.show()}});
$('agencySelect')?.addEventListener('change',e=>{const selected=(agencies||[]).find(a=>String(a.id)===e.target.value);if(selected){currentAgency=selected;render()}});
addEventListener('hashchange',()=>{if(session)render()});boot();

$('togglePassword')?.addEventListener('click',()=>{const input=$('password'),button=$('togglePassword'),show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'پنهان':'نمایش';button.setAttribute('aria-label',show?'پنهان کردن رمز عبور':'نمایش رمز عبور');button.setAttribute('aria-pressed',String(show));input.focus()});
$('username')?.addEventListener('input',()=>{$('username').removeAttribute('aria-invalid');if(!$('loginError').hidden){$('loginError').hidden=true}});
$('password')?.addEventListener('input',()=>{$('password').removeAttribute('aria-invalid');if(!$('loginError').hidden){$('loginError').hidden=true}});
