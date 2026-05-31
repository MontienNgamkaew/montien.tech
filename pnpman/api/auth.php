<?php
require_once __DIR__ . '/../../api/jwt.php';
require_once __DIR__ . '/../../api/config.php';

function checkAuth($pdo) {
    // 1. Get Authorization Header
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';
    
    // Check $_SERVER if headers is empty (for some server configurations)
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized (No Token)']);
        exit;
    }

    $token = $matches[1];
    
    // 2. Decode JWT Token using Portal's secret key
    $payload = JWT::decode($token, JWT_SECRET_KEY);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized (Invalid or Expired Token)']);
        exit;
    }
    
    // 3. Verify user exists and is active in database
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$payload['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized (User not found or suspended)']);
        exit;
    }

    return true;
}
?>
