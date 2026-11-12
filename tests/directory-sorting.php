<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Domain\DomainService;
use ServiceYar\Auth\AuthException;

$method=new ReflectionMethod(DomainService::class,'listOrdering');
$cases=[
    ['students',['sortBy'=>'last_name','sortDir'=>'asc'],'students.last_name ASC, students.id DESC'],
    ['students',['sortBy'=>'monthly_fee','sortDir'=>'desc'],'students.monthly_fee DESC, students.id DESC'],
    ['drivers',['sortBy'=>'mobile','sortDir'=>'asc'],'drivers.mobile ASC, drivers.id DESC'],
    ['services',['sortBy'=>'capacity','sortDir'=>'asc'],'services.capacity ASC, services.id DESC'],
    ['vehicles',['sortBy'=>'plate','sortDir'=>'asc'],'vehicles.plate ASC, vehicles.id DESC'],
    ['ledger',['sortBy'=>'amount','sortDir'=>'desc'],'ledger_entries.amount DESC, ledger_entries.id DESC'],
    ['invoices',[],'invoices.id DESC'],
];
foreach($cases as [$resource,$query,$expected]){
    $actual=$method->invoke(null,$resource,$query);
    if($actual!==$expected)throw new RuntimeException("Unexpected order for {$resource}: {$actual}");
}
foreach([
    ['students',['sortBy'=>'id;DROP TABLE students','sortDir'=>'asc']],
    ['students',['sortBy'=>'nonexistent_field','sortDir'=>'asc']],
    ['students',['sortBy'=>'last_name','sortDir'=>'ASC;DELETE']],
    ['ledger',['sortBy'=>'gross','sortDir'=>'desc']],
    ['vehicles',['sortBy'=>['plate'],'sortDir'=>'asc']],
] as [$resource,$query]){
    try{$method->invoke(null,$resource,$query);throw new RuntimeException('Invalid ordering accepted');}
    catch(AuthException $e){if($e->status!==422)throw $e;}
}
echo "Allowed directory ordering and SQL-injection rejection passed\n";
