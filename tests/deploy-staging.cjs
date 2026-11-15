const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const source=readFileSync('tools/deploy_serviceyar.py','utf8');
assert.match(source,/GITHUB_RUN_ID/,'FTP staging must use a unique workflow run ID');
assert.match(source,/\.serviceyar-staging-/,'Each workflow must use an independent staging folder');
assert.match(source,/verify\(ftp, digests\[name\], live\)/,'Published files must be verified');
assert.match(source,/3 if name == "index.html"/,'HTML index must publish last');
const workflow=readFileSync('.github/workflows/deploy-serviceyar.yml','utf8');
for(const asset of ['public-site.js','public-site.css','public-site-content.html']){
 const {existsSync}=require('node:fs');
 assert.ok(existsSync('frontend/assets/'+asset),'Missing public asset: '+asset);
}
assert.match(workflow,/cancel-in-progress: true/,'Supersede stuck old release runs');
assert.match(source,/PUBLIC_BASE_URL", "https:\/\/challi\.ir"/,'Live release verification must target the production challi.ir domain by default');
assert.match(workflow,/PUBLIC_BASE_URL: https:\/\/challi\.ir/,'Production deployment must explicitly verify challi.ir');
assert.match(source,/https:\/\/www\.challi\.ir\//,'TLS diagnostics must inspect the production www host');
console.log('FTP staging isolation and public website deployment checks OK');
