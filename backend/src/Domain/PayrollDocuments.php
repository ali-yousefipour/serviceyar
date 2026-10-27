<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class PayrollDocuments
{
    public static function list(int $uid,int $aid,array $filter): array
    {
        Authorization::requirePermission($uid,'finance.view');Authorization::requireAgencyAccess($uid,$aid);[$from,$to]=OperationsService::range($filter);
        $q=Connection::get()->prepare('SELECT id,document_no,status,total_amount,created_at FROM payroll_documents WHERE agency_id=:a AND date_from=:f AND date_to=:t ORDER BY id DESC');$q->execute(['a'=>$aid,'f'=>$from,'t'=>$to]);return $q->fetchAll();
    }

    public static function get(int $uid,int $aid,int $id): array
    {
        Authorization::requirePermission($uid,'finance.view');Authorization::requireAgencyAccess($uid,$aid);$p=Connection::get();
        $q=$p->prepare('SELECT * FROM payroll_documents WHERE id=:id AND agency_id=:a');$q->execute(['id'=>$id,'a'=>$aid]);$doc=$q->fetch();if(!$doc)throw new AuthException(404,'سند حقوق پیدا نشد.','NOT_FOUND');
        $q=$p->prepare('SELECT l.driver_id,l.earned,l.agreed,l.paid,l.salary,d.first_name,d.last_name,d.iban,d.bank_account,d.bank_name FROM payroll_document_lines l JOIN drivers d ON d.id=l.driver_id AND d.agency_id=:a WHERE l.document_id=:id ORDER BY d.last_name,d.first_name');$q->execute(['a'=>$aid,'id'=>$id]);$doc['lines']=$q->fetchAll();return $doc;
    }

    public static function create(int $uid,int $aid,array $body): array
    {
        Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);[$from,$to]=OperationsService::range($body);$lines=$body['lines']??null;
        if(!is_array($lines)||!count($lines)||count($lines)>200)throw new AuthException(422,'بین ۱ تا ۲۰۰ راننده انتخاب کنید.','VALIDATION_ERROR');
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$from,$to,$lines){
            $q=$p->prepare('SELECT id FROM agencies WHERE id=:a FOR UPDATE');$q->execute(['a'=>$aid]);
            $q=$p->prepare('SELECT id FROM payroll_documents WHERE agency_id=:a AND date_from=:f AND date_to=:t');$q->execute(['a'=>$aid,'f'=>$from,'t'=>$to]);if($q->fetchColumn())throw new AuthException(409,'برای این بازه سند حقوق ثبت شده است.','PAYROLL_EXISTS');
            $payroll=OperationsService::payroll($uid,$aid,['from'=>$from,'to'=>$to]);$actual=[];foreach($payroll['items'] as $row)$actual[(int)$row['id']]=$row;
            $chosen=[];$total=0;
            foreach($lines as $line){if(!is_array($line))throw new AuthException(422,'ردیف حقوق نامعتبر است.','VALIDATION_ERROR');$id=(int)($line['driverId']??0);$amount=$line['amount']??null;if(!isset($actual[$id])||isset($chosen[$id]))throw new AuthException(422,'راننده تکراری یا خارج از شرکت است.','CROSS_SCOPE_REFERENCE');if(!is_numeric($amount)||!is_finite((float)$amount)||(float)$amount<0||(float)$amount>1000000000000)throw new AuthException(422,'حقوق نامعتبر است.','VALIDATION_ERROR');$chosen[$id]=['row'=>$actual[$id],'salary'=>round((float)$amount,2)];$total+=round((float)$amount,2);}
            $no='PAY-'.str_replace('-','',$from).'-'.bin2hex(random_bytes(3));$p->prepare('INSERT INTO payroll_documents(agency_id,document_no,date_from,date_to,total_amount,created_by) VALUES(:a,:n,:f,:t,:v,:u)')->execute(['a'=>$aid,'n'=>$no,'f'=>$from,'t'=>$to,'v'=>$total,'u'=>$uid]);$doc=(int)$p->lastInsertId();
            $q=$p->prepare('INSERT INTO payroll_document_lines(document_id,driver_id,earned,agreed,paid,salary) VALUES(:doc,:driver,:earned,:agreed,:paid,:salary)');foreach($chosen as $driver=>$item){$r=$item['row'];$q->execute(['doc'=>$doc,'driver'=>$driver,'earned'=>$r['earned'],'agreed'=>$r['agreed'],'paid'=>$r['paid'],'salary'=>$item['salary']]);}
            OperationsService::audit($uid,$aid,'create_payroll','payroll_documents',$doc);return ['id'=>$doc,'documentNo'=>$no,'total'=>$total,'status'=>'draft'];
        });
    }

    public static function approve(int $uid,int $aid,int $id): array
    {
        Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$id){$q=$p->prepare('SELECT status FROM payroll_documents WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$id,'a'=>$aid]);$status=$q->fetchColumn();if($status===false)throw new AuthException(404,'سند حقوق پیدا نشد.','NOT_FOUND');if($status!=='draft')throw new AuthException(409,'سند قبلاً تأیید شده است.','ALREADY_REVIEWED');$p->prepare("UPDATE payroll_documents SET status='approved' WHERE id=:id AND agency_id=:a")->execute(['id'=>$id,'a'=>$aid]);OperationsService::audit($uid,$aid,'approve_payroll','payroll_documents',$id);return ['status'=>'approved'];});
    }

    public static function update(int $uid,int $aid,int $id,array $body): array
    {
        Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$id,$body){
            $q=$p->prepare('SELECT status FROM payroll_documents WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$id,'a'=>$aid]);$status=$q->fetchColumn();if($status===false)throw new AuthException(404,'سند پیدا نشد.','NOT_FOUND');if($status!=='draft')throw new AuthException(409,'سند تأییدشده قابل تغییر نیست.','PAYROLL_LOCKED');
            $q=$p->prepare('SELECT driver_id FROM payroll_document_lines WHERE document_id=?');$q->execute([$id]);$drivers=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$lines=$body['lines']??null;if(!is_array($lines)||count($lines)!==count($drivers))throw new AuthException(422,'همه ردیف‌های سند را ارسال کنید.','VALIDATION_ERROR');
            $seen=[];$total=0;$q=$p->prepare('UPDATE payroll_document_lines SET salary=:v WHERE document_id=:doc AND driver_id=:driver');
            foreach($lines as $line){if(!is_array($line))throw new AuthException(422,'ردیف حقوق نامعتبر است.','VALIDATION_ERROR');$driver=(int)($line['driverId']??0);$value=$line['amount']??null;if(!in_array($driver,$drivers,true)||isset($seen[$driver])||!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>1000000000000)throw new AuthException(422,'ردیف حقوق نامعتبر است.','VALIDATION_ERROR');$seen[$driver]=true;$value=round((float)$value,2);$total+=$value;$q->execute(['v'=>$value,'doc'=>$id,'driver'=>$driver]);}
            $p->prepare('UPDATE payroll_documents SET total_amount=:v WHERE id=:id AND agency_id=:a')->execute(['v'=>$total,'id'=>$id,'a'=>$aid]);OperationsService::audit($uid,$aid,'update_payroll','payroll_documents',$id);return ['total'=>$total];
        });
    }

    public static function validIban(string $iban): bool
    {
        $iban=strtoupper(str_replace(' ','',$iban));if(!preg_match('/^IR\d{24}$/',$iban))return false;$digits=substr($iban,4).'1827'.substr($iban,2,2);$mod=0;foreach(str_split($digits) as $digit)$mod=($mod*10+(int)$digit)%97;return $mod===1;
    }

    public static function bankCsv(int $uid,int $aid,int $id): never
    {
        $doc=self::get($uid,$aid,$id);if($doc['status']!=='approved')throw new AuthException(422,'ابتدا سند حقوق را تأیید کنید.','PAYROLL_NOT_APPROVED');
        foreach($doc['lines'] as $line)if((float)$line['salary']>0&&(!self::validIban((string)$line['iban'])||floor((float)$line['salary'])!==(float)$line['salary']))throw new AuthException(422,'تمام ردیف‌های دارای مبلغ باید شبای معتبر و حقوق صحیح ریالی داشته باشند.','INVALID_BANK_DETAILS');
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="payroll-'.$id.'.csv"');header('Cache-Control: no-store');$f=fopen('php://output','wb');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,['نام','نام خانوادگی','شماره شبا','مبلغ ریال'],',','"','');foreach($doc['lines'] as $line)if((float)$line['salary']>0){$safe=fn($s)=>preg_match('/^[=+@-]/',(string)$s)?"'".$s:$s;fputcsv($f,[$safe($line['first_name']),$safe($line['last_name']),strtoupper(str_replace(' ','',(string)$line['iban'])),$line['salary']],',','"','');}fclose($f);exit;
    }
}
