const assert=require('node:assert/strict');const dates=require('../frontend/assets/solar-date.js');
for(const [solar,iso] of [['1405/08/05','2026-10-27'],['1403/12/30','2025-03-20'],['1404/01/01','2025-03-21'],['1400/01/01','2021-03-21']]){assert.equal(dates.toISO(solar),iso);assert.equal(dates.toSolar(iso),solar);}
assert.equal(dates.toISO('۱۴۰۵/۰۸/۰۵'),'2026-10-27');for(const s of ['1404/12/30','1405/07/31','1405/13/01','1405/00/01','1405/01/00','2026-10-27'])assert.equal(dates.toISO(s),'');assert.equal(dates.toSolar('2026-02-30'),'');
for(let y=1390;y<=1410;y++)for(let m=1;m<=12;m++){const s=`${y}/${String(m).padStart(2,'0')}/15`;assert.equal(dates.toSolar(dates.toISO(s)),s);}console.log('Solar date conversion: leap years, invalid dates, Persian digits and 252 round trips passed.');
