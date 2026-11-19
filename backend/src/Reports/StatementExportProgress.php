<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

/** Short-lived stage markers, scoped to the authenticated user and agency. */
final class StatementExportProgress
{
    private static function path(int $uid,int $aid,string $token): ?string
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$token))return null;
        $dir=dirname(__DIR__,3).'/storage/tmp/statement-progress';
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return null;
        return $dir.'/'.hash('sha256',$uid.':'.$aid.':'.$token).'.json';
    }
    public static function write(int $uid,int $aid,int $percent,string $stage): void
    {
        $path=self::path($uid,$aid,(string)($_GET['progressToken']??''));
        if($path===null)return;
        // Best-effort progress must never cause a valid export to fail.
        if($percent===8){
            foreach(glob(dirname($path).'/*.json')?:[] as $old){
                if((@filemtime($old)?:0)<time()-3600)@unlink($old);
            }
        }
        $tmp=$path.'.tmp';
        if(@file_put_contents($tmp,json_encode(['percent'=>$percent,'stage'=>$stage]),LOCK_EX)!==false)@rename($tmp,$path);
    }
    public static function read(int $uid,int $aid,string $token): array
    {
        $path=self::path($uid,$aid,$token);
        if($path!==null&&is_file($path)&&(@filemtime($path)?:0)>=time()-3600){
            $value=json_decode((string)@file_get_contents($path),true);
            if(is_array($value))return $value;
        }
        return ['percent'=>0,'stage'=>'PREPARE'];
    }
}
