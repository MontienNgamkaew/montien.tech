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
$comment = isset($input['comment']) ? $input['comment'] : null;

if (!$personnel_id || !$job_id || !$role) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

try {
    $stmt_person = $pdo->prepare("SELECT primary_position AS main_title FROM users WHERE id = ?");
    $stmt_person->execute([$personnel_id]);
    $person = $stmt_person->fetch();

    if ($person && trim($person['main_title']) === 'เจ้าหน้าที่') {
        if (in_array($role, ['หัวหน้างาน', 'ผู้ช่วยหัวหน้างาน', 'หัวหน้าแผนกวิชา', 'ผู้อำนวยการวิทยาลัย', 'รองผู้อำนวยการฝ่าย'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "บุคลากรตำแหน่ง 'เจ้าหน้าที่' ไม่สามารถรับหน้าที่ระดับหัวหน้าหรือผู้ช่วยได้"]);
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO assignments (personnel_id, job_id, role, academic_year, comment) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role), comment = VALUES(comment)");
    $stmt->execute([$personnel_id, $job_id, $role, $academic_year, $comment]);

    echo json_encode(["status" => "success", "message" => "Assignment updated successfully"]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
