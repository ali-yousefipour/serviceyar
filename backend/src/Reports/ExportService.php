<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class ExportService
{
    private const SCHEMAS = [
        'cheques'=>['table'=>'cheque_receipts','filename'=>'cheque-receipts.xlsx','date_column'=>'due_date','columns'=>['serial_no'=>'شماره چک','bank_name'=>'بانک','branch_name'=>'شعبه','bank_account'=>'شماره حساب','received_from'=>'دریافت از','received_date'=>'تاریخ دریافت میلادی','due_date'=>'سررسید میلادی','amount'=>'مبلغ ریال','status'=>'وضعیت','description'=>'بابت']],
        'schools' => ['table'=>'schools','filename'=>'schools-report.xlsx','date_column'=>'created_at','columns'=>[
            'id'=>'شناسه','name'=>'نام مدرسه','code'=>'کد مدرسه','school_type'=>'نوع مدرسه',
            'manager_name'=>'نام مدیر','phone'=>'تلفن','region'=>'منطقه','is_active'=>'وضعیت فعال','created_at'=>'تاریخ ثبت']],
        'students' => ['table'=>'students','filename'=>'students-report.xlsx','date_column'=>'created_at','columns'=>[
            'id'=>'شناسه','first_name'=>'نام','last_name'=>'نام خانوادگی','national_code'=>'کد ملی',
            'grade'=>'پایه تحصیلی','mobile'=>'شماره همراه','school_id'=>'شناسه مدرسه','academic_year'=>'سال تحصیلی','service_status'=>'وضعیت سرویس','monthly_fee'=>'ماهیانه','is_active'=>'وضعیت فعال','created_at'=>'تاریخ ثبت']],
        'drivers' => ['table'=>'drivers','filename'=>'drivers-report.xlsx','date_column'=>'created_at','columns'=>[
            'id'=>'شناسه','first_name'=>'نام','last_name'=>'نام خانوادگی','national_code'=>'کد ملی',
            'mobile'=>'شماره همراه','status'=>'وضعیت راننده','rating'=>'امتیاز','vehicle_id'=>'شناسه خودرو',
            'is_active'=>'وضعیت فعال','created_at'=>'تاریخ ثبت']],
        'services' => ['table'=>'services','filename'=>'services-report.xlsx','date_column'=>'created_at','columns'=>[
            'id'=>'شناسه','title'=>'عنوان سرویس','code'=>'کد سرویس','school_id'=>'شناسه مدرسه',
            'driver_id'=>'شناسه راننده','capacity'=>'ظرفیت','status'=>'وضعیت سرویس','is_active'=>'وضعیت فعال','created_at'=>'تاریخ ثبت']],
        'invoices' => ['table'=>'invoices','filename'=>'invoices-report.xlsx','date_column'=>'issue_date','columns'=>[
            'id'=>'شناسه','title'=>'عنوان فاکتور','party_type'=>'نوع طرف حساب','party_id'=>'شناسه طرف حساب',
            'amount'=>'مبلغ','status'=>'وضعیت','issue_date'=>'تاریخ صدور','due_date'=>'سررسید','created_at'=>'تاریخ ثبت']],
        'payments' => ['table'=>'payments','filename'=>'payments-report.xlsx','date_column'=>'payment_date','columns'=>[
            'id'=>'شناسه','invoice_id'=>'شناسه فاکتور','amount'=>'مبلغ پرداخت','payment_date'=>'تاریخ پرداخت',
            'method'=>'روش پرداخت','reference_no'=>'شماره پیگیری','created_at'=>'تاریخ ثبت']],
        'ledger' => ['table'=>'ledger_entries','filename'=>'ledger-report.xlsx','date_column'=>'entry_date','columns'=>['id'=>'شناسه','party_type'=>'طرف حساب','party_id'=>'شناسه طرف حساب','direction'=>'نوع عملیات','amount'=>'مبلغ','entry_date'=>'تاریخ','reference_no'=>'شماره پیگیری','is_active'=>'فعال']],
        'driver-payments' => ['table'=>'driver_payments','filename'=>'driver-payments-report.xlsx','date_column'=>'payment_date','columns'=>['id'=>'شناسه','driver_id'=>'راننده','payment_date'=>'تاریخ پرداخت','amount'=>'مبلغ','reference_no'=>'شماره پیگیری','note'=>'توضیحات']],
        'driver-work' => ['table'=>'driver_work','filename'=>'driver-work-report.xlsx','date_column'=>'work_date','columns'=>[
            'id'=>'شناسه','driver_id'=>'شناسه راننده','work_date'=>'تاریخ کارکرد','service_id'=>'شناسه سرویس',
            'amount'=>'مبلغ کارکرد','status'=>'وضعیت','note'=>'توضیحات','created_at'=>'تاریخ ثبت']],
    ];

    public static function xlsx(int $uid, int $aid, string $resource): never
    {
        Authorization::requirePermission($uid, 'reports.view');
        Authorization::requireAgencyAccess($uid, $aid);
        if (!isset(self::SCHEMAS[$resource])) self::jsonError(422, 'VALIDATION_ERROR', 'گزارش انتخاب‌شده قابل خروجی نیست.');
        $permission=['schools'=>'schools','students'=>'students','drivers'=>'drivers','services'=>'services'][$resource]??'finance';
        Authorization::requirePermission($uid,$permission.'.view');
        if (!class_exists(\ZipArchive::class)) self::jsonError(500, 'EXPORT_DEPENDENCY_MISSING', 'امکان ساخت فایل Excel روی سرور فعال نیست.');

        $schema = self::SCHEMAS[$resource];
        $table = $schema['table'];
        $columns = $schema['columns'];
        $columnNames = array_keys($columns);
        $where = ['agency_id = :agency'];
        $params = ['agency' => $aid];
        $filters = [
            'dateFrom' => ['column' => $schema['date_column'] ?? 'created_at', 'operator' => '>=', 'suffix' => ' 00:00:00'],
            'dateTo' => ['column' => $schema['date_column'] ?? 'created_at', 'operator' => '<=', 'suffix' => ' 23:59:59'],
        ];
        foreach ($filters as $key => $filter) {
            $value = trim((string)($_GET[$key] ?? ''));
            $column = $filter['column'];
            if ($value !== '' && in_array($column, $columnNames, true) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
                $param = $key === 'dateFrom' ? 'date_from' : 'date_to';
                $where[] = $column . ' ' . $filter['operator'] . ' :' . $param;
                $params[$param] = $value . $filter['suffix'];
            }
        }
        $search = trim((string)($_GET['search'] ?? ''));
        if ($search !== '') {
            $parts = [];
            $escapedSearch = '%' . str_replace(['\\\\', '%', '_'], ['\\\\\\\\', '\\%', '\\_'], $search) . '%';
            foreach ($columnNames as $index => $column) {
                $param = 'search_' . $index;
                $parts[] = 'CAST(' . $column . ' AS CHAR) LIKE :' . $param;
                $params[$param] = $escapedSearch;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach (['schoolId'=>'school_id', 'driverId'=>'driver_id', 'serviceId'=>'service_id'] as $key => $column) {
            $value = trim((string)($_GET[$key] ?? ''));
            if ($value !== '' && in_array($column, $columnNames, true) && ctype_digit($value) && (int)$value > 0) {
                $param = lcfirst($key);
                $where[] = $column . ' = :' . $param;
                $params[$param] = (int)$value;
            }
        }
        if (in_array('status', $columnNames, true) && trim((string)($_GET['status'] ?? '')) !== '') {
            $where[] = 'status = :status';
            $params['status'] = trim((string)$_GET['status']);
        }
        foreach(['serviceStatus'=>'service_status','academicYear'=>'academic_year','mobile'=>'mobile'] as $key=>$column){
            if(in_array($column,$columnNames,true)&&isset($_GET[$key])&&$_GET[$key]!==''){$where[]=$column.' = :extra_'.$column;$params['extra_'.$column]=(string)$_GET[$key];}
        }
        if (in_array('is_active', $columnNames, true) && in_array((string)($_GET['active'] ?? ''), ['0','1'], true)) {
            $where[] = 'is_active = :active';
            $params['active'] = (int)$_GET['active'];
        }
        if (in_array('amount', $columnNames, true)) {
            foreach (['amountMin'=>'amount_min', 'amountMax'=>'amount_max'] as $key => $param) {
                $value = trim((string)($_GET[$key] ?? ''));
                if ($value !== '' && is_numeric($value) && (float)$value >= 0) {
                    $where[] = 'amount ' . ($key === 'amountMin' ? '>=' : '<=') . ' :' . $param;
                    $params[$param] = $value;
                }
            }
        }
        $sql = 'SELECT ' . implode(', ', $columnNames) . ' FROM ' . $table
            . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 5000';
        $statement = Connection::get()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        self::downloadRows($columns,$rows,$schema['filename']);
    }

    public static function accountStatement(int $uid,int $aid,array $filter): never
    {
        Authorization::requirePermission($uid,'reports.view');
        $d=\ServiceYar\Domain\AccountStatement::report($uid,$aid,$filter,true);
        $rows=[['description'=>'مانده ابتدای بازه','balance'=>$d['opening']]];
        foreach($d['items'] as $row)$rows[]=$row;
        $rows[]=['description'=>'جمع بازه / مانده پایان','debit'=>$d['debit'],'credit'=>$d['credit'],'balance'=>$d['closing']];
        self::downloadRows(['document_date'=>'تاریخ میلادی','document_no'=>'شماره سند','row_no'=>'ردیف','description'=>'شرح','tracking_no'=>'پیگیری','debit'=>'بدهکار · ریال','credit'=>'بستانکار · ریال','balance'=>'مانده بدهکار · ریال'],$rows,'account-statement.xlsx');
    }

    private static function downloadRows(array $columns,array $rows,string $filename): never
    {
        if (!class_exists(\ZipArchive::class)) self::jsonError(500,'EXPORT_DEPENDENCY_MISSING','امکان ساخت فایل Excel روی سرور فعال نیست.');
        $columnNames=array_keys($columns);
        $xml = static function (mixed $value): string {
            $text = (string)($value ?? '');
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';
            return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };
        $columnLetter = static function (int $number): string {
            $label = '';
            while ($number > 0) {
                $number--;
                $label = chr(65 + ($number % 26)) . $label;
                $number = intdiv($number, 26);
            }
            return $label;
        };
        $cell = static function (string $reference, mixed $value) use ($xml): string {
            return '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . $xml($value) . '</t></is></c>';
        };

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/><sheetData><row r="1">';
        $index = 1;
        foreach ($columns as $label) $sheet .= $cell($columnLetter($index++) . '1', $label);
        $sheet .= '</row>';

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet .= '<row r="' . $rowNumber . '">';
            $index = 1;
            foreach ($columnNames as $column) $sheet .= $cell($columnLetter($index++) . $rowNumber, $row[$column] ?? '');
            $sheet .= '</row>';
            $rowNumber++;
        }
        $sheet .= '</sheetData><autoFilter ref="A1:' . $columnLetter(count($columns)) . max(1, $rowNumber - 1) . '"/></worksheet>';

        $tmp = tempnam(sys_get_temp_dir(), 'serviceyar-xlsx-');
        if ($tmp === false) self::jsonError(500, 'EXPORT_TEMP_FAILED', 'ساخت فایل گزارش با خطا مواجه شد.');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            self::jsonError(500, 'EXPORT_ARCHIVE_FAILED', 'ساخت فایل Excel با خطا مواجه شد.');
        }
        $parts = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="گزارش" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];
        foreach ($parts as $name => $part) {
            if (!$zip->addFromString($name, $part)) {
                $zip->close(); @unlink($tmp);
                self::jsonError(500, 'EXPORT_WRITE_FAILED', 'ذخیره فایل Excel با خطا مواجه شد.');
            }
        }
        if (!$zip->close()) {
            @unlink($tmp);
            self::jsonError(500, 'EXPORT_WRITE_FAILED', 'ذخیره فایل Excel با خطا مواجه شد.');
        }
        $data = file_get_contents($tmp);
        @unlink($tmp);
        if ($data === false || $data === '') self::jsonError(500, 'EXPORT_READ_FAILED', 'دریافت فایل گزارش با خطا مواجه شد.');

        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo $data;
        exit;
    }

    private static function jsonError(int $status, string $code, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>['code'=>$code,'message'=>$message]], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
