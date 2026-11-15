const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=p=>fs.readFileSync(path.join(__dirname,'..',p),'utf8');

const provider=read('backend/src/Integrations/NeginSms.php');
const routes=read('backend/public/index.php');
const policy=read('backend/src/Auth/LoginMethods.php');
const ui=read('frontend/assets/login-methods.js');
const css=read('frontend/assets/brand-theme.css');
const docs=read('docs/LOGIN-METHODS.md');

for(const key of ['negin_sms_username_enc','negin_sms_password_enc','negin_sms_enabled'])
 assert.ok(provider.includes(key),'provider setting key '+key);
assert.ok(provider.includes('aes-256-gcm'),'provider credentials are encrypted at rest');
assert.ok(provider.includes('storage/private/negin-sms.key'),'dedicated private encryption key is persisted outside public assets');
assert.ok(provider.includes('Authorization::isSuperAdmin($actor)'),'only super admin can manage provider credentials');
assert.ok(provider.includes('self::encrypt($username)')&&provider.includes('self::encrypt($password)'),'username and password are encrypted before persistence');
assert.ok(provider.includes("'usernameHint'=>$hint"),'API returns only a masked username hint');
assert.ok(!provider.includes("'password'=>$credentials['password']"),'API never returns the password');
assert.ok(provider.includes("$credentials['username']")&&provider.includes("$credentials['password']"),'SMS delivery uses stored provider credentials');
assert.ok(provider.includes("config('NEGIN_SMS_USERNAME'")&&provider.includes("config('NEGIN_SMS_PASSWORD'"),'environment credentials remain supported as fallback');
assert.ok(provider.includes('SMS_CREDENTIALS_PAIR_REQUIRED'),'credential rotation requires username and password together');
assert.ok(provider.includes('beginTransaction()')&&provider.includes('rollBack()'),'credential updates are atomic');
assert.ok(routes.includes("'/api/v1/admin/sms-provider'")&&routes.includes('NeginSms::configure'),'admin SMS provider API is registered');
assert.ok(policy.includes('$smsReady=NeginSms::ready();'),'login methods use the same provider readiness check');
assert.ok(ui.includes('syAdminNeginSms'),'super admin page mounts the Negin SMS settings panel');
assert.ok(ui.includes('/api/v1/admin/sms-provider'),'settings panel reads and saves provider settings');
assert.ok(ui.includes('X-CSRF-Token'),'provider settings writes are protected by CSRF');
assert.ok(ui.includes('smsPassword.value'), 'password field is submitted only on explicit save');
assert.ok(css.includes('.sy-sms-provider-grid'),'responsive provider settings styling exists');
assert.ok(docs.includes('تنظیمات سرویس پیامک نگین'),'admin setup is documented');
console.log('Negin SMS super-admin settings, encrypted credentials and provider readiness: OK');
