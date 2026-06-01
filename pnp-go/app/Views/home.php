<?php
$mainClass = 'home-wrap';
ob_start();
?>

<div class="home-container">

    <!-- Header Brand -->
    <div class="home-brand">
        <div class="home-brand-icon">
            <svg viewBox="0 0 40 40" fill="none" width="30" height="30">
                <rect width="40" height="40" rx="10" fill="rgba(255,255,255,0.15)"/>
                <path d="M12 28h16l-2-6a2 2 0 00-1.9-1.4h-8.2A2 2 0 0014 22l-2 6Z" fill="white"/>
                <circle cx="16" cy="30" r="2" fill="white"/>
                <circle cx="24" cy="30" r="2" fill="white"/>
                <path d="M20 10v6M17 13h6" stroke="white" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div>
            <div class="home-brand-title">PNP Go</div>
            <div class="home-brand-sub">ระบบขออนุญาตใช้รถยนต์และสั่งซื้อน้ำมันเชื้อเพลิง</div>
        </div>
    </div>

    <!-- Action Cards -->
    <div class="action-grid">

        <a href="<?= e(config('app')['base_path']) ?>/request" class="action-card action-primary" id="btn-request">
            <div class="action-icon-wrap" style="background:#dbeafe;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" width="32" height="32">
                    <rect x="3" y="4" width="18" height="16" rx="2"/>
                    <path d="M8 9h8M8 12h8M8 15h5" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="action-label">ยื่นคำขอใช้รถ</div>
            <div class="action-desc">กรอกแบบฟอร์มออนไลน์</div>
        </a>

        <a href="<?= e(config('app')['base_path']) ?>/status" class="action-card" id="btn-status">
            <div class="action-icon-wrap" style="background:#d1fae5;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" width="32" height="32">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m21 21-3.5-3.5" stroke-linecap="round"/>
                    <path d="M11 8v3l2 2" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="action-label">ตรวจสอบสถานะ</div>
            <div class="action-desc">ค้นหาด้วย Tracking ID</div>
        </a>

        <a href="<?= e(config('app')['base_path']) ?>/login" class="action-card" id="btn-login">
            <div class="action-icon-wrap" style="background:#ede9fe;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" width="32" height="32">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="action-label">เข้าสู่ระบบ</div>
            <div class="action-desc">สำหรับผู้อนุมัติ</div>
        </a>

        <a href="<?= e(config('app')['base_path']) ?>/vehicles" class="action-card" id="btn-vehicles">
            <div class="action-icon-wrap" style="background:#fef9c3;">
                <svg viewBox="0 0 24 24" fill="none" width="32" height="32">
                    <rect x="2" y="10" width="20" height="9" rx="2.5" fill="#fbbf24" opacity=".3"/>
                    <path d="M5 10 C5 10 7 5 10 5 L14 5 C17 5 19 10 19 10" fill="#fbbf24" opacity=".7"/>
                    <rect x="2" y="14" width="20" height="5" rx="2" fill="#f59e0b" opacity=".5"/>
                    <rect x="3" y="6" width="6" height="5" rx="1" fill="#fef08a" opacity=".9"/>
                    <rect x="15" y="6" width="6" height="5" rx="1" fill="#fef08a" opacity=".9"/>
                    <circle cx="7" cy="19" r="2.5" fill="#92400e"/>
                    <circle cx="17" cy="19" r="2.5" fill="#92400e"/>
                    <circle cx="7" cy="19" r="1" fill="#d97706"/>
                    <circle cx="17" cy="19" r="1" fill="#d97706"/>
                    <circle cx="20" cy="11" r="1.5" fill="#86efac"/>
                </svg>
            </div>
            <div class="action-label">สถานะรถยนต์</div>
            <div class="action-desc">ดูว่าคันไหนว่าง/ใช้งาน</div>
        </a>

    </div><!-- end action-grid -->
    <div class="home-note">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16" style="color:#6b7280;flex-shrink:0;margin-top:2px;">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span>หลังยื่นคำขอ ระบบจะออก <strong>Tracking ID</strong> ให้ใช้ตรวจสอบสถานะการอนุมัติได้ทุกเวลา</span>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
