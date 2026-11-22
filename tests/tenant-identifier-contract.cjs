'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const php=fs.readFileSync('backend/public/index.php','utf8');
const start=php.indexOf('function agencyId(array $session): int {');
const end=php.indexOf('\ntry {',start);
assert.ok(start>=0&&end>start,'agencyId tenant boundary must exist');
const guard=php.slice(start,end);
for(const expected of [
 "is_string($raw)",
 "preg_match('/^[1-9][0-9]*$/D',$raw)",
 "FILTER_VALIDATE_INT",
 "Authorization::requireAgencyAccess($session['id'],$id)"
])assert.ok(guard.includes(expected),'Missing tenant-boundary guard: '+expected);
assert.ok(!guard.includes('(int)$raw'),'Tenant ID must not be cast before validation');
console.log('Tenant identifier validation contract: OK');
