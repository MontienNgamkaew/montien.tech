<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">โปรไฟล์และลายเซ็น</h1>
        <p class="text-secondary mb-0"><?= e($user['full_name']) ?> | <?= e(role_label($user['role'])) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(config('app')['base_path']) ?>/dashboard">กลับแดชบอร์ด</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="form-section">
    <h2 class="section-title">อัปโหลดลายเซ็น PNG</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="text-secondary">ชื่อผู้ใช้</div>
            <div class="fw-semibold"><?= e($user['username']) ?></div>
        </div>
        <div class="col-md-6">
            <div class="text-secondary">ตำแหน่ง</div>
            <div class="fw-semibold"><?= e($user['position_title']) ?></div>
        </div>
        <div class="col-12">
            <div class="text-secondary">ไฟล์ลายเซ็นปัจจุบัน</div>
            <div class="fw-semibold"><?= e($user['signature_path'] ?: 'ยังไม่ได้อัปโหลด') ?></div>
        </div>
    </div>

    <form method="post" action="<?= e(config('app')['base_path']) ?>/profile/signature" enctype="multipart/form-data" class="mt-4 d-grid gap-3" data-confirm data-confirm-title="บันทึกลายเซ็น" data-confirm-text="ต้องการอัปโหลดและบันทึกลายเซ็นนี้หรือไม่" data-confirm-button="บันทึก">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div>
            <label class="form-label" for="signature">ไฟล์ลายเซ็น .png แบบพื้นหลังโปร่งใส</label>
            <input class="form-control" type="file" id="signature" name="signature" accept="image/png">
        </div>
        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">บันทึกลายเซ็น</button>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
