'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const get=path=>fs.readFileSync(path,'utf8');
const publicJs=get('frontend/assets/public-site.js');
const operations=get('frontend/assets/operations.js');
const html=get('frontend/index.html');
const deploy=get('.github/workflows/deploy-serviceyar.yml');
const vendor=get('tools/vendor_neshan_sdk.py');

const loader=get('frontend/assets/neshan-sdk-loader.js');
assert.match(loader,/\/assets\/vendor\/neshan\/v1\.0\.8\//,'Shared SDK loader must use first-party versioned path');
assert.match(loader,/ROOT\+'index.js'/,'Loader must request local SDK JS');
assert.match(loader,/ROOT\+'index.css'/,'Loader must request local SDK CSS');
for(const [name,source] of [['public',publicJs],['company',operations]]){
 assert.match(source,/ServiceYarNeshanSDK\.load\(\)/,name+' must reuse the shared SDK loader');
 assert.doesNotMatch(source,/https:\/\/static\.neshan\.org\/sdk\//,name+' must not fetch third-party SDK in browser');
}
assert.ok(html.indexOf('/assets/neshan-sdk-loader.js') < html.indexOf('/assets/public-site.js'), 'SDK loader must initialize before public map');
assert.match(html,/src="\/assets\/leaflet\.js/, 'Leaflet already hosted locally');
assert.match(html,/href="\/assets\/leaflet\.css/, 'Leaflet CSS already hosted locally');
assert.match(operations,/\/assets\/html2pdf\.bundle\.min\.js/, 'PDF bundle must remain same-origin');
assert.match(deploy,/python3 tools\/vendor_neshan_sdk\.py deploy-payload\/assets\/vendor\/neshan\/v1\.0\.8/, 'Deploy must stage actual SDK content');
assert.match(deploy,/test -s deploy-payload\/assets\/vendor\/neshan\/v1\.0\.8\/index\.js/, 'Deploy must fail if SDK JS missing');
assert.match(deploy,/test -s deploy-payload\/assets\/vendor\/neshan\/v1\.0\.8\/index\.css/, 'Deploy must fail if SDK CSS missing');
assert.match(vendor,/static\.neshan\.org/, 'Source for the pinned SDK must be explicit');
console.log('First-party map and export library asset contracts: OK');
