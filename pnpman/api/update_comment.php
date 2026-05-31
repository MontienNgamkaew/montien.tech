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

$personnel_id = $input['personnel_id'] ?? null;
$job_id = $input['job_id'] ?? null;
$role = $input['role'] ?? null;
$academic_year = $input['academic_year'] ?? 2569;
$comment = $input['comment'] ?? null;

if (!$personnel_id || !$job_id || !$role) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields (personnel_id, job_id, role)"]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE assignments SET comment = ? WHERE personnel_id = ? AND job_id = ? AND role = ? AND academic_year = ?");
    $stmt->execute([$comment, $personnel_id, $job_id, $role, $academic_year]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["status" => "success", "message" => "อัปเดตหมายเหตุเรียบร้อยแล้ว"]);
    } else {
        echo json_encode(["status" => "success", "message" => "ไม่พบรายการที่ต้องอัปเดต หรือค่าเดิมเหมือนกัน"]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
