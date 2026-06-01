<?php ob_start(); ?>

<div style="max-width:1000px;margin:0 auto;padding:16px;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:8px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#1e3a5f;margin:0;">🚗 สถานะรถยนต์ปัจจุบัน</h1>
            <p style="margin:2px 0 0;font-size:13px;color:#6b7280;">อัปเดต ณ <?= e(date('d/m/Y H:i')) ?> น.</p>
        </div>
        <a href="<?= e(config('app')['base_path']) ?>/" style="font-size:13px;color:#2563eb;text-decoration:none;">← กลับหน้าแรก</a>
    </div>

    <!-- Vehicle Status Grid -->
    <div class="vehicle-board" style="margin-bottom:28px;">
        <?php foreach ($vehicles as $v):
            $inUse = !empty($v['req_id']);
            $type  = $v['vehicle_type'] ?? '';
        ?>
        <div class="vcard <?= $inUse ? 'vcard-busy' : 'vcard-free' ?>">
            <div class="vcard-icon">
                <?php if (str_contains($type, 'ตู้') || str_contains($type, 'ตู')): ?>
                <svg viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg" width="68" height="44">
                    <defs><linearGradient id="pb-van-body" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#2563eb"/></linearGradient><linearGradient id="pb-van-roof" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#93c5fd"/><stop offset="100%" stop-color="#60a5fa"/></linearGradient></defs>
                    <ellipse cx="40" cy="50" rx="34" ry="3" fill="#00000020"/>
                    <rect x="4" y="18" width="72" height="26" rx="5" fill="url(#pb-van-body)"/>
                    <rect x="6" y="10" width="68" height="12" rx="4" fill="url(#pb-van-roof)"/>
                    <rect x="9" y="12" width="18" height="9" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <rect x="30" y="12" width="14" height="9" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <rect x="47" y="12" width="14" height="9" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <rect x="4" y="22" width="72" height="3" rx="1" fill="white" opacity=".2"/>
                    <rect x="5" y="26" width="9" height="6" rx="2" fill="#fef08a"/><rect x="66" y="26" width="9" height="6" rx="2" fill="#fca5a5"/>
                    <rect x="4" y="38" width="72" height="4" rx="2" fill="#1d4ed8"/>
                    <circle cx="18" cy="44" r="7" fill="#374151"/><circle cx="18" cy="44" r="4" fill="#6b7280"/><circle cx="18" cy="44" r="2" fill="#d1d5db"/>
                    <circle cx="62" cy="44" r="7" fill="#374151"/><circle cx="62" cy="44" r="4" fill="#6b7280"/><circle cx="62" cy="44" r="2" fill="#d1d5db"/>
                </svg>
                <?php elseif (str_contains($type, 'หกล้อ') || str_contains($type, 'บรรทุก')): ?>
                <svg viewBox="0 0 88 52" fill="none" xmlns="http://www.w3.org/2000/svg" width="72" height="44">
                    <defs><linearGradient id="pb-truck-cab" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fb923c"/><stop offset="100%" stop-color="#ea580c"/></linearGradient><linearGradient id="pb-truck-cargo" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/></linearGradient></defs>
                    <ellipse cx="44" cy="50" rx="38" ry="3" fill="#00000020"/>
                    <rect x="2" y="10" width="52" height="32" rx="3" fill="url(#pb-truck-cargo)"/>
                    <line x1="2" y1="20" x2="54" y2="20" stroke="#b45309" stroke-width="1"/><line x1="2" y1="30" x2="54" y2="30" stroke="#b45309" stroke-width="1"/>
                    <line x1="18" y1="10" x2="18" y2="42" stroke="#b45309" stroke-width="1"/><line x1="36" y1="10" x2="36" y2="42" stroke="#b45309" stroke-width="1"/>
                    <rect x="54" y="20" width="30" height="22" rx="4" fill="url(#pb-truck-cab)"/>
                    <path d="M54 24 Q56 16 64 14 L82 14 L84 20 Z" fill="#fb923c"/>
                    <rect x="58" y="16" width="20" height="11" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <rect x="80" y="28" width="6" height="5" rx="1.5" fill="#fef08a"/>
                    <circle cx="14" cy="44" r="7" fill="#374151"/><circle cx="14" cy="44" r="4" fill="#6b7280"/><circle cx="14" cy="44" r="2" fill="#d1d5db"/>
                    <circle cx="42" cy="44" r="7" fill="#374151"/><circle cx="42" cy="44" r="4" fill="#6b7280"/><circle cx="42" cy="44" r="2" fill="#d1d5db"/>
                    <circle cx="72" cy="44" r="7" fill="#374151"/><circle cx="72" cy="44" r="4" fill="#6b7280"/><circle cx="72" cy="44" r="2" fill="#d1d5db"/>
                </svg>
                <?php else: ?>
                <svg viewBox="0 0 80 50" fill="none" xmlns="http://www.w3.org/2000/svg" width="68" height="43">
                    <defs><linearGradient id="pb-car-body" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/></linearGradient><linearGradient id="pb-car-roof" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#6ee7b7"/><stop offset="100%" stop-color="#34d399"/></linearGradient></defs>
                    <ellipse cx="40" cy="48" rx="32" ry="3" fill="#00000020"/>
                    <rect x="4" y="26" width="72" height="16" rx="5" fill="url(#pb-car-body)"/>
                    <path d="M18 26 C18 26 24 12 32 10 L48 10 C56 10 62 26 62 26 Z" fill="url(#pb-car-roof)"/>
                    <path d="M20 26 C20 26 25 14 32 12 L36 12 L36 26 Z" fill="#a7f3d0" opacity=".85"/>
                    <path d="M60 26 C60 26 55 14 48 12 L44 12 L44 26 Z" fill="#a7f3d0" opacity=".85"/>
                    <rect x="36" y="13" width="8" height="13" rx="1" fill="#a7f3d0" opacity=".85"/>
                    <rect x="4" y="29" width="72" height="3" rx="1.5" fill="white" opacity=".2"/>
                    <rect x="5" y="30" width="10" height="6" rx="2" fill="#fef08a"/><rect x="65" y="30" width="10" height="6" rx="2" fill="#fca5a5"/>
                    <rect x="4" y="38" width="72" height="3" rx="1.5" fill="#047857"/>
                    <circle cx="18" cy="42" r="7" fill="#1f2937"/><circle cx="18" cy="42" r="4.5" fill="#4b5563"/><circle cx="18" cy="42" r="2" fill="#d1d5db"/>
                    <circle cx="62" cy="42" r="7" fill="#1f2937"/><circle cx="62" cy="42" r="4.5" fill="#4b5563"/><circle cx="62" cy="42" r="2" fill="#d1d5db"/>
                </svg>
                <?php endif; ?>
            </div>
            <div class="vcard-body">
                <div class="vcard-name"><?= e($v['vehicle_name']) ?></div>
                <div class="vcard-plate"><?= e($v['license_plate']) ?></div>
                <div class="vcard-status-badge <?= $inUse ? 'badge-busy' : 'badge-free' ?>">
                    <?= $inUse ? '● กำลังใช้งาน' : '● ว่าง' ?>
                </div>
                <?php if ($inUse): ?>
                <div class="vcard-info">
                    <div class="vcard-info-row"><span class="vcard-info-icon">👤</span><?= e($v['requester_name']) ?></div>
                    <div class="vcard-info-row"><span class="vcard-info-icon">📍</span><?= e(mb_strimwidth($v['destination'], 0, 26, '…')) ?></div>
                    <div class="vcard-info-row"><span class="vcard-info-icon">🕐</span>คืนรถ <?= e(date('d/m H:i', strtotime($v['travel_end_at']))) ?> น.</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($vehicles)): ?>
            <p style="color:#6b7280;">ยังไม่มีข้อมูลรถในระบบ</p>
        <?php endif; ?>
    </div>

    <!-- Recent Requests -->
    <div class="form-section">
        <h2 class="section-title" style="margin-bottom:14px;">📋 คำขอล่าสุด</h2>
        <?php if (empty($recentRequests)): ?>
            <p style="color:#6b7280;">ยังไม่มีคำขอ</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>ผู้ขอ</th>
                        <th>สถานที่</th>
                        <th>วันออกเดินทาง</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentRequests as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['tracking_id']) ?></td>
                        <td><?= e($r['requester_name']) ?></td>
                        <td><?= e(mb_strimwidth($r['destination'], 0, 28, '…')) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($r['travel_start_at']))) ?></td>
                        <td><span class="status-pill <?= e(status_badge_class($r['status'])) ?>"><?= e($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
