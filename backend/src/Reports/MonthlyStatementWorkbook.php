<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Domain\MonthlyStatements;
use ServiceYar\Domain\SchoolServiceStatement;
use ServiceYar\Database\Connection;

/** Multi-sheet Excel export from the frozen, approved monthly statement snapshot. */
final class MonthlyStatementWorkbook
{
    private static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
    }
    private static function column(int $n): string
    {
        $s='';while($n>0){$n--; $s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;
    }
    private static function sheet(array $headers,array $rows,string $title): string
    {
        $cols=count($headers);$end=max(2,count($rows)+2);
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.self::column($cols).$end.'"/>'
            .'<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="24"/><cols>';
        for($i=1;$i<=$cols;$i++)$xml.='<col min="'.$i.'" max="'.$i.'" width="'.($i===1?16:28).'" customWidth="1"/>';
        $xml.='</cols><sheetData>';
        $all=array_merge([[$title],$headers],$rows);
        foreach($all as $ri=>$row){
            $r=$ri+1;$xml.='<row r="'.$r.'"'.($r===2?' ht="75" customHeight="1"':'').'>';
            foreach($row as $ci=>$v){
                $ref=self::column($ci+1).$r;$style=$r<=2?1:(is_numeric($v)&&!is_string($v)?2:3);
                if(is_int($v)||is_float($v))$xml.='<c r="'.$ref.'" s="'.$style.'"><v>'.self::escape($v).'</v></c>';
                else $xml.='<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::escape($v).'</t></is></c>';
            }
            $xml.='</row>';
        }
        $xml.='</sheetData><mergeCells count="1"><mergeCell ref="A1:'.self::column($cols).'1"/></mergeCells>';
        if($rows)$xml.='<autoFilter ref="A2:'.self::column($cols).$end.'"/>';
        return $xml.'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/></worksheet>';
    }
    public static function download(int $uid,int $aid,int $id): never
    {
        Authorization::requirePermission($uid,'finance.view');
        $d=MonthlyStatements::get($uid,$aid,$id);
        if($d['status']!=='final')throw new AuthException(409,'برای دریافت فایل، صورت‌وضعیت باید فعال باشد.','STATEMENT_DELETED');
        if(!class_exists(\ZipArchive::class))throw new AuthException(500,'افزونه ZIP برای اکسل فعال نیست.','EXPORT_DEPENDENCY_MISSING');
        $q=Connection::get()->prepare('SELECT name FROM agencies WHERE id=?');$q->execute([$aid]);$agency=(string)$q->fetchColumn();
        // Prefer the real reference's layout and formula-bearing sheets when
        // the scrubbed template has been installed. Legacy synthetic XLSX is
        // retained as a safe fallback until the template is deployed.
        ReferenceMonthlyStatementWorkbook::downloadIfAvailable($d,$agency,$id);
        $label=$d['month_name'].' '.$d['year'];
        $title=$label.' - '.$agency.' - صورت‌وضعیت سرویس مدارس (ریال)';
        $items=$d['items'];$drivers=$d['drivers'];$rates=$d['rates'];$t=$d['totals'];
        // Frozen reference accounting is separate from driver deductions.
        // Legacy snapshots are calculated at export without mutating storage.
        $referenceTotals=$d['reference_totals']??[];
        $referenceDrivers=$d['reference_drivers']??[];
        if(!$referenceTotals || !$referenceDrivers){
            $referenceTotals=[];$referenceDrivers=[];
            foreach($items as $item){
                $ref=$item['reference']??SchoolServiceStatement::fromWorkbookRate((string)($item['gross']??0),30,true,true);
                $driverId=(int)($item['driver_id']??0);
                if(!isset($referenceDrivers[$driverId])){
                    $referenceDrivers[$driverId]=['driver_id'=>$driverId,
                        'driver_name'=>$item['driver_name']?:'راننده نامشخص',
                        'driver_iban'=>$item['driver_iban']??'','student_count'=>0];
                }
                $referenceDrivers[$driverId]['student_count']++;
                foreach([
                    'driver_fee'=>$ref['driver_fee'],
                    'oversight_3pct'=>$ref['oversight_3pct'],
                    'municipality_2_75pct'=>$ref['municipality_2_75pct'],
                    'municipality_vat_10pct'=>$ref['municipality_vat_10pct'],
                    'road_police_0_25pct'=>$ref['road_police_0_25pct'],
                    'education_1pct'=>$ref['education_1pct'],
                    'company_7pct'=>$ref['company_7pct'],
                    'certificate_fee'=>$ref['certificate_fee'],
                    'insurance_fee'=>$ref['insurance_fee'],
                    'parent_total'=>$ref['parent_total'],
                    'statement_excluding_certificate_insurance'=>$ref['statement_excluding_certificate_insurance']
                ] as $k=>$v){
                    $referenceTotals[$k]=($referenceTotals[$k]??0)+(float)$v;
                    $referenceDrivers[$driverId][$k]=($referenceDrivers[$driverId][$k]??0)+(float)$v;
                }
            }
            $referenceDrivers=array_values($referenceDrivers);
        }
        $schoolMap=[];$driverMap=[];$vehicleMap=[];
        foreach($items as $r){
            $schoolMap[$r['school_name']??'']=true;
            $driverMap[$r['driver_id']??0]=$r;
            $vehicleMap[$r['vehicle_plate']??'']=$r;
        }
        $studentRows=[];$parentRows=[];$archiveRows=[];
        foreach($items as $i=>$r){
            $studentRows[]=[
                $i+1,$r['student_name'],$r['student_national_code'],$r['grade'],$r['guardian_first_name'].' '.$r['guardian_last_name'],
                $r['mobile'],$r['address'],$r['school_name'],$r['region'],$r['tariff_distance_km'],$r['driver_name'],$r['service_name'],$r['gross']
            ];
            $ref=$r['reference']??SchoolServiceStatement::fromWorkbookRate((string)$r['gross'],30,true,true);
            $parentRows[]=[
                $i+1,$r['student_name'],$r['student_national_code'],$r['school_name'],$r['region'],$r['driver_name'],$r['vehicle_model'],$r['tariff_distance_km'],
                ($ref['base_fee']??$ref['driver_fee']),30,$ref['driver_fee'],$ref['oversight_3pct'],$ref['municipality_2_75pct'],
                $ref['municipality_vat_10pct'],$ref['road_police_0_25pct'],$ref['education_1pct'],
                $ref['company_7pct'],$ref['certificate_fee'],
                $ref['parent_total']-$ref['insurance_fee'],$ref['insurance_fee'],$ref['parent_total'],
                $ref['statement_excluding_certificate_insurance'],$r['driver_iban']??''
            ];
            $archiveRows[]=[$d['year'],$label,$i+1,$r['student_name'],$r['student_national_code'],$r['grade'],$r['guardian_first_name'].' '.$r['guardian_last_name'],$r['mobile'],
                '',$r['address'],$r['school_name'],$r['region'],$r['driver_name'],$r['driver_national_code'],$r['gross'],$r['deductions'],$r['net']];
        }
        $driverRows=[];$finalRows=[];
        foreach($drivers as $i=>$r){
            $extra=$driverMap[$r['driver_id']]??[];
            $driverRows[]=[$i+1,$r['driver_name'],$extra['driver_national_code']??'',$extra['driver_mobile']??'',
                $extra['vehicle_model']??'',$extra['vehicle_plate']??'',$extra['driver_iban']??'',$extra['bank_name']??'',$extra['bank_account']??''];

        }
        foreach(array_values($referenceDrivers) as $i=>$r){
            $finalRows[]=[$i+1,$r['driver_name'],$r['driver_iban'],$r['driver_fee'],
                $r['oversight_3pct'],$r['education_1pct'],$r['company_7pct'],
                $r['statement_excluding_certificate_insurance']];
        }
        $rateRows=[];foreach($rates as $key=>$rate)$rateRows[]=[$key,(float)$rate];
        $summaryRows=[
            ['دانش‌آموزان',$t['students']],['رانندگان',$t['drivers']],['مدارس',$t['schools']],
            ['حق‌الزحمه رانندگان',$referenceTotals['driver_fee']??0],
            ['مدیریت و نظارت ۳٪',$referenceTotals['oversight_3pct']??0],
            ['اطلاع‌رسانی و هماهنگی ۱٪',$referenceTotals['education_1pct']??0],
            ['سهم شرکت ۷٪',$referenceTotals['company_7pct']??0],
            ['شهرداری ۲٫۷۵٪',$referenceTotals['municipality_2_75pct']??0],
            ['ارزش افزوده شهرداری',$referenceTotals['municipality_vat_10pct']??0],
            ['راهور ۰٫۲۵٪',$referenceTotals['road_police_0_25pct']??0],
            ['گواهی صلاحیت',$referenceTotals['certificate_fee']??0],
            ['بیمه ۷٫۸٪',$referenceTotals['insurance_fee']??0],
            ['بهای خدمات اولیا',$referenceTotals['parent_total']??0],
            ['صورت‌وضعیت بدون بیمه و گواهی',$referenceTotals['statement_excluding_certificate_insurance']??0]
        ];
        $sheets=[
            ['راهنما',['عنوان','توضیح'],[['دوره',$label],['شرکت',$agency],['وضعیت','نهایی'],['مبنای داده','تصویر ثابت از تخصیص‌های فعال در زمان تأیید'],['ارقام','ریال']]],
            ['اطلاعات پایه1',['عنوان','مقدار'],[['نام شرکت',$agency],['سال مالی',$d['year']],['ماه',$d['month_name']],['شروع دوره',$d['from']],['پایان دوره',$d['to']],['سال تحصیلی',$d['academic_year']]]],
            ['tbl_Monthly_Archive',['سال مالی','ماه','ردیف','نام دانش‌آموز','کد ملی','مقطع','نام ولی','تلفن','شبا ولی','مبدا','مدرسه','ناحیه','راننده','کد ملی راننده','شهریه','کسورات','خالص'],$archiveRows],
            ['دانش آموزان6',['ردیف','نام دانش‌آموز','کد ملی','مقطع','نام ولی','تلفن','مبدا','مدرسه','ناحیه','فاصله','راننده','سرویس','شهریه ماهانه'],$studentRows],
            ['رانندگان 7',['ردیف','نام راننده','کد ملی','تلفن','نوع خودرو','پلاک','شبا','بانک','شماره حساب'],$driverRows],
            ['بهای خدمات اولیا5',['ردیف','دانش‌آموز','کد ملی','مدرسه','ناحیه','راننده','نوع خودرو','فاصله','حق‌الزحمه مبنا','روز خدمت','حق‌الزحمه ماه','نظارت ۳٪','شهرداری ۲٫۷۵٪','ارزش افزوده شهرداری','راهور ۰٫۲۵٪','هماهنگی ۱٪','بالاسری شرکت ۷٪','گواهی صلاحیت','جمع سهم‌ها','بیمه ۷٫۸٪','بهای خدمات اولیا','صورت‌وضعیت بدون گواهی و بیمه','شماره شبا'],$parentRows],
            ['صورت وضعیت نهایی8',['ردیف','راننده','شبا','حق‌الزحمه ماه','نظارت ۳٪','هماهنگی ۱٪','سهم شرکت ۷٪','جمع صورت‌وضعیت بدون گواهی و بیمه'],$finalRows],
            ['Peyvastha',['ماه','نام پیوست','مسیر پیوست'],[[$label,'','']]],
            ['1',['نوع کسور','درصد'],array_slice($rateRows,0,2)],
            ['2',['نوع کسور','درصد'],array_slice($rateRows,2,3)],
            ['3',['عنوان','مبلغ ریال'],$summaryRows],
            ['مفاصا',['عنوان','مقدار'],array_merge([['دوره',$label],['شرکت',$agency]],$summaryRows)],
            ['سند مالی10',['شرح سند','بدهکار ریال','بستانکار ریال'],[['حق‌الزحمه رانندگان',$referenceTotals['driver_fee']??0,0],['نظارت ۳٪',$referenceTotals['oversight_3pct']??0,0],['هماهنگی ۱٪',$referenceTotals['education_1pct']??0,0],['سهم شرکت ۷٪',$referenceTotals['company_7pct']??0,0],['جمع صورت‌وضعیت',0,$referenceTotals['statement_excluding_certificate_insurance']??0]]],
            ['روکش محاسبات 11',['عنوان','مبلغ ریال'],$summaryRows]
        ];
        $tmp=tempnam(sys_get_temp_dir(),'sy-statement-');
        if($tmp===false)throw new \RuntimeException('Cannot create temporary statement file');
        try{
            $zip=new \ZipArchive();
            if($zip->open($tmp,\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Cannot open statement archive');
            $types='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
            $book='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>';
            $rels='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
            foreach($sheets as $i=>$sheet){
                $n=$i+1;$types.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $book.='<sheet name="'.self::escape($sheet[0]).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
                $rels.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
                $zip->addFromString('xl/worksheets/sheet'.$n.'.xml',self::sheet($sheet[1],$sheet[2],$title.' | '.$sheet[0]));
            }
            $types.='</Types>';$book.='</sheets></workbook>';
            $rels.='<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
            $zip->addFromString('[Content_Types].xml',$types);
            $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml',$book);
            $zip->addFromString('xl/_rels/workbook.xml.rels',$rels);
            $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF185B50"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="3" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellXfs></styleSheet>');
            $zip->close();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="school-statement-'.$d['year'].'-'.str_pad((string)$d['month'],2,'0',STR_PAD_LEFT).'-'.$id.'.xlsx"');
            header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
            readfile($tmp);
        }finally{if(is_file($tmp))@unlink($tmp);}
        exit;
    }
}
