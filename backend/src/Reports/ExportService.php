<?php
declare(strict_types=1);
namespace ServiceYar\Reports;
use ServiceYar\Auth\Authorization; use ServiceYar\Database\Connection;
final class ExportService {
 public static function xlsx(int $uid,int $aid,string $resource): never {
  Authorization::requirePermission($uid,'reports.view');Authorization::requireAgencyAccess($uid,$aid);
  $map=['schools'=>'schools','students'=>'students','drivers'=>'drivers','services'=>'services','invoices'=>'invoices','payments'=>'payments','driver-work'=>'driver_work'];
  if(!isset($map[$resource])){http_response_code(422);header('Content-Type:application/json;charset=utf-8');echo json_encode(['ok'=>false,'error'=>['code'=>'VALIDATION_ERROR','message'=>'گزارش قابل خروجی نیست.']],JSON_UNESCAPED_UNICODE);exit;}
  $table=$map[$resource];$pdo=Connection::get();$s=$pdo->prepare("SELECT * FROM $table WHERE agency_id=:a ORDER BY id DESC LIMIT 5000");$s->execute(['a'=>$aid]);$rows=$s->fetchAll(\PDO::FETCH_ASSOC);$headers=$rows?array_keys($rows[0]):[];
  $esc=fn($v)=>htmlspecialchars((string)$v,ENT_XML1|ENT_COMPAT,'UTF-8');
  $sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
  $row=1;$sheet.='<row r="1">'.implode('',array_map(fn($h)=>'<c t="inlineStr"><is><t>'.$esc($h).'</t></is></c>',$headers)).'</row>';
  foreach($rows as $r){$row++;$sheet.='<row r="'.$row.'">'.implode('',array_map(fn($v)=>'<c t="inlineStr"><is><t>'.$esc($v).'</t></is></c>',$r)).'</row>';}$sheet.='</sheetData></worksheet>';
  $tmp=tempnam(sys_get_temp_dir(),'syx');$zip=new \ZipArchive();$zip->open($tmp,\ZipArchive::CREATE|\ZipArchive::OVERWRITE);
  $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
  $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="گزارش" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
  $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);$zip->close();$data=file_get_contents($tmp);unlink($tmp);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$resource.'-report.xlsx"');header('Content-Length: '.strlen($data));echo $data;exit;
 }
}
