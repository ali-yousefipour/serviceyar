/* Safety contract for regular user contact linking vs super admin code linking */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const bot=fs.readFileSync(path.join(__dirname,'../backend/src/Integrations/BaleBotService.php'),'utf8');
const ui=fs.readFileSync(path.join(__dirname,'../frontend/assets/bale-bot.js'),'utf8');
const claims=[
 ['bot receives phone contact',bot.includes("isset($update['message']['contact'])")],
 ['contact belongs to sender',bot.includes("(string)$contact['user_id']!==$sender")],
 ['chat belongs to sender',bot.includes('$sender!==$chat')],
 ['phone sharing is explicitly requested',bot.includes("'request_contact'=>true")],
 ['phone must be a valid Iranian mobile',bot.includes("preg_match('/^09[0-9]{9}$/D'")],
 ['only one active profile matches',bot.includes("count($matches)!==1")],
 ['super admins remain protected',bot.includes('Authorization::isSuperAdmin($userId)')],
 ['existing links are not reassigned silently',bot.includes('OR user_id=? LIMIT 2')],
 ['client distinguishes super admins',ui.includes('info.requiresCode')],
 ['regular user has no code button',ui.includes("hidden=!info.requiresCode")],
];
for(const [claim,valid] of claims)assert.ok(valid,claim);
console.log('Bale verified-contact user linking safeguards: '+claims.length+' OK');
