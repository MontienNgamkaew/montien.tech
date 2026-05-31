<?php
require_once __DIR__ . '/api/db.php';

try {
    echo "Connected to database successfully.\n";

    // 1. Add sort_order column to assignments table (ignore if already exists)
    try {
        $pdo->exec("ALTER TABLE assignments ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
        echo "Added 'sort_order' column to 'assignments' table successfully.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "'sort_order' column already exists in 'assignments' table.\n";
        } else {
            throw $e;
        }
    }

    echo "Migration V3 completed successfully.\n";

} catch (PDOException $e) {
    echo "Connection or Execution failed: " . $e->getMessage() . "\n";
}
?>
