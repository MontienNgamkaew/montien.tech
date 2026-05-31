<?php
require_once __DIR__ . '/api/db.php';

try {
    echo "Connected to database successfully.\n";

    // 1. Create college_settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS college_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        college_name VARCHAR(255) NOT NULL DEFAULT 'วิทยาลัยการอาชีพพนมไพร',
        logo_path VARCHAR(255) NULL,
        theme_preset VARCHAR(50) NOT NULL DEFAULT 'rose'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created 'college_settings' table successfully.\n";

    // 2. Insert default row if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM college_settings WHERE id = 1");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO college_settings (id, college_name, logo_path, theme_preset) 
                    VALUES (1, 'วิทยาลัยการอาชีพพนมไพร', '', 'rose')");
        echo "Inserted default college settings.\n";
    } else {
        echo "Default settings already exist.\n";
    }

    // 3. Create uploads directory if not exists
    $uploadDir = __DIR__ . '/uploads';
    if (!file_exists($uploadDir)) {
        if (mkdir($uploadDir, 0777, true)) {
            echo "Created uploads directory successfully.\n";
        } else {
            echo "Failed to create uploads directory.\n";
        }
    } else {
        echo "Uploads directory already exists.\n";
    }

    echo "Migration V2 completed successfully.\n";

} catch (PDOException $e) {
    echo "Connection or Execution failed: " . $e->getMessage() . "\n";
}
?>
