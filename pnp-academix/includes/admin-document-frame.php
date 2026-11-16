<?php
// Rendering only: caller authorizes and loads $submission before this include.
require_once __DIR__.'/preview-support.php';
$pdf=ac_pdf_source($submission['file_path']??null,[dirname(__DIR__),upload_base_path('pnp-academix')]);
$external=ac_external_source($submission['drive_link']??null);
$requested=is_string($_GET['source']??null)?$_GET['source']:'';
$source=$requested==='file'?'file':($requested==='link'?'link':($external?'link':($pdf?'file':(!empty($submission['drive_link'])?'link':'file'))));
$frame=$source==='file'?($pdf?ac_base().'/admin/preview.php?submission_id='.(int)$submission['id'].'&raw=1':null):($external['embed']??null);
$original=$source==='file'?$frame:($external['url']??null);
?>
<section class="ac-panel ac-preview-panel" aria-label="Preview เอกสาร"><div class="ac-preview-toolbar"><nav class="ac-source-tabs" aria-label="เอกสารแนบ">
<?php if(!empty($submission['file_path'])): ?><a <?= $source==='file'?'aria-current="page"':'' ?> href="<?= ac_h($documentUrl.'&source=file') ?>">ไฟล์ PDF</a><?php endif; ?>
<?php if(!empty($submission['drive_link'])): ?><a <?= $source==='link'?'aria-current="page"':'' ?> href="<?= ac_h($documentUrl.'&source=link') ?>"><?= ac_h($external['label']??'ลิงก์ที่แนบ') ?></a><?php endif; ?>
</nav><?php if($original): ?><a class="ac-button" href="<?= ac_h($original) ?>" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">เปิดต้นฉบับ ↗</a><?php endif; ?></div>
<?php if($frame): ?><iframe class="ac-preview-frame <?= $source==='link'&&($external['label']??'')==='YouTube'?'ac-video':'' ?>" title="Preview เอกสารที่ส่ง" src="<?= ac_h($frame) ?>" referrerpolicy="no-referrer" <?= $source==='link'?'sandbox="allow-scripts allow-same-origin allow-presentation" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen':'' ?>></iframe><p class="ac-preview-help">หากเอกสารไม่แสดง ให้เปิดต้นฉบับในแท็บใหม่ ลิงก์ภายนอกใช้สิทธิ์เข้าถึงเดิมของเอกสาร</p>
<?php else: ?><div class="ac-empty"><h2><?= $source==='file'?'ไม่พบไฟล์ PDF ที่เปิดดูได้':'เปิดดูผ่านลิงก์ต้นฉบับ' ?></h2><p><?= $original?'เลือกเปิดต้นฉบับเพื่อตรวจเอกสาร': 'รายการส่งงานยังคงอยู่ หากมีเอกสารแนบอีกแบบให้เลือกจากแถบด้านบน หรือติดต่อครูเพื่อตรวจสอบต้นฉบับ' ?></p></div><?php endif; ?>
</section>
