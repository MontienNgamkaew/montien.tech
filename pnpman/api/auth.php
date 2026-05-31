<?php
require_once 'db.php';

function checkAuth($pdo) {
    if (!isCurrentAdmin($pdo)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden: You do not have administrator rights for PNP Man']);
        exit;
    }
    return true;
}
?>
