<?php
declare(strict_types=1);
namespace ServiceYar\Reports;

use ServiceYar\Domain\SchoolServiceStatement;

/**
 * Optional faithful-layout exporter. It works from a DATA-SCRUBBED XLSX copy
 * of the user's reference, never from an XLSM containing personal data/macros.
 * Keeps sheet names, formatting, dimensions, print settings and tariff tables.
 * Account numbers/signatures must be configured independently before the
 * accounting sheet can be treated as a posted voucher.
 */
final class ReferenceMonthlyStatementWorkbook
{
    private const TEMPLATE=__DIR__.'/templates/school-statement-reference.xlsx';
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public static function available(): bool
    {
        return is_file(self::TEMPLATE) && class_exists(\ZipArchive::class) && class_exists(\DOMDocument::class);
    }

    private static function col(int $number): string
    {
        $out='';
        while($number>0){$number--; $out=chr(65+$number%26).$out; $number=intdiv($number,26);}
        return $out;
    }

    private static function document(\ZipArchive $zip,int $sheet): \DOMDocument
    {
        $xml=$zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml');
        if(!is_string($xml))throw new \RuntimeException('Reference sheet missing: '.$sheet);
        $doc=new \DOMDocument('1.0','UTF-8');
        $doc->preserveWhiteSpace=false;
        if(!$doc->loadXML($xml,LIBXML_NONET))throw new \RuntimeException('Reference sheet invalid: '.$sheet);
        return $doc;
    }

    private static function body(\DOMDocument $doc): \DOMElement
    {
        $items=$doc->getElementsByTagNameNS(self::NS,'sheetData');
        $node=$items->item(0);
        if(!$node instanceof \DOMElement)throw new \RuntimeException('Missing worksheet data');
        return $node;
    }

    private static function put(\DOMDocument $doc,\DOMElement $row,string $col,int $index,mixed $value,?string $style=null): void
    {
        if($value===null || $value==='')return;
        $cell=$doc->createElementNS(self::NS,'c');
        $cell->setAttribute('r',$col.$index);
        if($style!==null)$cell->setAttribute('s',$style);
        if(is_array($value) && array_key_exists('formula',$value)){
            $f=$doc->createElementNS(self::NS,'f');
            $f->appendChild($doc->createTextNode((string)$value['formula']));
            $cell->appendChild($f);
        }elseif(is_int($value)||is_float($value)){
            $v=$doc->createElementNS(self::NS,'v');
            $v->appendChild($doc->createTextNode((string)$value));
            $cell->appendChild($v);
        }else{
            $cell->setAttribute('t','inlineStr');
            $is=$doc->createElementNS(self::NS,'is');
            $t=$doc->createElementNS(self::NS,'t');
            $t->appendChild($doc->createTextNode((string)$value));
            $is->appendChild($t);
            $cell->appendChild($is);
        }
        $row->appendChild($cell);
    }

    private static function replaceRows(\ZipArchive $zip,int $sheet,int $start,array $records,bool $withTotal=false): void
    {
        $doc=self::document($zip,$sheet);
        $body=self::body($doc);
        $style=[];$height=null;
        foreach($body->childNodes as $row){
            if(!$row instanceof \DOMElement || $row->localName!=='row')continue;
            if((int)$row->getAttribute('r')!==$start)continue;
            if($row->hasAttribute('ht'))$height=$row->getAttribute('ht');
            foreach($row->childNodes as $cell){
                if(!$cell instanceof \DOMElement || $cell->localName!=='c')continue;
                $letter=preg_replace('/[0-9]/','',$cell->getAttribute('r'));
                $style[$letter]=$cell->getAttribute('s')?:null;
            }
        }
        // Remove every old source row, including cached financial subtotals.
        // The copied format row is used only for its style IDs, never its data.
        foreach(iterator_to_array($body->childNodes) as $row){
            if($row instanceof \DOMElement && $row->localName==='row' && (int)$row->getAttribute('r') >= $start){
                $body->removeChild($row);
            }
        }
        foreach($records as $i=>$cells){
            $r=$start+$i;
            $row=$doc->createElementNS(self::NS,'row');
            $row->setAttribute('r',(string)$r);
            if($height!==null){$row->setAttribute('ht',$height);$row->setAttribute('customHeight','1');}
            foreach($cells as $col=>$value){
                self::put($doc,$row,(string)$col,$r,$value,$style[$col]??null);
            }
            $body->appendChild($row);
        }
        if($withTotal && $records){
            $r=$start+count($records);
            $row=$doc->createElementNS(self::NS,'row');
            $row->setAttribute('r',(string)$r);
            self::put($doc,$row,'B',$r,'جمع کل',$style['B']??null);
            foreach(['D','E','F','G','H'] as $col){
                self::put($doc,$row,$col,$r,['formula'=>'SUM('.$col.$start.':'.$col.($r-1).')'],$style[$col]??null);
            }
            $body->appendChild($row);
        }
        // Force Excel to recalculate dependent report formulas upon open.
        $zip->addFromString('xl/worksheets/sheet'.$sheet.'.xml',$doc->saveXML());
    }

    private static function replaceCells(\ZipArchive $zip,int $sheet,array $values): void
    {
        $doc=self::document($zip,$sheet);
        $body=self::body($doc);
        $rows=[];
        foreach($body->childNodes as $row){
            if($row instanceof \DOMElement && $row->localName==='row')$rows[(int)$row->getAttribute('r')]=$row;
        }
        foreach($values as $address=>$value){
            if(!preg_match('/^([A-Z]+)([1-9][0-9]*)$/',$address,$m))throw new \InvalidArgumentException('Invalid cell reference');
            [$whole,$col,$num]=$m;$num=(int)$num;
            if(!isset($rows[$num])){
                $rows[$num]=$doc->createElementNS(self::NS,'row');
                $rows[$num]->setAttribute('r',(string)$num);
                $body->appendChild($rows[$num]);
            }
            $row=$rows[$num];$style=null;
            foreach(iterator_to_array($row->childNodes) as $cell){
                if($cell instanceof \DOMElement && $cell->localName==='c' && $cell->getAttribute('r')===$address){
                    $style=$cell->getAttribute('s')?:null;$row->removeChild($cell);
                }
            }
            self::put($doc,$row,$col,$num,$value,$style);
        }
        // OOXML requires cells to remain ordered by column. Replaced cells
        // are appended by put(), so normalize each modified row explicitly.
        foreach($rows as $row){
            $cells=[];
            foreach(iterator_to_array($row->childNodes) as $cell){
                if($cell instanceof \\DOMElement && $cell->localName==='c'){
                    $cells[]=$cell;$row->removeChild($cell);
                }
            }
            usort($cells,static function(\\DOMElement $a,\\DOMElement $b): int{
                $key=static function(string $ref): int{
                    preg_match('/^[A-Z]+/',$ref,$m);$n=0;
                    foreach(str_split($m[0]??'') as $letter)$n=$n*26+ord($letter)-64;
                    return $n;
                };
                return $key($a->getAttribute('r'))<=>$key($b->getAttribute('r'));
            });
            foreach($cells as $cell)$row->appendChild($cell);
        }
        $zip->addFromString('xl/worksheets/sheet'.$sheet.'.xml',$doc->saveXML());
    }

    public static function downloadIfAvailable(array $d,string $agency,int $statementId): bool
    {
        if(!self::available())return false;
        $temp=tempnam(sys_get_temp_dir(),'sy-ref-');
        if($temp===false || !copy(self::TEMPLATE,$temp))throw new \RuntimeException('Cannot prepare reference workbook');
        try{
            $zip=new \ZipArchive();
            if($zip->open($temp)!==true)throw new \RuntimeException('Cannot open reference XLSX');
            try{
                $items=$d['items'];
                $students=[];$drivers=[];$parents=[];$final=[];
                foreach($items as $i=>$v){
                    $row=$i+1;$ref=$v['reference']??SchoolServiceStatement::fromWorkbookRate((string)$v['gross'],30,true,true);
                    $iban=(string)($v['driver_iban']??'');
                    $students[]=[
                        'A'=>$row,'B'=>$v['student_name']??'','C'=>(string)($v['student_national_code']??''),
                        'D'=>$v['grade']??'','E'=>trim(($v['guardian_first_name']??'').' '.($v['guardian_last_name']??'')),
                        'F'=>(string)($v['mobile']??''),'H'=>$v['address']??'','I'=>$v['school_name']??'',
                        'J'=>$v['region']??'','K'=>(float)($v['tariff_distance_km']??0)
                    ];
                    $r=$i+3;
                    $parents[]=[
                        'A'=>$row,'B'=>$v['student_name']??'','C'=>(string)($v['student_national_code']??''),
                        'D'=>$v['school_name']??'','E'=>$v['region']??'','F'=>$v['driver_name']??'',
                        'G'=>$v['vehicle_model']??'','H'=>(float)($v['tariff_distance_km']??0),
                        'I'=>(float)($v['gross']??0),'J'=>30,'K'=>(float)$ref['driver_fee'],
                        'L'=>['formula'=>"K{$r}*3%"],'M'=>['formula'=>"K{$r}*2.75%"],
                        'N'=>['formula'=>"M{$r}*10%"],'O'=>['formula'=>"K{$r}*0.25%"],
                        'P'=>['formula'=>"K{$r}*1%"],'Q'=>['formula'=>"K{$r}*7%"],
                        'R'=>(float)$ref['certificate_fee'],
                        'S'=>['formula'=>"SUM(K{$r},L{$r},P{$r},Q{$r},R{$r})"],
                        'T'=>['formula'=>"S{$r}*7.8%"],'U'=>['formula'=>"S{$r}+T{$r}"],
                        'V'=>['formula'=>"SUM(K{$r},L{$r},P{$r},Q{$r})"],
                        'W'=>$iban
                    ];
                }
                $driverData=$d['reference_drivers']??[];
                if(!$driverData){
                    $indexed=[];
                    foreach($items as $v){
                        $id=(int)($v['driver_id']??0);
                        if(!isset($indexed[$id]))$indexed[$id]=['driver_name'=>$v['driver_name']??'',
                            'driver_iban'=>$v['driver_iban']??'','driver_fee'=>0.0];
                        $indexed[$id]['driver_fee']+=(float)($v['gross']??0);
                    }
                    $driverData=array_values($indexed);
                }
                $unique=[];
                foreach($items as $v){
                    $id=(int)($v['driver_id']??0);
                    if(isset($unique[$id]))continue;
                    $unique[$id]=true;
                    $iban=(string)($v['driver_iban']??'');
                    $drivers[]=[
                        'A'=>count($drivers)+1,'B'=>$v['driver_name']??'',
                        'C'=>(string)($v['driver_national_code']??''),
                        'D'=>(string)($v['driver_mobile']??''),'E'=>$v['vehicle_model']??'',
                        'G'=>preg_replace('/^IR/i','',$iban),'H'=>$iban,
                        'I'=>$v['bank_name']??'','J'=>(string)($v['vehicle_plate']??'')
                    ];
                }
                foreach($driverData as $i=>$v){
                    $r=$i+4;
                    $final[]=['A'=>$i+1,'B'=>$v['driver_name']??'','C'=>$v['driver_iban']??'',
                        'D'=>(float)($v['driver_fee']??0),
                        'E'=>['formula'=>"D{$r}*3%"],'F'=>['formula'=>"D{$r}*1%"],
                        'G'=>['formula'=>"D{$r}*7%"],'H'=>['formula'=>"SUM(D{$r}:G{$r})"]];
                }
                self::replaceRows($zip,4,3,$students);
                self::replaceRows($zip,5,3,$drivers);
                self::replaceRows($zip,6,3,$parents);
                self::replaceRows($zip,7,4,$final,true);
                self::replaceCells($zip,2,['B1'=>$agency,'B8'=>$d['month_name']]);
                $t=$d['reference_totals']??[];
                if(!$t){
                    $t=array_fill_keys(['driver_fee','oversight_3pct','municipality_2_75pct',
                      'municipality_vat_10pct','road_police_0_25pct','education_1pct',
                      'company_7pct','certificate_fee','insurance_fee','parent_total',
                      'statement_excluding_certificate_insurance'],0.0);
                    foreach($items as $v){
                        $ref=$v['reference']??SchoolServiceStatement::fromWorkbookRate((string)$v['gross'],30,true,true);
                        foreach($t as $k=>&$total)$total+=(float)$ref[$k];
                        unset($total);
                    }
                }
                $period=$d['month_name'].' '.$d['year'];
                self::replaceCells($zip,12,[
                    'A5'=>$period,'C5'=>count($items),'D5'=>count($drivers),
                    'E5'=>$d['totals']['schools']??0,
                    'I5'=>$t['driver_fee'],'J5'=>$t['oversight_3pct'],
                    'K5'=>$t['municipality_2_75pct'],'L5'=>$t['municipality_vat_10pct'],
                    'M5'=>$t['road_police_0_25pct'],'N5'=>$t['education_1pct'],
                    'O5'=>$t['company_7pct'],'P5'=>$t['certificate_fee'],
                    'Q5'=>$t['parent_total']-$t['insurance_fee'],
                    'R5'=>$t['insurance_fee'],'S5'=>$t['parent_total'],
                    'U5'=>$t['statement_excluding_certificate_insurance'],
                    'AA5'=>$period,'AB5'=>$t['driver_fee'],'AC5'=>$t['oversight_3pct'],
                    'AD5'=>$t['education_1pct'],'AE5'=>$t['company_7pct']
                ]);
                self::replaceCells($zip,14,[
                    'A6'=>$d['month_name'],'B6'=>$t['driver_fee'],
                    'C6'=>$t['road_police_0_25pct'],'D6'=>$t['municipality_2_75pct'],
                    'E6'=>$t['municipality_vat_10pct'],'F6'=>$t['education_1pct'],
                    'G6'=>$t['company_7pct']
                ]);
                // Do not invent journal voucher account codes or contract IDs.
                // The reference accounting sheet remains unposted until mapped.
                $zip->close();
            }catch(\Throwable $e){$zip->close();throw $e;}
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="school-statement-reference-'.$d['year'].'-'.str_pad((string)$d['month'],2,'0',STR_PAD_LEFT).'-'.$statementId.'.xlsx"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            readfile($temp);
        }finally{if(is_file($temp))@unlink($temp);}
        exit;
    }
}
