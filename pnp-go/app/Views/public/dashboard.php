<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">แดชบอร์ดคำขอของฉัน</h1>
        <p class="text-secondary mb-0"><?= e($user['full_name']) ?> | ครูผู้ยื่นคำขอ</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="<?= e(config('app')['base_path']) ?>/request">➕ ยื่นคำขอใช้รถ</a>
        <a class="btn btn-outline-primary" href="<?= e(config('app')['base_path']) ?>/profile">โปรไฟล์/ลายเซ็น</a>
    </div>
</div>

<?php
// Calculate simple stats for the user
$totalCount = count($myRequisitions);
$pendingCount = 0;
$approvedCount = 0;
$reportedCount = 0;

foreach ($myRequisitions as $item) {
    if (str_starts_with($item['status'], 'pending_') || $item['status'] === 'submitted') {
        $pendingCount++;
    } elseif ($item['status'] === 'approved') {
        $approvedCount++;
        if (!empty($item['reported_at'])) {
            $reportedCount++;
        }
    }
}
?>

<div class="stat-grid">
    <div class="stat-tile">
        <div class="stat-value"><?= e((string) $totalCount) ?></div>
        <div class="stat-label">คำขอทั้งหมดของคุณ</div>
    </div>
    <div class="stat-tile <?= $pendingCount > 0 ? 'stat-tile--warn' : '' ?>">
        <div class="stat-value"><?= e((string) $pendingCount) ?></div>
        <div class="stat-label">รออนุมัติ</div>
    </div>
    <div class="stat-tile <?= ($approvedCount - $reportedCount) > 0 ? 'stat-tile--ok' : '' ?>">
        <div class="stat-value"><?= e((string) ($approvedCount - $reportedCount)) ?></div>
        <div class="stat-label">อนุมัติแล้ว (ค้างรายงานไมล์)</div>
    </div>
</div>

<!-- ===== Real-time Vehicle Status Board ===== -->
<section class="form-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">🚘 สถานะรถยนต์ปัจจุบัน</h2>
        <span class="badge bg-light text-secondary border">อัปเดตเรียลไทม์</span>
    </div>

    <div class="vehicle-board">
        <?php foreach ($vehicleStatus as $v):
            $inUse = !empty($v['req_id']);
            $type  = $v['vehicle_type'] ?? 'รถยนต์';
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
                    <?= $inUse ? '● ไม่ว่าง' : '● ว่างพร้อมใช้งาน' ?>
                </div>
                <?php if ($inUse): ?>
                <div class="vcard-info mt-2">
                    <div class="vcard-info-row text-muted">
                        <span class="vcard-info-icon">📍</span>
                        <?= e(mb_strimwidth($v['destination'], 0, 24, '…')) ?>
                    </div>
                    <div class="vcard-info-row text-muted">
                        <span class="vcard-info-icon">🕐</span>
                        คืนรถ <?= e(date('d/m H:i', strtotime($v['travel_end_at']))) ?> น.
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($vehicleStatus)): ?>
            <p class="text-muted text-center w-100 py-3">ไม่มีข้อมูลยานพาหนะในขณะนี้</p>
        <?php endif; ?>
    </div>
</section>

<!-- ===== My Requisitions Section ===== -->
<section class="form-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">📅 ประวัติการจองและรายงานผลการใช้รถของคุณ</h2>
    </div>

    <?php if ($myRequisitions === []): ?>
        <div class="text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 12px;">🚗</div>
            <h4 class="text-secondary h5">คุณยังไม่มีประวัติการยื่นคำขอจองรถยนต์</h4>
            <p class="text-muted">คุณสามารถเริ่มเขียนคำขออนุญาตใช้รถยนต์ส่วนกลางและเบิกน้ำมันได้ในคลิกเดียว</p>
            <a class="btn btn-primary mt-2" href="<?= e(config('app')['base_path']) ?>/request">เขียนคำขออนุญาตใช้รถยนต์</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead>
                    <tr>
                        <th>เลขที่เอกสาร</th>
                        <th>จุดหมายปลายทาง</th>
                        <th>วันเดินทาง</th>
                        <th>รถยนต์ที่ได้รับ</th>
                        <th>สถานะคำขอ</th>
                        <th class="text-end">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myRequisitions as $item): ?>
                        <tr>
                            <td class="fw-bold text-primary">
                                <?= e($item['tracking_id']) ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($item['destination']) ?></div>
                                <small class="text-muted"><?= e(trim(($item['destination_subdistrict'] ?? '') . ' ' . ($item['destination_district'] ?? '') . ' ' . ($item['destination_province'] ?? ''))) ?></small>
                            </td>
                            <td>
                                <div class="text-dark" style="font-size: 0.9rem;"><?= e(date('d/m/Y H:i', strtotime($item['travel_start_at']))) ?></div>
                                <small class="text-muted">ถึง <?= e(date('d/m/Y H:i', strtotime($item['travel_end_at']))) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($item['assigned_vehicle_id'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1.5" style="font-size: 11px; border-radius: 8px;">
                                        🚘 <?= e($item['vehicle_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 13px;">
                                        🚗 <?= e($item['vehicle_name'] ?? 'ไม่ได้ระบุเจาะจง') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badgeClass = 'bg-secondary';
                                if ($item['status'] === 'approved') {
                                    $badgeClass = 'bg-success';
                                } elseif ($item['status'] === 'rejected') {
                                    $badgeClass = 'bg-danger';
                                } elseif (str_starts_with($item['status'], 'pending_')) {
                                    $badgeClass = 'bg-warning text-dark';
                                }
                                ?>
                                <span class="badge <?= $badgeClass ?> px-2 py-1.5" style="font-size: 11px; font-weight: 600; border-radius: 8px;">
                                    <?= e($statusLabels[$item['status']] ?? $item['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(config('app')['base_path']) ?>/status?id=<?= e($item['id']) ?>">
                                        🔍 เปิดดู
                                    </a>

                                    <?php if ($item['status'] === 'approved' && empty($item['reported_at'])): ?>
                                        <a class="btn btn-sm btn-success text-white" href="<?= e(config('app')['base_path']) ?>/report/submit?id=<?= e($item['id']) ?>">
                                            📝 รายงานการใช้รถ
                                        </a>
                                    <?php elseif ($item['status'] === 'approved' && !empty($item['reported_at'])): ?>
                                        <span class="btn btn-sm btn-outline-secondary disabled" style="cursor: default;">
                                            ✅ รายงานแล้ว
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($item['status'] === 'approved' && !empty($item['pdf_path'])): ?>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= e(config('app')['base_path']) ?>/download?id=<?= e($item['id']) ?>" target="_blank">
                                            📄 PDF
                                        </a>
                                    <?php endif; ?>

                                    <?php if (str_starts_with($item['status'], 'pending_') || $item['status'] === 'submitted'): ?>
                                        <form method="post" action="<?= e(config('app')['base_path']) ?>/request/cancel" class="d-inline mb-0" data-confirm data-confirm-title="ยกเลิกคำขอ" data-confirm-text="ต้องการยกเลิกคำขอนี้หรือไม่? เมื่อยกเลิกแล้วไม่สามารถย้อนกลับได้" data-confirm-button="ยกเลิกคำขอ" data-confirm-icon="warning">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">✕ ยกเลิกคำขอ</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
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
?>
