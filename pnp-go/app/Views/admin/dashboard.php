<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">แดชบอร์ดผู้อนุมัติ</h1>
        <p class="text-secondary mb-0"><?= e($user['full_name']) ?> | <?= e(role_label($user['role'])) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($user['role'] === 'admin' || $user['role'] === 'supply_head'): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(config('app')['base_path']) ?>/manage/vehicles">🚗 จัดการรถ</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(config('app')['base_path']) ?>/vendors">⛽ ร้านน้ำมัน</a>
        <?php endif; ?>
        <a class="btn btn-outline-success btn-sm" href="<?= e(config('app')['base_path']) ?>/report">📊 รายงาน</a>
        <a class="btn btn-outline-primary" href="<?= e(config('app')['base_path']) ?>/profile">โปรไฟล์/ลายเซ็น</a>
        <form method="post" action="<?= e(config('app')['base_path']) ?>/logout" data-confirm data-confirm-title="ออกจากระบบ" data-confirm-text="ต้องการออกจากระบบผู้อนุมัติหรือไม่" data-confirm-button="ออกจากระบบ" data-confirm-icon="warning">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-outline-secondary" type="submit">ออกจากระบบ</button>
        </form>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-tile">
        <div class="stat-value"><?= e((string) count($pendingRequisitions)) ?></div>
        <div class="stat-label"><?= $user['role'] === 'admin' ? 'คำขอที่แสดงอยู่' : 'งานรออนุมัติของคุณ' ?></div>
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

<section class="form-section mb-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0"><?= $user['role'] === 'admin' ? 'คำขอล่าสุด' : 'งานที่รออนุมัติ' ?></h2>
        <?php if ($level): ?>
            <span class="status-pill">Level <?= e((string) $level) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($pendingRequisitions === []): ?>
        <div class="text-secondary">ยังไม่มีคำขอในคิวนี้</div>
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
    <?php endif; ?>
</section>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="form-section h-100">
            <h2 class="section-title">สถิติการใช้รถ</h2>
            <?php if ($vehicleStats === []): ?>
                <div class="text-secondary">ยังไม่มีข้อมูล</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>รถ</th>
                                <th class="text-end">จำนวนคำขอ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicleStats as $stat): ?>
                                <tr>
                                    <td><?= e($stat['vehicle_name'] . ' - ' . $stat['license_plate']) ?></td>
                                    <td class="text-end fw-semibold"><?= e((string) $stat['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="form-section h-100">
            <h2 class="section-title">สรุปน้ำมันรายเดือน</h2>
            <?php if ($fuelSummary === []): ?>
                <div class="text-secondary">ยังไม่มีข้อมูล</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>เดือน</th>
                                <th>ชนิดน้ำมัน</th>
                                <th class="text-end">จำนวนคำขอ</th>
                                <th class="text-end">จำนวนเงินรวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fuelSummary as $fuel): ?>
                                <tr>
                                    <td><?= e($fuel['month_key']) ?></td>
                                    <td><?= e(DashboardController::fuelTypeLabel($fuel['fuel_type'] ?? null)) ?></td>
                                    <td class="text-end"><?= e((string) $fuel['total_requests']) ?></td>
                                    <td class="text-end fw-semibold"><?= e(number_format((float) $fuel['total_amount'], 2)) ?> บาท</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
