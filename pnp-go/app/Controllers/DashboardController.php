<?php

final class DashboardController
{
    private const ROLE_LEVELS = [
        'supply_head' => 1,
        'deputy_director' => 2,
        'director' => 3,
    ];

    public function index(): void
    {
        $user = require_auth();
        $level = self::ROLE_LEVELS[$user['role']] ?? null;

        render('admin/dashboard', [
            'title'               => 'แดชบอร์ดผู้อนุมัติ',
            'user'                => $user,
            'pendingRequisitions' => $this->pendingRequisitions($user),
            'vehicleStats'        => $this->vehicleStats(),
            'vehicleStatus'       => $this->vehicleStatus(),
            'fuelSummary'         => $this->fuelSummary(),
            'level'               => $level,
            'statusLabels'        => $this->statusLabels(),
        ]);
    }

    public function show(): void
    {
        $user = require_auth();
        $id = (int) ($_GET['id'] ?? 0);
        $requisition = $this->findRequisition($id);

        if ($requisition === null) {
            view('ไม่พบคำขอ', '<h1 class="h4">ไม่พบคำขอที่ต้องการ</h1>', 404);
            return;
        }

        render('admin/requisition_show', [
            'title' => 'รายละเอียดคำขอ',
            'user' => $user,
            'requisition' => $requisition,
            'vehicles' => $this->vehicles(),
            'logs' => $this->logs($id),
            'canApprove' => $this->canApprove($user, $requisition),
            'statusLabels' => $this->statusLabels(),
        ]);
    }

    public function approve(): void
    {
        verify_csrf();
        $user = require_auth();
        $id = (int) ($_POST['id'] ?? 0);
        $requisition = $this->findRequisition($id);

        if ($requisition === null || !$this->canApprove($user, $requisition)) {
            view('ไม่สามารถอนุมัติได้', '<h1 class="h4">คุณไม่มีสิทธิ์อนุมัติคำขอนี้</h1>', 403);
            return;
        }

        $level = (int) $requisition['current_level'];
        $comment = trim($_POST['comment'] ?? '');
        $db = Database::connection();
        $db->beginTransaction();

        try {
            if ($level === 1) {
                $vehicleId = trim($_POST['assigned_vehicle_id'] ?? '');
                $driverName = trim($_POST['assigned_driver_name'] ?? '');

                if ($vehicleId === '' || $driverName === '') {
                    $db->rollBack();
                    $this->showWithError($user, $requisition, 'กรุณาเลือกรถและกรอกชื่อพนักงานขับรถก่อนอนุมัติ');
                    return;
                }

                $update = $db->prepare(
                    'UPDATE requisitions
                     SET status = :status, current_level = 2, assigned_vehicle_id = :vehicle_id,
                         assigned_driver_name = :driver_name, level1_approved_by = :user_id,
                         level1_approved_at = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    'status' => 'pending_level_2',
                    'vehicle_id' => $vehicleId,
                    'driver_name' => $driverName,
                    'user_id' => $user['id'],
                    'id' => $id,
                ]);
                $statusTo = 'pending_level_2';
            } elseif ($level === 2) {
                $update = $db->prepare(
                    'UPDATE requisitions
                     SET status = :status, current_level = 3, level2_approved_by = :user_id,
                         level2_approved_at = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    'status' => 'pending_level_3',
                    'user_id' => $user['id'],
                    'id' => $id,
                ]);
                $statusTo = 'pending_level_3';
            } else {
                $update = $db->prepare(
                    'UPDATE requisitions
                     SET status = :status, current_level = 3, level3_approved_by = :user_id,
                         level3_approved_at = NOW()
                     WHERE id = :id'
                );
                $update->execute([
                    'status' => 'approved',
                    'user_id' => $user['id'],
                    'id' => $id,
                ]);
                $statusTo = 'approved';
            }

            $this->writeLog($db, $id, (int) $user['id'], $level, 'approved', $requisition['status'], $statusTo, $comment);
            $db->commit();

            if ($statusTo === 'approved') {
                (new PdfService())->generateForRequisition($id);
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        redirect('/dashboard');
    }

    public function reject(): void
    {
        verify_csrf();
        $user = require_auth();
        $id = (int) ($_POST['id'] ?? 0);
        $requisition = $this->findRequisition($id);
        $reason = trim($_POST['rejection_reason'] ?? '');

        if ($requisition === null || !$this->canApprove($user, $requisition)) {
            view('ไม่สามารถปฏิเสธได้', '<h1 class="h4">คุณไม่มีสิทธิ์ปฏิเสธคำขอนี้</h1>', 403);
            return;
        }

        if ($reason === '') {
            $this->showWithError($user, $requisition, 'กรุณากรอกเหตุผลที่ไม่อนุมัติ');
            return;
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $update = $db->prepare(
                'UPDATE requisitions
                 SET status = :status, rejected_by = :user_id, rejected_at = NOW(), rejection_reason = :reason
                 WHERE id = :id'
            );
            $update->execute([
                'status' => 'rejected',
                'user_id' => $user['id'],
                'reason' => $reason,
                'id' => $id,
            ]);

            $this->writeLog($db, $id, (int) $user['id'], (int) $requisition['current_level'], 'rejected', $requisition['status'], 'rejected', $reason);
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        redirect('/dashboard');
    }

    public function profile(?string $message = null, ?string $error = null): void
    {
        render('admin/profile', [
            'title' => 'ข้อมูลผู้ใช้งาน',
            'user' => require_auth(),
            'message' => $message,
            'error' => $error,
        ]);
    }

    public function updateSignature(): void
    {
        verify_csrf();
        $user = require_auth();

        if (empty($_FILES['signature']['tmp_name']) || !is_uploaded_file($_FILES['signature']['tmp_name'])) {
            $this->profile(null, 'กรุณาเลือกไฟล์ลายเซ็น .png');
            return;
        }

        if (($_FILES['signature']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->profile(null, 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่');
            return;
        }

        $imageInfo = getimagesize($_FILES['signature']['tmp_name']);

        if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== 'image/png') {
            $this->profile(null, 'รองรับเฉพาะไฟล์ PNG เท่านั้น');
            return;
        }

        $signatureDir = dirname(__DIR__, 2) . '/storage/signatures';

        if (!is_dir($signatureDir)) {
            mkdir($signatureDir, 0775, true);
        }

        $relativePath = 'storage/signatures/user_' . $user['id'] . '_signature.png';
        $targetPath = dirname(__DIR__, 2) . '/' . $relativePath;

        if (!move_uploaded_file($_FILES['signature']['tmp_name'], $targetPath)) {
            $this->profile(null, 'ไม่สามารถบันทึกไฟล์ลายเซ็นได้');
            return;
        }

        $statement = Database::connection()->prepare('UPDATE users SET signature_path = :path WHERE id = :id');
        $statement->execute([
            'path' => $relativePath,
            'id' => $user['id'],
        ]);

        $this->profile('บันทึกลายเซ็นเรียบร้อยแล้ว');
    }

    private function pendingRequisitions(array $user): array
    {
        if ($user['role'] === 'admin') {
            $statement = Database::connection()->query(
                'SELECT r.*, v.vehicle_name, v.license_plate
                 FROM requisitions r
                 LEFT JOIN vehicles v ON v.id = r.requested_vehicle_id
                 ORDER BY r.created_at DESC
                 LIMIT 50'
            );
            return $statement->fetchAll();
        }

        $level = self::ROLE_LEVELS[$user['role']] ?? 0;
        $status = 'pending_level_' . $level;
        $statement = Database::connection()->prepare(
            'SELECT r.*, v.vehicle_name, v.license_plate
             FROM requisitions r
             LEFT JOIN vehicles v ON v.id = r.requested_vehicle_id
             WHERE r.status = :status AND r.current_level = :level
             ORDER BY r.created_at ASC'
        );
        $statement->execute(['status' => $status, 'level' => $level]);

        return $statement->fetchAll();
    }

    private function canApprove(array $user, array $requisition): bool
    {
        if (!isset(self::ROLE_LEVELS[$user['role']])) {
            return false;
        }

        return (int) $requisition['current_level'] === self::ROLE_LEVELS[$user['role']]
            && $requisition['status'] === 'pending_level_' . $requisition['current_level'];
    }

    private function findRequisition(int $id): ?array
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

    private function vehicles(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, vehicle_name, license_plate, vehicle_type FROM vehicles WHERE is_active = 1 ORDER BY vehicle_name'
        );

        return $statement->fetchAll();
    }

    private function logs(int $requisitionId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT l.*, u.full_name, u.position_title
             FROM approval_logs l
             LEFT JOIN users u ON u.id = l.approver_id
             WHERE l.requisition_id = :id
             ORDER BY l.created_at ASC'
        );
        $statement->execute(['id' => $requisitionId]);

        return $statement->fetchAll();
    }

    private function vehicleStats(): array
    {
        $statement = Database::connection()->query(
            'SELECT v.vehicle_name, v.license_plate, COUNT(r.id) AS total
             FROM vehicles v
             LEFT JOIN requisitions r ON r.assigned_vehicle_id = v.id AND r.status <> "cancelled"
             GROUP BY v.id, v.vehicle_name, v.license_plate
             ORDER BY total DESC, v.vehicle_name ASC'
        );

        return $statement->fetchAll();
    }

    private function vehicleStatus(): array
    {
        $statement = Database::connection()->query(
            'SELECT
                v.id, v.vehicle_name, v.license_plate, v.vehicle_type,
                r.id            AS req_id,
                r.tracking_id,
                r.requester_name,
                r.destination,
                r.travel_start_at,
                r.travel_end_at,
                r.assigned_driver_name
             FROM vehicles v
             LEFT JOIN requisitions r
                ON r.assigned_vehicle_id = v.id
                AND r.status = \'approved\'
                AND r.travel_start_at <= NOW()
                AND r.travel_end_at   >= NOW()
             WHERE v.is_active = 1
             ORDER BY v.vehicle_name ASC'
        );
        return $statement->fetchAll();
    }

    private function fuelSummary(): array
    {
        $statement = Database::connection()->query(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month_key,
                    fuel_type,
                    COUNT(*) AS total_requests,
                    SUM(fuel_total_amount) AS total_amount
             FROM requisitions
             WHERE fuel_purchase_requested = 1
               AND status <> "cancelled"
             GROUP BY month_key, fuel_type
             ORDER BY month_key DESC, fuel_type ASC
             LIMIT 12'
        );

        return $statement->fetchAll();
    }

    private function writeLog(PDO $db, int $requisitionId, int $userId, int $level, string $action, string $from, string $to, string $comment): void
    {
        $log = $db->prepare(
            'INSERT INTO approval_logs (
                requisition_id, approver_id, approval_level, action, status_from, status_to, comment, ip_address, user_agent
            ) VALUES (
                :requisition_id, :approver_id, :approval_level, :action, :status_from, :status_to, :comment, :ip_address, :user_agent
            )'
        );
        $log->execute([
            'requisition_id' => $requisitionId,
            'approver_id' => $userId,
            'approval_level' => $level,
            'action' => $action,
            'status_from' => $from,
            'status_to' => $to,
            'comment' => $comment ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }

    private function showWithError(array $user, array $requisition, string $error): void
    {
        render('admin/requisition_show', [
            'title' => 'รายละเอียดคำขอ',
            'user' => $user,
            'requisition' => $requisition,
            'vehicles' => $this->vehicles(),
            'logs' => $this->logs((int) $requisition['id']),
            'canApprove' => $this->canApprove($user, $requisition),
            'statusLabels' => $this->statusLabels(),
            'error' => $error,
        ]);
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

    public static function fuelTypeLabel(?string $fuelType): string
    {
        return [
            'gasoline_95' => 'เบนซิน 95',
            'gasoline_91' => 'เบนซิน 91',
            'diesel' => 'ดีเซล',
            'engine_oil' => 'น้ำมันเครื่อง',
            'other' => 'น้ำมันเชื้อเพลิงประเภทอื่น',
        ][$fuelType ?? ''] ?? '-';
    }
}
