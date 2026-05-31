<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM college_settings WHERE id = 1");
    $settings = $stmt->fetch();

    if (!$settings) {
        // Fallback if somehow not found
        $settings = [
            "college_name" => "วิทยาลัยการอาชีพพนมไพร",
            "logo_path" => "",
            "theme_preset" => "rose"
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => $settings
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
