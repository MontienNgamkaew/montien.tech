<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');
checkAuth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "กรุณาระบุ action ที่ต้องการทำ"]);
    exit;
}

try {
    switch ($action) {
        // === DEPARTMENT ACTIONS ===
        case 'add_dept':
            $name = trim($input['name'] ?? '');
            if (empty($name)) {
                throw new Exception("กรุณากรอกชื่อฝ่ายงาน");
            }
            
            // Get max sort order
            $stmt = $pdo->query("SELECT MAX(sort_order) FROM departments");
            $maxOrder = intval($stmt->fetchColumn());
            
            $stmt = $pdo->prepare("INSERT INTO departments (name, sort_order) VALUES (?, ?)");
            $stmt->execute([$name, $maxOrder + 1]);
            
            echo json_encode(["status" => "success", "message" => "เพิ่มฝ่ายงาน '$name' เรียบร้อยแล้ว"]);
            break;

        case 'edit_dept':
            $id = intval($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            if ($id <= 0 || empty($name)) {
                throw new Exception("ข้อมูลไม่ครบถ้วน");
            }
            
            $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            
            echo json_encode(["status" => "success", "message" => "แก้ไขชื่อฝ่ายงานเรียบร้อยแล้ว"]);
            break;

        case 'delete_dept':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ไอดีไม่ถูกต้อง");
            }
            
            // Safety check: Don't allow deleting if it's the last department
            $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
            $count = intval($stmt->fetchColumn());
            if ($count <= 1) {
                throw new Exception("ไม่สามารถลบฝ่ายงานสุดท้ายได้ ระบบต้องมีอย่างน้อย 1 ฝ่ายงาน");
            }
            
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(["status" => "success", "message" => "ลบฝ่ายงานเรียบร้อยแล้ว"]);
            break;

        case 'reorder_depts':
            $ids = $input['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception("รูปแบบข้อมูลไม่ถูกต้อง");
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE departments SET sort_order = ? WHERE id = ?");
            foreach ($ids as $index => $id) {
                $stmt->execute([$index + 1, intval($id)]);
            }
            $pdo->commit();
            
            echo json_encode(["status" => "success", "message" => "จัดลำดับฝ่ายงานเรียบร้อยแล้ว"]);
            break;

        // === JOB / POSITION ACTIONS ===
        case 'add_job':
            $dept_id = intval($input['department_id'] ?? 0);
            $name = trim($input['name'] ?? '');
            if ($dept_id <= 0 || empty($name)) {
                throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
            }
            
            // Get max sort order for jobs in this department
            $stmt = $pdo->prepare("SELECT MAX(sort_order) FROM jobs WHERE department_id = ?");
            $stmt->execute([$dept_id]);
            $maxOrder = intval($stmt->fetchColumn());
            
            $stmt = $pdo->prepare("INSERT INTO jobs (department_id, name, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$dept_id, $name, $maxOrder + 1]);
            
            echo json_encode(["status" => "success", "message" => "เพิ่มตำแหน่งงาน '$name' เรียบร้อยแล้ว"]);
            break;

        case 'edit_job':
            $id = intval($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $dept_id = intval($input['department_id'] ?? 0);
            
            if ($id <= 0 || empty($name) || $dept_id <= 0) {
                throw new Exception("ข้อมูลไม่ครบถ้วน");
            }
            
            $stmt = $pdo->prepare("UPDATE jobs SET name = ?, department_id = ? WHERE id = ?");
            $stmt->execute([$name, $dept_id, $id]);
            
            echo json_encode(["status" => "success", "message" => "แก้ไขตำแหน่งงานเรียบร้อยแล้ว"]);
            break;

        case 'delete_job':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ไอดีไม่ถูกต้อง");
            }
            
            // We shouldn't allow deleting special executive positions (like Director, Deputy Directors)
            // let's check by id
            if ($id >= 900 && $id <= 904) {
                throw new Exception("ไม่สามารถลบตำแหน่งบริหารหลักของระบบ (ผู้อำนวยการ/รองผู้อำนวยการ) ได้");
            }
            
            $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(["status" => "success", "message" => "ลบตำแหน่งงานเรียบร้อยแล้ว"]);
            break;

        case 'reorder_jobs':
            $ids = $input['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new Exception("รูปแบบข้อมูลไม่ถูกต้อง");
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE jobs SET sort_order = ? WHERE id = ?");
            foreach ($ids as $index => $id) {
                $stmt->execute([$index + 1, intval($id)]);
            }
            $pdo->commit();
            
            echo json_encode(["status" => "success", "message" => "จัดลำดับตำแหน่งงานเรียบร้อยแล้ว"]);
            break;

        default:
            throw new Exception("ไม่พบ action ที่ต้องการ");
    }
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
