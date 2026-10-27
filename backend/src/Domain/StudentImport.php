<?php
declare(strict_types=1);
namespace ServiceYar\Domain;
use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class StudentImport
{
    private const HEADERS=['نام'=>'firstName','نام خانوادگی'=>'lastName','کد ملی'=>'nationalCode','شماره همراه ولی'=>'mobile','شماره والدین'=>'mobile','پایه'=>'grade','آدرس'=>'address','نام ولی'=>'guardianFirstName','نام خانوادگی ولی'=>'guardianLastName','سال تحصیلی'=>'academicYear'];

    public static function preview(int $uid,int $aid,array $file): array
    {
        Authorization::requirePermission($uid,'students.manage');Authorization::requireAgencyAccess($uid,$aid);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>2*1024*1024||!is_uploaded_file($file['tmp_name']??''))throw new AuthException(422,'فایل CSV یا XLSX معتبر تا حجم ۲ مگابایت انتخاب کنید.','IMPORT_INVALID');
        $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
        if($ext==='csv'){$f=fopen($file['tmp_name'],'rb');$rows=[];try{while(($r=fgetcsv($f,0,',','"',''))!==false){$rows[]=$r;if(count($rows)>201)throw new AuthException(422,'هر بار حداکثر ۲۰۰ ردیف وارد کنید.','IMPORT_LIMIT');}}finally{fclose($f);}}
        elseif($ext==='xlsx')$rows=self::xlsx($file['tmp_name']);
        else throw new AuthException(422,'فقط CSV و XLSX پشتیبانی می‌شوند.','IMPORT_INVALID');
        return ['rows'=>self::map($rows),'limit'=>200];
    }

    public static function map(array $rows): array
    {
        if(count($rows)<2)throw new AuthException(422,'فایل باید سطر عنوان و حداقل یک دانش‌آموز داشته باشد.','IMPORT_INVALID');
        $headers=array_map(function($h){$h=trim(str_replace("\xEF\xBB\xBF",'',(string)$h));return self::HEADERS[$h]??$h;},array_shift($rows));
        foreach(['firstName','lastName','mobile'] as $h)if(!in_array($h,$headers,true))throw new AuthException(422,'ستون‌های نام، نام خانوادگی و شماره همراه ولی الزامی است.','IMPORT_INVALID');
        $allowed=['firstName','lastName','nationalCode','mobile','grade','address','guardianFirstName','guardianLastName','academicYear'];$out=[];
        foreach($rows as $index=>$values){if(!array_filter($values,fn($v)=>trim((string)$v)!==''))continue;$row=[];foreach($headers as $i=>$h)if(in_array($h,$allowed,true))$row[$h]=trim((string)($values[$i]??''));
            foreach(['mobile','nationalCode'] as $key)if(isset($row[$key]))$row[$key]=strtr($row[$key],['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
            if(empty($row['firstName'])||empty($row['lastName'])||!preg_match('/^09\d{9}$/',$row['mobile']??''))throw new AuthException(422,'نام یا شماره همراه در ردیف '.($index+2).' نامعتبر است.','IMPORT_INVALID');$out[]=$row;
        }if(!$out||count($out)>200)throw new AuthException(422,'بین ۱ تا ۲۰۰ دانش‌آموز وارد کنید.','IMPORT_LIMIT');return $out;
    }

    private static function xlsx(string $path): array
    {
        if(!class_exists(\ZipArchive::class))throw new AuthException(503,'افزونه ZIP برای ورود Excel نصب نیست.','IMPORT_DEPENDENCY');
        $zip=new \ZipArchive();if($zip->open($path)!==true)throw new AuthException(422,'فایل Excel خوانده نشد.','IMPORT_INVALID');
        try{
            if($zip->numFiles>500)throw new AuthException(422,'تعداد بخش‌های فایل بیش از حد مجاز است.','IMPORT_LIMIT');
            $size=0;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$size+=$stat['size'];}if($size>12*1024*1024)throw new AuthException(422,'حجم بازشده فایل بیش از حد مجاز است.','IMPORT_LIMIT');
            $read=static function(string $name)use($zip){$xml=$zip->getFromName($name);if($xml===false)return null;if(stripos($xml,'<!DOCTYPE')!==false||stripos($xml,'<!ENTITY')!==false)throw new AuthException(422,'XML فایل مجاز نیست.','IMPORT_INVALID');$old=libxml_use_internal_errors(true);try{$doc=simplexml_load_string($xml,\SimpleXMLElement::class,LIBXML_NONET);if(!$doc)throw new AuthException(422,'ساختار فایل Excel معتبر نیست.','IMPORT_INVALID');return $doc;}finally{libxml_clear_errors();libxml_use_internal_errors($old);}};
            $strings=[];$shared=$read('xl/sharedStrings.xml');if($shared)foreach($shared->si as $si){$value='';foreach($si->xpath('.//*[local-name()="t"]') as $t)$value.=(string)$t;$strings[]=$value;}
            $sheet=$read('xl/worksheets/sheet1.xml');if(!$sheet)throw new AuthException(422,'برگه اول Excel پیدا نشد.','IMPORT_INVALID');$rows=[];
            foreach($sheet->sheetData->row as $row){$cells=[];foreach($row->c as $cell){preg_match('/^([A-Z]+)\d+$/',(string)$cell['r'],$m);$column=0;foreach(str_split($m[1]??'A') as $letter)$column=$column*26+ord($letter)-64;if($column>50)throw new AuthException(422,'تعداد ستون‌ها بیش از حد است.','IMPORT_LIMIT');$type=(string)$cell['t'];$v=(string)$cell->v;if($type==='s')$v=$strings[(int)$v]??'';elseif($type==='inlineStr'){$v='';foreach($cell->is->xpath('.//*[local-name()="t"]') as $t)$v.=(string)$t;}$cells[$column-1]=$v;}
                $values=[];for($i=0;$i<=max(array_keys($cells)?:[0]);$i++)$values[]=$cells[$i]??'';$rows[]=$values;if(count($rows)>201)throw new AuthException(422,'حداکثر ۲۰۰ ردیف وارد کنید.','IMPORT_LIMIT');}return $rows;
        }finally{$zip->close();}
    }

    public static function commit(int $uid,int $aid,array $body): array
    {
        Authorization::requirePermission($uid,'students.manage');Authorization::requireAgencyAccess($uid,$aid);
        $rows=$body['rows']??[];if(!is_array($rows)||!count($rows)||count($rows)>200)throw new AuthException(422,'بین ۱ تا ۲۰۰ ردیف وارد کنید.','IMPORT_LIMIT');
        $school=(int)($body['schoolId']??0);DomainService::party(Connection::get(),$aid,'school',$school);
        return OperationsService::transaction(function()use($uid,$aid,$school,$rows){$ids=[];foreach($rows as $row){if(!is_array($row))throw new AuthException(422,'ساختار ردیف نامعتبر است.','IMPORT_INVALID');$allowed=array_intersect_key($row,array_flip(['firstName','lastName','nationalCode','mobile','grade','address','guardianFirstName','guardianLastName','academicYear']));$allowed['schoolId']=$school;$ids[]=DomainService::save($uid,$aid,'students',$allowed)['id'];}return ['created'=>count($ids),'ids'=>$ids];});
    }
}
