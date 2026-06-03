<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">ตั้งค่าระบบ</h1>
        <p class="text-secondary mb-0 small">ปรับแต่งชื่อระบบขอใช้รถยนต์</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(config('app')['base_path']) ?>/dashboard">🏠 กลับแดชบอร์ด</a>
</div>

<?php if (isset($_GET['saved']) && $_GET['saved'] === 'success'): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-3">
        <span class="fs-5">✅</span>
        <div class="small"><strong>บันทึกสำเร็จ</strong> — อัปเดตชื่อระบบเรียบร้อยแล้ว</div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-7 mx-auto">
        <section class="form-section">
            <h2 class="section-title mb-3">🔧 ข้อมูลระบบ</h2>

            <form method="post" action="<?= e(config('app')['base_path']) ?>/dashboard/settings">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="mb-3">
                    <label for="system_name" class="form-label fw-semibold">ชื่อระบบ</label>
                    <input type="text" class="form-control <?= isset($errors['system_name']) ? 'is-invalid' : '' ?>" id="system_name" name="system_name" value="<?= e($settings['system_name'] ?? 'PNP Go') ?>" placeholder="เช่น PNP Go หรือ ระบบขอใช้รถยนต์ส่วนกลาง">
                    <?php if (isset($errors['system_name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['system_name']) ?></div>
                    <?php endif; ?>
                    <div class="form-text text-muted">ชื่อนี้จะปรากฏที่แถบนำทาง (Navbar) และหัวเรื่องของระบบ</div>
                </div>

                <div class="border-top pt-3 text-end">
                    <button type="submit" class="btn btn-primary fw-bold">💾 บันทึกการตั้งค่า</button>
                </div>
            </form>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
?>
