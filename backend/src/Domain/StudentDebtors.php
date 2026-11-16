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
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'finance.view');
        Authorization::requirePermission($uid,'students.view');

        $year = is_string($filter['academicYear'] ?? null) ? str_replace('/','-',$filter['academicYear']) : '';
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
            if (!is_string($value)||trim($value)==='') continue;
            $column=$key==='student'?'student':'school';
            $normalize=static fn(string $s): string => str_replace(['ي','ك','‌',' '],['ی','ک','',''],trim($s));
            $where[]="REPLACE(REPLACE(REPLACE(REPLACE($column,'ي','ی'),'ك','ک'),'‌',''),' ','') LIKE :$key ESCAPE '!'";
            $params[$key]='%'.strtr($normalize($value),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        }
        $sql="WITH balances AS (
            SELECT st.id, st.school_id, COALESCE(sc.name,'مدرسه نامشخص') school,
                   CONCAT_WS(' ',st.first_name,st.last_name) student, st.mobile guardian_mobile,
                   i.amount annual_fee,
                   COALESCE((SELECT SUM(p.amount) FROM payments p
                             WHERE p.agency_id=i.agency_id AND p.invoice_id=i.id),0) paid
            FROM student_service_debt_invoices m
            JOIN invoices i ON i.id=m.invoice_id AND i.agency_id=m.agency_id
                           AND i.party_type='student' AND i.party_id=m.student_id
            JOIN students st ON st.id=m.student_id AND st.agency_id=m.agency_id
            LEFT JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
            WHERE m.agency_id=:agency AND m.academic_year=:year AND i.status IN ('open','paid','overdue')
        ), debtors AS (SELECT balances.*, annual_fee-paid debt FROM balances)
        SELECT debtors.*, SUM(debt) OVER () total_debt FROM debtors WHERE ".implode(' AND ',$where)
            .' ORDER BY school,school_id,debt DESC,student,id';
        $query=Connection::get()->prepare($sql);
        $query->execute($params);
        $rows=$query->fetchAll(\PDO::FETCH_ASSOC);
        $total=$rows[0]['total_debt']??'0.00';
        foreach ($rows as &$row) unset($row['total_debt']);
        unset($row);
        return ['items'=>$rows,'total'=>$total,'academicYear'=>$year];
    }
}
