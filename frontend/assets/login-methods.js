/* ServiceYar login: one cohesive card with policy-driven accessible method tabs. */
'use strict';
(() => {
 const labels={password:'نام کاربری و رمز عبور',sms:'ورود با پیامک',bale:'ورود با ربات بله'};
 const icons={password:'♙',sms:'✉',bale:'◈'};
 const normalize=value=>String(value||'').trim().replace(/[۰-۹]/g,c=>String(c.charCodeAt(0)-1776)).replace(/[٠-٩]/g,c=>String(c.charCodeAt(0)-1632));
 const make=(name,cls,text)=>{const el=document.createElement(name);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
 const note=(node,text,error=false)=>{node.textContent=text||'';node.classList.toggle('error',error);};
 const root=document.querySelector('.login-card'),passwordForm=document.getElementById('loginForm');
 if(!root||!passwordForm)return;

 const slot=make('section','sy-login-methods');slot.id='syLoginMethods';
 const tabs=make('div','sy-login-method-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','روش ورود به سامانه');
 const state=make('p','sy-login-method-state','در حال دریافت روش‌های مجاز ورود…');state.setAttribute('role','status');state.setAttribute('aria-live','polite');
 const panelsHost=make('div','sy-login-method-panels');
 slot.append(tabs,state,panelsHost);
 passwordForm.hidden=true;
 passwordForm.classList.add('sy-login-method-panel');
 passwordForm.id='syLoginPanelPassword';
 passwordForm.setAttribute('role','tabpanel');
 passwordForm.before(slot);
 panelsHost.append(passwordForm);
 const panels={password:passwordForm};
 const tabButtons={};
 let available=[],selected='password';
 const userLabel='نام کاربری';

 const createOtp=(method)=>{
  const form=make('form','sy-login-otp-form sy-login-method-panel');
  form.id='syLoginPanel'+(method==='sms'?'Sms':'Bale');
  form.hidden=true;form.setAttribute('role','tabpanel');
  const intro=make('p','sy-login-otp-intro',method==='sms'
   ?'کد یک‌بارمصرف به شماره همراه ثبت‌شده در پروفایل شما ارسال می‌شود.'
   :'برای استفاده از ورود با بله، ابتدا ربات syarbot را در پیام‌رسان بله باز کنید و دکمه «شروع» (Start) را بزنید. کاربران عادی در اولین مراجعه باید شماره همراه خود را با دکمه «اشتراک شماره همراه من» در ربات تأیید کنند. ادمین کل نیز باید اتصال اختصاصی پروفایل خود را انجام داده باشد.');
  if(method==='bale'){
   const botLink=make('a','sy-login-bot-link','ورود به ربات syarbot در بله');
   botLink.href='https://ble.ir/syarbot';botLink.target='_blank';botLink.rel='noopener noreferrer';
   botLink.setAttribute('aria-label','باز کردن ربات syarbot در پیام‌رسان بله');
   const backHint=make('p','sy-login-bot-return','پس از شروع ربات و اتصال حساب، به این صفحه برگردید، نام کاربری را وارد کنید و کد ورود را دریافت نمایید.');
   form.append(intro,botLink,backHint);
  }else form.append(intro);

  const user=make('label','sy-login-field');user.textContent=userLabel;
  const username=make('input');username.name='username';username.required=true;username.maxLength=100;
  username.autocomplete='username';username.placeholder='نام کاربری خود را وارد کنید';user.append(username);
  const send=make('button','sy-login-otp-send','ارسال کد ورود');send.type='button';
  const codeLabel=make('label','sy-login-field');codeLabel.textContent='کد تأیید ۶ رقمی';
  const code=make('input');code.name='code';code.required=true;code.maxLength=6;code.inputMode='numeric';
  code.autocomplete='one-time-code';code.placeholder='ــــــ';code.pattern='[0-9۰-۹٠-٩]{6}';code.dir='ltr';codeLabel.append(code);
  const verify=make('button','sy-login-otp-confirm','تأیید کد و ورود');verify.type='submit';
  const info=make('p','sy-login-otp-message');info.setAttribute('role','status');info.setAttribute('aria-live','polite');
  form.append(user,send,codeLabel,verify,info);

  send.onclick=async()=>{
   const name=normalize(username.value);
   if(!name){note(info,'ابتدا نام کاربری را وارد کنید.',true);username.focus();return;}
   send.disabled=true;note(info,'در حال ارسال درخواست…');
   try{
    await api('/api/v1/auth/'+method+'/request',{method:'POST',body:JSON.stringify({username:name})});
    code.value='';note(info,'در صورت وجود حساب فعال و اتصال صحیح، کد ارسال شده است. اعتبار کد ۵ دقیقه است.');code.focus();
   }catch(e){note(info,e.message||'ارسال کد انجام نشد.',true);}
   finally{send.disabled=false;}
  };
  form.onsubmit=async e=>{
   e.preventDefault();
   const name=normalize(username.value),otp=normalize(code.value);
   if(!name||!/^[0-9]{6}$/.test(otp)){note(info,'نام کاربری و کد ۶ رقمی معتبر وارد کنید.',true);return;}
   verify.disabled=true;note(info,'در حال بررسی کد…');
   try{
    await api('/api/v1/auth/'+method+'/verify',{method:'POST',body:JSON.stringify({username:name,code:otp})});
    code.value='';note(info,'ورود تأیید شد.');await boot();
   }catch(err){note(info,err.message||'تأیید کد انجام نشد.',true);}
   finally{verify.disabled=false;}
  };
  return form;
 };
 panels.sms=createOtp('sms');panels.bale=createOtp('bale');
 panelsHost.append(panels.sms,panels.bale);

 const activate=(method,focus=false)=>{
  if(!available.includes(method))return;
  selected=method;
  for(const [key,panel] of Object.entries(panels)){
   panel.hidden=key!==method;
   panel.setAttribute('aria-labelledby','sy-login-tab-'+key);
  }
  for(const [key,button] of Object.entries(tabButtons)){
   const active=key===method;
   button.setAttribute('aria-selected',String(active));
   button.tabIndex=active?0:-1;
  }
  if(focus)tabButtons[method]?.focus();
 };
 const render=(enabled)=>{
  available=Object.keys(labels).filter(key=>enabled&&enabled[key]===true);
  tabs.replaceChildren();
  Object.keys(tabButtons).forEach(key=>delete tabButtons[key]);
  state.hidden=available.length>0;
  if(!available.length){
   state.textContent='در حال حاضر هیچ روش ورود فعالی در دسترس نیست. لطفاً با مدیر سامانه تماس بگیرید.';
   for(const panel of Object.values(panels))panel.hidden=true;
   tabs.hidden=true;return;
  }
  tabs.hidden=false;
  for(const method of available){
   const button=make('button','sy-login-method-tab');
   button.type='button';button.id='sy-login-tab-'+method;button.setAttribute('role','tab');
   button.setAttribute('aria-controls',panels[method].id);
   const icon=make('span','sy-login-method-icon',icons[method]);icon.setAttribute('aria-hidden','true');
   const title=make('span','sy-login-method-label',labels[method]);
   button.append(icon,title);
   button.onclick=()=>activate(method,true);
   button.onkeydown=e=>{
    if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key))return;
    e.preventDefault();const index=available.indexOf(method);
    const next=e.key==='Home'?0:e.key==='End'?available.length-1:(index+(e.key==='ArrowLeft'?1:-1)+available.length)%available.length;
    activate(available[next],true);
   };
   tabs.append(button);tabButtons[method]=button;
  }
  activate(available.includes(selected)?selected:available[0]);
  const heading=root.querySelector('.login-heading p');
  if(heading)heading.textContent=available.length>1?'روش ورود موردنظر خود را از زبانه‌های زیر انتخاب کنید.':'اطلاعات ورود خود را در کادر زیر وارد کنید.';
 };
 // Fail closed until the server confirms which methods are enabled and ready.
 async function reload(){
  try{
   const result=await api('/api/v1/auth/methods');
   render(result.methods||{});
  }catch(e){
   render({});
   state.textContent='دریافت تنظیمات ورود از سرور ناموفق بود. اتصال را بررسی و صفحه را دوباره بارگذاری کنید.';
  }
 }
 void reload();
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&!window.serviceyarSessionActive)void reload();});

 const oldCompaniesPage=companiesPage;
 companiesPage=async function(){
  await oldCompaniesPage();
  if(!session?.roles?.includes('super_admin'))return;
  const parent=document.getElementById('syAdminIntegrations');
  if(!parent||parent.querySelector('#syAdminLoginMethods'))return;
  const block=make('section','sy-login-policy-panel');block.id='syAdminLoginMethods';
  const title=make('h3','','مدیریت روش‌های ورود کاربران');
  const description=make('p','','فقط روش‌های انتخاب‌شده و آماده در صفحه ورود نمایش داده می‌شوند. روش‌های غیرفعال از طریق API نیز مسدود هستند؛ حداقل یک روش فعال لازم است.');
  const form=make('form','sy-login-policy-form'),checks={};
  for(const key of Object.keys(labels)){
   const row=make('label','sy-login-policy-option'),checkbox=make('input');
   checkbox.type='checkbox';checkbox.name=key;
   const desc=make('span','',labels[key]);row.append(checkbox,desc);checks[key]=checkbox;form.append(row);
  }
  const submit=make('button','primary','ذخیره روش‌های ورود');submit.type='submit';
  const result=make('p','sy-login-policy-status');result.setAttribute('role','status');
  form.append(submit,result);block.append(title,description,form);parent.append(block);
  const refresh=async()=>{
   const status=await api('/api/v1/admin/login-methods');
   if(!block.isConnected)return;
   for(const key of Object.keys(checks)){
    checks[key].checked=Boolean(status.configured[key]);
    if(key!=='password')checks[key].parentElement.title=status.available[key]?'سرویس آماده است':'این روش نیازمند تنظیم سرویس ارسال کد است';
   }
  };
  form.onsubmit=async e=>{
   e.preventDefault();
   if(!Object.values(checks).some(x=>x.checked)){note(result,'حداقل یک روش ورود را انتخاب کنید.',true);return;}
   submit.disabled=true;note(result,'در حال ذخیره…');
   try{
    const data=Object.fromEntries(Object.entries(checks).map(([key,value])=>[key,value.checked]));
    await api('/api/v1/admin/login-methods',{method:'PUT',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(data)});
    note(result,'تنظیمات ذخیره شد و روی API اعمال می‌شود.');await refresh();
   }catch(e){note(result,e.message,true);}
   finally{submit.disabled=false;}
  };
  try{await refresh();}catch(e){note(result,e.message,true);}
 };
})();