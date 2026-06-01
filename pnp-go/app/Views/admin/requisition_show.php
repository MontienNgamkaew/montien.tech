<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">รายละเอียดคำขอ</h1>
        <p class="text-secondary mb-0">Tracking ID: <?= e($requisition['tracking_id']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(config('app')['base_path']) ?>/dashboard">กลับแดชบอร์ด</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="form-section mb-3">
    <h2 class="section-title">ขั้นตอนการอนุมัติ</h2>
    <div class="workflow">
        <div class="workflow-step <?= $requisition['level1_approved_at'] ? 'is-done' : ((int) $requisition['current_level'] === 1 && str_starts_with($requisition['status'], 'pending') ? 'is-active' : '') ?>">
            <div class="workflow-step-title">1. หัวหน้างานพัสดุ</div>
            <div class="workflow-step-meta"><?= $requisition['level1_approved_at'] ? 'อนุมัติแล้ว ' . e(date('d/m/Y H:i', strtotime($requisition['level1_approved_at']))) : 'รอดำเนินการ' ?></div>
        </div>
        <div class="workflow-step <?= $requisition['level2_approved_at'] ? 'is-done' : ((int) $requisition['current_level'] === 2 && str_starts_with($requisition['status'], 'pending') ? 'is-active' : '') ?>">
            <div class="workflow-step-title">2. รองผู้อำนวยการ</div>
            <div class="workflow-step-meta"><?= $requisition['level2_approved_at'] ? 'อนุมัติแล้ว ' . e(date('d/m/Y H:i', strtotime($requisition['level2_approved_at']))) : 'รอดำเนินการ' ?></div>
        </div>
        <div class="workflow-step <?= $requisition['level3_approved_at'] ? 'is-done' : ((int) $requisition['current_level'] === 3 && str_starts_with($requisition['status'], 'pending') ? 'is-active' : '') ?>">
            <div class="workflow-step-title">3. ผู้อำนวยการ</div>
            <div class="workflow-step-meta"><?= $requisition['level3_approved_at'] ? 'อนุมัติแล้ว ' . e(date('d/m/Y H:i', strtotime($requisition['level3_approved_at']))) : 'รอดำเนินการ' ?></div>
        </div>
    </div>
</section>

<section class="form-section mb-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
        <h2 class="section-title mb-0">ข้อมูลคำขอ</h2>
        <span class="status-pill <?= e(status_badge_class($requisition['status'])) ?>"><?= e($statusLabels[$requisition['status']] ?? $requisition['status']) ?></span>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="text-secondary">ผู้ขอ</div>
            <div class="fw-semibold"><?= e($requisition['requester_name']) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary">ตำแหน่ง</div>
            <div class="fw-semibold"><?= e($requisition['requester_position']) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary">สถานที่</div>
            <div class="fw-semibold"><?= e($requisition['destination']) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary">วันเวลาออกเดินทาง</div>
            <div class="fw-semibold"><?= e(date('d/m/Y H:i', strtotime($requisition['travel_start_at']))) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary">วันเวลากลับ</div>
            <div class="fw-semibold"><?= e(date('d/m/Y H:i', strtotime($requisition['travel_end_at']))) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary">รถที่ขอใช้</div>
            <div class="fw-semibold">
                <?= $requisition['requested_vehicle_name'] ? e($requisition['requested_vehicle_name'] . ' - ' . $requisition['requested_license_plate']) : 'ให้พัสดุพิจารณา' ?>
            </div>
        </div>
        <div class="col-12">
            <div class="text-secondary">ภารกิจ</div>
            <div class="fw-semibold"><?= nl2br(e($requisition['purpose'])) ?></div>
        </div>
        <div class="col-md-6">
            <div class="text-secondary">ผู้ร่วมเดินทาง</div>
            <div class="fw-semibold"><?= e((string) $requisition['passenger_count']) ?> คน <?= $requisition['passenger_names'] ? ' | ' . e($requisition['passenger_names']) : '' ?></div>
        </div>
        <div class="col-md-6">
            <div class="text-secondary">การสั่งซื้อน้ำมัน</div>
            <div class="fw-semibold">
                <?php if ((int) $requisition['fuel_purchase_requested']): ?>
                    <?= e(DashboardController::fuelTypeLabel($requisition['fuel_type'] ?? null)) ?> |
                    สั่งซื้อ <?= e(number_format((float) $requisition['fuel_total_amount'], 2)) ?> บาท
                <?php else: ?>
                    ไม่สั่งซื้อ
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($canApprove): ?>
    <section class="form-section mb-3">
        <h2 class="section-title">การพิจารณาอนุมัติ</h2>
        <form method="post" action="<?= e(config('app')['base_path']) ?>/dashboard/requisition/approve" class="row g-3" data-confirm data-confirm-title="ยืนยันการอนุมัติ" data-confirm-text="เมื่อตกลง ระบบจะส่งคำขอนี้ไปยังขั้นตอนถัดไป" data-confirm-button="อนุมัติ" data-confirm-icon="question">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $requisition['id']) ?>">

            <?php if ((int) $requisition['current_level'] === 1): ?>
                <div class="col-md-6">
                    <label class="form-label" for="assigned_vehicle_id">รถที่มอบหมาย</label>
                    <select class="form-select" id="assigned_vehicle_id" name="assigned_vehicle_id">
                        <option value="">เลือกรถ</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= e((string) $vehicle['id']) ?>" <?= (string) $requisition['assigned_vehicle_id'] === (string) $vehicle['id'] ? 'selected' : '' ?>>
                                <?= e($vehicle['vehicle_name'] . ' - ' . $vehicle['license_plate']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="assigned_driver_name">พนักงานขับรถ</label>
                    <input class="form-control" id="assigned_driver_name" name="assigned_driver_name" value="<?= e($requisition['assigned_driver_name'] ?? '') ?>">
                </div>
            <?php endif; ?>

            <div class="col-12">
                <label class="form-label" for="comment">ความเห็น</label>
                <textarea class="form-control" id="comment" name="comment" rows="2"></textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                <button class="btn btn-success" type="submit">อนุมัติและส่งต่อ</button>
            </div>
        </form>

        <hr>

        <form method="post" action="<?= e(config('app')['base_path']) ?>/dashboard/requisition/reject" class="row g-3" data-confirm data-confirm-title="ยืนยันไม่อนุมัติ" data-confirm-text="คำขอนี้จะถูกเปลี่ยนสถานะเป็นไม่อนุมัติ" data-confirm-button="ไม่อนุมัติ" data-confirm-icon="warning">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $requisition['id']) ?>">
            <div class="col-12">
                <label class="form-label" for="rejection_reason">เหตุผลที่ไม่อนุมัติ</label>
                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="2"></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-outline-danger" type="submit">ไม่อนุมัติ</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="form-section">
    <h2 class="section-title">ประวัติการดำเนินการ</h2>
    <?php if ($logs === []): ?>
        <div class="text-secondary">ยังไม่มีประวัติ</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>เวลา</th>
                        <th>Level</th>
                        <th>Action</th>
                        <th>ผู้ดำเนินการ</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
                            <td><?= e((string) $log['approval_level']) ?></td>
                            <td><?= e($log['action']) ?></td>
                            <td><?= e($log['full_name'] ?: 'ผู้ยื่นคำขอ') ?></td>
                            <td><?= e($log['comment'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
