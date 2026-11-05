<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Integrations\Neshan as N;
use ServiceYar\Auth\AuthException;
$checks=0;
function expect(bool $ok):void{global $checks;if(!$ok)throw new RuntimeException('Neshan regression check failed');$checks++;}
function invalid(callable $fn):void{try{$fn();}catch(AuthException $e){expect(true);return;}throw new RuntimeException('Expected validation error');}
expect(N::coordinate('36.3','59.6')===['latitude'=>36.3,'longitude'=>59.6]);
foreach([[null,59],['',59],[91,59],[36,181],['bad',59],[INF,59],[NAN,59]] as $pair)invalid(fn()=>N::coordinate(...$pair));
$encoded='_p~iF~ps|U_ulLnnqC_mqNvxq`@';
expect(N::decodePolyline($encoded)===[[38.5,-120.2],[40.7,-120.95],[43.252,-126.453]]);
expect(N::decodePolyline('')===[]);
invalid(fn()=>N::decodePolyline('_'));
invalid(fn()=>N::decodePolyline(str_repeat('~',10)));
invalid(fn()=>N::decodePolyline(chr(1)));
$data=['routes'=>[['overview_polyline'=>['points'=>$encoded],'legs'=>[['distance'=>['value'=>1200],'duration'=>['value'=>60]],['distance'=>['value'=>300],'duration'=>['value'=>30]]]]]];
$r=N::routeResult($data);expect($r['provider']==='neshan'&&$r['distanceMeters']===1500&&$r['duration']==='90s'&&count($r['geometry'])===3);
invalid(fn()=>N::routeResult(['routes'=>[]]));
foreach([-1,INF,'bad',null] as $bad){$copy=$data;$copy['routes'][0]['legs'][0]['distance']['value']=$bad;invalid(fn()=>N::routeResult($copy));}
echo "Neshan coordinate, route totals and polyline: $checks checks passed.\n";
