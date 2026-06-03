<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">แดชบอร์ดผู้อนุมัติ</h1>
        <p class="text-secondary mb-0"><?= e($user['full_name']) ?> | <?= e(role_label($user['role'])) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="<?= e(config('app')['base_path']) ?>/request">➕ ยื่นคำขอใช้รถ</a>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'supply_head'): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(config('app')['base_path']) ?>/manage/vehicles">🚗 จัดการรถ</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(config('app')['base_path']) ?>/vendors">⛽ ร้านน้ำมัน</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(config('app')['base_path']) ?>/dashboard/settings">⚙️ ตั้งค่าระบบ</a>
        <?php endif; ?>
        <a class="btn btn-outline-success btn-sm" href="<?= e(config('app')['base_path']) ?>/report">📊 รายงาน</a>
        <a class="btn btn-outline-primary" href="<?= e(config('app')['base_path']) ?>/profile">โปรไฟล์/ลายเซ็น</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-tile <?= count($pendingRequisitions) > 0 && $user['role'] !== 'admin' ? 'stat-tile--warn' : '' ?>">
        <div class="stat-value"><?= e((string) count($pendingRequisitions)) ?></div>
        <div class="stat-label">⚡ <?= $user['role'] === 'admin' ? 'คำขอที่แสดงอยู่' : 'งานรอการอนุมัติของคุณ (เร่งด่วน)' ?></div>
    </div>
    <div class="stat-tile">
        <div class="stat-value"><?= e((string) count($vehicleStats)) ?></div>
        <div class="stat-label">รถในระบบ</div>
    </div>
    <div class="stat-tile">
        <div class="stat-value"><?= e((string) count($fuelSummary)) ?></div>
        <div class="stat-label">รายการสรุปน้ำมัน</div>
    </div>
</div>


<section class="form-section mb-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">
            <?= $user['role'] === 'admin' ? 'คำขอทั้งหมด' : 'งานที่รออนุมัติ' ?>
            <?php if (!empty($pagination)): ?>
                <span class="text-secondary fw-normal" style="font-size: 0.9rem;">(<?= e((string) $pagination['total']) ?> รายการ)</span>
            <?php endif; ?>
        </h2>
        <?php if ($level): ?>
            <span class="status-pill">Level <?= e((string) $level) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($pagination)): ?>
        <form method="get" action="<?= e(config('app')['base_path']) ?>/dashboard" class="row g-2 mb-3">
            <div class="col-sm-6 col-md-5">
                <input type="text" name="q" class="form-control" placeholder="ค้นหาเลขที่เอกสาร / ผู้ขอ / สถานที่" value="<?= e($filters['q'] ?? '') ?>">
            </div>
            <div class="col-sm-7 col-md-4">
                <select name="status" class="form-select">
                    <option value="">— ทุกสถานะ —</option>
                    <?php foreach ($statusLabels as $sKey => $sLabel): ?>
                        <option value="<?= e($sKey) ?>" <?= ($filters['status'] ?? '') === $sKey ? 'selected' : '' ?>><?= e($sLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-5 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">ค้นหา</button>
                <?php if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>
                    <a href="<?= e(config('app')['base_path']) ?>/dashboard" class="btn btn-outline-secondary">ล้าง</a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($pendingRequisitions === []): ?>
        <div class="text-secondary"><?= !empty($filters) && (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '') ? 'ไม่พบคำขอที่ตรงกับเงื่อนไขการค้นหา' : 'ยังไม่มีคำขอในคิวนี้' ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>ผู้ขอ</th>
                        <th>สถานที่</th>
                        <th>วันเดินทาง</th>
                        <th>สถานะ</th>
                        <th class="text-end">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRequisitions as $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($item['tracking_id']) ?></td>
                            <td><?= e($item['requester_name']) ?></td>
                            <td><?= e($item['destination']) ?></td>
                            <td><?= e(date('d/m/Y H:i', strtotime($item['travel_start_at']))) ?></td>
                            <td><span class="status-pill <?= e(status_badge_class($item['status'])) ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?= e(config('app')['base_path']) ?>/dashboard/requisition?id=<?= e((string) $item['id']) ?>">เปิดดู</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
            <?php
            $pageLink = function (int $p) use ($filters) {
                return '?' . http_build_query([
                    'q'      => $filters['q'] ?? '',
                    'status' => $filters['status'] ?? '',
                    'page'   => $p,
                ]);
            };
            $dashBase = config('app')['base_path'] . '/dashboard';
            ?>
            <nav class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-secondary">หน้า <?= e((string) $pagination['page']) ?> / <?= e((string) $pagination['totalPages']) ?></small>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($dashBase . $pageLink($pagination['page'] - 1)) ?>">‹ ก่อนหน้า</a>
                    </li>
                    <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e($dashBase . $pageLink($pagination['page'] + 1)) ?>">ถัดไป ›</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($myRequisitions !== []): ?>
<!-- ===== My Personal Requisitions Section ===== -->
<section class="form-section mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">📅 ประวัติการขอใช้รถส่วนตัวของฉัน</h2>
    </div>

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
</section>
<?php endif; ?>

<!-- ===== Vehicle Status Board ===== -->
<section class="form-section mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">สถานะรถยนต์ปัจจุบัน</h2>
        <small class="text-muted">ณ <?= e(date('d/m/Y H:i')) ?> น.</small>
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
                    <?= $inUse ? '● กำลังใช้งาน' : '● ว่าง' ?>
                </div>
                <?php if ($inUse): ?>
                <div class="vcard-info">
                    <div class="vcard-info-row">
                        <span class="vcard-info-icon">👤</span>
                        <?= e($v['requester_name']) ?>
                    </div>
                    <div class="vcard-info-row">
                        <span class="vcard-info-icon">📍</span>
                        <?= e(mb_strimwidth($v['destination'], 0, 24, '…')) ?>
                    </div>
                    <div class="vcard-info-row">
                        <span class="vcard-info-icon">🕐</span>
                        คืนรถ <?= e(date('d/m H:i', strtotime($v['travel_end_at']))) ?> น.
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($vehicleStatus)): ?>
            <p class="text-muted">ยังไม่มีข้อมูลรถในระบบ</p>
        <?php endif; ?>
    </div>
</section>


<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
