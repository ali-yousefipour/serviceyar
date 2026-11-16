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
        $book=(new SimpleXLSXGen())->setDefaultFont('Vazirmatn')->setDefaultFontSize(11)->rightToLeft();
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
            $money=static fn(string $v): string => '<style nf="[$-0429]#,##0.##">'.$v.'</style>';
            $head=['نام و نام خانوادگی دانش‌آموز','شماره تماس ولی','مبلغ ماهیانه سرویس (ریال)','مبلغ سالیانه سرویس (ریال)','مبلغ پرداخت‌شده (ریال)','بدهی باقی‌مانده (ریال)'];
            if (!$full) array_splice($head,2,0,['مدرسه']);
            $rows=[
                ['<b><style font-size="16" bgcolor="#005B35" color="#FFFFFF">'.$label($title).'</style></b>'],
                ['<style font-size="10" color="#005B35">'.$label('تاریخ گزارش‌گیری '.$date.' · سال تحصیلی '.$report['academicYear']).'</style>'],
                array_map(static fn($v)=>'<b><style bgcolor="#087447" color="#FFFFFF">'.$v.'</style></b>',$head),
            ];
            $total=0;
            foreach ($group['items'] as $item) {
                $row=[SimpleXLSXGen::raw(ExcelLocalization::digits($item['student'])),SimpleXLSXGen::raw(ExcelLocalization::digits((string)$item['guardian_mobile']))];
                if (!$full) $row[]=SimpleXLSXGen::raw(ExcelLocalization::digits($item['school']));
                foreach (['monthly_fee','annual_fee','paid','debt'] as $field) $row[]=$money((string)$item[$field]);
                $total+=(int)round((float)$item['debt']*100);
                $rows[]=$row;
            }
            $last=count($rows);
            $end=chr(64+count($head));
            $footer=array_fill(0,count($head),'');
            $footer[0]='<b><style bgcolor="#E8F5ED" color="#005B35">مجموع بدهی</style></b>';
            $formula=$last>3?"SUM({$end}4:{$end}{$last})":'0';
            $footer[count($head)-1]='<b><style bgcolor="#E8F5ED" color="#005B35" nf="[$-0429]#,##0.##"><f v="'.number_format($total/100,2,'.','').'">'.$formula.'</f></style></b>';
            $rows[]=$footer;
            $book->addSheet($rows,$name)->mergeCells("A1:{$end}1")->mergeCells("A2:{$end}2")->freezePanes('A4');
            if ($last>3) $book->autoFilter("A3:$end$last");
            foreach ($head as $i=>$heading) $book->setColWidth($i+1,$i===0?38:30);
        }
        return $book;
    }
}
