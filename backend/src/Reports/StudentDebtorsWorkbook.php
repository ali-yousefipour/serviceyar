<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use ServiceYar\Auth\Authorization;
use ServiceYar\Domain\StudentDebtors;
use Shuchkin\SimpleXLSXGen;

final class StudentDebtorsWorkbook
{
    private const COLUMNS = [
        'student'=>['نام و نام خانوادگی دانش‌آموز',25],
        'school'=>['مدرسه',20],
        'service'=>['سرویس / راننده',24],
        'basis'=>['مبنای بدهی',18],
        'guardian_mobile'=>['شماره تماس ولی',17],
        'tariff_distance_km'=>['مسافت (کیلومتر)',12],
        'monthly_fee'=>['مبلغ ماهیانه سرویس (ریال)',19],
        'annual_fee'=>['مبلغ سالانه سرویس (غیرمبنای مانده)',19],
        'paid'=>['پرداخت‌شده (ریال)',19],
        'debt'=>['بدهی باقی‌مانده (ریال)',19],
        'national_code'=>['کد ملی دانش‌آموز',16],
        'father_name'=>['نام پدر',16],
        'education_levels'=>['مقطع تحصیلی مدرسه',20],
        'grade'=>['پایه تحصیلی',15],
        'region'=>['ناحیه مدرسه',15],
        'address'=>['نشانی منزل',32],
        'guardian_name'=>['نام ولی',24],
        'guardian_national_code'=>['کد ملی ولی',16],
        'plate'=>['شماره پلاک خودرو',20],
        'contract_start'=>['تاریخ شروع قرارداد',18],
        'contract_end'=>['تاریخ پایان قرارداد',18],
        'contract_no'=>['شماره قرارداد',20],
        'guardian_relation'=>['نسبت ولی',15],
        'emergency_phone'=>['شماره اضطراری',20],
        'return_address'=>['نشانی برگشت',32],
        'gender'=>['جنسیت',12],
        'nationality'=>['ملیت',14],
        'shift'=>['شیفت',14],
        'academic_year'=>['سال تحصیلی',15],
        'service_status'=>['وضعیت سرویس',18],
        'tariff_vehicle_class'=>['کلاس خودرو',14],
        'contract_amount'=>['مبلغ قطعی قرارداد (ریال)',20],
        'annual_months'=>['مدت استاندارد سال تحصیلی',20],
        'credit'=>['بستانکاری (ریال)',20],
        'reconciliation_status'=>['وضعیت تطبیق مالی',32],
        'invoice_total'=>['جمع فاکتورهای معتبر (ریال)',19],
    ];
    private const MONEY = ['monthly_fee','annual_fee','paid','debt','invoice_total','contract_amount','credit'];
    public static function columns(?string $raw=null): array
    {
        $keys=$raw===null?[]:explode(',',$raw);
        $keys=array_values(array_unique(array_filter($keys,static fn($k)=>isset(self::COLUMNS[$k]))));
        return $keys?:['student','school','service','basis','guardian_mobile','tariff_distance_km','monthly_fee','invoice_total','paid','debt'];
    }
    private static function value(array $row,string $key): string
    {
        if ($key==='service') return implode(' / ',array_filter([$row['service_names']??'', $row['driver_names']??'']))?:'—';
        if ($key==='basis') return match($row['balance_source']??''){ 'invoiced'=>'فاکتور ثبت‌شده', 'refund_balance'=>'مانده ناشی از خالص پرداخت / استرداد', default=>'نیازمند بررسی' };
        $v=$row[$key]??null;
        if ($v===null||$v==='') return '—';
        if (in_array($key,self::MONEY,true)) return self::persianMoney((string)$v);
        if ($key==='tariff_distance_km') return (string)(int)round((float)$v);
        if (in_array($key,['contract_start','contract_end'],true)) return ExcelLocalization::jalali((string)$v);
        return (string)$v;
    }
    private static function col(int $n): string
    {
        $s=''; while($n>0){$n--; $s=chr(65+$n%26).$s; $n=intdiv($n,26);} return $s;
    }

    public static function download(int $uid,int $aid,array $filter,bool $full): never
    {
        Authorization::requirePermission($uid,'reports.view');
        $report=StudentDebtors::report($uid,$aid,$full?array_intersect_key($filter,['academicYear'=>true]):$filter);
        header('Cache-Control: private, no-store');
        self::build($report,$full,self::columns(is_string($filter['columns']??null)?$filter['columns']:null))->downloadAs($full?'school-debtors-all.xlsx':'school-debtors-filtered.xlsx');
        exit;
    }

    public static function build(array $report,bool $full,?array $columns=null): SimpleXLSXGen
    {
        require_once __DIR__.'/../Vendor/SimpleXLSXGen.php';
        $book=(new SimpleXLSXGen())->setDefaultFont('Vazirmatn')->setDefaultFontSize(10)->rightToLeft();
        $columns=self::columns($columns===null?null:implode(',',$columns));
        $groups=[];
        if ($full) {
            foreach ($report['items'] as $row) {
                $id=$row['school_id']??0;
                $groups[$id]['school']=$row['school'];
                $groups[$id]['items'][]=$row;
            }
        } else {
            $schools=array_unique(array_column($report['items'],'school_id'));
            $groups[]=['school'=>count($schools)===1?$report['items'][0]['school']:'نتایج فیلترشده','items'=>$report['items']];
        }
        if (!$groups) $groups[]=['school'=>'بدون بدهکار','items'=>[]];
        $date=ExcelLocalization::jalali((new \DateTimeImmutable('now',new \DateTimeZone('Asia/Tehran')))->format('Y-m-d'));
        $used=[];
        foreach ($groups as $group) {
            $school=$group['school'];
            $base=trim(str_replace(['[',']',':','*','?','/','\\'],' ',$school)," '\t\r\n")?:'مدرسه';
            $name=mb_substr($base,0,31);
            for ($n=2;isset($used[mb_strtolower($name)]);$n++) {
                $suffix=' ('.$n.')';
                $name=mb_substr($base,0,31-mb_strlen($suffix)).$suffix;
            }
            $used[mb_strtolower($name)]=true;
            $title=$full || count($schools??[])===1?'گزارش مالی بدهکاران مدرسه '.$school:'گزارش مالی بدهکاران · '.$school;
            $label=static fn(string $v): string => htmlspecialchars(ExcelLocalization::digits($v),ENT_XML1|ENT_QUOTES,'UTF-8');
            $border=' border="thin#CDDED2"';
            $head=array_map(static fn($key)=>self::COLUMNS[$key][0],$columns);
            $moneyKeys=array_values(array_intersect($columns,self::MONEY));
            $moneyHead=array_map(static fn($key)=>self::COLUMNS[$key][0],$moneyKeys);
            $rows=[
                ['<center><b><style font-size="14" height="27" bgcolor="#005B35" color="#FFFFFF">'.$label($title).'</style></b></center>'],
                ['<center><style height="17" color="#005B35">'.$label('تاریخ گزارش‌گیری '.$date.' · سال تحصیلی '.$report['academicYear']).'</style></center>'],
                ['<center><b><style height="19" color="#005B35">'.$label(($report['agencyName']??'شرکت').' - سامانه سرویس یار').'</style></b></center>'],
                array_merge(array_map(static fn($v)=>'<center><wraptext><b><style height="31" bgcolor="#087447" color="#FFFFFF"'.$border.'>'.$v.'</style></b></wraptext></center>',$head),$moneyHead),
            ];
            $total=0;
            foreach ($group['items'] as $item) {
                $row=array_map(static fn($key)=>self::value($item,$key),$columns);
                $values=array_map(static fn($key)=>(string)($item[$key]??0),$moneyKeys);
                $background=count($rows)%2===0?'#FFFFFF':'#F3F8F4';
                $style='<style bgcolor="'.$background.'"'.$border.'>';
                $row=array_map(static fn(string $value,int $index): string =>
                    ($index===0?'<right>':'<center>').$style.'<raw>'.$label(str_replace(["\r","\n"],' ',$value))
                    .'</raw></style>'.($index===0?'</right>':'</center>'),$row,array_keys($row));
                $total+=(int)round((float)$item['debt']*100);
                $rows[]=array_merge($row,$values);
            }
            $last=count($rows);
            $end=self::col(count($head));
            $number=number_format($total/100,2,'.','');
            $footerStyle='<b><style bgcolor="#E8F5ED" color="#005B35"'.$border.'>';
            $footer=array_fill(0,count($head)+count($moneyKeys),$footerStyle.'</style></b>');
            $debtIndex=array_search('debt',$columns,true);
            if ($debtIndex!==false) {
                if ($debtIndex!==0) $footer[0]=$footerStyle.'مجموع بدهی</style></b>';
                $footer[$debtIndex]=$footerStyle.($debtIndex===0?'مجموع: ':'').self::persianMoney($number).'</style></b>';
                $rawIndex=count($head)+array_search('debt',$moneyKeys,true);
                $debtColumn=self::col($rawIndex+1);
                $formula=$last>4?"SUM({$debtColumn}5:{$debtColumn}{$last})":'0';
                $footer[$rawIndex]='<f v="'.$number.'">'.$formula.'</f>';
            }
            $rows[]=$footer;
            $book->addSheet($rows,$name);
            if(count($head)>1) $book->mergeCells("A1:{$end}1")->mergeCells("A2:{$end}2")->mergeCells("A3:{$end}3");
            $book->freezePanes('A5')->a4Landscape($last<=28);
            if ($last>4) $book->autoFilter("A4:$end$last");
            foreach ($columns as $i=>$key) $book->setColWidth($i+1,self::COLUMNS[$key][1]);
            for ($i=1;$i<=count($moneyHead);$i++) $book->hideColumn(self::col(count($head)+$i));
        }
        return $book;
    }

    private static function persianMoney(string $value): string
    {
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
        $whole=preg_replace('/(?<=\d)(?=(?:\d{3})+$)/','٬',$whole);
        $fraction=rtrim($fraction,'0');
        return ExcelLocalization::digits($whole.($fraction===''?'':'٫'.$fraction));
    }
}
