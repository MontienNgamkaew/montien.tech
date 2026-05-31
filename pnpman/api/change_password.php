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
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "กรุณากรอกรหัสผ่านให้ครบ"]);
    exit;
}

if (mb_strlen($new_password) < 4) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "รหัสผ่านใหม่ต้องมีอย่างน้อย 4 ตัวอักษร"]);
    exit;
}

try {
    // Get current user from token
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE auth_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }

    // Verify current password
    if (!password_verify($current_password, $user['password_hash'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "รหัสผ่านปัจจุบันไม่ถูกต้อง"]);
        exit;
    }

    // Update password
    $newHash = password_hash($new_password, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $updateStmt->execute([$newHash, $user['id']]);

    echo json_encode(["status" => "success", "message" => "เปลี่ยนรหัสผ่านสำเร็จ"]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
