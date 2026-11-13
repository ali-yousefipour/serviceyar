<?php
declare(strict_types=1);
$path=__DIR__.'/../backend/src/Reports/templates/school-statement-reference.xlsx';
$z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('open');
foreach([2,3,4,5] as $i){$d=new DOMDocument();$d->loadXML($z->getFromName("xl/tables/table$i.xml"));$t=$d->documentElement;$cols=$d->getElementsByTagName('tableColumn');$filter=$d->getElementsByTagName('autoFilter')->item(0);echo "Table $i ref=".$t->getAttribute('ref')." cols=".$cols->length." filter=".$filter?->getAttribute('ref')." totals=".$t->getAttribute('totalsRowCount')." names=";foreach($cols as $c)echo $c->getAttribute('name').'|';echo "\n";}
foreach([7,12] as $i){$d=new DOMDocument();$d->loadXML($z->getFromName("xl/worksheets/sheet$i.xml"));$count=[];$found=[];foreach($d->getElementsByTagName('f') as $f){if($f->getAttribute('t')==='shared'){$si=$f->getAttribute('si');$count[$si]=($count[$si]??0)+1;if(count($found)<15)$found[]=[$f->parentNode->getAttribute('r'),$si,$f->getAttribute('ref'),substr($f->textContent,0,100)];}}echo "Sheet $i shared=".json_encode($count)." sample=".json_encode($found,JSON_UNESCAPED_UNICODE)."\n";}
foreach([1,2,3,4,5,6,7,8,9,10,11,12,13,14] as $i){$d=new DOMDocument();$d->loadXML($z->getFromName("xl/worksheets/sheet$i.xml"));$values=[];foreach($d->getElementsByTagName('row') as $row){if((int)$row->getAttribute('r')>10)break;foreach($row->getElementsByTagName('c') as $c){$text=$c->textContent;if(preg_match('/1405|1404|شهریور|مهر|ماه|صورت وضع/u',$text)){$values[]=$c->getAttribute('r').':'.mb_substr($text,0,58);if(count($values)>12)break 2;}}}echo "Titles sheet$i ".json_encode($values,JSON_UNESCAPED_UNICODE)."\n";}

foreach([2=>4,3=>5,4=>6,5=>12] as $tableNo=>$sheetNo){
 $t=new DOMDocument();$t->loadXML($z->getFromName('xl/tables/table'.$tableNo.'.xml'));$cols=$t->getElementsByTagName('tableColumn');
 $d=new DOMDocument();$d->loadXML($z->getFromName('xl/worksheets/sheet'.$sheetNo.'.xml'));$r=$tableNo===5?4:2;
 $headers=[];foreach($d->getElementsByTagName('row') as $row)if((int)$row->getAttribute('r')===$r){foreach($row->getElementsByTagName('c') as $cell){$name=preg_replace('/[0-9]/','',$cell->getAttribute('r'));$headers[$name]=$cell->textContent;}break;}
 $diff=[];foreach($cols as $i=>$col){$k='';$n=$i+1;while($n>0){$n--;$k=chr(65+$n%26).$k;$n=intdiv($n,26);}if(trim($col->getAttribute('name'))!==trim($headers[$k]??''))$diff[]=$k.':'.substr($col->getAttribute('name'),0,28).'!='.substr($headers[$k]??'',0,28);}
 echo 'TABLE-MISMATCH-'.$tableNo.' '.json_encode($diff,JSON_UNESCAPED_UNICODE)."\\n";
}
$z->close();
