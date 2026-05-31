<?php
require_once __DIR__ . '/api/db.php';

try {
    echo "Connected to database successfully.\n";

    // 1. Add photo_path column to personnel table (ignore if already exists)
    try {
        $pdo->exec("ALTER TABLE personnel ADD COLUMN photo_path VARCHAR(255) NULL DEFAULT NULL");
        echo "Added 'photo_path' column to 'personnel' table successfully.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "'photo_path' column already exists in 'personnel' table.\n";
        } else {
            throw $e;
        }
    }

    echo "Migration V4 completed successfully.\n";

} catch (PDOException $e) {
    echo "Connection or Execution failed: " . $e->getMessage() . "\n";
}
?>
