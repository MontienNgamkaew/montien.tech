<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Allow session reading
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    $token = null;
    $authHeader = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } else {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        }
    }

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (isset($_GET['token'])) {
        $token = $_GET['token'];
    }

    if ($token) {
        try {
            require_once __DIR__ . '/../api/jwt.php';
            $payload = JWT::decode($token, JWT_SECRET_KEY);
            if ($payload && isset($payload['username'])) {
                $username = $payload['username'];
                $db = Database::connection();
                $stmt = $db->prepare('SELECT id FROM users WHERE username = :username AND is_active = 1 LIMIT 1');
                $stmt->execute(['username' => $username]);
                $localUser = $stmt->fetch();
                if ($localUser) {
                    $userId = (int)$localUser['id'];
                }
            }
        } catch (Throwable $jwtEx) {
            // Fail silently, fallback to $userId = null
        }
    }
}

if (!$userId) {
    echo json_encode(['pending_count' => 0]);
    exit;
}

try {
    $db = Database::connection();
    
    // Get the user's role in PNP Go
    $stmt = $db->prepare('SELECT role FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] === 'user') {
        echo json_encode(['pending_count' => 0]);
        exit;
    }
    
    $role = $user['role'];
    
    // Map role to approval levels
    $level = null;
    $status = null;
    
    if ($role === 'supply_head') {
        $level = 1;
        $status = 'pending_level_1';
    } elseif ($role === 'deputy_director') {
        $level = 2;
        $status = 'pending_level_2';
    } elseif ($role === 'director') {
        $level = 3;
        $status = 'pending_level_3';
    }
    
    if ($role === 'admin') {
        // Admins see all pending levels
        $countStmt = $db->query("SELECT COUNT(*) FROM requisitions WHERE status IN ('pending_level_1', 'pending_level_2', 'pending_level_3')");
        $count = (int)$countStmt->fetchColumn();
    } elseif ($level !== null && $status !== null) {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM requisitions WHERE status = :status AND current_level = :level");
        $countStmt->execute(['status' => $status, 'level' => $level]);
        $count = (int)$countStmt->fetchColumn();
    } else {
        $count = 0;
    }
    
    echo json_encode(['pending_count' => $count]);
    
} catch (Throwable $e) {
    // Fail silently to avoid breaking portal frontend
    echo json_encode(['pending_count' => 0, 'error' => $e->getMessage()]);
}
