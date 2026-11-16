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

$errorMessage = '';
$successMessage = (string)($_SESSION['success_flash']??'');
unset($_SESSION['success_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete_teacher') {
            $teacherIdToDelete = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
            if ($teacherIdToDelete > 0) {
                try {
                    // Check if teacher exists
                    $stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = :id AND role = 'teacher' LIMIT 1");
                    $stmt->execute(['id' => $teacherIdToDelete]);
                    $teacherToDelete = $stmt->fetch();
                    
                    if ($teacherToDelete) {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'teacher'");
                        $stmt->execute(['id' => $teacherIdToDelete]);
                        
                        $successMessage = "ลบข้อมูลคุณครู '{$teacherToDelete['fullname']}' และประวัติการจัดเรียนการสอนทั้งหมดเรียบร้อยแล้ว";
                    } else {
                        $errorMessage = "ไม่พบข้อมูลคุณครูรายนี้ในระบบ";
                    }
                } catch (Exception $e) {
                    $errorMessage = "เกิดข้อผิดพลาดในการลบครูผู้สอน: " . $e->getMessage();
                }
            }
        } elseif ($action === 'update_primary_dept') {
            $teacherIdToUpdate = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
            $newDept = trim((string)($_POST['department'] ?? ''));
            if ($teacherIdToUpdate > 0 && $newDept !== '') {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET department = :dept, updated_at = NOW() WHERE id = :id AND role = 'teacher'");
                    $stmt->execute(['dept' => $newDept, 'id' => $teacherIdToUpdate]);
                    $successMessage = 'อัปเดตแผนกวิชาหลักเรียบร้อยแล้ว';
                } catch (Exception $e) {
                    $errorMessage = 'เกิดข้อผิดพลาดในการอัปเดตแผนกวิชาหลัก: ' . $e->getMessage();
                }
            } else {
                $errorMessage = 'ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้อง';
            }
        }
    }
}

// 1. Get active semester
$semester = $pdo->query('SELECT id, semester_name FROM semesters WHERE is_active = 1 LIMIT 1')->fetch();
if (!$semester) {
    exit('ไม่พบภาคเรียนที่กำลังเปิดใช้งานในระบบ กรุณาติดต่อผู้ดูแลระบบ');
}

// 2. Fetch all teachers
$stmt = $pdo->prepare("SELECT id, username, fullname, department FROM users WHERE (role = 'teacher' OR EXISTS (SELECT 1 FROM courses own_courses WHERE own_courses.teacher_id=users.id)) AND status = 'active' ORDER BY fullname ASC");
$stmt->execute();
$teachers = $stmt->fetchAll();
if (pnp_portal_v2()) $teachers = pnp_portal_filter($GLOBALS['pnp_academic_context'], $teachers);

// 2.5 Query all assigned departments from portal DB for active teachers
$teacherDepartmentsMap = [];
$dbPortal = get_portal_db_connection();
if ($dbPortal) {
    try {
        $rows = $dbPortal->query("
            SELECT u.username, j.name AS dept_name
            FROM assignments a
            INNER JOIN jobs j ON j.id = a.job_id
            INNER JOIN users u ON u.id = a.personnel_id
            WHERE j.name LIKE 'แผนกวิชา%'
            ORDER BY j.name ASC
        ")->fetchAll();
        foreach ($rows as $row) {
            $teacherDepartmentsMap[$row['username']][] = $row['dept_name'];
        }
    } catch (Exception $e) {
        error_log('Portal DB fetch in overview.php failed: ' . $e->getMessage());
    }
}

// 3. For each teacher, calculate compliance statuses
$teacherData = [];
foreach ($teachers as $t) {
    $tId = (int)$t['id'];
    
    // Fetch courses assigned to this teacher in active semester
    $stmt = $pdo->prepare('SELECT id, course_code, course_name FROM courses WHERE teacher_id = :teacher_id AND semester_id = :semester_id');
    $stmt->execute(['teacher_id' => $tId, 'semester_id' => $semester['id']]);
    $teacherCourses = $stmt->fetchAll();
    $courseCount = count($teacherCourses);
    
    // Default statuses
    $syllabusStatus = 'missing'; // 'missing', 'pending', 'approved', 'rejected', 'incomplete'
    $planStatus = 'missing';
    $matStatus = 'missing';
    
    $syllabusIsLate = false;
    $planIsLate = false;
    $matIsLate = false;
    
    $syllabusSubIds = [];
    $planSubIds = [];
    $matSubIds = [];
    
    if ($courseCount > 0) {
        $cIds = array_map(fn($c) => (int)$c['id'], $teacherCourses);
        $inQuery = implode(',', $cIds);
        
        // Latest syllabus submissions
        $syllabuses = $pdo->query("
            SELECT s1.* FROM submissions s1
            INNER JOIN (
                SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'course_syllabus' AND course_id IN ($inQuery) GROUP BY course_id
            ) s2 ON s1.id = s2.max_id
        ")->fetchAll();
        
        // Latest lesson plan submissions
        $plans = $pdo->query("
            SELECT s1.* FROM submissions s1
            INNER JOIN (
                SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'lesson_plan' AND course_id IN ($inQuery) GROUP BY course_id
            ) s2 ON s1.id = s2.max_id
        ")->fetchAll();
        
        // Latest teaching materials submissions
        $mats = $pdo->query("
            SELECT s1.* FROM submissions s1
            INNER JOIN (
                SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'teaching_materials' AND course_id IN ($inQuery) GROUP BY course_id
            ) s2 ON s1.id = s2.max_id
        ")->fetchAll();
        
        // Map sub IDs for quick links
        foreach ($syllabuses as $s) { $syllabusSubIds[$s['course_id']] = $s; }
        foreach ($plans as $p) { $planSubIds[$p['course_id']] = $p; }
        foreach ($mats as $m) { $matSubIds[$m['course_id']] = $m; }
        
        // Calculate Syllabus compliance: Must submit for ALL courses
        $syllabusAppCount = 0;
        $syllabusPendCount = 0;
        $syllabusRejCount = 0;
        foreach ($syllabuses as $s) {
            if ($s['status'] === 'approved') $syllabusAppCount++;
            if ($s['status'] === 'pending') $syllabusPendCount++;
            if ($s['status'] === 'rejected') $syllabusRejCount++;
        }
        
        $syllabusIsLate = false;
        if ($syllabusAppCount === $courseCount) {
            $syllabusStatus = 'approved';
            foreach ($syllabuses as $s) {
                if ($s['status'] === 'approved' && $s['submission_timing'] === 'late') {
                    $syllabusIsLate = true;
                    break;
                }
            }
        } elseif ($syllabusPendCount > 0) {
            $syllabusStatus = 'pending';
            foreach ($syllabuses as $s) {
                if ($s['submission_timing'] === 'late') {
                    $syllabusIsLate = true;
                    break;
                }
            }
        } elseif ($syllabusRejCount > 0) {
            $syllabusStatus = 'rejected';
        } elseif (count($syllabuses) > 0) {
            $syllabusStatus = 'incomplete';
        }
        
        // Plan compliance: At least 1 course
        $planAppCount = 0;
        $planPendCount = 0;
        $planRejCount = 0;
        $planApprovedSubs = [];
        $planPendingSubs = [];
        foreach ($plans as $p) {
            if ($p['status'] === 'approved') {
                $planAppCount++;
                $planApprovedSubs[] = $p;
            }
            if ($p['status'] === 'pending') {
                $planPendCount++;
                $planPendingSubs[] = $p;
            }
            if ($p['status'] === 'rejected') $planRejCount++;
        }
        
        $planIsLate = false;
        if ($planAppCount >= 1) {
            $planStatus = 'approved';
            $allLate = true;
            foreach ($planApprovedSubs as $p) {
                if ($p['submission_timing'] !== 'late') {
                    $allLate = false;
                    break;
                }
            }
            $planIsLate = $allLate;
        } elseif ($planPendCount >= 1) {
            $planStatus = 'pending';
            $allLate = true;
            foreach ($planPendingSubs as $p) {
                if ($p['submission_timing'] !== 'late') {
                    $allLate = false;
                    break;
                }
            }
            $planIsLate = $allLate;
        } elseif ($planRejCount >= 1) {
            $planStatus = 'rejected';
        } elseif (count($plans) >= 1) {
            $planStatus = 'incomplete';
        }
        
        // Materials compliance: At least 1 course
        $matAppCount = 0;
        $matPendCount = 0;
        $matRejCount = 0;
        $matApprovedSubs = [];
        $matPendingSubs = [];
        foreach ($mats as $m) {
            if ($m['status'] === 'approved') {
                $matAppCount++;
                $matApprovedSubs[] = $m;
            }
            if ($m['status'] === 'pending') {
                $matPendCount++;
                $matPendingSubs[] = $m;
            }
            if ($m['status'] === 'rejected') $matRejCount++;
        }
        
        $matIsLate = false;
        if ($matAppCount >= 1) {
            $matStatus = 'approved';
            $allLate = true;
            foreach ($matApprovedSubs as $m) {
                if ($m['submission_timing'] !== 'late') {
                    $allLate = false;
                    break;
                }
            }
            $matIsLate = $allLate;
        } elseif ($matPendCount >= 1) {
            $matStatus = 'pending';
            $allLate = true;
            foreach ($matPendingSubs as $m) {
                if ($m['submission_timing'] !== 'late') {
                    $allLate = false;
                    break;
                }
            }
            $matIsLate = $allLate;
        } elseif ($matRejCount >= 1) {
            $matStatus = 'rejected';
        } elseif (count($mats) >= 1) {
            $matStatus = 'incomplete';
        }
    }
    
    // Calculate submitted and missing counts for sorting
    $submittedSystemsCount = 0;
    $missingSystemsCount = 0;
    
    if (in_array($syllabusStatus, ['approved', 'pending'])) {
        $submittedSystemsCount++;
    } else {
        $missingSystemsCount++;
    }
    
    if (in_array($planStatus, ['approved', 'pending'])) {
        $submittedSystemsCount++;
    } else {
        $missingSystemsCount++;
    }
    
    if (in_array($matStatus, ['approved', 'pending'])) {
        $submittedSystemsCount++;
    } else {
        $missingSystemsCount++;
    }
    
    $teacherData[] = [
        'teacher' => $t,
        'courses' => $teacherCourses,
        'course_count' => $courseCount,
        'syllabus_status' => $syllabusStatus,
        'syllabus_is_late' => $syllabusIsLate,
        'plan_status' => $planStatus,
        'plan_is_late' => $planIsLate,
        'mat_status' => $matStatus,
        'mat_is_late' => $matIsLate,
        'syllabuses' => $syllabusSubIds,
        'plans' => $planSubIds,
        'mats' => $matSubIds,
        'submitted_systems_count' => $submittedSystemsCount,
        'missing_systems_count' => $missingSystemsCount
    ];
}

// 3.5 Sorting logic for $teacherData
$sortBy = $_GET['sort_by'] ?? 'name';
$sortDir = $_GET['sort_dir'] ?? 'asc';

if (!in_array($sortBy, ['name', 'department', 'submitted', 'missing', 'courses'])) {
    $sortBy = 'name';
}
if (!in_array($sortDir, ['asc', 'desc'])) {
    $sortDir = ($sortBy === 'name') ? 'asc' : 'desc';
}

usort($teacherData, function($a, $b) use ($sortBy, $sortDir) {
    if ($sortBy === 'name') {
        $valA = $a['teacher']['fullname'];
        $valB = $b['teacher']['fullname'];
        $cmp = strcmp($valA, $valB);
        if ($cmp === 0) return 0;
        return ($sortDir === 'asc') ? $cmp : -$cmp;
    } elseif ($sortBy === 'department') {
        $valA = $a['teacher']['department'] ?? '';
        $valB = $b['teacher']['department'] ?? '';
        $cmp = strcmp($valA, $valB);
        if ($cmp === 0) return 0;
        return ($sortDir === 'asc') ? $cmp : -$cmp;
    } elseif ($sortBy === 'submitted') {
        $valA = $a['submitted_systems_count'];
        $valB = $b['submitted_systems_count'];
        if ($valA === $valB) {
            return strcmp($a['teacher']['fullname'], $b['teacher']['fullname']);
        }
        return ($sortDir === 'asc') ? ($valA <=> $valB) : ($valB <=> $valA);
    } elseif ($sortBy === 'missing') {
        $valA = $a['missing_systems_count'];
        $valB = $b['missing_systems_count'];
        if ($valA === $valB) {
            return strcmp($a['teacher']['fullname'], $b['teacher']['fullname']);
        }
        return ($sortDir === 'asc') ? ($valA <=> $valB) : ($valB <=> $valA);
    } elseif ($sortBy === 'courses') {
        $valA = $a['course_count'];
        $valB = $b['course_count'];
        if ($valA === $valB) {
            return strcmp($a['teacher']['fullname'], $b['teacher']['fullname']);
        }
        return ($sortDir === 'asc') ? ($valA <=> $valB) : ($valB <=> $valA);
    }
    return 0;
});

// 4. Calculate high-level admin metrics
$totalTeachersCount = count($teachers);
$fullyCompliantCount = 0;
$pendingReviewCount = 0;

foreach ($teacherData as $td) {
    if ($td['syllabus_status'] === 'approved' && $td['plan_status'] === 'approved' && $td['mat_status'] === 'approved') {
        $fullyCompliantCount++;
    }
    
    // Check if there are any pending reviews for this teacher's items
    foreach ($td['syllabuses'] as $s) { if ($s['status'] === 'pending') $pendingReviewCount++; }
    foreach ($td['plans'] as $p) { if ($p['status'] === 'pending') $pendingReviewCount++; }
    foreach ($td['mats'] as $m) { if ($m['status'] === 'pending') $pendingReviewCount++; }
}

// 5. Group and calculate Departmental metrics
$departmentData = [];
foreach ($teacherData as $td) {
    $dept = trim($td['teacher']['department'] ?? '');
    if ($dept === '') {
        $dept = 'ไม่ระบุแผนกวิชา';
    }
    
    if (!isset($departmentData[$dept])) {
        $departmentData[$dept] = [
            'name' => $dept,
            'total_teachers' => 0,
            'compliant_teachers' => 0,
            'total_courses' => 0,
            'syllabus_approved' => 0,
            'syllabus_pending' => 0,
            'syllabus_missing' => 0,
            'plan_approved_teachers' => 0,
            'materials_approved_teachers' => 0,
        ];
    }
    
    $departmentData[$dept]['total_teachers']++;
    $departmentData[$dept]['total_courses'] += $td['course_count'];
    
    // Check if fully compliant
    if ($td['syllabus_status'] === 'approved' && $td['plan_status'] === 'approved' && $td['mat_status'] === 'approved') {
        $departmentData[$dept]['compliant_teachers']++;
    }
    
    // Check plan approved
    if ($td['plan_status'] === 'approved') {
        $departmentData[$dept]['plan_approved_teachers']++;
    }
    
    // Check materials approved
    if ($td['mat_status'] === 'approved') {
        $departmentData[$dept]['materials_approved_teachers']++;
    }
    
    // Calculate syllabus approved courses
    foreach ($td['courses'] as $c) {
        $sub = $td['syllabuses'][$c['id']] ?? null;
        if ($sub && $sub['status'] === 'approved') {
            $departmentData[$dept]['syllabus_approved']++;
        } elseif ($sub && $sub['status'] === 'pending') {
            $departmentData[$dept]['syllabus_pending']++;
        } else {
            $departmentData[$dept]['syllabus_missing']++;
        }
    }
}
ksort($departmentData);

$viewTab = $_GET['view_tab'] ?? 'individual';
if ($viewTab !== 'individual' && $viewTab !== 'department') {
    $viewTab = 'individual';
}
$branding = get_branding_settings();

// Status badge helper for compliance overview
function renderComplianceBadge(string $status, string $labelType, bool $isLate = false): string
{
    switch ($status) {
        case 'approved':
            if ($isLate) {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">ส่งครบ (ไม่ตามกำหนดเวลา)</span>';
            }
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">ส่งครบถ้วน</span>';
        case 'pending':
            if ($isLate) {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>รอตรวจสอบ (ส่งช้า)</span>';
            }
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>รอตรวจสอบ</span>';
        case 'rejected':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">ต้องปรับปรุง</span>';
        case 'incomplete':
            $incompleteText = ($labelType === 'syllabus') ? 'ยังส่งไม่ครบ' : 'ไม่ถึงเกณฑ์';
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-orange-50 text-orange-700 border border-orange-200">' . $incompleteText . '</span>';
        case 'missing':
        default:
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-450 border border-slate-200">ค้างส่ง</span>';
    }
}
require dirname(__DIR__).'/includes/admin-overview-view.php';
