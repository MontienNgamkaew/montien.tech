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
                <?php
                    if (str_contains($type, 'ตู้') || str_contains($type, 'ตู')) { $vIcon = 'van.png'; }
                    elseif (str_contains($type, 'หกล้อ') || str_contains($type, 'บรรทุก')) { $vIcon = 'truck.png'; }
                    else { $vIcon = 'car.png'; }
                ?>
                <img src="<?= e(config('app')['base_path']) ?>/public/assets/icons/<?= $vIcon ?>" alt="<?= e($type) ?>" class="vcard-icon-img" width="60" height="60" loading="lazy">
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
