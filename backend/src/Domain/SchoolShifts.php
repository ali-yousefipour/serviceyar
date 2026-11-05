<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
final class SchoolShifts {
 public static function get(int $uid,int $aid,int $id): array {
  DomainService::get($uid,$aid,'schools',$id);
  $q=Connection::get()->prepare('SELECT plan_json FROM school_shift_plans WHERE school_id=?');$q->execute([$id]);
  return json_decode((string)$q->fetchColumn(),true)?:[];
 }
 public static function onDate(int $uid,int $aid,int $id,array $query): array {
  $rows=self::get($uid,$aid,$id);$date=$query['date']??'';$anchor=$query['weekAnchor']??'';
  if(!is_string($date)||!OperationsService::validDate($date))self::invalid();
  $day=new \DateTimeImmutable($date,new \DateTimeZone('Asia/Tehran'));$weekday=((int)$day->format('w')+1)%7;
  $week=null;
  if($anchor!==''){
   if(!is_string($anchor)||!OperationsService::validDate($anchor))self::invalid();
   $first=new \DateTimeImmutable($anchor,new \DateTimeZone('Asia/Tehran'));
   if($first->format('w')!=='6'||$date<$anchor)throw new AuthException(422,'مبدأ هفته باید شنبه و قبل از تاریخ بررسی باشد.','VALIDATION_ERROR');
   $week=intdiv((int)$first->diff($day)->days,7)+1;
  }
  $active=[];$unresolved=[];$keys=['sat','sun','mon','tue','wed','thu','fri'];
  foreach($rows as $row){
   if(in_array($weekday,$row['holidays'],true))continue;
   $pattern=$row['pattern'];
   if(in_array($pattern,['even_weeks','odd_weeks'],true)&&$week===null){$unresolved[]=$row['name'];continue;}
   // Day parity means Saturday/Monday/Wednesday are even attendance days.
   $matches=match($pattern){'all'=>true,'even_days'=>$weekday%2===0,'odd_days'=>$weekday%2===1,'even_weeks'=>$week%2===0,'odd_weeks'=>$week%2===1,default=>$pattern===$keys[$weekday]};
   if($matches)$active[]=$row;
  }
  return ['date'=>$date,'weekday'=>$weekday,'weekNumber'=>$week,'shifts'=>$active,'needsWeekAnchor'=>$unresolved];
 }
 public static function save(int $uid,int $aid,int $id,array $body): array {
  \ServiceYar\Auth\Authorization::requirePermission($uid,'schools.manage');
  return OperationsService::transaction(function($p)use($uid,$aid,$id,$body){
   DomainService::get($uid,$aid,'schools',$id);
   $rows=$body['shifts']??null;if(!is_array($rows)||!array_is_list($rows)||count($rows)>20)self::invalid();
   $out=[];$names=[];
   foreach($rows as $r){
    if(!is_array($r))self::invalid();
    $name=$r['name']??null;$pattern=$r['pattern']??null;$start=$r['start']??null;$end=$r['end']??null;$holidays=$r['holidays']??[];
    if(!is_string($name)||trim($name)===''||preg_match_all('/./us',$name)>80||isset($names[trim($name)])||!in_array($pattern,['all','even_days','odd_days','even_weeks','odd_weeks','sat','sun','mon','tue','wed','thu','fri'],true))self::invalid();
    foreach([$start,$end] as $time)if(!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time))self::invalid();
    if($start>=$end||!is_array($holidays)||!array_is_list($holidays)||count($holidays)>7)self::invalid();
    foreach($holidays as $day)if(!is_int($day)||$day<0||$day>6)self::invalid();
    $names[trim($name)]=true;$out[]=['name'=>trim($name),'pattern'=>$pattern,'start'=>$start,'end'=>$end,'holidays'=>array_values(array_unique($holidays))];
   }
   $p->prepare('INSERT INTO school_shift_plans(school_id,plan_json) VALUES(?,?) ON DUPLICATE KEY UPDATE plan_json=VALUES(plan_json)')->execute([$id,json_encode($out,JSON_UNESCAPED_UNICODE)]);
   OperationsService::audit($uid,$aid,'save_school_shifts','schools',$id);return $out;
  });
 }
 private static function invalid(): never {throw new AuthException(422,'نام یکتا، الگوی معتبر، ساعت ورود قبل از خروج و روزهای تعطیل معتبر لازم است.','VALIDATION_ERROR');}
}
