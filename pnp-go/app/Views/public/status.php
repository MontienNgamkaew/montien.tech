<?php ob_start(); ?>
<div class="page-heading">
    <h1 class="h3 mb-1">ตรวจสอบสถานะคำขอ</h1>
    <p class="text-secondary mb-0">กรอก Tracking ID ที่ได้รับหลังส่งคำขอ</p>
</div>

<section class="form-section mb-3">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(config('app')['base_path']) ?>/status" class="row g-2 align-items-end">
        <div class="col-md-9">
            <label class="form-label" for="tracking_id">Tracking ID</label>
            <input class="form-control form-control-lg" id="tracking_id" name="tracking_id" value="<?= e($trackingId ?? '') ?>" placeholder="เช่น CR260514ABC123">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary btn-lg" type="submit">ตรวจสอบ</button>
        </div>
    </form>
</section>

<?php if ($result): ?>
    <section class="form-section">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
            <div>
                <h2 class="h5 mb-1">รายละเอียดคำขอ</h2>
                <div class="text-secondary">Tracking ID: <?= e($result['tracking_id']) ?></div>
            </div>
            <span class="status-pill <?= e(status_badge_class($result['status'])) ?>"><?= e($statusLabels[$result['status']] ?? $result['status']) ?></span>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-secondary">ผู้ขอ</div>
                <div class="fw-semibold"><?= e($result['requester_name']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary">ตำแหน่ง</div>
                <div class="fw-semibold"><?= e($result['requester_position']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary">สถานที่ไปราชการ</div>
                <div class="fw-semibold"><?= e($result['destination']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary">วันเวลาเดินทาง</div>
                <div class="fw-semibold"><?= e(date('d/m/Y H:i', strtotime($result['travel_start_at']))) ?> - <?= e(date('d/m/Y H:i', strtotime($result['travel_end_at']))) ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary">รถที่ได้รับมอบหมาย</div>
                <div class="fw-semibold">
                    <?php if ($result['vehicle_name']): ?>
                        <?= e($result['vehicle_name'] . ' - ' . $result['license_plate']) ?>
                    <?php else: ?>
                        รอการมอบหมายจากหัวหน้างานพัสดุ
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary">พนักงานขับรถ</div>
                <div class="fw-semibold"><?= e($result['assigned_driver_name'] ?: 'รอการมอบหมาย') ?></div>
            </div>
        </div>

        <?php if ($result['status'] === 'approved'): ?>
            <div class="mt-4">
                <a class="btn btn-success" href="<?= e(config('app')['base_path'] . '/download?tracking_id=' . urlencode($result['tracking_id'])) ?>" target="_blank" rel="noopener">ดาวน์โหลด PDF</a>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
