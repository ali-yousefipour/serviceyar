const $ = id => document.getElementById(id);
const health=$('health'), loginView=$('loginView'), appView=$('appView'), loginError=$('loginError');

async function api(url, options={}) {
  const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{})},...options});
  const data=await response.json().catch(()=>({}));
  if(!response.ok || !data.ok) throw new Error(data?.error?.message || 'خطای سرور');
  return data.data;
}
async function loadSession(){
  try{
    const data=await api('/api/v1/auth/me');
    loginView.hidden=true; appView.hidden=false;
    $('welcome').textContent=`خوش آمدید، ${[data.user.firstName,data.user.lastName].filter(Boolean).join(' ') || data.user.username}`;
  }catch{ loginView.hidden=false; appView.hidden=true; }
}
$('loginForm').addEventListener('submit',async e=>{
  e.preventDefault(); loginError.hidden=true;
  try{
    const data=await api('/api/v1/auth/login',{method:'POST',body:JSON.stringify({username:$('username').value,password:$('password').value})});
    $('password').value='';
    loginView.hidden=true; appView.hidden=false;
    $('welcome').textContent=`خوش آمدید، ${[data.user.firstName,data.user.lastName].filter(Boolean).join(' ') || data.user.username}`;
  }catch(err){loginError.textContent=err.message;loginError.hidden=false;}
});
$('logout').addEventListener('click',async()=>{
  try{
    const session=await api('/api/v1/auth/me');
    await api('/api/v1/auth/logout',{method:'POST',headers:{'X-CSRF-Token':session.csrfToken}});
  }finally{loginView.hidden=false;appView.hidden=true;}
});
fetch('/api/v1/health',{headers:{Accept:'application/json'}}).then(async r=>{
  const d=await r.json(); if(!r.ok||!d.ok) throw 0;
  health.textContent='وضعیت سرور: فعال — API آماده است.'; health.className='status ok';
}).catch(()=>{health.textContent='اتصال به API برقرار نشد.';health.className='status error';});
loadSession();