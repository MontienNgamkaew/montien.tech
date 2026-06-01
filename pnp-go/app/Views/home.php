<?php
$mainClass = 'home-wrap';
$sysSet = system_settings();
$colSet = college_settings();

$systemName = $sysSet['system_name'] ?? 'PNP Go';
$collegeName = $colSet['college_name'] ?? 'วิทยาลัยการอาชีพพนมไพร';
$logoPath = $colSet['logo_path'] ?? '';
$logoSrc = !empty($logoPath) ? (central_portal_base() . '/' . $logoPath) : (config('app')['base_path'] . '/public/assets/logo.png');

ob_start();
?>

<style>
/* Modern premium glassmorphism styling matching PNP Man */
body {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e2e8f0 100%) !important;
}

.home-container {
    max-width: 880px;
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.05);
    margin: 20px auto 40px;
    transition: all 0.3s ease;
}

.home-brand {
    background: var(--theme-gradient);
    color: white;
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 35px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.home-brand-icon-wrapper {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    padding: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.home-brand-title {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    font-family: 'Sarabun', sans-serif;
}

.home-brand-sub {
    font-size: 14px;
    opacity: 0.9;
    font-weight: 500;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.action-card {
    background: rgba(255, 255, 255, 0.85) !important;
    border: 1px solid rgba(255, 255, 255, 0.7) !important;
    border-radius: 18px;
    padding: 24px;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.action-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08) !important;
    border-color: var(--theme-primary) !important;
    background: #ffffff !important;
}

.action-primary {
    border-top: 4px solid var(--theme-primary) !important;
}

.action-icon-wrap {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    transition: all 0.3s ease;
}

.action-card:hover .action-icon-wrap {
    transform: scale(1.1);
}

.action-label {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 6px;
}

.action-desc {
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
}

.home-note {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 12px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #475569;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.01);
}

.home-note strong {
    color: var(--theme-primary);
}

@media (max-width: 576px) {
    .home-container {
        padding: 24px 16px;
        margin: 10px;
    }
    .home-brand {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    .action-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .action-card {
        padding: 16px 12px;
    }
}
</style>

<div class="home-container">

    <!-- Header Brand -->
    <div class="home-brand">
        <div class="home-brand-icon-wrapper">
            <img src="<?= e($logoSrc) ?>" alt="<?= e($collegeName) ?>" width="52" height="52" style="object-fit: contain;">
        </div>
        <div>
            <div class="home-brand-title"><?= e($systemName) ?></div>
            <div class="home-brand-sub">ศูนย์รวมการจองยานพาหนะส่วนกลางและบริหารน้ำมันเชื้อเพลิง | <?= e($collegeName) ?></div>
        </div>
    </div>

    <!-- Action Cards -->
    <div class="action-grid">

        <a href="<?= e(config('app')['base_path']) ?>/request" class="action-card action-primary" id="btn-request">
            <div class="action-icon-wrap" style="background: rgba(235, 245, 255, 0.8);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--theme-primary)" stroke-width="2" width="32" height="32">
                    <rect x="3" y="4" width="18" height="16" rx="2"/>
                    <path d="M8 9h8M8 12h8M8 15h5" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="action-label">ยื่นคำขอใช้รถ</div>
            <div class="action-desc">เขียนคำขอและจองรถผ่านระบบ</div>
        </a>

        <a href="<?= e(config('app')['base_path']) ?>/status" class="action-card" id="btn-status">
            <div class="action-icon-wrap" style="background: rgba(209, 250, 229, 0.8);">
                <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" width="32" height="32">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m21 21-3.5-3.5" stroke-linecap="round"/>
                    <path d="M11 8v3l2 2" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="action-label">ตรวจสอบสถานะ</div>
            <div class="action-desc">ค้นหารายละเอียดคิวคำขอจอง</div>
        </a>

        <a href="<?= e(config('app')['base_path']) ?>/login" class="action-card" id="btn-login">
            <div class="action-icon-wrap" style="background: rgba(237, 233, 254, 0.8);">
                <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" width="32" height="32">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="action-label">เข้าสู่ระบบ</div>
            <div class="action-desc">ตรวจสอบคิวงานและอนุมัติ</div>
        </a>

        <a href="<?= e(config('app')['base_path']) ?>/vehicles" class="action-card" id="btn-vehicles">
            <div class="action-icon-wrap" style="background: rgba(254, 249, 195, 0.8);">
                <svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" width="32" height="32">
                    <rect x="2" y="10" width="20" height="9" rx="2"/>
                    <circle cx="6.5" cy="19" r="2.5"/>
                    <circle cx="17.5" cy="19" r="2.5"/>
                    <path d="M10 5h4l4 5H6l4-5z"/>
                </svg>
            </div>
            <div class="action-label">สถานะรถยนต์</div>
            <div class="action-desc">กระดานเช็คคิวและสถิติรถว่าง</div>
        </a>

    </div><!-- end action-grid -->

    <div class="home-note">
        <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="color: var(--theme-primary); flex-shrink:0; margin-top:2px;">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span>หลังล็อกอินหรือเขียนคำขอจอง ระบบจะออกรหัส <strong>เลขที่เอกสาร (เช่น GO-260601-001)</strong> เพื่อใช้ติดตามสถานะคิวคำขอและตรวจสอบได้สะดวกรวดเร็วครับ</span>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
