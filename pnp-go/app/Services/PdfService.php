<?php

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class PdfService
{
    public function generateForRequisition(int $requisitionId): string
    {
        $requisition = $this->requisition($requisitionId);

        if ($requisition === null) {
            throw new RuntimeException('Requisition not found.');
        }

        $approvers = $this->approvers($requisition);
        $config = config('pdf');

        if (!is_dir($config['output_dir'])) {
            mkdir($config['output_dir'], 0775, true);
        }

        if (!is_dir($config['temp_dir'])) {
            mkdir($config['temp_dir'], 0775, true);
        }

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $defaultFontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $defaultFontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 8,
            'margin_bottom' => 8,
            'fontDir'       => array_merge($defaultFontDirs, [$config['font_dir']]),
            'fontdata'      => $defaultFontData + $config['font_data'],
            'default_font'  => $config['font'],
            'tempDir'       => $config['temp_dir'],
        ]);

        $mpdf->autoScriptToLang = false;
        $mpdf->autoLangToFont = false;
        $mpdf->SetTitle('ใบขออนุญาตใช้รถยนต์และสั่งซื้อน้ำมัน ' . $requisition['tracking_id']);

        // หน้าเดียว: ใบขออนุญาต + ใบสั่งซื้อน้ำมัน
        $mpdf->WriteHTML($this->html($requisition, $approvers));

        $relativePath = 'storage/pdfs/requisition_' . $requisition['tracking_id'] . '.pdf';
        $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
        $mpdf->Output($absolutePath, Destination::FILE);

        $statement = Database::connection()->prepare('UPDATE requisitions SET pdf_path = :pdf_path WHERE id = :id');
        $statement->execute([
            'pdf_path' => $relativePath,
            'id' => $requisitionId,
        ]);

        return $relativePath;
    }

    private function requisition(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.*, rv.vehicle_name requested_vehicle_name, rv.license_plate requested_license_plate,
                    av.vehicle_name assigned_vehicle_name, av.license_plate assigned_license_plate
             FROM requisitions r
             LEFT JOIN vehicles rv ON rv.id = r.requested_vehicle_id
             LEFT JOIN vehicles av ON av.id = r.assigned_vehicle_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    private function approvers(array $requisition): array
    {
        $ids = array_filter([
            $requisition['level1_approved_by'] ?? null,
            $requisition['level2_approved_by'] ?? null,
            $requisition['level3_approved_by'] ?? null,
        ]);

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare("SELECT * FROM users WHERE id IN ({$placeholders})");
        $statement->execute(array_values($ids));
        $users = [];

        foreach ($statement->fetchAll() as $user) {
            $users[(int) $user['id']] = $user;
        }

        return [
            1 => $users[(int) ($requisition['level1_approved_by'] ?? 0)] ?? null,
            2 => $users[(int) ($requisition['level2_approved_by'] ?? 0)] ?? null,
            3 => $users[(int) ($requisition['level3_approved_by'] ?? 0)] ?? null,
        ];
    }

    private function html(array $requisition, array $approvers): string
    {
        $start = strtotime($requisition['travel_start_at']);
        $end = strtotime($requisition['travel_end_at']);
        $approvedAt = $requisition['level3_approved_at'] ? strtotime($requisition['level3_approved_at']) : time();
        $requestedVehicle = $this->vehicleText($requisition, 'requested');
        $assignedVehicle = $this->vehicleText($requisition, 'assigned');
        $fuelLabel = DashboardController::fuelTypeLabel($requisition['fuel_type'] ?? null);
        $fuelText = (int) $requisition['fuel_purchase_requested']
            ? $fuelLabel . ' จำนวนเงิน ' . number_format((float) $requisition['fuel_total_amount'], 2) . ' บาท'
            : 'ไม่สั่งซื้อน้ำมันเชื้อเพลิง';

        return '<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: "thsarabunnew", sans-serif; }
        body { color: #172033; font-size: 12px; line-height: 1.25; }
        .header { border-bottom: 2px solid #163b68; padding-bottom: 5px; margin-bottom: 6px; }
        .document-title { color: #163b68; font-size: 15px; font-weight: bold; text-align: center; margin: 0; }
        .subtitle { text-align: center; color: #4b5563; margin-top: 1px; font-size: 11px; }
        .meta { width: 100%; margin-top: 5px; font-size: 11px; }
        .meta td { padding: 1px 0; }
        .section { border: 1px solid #cbd5e1; border-radius: 4px; margin-top: 5px; padding: 5px 8px; }
        .section-title { color: #163b68; font-weight: bold; font-size: 12px; margin-bottom: 3px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; padding: 2px 4px; border-bottom: 1px solid #e5e7eb; }
        .label { color: #64748b; width: 28%; white-space: nowrap; }
        .value { color: #111827; font-weight: bold; }
        .approval-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .approval-table th, .approval-table td { border: 1px solid #cbd5e1; padding: 3px 4px; vertical-align: top; text-align: center; font-size: 11px; }
        .approval-table th { background: #eef3f8; color: #163b68; }
        .signature-box { height: 16mm; }
        .signature-img { max-width: 30mm; max-height: 12mm; margin-bottom: 1mm; }
        .muted { color: #64748b; font-weight: normal; }
        .footer { margin-top: 4px; font-size: 9px; color: #64748b; text-align: right; }
        /* ===== ใบสั่งซื้อ ===== */
        .fuel-divider { border-top: 2px dashed #163b68; margin-top: 8px; padding-top: 6px; }
        .fuel-title { color: #163b68; font-size: 13px; font-weight: bold; text-align: center; margin: 0 0 4px; }
        .fuel-to { font-size: 12px; margin-bottom: 4px; }
        table.order-table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 12px; }
        table.order-table th, table.order-table td { border: 1px solid #000; padding: 3px 5px; vertical-align: middle; }
        table.order-table th { text-align: center; font-weight: bold; background: #f5f5f5; }
        .col-qty { width: 11%; text-align: center; }
        .col-item { width: 45%; }
        .col-unit { width: 12%; text-align: center; }
        .col-baht { width: 16%; text-align: center; }
        .col-satang { width: 16%; text-align: center; }
        .sig-block { margin-top: 6px; font-size: 12px; line-height: 1.8; }
        .dots { border-bottom: 1px dotted #000; display: inline-block; min-width: 100px; }
        .dots-long { border-bottom: 1px dotted #000; display: inline-block; min-width: 200px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="document-title">ใบขออนุญาตใช้รถยนต์และสั่งซื้อน้ำมันเชื้อเพลิง</h1>
        <div class="subtitle">วิทยาลัยการอาชีพพนมไพร</div>
        <table class="meta">
            <tr>
                <td><strong>Tracking ID:</strong> ' . e($requisition['tracking_id']) . '</td>
                <td style="text-align:right;"><strong>วันที่อนุมัติ:</strong> ' . e($this->thaiDate($approvedAt)) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">1. ข้อมูลผู้ขออนุญาต</div>
        <table class="grid">
            <tr>
                <td class="label">ชื่อ-สกุล</td>
                <td class="value">' . e($requisition['requester_name']) . '</td>
                <td class="label">ตำแหน่ง</td>
                <td class="value">' . e($requisition['requester_position']) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. รายละเอียดการเดินทาง</div>
        <table class="grid">
            <tr>
                <td class="label">สถานที่ไปราชการ</td>
                <td class="value" colspan="3">' . e($requisition['destination']) . '</td>
            </tr>
            <tr>
                <td class="label">ตำบล</td>
                <td class="value">' . e($requisition['destination_subdistrict'] ?: '-') . '</td>
                <td class="label">อำเภอ / จังหวัด</td>
                <td class="value">' . e(($requisition['destination_district'] ?: '-') . ' / ' . ($requisition['destination_province'] ?: '-')) . '</td>
            </tr>
            <tr>
                <td class="label">ออกเดินทาง</td>
                <td class="value">' . e($this->thaiDateTime($start)) . '</td>
                <td class="label">กลับ</td>
                <td class="value">' . e($this->thaiDateTime($end)) . '</td>
            </tr>
            <tr>
                <td class="label">ระยะทางโดยประมาณ</td>
                <td class="value">' . e($requisition['distance_km'] ? number_format((float) $requisition['distance_km'], 2) . ' กม.' : '-') . '</td>
                <td class="label">เลขไมล์ก่อนออกเดินทาง</td>
                <td class="value">' . e($requisition['odometer_before'] ?: '-') . '</td>
            </tr>
            <tr>
                <td class="label">ภารกิจ</td>
                <td class="value" colspan="3">' . nl2br(e($requisition['purpose'])) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">3. ผู้ร่วมเดินทาง รถยนต์ และน้ำมันเชื้อเพลิง</div>
        <table class="grid">
            <tr>
                <td class="label">จำนวนผู้ร่วมเดินทาง</td>
                <td class="value">' . e((string) $requisition['passenger_count']) . ' คน</td>
                <td class="label">รายชื่อ</td>
                <td class="value">' . e($requisition['passenger_names'] ?: '-') . '</td>
            </tr>
            <tr>
                <td class="label">รถที่ผู้ขอเลือก</td>
                <td class="value">' . e($requestedVehicle) . '</td>
                <td class="label">รถที่มอบหมาย</td>
                <td class="value">' . e($assignedVehicle) . '</td>
            </tr>
            <tr>
                <td class="label">พนักงานขับรถ</td>
                <td class="value">' . e($requisition['assigned_driver_name'] ?: '-') . '</td>
                <td class="label">การสั่งซื้อน้ำมัน</td>
                <td class="value">' . e($fuelText) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">4. การอนุมัติ</div>
        <table class="approval-table">
            <tr>
                <th>หัวหน้างานพัสดุ</th>
                <th>รองผู้อำนวยการฝ่ายบริหารทรัพยากร</th>
                <th>ผู้อำนวยการ</th>
            </tr>
            <tr>
                ' . $this->approvalCell($approvers[1] ?? null, $requisition['level1_approved_at'] ?? null) . '
                ' . $this->approvalCell($approvers[2] ?? null, $requisition['level2_approved_at'] ?? null) . '
                ' . $this->approvalCell($approvers[3] ?? null, $requisition['level3_approved_at'] ?? null) . '
            </tr>
        </table>
    </div>

    <div class="footer">สร้างเอกสารโดยระบบ เมื่อวันที่ ' . e($this->thaiDateTime(time())) . '</div>

    ' . $this->fuelOrderSection($requisition) . '
</body>
</html>';
    }

    private function fuelOrderSection(array $requisition): string
    {
        // ดึงร้านน้ำมัน default จาก DB
        $vendorStmt = Database::connection()->query(
            "SELECT * FROM fuel_vendors WHERE is_default = 1 AND is_active = 1 LIMIT 1"
        );
        $vendor = $vendorStmt->fetch() ?: ['name' => '-', 'address' => '-', 'phone' => '-'];

        $hasFuel     = (int) ($requisition['fuel_purchase_requested'] ?? 0) === 1;
        $fuelType    = $requisition['fuel_type'] ?? '';
        $total       = $hasFuel ? (float) ($requisition['fuel_total_amount'] ?? 0) : 0;
        $qtyRaw      = (float) ($requisition['fuel_quantity'] ?? 0);
        $qty         = $hasFuel && $qtyRaw > 0 ? number_format($qtyRaw, 2) : '-';
        $unitPrice   = $hasFuel && $total > 0 && $qtyRaw > 0 ? number_format($total / $qtyRaw, 2) : '-';
        $totalBaht   = $hasFuel ? number_format((int) floor($total), 0, '.', ',') : '-';
        $totalSatang = $hasFuel ? str_pad((string) round(fmod($total, 1) * 100), 2, '0', STR_PAD_LEFT) : '-';
        $amountText  = $hasFuel && $total > 0 ? $this->thaiAmountText($total) : '-';

        $isGasohol91 = $hasFuel && $fuelType === 'gasoline_91';
        $isGasohol95 = $hasFuel && $fuelType === 'gasoline_95';
        $isDiesel    = $hasFuel && $fuelType === 'diesel';
        $isEngineOil = $hasFuel && $fuelType === 'engine_oil';
        $isOther     = $hasFuel && ($fuelType === 'other');

        $chk91 = $isGasohol91 ? '[/]' : '[  ]';
        $chk95 = $isGasohol95 ? '[/]' : '[  ]';
        $chkDs = $isDiesel    ? '[/]' : '[  ]';
        $chkEo = $isEngineOil ? '[/]' : '[  ]';

        $isRow1 = $isGasohol91 || $isGasohol95;
        $isRow2 = $isDiesel || $isEngineOil || $isOther;

        $qty1  = $isRow1 ? $qty : '-';
        $up1   = $isRow1 ? $unitPrice : '-';
        $b1    = $isRow1 ? $totalBaht : '-';
        $s1    = $isRow1 ? $totalSatang : '-';

        $qty2  = $isRow2 ? $qty : '-';
        $up2   = $isRow2 ? $unitPrice : '-';
        $b2    = $isRow2 ? $totalBaht : '-';
        $s2    = $isRow2 ? $totalSatang : '-';

        return '
<div class="fuel-divider">
    <div class="fuel-title">ใบสั่งซื้อน้ำมันเชื้อเพลิง</div>
    <div class="fuel-to">
        <strong>เรียน</strong>&nbsp;&nbsp;&nbsp;
        ผู้จัดการ ' . e($vendor['name']) . ' &nbsp;&nbsp; ที่อยู่ ' . e($vendor['address']) . ' &nbsp; ' . e($vendor['phone']) . '<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        วิทยาลัยการอาชีพพนมไพร ขอสั่งซื้อสินค้าเงินเชื่อค่าน้ำมันเชื้อเพลิงในบัญชีของวิทยาลัยฯ ดังนี้
    </div>
    <table class="order-table">
        <thead>
            <tr>
                <th class="col-qty" rowspan="2">จำนวน</th>
                <th class="col-item" rowspan="2">รายการ</th>
                <th class="col-unit" rowspan="2">หน่วยละ</th>
                <th colspan="2">จำนวนเงิน</th>
            </tr>
            <tr>
                <th class="col-baht">บาท</th>
                <th class="col-satang">สต.</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="col-qty">' . $qty1 . '</td>
                <td class="col-item">ลิตร &nbsp; แก็สโซฮอล์ &nbsp; ' . $chk91 . ' 91 &nbsp; ' . $chk95 . ' 95</td>
                <td class="col-unit">' . $up1 . '</td>
                <td class="col-baht">' . $b1 . '</td>
                <td class="col-satang">' . $s1 . '</td>
            </tr>
            <tr>
                <td class="col-qty">' . $qty2 . '</td>
                <td class="col-item">ลิตร &nbsp; น้ำมัน &nbsp; ' . $chkDs . ' ดีเซล &nbsp; ' . $chkEo . ' น้ำมันเครื่อง</td>
                <td class="col-unit">' . $up2 . '</td>
                <td class="col-baht">' . $b2 . '</td>
                <td class="col-satang">' . $s2 . '</td>
            </tr>
            <tr>
                <td colspan="2" style="font-weight:bold;">ตัวอักษร (' . e($amountText) . ')</td>
                <td class="col-unit" style="font-weight:bold;">รวมเงิน</td>
                <td class="col-baht" style="font-weight:bold;">' . $totalBaht . '</td>
                <td class="col-satang" style="font-weight:bold;">' . $totalSatang . '</td>
            </tr>
        </tbody>
    </table>
    <div class="sig-block">
        &nbsp;<br>
        ลงชื่อ................................................ผู้จัดการ/ผู้ให้บริการ &nbsp;&nbsp; กรุณาลงบันทึก วัน/เวลา ที่ให้บริการเติมน้ำมัน<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        (................................................)<br>
        วันที่.................................................. เวลา................... น.
    </div>
</div>';
    }

    private function fuelOrderHtml(array $requisition): string
    {
        $hasFuel = (int) ($requisition['fuel_purchase_requested'] ?? 0) === 1;
        $fuelType = $requisition['fuel_type'] ?? '';
        $qty      = $hasFuel ? number_format((float) ($requisition['fuel_quantity'] ?? 0), 2) : '-';
        $total    = $hasFuel ? (float) ($requisition['fuel_total_amount'] ?? 0) : 0;
        $totalFmt = $hasFuel ? number_format($total, 2) : '-';
        $unitPrice = $hasFuel && $total > 0 && (float) ($requisition['fuel_quantity'] ?? 0) > 0
            ? number_format($total / (float) $requisition['fuel_quantity'], 2)
            : '-';
        $amountText = $hasFuel && $total > 0 ? $this->thaiAmountText($total) : '-';

        // Checkboxes for gasohol vs diesel/engine-oil
        $isGasohol91 = $hasFuel && $fuelType === 'gasoline_91';
        $isGasohol95 = $hasFuel && $fuelType === 'gasoline_95';
        $isDiesel    = $hasFuel && $fuelType === 'diesel';
        $isEngineOil = $hasFuel && $fuelType === 'engine_oil';
        $isOther     = $hasFuel && $fuelType === 'other';

        $chk91  = $isGasohol91 ? '&#9745;' : '&#9744;';
        $chk95  = $isGasohol95 ? '&#9745;' : '&#9744;';
        $chkDs  = ($isDiesel)    ? '&#9745;' : '&#9744;';
        $chkEo  = ($isEngineOil) ? '&#9745;' : '&#9744;';

        // Row 1: แก็สโซฮอล์
        $qty1  = ($isGasohol91 || $isGasohol95) ? number_format((float)($requisition['fuel_quantity'] ?? 0), 2) : ($hasFuel ? '-' : '-');
        $up1   = ($isGasohol91 || $isGasohol95) ? $unitPrice : '-';
        $tot1  = ($isGasohol91 || $isGasohol95) ? $totalFmt  : '-';

        // Row 2: ดีเซล/น้ำมันเครื่อง/อื่นๆ
        $qty2  = ($isDiesel || $isEngineOil || $isOther) ? number_format((float)($requisition['fuel_quantity'] ?? 0), 2) : ($hasFuel ? '-' : '-');
        $up2   = ($isDiesel || $isEngineOil || $isOther) ? $unitPrice : '-';
        $tot2  = ($isDiesel || $isEngineOil || $isOther) ? $totalFmt  : '-';

        // Split total into baht and satang
        $totalBaht   = $hasFuel ? number_format((int) floor($total), 0, '.', ',') : '-';
        $totalSatang = $hasFuel ? str_pad((string)round(fmod($total, 1) * 100), 2, '0', STR_PAD_LEFT) : '-';

        $approvedAt = $requisition['level3_approved_at'] ? strtotime($requisition['level3_approved_at']) : time();

        return '<!doctype html>
<html lang="th"><head><meta charset="utf-8">
<style>
* { font-family: "thsarabunnew", sans-serif; }
body { color: #000; font-size: 15px; line-height: 1.5; margin: 0; padding: 0; }
.to-block { margin-bottom: 10px; font-size: 15px; }
.to-block .to-label { font-weight: bold; display: inline; }
table.order-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 15px; }
table.order-table th, table.order-table td { border: 1px solid #000; padding: 5px 8px; vertical-align: middle; }
table.order-table th { text-align: center; font-weight: bold; background: #f5f5f5; }
.col-qty   { width: 12%; text-align: center; }
.col-item  { width: 44%; }
.col-unit  { width: 12%; text-align: center; }
.col-baht  { width: 16%; text-align: center; }
.col-satang{ width: 16%; text-align: center; }
.amount-text { font-weight: bold; }
.sig-block { margin-top: 16px; font-size: 15px; line-height: 2; }
.dots { border-bottom: 1px dotted #000; display: inline-block; min-width: 160px; }
.dots-long { border-bottom: 1px dotted #000; display: inline-block; min-width: 260px; }
.page-title { text-align: center; font-size: 17px; font-weight: bold; margin-bottom: 10px; border-bottom: 2px solid #163b68; padding-bottom: 6px; color: #163b68; }
.tracking-info { font-size: 12px; color: #555; margin-bottom: 8px; }
</style>
</head><body>
<div class="page-title">ใบสั่งซื้อน้ำมันเชื้อเพลิง</div>
<div class="tracking-info">Tracking ID: ' . e($requisition['tracking_id']) . ' &nbsp;|&nbsp; วันที่อนุมัติ: ' . e($this->thaiDate($approvedAt)) . '</div>

<div class="to-block">
    <span class="to-label">เรียน &nbsp;&nbsp;&nbsp;</span>ผู้จัดการ หจก.โพลีพัฒนกิจ &nbsp;&nbsp; ที่อยู่ 91 ม.1 ต.สระแก้ว อ.พนมไพร จ.ร้อยเอ็ด &nbsp; 043-590619<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    วิทยาลัยการอาชีพพนมไพร ขอสั่งซื้อสินค้าเงินเชื่อค่าน้ำมันเชื้อเพลิงในบัญชีของวิทยาลัยฯ ดังนี้
</div>

<table class="order-table">
    <thead>
        <tr>
            <th class="col-qty" rowspan="2">จำนวน</th>
            <th class="col-item" rowspan="2">รายการ</th>
            <th class="col-unit" rowspan="2">หน่วยละ</th>
            <th colspan="2">จำนวนเงิน</th>
        </tr>
        <tr>
            <th class="col-baht">บาท</th>
            <th class="col-satang">สต.</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-qty">' . $qty1 . '</td>
            <td class="col-item">ลิตร &nbsp; แก็สโซฮอล์ &nbsp; ' . $chk91 . ' 91 &nbsp; ' . $chk95 . ' 95</td>
            <td class="col-unit">' . $up1 . '</td>
            <td class="col-baht">' . ($isGasohol91 || $isGasohol95 ? $totalBaht : '-') . '</td>
            <td class="col-satang">' . ($isGasohol91 || $isGasohol95 ? $totalSatang : '-') . '</td>
        </tr>
        <tr>
            <td class="col-qty">' . $qty2 . '</td>
            <td class="col-item">ลิตร &nbsp; น้ำมัน &nbsp; ' . $chkDs . ' ดีเซล &nbsp; ' . $chkEo . ' น้ำมันเครื่อง</td>
            <td class="col-unit">' . $up2 . '</td>
            <td class="col-baht">' . ($isDiesel || $isEngineOil || $isOther ? $totalBaht : '-') . '</td>
            <td class="col-satang">' . ($isDiesel || $isEngineOil || $isOther ? $totalSatang : '-') . '</td>
        </tr>
        <tr>
            <td colspan="2" class="amount-text">ตัวอักษร (' . e($amountText) . ')</td>
            <td class="col-unit" style="font-weight:bold;">รวมเงิน</td>
            <td class="col-baht" style="font-weight:bold;">' . $totalBaht . '</td>
            <td class="col-satang" style="font-weight:bold;">' . $totalSatang . '</td>
        </tr>
    </tbody>
</table>

<div class="sig-block">
    ลงชื่อ <span class="dots-long"></span> ผู้จัดการ/ผู้ให้บริการ &nbsp;&nbsp; กรุณาลงบันทึก วัน/เวลา ที่ให้บริการเติมน้ำมัน<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    (<span class="dots-long"></span>)
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    วันที่ <span class="dots" style="min-width:120px;"></span>
    &nbsp; เวลา <span class="dots" style="min-width:80px;"></span> น.
</div>
</body></html>';
    }

    private function thaiAmountText(float $amount): string
    {
        $ones  = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
        $tens  = ['', 'สิบ', 'ยี่สิบ', 'สามสิบ', 'สี่สิบ', 'ห้าสิบ', 'หกสิบ', 'เจ็ดสิบ', 'แปดสิบ', 'เก้าสิบ'];
        $units = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];

        $baht   = (int) floor($amount);
        $satang = (int) round(fmod($amount, 1) * 100);

        $convert = function (int $n) use ($ones, $tens): string {
            if ($n === 0) return 'ศูนย์';
            $result = '';
            $digits = str_split(str_pad((string) $n, 7, '0', STR_PAD_LEFT));
            $positions = ['ล้าน', 'แสน', 'หมื่น', 'พัน', 'ร้อย', 'สิบ', ''];
            foreach ($digits as $i => $d) {
                $d = (int) $d;
                if ($d === 0) continue;
                if ($positions[$i] === 'สิบ' && $d === 1) { $result .= 'สิบ'; continue; }
                if ($positions[$i] === 'สิบ' && $d === 2) { $result .= 'ยี่สิบ'; continue; }
                $result .= $ones[$d] . $positions[$i];
            }
            return $result;
        };

        $text = $convert($baht) . 'บาท';
        $text .= $satang > 0 ? $convert($satang) . 'สตางค์' : 'ถ้วน';
        return $text;
    }

    private function approvalCell(?array $user, ?string $approvedAt): string
    {
        $signature = $this->signatureHtml($user);
        $name = $user['full_name'] ?? '-';
        $position = $user['position_title'] ?? '';
        $date = $approvedAt ? $this->thaiDateTime(strtotime($approvedAt)) : '-';

        return '<td>
            <div class="signature-box">' . $signature . '</div>
            <div><strong>' . e($name) . '</strong></div>
            <div class="muted">' . e($position) . '</div>
            <div class="muted">อนุมัติเมื่อ ' . e($date) . '</div>
        </td>';
    }

    private function signatureHtml(?array $user): string
    {
        if (!$user || empty($user['signature_path'])) {
            return '<div class="muted">ลงนามอิเล็กทรอนิกส์</div>';
        }

        $path = dirname(__DIR__, 2) . '/' . $user['signature_path'];

        if (!is_file($path)) {
            return '<div class="muted">ลงนามอิเล็กทรอนิกส์</div>';
        }

        return '<img class="signature-img" src="' . e($this->pathForCss($path)) . '" alt="">';
    }

    private function vehicleText(array $requisition, string $type): string
    {
        $nameKey = $type . '_vehicle_name';
        $plateKey = $type . '_license_plate';
        $text = trim(($requisition[$nameKey] ?? '') . ' ' . ($requisition[$plateKey] ?? ''));

        return $text !== '' ? $text : '-';
    }

    private function thaiDateTime(int $timestamp): string
    {
        return $this->thaiDate($timestamp) . ' เวลา ' . date('H:i', $timestamp) . ' น.';
    }

    private function thaiDate(int $timestamp): string
    {
        $months = [
            1 => 'มกราคม',
            2 => 'กุมภาพันธ์',
            3 => 'มีนาคม',
            4 => 'เมษายน',
            5 => 'พฤษภาคม',
            6 => 'มิถุนายน',
            7 => 'กรกฎาคม',
            8 => 'สิงหาคม',
            9 => 'กันยายน',
            10 => 'ตุลาคม',
            11 => 'พฤศจิกายน',
            12 => 'ธันวาคม',
        ];

        return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
    }

    private function pathForCss(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
