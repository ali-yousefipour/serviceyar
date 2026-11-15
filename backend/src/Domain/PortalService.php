<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

/**
 * Self-service parent / driver portal. Access is always derived from verified
 * Bale contact memberships; client-supplied party IDs are never trusted.
 * All endpoints are read-only except whitelisted profile/password fields and
 * an approval-required vehicle change request.
 */
final class PortalService {
    private static bool $schemaReady=false;
    private static function ensureSchema(): void {
        if(self::$schemaReady)return;
        $pdo=Connection::get();
        try {
            $tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
                 ('portal_memberships','portal_credentials','portal_vehicle_change_requests')")
                ->fetchAll(PDO::FETCH_COLUMN);
            if(count($tables)!==3) {
                // Existing shared hosting deploy uploads PHP/SQL over FTP but
                // cannot execute the migration through an SSH shell.
                // Create only the idempotent, audited 0115 schema, never ALTER
                // unrelated production tables or backfill unauthorized users.
                $sql=file_get_contents(dirname(__DIR__,3).'/database/migrations/0115_parent_driver_portals.sql');
                if(!is_string($sql)||$sql==='')throw new \RuntimeException('Portal migration file missing');
                $sql=preg_replace('/^\s*--[^\n]*$/m','',$sql);
                foreach(preg_split('/;\s*(?:\r?\n|$)/',(string)$sql) as $statement) {
                    if(trim($statement)!=='')$pdo->exec(trim($statement));
                }
            }
            $pdo->exec("INSERT IGNORE INTO roles(name,title) VALUES
                ('parent_portal','ولی دانش‌آموز'),('driver_portal','راننده سرویس مدارس')");
            self::$schemaReady=true;
        } catch(\Throwable $error) {
            error_log('[ServiceYar][portal] Schema not ready: '.$error->getMessage());
            throw new AuthException(503,'جداول پنل والدین و رانندگان آماده نیستند؛ مهاجرت 0115 را اجرا کنید.','PORTAL_SCHEMA_REQUIRED');
        }
    }
    private static function mobile(string $value): string {
        $value=strtr(trim($value),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        if(str_starts_with($value,'+98'))$value='0'.substr($value,3);
        if(str_starts_with($value,'98')&&strlen($value)===12)$value='0'.substr($value,2);
        return preg_match('/^09[0-9]{9}$/D',$value)?$value:'';
    }
    private static function rows(PDO $pdo,string $sql,array $params): array {
        $q=$pdo->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    private static function uuid(): string {
        $v=random_bytes(16);$v[6]=chr((ord($v[6])&15)|64);$v[8]=chr((ord($v[8])&63)|128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($v),4));
    }
    /** Invoked ONLY after the Bale webhook validates contact.user_id==message.from.id.
     * Never accept an HTTP-supplied phone number as enrollment proof.
     */
    public static function provisionVerifiedMobile(string $phone): ?int {
        $mobile=self::mobile($phone);if($mobile==='')return null;
        self::ensureSchema();$pdo=Connection::get();
        $parent=self::rows($pdo,"SELECT DISTINCT s.agency_id,s.id party_id,'parent' party_type
          FROM students s LEFT JOIN guardians g ON g.student_id=s.id
          JOIN agencies a ON a.id=s.agency_id AND a.is_active=1
          WHERE s.is_active=1 AND (g.mobile=? OR
            (s.mobile=? AND (s.guardian_first_name IS NOT NULL OR s.guardian_last_name IS NOT NULL)))",
            [$mobile,$mobile]);
        $drivers=self::rows($pdo,"SELECT d.agency_id,d.id party_id,'driver' party_type
          FROM drivers d JOIN agencies a ON a.id=d.agency_id AND a.is_active=1
          WHERE d.mobile=? AND d.is_active=1",[$mobile]);
        $members=array_merge($parent,$drivers);
        if(!$members)return null;
        // Refuse account collisions and privileged existing identities. Portal
        // enrollment never silently changes a staff user's roles or username.
        $matches=self::rows($pdo,"SELECT u.id,u.username,u.mobile FROM users u WHERE
          u.is_active=1 AND (u.username=? OR u.mobile=?) LIMIT 3",[$mobile,$mobile]);
        if(count($matches)>1)return null;
        $userId=$matches?(int)$matches[0]['id']:null;
        if($matches && ((string)$matches[0]['username']!==$mobile ||
            (self::mobile((string)($matches[0]['mobile']??''))!=='' &&
             self::mobile((string)$matches[0]['mobile'])!==$mobile)))return null;
        if($userId){
            $role=self::rows($pdo,"SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?",[$userId]);
            if(array_filter($role,static fn($r)=>!in_array($r['name'],['parent_portal','driver_portal'],true)))return null;
        }
        $owned=!$pdo->inTransaction();if($owned)$pdo->beginTransaction();
        try {
            if(!$userId){
                $pdo->prepare("INSERT INTO users(uuid,username,password_hash,mobile,is_active)
                    VALUES(?,?,?,?,1)")->execute([self::uuid(),$mobile,
                      password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$mobile]);
                $userId=(int)$pdo->lastInsertId();
            }
            $pdo->prepare("INSERT IGNORE INTO portal_credentials(user_id) VALUES(?)")->execute([$userId]);
            $roles=[];
            foreach($members as $m){
                $role=(string)$m['party_type'];$roles[$role]=true;
                $pdo->prepare("INSERT IGNORE INTO portal_memberships(user_id,agency_id,party_type,party_id,verified_mobile)
                  VALUES(?,?,?,?,?)")->execute([$userId,(int)$m['agency_id'],$role,(int)$m['party_id'],$mobile]);
            }
            foreach(array_keys($roles) as $type)
                $pdo->prepare("INSERT IGNORE INTO user_roles(user_id,role_id)
                    SELECT ?,id FROM roles WHERE name=?")->execute([$userId,$type.'_portal']);
            if($owned)$pdo->commit();return $userId;
        }catch(\Throwable $e){if($owned&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private static function authorized(int $uid,string $type,int $agencyId,int $partyId): void {
        self::ensureSchema();
        if(!in_array($type,['parent','driver'],true)||$agencyId<1||$partyId<1)
            throw new AuthException(404,'اطلاعات درخواست‌شده یافت نشد.','PORTAL_NOT_FOUND');
        $q=Connection::get()->prepare("SELECT 1 FROM portal_memberships m JOIN users u ON u.id=m.user_id AND u.is_active=1
             JOIN agencies a ON a.id=m.agency_id AND a.is_active=1
             WHERE m.user_id=? AND m.party_type=? AND m.agency_id=? AND m.party_id=?");
        $q->execute([$uid,$type,$agencyId,$partyId]);
        if(!$q->fetchColumn())throw new AuthException(403,'دسترسی به این اطلاعات مجاز نیست.','PORTAL_FORBIDDEN');
    }
    public static function memberships(int $uid): array {
        self::ensureSchema();
        return self::rows(Connection::get(),"SELECT m.party_type portalType,m.party_id partyId,
          m.agency_id agencyId,a.name agencyName FROM portal_memberships m
          JOIN agencies a ON a.id=m.agency_id AND a.is_active=1
          JOIN users u ON u.id=m.user_id AND u.is_active=1
          WHERE m.user_id=? ORDER BY m.party_type,m.agency_id,m.party_id",[$uid]);
    }
    public static function dashboard(int $uid,string $type,int $agencyId,int $partyId): array {
        self::authorized($uid,$type,$agencyId,$partyId);
        $pdo=Connection::get();
        $agency=self::rows($pdo,"SELECT id,name FROM agencies WHERE id=? AND is_active=1",[$agencyId])[0]??null;
        $configured=self::rows($pdo,"SELECT password_set_at FROM portal_credentials WHERE user_id=?",[$uid]);
        $profile=self::rows($pdo,"SELECT username,first_name,last_name,mobile,address,postal_code,email FROM users WHERE id=?",[$uid])[0]??[];
        $base=['portalType'=>$type,'agency'=>$agency,'profile'=>$profile,
            'hasPassword'=>!empty($configured[0]['password_set_at']),'readonly'=>false];
        if($type==='parent'){
            $student=self::rows($pdo,"SELECT st.id,st.first_name,st.last_name,st.grade,st.address,
                st.return_address,st.monthly_fee,st.academic_year,st.service_status,
                sc.name schoolName,sc.address schoolAddress
                FROM students st JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
                WHERE st.id=? AND st.agency_id=? AND st.is_active=1",[$partyId,$agencyId])[0]??null;
            if(!$student)throw new AuthException(404,'دانش‌آموز فعال پیدا نشد.','PORTAL_NOT_FOUND');
            $guardian=self::rows($pdo,"SELECT id,name,mobile,relation_title FROM guardians
                WHERE student_id=? AND mobile=? ORDER BY is_primary DESC LIMIT 1",
                [$partyId,(string)($profile['mobile']??'')])[0]??null;
            $services=self::rows($pdo,"SELECT sv.id,sv.title,sv.code,ss.pickup_address,ss.pickup_order,
                d.first_name driverFirstName,d.last_name driverLastName,d.mobile driverMobile,
                v.plate,v.model,v.capacity
                FROM service_students ss JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=?
                  AND sv.is_active=1 AND sv.status<>'cancelled'
                LEFT JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id AND d.is_active=1
                LEFT JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=sv.agency_id AND v.is_active=1
                WHERE ss.student_id=? ORDER BY sv.id",[$agencyId,$partyId]);
            $invoices=self::rows($pdo,"SELECT id,title,amount,status,issue_date,due_date
                FROM invoices WHERE agency_id=? AND party_type='student' AND party_id=?
                AND status<>'cancelled' ORDER BY issue_date DESC,id DESC",[$agencyId,$partyId]);
            $payments=self::rows($pdo,"SELECT p.id,p.amount,p.payment_date,p.method,p.reference_no,p.invoice_id
                FROM payments p JOIN invoices i ON i.id=p.invoice_id AND i.agency_id=p.agency_id
                WHERE i.agency_id=? AND i.party_type='student' AND i.party_id=?
                ORDER BY p.payment_date DESC,p.id DESC",[$agencyId,$partyId]);
            $ledger=self::rows($pdo,"SELECT id,amount,entry_date AS payment_date,direction,method,reference_no
                FROM ledger_entries WHERE agency_id=? AND party_type='student' AND party_id=?
                  AND is_active=1 ORDER BY entry_date DESC,id DESC",[$agencyId,$partyId]);
            $received=array_sum(array_map(static fn($p)=>(float)$p['amount'],$payments));
            foreach($ledger as $line){
                $value=($line['direction']==='receipt'?1:-1)*(float)$line['amount'];
                $received+=$value;$payments[]=['id'=>$line['id'],'amount'=>$value,
                  'payment_date'=>$line['payment_date'],'method'=>$line['method'],
                  'reference_no'=>$line['reference_no'],'source'=>'ledger'];
            }
            $total=array_sum(array_map(static fn($i)=>(float)$i['amount'],$invoices));
            $available=max(0,$received);
            for($i=count($invoices)-1;$i>=0;$i--){
                $allocated=min($available,(float)$invoices[$i]['amount']);$available-=$allocated;
                $invoices[$i]['paidAmount']=$allocated;
                $invoices[$i]['remainingAmount']=max(0,(float)$invoices[$i]['amount']-$allocated);
            }
            return $base+['student'=>$student,'guardian'=>$guardian,'services'=>$services,
                'finance'=>['invoices'=>$invoices,'payments'=>$payments,
                   'total'=>$total,'paid'=>$received,'balance'=>max(0,$total-$received),
                   'credit'=>max(0,$received-$total)],
                'installmentsNote'=>'برنامه اقساط بر اساس سند قطعی شرکت نمایش داده می‌شود؛ برای تاریخ سررسید قطعی از شرکت استعلام کنید.'];
        }
        $driver=self::rows($pdo,"SELECT d.id,d.first_name,d.last_name,d.mobile,d.national_code,
            d.address,d.bank_name,d.bank_account,d.iban,d.status,v.id vehicleId,
            v.plate,v.model,v.capacity
            FROM drivers d LEFT JOIN vehicles v ON v.id=d.vehicle_id AND v.agency_id=d.agency_id
            WHERE d.id=? AND d.agency_id=? AND d.is_active=1",[$partyId,$agencyId])[0]??null;
        if(!$driver)throw new AuthException(404,'راننده فعال پیدا نشد.','PORTAL_NOT_FOUND');
        $services=self::rows($pdo,"SELECT sv.id,sv.title,sv.code,sv.monthly_fee,sv.capacity,
            sc.id schoolId,sc.name schoolName,sc.address schoolAddress,
            st.id studentId,st.first_name,st.last_name,st.address,st.return_address,
            ss.pickup_address,ss.pickup_order
            FROM services sv JOIN service_students ss ON ss.service_id=sv.id
            JOIN students st ON st.id=ss.student_id AND st.agency_id=sv.agency_id AND st.is_active=1
            JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=sv.agency_id
            WHERE sv.agency_id=? AND sv.driver_id=? AND sv.is_active=1
            AND sv.status<>'cancelled' ORDER BY sc.name,sv.id,ss.pickup_order,st.last_name",
            [$agencyId,$partyId]);
        $earnings=self::rows($pdo,"SELECT id,work_date,amount,status,service_id FROM driver_work
            WHERE agency_id=? AND driver_id=? ORDER BY work_date DESC,id DESC LIMIT 200",[$agencyId,$partyId]);
        $payments=self::rows($pdo,"SELECT id,amount,payment_date,reference_no
            FROM driver_payments WHERE agency_id=? AND driver_id=? ORDER BY payment_date DESC,id DESC",
            [$agencyId,$partyId]);
        $ledger=self::rows($pdo,"SELECT id,amount,entry_date AS payment_date,direction,reference_no
            FROM ledger_entries WHERE agency_id=? AND party_type='driver' AND party_id=?
            AND is_active=1 ORDER BY entry_date DESC,id DESC",[$agencyId,$partyId]);
        $paid=array_sum(array_map(static fn($p)=>(float)$p['amount'],$payments));
        foreach($ledger as $line){
            $value=($line['direction']==='payment'?1:-1)*(float)$line['amount'];$paid+=$value;
            $payments[]=['id'=>$line['id'],'amount'=>$value,'payment_date'=>$line['payment_date'],
                'reference_no'=>$line['reference_no'],'source'=>'ledger'];
        }
        $gross=array_sum(array_map(static fn($e)=>(float)$e['amount'],$earnings));
        $net=self::driverNet($agencyId,$gross);
        $monthlyServices=[];
        foreach($services as $r)$monthlyServices[(int)$r['id']]=['id'=>(int)$r['id'],
            'title'=>$r['title'],'code'=>$r['code'],'monthlyFee'=>$r['monthly_fee']];
        return $base+['driver'=>$driver,'students'=>$services,'services'=>array_values($monthlyServices),
            'finance'=>['earnings'=>$earnings,'payments'=>$payments,'recordedGross'=>$gross,
               'recordedNet'=>$net,'paid'=>$paid,'balance'=>max(0,$net-$paid),
               'monthlyServicesTotal'=>array_sum(array_map(static fn($x)=>(float)$x['monthlyFee'], $monthlyServices)),
               'note'=>'جمع کارکرد فقط ردیف‌های ثبت‌شده را نشان می‌دهد؛ فیش نهایی و مبالغ قطعی شرکت ملاک تسویه هستند.']];
    }
    private static function driverNet(int $agencyId,float $gross): float {
        return (float)OperationsService::driverDeductions($gross,$agencyId)['net_earned'];
    }
    public static function updateProfile(int $uid,string $type,int $agencyId,int $partyId,array $input): array {
        self::authorized($uid,$type,$agencyId,$partyId);
        $pdo=Connection::get();
        $allowed=$type==='parent'
            ?['address','returnAddress','guardianName','guardianRelation','email']
            :['address','bankName','bankAccount','iban','email'];
        foreach($input as $key=>$value){
            if(!in_array((string)$key,$allowed,true)||!is_string($value)||mb_strlen($value)>700)
                throw new AuthException(422,'فیلد نامعتبر یا طول اطلاعات بیش از حد مجاز است.','PORTAL_PROFILE_INVALID');
        }
        $owned=!$pdo->inTransaction();if($owned)$pdo->beginTransaction();
        try {
            if($type==='parent'){
                if(array_key_exists('address',$input)||array_key_exists('returnAddress',$input)){
                    $q=$pdo->prepare("UPDATE students SET address=COALESCE(?,address),
                      return_address=COALESCE(?,return_address) WHERE id=? AND agency_id=? AND is_active=1");
                    $q->execute([$input['address']??null,$input['returnAddress']??null,$partyId,$agencyId]);
                }
                if(array_key_exists('guardianName',$input)||array_key_exists('guardianRelation',$input)){
                    $mobile=self::rows($pdo,"SELECT mobile FROM users WHERE id=?",[$uid])[0]['mobile']??'';
                    $q=$pdo->prepare("UPDATE guardians SET name=COALESCE(?,name),relation_title=COALESCE(?,relation_title)
                       WHERE student_id=? AND mobile=?");
                    $q->execute([$input['guardianName']??null,$input['guardianRelation']??null,$partyId,$mobile]);
                }
            }else{
                $q=$pdo->prepare("UPDATE drivers SET address=COALESCE(?,address),bank_name=COALESCE(?,bank_name),
                  bank_account=COALESCE(?,bank_account),iban=COALESCE(?,iban)
                  WHERE id=? AND agency_id=? AND is_active=1");
                if(isset($input['iban'])&&$input['iban']!==''&&!preg_match('/^IR[0-9]{24}$/D',strtoupper($input['iban'])))
                    throw new AuthException(422,'شماره شبا باید با IR شروع شده و ۲۴ رقم داشته باشد.','INVALID_IBAN');
                $q->execute([$input['address']??null,$input['bankName']??null,
                    $input['bankAccount']??null,isset($input['iban'])?strtoupper($input['iban']):null,
                    $partyId,$agencyId]);
            }
            if(array_key_exists('email',$input)){
                $email=trim($input['email']);
                if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))
                    throw new AuthException(422,'نشانی ایمیل معتبر نیست.','INVALID_EMAIL');
                $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute([$email,$uid]);
            }
            if($owned)$pdo->commit();
        }catch(\Throwable $e){if($owned&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
        return self::dashboard($uid,$type,$agencyId,$partyId);
    }
    public static function setPassword(int $uid,string $password,string $confirm,string $current=''): array {
        if(!self::memberships($uid))throw new AuthException(403,'حساب پنل یافت نشد.','PORTAL_FORBIDDEN');
        if(strlen($password)<10||strlen($password)>200||$password!==$confirm)
            throw new AuthException(422,'رمز باید حداقل ۱۰ کاراکتر داشته باشد و تکرار آن یکسان باشد.','INVALID_PASSWORD');
        $pdo=Connection::get();$pdo->beginTransaction();
        try {
            $q=$pdo->prepare("SELECT c.password_set_at,u.password_hash FROM portal_credentials c
               JOIN users u ON u.id=c.user_id WHERE c.user_id=? FOR UPDATE");
            $q->execute([$uid]);$state=$q->fetch(PDO::FETCH_ASSOC);
            if(!$state)throw new AuthException(403,'ثبت رمز برای این حساب مجاز نیست.','PORTAL_FORBIDDEN');
            if($state['password_set_at']!==null&&!password_verify($current,$state['password_hash']))
                throw new AuthException(403,'برای تغییر رمز، رمز فعلی را وارد کنید.','CURRENT_PASSWORD_REQUIRED');
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$uid]);
            $pdo->prepare('UPDATE portal_credentials SET password_set_at=UTC_TIMESTAMP() WHERE user_id=?')->execute([$uid]);
            $pdo->commit();return ['updated'=>true,'username'=>self::rows($pdo,'SELECT username FROM users WHERE id=?',[$uid])[0]['username']];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function requestVehicleChange(int $uid,int $agencyId,int $driverId,array $body): array {
        self::authorized($uid,'driver',$agencyId,$driverId);
        $pdo=Connection::get();$q=$pdo->prepare('SELECT vehicle_id FROM drivers WHERE id=? AND agency_id=? AND is_active=1');
        $q->execute([$driverId,$agencyId]);$vehicleId=(int)$q->fetchColumn();
        if(!$vehicleId)throw new AuthException(409,'خودروی فعالی برای راننده ثبت نشده است.','VEHICLE_MISSING');
        $model=trim((string)($body['model']??''));$plate=trim((string)($body['plate']??''));$note=trim((string)($body['note']??''));
        if(($model===''&&$plate==='')||mb_strlen($model)>100||mb_strlen($plate)>50||mb_strlen($note)>700)
            throw new AuthException(422,'مشخصات درخواست اصلاح خودرو معتبر نیست.','INVALID_VEHICLE_REQUEST');
        $pdo->prepare("INSERT INTO portal_vehicle_change_requests
            (user_id,agency_id,driver_id,vehicle_id,requested_model,requested_plate,note)
            VALUES(?,?,?,?,?,?,?)")->execute([$uid,$agencyId,$driverId,$vehicleId,$model?:null,$plate?:null,$note?:null]);
        return ['requestId'=>(int)$pdo->lastInsertId(),'status'=>'pending',
            'message'=>'درخواست اصلاح خودرو ثبت شد. تغییر پلاک و مدل فقط بعد از تأیید شرکت اعمال می‌شود.'];
    }
}
