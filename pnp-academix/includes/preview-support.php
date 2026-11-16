<?php
declare(strict_types=1);
/** Parse only; never fetch, rewrite or persist a submitted external URL. */
function ac_external_source(?string $url):?array{
 $url=trim((string)$url);if(!$url||preg_match('/[\x00-\x20\x7f]/',$url)||!filter_var($url,FILTER_VALIDATE_URL))return null;
 $p=parse_url($url);if(!in_array(strtolower($p['scheme']??''),['https','http'],true)||isset($p['user'])||isset($p['pass']))return null;
 $result=['url'=>$url,'embed'=>null,'label'=>'ลิงก์ภายนอก'];$host=strtolower($p['host']??'');$path=$p['path']??'';parse_str($p['query']??'',$query);
 if(strtolower($p['scheme'])!=='https'||(isset($p['port'])&&(int)$p['port']!==443))return $result;
 if($host==='drive.google.com'){
  $id=null;if(preg_match('~^/file/d/([A-Za-z0-9_-]{10,200})(?:/|$)~D',$path,$m))$id=$m[1];elseif(in_array($path,['/open','/uc'],true)&&is_string($query['id']??null)&&preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$query['id']))$id=$query['id'];
  $result['label']='Google Drive';if($id)$result['embed']='https://drive.google.com/file/d/'.$id.'/preview';
 }
 $video=null;
 if(in_array($host,['youtube.com','www.youtube.com','m.youtube.com','www.youtube-nocookie.com'],true)){
  if($path==='/watch'&&is_string($query['v']??null))$video=$query['v'];elseif(preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})(?:/|$)~D',$path,$m))$video=$m[1];
 }elseif($host==='youtu.be'&&preg_match('~^/([A-Za-z0-9_-]{11})/?$~D',$path,$m))$video=$m[1];
 if(is_string($video)&&preg_match('/^[A-Za-z0-9_-]{11}$/D',$video)){$result['label']='YouTube';$result['embed']='https://www.youtube-nocookie.com/embed/'.$video;}
 return $result;
}
/** Resolve inside the two existing upload roots; open original PDF read-only. */
function ac_pdf_source(?string $relative,array $roots):?string{
 $relative=(string)$relative;if(!str_starts_with($relative,'uploads/')||str_contains($relative,'..')||str_contains($relative,'\\')||str_contains($relative,"\0"))return null;
 foreach($roots as $root){$base=realpath($root);$path=realpath($root.'/'.$relative);if($base&&$path&&str_starts_with($path,$base.'/')&&is_file($path)&&is_readable($path)){return (new finfo(FILEINFO_MIME_TYPE))->file($path)==='application/pdf'?$path:null;}}
 return null;
}
function ac_pdf_range(int $size,?string $range):?array{
 if(!$range)return [0,$size-1,false];
 if($size<1||strlen($range)>100||!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$m)||($m[1]===''&&$m[2]===''))return null;
 if($m[1]===''){$length=(int)$m[2];if($length<1)return null;$start=max(0,$size-$length);$end=$size-1;}
 else{$start=(int)$m[1];$end=$m[2]===''?$size-1:min((int)$m[2],$size-1);if($start>=$size||$end<$start)return null;}
 return [$start,$end,true];
}
function ac_stream_pdf(string $file,int $id):never{
 $size=filesize($file);$range=ac_pdf_range($size,$_SERVER['HTTP_RANGE']??null);
 header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header("Content-Security-Policy: frame-ancestors 'self'");header('Accept-Ranges: bytes');
 if(!$range){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
 [$start,$end,$partial]=$range;$length=max(0,$end-$start+1);http_response_code($partial?206:200);header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="PNP-Academix-'.$id.'.pdf"');header('Content-Length: '.$length);
 if($partial)header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
 if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')exit;
 $handle=fopen($file,'rb');if(!$handle)exit;fseek($handle,$start);
 while($length>0&&!feof($handle)&&!connection_aborted()){$chunk=fread($handle,min(65536,$length));if($chunk===false||$chunk==='')break;echo $chunk;$length-=strlen($chunk);}fclose($handle);exit;
}
