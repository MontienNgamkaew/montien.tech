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

$job_id = $input['job_id'] ?? null;
$role = $input['role'] ?? null;
$academic_year = $input['academic_year'] ?? null;
$assignment_ids = $input['assignment_ids'] ?? [];

if (!$job_id || !$role || !$academic_year || !is_array($assignment_ids)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields or invalid data format"]);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE assignments SET sort_order = ? WHERE id = ? AND job_id = ? AND role = ? AND academic_year = ?");
    
    foreach ($assignment_ids as $index => $id) {
        $stmt->execute([$index + 1, intval($id), $job_id, $role, $academic_year]);
    }
    
    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Reordered assignments successfully"]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database failure: " . $e->getMessage()]);
}
?>
