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
console.log('FTP staging isolation and public website deployment checks OK');
