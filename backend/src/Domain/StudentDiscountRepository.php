<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;

/**
 * Internal persistence boundary. Call only inside the financial transaction,
 * after authorization. The caller MUST post all financial effects before
 * committing; this repository deliberately exposes no HTTP mutation endpoint.
 */
final class StudentDiscountRepository
{
    public static function overlapping(PDO $pdo,int $agency,int $student,string $from,string $to): array
    {
        $q=$pdo->prepare('SELECT * FROM student_discounts WHERE agency_id=? AND student_id=? AND status<>'cancelled' AND effective_from<? AND (effective_to IS NULL OR effective_to>?) ORDER BY effective_from,id');
        $q->execute([$agency,$student,$to,$from]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function insert(PDO $pdo,int $agency,int $actor,int $student,int $contract,array $input): array
    {
        if(!$pdo->inTransaction()) throw new \LogicException('Financial transaction required');
        $from=VehicleClassEffectiveDates::requireDate($input['effectiveFrom']??null,'تاریخ شروع تخفیف');
        $rate=StudentDiscountInput::rate($input);
        $parent=$input['applyToStudent']??null;
        $driver=$input['applyToDriver']??null;
        $reason=$input['reason']??null;
        $key=$input['requestKey']??null;
        if(!is_int($rate)||$rate<1||$rate>100000||!is_bool($parent)||!is_bool($driver)||(!$parent&&!$driver)||
            !is_string($reason)||trim($reason)===''||strlen($reason)>4000||
            !is_string($key)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di',$key))
            throw new AuthException(422,'اطلاعات تخفیف معتبر نیست.','INVALID_DISCOUNT');
        $reason=trim($reason);$key=strtolower($key);
        // Serialize all discount mutations for this student.
        ContractBilling::lockStudent($pdo,$agency,$student);
        $q=$pdo->prepare("SELECT id,start_date,end_date FROM contracts WHERE agency_id=? AND id=? AND party_type='student' AND party_id=? AND status='active' FOR UPDATE");
        $q->execute([$agency,$contract,$student]);$c=$q->fetch(PDO::FETCH_ASSOC);
        if(!$c||!$c['start_date']||!$c['end_date']||$from<$c['start_date']||$from>$c['end_date'])
            throw new AuthException(422,'تاریخ تخفیف باید در قرارداد فعال دانش‌آموز باشد.','DISCOUNT_CONTRACT_REQUIRED');
        $limit=(new \DateTimeImmutable($c['end_date']))->modify('+1 day')->format('Y-m-d');
        $to=isset($input['effectiveTo'])&&$input['effectiveTo']!==''
            ?VehicleClassEffectiveDates::requireDate($input['effectiveTo'],'تاریخ پایان تخفیف'):$limit;
        if($to<=$from||$to>$limit)throw new AuthException(422,'بازه تخفیف نامعتبر است.','INVALID_DISCOUNT_PERIOD');
        $q=$pdo->prepare('SELECT * FROM student_discounts WHERE agency_id=? AND request_key=? FOR UPDATE');
        $q->execute([$agency,$key]);$old=$q->fetch(PDO::FETCH_ASSOC);
        if($old){
            if((int)$old['student_id']!==$student||(int)$old['contract_id']!==$contract||
                (int)$old['rate_milli_percent']!==$rate||$old['reason']!==$reason||
                $old['effective_from']!==$from||$old['effective_to']!==$to||
                (bool)$old['apply_to_student']!==$parent||(bool)$old['apply_to_driver']!==$driver)
                throw new AuthException(409,'شناسه درخواست قبلاً برای اطلاعات دیگری استفاده شده است.','DISCOUNT_REQUEST_CONFLICT');
            return ['id'=>(int)$old['id'],'alreadyCreated'=>true];
        }
        $q=$pdo->prepare('SELECT id FROM student_discounts WHERE agency_id=? AND student_id=? AND status<>'cancelled' AND effective_from<? AND (effective_to IS NULL OR effective_to>?) LIMIT 1 FOR UPDATE');
        $q->execute([$agency,$student,$to,$from]);
        if($q->fetchColumn())throw new AuthException(409,'تخفیف هم‌پوشان وجود دارد.','DISCOUNT_OVERLAP');
        $q=$pdo->prepare('INSERT INTO student_discounts(agency_id,student_id,contract_id,rate_milli_percent,reason,effective_from,effective_to,apply_to_student,apply_to_driver,request_key,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$agency,$student,$contract,$rate,$reason,$from,$to,(int)$parent,(int)$driver,$key,$actor]);
        $id=(int)$pdo->lastInsertId();
        $snapshot=['studentId'=>$student,'contractId'=>$contract,'rateMilliPercent'=>$rate,'reason'=>$reason,'effectiveFrom'=>$from,'effectiveTo'=>$to,'applyToStudent'=>$parent,'applyToDriver'=>$driver];
        $q=$pdo->prepare("INSERT INTO student_discount_audit_logs(discount_id,actor_id,action,after_snapshot) VALUES(?,?,'create',?)");
        $q->execute([$id,$actor,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        return ['id'=>$id,'alreadyCreated'=>false];
    }
}
