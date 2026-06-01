<?php

final class VehicleController
{
    private const FUEL_OPTIONS = [
        'diesel'      => 'ดีเซล',
        'gasoline_91' => 'แก็สโซฮอล์ 91',
        'gasoline_95' => 'แก็สโซฮอล์ 95',
        'engine_oil'  => 'น้ำมันเครื่อง',
        'other'       => 'อื่นๆ',
    ];

    public function index(): void
    {
        $this->requireAdmin();
        $vehicles = Database::connection()
            ->query('SELECT * FROM vehicles ORDER BY vehicle_name ASC')
            ->fetchAll();

        render('admin/vehicles/index', [
            'title'       => 'จัดการรถยนต์',
            'user'        => require_auth(),
            'vehicles'    => $vehicles,
            'fuelOptions' => self::FUEL_OPTIONS,
            'flash'       => $this->flash(),
        ]);
    }

    public function store(): void
    {
        verify_csrf();
        $this->requireAdmin();

        $name         = trim($_POST['vehicle_name']        ?? '');
        $plate        = trim($_POST['license_plate']       ?? '');
        $type         = trim($_POST['vehicle_type']        ?? '');
        $fuelType     = $_POST['fuel_type']                ?? 'diesel';
        $driverName   = trim($_POST['default_driver_name'] ?? '');
        $notes        = trim($_POST['notes']               ?? '');

        if ($name === '' || $plate === '') {
            $_SESSION['flash_error'] = 'กรุณากรอกชื่อรถและทะเบียน';
            $this->redirect('/manage/vehicles');
        }

        try {
            Database::connection()->prepare(
                'INSERT INTO vehicles (vehicle_name, license_plate, vehicle_type, fuel_type, default_driver_name, notes)
                 VALUES (:name, :plate, :type, :fuel, :driver, :notes)'
            )->execute([
                'name'   => $name,
                'plate'  => $plate,
                'type'   => $type ?: null,
                'fuel'   => $fuelType,
                'driver' => $driverName ?: null,
                'notes'  => $notes ?: null,
            ]);
            $_SESSION['flash_success'] = 'เพิ่มรถยนต์ "' . $name . '" เรียบร้อย';
        } catch (\PDOException $e) {
            $_SESSION['flash_error'] = 'ทะเบียนรถซ้ำในระบบ กรุณาตรวจสอบใหม่';
        }

        $this->redirect('/manage/vehicles');
    }

    public function update(): void
    {
        verify_csrf();
        $this->requireAdmin();

        $id           = (int) ($_POST['id']                ?? 0);
        $name         = trim($_POST['vehicle_name']        ?? '');
        $plate        = trim($_POST['license_plate']       ?? '');
        $type         = trim($_POST['vehicle_type']        ?? '');
        $fuelType     = $_POST['fuel_type']                ?? 'diesel';
        $driverName   = trim($_POST['default_driver_name'] ?? '');
        $notes        = trim($_POST['notes']               ?? '');
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        if ($id === 0 || $name === '' || $plate === '') {
            $_SESSION['flash_error'] = 'ข้อมูลไม่ครบถ้วน';
            $this->redirect('/manage/vehicles');
        }

        try {
            Database::connection()->prepare(
                'UPDATE vehicles
                 SET vehicle_name=:name, license_plate=:plate, vehicle_type=:type,
                     fuel_type=:fuel, default_driver_name=:driver, notes=:notes, is_active=:active
                 WHERE id=:id'
            )->execute([
                'name'   => $name,
                'plate'  => $plate,
                'type'   => $type ?: null,
                'fuel'   => $fuelType,
                'driver' => $driverName ?: null,
                'notes'  => $notes ?: null,
                'active' => $isActive,
                'id'     => $id,
            ]);
            $_SESSION['flash_success'] = 'แก้ไขข้อมูลรถเรียบร้อย';
        } catch (\PDOException $e) {
            $_SESSION['flash_error'] = 'ทะเบียนรถซ้ำในระบบ กรุณาตรวจสอบใหม่';
        }

        $this->redirect('/manage/vehicles');
    }

    public function delete(): void
    {
        verify_csrf();
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            // ตรวจว่ามีคำขอที่ยังใช้รถนี้อยู่ไหม
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM requisitions
                 WHERE (assigned_vehicle_id = :id OR requested_vehicle_id = :id2)
                   AND status NOT IN (\'cancelled\', \'rejected\')'
            );
            $stmt->execute(['id' => $id, 'id2' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = 'ไม่สามารถลบได้ — รถคันนี้มีคำขอที่ยังใช้งานอยู่';
                $this->redirect('/manage/vehicles');
            }

            Database::connection()
                ->prepare('DELETE FROM vehicles WHERE id = :id')
                ->execute(['id' => $id]);
            $_SESSION['flash_success'] = 'ลบรถยนต์เรียบร้อย';
        }

        $this->redirect('/manage/vehicles');
    }

    private function requireAdmin(): void
    {
        $user = require_auth();
        if ($user['role'] !== 'admin' && $user['role'] !== 'supply_head') {
            header('Location: ' . config('app')['base_path'] . '/dashboard');
            exit;
        }
    }

    private function flash(): array
    {
        $data = [
            'success' => $_SESSION['flash_success'] ?? null,
            'error'   => $_SESSION['flash_error']   ?? null,
        ];
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
        return $data;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . config('app')['base_path'] . $path);
        exit;
    }
}
