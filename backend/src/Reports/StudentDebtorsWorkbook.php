<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use ServiceYar\Auth\Authorization;
use ServiceYar\Domain\StudentDebtors;
use Shuchkin\SimpleXLSXGen;

final class StudentDebtorsWorkbook
{
    public static function download(int $uid,int $aid,array $filter,bool $full): never
    {
        Authorization::requirePermission($uid,'reports.view');
        $report=StudentDebtors::report($uid,$aid,$full?array_intersect_key($filter,['academicYear'=>true]):$filter);
        header('Cache-Control: private, no-store');
        self::build($report,$full)->downloadAs($full?'school-debtors-all.xlsx':'school-debtors-filtered.xlsx');
        exit;
    }

    public static function build(array $report,bool $full): SimpleXLSXGen
    {
        require_once __DIR__.'/../Vendor/SimpleXLSXGen.php';
        $book=(new SimpleXLSXGen())->setDefaultFont('Vazirmatn')->setDefaultFontSize(10)->rightToLeft();
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
            $head=['نام و نام خانوادگی دانش‌آموز','شماره تماس ولی','مسافت','مبلغ ماهیانه سرویس (ریال)','مبلغ سالیانه سرویس (ریال)','مبلغ پرداخت‌شده (ریال)','بدهی باقی‌مانده (ریال)'];
            if (!$full) array_splice($head,2,0,['مدرسه']);
            $moneyHead=array_slice($head,-4);
            $rows=[
                ['<center><b><style font-size="14" height="27" bgcolor="#005B35" color="#FFFFFF">'.$label($title).'</style></b></center>'],
                ['<center><style height="17" color="#005B35">'.$label('تاریخ گزارش‌گیری '.$date.' · سال تحصیلی '.$report['academicYear']).'</style></center>'],
                ['<center><b><style height="19" color="#005B35">'.$label(($report['agencyName']??'شرکت').' - سامانه سرویس یار').'</style></b></center>'],
                array_merge(array_map(static fn($v)=>'<center><wraptext><b><style height="31" bgcolor="#087447" color="#FFFFFF"'.$border.'>'.$v.'</style></b></wraptext></center>',$head),$moneyHead),
            ];
            $total=0;
            foreach ($group['items'] as $item) {
                $row=[$item['student'],(string)$item['guardian_mobile']];
                if (!$full) $row[]=$item['school'];
                $distance=$item['tariff_distance_km']??null;
                $row[]=$distance===null?'—':(string)(int)round((float)$distance);
                $values=array_map(static fn($field)=>(string)$item[$field],['monthly_fee','annual_fee','paid','debt']);
                foreach ($values as $value) $row[]=self::persianMoney($value);
                $background=count($rows)%2===0?'#FFFFFF':'#F3F8F4';
                $style='<style bgcolor="'.$background.'"'.$border.'>';
                $row=array_map(static fn(string $value,int $index): string =>
                    ($index===0?'<right>':'<center>').$style.'<raw>'.$label(str_replace(["\r","\n"],' ',$value))
                    .'</raw></style>'.($index===0?'</right>':'</center>'),$row,array_keys($row));
                $total+=(int)round((float)$item['debt']*100);
                $rows[]=array_merge($row,$values);
            }
            $last=count($rows);
            $end=chr(64+count($head));
            $number=number_format($total/100,2,'.','');
            $footerStyle='<b><style bgcolor="#E8F5ED" color="#005B35"'.$border.'>';
            $footer=array_fill(0,count($head),$footerStyle.'</style></b>');
            $footer[0]=$footerStyle.'مجموع بدهی</style></b>';
            $debtColumn=chr(ord($end)+4);
            $formula=$last>4?"SUM({$debtColumn}5:{$debtColumn}{$last})":'0';
            $footer[count($head)-1]='<center>'.$footerStyle.self::persianMoney($number).'</style></b></center>';
            array_push($footer,'','','','<f v="'.$number.'">'.$formula.'</f>');
            $rows[]=$footer;
            $book->addSheet($rows,$name)->mergeCells("A1:{$end}1")->mergeCells("A2:{$end}2")->mergeCells("A3:{$end}3")
                ->freezePanes('A5')->a4Landscape($last<=28);
            if ($last>4) $book->autoFilter("A4:$end$last");
            $widths=$full?[30,17,9,21,21,21,21]:[26,16,20,9,19,19,19,20];
            foreach ($widths as $i=>$width) $book->setColWidth($i+1,$width);
            for ($i=1;$i<=count($moneyHead);$i++) $book->hideColumn(chr(ord($end)+$i));
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
