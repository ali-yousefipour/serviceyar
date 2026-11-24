const assert=require('node:assert/strict');
const source=require('node:fs').readFileSync(require('node:path').join(__dirname,'../frontend/assets/operations.js'),'utf8');
const handler=source.indexOf("body.querySelectorAll('[data-contract-print]')");
const start=source.indexOf('    const inline=',handler),end=source.indexOf('    try{',start);
assert(start>handler&&end>start);
const open=new Function('button','dialog','$','window','notify',source.slice(start,end)+';return printWindow;');
let markup='',written='',closed=false;
const preview={document:{write:s=>written=s,close:()=>closed=true}};
const result=open({dataset:{inlinePreview:'1'}},(_,html)=>markup=html,
 id=>{assert.equal(id,'syContractPreviewFrame');return {contentWindow:preview};},
 {open:()=>{throw Error('Inline preview must not require popups');}},()=>{});
assert.equal(result,preview);
assert(markup.includes('title="نسخه چاپی قرارداد و الحاقیه"'));
assert(markup.includes('sandbox="allow-same-origin allow-modals"'));
assert(!markup.includes('allow-scripts'));
assert(written.includes('در حال دریافت'));
assert(closed);
let blocked=false;
assert.equal(open({dataset:{}},()=>{},()=>{}, {open:()=>null},()=>blocked=true),undefined);
assert(blocked,'Popup failure should remain visible');
console.log('Contract inline preview without popup: OK');
