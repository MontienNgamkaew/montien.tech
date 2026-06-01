<?php

final class VendorController
{
    public function index(): void
    {
        $this->requireAdmin();
        $vendors = Database::connection()
            ->query('SELECT * FROM fuel_vendors ORDER BY is_default DESC, id ASC')
            ->fetchAll();
        $title = 'จัดการร้านน้ำมัน';
        $content = $this->renderView('admin/vendors/index', compact('vendors'));
        require __DIR__ . '/../Views/layout.php';
    }

    public function store(): void
    {
        $this->requireAdmin();
        $name    = trim($_POST['name']    ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone   = trim($_POST['phone']   ?? '');

        if ($name === '') {
            $_SESSION['flash_error'] = 'กรุณากรอกชื่อร้าน';
            $this->redirect('/vendors');
        }

        $db = Database::connection();
        $db->prepare('INSERT INTO fuel_vendors (name, address, phone) VALUES (:name, :address, :phone)')
           ->execute(['name' => $name, 'address' => $address, 'phone' => $phone]);

        $_SESSION['flash_success'] = 'เพิ่มร้านน้ำมันเรียบร้อย';
        $this->redirect('/vendors');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $id      = (int) ($_POST['id']      ?? 0);
        $name    = trim($_POST['name']      ?? '');
        $address = trim($_POST['address']   ?? '');
        $phone   = trim($_POST['phone']     ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($id === 0 || $name === '') {
            $_SESSION['flash_error'] = 'ข้อมูลไม่ครบถ้วน';
            $this->redirect('/vendors');
        }

        $db = Database::connection();
        if ($isDefault) {
            $db->exec('UPDATE fuel_vendors SET is_default = 0');
        }
        $db->prepare('UPDATE fuel_vendors SET name=:name, address=:address, phone=:phone, is_default=:def WHERE id=:id')
           ->execute(['name' => $name, 'address' => $address, 'phone' => $phone, 'def' => $isDefault, 'id' => $id]);

        $_SESSION['flash_success'] = 'แก้ไขข้อมูลร้านเรียบร้อย';
        $this->redirect('/vendors');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            Database::connection()->prepare('DELETE FROM fuel_vendors WHERE id = :id')->execute(['id' => $id]);
            $_SESSION['flash_success'] = 'ลบร้านน้ำมันเรียบร้อย';
        }
        $this->redirect('/vendors');
    }

    private function requireAdmin(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: ' . config('app')['base_path'] . '/login');
            exit;
        }
    }

    private function redirect(string $path): never
    {
        header('Location: ' . config('app')['base_path'] . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        require __DIR__ . '/../Views/' . $view . '.php';
        return ob_get_clean();
    }
}
