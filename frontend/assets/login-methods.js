/* ServiceYar login: policy-driven method tabs, OTP timers and account recovery. */
'use strict';
(() => {
 const labels={password:'نام کاربری و رمز عبور',sms:'ورود با پیامک',bale:'ورود با ربات بله',mobile:'ورود با موبایل و کد'};
 const icons={password:'♙',sms:'✉',bale:'◈',mobile:'▣'};
 const normalize=value=>String(value||'').trim().replace(/[۰-۹]/g,c=>String(c.charCodeAt(0)-1776)).replace(/[٠-٩]/g,c=>String(c.charCodeAt(0)-1632));
 const make=(name,cls,text)=>{const el=document.createElement(name);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
 const note=(node,text,error=false)=>{node.textContent=text||'';node.classList.toggle('error',error);};
 const faDigits=value=>String(value).replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
 const timerText=seconds=>faDigits(String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0'));
 const root=document.querySelector('.login-card'),passwordForm=document.getElementById('loginForm');
 if(!root||!passwordForm)return;

 const slot=make('section','sy-login-methods');slot.id='syLoginMethods';
 const tabs=make('div','sy-login-method-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','روش ورود به سامانه');tabs.setAttribute('aria-orientation','horizontal');
 const state=make('p','sy-login-method-state','در حال دریافت روش‌های مجاز ورود…');state.setAttribute('role','status');state.setAttribute('aria-live','polite');
 const panelsHost=make('div','sy-login-method-panels');
 slot.append(tabs,state,panelsHost);
 passwordForm.hidden=true;passwordForm.classList.add('sy-login-method-panel');
 passwordForm.id='syLoginPanelPassword';passwordForm.setAttribute('role','tabpanel');
 passwordForm.before(slot);panelsHost.append(passwordForm);
 const panels={password:passwordForm},tabButtons={};
 let available=[],selected='password';

 const makeTimer=(container,send,verify,code,info)=>{
  let interval=null,remaining=0,cooldown=0;
  const timer=make('p','sy-login-otp-timer');timer.hidden=true;container.append(timer);
  const stop=()=>{if(interval)clearInterval(interval);interval=null;};
  const tick=()=>{
   remaining=Math.max(0,remaining-1);cooldown=Math.max(0,cooldown-1);
   if(remaining===0){
    stop();send.disabled=false;send.textContent='ارسال مجدد کد';verify.disabled=true;code.disabled=true;
    note(info,'اعتبار کد پایان یافته است؛ برای دریافت کد جدید، دوباره درخواست دهید.',true);
   }else{
    send.disabled=cooldown>0;
    send.textContent=cooldown>0?'ارسال مجدد کد':'ارسال مجدد کد';
    timer.textContent='اعتبار کد: '+timerText(remaining)+(cooldown>0?' · ارسال مجدد تا '+timerText(cooldown):' · امکان ارسال مجدد وجود دارد');
   }
  };
  const start=(seconds=300)=>{
   stop();remaining=Math.max(1,seconds);cooldown=Math.min(60,remaining);
   code.disabled=false;verify.disabled=false;send.disabled=true;send.textContent='ارسال مجدد کد';
   timer.hidden=false;timer.textContent='اعتبار کد: '+timerText(remaining)+' · ارسال مجدد تا '+timerText(cooldown);
   interval=setInterval(tick,1000);
  };
  return {start,stop};
 };

 const createOtp=(method)=>{
  const form=make('form','sy-login-otp-form sy-login-method-panel');
  form.id='syLoginPanel'+({sms:'Sms',bale:'Bale',mobile:'Mobile'}[method]);form.hidden=true;form.setAttribute('role','tabpanel');
  const mobile=method==='mobile';
  const user=make('label','sy-login-field');user.textContent=mobile?'شماره همراه':'نام کاربری';
  const identifier=make('input');identifier.name=mobile?'mobile':'username';identifier.required=true;identifier.maxLength=mobile?15:100;
  identifier.autocomplete=mobile?'tel':'username';identifier.inputMode=mobile?'tel':'text';
  identifier.placeholder=mobile?'09xxxxxxxxx':'نام کاربری خود را وارد کنید';user.append(identifier);
  form.append(user);

  if(method==='bale'){
   const intro=make('p','sy-login-otp-intro','پس از وارد کردن نام کاربری، کد یک‌بارمصرف برای حساب بله متصل به همان کاربر ارسال می‌شود.');
   const botLink=make('a','sy-login-bot-link','ورود به ربات syarbot در بله');
   botLink.href='https://ble.ir/syarbot';botLink.target='_blank';botLink.rel='noopener noreferrer';
   botLink.setAttribute('aria-label','باز کردن ربات syarbot در پیام‌رسان بله');
   const backHint=make('p','sy-login-bot-return','اگر هنوز حساب خود را متصل نکرده‌اید، ربات را باز کنید، دکمه «شروع» (Start) و سپس «اشتراک شماره همراه من» را بزنید و بعد به این صفحه بازگردید.');
   form.append(intro,botLink,backHint);
  }else{
   const intro=make('p','sy-login-otp-intro',mobile?'کد ورود به شماره همراه ثبت‌شده در پروفایل شما ارسال می‌شود.':'کد یک‌بارمصرف به شماره همراه ثبت‌شده در پروفایل کاربر پیامک می‌شود.');
   form.append(intro);
  }

  const send=make('button','sy-login-otp-send','دریافت کد ورود');send.type='button';
  const timerHost=make('div','sy-login-otp-timer-host');
  const codeLabel=make('label','sy-login-field');codeLabel.textContent='کد تأیید ۶ رقمی';codeLabel.hidden=true;
  const code=make('input');code.name='code';code.required=true;code.maxLength=6;code.inputMode='numeric';
  code.autocomplete='one-time-code';code.placeholder='ــــــ';code.pattern='[0-9۰-۹٠-٩]{6}';code.dir='ltr';code.disabled=true;codeLabel.append(code);
  const verify=make('button','sy-login-otp-confirm','تأیید کد و ورود');verify.type='submit';verify.hidden=true;verify.disabled=true;
  const info=make('p','sy-login-otp-message');info.setAttribute('role','status');info.setAttribute('aria-live','polite');
  form.append(send,timerHost,codeLabel,verify,info);
  const timer=makeTimer(timerHost,send,verify,code,info);
  send.onclick=async()=>{
   const value=normalize(identifier.value);
   if(!value){note(info,mobile?'شماره همراه را وارد کنید.':'ابتدا نام کاربری را وارد کنید.',true);identifier.focus();return;}
   if(mobile&&!/^09[0-9]{9}$/.test(value)){note(info,'شماره همراه باید ۱۱ رقم و با ۰۹ آغاز شود.',true);identifier.focus();return;}
   send.disabled=true;verify.disabled=true;code.value='';note(info,'در حال ارسال درخواست…');
   try{
    const body=mobile?{mobile:value}:{username:value};
    await api('/api/v1/auth/'+method+'/request',{method:'POST',body:JSON.stringify(body)});
    code.value='';codeLabel.hidden=false;verify.hidden=false;timer.start(300);
    note(info,'اگر حساب فعال و اطلاعات تماس معتبر باشد، کد ارسال شده است. کد تا ۵ دقیقه اعتبار دارد.');
    code.focus();
   }catch(e){send.disabled=false;verify.disabled=codeLabel.hidden;note(info,e.message||'ارسال کد انجام نشد.',true);}
  };
  form.onsubmit=async e=>{
   e.preventDefault();const value=normalize(identifier.value),otp=normalize(code.value);
   if(!value||!/^[0-9]{6}$/.test(otp)){note(info,'اطلاعات و کد ۶ رقمی معتبر وارد کنید.',true);return;}
   verify.disabled=true;note(info,'در حال بررسی کد…');
   try{
    const body=mobile?{mobile:value,code:otp}:{username:value,code:otp};
    await api('/api/v1/auth/'+method+'/verify',{method:'POST',body:JSON.stringify(body)});
    timer.stop();code.value='';note(info,'ورود تأیید شد.');await boot();
   }catch(err){note(info,err.message||'تأیید کد انجام نشد.',true);verify.disabled=false;}
  };
  return form;
 };
 panels.sms=createOtp('sms');panels.bale=createOtp('bale');panels.mobile=createOtp('mobile');
 panelsHost.append(panels.sms,panels.bale,panels.mobile);
 // Parent/driver sign-in shares this exact login card and the same session cookie.
 // Staff login policies remain configured independently of the portal role.
 const portalForm=make('form','sy-login-otp-form sy-login-method-panel sy-portal-signin');
 portalForm.id='syLoginPanelPortal';portalForm.hidden=true;portalForm.setAttribute('role','tabpanel');
 const portalIntro=make('p','sy-login-otp-intro','والدین و رانندگان با شماره همراه ثبت‌شده در شرکت وارد می‌شوند. برای اولین ورود، ربات بله را آغاز و شماره خود را در همان گفتگو به اشتراک بگذارید.');
 const portalBot=make('a','sy-login-bot-link','شروع ربات syarbot در بله');portalBot.href='https://ble.ir/syarbot';portalBot.target='_blank';portalBot.rel='noopener noreferrer';
 const portalMobileLabel=make('label','sy-login-field','شماره همراه ثبت‌شده');
 const portalMobile=make('input');portalMobile.name='mobile';portalMobile.type='tel';portalMobile.inputMode='tel';portalMobile.placeholder='09123456789';portalMobile.maxLength=15;portalMobile.required=true;portalMobile.autocomplete='username';portalMobile.dir='ltr';portalMobileLabel.append(portalMobile);
 const portalModeRow=make('div','sy-portal-login-options');
 const portalBaleButton=make('button','sy-portal-login-option active','کد ورود بله'),portalPasswordButton=make('button','sy-portal-login-option','رمز عبور');
 portalBaleButton.type='button';portalPasswordButton.type='button';portalModeRow.append(portalBaleButton,portalPasswordButton);
 const portalCodeLabel=make('label','sy-login-field','کد ۶ رقمی بله');
 const portalCode=make('input');portalCode.name='code';portalCode.inputMode='numeric';portalCode.autocomplete='one-time-code';portalCode.maxLength=6;portalCode.disabled=true;portalCode.dir='ltr';portalCodeLabel.append(portalCode);
 const portalPasswordLabel=make('label','sy-login-field','رمز عبور');portalPasswordLabel.hidden=true;
 const portalPassword=make('input');portalPassword.type='password';portalPassword.autocomplete='current-password';portalPassword.minLength=10;portalPasswordLabel.append(portalPassword);
 const portalSend=make('button','sy-login-otp-send','دریافت کد ورود بله');portalSend.type='button';
 const portalVerify=make('button','sy-login-otp-confirm','تأیید کد و ورود');portalVerify.type='submit';portalVerify.disabled=true;
 const portalTimerHost=make('div','sy-login-otp-timer-host');
 const portalInfo=make('p','sy-login-otp-message');portalInfo.setAttribute('role','status');portalInfo.setAttribute('aria-live','polite');
 portalForm.append(portalIntro,portalBot,portalMobileLabel,portalModeRow,portalCodeLabel,portalPasswordLabel,portalSend,portalTimerHost,portalVerify,portalInfo);
 panelsHost.append(portalForm);panels.portal=portalForm;
 const portalTimer=makeTimer(portalTimerHost,portalSend,portalVerify,portalCode,portalInfo);
 let portalMode='bale',portalBaleAvailable=false;
 const switchPortalMode=value=>{
   portalMode=value;const byBale=value==='bale';
   portalBaleButton.classList.toggle('active',byBale);portalPasswordButton.classList.toggle('active',!byBale);
   portalCodeLabel.hidden=!byBale;portalPasswordLabel.hidden=byBale;
   portalSend.hidden=!byBale;portalBot.hidden=!byBale;
   portalVerify.disabled=byBale;portalVerify.textContent=byBale?'تأیید کد و ورود':'ورود با رمز عبور';
   if(!byBale)portalTimer.stop();
   if(byBale&&!portalBaleAvailable)note(portalInfo,'ورود با کد بله در حال حاضر فعال نیست. در صورت تعیین رمز اختصاصی، از ورود با رمز استفاده کنید.',true);
   else note(portalInfo,'');
 };
 portalBaleButton.onclick=()=>switchPortalMode('bale');
 portalPasswordButton.onclick=()=>switchPortalMode('password');
 portalSend.onclick=async()=>{
   const mobile=normalize(portalMobile.value);
   if(!/^09[0-9]{9}$/.test(mobile)){note(portalInfo,'شماره همراه را با ۰۹ و ۱۱ رقم وارد کنید.',true);return;}
   portalSend.disabled=true;portalCode.value='';note(portalInfo,'در حال دریافت کد از ربات بله…');
   try{
     await api('/api/v1/auth/bale/request',{method:'POST',body:JSON.stringify({username:mobile})});
     portalTimer.start(300);note(portalInfo,'در صورت ثبت شماره و اتصال ربات بله، کد ورود در همان گفتگو ارسال می‌شود.');
     portalCode.focus();
   }catch(error){portalSend.disabled=false;note(portalInfo,error.message||'دریافت کد ناموفق بود.',true);}
 };
 portalForm.onsubmit=async event=>{
   event.preventDefault();const mobile=normalize(portalMobile.value);
   if(!/^09[0-9]{9}$/.test(mobile)){note(portalInfo,'شماره همراه معتبر نیست.',true);return;}
   const isBale=portalMode==='bale';
   const payload=isBale?{username:mobile,code:normalize(portalCode.value)}:{username:mobile,password:portalPassword.value};
   if(isBale&&!/^[0-9]{6}$/.test(payload.code)){note(portalInfo,'کد ۶ رقمی را وارد کنید.',true);return;}
   if(!isBale&&!payload.password){note(portalInfo,'رمز عبور را وارد کنید.',true);return;}
   portalVerify.disabled=true;note(portalInfo,'در حال بررسی حساب…');
   try{
     await api(isBale?'/api/v1/auth/bale/verify':'/api/v1/portal/auth/password',{method:'POST',body:JSON.stringify(payload)});
     portalTimer.stop();portalPassword.value='';portalCode.value='';await boot();
   }catch(error){note(portalInfo,error.message||'ورود به پنل ناموفق بود.',true);portalVerify.disabled=false;}
 };


 // Password recovery is a sub-flow inside the password tab, not a separate login method.
 const recoveryLink=make('button','sy-password-forgot','فراموشی رمز عبور؟');recoveryLink.type='button';recoveryLink.hidden=true;
 const passwordSuccess=make('p','sy-login-otp-message');passwordSuccess.hidden=true;
 const recovery=make('section','sy-password-recovery');recovery.hidden=true;
 const recoveryTitle=make('h3','','بازیابی رمز عبور');
 const recoveryIntro=make('p','sy-login-otp-intro','نام کاربری را وارد کنید و روش دریافت کد بازیابی را انتخاب نمایید. پس از تأیید کد می‌توانید رمز جدید تعیین کنید.');
 const recoveryChannels=make('div','sy-recovery-channels');recoveryChannels.setAttribute('role','group');recoveryChannels.setAttribute('aria-label','روش دریافت کد بازیابی');
 const recoveryUser=make('label','sy-login-field');recoveryUser.textContent='نام کاربری';
 const recoveryUsername=make('input');recoveryUsername.autocomplete='username';recoveryUsername.maxLength=100;recoveryUsername.placeholder='نام کاربری حساب';recoveryUser.append(recoveryUsername);
 const recoverySend=make('button','sy-login-otp-send','ارسال کد بازیابی');recoverySend.type='button';
 const recoveryTimerHost=make('div','sy-login-otp-timer-host');
 const recoveryCodeLabel=make('label','sy-login-field');recoveryCodeLabel.textContent='کد بازیابی ۶ رقمی';recoveryCodeLabel.hidden=true;
 const recoveryCode=make('input');recoveryCode.inputMode='numeric';recoveryCode.maxLength=6;recoveryCode.autocomplete='one-time-code';recoveryCode.dir='ltr';recoveryCode.placeholder='ــــــ';recoveryCodeLabel.append(recoveryCode);
 const newPasswordLabel=make('label','sy-login-field');newPasswordLabel.textContent='رمز عبور جدید';newPasswordLabel.hidden=true;
 const newPassword=make('input');newPassword.type='password';newPassword.autocomplete='new-password';newPassword.minLength=8;newPassword.placeholder='حداقل ۸ کاراکتر';newPasswordLabel.append(newPassword);
 const confirmPasswordLabel=make('label','sy-login-field');confirmPasswordLabel.textContent='تکرار رمز عبور جدید';confirmPasswordLabel.hidden=true;
 const confirmPassword=make('input');confirmPassword.type='password';confirmPassword.autocomplete='new-password';confirmPassword.minLength=8;confirmPassword.placeholder='رمز جدید را دوباره وارد کنید';confirmPasswordLabel.append(confirmPassword);
 const recoveryConfirm=make('button','sy-login-otp-confirm','ثبت رمز جدید');recoveryConfirm.type='button';recoveryConfirm.hidden=true;recoveryConfirm.disabled=true;
 const recoveryBack=make('button','sy-recovery-back','بازگشت به ورود');recoveryBack.type='button';
 const recoveryInfo=make('p','sy-login-otp-message');recoveryInfo.setAttribute('role','status');recoveryInfo.setAttribute('aria-live','polite');
 recovery.append(recoveryTitle,recoveryIntro,recoveryChannels,recoveryUser,recoverySend,recoveryTimerHost,recoveryCodeLabel,newPasswordLabel,confirmPasswordLabel,recoveryConfirm,recoveryBack,recoveryInfo);
 passwordForm.append(recoveryLink,passwordSuccess,recovery);
 let recoveryChannel='';
 const recoveryTimer=makeTimer(recoveryTimerHost,recoverySend,recoveryConfirm,recoveryCode,recoveryInfo);
 const togglePasswordForm=show=>{
  for(const selector of ['#username','#password','.login-help','#loginSubmit','#loginError']){
   const el=passwordForm.querySelector(selector);if(el)el.hidden=!show;
  }
  const labels=passwordForm.querySelectorAll(':scope > label');labels.forEach(el=>el.hidden=!show);
  const wraps=passwordForm.querySelectorAll(':scope > .login-input-wrap');wraps.forEach(el=>el.hidden=!show);
  recoveryLink.hidden=show?!(recoveryMethodsFromServer.sms||recoveryMethodsFromServer.bale):true;recovery.hidden=show;
 };
 recoveryLink.onclick=()=>{
  passwordSuccess.hidden=true;togglePasswordForm(false);
  recoveryChannels.replaceChildren();
  const choices=['sms','bale'].filter(key=>recoveryMethodsFromServer[key]);
  if(!choices.length){note(recoveryInfo,'روش بازیابی رمز عبور در حال حاضر فعال نیست.',true);return;}
  choices.forEach(channel=>{
   const button=make('button','sy-recovery-channel',channel==='sms'?'پیامک':'ربات بله');button.type='button';button.dataset.channel=channel;
   button.onclick=()=>selectRecoveryChannel(channel);recoveryChannels.append(button);
  });
  selectRecoveryChannel(choices[0]);recoveryUsername.value='';recoveryCode.value='';newPassword.value='';confirmPassword.value='';
  recoveryCodeLabel.hidden=true;newPasswordLabel.hidden=true;confirmPasswordLabel.hidden=true;recoveryConfirm.hidden=true;recoveryConfirm.disabled=true;recoverySend.hidden=false;recoverySend.disabled=false;recoverySend.textContent='ارسال کد بازیابی';recoveryTimer.stop();recoveryTimerHost.querySelector('.sy-login-otp-timer').hidden=true;
  note(recoveryInfo,'');
 };
 let recoveryMethodsFromServer={sms:false,bale:false};
 const selectRecoveryChannel=channel=>{
  recoveryChannel=channel;
  recoveryChannels.querySelectorAll('button').forEach(button=>button.classList.toggle('active',button.dataset.channel===channel));
 };
 recoverySend.onclick=async()=>{
  const username=normalize(recoveryUsername.value);
  if(!username){note(recoveryInfo,'نام کاربری را وارد کنید.',true);recoveryUsername.focus();return;}
  if(!recoveryChannel){note(recoveryInfo,'روش دریافت کد را انتخاب کنید.',true);return;}
  recoverySend.disabled=true;recoveryConfirm.disabled=true;recoveryCode.value='';note(recoveryInfo,'در حال ارسال کد بازیابی…');
  try{
   await api('/api/v1/auth/password-reset/request',{method:'POST',body:JSON.stringify({username,channel:recoveryChannel})});
   recoveryCodeLabel.hidden=false;newPasswordLabel.hidden=false;confirmPasswordLabel.hidden=false;recoveryConfirm.hidden=false;
   recoveryTimer.start(300);note(recoveryInfo,'در صورت وجود حساب فعال و اتصال معتبر، کد بازیابی ارسال شده است. کد تا ۵ دقیقه اعتبار دارد.');
   recoveryCode.focus();
  }catch(e){recoverySend.disabled=false;recoveryConfirm.disabled=recoveryCodeLabel.hidden;note(recoveryInfo,e.message||'ارسال کد بازیابی انجام نشد.',true);}
 };
 recoveryConfirm.onclick=async()=>{
  const username=normalize(recoveryUsername.value),code=normalize(recoveryCode.value);
  if(!username||!/^[0-9]{6}$/.test(code)){note(recoveryInfo,'نام کاربری و کد ۶ رقمی معتبر وارد کنید.',true);return;}
  if(newPassword.value.length<8){note(recoveryInfo,'رمز جدید باید حداقل ۸ کاراکتر باشد.',true);newPassword.focus();return;}
  if(newPassword.value!==confirmPassword.value){note(recoveryInfo,'تکرار رمز عبور یکسان نیست.',true);confirmPassword.focus();return;}
  recoveryConfirm.disabled=true;note(recoveryInfo,'در حال ثبت رمز جدید…');
  try{
   await api('/api/v1/auth/password-reset/confirm',{method:'POST',body:JSON.stringify({username,channel:recoveryChannel,code,newPassword:newPassword.value,passwordConfirmation:confirmPassword.value})});
   recoveryTimer.stop();recoveryCode.value='';newPassword.value='';confirmPassword.value='';
   togglePasswordForm(true);passwordSuccess.textContent='رمز عبور با موفقیت تغییر کرد. اکنون با رمز جدید وارد شوید.';passwordSuccess.hidden=false;
  }catch(e){note(recoveryInfo,e.message||'تغییر رمز عبور انجام نشد.',true);recoveryConfirm.disabled=false;}
 };
 recoveryBack.onclick=()=>{recoveryTimer.stop();togglePasswordForm(true);const error=document.getElementById('loginError');if(error){note(error,'');error.hidden=true;}};
 [document.getElementById('username'),document.getElementById('password')].forEach(input=>input?.addEventListener('input',()=>{passwordSuccess.hidden=true;}));
 [recoveryUsername,recoveryCode,newPassword,confirmPassword].forEach(input=>input.addEventListener('keydown',event=>{
  if(event.key!=='Enter')return;
  event.preventDefault();
  if(input===recoveryUsername)recoverySend.click();
  else recoveryConfirm.click();
 }));

 const activate=(method,focus=false)=>{
  if(!available.includes(method))return;
  selected=method;
  for(const [key,panel] of Object.entries(panels)){panel.hidden=key!==method;panel.setAttribute('aria-labelledby','sy-login-tab-'+key);}
  for(const [key,button] of Object.entries(tabButtons)){const active=key===method;button.setAttribute('aria-selected',String(active));button.tabIndex=active?0:-1;}
  if(focus)tabButtons[method]?.focus();
 };
 const render=(enabled)=>{
  available=Object.keys(labels).filter(key=>enabled&&enabled[key]===true);
  available.push('portal');portalBaleAvailable=Boolean(enabled?.bale);
  if(!portalBaleAvailable)switchPortalMode('password');
  tabs.replaceChildren();Object.keys(tabButtons).forEach(key=>delete tabButtons[key]);
  state.hidden=available.length>0;
  if(!available.length){
   state.textContent='در حال حاضر هیچ روش ورود فعالی در دسترس نیست. لطفاً با مدیر سامانه تماس بگیرید.';
   for(const panel of Object.values(panels))panel.hidden=true;tabs.hidden=true;return;
  }
  tabs.hidden=false;
  for(const method of available){
   const button=make('button','sy-login-method-tab');button.type='button';button.id='sy-login-tab-'+method;button.setAttribute('role','tab');button.setAttribute('aria-controls',panels[method].id);
   const icon=make('span','sy-login-method-icon',method==='portal'?'♧':icons[method]);icon.setAttribute('aria-hidden','true');
   const title=make('span','sy-login-method-label',method==='portal'?'والدین و رانندگان':labels[method]);button.append(icon,title);
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
 async function reload(){
  try{
   const result=await api('/api/v1/auth/methods');
   recoveryMethodsFromServer=result.recoveryMethods||{sms:Boolean(result.methods?.sms||result.methods?.mobile),bale:Boolean(result.methods?.bale)};
   recoveryLink.hidden=!(recoveryMethodsFromServer.sms||recoveryMethodsFromServer.bale);
   render(result.methods||{});
  }catch(e){
   recoveryMethodsFromServer={sms:false,bale:false};recoveryLink.hidden=true;render({});
   state.textContent='دریافت تنظیمات ورود از سرور ناموفق بود. اتصال را بررسی و صفحه را دوباره بارگذاری کنید.';
  }
 }
 void reload();
 document.querySelectorAll('[data-portal-login]').forEach(link=>link.addEventListener('click',()=>{selected='portal';activate('portal');}));
 if(location.hash==='#portal-parent'||location.hash==='#portal-driver'){selected='portal';history.replaceState(history.state,'',location.pathname+location.search+'#login');}
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&!window.serviceyarSessionActive)void reload();});

 const oldCompaniesPage=companiesPage;
 companiesPage=async function(){
  await oldCompaniesPage();
  if(!session?.roles?.includes('super_admin'))return;
  const parent=document.getElementById('content');
  if(!parent||parent.querySelector('#syAdminLoginMethods'))return;
  const block=make('section','sy-login-policy-panel');block.id='syAdminLoginMethods';
  const title=make('h3','','مدیریت روش‌های ورود کاربران');
  const description=make('p','','فقط روش‌های انتخاب‌شده و آماده در صفحه ورود نمایش داده می‌شوند. روش‌های غیرفعال از طریق API نیز مسدود هستند؛ حداقل یک روش فعال و آماده لازم است.');
  const form=make('form','sy-login-policy-form'),checks={};
  for(const key of Object.keys(labels)){
   const row=make('label','sy-login-policy-option'),checkbox=make('input');checkbox.type='checkbox';checkbox.name=key;
   const desc=make('span','',labels[key]);row.append(checkbox,desc);checks[key]=checkbox;form.append(row);
  }
  const submit=make('button','primary','ذخیره روش‌های ورود');submit.type='submit';
  const readiness=make('p','sy-login-policy-readiness');readiness.setAttribute('role','status');
  const result=make('p','sy-login-policy-status');result.setAttribute('role','status');
  form.append(submit,result);block.append(title,description,readiness,form);parent.append(block);
  const refresh=async()=>{
   const status=await api('/api/v1/admin/login-methods');if(!block.isConnected)return;
   for(const key of Object.keys(checks)){
    checks[key].checked=Boolean(status.configured[key]);
    checks[key].disabled=!status.available[key]&&!status.configured[key];
    if(key==='sms'||key==='mobile')checks[key].parentElement.title=status.available[key]?'سرویس پیامک آماده است':'برای فعال‌سازی، سرویس پیامک نگین و افزونه SOAP را تنظیم کنید.';
    if(key==='bale')checks[key].parentElement.title=status.available[key]?'ربات بله آماده است':'برای فعال‌سازی، ربات بله را تنظیم و متصل کنید.';
   }
   const readiness=block.querySelector('.sy-login-policy-readiness');
   if(readiness){
    const reset=status.recoveryMethods||{};
    readiness.textContent='وضعیت بازیابی رمز: پیامک '+(reset.sms?'آماده':'نیازمند مهاجرت دیتابیس/تنظیم پیامک')+' · ربات بله '+(reset.bale?'آماده':'نیازمند مهاجرت دیتابیس/تنظیم ربات');
   }
  };
  form.onsubmit=async e=>{
   e.preventDefault();if(!Object.values(checks).some(x=>x.checked)){note(result,'حداقل یک روش ورود را انتخاب کنید.',true);return;}
   submit.disabled=true;note(result,'در حال ذخیره…');
   try{
    const data=Object.fromEntries(Object.entries(checks).map(([key,value])=>[key,value.checked]));
    await api('/api/v1/admin/login-methods',{method:'PUT',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify(data)});
    note(result,'تنظیمات ذخیره شد و روی API اعمال می‌شود.');await refresh();
   }catch(e){note(result,e.message,true);}finally{submit.disabled=false;}
  };
  try{await refresh();}catch(e){note(result,e.message,true);}

  const smsBlock=make('section','sy-login-policy-panel');smsBlock.id='syAdminNeginSms';
  const smsTitle=make('h3','','تنظیمات سرویس پیامک نگین');
  const smsDescription=make('p','','اطلاعات پنل پیامک فقط در سرور ذخیره می‌شود و با رمزگذاری نگهداری خواهد شد. این تنظیمات برای ورود پیامکی، ورود با موبایل و بازیابی رمز استفاده می‌شوند.');
  const smsForm=make('form','sy-sms-provider-form');
  const smsEnabledRow=make('label','sy-login-policy-option');
  const smsEnabled=make('input');smsEnabled.type='checkbox';smsEnabled.name='enabled';
  smsEnabledRow.append(smsEnabled,make('span','','فعال‌سازی سرویس پیامک نگین'));
  const smsGrid=make('div','sy-sms-provider-grid');
  const usernameLabel=make('label','sy-sms-provider-field');usernameLabel.append(make('span','','نام کاربری پنل نگین'));
  const smsUsername=make('input');smsUsername.type='text';smsUsername.name='username';smsUsername.autocomplete='off';smsUsername.maxLength=150;smsUsername.placeholder='نام کاربری پنل پیامک';usernameLabel.append(smsUsername);
  const passwordLabel=make('label','sy-sms-provider-field');passwordLabel.append(make('span','','رمز عبور پنل نگین'));
  const smsPassword=make('input');smsPassword.type='password';smsPassword.name='password';smsPassword.autocomplete='new-password';smsPassword.maxLength=500;smsPassword.placeholder='رمز عبور پنل پیامک';passwordLabel.append(smsPassword);
  smsGrid.append(usernameLabel,passwordLabel);
  const smsHint=make('p','sy-sms-provider-hint','اگر اطلاعات پنل قبلاً ثبت شده است، برای حفظ آن‌ها دو کادر نام کاربری و رمز عبور را خالی بگذارید. برای تغییر اطلاعات، هر دو کادر را تکمیل کنید.');
  const smsReadiness=make('p','sy-login-policy-readiness');smsReadiness.setAttribute('role','status');smsReadiness.setAttribute('aria-live','polite');
  const smsResult=make('p','sy-login-policy-status');smsResult.setAttribute('role','status');smsResult.setAttribute('aria-live','polite');
  const smsSave=make('button','primary','ذخیره تنظیمات پیامک');smsSave.type='submit';
  smsForm.append(smsEnabledRow,smsGrid,smsHint,smsReadiness,smsSave,smsResult);
  smsBlock.append(smsTitle,smsDescription,smsForm);parent.append(smsBlock);

  const refreshSms=async()=>{
   const status=await api('/api/v1/admin/sms-provider');if(!smsBlock.isConnected)return;
   smsEnabled.checked=Boolean(status.enabled);
   smsUsername.placeholder=status.usernameHint?'نام کاربری ثبت‌شده: '+status.usernameHint:'نام کاربری پنل پیامک';
   smsPassword.placeholder=status.credentialsConfigured?'برای حفظ رمز فعلی خالی بگذارید':'رمز عبور پنل پیامک';
   const parts=[
    status.enabled?'سرویس فعال است':'سرویس غیرفعال است',
    status.credentialsConfigured?'اطلاعات پنل ثبت شده':'اطلاعات پنل ثبت نشده',
    status.soapAvailable?'افزونه SOAP فعال است':'افزونه SOAP روی هاست فعال نیست',
    status.ready?'آماده استفاده':'آماده ارسال پیامک نیست'
   ];
   smsReadiness.textContent=parts.join(' · ');
   smsReadiness.classList.toggle('error',!status.ready);
  };
  smsForm.onsubmit=async e=>{
   e.preventDefault();smsSave.disabled=true;note(smsResult,'در حال ذخیره امن تنظیمات پیامک…');
   try{
    await api('/api/v1/admin/sms-provider',{method:'PUT',headers:{'X-CSRF-Token':session.csrfToken},body:JSON.stringify({enabled:smsEnabled.checked,username:smsUsername.value,password:smsPassword.value})});
    smsUsername.value='';smsPassword.value='';
    note(smsResult,'تنظیمات سرویس پیامک با موفقیت ذخیره شد.');
    await refreshSms();await refresh();
   }catch(error){note(smsResult,error.message||'ذخیره تنظیمات پیامک ناموفق بود.',true);}
   finally{smsSave.disabled=false;}
  };
  try{await refreshSms();}catch(error){note(smsResult,error.message||'دریافت تنظیمات پیامک ناموفق بود.',true);}
 };
})();