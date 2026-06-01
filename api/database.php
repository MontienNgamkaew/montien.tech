<?php
/* -------------------------------------------------------------
 * MYSQL DATABASE CONNECTION & AUTO-MIGRATION
 * Supports primary_position, org_position, and department columns
 * Plus normalized departments, jobs, and user_org_assignments
 * ------------------------------------------------------------- */

date_default_timezone_set('Asia/Bangkok');

// 1. Dynamic Environment Selection (XAMPP Local vs Hostinger Production)
$isHostinger = isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'montien.tech') !== false || $_SERVER['HTTP_HOST'] === 'pnp-portal.montien.tech');

if ($isHostinger) {
    $dbHost = 'localhost';
    $dbName = 'u651170081_pnp_portal';
    $dbUser = 'u651170081_pnp_portal';
    $dbPass = 'a1d9GH10%'; // รหัสผ่านสอดคล้องกับโปรเจกต์อื่นๆ ใน Hostinger
} else {
    $dbHost = 'localhost';
    $dbName = 'pnp_portal';
    $dbUser = 'root';
    $dbPass = '';
}

try {
    // 2. เชื่อมต่อเซิร์ฟเวอร์ MySQL ขั้นต้น (โดยยังไม่ระบุชื่อฐานข้อมูล เพื่อทำการสร้างแบบ Auto-Creation)
    $dsnInit = "mysql:host=$dbHost;charset=utf8mb4";
    $pdoInit = new PDO($dsnInit, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // สร้างฐานข้อมูลหากยังไม่มี
    $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. เชื่อมต่อฐานข้อมูลจริง
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    // 4. สร้างตารางหลัก: users (รักษาความเข้ากันได้ย้อนหลัง 100% สำหรับหน้ากาก Portal UI)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        title VARCHAR(50) DEFAULT '',
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        birthdate VARCHAR(50) DEFAULT '',
        nickname VARCHAR(50) DEFAULT '',
        gender VARCHAR(20) DEFAULT '',
        phone VARCHAR(20) NULL,
        education VARCHAR(255) DEFAULT '',
        avatar_path VARCHAR(255) NULL,
        primary_position ENUM(
            'ผู้ดูแลระบบ',
            'ผู้อำนวยการ',
            'รองผู้อำนวยการ',
            'ข้าราชการครู',
            'พนักงานราชการครู',
            'ครูพิเศษสอน',
            'เจ้าหน้าที่',
            'นักการภารโรง',
            'แม่บ้าน',
            'พนักงานขับรถ'
        ) NOT NULL,
        org_position VARCHAR(100) DEFAULT '',
        department VARCHAR(100) DEFAULT '',
        job VARCHAR(100) DEFAULT '',
        status ENUM('active', 'suspended') DEFAULT 'active',
        is_portal_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 4.1 Robust Auto-migration: ตรวจสอบและเพิ่มคอลัมน์ที่จำเป็นสำหรับโครงสร้างบุคลากรใหม่ (หากยังไม่มีในตาราง users)
    $stmtCols = $db->query("SHOW COLUMNS FROM users");
    $existingCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('title', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN title VARCHAR(50) DEFAULT '' AFTER password_hash");
    }
    if (!in_array('birthdate', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN birthdate VARCHAR(50) DEFAULT '' AFTER email");
    }
    if (!in_array('nickname', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(50) DEFAULT '' AFTER birthdate");
    }
    if (!in_array('gender', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT '' AFTER nickname");
    }
    if (!in_array('org_position', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN org_position VARCHAR(100) DEFAULT '' AFTER primary_position");
    }
    if (!in_array('department', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN department VARCHAR(100) DEFAULT '' AFTER org_position");
    }
    if (!in_array('job', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN job VARCHAR(100) DEFAULT '' AFTER department");
    }
    if (!in_array('education', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN education VARCHAR(255) DEFAULT '' AFTER phone");
    }

    // 5. สร้างตารางสิทธิ์ระบบย่อย: app_roles
    $db->exec("CREATE TABLE IF NOT EXISTS app_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        app_id VARCHAR(50) NOT NULL, -- 'pnp-go', 'pnp-academic', 'pnp-man'
        role VARCHAR(50) NOT NULL,   -- 'admin', 'user', 'driver', 'teacher', 'none'
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_app (user_id, app_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 6. สร้างตารางฝ่ายงานหลัก: departments
    $db->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 7. สร้างตารางงานย่อย / แผนกวิชา: jobs
    $db->exec("CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        UNIQUE KEY unique_dept_job (department_id, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 8. สร้างตารางการมอบหมายตำแหน่งหน้าที่: user_org_assignments
    $db->exec("CREATE TABLE IF NOT EXISTS user_org_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        org_position ENUM('รองผู้อำนวยการ', 'หัวหน้างาน', 'ผู้ช่วยหัวหน้างาน', 'เจ้าหน้าที่', 'ครูผู้สอน') NOT NULL,
        department_id INT NULL,
        job_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
        UNIQUE KEY unique_user_assignment (user_id, org_position, department_id, job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 9. ทำการ Seed ข้อมูลเบื้องต้นลงในตารางฝ่ายและตารางงาน (หากยังไม่มีข้อมูล)
    $deptCount = $db->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    if ($deptCount == 0) {
        $db->exec("INSERT INTO departments (id, name, sort_order) VALUES
            (1, 'ฝ่ายบริหารทรัพยากร', 1),
            (2, 'ฝ่ายยุทธศาสตร์และแผนงาน', 2),
            (3, 'ฝ่ายกิจการนักเรียน นักศึกษา', 3),
            (4, 'ฝ่ายวิชาการ', 4)
        ");

        $db->exec("INSERT INTO jobs (department_id, name) VALUES
            (1, 'งานบริหารงานทั่วไป'), (1, 'งานบริหารและพัฒนาทรัพยากรบุคคล'), (1, 'งานการเงิน'), (1, 'งานการบัญชี'), (1, 'งานพัสดุ'), (1, 'งานอาคารสถานที่'), (1, 'งานทะเบียน'),
            (2, 'งานพัฒนายุทธศาสตร์ แผนงาน และงบประมาณ'), (2, 'งานมาตรฐานและประกันคุณภาพ'), (2, 'งานศูนย์ดิจิทัลและสื่อสารองค์กร'), (2, 'งานส่งเสริมการวิจัย นวัตกรรม และสิ่งประดิษฐ์'), (2, 'งานส่งเสริมธุรกิจและการเป็นผู้ประกอบการ'), (2, 'งานติดตามและประเมินผลการอาชีวศึกษา'),
            (3, 'งานกิจกรรมนักเรียนนักศึกษา'), (3, 'งานครูที่ปรึกษาและการแนะแนว'), (3, 'งานปกครองและความปลอดภัยนักเรียนนักศึกษา'), (3, 'งานโครงการพิเศษและการบริการ'), (3, 'งานสวัสดิการนักเรียน นักศึกษา'),
            (4, 'แผนกวิชาช่างกลโรงงานและเทคนิคพื้นฐาน'), (4, 'แผนกวิชาช่างยนต์'), (4, 'แผนกวิชาช่างไฟฟ้ากำลัง'), (4, 'แผนกวิชาช่างอิเล็กทรอนิกส์'), (4, 'แผนกวิชาการบัญชี'), (4, 'แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล'), (4, 'แผนกวิชาสามัญสัมพันธ์'), (4, 'แผนกวิชาชีพระยะสั้น'), (4, 'งานพัฒนาหลักสูตรและการจัดการเรียนรู้'), (4, 'งานวัดผลและประเมินผล'), (4, 'งานอาชีวศึกษาระบบทวิภาคีและความร่วมมือ'), (4, 'งานวิทยบริการและเทคโนโลยีการศึกษา'), (4, 'งานการศึกษาพิเศษและความเสมอภาคทางการศึกษา')
        ");
    }

    // 10. ทำการ Seed ข้อมูลบัญชีผู้ใช้งานเริ่มต้นลง MySQL
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        $db->beginTransaction();

        $usersToSeed = [
            [
                'username' => 'admin',
                'password' => 'admin123',
                'first_name' => 'ผู้ดูแลระบบ',
                'last_name' => 'สูงสุด (Admin)',
                'email' => 'admin@pnp.ac.th',
                'primary_position' => 'ผู้ดูแลระบบ',
                'org_position' => 'ผู้ดูแลระบบ',
                'department' => 'ผู้ดูแลระบบ',
                'is_portal_admin' => 1,
                'roles' => [
                    'pnp-go' => 'admin',
                    'pnp-academic' => 'admin',
                    'pnp-man' => 'admin'
                ]
            ]
        ];

        foreach ($usersToSeed as $u) {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, first_name, last_name, email, primary_position, org_position, department, is_portal_admin) 
                                  VALUES (:username, :password_hash, :first_name, :last_name, :email, :primary_position, :org_position, :department, :is_portal_admin)");
            $stmt->execute([
                ':username' => $u['username'],
                ':password_hash' => password_hash($u['password'], PASSWORD_BCRYPT),
                ':first_name' => $u['first_name'],
                ':last_name' => $u['last_name'],
                ':email' => $u['email'],
                ':primary_position' => $u['primary_position'],
                ':org_position' => $u['org_position'],
                ':department' => $u['department'],
                ':is_portal_admin' => $u['is_portal_admin']
            ]);
            
            $userId = $db->lastInsertId();

            foreach ($u['roles'] as $appId => $role) {
                $stmtRole = $db->prepare("INSERT INTO app_roles (user_id, app_id, role) VALUES (:user_id, :app_id, :role)");
                $stmtRole->execute([
                    ':user_id' => $userId,
                    ':app_id' => $appId,
                    ':role' => $role
                ]);
            }
        }

        $db->commit();
    }

} catch (PDOException $e) {
    sendResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}
