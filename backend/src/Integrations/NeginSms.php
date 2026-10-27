<?php
declare(strict_types=1);
namespace ServiceYar\Integrations;
use ServiceYar\Auth\AuthException;

final class NeginSms
{
    public static function configured(): bool{return config('NEGIN_SMS_ENABLED','0')==='1'&&config('NEGIN_SMS_USERNAME','')!==''&&config('NEGIN_SMS_PASSWORD','')!=='';}

    public static function result(object $response): array
    {
        $raw=$response->pMessageIds->long??[];$ids=is_array($raw)?$raw:[$raw];$ids=array_values(array_filter(array_map('strval',$ids),fn($id)=>preg_match('/^-?\d+$/',$id)&&$id!=='0'));
        $code=(string)($response->SendSmsResult??'0');
        if($ids)return ['accepted'=>true,'id'=>$ids[0]];
        if(is_numeric($code)&&(float)$code<0)return ['accepted'=>true,'id'=>$code];
        return ['accepted'=>false,'code'=>ctype_digit($code)?$code:'unknown'];
    }

    public function send(string $mobile,string $text): array
    {
        if(!self::configured())throw new AuthException(503,'پیامک نگین تنظیم یا فعال نشده است.','SMS_NOT_CONFIGURED');
        if(!preg_match('/^09\d{9}$/',$mobile)||trim($text)==='')throw new AuthException(422,'شماره یا متن پیامک نامعتبر است.','VALIDATION_ERROR');
        if(!class_exists(\SoapClient::class))throw new AuthException(503,'افزونه SOAP برای پیامک نگین فعال نیست.','PROVIDER_UNAVAILABLE');
        try{
            $client=new \SoapClient(__DIR__.'/negin.wsdl',['location'=>'https://sms.3300.ir/almassms.asmx','exceptions'=>true,'trace'=>false,'connection_timeout'=>8,'cache_wsdl'=>WSDL_CACHE_MEMORY,'stream_context'=>stream_context_create(['http'=>['timeout'=>25],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]])]);
            return self::result($client->SendSms(['pUsername'=>(string)config('NEGIN_SMS_USERNAME'),'pPassword'=>(string)config('NEGIN_SMS_PASSWORD'),'messages'=>[$text],'mobiles'=>[$mobile]]));
        }catch(\SoapFault){throw new AuthException(502,'نتیجه ارسال پیامک مشخص نیست؛ برای جلوگیری از ارسال تکراری، وضعیت پنل نگین بررسی شود.','SMS_UNCERTAIN');}
    }
}
