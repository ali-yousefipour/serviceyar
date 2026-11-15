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
assert.match(tariff,/self::TRAFFIC=\[1=>0\.0,2=>0\.10,3=>0\.25,4=>0\.25,5=>0\.10,6=>0\.25,7=>0\.10,8=>0\.0\]/,'Official district traffic coefficients');
assert.match(tariff,/\$km=max\(3,\(int\)ceil\(\(float\)\$distance\)\)/,'Distance selection documented for separate rounding policy review');
assert.match(tariff,/\$extra=\(\$km-20\)\*\(int\)\$vehicle\['extra_driver_riyal_per_km'\]/,'Linear increase after 20km');
assert.match(tariff,/\$trafficAmount=empty\(\$rate\['overridden'\]\)\?\(int\)round\(\$baseDriver\*\$traffic\):0/,'Traffic must also increase default driver entitlement, never overrides');
assert.match(tariff,/\$rate\['driverMonthly'\]=\$baseDriver\+\$trafficAmount/,'Driver total must include district uplift');
assert.match(tariff,/parent_monthly_riyal'\]\+\$driver\*\$traffic\*1\.19658/,'Default parent amount already contains district uplift');
assert.match(tariff,/overrideAmountsAreFinal/,'Admin override is not modified a second time');
const base=reference.classes[0].brackets[0].driver_1405_riyal;
assert.equal(base,10000000);
assert.equal(base*1.25,12500000,'Example: district 6 driver remuneration');
assert.equal(Math.round(reference.classes[0].brackets[0].parent_monthly_riyal+base*0.25*1.19658),15082119,'Example: parents amount for district 6');
console.log('Taxi tariff source-note rules and traffic driver entitlement contract passed');
