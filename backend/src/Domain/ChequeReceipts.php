<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;

final class ChequeReceipts
{
    private static function scope(int $uid,int $aid,bool $write=false): PDO
    {
        Authorization::requirePermission($uid,$write?'finance.manage':'finance.view');Authorization::requireAgencyAccess($uid,$aid);return Connection::get();
    }
    public static function list(int $uid,int $aid,array $filter): array
    {
        $p=self::scope($uid,$aid);$where='c.agency_id=:agency';$params=['agency'=>$aid];
        $status=$filter['status']??'';if(!is_string($status)||!in_array($status,['','received','collected','bounced','cancelled'],true))throw new AuthException(422,'وضعیت چک نامعتبر است.','VALIDATION_ERROR');
        if($status!==''){$where.=' AND c.status=:status';$params['status']=$status;}
        foreach(['from'=>'>=','to'=>'<='] as $key=>$operator){$date=$filter[$key]??'';if(!is_string($date)||($date!==''&&!OperationsService::validDate($date)))throw new AuthException(422,'تاریخ نامعتبر است.','VALIDATION_ERROR');if($date!==''){$where.=' AND c.due_date'.$operator.':'.$key;$params[$key]=$date;}}
        if(!empty($filter['from'])&&!empty($filter['to'])&&$filter['from']>$filter['to'])throw new AuthException(422,'بازه تاریخ معکوس است.','VALIDATION_ERROR');
        $search=$filter['search']??'';if(!is_string($search)||strlen($search)>200)throw new AuthException(422,'جستجو نامعتبر است.','VALIDATION_ERROR');
        if($search!==''){$where.=' AND (c.serial_no LIKE :s1 OR c.bank_name LIKE :s2 OR c.received_from LIKE :s3 OR c.bank_account LIKE :s4)';foreach(['s1','s2','s3','s4'] as $key)$params[$key]='%'.$search.'%';}
        $page=filter_var($filter['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);if($page===false)throw new AuthException(422,'صفحه نامعتبر است.','VALIDATION_ERROR');
        $q=$p->prepare('SELECT COUNT(*) n,COALESCE(SUM(c.amount),0) total FROM cheque_receipts c WHERE '.$where);$q->execute($params);$totals=$q->fetch();
        $q=$p->prepare('SELECT c.*,a.code account_code,a.title account_title FROM cheque_receipts c JOIN accounting_accounts a ON a.id=c.account_id AND a.agency_id=c.agency_id WHERE '.$where.' ORDER BY c.due_date,c.id LIMIT 25 OFFSET '.(($page-1)*25));$q->execute($params);
        return ['items'=>$q->fetchAll(),'amount'=>$totals['total'],'pagination'=>['page'=>$page,'pages'=>(int)ceil((int)$totals['n']/25),'total'=>(int)$totals['n']]];
    }
    public static function get(int $uid,int $aid,int $id): array
    {
        $p=self::scope($uid,$aid);$q=$p->prepare('SELECT c.*,a.code account_code,a.title account_title FROM cheque_receipts c JOIN accounting_accounts a ON a.id=c.account_id AND a.agency_id=c.agency_id WHERE c.id=? AND c.agency_id=?');$q->execute([$id,$aid]);$row=$q->fetch();if(!$row)throw new AuthException(404,'چک پیدا نشد.','NOT_FOUND');
        $q=$p->prepare('SELECT e.event_type,e.event_date,e.note,e.created_at,u.first_name,u.last_name FROM cheque_events e JOIN users u ON u.id=e.actor_id WHERE e.cheque_id=? ORDER BY e.id');$q->execute([$id]);$row['events']=$q->fetchAll();return $row;
    }
    public static function save(int $uid,int $aid,array $body,?int $id=null): array
    {
        self::scope($uid,$aid,true);
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$body,$id){
            if($id){$q=$p->prepare('SELECT status FROM cheque_receipts WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$id,$aid]);$status=$q->fetchColumn();if($status===false)throw new AuthException(404,'چک پیدا نشد.','NOT_FOUND');if($status!=='received')throw new AuthException(409,'چک دارای عملیات نهایی قابل ویرایش نیست.','CHEQUE_LOCKED');}
            $data=[];foreach(['bankName'=>100,'branchName'=>100,'serialNo'=>100,'bankAccount'=>100,'receivedFrom'=>200,'description'=>1000] as $key=>$max){$value=$body[$key]??'';if(!is_string($value)||preg_match_all('/./us',$value)>$max)throw new AuthException(422,'متن مشخصات چک نامعتبر است.','VALIDATION_ERROR');$data[$key]=trim($value);}
            foreach(['bankName','serialNo','receivedFrom'] as $key)if($data[$key]==='')throw new AuthException(422,'بانک، شماره چک و نام پرداخت‌کننده لازم است.','VALIDATION_ERROR');
            $amount=$body['amount']??'';if(!is_scalar($amount)||!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D',(string)$amount)||(float)$amount<=0)throw new AuthException(422,'مبلغ مثبت با حداکثر دو رقم اعشار وارد کنید.','VALIDATION_ERROR');
            $received=$body['receivedDate']??'';$due=$body['dueDate']??'';if(!is_string($received)||!is_string($due)||!OperationsService::validDate($received)||!OperationsService::validDate($due))throw new AuthException(422,'تاریخ دریافت و سررسید لازم است.','VALIDATION_ERROR');
            $account=$body['accountId']??'';if(!is_scalar($account)||!ctype_digit((string)$account))throw new AuthException(422,'حساب را انتخاب کنید.','CROSS_SCOPE_REFERENCE');$q=$p->prepare('SELECT id FROM accounting_accounts WHERE id=? AND agency_id=? AND is_active=1 FOR UPDATE');$q->execute([(int)$account,$aid]);if(!$q->fetchColumn())throw new AuthException(422,'حساب فعال متعلق به شرکت انتخاب کنید.','CROSS_SCOPE_REFERENCE');
            $q=$p->prepare('SELECT id FROM cheque_receipts WHERE agency_id=? AND bank_name=? AND serial_no=? AND id<>? FOR UPDATE');$q->execute([$aid,$data['bankName'],$data['serialNo'],$id??0]);if($q->fetchColumn())throw new AuthException(409,'این شماره چک برای همین بانک ثبت شده است.','CHEQUE_EXISTS');
            $values=[$data['bankName'],$data['branchName'],$data['serialNo'],$data['bankAccount'],$data['receivedFrom'],(int)$account,(string)$amount,$received,$due,$data['description']];
            try{if($id)$p->prepare('UPDATE cheque_receipts SET bank_name=?,branch_name=?,serial_no=?,bank_account=?,received_from=?,account_id=?,amount=?,received_date=?,due_date=?,description=? WHERE id=? AND agency_id=?')->execute([...$values,$id,$aid]);else{$p->prepare('INSERT INTO cheque_receipts(bank_name,branch_name,serial_no,bank_account,received_from,account_id,amount,received_date,due_date,description,agency_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$values,$aid,$uid]);$id=(int)$p->lastInsertId();}}catch(\PDOException $e){if(($e->errorInfo[1]??0)===1062)throw new AuthException(409,'چک تکراری است.','CHEQUE_EXISTS');throw $e;}
            $p->prepare('INSERT INTO cheque_events(cheque_id,actor_id,event_type,event_date,note) VALUES(?,?,?,?,?)')->execute([$id,$uid,'saved',$received,$data['description']]);OperationsService::audit($uid,$aid,'save_cheque','cheque_receipts',$id);return self::get($uid,$aid,$id);
        });
    }
    public static function transition(int $uid,int $aid,int $id,array $body): array
    {
        self::scope($uid,$aid,true);$state=$body['status']??'';$date=$body['date']??'';$note=$body['note']??'';
        if(!in_array($state,['collected','bounced','cancelled'],true)||!is_string($date)||!OperationsService::validDate($date)||!is_string($note)||trim($note)===''||preg_match_all('/./us',$note)>1000)throw new AuthException(422,'نوع عملیات، تاریخ و شرح لازم است.','VALIDATION_ERROR');
        return OperationsService::transaction(function(PDO $p)use($uid,$aid,$id,$state,$date,$note){$q=$p->prepare('SELECT status,received_date FROM cheque_receipts WHERE id=? AND agency_id=? FOR UPDATE');$q->execute([$id,$aid]);$row=$q->fetch();if(!$row)throw new AuthException(404,'چک پیدا نشد.','NOT_FOUND');if($row['status']!=='received')throw new AuthException(409,'عملیات این چک قبلاً نهایی شده است.','CHEQUE_LOCKED');if($date<$row['received_date'])throw new AuthException(422,'تاریخ عملیات نباید قبل از دریافت باشد.','VALIDATION_ERROR');$p->prepare('UPDATE cheque_receipts SET status=? WHERE id=? AND agency_id=?')->execute([$state,$id,$aid]);$p->prepare('INSERT INTO cheque_events(cheque_id,actor_id,event_type,event_date,note) VALUES(?,?,?,?,?)')->execute([$id,$uid,$state,$date,trim($note)]);OperationsService::audit($uid,$aid,'cheque_'.$state,'cheque_receipts',$id);return self::get($uid,$aid,$id);});
    }
}
