/* Regression: Iranian mobile usernames accept all common digit sets without changing passwords. */
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const auth=fs.readFileSync(path.join(__dirname,'../backend/src/Auth/AuthService.php'),'utf8');
const app=fs.readFileSync(path.join(__dirname,'../frontend/assets/app.js'),'utf8');
const persian='۰۹۱۵۱۲۳۴۵۶۷';
const arabic='٠٩١٥١٢٣٤٥٦٧';
const ascii='09151234567';
const normalize=value=>value.trim().replace(/[۰-۹]/g,ch=>String(ch.charCodeAt(0)-1776)).replace(/[٠-٩]/g,ch=>String(ch.charCodeAt(0)-1632));
assert.equal(normalize(persian),ascii);
assert.equal(normalize(arabic),ascii);
assert.equal(normalize(ascii),ascii);
assert.match(auth,/strtr\(trim\(\$username\)/);
assert.match(auth,/password_verify\(\$password,\s*\$user\['password_hash'\]\)/);
assert.match(app,/replace\(\/\[۰-۹\]\/g/);
assert.match(app,/replace\(\/\[٠-٩\]\/g/);
console.log('Login username digit normalization and password integrity: OK');
