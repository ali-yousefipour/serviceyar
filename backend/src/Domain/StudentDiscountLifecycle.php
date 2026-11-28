<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;

/**
 * Approved discount reversals. Never delete the original journal, invoice,
 * contract, cash receipts or the issued appendix.
 */
final class StudentDiscountLifecycle
{
    public static function apply(int $uid,int $aid,int $id,string $action,array $input): array
    {
        Authorization::requireAgencyAccess($uid,$aid);
        Authorization::requirePermission($uid,'finance.manage');
        Authorization::requirePermission($uid,'finance.view');
        if(!in_array($action,['cancel','terminate'],true))
            throw new AuthException(422,'نوع عملیات تخفیف نامعتبر است.','INVALID_DISCOUNT_ACTION');
        $key=$input['requestKey']??null;
        if(!is_string($key)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di',$key))
            throw new AuthException(422,'شناسه درخواست معتبر نیست.','INVALID_DISCOUNT_REQUEST_KEY');
        $key=strtolower($key);
        $reason=trim((string)($input['reason']??''));
        if($reason===''||mb_strlen($reason)>1000)
            throw new AuthException(422,'علت اصلاح یا لغو تخفیف الزامی است.','DISCOUNT_REASON_REQUIRED');
        $cutoff=$action==='terminate'
            ?VehicleClassEffectiveDates::requireDate($input['effectiveTo']??null,'تاریخ پایان تخفیف'):null;

        return OperationsService::transaction(static function(PDO $pdo) use($uid,$aid,$id,$action,$key,$reason,$cutoff): array {
            StudentDiscountSchema::requireReady($pdo);
            // Lock the student first to serialize other discount operations.
            $q=$pdo->prepare('SELECT student_id FROM student_discounts WHERE id=? AND agency_id=?');
            $q->execute([$id,$aid]);$studentId=(int)$q->fetchColumn();
            if(!$studentId)throw new AuthException(404,'تخفیف یافت نشد.','DISCOUNT_NOT_FOUND');
            ContractBilling::lockStudent($pdo,$aid,$studentId);
            $q=$pdo->prepare('SELECT * FROM student_discounts WHERE id=? AND agency_id=? FOR UPDATE');
            $q->execute([$id,$aid]);$record=$q->fetch(PDO::FETCH_ASSOC);
            if(!$record)throw new AuthException(404,'تخفیف یافت نشد.','DISCOUNT_NOT_FOUND');
            $q=$pdo->prepare('SELECT discount_id,event_type,journal_document_id FROM student_discount_financial_effects WHERE agency_id=? AND request_key=? FOR UPDATE');
            $q->execute([$aid,$key]);$existing=$q->fetch(PDO::FETCH_ASSOC);
            if($existing){
                if((int)$existing['discount_id']!==$id||$existing['event_type']!==$action)
                    throw new AuthException(409,'شناسه درخواست برای عملیات دیگری استفاده شده است.','DISCOUNT_REQUEST_CONFLICT');
                return ['discountId'=>$id,'alreadyApplied'=>true,'journalDocumentId'=>(int)$existing['journal_document_id']];
            }
            if($record['status']==='cancelled')
                throw new AuthException(409,'این تخفیف قبلاً لغو شده است.','DISCOUNT_ALREADY_CANCELLED');
            if($action==='terminate'&&($cutoff<=$record['effective_from']||$cutoff>=$record['effective_to']))
                throw new AuthException(422,'تاریخ پایان باید بعد از شروع و قبل از پایان فعلی باشد.','INVALID_DISCOUNT_PERIOD');

            $q=$pdo->prepare("SELECT * FROM student_discount_financial_effects
                WHERE agency_id=? AND discount_id=? ORDER BY id FOR UPDATE");
            $q->execute([$aid,$id]);$events=$q->fetchAll(PDO::FETCH_ASSOC);
            $original=null;$currentStudent=0;$currentDriver=0;
            foreach($events as $event){
                $sign=$event['event_type']==='create'?1:-1;
                if($event['event_type']==='create'){
                    if($original!==null)throw new AuthException(409,'چند سند مبنای تخفیف یافت شد.','DISCOUNT_FINANCIAL_RECONCILIATION_REQUIRED');
                    $original=$event;
                }
                $currentStudent+=$sign*(int)$event['student_discount_irr'];
                $currentDriver+=$sign*(int)$event['driver_discount_irr'];
            }
            if($original===null||$currentStudent<0||$currentDriver<0)
                throw new AuthException(409,'سابقه مالی تخفیف معتبر نیست.','DISCOUNT_FINANCIAL_RECONCILIATION_REQUIRED');
            $snapshot=json_decode((string)$original['calculation_snapshot'],true,512,JSON_THROW_ON_ERROR);
            if(!is_array($snapshot))throw new AuthException(409,'اطلاعات نرخ تاریخی تخفیف ناقص است.','DISCOUNT_FINANCIAL_RECONCILIATION_REQUIRED');
            $targetStudent=0;$targetDriver=0;
            if($action==='terminate'){
                foreach(['contractStart','contractEnd','studentMonthlyBase','driverMonthlyBase'] as $field)
                    if(!array_key_exists($field,$snapshot))
                        throw new AuthException(409,'مبنای نرخ قدیمی برای پایان تخفیف ثبت نشده است.','DISCOUNT_FINANCIAL_RECONCILIATION_REQUIRED');
                $newRule=['effective_from'=>$record['effective_from'],
                    'effective_to'=>$cutoff,'rate_milli_percent'=>(int)$record['rate_milli_percent'],
                    'apply_to_student'=>(bool)$record['apply_to_student'],
                    'apply_to_driver'=>(bool)$record['apply_to_driver']];
                $revised=StudentDiscountBillingQuote::calculate(
                    (string)$original['academic_year'],(string)$snapshot['contractStart'],
                    (string)$snapshot['contractEnd'],(int)$snapshot['studentMonthlyBase'],
                    (int)$snapshot['driverMonthlyBase'],[$newRule]);
                $targetStudent=(int)$revised['studentDiscount'];
                $targetDriver=(int)$revised['driverDiscount'];
            }
            $reverseStudent=$currentStudent-$targetStudent;
            $reverseDriver=$currentDriver-$targetDriver;
            if($reverseStudent<0||$reverseDriver<0||($reverseStudent===0&&$reverseDriver===0))
                throw new AuthException(409,'مبلغ تعدیل تخفیف نامعتبر است یا تغییر مالی ندارد.','DISCOUNT_FINANCIAL_RECONCILIATION_REQUIRED');

            // Reverse on/after the original effective date. Future cancellations
            // are exactly offset on their activation date, not charged early.
            $today=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Tehran')))->format('Y-m-d');
            $effective=$action==='terminate'?$cutoff:max($today,(string)$record['effective_from']);
            $originalJournal=(int)$original['journal_document_id'];
            $q=$pdo->prepare('SELECT status FROM accounting_documents WHERE agency_id=? AND id=? FOR UPDATE');
            $q->execute([$aid,$originalJournal]);
            if($q->fetchColumn()!=='approved')
                throw new AuthException(409,'سند اصلی حسابداری تأییدشده یافت نشد.','DISCOUNT_JOURNAL_RECONCILIATION_REQUIRED');
            $q=$pdo->prepare('SELECT account_id,debit,credit FROM accounting_document_lines WHERE document_id=? ORDER BY id');
            $q->execute([$originalJournal]);$lines=$q->fetchAll(PDO::FETCH_ASSOC);
            $index=0;$reversal=[];$tracking='SD-'.$id.'-'.strtoupper($action).'-'.substr($key,0,8);
            foreach([
                ['amount'=>$reverseStudent,'original'=>(int)$original['student_discount_irr'],
                    'title'=>'برگشت تخفیف دانش‌آموز'],
                ['amount'=>$reverseDriver,'original'=>(int)$original['driver_discount_irr'],
                    'title'=>'برگشت کسورات راننده']
            ] as $part){
                if($part['original']===0)continue;
                if(!isset($lines[$index],$lines[$index+1])||
                    (int)$lines[$index]['debit']!==$part['original']||
                    (int)$lines[$index]['credit']!==0||
                    (int)$lines[$index+1]['credit']!==$part['original']||
                    (int)$lines[$index+1]['debit']!==0)
                    throw new AuthException(409,'ردیف‌های سند مالی اولیه معتبر نیستند.','DISCOUNT_JOURNAL_RECONCILIATION_REQUIRED');
                if($part['amount']>0){
                    $reversal[]=['accountId'=>(int)$lines[$index]['account_id'],
                        'debit'=>'0','credit'=>(string)$part['amount'],
                        'description'=>$part['title'].' #'.$id,'trackingNo'=>$tracking];
                    $reversal[]=['accountId'=>(int)$lines[$index+1]['account_id'],
                        'debit'=>(string)$part['amount'],'credit'=>'0',
                        'description'=>$part['title'].' #'.$id,'trackingNo'=>$tracking];
                }
                $index+=2;
            }
            if($index!==count($lines)||count($reversal)<2)
                throw new AuthException(409,'ساختار سند اولیه با تعدیل مطابقت ندارد.','DISCOUNT_JOURNAL_RECONCILIATION_REQUIRED');
            $journal=AccountingJournal::save($uid,$aid,[
                'documentNo'=>$tracking,'documentDate'=>$effective,
                'description'=>$action==='cancel'?'ابطال مالی تخفیف #'.$id:'تعدیل پایان تخفیف #'.$id,
                'lines'=>$reversal]);
            AccountingJournal::approve($uid,$aid,(int)$journal['id']);
            $q=$pdo->prepare('INSERT INTO student_discount_financial_effects
                (agency_id,discount_id,student_id,driver_id,service_id,contract_id,invoice_id,
                 academic_year,effective_date,event_type,student_discount_irr,driver_discount_irr,
                 journal_document_id,request_key,calculation_snapshot)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$aid,$id,$studentId,$original['driver_id'],$original['service_id'],
                $original['contract_id'],$original['invoice_id'],$original['academic_year'],
                $effective,$action,$reverseStudent,$reverseDriver,
                (int)$journal['id'],$key,json_encode(['original'=>$original['id'],'reason'=>$reason,
                    'effective'=>$effective,'newTargetStudent'=>$targetStudent,
                    'newTargetDriver'=>$targetDriver],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $newStatus=$action==='cancel'?'cancelled':'terminated';
            $newTo=$action==='terminate'?$cutoff:$record['effective_to'];
            $q=$pdo->prepare('UPDATE student_discounts SET status=?,ended_at=?,effective_to=?,updated_at=NOW()
                WHERE id=? AND agency_id=?');
            $q->execute([$newStatus,$effective,$newTo,$id,$aid]);
            $before=['status'=>$record['status'],'effectiveTo'=>$record['effective_to'],
                'studentDiscount'=>$currentStudent,'driverDiscount'=>$currentDriver];
            $after=['status'=>$newStatus,'effectiveTo'=>$newTo,'reason'=>$reason,
                'studentDiscount'=>$targetStudent,'driverDiscount'=>$targetDriver,
                'journalDocumentId'=>(int)$journal['id']];
            $q=$pdo->prepare('INSERT INTO student_discount_audit_logs
                (discount_id,actor_id,action,before_snapshot,after_snapshot)
                VALUES(?,?,?,?,?)');
            $q->execute([$id,$uid,$action,json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            OperationsService::audit($uid,$aid,'student_discount_'.$action,'student_discounts',$id);
            return ['discountId'=>$id,'status'=>$newStatus,'effectiveDate'=>$effective,
                'journalDocumentId'=>(int)$journal['id'],
                'reversedStudentIrr'=>$reverseStudent,'reversedDriverIrr'=>$reverseDriver,
                'alreadyApplied'=>false];
        });
    }
}
