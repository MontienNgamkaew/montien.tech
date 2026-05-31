<?php
require_once __DIR__ . '/api/db.php';

try {
    
    // 1. Change role to VARCHAR
    $pdo->exec("ALTER TABLE assignments MODIFY role VARCHAR(50) NOT NULL");
    echo "Modified role column.\n";

    // 2. Add academic_year (ignore if exists)
    try {
        $pdo->exec("ALTER TABLE assignments ADD COLUMN academic_year INT NOT NULL DEFAULT 2569");
        echo "Added academic_year column.\n";
    } catch(PDOException $e) {
        // column likely exists
    }

    // 3. Re-create unique constraint
    try {
        $pdo->exec("ALTER TABLE assignments ADD INDEX idx_personnel (personnel_id)");
    } catch(PDOException $e) {}

    try {
        $pdo->exec("ALTER TABLE assignments DROP INDEX unique_assignment");
    } catch(PDOException $e) { 
        echo "Drop index failed: " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE assignments ADD UNIQUE KEY unique_assignment_v2 (personnel_id, job_id, role, academic_year)");
        echo "Updated unique constraint.\n";
    } catch(PDOException $e) {
        echo "Add index failed: " . $e->getMessage() . "\n";
    }

    // 4. Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(50) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      auth_token VARCHAR(100) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created users table.\n";

    // 5. Insert default admin
    $hash = password_hash('1234', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password_hash) VALUES ('admin', ?)");
    $stmt->execute([$hash]);
    echo "Inserted default admin.\n";

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Connection or Execution failed: " . $e->getMessage() . "\n";
}
?>
