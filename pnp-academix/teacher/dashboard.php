<?php
declare(strict_types=1);

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/includes/academix-ui.php';

// Force teacher role
require_teacher();

$teacherId = current_user_id();

// 1. Get active semester
$semester = $pdo->query('SELECT id, semester_name FROM semesters WHERE is_active = 1 LIMIT 1')->fetch();
if (!$semester) {
    $branding=get_branding_settings();ac_start('งานของฉัน',$branding);ac_header($branding);echo '<main class="ac-main" id="ac-main"><section class="ac-panel ac-empty"><h1>ยังไม่มีภาคเรียนที่เปิดใช้งาน</h1><p>กรุณาติดต่อผู้ดูแลเพื่อตรวจสอบภาคเรียน ข้อมูลรายวิชาและงานเดิมยังคงอยู่</p></section></main>';ac_end($branding);exit;
}

$errorMessage = '';
$successMessage = (string)($_SESSION['success_flash']??'');
unset($_SESSION['success_flash']);

// Handle Add/Delete Course actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $action = $_POST['action'] ?? '';
        
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
                    
                    $_SESSION['success_flash'] = "เพิ่มรายวิชา '{$courseCode} - {$courseName}' สำเร็จเรียบร้อยแล้ว!";
                    redirect_to('dashboard.php');
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
                
                $_SESSION['success_flash'] = "ลบรายวิชา '{$courseToDelete['course_code']} - {$courseToDelete['course_name']}' ออกจากบัญชีสอนเรียบร้อยแล้ว";
                redirect_to('dashboard.php');
            } else {
                $errorMessage = 'ไม่พบรายวิชาที่ต้องการลบ หรือคุณไม่มีสิทธิ์ลบรายวิชานี้';
            }
        }
    }
}

// 2. Fetch system settings (deadlines and open statuses)
$stmt = $pdo->prepare('SELECT system_type, deadline_date, is_open FROM system_settings WHERE semester_id = :semester_id');
$stmt->execute(['semester_id' => $semester['id']]);
$systemSettings = [];
foreach ($stmt->fetchAll() as $row) {
    $systemSettings[$row['system_type']] = $row;
}

// 3. Fetch all courses for this teacher and their latest submissions
$stmt = $pdo->prepare("
    SELECT c.*, 
           s_sub.status AS syllabus_status, s_sub.id AS syllabus_sub_id, s_sub.submission_timing AS syllabus_timing, s_sub.submitted_at AS syllabus_submitted_at, s_sub.feedback AS syllabus_feedback, s_sub.file_path AS syllabus_file_path, s_sub.drive_link AS syllabus_drive_link,
           s_plan.status AS plan_status, s_plan.id AS plan_sub_id, s_plan.submission_timing AS plan_timing, s_plan.submitted_at AS plan_submitted_at, s_plan.feedback AS plan_feedback, s_plan.file_path AS plan_file_path, s_plan.drive_link AS plan_drive_link,
           s_mat.status AS mat_status, s_mat.id AS mat_sub_id, s_mat.submission_timing AS mat_timing, s_mat.submitted_at AS mat_submitted_at, s_mat.feedback AS mat_feedback, s_mat.file_path AS mat_file_path, s_mat.drive_link AS mat_drive_link
    FROM courses c
    LEFT JOIN (
        SELECT s1.* FROM submissions s1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'course_syllabus' GROUP BY course_id
        ) s2 ON s1.id = s2.max_id
    ) s_sub ON c.id = s_sub.course_id
    LEFT JOIN (
        SELECT s1.* FROM submissions s1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'lesson_plan' GROUP BY course_id
        ) s2 ON s1.id = s2.max_id
    ) s_plan ON c.id = s_plan.course_id
    LEFT JOIN (
        SELECT s1.* FROM submissions s1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'teaching_materials' GROUP BY course_id
        ) s2 ON s1.id = s2.max_id
    ) s_mat ON c.id = s_mat.course_id
    WHERE c.teacher_id = :teacher_id AND c.semester_id = :semester_id
    ORDER BY c.course_code ASC
");
$stmt->execute([
    'teacher_id' => $teacherId,
    'semester_id' => $semester['id']
]);
$courses = $stmt->fetchAll();
$branding = get_branding_settings();

// 4. Calculate compliance stats
$totalCourses = count($courses);

$syllabusSubmitted = 0;
$syllabusApproved = 0;
$planSubmitted = 0;
$planApproved = 0;
$matSubmitted = 0;
$matApproved = 0;

foreach ($courses as $c) {
    if ($c['syllabus_status']) {
        $syllabusSubmitted++;
        if ($c['syllabus_status'] === 'approved') {
            $syllabusApproved++;
        }
    }
    if ($c['plan_status']) {
        $planSubmitted++;
        if ($c['plan_status'] === 'approved') {
            $planApproved++;
        }
    }
    if ($c['mat_status']) {
        $matSubmitted++;
        if ($c['mat_status'] === 'approved') {
            $matApproved++;
        }
    }
}

// Syllabus compliance rule: Any number of courses, but show overall progress
$syllabusPercent = $totalCourses > 0 ? round(($syllabusSubmitted / $totalCourses) * 100) : 0;

// Lesson Plan compliance rule: At least 1 course
$planCompliant = $planSubmitted >= 1;

// Teaching Materials compliance rule: At least 1 course
$matCompliant = $matSubmitted >= 1;

// สถานะเปิด/ปิดของแต่ละระบบ (ผู้ดูแลตั้งค่า) — ใช้ซ่อนปุ่มยื่นส่งเมื่อระบบปิด
$syllabusOpen = isset($systemSettings['course_syllabus']) && (int) $systemSettings['course_syllabus']['is_open'] === 1;
$planOpen     = isset($systemSettings['lesson_plan']) && (int) $systemSettings['lesson_plan']['is_open'] === 1;
$matOpen      = isset($systemSettings['teaching_materials']) && (int) $systemSettings['teaching_materials']['is_open'] === 1;

// ปุ่ม "ปิดรับยื่นส่ง" สำหรับการ์ดบนสุด (เมื่อระบบปิด)
function renderClosedSubmitButton(): string
{
    return '<span class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 bg-slate-100 text-slate-400 text-xs font-bold rounded-xl border border-slate-200 cursor-not-allowed select-none">'
        . '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 0h10.5a2.25 2.25 0 012.25 2.25v6a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25v-6a2.25 2.25 0 012.25-2.25z"/></svg>'
        . '<span>ปิดรับยื่นส่งชั่วคราว</span></span>';
}

// Helper to render badge
function renderStatusBadge(?string $status, ?string $timing = null): string
{
    if (!$status) {
        return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">ยังไม่ส่ง</span>';
    }
    $lateBadge = ($timing === 'late') ? ' <span class="text-[10px] text-amber-600 font-extrabold bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 ml-1 shrink-0">ส่งช้า</span>' : '';
    switch ($status) {
        case 'pending':
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200/70"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>รออนุมัติ</span>' . $lateBadge;
        case 'approved':
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">อนุมัติแล้ว</span>' . $lateBadge;
        case 'rejected':
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">ต้องแก้ไข</span>' . $lateBadge;
        default:
            return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">ยังไม่ส่ง</span>';
    }
}
require dirname(__DIR__).'/includes/teacher-dashboard-view.php';
