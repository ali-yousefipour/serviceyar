<?php
declare(strict_types=1);
namespace ServiceYar\Reports;
use ServiceYar\Auth\Authorization; use ServiceYar\Database\Connection;
final class ReportService {
 public static function summary(int $uid,int $aid): array { Authorization::requirePermission($uid,'reports.view');Authorization::requireAgencyAccess($uid,$aid);$p=Connection::get();$out=[];foreach(['schools','students','drivers','services'] as $t){$q=$p->prepare("SELECT COUNT(*) FROM $t WHERE agency_id=:a AND is_active=1");$q->execute(['a'=>$aid]);$out[$t]=(int)$q->fetchColumn();} $q=$p->prepare('SELECT COUNT(*) FROM driver_work WHERE agency_id=:a AND work_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)');$q->execute(['a'=>$aid]);$out['driverWork30d']=(int)$q->fetchColumn();$q=$p->prepare('SELECT COALESCE(SUM(amount),0) FROM invoices WHERE agency_id=:a AND status IN ("open","overdue")');$q->execute(['a'=>$aid]);$out['openInvoicesAmount']=$q->fetchColumn();return $out; }
}
