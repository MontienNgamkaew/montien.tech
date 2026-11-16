<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/api/portal_access.php';
require_once dirname(__DIR__).'/includes/academix-ui.php';
require_once dirname(__DIR__).'/includes/preview-support.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
if(!pnp_portal_v2())pnp_portal_fail(new PortalProblem(503,'Preview ต้องใช้งานผ่านบัญชี Portal รุ่นปัจจุบัน'));
$raw=($_GET['raw']??'')==='1';$context=pnp_portal_guard('pnp-academic','view',$raw,'self');
$id=filter_var($_GET['submission_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$status=200;$message='';$branding=['college_name'=>'วิทยาลัยการอาชีพพนมไพร'];$submission=null;$pdf=null;$external=null;$pdo=null;
try{
 if(!$id)throw new PortalProblem(400,'กรุณาเลือกรายการส่งงานที่ถูกต้อง');
 $prod=str_contains($_SERVER['HTTP_HOST']??'','montien.tech');$prefix='ACADEMIX_DB_';
 $pdo=new PDO('mysql:host='.env($prefix.'HOST','localhost').';port='.(int)env($prefix.'PORT',3306).';dbname='.env($prefix.'NAME',$prod?'u651170081_pnp_academix':'pnp_academix').';charset=utf8mb4',(string)env($prefix.'USER',$prod?'u651170081_pnp_academix':'root'),(string)env($prefix.'PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
 $q=$pdo->prepare('SELECT id FROM users WHERE username=?');$q->execute([$context['user']['username']]);$teacherId=$q->fetchColumn();
 if(!$teacherId)throw new PortalProblem(404,'ไม่พบงานที่คุณมีสิทธิ์เปิดดู');
 $q=$pdo->prepare('SELECT s.*,c.course_code,c.course_name,sem.semester_name FROM submissions s JOIN courses c ON c.id=s.course_id JOIN semesters sem ON sem.id=c.semester_id WHERE s.id=? AND c.teacher_id=?');$q->execute([$id,$teacherId]);$submission=$q->fetch();
 if(!$submission)throw new PortalProblem(404,'ไม่พบงานที่คุณมีสิทธิ์เปิดดู');
 $branding=ac_branding($pdo);$pdo->rollBack();
 $pdf=ac_pdf_source($submission['file_path']??null,[dirname(__DIR__),upload_base_path('pnp-academix')]);$external=ac_external_source($submission['drive_link']??null);
 if($raw){if(!$pdf)throw new PortalProblem(404,'ไม่พบไฟล์ PDF เดิมที่เปิดดูได้ ข้อมูลการส่งงานยังคงอยู่');ac_stream_pdf($pdf,(int)$id);}
}catch(PortalProblem $e){$status=$e->status;$message=$e->getMessage();}
catch(Throwable $e){error_log('Academix preview unavailable: '.get_class($e));$status=503;$message='ไม่สามารถเปิดดูงานได้ในขณะนี้ กรุณาลองใหม่ภายหลัง';}
finally{if($pdo?->inTransaction())$pdo->rollBack();}
if($raw){http_response_code($status);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: private, no-store');echo $method==='HEAD'?'':$message;exit;}
http_response_code($status);header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; img-src 'self' https: data:; frame-src 'self' https://drive.google.com https://docs.google.com https://www.youtube-nocookie.com; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; form-action 'none'");
if($method==='HEAD')exit;
ac_start('เปิดดูงานที่ส่ง',$branding);ac_header($branding,'self',trim($context['user']['first_name'].' '.$context['user']['last_name']));
?>
<main id="ac-main" class="ac-main">
 <a class="ac-back" href="<?= ac_h(ac_base().'/teacher/dashboard.php'.($submission?'#course-'.$submission['course_id']:'')) ?>">← กลับหน้าสถานะงานของฉัน</a>
 <?php if($message): ?><section class="ac-panel ac-empty"><h1>ไม่สามารถเปิดดูงานได้</h1><p><?= ac_h($message) ?></p></section>
 <?php else:
 $type=AC_DOCUMENT_TYPES[$submission['system_type']]??['title'=>'เอกสารที่ส่ง'];
 $url=ac_base().'/teacher/preview.php?submission_id='.(int)$id;
 $requested=is_string($_GET['source']??null)?$_GET['source']:'';$source=$requested==='file'?'file':($requested==='link'?'link':($external?'link':($pdf?'file':(!empty($submission['drive_link'])?'link':'file'))));
 $fileUrl=$url.'&source=file&raw=1';$frame=$source==='file'?($pdf?$fileUrl:null):($external['embed']??null);$original=$source==='file'?($pdf?$fileUrl:null):($external['url']??null);
 ?>
 <div class="ac-page-heading"><div><p class="ac-eyebrow">Submission preview</p><h1><?= ac_h($type['title']) ?></h1><p><?= ac_h($submission['course_code'].' · '.$submission['course_name']) ?></p></div><div class="ac-badges"><?= ac_badge($submission['status'],$submission['submission_timing']) ?></div></div>
 <section class="ac-panel ac-document-meta"><div><span>ภาคเรียน</span><strong><?= ac_h($submission['semester_name']) ?></strong></div><div><span>ส่งเมื่อ</span><strong><?= ac_h(ac_date($submission['submitted_at'])) ?></strong></div><div><span>รูปแบบการเปิดดู</span><strong>อ่านอย่างเดียว</strong></div></section>
 <?php if(!empty($submission['feedback'])): ?><aside class="ac-notice <?= $submission['status']==='rejected'?'warning':'' ?>"><strong>ข้อเสนอแนะจากผู้ตรวจ</strong><p><?= nl2br(ac_h($submission['feedback'])) ?></p></aside><?php endif; ?>
 <section class="ac-panel ac-preview-panel"><div class="ac-preview-toolbar"><nav aria-label="เอกสารแนบ" class="ac-source-tabs">
 <?php if(!empty($submission['file_path'])): ?><a <?= $source==='file'?'aria-current="page"':'' ?> href="<?= ac_h($url.'&source=file') ?>">ไฟล์ PDF</a><?php endif; ?>
 <?php if(!empty($submission['drive_link'])): ?><a <?= $source==='link'?'aria-current="page"':'' ?> href="<?= ac_h($url.'&source=link') ?>"><?= ac_h($external['label']??'ลิงก์ที่แนบ') ?></a><?php endif; ?></nav>
 <?php if($original): ?><a class="ac-button" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" href="<?= ac_h($original) ?>">เปิดต้นฉบับในแท็บใหม่ ↗</a><?php endif; ?></div>
 <?php if($frame): ?><iframe class="ac-preview-frame <?= ($external['label']??'')==='YouTube'&&$source==='link'?'ac-video':'' ?>" title="<?= ac_h('Preview '.$type['title']) ?>" src="<?= ac_h($frame) ?>" referrerpolicy="no-referrer" <?= $source==='link'?'sandbox="allow-scripts allow-same-origin allow-presentation" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen':'' ?>></iframe>
 <p class="ac-preview-help">หากเอกสารไม่แสดง ให้เลือก “เปิดต้นฉบับในแท็บใหม่”<?= $source==='link'?' การแสดงลิงก์ภายนอกขึ้นอยู่กับสิทธิ์เข้าถึงเดิมของเอกสาร':'' ?></p>
 <?php else: ?><div class="ac-empty"><h2><?= $source==='file'?'ไม่พบไฟล์ PDF ที่แสดงตัวอย่างได้':'ลิงก์นี้เปิดดูผ่านต้นฉบับ' ?></h2><p><?= $source==='file'?'รายการและผลการส่งงานยังอยู่ครบ หากมีลิงก์แนบให้เลือกดูได้จากแถบด้านบน':($external?'เลือกเปิดต้นฉบับในแท็บใหม่เพื่อดูงาน โดยไม่เปลี่ยนลิงก์ที่บันทึกไว้':'ยังไม่มีลิงก์ที่รองรับการเปิดดู ข้อมูลเดิมไม่ได้ถูกแก้ไข') ?></p></div><?php endif; ?>
 </section>
 <?php endif; ?>
</main>
<?php ac_end($branding); ?>
