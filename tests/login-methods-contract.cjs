/* Offline auth policy and UX regression contract; live provider delivery requires a separate smoke test. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');
const routes=read('backend/public/index.php'),policy=read('backend/src/Auth/LoginMethods.php');
const sms=read('backend/src/Auth/SmsLogin.php'),bot=read('backend/src/Integrations/BaleBotService.php');
const js=read('frontend/assets/login-methods.js'),baleUi=read('frontend/assets/bale-bot.js');
const html=read('frontend/index.html'),css=read('frontend/assets/brand-theme.css');
const migration=read('database/migrations/0111_sms_login.sql');
const posSession=routes.indexOf('$s=$auth->current(); $aid=null;');
for(const suffix of ['methods','sms/request','sms/verify']){
 const p=routes.indexOf("'/api/v1/auth/"+suffix+"'");
 assert.ok(p>0&&p<posSession,suffix+' is available before authentication');
}
assert.ok(routes.indexOf("'/api/v1/admin/login-methods'")>posSession,'admin settings are authenticated');
assert.ok(routes.includes("LoginMethods::requireEnabled('password')"),'password route is policy protected');
assert.ok(bot.includes("LoginMethods::requireEnabled('bale')"),'Bale OTP is policy protected');
assert.ok(sms.includes("LoginMethods::requireEnabled('sms')"),'SMS OTP is policy protected');
assert.ok(policy.includes('Authorization::isSuperAdmin($actor)'),'only super admin can edit policy');
assert.ok(policy.includes('LOGIN_METHOD_REQUIRED'),'all-off configuration rejected');
assert.ok(policy.includes('LOGIN_METHOD_UNAVAILABLE'),'unavailable only method rejected');
assert.ok(policy.includes('SmsLogin::prepareSchema()'),'SMS schema installed on authorized enable');
assert.ok(sms.includes('password_hash($code,PASSWORD_DEFAULT)'),'OTP not stored plaintext');
assert.ok(sms.includes("password_verify($code,$row['code_hash'])"),'OTP verified securely');
assert.ok(sms.includes('attempt_count>=5')||sms.includes("['attempt_count']>=5"),'OTP attempts limited');
assert.ok(sms.includes('DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)'),'OTP expires');
assert.ok(sms.includes('NeginSms())->send'),'SMS uses provider');
assert.ok(js.includes("setAttribute('role','tablist')"),'accessible tab list');
assert.ok(js.includes("setAttribute('aria-selected'"),'accessible active tab');
assert.ok(js.includes('Object.keys(labels).filter(x=>Boolean(enabled[x]))'),'only enabled methods shown');
assert.ok(js.includes('session.csrfToken'),'policy editor uses CSRF');
assert.ok(html.includes('/assets/login-methods.js?v=__BUILD_ID__'),'versioned login script deployed');
assert.ok(!baleUi.includes('void initializeLogin();'),'old Bale-only login hidden');
assert.ok(css.includes('#loginForm[hidden]'),'legacy form can be hidden');
assert.ok(migration.includes('sms_login_challenges')&&migration.includes('sms_login_requests'),'migration included');
console.log('Three-way login policy, SMS OTP and responsive login UI: OK');
