<?php
declare(strict_types=1);
require_once __DIR__.'/admin-support.php';
$successMessage=(string)($_SESSION['success_flash']??'');unset($_SESSION['success_flash']);
ac_start('งานของครู · '.$teacher['fullname'],$branding);ac_header($branding,'admin');
?>
<main class="ac-main" id="ac-main"><?php ac_admin_navigation(); ?><a class="ac-back" href="overview.php">← กลับภาพรวมการส่งงาน</a>
<div class="ac-page-heading"><div><p class="ac-eyebrow">Teacher submissions</p><h1><?= ac_h($teacher['fullname']) ?></h1><p><?= ac_h($teacher['department']??'ไม่ระบุแผนก') ?> · <?= count($courses) ?> รายวิชา</p></div><span class="ac-term">ภาคเรียน <?= ac_h($semester['semester_name']) ?></span></div>
<?php if($successMessage): ?><div class="ac-notice success" role="status"><?= ac_h($successMessage) ?></div><?php endif; ?>
<p class="ac-summary-note">โครงการสอนส่งทุกรายวิชา · แผนการสอนและสื่อการสอนอย่างละ 1 รายวิชาเป็นอย่างน้อย เลือกตรวจพิจารณาเพื่อดูเอกสารพร้อมบันทึกผลตามสิทธิ์ของคุณ</p>
<div class="ac-courses">
<?php foreach($courses as $course): ?><article class="ac-panel ac-course" id="course-<?= (int)$course['id'] ?>"><header class="ac-course-heading"><span class="ac-course-code"><?= ac_h($course['course_code']) ?></span><h2><?= ac_h($course['course_name']) ?></h2></header><div class="ac-document-grid">
<?php foreach(AC_DOCUMENT_TYPES as $kind=>$type):$p=$type['prefix'];$sid=(int)($course[$p.'_sub_id']??0); ?><section class="ac-document-cell"><h3><?= $type['title'] ?></h3><div class="ac-badges"><?= ac_badge($course[$p.'_status'],$course[$p.'_timing']) ?></div><p class="ac-submission-time"><?= $sid?'ส่งเมื่อ '.ac_h(ac_date($course[$p.'_submitted_at'])):'ยังไม่มีข้อมูลการส่งงาน' ?></p><?php if(!empty($course[$p.'_feedback'])): ?><details class="ac-feedback"><summary>ข้อเสนอแนะที่บันทึกไว้</summary><p><?= nl2br(ac_h($course[$p.'_feedback'])) ?></p></details><?php endif; ?>
<?php if($sid): ?><div class="ac-document-actions"><a class="ac-button primary" href="review.php?submission_id=<?= $sid ?>">ตรวจพิจารณา →</a><a class="ac-submit-link" href="preview.php?submission_id=<?= $sid ?>" target="_blank" rel="noopener">Preview ↗</a></div><?php endif; ?></section><?php endforeach; ?></div></article><?php endforeach; ?>
<?php if(!$courses): ?><div class="ac-panel ac-empty"><h2>ยังไม่มีรายวิชาในภาคเรียนนี้</h2><p>ข้อมูลรายวิชาและเอกสารภาคเรียนอื่นยังคงอยู่ในประวัติย้อนหลัง</p></div><?php endif; ?>
</div></main><?php ac_end($branding); ?>
