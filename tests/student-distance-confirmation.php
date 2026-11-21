<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Domain\DomainService;

$compare=new ReflectionMethod(DomainService::class,'studentDistanceChanged');
foreach ([
 ['5.00','5',false],
 ['05.000','۵',false],
 ['۵٫۰۰','5.0',false],
 ['٠٥٫٠٠','5',false],
 ['10.50','10.5',false],
 [null,null,false],
 ['5.00','6',true],
 ['5','6.00',true],
 ['4.5','5',true],
 [null,'5',true]
] as [$stored,$input,$expected]) {
    $actual=$compare->invoke(null,$stored,$input);
    if($actual!==$expected)throw new RuntimeException(
        'Distance comparison failed: '.json_encode([$stored,$input,$expected,$actual],JSON_UNESCAPED_UNICODE)
    );
}
echo "Student distance comparisons: decimal representation, Persian/Arabic digits and actual changes passed\n";
