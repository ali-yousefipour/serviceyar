<?php
declare(strict_types=1);
namespace ServiceYar\Agency;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;

final class AgencyLogo
{
    private static function directory(): string {return dirname(__DIR__,3).'/storage/uploads/agency-logos';}
    private static function filename(int $aid): string {return self::directory().'/agency-'.(int)$aid.'.img';}

    public static function upload(int $uid,int $aid,array $file): array
    {
        Authorization::requirePermission($uid,'settings.manage');
        Authorization::requireAgencyAccess($uid,$aid);
        $size=(int)($file['size']??0);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||$size<1||$size>1048576||!is_uploaded_file((string)($file['tmp_name']??'')))
            throw new AuthException(422,'تصویر لوگو باید PNG، JPEG یا WebP و حداکثر یک مگابایت باشد.','INVALID_LOGO');
        $info=@getimagesize($file['tmp_name']);
        if(!$info||!in_array($info['mime']??'',['image/png','image/jpeg','image/webp'],true)||($info[0]??0)>2000||($info[1]??0)>2000||($info[0]??0)<32||($info[1]??0)<32)
            throw new AuthException(422,'ابعاد لوگو باید بین ۳۲ تا ۲۰۰۰ پیکسل و نوع آن تصویری معتبر باشد.','INVALID_LOGO');
        $dir=self::directory();
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))throw new AuthException(503,'فضای ذخیره لوگو در دسترس نیست.','STORAGE_UNAVAILABLE');
        $temp=tempnam($dir,'company-logo-');
        if($temp===false)throw new AuthException(503,'فضای ذخیره لوگو در دسترس نیست.','STORAGE_UNAVAILABLE');
        if(!move_uploaded_file($file['tmp_name'],$temp)){@unlink($temp);throw new AuthException(503,'لوگو ذخیره نشد.','STORAGE_UNAVAILABLE');}
        chmod($temp,0600);
        if(!rename($temp,self::filename($aid))){@unlink($temp);throw new AuthException(503,'لوگو ذخیره نشد.','STORAGE_UNAVAILABLE');}
        return ['updated'=>true,'version'=>(string)time()];
    }

    public static function delete(int $uid,int $aid): array
    {
        Authorization::requirePermission($uid,'settings.manage');
        Authorization::requireAgencyAccess($uid,$aid);
        $path=self::filename($aid);
        if(is_file($path))@unlink($path);
        return ['removed'=>true];
    }

    public static function output(int $uid,int $aid): never
    {
        Authorization::requireAgencyAccess($uid,$aid);
        $path=self::filename($aid);
        if(!is_file($path)){
            // Production FTP publishes frontend/assets under /public_html/assets,
            // whereas the local checkout keeps them under frontend/assets.
            foreach([dirname(__DIR__,3).'/assets/logo.png',dirname(__DIR__,3).'/frontend/assets/logo.png'] as $fallback){
                if(is_file($fallback)){$path=$fallback;break;}
            }
        }
        if(!is_file($path))throw new AuthException(404,'لوگوی پیش‌فرض در فایل‌های انتشار موجود نیست.','NOT_FOUND');
        $mime=(string)(@getimagesize($path)['mime']??'');
        if(!in_array($mime,['image/png','image/jpeg','image/webp'],true))throw new AuthException(500,'فایل لوگو معتبر نیست.','INVALID_LOGO');
        header('Content-Type: '.$mime);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: '.filesize($path));
        readfile($path);exit;
    }
}
