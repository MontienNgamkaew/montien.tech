<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $year = isset($_GET['year']) ? intval($_GET['year']) : 2569;

    // Get all personnel from unified users table
    $stmt = $pdo->query("SELECT id, CONCAT(title, first_name, ' ', last_name) AS name, primary_position AS main_title, avatar_path AS photo_path FROM users WHERE status = 'active'");
    $personnel = $stmt->fetchAll();

    // Get all departments
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY sort_order, id");
    $departments = $stmt->fetchAll();

    // Get all jobs
    $stmt = $pdo->query("SELECT * FROM jobs ORDER BY sort_order, id");
    $jobs = $stmt->fetchAll();

    // Get all assignments
    $stmt = $pdo->prepare("SELECT * FROM assignments WHERE academic_year = ? ORDER BY sort_order, id");
    $stmt->execute([$year]);
    $assignments = $stmt->fetchAll();

    // Get college settings
    $stmt = $pdo->query("SELECT * FROM college_settings WHERE id = 1");
    $settings = $stmt->fetch();
    if (!$settings) {
        $settings = [
            "college_name" => "วิทยาลัยการอาชีพพนมไพร",
            "logo_path" => "",
            "theme_preset" => "rose"
        ];
    }

    // Return the response
    echo json_encode([
        "status" => "success",
        "data" => [
            "personnel" => $personnel,
            "departments" => $departments,
            "jobs" => $jobs,
            "assignments" => $assignments,
            "settings" => $settings
        ]
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
