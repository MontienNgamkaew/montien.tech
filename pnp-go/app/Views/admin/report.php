<?php ob_start(); ?>

<!-- เลือกเดือน + ปุ่มพิมพ์ -->
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 no-print">
    <div>
        <h1 class="h3 mb-1">รายงานประจำเดือน</h1>
        <p class="text-secondary mb-0">รายงานการใช้รถและสั่งซื้อน้ำมันเชื้อเพลิง</p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <form method="get" action="" class="d-flex gap-2 align-items-center">
            <label class="form-label mb-0 fw-semibold">เลือกเดือน:</label>
            <input type="month" name="month" value="<?= e($month) ?>" class="form-control" style="width:auto;" onchange="this.form.submit()">
        </form>
        <button class="btn btn-success" onclick="window.print()">
            🖨️ พิมพ์รายงาน
        </button>
    </div>
</div>

<!-- ====================================================
     เนื้อหาที่พิมพ์
     ==================================================== -->
<div id="report-body">

    <!-- หัวรายงาน -->
    <div class="report-header">
        <div class="report-school">วิทยาลัยการอาชีพพนมไพร</div>
        <h2 class="report-title">รายงานการใช้รถยนต์และสั่งซื้อน้ำมันเชื้อเพลิง</h2>
        <div class="report-subtitle">ประจำเดือน <?= e($monthLabel) ?></div>
        <div class="report-meta">พิมพ์วันที่ <?= e(date('d/m/') . (date('Y') + 543) . ' เวลา ' . date('H:i') . ' น.') ?></div>
    </div>

    <!-- สรุปตัวเลข -->
    <div class="report-summary-grid">
        <div class="report-summary-card">
            <div class="rs-num"><?= $totalUsage ?></div>
            <div class="rs-label">คำขออนุมัติทั้งหมด</div>
        </div>
        <div class="report-summary-card">
            <div class="rs-num"><?= $totalFuel ?></div>
            <div class="rs-label">รายการสั่งซื้อน้ำมัน</div>
        </div>
        <div class="report-summary-card">
            <div class="rs-num"><?= number_format($totalFuelAmt, 2) ?></div>
            <div class="rs-label">ยอดเงินน้ำมันรวม (บาท)</div>
        </div>
        <div class="report-summary-card">
            <div class="rs-num"><?= count($byVehicle) ?></div>
            <div class="rs-label">รถที่มีการใช้งาน</div>
        </div>
    </div>

    <!-- สรุปตามรถ -->
    <?php if (!empty($byVehicle)): ?>
    <div class="report-section">
        <div class="report-section-title">สรุปการใช้รถแยกตามคัน</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th>รถยนต์</th>
                    <th class="text-end">จำนวนครั้ง</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byVehicle as $vname => $cnt): ?>
                <tr>
                    <td><?= e($vname) ?></td>
                    <td class="text-end fw-semibold"><?= $cnt ?> ครั้ง</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ตารางรายละเอียดการใช้รถ -->
    <div class="report-section">
        <div class="report-section-title">รายละเอียดการใช้รถยนต์</div>
        <?php if (empty($usageRows)): ?>
            <p class="text-muted">ไม่มีข้อมูลสำหรับเดือนนี้</p>
        <?php else: ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width:28px">ที่</th>
                    <th>Tracking ID</th>
                    <th>ผู้ขอ / ตำแหน่ง</th>
                    <th>สถานที่ / วัตถุประสงค์</th>
                    <th>วันที่ออกเดินทาง</th>
                    <th>วันที่กลับ</th>
                    <th>รถ / ทะเบียน</th>
                    <th>คนขับ</th>
                    <th class="text-center">น้ำมัน</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usageRows as $i => $r): ?>
                <tr>
                    <td class="text-center text-muted"><?= $i + 1 ?></td>
                    <td class="fw-semibold" style="font-size:11px"><?= e($r['tracking_id']) ?></td>
                    <td>
                        <?= e($r['requester_name']) ?><br>
                        <span class="text-muted" style="font-size:11px"><?= e($r['requester_position']) ?></span>
                    </td>
                    <td>
                        <?= e(mb_strimwidth($r['destination'], 0, 30, '…')) ?><br>
                        <span class="text-muted" style="font-size:11px"><?= e(mb_strimwidth($r['purpose'], 0, 40, '…')) ?></span>
                    </td>
                    <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($r['travel_start_at']))) ?></td>
                    <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($r['travel_end_at']))) ?></td>
                    <td>
                        <?= e($r['vehicle_name'] ?? '-') ?><br>
                        <span class="fw-semibold" style="font-size:11px"><?= e($r['license_plate'] ?? '') ?></span>
                    </td>
                    <td><?= e($r['assigned_driver_name'] ?? '-') ?></td>
                    <td class="text-center">
                        <?= $r['fuel_purchase_requested'] ? '✔' : '-' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ตารางรายละเอียดน้ำมัน -->
    <div class="report-section">
        <div class="report-section-title">รายละเอียดการสั่งซื้อน้ำมันเชื้อเพลิง</div>
        <?php if (empty($fuelRows)): ?>
            <p class="text-muted">ไม่มีการสั่งซื้อน้ำมันในเดือนนี้</p>
        <?php else: ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width:28px">ที่</th>
                    <th>Tracking ID</th>
                    <th>ผู้ขอ</th>
                    <th>วันที่</th>
                    <th>รถ / ทะเบียน</th>
                    <th>ชนิดน้ำมัน</th>
                    <th class="text-end">ปริมาณ</th>
                    <th class="text-end">ยอดเงิน (บาท)</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $grandTotal = 0;
            foreach ($fuelRows as $i => $f):
                $grandTotal += (float) $f['fuel_total_amount'];
            ?>
                <tr>
                    <td class="text-center text-muted"><?= $i + 1 ?></td>
                    <td class="fw-semibold" style="font-size:11px"><?= e($f['tracking_id']) ?></td>
                    <td><?= e($f['requester_name']) ?></td>
                    <td style="white-space:nowrap"><?= e(date('d/m/Y', strtotime($f['travel_start_at']))) ?></td>
                    <td>
                        <?= e($f['vehicle_name'] ?? '-') ?><br>
                        <span class="fw-semibold" style="font-size:11px"><?= e($f['license_plate'] ?? '') ?></span>
                    </td>
                    <td><?= e($fuelLabels[$f['fuel_type']] ?? $f['fuel_type']) ?></td>
                    <td class="text-end"><?= e(number_format((float)$f['fuel_quantity'], 2)) ?> <?= e($f['fuel_unit'] ?? 'ลิตร') ?></td>
                    <td class="text-end fw-semibold"><?= e(number_format((float)$f['fuel_total_amount'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-warning fw-bold">
                    <td colspan="7" class="text-end">รวมทั้งสิ้น</td>
                    <td class="text-end"><?= e(number_format($grandTotal, 2)) ?> บาท</td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>

    <!-- ลายเซ็นท้ายรายงาน -->
    <div class="report-sigs">
        <div class="report-sig-box">
            <div class="report-sig-line">ลงชื่อ .................................................</div>
            <div class="report-sig-name">( ........................................................ )</div>
            <div class="report-sig-role">ผู้จัดทำรายงาน</div>
            <div class="report-sig-date">วันที่ ........................................</div>
        </div>
        <div class="report-sig-box">
            <div class="report-sig-line">ลงชื่อ .................................................</div>
            <div class="report-sig-name">( ........................................................ )</div>
            <div class="report-sig-role">หัวหน้างานพัสดุ</div>
            <div class="report-sig-date">วันที่ ........................................</div>
        </div>
        <div class="report-sig-box">
            <div class="report-sig-line">ลงชื่อ .................................................</div>
            <div class="report-sig-name">( ........................................................ )</div>
            <div class="report-sig-role">ผู้อำนวยการวิทยาลัย</div>
            <div class="report-sig-date">วันที่ ........................................</div>
        </div>
    </div>

</div><!-- end report-body -->

<style>
/* ===================== REPORT STYLES ===================== */
.report-header { text-align:center; margin-bottom:18px; padding-bottom:12px; border-bottom:2px solid #163b68; }
.report-school { font-size:16px; font-weight:700; color:#163b68; }
.report-title  { font-size:15px; font-weight:700; margin:4px 0 2px; }
.report-subtitle { font-size:14px; font-weight:600; color:#163b68; }
.report-meta   { font-size:12px; color:#6b7280; margin-top:4px; }

.report-summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.report-summary-card { text-align:center; background:#f0f6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px 8px; }
.rs-num  { font-size:24px; font-weight:800; color:#163b68; }
.rs-label{ font-size:12px; color:#4b5563; margin-top:2px; }

.report-section { margin-bottom:20px; }
.report-section-title {
    font-size:13px; font-weight:700; color:#163b68;
    background:#dbeafe; border-left:4px solid #163b68;
    padding:5px 10px; margin-bottom:8px; border-radius:0 6px 6px 0;
}

.report-table { width:100%; border-collapse:collapse; font-size:12px; }
.report-table th {
    background:#163b68; color:#fff; padding:6px 8px;
    text-align:left; font-weight:600;
}
.report-table td { padding:5px 8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
.report-table tbody tr:nth-child(even) { background:#f8fafc; }
.report-table tfoot td { background:#fef9c3; padding:6px 8px; border-top:2px solid #d97706; }

.report-sigs { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:30px; }
.report-sig-box { text-align:center; }
.report-sig-line { margin-bottom:6px; margin-top:30px; }
.report-sig-name, .report-sig-role, .report-sig-date { font-size:12px; margin-top:3px; color:#374151; }
.report-sig-role { font-weight:600; }

/* =================== PRINT STYLES =================== */
@media print {
    .no-print, .site-header, .site-footer, nav, .page-heading { display:none !important; }
    body, html { background:white !important; }
    #report-body { padding:0; margin:0; }
    .report-summary-grid { grid-template-columns:repeat(4,1fr); }
    .report-table th { background:#163b68 !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .report-table tbody tr:nth-child(even) { background:#f8fafc !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .report-section-title { background:#dbeafe !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .report-summary-card { background:#f0f6ff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    @page { size:A4 landscape; margin:12mm 15mm; }
}
</style>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
