'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const read=p=>fs.readFileSync(p,'utf8');
const bale=read('frontend/assets/bale-bot.js');
const app=read('frontend/assets/app.js');
const operations=read('frontend/assets/operations.js');
const backend=read('backend/src/Domain/OperationsService.php');
const router=read('backend/public/index.php');
const auth=read('backend/src/Auth/AuthException.php');
assert.match(bale,/window\.__serviceYarBaleInitialized/, 'Bale must have a single initialization guard');
assert.match(bale,/window\.addEventListener\('serviceyar:operations-ready',attachOperationsIntegrations\)/, 'Page adapters must follow lazy operations loading');
for(const adapter of ['attachCompaniesIntegration','attachProfileIntegration','attachSettingsIntegration']){
 assert.ok(bale.includes('function '+adapter+'()'), adapter+' adapter missing');
}
assert.match(app,/window\.__serviceYarOperationsReady=true/, 'Operations readiness must be flagged before notification');
assert.match(operations,/const controller=new AbortController\(\)/, 'Obsolete operations requests must be cancelled');
assert.match(operations,/signal:controller\.signal/, 'Fetch must receive its abort signal');
assert.match(operations,/controller\.signal\.aborted/, 'Cancelled responses must not update UI');
assert.match(backend,/\$conflicts\[\]=\[/, 'Conflicts should accumulate instead of failing at the first invalid student');
assert.match(backend,/driver_base_monthly/, 'Payroll uses independent driver remuneration basis');
assert.match(backend,/VehicleClassEffectiveDates::blendedRate/, 'Historical approved class rates are used');
assert.match(backend,/\['period'=>/, 'Payroll conflicts include request period');
assert.match(router,/array_merge\(\['code'=>\$e->codeName/, 'HTTP contract exposes structured exception context');
assert.match(auth,/public readonly array \$context/, 'Domain exception permits scoped context');
assert.match(operations,/e\.details\?\.conflicts/, 'UI reads financial conflict detail');
console.log('Bale lazy integration and payroll conflict regression contracts: OK');
