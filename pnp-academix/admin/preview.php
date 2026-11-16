<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/api/portal_access.php';
require_once dirname(__DIR__).'/includes/academix-ui.php';
require_once dirname(__DIR__).'/includes/preview-support.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
if(!pnp_portal_v2())pnp_portal_fail(new PortalProblem(503,'Preview ต้องใช้งานผ่านบัญชี Portal รุ่นปัจจุบัน'));
$raw=($_GET['raw']??'')==='1';$context=pnp_portal_guard('pnp-academic','view',$raw,'admin');
$id=filter_var($_GET['submission_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$status=200;$message='';$branding=['college_name'=>'วิทยาลัยการอาชีพพนมไพร'];$submission=null;$pdf=null;$external=null;$pdo=null;
try{
 if(!$id)throw new PortalProblem(400,'กรุณาเลือกรายการส่งงานที่ถูกต้อง');
 $prod=str_contains($_SERVER['HTTP_HOST']??'','montien.tech');$prefix='ACADEMIX_DB_';
 $pdo=new PDO('mysql:host='.env($prefix.'HOST','localhost').';port='.(int)env($prefix.'PORT',3306).';dbname='.env($prefix.'NAME',$prod?'u651170081_pnp_academix':'pnp_academix').';charset=utf8mb4',(string)env($prefix.'USER',$prod?'u651170081_pnp_academix':'root'),(string)env($prefix.'PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
 $q=$pdo->prepare('SELECT s.*,c.course_code,c.course_name,c.teacher_id AS submission_teacher_id,u.username AS teacher_username,u.fullname AS teacher_fullname,sem.semester_name FROM submissions s JOIN courses c ON c.id=s.course_id JOIN users u ON u.id=c.teacher_id JOIN semesters sem ON sem.id=c.semester_id WHERE s.id=?');$q->execute([$id]);$submission=$q->fetch();
 if(!$submission)throw new PortalProblem(404,'ไม่พบงานที่คุณมีสิทธิ์เปิดดู');
 if(!PortalV2::scopeAllows($context,(string)$submission['teacher_username']))throw new PortalProblem(403,'ข้อมูลอยู่นอกขอบเขตที่ได้รับมอบหมาย');
 $branding=ac_branding($pdo);$pdo->rollBack();
 $pdf=ac_pdf_source($submission['file_path']??null,[dirname(__DIR__),upload_base_path('pnp-academix')]);$external=ac_external_source($submission['drive_link']??null);
 if($raw){if(!$pdf)throw new PortalProblem(404,'ไม่พบไฟล์ PDF เดิมที่เปิดดูได้ ข้อมูลการส่งงานยังคงอยู่');ac_stream_pdf($pdf,(int)$id);}
}catch(PortalProblem $e){$status=$e->status;$message=$e->getMessage();}
catch(Throwable $e){error_log('Academix preview unavailable: '.get_class($e));$status=503;$message='ไม่สามารถเปิดดูงานได้ในขณะนี้ กรุณาลองใหม่ภายหลัง';}
finally{if($pdo?->inTransaction())$pdo->rollBack();}
if($raw){http_response_code($status);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: private, no-store');echo $method==='HEAD'?'':$message;exit;}
http_response_code($status);header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; img-src 'self' https: data:; frame-src 'self' https://drive.google.com https://docs.google.com https://www.youtube-nocookie.com; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; form-action 'none'");
if($method==='HEAD')exit;
ac_start('เปิดดูงานที่ส่ง',$branding);ac_header($branding,'admin',trim($context['user']['first_name'].' '.$context['user']['last_name']));
?>
<main id="ac-main" class="ac-main">
 <a class="ac-back" href="overview.php">← กลับภาพรวมผู้ดูแล</a>
 <?php if($message): ?><section class="ac-panel ac-empty"><h1>ไม่สามารถเปิดดูงานได้</h1><p><?= ac_h($message) ?></p></section>
 <?php else: $type=AC_DOCUMENT_TYPES[$submission['system_type']]??['title'=>'เอกสารที่ส่ง']; ?>
 <div class="ac-page-heading"><div><p class="ac-eyebrow">Review preview · อ่านอย่างเดียว</p><h1><?= ac_h($type['title']) ?></h1><p><?= ac_h($submission['teacher_fullname'].' · '.$submission['course_code'].' '.$submission['course_name']) ?></p></div><div class="ac-badges"><?= ac_badge($submission['status'],$submission['submission_timing']) ?></div></div>
 <section class="ac-panel ac-document-meta"><div><span>ภาคเรียน</span><strong><?= ac_h($submission['semester_name']) ?></strong></div><div><span>ส่งเมื่อ</span><strong><?= ac_h(ac_date($submission['submitted_at'])) ?></strong></div><div><a class="ac-button" href="review.php?submission_id=<?= (int)$id ?>">ไปหน้าตรวจพิจารณา →</a></div></section>
 <?php $documentUrl=ac_base().'/admin/preview.php?submission_id='.(int)$id;require dirname(__DIR__).'/includes/admin-document-frame.php';endif; ?>
</main><?php ac_end($branding); ?>
