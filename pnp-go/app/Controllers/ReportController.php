<?php

final class ReportController
{
    public function index(): void
    {
        require_auth();

        $month = $_GET['month'] ?? date('Y-m');
        // Validate format YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        [$year, $mon] = explode('-', $month);
        $monthStart = $month . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $monthLabel = $this->thaiMonth((int) $mon) . ' ' . ((int) $year + 543);

        $db = Database::connection();

        // รายงานการใช้รถ: คำขอที่อนุมัติแล้วในเดือนนี้
        $usageStmt = $db->prepare(
            'SELECT r.tracking_id, r.requester_name, r.requester_position,
                    r.destination, r.purpose,
                    r.travel_start_at, r.travel_end_at,
                    r.passenger_count,
                    r.assigned_driver_name,
                    v.vehicle_name, v.license_plate,
                    r.fuel_purchase_requested,
                    r.fuel_type, r.fuel_quantity, r.fuel_total_amount
             FROM requisitions r
             LEFT JOIN vehicles v ON v.id = r.assigned_vehicle_id
             WHERE r.status = \'approved\'
               AND DATE(r.travel_start_at) BETWEEN :start AND :end
             ORDER BY r.travel_start_at ASC'
        );
        $usageStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
        $usageRows = $usageStmt->fetchAll();

        // สรุปน้ำมัน: คำขอสั่งซื้อน้ำมันที่อนุมัติในเดือนนี้
        $fuelStmt = $db->prepare(
            'SELECT r.tracking_id, r.requester_name,
                    r.travel_start_at,
                    r.fuel_type, r.fuel_quantity, r.fuel_unit, r.fuel_total_amount,
                    v.vehicle_name, v.license_plate,
                    fv.name AS vendor_name
             FROM requisitions r
             LEFT JOIN vehicles v  ON v.id  = r.assigned_vehicle_id
             LEFT JOIN fuel_vendors fv ON fv.is_default = 1
             WHERE r.status = \'approved\'
               AND r.fuel_purchase_requested = 1
               AND DATE(r.travel_start_at) BETWEEN :start AND :end
             ORDER BY r.travel_start_at ASC'
        );
        $fuelStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
        $fuelRows = $fuelStmt->fetchAll();

        // สรุปตัวเลข
        $totalUsage    = count($usageRows);
        $totalFuel     = count($fuelRows);
        $totalFuelAmt  = array_sum(array_column($fuelRows, 'fuel_total_amount'));

        // รายงานตามรถ
        $byVehicle = [];
        foreach ($usageRows as $r) {
            $key = ($r['vehicle_name'] ?? '-') . ' (' . ($r['license_plate'] ?? '-') . ')';
            $byVehicle[$key] = ($byVehicle[$key] ?? 0) + 1;
        }

        render('admin/report', [
            'title'        => 'รายงานประจำเดือน',
            'user'         => require_auth(),
            'month'        => $month,
            'monthLabel'   => $monthLabel,
            'usageRows'    => $usageRows,
            'fuelRows'     => $fuelRows,
            'totalUsage'   => $totalUsage,
            'totalFuel'    => $totalFuel,
            'totalFuelAmt' => $totalFuelAmt,
            'byVehicle'    => $byVehicle,
            'fuelLabels'   => $this->fuelLabels(),
        ]);
    }

    private function thaiMonth(int $m): string
    {
        return [
            1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน',
            5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม',
            9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม',
        ][$m] ?? '';
    }

    private function fuelLabels(): array
    {
        return [
            'gasoline_91' => 'แก็สโซฮอล์ 91',
            'gasoline_95' => 'แก็สโซฮอล์ 95',
            'diesel'      => 'ดีเซล',
            'engine_oil'  => 'น้ำมันเครื่อง',
            'other'       => 'อื่นๆ',
        ];
    }
}
