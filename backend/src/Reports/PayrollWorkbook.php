<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ServiceYar\Auth\Authorization;
use ServiceYar\Auth\AuthException;
use ServiceYar\Domain\PayrollDocuments;
use ZipArchive;

final class PayrollWorkbook
{
    private const SHEET = 'xl/worksheets/sheet1.xml';

    public static function download(int $uid, int $aid, int $id): never
    {
        Authorization::requirePermission($uid, 'drivers.view');
        $payroll = PayrollDocuments::get($uid, $aid, $id, true);
        if ($payroll['status'] !== 'approved') {
            throw new AuthException(422, 'ابتدا سند حقوق را تأیید کنید.', 'PAYROLL_NOT_APPROVED');
        }
        // Preserve the reference template by default. The locally bundled
        // XLSX writer supplies a standards-compliant fallback when the template
        // is missing, or when an explicit standard export is requested.
        if ((!is_file(__DIR__.'/payroll-mehr-layout.xlsx') || (($_GET['workbookFormat']??'')==='standard'))
            && extension_loaded('mbstring') && extension_loaded('zlib')) {
            self::downloadStandardWithLibrary($payroll,$id);
        }
        if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
            throw new AuthException(500, 'امکان ساخت فایل اکسل روی سرور فعال نیست.', 'EXPORT_DEPENDENCY_MISSING');
        }
        $path = tempnam(sys_get_temp_dir(), 'serviceyar-payroll-');
        if ($path === false) {
            throw new \RuntimeException('Cannot allocate payroll workbook');
        }
        try {
            if (!copy(__DIR__.'/payroll-mehr-layout.xlsx', $path)) {
                throw new \RuntimeException('Cannot copy payroll workbook layout');
            }
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Cannot open payroll workbook');
            }
            $last = max(109, count($payroll['lines']) + 4);
            $book = $zip->getFromName('xl/workbook.xml');
            $book = str_replace('$AQ$109', '$AQ$'.$last, $book);
            $sheet = self::worksheet($zip->getFromName(self::SHEET), $payroll);
            if (!$zip->addFromString(self::SHEET, $sheet) ||
                !$zip->addFromString('xl/workbook.xml', $book) || !$zip->close()) {
                throw new \RuntimeException('Cannot write payroll workbook');
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="payroll-'.$id.'.xlsx"');
            header('Cache-Control: no-store');
            readfile($path);
        } finally {
            unlink($path);
        }
        exit;
    }

    /** Data-only fallback; source Mehr template is never overwritten. */
    private static function downloadStandardWithLibrary(array $payroll,int $id): never
    {
        require_once __DIR__.'/../Vendor/SimpleXLSXGen.php';
        $headers=['ردیف','نام','نام خانوادگی','کد ملی','شماره حساب','شبا','کارکرد ناخالص','نظارت و بازرسی ۳٪','اطلاع‌رسانی ۱٪','بالاسری ۷٪','بیمه راننده ۷٫۸٪','جمع کسورات','خالص محاسباتی','حقوق سند'];
        require_once __DIR__.'/ExcelLocalization.php';
        $rows=[array_map(static fn($name)=>'<b><style color="#FFFFFF" bgcolor="#165F3C"><wraptext>'.htmlspecialchars(ExcelLocalization::header($name),ENT_XML1|ENT_QUOTES,'UTF-8').'</wraptext></style></b>',$headers)];
        $totals=array_fill(0,count($headers),0);
        foreach($payroll['lines'] as $i=>$line){
            $row=[$i+1,(string)($line['first_name']??''),(string)($line['last_name']??''),(string)($line['national_code']??''),(string)($line['bank_account']??''),(string)($line['iban']??''),(float)($line['earned']??0),(float)($line['supervision_deduction']??0),(float)($line['coordination_deduction']??0),(float)($line['overhead_deduction']??0),(float)($line['insurance_deduction']??0),(float)($line['total_deductions']??0),(float)($line['net_earned']??0),(float)($line['salary']??0)];
            foreach($row as $col=>$value){
                if($col>=6)$totals[$col]+=$value;
                elseif($col>0)$row[$col]=\Shuchkin\SimpleXLSXGen::raw((string)ExcelLocalization::value($value));
            }
            $rows[]=$row;
        }
        $summary=array_fill(0,count($headers),'');$summary[0]=\Shuchkin\SimpleXLSXGen::raw('جمع کل');
        for($i=6;$i<count($headers);$i++)$summary[$i]=$totals[$i];
        $rows[]=$summary;
        $writer=\Shuchkin\SimpleXLSXGen::fromArray($rows,'حقوق رانندگان')
            ->setDefaultFont('Vazirmatn')->setDefaultFontSize(11)->rightToLeft()
            ->freezePanes('A2')->autoFilter('A1:N'.max(1,count($rows)))->setAuthor('ServiceYar');
        foreach($headers as $i=>$heading)$writer->setColWidth($i+1,($i>=6?22:($i===5?33:23)));
        $tmp=tempnam(sys_get_temp_dir(),'serviceyar-payroll-xlsx-');
        if($tmp===false)throw new \RuntimeException('Cannot allocate payroll XLSX');
        try{
            if(!$writer->saveAs($tmp))throw new \RuntimeException('Cannot create payroll XLSX');
            while(ob_get_level()>0)ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="payroll-standard-'.$id.'.xlsx"');
            header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
            readfile($tmp);
        }finally{@unlink($tmp);}
        exit;
    }

    public static function worksheet(string $template, array $payroll): string
    {
        $sheet = new DOMDocument();
        $sheet->loadXML($template, LIBXML_NONET);
        $xpath = new DOMXPath($sheet);
        $xpath->registerNamespace('s', $sheet->documentElement->namespaceURI);
        $rows = $xpath->query('//s:sheetData/s:row');
        $byNumber = [];
        foreach ($rows as $row) {
            $byNumber[(int)$row->getAttribute('r')] = $row;
        }
        $last = max(108, count($payroll['lines']) + 3);
        $offset = $last - 108;
        $totalRow = $byNumber[109];
        if ($offset > 0) {
            for ($number = 114; $number >= 109; --$number) {
                if (isset($byNumber[$number])) {
                    self::numberRow($byNumber[$number], $number + $offset);
                }
            }
            for ($number = 109; $number <= $last; ++$number) {
                $row = $byNumber[108]->cloneNode(true);
                self::numberRow($row, $number);
                $totalRow->parentNode->insertBefore($row, $totalRow);
                $byNumber[$number] = $row;
            }
            foreach ($xpath->query('//s:mergeCell[@ref="A109:G109"]') as $merge) {
                $total = $last + 1;
                $merge->setAttribute('ref', "A$total:G$total");
            }
            $xpath->query('/s:worksheet/s:dimension')->item(0)->setAttribute('ref', 'A1:AZ'.(114 + $offset));
        }
        // Keep the original Mehr layout intact; append an auditable deduction ledger after column AS.
        $headers=['AT'=>'کارکرد ناخالص (ریال)','AU'=>'مدیریت، نظارت و بازرسی ۳٪','AV'=>'اطلاع‌رسانی و هماهنگی ۱٪','AW'=>'بالاسری شرکت ۷٪','AX'=>'بیمه راننده ۷٫۸٪','AY'=>'جمع کسورات ۱۸٫۸٪','AZ'=>'خالص محاسباتی (ریال)'];
        foreach($headers as $column=>$label)self::put($byNumber[3],$column,$label);
        $xpath->query('/s:worksheet/s:dimension')->item(0)->setAttribute('ref','A1:AZ'.(114+$offset));
        self::put($byNumber[1], 'A', 'لیست حقوق '.$payroll['agency_name'].' | '.$payroll['date_from'].' تا '.$payroll['date_to']);
        $work = 0.0;
        $salary = 0.0;
        foreach ($payroll['lines'] as $index => $line) {
            $number = $index + 4;
            $row = $byNumber[$number];
            foreach ([
                'A' => $index + 1, 'B' => $line['first_name'], 'C' => $line['last_name'],
                'E' => $line['national_code'], 'F' => $line['bank_account'], 'G' => 'راننده سرویس مدارس',
                'P' => (float)$line['earned'], 'AM' => (float)$line['salary'],
            ] as $column => $value) {
                if ($value !== null && $value !== '') {
                    self::put($row, $column, $value);
                }
            }
            foreach(['AT'=>(float)$line['earned'],'AU'=>(float)$line['supervision_deduction'],'AV'=>(float)$line['coordination_deduction'],'AW'=>(float)$line['overhead_deduction'],'AX'=>(float)$line['insurance_deduction'],'AY'=>(float)$line['total_deductions'],'AZ'=>(float)$line['net_earned']] as $column=>$amount){
                self::put($row,$column,$amount);
            }
            $work += (float)$line['earned'];
            $salary += (float)$line['salary'];
        }
        self::put($totalRow, 'P', $work, "SUM(P4:P$last)");
        self::put($totalRow, 'AM', $salary, "SUM(AM4:AM$last)");
        foreach(array_keys($headers) as $column)self::put($totalRow,$column,0,"SUM({$column}4:{$column}$last)");
        return $sheet->saveXML();
    }

    private static function numberRow(DOMElement $row, int $number): void
    {
        $row->setAttribute('r', (string)$number);
        foreach ($row->getElementsByTagName('c') as $cell) {
            $cell->setAttribute('r', rtrim($cell->getAttribute('r'), '0123456789').$number);
        }
    }

    private static function put(DOMElement $row, string $column, string|int|float $value, ?string $formula = null): void
    {
        require_once __DIR__.'/ExcelLocalization.php';
        if($formula===null)$value=ExcelLocalization::value($value,$column);
        $sheet = $row->ownerDocument;
        $ref = $column.$row->getAttribute('r');
        foreach ($row->getElementsByTagName('c') as $cell) {
            if ($cell->getAttribute('r') !== $ref) {
                continue;
            }
            if (is_string($value)) {
                $cell->setAttribute('t', 'inlineStr');
                $inline = $cell->appendChild($sheet->createElementNS($cell->namespaceURI, 'is'));
                $text = $inline->appendChild($sheet->createElementNS($cell->namespaceURI, 't'));
                $text->appendChild($sheet->createTextNode($value));
            } else {
                if ($formula !== null) {
                    $cell->appendChild($sheet->createElementNS($cell->namespaceURI, 'f', $formula));
                }
                $cell->appendChild($sheet->createElementNS($cell->namespaceURI, 'v', (string)$value));
            }
            return;
        }
        // New deduction columns do not exist in the original template; append cells without modifying its formulas.
        $cell=$sheet->createElementNS($row->namespaceURI,'c');
        $cell->setAttribute('r',$ref);
        if(is_string($value)){
            $cell->setAttribute('t','inlineStr');
            $is=$cell->appendChild($sheet->createElementNS($row->namespaceURI,'is'));
            $is->appendChild($sheet->createElementNS($row->namespaceURI,'t'))->appendChild($sheet->createTextNode($value));
        }else{
            if($formula!==null)$cell->appendChild($sheet->createElementNS($row->namespaceURI,'f',$formula));
            $cell->appendChild($sheet->createElementNS($row->namespaceURI,'v',(string)$value));
        }
        $row->appendChild($cell);
        return;
    }
}
