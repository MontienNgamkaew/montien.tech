<?php
declare(strict_types=1);

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';

// Force teacher role
require_teacher();

$teacherId = current_user_id();

// 1. Get active semester
$semester = $pdo->query('SELECT id, semester_name FROM semesters WHERE is_active = 1 LIMIT 1')->fetch();
if (!$semester) {
    exit('ไม่พบภาคเรียนที่กำลังเปิดใช้งานในระบบ กรุณาติดต่อผู้ดูแลระบบ');
}

// 2. Fetch teacher's courses
$stmt = $pdo->prepare('SELECT id, course_code, course_name FROM courses WHERE teacher_id = :teacher_id AND semester_id = :semester_id ORDER BY course_code ASC');
$stmt->execute([
    'teacher_id' => $teacherId,
    'semester_id' => $semester['id']
]);
$courses = $stmt->fetchAll();

// Get input parameters
$selectedCourseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$selectedSystemType = isset($_GET['system_type']) ? $_GET['system_type'] : '';

// Validate system type
$validSystemTypes = ['course_syllabus', 'lesson_plan', 'teaching_materials'];
if ($selectedSystemType && !in_array($selectedSystemType, $validSystemTypes, true)) {
    $selectedSystemType = '';
}

// Fetch specific course details if selected
$courseDetails = null;
if ($selectedCourseId) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $selectedCourseId) {
            $courseDetails = $c;
            break;
        }
    }
    // If course not found in teacher's assigned courses, reset selection
    if (!$courseDetails) {
        $selectedCourseId = 0;
    }
}

// Fetch existing submission if selected
$existingSubmission = null;
if ($selectedCourseId && $selectedSystemType) {
    $stmt = $pdo->prepare('SELECT id, file_path, drive_link FROM submissions WHERE course_id = :course_id AND system_type = :system_type LIMIT 1');
    $stmt->execute([
        'course_id' => $selectedCourseId,
        'system_type' => $selectedSystemType
    ]);
    $existingSubmission = $stmt->fetch();
}

// Check deadline and open status for the system type
$systemSetting = null;
$isOpen = false;
$isLate = false;
$deadlineStr = '-';

if ($selectedSystemType) {
    $stmt = $pdo->prepare('SELECT deadline_date, is_open FROM system_settings WHERE system_type = :system_type AND semester_id = :semester_id LIMIT 1');
    $stmt->execute([
        'system_type' => $selectedSystemType,
        'semester_id' => $semester['id']
    ]);
    $systemSetting = $stmt->fetch();
    
    if ($systemSetting) {
        $isOpen = (int)$systemSetting['is_open'] === 1;
        $deadlineTime = strtotime($systemSetting['deadline_date']);
        $isLate = time() > $deadlineTime;
        $deadlineStr = date('d/m/Y H:i', $deadlineTime);
    }
}

$errorMessage = '';
$successMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $action = $_POST['action'] ?? 'submit_document';
        
        if ($action === 'add_course') {
            $courseCode = trim((string)($_POST['course_code'] ?? ''));
            $courseName = trim((string)($_POST['course_name'] ?? ''));
            
            if ($courseCode === '' || $courseName === '') {
                $errorMessage = 'กรุณากรอกรหัสวิชาและชื่อรายวิชาให้ครบถ้วน';
            } else {
                // Check if already exists for this teacher in this semester
                $stmt = $pdo->prepare('SELECT id FROM courses WHERE course_code = :code AND teacher_id = :t_id AND semester_id = :sem_id LIMIT 1');
                $stmt->execute([
                    'code' => $courseCode,
                    't_id' => $teacherId,
                    'sem_id' => $semester['id']
                ]);
                if ($stmt->fetch()) {
                    $errorMessage = "คุณมีรายวิชา '{$courseCode}' ในบัญชีการจัดสอนประจำภาคเรียนนี้อยู่แล้ว";
                } else {
                    $stmt = $pdo->prepare('INSERT INTO courses (course_code, course_name, teacher_id, semester_id) VALUES (:code, :name, :t_id, :sem_id)');
                    $stmt->execute([
                        'code' => $courseCode,
                        'name' => $courseName,
                        't_id' => $teacherId,
                        'sem_id' => $semester['id']
                    ]);
                    
                    $successMessage = "เพิ่มรายวิชา '{$courseCode} - {$courseName}' สำเร็จเรียบร้อยแล้ว!";
                    // Refresh courses array
                    $stmt = $pdo->prepare('SELECT id, course_code, course_name FROM courses WHERE teacher_id = :teacher_id AND semester_id = :semester_id ORDER BY course_code ASC');
                    $stmt->execute([
                        'teacher_id' => $teacherId,
                        'semester_id' => $semester['id']
                    ]);
                    $courses = $stmt->fetchAll();
                }
            }
        } elseif ($action === 'delete_course') {
            $courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            
            // Verify ownership first for security
            $stmt = $pdo->prepare('SELECT course_code, course_name FROM courses WHERE id = :course_id AND teacher_id = :t_id AND semester_id = :sem_id LIMIT 1');
            $stmt->execute([
                'course_id' => $courseId,
                't_id' => $teacherId,
                'sem_id' => $semester['id']
            ]);
            $courseToDelete = $stmt->fetch();
            
            if ($courseToDelete) {
                // Delete course (will cascade delete submissions in DB)
                $stmt = $pdo->prepare('DELETE FROM courses WHERE id = :course_id');
                $stmt->execute(['course_id' => $courseId]);
                
                $successMessage = "ลบรายวิชา '{$courseToDelete['course_code']} - {$courseToDelete['course_name']}' ออกจากบัญชีสอนเรียบร้อยแล้ว";
                
                // If the deleted course was currently selected, reset it
                if ($selectedCourseId === $courseId) {
                    $selectedCourseId = 0;
                    $courseDetails = null;
                }
                
                // Refresh courses array
                $stmt = $pdo->prepare('SELECT id, course_code, course_name FROM courses WHERE teacher_id = :teacher_id AND semester_id = :semester_id ORDER BY course_code ASC');
                $stmt->execute([
                    'teacher_id' => $teacherId,
                    'semester_id' => $semester['id']
                ]);
                $courses = $stmt->fetchAll();
            } else {
                $errorMessage = 'ไม่พบรายวิชาที่ต้องการลบ หรือคุณไม่มีสิทธิ์ลบรายวิชานี้';
            }
        } elseif ($action === 'submit_document') {
            $postCourseId = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
            $postSystemType = isset($_POST['system_type']) ? $_POST['system_type'] : '';
            $driveLink = isset($_POST['drive_link']) ? trim($_POST['drive_link']) : '';
            $youtubeLink = isset($_POST['youtube_link']) ? trim($_POST['youtube_link']) : '';
            
            if ($postSystemType === 'teaching_materials' && !empty($youtubeLink)) {
                if (!empty($driveLink)) {
                    $errorMessage = 'กรุณาเลือกใส่ลิงก์ Google Drive หรือลิงก์ YouTube อย่างใดอย่างหนึ่งเท่านั้น';
                } else {
                    $driveLink = $youtubeLink;
                }
            }
            
            // Validation
            $validPostCourse = false;
            foreach ($courses as $c) {
                if ((int)$c['id'] === $postCourseId) {
                    $validPostCourse = true;
                    break;
                }
            }
            
            if (!$validPostCourse) {
                $errorMessage = 'กรุณาเลือกวิชาที่สอนให้ถูกต้อง';
            } elseif (!in_array($postSystemType, $validSystemTypes, true)) {
                $errorMessage = 'กรุณาเลือกประเภทระบบส่งงานให้ถูกต้อง';
            } else {
                // Get system settings for validation
                $stmt = $pdo->prepare('SELECT deadline_date, is_open FROM system_settings WHERE system_type = :system_type AND semester_id = :semester_id LIMIT 1');
                $stmt->execute([
                    'system_type' => $postSystemType,
                    'semester_id' => $semester['id']
                ]);
                $setting = $stmt->fetch();
                
                if (!$setting || (int)$setting['is_open'] !== 1) {
                    $errorMessage = 'ระบบนี้ถูกปิดรับการส่งเอกสารชั่วคราวโดยผู้ดูแลระบบ';
                } else {
                    $deadlineTime = strtotime($setting['deadline_date']);
                    $submittedTiming = (time() > $deadlineTime) ? 'late' : 'on_time';
                    
                    $filePath = null;
                    $fileUploaded = false;
                    
                    // Process File Upload
                    if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
                        $fileTmpPath = $_FILES['file_upload']['tmp_name'];
                        $fileName = $_FILES['file_upload']['name'];
                        $fileSize = $_FILES['file_upload']['size'];
                        $fileType = $_FILES['file_upload']['type'];
                        
                        $fileNameCmps = explode(".", $fileName);
                        $fileExtension = strtolower(end($fileNameCmps));
                        
                        // PDF only
                        if ($fileExtension !== 'pdf') {
                            $errorMessage = 'ไม่อนุญาตให้อัปโหลดไฟล์รูปแบบอื่น อนุญาตเฉพาะไฟล์เอกสารรูปแบบ PDF เท่านั้น เพื่อง่ายต่อการตรวจสำหรับแอดมิน';
                        } elseif ($fileSize > 104857600) { // 100MB limit
                            $errorMessage = 'ขนาดไฟล์อัปโหลดเกิน 100 MB กรุณาตรวจสอบหรือลดขนาดไฟล์ก่อนอัปโหลด';
                        } else {
                            // Create directory: uploads/semester_id/teacher_id/course_id/system_type/
                            $uploadDir = dirname(__DIR__) . "/uploads/{$semester['id']}/{$teacherId}/{$postCourseId}/{$postSystemType}/";
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            // Clean filename
                            $newFileName = $postSystemType . '_' . time() . '.' . $fileExtension;
                            $destPath = $uploadDir . $newFileName;
                            
                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                $filePath = "uploads/{$semester['id']}/{$teacherId}/{$postCourseId}/{$postSystemType}/" . $newFileName;
                                $fileUploaded = true;
                            } else {
                                $errorMessage = 'เกิดข้อผิดพลาดในการย้ายไฟล์ไปยังโฟลเดอร์เซิร์ฟเวอร์';
                            }
                        }
                    }
                    
                    // Validation for having either a file or a Google Drive link / YouTube link
                    if (!$errorMessage) {
                        if (!$fileUploaded && empty($driveLink)) {
                            $errorMessage = ($postSystemType === 'teaching_materials')
                                ? 'กรุณาเลือกอัปโหลดไฟล์จากเครื่อง หรือใส่ลิงก์ Google Drive หรือลิงก์ YouTube อย่างใดอย่างหนึ่ง'
                                : 'กรุณาเลือกอัปโหลดไฟล์จากเครื่อง หรือใส่อัปโหลดผ่านลิงก์ Google Drive อย่างใดอย่างหนึ่ง';
                        } elseif (!empty($driveLink) && !filter_var($driveLink, FILTER_VALIDATE_URL)) {
                            $errorMessage = ($postSystemType === 'teaching_materials')
                                ? 'รูปแบบลิงก์ไม่ถูกต้อง (กรุณากรอกในรูปแบบ URL)'
                                : 'รูปแบบลิงก์ Google Drive ไม่ถูกต้อง (กรุณากรอกในรูปแบบ URL)';
                        } else {
                            // Check if a submission already exists for this course and system type in the active semester
                            $stmtCheck = $pdo->prepare("SELECT id, file_path FROM submissions WHERE course_id = :course_id AND system_type = :system_type LIMIT 1");
                            $stmtCheck->execute([
                                'course_id' => $postCourseId,
                                'system_type' => $postSystemType
                            ]);
                            $existingSubmission = $stmtCheck->fetch();
                            
                            if ($existingSubmission) {
                                // If a new file was uploaded, delete the old file on disk
                                if ($fileUploaded) {
                                    if (!empty($existingSubmission['file_path'])) {
                                        $oldFile = dirname(__DIR__) . '/' . $existingSubmission['file_path'];
                                        if (file_exists($oldFile)) {
                                            @unlink($oldFile);
                                        }
                                    }
                                } else {
                                    // If no new file was uploaded, keep the old file path (if any)
                                    $filePath = $existingSubmission['file_path'];
                                }
                                
                                // Update existing entry
                                $stmt = $pdo->prepare("
                                    UPDATE submissions
                                    SET file_path = :file_path, drive_link = :drive_link, submission_timing = :submission_timing, status = 'pending', feedback = NULL, submitted_at = CURRENT_TIMESTAMP
                                    WHERE id = :id
                                ");
                                $stmt->execute([
                                    'file_path' => $filePath,
                                    'drive_link' => !empty($driveLink) ? $driveLink : null,
                                    'submission_timing' => $submittedTiming,
                                    'id' => (int)$existingSubmission['id']
                                ]);
                            } else {
                                // No existing submission: insert a new one
                                $stmt = $pdo->prepare("
                                    INSERT INTO submissions (course_id, system_type, file_path, drive_link, submission_timing, status)
                                    VALUES (:course_id, :system_type, :file_path, :drive_link, :submission_timing, 'pending')
                                ");
                                $stmt->execute([
                                    'course_id' => $postCourseId,
                                    'system_type' => $postSystemType,
                                    'file_path' => $filePath,
                                    'drive_link' => !empty($driveLink) ? $driveLink : null,
                                    'submission_timing' => $submittedTiming
                                ]);
                            }
                            
                            $_SESSION['success_flash'] = 'ยื่นส่งเอกสารของคุณเรียบร้อยแล้ว ระบบกำลังรอการตรวจประเมินจากผู้ดูแลระบบ';
                            redirect_to('dashboard.php');
                        }
                    }
                }
            }
        }
    }
}

// Label display helper
$systemTypeLabels = [
    'course_syllabus' => 'โครงการสอน (Syllabus)',
    'lesson_plan' => 'แผนการจัดการเรียนรู้ (Lesson Plan)',
    'teaching_materials' => 'สื่อการเรียนการสอน (Teaching Materials)'
];
$branding = get_branding_settings();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ส่งภารกิจวิชาการ | <?= htmlspecialchars($branding['system_name']); ?></title>
    <?php if (!empty($branding['logo_path'])): ?>
        <link rel="icon" type="image/png" href="../<?= htmlspecialchars($branding['logo_path']); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Outfit:wght@400;600;850&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: {
                            650: '#0e7490',
                            750: '#0f766e',
                        }
                    },
                    fontFamily: {
                        thai: ['"IBM Plex Sans Thai"', 'sans-serif'],
                        outfit: ['"Outfit"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'IBM Plex Sans Thai', 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-thai min-h-screen flex flex-col relative overflow-x-hidden">

<header class="w-full sticky top-0 z-50 bg-gradient-to-r from-indigo-50/80 via-white/95 to-teal-50/80 backdrop-blur-md border-b border-slate-200/85 shadow-sm">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <a href="dashboard.php" class="flex items-center gap-3 group no-underline">
                <?php if (!empty($branding['logo_path'])): ?>
                    <img src="../<?= htmlspecialchars($branding['logo_path']); ?>" class="w-10 h-10 object-contain rounded-xl shadow-md group-hover:scale-105 transition-transform duration-200">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white font-extrabold text-xs flex items-center justify-center tracking-wider">
                        <?= htmlspecialchars($branding['logo_text']); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="text-base font-black text-slate-800 tracking-wide leading-tight"><?= htmlspecialchars($branding['system_name']); ?></div>
                    <div class="text-[10px] text-slate-400 font-medium uppercase tracking-wider mt-0.5"><?= htmlspecialchars($branding['college_name']); ?></div>
                </div>
            </a>
            
            <a href="dashboard.php"
               class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 hover:text-slate-800 text-xs font-semibold rounded-xl transition">
                กลับหน้าแดชบอร์ด
            </a>
        </div>
    </div>
</header>

<main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8">
        <h1 class="text-xl sm:text-2xl font-black text-slate-800">ยื่นส่งเอกสารงานวิชาการ</h1>
        <p class="text-xs text-slate-400 font-medium mt-1">
            ภาคเรียนที่ <span class="text-indigo-650 font-bold"><?= e($semester['semester_name']); ?></span> &middot; กรุณากรอกและส่งไฟล์ที่ถูกต้องเพื่อความรวดเร็วในการพิจารณาตรวจสอบ
        </p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 text-xs sm:text-sm font-bold mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span><?= e($errorMessage); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-[32px] p-6 sm:p-10 shadow-sm">
        
        <form method="get" action="submit.php" class="grid gap-6 sm:grid-cols-2 mb-8 pb-8 border-b border-slate-100">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider" for="course_select">1. เลือกรายวิชา</label>
                    <button type="button" onclick="openManageCoursesModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 hover:bg-teal-600 border border-teal-200 hover:border-teal-600 text-teal-700 hover:text-white text-xs font-bold rounded-xl transition-all duration-200 shadow-sm active:scale-[0.97]">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                        <span>จัดการรายวิชา</span>
                    </button>
                </div>
                <select name="course_id" id="course_select" onchange="this.form.submit()" 
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-700 focus:bg-white transition">
                    <option value="0">-- กรุณาเลือกรายวิชา --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id']; ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : ''; ?>>
                            [<?= e($c['course_code']); ?>] <?= e($c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2" for="type_select">2. เลือกประเภทเอกสารที่ต้องการส่ง</label>
                <select name="system_type" id="type_select" onchange="this.form.submit()"
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-700 focus:bg-white transition">
                    <option value="">-- กรุณาเลือกประเภทเอกสาร --</option>
                    <?php foreach ($systemTypeLabels as $typeKey => $typeLabel): ?>
                        <option value="<?= $typeKey; ?>" <?= $selectedSystemType === $typeKey ? 'selected' : ''; ?>>
                            <?= $typeLabel; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($selectedCourseId && $selectedSystemType): ?>
            
            <!-- Real-time Deadline Box -->
            <div class="mb-8">
                <?php if ($systemSetting): ?>
                    <?php if ($isOpen): ?>
                        <?php if ($isLate): ?>
                            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-amber-800">
                                <div class="flex items-start gap-3">
                                    <span class="p-1 rounded-lg bg-amber-100 text-amber-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                    <div>
                                        <h4 class="text-xs sm:text-sm font-black">เลยกำหนดระยะเวลาปิดระบบส่งงาน (ส่งล่าช้า)</h4>
                                        <p class="text-[11px] sm:text-xs text-amber-700 font-medium mt-1 leading-relaxed">
                                            ระบบยังอนุญาตให้ท่านยื่นส่งเอกสารได้ตามปกติหลังเวลาเดดไลน์ (<?= $deadlineStr; ?> น.) แต่ระบบจะทำการไฮไลต์บันทึกประวัติการส่งเป็น **"ส่งล่าช้า"** โดยอัตโนมัติเพื่อประกอบการพิจารณาครับ
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-teal-50/70 border border-teal-100 rounded-2xl p-5 text-teal-800">
                                <div class="flex items-start gap-3">
                                    <span class="p-1 rounded-lg bg-teal-100 text-teal-700">
                                        <svg class="w-5 h-5 text-teal-800" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                    <div>
                                        <h4 class="text-xs sm:text-sm font-black">ระบบเปิดอยู่ (ภายในระยะเวลากำหนดส่ง)</h4>
                                        <p class="text-[11px] sm:text-xs text-teal-700 font-medium mt-1 leading-relaxed">
                                            สามารถยื่นส่งเอกสารได้ตามเกณฑ์เวลาปกติ วันสิ้นสุดกำหนดส่งคือวันที่ <span class="font-black text-teal-900"><?= $deadlineStr; ?> น.</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 text-rose-800">
                            <div class="flex items-start gap-3">
                                <span class="p-1 rounded-lg bg-rose-100 text-rose-700">
                                    <svg class="w-5 h-5 text-rose-700" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                </span>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-black">ปิดระบบชั่วคราว</h4>
                                    <p class="text-[11px] sm:text-xs text-rose-600 font-medium mt-1 leading-relaxed">
                                        ผู้ดูแลระบบงานวิชาการได้ทำการปิดสวิตช์การส่งงานสำหรับประเภทนี้ชั่วคราว ท่านจะไม่สามารถกดปุ่มส่งเอกสารได้ในขณะนี้
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Upload Form -->
            <form method="post" action="submit.php?course_id=<?= $selectedCourseId; ?>&system_type=<?= $selectedSystemType; ?>" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= e(create_csrf_token()); ?>">
                <input type="hidden" name="course_id" value="<?= $selectedCourseId; ?>">
                <input type="hidden" name="system_type" value="<?= $selectedSystemType; ?>">

                <div class="grid gap-6 md:grid-cols-<?= $selectedSystemType === 'teaching_materials' ? '3' : '2'; ?>">
                    
                    <!-- File Upload Option -->
                    <div class="border border-slate-200 rounded-2xl p-6 bg-slate-50/50 hover:bg-slate-50 transition relative flex flex-col justify-between">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">ช่องทางที่ 1: อัปโหลดไฟล์จากเครื่องโดยตรง</label>
                            <input type="file" name="file_upload" id="file_upload" accept="application/pdf"
                                   class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 transition cursor-pointer"
                                   <?= !$isOpen ? 'disabled' : ''; ?>>
                            <p class="text-[10px] text-rose-500 font-bold mt-2 leading-relaxed">
                                ⚠️ รับเฉพาะไฟล์รูปแบบ PDF เท่านั้น (ขนาดไฟล์สูงสุดไม่เกิน 100 MB)
                            </p>
                        </div>
                        
                        <!-- iLovePDF Help Banner -->
                        <div class="mt-4 p-4 rounded-xl bg-red-50/60 border border-red-100 text-slate-700">
                            <div class="flex items-start gap-2">
                                <span class="p-1 rounded bg-red-100 text-red-700 text-[9px] font-black shrink-0 mt-0.5 uppercase">PDF Merge</span>
                                <div class="text-[10px] leading-relaxed">
                                    <span class="font-bold text-red-800">คำแนะนำ:</span> เพื่อความสะดวกและง่ายต่อการตรวจเอกสารของแอดมิน กรุณารวมเอกสารหลักฐานทั้งหมดของวิชานี้ให้เป็น <b>ไฟล์ PDF ไฟล์เดียวเท่านั้น</b> ก่อนส่ง
                                    <?php if ($isOpen): ?>
                                        <div class="mt-2.5">
                                            <a href="https://www.ilovepdf.com/merge_pdf" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 active:scale-[0.97] text-white text-[9px] font-black rounded-lg transition shadow-sm no-underline">
                                                <span>🔗 รวมไฟล์ PDF ออนไลน์ที่ iLovePDF ↗</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Drive Link Option -->
                    <div class="border border-slate-200 rounded-2xl p-6 bg-slate-50/50 hover:bg-slate-50 transition flex flex-col justify-between">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2" for="drive_link">ช่องทางที่ 2: แนบลิงก์ Google Drive</label>
                            <input type="url" name="drive_link" id="drive_link" placeholder="https://drive.google.com/..."
                                   value="<?= ($existingSubmission && $existingSubmission['drive_link'] && !get_youtube_id($existingSubmission['drive_link'])) ? e($existingSubmission['drive_link']) : ''; ?>"
                                   class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-700 transition"
                                   <?= !$isOpen ? 'disabled' : ''; ?>>
                            <p class="text-[10px] text-slate-400 font-medium mt-2 leading-relaxed">
                                กรุณาเปิดการแชร์ลิงก์ให้เป็น *"ทุกคนที่มีลิงก์มีสิทธิ์อ่าน"* เพื่อให้งานวิชาการสามารถเปิดตรวจได้
                            </p>
                        </div>
                    </div>

                    <!-- YouTube Link Option (Only for Teaching Materials) -->
                    <?php if ($selectedSystemType === 'teaching_materials'): ?>
                        <div class="border border-slate-200 rounded-2xl p-6 bg-slate-50/50 hover:bg-slate-50 transition flex flex-col justify-between">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2" for="youtube_link">ช่องทางที่ 3: แนบลิงก์วิดีโอ YouTube</label>
                                <input type="url" name="youtube_link" id="youtube_link" placeholder="https://www.youtube.com/watch?v=..."
                                       value="<?= ($existingSubmission && $existingSubmission['drive_link'] && get_youtube_id($existingSubmission['drive_link'])) ? e($existingSubmission['drive_link']) : ''; ?>"
                                       class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-700 transition"
                                       <?= !$isOpen ? 'disabled' : ''; ?>>
                                <p class="text-[10px] text-slate-400 font-medium mt-2 leading-relaxed">
                                    คุณครูสามารถแนบลิงก์สื่อการเรียนการสอนที่เป็นวิดีโอจาก YouTube (เช่น คลิปวิดีโอบันทึกการสอน หรือสื่อแนะนำวิชา)
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <div class="pt-4 flex items-center justify-end gap-3">
                    <a href="dashboard.php" class="px-5 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-500 text-xs font-bold rounded-xl transition">
                        ยกเลิก
                    </a>
                    <button type="submit" 
                            class="px-6 py-2.5 text-xs font-black text-white bg-slate-900 hover:bg-slate-800 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed rounded-xl transition shadow-md shadow-slate-900/10"
                            <?= !$isOpen ? 'disabled' : ''; ?>>
                        ยืนยันการส่งเอกสาร
                    </button>
                </div>

            </form>

        <?php else: ?>
            <div class="py-12 text-center text-slate-400 font-medium">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <span>กรุณาเลือกรายวิชาและประเภทของเอกสารด้านบนเพื่อทำการยื่นส่งงานครับ</span>
            </div>
        <?php endif; ?>

    </div>
</main>

<footer class="py-8 bg-white border-t border-slate-200 mt-16">
    <div class="max-w-4xl mx-auto px-4 text-center text-xs text-slate-400 font-medium">
        &copy; <?= date('Y'); ?> <?= htmlspecialchars($branding['college_name']); ?> &middot; ฝ่ายวิชาการ &middot; สงวนลิขสิทธิ์
    </div>
</footer>

<!-- Manage Courses Modal -->
<div id="manage_courses_modal" class="fixed inset-0 z-50 hidden bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-[32px] max-w-lg w-full p-6 sm:p-8 shadow-2xl relative max-h-[85vh] flex flex-col">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                <span>จัดการรายวิชาสอน</span>
            </h3>
            <button type="button" onclick="closeManageCoursesModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="overflow-y-auto py-4 flex-1 space-y-6">
            <!-- Add Course form -->
            <form method="post" action="submit.php?course_id=<?= $selectedCourseId; ?>&system_type=<?= urlencode($selectedSystemType); ?>" class="space-y-4 bg-slate-50 border border-slate-150 p-4 sm:p-5 rounded-2xl">
                <input type="hidden" name="csrf_token" value="<?= e(create_csrf_token()); ?>">
                <input type="hidden" name="action" value="add_course">
                <h4 class="text-xs font-black text-slate-500 uppercase tracking-wider">+ เพิ่มรายวิชาใหม่</h4>
                
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5" for="modal_course_code">รหัสวิชา</label>
                        <input type="text" name="course_code" id="modal_course_code" placeholder="เช่น 20001-2001"
                               class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-teal-700 transition" required>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5" for="modal_course_name">ชื่อรายวิชา</label>
                        <input type="text" name="course_name" id="modal_course_name" placeholder="เช่น คอมพิวเตอร์..."
                               class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-teal-700 transition" required>
                    </div>
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-4 py-2 text-xs font-black text-white bg-slate-900 hover:bg-slate-800 rounded-xl transition shadow-sm">
                        บันทึกรายวิชา
                    </button>
                </div>
            </form>
            
            <!-- Course List / View -->
            <div class="space-y-3">
                <h4 class="text-xs font-black text-slate-500 uppercase tracking-wider">รายวิชาที่สอนในภาคเรียนนี้ (<?= count($courses); ?> วิชา)</h4>
                <div class="divide-y divide-slate-100 max-h-[250px] overflow-y-auto border border-slate-100 rounded-2xl bg-white">
                    <?php if (count($courses) === 0): ?>
                        <p class="p-6 text-center text-xs text-slate-400 font-medium">ไม่พบวิชาสอนประจำภาคเรียนนี้</p>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <div class="p-3 sm:p-4 flex items-center justify-between hover:bg-slate-50/50 transition">
                                <div class="flex-1 min-w-0 pr-3">
                                    <span class="inline-block text-[10px] font-bold bg-slate-100 text-slate-650 px-2 py-0.5 rounded-lg mr-2"><?= e($c['course_code']); ?></span>
                                    <span class="text-xs font-bold text-slate-800 truncate block sm:inline-block sm:max-w-[240px] align-middle" title="<?= e($c['course_name']); ?>"><?= e($c['course_name']); ?></span>
                                </div>
                                <form method="post" action="submit.php?course_id=<?= $selectedCourseId; ?>&system_type=<?= urlencode($selectedSystemType); ?>" class="inline delete-course-form" data-course-code="<?= e($c['course_code']); ?>" data-course-name="<?= e($c['course_name']); ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(create_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete_course">
                                    <input type="hidden" name="course_id" value="<?= $c['id']; ?>">
                                    <button type="submit" class="p-2 text-slate-350 hover:text-rose-600 transition" title="ลบรายวิชา">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button type="button" onclick="closeManageCoursesModal()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                เสร็จสิ้น
            </button>
        </div>
    </div>
</div>

<script>
    function openManageCoursesModal() {
        document.getElementById('manage_courses_modal').classList.remove('hidden');
    }
    
    function closeManageCoursesModal() {
        document.getElementById('manage_courses_modal').classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Success Message popup
        <?php if ($successMessage): ?>
        Swal.fire({
            title: 'สำเร็จ!',
            text: <?= json_encode($successMessage); ?>,
            icon: 'success',
            confirmButtonColor: '#0f766e', // Teal 700
            confirmButtonText: 'ตกลง',
            customClass: {
                popup: 'rounded-3xl border border-slate-200 shadow-xl font-thai'
            }
        });
        <?php endif; ?>

        // 2. Error Message popup
        <?php if ($errorMessage): ?>
        Swal.fire({
            title: 'เกิดข้อผิดพลาด!',
            text: <?= json_encode($errorMessage); ?>,
            icon: 'error',
            confirmButtonColor: '#e11d48', // Rose 600
            confirmButtonText: 'ตกลง',
            customClass: {
                popup: 'rounded-3xl border border-slate-200 shadow-xl font-thai'
            }
        });
        <?php endif; ?>

        // 3. Intercept Course Deletion Form
        const deleteCourseForms = document.querySelectorAll('.delete-course-form');
        deleteCourseForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const code = this.dataset.courseCode || '';
                const name = this.dataset.courseName || 'รายวิชา';
                Swal.fire({
                    title: '⚠️ ยืนยันการลบรายวิชาสอน?',
                    html: `คุณครูแน่ใจหรือไม่ว่าต้องการลบวิชา <strong>${code} - ${name}</strong> ออกจากรายการสอนในภาคเรียนนี้?<br><br><span class="text-rose-600 font-bold">⚠️ ประวัติและไฟล์เอกสารการยื่นส่งทั้งหมดของวิชานี้จะถูกลบทิ้งอย่างถาวรทันที!</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e11d48', // Rose 600
                    cancelButtonColor: '#64748b',  // Slate 500
                    confirmButtonText: 'ใช่, ลบรายวิชาออก',
                    cancelButtonText: 'ยกเลิก',
                    customClass: {
                        popup: 'rounded-3xl border border-slate-200 shadow-xl font-thai'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            });
        });
    });
</script>

</body>
</html>
