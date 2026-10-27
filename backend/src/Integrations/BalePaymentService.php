<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use PDO;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Database\Connection;
use ServiceYar\Domain\DomainService;
use ServiceYar\Domain\OperationsService;
use ServiceYar\Support\Uuid;

final class BalePaymentService
{
    public static function create(int $uid,int $aid,array $body,?BaleClient $client=null): array
    {
        Authorization::requirePermission($uid,'finance.manage');Authorization::requireAgencyAccess($uid,$aid);
        if(!$client&&!BaleClient::configured())throw new AuthException(503,'پرداخت بله در تنظیمات سرور فعال نشده است.','BALE_NOT_CONFIGURED');
        $chat=(string)($body['chatId']??'');$invoice=(int)($body['invoiceId']??0);
        if(!preg_match('/^[1-9]\d{0,18}$/',$chat))throw new AuthException(422,'شناسه عددی گفتگوی بله را وارد کنید.','VALIDATION_ERROR');
        $row=OperationsService::transaction(function(PDO $p)use($uid,$aid,$chat,$invoice){
            $q=$p->prepare('SELECT * FROM invoices WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$invoice,'a'=>$aid]);$invoiceRow=$q->fetch();if(!$invoiceRow)throw new AuthException(404,'فاکتور پیدا نشد.','NOT_FOUND');
            $q=$p->prepare("SELECT id,uuid,status FROM payment_intents WHERE invoice_id=:id AND agency_id=:a AND status IN ('sending','pending','uncertain') AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1");$q->execute(['id'=>$invoice,'a'=>$aid]);if($pending=$q->fetch())return ['existing'=>$pending];
            $q=$p->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=:id AND agency_id=:a');$q->execute(['id'=>$invoice,'a'=>$aid]);$remaining=(float)$invoiceRow['amount']-(float)$q->fetchColumn();
            if($remaining<1||$remaining>1000000000000||floor($remaining)!==$remaining)throw new AuthException(422,'مانده فاکتور باید مبلغ صحیح ریالی و مثبت باشد.','INVALID_AMOUNT');
            $data=['uuid'=>Uuid::v4(),'agency'=>$aid,'invoice'=>$invoice,'user'=>$uid,'payload'=>bin2hex(random_bytes(32)),'chat'=>$chat,'amount'=>(int)$remaining];
            $p->prepare("INSERT INTO payment_intents(uuid,agency_id,invoice_id,created_by,payload,chat_id,amount,status,expires_at) VALUES(:uuid,:agency,:invoice,:user,:payload,:chat,:amount,'sending',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))")->execute($data);$data['id']=(int)$p->lastInsertId();return $data;
        });
        if(isset($row['existing']))return ['intent'=>$row['existing'],'alreadyPending'=>true];
        $client??=new BaleClient();
        try{
            $message=$client->request('sendInvoice',['chat_id'=>$chat,'title'=>'پرداخت فاکتور سرویسیار','description'=>'پرداخت فاکتور شماره '.$invoice,'payload'=>$row['payload'],'provider_token'=>(string)config('BALE_WALLET_PROVIDER_TOKEN'),'currency'=>'IRR','prices'=>[['label'=>'مانده فاکتور','amount'=>$row['amount']]]]);
            Connection::get()->prepare("UPDATE payment_intents SET status='pending' WHERE id=:id AND status='sending'")->execute(['id'=>$row['id']]);
            OperationsService::audit($uid,$aid,'bale_invoice_created','invoices',$invoice);return ['intent'=>['id'=>$row['id'],'uuid'=>$row['uuid'],'status'=>'pending'],'messageId'=>$message['message_id']??null];
        }catch(\Throwable $e){Connection::get()->prepare("UPDATE payment_intents SET status='uncertain' WHERE id=:id AND status='sending'")->execute(['id'=>$row['id']]);throw $e;}
    }

    public static function status(int $uid,int $aid): array
    {
        Authorization::requirePermission($uid,'finance.view');Authorization::requireAgencyAccess($uid,$aid);$q=Connection::get()->prepare('SELECT id,uuid,invoice_id,amount,status,payment_id,created_at,paid_at FROM payment_intents WHERE agency_id=:a ORDER BY id DESC LIMIT 100');$q->execute(['a'=>$aid]);return $q->fetchAll();
    }

    public static function webhook(array $body,?BaleClient $client=null): array
    {
        $client??=new BaleClient();
        if(isset($body['pre_checkout_query'])){
            $event=$body['pre_checkout_query'];$ok=false;
            try{OperationsService::transaction(function(PDO $p)use($event){
                $r=self::intent($p,(string)($event['invoice_payload']??''));
                if(!in_array($r['status'],['pending','sending','uncertain'],true)||strtotime($r['expires_at'].' UTC')<time())throw new AuthException(422,'درخواست پرداخت منقضی است.','PAYMENT_MISMATCH');
                self::matches($r,$event,(string)($event['from']['id']??''));$tx=(string)($event['id']??'');if($tx===''||strlen($tx)>100||($r['transaction_id']!==null&&$r['transaction_id']!==$tx))throw new AuthException(422,'شناسه پرداخت متفاوت است.','PAYMENT_MISMATCH');
                $p->prepare('UPDATE payment_intents SET transaction_id=:t WHERE id=:id')->execute(['t'=>$tx,'id'=>$r['id']]);
            });$ok=true;}catch(AuthException){}
            $reply=['pre_checkout_query_id'=>(string)($event['id']??''),'ok'=>$ok];if(!$ok)$reply['error_message']='اطلاعات پرداخت معتبر نیست یا درخواست منقضی شده است.';$client->request('answerPreCheckoutQuery',$reply);return ['preCheckout'=>$ok];
        }
        $payment=$body['message']['successful_payment']??null;if(!is_array($payment))return ['ignored'=>true];
        $transaction=(string)($payment['telegram_payment_charge_id']??'');if($transaction===''||strlen($transaction)>100)throw new AuthException(422,'شناسه پرداخت نامعتبر است.','PAYMENT_MISMATCH');
        $proof=$client->request('inquireTransaction',['transaction_id'=>$transaction]);
        if(!is_array($proof)||($proof['status']??'')!=='paid'||(string)($proof['id']??'')!==$transaction)throw new AuthException(409,'پرداخت توسط بله تأیید نشده است.','PAYMENT_UNVERIFIED');
        return self::settle($payment,$proof);
    }

    private static function intent(PDO $p,string $payload): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$payload))throw new AuthException(422,'اطلاعات صورتحساب معتبر نیست.','PAYMENT_MISMATCH');
        $q=$p->prepare('SELECT * FROM payment_intents WHERE payload=:p FOR UPDATE');$q->execute(['p'=>$payload]);$r=$q->fetch();if(!$r)throw new AuthException(404,'درخواست پرداخت پیدا نشد.','NOT_FOUND');return $r;
    }

    private static function matches(array $intent,array $payment,string $payer): void
    {
        $amount=$payment['total_amount']??null;
        if(($payment['currency']??'')!=='IRR'||!ctype_digit((string)$amount)||(string)(int)$amount!==(string)$intent['amount']||$payer!==$intent['chat_id'])throw new AuthException(422,'مبلغ، واحد یا پرداخت‌کننده با درخواست یکسان نیست.','PAYMENT_MISMATCH');
    }

    private static function settle(array $payment,array $proof): array
    {
        return OperationsService::transaction(function(PDO $p)use($payment,$proof){
            $r=self::intent($p,(string)($payment['invoice_payload']??''));self::matches($r,$payment,(string)($proof['userID']??''));
            if((string)($proof['amount']??'')!==(string)$r['amount']||$r['transaction_id']!==(string)$proof['id'])throw new AuthException(422,'استعلام پرداخت با درخواست یکسان نیست.','PAYMENT_MISMATCH');
            if($r['status']==='paid')return ['paid'=>true,'duplicate'=>true,'paymentId'=>$r['payment_id']];
            $q=$p->prepare('SELECT * FROM invoices WHERE id=:id AND agency_id=:a FOR UPDATE');$q->execute(['id'=>$r['invoice_id'],'a'=>$r['agency_id']]);$invoice=$q->fetch();if(!$invoice)throw new AuthException(404,'فاکتور پیدا نشد.','NOT_FOUND');
            $date=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Tehran')))->format('Y-m-d');
            $p->prepare("INSERT INTO payments(agency_id,invoice_id,amount,payment_date,method,reference_no,note) VALUES(:a,:i,:v,:date,'bale',:r,'پرداخت استعلام‌شده کیف پول بله')")->execute(['a'=>$r['agency_id'],'i'=>$r['invoice_id'],'v'=>$r['amount'],'date'=>$date,'r'=>(string)$proof['id']]);$id=(int)$p->lastInsertId();
            $q=$p->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=:i AND agency_id=:a');$q->execute(['i'=>$r['invoice_id'],'a'=>$r['agency_id']]);if((float)$q->fetchColumn()>=(float)$invoice['amount'])$p->prepare("UPDATE invoices SET status='paid' WHERE id=:i AND agency_id=:a")->execute(['i'=>$r['invoice_id'],'a'=>$r['agency_id']]);
            $p->prepare("UPDATE payment_intents SET status='paid',payment_id=:p,paid_at=UTC_TIMESTAMP() WHERE id=:id")->execute(['p'=>$id,'id'=>$r['id']]);OperationsService::audit((int)$r['created_by'],(int)$r['agency_id'],'bale_payment_verified','invoices',(int)$r['invoice_id']);return ['paid'=>true,'paymentId'=>$id];
        });
    }
}
