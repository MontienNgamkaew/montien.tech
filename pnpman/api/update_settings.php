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

$college_name = $_POST['college_name'] ?? '';
$theme_preset = $_POST['theme_preset'] ?? 'rose';
$delete_logo = isset($_POST['delete_logo']) && $_POST['delete_logo'] === 'true';

if (empty($college_name)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "กรุณาระบุชื่อวิทยาลัย"]);
    exit;
}

$allowed_themes = ['rose', 'emerald', 'sky', 'indigo', 'amber', 'slate'];
if (!in_array($theme_preset, $allowed_themes)) {
    $theme_preset = 'rose';
}

try {
    // Get existing settings to check current logo
    $stmt = $pdo->query("SELECT logo_path FROM college_settings WHERE id = 1");
    $existing = $stmt->fetch();
    $current_logo_path = $existing ? $existing['logo_path'] : '';
    $new_logo_path = $current_logo_path;

    if ($delete_logo) {
        if (!empty($current_logo_path)) {
            $full_path = __DIR__ . '/../' . $current_logo_path;
            if (file_exists($full_path) && is_file($full_path)) {
                @unlink($full_path);
            }
        }
        $new_logo_path = '';
    }

    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        
        // Validation
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'svg'];
        
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ไม่อนุญาตให้อัปโหลดไฟล์ประเภทนี้ รองรับเฉพาะ JPG, JPEG, PNG, SVG"]);
            exit;
        }

        // Limit size to 2MB
        if ($fileSize > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ขนาดไฟล์ห้ามเกิน 2MB"]);
            exit;
        }

        // Create target filename
        $newFileName = 'logo_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/';
        $targetFile = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $targetFile)) {
            // Delete old file if existed
            if (!empty($current_logo_path)) {
                $old_full_path = __DIR__ . '/../' . $current_logo_path;
                if (file_exists($old_full_path) && is_file($old_full_path)) {
                    @unlink($old_full_path);
                }
            }
            $new_logo_path = 'uploads/' . $newFileName;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "ไม่สามารถจัดเก็บภาพโลโก้ได้"]);
            exit;
        }
    }

    // Update in database
    $stmt = $pdo->prepare("UPDATE college_settings 
                           SET college_name = ?, logo_path = ?, theme_preset = ? 
                           WHERE id = 1");
    $stmt->execute([$college_name, $new_logo_path, $theme_preset]);

    echo json_encode([
        "status" => "success",
        "message" => "บันทึกตั้งค่าวิทยาลัยสำเร็จแล้ว",
        "data" => [
            "college_name" => $college_name,
            "logo_path" => $new_logo_path,
            "theme_preset" => $theme_preset
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database failure: " . $e->getMessage()]);
}
?>
