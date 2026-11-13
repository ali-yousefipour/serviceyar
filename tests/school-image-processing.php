<?php
declare(strict_types=1);

require dirname(__DIR__).'/backend/src/bootstrap.php';
use ServiceYar\Domain\SchoolPhotos;

if(!extension_loaded('gd'))throw new RuntimeException('GD required');

$make=function(int $w,int $h,string $format): string {
 $im=imagecreatetruecolor($w,$h);
 for($y=0;$y<$h;$y+=24){
  $shade=($y*7)%255;
  $c=imagecolorallocate($im,$shade,150,255-$shade);
  imagefilledrectangle($im,0,$y,$w-1,$y+23,$c);
 }
 $file=tempnam(sys_get_temp_dir(),'school-photo-');
 if($format==='image/jpeg')imagejpeg($im,$file,91);
 elseif($format==='image/png')imagepng($im,$file);
 else imagewebp($im,$file,88);
 imagedestroy($im);
 return $file;
};
$cases=[
 ['jpeg portrait',900,1200,'image/jpeg',480,640],
 ['png landscape',1600,900,'image/png',640,480],
 ['webp square',900,900,'image/webp',640,480],
 ['jpeg wide',1920,1080,'image/jpeg',640,480],
];
foreach($cases as [$label,$width,$height,$mime,$targetW,$targetH]){
 $source=$make($width,$height,$mime);
 try{
  $result=SchoolPhotos::prepareImage($source,$mime);
  if($result['width']!==$targetW||$result['height']!==$targetH)
   throw new RuntimeException($label.': incorrect dimensions');
  if($result['sizeBytes']>110*1024||$result['sizeBytes']<1)
   throw new RuntimeException($label.': compressed output outside limit');
  $output=getimagesizefromstring($result['bytes']);
  if(!$output||$output[0]!==$targetW||$output[1]!==$targetH||$output['mime']!=='image/jpeg')
   throw new RuntimeException($label.': invalid JPEG output');
  echo "PASS {$label}: {$output[0]}x{$output[1]} / {$result['sizeBytes']} bytes\n";
 }finally{unlink($source);}
}
