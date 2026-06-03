<?php ob_start(); ?>

<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <div>
        <h1 class="h3 mb-1">🚗 สถานะรถยนต์ปัจจุบัน</h1>
        <p class="text-secondary mb-0 small">อัปเดต ณ <?= e(date('d/m/Y H:i')) ?> น.</p>
    </div>
    <a href="<?= e(config('app')['base_path']) ?>/" class="btn btn-outline-secondary btn-sm">← กลับหน้าแรก</a>
</div>

<!-- Vehicle Status Grid -->
<div class="vehicle-board mb-4">
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
        <p class="text-muted">ยังไม่มีข้อมูลรถในระบบ</p>
    <?php endif; ?>
</div>

<!-- Recent Requests -->
<div class="form-section">
    <h2 class="section-title mb-3">📋 คำขอล่าสุด</h2>
    <?php if (empty($recentRequests)): ?>
        <p class="text-muted mb-0">ยังไม่มีคำขอ</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
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

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
