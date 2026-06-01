<?php
/* -------------------------------------------------------------
 * IMPORT MEMBERS FROM CSV API
 * Securely imports multiple members from an uploaded CSV file.
 * Handles Thai character encoding (UTF-8 and TIS-620/Windows-874).
 * Performs duplication and formatting checks.
 * Secured via Administrator JWT verification.
 * ------------------------------------------------------------- */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';

// 1. ตรวจสอบการยืนยันตัวตนระดับผู้ดูแลระบบ (Admin Authorization)
$token = '';
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

// Allow token from POST parameter for file uploads (some JS upload libraries send it this way)
if (empty($token) && isset($_POST['token'])) {
    $token = $_POST['token'];
}

if (empty($token) && isset($_GET['token'])) {
    $token = $_GET['token'];
}

if (empty($token)) {
    sendResponse(['error' => 'จำเป็นต้องลงชื่อเข้าใช้งานระดับผู้ดูแลระบบ'], 401);
}

// ถอดรหัส Token และตรวจสิทธิ์การเป็นแอดมินพอร์ทัลหลัก
$adminPayload = JWT::decode($token, JWT_SECRET_KEY);
if (!$adminPayload || (int)$adminPayload['is_portal_admin'] !== 1) {
    sendResponse(['error' => 'ไม่มีสิทธิ์การใช้งาน: เฉพาะผู้ดูแลระบบพอร์ทัลกลางเท่านั้น'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Method Not Allowed'], 405);
}

// ตรวจสอบไฟล์อัปโหลด
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    sendResponse(['error' => 'ไม่พบไฟล์อัปโหลด หรือเกิดข้อผิดพลาดในการอัปโหลดไฟล์'], 400);
}

$fileTmpPath = $_FILES['csv_file']['tmp_name'];
$fileName = $_FILES['csv_file']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExtension !== 'csv') {
    sendResponse(['error' => 'กรุณาอัปโหลดเฉพาะไฟล์นามสกุล .csv เท่านั้น'], 400);
}

// อ่านเนื้อหาไฟล์
$content = file_get_contents($fileTmpPath);
if ($content === false) {
    sendResponse(['error' => 'ไม่สามารถอ่านเนื้อหาไฟล์ได้'], 500);
}

// ตรวจสอบและเอา UTF-8 BOM ออกหากมี
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}

// ตรวจจับและแปลง Encoding ของภาษาไทย (ป้องกันภาษาต่างดาว)
if (!mb_check_encoding($content, 'UTF-8')) {
    // หากไม่ใช่ UTF-8 แปลว่าอาจเป็น TIS-620 หรือ Windows-874 ที่ส่งออกจาก Excel เวอร์ชั่นเก่าในไทย
    $convertedContent = @mb_convert_encoding($content, 'UTF-8', 'TIS-620');
    if ($convertedContent !== false && mb_check_encoding($convertedContent, 'UTF-8')) {
        $content = $convertedContent;
    } else {
        // ลองแปลงด้วย iconv เผื่อไว้
        $convertedContent = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
        if ($convertedContent !== false) {
            $content = $convertedContent;
        }
    }
}

// สร้าง Temporary Stream เพื่อใช้ฟังก์ชัน fgetcsv ในการแยกคอลัมน์
$temp = fopen('php://temp', 'r+');
fwrite($temp, $content);
rewind($temp);

// อ่านหัวข้อคอลัมน์ (Headers)
$headers = fgetcsv($temp);
if ($headers === false) {
    fclose($temp);
    sendResponse(['error' => 'ไฟล์ CSV ไม่มีข้อมูลหัวข้อคอลัมน์ หรือไฟล์ว่างเปล่า'], 400);
}

// ปรับแต่งคีย์หัวข้อ (ลบช่องว่าง และทำให้เป็นตัวพิมพ์เล็กทั้งหมดเพื่อรองรับภาษาอังกฤษและไทย)
$headers = array_map(function($h) {
    return trim($h);
}, $headers);

// ค้นหาตำแหน่งอินเด็กซ์ของหัวข้อคอลัมน์แบบยืดหยุ่น (รองรับทั้งภาษาไทยตามภาพและภาษาอังกฤษ)
$idxUsername = -1;
$idxFirstName = -1;
$idxLastName = -1;
$idxBirthdate = -1;
$idxNickname = -1;
$idxGender = -1;
$idxPrimaryPos = -1;

foreach ($headers as $index => $header) {
    $headerClean = strtolower($header);
    
    if ($header === 'รหัสประชาชน' || $header === 'เลขประจำตัวประชาชน' || $headerClean === 'username') {
        $idxUsername = $index;
    } elseif ($header === 'ชื่อ' || $header === 'ชื่อจริง' || $headerClean === 'first_name') {
        $idxFirstName = $index;
    } elseif ($header === 'สกุล' || $header === 'นามสกุล' || $headerClean === 'last_name') {
        $idxLastName = $index;
    } elseif ($header === 'วัน/เดือน/ปีเกิด' || $header === 'วันเกิด' || $headerClean === 'birthdate') {
        $idxBirthdate = $index;
    } elseif ($header === 'ชื่อเล่น' || $headerClean === 'nickname') {
        $idxNickname = $index;
    } elseif ($header === 'เพศ' || $headerClean === 'gender') {
        $idxGender = $index;
    } elseif ($header === 'ตำแหน่งหลัก' || $header === 'ตำแหน่ง' || $headerClean === 'primary_position') {
        $idxPrimaryPos = $index;
    }
}

// ตรวจสอบคอลัมน์ที่จำเป็นสุดๆ (ต้องมีรหัสประชาชน ชื่อ และสกุล)
if ($idxUsername === -1 || $idxFirstName === -1 || $idxLastName === -1) {
    fclose($temp);
    sendResponse([
        'error' => 'หัวข้อคอลัมน์ของไฟล์ CSV ไม่ถูกต้องตามโครงสร้างข้อมูลพอร์ทัล!',
        'details' => 'จำเป็นต้องมีคอลัมน์หัวข้ออย่างน้อย: "รหัสประชาชน", "ชื่อ", "สกุล" (ภาษาไทยตรงตามระบบดั้งเดิม)'
    ], 400);
}

$successCount = 0;
$failedCount = 0;
$failures = [];
$rowNum = 1; // เริ่มนับแถวที่ 1 คือ หัวข้อ

// เตรียม SQL Statements สำหรับความรวดเร็วและปลอดภัย (Prepared Statements)
try {
    $stmtCheckUser = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmtCheckEmail = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmtInsertUser = $db->prepare("
        INSERT INTO users (username, password_hash, title, first_name, last_name, email, birthdate, nickname, gender, primary_position, is_portal_admin)
        VALUES (:username, :password_hash, :title, :first_name, :last_name, :email, :birthdate, :nickname, :gender, :primary_position, 0)
    ");
    $stmtRoleInit = $db->prepare("INSERT INTO app_roles (user_id, app_id, role) VALUES (:user_id, :app_id, :role)");

    // วนลูปอ่านข้อมูลทีละแถว
    while (($row = fgetcsv($temp)) !== false) {
        $rowNum++;
        
        // ข้ามแถวว่าง
        if (empty(array_filter($row))) {
            continue;
        }
        
        // ดึงค่าตามอินเด็กซ์ที่ค้นหาเจอ (หากไม่มีใน CSV ให้เก็บเป็นค่าว่าง)
        $username = $idxUsername !== -1 && isset($row[$idxUsername]) ? trim($row[$idxUsername]) : '';
        $rawName = $idxFirstName !== -1 && isset($row[$idxFirstName]) ? trim($row[$idxFirstName]) : '';
        $lastName = $idxLastName !== -1 && isset($row[$idxLastName]) ? trim($row[$idxLastName]) : '';
        
        $birthdate = $idxBirthdate !== -1 && isset($row[$idxBirthdate]) ? trim($row[$idxBirthdate]) : '';
        $nickname = $idxNickname !== -1 && isset($row[$idxNickname]) ? trim($row[$idxNickname]) : '';
        $gender = $idxGender !== -1 && isset($row[$idxGender]) ? trim($row[$idxGender]) : '';
        
        $primaryPos = $idxPrimaryPos !== -1 && isset($row[$idxPrimaryPos]) ? trim($row[$idxPrimaryPos]) : '';
        
        // 1. ตรวจสอบฟิลด์หลักห้ามว่าง
        if (empty($username) || empty($rawName) || empty($lastName)) {
            $failedCount++;
            $failures[] = "แถวที่ {$rowNum}: ข้อมูลจำเป็นไม่ครบถ้วน (ต้องระบุรหัสประชาชน, ชื่อ, สกุล)";
            continue;
        }
        
        // 2. ตรวจสอบความถูกต้องของเลขประจำตัวประชาชน 13 หลัก
        if (!preg_match('/^\d{13}$/', $username)) {
            $failedCount++;
            $failures[] = "แถวที่ {$rowNum}: รหัสประชาชน '{$username}' ต้องเป็นตัวเลข 13 หลัก";
            continue;
        }
        
        // 3. แยก "คำนำหน้าชื่อ" และ "ชื่อจริง" ออกจากคอลัมน์ "ชื่อ" โดยอัตโนมัติ
        $title = '';
        $firstName = $rawName;
        
        $prefixes = ['นาย', 'นางสาว', 'นาง', 'เด็กชาย', 'เด็กหญิง', 'พระครู', 'พระ'];
        foreach ($prefixes as $prefix) {
            if (strpos($rawName, $prefix) === 0) {
                $title = $prefix;
                $firstName = trim(substr($rawName, strlen($prefix)));
                break;
            }
        }
        
        // 4. ตรวจจับตำแหน่งหลักโดยอัตโนมัติจากข้อมูลเพิ่มเติมหากไม่ได้ระบุคอลัมน์ตำแหน่งมา
        if (empty($primaryPos)) {
            if (strpos($nickname, 'ผอ.') !== false || strpos($rawName, 'ผู้อำนวยการ') !== false) {
                $primaryPos = 'ผู้อำนวยการ';
            } elseif (strpos($nickname, 'รองฯ') !== false || strpos($rawName, 'รองผู้อำนวยการ') !== false) {
                $primaryPos = 'รองผู้อำนวยการ';
            } elseif (strpos($nickname, 'ภารโรง') !== false || strpos($nickname, 'นักการ') !== false) {
                $primaryPos = 'นักการภารโรง';
            } elseif (strpos($nickname, 'แม่บ้าน') !== false) {
                $primaryPos = 'แม่บ้าน';
            } elseif (strpos($nickname, 'ขับรถ') !== false || strpos($nickname, 'คนขับ') !== false) {
                $primaryPos = 'พนักงานขับรถ';
            } elseif (strpos($nickname, 'เจ้าหน้าที่') !== false || strpos($nickname, 'พัสดุ') !== false || strpos($nickname, 'การเงิน') !== false || strpos($nickname, 'ทะเบียน') !== false) {
                $primaryPos = 'เจ้าหน้าที่';
            } else {
                // ค่าเริ่มต้นสำหรับสมาชิกครูทั่วไปตามหน้างาน
                $primaryPos = 'ข้าราชการครู';
            }
        }
        
        // 5. สร้างอีเมลอัตโนมัติจากเลขประจำตัวประชาชน
        $email = $username . '@pnp.ac.th';
        
        // 6. ตรวจสอบเลขประจำตัวประชาชนซ้ำในระบบ
        $stmtCheckUser->execute([':username' => $username]);
        if ($stmtCheckUser->fetchColumn() > 0) {
            $failedCount++;
            $failures[] = "แถวที่ {$rowNum}: รหัสประชาชน '{$username}' มีอยู่แล้วในระบบ";
            continue;
        }
        
        // 7. ตรวจสอบอีเมลซ้ำในระบบ
        $stmtCheckEmail->execute([':email' => $email]);
        if ($stmtCheckEmail->fetchColumn() > 0) {
            $failedCount++;
            $failures[] = "แถวที่ {$rowNum}: อีเมลระบบ '{$email}' ถูกใช้งานโดยบัญชีอื่นแล้ว";
            continue;
        }
        
        // 8. ดำเนินการเพิ่มลงฐานข้อมูลพอร์ทัล
        try {
            $db->beginTransaction();
            
            // เพิ่มผู้ใช้ (รหัสผ่านเริ่มต้นตั้งเป็น รหัสประชาชน 13 หลัก)
            $stmtInsertUser->execute([
                ':username' => $username,
                ':password_hash' => password_hash($username, PASSWORD_BCRYPT),
                ':title' => $title,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':birthdate' => $birthdate,
                ':nickname' => $nickname,
                ':gender' => $gender,
                ':primary_position' => $primaryPos
            ]);
            
            $newUserId = $db->lastInsertId();
            
            // ตั้งค่าเริ่มต้นสิทธิ์เข้าใช้ระบบย่อยทั้ง 3 ตัวตามกฎเริ่มต้น
            $go_role = 'none';
            $academic_role = 'none';
            $man_role = 'none';

            if (in_array($primaryPos, ['ข้าราชการครู', 'พนักงานราชการครู', 'ครูพิเศษสอน'])) {
                $go_role = 'user';
                $academic_role = 'user';
                $man_role = 'user';
            } elseif (in_array($primaryPos, ['เจ้าหน้าที่', 'นักการภารโรง', 'แม่บ้าน', 'พนักงานขับรถ'])) {
                $go_role = 'user';
                $academic_role = 'none';
                $man_role = 'user';
            }

            $stmtRoleInit->execute([
                ':user_id' => $newUserId,
                ':app_id' => 'pnp-go',
                ':role' => $go_role
            ]);
            $stmtRoleInit->execute([
                ':user_id' => $newUserId,
                ':app_id' => 'pnp-academic',
                ':role' => $academic_role
            ]);
            $stmtRoleInit->execute([
                ':user_id' => $newUserId,
                ':app_id' => 'pnp-man',
                ':role' => $man_role
            ]);
            
            $db->commit();
            $successCount++;
            
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $failedCount++;
            $failures[] = "แถวที่ {$rowNum}: บันทึกข้อมูลล้มเหลวเนื่องจากระบบฐานข้อมูล ({$e->getMessage()})";
        }
    }
    
    fclose($temp);
    
    sendResponse([
        'success' => true,
        'total_rows' => $rowNum - 1,
        'success_count' => $successCount,
        'failed_count' => $failedCount,
        'failures' => $failures
    ]);

} catch (PDOException $ex) {
    fclose($temp);
    sendResponse(['error' => 'เกิดข้อผิดพลาดในการประมวลผลข้อมูลฐานข้อมูล: ' . $ex->getMessage()], 500);
}
