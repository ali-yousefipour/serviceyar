<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

/** Authoritative read-only discount preview. No invoice or payroll mutations. */
final class StudentDiscountService
{
    public static function preview(int $uid,int $aid,array $input): array
    {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'finance.manage');
        Authorization::requirePermission($uid,'students.view');
        $sid=filter_var($input['studentId']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$sid)throw new AuthException(422,'دانش‌آموز معتبر انتخاب کنید.','INVALID_DISCOUNT_STUDENT');
        $parent=$input['applyToStudent']??null;
        $driver=$input['applyToDriver']??null;
        if(!is_bool($parent)||!is_bool($driver)||(!$parent&&!$driver))
            throw new AuthException(422,'حداقل یکی از تخفیف‌های دانش‌آموز یا راننده باید فعال باشد.','INVALID_DISCOUNT_SCOPE');
        $rate=StudentDiscountInput::rate($input);
        $from=VehicleClassEffectiveDates::requireDate($input['effectiveFrom']??null,'تاریخ شروع تخفیف');
        $pdo=Connection::get();
        $q=$pdo->prepare('SELECT st.id,st.first_name,st.last_name,st.monthly_fee,st.driver_base_monthly,st.academic_year,st.school_id FROM students st WHERE st.id=? AND st.agency_id=? AND st.is_active=1');
        $q->execute([$sid,$aid]);$student=$q->fetch(PDO::FETCH_ASSOC);
        if(!$student)throw new AuthException(404,'دانش‌آموز فعال یافت نشد.','STUDENT_NOT_FOUND');
        $q=$pdo->prepare("SELECT c.id,c.contract_no,c.start_date,c.end_date,c.amount,m.invoice_id,m.academic_year contract_year,i.amount invoice_amount,i.status invoice_status
            FROM contracts c JOIN student_contract_invoices m ON m.contract_id=c.id AND m.agency_id=c.agency_id AND m.student_id=c.party_id
            JOIN invoices i ON i.id=m.invoice_id AND i.agency_id=m.agency_id AND i.party_type='student' AND i.party_id=m.student_id
            WHERE c.agency_id=? AND c.party_type='student' AND c.party_id=? AND c.status='active'
              AND i.status<>'cancelled' AND c.start_date<=? AND c.end_date>=?
            ORDER BY c.id");
        $q->execute([$aid,$sid,$from,$from]);$contracts=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($contracts)!==1)
            throw new AuthException(409,'قرارداد قطعی یکتا برای تاریخ تخفیف پیدا نشد؛ ابتدا تطبیق مالی انجام دهید.','DISCOUNT_CONTRACT_RECONCILIATION_REQUIRED');
        $contract=$contracts[0];
        $to=(new \DateTimeImmutable($contract['end_date']))->modify('+1 day')->format('Y-m-d');
        if(isset($input['effectiveTo'])&&$input['effectiveTo']!==''){
            $to=VehicleClassEffectiveDates::requireDate($input['effectiveTo'],'تاریخ پایان تخفیف');
            if($to<=$from||$to>(new \DateTimeImmutable($contract['end_date']))->modify('+1 day')->format('Y-m-d'))
                throw new AuthException(422,'تاریخ پایان تخفیف خارج از محدوده قرارداد است.','INVALID_DISCOUNT_PERIOD');
        }
        $q=$pdo->prepare("SELECT sv.id service_id,sv.driver_id
            FROM service_students ss JOIN services sv ON sv.id=ss.service_id
            JOIN drivers d ON d.id=sv.driver_id AND d.agency_id=sv.agency_id AND d.is_active=1
            WHERE ss.student_id=? AND sv.agency_id=? AND sv.is_active=1 AND sv.status='active'");
        $q->execute([$sid,$aid]);$services=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($services)!==1)
            throw new AuthException(409,'سرویس و راننده فعال یکتا نیست؛ ابتدا تخصیص سرویس را اصلاح کنید.','DISCOUNT_SERVICE_RECONCILIATION_REQUIRED');
        if($student['monthly_fee']===null||$student['driver_base_monthly']===null||
            (int)$student['monthly_fee']<0||(int)$student['driver_base_monthly']<0)
            throw new AuthException(409,'مبنای مالی دانش‌آموز یا راننده مشخص نیست.','DISCOUNT_FINANCIAL_BASIS_REQUIRED');
        $year=ContractBilling::year((string)$student['academic_year']);
        if(ContractBilling::year((string)$contract['contract_year'])!==$year)
            throw new AuthException(409,'سال تحصیلی پرونده با قرارداد قطعی متفاوت است؛ ابتدا تطبیق مالی انجام دهید.','DISCOUNT_YEAR_RECONCILIATION_REQUIRED');
        $overlap=StudentDiscountRepository::overlapping($pdo,$aid,(int)$sid,$from,$to);
        if($overlap)throw new AuthException(409,'برای این دانش‌آموز تخفیف هم‌پوشان وجود دارد.','DISCOUNT_OVERLAP');
        $rule=['effective_from'=>$from,'effective_to'=>$to,'rate_milli_percent'=>$rate,
            'apply_to_student'=>$parent,'apply_to_driver'=>$driver];
        $amounts=StudentDiscountBillingQuote::calculate($year,(string)$contract['start_date'],
            (string)$contract['end_date'],(int)$student['monthly_fee'],
            (int)$student['driver_base_monthly'],[$rule]);
        // The raw monthly tariff is NOT necessarily the amount in an issued
        // contract (manual agreements or previous class changes). Reject an
        // unreconciled contract rather than silently changing signed history.
        if((int)$contract['amount']!==$amounts['studentBase']||
            (int)$contract['invoice_amount']!==(int)$contract['amount']){
            throw new AuthException(409,'مبلغ قرارداد یا فاکتور با مبنای فعلی برابر نیست؛ ابتدا تطبیق مالی انجام دهید.','DISCOUNT_FINANCIAL_RECONCILIATION_REQUIRED');
        }
        $account=StudentServiceBilling::account($uid,$aid,(int)$sid);
        $before=$account['totals'];
        $nextNet=(int)round((float)$before['net'])-$amounts['studentDiscount'];
        $after=['balance'=>max(0,$nextNet),'credit'=>max(0,-$nextNet),
            'net'=>$nextNet,'paid'=>(int)round((float)$before['received'])];
        return [
            'studentId'=>(int)$sid,'studentName'=>trim($student['first_name'].' '.$student['last_name']),
            'schoolId'=>(int)$student['school_id'],'serviceId'=>(int)$services[0]['service_id'],
            'driverId'=>(int)$services[0]['driver_id'],
            'contractId'=>(int)$contract['id'],'contractNo'=>$contract['contract_no'],
            'contractStart'=>$contract['start_date'],'contractEnd'=>$contract['end_date'],
            'studentMonthlyBase'=>(int)$student['monthly_fee'],
            'driverMonthlyBase'=>(int)$student['driver_base_monthly'],
            'invoiceId'=>(int)$contract['invoice_id'],'academicYear'=>$year,
            'effectiveFrom'=>$from,'effectiveTo'=>$to,'rateMilliPercent'=>$rate,
            'applyToStudent'=>$parent,'applyToDriver'=>$driver,
            'amounts'=>$amounts,'accountBefore'=>$before,'accountAfter'=>$after,
            'requiresConfirmation'=>true,'readOnly'=>true
        ];
    }
    /**
     * Atomic write: issued contract and cash receipts stay immutable.
     * Two independent discounted bases are posted to a balanced journal;
     * failure to configure either accounting side aborts the entire transaction.
     */
    public static function create(int $uid,int $aid,array $input): array
    {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'finance.manage');
        Authorization::requirePermission($uid,'finance.view');
        Authorization::requirePermission($uid,'students.view');
        $sid=filter_var($input['studentId']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$sid)throw new AuthException(422,'دانش‌آموز معتبر انتخاب کنید.','INVALID_DISCOUNT_STUDENT');
        $rate=StudentDiscountInput::rate($input);
        $data=$input;
        $data['rateMilliPercent']=$rate;
        $key=$data['requestKey']??null;
        if(!is_string($key)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di',$key))
            throw new AuthException(422,'شناسه یکتای درخواست معتبر نیست.','INVALID_DISCOUNT_REQUEST_KEY');
        return OperationsService::transaction(function(PDO $pdo) use($uid,$aid,$sid,$rate,$key,$data) {
            StudentDiscountSchema::requireReady($pdo);
            // Serialize all new discount requests on this student's row. This
            // also makes the overlap check safe against concurrent submissions.
            ContractBilling::lockStudent($pdo,$aid,(int)$sid);
            $exists=$pdo->prepare('SELECT * FROM student_discounts WHERE agency_id=? AND request_key=? FOR UPDATE');
            $exists->execute([$aid,strtolower($key)]);
            $previous=$exists->fetch(PDO::FETCH_ASSOC);
            if($previous){
                $repeat=StudentDiscountRepository::insert($pdo,$aid,$uid,(int)$sid,(int)$previous['contract_id'],$data);
                $effect=$pdo->prepare('SELECT journal_document_id FROM student_discount_financial_effects WHERE agency_id=? AND discount_id=? AND event_type=?');
                $effect->execute([$aid,$repeat['id'],'create']);
                $journal=$effect->fetchColumn();
                if(!$journal)throw new AuthException(409,'ثبت مالی درخواست قبلی ناقص است؛ تطبیق لازم است.','DISCOUNT_POSTING_INCOMPLETE');
                return ['id'=>$repeat['id'],'alreadyCreated'=>true,'journalDocumentId'=>(int)$journal];
            }
            $preview=self::preview($uid,$aid,$data);
            $amounts=$preview['amounts'];
            if(($amounts['studentDiscount']??0)===0 && ($amounts['driverDiscount']??0)===0)
                throw new AuthException(422,'تخفیف انتخابی اثر مالی ندارد.','DISCOUNT_NO_FINANCIAL_EFFECT');
            $accounts=FinancialAccounts::get($uid,$aid);
            $need=[];
            if($amounts['studentDiscount']>0)$need=array_merge($need,['discount','studentReceivable']);
            if($amounts['driverDiscount']>0)$need=array_merge($need,['driverPayable','driverSalary']);
            foreach($need as $slot) {
                if(empty($accounts[$slot]))
                    throw new AuthException(409,'ابتدا حساب‌های بدهکار و بستانکار تخفیف را در تنظیمات حساب‌های پیش‌فرض تعریف کنید.','DISCOUNT_ACCOUNT_MAPPING_REQUIRED');
            }
            $result=StudentDiscountRepository::insert($pdo,$aid,$uid,(int)$sid,
                (int)$preview['contractId'],$data);
            $discountId=$result['id'];
            $lines=[];
            $tracking='SD-'.$discountId;
            if($amounts['studentDiscount']>0) {
                if($accounts['discount']===$accounts['studentReceivable'])
                    throw new AuthException(409,'حساب تخفیف و حساب مطالبات نباید یکسان باشند.','DISCOUNT_ACCOUNT_CONFLICT');
                $value=(string)$amounts['studentDiscount'];
                $lines[]=['accountId'=>$accounts['discount'],'debit'=>$value,'credit'=>'0',
                    'description'=>'تخفیف بدهکاری دانش‌آموز #'.$sid,'trackingNo'=>$tracking];
                $lines[]=['accountId'=>$accounts['studentReceivable'],'debit'=>'0','credit'=>$value,
                    'description'=>'کاهش مطالبات دانش‌آموز #'.$sid,'trackingNo'=>$tracking];
            }
            if($amounts['driverDiscount']>0) {
                if($accounts['driverPayable']===$accounts['driverSalary'])
                    throw new AuthException(409,'حساب بدهی راننده و حساب هزینه حقوق نباید یکسان باشند.','DISCOUNT_ACCOUNT_CONFLICT');
                $value=(string)$amounts['driverDiscount'];
                $lines[]=['accountId'=>$accounts['driverPayable'],'debit'=>$value,'credit'=>'0',
                    'description'=>'کاهش بدهی به راننده بابت دانش‌آموز #'.$sid,'trackingNo'=>$tracking];
                $lines[]=['accountId'=>$accounts['driverSalary'],'debit'=>'0','credit'=>$value,
                    'description'=>'تعدیل هزینه حقوق راننده بابت دانش‌آموز #'.$sid,'trackingNo'=>$tracking];
            }
            $journal=AccountingJournal::save($uid,$aid,[
                'documentNo'=>$tracking,'documentDate'=>$preview['effectiveFrom'],
                'description'=>'ثبت تخفیف اختصاصی دانش‌آموز #'.$sid.'، قرارداد #'.$preview['contractId'],
                'lines'=>$lines
            ]);
            AccountingJournal::approve($uid,$aid,(int)$journal['id']);
            $stmt=$pdo->prepare('INSERT INTO student_discount_financial_effects
                (agency_id,discount_id,student_id,driver_id,service_id,contract_id,invoice_id,academic_year,
                 effective_date,event_type,student_discount_irr,driver_discount_irr,
                 journal_document_id,request_key,calculation_snapshot)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$aid,$discountId,(int)$sid,$preview['driverId'],$preview['serviceId'],
                $preview['contractId'],$preview['invoiceId'],$preview['academicYear'],
                $preview['effectiveFrom'],'create',$amounts['studentDiscount'],
                $amounts['driverDiscount'],(int)$journal['id'],strtolower($key),
                json_encode($preview,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            if($amounts['studentDiscount']>0){
                $terms='به موجب این الحاقیه، از تاریخ مؤثر، مبلغ حق سرویس دانش‌آموز به میزان تخفیف مصوب تعدیل می‌شود. سایر مفاد قرارداد اصلی معتبر است.';
                $q=$pdo->prepare('INSERT INTO student_discount_contract_appendices
                    (agency_id,discount_id,contract_id,appendix_number,effective_from,effective_to,
                     old_amount_irr,discount_amount_irr,effective_amount_irr,terms,original_snapshot)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$aid,$discountId,$preview['contractId'],$tracking,
                    $preview['effectiveFrom'],$preview['effectiveTo'],
                    $amounts['studentBase'],$amounts['studentDiscount'],$amounts['studentNet'],$terms,
                    json_encode(['contractId'=>$preview['contractId'],'invoiceId'=>$preview['invoiceId'],
                        'contractNo'=>$preview['contractNo'],'originalAmount'=>$amounts['studentBase']],
                        JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            }
            OperationsService::audit($uid,$aid,'create_student_discount','student_discounts',$discountId);
            return ['id'=>$discountId,'alreadyCreated'=>false,
                'journalDocumentId'=>(int)$journal['id'],
                'amounts'=>$amounts,'contractId'=>$preview['contractId'],
                'invoiceId'=>$preview['invoiceId']];
        });
    }

    public static function listing(int $uid,int $aid,array $filter=[]): array
    {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'finance.view');
        Authorization::requirePermission($uid,'students.view');
        $pdo=Connection::get();
        StudentDiscountSchema::requireReady($pdo);
        $search=$filter['q']??'';
        if(!is_string($search)||mb_strlen($search)>120)
            throw new AuthException(422,'عبارت جستجوی تخفیف نامعتبر است.','INVALID_DISCOUNT_SEARCH');
        $search=trim($search);
        $where=['d.agency_id=?'];$args=[$aid];
        if($search!==''){
            $term=strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']);
            $where[]="CONCAT_WS(' ',s.first_name,s.last_name) LIKE ? ESCAPE '!'";
            $args[]='%'.$term.'%';
        }
        if(isset($filter['studentId'])&&$filter['studentId']!==''){
            $id=filter_var($filter['studentId'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$id)throw new AuthException(422,'شناسه دانش‌آموز نامعتبر است.','INVALID_DISCOUNT_STUDENT');
            $where[]='d.student_id=?';$args[]=$id;
        }
        if(isset($filter['schoolId'])&&$filter['schoolId']!==''){
            $id=filter_var($filter['schoolId'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$id)throw new AuthException(422,'شناسه مدرسه نامعتبر است.','INVALID_DISCOUNT_SCHOOL');
            $where[]='s.school_id=?';$args[]=$id;
        }
        $sql="SELECT d.id,d.student_id,d.contract_id,d.rate_milli_percent,d.reason,
             d.effective_from,d.effective_to,d.apply_to_student,d.apply_to_driver,d.created_at,
             CONCAT_WS(' ',s.first_name,s.last_name) student_name,sc.name school_name,
             e.driver_id,e.service_id,e.invoice_id,
             COALESCE(x.student_discount_irr,0) student_discount_irr,
             COALESCE(x.driver_discount_irr,0) driver_discount_irr,
             e.effective_date,e.journal_document_id
             FROM student_discounts d JOIN students s ON s.id=d.student_id AND s.agency_id=d.agency_id
             LEFT JOIN schools sc ON sc.id=s.school_id AND sc.agency_id=s.agency_id
             JOIN student_discount_financial_effects e ON e.discount_id=d.id AND e.agency_id=d.agency_id AND e.event_type='create'
             LEFT JOIN (SELECT agency_id,discount_id,
                 SUM(CASE WHEN event_type='create' THEN student_discount_irr ELSE -student_discount_irr END) student_discount_irr,
                 SUM(CASE WHEN event_type='create' THEN driver_discount_irr ELSE -driver_discount_irr END) driver_discount_irr
                 FROM student_discount_financial_effects
                 WHERE effective_date<=DATE(UTC_TIMESTAMP() + INTERVAL 210 MINUTE)
                 GROUP BY agency_id,discount_id) x ON x.agency_id=d.agency_id AND x.discount_id=d.id
             WHERE ".implode(' AND ',$where)." ORDER BY d.created_at DESC,d.id DESC LIMIT 500";
        $q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Tehran')))->format('Y-m-d');
        $summary=['count'=>count($rows),'active'=>0,'future'=>0,'ended'=>0,'cancelled'=>0,
            'studentDiscountIrr'=>0,'driverDiscountIrr'=>0,'companyShareImpactIrr'=>0];
        foreach($rows as &$row){
            $row['status']=$row['status']==='cancelled'?'cancelled':
                ($row['effective_from']>$today?'future':
                (($row['effective_to']??'9999-12-31')<=$today?'ended':'active'));
            $summary[$row['status']]++;
            $summary['studentDiscountIrr']+=(int)$row['student_discount_irr'];
            $summary['driverDiscountIrr']+=(int)$row['driver_discount_irr'];
        }
        unset($row);
        $summary['companyShareImpactIrr']=$summary['driverDiscountIrr']-$summary['studentDiscountIrr'];
        return ['items'=>$rows,'summary'=>$summary,'readOnly'=>true];
    }

    public static function details(int $uid,int $aid,int $id): array
    {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'finance.view');
        Authorization::requirePermission($uid,'students.view');
        $pdo=Connection::get();
        StudentDiscountSchema::requireReady($pdo);
        $q=$pdo->prepare("SELECT d.*,CONCAT_WS(' ',s.first_name,s.last_name) student_name
            FROM student_discounts d JOIN students s ON s.id=d.student_id AND s.agency_id=d.agency_id
            WHERE d.id=? AND d.agency_id=?");
        $q->execute([$id,$aid]);$discount=$q->fetch(PDO::FETCH_ASSOC);
        if(!$discount)throw new AuthException(404,'تخفیف یافت نشد.','DISCOUNT_NOT_FOUND');
        $q=$pdo->prepare('SELECT id,event_type,student_discount_irr,driver_discount_irr,effective_date,
            journal_document_id,calculation_snapshot,created_at FROM student_discount_financial_effects
            WHERE agency_id=? AND discount_id=? ORDER BY id');
        $q->execute([$aid,$id]);$effects=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare('SELECT id,appendix_number,contract_id,effective_from,effective_to,
            old_amount_irr,discount_amount_irr,effective_amount_irr,terms,created_at
            FROM student_discount_contract_appendices WHERE agency_id=? AND discount_id=? ORDER BY id');
        $q->execute([$aid,$id]);$appendices=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare('SELECT action,before_snapshot,after_snapshot,created_at FROM student_discount_audit_logs
            WHERE discount_id=? ORDER BY id');
        $q->execute([$id]);$history=$q->fetchAll(PDO::FETCH_ASSOC);
        return ['discount'=>$discount,'effects'=>$effects,'appendices'=>$appendices,'history'=>$history];
    }

}
