<?php
declare(strict_types=1);

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';

// Force admin role
require_admin();

$teacherId = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : 0;
if (!$teacherId) {
    redirect_to('overview.php');
}

// 1. Get active semester
$semester = $pdo->query('SELECT id, semester_name FROM semesters WHERE is_active = 1 LIMIT 1')->fetch();
if (!$semester) {
    exit('ไม่พบภาคเรียนที่กำลังเปิดใช้งานในระบบ');
}

// 2. Fetch teacher details
$stmt = $pdo->prepare("SELECT id, username, fullname, department FROM users WHERE id = :teacher_id AND (role = 'teacher' OR EXISTS (SELECT 1 FROM courses own_courses WHERE own_courses.teacher_id=users.id)) LIMIT 1");
$stmt->execute(['teacher_id' => $teacherId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    exit('ไม่พบข้อมูลคุณครูท่านนี้ในระบบวิชาการ');
}

// 3. Fetch courses assigned to this teacher in the active semester, with their submissions
$stmt = $pdo->prepare("
    SELECT c.*, 
           s_sub.status AS syllabus_status, s_sub.id AS syllabus_sub_id, s_sub.submission_timing AS syllabus_timing, s_sub.submitted_at AS syllabus_submitted_at, s_sub.feedback AS syllabus_feedback,
           s_plan.status AS plan_status, s_plan.id AS plan_sub_id, s_plan.submission_timing AS plan_timing, s_plan.submitted_at AS plan_submitted_at, s_plan.feedback AS plan_feedback,
           s_mat.status AS mat_status, s_mat.id AS mat_sub_id, s_mat.submission_timing AS mat_timing, s_mat.submitted_at AS mat_submitted_at, s_mat.feedback AS mat_feedback
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

$branding=get_branding_settings();
require dirname(__DIR__).'/includes/admin-teacher-view.php';
