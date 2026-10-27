<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class AccountingJournal
{
    public static function get(int $uid,int $aid,int $id): array
    {
        $doc=DomainService::get($uid,$aid,'accounting-documents',$id);
        $q=Connection::get()->prepare('SELECT l.*,a.code,a.title FROM accounting_document_lines l JOIN accounting_accounts a ON a.id=l.account_id AND a.agency_id=:a WHERE l.document_id=:id ORDER BY l.id');
        $q->execute(['a'=>$aid,'id'=>$id]);$doc['lines']=$q->fetchAll();return $doc;
    }

    // Store money as integer hundredths during validation; never compare floating totals.
    private static function cents(mixed $value): int
    {
        if(!is_scalar($value)||!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D',(string)$value))throw new AuthException(422,'مبلغ باید عدد مثبت با حداکثر دو رقم اعشار باشد.','VALIDATION_ERROR');
        $parts=explode('.',(string)$value);return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
    }

    private static function decimal(int $value): string
    {
        return intdiv($value,100).'.'.str_pad((string)($value%100),2,'0',STR_PAD_LEFT);
    }

    public static function save(int $uid,int $aid,array $body,?int $id=null): array
    {
        Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$body,$id){
            // Serialize account validation with account edits/deactivation and document numbering.
            $q=$p->prepare('SELECT id FROM agencies WHERE id=? FOR UPDATE');$q->execute([$aid]);
            if($id){$q=$p->prepare('SELECT status FROM accounting_documents WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$id,$aid]);$status=$q->fetchColumn();if($status===false)throw new AuthException(404,'سند پیدا نشد.','NOT_FOUND');if($status!=='draft')throw new AuthException(409,'سند تأییدشده قابل تغییر نیست.','JOURNAL_LOCKED');}
            $no=$body['documentNo']??'';$date=$body['documentDate']??'';$description=$body['description']??'';$lines=$body['lines']??null;
            if(!is_string($no)||trim($no)===''||strlen($no)>100||!is_string($date)||!OperationsService::validDate($date)||!is_string($description)||strlen($description)>10000||!is_array($lines)||count($lines)<2||count($lines)>200)throw new AuthException(422,'شماره، تاریخ و بین ۲ تا ۲۰۰ ردیف سند لازم است.','VALIDATION_ERROR');
            $q=$p->prepare('SELECT id FROM accounting_documents WHERE agency_id=? AND document_no=? AND id<>?');$q->execute([$aid,trim($no),$id??0]);if($q->fetchColumn())throw new AuthException(409,'شماره سند تکراری است.','DOCUMENT_EXISTS');
            $debit=0;$credit=0;$rows=[];
            foreach($lines as $line){
                if(!is_array($line))throw new AuthException(422,'ردیف سند نامعتبر است.','VALIDATION_ERROR');
                $account=$line['accountId']??null;if(!is_scalar($account)||!ctype_digit((string)$account))throw new AuthException(422,'حساب نامعتبر است.','CROSS_SCOPE_REFERENCE');
                $q=$p->prepare('SELECT id FROM accounting_accounts WHERE id=? AND agency_id=? AND is_active=1 FOR UPDATE');$q->execute([(int)$account,$aid]);if(!$q->fetchColumn())throw new AuthException(422,'حساب فعال متعلق به همین شرکت انتخاب کنید.','CROSS_SCOPE_REFERENCE');
                $d=self::cents($line['debit']??0);$c=self::cents($line['credit']??0);
                if(($d===0)===( $c===0))throw new AuthException(422,'هر ردیف باید فقط بدهکار یا بستانکار باشد.','VALIDATION_ERROR');
                $text=$line['description']??'';$tracking=$line['trackingNo']??'';
                if(!is_string($text)||strlen($text)>1000||!is_string($tracking)||strlen($tracking)>100)throw new AuthException(422,'شرح یا شماره پیگیری نامعتبر است.','VALIDATION_ERROR');
                $debit+=$d;$credit+=$c;$rows[]=[(int)$account,$text,$tracking,self::decimal($d),self::decimal($c)];
            }
            if($debit!==$credit)throw new AuthException(422,'جمع بدهکار و بستانکار برابر نیست.','UNBALANCED_JOURNAL');
            if($id){$p->prepare('UPDATE accounting_documents SET document_no=?,document_date=?,description=?,total_amount=? WHERE id=? AND agency_id=?')->execute([trim($no),$date,$description,self::decimal($debit),$id,$aid]);$p->prepare('DELETE FROM accounting_document_lines WHERE document_id=?')->execute([$id]);}
            else{$p->prepare("INSERT INTO accounting_documents(agency_id,document_no,document_date,description,total_amount,status) VALUES(?,?,?,?,?,'draft')")->execute([$aid,trim($no),$date,$description,self::decimal($debit)]);$id=(int)$p->lastInsertId();}
            $q=$p->prepare('INSERT INTO accounting_document_lines(document_id,account_id,description,tracking_no,debit,credit) VALUES(?,?,?,?,?,?)');foreach($rows as $row)$q->execute(array_merge([$id],$row));
            OperationsService::audit($uid,$aid,'save_journal','accounting_documents',$id);return self::get($uid,$aid,$id);
        });
    }

    public static function approve(int $uid,int $aid,int $id): array
    {
        Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$id){
            $q=$p->prepare('SELECT status FROM accounting_documents WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$id,$aid]);$status=$q->fetchColumn();if($status===false)throw new AuthException(404,'سند پیدا نشد.','NOT_FOUND');if($status!=='draft')throw new AuthException(409,'سند قبلاً تأیید شده است.','JOURNAL_LOCKED');
            $q=$p->prepare('SELECT COUNT(*) n,COALESCE(SUM(debit),0) d,COALESCE(SUM(credit),0) c FROM accounting_document_lines WHERE document_id=?');$q->execute([$id]);$s=$q->fetch();if((int)$s['n']<2||$s['d']!==$s['c']||(float)$s['d']<=0)throw new AuthException(422,'سند فاقد ردیف تراز است.','UNBALANCED_JOURNAL');
            $p->prepare("UPDATE accounting_documents SET status='approved' WHERE id=? AND agency_id=?")->execute([$id,$aid]);OperationsService::audit($uid,$aid,'approve_journal','accounting_documents',$id);return self::get($uid,$aid,$id);
        });
    }
}
