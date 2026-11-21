<?php
declare(strict_types=1);
require __DIR__.'/../backend/src/Auth/AuthException.php';
require __DIR__.'/../backend/src/Integrations/KavenegarSms.php';

use ServiceYar\Auth\AuthException;
use ServiceYar\Integrations\KavenegarSms;

function check(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}
$success='{"return":{"status":200,"message":"تایید شد"},"entries":[{"messageid":123456,"receptor":"09123456789"}]}';
$result=KavenegarSms::parseResponse(200,$success);
check($result===['accepted'=>true,'id'=>'123456'],'Valid accepted result');
$failed=KavenegarSms::parseResponse(200,'{"return":{"status":418,"message":"Invalid"}}');
check($failed===['accepted'=>false,'code'=>'418'],'Provider rejection must remain rejected');
$failed=KavenegarSms::parseResponse(401,$success);
check($failed['accepted']===false,'HTTP errors must not be accepted');
foreach(['','not json','{"return":{"status":200}}','<html>error</html>'] as $body){
    try {
        KavenegarSms::parseResponse(200,$body);
        throw new RuntimeException('Invalid provider response was accepted');
    }catch(AuthException $e){
        check($e->codeName==='SMS_PROVIDER_INVALID_RESPONSE','Invalid response error code');
    }
}
echo "Kavenegar SMS parse response and error isolation: OK\n";
