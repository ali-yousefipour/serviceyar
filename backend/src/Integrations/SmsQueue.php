<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Database\Connection;
use ServiceYar\Domain\OperationsService;

final class SmsQueue
{
    public static function run(int $limit=20,?NeginSms $provider=null): array
    {
        $provider??=new NeginSms();$p=Connection::get();$sent=0;$failed=0;
        for($i=0;$i<min(100,max(1,$limit));$i++){
            $row=OperationsService::transaction(function($p){$q=$p->query("SELECT id,recipient_mobile,body FROM messages WHERE channel='sms' AND status='queued' ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");$r=$q->fetch();if(!$r)return null;$p->prepare("UPDATE messages SET status='sending',attempts=attempts+1 WHERE id=?")->execute([$r['id']]);return $r;});
            if(!$row)break;
            try{$result=$provider->send((string)$row['recipient_mobile'],(string)$row['body']);$ok=!empty($result['accepted']);$p->prepare('UPDATE messages SET status=:s,provider_id=:pid,provider_status=:ps,last_error=:e,sent_at=CASE WHEN :ok=1 THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id=:id')->execute(['s'=>$ok?'sent':'failed','pid'=>$result['id']??null,'ps'=>$ok?'accepted':'rejected','e'=>$ok?null:'Provider code '.($result['code']??'unknown'),'ok'=>$ok?1:0,'id'=>$row['id']]);$ok?$sent++:$failed++;}
            catch(\Throwable $e){$code=$e instanceof \ServiceYar\Auth\AuthException?$e->codeName:'PROVIDER_ERROR';$p->prepare("UPDATE messages SET status='uncertain',provider_status='needs_review',last_error=:e WHERE id=:id")->execute(['e'=>$code,'id'=>$row['id']]);$failed++;}
        }return ['accepted'=>$sent,'failedOrUncertain'=>$failed];
    }
}
