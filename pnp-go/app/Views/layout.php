<?php
$app = config('app');
$sysSet = system_settings();
$colSet = college_settings();

$themeColor = $sysSet['theme_color'] ?? 'rose';
$systemName = $sysSet['system_name'] ?? 'PNP Go';
$collegeName = $colSet['college_name'] ?? 'วิทยาลัยการอาชีพพนมไพร';
$logoPath = $colSet['logo_path'] ?? '';

$pageTitle = isset($title) ? $title . ' | ' . $systemName : $systemName;
$cssFiles = [__DIR__ . '/../../public/assets/app.css', __DIR__ . '/../../public/assets/home.css'];
$assetVersion = max(array_map(fn ($f) => file_exists($f) ? filemtime($f) : 0, $cssFiles)) ?: time();
$mainClass = isset($mainClass) ? $mainClass : 'container app-shell py-4 py-md-5';

$user = current_user();

function central_portal_base(): string
{
    $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
    return $isLocal ? '/pnp-portal' : '';
}

$logoSrc = !empty($logoPath) ? (central_portal_base() . '/' . $logoPath) : ($app['base_path'] . '/public/assets/logo.png');

$themeVariables = [
    'rose' => [
        'primary' => '#8b1a2b',
        'primary_gradient' => 'linear-gradient(135deg, #6b1525 0%, #8b1a2b 40%, #7a1827 70%, #5c1220 100%)',
        'accent' => '#e8a0a0',
        'accent_hover' => '#c0494e'
    ],
    'indigo' => [
        'primary' => '#4f46e5',
        'primary_gradient' => 'linear-gradient(135deg, #4338ca 0%, #4f46e5 40%, #3730a3 70%, #1e1b4b 100%)',
        'accent' => '#c7d2fe',
        'accent_hover' => '#818cf8'
    ],
    'emerald' => [
        'primary' => '#059669',
        'primary_gradient' => 'linear-gradient(135deg, #047857 0%, #059669 40%, #065f46 70%, #064e3b 100%)',
        'accent' => '#a7f3d0',
        'accent_hover' => '#34d399'
    ],
    'sky' => [
        'primary' => '#0284c7',
        'primary_gradient' => 'linear-gradient(135deg, #0369a1 0%, #0284c7 40%, #075985 70%, #0c4a6e 100%)',
        'accent' => '#bae6fd',
        'accent_hover' => '#38bdf8'
    ],
    'amber' => [
        'primary' => '#d97706',
        'primary_gradient' => 'linear-gradient(135deg, #b45309 0%, #d97706 40%, #92400e 70%, #78350f 100%)',
        'accent' => '#fde68a',
        'accent_hover' => '#fbbf24'
    ],
    'slate' => [
        'primary' => '#475569',
        'primary_gradient' => 'linear-gradient(135deg, #334155 0%, #475569 40%, #1e293b 70%, #0f172a 100%)',
        'accent' => '#cbd5e1',
        'accent_hover' => '#94a3b8'
    ]
];

// โทนทางการเดียวของระบบ: น้ำเงินกรมท่า (navy) ให้สอดคล้องกับ home.css ทั้งหมด
// (ยกเลิกการชนกันระหว่างธีม rose ที่ฉีดจาก layout กับ navy ใน home.css)
$activeVars = [
    'primary'          => '#1e3a8a',
    'primary_gradient' => '#1e3a8a',
    'accent'           => '#dbeafe',
    'accent_hover'     => '#1d4ed8',
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($systemName) ?> - <?= e($collegeName) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e($app['base_path']) ?>/public/assets/app.css?v=<?= e((string) $assetVersion) ?>" rel="stylesheet">
    <link href="<?= e($app['base_path']) ?>/public/assets/home.css?v=<?= e((string) $assetVersion) ?>" rel="stylesheet">
    <style>
    :root {
        --theme-primary: <?= $activeVars['primary'] ?>;
        --theme-gradient: <?= $activeVars['primary_gradient'] ?>;
        --theme-accent: <?= $activeVars['accent'] ?>;
        --theme-accent-hover: <?= $activeVars['accent_hover'] ?>;
    }
    
    /* ===== โทนทางการ navy: header ทึบเรียบ + คุมสีลิงก์/หัวข้อ (ปล่อยให้ home.css คุมปุ่ม) ===== */
    .site-header {
        background: var(--theme-primary) !important;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
    }
    .brand-link { text-decoration: none; }
    .text-primary, .brand-link:hover .brand-name {
        color: var(--theme-primary) !important;
    }
    .btn-outline-primary {
        color: var(--theme-primary) !important;
        border-color: var(--theme-primary) !important;
    }
    .btn-outline-primary:hover {
        background-color: var(--theme-primary) !important;
        color: #fff !important;
    }
    a { color: var(--brand-secondary); }
    a:hover { color: var(--theme-accent-hover); }
    /* เมนู/ชื่อผู้ใช้บนแถบ header navy ต้องเป็นสีอ่อนเสมอ (กัน .text-primary/.text-secondary ทำให้กลืนพื้น) */
    .site-header .main-nav .nav-item:not(.nav-item-btn) { color: rgba(255, 255, 255, 0.85) !important; }
    .site-header .main-nav .nav-item:not(.nav-item-btn):hover { color: #ffffff !important; }
    .site-header .user-profile-menu .text-secondary { color: rgba(255, 255, 255, 0.9) !important; }
    </style>
</head>
<body>

    <header class="site-header" id="site-header">
        <div class="header-inner">
            <a class="brand-link" href="<?= e($app['base_path']) ?>/">
                <div class="brand-logo-img">
                    <img src="<?= e($logoSrc) ?>" alt="<?= e($collegeName) ?>" width="44" height="44">
                </div>
                <span class="brand-text">
                    <span class="brand-name"><?= e($systemName) ?></span>
                    <span class="brand-org"><?= e($collegeName) ?></span>
                </span>
            </a>

            <nav class="main-nav d-none d-lg-flex align-items-center gap-3">
                <a class="nav-item text-secondary fw-medium" href="<?= e(central_portal_base()) ?>/">🏠 กลับสู่พอร์ทัลหลัก</a>
                <a class="nav-item fw-medium" href="<?= e($app['base_path']) ?>/status">ตรวจสอบสถานะ</a>
                <?php if ($user): ?>
                    <?php if ($user['role'] === 'user'): ?>
                        <a class="nav-item fw-medium text-primary" href="<?= e($app['base_path']) ?>/dashboard">แดชบอร์ดของฉัน</a>
                    <?php else: ?>
                        <a class="nav-item fw-medium text-primary" href="<?= e($app['base_path']) ?>/dashboard">แดชบอร์ดอนุมัติ</a>
                    <?php endif; ?>
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
            <a href="<?= e($app['base_path']) ?>/request" class="mobile-cta">➕ ยื่นคำขอใช้รถ</a>
            <a href="<?= e(central_portal_base()) ?>/">🏠 กลับสู่พอร์ทัลหลัก</a>
            <a href="<?= e($app['base_path']) ?>/status">ตรวจสอบสถานะ</a>
            <?php if ($user): ?>
                <?php if ($user['role'] === 'user'): ?>
                    <a href="<?= e($app['base_path']) ?>/dashboard">แดชบอร์ดของฉัน</a>
                <?php else: ?>
                    <a href="<?= e($app['base_path']) ?>/dashboard">แดชบอร์ดอนุมัติ</a>
                <?php endif; ?>
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
