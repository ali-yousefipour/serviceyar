<?php
declare(strict_types=1);
namespace ServiceYar\Reports;
use ServiceYar\Auth\Authorization; use ServiceYar\Database\Connection;
final class ReportService {
 public static function ledger(int $uid,int $aid,array $filter,bool $all=false): array {
  Authorization::requirePermission($uid,'reports.view');
  Authorization::requirePermission($uid,'finance.view');
  Authorization::requireAgencyAccess($uid,$aid);
  $pdo=Connection::get();
  $query=$filter;
  $query['page']=1;
  $query['limit']=1;
  $check=\ServiceYar\Domain\DomainService::list($uid,$aid,'ledger',$query);
  $total=(int)$check['pagination']['total'];
  if($all&&$total>5000)throw new \ServiceYar\Auth\AuthException(422,'گزارش بیش از ۵۰۰۰ ردیف است؛ بازه زمانی را محدودتر کنید.','REPORT_TOO_LARGE');
  $items=[];$limit=$all?200:min(max((int)($filter['limit']??50),1),200);
  $page=$all?1:max((int)($filter['page']??1),1);
  do{
   $query['limit']=$limit;$query['page']=$page;
   $result=\ServiceYar\Domain\DomainService::list($uid,$aid,'ledger',$query);
   foreach($result['items'] as $row)$items[]=$row;
   if(!$all)break;
   $page++;
  }while(count($items)<$total&&count($result['items'])===$limit);
  // Totals are calculated over the entire filtered dataset, not only the visible page.
  $receipts=0.0;$payments=0.0;
  if($all){foreach($items as $row){if($row['direction']==='receipt')$receipts+=(float)$row['amount'];elseif($row['direction']==='payment')$payments+=(float)$row['amount'];}}
  else{
   $cursor=1;
   do{
    $query['limit']=200;$query['page']=$cursor++;
    $chunk=\ServiceYar\Domain\DomainService::list($uid,$aid,'ledger',$query);
    foreach($chunk['items'] as $row){if($row['direction']==='receipt')$receipts+=(float)$row['amount'];elseif($row['direction']==='payment')$payments+=(float)$row['amount'];}
   }while($cursor<=(int)$chunk['pagination']['pages']);
  }
  return ['items'=>$items,'pagination'=>['page'=>$all?1:$page,'limit'=>$limit,'total'=>$total,'pages'=>$all?1:(int)ceil($total/$limit)],'totals'=>['receipts'=>$receipts,'payments'=>$payments,'balance'=>$receipts-$payments]];
 }
 public static function summary(int $uid,int $aid): array { Authorization::requirePermission($uid,'reports.view');Authorization::requireAgencyAccess($uid,$aid);$p=Connection::get();$out=[];foreach(['schools','students','drivers','services'] as $t){$q=$p->prepare("SELECT COUNT(*) FROM $t WHERE agency_id=:a AND is_active=1");$q->execute(['a'=>$aid]);$out[$t]=(int)$q->fetchColumn();} $q=$p->prepare('SELECT COUNT(*) FROM driver_work WHERE agency_id=:a AND work_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)');$q->execute(['a'=>$aid]);$out['driverWork30d']=(int)$q->fetchColumn();$q=$p->prepare('SELECT COALESCE(SUM(amount),0) FROM invoices WHERE agency_id=:a AND status IN ("open","overdue")');$q->execute(['a'=>$aid]);$out['openInvoicesAmount']=$q->fetchColumn();return $out; }
}
