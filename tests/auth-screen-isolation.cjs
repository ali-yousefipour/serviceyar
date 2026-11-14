/* Regression: public hydration must never reveal guest screens after auth. */
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const path=require('node:path');
const publicJs=fs.readFileSync(path.join(__dirname,'../frontend/assets/public-site.js'),'utf8');
const css=fs.readFileSync(path.join(__dirname,'../frontend/assets/brand-theme.css'),'utf8');
const appJs=fs.readFileSync(path.join(__dirname,'../frontend/assets/app.js'),'utf8');
const start=publicJs.indexOf(' async function show(){');
const end=publicJs.indexOf(" addEventListener('hashchange',",start);
assert(start>=0&&end>start,'Public view functions should remain inspectable');
const implementations=publicJs.slice(start,end);
let resolveMount;const mountPromise=new Promise(resolve=>resolveMount=resolve);
const root={hidden:true},login={hidden:true},window={serviceyarSessionActive:false};
const context={root,login,window,observer:null,setMenu(){},mount(){return mountPromise;},requestedPage(){return 'login';},
 renderPage(){throw new Error('Public page must not render');},loadStats(){throw new Error('Stale public statistics load');},
 setupPublicSchools(){throw new Error('Stale public map initialization');},activePage:null};
vm.runInNewContext(implementations+';this.view={show,hide};',context);
(async()=>{
 const task=context.view.show();
 window.serviceyarSessionActive=true;
 resolveMount();
 await task;
 assert(root.hidden&&login.hidden,'Stale login show must not reveal guest UI after auth');
 window.serviceyarSessionActive=false;
 await context.view.show();
 assert(root.hidden&&!login.hidden,'Guest login route must remain accessible');
 context.view.hide();assert(root.hidden&&login.hidden,'Hide must close guest surfaces');
 assert(css.includes('#app:not([hidden]) ~ #login'),'Logged-in admin must mask login in CSS');
 assert(css.includes('#app:not([hidden]) ~ #publicSite'),'Logged-in admin must mask public site in CSS');
 assert(appJs.includes('window.serviceyarSessionActive=true;window.serviceyarPublic?.hide()'),'Auth boot must hide guest screens');
 console.log('Authenticated / public / login view isolation: OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
