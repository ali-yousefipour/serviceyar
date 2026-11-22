<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class StudentDebtors
{
    public static function report(int $uid, int $aid, array $filter): array
    {
        return self::build($uid,$aid,$filter,false);
    }

    /** The representative endpoint validates the driver membership and school scope first. */
    public static function reportForRepresentative(int $uid,int $aid,array $filter): array
    {
        if(!filter_var($filter['schoolId']??null,FILTER_VALIDATE_INT))
            throw new AuthException(422,'مدرسه موردنظر را انتخاب کنید.','SCHOOL_REQUIRED');
        return self::build($uid,$aid,$filter,true);
    }

    private static function build(int $uid,int $aid,array $filter,bool $representative): array
    {
        if(!$representative){
            Authorization::requireAgencyAccess($uid,$aid);
            Authorization::requirePermission($uid,'finance.view');
            Authorization::requirePermission($uid,'students.view');
        }

        $year = ContractBilling::year(is_string($filter['academicYear'] ?? null)?$filter['academicYear']:'');
        if (!preg_match('/\A(1[34]\d{2})-(1[34]\d{2})\z/', $year, $parts) || (int)$parts[1]+1 !== (int)$parts[2]) {
            throw new AuthException(422,'سال تحصیلی معتبر وارد کنید.','VALIDATION_ERROR');
        }
        $where=['debt>0'];
        $params=['agency'=>$aid,'year'=>$year];
        foreach (['minDebt'=>'>=','maxDebt'=>'<='] as $key=>$operator) {
            $value=$filter[$key]??'';
            if ($value==='') continue;
            if (!is_scalar($value)||!preg_match('/\A\d{1,16}(?:\.\d{1,2})?\z/',(string)$value)) {
                throw new AuthException(422,'مبلغ بدهی باید عدد نامنفی به ریال باشد.','VALIDATION_ERROR');
            }
            $where[]="debt $operator :$key";
            $params[$key]=$value;
        }
        if (isset($params['minDebt'],$params['maxDebt'])&&(float)$params['minDebt']>(float)$params['maxDebt']) {
            throw new AuthException(422,'حداقل بدهی از حداکثر بیشتر است.','VALIDATION_ERROR');
        }
        $school=$filter['schoolId']??'';
        if ($school!=='') {
            if (!is_scalar($school)||!ctype_digit((string)$school)||(int)$school<1) {
                throw new AuthException(422,'مدرسه معتبر انتخاب کنید.','VALIDATION_ERROR');
            }
            $where[]='school_id=:school';
            $params['school']=$school;
        }
        foreach (['student','schoolName'] as $key) {
            $value=$filter[$key]??'';
            if($value===null||$value==='')continue;
            if(!is_string($value)||strlen($value)>160)
                throw new AuthException(422,'عبارت جستجوی گزارش بدهکاران معتبر یا در محدوده مجاز نیست.','VALIDATION_ERROR');
            $value=trim($value);
            if($value==='')continue;
            $column=$key==='student'?'student':'school';
            $normalize=static fn(string $s): string => str_replace(['ي','ك','‌',' '],['ی','ک','',''],trim($s));
            $where[]="REPLACE(REPLACE(REPLACE(REPLACE($column,'ي','ی'),'ك','ک'),'‌',''),' ','') LIKE :$key ESCAPE '!'";
            $params[$key]='%'.strtr($normalize($value),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        }
        $extra=$representative?'':"students.national_code, students.father_name, sc.education_levels, students.grade, sc.region, students.address,
                   CONCAT_WS(' ',students.guardian_first_name,students.guardian_last_name) guardian_name, students.guardian_national_code,
                   students.guardian_relation, students.emergency_phone, students.return_address, students.gender, students.nationality, students.shift, students.academic_year, students.service_status, students.tariff_vehicle_class,
                   (SELECT GROUP_CONCAT(DISTINCT v.plate ORDER BY v.plate SEPARATOR '، ') FROM service_students ss JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=students.agency_id JOIN drivers dr ON dr.id=sv.driver_id AND dr.agency_id=sv.agency_id JOIN vehicles v ON v.id=dr.vehicle_id AND v.agency_id=sv.agency_id WHERE ss.student_id=students.id AND sv.is_active=1 AND sv.status<>'cancelled') plate,
                   (SELECT c.start_date FROM contracts c WHERE c.agency_id=students.agency_id AND c.party_type='student' AND c.party_id=students.id ORDER BY c.id DESC LIMIT 1) contract_start,
                   (SELECT c.end_date FROM contracts c WHERE c.agency_id=students.agency_id AND c.party_type='student' AND c.party_id=students.id ORDER BY c.id DESC LIMIT 1) contract_end,
                   (SELECT c.contract_no FROM contracts c WHERE c.agency_id=students.agency_id AND c.party_type='student' AND c.party_id=students.id ORDER BY c.id DESC LIMIT 1) contract_no,";
        $hasVehicleChange=VehicleClassSchema::exists();
        $adjustmentCondition=$hasVehicleChange
            ? "EXISTS(SELECT 1 FROM vehicle_class_debt_adjustments va WHERE va.agency_id=:adjustmentAgency AND va.student_id=basis.id AND va.academic_year=:adjustmentYear AND va.effective_date<=CURRENT_DATE())"
            : "FALSE";
        if($hasVehicleChange){$params['adjustmentAgency']=$aid;$params['adjustmentYear']=$year;}
        $sql="WITH balances AS (
            SELECT students.id, students.school_id, COALESCE(sc.name,'مدرسه نامشخص') school,
                   CONCAT_WS(' ',students.first_name,students.last_name) student, students.mobile guardian_mobile,
                   $extra students.tariff_distance_km,
                   COALESCE(students.monthly_fee,0) monthly_fee,
                   (SELECT COUNT(*) FROM invoices i WHERE i.agency_id=students.agency_id AND i.party_type='student' AND i.party_id=students.id AND i.status<>'cancelled') invoice_count,
                   (SELECT COUNT(*) FROM service_students ss JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=students.agency_id WHERE ss.student_id=students.id AND sv.is_active=1 AND sv.status='active') active_service_count,
                   (SELECT GROUP_CONCAT(DISTINCT sv.title ORDER BY sv.title SEPARATOR '، ') FROM service_students ss JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=students.agency_id WHERE ss.student_id=students.id AND sv.is_active=1 AND sv.status<>'cancelled') service_names,
                   (SELECT GROUP_CONCAT(DISTINCT CONCAT_WS(' ',dr.first_name,dr.last_name) ORDER BY dr.last_name SEPARATOR '، ') FROM service_students ss JOIN services sv ON sv.id=ss.service_id AND sv.agency_id=students.agency_id JOIN drivers dr ON dr.id=sv.driver_id AND dr.agency_id=sv.agency_id WHERE ss.student_id=students.id AND sv.is_active=1 AND sv.status<>'cancelled') driver_names,
                   ".DomainService::studentAnnualFeeSql()." annual_fee,
                   ".DomainService::studentAnnualMonthsSql()." annual_months,
                   ".DomainService::studentContractAmountSql()." contract_amount,
                   ".DomainService::studentInvoiceTotalSql()." invoice_total,
                   ".DomainService::studentPaidTotalSql()." paid
            FROM students
            LEFT JOIN schools sc ON sc.id=students.school_id AND sc.agency_id=students.agency_id
            WHERE students.agency_id=:agency AND students.is_active=1
              AND ".StudentBalance::yearSql('students.academic_year')."=CONVERT(:year USING utf8mb4) COLLATE utf8mb4_unicode_ci
        ), basis AS (SELECT balances.*,
              invoice_total debt_basis,
              CASE WHEN invoice_count>0 THEN 'invoiced' ELSE 'refund_balance' END balance_source
              FROM balances),
              debtors AS (SELECT basis.*, GREATEST(0,ROUND(debt_basis-paid,2)) debt, GREATEST(0,ROUND(paid-debt_basis,2)) credit, CASE WHEN annual_months IS NULL THEN 'مقطع نامشخص' WHEN contract_amount IS NULL THEN 'بدون قرارداد مرتبط' WHEN $adjustmentCondition THEN 'دارای تعدیل تاریخ‌دار کلاس خودرو' WHEN contract_amount<>invoice_total THEN 'اختلاف قرارداد و فاکتور' WHEN annual_fee<>contract_amount THEN 'اختلاف تعرفه و قرارداد؛ نیازمند بررسی' ELSE 'منطبق' END reconciliation_status FROM basis)
        SELECT debtors.*, SUM(debt) OVER () total_debt FROM debtors WHERE ".implode(' AND ',$where)
            .' ORDER BY school,school_id,debt DESC,student,id';
        $query=Connection::get()->prepare($sql);
        $query->execute($params);
        $rows=$query->fetchAll(\PDO::FETCH_ASSOC);
        $total=$rows[0]['total_debt']??'0.00';
        foreach ($rows as &$row) unset($row['total_debt']);
        unset($row);
        $agency=Connection::get()->prepare('SELECT name FROM agencies WHERE id=?');
        $agency->execute([$aid]);
        return ['items'=>$rows,'total'=>$total,'unbilledCount'=>count(array_filter($rows,static fn($r)=>$r['balance_source']==='unbilled_service')),'unbilledTotal'=>array_sum(array_map(static fn($r)=>$r['balance_source']==='unbilled_service'?(float)$r['debt']:0,$rows)),'academicYear'=>$year,'agencyName'=>$agency->fetchColumn()?:'شرکت'];
    }
}
