const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const read=path=>readFileSync(path,'utf8');
const authorization=read('backend/src/Auth/Authorization.php');
const users=read('backend/src/Users/UserService.php');
const migration=read('database/migrations/0114_school_company_manager_user_permissions.sql');
const auditor=read('backend/bin/schema-audit.php');
const repair=read('backend/bin/repair-company-user-permissions.php');
const routes=read('backend/public/index.php');
const operations=read('frontend/assets/operations.js');
assert.match(authorization,/r\.name IN \('agency_admin','school_company_manager'\)/,
  'School company managers receive tenant-scoped user management permission');
assert.match(users,/\['super_admin','agency_admin','school_company_manager'\]/,
  'Tenant managers must not edit another company-manager account');
assert.match(migration,/INSERT IGNORE INTO role_permissions/,
  'Repair migration must be idempotent');
assert.match(migration,/school_company_manager/,
  'Repair migration must cover the real company manager role');
assert.match(auditor,/not_recorded/,
  'Audit reports missing migration history distinctly from schema absence');
assert.match(auditor,/required_schema_issues/,
  'Audit checks essential columns and tables');
assert.doesNotMatch(auditor,/DROP TABLE|TRUNCATE TABLE|DELETE FROM|UPDATE users/,
  'Audit must not mutate production data');
assert.match(repair,/in_array\('--apply',\$argv,true\)/,
  'Legacy repair requires an explicit apply flag');
assert.match(repair,/\$pdo->beginTransaction\(\)/,
  'Role grant repair is transactional');
assert.doesNotMatch(repair,/DROP TABLE|TRUNCATE TABLE|DELETE FROM/,
  'Role grant repair must not remove existing records');
assert.match(routes,/Cache-Control: private, no-store, max-age=0/,
  'Authentication response is never cached');
assert.match(operations,/role==='school_company_manager'/,
  'Company manager role is displayed correctly');
console.log('Legacy production admin schema and tenant role repair contract: OK');
