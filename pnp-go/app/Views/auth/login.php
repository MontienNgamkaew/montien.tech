<?php ob_start(); ?>
<section class="form-section login-panel mx-auto">
    <h1 class="h3 mb-1">เข้าสู่ระบบผู้อนุมัติ</h1>
    <p class="text-secondary mb-4">สำหรับหัวหน้างานพัสดุ รองผู้อำนวยการ ผู้อำนวยการ และผู้ดูแลระบบ</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(config('app')['base_path']) ?>/login" class="d-grid gap-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div>
            <label class="form-label" for="username">ชื่อผู้ใช้</label>
            <input class="form-control form-control-lg" id="username" name="username" value="<?= e($username) ?>" autocomplete="username">
        </div>
        <div>
            <label class="form-label" for="password">รหัสผ่าน</label>
            <input type="password" class="form-control form-control-lg" id="password" name="password" autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-lg" type="submit">เข้าสู่ระบบ</button>
    </form>

    <div class="helper-note mt-4">
        บัญชีทดสอบ: <strong>supply</strong>, <strong>deputy</strong>, <strong>director</strong>, <strong>admin</strong><br>
        รหัสผ่านเริ่มต้น: <strong>password123</strong>
    </div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
