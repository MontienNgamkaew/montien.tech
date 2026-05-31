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
$academic_year = $input['academic_year'] ?? 2569;

if (!$personnel_id || !$job_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

try {
    // If role is passed, only remove that role, otherwise remove all roles for this person in this job
    if (isset($input['role'])) {
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE personnel_id = ? AND job_id = ? AND role = ? AND academic_year = ?");
        $stmt->execute([$personnel_id, $job_id, $input['role'], $academic_year]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE personnel_id = ? AND job_id = ? AND academic_year = ?");
        $stmt->execute([$personnel_id, $job_id, $academic_year]);
    }

    echo json_encode(["status" => "success", "message" => "Assignment removed successfully"]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
