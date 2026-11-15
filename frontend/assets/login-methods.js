/* Unified accessible login + platform-wide login-method policy; no third-party dependencies. */
'use strict';
(() => {
 const labels={password:'نام کاربری و رمز عبور',sms:'کد پیامکی',bale:'کد ربات بله'};
 const icons={password:'🔐',sms:'✉',bale:'◈'};
 const normalize=value=>String(value||'').trim().replace(/[۰-۹]/g,c=>String(c.charCodeAt(0)-1776)).replace(/[٠-٩]/g,c=>String(c.charCodeAt(0)-1632));
 const make=(name,cls,text)=>{const el=document.createElement(name);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
 const note=(node,text,error=false)=>{node.textContent=text||'';node.classList.toggle('error',error);};
 const root=document.querySelector('.login-card'),passwordForm=document.getElementById('loginForm');
 const slot=root&&passwordForm?make('section','sy-login-methods'):null;
 if(!slot)return;
 slot.id='syLoginMethods';
 const tabs=make('div','sy-login-method-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','انتخاب روش ورود');
 slot.append(tabs);
 const panels={password:passwordForm};
 passwordForm.before(slot);
 const userLabel='نام کاربری سامانه';
 const createOtp=(method)=>{
  const form=make('form','sy-login-otp-form');form.id='syLogin'+method;
  form.hidden=true;
  const intro=make('p','sy-login-otp-intro',method==='sms'?'کد یک‌بارمصرف به شماره همراه ثبت‌شده در پروفایل شما پیامک می‌شود.':'کد یک‌بارمصرف به گفتگوی ربات بله متصل به حساب شما ارسال می‌شود.');
  const user=make('label','sy-login-field');user.textContent=userLabel;
  const username=make('input');username.name='username';username.required=true;username.maxLength=100;username.autocomplete='username';username.placeholder='نام کاربری یا شماره ورود';user.append(username);
  const send=make('button','sy-login-otp-send','ارسال کد ورود');send.type='button';
  const codeLabel=make('label','sy-login-field');codeLabel.textContent='کد تأیید ۶ رقمی';
  const code=make('input');code.name='code';code.required=true;code.maxLength=6;code.inputMode='numeric';code.autocomplete='one-time-code';code.placeholder='ــــــ';code.pattern='[0-9۰-۹٠-٩]{6}';code.dir='ltr';codeLabel.append(code);
  const verify=make('button','sy-login-otp-confirm','تأیید کد و ورود');verify.type='submit';
  const info=make('p','sy-login-otp-message');info.setAttribute('role','status');info.setAttribute('aria-live','polite');
  form.append(intro,user,send,codeLabel,verify,info);
  send.onclick=async()=>{
   const name=normalize(username.value);
   if(!name){note(info,'ابتدا نام کاربری را وارد کنید.',true);username.focus();return;}
   send.disabled=true;note(info,'در حال بررسی درخواست…');
   try{
    await api('/api/v1/auth/'+method+'/request',{method:'POST',body:JSON.stringify({username:name})});
    code.value='';note(info,'در صورت وجود حساب فعال و اتصال صحیح، کد ارسال شده است. اعتبار کد ۵ دقیقه است.');
    code.focus();
   }catch(e){note(info,e.message||'ارسال کد انجام نشد.',true);}
   finally{send.disabled=false;}
  };
  form.onsubmit=async e=>{
   e.preventDefault();
   const name=normalize(username.value),otp=normalize(code.value);
   if(!name||!/^[0-9]{6}$/.test(otp)){note(info,'نام کاربری و کد ۶ رقمی معتبر وارد کنید.',true);return;}
   verify.disabled=true;note(info,'در حال تأیید کد…');
   try{
    await api('/api/v1/auth/'+method+'/verify',{method:'POST',body:JSON.stringify({username:name,code:otp})});
    code.value='';note(info,'ورود تأیید شد.');
    await boot();
   }catch(err){note(info,err.message||'تأیید کد انجام نشد.',true);}
   finally{verify.disabled=false;}
  };
  return form;
 };
 panels.sms=createOtp('sms');panels.bale=createOtp('bale');
 slot.after(panels.sms,panels.bale);
 let available=['password'],selected='password';
 const tabButtons={};
 const activate=(method,focus=false)=>{
  if(!available.includes(method))method=available[0];
  selected=method;
  for(const [key,form] of Object.entries(panels)){
   form.hidden=key!==method;
   form.setAttribute('role','tabpanel');
   form.setAttribute('aria-labelledby','sy-login-tab-'+key);
  }
  Object.entries(tabButtons).forEach(([key,button])=>{
   const active=key===method;
   button.setAttribute('aria-selected',String(active));
   button.tabIndex=active?0:-1;
  });
  if(focus)tabButtons[method]?.focus();
 };
 const render=(enabled)=>{
  available=Object.keys(labels).filter(x=>Boolean(enabled[x]));
  if(!available.length)available=['password'];
  tabs.replaceChildren();
  for(const method of available){
   const button=make('button','sy-login-method-tab');
   button.type='button';button.id='sy-login-tab-'+method;button.setAttribute('role','tab');
   button.textContent=icons[method]+' '+labels[method];
   button.onclick=()=>activate(method,true);
   button.onkeydown=e=>{
    if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key))return;
    e.preventDefault();const i=available.indexOf(method);
    const next=e.key==='Home'?0:e.key==='End'?available.length-1:(i+(e.key==='ArrowLeft'?1:-1)+available.length)%available.length;
    activate(available[next],true);
   };
   tabs.append(button);tabButtons[method]=button;
  }
  tabs.hidden=available.length===1;
  activate(available.includes(selected)?selected:available[0]);
  const heading=root.querySelector('.login-heading p');
  if(heading)heading.textContent=available.length>1?'روش ورود دلخواه خود را انتخاب کنید.':'برای ورود، اطلاعات حساب خود را وارد کنید.';
 };
 // Hide unused OTP methods until the public policy has been loaded.
 panels.sms.hidden=true;panels.bale.hidden=true;
 async function reload(){
  try{const result=await api('/api/v1/auth/methods');render(result.methods||{password:true});}
  catch(e){render({password:true});}
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
  const title=make('h3','', 'مدیریت روش‌های ورود کاربران');
  const description=make('p','','هر روش انتخاب‌شده در صفحه ورود نمایش داده می‌شود. روش‌های غیرفعال از طریق API نیز مسدود هستند؛ حداقل یک روش فعال لازم است.');
  const form=make('form','sy-login-policy-form');
  const checks={};
  for(const key of Object.keys(labels)){
   const row=make('label','sy-login-policy-option');
   const checkbox=make('input');checkbox.type='checkbox';checkbox.name=key;
   const desc=make('span');desc.textContent=labels[key];row.append(checkbox,desc);
   checks[key]=checkbox;form.append(row);
  }
  const submit=make('button','primary','ذخیره روش‌های ورود');submit.type='submit';
  const result=make('p','sy-login-policy-status');result.setAttribute('role','status');
  form.append(submit,result);block.append(title,description,form);parent.append(block);
  const refresh=async()=>{
   const status=await api('/api/v1/admin/login-methods');
   if(!block.isConnected)return;
   for(const key of Object.keys(checks)){
    checks[key].checked=Boolean(status.configured[key]);
    if(key!=='password'){
     checks[key].parentElement.title=status.available[key]?'سرویس آماده است':'این روش نیازمند تنظیم سرویس ارسال کد است';
    }
   }
  };
  form.onsubmit=async e=>{
   e.preventDefault();
   if(!Object.values(checks).some(x=>x.checked)){note(result,'حداقل یک روش ورود را انتخاب کنید.',true);return;}
   submit.disabled=true;note(result,'در حال ذخیره…');
   try{
    const data=Object.fromEntries(Object.entries(checks).map(([k,v])=>[k,v.checked]));
    await api('/api/v1/admin/login-methods',{method:'PUT',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(data)});
    note(result,'تنظیمات ذخیره شد و روی API اعمال می‌شود.');
    await refresh();
   }catch(e){note(result,e.message,true);}
   finally{submit.disabled=false;}
  };
  try{await refresh();}catch(e){note(result,e.message,true);}
 };
})();