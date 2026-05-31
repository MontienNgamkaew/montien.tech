<?php
// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Dynamic Environment Selection (XAMPP Local vs Hostinger Production)
$isHostinger = isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'montien.tech') !== false || $_SERVER['HTTP_HOST'] === 'pnp-portal.montien.tech');

if ($isHostinger) {
    $host = 'localhost';
    $db   = 'u651170081_pnp_portal';
    $user = 'u651170081_pnp_portal';
    $pass = 'a1d9GH10%';
} else {
    $host = 'localhost';
    $db   = 'pnp_portal';
    $user = 'root';
    $pass = '';
}
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

function parseFullName($fullName) {
    $fullName = trim($fullName);
    $titles = ['นาย', 'นางสาว', 'นาง', 'ดร.', 'ศาสตราจารย์', 'รองศาสตราจารย์', 'ผู้ช่วยศาสตราจารย์', 'ศ.', 'รศ.', 'ผศ.', 'ว่าที่ร้อยตรี', 'ว่าที่ ร.ต.', 'จ่าสิบเอก'];
    $title = '';
    $rest = $fullName;
    
    foreach ($titles as $t) {
        if (strpos($fullName, $t) === 0) {
            $title = $t;
            $rest = trim(substr($fullName, strlen($t)));
            break;
        }
    }
    
    $parts = preg_split('/\s+/', $rest);
    $first_name = $parts[0] ?? '';
    $last_name = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
    
    return [
        'title' => $title,
        'first_name' => $first_name,
        'last_name' => $last_name
    ];
}

function isCurrentAdmin($pdo) {
    require_once __DIR__ . '/../../api/jwt.php';
    require_once __DIR__ . '/../../api/config.php';

    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (empty($authHeader) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return false;
    }

    $token = $matches[1];
    $payload = JWT::decode($token, JWT_SECRET_KEY);
    if (!$payload) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT u.id 
        FROM users u 
        LEFT JOIN app_roles r ON u.id = r.user_id AND r.app_id = 'pnp-man'
        WHERE u.id = ? 
          AND u.status = 'active'
          AND (u.is_portal_admin = 1 OR r.role = 'admin')
    ");
    $stmt->execute([$payload['user_id']]);
    return (bool)$stmt->fetch();
}
?>
