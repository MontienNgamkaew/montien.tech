<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">แดชบอร์ดคำขอของฉัน</h1>
        <p class="text-secondary mb-0"><?= e($user['full_name']) ?> | ครูผู้ยื่นคำขอ</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary d-flex align-items-center gap-2" href="<?= e(config('app')['base_path']) ?>/request" style="border-radius: 12px; font-weight: 500;">
            <span>➕ เขียนคำขอใช้รถยนต์ใหม่</span>
        </a>
        <a class="btn btn-outline-primary" href="<?= e(config('app')['base_path']) ?>/profile" style="border-radius: 12px;">โปรไฟล์/ลายเซ็น</a>
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

<div class="stat-grid mb-4">
    <div class="stat-tile" style="border-radius: 16px;">
        <div class="stat-value"><?= e((string) $totalCount) ?></div>
        <div class="stat-label">คำขอทั้งหมดของคุณ</div>
    </div>
    <div class="stat-tile" style="border-radius: 16px; <?= $pendingCount > 0 ? 'box-shadow: 0 0 15px rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.4);' : '' ?>">
        <div class="stat-value" style="<?= $pendingCount > 0 ? 'color: #d97706;' : '' ?>"><?= e((string) $pendingCount) ?></div>
        <div class="stat-label">รออนุมัติ</div>
    </div>
    <div class="stat-tile" style="border-radius: 16px; <?= ($approvedCount - $reportedCount) > 0 ? 'box-shadow: 0 0 15px rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4);' : '' ?>">
        <div class="stat-value" style="<?= ($approvedCount - $reportedCount) > 0 ? 'color: #059669;' : '' ?>"><?= e((string) ($approvedCount - $reportedCount)) ?></div>
        <div class="stat-label">อนุมัติแล้ว (ค้างรายงานไมล์)</div>
    </div>
</div>

<!-- ===== Real-time Vehicle Status Board for Teachers ===== -->
<section class="form-section mb-4 shadow-sm p-4" style="border-radius: 20px; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(0, 0, 0, 0.05);">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0" style="font-size: 1.1rem; font-weight: 700;">🚘 ตารางแสดงสถานะรถยนต์แต่ละคันในระบบ (Vehicle Status Board)</h2>
        <span class="badge bg-light text-secondary border px-2 py-1.5" style="font-size: 11px;">อัปเดตเรียลไทม์</span>
    </div>
    
    <div class="vehicle-board">
        <?php foreach ($vehicleStatus as $v):
            $inUse = !empty($v['req_id']);
            $type  = $v['vehicle_type'] ?? 'รถยนต์';
        ?>
        <div class="vcard <?= $inUse ? 'vcard-busy' : 'vcard-free' ?>" style="border-radius: 16px; overflow: hidden; border: 1px solid rgba(0, 0, 0, 0.05); background: #ffffff;">
            <div class="vcard-icon">
                <?php if (str_contains($type, 'ตู้') || str_contains($type, 'ตู')): ?>
                <!-- Van icon - colorful -->
                <svg viewBox="0 0 80 52" fill="none" xmlns="http://www.w3.org/2000/svg" width="72" height="46">
                    <defs>
                        <linearGradient id="van-body" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#60a5fa"/>
                            <stop offset="100%" stop-color="#2563eb"/>
                        </linearGradient>
                        <linearGradient id="van-roof" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#93c5fd"/>
                            <stop offset="100%" stop-color="#60a5fa"/>
                        </linearGradient>
                    </defs>
                    <!-- Shadow -->
                    <ellipse cx="40" cy="50" rx="34" ry="3" fill="#00000020"/>
                    <!-- Body -->
                    <rect x="4" y="18" width="72" height="26" rx="5" fill="url(#van-body)"/>
                    <!-- Roof -->
                    <rect x="6" y="10" width="68" height="12" rx="4" fill="url(#van-roof)"/>
                    <!-- Windows left -->
                    <rect x="9" y="12" width="18" height="9" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <!-- Windows right group -->
                    <rect x="30" y="12" width="14" height="9" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <rect x="47" y="12" width="14" height="9" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <!-- Highlight stripe -->
                    <rect x="4" y="22" width="72" height="3" rx="1" fill="white" opacity=".2"/>
                    <!-- Headlight -->
                    <rect x="5" y="26" width="9" height="6" rx="2" fill="#fef08a"/>
                    <rect x="5" y="26" width="9" height="6" rx="2" fill="#fbbf24" opacity=".4"/>
                    <!-- Tail light -->
                    <rect x="66" y="26" width="9" height="6" rx="2" fill="#fca5a5"/>
                    <rect x="66" y="26" width="9" height="6" rx="2" fill="#ef4444" opacity=".5"/>
                    <!-- Bumper -->
                    <rect x="4" y="38" width="72" height="4" rx="2" fill="#1d4ed8"/>
                    <!-- Wheels -->
                    <circle cx="18" cy="44" r="7" fill="#374151"/>
                    <circle cx="18" cy="44" r="4" fill="#6b7280"/>
                    <circle cx="18" cy="44" r="2" fill="#d1d5db"/>
                    <circle cx="62" cy="44" r="7" fill="#374151"/>
                    <circle cx="62" cy="44" r="4" fill="#6b7280"/>
                    <circle cx="62" cy="44" r="2" fill="#d1d5db"/>
                    <!-- Door line -->
                    <line x1="28" y1="18" x2="28" y2="42" stroke="#1d4ed8" stroke-width="1.5"/>
                </svg>

                <?php elseif (str_contains($type, 'หกล้อ') || str_contains($type, 'บรรทุก')): ?>
                <!-- Truck icon - colorful -->
                <svg viewBox="0 0 88 52" fill="none" xmlns="http://www.w3.org/2000/svg" width="76" height="46">
                    <defs>
                        <linearGradient id="truck-cab" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#fb923c"/>
                            <stop offset="100%" stop-color="#ea580c"/>
                        </linearGradient>
                        <linearGradient id="truck-cargo" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#fbbf24"/>
                            <stop offset="100%" stop-color="#d97706"/>
                        </linearGradient>
                    </defs>
                    <!-- Shadow -->
                    <ellipse cx="44" cy="50" rx="38" ry="3" fill="#00000020"/>
                    <!-- Cargo box -->
                    <rect x="2" y="10" width="52" height="32" rx="3" fill="url(#truck-cargo)"/>
                    <!-- Cargo lines -->
                    <line x1="2" y1="20" x2="54" y2="20" stroke="#b45309" stroke-width="1"/>
                    <line x1="2" y1="30" x2="54" y2="30" stroke="#b45309" stroke-width="1"/>
                    <line x1="18" y1="10" x2="18" y2="42" stroke="#b45309" stroke-width="1"/>
                    <line x1="36" y1="10" x2="36" y2="42" stroke="#b45309" stroke-width="1"/>
                    <!-- Cab -->
                    <rect x="54" y="20" width="30" height="22" rx="4" fill="url(#truck-cab)"/>
                    <!-- Cab roof curve -->
                    <path d="M54 24 Q56 16 64 14 L82 14 L84 20 Z" fill="#fb923c"/>
                    <!-- Cab window -->
                    <rect x="58" y="16" width="20" height="11" rx="2" fill="#bfdbfe" opacity=".9"/>
                    <!-- Highlight -->
                    <rect x="54" y="24" width="30" height="2.5" rx="1" fill="white" opacity=".25"/>
                    <!-- Headlight -->
                    <rect x="80" y="28" width="6" height="5" rx="1.5" fill="#fef08a"/>
                    <!-- Wheels -->
                    <circle cx="14" cy="44" r="7" fill="#374151"/>
                    <circle cx="14" cy="44" r="4" fill="#6b7280"/>
                    <circle cx="14" cy="44" r="2" fill="#d1d5db"/>
                    <circle cx="42" cy="44" r="7" fill="#374151"/>
                    <circle cx="42" cy="44" r="4" fill="#6b7280"/>
                    <circle cx="42" cy="44" r="2" fill="#d1d5db"/>
                    <circle cx="72" cy="44" r="7" fill="#374151"/>
                    <circle cx="72" cy="44" r="4" fill="#6b7280"/>
                    <circle cx="72" cy="44" r="2" fill="#d1d5db"/>
                    <!-- Exhaust -->
                    <rect x="52" y="8" width="4" height="8" rx="2" fill="#78716c"/>
                </svg>

                <?php else: ?>
                <!-- Sedan icon - colorful -->
                <svg viewBox="0 0 80 50" fill="none" xmlns="http://www.w3.org/2000/svg" width="72" height="45">
                    <defs>
                        <linearGradient id="car-body" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#34d399"/>
                            <stop offset="100%" stop-color="#059669"/>
                        </linearGradient>
                        <linearGradient id="car-roof" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#6ee7b7"/>
                            <stop offset="100%" stop-color="#34d399"/>
                        </linearGradient>
                    </defs>
                    <!-- Shadow -->
                    <ellipse cx="40" cy="48" rx="32" ry="3" fill="#00000020"/>
                    <!-- Body lower -->
                    <rect x="4" y="26" width="72" height="16" rx="5" fill="url(#car-body)"/>
                    <!-- Roof / cabin -->
                    <path d="M18 26 C18 26 24 12 32 10 L48 10 C56 10 62 26 62 26 Z" fill="url(#car-roof)"/>
                    <!-- Hood -->
                    <path d="M4 30 L18 26 L4 26 Z" fill="#047857"/>
                    <!-- Trunk -->
                    <path d="M76 30 L62 26 L76 26 Z" fill="#047857"/>
                    <!-- Windshield front -->
                    <path d="M20 26 C20 26 25 14 32 12 L36 12 L36 26 Z" fill="#a7f3d0" opacity=".85"/>
                    <!-- Windshield rear -->
                    <path d="M60 26 C60 26 55 14 48 12 L44 12 L44 26 Z" fill="#a7f3d0" opacity=".85"/>
                    <!-- Side windows -->
                    <rect x="36" y="13" width="8" height="13" rx="1" fill="#a7f3d0" opacity=".85"/>
                    <!-- Highlight -->
                    <rect x="4" y="29" width="72" height="3" rx="1.5" fill="white" opacity=".2"/>
                    <!-- Headlights -->
                    <rect x="5" y="30" width="10" height="6" rx="2" fill="#fef08a"/>
                    <rect x="5" y="30" width="10" height="6" rx="2" fill="#fbbf24" opacity=".5"/>
                    <!-- Tail lights -->
                    <rect x="65" y="30" width="10" height="6" rx="2" fill="#fca5a5"/>
                    <rect x="65" y="30" width="10" height="6" rx="2" fill="#ef4444" opacity=".5"/>
                    <!-- Bumper -->
                    <rect x="4" y="38" width="72" height="3" rx="1.5" fill="#047857"/>
                    <!-- Wheels -->
                    <circle cx="18" cy="42" r="7" fill="#1f2937"/>
                    <circle cx="18" cy="42" r="4.5" fill="#4b5563"/>
                    <circle cx="18" cy="42" r="2" fill="#d1d5db"/>
                    <circle cx="62" cy="42" r="7" fill="#1f2937"/>
                    <circle cx="62" cy="42" r="4.5" fill="#4b5563"/>
                    <circle cx="62" cy="42" r="2" fill="#d1d5db"/>
                    <!-- Door handle -->
                    <rect x="36" y="31" width="8" height="2" rx="1" fill="#047857" opacity=".7"/>
                </svg>
                <?php endif; ?>
            </div>

            <div class="vcard-body">
                <div class="vcard-name" style="font-weight: 700; color: #1e293b;"><?= e($v['vehicle_name']) ?></div>
                <div class="vcard-plate" style="color: #64748b; font-size: 0.8rem;"><?= e($v['license_plate']) ?></div>
                <div class="vcard-status-badge <?= $inUse ? 'badge-busy' : 'badge-free' ?>" style="font-weight: 600;">
                    <?= $inUse ? '● ไม่ว่าง' : '● ว่างพร้อมใช้งาน' ?>
                </div>
                <?php if ($inUse): ?>
                <div class="vcard-info mt-2" style="background: rgba(239, 68, 68, 0.05); padding: 0.5rem; border-radius: 8px; font-size: 0.75rem;">
                    <div class="vcard-info-row text-muted" style="margin-bottom: 2px;">
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
<section class="form-section shadow-sm p-4" style="border-radius: 20px; background: #ffffff; border: 1px solid rgba(0, 0, 0, 0.05);">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="section-title mb-0" style="font-size: 1.1rem; font-weight: 700;">📅 ประวัติการจองและรายงานผลการใช้รถยนต์ของคุณ</h2>
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
                                    <a class="btn btn-sm btn-outline-primary" style="border-radius: 8px;" href="<?= e(config('app')['base_path']) ?>/status?id=<?= e($item['id']) ?>">
                                        🔍 เปิดดู
                                    </a>

                                    <?php if ($item['status'] === 'approved' && empty($item['reported_at'])): ?>
                                        <a class="btn btn-sm btn-success text-white" style="border-radius: 8px;" href="<?= e(config('app')['base_path']) ?>/report/submit?id=<?= e($item['id']) ?>">
                                            📝 รายงานผลไมล์
                                        </a>
                                    <?php elseif ($item['status'] === 'approved' && !empty($item['reported_at'])): ?>
                                        <span class="btn btn-sm btn-outline-secondary disabled" style="cursor: default; border-radius: 8px;">
                                            ✅ รายงานแล้ว
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($item['status'] === 'approved' && !empty($item['pdf_path'])): ?>
                                        <a class="btn btn-sm btn-outline-danger" style="border-radius: 8px;" href="<?= e(config('app')['base_path']) ?>/download?id=<?= e($item['id']) ?>" target="_blank">
                                            📄 PDF
                                        </a>
                                    <?php endif; ?>

                                    <?php if (str_starts_with($item['status'], 'pending_') || $item['status'] === 'submitted'): ?>
                                        <form method="post" action="<?= e(config('app')['base_path']) ?>/request/cancel" class="d-inline mb-0" data-confirm data-confirm-title="ยกเลิกคำขอ" data-confirm-text="ต้องการยกเลิกคำขอนี้หรือไม่? เมื่อยกเลิกแล้วไม่สามารถย้อนกลับได้" data-confirm-button="ยกเลิกคำขอ" data-confirm-icon="warning">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 8px;">✕ ยกเลิกคำขอ</button>
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
