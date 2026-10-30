<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;

final class AccountStatement
{
    public static function report(int $uid,int $aid,array $filter,bool $export=false): array
    {
        Authorization::requirePermission($uid,'finance.view');
        Authorization::requireAgencyAccess($uid,$aid);
        $id=$filter['accountId']??'';
        if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new AuthException(422,'حساب را انتخاب کنید.','VALIDATION_ERROR');
        $from=$filter['from']??'';$to=$filter['to']??'';
        foreach([$from,$to] as $date)if(!is_string($date)||($date!==''&&!OperationsService::validDate($date)))throw new AuthException(422,'تاریخ نامعتبر است.','VALIDATION_ERROR');
        if($from!==''&&$to!==''&&$from>$to)throw new AuthException(422,'ابتدای بازه باید قبل از پایان باشد.','VALIDATION_ERROR');
        $page=filter_var($filter['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
        if($page===false)throw new AuthException(422,'صفحه نامعتبر است.','VALIDATION_ERROR');
        return OperationsService::transaction(function(PDO $p)use($aid,$id,$from,$to,$page,$export){
            // Historical entries remain visible after their account is deactivated.
            $q=$p->prepare('SELECT id,code,title,is_active FROM accounting_accounts WHERE id=? AND agency_id=?');$q->execute([(int)$id,$aid]);$account=$q->fetch();
            if(!$account)throw new AuthException(404,'حساب پیدا نشد.','NOT_FOUND');
            $base=" FROM accounting_document_lines l JOIN accounting_documents d ON d.id=l.document_id AND d.agency_id=:agency WHERE l.account_id=:account AND d.status='approved'";
            $params=['agency'=>$aid,'account'=>(int)$id];$opening='0.00';
            if($from!==''){$q=$p->prepare('SELECT COALESCE(SUM(l.debit-l.credit),0)'.$base.' AND d.document_date<:from');$q->execute($params+['from'=>$from]);$opening=(string)$q->fetchColumn();}
            $range='';if($from!==''){$range.=' AND d.document_date>=:from';$params['from']=$from;}if($to!==''){$range.=' AND d.document_date<=:to';$params['to']=$to;}
            $q=$p->prepare('SELECT COUNT(*) total,COALESCE(SUM(l.debit),0) debit,COALESCE(SUM(l.credit),0) credit,CAST(:opening AS DECIMAL(30,2))+COALESCE(SUM(l.debit-l.credit),0) closing'.$base.$range);$q->execute($params+['opening'=>$opening]);$totals=$q->fetch();$total=(int)$totals['total'];
            if($export&&$total>5000)throw new AuthException(422,'برای خروجی اکسل بازه را به حداکثر ۵۰۰۰ ردیف محدود کنید.','EXPORT_LIMIT');
            $limit=$export?5000:25;$offset=$export?0:($page-1)*$limit;
            // Window is calculated before LIMIT, so balances continue across pages.
            $q=$p->prepare('SELECT l.id,l.document_id,d.document_no,d.document_date,l.description,l.tracking_no,l.debit,l.credit,ROW_NUMBER() OVER (ORDER BY d.document_date,d.id,l.id) row_no,CAST(:opening AS DECIMAL(30,2))+SUM(l.debit-l.credit) OVER (ORDER BY d.document_date,d.id,l.id ROWS UNBOUNDED PRECEDING) balance'.$base.$range.' ORDER BY d.document_date,d.id,l.id LIMIT '.$limit.' OFFSET '.$offset);
            $q->execute($params+['opening'=>$opening]);
            return ['account'=>$account,'from'=>$from,'to'=>$to,'opening'=>$opening,'debit'=>$totals['debit'],'credit'=>$totals['credit'],'closing'=>$totals['closing'],'items'=>$q->fetchAll(),'pagination'=>['page'=>$export?1:$page,'pages'=>(int)ceil($total/$limit),'total'=>$total,'limit'=>$limit]];
        });
    }
}
