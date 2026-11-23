<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use ServiceYar\Auth\AuthException;
use ServiceYar\Domain\OperationsService;

/**
 * Read-only payroll validation export. Always recomputes conflicts in the
 * authenticated agency scope rather than accepting client-supplied rows.
 */
final class PayrollConflictsWorkbook
{
    private static function cell(mixed $value): string
    {
        $text=(string)ExcelLocalization::value($value);
        // Prevent user-controlled text from being interpreted as a formula.
        return preg_match('/^[=+\-@]/u',$text)?"'".$text:$text;
    }

    public static function download(int $uid,int $agencyId,array $filter): never
    {
        $conflicts=[];
        try {
            OperationsService::payroll($uid,$agencyId,$filter);
        } catch(AuthException $e) {
            if($e->codeName!=='DRIVER_BASE_MONTHLY_REQUIRED')throw $e;
            $conflicts=$e->context['conflicts']??[];
        }
        require_once __DIR__.'/../Vendor/SimpleXLSXGen.php';
        $headers=['ردیف','دانش‌آموز','شناسه دانش‌آموز','راننده','شناسه راننده','شرکت','سرویس','شناسه سرویس','خودرو','کلاس خودرو','تاریخ مؤثر','مبنای ثبت‌شده','علت مغایرت','اقدام پیشنهادی'];
        $rows=[$headers];
        foreach($conflicts as $i=>$c) {
            $rows[]=[
                $i+1,self::cell($c['studentName']??''),self::cell($c['studentId']??''),
                self::cell($c['driverName']??''),self::cell($c['driverId']??''),
                self::cell((string)$agencyId),self::cell($c['serviceName']??''),
                self::cell($c['serviceId']??''),self::cell($c['vehicleName']??''),
                self::cell($c['vehicleClass']??''),self::cell($c['effectiveDate']??''),
                self::cell($c['recordedBasis']??'ثبت نشده'),
                self::cell($c['reason']??''),self::cell($c['suggestedAction']??'')
            ];
        }
        if(!$conflicts)$rows[]=['هیچ مغایرت مسدودکننده‌ای در این دوره یافت نشد.'];
        $book=\Shuchkin\SimpleXLSXGen::fromArray($rows,'مغایرت‌های حقوق')
            ->setDefaultFont('Vazirmatn')->setDefaultFontSize(11)->rightToLeft()
            ->freezePanes('A2')->autoFilter('A1:N'.max(1,count($rows)))->setAuthor('ServiceYar');
        foreach($headers as $i=>$value)$book->setColWidth($i+1,in_array($i,[1,3,6,8,12,13],true)?36:20);
        $tmp=tempnam(sys_get_temp_dir(),'serviceyar-payroll-conflicts-');
        if($tmp===false)throw new \RuntimeException('Cannot allocate payroll conflicts XLSX');
        try {
            if(!$book->saveAs($tmp))throw new \RuntimeException('Cannot create payroll conflicts XLSX');
            while(ob_get_level()>0)ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="serviceyar-payroll-conflicts.xlsx"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: '.filesize($tmp));
            readfile($tmp);
        } finally {
            @unlink($tmp);
        }
        exit;
    }
}
