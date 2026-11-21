'use strict';
/**
 * One same-origin Neshan SDK loader shared by the public map and company panel.
 * Only fetches SDK code/styles; map tile and geocoding requests remain with Neshan.
 */
(() => {
 const ROOT='/assets/vendor/neshan/v1.0.8/';
 let loading=null,ready=false;
 function load(){
  if(ready)return Promise.resolve();
  if(loading)return loading;
  loading=new Promise((resolve,reject)=>{
   const style=document.createElement('link');
   style.rel='stylesheet';
   style.href=ROOT+'index.css';
   style.dataset.syNeshan='stylesheet';
   const script=document.createElement('script');
   script.src=ROOT+'index.js';
   script.async=true;
   script.dataset.syNeshan='script';
   let completed=false,cssLoaded=false,jsLoaded=false;
   const finish=(error)=>{
    if(completed)return;
    if(!error&&(!cssLoaded||!jsLoaded))return;
    completed=true;
    clearTimeout(timeout);
    if(error){
     style.remove();script.remove();
     loading=null;
     reject(error);
    }else{
     ready=true;
     resolve();
    }
   };
   const timeout=setTimeout(()=>finish(new Error('زمان بارگذاری نقشه نشان به پایان رسید. دوباره تلاش کنید.')),15000);
   style.onload=()=>{cssLoaded=true;finish();};
   style.onerror=()=>finish(new Error('فایل استایل نقشه نشان روی هاست در دسترس نیست.'));
   script.onload=()=>{
    if(!window.L||typeof window.L.Map!=='function'){
     finish(new Error('کتابخانه نقشه نشان به‌درستی آماده نشده است.'));
     return;
    }
    jsLoaded=true;finish();
   };
   script.onerror=()=>finish(new Error('کتابخانه نقشه نشان روی هاست در دسترس نیست.'));
   document.head.append(style,script);
  });
  return loading;
 }
 window.ServiceYarNeshanSDK={load};
})();
