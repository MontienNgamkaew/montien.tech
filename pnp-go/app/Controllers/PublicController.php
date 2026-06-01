<?php

final class PublicController
{
    public function requestForm(array $errors = [], array $old = []): void
    {
        $vehicles = $this->activeVehicles();

        render('public/request_form', [
            'title' => 'ยื่นคำขอ',
            'vehicles' => $vehicles,
            'errors' => $errors,
            'old' => $old,
            'positionOptions' => $this->positionOptions(),
            'fuelTypeOptions' => $this->fuelTypeOptions(),
        ]);
    }

    public function submitRequest(): void
    {
        $data = $this->requestData();
        $errors = $this->validateRequest($data);

        if ($errors !== []) {
            $this->requestForm($errors, $data);
            return;
        }

        $trackingId = $this->generateTrackingId();
        $fuelRequested = false;
        $fuelPurchaseRequested = $data['fuel_purchase_requested'] === '1';
        $fuelNotRequested = !$fuelPurchaseRequested;

        $sql = 'INSERT INTO requisitions (
            tracking_id, requester_name, requester_position, destination,
            destination_subdistrict, destination_district, destination_province,
            distance_km, odometer_before, travel_start_at, travel_end_at,
            purpose, passenger_count, passenger_names, requested_vehicle_id,
            fuel_requested, fuel_purchase_requested, fuel_not_requested,
            fuel_type, fuel_quantity, fuel_unit, fuel_total_amount, status, current_level
        ) VALUES (
            :tracking_id, :requester_name, :requester_position, :destination,
            :destination_subdistrict, :destination_district, :destination_province,
            :distance_km, :odometer_before, :travel_start_at, :travel_end_at,
            :purpose, :passenger_count, :passenger_names, :requested_vehicle_id,
            :fuel_requested, :fuel_purchase_requested, :fuel_not_requested,
            :fuel_type, :fuel_quantity, :fuel_unit, :fuel_total_amount, :status, :current_level
        )';

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $statement = $db->prepare($sql);
            $statement->execute([
                'tracking_id' => $trackingId,
                'requester_name' => $data['requester_name'],
                'requester_position' => $data['requester_position'],
                'destination' => $data['destination'],
                'destination_subdistrict' => $data['destination_subdistrict'] ?: null,
                'destination_district' => $data['destination_district'] ?: null,
                'destination_province' => $data['destination_province'] ?: null,
                'distance_km' => $data['distance_km'] ?: null,
                'odometer_before' => $data['odometer_before'] ?: null,
                'travel_start_at' => $this->toMysqlDateTime($data['travel_start_at']),
                'travel_end_at' => $this->toMysqlDateTime($data['travel_end_at']),
                'purpose' => $data['purpose'],
                'passenger_count' => (int) $data['passenger_count'],
                'passenger_names' => $data['passenger_names'] ?: null,
                'requested_vehicle_id' => $data['requested_vehicle_id'] ?: null,
                'fuel_requested' => $fuelRequested ? 1 : 0,
                'fuel_purchase_requested' => $fuelPurchaseRequested ? 1 : 0,
                'fuel_not_requested' => $fuelNotRequested ? 1 : 0,
                'fuel_type' => $fuelPurchaseRequested ? $data['fuel_type'] : null,
                'fuel_quantity' => null,
                'fuel_unit' => null,
                'fuel_total_amount' => $fuelPurchaseRequested ? $data['fuel_total_amount'] : null,
                'status' => 'pending_level_1',
                'current_level' => 1,
            ]);

            $requisitionId = (int) $db->lastInsertId();

            $log = $db->prepare('INSERT INTO approval_logs (
                requisition_id, approval_level, action, status_to, comment, ip_address, user_agent
            ) VALUES (
                :requisition_id, 1, :action, :status_to, :comment, :ip_address, :user_agent
            )');
            $log->execute([
                'requisition_id' => $requisitionId,
                'action' => 'submitted',
                'status_to' => 'pending_level_1',
                'comment' => 'Public requester submitted a requisition.',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);

            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        render('public/request_success', [
            'title' => 'ส่งคำขอสำเร็จ',
            'trackingId' => $trackingId,
        ]);
    }

    public function statusForm(?array $result = null, ?string $trackingId = null, ?string $error = null): void
    {
        render('public/status', [
            'title'        => 'ตรวจสอบสถานะ',
            'result'       => $result,
            'trackingId'   => $trackingId,
            'error'        => $error,
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function vehicleBoard(): void
    {
        $db = Database::connection();

        // สถานะรถปัจจุบัน
        $vStmt = $db->query(
            'SELECT v.id, v.vehicle_name, v.license_plate, v.vehicle_type,
                    r.id AS req_id, r.tracking_id, r.requester_name,
                    r.destination, r.travel_start_at, r.travel_end_at, r.assigned_driver_name
             FROM vehicles v
             LEFT JOIN requisitions r
                ON r.assigned_vehicle_id = v.id
                AND r.status = \'approved\'
                AND r.travel_start_at <= NOW()
                AND r.travel_end_at   >= NOW()
             WHERE v.is_active = 1
             ORDER BY v.vehicle_name ASC'
        );

        // คำขอล่าสุด 15 รายการ (ไม่แสดงที่ถูกยกเลิก)
        $rStmt = $db->query(
            'SELECT r.tracking_id, r.requester_name, r.destination,
                    r.travel_start_at, r.travel_end_at, r.status,
                    v.vehicle_name, v.license_plate
             FROM requisitions r
             LEFT JOIN vehicles v ON v.id = r.assigned_vehicle_id
             WHERE r.status NOT IN (\'cancelled\')
             ORDER BY r.created_at DESC
             LIMIT 15'
        );

        render('public/vehicle_board', [
            'title'        => 'สถานะรถยนต์',
            'vehicles'     => $vStmt->fetchAll(),
            'recentRequests' => $rStmt->fetchAll(),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function checkStatus(): void
    {
        $trackingId = strtoupper(trim($_POST['tracking_id'] ?? ''));

        if ($trackingId === '') {
            $this->statusForm(null, '', 'กรุณากรอก Tracking ID');
            return;
        }

        $statement = Database::connection()->prepare(
            'SELECT r.*, v.vehicle_name, v.license_plate
             FROM requisitions r
             LEFT JOIN vehicles v ON v.id = r.assigned_vehicle_id
             WHERE r.tracking_id = :tracking_id
             LIMIT 1'
        );
        $statement->execute(['tracking_id' => $trackingId]);
        $result = $statement->fetch() ?: null;

        if ($result === null) {
            $this->statusForm(null, $trackingId, 'ไม่พบคำขอจาก Tracking ID นี้');
            return;
        }

        $this->statusForm($result, $trackingId);
    }

    public function downloadPdf(): void
    {
        $trackingId = strtoupper(trim($_GET['tracking_id'] ?? ''));

        if ($trackingId === '') {
            view('ไม่พบไฟล์ PDF', '<h1 class="h4">กรุณาระบุ Tracking ID</h1>', 404);
            return;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, tracking_id, status, pdf_path FROM requisitions WHERE tracking_id = :tracking_id LIMIT 1'
        );
        $statement->execute(['tracking_id' => $trackingId]);
        $requisition = $statement->fetch();

        if (!$requisition || $requisition['status'] !== 'approved') {
            view('ยังไม่สามารถดาวน์โหลดได้', '<h1 class="h4">คำขอยังไม่ได้รับอนุมัติสมบูรณ์</h1>', 403);
            return;
        }

        $requisition['pdf_path'] = (new PdfService())->generateForRequisition((int) $requisition['id']);

        $path = dirname(__DIR__, 2) . '/' . $requisition['pdf_path'];
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="requisition_' . $requisition['tracking_id'] . '.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function activeVehicles(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, vehicle_name, license_plate, vehicle_type FROM vehicles WHERE is_active = 1 ORDER BY vehicle_name'
        );

        return $statement->fetchAll();
    }

    private function requestData(): array
    {
        return [
            'requester_name' => trim($_POST['requester_name'] ?? ''),
            'requester_position' => trim($_POST['requester_position'] ?? ''),
            'destination' => trim($_POST['destination'] ?? ''),
            'destination_subdistrict' => trim($_POST['destination_subdistrict'] ?? ''),
            'destination_district' => trim($_POST['destination_district'] ?? ''),
            'destination_province' => trim($_POST['destination_province'] ?? ''),
            'distance_km' => trim($_POST['distance_km'] ?? ''),
            'odometer_before' => trim($_POST['odometer_before'] ?? ''),
            'travel_start_at' => trim($_POST['travel_start_at'] ?? ''),
            'travel_end_at' => trim($_POST['travel_end_at'] ?? ''),
            'purpose' => trim($_POST['purpose'] ?? ''),
            'passenger_count' => trim($_POST['passenger_count'] ?? '0'),
            'passenger_names' => trim($_POST['passenger_names'] ?? ''),
            'requested_vehicle_id' => trim($_POST['requested_vehicle_id'] ?? ''),
            'fuel_purchase_requested' => ($_POST['fuel_purchase_requested'] ?? '0') === '1' ? '1' : '0',
            'fuel_type' => trim($_POST['fuel_type'] ?? ''),
            'fuel_total_amount' => trim($_POST['fuel_total_amount'] ?? ''),
        ];
    }

    private function validateRequest(array $data): array
    {
        $errors = [];

        foreach (['requester_name', 'requester_position', 'destination', 'travel_start_at', 'travel_end_at', 'purpose'] as $field) {
            if ($data[$field] === '') {
                $errors[$field] = 'กรุณากรอกข้อมูลนี้';
            }
        }

        if (!in_array($data['requester_position'], $this->positionOptions(), true)) {
            $errors['requester_position'] = 'กรุณาเลือกตำแหน่งจากรายการ';
        }

        if ($data['travel_start_at'] !== '' && $data['travel_end_at'] !== '') {
            $start = strtotime($data['travel_start_at']);
            $end = strtotime($data['travel_end_at']);

            if ($start === false || $end === false || $end < $start) {
                $errors['travel_end_at'] = 'วันเวลากลับต้องไม่น้อยกว่าวันเวลาออกเดินทาง';
            }
        }

        if ($data['passenger_count'] !== '' && !ctype_digit($data['passenger_count'])) {
            $errors['passenger_count'] = 'จำนวนผู้ร่วมเดินทางต้องเป็นตัวเลข';
        }

        foreach (['distance_km'] as $field) {
            if ($data[$field] !== '' && !is_numeric($data[$field])) {
                $errors[$field] = 'กรุณากรอกเป็นตัวเลข';
            }
        }

        if ($data['odometer_before'] !== '' && !ctype_digit($data['odometer_before'])) {
            $errors['odometer_before'] = 'เลขไมล์ต้องเป็นตัวเลขจำนวนเต็ม';
        }

        if ($data['fuel_purchase_requested'] === '1') {
            if (!array_key_exists($data['fuel_type'], $this->fuelTypeOptions())) {
                $errors['fuel_type'] = 'กรุณาเลือกชนิดน้ำมันเชื้อเพลิง';
            }

            if ($data['fuel_total_amount'] === '') {
                $errors['fuel_total_amount'] = 'กรุณากรอกจำนวนเงินที่สั่งซื้อ';
            } elseif (!is_numeric($data['fuel_total_amount']) || (float) $data['fuel_total_amount'] <= 0) {
                $errors['fuel_total_amount'] = 'จำนวนเงินต้องเป็นตัวเลขมากกว่า 0';
            }
        }

        return $errors;
    }

    private function toMysqlDateTime(string $value): string
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    private function generateTrackingId(): string
    {
        $db      = Database::connection();
        $dateStr = date('dmy'); // เช่น 150569

        // นับจำนวนคำขอที่ออกในวันนี้เพื่อคำนวณลำดับ
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM requisitions WHERE DATE(created_at) = CURDATE()"
        );
        $stmt->execute();
        $seq = (int) $stmt->fetchColumn() + 1;

        // ตรวจสอบซ้ำและเพิ่มลำดับหากชนกัน
        do {
            $trackingId = str_pad((string) $seq, 3, '0', STR_PAD_LEFT) . $dateStr;
            $check = $db->prepare('SELECT COUNT(*) FROM requisitions WHERE tracking_id = :id');
            $check->execute(['id' => $trackingId]);
            if ((int) $check->fetchColumn() === 0) break;
            $seq++;
        } while (true);

        return $trackingId;
    }

    private function positionOptions(): array
    {
        return [
            'ผู้อำนวยการ',
            'รองผู้อำนวยการ',
            'ข้าราชการครู',
            'พนักงานราชการ',
            'ครูพิเศษสอน',
            'เจ้าหน้าที่',
        ];
    }

    private function fuelTypeOptions(): array
    {
        return [
            'gasoline_95' => 'เบนซิน 95',
            'gasoline_91' => 'เบนซิน 91',
            'diesel' => 'ดีเซล',
            'engine_oil' => 'น้ำมันเครื่อง',
            'other' => 'น้ำมันเชื้อเพลิงประเภทอื่น',
        ];
    }

    private function statusLabels(): array
    {
        return [
            'submitted' => 'ส่งคำขอแล้ว',
            'pending_level_1' => 'รอหัวหน้างานพัสดุอนุมัติ',
            'pending_level_2' => 'รอรองผู้อำนวยการฝ่ายบริหารทรัพยากรอนุมัติ',
            'pending_level_3' => 'รอผู้อำนวยการอนุมัติ',
            'approved' => 'อนุมัติเรียบร้อย',
            'rejected' => 'ไม่อนุมัติ',
            'cancelled' => 'ยกเลิกคำขอ',
        ];
    }
}
