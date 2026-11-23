'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

async function main(){
 const handlers=new Map();
 const window={
  __serviceYarOperationsReady:false,
  addEventListener(name,handler){
   const values=handlers.get(name)||[];
   values.push(handler);
   handlers.set(name,values);
  },
  dispatchEvent(event){
   for(const listener of handlers.get(event.type)||[])listener(event);
  }
 };
 const sandbox={
  window,
  document:{querySelector:()=>null,getElementById:()=>null},
  navigator:{},console,
  session:null,
  api:async()=>({}),
  can:()=>false,
  scoped:v=>v,
  boot:async()=>{},
  confirm:()=>false
 };
 vm.createContext(sandbox);
 const source=fs.readFileSync('frontend/assets/bale-bot.js','utf8');
 assert.doesNotThrow(()=>vm.runInContext(source,sandbox),
   'Bale must execute without eagerly loaded operations or profile globals');
 assert.equal(handlers.get('serviceyar:operations-ready')?.length,1,
   'Exactly one deferred operations listener must be registered');
 let called=0;
 const baseProfile=async()=>{called++};
 const baseCompanies=async()=>{called++};
 const baseSettings=async()=>{called++};
 Object.assign(sandbox,{
  ownProfileDialog:baseProfile,
  companiesPage:baseCompanies,
  settingsPage:baseSettings
 });
 window.__serviceYarOperationsReady=true;
 window.dispatchEvent({type:'serviceyar:operations-ready'});
 const profile=sandbox.ownProfileDialog,companies=sandbox.companiesPage,settings=sandbox.settingsPage;
 assert.notEqual(profile,baseProfile,'Profile integration must be attached');
 assert.notEqual(companies,baseCompanies,'Company integration must be attached');
 assert.notEqual(settings,baseSettings,'Settings integration must be attached');
 await profile();await companies();await settings();
 assert.equal(called,3,'Each adapter must preserve its original implementation');
 window.dispatchEvent({type:'serviceyar:operations-ready'});
 assert.equal(sandbox.ownProfileDialog,profile,'Profile adapter must be idempotent');
 assert.equal(sandbox.companiesPage,companies,'Company adapter must be idempotent');
 assert.equal(sandbox.settingsPage,settings,'Settings adapter must be idempotent');
 vm.runInContext(source,sandbox);
 assert.equal(handlers.get('serviceyar:operations-ready').length,1,
   'Reload must not duplicate listeners');
 assert.equal(sandbox.ownProfileDialog,profile,'Reload must not stack adapters');
 console.log('Bale deferred operations initialization and duplicate listener runtime regression: OK');
}
main().catch(error=>{console.error(error);process.exitCode=1});
