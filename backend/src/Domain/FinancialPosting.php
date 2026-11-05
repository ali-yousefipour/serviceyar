<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
final class FinancialPosting {
 private const TABLES=['ledger'=>'ledger_entries','payments'=>'payments','driver-payments'=>'driver_payments'];
 public static function assertEditable(int $aid,string $resource,int $id): void {
  if(!isset(self::TABLES[$resource]))return;
  $q=Connection::get()->prepare('SELECT id FROM '.self::TABLES[$resource].' WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$id,$aid]);
  $q=Connection::get()->prepare('SELECT document_id FROM financial_postings WHERE agency_id=? AND resource=? AND source_id=? FOR UPDATE');$q->execute([$aid,$resource,$id]);if($q->fetchColumn())throw new AuthException(409,'این سابقه سند حسابداری دارد و قابل تغییر نیست.','FINANCIAL_SOURCE_LOCKED');
 }
 public static function post(int $uid,int $aid,string $resource,int $id,array $body): array {
  Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);
  if(!isset(self::TABLES[$resource]))throw new AuthException(422,'نوع سابقه مالی نامعتبر است.','VALIDATION_ERROR');
  return OperationsService::transaction(function($p)use($uid,$aid,$resource,$id,$body){
   $q=$p->prepare('SELECT * FROM '.self::TABLES[$resource].' WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$id,$aid]);$row=$q->fetch();if(!$row)throw new AuthException(404,'سابقه مالی پیدا نشد.','NOT_FOUND');
   $q=$p->prepare('SELECT document_id FROM financial_postings WHERE agency_id=? AND resource=? AND source_id=? FOR UPDATE');$q->execute([$aid,$resource,$id]);$existing=$q->fetchColumn();if($existing)return ['documentId'=>(int)$existing,'alreadyPosted'=>true];
   if(isset($row['is_active'])&&!(int)$row['is_active'])throw new AuthException(422,'سابقه غیرفعال قابل ثبت سند نیست.','VALIDATION_ERROR');
   $debit=$body['debitAccountId']??null;$credit=$body['creditAccountId']??null;
   if(!is_scalar($debit)||!ctype_digit((string)$debit)||!is_scalar($credit)||!ctype_digit((string)$credit)||(int)$debit===(int)$credit)throw new AuthException(422,'دو حساب متفاوت انتخاب کنید.','VALIDATION_ERROR');
   $date=$row['entry_date']??$row['payment_date'];$tracking=(string)($row['reference_no']??'');
   $doc=AccountingJournal::save($uid,$aid,['documentNo'=>'FIN-'.$resource.'-'.$id,'documentDate'=>$date,'description'=>'ثبت مالی '.$resource.' #'.$id,'lines'=>[
    ['accountId'=>$debit,'debit'=>$row['amount'],'credit'=>'0','trackingNo'=>$tracking],
    ['accountId'=>$credit,'credit'=>$row['amount'],'debit'=>'0','trackingNo'=>$tracking]
   ]]);AccountingJournal::approve($uid,$aid,(int)$doc['id']);
   $p->prepare('INSERT INTO financial_postings(agency_id,resource,source_id,document_id) VALUES(?,?,?,?)')->execute([$aid,$resource,$id,$doc['id']]);
   OperationsService::audit($uid,$aid,'post_financial_source',$resource,$id);return ['documentId'=>(int)$doc['id'],'alreadyPosted'=>false];
  });
 }
}
