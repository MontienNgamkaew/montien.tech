<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // Generate simple token
    $token = bin2hex(random_bytes(32));
    
    $updateStmt = $pdo->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
    $updateStmt->execute([$token, $user['id']]);
    
    echo json_encode([
        'status' => 'success',
        'token' => $token
    ]);
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านไม่ถูกต้อง']);
}
?>
