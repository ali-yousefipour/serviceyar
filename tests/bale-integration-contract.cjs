/* Offline regression checks for Bale configuration, wallet and webhook wiring.
 * Provider credentials are intentionally NOT used by CI. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const read=(p)=>fs.readFileSync(path.resolve(__dirname,'..',p),'utf8');
const bot=read('backend/src/Integrations/BaleBotService.php');
const client=read('backend/src/Integrations/BaleClient.php');
const payment=read('backend/src/Integrations/BalePaymentService.php');
const routes=read('backend/public/index.php');
const ui=read('frontend/assets/bale-bot.js');
const html=read('frontend/index.html');
const migration=read('database/migrations/0110_bale_bot_auth.sql');
const checks=[
 ['bot token stored with encryption',bot.includes("self::put('bale_auth_token',self::encrypt($token))")],
 ['provider token stored with encryption',bot.includes("self::put('bale_wallet_provider_token',self::encrypt($wallet))")],
 ['admin-only token configuration',bot.includes('Authorization::isSuperAdmin($uid)')],
 ['secret values excluded from API overview',!bot.match(/return \['configured'.*'walletToken'\s*=>/s)],
 ['same bot token shared by payment client',client.includes('BaleBotService::botToken()')],
 ['wallet token supplied by secure store',payment.includes('BaleBotService::walletProviderToken()')],
 ['wallet enabled independently',bot.includes("self::put('bale_wallet_enabled'")],
 ['webhook routes payment event first',bot.includes("return BalePaymentService::webhook($update)")],
 ['webhook verifies secret',bot.includes('hash_equals($saved,$secret)')],
 ['admin wallet fields',ui.includes('name="walletToken"')&&ui.includes('name="walletEnabled"')],
 ['admin wallet config submitted',ui.includes('walletToken:form.elements.walletToken.value.trim()')],
 ['CSRF required for modifying credentials',routes.includes("if($method==='PUT'){$auth->requireCsrf($s);Response::json(['ok'=>true,'data'=>\\ServiceYar\\Integrations\\BaleBotService::configure")],
 ['one time login verification',bot.includes("purpose='login'")&&bot.includes('hash_hmac(')],
 ['login and payment routes are both registered',routes.includes('/api/v1/auth/bale/verify')&&routes.includes('/api/v1/integrations/bale/invoices')],
 ['frontend asset is loaded with build version',html.includes('/assets/bale-bot.js?v=__BUILD_ID__')],
 ['database stores no plaintext provider token',!migration.includes('WALLET-')],
];
for(const [name,result] of checks)assert.ok(result,name);
console.log('Bale secure configuration, auth, shared webhook and invoice contracts: '+checks.length+' OK');
