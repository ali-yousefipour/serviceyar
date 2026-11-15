'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const tariff=fs.readFileSync('backend/src/Domain/TaxiOrganizationTariffs.php','utf8');
const reference=JSON.parse(fs.readFileSync('backend/src/Domain/public-service-valuation-1405.json','utf8'));
const classes={economy:680000,standard:820000,van:580000};
for(const item of reference.classes){
 assert.equal(Number(item.extra_driver_riyal_per_km),classes[item.key],'More-than-20km unit amount must match official footnote: '+item.key);
 assert.equal(item.brackets[0].distance_km,3);
 assert.equal(item.brackets.at(-1).distance_km,20);
 assert.equal(item.brackets.length,18);
}

const officialDriverTable={
 economy:[10000000,11000000,12500000,13500000,14500000,15500000,16500000,17500000,18000000,19000000,20000000,21000000,21500000,22500000,23500000,24000000,25000000,26000000],
 standard:[11000000,12500000,14000000,15000000,16500000,18000000,19500000,20000000,20500000,22000000,23500000,24500000,25000000,26000000,27500000,28500000,29000000,30000000],
 van:[7000000,8500000,9500000,10800000,11000000,12500000,13500000,14000000,15000000,15000000,16500000,16500000,18000000,18000000,19500000,19500000,20500000,20500000]
};
const officialParentTable={
 economy:[12090569,13287149,15082019,16278599,17475179,18671759,19868339,21064919,21663209,22859789,24056369,25252949,25851239,27047819,28244399,28842689,30039269,31235849],
 standard:[13287149,15082019,16876889,18073469,19868339,21663209,23458079,24056369,24654659,26449529,28244399,29440979,30039269,31235849,33030719,34227299,34825589,36022169],
 van:[8500829,10295699,11492279,13047833,13287149,15082019,16278599,16876889,18073469,18073469,19868339,19868339,21663209,21663209,23458079,23458079,24654659,24654659]
};
for(const cls of reference.classes){
 for(let i=0;i<18;i++){
  const bracket=cls.brackets[i];
  assert.equal(bracket.distance_km,i+3,'Official kilometre bracket '+cls.key);
  assert.equal(bracket.driver_1405_riyal,officialDriverTable[cls.key][i],'Official driver base '+cls.key+' at '+(i+3)+'km');
  assert.equal(Math.round(bracket.parent_monthly_riyal),officialParentTable[cls.key][i],'Official parent table '+cls.key+' at '+(i+3)+'km');
 }
}

assert.match(tariff,/const TRAFFIC=\[1=>0\.0,2=>0\.10,3=>0\.25,4=>0\.25,5=>0\.10,6=>0\.25,7=>0\.10,8=>0\.0\]/,'Official district traffic coefficients');
assert.match(tariff,/\$km=max\(3,\(int\)ceil\(\(float\)\$distance\)\)/,'Distance selection documented for separate rounding policy review');
assert.match(tariff,/\$extra=\(\$km-20\)\*\(int\)\$vehicle\['extra_driver_riyal_per_km'\]/,'Linear increase after 20km');
assert.match(tariff,/\$trafficAmount=empty\(\$rate\['overridden'\]\)\?\(int\)round\(\$baseDriver\*\$traffic\):0/,'Traffic must also increase default driver entitlement, never overrides');
assert.match(tariff,/\$rate\['driverMonthly'\]=\$baseDriver\+\$trafficAmount/,'Driver total must include district uplift');
assert.match(tariff,/parent_monthly_riyal'\]\+\$driver\*\$traffic\*1\.19658/,'Default parent amount already contains district uplift');
assert.match(tariff,/overrideAmountsAreFinal/,'Admin override is not modified a second time');
assert.match(tariff,/\$additionalDriver=\$inheritedOverride\?\(int\)round\(\$extra\*\(1\+\$traffic\)\):\$extra/,'20km override extrapolation must only uplift the extra distance');
assert.match(tariff,/\$rate\['parentPayableMonthly'\]/,'Special quote must expose parents payable separately from whole transport cost');
assert.match(tariff,/TRIPARTITE_AGREEMENT_REQUIRED/,'Exceptional-school three-party billing requires documented agreement');
const base=reference.classes[0].brackets[0].driver_1405_riyal;
assert.equal(base,10000000);
assert.equal(base*1.25,12500000,'Example: district 6 driver remuneration');
assert.equal(Math.round(reference.classes[0].brackets[0].parent_monthly_riyal+base*0.25*1.19658),15082019,'Example: parents amount for district 6');
console.log('Taxi tariff source-note rules and traffic driver entitlement contract passed');
