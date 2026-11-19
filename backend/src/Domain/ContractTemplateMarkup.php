<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

final class ContractTemplateMarkup {
 private const TAGS=['h1','h2','h3','h4','p','div','span','strong','b','em','i','u','s','strike','sub','sup','ul','ol','li','table','thead','tbody','tfoot','tr','td','th','br','hr','img','blockquote','font'];
 private const CLASSES=['contract-top','center','signatures','contract-logo','contract-value'];
 private static function validImage(string $src): bool {
  if(!preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D',$src,$m)||strlen($src)>700000)return false;
  $bytes=base64_decode($m[2],true);
  if($bytes===false||strlen($bytes)>500000)return false;
  $info=@getimagesizefromstring($bytes);
  return $info&&($info['mime']??'')==='image/'.$m[1]&&$info[0]<=2000&&$info[1]<=2000;
 }
 private static function style(string $raw): string {
  $safe=[];
  foreach(explode(';',$raw) as $part){
   $pair=explode(':',$part,2);if(count($pair)!==2)continue;
   $key=strtolower(trim($pair[0]));$value=trim($pair[1]);
   $valid=match($key){
    'text-align'=>in_array($value,['left','right','center','justify','start','end'],true),
    'direction'=>in_array($value,['rtl','ltr'],true),
    'font-weight'=>in_array($value,['normal','bold','400','500','600','700'],true),
    'font-style'=>in_array($value,['normal','italic'],true),
    'text-decoration','text-decoration-line'=>in_array($value,['none','underline','line-through'],true),
    'font-family'=>in_array(trim($value,"\"'"),['Vazirmatn','Tahoma','B Nazanin','Arial'],true),
    'font-size'=>(bool)preg_match('/^(?:[8-9]|1[0-9]|2[0-8])(?:pt|px)$/D',$value),
    'color','background-color'=>(bool)preg_match('/^(?:#[0-9a-fA-F]{3,6}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)|black|white|transparent)$/D',$value),
    'line-height'=>in_array($value,['1','1.2','1.4','1.5','1.8','2'],true),
    'width','height','margin-left','margin-right','padding-left','padding-right'=>(bool)preg_match('/^(?:0|[1-9]\d{0,2}(?:px|mm|%))$/D',$value),
    default=>false
   };
   if($valid)$safe[]=$key.':'.$value;
  }
  return implode(';',$safe);
 }
 public static function sanitize(string $html): string {
  $doc=new \DOMDocument('1.0','UTF-8');$prior=libxml_use_internal_errors(true);
  try{$doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($prior);}
  $body=$doc->getElementsByTagName('body')->item(0);if(!$body)return '';
  $walk=function(\DOMNode $node)use(&$walk): void{
   foreach(iterator_to_array($node->childNodes) as $child){
    if($child instanceof \DOMComment||$child instanceof \DOMProcessingInstruction){$node->removeChild($child);continue;}
    if(!$child instanceof \DOMElement)continue;
    $tag=strtolower($child->tagName);
    if(!in_array($tag,self::TAGS,true)){
     if(in_array($tag,['script','style','iframe','object','embed','svg','math','form','link','meta','base','template'],true)){$node->removeChild($child);continue;}
     $walk($child);while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;
    }
    $attrs=[];foreach($child->attributes as $attr)$attrs[$attr->name]=$attr->value;
    foreach(array_keys($attrs) as $key)$child->removeAttribute($key);
    if(isset($attrs['class'])){
     $classes=array_intersect(preg_split('/\s+/',trim($attrs['class']))?:[],self::CLASSES);
     if($classes)$child->setAttribute('class',implode(' ',$classes));
    }
    $style=self::style($attrs['style']??'');
    if(in_array($attrs['align']??'',['right','left','center','justify'],true))$style.=';text-align:'.$attrs['align'];
    if($tag==='font'){
     if(in_array($attrs['face']??'',['Vazirmatn','Tahoma','B Nazanin','Arial'],true))$style.=';font-family:'.$attrs['face'];
     $size=['1'=>'8pt','2'=>'10pt','3'=>'12pt','4'=>'14pt','5'=>'18pt','6'=>'22pt','7'=>'28pt'][$attrs['size']??'']??null;
     if($size)$style.=';font-size:'.$size;
     if(isset($attrs['color']))$style.=';'.self::style('color:'.$attrs['color']);
    }
    if(trim($style,';')!=='')$child->setAttribute('style',trim($style,';'));
    if(in_array($attrs['dir']??'',['rtl','ltr'],true))$child->setAttribute('dir',$attrs['dir']);
    if(in_array($tag,['td','th'],true))foreach(['colspan','rowspan'] as $key)if(preg_match('/^[1-9]\d?$/D',$attrs[$key]??''))$child->setAttribute($key,$attrs[$key]);
    if($tag==='img'){
     $logo=$attrs['data-contract-logo']??'';$src=$attrs['src']??'';
     if(in_array($logo,['company','fixed'],true)){$child->setAttribute('data-contract-logo',$logo);$child->setAttribute('src','/assets/logo.png');}
     elseif(self::validImage($src))$child->setAttribute('src',$src);
     else{$node->removeChild($child);continue;}
     $child->setAttribute('alt',mb_substr($attrs['alt']??'لوگو',0,100));
     $wrap=$attrs['data-logo-wrap']??'inline';
     if(!in_array($wrap,['inline','right','left','above','below','front','behind'],true))$wrap='inline';
     $child->setAttribute('data-logo-wrap',$wrap);
     foreach(['data-logo-x','data-logo-y'] as $coordinate){
      $raw=$attrs[$coordinate]??'0';
      $limit=$coordinate==='data-logo-x'?100:100;
      $child->setAttribute($coordinate,(string)max(0,min($limit,is_numeric($raw)?(float)$raw:0)));
     }
     $width=(int)($attrs['width']??100);$child->setAttribute('width',(string)max(24,min(300,$width)));
    }
    $walk($child);
   }
  };$walk($body);$out='';foreach($body->childNodes as $child)$out.=$doc->saveHTML($child);return $out;
 }
 /** Convert bounded image coordinates into generated CSS, rather than accepting arbitrary positioning CSS. */
 public static function positionLogos(string $html): string {
  return preg_replace_callback('/<img\b[^>]*>/i',static function(array $m){
   $tag=$m[0];
   if(!preg_match('/data-logo-wrap="(front|behind)"/',$tag))return $tag;
   $x=preg_match('/data-logo-x="([0-9.]+)"/',$tag,$a)?max(0,min(100,(float)$a[1])):0;
   $y=preg_match('/data-logo-y="([0-9.]+)"/',$tag,$a)?max(0,min(100,(float)$a[1])):0;
   $tag=preg_replace('/\sstyle="[^"]*"/i','',$tag)??$tag;
   return substr($tag,0,-1).' style="left:'.$x.'%;top:'.$y.'%">';
  },$html)??$html;
 }
 public static function resolveLogos(string $html,string $company): string {
  return preg_replace_callback('/<img\b[^>]*>/i',static function(array $m)use($company){
   if(!preg_match('/data-contract-logo="(company|fixed)"/',$m[0],$type))return $m[0];
   $src=$type[1]==='company'&&preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D',$company)?$company:'/assets/logo.png';
   return preg_replace('/\bsrc="[^"]*"/','src="'.$src.'"',$m[0],1)??$m[0];
  },$html)??$html;
 }
}
