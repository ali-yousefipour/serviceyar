<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/bootstrap.php';

use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use ServiceYar\Users\UserService;
use ServiceYar\Support\Uuid;

if(getenv('SERVICEYAR_TEST_DATABASE')!=='1'||
 !str_starts_with((string)config('DB_DATABASE'),'serviceyar_test'))
 throw new RuntimeException('Run only against an opted-in serviceyar_test database.');
$p=Connection::get();
$p->beginTransaction();
try {
 $roleId=(int)$p->query("SELECT id FROM roles WHERE name='school_company_manager'")->fetchColumn();
 if($roleId<1)throw new RuntimeException('Manager role must be seeded by migration 0114.');
 $makeAgency=function(string $name)use($p):int{
  $p->prepare('INSERT INTO agencies(uuid,name) VALUES(?,?)')->execute([Uuid::v4(),$name]);
  return (int)$p->lastInsertId();
 };
 $makeUser=function(string $username)use($p):int{
  $p->prepare('INSERT INTO users(uuid,username,password_hash,is_active) VALUES(?,?,?,1)')
    ->execute([Uuid::v4(),$username,password_hash('test-only-password',PASSWORD_DEFAULT)]);
  return (int)$p->lastInsertId();
 };
 $tag=bin2hex(random_bytes(4));
 $agency=$makeAgency('manager-test-own-'.$tag);
 $foreignAgency=$makeAgency('manager-test-other-'.$tag);
 $manager=$makeUser('manager_'.$tag);
 $ownOperator=$makeUser('operator_'.$tag);
 $foreignOperator=$makeUser('foreign_'.$tag);
 $p->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$manager,$roleId]);
 foreach([[$agency,$manager],[$agency,$ownOperator],[$foreignAgency,$foreignOperator]] as [$aid,$uid])
  $p->prepare('INSERT INTO agency_users(agency_id,user_id) VALUES(?,?)')->execute([$aid,$uid]);
 foreach(['users.view','users.manage'] as $perm)
  if(!in_array($perm,Authorization::permissions($manager),true))
   throw new RuntimeException('Missing company manager permission: '.$perm);
 $own=UserService::list($manager,$agency);
 $ids=array_map('intval',array_column($own,'id'));
 if(!in_array($ownOperator,$ids,true)||in_array($foreignOperator,$ids,true))
  throw new RuntimeException('Company manager user list leaked cross-agency users.');
 $reject=function(callable $fn,string $expected):void{
  try{$fn();}catch(AuthException $e){
    if($e->codeName===$expected)return;
    throw new RuntimeException('Unexpected error: '.$e->codeName.' expected '.$expected);
  }
  throw new RuntimeException('Expected '.$expected.' rejection');
 };
 $reject(fn()=>UserService::list($manager,$foreignAgency),'SCOPE_FORBIDDEN');
 $reject(fn()=>UserService::list($manager,0),'FORBIDDEN');
 $reject(fn()=>UserService::save($manager,$agency,['firstName'=>'not allowed'],$manager),'PROTECTED_USER');
 $p->rollBack();
 echo "School company manager authorization, tenant isolation and protected role: OK\n";
}catch(Throwable $e){
 if($p->inTransaction())$p->rollBack();
 throw $e;
}
