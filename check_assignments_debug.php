<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/database.php';

echo "=== DATABASE CONFIG ===\n";
echo "Hostinger? " . ($isHostinger ? "YES" : "NO") . "\n";
echo "DB Host: " . $dbHost . "\n";
echo "DB Name: " . $dbName . "\n";

try {
    echo "\n=== TABLES STATUS ===\n";
    foreach (['users', 'departments', 'jobs', 'assignments', 'user_org_assignments'] as $tbl) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
            echo "Table '$tbl': $count rows\n";
        } catch (Exception $e) {
            echo "Table '$tbl' ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== ALL ASSIGNMENTS IN PNP MAN ===\n";
    try {
        $stmt = $db->query("
            SELECT a.id, a.personnel_id, u.username, u.first_name, u.last_name, a.job_id, j.name AS job_name, a.role, a.academic_year
            FROM assignments a
            JOIN users u ON a.personnel_id = u.id
            JOIN jobs j ON a.job_id = j.id
            LIMIT 50
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo "No assignments found in 'assignments' table.\n";
        } else {
            foreach ($rows as $row) {
                echo "ID: {$row['id']} | User: {$row['first_name']} {$row['last_name']} ({$row['username']}, ID: {$row['personnel_id']}) | Job: {$row['job_name']} | Role: {$row['role']} | Year: {$row['academic_year']}\n";
            }
        }
    } catch (Exception $e) {
        echo "Query assignments ERROR: " . $e->getMessage() . "\n";
    }

    echo "\n=== RECENT USERS WITH PNP MAN ASSIGNMENTS ===\n";
    try {
        $stmt = $db->query("
            SELECT DISTINCT u.id, u.username, u.first_name, u.last_name
            FROM users u
            JOIN assignments a ON u.id = a.personnel_id
            LIMIT 10
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            echo "User ID: {$row['id']} | Username: {$row['username']} | Name: {$row['first_name']} {$row['last_name']}\n";
        }
    } catch (Exception $e) {
        echo "Query users with assignments ERROR: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "General ERROR: " . $e->getMessage() . "\n";
}
