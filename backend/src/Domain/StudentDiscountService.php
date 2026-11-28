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
        $q=$pdo->prepare("SELECT c.id,c.contract_no,c.start_date,c.end_date,c.amount,m.invoice_id,i.amount invoice_amount,i.status invoice_status
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
        return [
            'studentId'=>(int)$sid,'studentName'=>trim($student['first_name'].' '.$student['last_name']),
            'schoolId'=>(int)$student['school_id'],'serviceId'=>(int)$services[0]['service_id'],
            'driverId'=>(int)$services[0]['driver_id'],
            'contractId'=>(int)$contract['id'],'contractNo'=>$contract['contract_no'],
            'invoiceId'=>(int)$contract['invoice_id'],'academicYear'=>$year,
            'effectiveFrom'=>$from,'effectiveTo'=>$to,'rateMilliPercent'=>$rate,
            'applyToStudent'=>$parent,'applyToDriver'=>$driver,
            'amounts'=>$amounts,'requiresConfirmation'=>true,'readOnly'=>true
        ];
    }
}
