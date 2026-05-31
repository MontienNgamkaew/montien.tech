<?php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    echo "Starting unified database migration...\n";

    // 1. Make department_id in jobs table nullable (so we can insert Director/Deputies with NULL department_id)
    try {
        $pdo->exec("ALTER TABLE jobs MODIFY department_id INT NULL");
        echo "1. Modified 'jobs.department_id' to be NULLABLE successfully.\n";
    } catch (PDOException $e) {
        echo "1. Note: Modifying 'jobs.department_id' failed: " . $e->getMessage() . "\n";
    }

    // 2. Create assignments table linked to users(id)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            personnel_id INT NOT NULL,
            job_id INT NOT NULL,
            role VARCHAR(100) NOT NULL,
            academic_year INT NOT NULL DEFAULT 2569,
            sort_order INT DEFAULT 0,
            comment VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (personnel_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
            UNIQUE KEY unique_assignment_v2 (personnel_id, job_id, role, academic_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "2. Created/verified 'assignments' table successfully.\n";
    } catch (PDOException $e) {
        echo "2. Error creating 'assignments' table: " . $e->getMessage() . "\n";
    }

    // 3. Create college_settings table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS college_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            college_name VARCHAR(255) NOT NULL DEFAULT 'วิทยาลัยการอาชีพพนมไพร',
            logo_path VARCHAR(255) NULL,
            theme_preset VARCHAR(50) NOT NULL DEFAULT 'rose'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM college_settings WHERE id = 1");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO college_settings (id, college_name, logo_path, theme_preset) VALUES (1, 'วิทยาลัยการอาชีพพนมไพร', '', 'rose')");
        }
        echo "3. Created/verified 'college_settings' table successfully.\n";
    } catch (PDOException $e) {
        echo "3. Error creating 'college_settings' table: " . $e->getMessage() . "\n";
    }

    // 4. Insert Director and Deputy Director positions (IDs 900-904)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO jobs (id, department_id, name, sort_order) VALUES
            (900, NULL, 'ผู้อำนวยการวิทยาลัย', 0),
            (901, 1, 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร', 0),
            (902, 2, 'รองผู้อำนวยการฝ่ายยุทธศาสตร์และแผนงาน', 0),
            (903, 3, 'รองผู้อำนวยการฝ่ายพัฒนากิจการนักเรียน นักศึกษา', 0),
            (904, 4, 'รองผู้อำนวยการฝ่ายวิชาการ', 0)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                department_id = VALUES(department_id)
        ");
        $stmt->execute();
        echo "4. Director and Deputy Director positions inserted/updated successfully (IDs 900-904).\n";
    } catch (PDOException $e) {
        echo "4. Error inserting/updating Director/Deputy positions: " . $e->getMessage() . "\n";
    }

    echo "Unified database migration completed successfully!\n";

} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
