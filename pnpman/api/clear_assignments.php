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
$academic_year = $input['academic_year'] ?? 2569;

try {
    $stmt = $pdo->prepare("DELETE FROM assignments WHERE academic_year = ?");
    $stmt->execute([$academic_year]);
    $count = $stmt->rowCount();
    echo json_encode(["status" => "success", "message" => "ลบข้อมูลทั้งหมด $count รายการ สำเร็จ"]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
