/* ServiceYar Bale bot: admin configuration, user linking, login and scoped messaging. */
'use strict';
(function(){
 const el=(tag,cls,text)=>{const node=document.createElement(tag);if(cls)node.className=cls;if(text!==undefined)node.textContent=text;return node;};
 const message=(node,text,invalid=false)=>{node.textContent=text;node.classList.toggle('error',invalid);};
 const normalizeUsername=value=>String(value||'').trim().replace(/[۰-۹]/g,ch=>String(ch.charCodeAt(0)-1776)).replace(/[٠-٩]/g,ch=>String(ch.charCodeAt(0)-1632));
 const sessionError=error=>/نشست معتبر نیست|نشست منقضی|ورود انجام نشده|UNAUTHENTICATED/i.test(String(error?.message||''));
 const showLinkError=(node,error)=>message(node,sessionError(error)?'نشست شما منقضی شده است. برای دریافت کد اتصال بله، دوباره با نام کاربری و رمز عبور وارد سامانه شوید.':error.message,true);
 async function initializeLogin(){
  const card=document.querySelector('.login-card'),credentials=document.getElementById('loginForm');
  if(!card||!credentials)return;
  let status;
  try{status=await api('/api/v1/auth/bale/status');}catch(_){return;}
  if(!status?.enabled||document.getElementById('syBaleLogin'))return;
  const wrapper=el('section','sy-bale-login');wrapper.id='syBaleLogin';
  const toggle=el('button','sy-bale-login-toggle','ورود با پیام‌رسان بله');
  toggle.type='button';toggle.setAttribute('aria-expanded','false');
  const form=el('form','sy-bale-login-form');form.hidden=true;
  form.innerHTML='<p>نام کاربری سامانه را وارد کنید؛ کد ورود به گفتگوی بله متصل به حساب ارسال می‌شود.</p>'+
   '<label>نام کاربری<input name="username" maxlength="100" autocomplete="username" required></label>'+
   '<button type="button" data-request-otp class="primary">ارسال کد ورود به بله</button>'+
   '<label>کد یک‌بارمصرف<input name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9۰-۹]{6}" required></label>'+
   '<button class="primary" type="submit">تأیید و ورود</button><p data-bale-login-message role="status"></p>';
  wrapper.append(toggle,form);credentials.after(wrapper);
  toggle.onclick=()=>{form.hidden=!form.hidden;toggle.setAttribute('aria-expanded',String(!form.hidden));};
  const feedback=form.querySelector('[data-bale-login-message]');
  form.querySelector('[data-request-otp]').onclick=async e=>{
   const button=e.currentTarget;button.disabled=true;
   try{await api('/api/v1/auth/bale/request',{method:'POST',body:JSON.stringify({username:normalizeUsername(form.elements.username.value)})});message(feedback,'اگر حساب به بله متصل باشد، کد ورود ارسال شده است. اعتبار کد ۵ دقیقه است.');}
   catch(err){message(feedback,err.message,true);}
   finally{button.disabled=false;}
  };
  form.onsubmit=async e=>{
   e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;
   try{
    const code=String(form.elements.code.value).replace(/[۰-۹]/g,ch=>String(ch.charCodeAt(0)-1776));
    await api('/api/v1/auth/bale/verify',{method:'POST',body:JSON.stringify({username:normalizeUsername(form.elements.username.value),code})});
    message(feedback,'ورود تأیید شد.');
    await boot();
   }catch(err){message(feedback,err.message,true);}
   finally{button.disabled=false;}
  };
 }
 const previousCompaniesPage=companiesPage;
 companiesPage=async function(){
  await previousCompaniesPage();
  if(!session?.roles?.includes('super_admin'))return;
  const target=document.getElementById('syAdminIntegrations');
  if(!target||target.querySelector('#syBaleAdminForm'))return;
  const block=el('section','sy-bale-admin-panel');
  block.innerHTML='<h3>ربات پیام‌رسان بله سرویس‌یار</h3><p>توکن به‌صورت رمزگذاری‌شده در سرور ذخیره می‌شود و در پاسخ API نمایش داده نمی‌شود. پس از ذخیره، وب‌هوک HTTPS به‌صورت خودکار ثبت می‌شود.</p>'+
   '<form id="syBaleAdminForm"><div class="form-grid"><label>توکن ربات بله<input name="token" type="password" autocomplete="new-password" placeholder="برای حفظ توکن فعلی خالی بگذارید"></label><label>توکن ارائه‌دهنده کیف پول بله<input name="walletToken" type="password" autocomplete="new-password" placeholder="برای حفظ توکن پرداخت فعلی خالی بگذارید"></label><label class="sy-bale-checkbox"><input name="enabled" type="checkbox" value="1"> فعال‌سازی ربات و ورود با بله</label><label class="sy-bale-checkbox"><input name="walletEnabled" type="checkbox" value="1"> فعال‌سازی درگاه پرداخت کیف پول بله</label></div>'+
   '<button class="primary" type="submit">ذخیره و اتصال ربات بله</button><button type="button" data-bale-refresh>بررسی وضعیت</button><p data-bale-settings-message role="status"></p><div data-bale-overview></div>'+
   '<fieldset class="sy-bale-user-check"><legend>عیب‌یابی ارسال کد ورود</legend><label>نام کاربری<input name="checkUsername" autocomplete="off" placeholder="کاربر موردنظر"></label><button type="button" data-bale-user-check>بررسی اتصال کاربر</button><p data-bale-user-result role="status"></p></fieldset></form>';
  target.append(block);
  const form=block.querySelector('form'),status=block.querySelector('[data-bale-settings-message]'),overview=block.querySelector('[data-bale-overview]');
  const refresh=async()=>{
   const info=await api('/api/v1/admin/bale-bot');
   if(!form.isConnected)return;
   form.elements.enabled.checked=Boolean(info.configured);
   form.elements.walletEnabled.checked=Boolean(info.walletEnabled);
   overview.textContent='توکن ربات: '+(info.tokenStored?'ثبت شده':'ثبت نشده')+' | توکن پرداخت: '+(info.walletTokenStored?'ثبت شده':'ثبت نشده')+' | کیف پول: '+(info.walletConfigured?'آماده':'آماده نیست')+' | ربات: '+(info.botUsername?'@'+info.botUsername:'نامشخص')+' | وب‌هوک: '+(info.webhookRegistered?'ثبت شده':'ثبت نشده');
  };
  form.querySelector('[data-bale-user-check]').onclick=async()=>{
   const result=form.querySelector('[data-bale-user-result]'),button=form.querySelector('[data-bale-user-check]'),username=normalizeUsername(form.elements.checkUsername.value);
   if(!username){message(result,'نام کاربری را وارد کنید.',true);return;}
   button.disabled=true;
   try{
    const info=await api('/api/v1/admin/bale-bot/user-link?username='+encodeURIComponent(username));
    message(result,!info.userFound?'کاربری با این نام ثبت نشده است.':!info.userActive?'حساب کاربر غیرفعال است.':!info.baleLinked?'حساب بله کاربر هنوز متصل نشده است؛ ابتدا از پروفایل دستور اتصال را به ربات ارسال کند.':!info.botEnabled?'حساب بله متصل است اما ربات ورود فعال نیست.':'حساب کاربر فعال و به بله متصل است؛ امکان درخواست کد ورود وجود دارد.',!info.userFound||!info.userActive||!info.baleLinked||!info.botEnabled);
   }catch(e){message(result,e.message,true);}
   finally{button.disabled=false;}
  };
  form.querySelector('[data-bale-refresh]').onclick=async()=>{try{await refresh();const health=await api('/api/v1/admin/bale-bot/check');message(status,health.connected?'اتصال به بله برقرار است. وب‌هوک: '+(health.webhookCorrect?'صحیح':'نیازمند اصلاح')+'؛ پیام‌های در انتظار: '+Number(health.pendingUpdates||0)+(health.lastError?'؛ آخرین خطا: '+health.lastError:''):'ربات هنوز فعال نشده است.',!health.webhookCorrect);}catch(e){message(status,e.message,true);}};
  form.onsubmit=async e=>{
   e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;
   try{
    await api('/api/v1/admin/bale-bot',{method:'PUT',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({token:form.elements.token.value.trim(),walletToken:form.elements.walletToken.value.trim(),enabled:form.elements.enabled.checked,walletEnabled:form.elements.walletEnabled.checked})});
    form.elements.token.value='';form.elements.walletToken.value='';await refresh();message(status,'تنظیمات ربات ذخیره و درخواست ثبت وب‌هوک ارسال شد.');
   }catch(e){message(status,e.message,true);}
   finally{button.disabled=false;}
  };
  try{await refresh();}catch(e){message(status,e.message,true);}
 };
 const previousProfileDialog=ownProfileDialog;
 ownProfileDialog=async function(){
  await previousProfileDialog();
  const form=document.getElementById('syOwnProfileForm');if(!form)return;
  const block=el('section','sy-bale-profile-panel');
  block.innerHTML='<h4>اتصال حساب به پیام‌رسان بله</h4><p>پس از اتصال، ورود یک‌بارمصرف و پیام‌های سامانه را در بله دریافت می‌کنید.</p>'+
   '<div class="sy-bale-actions"><button type="button" data-link-bale>دریافت کد اتصال</button><button type="button" data-unlink-bale>قطع اتصال</button></div><p data-link-message role="status"></p>';
  form.querySelector('button[type=submit]')?.before(block);
  const feedback=block.querySelector('[data-link-message]');
  const refresh=async()=>{
   const info=await api('/api/v1/bale/link');
   if(!block.isConnected)return;
   message(feedback,info.linked?'حساب شما به بله متصل است.':info.enabled?'حساب بله هنوز متصل نیست.':'ربات بله توسط مدیر سامانه فعال نشده است.');
   block.querySelector('[data-link-bale]').disabled=!info.enabled;
   block.querySelector('[data-unlink-bale]').disabled=!info.linked;
  };
  block.querySelector('[data-link-bale]').onclick=async()=>{
   try{
    const data=await api('/api/v1/bale/link',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:'{}'});
    const command='/start '+data.code;
    feedback.replaceChildren(document.createTextNode('در ربات '+(data.botUsername?'@'+data.botUsername:'سرویس‌یار')+' دستور زیر را ارسال کنید (اعتبار ۱۰ دقیقه): '));
    const code=el('code','',command);feedback.append(code);
    const copy=el('button','','کپی دستور');copy.type='button';copy.onclick=()=>navigator.clipboard?.writeText(command);feedback.append(copy);
   }catch(e){showLinkError(feedback,e);}
  };
  block.querySelector('[data-unlink-bale]').onclick=async()=>{
   if(!confirm('اتصال حساب به بله قطع شود؟'))return;
   try{await api('/api/v1/bale/link',{method:'DELETE',headers:{'X-CSRF-Token':session.csrfToken}});await refresh();}
   catch(e){message(feedback,e.message,true);}
  };
  try{await refresh();}catch(e){showLinkError(feedback,e);}
 };
 const previousSettingsPage=settingsPage;
 settingsPage=async function(){
  await previousSettingsPage();
  if(!session||!can('users','manage')||!document.getElementById('companyUserRows'))return;
  const usersPanel=document.getElementById('companyUserRows')?.closest('section');
  if(!usersPanel||usersPanel.querySelector('#syBaleMessageForm'))return;
  const section=el('section','sy-bale-message-panel');
  section.innerHTML='<h3>ارسال پیام از طریق ربات بله</h3><p>پیام فقط به حساب بله متصل‌شده کاربر شرکت ارسال می‌شود.</p>'+
   '<form id="syBaleMessageForm"><label>گیرنده<select name="recipientId" required><option value="">انتخاب کاربر</option></select></label>'+
   '<label>متن پیام<textarea name="text" maxlength="3000" rows="3" required></textarea></label><button class="primary" type="submit">ارسال پیام در بله</button><p data-bale-message-status role="status"></p></form>';
  usersPanel.append(section);
  const form=section.querySelector('form');
  try{
   const rows=await api(scoped('/api/v1/users'));
   if(!form.isConnected)return;
   rows.forEach(row=>{const option=document.createElement('option');option.value=String(row.id);option.textContent=[row.first_name,row.last_name].filter(Boolean).join(' ')||row.username;form.elements.recipientId.append(option);});
  }catch(e){message(form.querySelector('[data-bale-message-status]'),e.message,true);}
  form.onsubmit=async event=>{
   event.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;
   const feedback=form.querySelector('[data-bale-message-status]');
   try{
    await api('/api/v1/bale/message',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({recipientId:Number(form.elements.recipientId.value),text:form.elements.text.value})});
    form.elements.text.value='';message(feedback,'پیام به بله ارسال شد.');
   }catch(e){message(feedback,e.message,true);}
   finally{button.disabled=false;}
  };
 };
 void initializeLogin();
})();
