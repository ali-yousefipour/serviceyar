<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class OperationsService
{
    public static function validDate(string $date): bool
    {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        return $d!==false && $d->format('Y-m-d')===$date;
    }

    private static function scope(int $uid,int $aid,string $permission): PDO
    {
        Authorization::requirePermission($uid,$permission);
        Authorization::requireAgencyAccess($uid,$aid);
        return Connection::get();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo=Connection::get();$owns=!$pdo->inTransaction();
        $savepoint='sy_'.bin2hex(random_bytes(5));
        if($owns)$pdo->beginTransaction();else $pdo->exec('SAVEPOINT '.$savepoint);
        try{$result=$callback($pdo);if($owns)$pdo->commit();else $pdo->exec('RELEASE SAVEPOINT '.$savepoint);return $result;}
        catch(\Throwable $e){if($pdo->inTransaction()){if($owns)$pdo->rollBack();else $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);}throw $e;}
    }

    public static function summary(int $uid,int $aid): array
    {
        $p=self::scope($uid,$aid,'dashboard.view');$counts=[];
        foreach(['schools','students','drivers','services'] as $t){$extra=$t==='drivers'?" AND status='active'":'';$q=$p->prepare("SELECT COUNT(*) FROM $t WHERE agency_id=:a AND is_active=1$extra");$q->execute(['a'=>$aid]);$counts[$t]=(int)$q->fetchColumn();}
        $q=$p->prepare('SELECT service_status,COUNT(*) total FROM students WHERE agency_id=:a AND is_active=1 GROUP BY service_status');$q->execute(['a'=>$aid]);$statuses=$q->fetchAll();
        $q=$p->prepare('SELECT sc.id,sc.name,sc.latitude,sc.longitude,COUNT(st.id) students,SUM(CASE WHEN st.service_status<>\'assigned\' THEN 1 ELSE 0 END) unassigned FROM schools sc LEFT JOIN students st ON st.school_id=sc.id AND st.agency_id=sc.agency_id AND st.is_active=1 WHERE sc.agency_id=:a AND sc.is_active=1 GROUP BY sc.id,sc.name,sc.latitude,sc.longitude ORDER BY sc.name');$q->execute(['a'=>$aid]);
        return ['counts'=>$counts,'statuses'=>$statuses,'schools'=>$q->fetchAll()];
    }

    public static function assign(int $uid,int $aid,int $serviceId,int $studentId,?string $address=null,?int $order=null): void
    {
        self::scope($uid,$aid,'services.manage');
        if($order!==null&&$order<1)throw new AuthException(422,'ترتیب توقف نامعتبر است.','VALIDATION_ERROR');
        self::transaction(function(PDO $p)use($aid,$serviceId,$studentId,$address,$order){
            $q=$p->prepare('SELECT * FROM services WHERE id=:id AND agency_id=:a AND is_active=1 FOR UPDATE');$q->execute(['id'=>$serviceId,'a'=>$aid]);$service=$q->fetch();
            if(!$service)throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');
            $q=$p->prepare('SELECT * FROM students WHERE id=:id AND agency_id=:a AND is_active=1 FOR UPDATE');$q->execute(['id'=>$studentId,'a'=>$aid]);$student=$q->fetch();
            if(!$student)throw new AuthException(404,'دانش‌آموز پیدا نشد.','NOT_FOUND');
            $q=$p->prepare('SELECT school_id FROM service_schools WHERE service_id=:s');$q->execute(['s'=>$serviceId]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if($service['school_id'])$allowed[]=(int)$service['school_id'];
            if(!$allowed||!in_array((int)$student['school_id'],$allowed,true))throw new AuthException(422,'مدرسه دانش‌آموز در مدارس سرویس نیست.','SCHOOL_MISMATCH');
            $q=$p->prepare('SELECT ss.service_id FROM service_students ss JOIN services s ON s.id=ss.service_id WHERE ss.student_id=:id AND s.agency_id=:a AND s.is_active=1');$q->execute(['id'=>$studentId,'a'=>$aid]);$assigned=$q->fetchAll(PDO::FETCH_COLUMN);
            foreach($assigned as $id)if((int)$id!==$serviceId)throw new AuthException(409,'دانش‌آموز سرویس فعال دیگری دارد. ابتدا تخصیص قبلی را حذف کنید.','ALREADY_ASSIGNED');
            if(!$assigned){$q=$p->prepare('SELECT COUNT(*) FROM service_students WHERE service_id=:s');$q->execute(['s'=>$serviceId]);if((int)$q->fetchColumn()>=(int)$service['capacity'])throw new AuthException(409,'ظرفیت سرویس تکمیل است.','CAPACITY_EXCEEDED');}
            $p->prepare('INSERT INTO service_students(service_id,student_id,pickup_address,pickup_order) VALUES(:s,:st,:p,:o) ON DUPLICATE KEY UPDATE pickup_address=VALUES(pickup_address),pickup_order=VALUES(pickup_order)')->execute(['s'=>$serviceId,'st'=>$studentId,'p'=>$address,'o'=>$order]);
            $p->prepare("UPDATE students SET service_status='assigned' WHERE id=:id AND agency_id=:a")->execute(['id'=>$studentId,'a'=>$aid]);
        });
        self::audit($uid,$aid,'assign_student','services',$serviceId);
    }

    public static function bulkFees(int $uid,int $aid,array $body): array
    {
        self::scope($uid,$aid,'services.manage');Authorization::requirePermission($uid,'students.manage');
        $ids=self::ids($body['ids']??[]);$mode=$body['mode']??'set';$value=$body['value']??null;
        if(!in_array($mode,['set','increase','decrease','percent'],true)||!is_numeric($value)||!is_finite((float)$value)||abs((float)$value)>1000000000000||($mode!=='percent'&&(float)$value<0)||($mode==='percent'&&(float)$value< -100))throw new AuthException(422,'نوع تغییر یا مبلغ نامعتبر است.','VALIDATION_ERROR');
        self::transaction(function(PDO $p)use($ids,$aid,$mode,$value){
            $q=$p->prepare('SELECT id,monthly_fee FROM students WHERE agency_id=? AND is_active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') FOR UPDATE');$q->execute(array_merge([$aid],$ids));$rows=$q->fetchAll();
            if(count($rows)!==count($ids))throw new AuthException(422,'انتخاب‌ها نامعتبر یا خارج از شرکت هستند.','CROSS_SCOPE_REFERENCE');
            $u=$p->prepare('UPDATE students SET monthly_fee=:v WHERE id=:id AND agency_id=:a');
            foreach($rows as $r){$old=(float)$r['monthly_fee'];$new=match($mode){'set'=>(float)$value,'increase'=>$old+(float)$value,'decrease'=>$old-(float)$value,'percent'=>$old*(1+(float)$value/100)};
                if($new<0||$new>999999999999999)throw new AuthException(422,'نتیجه تغییر هزینه خارج از محدوده است.','VALIDATION_ERROR');$u->execute(['v'=>round($new,2),'id'=>$r['id'],'a'=>$aid]);}
        });self::audit($uid,$aid,'bulk_fees','students',null);return ['updated'=>count($ids)];
    }

    public static function ids(mixed $raw): array
    {
        if(!is_array($raw)||!count($raw)||count($raw)>200)throw new AuthException(422,'بین ۱ تا ۲۰۰ مورد انتخاب کنید.','VALIDATION_ERROR');
        $ids=[];foreach($raw as $id){if(!ctype_digit((string)$id)||(int)$id<1)throw new AuthException(422,'شناسه نامعتبر است.','VALIDATION_ERROR');$ids[]=(int)$id;}return array_values(array_unique($ids));
    }

    public static function payroll(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'finance.view');[$from,$to]=self::range($filter);
        $q=$p->prepare('SELECT d.id,d.first_name,d.last_name,d.mobile,d.iban,d.bank_account,
          (SELECT COALESCE(SUM(w.amount),0) FROM driver_work w WHERE w.driver_id=d.id AND w.agency_id=d.agency_id AND w.work_date BETWEEN :wf AND :wt AND w.status<>\'cancelled\') earned,
          (SELECT COALESCE(SUM(dp.amount),0) FROM driver_payments dp WHERE dp.driver_id=d.id AND dp.agency_id=d.agency_id AND dp.payment_date BETWEEN :pf AND :pt) paid,
          (SELECT COALESCE(SUM(CASE WHEN le.direction=\'payment\' THEN le.amount ELSE -le.amount END),0) FROM ledger_entries le WHERE le.party_type=\'driver\' AND le.party_id=d.id AND le.agency_id=d.agency_id AND le.is_active=1 AND le.entry_date BETWEEN :lf AND :lt) ledger_paid,
          (SELECT COALESCE(SUM(s.agreed_amount),0) FROM services s WHERE s.driver_id=d.id AND s.agency_id=d.agency_id AND s.is_active=1) agreed
          FROM drivers d WHERE d.agency_id=:a AND d.is_active=1 ORDER BY d.last_name,d.first_name');
        $q->execute(['a'=>$aid,'wf'=>$from,'wt'=>$to,'pf'=>$from,'pt'=>$to,'lf'=>$from,'lt'=>$to]);$rows=$q->fetchAll();
        foreach($rows as &$r){$r['paid']=(float)$r['paid']+(float)$r['ledger_paid'];$r['balance']=(float)$r['earned']-$r['paid'];}unset($r);
        return ['items'=>$rows,'from'=>$from,'to'=>$to];
    }

    public static function range(array $q): array
    {
        $from=(string)($q['from']??date('Y-m-01'));$to=(string)($q['to']??date('Y-m-t'));
        if(!self::validDate($from)||!self::validDate($to)||$from>$to)throw new AuthException(422,'بازه تاریخ معتبر انتخاب کنید.','VALIDATION_ERROR');return [$from,$to];
    }

    public static function ledgerReport(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'finance.view');[$from,$to]=self::range($filter);
        $q=$p->prepare('SELECT party_type,party_id,SUM(receipt_amount) receipts,SUM(payment_amount) payments FROM (
          SELECT party_type,party_id,CASE WHEN direction=\'receipt\' THEN amount ELSE 0 END receipt_amount,CASE WHEN direction=\'payment\' THEN amount ELSE 0 END payment_amount FROM ledger_entries WHERE agency_id=:a1 AND is_active=1 AND entry_date BETWEEN :f1 AND :t1
          UNION ALL SELECT COALESCE(i.party_type,\'invoice\'),COALESCE(i.party_id,i.id),p.amount,0 FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id WHERE p.agency_id=:a2 AND p.payment_date BETWEEN :f2 AND :t2
          UNION ALL SELECT \'driver\',driver_id,0,amount FROM driver_payments WHERE agency_id=:a3 AND payment_date BETWEEN :f3 AND :t3
        ) entries GROUP BY party_type,party_id ORDER BY party_type,party_id');$q->execute(['a1'=>$aid,'f1'=>$from,'t1'=>$to,'a2'=>$aid,'f2'=>$from,'t2'=>$to,'a3'=>$aid,'f3'=>$from,'t3'=>$to]);return ['items'=>$q->fetchAll(),'from'=>$from,'to'=>$to];
    }

    public static function schoolReport(int $uid,int $aid): array
    {
        $p=self::scope($uid,$aid,'reports.view');Authorization::requirePermission($uid,'schools.view');
        $q=$p->prepare('SELECT sc.id,sc.code,sc.name,sc.shift,sc.school_type,COUNT(st.id) students,SUM(CASE WHEN st.service_status=\'assigned\' THEN 1 ELSE 0 END) assigned,COALESCE(SUM(st.monthly_fee),0) monthly_fees FROM schools sc LEFT JOIN students st ON st.school_id=sc.id AND st.agency_id=sc.agency_id AND st.is_active=1 WHERE sc.agency_id=:a AND sc.is_active=1 GROUP BY sc.id,sc.code,sc.name,sc.shift,sc.school_type ORDER BY sc.name');$q->execute(['a'=>$aid]);return ['items'=>$q->fetchAll()];
    }

    public static function serviceSchools(int $uid,int $aid,int $service): array
    {
        self::scope($uid,$aid,'services.view');DomainService::get($uid,$aid,'services',$service);$q=Connection::get()->prepare('SELECT sc.id,sc.name,sc.latitude,sc.longitude FROM service_schools ss JOIN schools sc ON sc.id=ss.school_id WHERE ss.service_id=:s AND sc.agency_id=:a ORDER BY sc.name');$q->execute(['s'=>$service,'a'=>$aid]);return $q->fetchAll();
    }

    public static function setServiceSchools(int $uid,int $aid,int $service,array $body): array
    {
        self::scope($uid,$aid,'services.manage');$ids=self::ids($body['schoolIds']??[]);
        return self::transaction(function(PDO $p)use($uid,$aid,$service,$ids){
            $q=$p->prepare('SELECT id FROM services WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$service,'a'=>$aid]);if(!$q->fetchColumn())throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');
            foreach($ids as $id)DomainService::party($p,$aid,'school',$id);
            $q=$p->prepare('SELECT DISTINCT st.school_id FROM service_students ss JOIN students st ON st.id=ss.student_id WHERE ss.service_id=:s AND st.agency_id=:a');$q->execute(['s'=>$service,'a'=>$aid]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $school)if(!in_array((int)$school,$ids,true))throw new AuthException(409,'برای حذف این مدرسه، ابتدا تخصیص دانش‌آموزان آن را حذف کنید.','SCHOOL_MISMATCH');
            $p->prepare('DELETE FROM service_schools WHERE service_id=?')->execute([$service]);$q=$p->prepare('INSERT INTO service_schools(service_id,school_id) VALUES(?,?)');foreach($ids as $id)$q->execute([$service,$id]);$p->prepare('UPDATE services SET school_id=:school WHERE id=:id AND agency_id=:a')->execute(['school'=>$ids[0],'id'=>$service,'a'=>$aid]);self::audit($uid,$aid,'set_service_schools','services',$service);return ['schoolIds'=>$ids];
        });
    }

    public static function queueMessages(int $uid,int $aid,array $body): array
    {
        $p=self::scope($uid,$aid,'messages.manage');$type=(string)($body['recipientType']??'');$resource=['student'=>'students','driver'=>'drivers'][$type]??null;
        if(!$resource)throw new AuthException(422,'نوع گیرنده نامعتبر است.','VALIDATION_ERROR');
        Authorization::requirePermission($uid,$resource.'.view');$ids=self::ids($body['ids']??[]);$text=trim((string)($body['body']??''));
        if($text===''||strlen($text)>6000)throw new AuthException(422,'متن پیام را وارد کنید (حداکثر ۶۰۰۰ بایت).','VALIDATION_ERROR');
        self::transaction(function(PDO $p)use($aid,$uid,$type,$ids,$text){foreach($ids as $id){DomainService::party($p,$aid,$type,$id);DomainService::save($uid,$aid,'messages',['channel'=>'sms','recipientType'=>$type,'recipientId'=>$id,'body'=>$text,'status'=>'queued']);}});
        return ['queued'=>count($ids),'delivery'=>'pending_provider'];
    }

    public static function changeDriver(int $uid,int $aid,int $id,string $status): array
    {
        self::scope($uid,$aid,'services.manage');if(!in_array($status,['approved','rejected'],true))throw new AuthException(422,'وضعیت نامعتبر است.','VALIDATION_ERROR');
        return self::transaction(function(PDO $p)use($uid,$aid,$id,$status){
            $q=$p->prepare('SELECT * FROM driver_changes WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$id,'a'=>$aid]);$row=$q->fetch();
            if(!$row)throw new AuthException(404,'درخواست پیدا نشد.','NOT_FOUND');if($row['status']!=='pending')throw new AuthException(409,'این درخواست قبلاً بررسی شده است.','ALREADY_REVIEWED');
            if($status==='approved'){DomainService::party($p,$aid,'driver',(int)$row['new_driver_id']);$q=$p->prepare('SELECT driver_id FROM services WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$row['service_id'],'a'=>$aid]);$driver=$q->fetchColumn();if($driver===false)throw new AuthException(404,'سرویس پیدا نشد.','NOT_FOUND');if((int)$driver!==(int)$row['old_driver_id'])throw new AuthException(409,'راننده سرویس تغییر کرده است؛ درخواست جدید ثبت کنید.','STALE_DRIVER');$p->prepare('UPDATE services SET driver_id=:d WHERE id=:s AND agency_id=:a')->execute(['d'=>$row['new_driver_id'],'s'=>$row['service_id'],'a'=>$aid]);}
            $p->prepare('UPDATE driver_changes SET status=:s,reviewed_by=:u,reviewed_at=CURRENT_TIMESTAMP WHERE id=:id AND agency_id=:a')->execute(['s'=>$status,'u'=>$uid,'id'=>$id,'a'=>$aid]);self::audit($uid,$aid,'driver_change_'.$status,'driver_changes',$id);return ['status'=>$status];
        });
    }

    public static function grouping(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'services.view');Authorization::requirePermission($uid,'students.view');$school=(int)($filter['schoolId']??0);DomainService::party($p,$aid,'school',$school);
        $capacity=(int)($filter['capacity']??4);if($capacity<1||$capacity>50)throw new AuthException(422,'ظرفیت باید بین ۱ تا ۵۰ باشد.','VALIDATION_ERROR');
        $q=$p->prepare('SELECT st.id,st.first_name,st.last_name,st.latitude,st.longitude,st.address FROM students st WHERE st.agency_id=:a AND st.school_id=:s AND st.is_active=1 AND st.service_status<>\'cancelled\' AND NOT EXISTS(SELECT 1 FROM service_students ss JOIN services sv ON sv.id=ss.service_id WHERE ss.student_id=st.id AND sv.agency_id=st.agency_id AND sv.is_active=1) ORDER BY st.id LIMIT 1000');$q->execute(['a'=>$aid,'s'=>$school]);$rows=$q->fetchAll();$unlocated=[];$located=[];
        foreach($rows as $r){if($r['latitude']===null||$r['longitude']===null)$unlocated[]=$r;else $located[]=$r;}
        $groups=[];while($located){$group=[array_shift($located)];while(count($group)<$capacity&&$located){$last=$group[count($group)-1];$closest=0;$distance=INF;foreach($located as $i=>$r){$d=self::distance($last,$r);if($d<$distance){$distance=$d;$closest=$i;}}$group[]=$located[$closest];array_splice($located,$closest,1);}$groups[]=$group;}
        return ['groups'=>$groups,'unlocated'=>$unlocated,'algorithm'=>'nearest-neighbour straight-line distance; review before assigning'];
    }

    public static function createGroup(int $uid,int $aid,array $body): array
    {
        self::scope($uid,$aid,'services.manage');$ids=self::ids($body['ids']??[]);
        if(count($ids)>(int)($body['capacity']??0))throw new AuthException(422,'تعداد دانش‌آموزان از ظرفیت بیشتر است.','CAPACITY_EXCEEDED');
        return self::transaction(function()use($uid,$aid,$body,$ids){
            $service=DomainService::save($uid,$aid,'services',['title'=>$body['title']??'','schoolId'=>$body['schoolId']??null,'capacity'=>$body['capacity']??null,'status'=>'draft']);
            foreach($ids as $i=>$id)self::assign($uid,$aid,(int)$service['id'],$id,null,$i+1);
            return ['service'=>$service,'assigned'=>count($ids)];
        });
    }

    public static function distance(array $a,array $b): float
    {
        $lat1=deg2rad((float)$a['latitude']);$lat2=deg2rad((float)$b['latitude']);$dl=$lat2-$lat1;$dn=deg2rad((float)$b['longitude']-(float)$a['longitude']);$h=sin($dl/2)**2+cos($lat1)*cos($lat2)*sin($dn/2)**2;return 6371*2*asin(sqrt(min(1,max(0,$h))));
    }

    public static function distances(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid,'reports.view');Authorization::requirePermission($uid,'students.view');$threshold=(float)($filter['km']??20);
        if($threshold<=0||$threshold>1000)throw new AuthException(422,'فاصله نامعتبر است.','VALIDATION_ERROR');
        $q=$p->prepare('SELECT st.id,st.first_name,st.last_name,st.latitude,st.longitude,sc.name school,sc.latitude school_lat,sc.longitude school_lon FROM students st JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id WHERE st.agency_id=:a AND st.is_active=1 AND st.latitude IS NOT NULL AND st.longitude IS NOT NULL AND sc.latitude IS NOT NULL AND sc.longitude IS NOT NULL LIMIT 5000');$q->execute(['a'=>$aid]);$out=[];foreach($q as $r){$r['km']=round(self::distance($r,['latitude'=>$r['school_lat'],'longitude'=>$r['school_lon']]),2);if($r['km']>=$threshold)$out[]=$r;}return ['items'=>$out,'threshold'=>$threshold];
    }

    public static function audit(int $uid,int $aid,string $action,string $type,?int $id): void
    {
        $q=Connection::get()->prepare('INSERT INTO activity_logs(agency_id,user_id,action,entity_type,entity_id) VALUES(:a,:u,:x,:t,:i)');$q->execute(['a'=>$aid,'u'=>$uid,'x'=>$action,'t'=>$type,'i'=>$id]);
    }

    public static function documents(int $uid,int $aid,int $driver): array
    {
        $p=self::scope($uid,$aid,'drivers.view');DomainService::party($p,$aid,'driver',$driver);
        $q=$p->prepare('SELECT id,document_type,document_no,expires_at,status FROM driver_documents WHERE driver_id=:d ORDER BY id DESC');$q->execute(['d'=>$driver]);return $q->fetchAll();
    }

    public static function saveDocument(int $uid,int $aid,int $driver,array $body,?int $id=null): array
    {
        $p=self::scope($uid,$aid,'drivers.manage');DomainService::party($p,$aid,'driver',$driver);
        $type=trim((string)($body['documentType']??''));$date=$body['expiresAt']??null;$status=$body['status']??'valid';
        if($type===''||strlen($type)>80||($date&&!self::validDate((string)$date))||!in_array($status,['valid','expired','revoked'],true))throw new AuthException(422,'نوع، تاریخ یا وضعیت مدرک نامعتبر است.','VALIDATION_ERROR');
        $data=['d'=>$driver,'t'=>$type,'n'=>$body['documentNo']??null,'e'=>$date?:null,'s'=>$status];
        if($id){$q=$p->prepare('SELECT id FROM driver_documents WHERE id=:id AND driver_id=:d');$q->execute(['id'=>$id,'d'=>$driver]);if(!$q->fetchColumn())throw new AuthException(404,'مدرک پیدا نشد.','NOT_FOUND');$data['id']=$id;$p->prepare('UPDATE driver_documents SET document_type=:t,document_no=:n,expires_at=:e,status=:s WHERE id=:id AND driver_id=:d')->execute($data);}
        else{$p->prepare('INSERT INTO driver_documents(driver_id,document_type,document_no,expires_at,status) VALUES(:d,:t,:n,:e,:s)')->execute($data);$id=(int)$p->lastInsertId();}
        self::audit($uid,$aid,'save_document','drivers',$driver);return ['id'=>$id];
    }
}
