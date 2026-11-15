'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const lines=fs.readFileSync('backend/public/index.php','utf8').split('\n');
// Static tripwire for directly declared write endpoints. This complements,
// rather than replaces, authenticated CSRF and authorization HTTP tests.
const method=/\$method\s*===?\s*'(POST|PUT|PATCH|DELETE)'|in_array\(\$method,\s*\['POST'/;
const routeLines=lines.flatMap((line,index)=>line.includes('/api/v1/')&&method.test(line)?[index]:[]);
const publicEntrypoints=[
 '/api/v1/integrations/bale/webhook/',
 '/api/v1/bale/bot/webhook/',
 '/api/v1/auth/sms/request',
 '/api/v1/auth/sms/verify',
 '/api/v1/auth/mobile/request',
 '/api/v1/auth/mobile/verify',
 '/api/v1/auth/password-reset/request',
 '/api/v1/auth/password-reset/confirm',
 '/api/v1/auth/bale/request',
 '/api/v1/auth/bale/verify',
 '/api/v1/auth/login'
];
assert.ok(routeLines.length>=60,'Expected broad API mutation route coverage');
let protectedRoutes=0,publicRoutes=0;
for(let n=0;n<routeLines.length;n++){
 const index=routeLines[n],line=lines[index];
 const end=Math.min(lines.length,index+12,routeLines[n+1]??lines.length);
 const handler=lines.slice(index,end).join('\n');
 if(publicEntrypoints.some(endpoint=>line.includes(endpoint))){
  publicRoutes++;
  continue;
 }
 assert.ok(handler.includes('requireCsrf($s)'),
  'Authenticated mutation lacks a CSRF guard near line '+(index+1)+': '+line.trim().slice(0,180));
 protectedRoutes++;
}
assert.ok(protectedRoutes>=50&&publicRoutes>=10,'Mutation route categorization changed: review newly added handlers');
console.log('API mutation-route CSRF static contract: '+protectedRoutes+' protected, '+publicRoutes+' public/webhook entrypoints');
