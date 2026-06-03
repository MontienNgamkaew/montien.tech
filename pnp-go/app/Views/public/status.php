<?php ob_start(); ?>

<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">ตรวจสอบสถานะคำขอใช้รถ</h1>
        <p class="text-secondary mb-0 small">ติดตามความคืบหน้าการพิจารณาคำขอจองยานพาหนะ</p>
    </div>
    <a href="<?= e(config('app')['base_path']) ?>/dashboard" class="btn btn-outline-secondary btn-sm">🏠 กลับแดชบอร์ด</a>
</div>

<?php if (($_GET['reported'] ?? '') === 'success'): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-3">
        <span class="fs-5">🎉</span>
        <div class="small"><strong>ส่งรายงานผลการเดินทางเสร็จสิ้น</strong> — รถคืนสถานะว่างพร้อมใช้งานแล้ว</div>
    </div>
<?php endif; ?>

<?php if ($result): ?>
    <?php
    $status = $result['status'];
    $isRejected = ($status === 'rejected');
    $isCancelled = ($status === 'cancelled');
    $activeStep = 0;
    $fillPercentage = 0;

    if ($status === 'pending_level_2') {
        $activeStep = 1;
        $fillPercentage = 33.33;
    } elseif ($status === 'pending_level_3') {
        $activeStep = 2;
        $fillPercentage = 66.66;
    } elseif ($status === 'approved') {
        $activeStep = 3;
        $fillPercentage = 100;
    }
    ?>

    <?php if ($isRejected): ?>
        <div class="alert alert-danger d-flex align-items-start gap-3 mb-3">
            <div class="fs-3">❌</div>
            <div>
                <h4 class="alert-heading h6 mb-1 fw-bold">คำขอใช้รถยนต์ไม่ได้รับการอนุมัติ</h4>
                <p class="mb-0"><strong>เหตุผล:</strong> <?= e($result['rejection_reason'] ?: 'ไม่ระบุเหตุผล') ?></p>
                <?php if (!empty($result['rejected_at'])): ?>
                    <small class="text-secondary d-block mt-1">วันที่พิจารณา: <?= e(date('d/m/Y H:i', strtotime($result['rejected_at']))) ?></small>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($isCancelled): ?>
        <div class="alert alert-secondary d-flex align-items-start gap-3 mb-3">
            <div class="fs-3">⚠️</div>
            <div>
                <h4 class="alert-heading h6 mb-1 fw-bold">คำขอนี้ถูกยกเลิกแล้ว</h4>
                <p class="mb-0 small">คำขอนี้ถูกยกเลิกโดยผู้ยื่นคำขอ</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$isCancelled && !$isRejected): ?>
        <section class="form-section">
            <h2 class="section-title mb-2">ความคืบหน้าการอนุมัติ</h2>
            <div class="stepper-wrapper">
                <div class="stepper-line">
                    <div class="stepper-line-fill" style="width: <?= $fillPercentage ?>%;"></div>
                </div>
                <div class="stepper-steps">
                    <div class="step-item active <?= $activeStep > 0 ? 'completed' : '' ?>">
                        <div class="step-circle"><?= $activeStep > 0 ? '✓' : '1' ?></div>
                        <div class="step-label">ยื่นคำขอสำเร็จ</div>
                        <div class="step-desc">บันทึกเข้าระบบ</div>
                    </div>
                    <div class="step-item <?= $activeStep >= 1 ? 'active' : '' ?> <?= $activeStep > 1 ? 'completed' : '' ?>">
                        <div class="step-circle"><?= $activeStep > 1 ? '✓' : '2' ?></div>
                        <div class="step-label">ผ่านงานพัสดุ</div>
                        <div class="step-desc">จัดมอบหมายรถ</div>
                    </div>
                    <div class="step-item <?= $activeStep >= 2 ? 'active' : '' ?> <?= $activeStep > 2 ? 'completed' : '' ?>">
                        <div class="step-circle"><?= $activeStep > 2 ? '✓' : '3' ?></div>
                        <div class="step-label">ผ่านรอง ผอ.</div>
                        <div class="step-desc">ฝ่ายบริหารทรัพยากร</div>
                    </div>
                    <div class="step-item <?= $activeStep >= 3 ? 'active' : '' ?>">
                        <div class="step-circle">4</div>
                        <div class="step-label">อนุมัติเรียบร้อย</div>
                        <div class="step-desc">โดยผู้อำนวยการ</div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="form-section">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3 pb-2 border-bottom">
            <div>
                <h2 class="h6 mb-1 fw-bold text-dark">📋 รายละเอียดใบคำขอใช้รถยนต์</h2>
                <div class="text-secondary small">รหัสอ้างอิง: <?= e($result['tracking_id']) ?></div>
            </div>
            <span class="status-pill <?= e(status_badge_class($result['status'])) ?>">
                <?= e($statusLabels[$result['status']] ?? $result['status']) ?>
            </span>
        </div>

        <div class="detail-grid">
            <div class="detail-item"><span class="di-label">👤 ผู้ขอใช้รถ</span><span class="di-value"><?= e($result['requester_name']) ?></span></div>
            <div class="detail-item"><span class="di-label">💼 ตำแหน่ง</span><span class="di-value"><?= e($result['requester_position']) ?></span></div>
            <div class="detail-item"><span class="di-label">📍 จุดหมายปลายทาง</span><span class="di-value"><?= e($result['destination']) ?></span></div>
            <div class="detail-item"><span class="di-label">📅 ออกเดินทาง</span><span class="di-value"><?= e(date('d/m/Y H:i', strtotime($result['travel_start_at']))) ?> น.</span></div>
            <div class="detail-item"><span class="di-label">📅 กลับถึงวิทยาลัย</span><span class="di-value"><?= e(date('d/m/Y H:i', strtotime($result['travel_end_at']))) ?> น.</span></div>
            <div class="detail-item"><span class="di-label">👥 จำนวนผู้เดินทาง</span><span class="di-value"><?= e(number_format((float) $result['passenger_count'])) ?> ท่าน</span></div>
            <div class="detail-item">
                <span class="di-label">🚗 ยานพาหนะที่จัดสรร</span>
                <span class="di-value">
                    <?php if ($result['vehicle_name']): ?>
                        <span class="text-success">🚘 <?= e($result['vehicle_name']) ?></span>
                        <span class="badge bg-light text-dark border ms-1"><?= e($result['license_plate']) ?></span>
                    <?php else: ?>
                        <span class="text-muted fw-normal">⏳ รอมอบหมายยานพาหนะ</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="di-label">👮 พนักงานขับรถ</span>
                <span class="di-value">
                    <?php if ($result['assigned_driver_name']): ?>
                        <?= e($result['assigned_driver_name']) ?>
                    <?php else: ?>
                        <span class="text-muted fw-normal">⏳ รอมอบหมาย</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="detail-item"><span class="di-label">📝 วัตถุประสงค์</span><span class="di-value"><?= e($result['purpose']) ?></span></div>
        </div>

        <?php if ($result['status'] === 'approved'): ?>
            <div class="mt-3 pt-3 border-top d-flex flex-wrap gap-2">
                <a class="btn btn-success" href="<?= e(config('app')['base_path'] . '/download?id=' . urlencode($result['id'])) ?>" target="_blank" rel="noopener">📥 ดาวน์โหลด PDF</a>
                <?php if (empty($result['reported_at'])): ?>
                    <a class="btn btn-primary" href="<?= e(config('app')['base_path'] . '/report/submit?id=' . urlencode($result['id'])) ?>">📝 รายงานผลการใช้รถ</a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary" disabled>✅ ส่งรายงานแล้ว</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($result['reported_at'])): ?>
                <div class="mt-3 pt-3 border-top">
                    <h2 class="section-title mb-3" style="font-size: 14px;">📊 รายงานการใช้รถจริงหลังเดินทาง</h2>
                    <div class="detail-grid">
                        <div class="detail-item"><span class="di-label">📅 วันที่ส่งรายงาน</span><span class="di-value"><?= e(date('d/m/Y H:i', strtotime($result['reported_at']))) ?> น.</span></div>
                        <div class="detail-item"><span class="di-label">📟 เลขไมล์ก่อนออก</span><span class="di-value"><?= e(number_format((float) $result['odometer_before'])) ?> กม.</span></div>
                        <div class="detail-item"><span class="di-label">📟 เลขไมล์หลังเดินทาง</span><span class="di-value"><?= e(number_format((float) $result['odometer_after'])) ?> กม.</span></div>
                    </div>
                    <?php if (!empty($result['report_photo_path'])): ?>
                        <div class="mt-3">
                            <span class="di-label">📸 รูปเลขไมล์ / สภาพรถ</span>
                            <a href="<?= e(config('app')['base_path'] . '/' . $result['report_photo_path']) ?>" target="_blank" title="คลิกเพื่อขยาย" class="d-inline-block mt-1">
                                <img src="<?= e(config('app')['base_path'] . '/' . $result['report_photo_path']) ?>" class="img-fluid rounded border" style="max-height: 160px;">
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
?>
