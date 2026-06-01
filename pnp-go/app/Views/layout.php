<?php
$app = config('app');
$pageTitle = isset($title) ? $title . ' | ' . $app['name'] : $app['name'];
$assetVersion = file_exists(__DIR__ . '/../../public/assets/app.css') ? filemtime(__DIR__ . '/../../public/assets/app.css') : time();
$mainClass = isset($mainClass) ? $mainClass : 'container app-shell py-4 py-md-5';
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

            <nav class="main-nav">
                <a class="nav-item" href="<?= e($app['base_path']) ?>/request">ยื่นคำขอ</a>
                <a class="nav-item" href="<?= e($app['base_path']) ?>/status">ตรวจสอบสถานะ</a>
                <a class="nav-item nav-item-btn" href="<?= e($app['base_path']) ?>/login">เข้าสู่ระบบ</a>
            </nav>

            <button class="nav-toggle" id="navToggle" aria-label="เมนู">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
                    <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="mobile-nav" id="mobileNav">
            <a href="<?= e($app['base_path']) ?>/request">ยื่นคำขอใช้รถ</a>
            <a href="<?= e($app['base_path']) ?>/status">ตรวจสอบสถานะ</a>
            <a href="<?= e($app['base_path']) ?>/login">เข้าสู่ระบบ (ผู้อนุมัติ)</a>
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
