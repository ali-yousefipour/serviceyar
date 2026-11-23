<?php
declare(strict_types=1);
require __DIR__.'/../backend/src/bootstrap.php';

// Exercise the actual validation block with the null produced for an empty form field.
$source=file_get_contents(__DIR__.'/../backend/src/Domain/DomainService.php');
$start=strpos($source,"  if(\$resource==='vehicles'&&isset(\$data['make_year'])");
if($start===false)throw new RuntimeException('Optional vehicle year validation missing');
$end=strpos($source,"  if(\$resource==='vehicles'&&\$id",$start);
$validation=substr($source,$start,$end-$start);
$run=static function($value)use($validation):void{
    $resource='vehicles';$data=['make_year'=>$value];
    eval('use ServiceYar\\Auth\\AuthException;'.$validation);
};
foreach([null,'','1300','1393','1600'] as $value)$run($value);
foreach(['0','1299','1601','abc','1393.5'] as $value){
    try{$run($value);throw new RuntimeException('Invalid year accepted');}
    catch(\ServiceYar\Auth\AuthException $e){if($e->codeName!=='INVALID_MAKE_YEAR')throw $e;}
}
echo "Optional vehicle year validation: OK\n";
