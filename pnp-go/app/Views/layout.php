<?php
$app = config('app');
$pageTitle = isset($title) ? $title . ' | ' . $app['name'] : $app['name'];
$assetVersion = file_exists(__DIR__ . '/../../public/assets/app.css') ? filemtime(__DIR__ . '/../../public/assets/app.css') : time();
$mainClass = isset($mainClass) ? $mainClass : 'container app-shell py-4 py-md-5';

$user = current_user();

function central_portal_base(): string
{
    $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
    return $isLocal ? '/pnp-portal' : '';
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="ระบบขอใช้รถยนต์ราชการและน้ำมันเชื้อเพลิง วิทยาลัยการอาชีพพนมไพร">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e($app['base_path']) ?>/public/assets/app.css?v=<?= e((string) $assetVersion) ?>" rel="stylesheet">
    <link href="<?= e($app['base_path']) ?>/public/assets/home.css?v=<?= e((string) $assetVersion) ?>" rel="stylesheet">
</head>
<body>

    <header class="site-header" id="site-header">
        <div class="header-inner">
            <a class="brand-link" href="<?= e($app['base_path']) ?>/">
                <div class="brand-logo-img">
                    <img src="<?= e($app['base_path']) ?>/public/assets/logo.png" alt="วิทยาลัยการอาชีพพนมไพร" width="44" height="44">
                </div>
                <span class="brand-text">
                    <span class="brand-name">ระบบขออนุญาตใช้รถยนต์/สั่งซื้อน้ำมันเชื้อเพลิง</span>
                    <span class="brand-org">วิทยาลัยการอาชีพพนมไพร</span>
                </span>
            </a>

            <nav class="main-nav d-none d-lg-flex align-items-center gap-3">
                <a class="nav-item text-secondary fw-medium" href="<?= e(central_portal_base()) ?>/">🏠 กลับสู่พอร์ทัลหลัก</a>
                <a class="nav-item fw-medium" href="<?= e($app['base_path']) ?>/request">ยื่นคำขอ</a>
                <a class="nav-item fw-medium" href="<?= e($app['base_path']) ?>/status">ตรวจสอบสถานะ</a>
                <?php if ($user): ?>
                    <a class="nav-item fw-medium text-primary" href="<?= e($app['base_path']) ?>/dashboard">แดชบอร์ดอนุมัติ</a>
                    <div class="user-profile-menu d-inline-flex align-items-center gap-2 border-start ps-3 ms-2">
                        <?php if ($user['avatar_path']): ?>
                            <img src="<?= e(central_portal_base() . '/' . $user['avatar_path']) ?>" class="rounded-circle border" width="32" height="32" alt="avatar" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-weight:600; font-size:14px;"><?= e(mb_substr($user['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <span class="text-secondary small fw-semibold"><?= e($user['full_name']) ?></span>
                        <form method="post" action="<?= e($app['base_path']) ?>/logout" class="d-inline mb-0">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger py-1" style="font-size: 11px; border-radius:6px;">ออก</button>
                        </form>
                    </div>
                <?php else: ?>
                    <a class="nav-item nav-item-btn" href="<?= e($app['base_path']) ?>/login">เข้าสู่ระบบ</a>
                <?php endif; ?>
            </nav>

            <button class="nav-toggle" id="navToggle" aria-label="เมนู">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
                    <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="mobile-nav" id="mobileNav">
            <a href="<?= e(central_portal_base()) ?>/">🏠 กลับสู่พอร์ทัลหลัก</a>
            <a href="<?= e($app['base_path']) ?>/request">ยื่นคำขอใช้รถ</a>
            <a href="<?= e($app['base_path']) ?>/status">ตรวจสอบสถานะ</a>
            <?php if ($user): ?>
                <a href="<?= e($app['base_path']) ?>/dashboard">แดชบอร์ดอนุมัติ</a>
                <div class="p-3 border-top mt-2">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <?php if ($user['avatar_path']): ?>
                            <img src="<?= e(central_portal_base() . '/' . $user['avatar_path']) ?>" class="rounded-circle border" width="36" height="36" alt="avatar" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:36px; height:36px; font-weight:600; font-size:16px;"><?= e(mb_substr($user['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <span class="text-dark fw-semibold"><?= e($user['full_name']) ?></span>
                    </div>
                    <form method="post" action="<?= e($app['base_path']) ?>/logout" class="d-grid">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="btn btn-danger">ออกจากระบบ</button>
                    </form>
                </div>
            <?php else: ?>
                <a href="<?= e($app['base_path']) ?>/login">เข้าสู่ระบบ (ผู้อนุมัติ)</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="<?= e($mainClass) ?>">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p class="footer-dept">งานพัสดุ ฝ่ายบริหารทรัพยากร วิทยาลัยการอาชีพพนมไพร</p>
            <p class="footer-copy">© <?= date('Y') ?> ระบบขออนุญาตใช้รถยนต์/สั่งซื้อน้ำมันเชื้อเพลิง</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= e($app['base_path']) ?>/public/assets/app.js"></script>
    <script>
        const navToggle = document.getElementById('navToggle');
        const mobileNav = document.getElementById('mobileNav');
        if (navToggle) {
            navToggle.addEventListener('click', () => {
                mobileNav.classList.toggle('open');
            });
        }
    </script>
</body>
</html>
