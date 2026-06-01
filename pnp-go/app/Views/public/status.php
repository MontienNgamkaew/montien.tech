<?php ob_start(); ?>
<div class="page-heading">
    <h1 class="h3 mb-1">ตรวจสอบสถานะคำขอ</h1>
    <p class="text-secondary mb-0">กรอก Tracking ID ที่ได้รับหลังส่งคำขอ</p>
</div>

<?php if (($_GET['reported'] ?? '') === 'success'): ?>
    <div class="alert alert-success">🎉 ส่งรายงานการใช้รถยนต์และหลักฐานเลขไมล์เสร็จเรียบร้อยแล้ว สถานะรถยนต์คืนเข้าคลังว่างพร้อมใช้งานทันทีขอบพระคุณครับ!</div>
<?php endif; ?>

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
            <div class="mt-4 d-flex flex-wrap gap-2">
                <a class="btn btn-success" href="<?= e(config('app')['base_path'] . '/download?tracking_id=' . urlencode($result['tracking_id'])) ?>" target="_blank" rel="noopener">📥 ดาวน์โหลด PDF</a>
                <?php if (empty($result['reported_at'])): ?>
                    <a class="btn btn-primary" href="<?= e(config('app')['base_path'] . '/report/submit?tracking_id=' . urlencode($result['tracking_id'])) ?>">📝 รายงานการใช้รถหลังเดินทาง</a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary" disabled>✅ รายงานผลการเดินทางเรียบร้อยแล้ว</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($result['reported_at'])): ?>
                <div class="mt-4 pt-3 border-top">
                    <h5 class="h6 text-primary mb-2">📋 ข้อมูลการรายงานหลังใช้งานจริง</h5>
                    <div class="row g-2 bg-light p-3 rounded border">
                        <div class="col-md-4">
                            <span class="text-secondary small d-block">เวลาส่งรายงาน</span>
                            <span class="fw-semibold"><?= e(date('d/m/Y H:i', strtotime($result['reported_at']))) ?></span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary small d-block">เลขไมล์ก่อนเดินทาง</span>
                            <span class="fw-semibold"><?= e(number_format((float)$result['odometer_before'])) ?> กม.</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary small d-block">เลขไมล์หลังเดินทาง</span>
                            <span class="fw-semibold"><?= e(number_format((float)$result['odometer_after'])) ?> กม.</span>
                        </div>
                        <?php if (!empty($result['report_photo_path'])): ?>
                            <div class="col-12 mt-3">
                                <span class="text-secondary small d-block mb-1">รูปถ่ายพยานหลักฐานเลขไมล์/สภาพรถ</span>
                                <a href="<?= e(config('app')['base_path'] . '/' . $result['report_photo_path']) ?>" target="_blank">
                                    <img src="<?= e(config('app')['base_path'] . '/' . $result['report_photo_path']) ?>" class="img-thumbnail" style="max-height: 200px;">
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
