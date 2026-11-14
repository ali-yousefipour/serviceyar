/* Reusable, accessible live vehicle model picker.
 * Dropdown options come from the super-admin's approved class dictionary.
 * The operator can explicitly override the suggested tariff class.
 */
(function(){
 'use strict';
 const norm=value=>String(value??'').replace(/[يى]/g,'ی').replace(/ك/g,'ک')
  .replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
  .replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
  .replace(/[‌\s]+/g,' ').trim().toLocaleLowerCase('fa');
 let optionsPromise=null;
 async function load(){
  if(!optionsPromise)optionsPromise=api(typeof scoped==='function'?scoped('/api/v1/vehicle-model-options'):'/api/v1/vehicle-model-options')
   .then(data=>data.items||[]).catch(error=>{optionsPromise=null;throw error;});
  return optionsPromise;
 }
 const el=(tag,cls)=>{const node=document.createElement(tag);node.className=cls;return node;};
 async function attach(input,classSelect,{onChange,modelMirror,initialValue}={}){
  if(!input||!classSelect)return null;
  input.type='search';input.autocomplete='off';input.spellcheck=false;
  input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');
  input.placeholder='نام خودرو یا تیپ را جستجو کنید…';
  const wrapper=el('div','sy-vehicle-model-picker');
  const parent=input.parentElement;parent.insertBefore(wrapper,input);wrapper.append(input);
  const list=el('div','sy-vehicle-model-options');list.setAttribute('role','listbox');list.hidden=true;
  const hint=el('small','sy-vehicle-model-hint');hint.textContent='با جستجو، مدل خودرو را از فهرست انتخاب کنید؛ کلاس پیشنهادی قابل تغییر است.';
  wrapper.append(list,hint);
  let models=[];let selected=null;
  const close=()=>{list.hidden=true;input.setAttribute('aria-expanded','false');};
  const pick=item=>{
   selected=item;
   input.value=item.name;input.dataset.vehicleFamily=item.name;delete input.dataset.vehicleTouched;
   if(modelMirror)modelMirror.value=item.name;
   classSelect.value=String(item.vehicleClass);
   hint.textContent='کلاس پیشنهادی: '+item.vehicleClass+' · در صورت نیاز می‌توانید کلاس را تغییر دهید.';
   close();onChange?.(item);
  };
  const render=()=>{
   const term=norm(input.value);
   const matches=models.filter(item=>!term||norm(item.name).includes(term)||
    item.aliases?.some(a=>norm(a).includes(term))).slice(0,35);
   list.replaceChildren();
   for(const item of matches){
    const option=el('button','sy-vehicle-model-option');option.type='button';
    option.setAttribute('role','option');
    option.textContent=item.name+' · کلاس '+item.vehicleClass;
    option.addEventListener('mousedown',e=>e.preventDefault());
    option.addEventListener('click',()=>pick(item));list.append(option);
   }
   if(!matches.length){const message=el('p','sy-vehicle-model-empty');message.textContent='مدل پیدا نشد؛ از ادمین کل بخواهید آن را در کلاس مربوطه تعریف کند.';list.append(message);}
   list.hidden=false;input.setAttribute('aria-expanded','true');
  };
  input.addEventListener('focus',render);
  input.addEventListener('input',()=>{
   selected=null;delete input.dataset.vehicleFamily;input.dataset.vehicleTouched='1';
   hint.textContent='برای تأیید نام خودرو یکی از نتایج را انتخاب کنید.';
   render();onChange?.(null);
  });
  input.addEventListener('keydown',e=>{
   if(e.key==='Escape')close();
   if(e.key==='ArrowDown'&&list.hidden===false){e.preventDefault();list.querySelector('button')?.focus();}
  });
  input.addEventListener('blur',()=>setTimeout(close,130));
  input.addEventListener('change',()=>{
   const match=models.find(item=>norm(item.name)===norm(input.value)||
     item.aliases?.some(a=>norm(a)===norm(input.value)));
   if(match)pick(match);
  });
  classSelect.addEventListener('change',()=>onChange?.(selected));
  let destroyed=false;
  try{
   models=await load();
   if(!input.isConnected){destroyed=true;return null;}
   const candidate=initialValue||input.value;
   const match=models.find(item=>norm(item.name)===norm(candidate)||
    item.aliases?.some(a=>norm(a)===norm(candidate)))||
    models.find(item=>norm(candidate).length>2&&
      item.aliases?.some(a=>norm(a).length>2&&norm(candidate).includes(norm(a))));
   if(match){
    input.value=match.name;input.dataset.vehicleFamily=match.name;delete input.dataset.vehicleTouched;
    if(modelMirror)modelMirror.value=match.name;
    // Never overwrite a valid, explicitly saved historical class merely
    // because the dictionary suggests a different one.
    if(!['1','2','3'].includes(classSelect.value))classSelect.value=String(match.vehicleClass);
    selected=match;
    hint.textContent='کلاس پیشنهادی: '+match.vehicleClass+' · مقدار ثبت‌شده را می‌توانید تغییر دهید.';
   }else if(candidate){
    input.value=candidate;
    hint.textContent='مدل ثبت‌شده در فهرست مصوب یافت نشد. برای تغییر، مدل معتبر انتخاب کنید.';
   }
  }catch(error){hint.textContent='فهرست خودرو دریافت نشد: '+error.message;}
  return {setVehicle(vehicle){
   const candidate=vehicle?.vehicle_trim||vehicle?.model||vehicle?.vehicle_system||'';
   const match=models.find(item=>norm(item.name)===norm(candidate)||
    item.aliases?.some(a=>norm(a)===norm(candidate)))||
    models.find(item=>norm(candidate).length>2&&item.aliases?.some(a=>norm(a).length>2&&norm(candidate).includes(norm(a))));
   if(match){
    input.value=match.name;input.dataset.vehicleFamily=match.name;delete input.dataset.vehicleTouched;selected=match;
    if(modelMirror)modelMirror.value=match.name;
   }else{input.value=candidate;selected=null;delete input.dataset.vehicleFamily;}
   classSelect.value=['1','2','3'].includes(String(vehicle?.tariff_vehicle_class))?String(vehicle.tariff_vehicle_class):match?String(match.vehicleClass):'';
   hint.textContent=match?'کلاس پیشنهادی: '+match.vehicleClass+'؛ قابل ویرایش':'نوع خودرو را از فهرست انتخاب کنید.';
   onChange?.(match);
  },get valid(){return !!selected&&!!input.dataset.vehicleFamily&&['1','2','3'].includes(classSelect.value);},
    get selected(){return selected;},destroy(){close();}};
 }
 window.ServiceYarVehicleModels={attach,load};
})();
