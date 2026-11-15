/* Bale OTP must be available to unauthenticated users; account linking must remain protected. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const api=fs.readFileSync(path.join(__dirname,'../backend/public/index.php'),'utf8');
const bot=fs.readFileSync(path.join(__dirname,'../backend/src/Integrations/BaleBotService.php'),'utf8');
const ui=fs.readFileSync(path.join(__dirname,'../frontend/assets/bale-bot.js'),'utf8');
const idxRequest=api.indexOf("if($method==='POST'&&$path==='/api/v1/auth/bale/request')");
const idxVerify=api.indexOf("if($method==='POST'&&$path==='/api/v1/auth/bale/verify')");
const idxSession=api.indexOf("$s=$auth->current(); $aid=null;");
const idxLink=api.indexOf("if($path==='/api/v1/bale/link')");
assert.ok(idxRequest>0&&idxVerify>idxRequest&&idxSession>idxVerify,'Bale OTP routes must bypass session auth');
assert.ok(idxLink>idxSession,'Account linking must require authenticated session');
assert.ok(bot.includes("strtr(trim($username)"),'Bale username normalization must be server-side');
assert.ok(ui.includes('normalizeUsername(form.elements.username.value)'), 'Bale login should normalize Persian digits');
assert.ok(ui.includes('showLinkError(feedback,e)'), 'Expired account-linking session must provide specific feedback');
assert.ok(api.includes('/api/v1/admin/bale-bot/user-link'),'Admin link diagnostic endpoint must be registered');
assert.ok(bot.includes('Authorization::isSuperAdmin($actor)'),'Linked-account diagnostics must be super admin only');
assert.ok(ui.includes('data-bale-user-check'),'Admin UI should inspect account linkage');
assert.ok(api.includes('X-ServiceYar-Auth-Flow: bale-otp-request'),'Anonymous OTP responses should be identifiable in Network');
console.log('Bale public OTP route and protected account linking: OK');
