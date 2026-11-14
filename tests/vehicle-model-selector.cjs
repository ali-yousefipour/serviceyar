const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../frontend/assets/operations.js'),'utf8');
const picker=fs.readFileSync(path.join(__dirname,'../frontend/assets/vehicle-model-picker.js'),'utf8');
const script=source.slice(source.indexOf('const persianDigits='),source.indexOf('const year='));
assert(script.includes('function iranPlate(value)'),'Plate renderer must be available');
const context=vm.createContext({esc:v=>String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;')});
const render=vm.runInContext(script+';iranPlate',context);
for(const number of ['۴۲-۶۴۷ط۵۹','42ط647-59','42-647ط59']){
 const result=render(number);
 assert(result.includes('iran-plate-main'),number+' must render as full Iranian plate');
 assert(result.includes('iran-plate-region'),number+' must include Iran region segment');
 assert(result.includes('۶۴۷'),number+' must preserve three middle digits');
}
assert(picker.includes("input.setAttribute('role','combobox')"),'Vehicle picker should be searchable combobox');
assert(picker.includes('classSelect.value=String(item.vehicleClass)'),'Model selection must suggest its approved class');
assert(picker.includes("classSelect.addEventListener('change'"),'Operator should be able to change recommended class');
assert(source.includes('name="vehicleModel"'),'Service creation must expose vehicle selector');
assert(source.includes('name="vehicleClass"'),'Service creation must expose editable class');
assert(source.includes("if(key==='vehicles'){"),'Vehicle mobile layout exists');
console.log('Vehicle model picker and Persian/Iranian plate variants: OK');
