<?php
/**
 * upload_serve.php — serve ไฟล์ upload จากนอก public_html
 *
 * ทำงานร่วมกับ .htaccess ใน pnp-academix/ และ pnpman/
 * ซึ่ง rewrite request uploads/ มาที่นี่แทนการเข้าถึงไฟล์โดยตรง
 * เพื่อให้ไฟล์ที่ผู้ใช้อัปโหลดอยู่นอก git deployment zone
 */
require_once __DIR__ . '/api/env.php';
require_once __DIR__ . '/api/portal_access.php';

$app  = $_GET['app'] ?? '';
$file = $_GET['file'] ?? '';

$allowedApps = ['pnp-academix', 'pnpman', 'pnp-go', 'portal'];
if (!in_array($app, $allowedApps, true) || $file === '' || str_contains($file, '..') || str_contains($file, "\0")) {
    http_response_code(400);
    exit;
}

$file=ltrim($file,'/');
$validPath=$app==='pnp-go'?preg_match('~^storage/(pdfs|reports|signatures)/.+$~D',$file):str_starts_with($file,'uploads/');
if(!$validPath){http_response_code(400);exit;}
try {
if(pnp_portal_v2()){
    $appId=['pnp-academix'=>'pnp-academic','pnpman'=>'pnp-man','pnp-go'=>'pnp-go','portal'=>'pnp-man'][$app];
    if($app==='portal'){
        try{$actor=PortalV2::actor();$owner=PortalV2::query('SELECT username,id FROM users WHERE avatar_path=?',[$file])->fetch();if(!$owner)throw new PortalProblem(404,'ไม่พบรูปที่อ้างอิง');if((int)$actor['is_portal_admin']!==1&&(int)$owner['id']!==(int)$actor['id'])pnp_portal_scope(pnp_portal_guard('pnp-man'),$owner['username']);}catch(PortalProblem $e){pnp_portal_fail($e);}
    }else{
        $context=pnp_portal_guard($appId);
        if((int)$context['user']['is_portal_admin']!==1){
            if($app==='pnpman'){
                $owner=PortalV2::query('SELECT username FROM users WHERE avatar_path=? LIMIT 1',[$file])->fetchColumn();
                if($owner)pnp_portal_scope($context,(string)$owner);
                elseif(!PortalV2::query('SELECT id FROM college_settings WHERE logo_path=? LIMIT 1',[$file])->fetchColumn())pnp_portal_fail(new PortalProblem(404,'ไม่พบไฟล์ที่อ้างอิง'));
            }else{
                $prefix=$app==='pnp-academix'?'ACADEMIX_DB_':'PNPGO_DB_';$host=(string)env($prefix.'HOST','localhost');$dbname=(string)env($prefix.'NAME','');$pdoFile=new PDO('mysql:host='.$host.';port='.(int)env($prefix.'PORT',3306).';dbname='.$dbname.';charset=utf8mb4',(string)env($prefix.'USER','root'),(string)env($prefix.'PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                if($app==='pnp-academix'){
                    $q=$pdoFile->prepare('SELECT u.username FROM submissions s JOIN courses c ON c.id=s.course_id JOIN users u ON u.id=c.teacher_id WHERE s.file_path=? LIMIT 1');$q->execute([$file]);$owner=$q->fetchColumn();
                    if(!$owner&&preg_match('~^uploads/\d+/(\d+)/(\d+)/~',$file,$parts)){$q=$pdoFile->prepare('SELECT u.username FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.id=? AND u.id=?');$q->execute([(int)$parts[2],(int)$parts[1]]);$owner=$q->fetchColumn();}
                    if($owner){if($context['access']['role']!=='admin'&&$owner!==$context['user']['username'])pnp_portal_fail(new PortalProblem(403,'เปิดเอกสารของบุคคลอื่นไม่ได้'));pnp_portal_scope($context,(string)$owner);}
                    elseif(!str_starts_with($file,'uploads/branding/'))pnp_portal_fail(new PortalProblem(404,'ไม่พบเอกสารที่อ้างอิง'));
                }else{
                    $q=$pdoFile->prepare('SELECT u.username FROM requisitions r JOIN users u ON u.id=r.user_id WHERE r.pdf_path=? OR r.report_photo_path=? OR r.report_photo_path_2=? LIMIT 1');$q->execute([$file,$file,$file]);$owner=$q->fetchColumn();
                    if(!$owner){$q=$pdoFile->prepare('SELECT username FROM users WHERE signature_path=? LIMIT 1');$q->execute([$file]);$owner=$q->fetchColumn();}
                    if(!$owner)pnp_portal_fail(new PortalProblem(404,'ไม่พบไฟล์ที่อ้างอิง'));
                    if(in_array($context['access']['role'],['user','driver'],true)&&$owner!==$context['user']['username'])pnp_portal_fail(new PortalProblem(403,'เปิดเอกสารของบุคคลอื่นไม่ได้'));
                    pnp_portal_scope($context,(string)$owner);
                }
            }
        }
    }
}
} catch (Throwable $e) { error_log('Protected upload unavailable: '.get_class($e)); http_response_code(503); header('Cache-Control: no-store'); exit; }
$roots=match($app){'portal'=>[__DIR__],'pnp-go'=>[__DIR__.'/pnp-go'],default=>[__DIR__.'/'.$app,upload_base_path($app)]};
$fullPath='';foreach($roots as $root){$base=realpath($root);$candidate=realpath($root.'/'.$file);if($base&&$candidate&&str_starts_with($candidate,$base.'/')&&is_file($candidate)){$fullPath=$candidate;break;}}

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
if(!in_array($mime,['application/pdf','image/jpeg','image/png','image/gif','image/webp','image/svg+xml'],true)){$mime='application/octet-stream';header('Content-Disposition: attachment');}
if($mime==='image/svg+xml')header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'");
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header(pnp_portal_v2()?'Cache-Control: private, no-store':'Cache-Control: public, max-age=86400');
readfile($fullPath);
