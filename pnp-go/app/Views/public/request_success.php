<?php ob_start(); ?>
<div data-swal-success data-swal-title="ส่งคำขอสำเร็จ" data-swal-text="กรุณาเก็บ Tracking ID ไว้สำหรับตรวจสอบสถานะ"></div>
<section class="form-section text-center">
    <h1 class="h3 mb-3">ส่งคำขอสำเร็จ</h1>
    <p class="text-secondary mb-2">กรุณาเก็บ Tracking ID นี้ไว้สำหรับตรวจสอบสถานะ</p>
    <div class="helper-note d-inline-block display-6 fw-bold mb-4 px-4"><?= e($trackingId) ?></div>
    <div class="d-flex flex-wrap gap-2 justify-content-center">
        <a class="btn btn-primary" href="<?= e(config('app')['base_path']) ?>/status">ตรวจสอบสถานะ</a>
        <a class="btn btn-outline-secondary" href="<?= e(config('app')['base_path']) ?>/request">ยื่นคำขอใหม่</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
