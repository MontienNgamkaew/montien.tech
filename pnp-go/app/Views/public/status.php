<?php ob_start(); ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">ตรวจสอบสถานะคำขอใช้รถ</h1>
        <p class="text-secondary mb-0">แผงติดตามสถานะและความคืบหน้าการพิจารณาคำขอจองคิวยานพาหนะ</p>
    </div>
    <a href="<?= e(config('app')['base_path']) ?>/dashboard" class="btn btn-outline-secondary d-flex align-items-center gap-2" style="border-radius: 12px; font-weight: 500;">
        <span>🏠 กลับสู่แดชบอร์ด</span>
    </a>
</div>

<?php if (($_GET['reported'] ?? '') === 'success'): ?>
    <div class="alert alert-success border-0 shadow-sm p-3 mb-4 d-flex align-items-center gap-3" style="border-radius: 16px; background: rgba(16, 185, 129, 0.1); color: #047857;">
        <span class="fs-4">🎉</span>
        <div><strong>ส่งรายงานผลการเดินทางเสร็จสิ้น!</strong> ข้อมูลเลขไมล์และรูปถ่ายหลักฐานได้รับการจัดเก็บเข้าระบบเรียบร้อยแล้ว ยานพาหนะคืนสถานะว่างพร้อมให้บริการท่านอื่นต่อไป ขอขอบพระคุณครับ</div>
    </div>
<?php endif; ?>

<?php if ($result): ?>
    <?php
    $status = $result['status'];
    $activeStep = 0;
    $fillPercentage = 0;
    $isRejected = ($status === 'rejected');
    $isCancelled = ($status === 'cancelled');

    if ($status === 'submitted' || $status === 'pending_level_1') {
        $activeStep = 0;
        $fillPercentage = 0;
    } elseif ($status === 'pending_level_2') {
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

    <!-- Status Alerts -->
    <?php if ($isRejected): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3 p-4 mb-4 shadow-sm" style="border-radius: 16px; border: 1px solid rgba(220, 38, 38, 0.2); background: rgba(220, 38, 38, 0.05); backdrop-filter: blur(8px);">
            <div class="fs-1">❌</div>
            <div>
                <h4 class="alert-heading h5 mb-1 fw-bold text-danger">คำขอใช้รถยนต์ไม่ได้รับการอนุมัติ (Rejected)</h4>
                <p class="mb-0 text-dark"><strong>เหตุผลการปฏิเสธ:</strong> <?= e($result['rejection_reason'] ?: 'ไม่ระบุเหตุผลในการปฏิเสธการจอง') ?></p>
                <?php if (!empty($result['rejected_at'])): ?>
                    <small class="text-secondary d-block mt-1">วันที่พิจารณาปฏิเสธ: <?= e(date('d/m/Y H:i', strtotime($result['rejected_at']))) ?></small>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($isCancelled): ?>
        <div class="alert alert-secondary d-flex align-items-center gap-3 p-4 mb-4 shadow-sm" style="border-radius: 16px; border: 1px solid rgba(100, 116, 139, 0.2); background: rgba(100, 116, 139, 0.05);">
            <div class="fs-1">⚠️</div>
            <div>
                <h4 class="alert-heading h5 mb-1 fw-bold text-secondary">คำขอใช้รถยนต์นี้ถูกยกเลิกแล้ว (Cancelled)</h4>
                <p class="mb-0 text-secondary">คำขอนี้ถูกขอยกเลิกโดยครูผู้ยื่นหรือระบบจัดคิวยานพาหนะกลาง</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Visual Stepper timeline (Only if not cancelled/rejected) -->
    <?php if (!$isCancelled && !$isRejected): ?>
        <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 20px; background: rgba(255, 255, 255, 0.65); backdrop-filter: blur(10px);">
            <h5 class="h6 fw-bold mb-4 text-dark d-flex align-items-center gap-2">
                <span class="d-inline-block" style="width: 8px; height: 18px; background: var(--color-primary, #4f46e5); border-radius: 4px;"></span>
                ความคืบหน้าการอนุมัติ (Approval Timeline)
            </h5>
            
            <div class="stepper-wrapper position-relative py-3">
                <div class="stepper-line" style="position: absolute; top: 32px; left: 6%; right: 6%; height: 4px; background-color: rgba(var(--color-primary-rgb, 79, 70, 229), 0.1); z-index: 1;">
                    <div class="stepper-line-fill" style="width: <?= $fillPercentage ?>%; height: 100%; background: linear-gradient(90deg, var(--color-primary, #4f46e5) 0%, var(--color-primary-light, #818cf8) 100%); transition: width 0.6s ease; z-index: 1;"></div>
                </div>

                <div class="d-flex justify-content-between position-relative" style="z-index: 2;">
                    <!-- Step 1 -->
                    <div class="step-item d-flex flex-column align-items-center text-center flex-1 <?= $activeStep >= 0 ? 'active' : '' ?> <?= $activeStep > 0 ? 'completed' : '' ?>">
                        <div class="step-circle" style="width: 44px; height: 44px; border-radius: 50%; background: <?= $activeStep > 0 ? 'var(--color-primary-light, #818cf8)' : ($activeStep === 0 ? 'var(--color-primary, #4f46e5)' : '#ffffff') ?>; border: 3px solid <?= $activeStep >= 0 ? 'var(--color-primary, #4f46e5)' : '#e2e8f0' ?>; color: <?= $activeStep >= 0 ? '#ffffff' : '#64748b' ?>; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.4s ease; box-shadow: <?= $activeStep === 0 ? '0 0 12px rgba(var(--color-primary-rgb, 79, 70, 229), 0.3)' : 'none' ?>;">
                            <?php if ($activeStep > 0): ?>✓<?php else: ?>1<?php endif; ?>
                        </div>
                        <div class="step-label mt-2 fw-semibold small" style="color: <?= $activeStep >= 0 ? 'var(--color-primary, #4f46e5)' : 'var(--text-muted)' ?>;">ยื่นคำขอสำเร็จ</div>
                        <div class="step-desc text-muted" style="font-size: 11px;">บันทึกเข้าระบบ</div>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-item d-flex flex-column align-items-center text-center flex-1 <?= $activeStep >= 1 ? 'active' : '' ?> <?= $activeStep > 1 ? 'completed' : '' ?>">
                        <div class="step-circle" style="width: 44px; height: 44px; border-radius: 50%; background: <?= $activeStep > 1 ? 'var(--color-primary-light, #818cf8)' : ($activeStep === 1 ? 'var(--color-primary, #4f46e5)' : '#ffffff') ?>; border: 3px solid <?= $activeStep >= 1 ? 'var(--color-primary, #4f46e5)' : '#e2e8f0' ?>; color: <?= $activeStep >= 1 ? '#ffffff' : '#64748b' ?>; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.4s ease; box-shadow: <?= $activeStep === 1 ? '0 0 12px rgba(var(--color-primary-rgb, 79, 70, 229), 0.3)' : 'none' ?>;">
                            <?php if ($activeStep > 1): ?>✓<?php else: ?>2<?php endif; ?>
                        </div>
                        <div class="step-label mt-2 fw-semibold small" style="color: <?= $activeStep >= 1 ? 'var(--color-primary, #4f46e5)' : 'var(--text-muted)' ?>;">ผ่านงานพัสดุ</div>
                        <div class="step-desc text-muted" style="font-size: 11px;">จัดหามอบหมายรถ</div>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-item d-flex flex-column align-items-center text-center flex-1 <?= $activeStep >= 2 ? 'active' : '' ?> <?= $activeStep > 2 ? 'completed' : '' ?>">
                        <div class="step-circle" style="width: 44px; height: 44px; border-radius: 50%; background: <?= $activeStep > 2 ? 'var(--color-primary-light, #818cf8)' : ($activeStep === 2 ? 'var(--color-primary, #4f46e5)' : '#ffffff') ?>; border: 3px solid <?= $activeStep >= 2 ? 'var(--color-primary, #4f46e5)' : '#e2e8f0' ?>; color: <?= $activeStep >= 2 ? '#ffffff' : '#64748b' ?>; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.4s ease; box-shadow: <?= $activeStep === 2 ? '0 0 12px rgba(var(--color-primary-rgb, 79, 70, 229), 0.3)' : 'none' ?>;">
                            <?php if ($activeStep > 2): ?>✓<?php else: ?>3<?php endif; ?>
                        </div>
                        <div class="step-label mt-2 fw-semibold small" style="color: <?= $activeStep >= 2 ? 'var(--color-primary, #4f46e5)' : 'var(--text-muted)' ?>;">ผ่านรอง ผอ.</div>
                        <div class="step-desc text-muted" style="font-size: 11px;">ฝ่ายบริหารทรัพยากร</div>
                    </div>

                    <!-- Step 4 -->
                    <div class="step-item d-flex flex-column align-items-center text-center flex-1 <?= $activeStep >= 3 ? 'active' : '' ?>">
                        <div class="step-circle" style="width: 44px; height: 44px; border-radius: 50%; background: <?= $activeStep === 3 ? 'var(--color-primary, #4f46e5)' : '#ffffff' ?>; border: 3px solid <?= $activeStep >= 3 ? 'var(--color-primary, #4f46e5)' : '#e2e8f0' ?>; color: <?= $activeStep >= 3 ? '#ffffff' : '#64748b' ?>; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: all 0.4s ease; box-shadow: <?= $activeStep === 3 ? '0 0 12px rgba(var(--color-primary-rgb, 79, 70, 229), 0.3)' : 'none' ?>;">
                            4
                        </div>
                        <div class="step-label mt-2 fw-semibold small" style="color: <?= $activeStep >= 3 ? 'var(--color-primary, #4f46e5)' : 'var(--text-muted)' ?>;">อนุมัติเรียบร้อย</div>
                        <div class="step-desc text-muted" style="font-size: 11px;">โดยผู้อำนวยการวิทยาลัย</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Detail Card -->
    <section class="form-section shadow-sm p-4 mb-4" style="border-radius: 20px; background: #ffffff; border: 1px solid rgba(0, 0, 0, 0.05);">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-4 pb-3 border-bottom">
            <div>
                <h2 class="h5 mb-1 fw-bold text-dark">📋 รายละเอียดใบคำขอใช้รถยนต์</h2>
                <div class="text-secondary small">ออกโดยระบบ PNP Go • รหัสอ้างอิง: <?= e($result['tracking_id']) ?></div>
            </div>
            <span class="status-pill <?= e(status_badge_class($result['status'])) ?>" style="font-weight: 600; padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.85rem;">
                <?= e($statusLabels[$result['status']] ?? $result['status']) ?>
            </span>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">👤 ชื่อผู้ขอใช้รถยนต์</span>
                <span class="fw-semibold text-dark fs-6"><?= e($result['requester_name']) ?></span>
            </div>
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">💼 ตำแหน่ง</span>
                <span class="fw-semibold text-dark fs-6"><?= e($result['requester_position']) ?></span>
            </div>
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">📍 สถานที่ไปราชการ / จุดหมายปลายทาง</span>
                <span class="fw-semibold text-dark fs-6"><?= e($result['destination']) ?></span>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">📅 วันเวลาเดินทางเริ่มต้น</span>
                <span class="fw-semibold text-dark fs-6"><?= e(date('d/m/Y H:i', strtotime($result['travel_start_at']))) ?> น.</span>
            </div>
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">📅 วันเวลากลับถึงวิทยาลัย</span>
                <span class="fw-semibold text-dark fs-6"><?= e(date('d/m/Y H:i', strtotime($result['travel_end_at']))) ?> น.</span>
            </div>
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">👥 จำนวนผู้เดินทาง (รวมผู้ขอ)</span>
                <span class="fw-semibold text-dark fs-6"><?= e(number_format((float)$result['passenger_count'])) ?> ท่าน</span>
            </div>

            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">🚗 ยานพาหนะที่จัดสรร</span>
                <span class="fw-semibold text-dark fs-6">
                    <?php if ($result['vehicle_name']): ?>
                        <span class="text-success">🚘 <?= e($result['vehicle_name']) ?></span>
                        <span class="badge bg-light text-dark border ms-1"><?= e($result['license_plate']) ?></span>
                    <?php else: ?>
                        <span class="text-muted">⏳ รอการพิจารณามอบหมายยานพาหนะ</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">👮 พนักงานขับรถยนต์</span>
                <span class="fw-semibold text-dark fs-6">
                    <?php if ($result['assigned_driver_name']): ?>
                        👮‍♂️ <?= e($result['assigned_driver_name']) ?>
                    <?php else: ?>
                        <span class="text-muted">⏳ รอพิจารณามอบหมายพนักงานขับรถ</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="col-md-6 col-lg-4">
                <span class="text-secondary small d-block mb-1">📝 วัตถุประสงค์การใช้รถ</span>
                <span class="fw-semibold text-dark fs-6"><?= e($result['purpose']) ?></span>
            </div>
        </div>

        <!-- Approved Actions: Download PDF / Submit Post-Trip Report -->
        <?php if ($result['status'] === 'approved'): ?>
            <div class="mt-4 pt-4 border-top d-flex flex-wrap gap-3">
                <a class="btn btn-success px-4 py-2 d-inline-flex align-items-center gap-2 shadow-sm" style="border-radius: 12px; font-weight: 500;" href="<?= e(config('app')['base_path'] . '/download?id=' . urlencode($result['id'])) ?>" target="_blank" rel="noopener">
                    <span>📥 ดาวน์โหลดใบขออนุมัติ PDF</span>
                </a>
                
                <?php if (empty($result['reported_at'])): ?>
                    <a class="btn btn-primary px-4 py-2 d-inline-flex align-items-center gap-2 shadow-sm" style="border-radius: 12px; font-weight: 500;" href="<?= e(config('app')['base_path'] . '/report/submit?id=' . urlencode($result['id'])) ?>">
                        <span>📝 รายงานผลการใช้รถ (ส่งเลขไมล์/รูปถ่าย)</span>
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary px-4 py-2 d-inline-flex align-items-center gap-2" style="border-radius: 12px;" disabled>
                        <span>✅ ส่งรายงานผลการใช้รถเรียบร้อยแล้ว</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Post-Trip Report Detail -->
            <?php if (!empty($result['reported_at'])): ?>
                <div class="mt-4 pt-3 border-top">
                    <h5 class="h6 text-primary mb-3 fw-bold d-flex align-items-center gap-2">
                        <span>📊 ข้อมูลรายงานการใช้รถจริงหลังเดินทาง</span>
                    </h5>
                    <div class="row g-3 bg-light p-3 rounded-4 border-0">
                        <div class="col-md-4">
                            <span class="text-secondary small d-block mb-1">📅 วันเวลาที่ส่งรายงาน</span>
                            <span class="fw-semibold text-dark"><?= e(date('d/m/Y H:i', strtotime($result['reported_at']))) ?> น.</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary small d-block mb-1">📟 เลขไมล์ก่อนออกเดินทาง</span>
                            <span class="fw-semibold text-dark"><?= e(number_format((float)$result['odometer_before'])) ?> กม.</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-secondary small d-block mb-1">📟 เลขไมล์หลังเดินทางเสร็จสิ้น</span>
                            <span class="fw-semibold text-dark"><?= e(number_format((float)$result['odometer_after'])) ?> กม.</span>
                        </div>
                        
                        <?php if (!empty($result['report_photo_path'])): ?>
                            <div class="col-12 mt-3">
                                <span class="text-secondary small d-block mb-2">📸 รูปภาพเลขไมล์ / สภาพรถจริง</span>
                                <div class="position-relative d-inline-block rounded-3 overflow-hidden shadow-sm border" style="max-width: 300px;">
                                    <a href="<?= e(config('app')['base_path'] . '/' . $result['report_photo_path']) ?>" target="_blank" title="คลิกเพื่อขยายภาพ">
                                        <img src="<?= e(config('app')['base_path'] . '/' . $result['report_photo_path']) ?>" class="img-fluid" style="max-height: 180px; object-fit: cover;">
                                    </a>
                                </div>
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
?>
