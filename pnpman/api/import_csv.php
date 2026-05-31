<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');
checkAuth($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

if (!isset($_FILES['csv_file'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "ไม่พบไฟล์ CSV"]);
    exit;
}

$file = $_FILES['csv_file'];

// Validate file type
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "กรุณาอัปโหลดไฟล์ .csv เท่านั้น"]);
    exit;
}

$validTitles = ['ผู้อำนวยการ', 'รองผู้อำนวยการ', 'ข้าราชการครู', 'พนักงานราชการครู', 'ครูพิเศษสอน', 'เจ้าหน้าที่'];

try {
    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        throw new Exception("ไม่สามารถเปิดไฟล์ได้");
    }

    // Detect and strip BOM (UTF-8 BOM from Excel)
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new Exception("ไฟล์ CSV ว่างเปล่า");
    }

    // Normalize header (trim whitespace)
    $header = array_map('trim', $header);

    // Find column indices
    $nameIdx = null;
    $titleIdx = null;
    foreach ($header as $i => $col) {
        $col = mb_strtolower($col, 'UTF-8');
        if (in_array($col, ['name', 'ชื่อ', 'ชื่อ-นามสกุล', 'ชื่อ-สกุล', 'ชื่อ นามสกุล'])) {
            $nameIdx = $i;
        }
        if (in_array($col, ['main_title', 'ตำแหน่ง', 'ตำแหน่งหลัก'])) {
            $titleIdx = $i;
        }
    }

    if ($nameIdx === null || $titleIdx === null) {
        fclose($handle);
        echo json_encode([
            "status" => "error",
            "message" => "ไม่พบคอลัมน์ที่จำเป็น กรุณาตรวจสอบว่ามีคอลัมน์ 'ชื่อ-นามสกุล' และ 'ตำแหน่งหลัก' ในไฟล์ CSV"
        ]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, title, first_name, last_name, email, primary_position, status, is_portal_admin) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0)");
    $stmtRole = $pdo->prepare("INSERT INTO app_roles (user_id, app_id, role) VALUES (?, 'pnp-go', 'none'), (?, 'pnp-academic', 'none'), (?, 'pnp-man', 'user')");
    $added = 0;
    $skipped = 0;
    $errors = [];
    $lineNum = 1; // header was line 1

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;

        // Skip empty rows
        if (!$row || (count($row) === 1 && trim($row[0]) === '')) {
            continue;
        }

        $name = isset($row[$nameIdx]) ? trim($row[$nameIdx]) : '';
        $title = isset($row[$titleIdx]) ? trim($row[$titleIdx]) : '';

        // Skip rows with empty name
        if ($name === '') {
            $skipped++;
            continue;
        }

        // Validate title
        if ($title !== '' && !in_array($title, $validTitles)) {
            $errors[] = "บรรทัด {$lineNum}: ตำแหน่ง '{$title}' ไม่ถูกต้อง (ข้าม)";
            $skipped++;
            continue;
        }

        try {
            $parsedName = parseFullName($name);
            $uniq = uniqid();
            $stmt->execute([
                'user_' . $uniq,
                password_hash('123456', PASSWORD_BCRYPT),
                $parsedName['title'],
                $parsedName['first_name'],
                $parsedName['last_name'],
                'user_' . $uniq . '@pnp.ac.th',
                $title
            ]);
            $newUserId = $pdo->lastInsertId();
            $stmtRole->execute([$newUserId, $newUserId, $newUserId]);
            $added++;
        } catch (PDOException $e) {
            $errors[] = "บรรทัด {$lineNum}: {$e->getMessage()}";
            $skipped++;
        }
    }

    fclose($handle);

    $response = [
        "status" => "success",
        "message" => "นำเข้าสำเร็จ {$added} คน" . ($skipped > 0 ? " (ข้าม {$skipped} รายการ)" : ""),
        "added" => $added,
        "skipped" => $skipped,
    ];
    if (!empty($errors)) {
        $response["errors"] = $errors;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
