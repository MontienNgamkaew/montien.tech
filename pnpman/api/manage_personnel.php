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

// Parse input based on request Content-Type (JSON or Multipart/Form-data)
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
$action = '';
$id = null;
$name = '';
$main_title = '';
$delete_photo = false;

if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $id = $input['id'] ?? null;
    $name = $input['name'] ?? '';
    $main_title = $input['main_title'] ?? '';
    $delete_photo = $input['delete_photo'] ?? false;
} else {
    // multipart/form-data or application/x-www-form-urlencoded
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $main_title = $_POST['main_title'] ?? '';
    $delete_photo = isset($_POST['delete_photo']) && ($_POST['delete_photo'] === 'true' || $_POST['delete_photo'] === '1');
}

try {
    if ($action === 'add') {
        if (!$name || !$main_title) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "กรุณากรอกข้อมูลให้ครบถ้วน (ชื่อ และ ตำแหน่งหลัก)"]);
            exit;
        }

        $photo_path = null;

        // Handle profile photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmp = $file['tmp_name'];

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'svg'];

            if (!in_array($ext, $allowed)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ไฟล์ภาพต้องเป็นรูปแบบ JPG, JPEG, PNG หรือ SVG เท่านั้น"]);
                exit;
            }

            if ($fileSize > 2 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB"]);
                exit;
            }

            $newFileName = 'personnel_' . uniqid() . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/';
            
            // Ensure uploads directory exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetFile = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                $photo_path = 'uploads/' . $newFileName;
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "ไม่สามารถบันทึกไฟล์รูปภาพประจำตัวได้"]);
                exit;
            }
        }

        $parsedName = parseFullName($name);
        $uniq = uniqid();
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, title, first_name, last_name, email, primary_position, avatar_path, status, is_portal_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 0)");
        $stmt->execute([
            'user_' . $uniq,
            password_hash('123456', PASSWORD_BCRYPT),
            $parsedName['title'],
            $parsedName['first_name'],
            $parsedName['last_name'],
            'user_' . $uniq . '@pnp.ac.th',
            $main_title,
            $photo_path
        ]);
        $newUserId = $pdo->lastInsertId();

        // Assign default app roles
        $stmtRole = $pdo->prepare("INSERT INTO app_roles (user_id, app_id, role) VALUES (?, 'pnp-go', 'none'), (?, 'pnp-academic', 'none'), (?, 'pnp-man', 'user')");
        $stmtRole->execute([$newUserId, $newUserId, $newUserId]);

        echo json_encode(["status" => "success", "id" => $newUserId]);

    } elseif ($action === 'update') {
        if (!$id || !$name || !$main_title) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ข้อมูลไม่ครบถ้วนสำหรับการแก้ไข"]);
            exit;
        }

        // Fetch existing personnel photo path from users
        $stmt = $pdo->prepare("SELECT avatar_path AS photo_path FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        $current_photo_path = $existing ? $existing['photo_path'] : null;
        $new_photo_path = $current_photo_path;

        // If requested to delete current photo
        if ($delete_photo) {
            if (!empty($current_photo_path)) {
                $full_path = __DIR__ . '/../' . $current_photo_path;
                if (file_exists($full_path) && is_file($full_path)) {
                    @unlink($full_path);
                }
            }
            $new_photo_path = null;
        }

        // Handle new photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmp = $file['tmp_name'];

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'svg'];

            if (!in_array($ext, $allowed)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ไฟล์ภาพต้องเป็นรูปแบบ JPG, JPEG, PNG หรือ SVG เท่านั้น"]);
                exit;
            }

            if ($fileSize > 2 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB"]);
                exit;
            }

            $newFileName = 'personnel_' . uniqid() . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/';

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $targetFile = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $targetFile)) {
                // Delete previous old photo
                if (!empty($current_photo_path)) {
                    $old_full_path = __DIR__ . '/../' . $current_photo_path;
                    if (file_exists($old_full_path) && is_file($old_full_path)) {
                        @unlink($old_full_path);
                    }
                }
                $new_photo_path = 'uploads/' . $newFileName;
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "ไม่สามารถบันทึกไฟล์รูปภาพใหม่ได้"]);
                exit;
            }
        }

        $parsedName = parseFullName($name);
        $stmt = $pdo->prepare("UPDATE users SET title = ?, first_name = ?, last_name = ?, primary_position = ?, avatar_path = ? WHERE id = ?");
        $stmt->execute([$parsedName['title'], $parsedName['first_name'], $parsedName['last_name'], $main_title, $new_photo_path, $id]);
        echo json_encode(["status" => "success"]);

    } elseif ($action === 'delete') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ไม่ระบุรหัสประจำตัวบุคลากร"]);
            exit;
        }

        // Fetch photo path to clean up file before deleting record from users
        $stmt = $pdo->prepare("SELECT avatar_path AS photo_path FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        $current_photo_path = $existing ? $existing['photo_path'] : null;

        if (!empty($current_photo_path)) {
            $full_path = __DIR__ . '/../' . $current_photo_path;
            if (file_exists($full_path) && is_file($full_path)) {
                @unlink($full_path);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["status" => "success"]);

    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "การทำงานไม่ถูกต้อง"]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage()]);
}
?>
