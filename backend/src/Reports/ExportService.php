<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;
use ServiceYar\Domain\DomainService;

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
            'driver_id'=>'شناسه راننده','capacity'=>'ظرفیت','shift'=>'شیفت','status'=>'وضعیت سرویس','is_active'=>'وضعیت فعال','created_at'=>'تاریخ ثبت']],
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
        foreach ($resource === "driver-work" ? [] : $filters as $key => $filter) {
            $value = trim((string)($_GET[$key] ?? ''));
            $column = $filter['column'];
            if ($value !== '' && in_array($column, $columnNames, true) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
                $param = $key === 'dateFrom' ? 'date_from' : 'date_to';
                $where[] = $column . ' ' . $filter['operator'] . ' :' . $param;
                $params[$param] = $value . $filter['suffix'];
            }
        }
        if ($resource === 'services') {
            [$serviceWhere, $serviceParams] = DomainService::serviceFilters($_GET,$uid);
            $where = array_merge($where, $serviceWhere);
            $params += $serviceParams;
        }
        if ($resource === 'driver-work') {
            [$workWhere, $workParams] = DomainService::workDateFilters($_GET);
            $where = array_merge($where, $workWhere);
            $params += $workParams;
        }
        $search = $resource === 'services' ? '' : trim((string)($_GET['search'] ?? ''));
        if ($search !== '' && $resource === 'driver-work') {
            $where[] = 'note LIKE :work_note';
            $params['work_note'] = '%' . $search . '%';
        } elseif ($search !== '') {
            $parts = [];
            $escapedSearch = '%' . str_replace(['\\\\', '%', '_'], ['\\\\\\\\', '\\%', '\\_'], $search) . '%';
            foreach ($columnNames as $index => $column) {
                $param = 'search_' . $index;
                $parts[] = 'CAST(' . $column . ' AS CHAR) LIKE :' . $param;
                $params[$param] = $escapedSearch;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach ($resource === 'services' ? [] : ['schoolId'=>'school_id', 'driverId'=>'driver_id', 'serviceId'=>'service_id'] as $key => $column) {
            $value = trim((string)($_GET[$key] ?? ''));
            if ($value !== '' && in_array($column, $columnNames, true) && ctype_digit($value) && (int)$value > 0) {
                $param = lcfirst($key);
                $where[] = $column . ' = :' . $param;
                $params[$param] = (int)$value;
            }
        }
        if ($resource !== 'services' && in_array('status', $columnNames, true) && trim((string)($_GET['status'] ?? '')) !== '') {
            $where[] = 'status = :status';
            $params['status'] = trim((string)$_GET['status']);
        }
        foreach(['serviceStatus'=>'service_status','academicYear'=>'academic_year','mobile'=>'mobile'] as $key=>$column){
            if(in_array($column,$columnNames,true)&&isset($_GET[$key])&&$_GET[$key]!==''){$where[]=$column.' = :extra_'.$column;$params['extra_'.$column]=(string)$_GET[$key];}
        }
        if ($resource !== 'services' && in_array('is_active', $columnNames, true) && in_array((string)($_GET['active'] ?? ''), ['0','1'], true)) {
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

    public static function ledgerReport(int $uid,int $aid,array $filter): never
    {
        $report=ReportService::ledger($uid,$aid,$filter,true);
        $rows=[];
        $type=['student'=>'دانش‌آموز','driver'=>'راننده','school'=>'مدرسه','agency'=>'شرکت'];
        $methods=['cash'=>'نقدی','card'=>'کارت به کارت','bank'=>'پایا','transfer'=>'ساتنا','cheque'=>'چک','wallet'=>'کیف پول'];foreach(\ServiceYar\Domain\AgencyPaymentMethods::forAgency($aid) as $method)$methods[$method['code']]=$method['name'];
        foreach($report['items'] as $i=>$row){
            $rows[]=['n'=>$i+1,'date'=>$row['entry_date'],'direction'=>$row['direction']==='receipt'?'دریافت':'پرداخت','party'=>(string)($row['party_name']??''),'type'=>$type[$row['party_type']]??$row['party_type'],'receipt'=>$row['direction']==='receipt'?(float)$row['amount']:0,'payment'=>$row['direction']==='payment'?(float)$row['amount']:0,'method'=>$methods[$row['method']??'']??(string)($row['method']??''),'company_account'=>(string)($row['company_account_name']??'حساب قدیمی نامشخص'),'reference'=>(string)($row['reference_no']??''),'note'=>(string)($row['note']??''),'status'=>(int)$row['is_active']===1?'فعال':'غیرفعال'];
        }
        $rows[]=['n'=>'جمع کل','receipt'=>$report['totals']['receipts'],'payment'=>$report['totals']['payments'],'note'=>'خالص: '.number_format($report['totals']['balance']).' ریال'];
        self::downloadRows(['n'=>'ردیف','date'=>'تاریخ','direction'=>'نوع عملیات','party'=>'نام دریافت‌کننده / پرداخت‌کننده','type'=>'طرف حساب','receipt'=>'دریافت (ریال)','payment'=>'پرداخت (ریال)','method'=>'روش دریافت / پرداخت','company_account'=>'حساب شرکت (مبدأ / مقصد)','reference'=>'شماره پیگیری','note'=>'توضیحات','status'=>'وضعیت سند'],$rows,'serviceyar-ledger-report.xlsx',true);
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

    public static function schoolWorkbook(int $uid,int $aid,string $period): never {
        Authorization::requirePermission($uid,'reports.view');Authorization::requirePermission($uid,'drivers.view');
        $d=\ServiceYar\Domain\SchoolServiceStatement::importedReport($uid,$aid,$period);
        $schema=json_decode(file_get_contents(__DIR__.'/../Domain/school-statement-template-1405.json'),true,512,JSON_THROW_ON_ERROR);
        $q=Connection::get()->prepare("SELECT CONCAT(first_name,' ',last_name) name,iban FROM drivers WHERE agency_id=? AND is_active=1");$q->execute([$aid]);$banks=[];foreach($q as $driver)$banks[trim($driver['name'])][]=$driver['iban'];
        $group=[];foreach($d['items'] as $r){$name=trim($r['driver_name']);if(!isset($group[$name]))$group[$name]=['name'=>$name,'work'=>0,'management'=>0,'coordination'=>0,'company'=>0,'total'=>0];foreach(['work'=>'driver_fee','management'=>'oversight_fee','coordination'=>'education_fee','company'=>'company_fee','total'=>'statement_total'] as $to=>$from)$group[$name][$to]+=(float)$r[$from];}
        $rows=[];$number=0;foreach($group as $name=>$r){$ibans=array_values(array_unique(array_filter($banks[$name]??[])));if(count($ibans)!==1||!\ServiceYar\Domain\PayrollDocuments::validIban($ibans[0]))throw new \ServiceYar\Auth\AuthException(422,'برای خروجی، نام و شبای یکتای معتبر تمام رانندگان باید تکمیل شود.','BANK_DETAILS_REQUIRED');$rows[]=['n'=>++$number,'name'=>$name,'iban'=>$ibans[0],'work'=>$r['work'],'management'=>$r['management'],'coordination'=>$r['coordination'],'company'=>$r['company'],'total'=>$r['total']];}
        if(!$rows)throw new \ServiceYar\Auth\AuthException(422,'صورت‌وضعیت واردشده برای این دوره موجود نیست.','STATEMENT_EMPTY');
        self::downloadSchoolStatement($rows,$d['items'],$schema,$period,$aid);
    }

    /**
     * The 1405 reference has two separate reports, not a generic flat "گزارش".
     * Keep numeric cells numeric and use formula-driven totals; no personally
     * identifiable data from the source workbook is embedded in the template.
     */
    private static function downloadSchoolStatement(array $drivers,array $students,array $schema,string $period,int $aid): never
    {
        if(!class_exists(\ZipArchive::class))self::jsonError(500,'EXPORT_DEPENDENCY_MISSING','امکان ساخت فایل اکسل فعال نیست.');
        $pdo=Connection::get();
        $agency=$pdo->prepare('SELECT name FROM agencies WHERE id=?');$agency->execute([$aid]);
        $agencyName=(string)$agency->fetchColumn();
        $titles=[
          'drivers'=>'صورت وضعیت '.$period.' شرکت '.$agencyName.' سرویس مدارس ۱۴۰۵-۱۴۰۶ (کلیه ارقام به ریال)',
          'students'=>'بهای خدمات به اولیا: '.$period.' شرکت '.$agencyName.' سرویس مدارس ۱۴۰۵-۱۴۰۶'
        ];
        $studentHeaders=[
          'ردیف','نام و نام خانوادگی دانش‌آموز','کد ملی','نام مدرسه','ناحیه آموزش و پرورش',
          'نام و نام خانوادگی راننده','نوع خودرو سرویس مدرسه','میانگین فاصله مبدأ تا مقصد (کیلومتر)',
          'حق‌الزحمه مبنای راننده بر اساس نرخنامه (ریال)','مدت ارائه خدمات در این ماه (به روز)',
          'حق‌الزحمه راننده برای مدت ارائه خدمات در این ماه',
          'هزینه‌های مدیریت، نظارت و بازرسی (۳٪ بهای خدمات راننده)','سهم شهرداری (۲٫۷۵٪)',
          'ارزش افزوده سهم شهرداری (۱۰٪)','سهم راهور (۰٫۲۵٪)',
          'هزینه‌های اطلاع‌رسانی و هماهنگی (۱٪ بهای خدمات راننده)',
          'بالاسری سهم شرکت (۷٪ بهای خدمات راننده)','گواهی صلاحیت رانندگان',
          'جمع سهم‌ها','هزینه بیمه (۷٫۸٪)','جمع کل بهای خدمات برای اولیا',
          'جمع کل صورت وضعیت (بدون گواهی صلاحیت و بیمه)','شماره شبا'
        ];
        $driverIban=[];foreach($drivers as $r)$driverIban[$r['name']]=$r['iban'];
        $schoolRegions=[];
        $q=$pdo->prepare('SELECT name,region FROM schools WHERE agency_id=?');$q->execute([$aid]);
        foreach($q as $r)$schoolRegions[trim((string)$r['name'])]=(string)$r['region'];
        $studentRows=[];
        foreach($students as $i=>$r){
          $name=trim((string)$r['driver_name']);
          $base=(float)$r['driver_fee'];
          $days=(float)$r['service_days'];
          $rate=$days>0?$base*30/$days:0;
          $studentRows[]=[
            $i+1,(string)$r['student_name'],(string)$r['student_national_code'],
            (string)$r['school_name'],$schoolRegions[trim((string)$r['school_name'])]??'',
            $name,'',(float)$r['distance_km'],$rate,$days,$base,
            (float)$r['oversight_fee'],(float)$r['municipality_fee'],
            (float)$r['municipality_vat'],(float)$r['road_police_fee'],
            (float)$r['education_fee'],(float)$r['company_fee'],
            (float)$r['certificate_fee'],
            (float)$r['oversight_fee']+(float)$r['municipality_fee']+(float)$r['municipality_vat']+(float)$r['road_police_fee']+(float)$r['education_fee']+(float)$r['company_fee'],
            (float)$r['insurance_fee'],(float)$r['parent_total'],(float)$r['statement_total'],
            $driverIban[$name]??''
          ];
        }
        $driverRows=array_map(static fn($r)=>array_values($r),$drivers);
        $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');
        $col=static function(int $n): string {$s='';while($n>0){$n--; $s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;};
        $cell=static function(string $ref,mixed $value,int $style=2,?string $formula=null)use($esc): string{
          if($formula!==null)return '<c r="'.$ref.'" s="'.$style.'"><f>'.$esc($formula).'</f><v>'.$esc($value).'</v></c>';
          if(is_int($value)||is_float($value))return '<c r="'.$ref.'" s="'.$style.'"><v>'.$esc($value).'</v></c>';
          return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$esc($value).'</t></is></c>';
        };
        $build=static function(string $title,array $headers,array $data,int $headerRow,int $totalFrom,int $numericStart)use($cell,$col,$esc): string{
          $lastCol=$col(count($headers));$rowCount=count($data);$end=$headerRow+$rowCount;
          $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
          $xml.='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
          $xml.='<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$lastCol.($end+1).'"/>';
          $xml.='<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow+1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
          $xml.='<sheetFormatPr defaultRowHeight="22"/><cols>';
          foreach($headers as $i=>$label){$n=$i+1;$width=$n===1?9:($n===2?34:($n===3?30:($n<=$numericStart?22:24)));$xml.='<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';}
          $xml.='</cols><sheetData><row r="1" ht="37" customHeight="1">'.$cell('A1',$title,1).'</row>';
          if($headerRow===3)$xml.='<row r="2" ht="22" customHeight="1">'.$cell('A2','صورت وضعیت رانندگان | ارقام به ریال',4).'</row>';
          $xml.='<row r="'.$headerRow.'" ht="78" customHeight="1">';
          foreach($headers as $i=>$label)$xml.=$cell($col($i+1).$headerRow,$label,1);
          $xml.='</row>';
          foreach($data as $i=>$record){
            $r=$headerRow+$i+1;$xml.='<row r="'.$r.'" ht="26" customHeight="1">';
            foreach($record as $j=>$value)$xml.=$cell($col($j+1).$r,$value,($j+1)>=$numericStart?3:2);
            $xml.='</row>';
          }
          $totalRow=$end+1;$xml.='<row r="'.$totalRow.'" ht="32" customHeight="1">'.$cell('A'.$totalRow,'جمع کل',4);
          for($j=$numericStart;$j<=count($headers);$j++){
            if($j===count($headers)&&count($headers)===23)continue;
            $letter=$col($j);$formula='SUM('.$letter.($headerRow+1).':'.$letter.$end.')';
            $xml.=$cell($letter.$totalRow,array_sum(array_map(static fn($r)=>(float)($r[$j-1]??0),$data)),5,$formula);
          }
          $xml.='</row></sheetData><mergeCells count="1"><mergeCell ref="A1:'.$lastCol.'1"/></mergeCells>';
          $xml.='<autoFilter ref="A'.$headerRow.':'.$lastCol.$end.'"/>';
          $xml.='<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>';
          return $xml.'</worksheet>';
        };
        $driverSheet=$build($titles['drivers'],$schema['columns'],$driverRows,3,4,4);
        $studentSheet=$build($titles['students'],$studentHeaders,$studentRows,2,3,8);
        $styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
          .'<fonts count="3"><font><sz val="11"/><name val="Vazirmatn"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Vazirmatn"/></font><font><b/><sz val="11"/><color rgb="FF123A50"/><name val="Vazirmatn"/></font></fonts>'
          .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123A50"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F3F6"/><bgColor indexed="64"/></patternFill></fill></fills>'
          .'<borders count="2"><border/><border><left style="thin"><color rgb="FFD0DCE2"/></left><right style="thin"><color rgb="FFD0DCE2"/></right><top style="thin"><color rgb="FFD0DCE2"/></top><bottom style="thin"><color rgb="FFD0DCE2"/></bottom></border></borders>'
          .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
          .'<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0"/><xf numFmtId="3" fontId="2" fillId="3" borderId="1" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>';
        $parts=[
          '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
          '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
          'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="صورت وضعیت نهایی8" sheetId="1" r:id="rId1"/><sheet name="بهای خدمات اولیا5" sheetId="2" r:id="rId2"/></sheets></workbook>',
          'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
          'xl/styles.xml'=>$styles,'xl/worksheets/sheet1.xml'=>$driverSheet,'xl/worksheets/sheet2.xml'=>$studentSheet
        ];
        $tmp=tempnam(sys_get_temp_dir(),'sy-statement-');
        if($tmp===false)self::jsonError(500,'EXPORT_TEMP_FAILED','ساخت فایل ممکن نیست.');
        $zip=new \ZipArchive();
        try{
          if($zip->open($tmp,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Cannot create statement workbook');
          foreach($parts as $name=>$xml)if(!$zip->addFromString($name,$xml))throw new \RuntimeException('Cannot write '.$name);
          if(!$zip->close())throw new \RuntimeException('Cannot close statement workbook');
          while(ob_get_level()>0)ob_end_clean();
          header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
          header('Content-Disposition: attachment; filename="school-statement-1405.xlsx"');
          header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
          readfile($tmp);
        }finally{@unlink($tmp);}
        exit;
    }

    public static function serviceFinance(int $uid, int $aid, array $filter): never
    {
        Authorization::requirePermission($uid, 'reports.view');
        $report = \ServiceYar\Domain\ServiceFinanceReport::report($uid, $aid, $filter);
        $rows = array_map(static fn(array $row): array => $row + [
            'academic_year'=>$report['academicYear'], 'from_date'=>$report['from'],
            'to_date'=>$report['to'], 'months'=>$report['months'],
        ], $report['items']);
        self::downloadRows([
            'academic_year'=>'سال تحصیلی', 'from_date'=>'صدور از تاریخ میلادی', 'to_date'=>'صدور تا تاریخ میلادی',
            'months'=>'تعداد ماه خدمت', 'school'=>'مدرسه', 'service'=>'سرویس', 'driver'=>'راننده',
            'student'=>'دانش‌آموز', 'assignment_count'=>'تعداد تخصیص فعال',
            'monthly_fee'=>'ماهیانه · ریال', 'estimated_fee'=>'برآورد · ریال',
            'invoiced'=>'فاکتور · ریال', 'received'=>'پرداخت ثبت‌شده · ریال', 'balance'=>'مانده فاکتور · ریال',
        ], $rows, 'service-finance.xlsx');
    }

    /**
     * Use the vendored XLSX writer for ordinary reports. Keep the legacy ZIP
     * writer as a compatibility fallback for hosts without mbstring.
     * Template-based accounting and monthly statement exports stay untouched.
     */
    private static function downloadRowsWithLibrary(array $columns,array $rows,string $filename,bool $styled): never
    {
        require_once __DIR__.'/../Vendor/SimpleXLSXGen.php';
        $names=array_keys($columns);
        $last=count($names);
        $lastColumn=static function(int $n):string {
            $s=''; while($n>0){$n--; $s=chr(65+$n%26).$s; $n=intdiv($n,26);} return $s;
        };
        $safeText=static function(mixed $v):string {
            $v=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',(string)($v??''))??'';
            // Raw values prevent implicit date, formula, URL and number conversion.
            return chr(0).$v;
        };
        $grid=[];
        $grid[]=array_map(static fn($heading)=>'<b><style color="#FFFFFF" bgcolor="#165F3C"><wraptext>'.htmlspecialchars((string)$heading,ENT_XML1|ENT_QUOTES,'UTF-8').'</wraptext></style></b>',array_values($columns));
        foreach($rows as $index=>$row){
            $cells=[];
            foreach($names as $field){
                $value=$row[$field]??'';
                if(is_int($value)||is_float($value)){
                    $cells[]='<style nf="#,##0">'.($value).'</style>';
                }else{
                    // ID, IBAN, tracking number, dates and text stay text.
                    $text=(string)($value??'');
                    $cells[]=$safeText($text);
                }
            }
            $grid[]=$cells;
        }
        $book=\Shuchkin\SimpleXLSXGen::fromArray($grid,'گزارش')
            ->setDefaultFont('Vazirmatn')->setDefaultFontSize(11)
            ->rightToLeft()->freezePanes('A2')
            ->autoFilter('A1:'.$lastColumn($last).max(1,count($grid)))
            ->setAuthor('ServiceYar')->setCompany('ServiceYar');
        foreach(array_values($columns) as $i=>$heading){
            $width=max(15,min(44,(int)ceil(mb_strlen((string)$heading,'UTF-8')*1.3)+6));
            $book->setColWidth($i+1,$width);
        }
        $tmp=tempnam(sys_get_temp_dir(),'serviceyar-xlsx-');
        if($tmp===false)self::jsonError(500,'EXPORT_TEMP_FAILED','ساخت فایل گزارش با خطا مواجه شد.');
        try{
            if(!$book->saveAs($tmp))self::jsonError(500,'EXPORT_WRITE_FAILED','ذخیره فایل Excel با خطا مواجه شد.');
            $data=file_get_contents($tmp);
        }finally{@unlink($tmp);}
        if(!is_string($data)||$data==='')self::jsonError(500,'EXPORT_READ_FAILED','دریافت فایل گزارش با خطا مواجه شد.');
        while(ob_get_level()>0)ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.preg_replace('/[\\r\\n"]/', '',$filename).'"; filename*=UTF-8\'\''.rawurlencode($filename));
        header('Content-Length: '.strlen($data));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo $data;
        exit;
    }

    private static function downloadRows(array $columns,array $rows,string $filename,bool $styled=false): never
    {
        if(extension_loaded('mbstring') && extension_loaded('zlib')){
            self::downloadRowsWithLibrary($columns,$rows,$filename,$styled);
        }
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
        $cell = static function (string $reference, mixed $value,int $style=0) use ($xml): string {
            $styleAttr=$style?' s="'.$style.'"':'';
            if(is_int($value)||is_float($value))return '<c r="'.$reference.'"'.$styleAttr.'><v>'.$xml($value).'</v></c>';
            return '<c r="'.$reference.'"'.$styleAttr.' t="inlineStr"><is><t xml:space="preserve">'.$xml($value).'</t></is></c>';
        };

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/><sheetData><row r="1">';
        $index = 1;
        foreach ($columns as $label) $sheet .= $cell($columnLetter($index++) . '1', $label,$styled?1:0);
        $sheet .= '</row>';

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet .= '<row r="' . $rowNumber . '">';
            $index = 1;
            foreach ($columnNames as $column) $sheet .= $cell($columnLetter($index++) . $rowNumber, $row[$column] ?? '',$styled?($rowNumber===count($rows)+1?3:($rowNumber%2===0?2:4)):0);
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
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Vazirmatn"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Vazirmatn"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123B53"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF5F3"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="3" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1"/><xf numFmtId="3" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/></cellXfs></styleSheet>',
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
