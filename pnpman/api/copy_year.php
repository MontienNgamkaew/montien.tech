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
$from_year = $input['from_year'] ?? null;
$to_year = $input['to_year'] ?? null;

if (!$from_year || !$to_year || $from_year == $to_year) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "กรุณาระบุปีต้นทางและปีปลายทางที่แตกต่างกัน"]);
    exit;
}

try {
    // Check if source year has any assignments
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM assignments WHERE academic_year = ?");
    $checkStmt->execute([$from_year]);
    $sourceCount = $checkStmt->fetch()['cnt'];

    if ($sourceCount == 0) {
        echo json_encode(["status" => "error", "message" => "ไม่พบข้อมูลการจัดวางในปีการศึกษา {$from_year}"]);
        exit;
    }

    // Check if target year already has assignments
    $checkStmt->execute([$to_year]);
    $targetCount = $checkStmt->fetch()['cnt'];

    if ($targetCount > 0) {
        // Delete existing target assignments first
        $delStmt = $pdo->prepare("DELETE FROM assignments WHERE academic_year = ?");
        $delStmt->execute([$to_year]);
    }

    // Copy assignments from source to target year
    $stmt = $pdo->prepare("
        INSERT INTO assignments (personnel_id, job_id, role, academic_year, comment)
        SELECT personnel_id, job_id, role, ?, comment 
        FROM assignments 
        WHERE academic_year = ?
    ");
    $stmt->execute([$to_year, $from_year]);
    $copied = $stmt->rowCount();

    $msg = "คัดลอกสำเร็จ {$copied} ตำแหน่ง จากปี {$from_year} → {$to_year}";
    if ($targetCount > 0) {
        $msg .= " (แทนที่ข้อมูลเดิม {$targetCount} รายการ)";
    }

    echo json_encode(["status" => "success", "message" => $msg, "copied" => $copied]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
