'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const read=p=>fs.readFileSync(p,'utf8');
const styles=read('frontend/assets/filter-consistency.css');
const html=read('frontend/index.html');
const operations=read('frontend/assets/operations.css');
const portal=read('frontend/assets/portal.css');
assert.ok(html.includes('filter-consistency.css?v=__BUILD_ID__'),'Versioned shared filter CSS must load');
assert.ok(html.indexOf('filter-consistency.css')>html.indexOf('ui-consistency.css'),'Filter rules must follow typography rules');
for(const selector of ['.filters','.directory-filters','.service-filters','.sy-operation-filters','.report-filters','.ledger-filters','.sy-invoice-filters','.portal-debtors-filters']){
 assert.ok(styles.includes(selector),'Filter selector missing: '+selector);
}
assert.ok(styles.includes('--sy-filter-gap:clamp(8px,1vw,12px)'), 'Filter spacing must remain compact');
assert.ok(styles.includes('min-height:42px'), 'Filter controls must be comfortably touchable');
assert.ok(styles.includes('font-size:14px'), 'Search fields must be readable');
assert.ok(styles.includes('@media screen and (max-width:760px)'), 'Tablet filter breakpoint required');
assert.ok(styles.includes('@media screen and (max-width:480px)'), 'Phone filter breakpoint required');
assert.ok(styles.includes(':focus-visible'), 'Keyboard navigation must retain focus');
assert.ok(styles.includes('.sy-sms-provider-grid'), 'SMS provider settings must stay responsive');
assert.ok(!styles.includes('!important'), 'Filter overlay must avoid competing important declarations');
assert.ok(operations.includes('.directory-filters')&&operations.includes('.sy-invoice-filters'), 'Existing operational filters must be preserved');
assert.ok(portal.includes('.portal-debtors-filters'), 'Existing portal debtor filtering must be preserved');
console.log('Compact responsive cross-panel search and filter contracts: OK');
