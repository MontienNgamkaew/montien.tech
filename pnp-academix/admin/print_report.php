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

// 1. Get active semester
$semester = $pdo->query('SELECT id, semester_name FROM semesters WHERE is_active = 1 LIMIT 1')->fetch();
if (!$semester) {
    exit('ไม่พบภาคเรียนที่กำลังเปิดใช้งานในระบบ กรุณาติดต่อผู้ดูแลระบบ');
}

// 2. Fetch all active teachers
$stmt = $pdo->prepare("SELECT id, username, fullname, department FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY fullname ASC");
$stmt->execute();
$teachers = $stmt->fetchAll();

// 2.2 Fetch deadlines from system settings
$stmt = $pdo->prepare('SELECT system_type, deadline_date, is_open FROM system_settings WHERE semester_id = :semester_id');
$stmt->execute(['semester_id' => $semester['id']]);
$systemSettings = [];
foreach ($stmt->fetchAll() as $row) {
    $systemSettings[$row['system_type']] = $row;
}

$syllabusDeadlinePassed = isset($systemSettings['course_syllabus']) && time() > strtotime($systemSettings['course_syllabus']['deadline_date']);
$planDeadlinePassed = isset($systemSettings['lesson_plan']) && time() > strtotime($systemSettings['lesson_plan']['deadline_date']);
$matDeadlinePassed = isset($systemSettings['teaching_materials']) && time() > strtotime($systemSettings['teaching_materials']['deadline_date']);

// 3. Define categorization structure
$categories = [
    'syllabus' => [
        'complete_ontime' => [],
        'incomplete_ontime' => [],
        'complete_late' => [],
        'incomplete_late' => [],
    ],
    'plan' => [
        'complete_ontime' => [],
        'incomplete_ontime' => [],
        'complete_late' => [],
        'incomplete_late' => [],
    ],
    'mat' => [
        'complete_ontime' => [],
        'incomplete_ontime' => [],
        'complete_late' => [],
        'incomplete_late' => [],
    ],
];

$totalTeachersCount = 0;
$fullyCompliantCount = 0;
$syllabusCompliantCount = 0;
$planCompliantCount = 0;
$matCompliantCount = 0;

foreach ($teachers as $t) {
    $tId = (int)$t['id'];
    
    // Fetch courses assigned to this teacher in active semester
    $stmt = $pdo->prepare('SELECT id, course_code, course_name FROM courses WHERE teacher_id = :teacher_id AND semester_id = :semester_id');
    $stmt->execute(['teacher_id' => $tId, 'semester_id' => $semester['id']]);
    $teacherCourses = $stmt->fetchAll();
    $courseCount = count($teacherCourses);
    
    // Only categorize teachers who have courses assigned
    if ($courseCount === 0) {
        continue;
    }
    
    $totalTeachersCount++;
    $cIds = array_map(fn($c) => (int)$c['id'], $teacherCourses);
    $inQuery = implode(',', $cIds);
    
    // -----------------------------------------------------------------
    // A. SYLLABUS CATEGORIZATION
    // -----------------------------------------------------------------
    $syllabuses = $pdo->query("
        SELECT s1.* FROM submissions s1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'course_syllabus' AND course_id IN ($inQuery) GROUP BY course_id
        ) s2 ON s1.id = s2.max_id
    ")->fetchAll();
    
    $sApprovedCount = 0;
    $sHasLate = false;
    $sHasMissing = count($syllabuses) < $courseCount;
    
    foreach ($syllabuses as $s) {
        if ($s['status'] === 'approved') {
            $sApprovedCount++;
        }
        if ($s['submission_timing'] === 'late') {
            $sHasLate = true;
        }
    }
    
    $sIsComplete = ($sApprovedCount === $courseCount);
    $sIsLate = $sIsComplete ? $sHasLate : ($sHasLate || ($sHasMissing && $syllabusDeadlinePassed));
    
    $sStatusText = "อนุมัติครบถ้วน ({$sApprovedCount}/{$courseCount} วิชา)";
    if (!$sIsComplete) {
        $missingCount = $courseCount - $sApprovedCount;
        $sStatusText = "ผ่านอนุมัติ {$sApprovedCount}/{$courseCount} วิชา (ค้างส่ง/ไม่ผ่าน {$missingCount} วิชา)";
    }
    
    $tInfoSyllabus = [
        'fullname' => $t['fullname'],
        'department' => $t['department'] ?: 'ไม่ระบุแผนกวิชา',
        'status_text' => $sStatusText
    ];
    
    if ($sIsComplete) {
        $syllabusCompliantCount++;
    }
    
    if ($sIsComplete && !$sIsLate) {
        $categories['syllabus']['complete_ontime'][] = $tInfoSyllabus;
    } elseif ($sIsComplete && $sIsLate) {
        $categories['syllabus']['complete_late'][] = $tInfoSyllabus;
    } elseif (!$sIsComplete && !$sIsLate) {
        $categories['syllabus']['incomplete_ontime'][] = $tInfoSyllabus;
    } else {
        $categories['syllabus']['incomplete_late'][] = $tInfoSyllabus;
    }
    
    // -----------------------------------------------------------------
    // B. LESSON PLAN CATEGORIZATION
    // -----------------------------------------------------------------
    $plans = $pdo->query("
        SELECT s1.* FROM submissions s1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'lesson_plan' AND course_id IN ($inQuery) GROUP BY course_id
        ) s2 ON s1.id = s2.max_id
    ")->fetchAll();
    
    $pApprovedCount = 0;
    $pTotalSubmissions = count($plans);
    $pApprovedPlans = [];
    $pAllSubmissionsLate = true;
    $pHasOnTimeSubmission = false;
    
    foreach ($plans as $p) {
        if ($p['status'] === 'approved') {
            $pApprovedCount++;
            $pApprovedPlans[] = $p;
        }
        if ($p['submission_timing'] === 'late') {
            // late
        } else {
            $pAllSubmissionsLate = false;
            $pHasOnTimeSubmission = true;
        }
    }
    
    $pIsComplete = ($pApprovedCount >= 1);
    
    $pIsLate = false;
    if ($pIsComplete) {
        $allApprovedLate = true;
        foreach ($pApprovedPlans as $ap) {
            if ($ap['submission_timing'] !== 'late') {
                $allApprovedLate = false;
                break;
            }
        }
        $pIsLate = $allApprovedLate;
    } else {
        if ($pTotalSubmissions > 0) {
            $pIsLate = $pAllSubmissionsLate;
        } else {
            $pIsLate = $planDeadlinePassed;
        }
    }
    
    $pStatusText = "อนุมัติแล้ว {$pApprovedCount}/{$courseCount} วิชา";
    if ($pApprovedCount === 0) {
        if ($pTotalSubmissions > 0) {
            $pStatusText = "ส่งตรวจสอบ {$pTotalSubmissions} วิชา (ยังไม่ผ่านเกณฑ์)";
        } else {
            $pStatusText = "ค้างส่งแผนการสอน";
        }
    }
    
    $tInfoPlan = [
        'fullname' => $t['fullname'],
        'department' => $t['department'] ?: 'ไม่ระบุแผนกวิชา',
        'status_text' => $pStatusText
    ];
    
    if ($pIsComplete) {
        $planCompliantCount++;
    }
    
    if ($pIsComplete && !$pIsLate) {
        $categories['plan']['complete_ontime'][] = $tInfoPlan;
    } elseif ($pIsComplete && $pIsLate) {
        $categories['plan']['complete_late'][] = $tInfoPlan;
    } elseif (!$pIsComplete && !$pIsLate) {
        $categories['plan']['incomplete_ontime'][] = $tInfoPlan;
    } else {
        $categories['plan']['incomplete_late'][] = $tInfoPlan;
    }
    
    // -----------------------------------------------------------------
    // C. TEACHING MATERIALS CATEGORIZATION
    // -----------------------------------------------------------------
    $mats = $pdo->query("
        SELECT s1.* FROM submissions s1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM submissions WHERE system_type = 'teaching_materials' AND course_id IN ($inQuery) GROUP BY course_id
        ) s2 ON s1.id = s2.max_id
    ")->fetchAll();
    
    $mApprovedCount = 0;
    $mTotalSubmissions = count($mats);
    $mApprovedMats = [];
    $mAllSubmissionsLate = true;
    $mHasOnTimeSubmission = false;
    
    foreach ($mats as $m) {
        if ($m['status'] === 'approved') {
            $mApprovedCount++;
            $mApprovedMats[] = $m;
        }
        if ($m['submission_timing'] === 'late') {
            // late
        } else {
            $mAllSubmissionsLate = false;
            $mHasOnTimeSubmission = true;
        }
    }
    
    $mIsComplete = ($mApprovedCount >= 1);
    
    $mIsLate = false;
    if ($mIsComplete) {
        $allApprovedLate = true;
        foreach ($mApprovedMats as $am) {
            if ($am['submission_timing'] !== 'late') {
                $allApprovedLate = false;
                break;
            }
        }
        $mIsLate = $allApprovedLate;
    } else {
        if ($mTotalSubmissions > 0) {
            $mIsLate = $mAllSubmissionsLate;
        } else {
            $mIsLate = $matDeadlinePassed;
        }
    }
    
    $mStatusText = "อนุมัติแล้ว {$mApprovedCount}/{$courseCount} วิชา";
    if ($mApprovedCount === 0) {
        if ($mTotalSubmissions > 0) {
            $mStatusText = "ส่งตรวจสอบ {$mTotalSubmissions} วิชา (ยังไม่ผ่านเกณฑ์)";
        } else {
            $mStatusText = "ค้างส่งสื่อการสอน";
        }
    }
    
    $tInfoMat = [
        'fullname' => $t['fullname'],
        'department' => $t['department'] ?: 'ไม่ระบุแผนกวิชา',
        'status_text' => $mStatusText
    ];
    
    if ($mIsComplete) {
        $matCompliantCount++;
    }
    
    if ($mIsComplete && !$mIsLate) {
        $categories['mat']['complete_ontime'][] = $tInfoMat;
    } elseif ($mIsComplete && $mIsLate) {
        $categories['mat']['complete_late'][] = $tInfoMat;
    } elseif (!$mIsComplete && !$mIsLate) {
        $categories['mat']['incomplete_ontime'][] = $tInfoMat;
    } else {
        $categories['mat']['incomplete_late'][] = $tInfoMat;
    }
    
    // Check full compliance across all three
    if ($sIsComplete && $pIsComplete && $mIsComplete) {
        $fullyCompliantCount++;
    }
}

// Convert numbers to Thai numerals helper
function toThaiNumerals($num): string
{
    $arabic = ['0','1','2','3','4','5','6','7','8','9'];
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    return str_replace($arabic, $thai, (string)$num);
}

// Date formatter helper
function thaiDate(string $timeStr): string
{
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $time = strtotime($timeStr);
    $day = date('j', $time);
    $month = $months[(int)date('n', $time)];
    $year = (int)date('Y', $time) + 543;
    return "{$day} {$month} พ.ศ. {$year}";
}

$branding = get_branding_settings();
$deputyDirectorName = $branding['deputy_director_name'] ?? '';
$directorName = $branding['director_name'] ?? '';

// Helper to render category table beautifully
function renderCategoryTable(string $title, array $teachersList) {
    ?>
    <div style="margin-top: 15px; margin-bottom: 20px; page-break-inside: avoid; break-inside: avoid;">
        <div style="font-size: 13pt; font-weight: bold; margin-bottom: 6px; color: #000; border-left: 4px solid #000; padding-left: 8px;">
            <?= htmlspecialchars($title); ?> (จำนวน <?= toThaiNumerals(count($teachersList)); ?> ราย)
        </div>
        <table class="report-table" style="width: 100%; border-collapse: collapse; font-size: 12pt; margin-bottom: 10px;">
            <thead>
                <tr style="background-color: #f9f9f9;">
                    <th style="width: 8%; border: 1px solid #000; padding: 6px; text-align: center; font-weight: bold;">ลำดับ</th>
                    <th style="width: 35%; border: 1px solid #000; padding: 6px; text-align: left; font-weight: bold;">ชื่อ-นามสกุลครูผู้สอน</th>
                    <th style="width: 32%; border: 1px solid #000; padding: 6px; text-align: left; font-weight: bold;">แผนกวิชา</th>
                    <th style="width: 25%; border: 1px solid #000; padding: 6px; text-align: center; font-weight: bold;">รายละเอียดผลงาน</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($teachersList) === 0): ?>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 10px; text-align: center; color: #777; font-style: italic;">
                            ไม่มีรายชื่อครูในกลุ่มนี้
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($teachersList as $t): ?>
                        <tr>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center;"><?= toThaiNumerals($i); ?>.</td>
                            <td style="border: 1px solid #000; padding: 6px; font-weight: bold;"><?= htmlspecialchars($t['fullname']); ?></td>
                            <td style="border: 1px solid #000; padding: 6px;"><?= htmlspecialchars($t['department']); ?></td>
                            <td style="border: 1px solid #000; padding: 6px; text-align: center; font-size: 11pt;"><?= htmlspecialchars($t['status_text']); ?></td>
                        </tr>
                    <?php $i++; endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>บันทึกข้อความ - รายงานความก้าวหน้าการจัดส่งภารกิจวิชาการ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 2.5cm 2cm 2cm 2.5cm; /* Left 2.5cm, Top 2.5cm, Right 2cm, Bottom 2cm */
        }
        body {
            font-family: 'TH Sarabun New', 'Sarabun', sans-serif;
            font-size: 15pt;
            line-height: 1.15;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
        }
        /* Top Garuda Area - Absolute Positioned */
        .garuda-container {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
        }
        .garuda-logo {
            width: 1.5cm;
            height: auto;
            display: block;
        }
        /* Page Title */
        .memo-title {
            font-size: 29pt;
            font-weight: bold;
            text-align: center;
            padding-top: 10px;
            margin-bottom: 25px;
            letter-spacing: 0.5px;
        }
        /* Metadata Header Fields */
        .metadata-section {
            border-bottom: 3px double #000;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .metadata-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 6px;
            font-size: 16pt;
        }
        .metadata-label {
            font-weight: bold;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .metadata-value {
            padding-left: 8px;
            flex-grow: 1;
            border-bottom: 1px dotted #000;
            padding-bottom: 1px;
            min-height: 24px;
        }
        .salutation {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 16pt;
        }
        .paragraph {
            text-indent: 2.5cm;
            text-align: justify;
            margin-bottom: 12px;
            text-justify: inter-word;
        }
        /* Printable Toolbar */
        .no-print {
            background-color: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 12px;
        }
        .print-btn {
            background-color: #0f766e;
            color: #fff;
            border: none;
            padding: 10px 22px;
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(15, 118, 110, 0.2);
            transition: all 0.2s;
        }
        .print-btn:hover {
            background-color: #115e59;
        }
        .back-btn {
            color: #475569;
            text-decoration: none;
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            font-weight: bold;
        }
        .signature-block {
            float: right;
            width: 320px;
            text-align: center;
            margin-top: 20px;
            margin-bottom: 25px;
        }
        .signature-line {
            margin-bottom: 6px;
        }
        .clearfix {
            clear: both;
        }
        .approvals-container {
            margin-top: 30px;
            border-top: 1px solid #000;
            padding-top: 20px;
        }
        .approval-grid {
            display: grid;
            grid-template-cols: 1fr 1fr;
            gap: 20px;
        }
        .approval-box {
            border: 1px solid #999;
            padding: 12px;
            border-radius: 8px;
            font-size: 12pt;
            line-height: 1.4;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .approval-header {
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
            margin-bottom: 10px;
            text-align: center;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12pt;
            margin-top: 10px;
        }
        .report-table th, .report-table td {
            border: 1px solid #000;
            padding: 6px 10px;
            vertical-align: middle;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                background-color: #fff;
                font-size: 15pt;
            }
            .container {
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Printable Toolbar (Hidden on actual print) -->
    <div class="no-print">
        <a href="overview.php" class="back-btn">&larr; ย้อนกลับไปแดชบอร์ด</a>
        <div>
            <button onclick="window.print()" class="print-btn">พิมพ์เอกสารรายงานสรุป (Ctrl+P)</button>
        </div>
    </div>

    <!-- Page 1: Memorandum (บันทึกข้อความ) -->
    <div class="garuda-container">
        <svg class="garuda-logo" viewBox="0 0 100 100" fill="#000" xmlns="http://www.w3.org/2000/svg">
            <path d="M50 0c-.5 0-1 .2-1.3.6C47.4 2 43.9 6.8 41 8.8c-1.3.9-2.2 2.3-2.6 3.8-.4 1.5-.2 3.1.5 4.5l1.6 3.1c-.8.8-1.5 1.7-2 2.7l-3.3-.9c-1.5-.4-3.1-.1-4.4.7-1.3.8-2.1 2.2-2.3 3.7-.4 2.8-.7 6.4.3 8.7.6 1.3 1.6 2.3 2.9 2.8l3.1 1.2c-.2.6-.3 1.2-.3 1.8 0 1.2.3 2.3.8 3.3L34 45.4c-.6.6-1.5 1-2.4 1-1 0-1.9-.4-2.5-1.1-1.3-1.5-2.7-3.1-4.2-4.5-.9-.8-2.1-1.2-3.3-1.1-1.2.1-2.3.8-2.9 1.8-1.3 2.2-2.7 5.1-3 7.8-.2 1.5.3 3 1.3 4.1l2.4 2.5c-.3 1-.5 2-.5 3 0 .7.1 1.4.3 2.1l-3 1.3c-1.4.6-2.4 1.8-2.7 3.3-.3 1.5.1 3 .1 4.5.3 2.8.9 5.6 2.3 8.1 1 1.8 3 2.8 5 2.5l3.8-.6c1 .8 2 1.5 3.2 2l-1.3 3.3c-.6 1.5-.4 3.1.5 4.4 1 1.3 2.5 2 4.1 1.8 2.8-.3 6.3-1 8.2-2.5 1.3-1 2-2.6 2-4.2v-3.2c1 .2 2 .3 3 .3 1.4 0 2.8-.2 4.1-.7l1.9 2.9c.9 1.3 2.3 2.1 3.9 2.1 1.6 0 3-.8 3.8-2.2 1.8-3 3.8-6.9 4.3-9.8.3-1.5-.1-3.1-1.1-4.3l-2.4-2.8c.8-1 1.4-2.2 1.8-3.4l3.1.5c1 .2 2-.1 2.8-.7.8-.6 1.3-1.5 1.4-2.5.3-2.8.7-6.3.3-8.6-.2-1.3-.9-2.4-2.1-2.9l-2.8-1.3c.4-.9.6-1.9.6-3s-.2-2.1-.6-3.1l2.8-.9c1.4-.4 2.5-1.5 2.9-2.9.4-1.5.1-3-.7-4.3-1.8-2.7-4.4-5.3-6.6-7-1.3-1-3-1.3-4.5-1l-3.3 1c-.3-1-.9-1.9-1.6-2.6l1.2-3.1c.6-1.5.4-3.1-.5-4.4-1-1.3-2.5-2-4.1-1.8-2.8.3-6.3 1-8.2 2.5-1.3 1-2 2.6-2 4.2v3.1c-.8-.2-1.7-.3-2.5-.3zm3.7 10.6c.5 0 .9.2 1.2.6.5.6.8 1.4.8 2.2 0 1.2-.7 2.3-1.8 2.7l-2 .8c-.2-.6-.5-1.2-.9-1.7l1.5-2.7c.3-.6.7-.9 1.2-.9zm-7.4 0c.5 0 .9.3 1.2.9l1.5 2.7c-.4.5-.7 1.1-.9 1.7l-2-.8C45 14 44.3 13 44.3 11.8c0-.8.3-1.6.8-2.2.3-.4.7-.6 1.2-.6z"/>
            <path d="M50 25c-5.5 0-10 4.5-10 10s4.5 10 10 10 10-4.5 10-10-4.5-10-10-10zm0 16c-3.3 0-6-2.7-6-6s2.7-6 6-6 6 2.7 6 6-2.7 6-6 6z"/>
            <path d="M50 49c-10.5 0-19 8.5-19 19 0 1.1.9 2 2 2h34c1.1 0 2-.9 2-2 0-10.5-8.5-19-19-19zm-14.8 17c1.3-6.2 6.8-11 13.3-11s12 4.8 13.3 11H35.2z"/>
            <path d="M50 74c-1.7 0-3 1.3-3 3v13c0 1.7 1.3 3 3 3s3-1.3 3-3V77c0-1.7-1.3-3-3-3z"/>
        </svg>
    </div>

    <div class="memo-title">บันทึกข้อความ</div>

    <div class="metadata-section">
        <div class="metadata-row">
            <span class="metadata-label" style="min-width: 90px;">ส่วนราชการ</span>
            <span class="metadata-value">งานพัฒนาหลักสูตรการเรียนการสอน ฝ่ายวิชาการ <?= htmlspecialchars($branding['college_name']); ?></span>
        </div>
        <div class="metadata-row" style="display: flex; justify-content: space-between;">
            <div style="width: 45%; display: flex; align-items: flex-end;">
                <span class="metadata-label" style="min-width: 30px;">ที่</span>
                <span class="metadata-value" style="display: flex; justify-content: space-between;">
                    <span>&nbsp;</span>
                    <span>/<?= toThaiNumerals(date('Y') + 543); ?></span>
                </span>
            </div>
            <div style="width: 50%; display: flex; align-items: flex-end;">
                <span class="metadata-label" style="min-width: 45px;">วันที่</span>
                <span class="metadata-value"><?= toThaiNumerals(thaiDate(date('Y-m-d'))); ?></span>
            </div>
        </div>
        <div class="metadata-row">
            <span class="metadata-label" style="min-width: 50px;">เรื่อง</span>
            <span class="metadata-value">รายงานสรุปความพร้อมการจัดส่งเอกสารและภารกิจการจัดการเรียนการสอน ภาคเรียนที่ <?= toThaiNumerals($semester['semester_name']); ?></span>
        </div>
    </div>

    <div class="salutation">เรียน &nbsp;&nbsp;ผู้อำนวยการ<?= htmlspecialchars($branding['college_name']); ?></div>

    <div class="paragraph">
        ด้วย งานพัฒนาหลักสูตรการเรียนการสอน ฝ่ายวิชาการ ได้ดำเนินการติดตามความพร้อมของเอกสารและการจัดส่งภารกิจบริหารจัดการเรียนการสอนของครูผู้สอน ประจำภาคเรียนที่ <?= toThaiNumerals($semester['semester_name']); ?> ประกอบด้วย (๑) โครงการสอน (Syllabus) (๒) แผนการจัดการเรียนรู้ (Lesson Plan) และ (๓) สื่อการจัดการเรียนรู้ เพื่อตรวจประเมินความสมบูรณ์และเกณฑ์กำหนดเวลาในการจัดส่งผลงานของสถานศึกษา
    </div>
    <div class="paragraph">
        บัดนี้ ฝ่ายวิชาการได้ดำเนินการรวบรวมและวิเคราะห์ผลสัมฤทธิ์การจัดส่งเอกสารของคณะครูในระบบวิชาการเป็นที่เรียบร้อยแล้ว โดยมีครูผู้สอนที่ได้รับมอบหมายจัดสอนทั้งสิ้นจำนวน <strong><?= toThaiNumerals($totalTeachersCount); ?></strong> ราย มีครูที่สามารถจัดส่งเอกสารผ่านเกณฑ์การประเมินอนุมัติครบถ้วนทั้ง ๓ ด้าน จำนวน <strong><?= toThaiNumerals($fullyCompliantCount); ?></strong> ราย รายละเอียดผลการจัดส่งเอกสารจำแนกตามประเภทและเกณฑ์กำหนดเวลา ปรากฏตามตารางสรุปผลงานในเอกสารแนบท้ายบันทึกข้อความนี้
    </div>
    <div class="paragraph">
        จึงเรียนมาเพื่อโปรดทราบและพิจารณา
    </div>

    <!-- Reporter Signature Area -->
    <div class="signature-block">
        <div class="signature-line" style="margin-bottom: 6px;">ลงชื่อ ..................................................... ผู้รายงาน</div>
        <div>( <strong><?= e(current_user_fullname()); ?></strong> )</div>
        <div style="margin-top: 4px;">เจ้าหน้าที่งานพัฒนาหลักสูตรการเรียนการสอน</div>
    </div>
    
    <div class="clearfix"></div>

    <!-- Approvals Block on Page 1 -->
    <div class="approvals-container">
        <div class="approval-grid">
            <!-- Deputy Director Box -->
            <div class="approval-box">
                <div class="approval-header">๑. ความเห็นของรองผู้อำนวยการฝ่ายวิชาการ</div>
                <div style="margin-bottom: 20px;">
                    [ &nbsp; ] ตรวจสอบแล้ว เห็นควรทราบและอนุมัติ<br>
                    [ &nbsp; ] อื่นๆ ............................................................................
                </div>
                <div style="text-align: center; margin-top: 25px;">
                    ลงชื่อ ....................................................................<br>
                    ( <?= htmlspecialchars($deputyDirectorName ?: '....................................................................'); ?> )<br>
                    ตำแหน่ง รองผู้อำนวยการฝ่ายวิชาการ<br>
                    วันที่ ...... / ................ / ...........
                </div>
            </div>

            <!-- Director Box -->
            <div class="approval-box">
                <div class="approval-header">๒. ผลการพิจารณาของผู้อำนวยการ</div>
                <div style="margin-bottom: 20px;">
                    [ &nbsp; ] ทราบและอนุมัติ<br>
                    [ &nbsp; ] อื่นๆ ............................................................................
                </div>
                <div style="text-align: center; margin-top: 25px;">
                    ลงชื่อ ....................................................................<br>
                    ( <?= htmlspecialchars($directorName ?: '....................................................................'); ?> )<br>
                    ผู้อำนวยการ<?= htmlspecialchars($branding['college_name']); ?><br>
                    วันที่ ...... / ................ / ...........
                </div>
            </div>
        </div>
    </div>

    <!-- Page Break for Annex -->
    <div class="page-break" style="page-break-before: always; margin-top: 30px;"></div>

    <div style="font-size: 16pt; font-weight: bold; text-align: center; margin-bottom: 5px;">เอกสารแนบท้ายบันทึกข้อความ: รายละเอียดสถานะการส่งภารกิจแยกตามเกณฑ์</div>
    <div style="font-size: 14pt; text-align: center; margin-bottom: 25px;">ฝ่ายวิชาการ <?= htmlspecialchars($branding['college_name']); ?> &middot; ภาคเรียนที่ <?= toThaiNumerals($semester['semester_name']); ?></div>

    <!-- SECTION 1: SYLLABUS -->
    <div style="margin-top: 30px; border-bottom: 2px solid #000; padding-bottom: 5px; page-break-inside: avoid; break-inside: avoid;">
        <span style="font-size: 15pt; font-weight: bold; color: #000;">๑. รายงานสรุปการส่งโครงการสอน (Syllabus)</span>
    </div>
    <?php
    renderCategoryTable('ตารางที่ ๑.๑: รายชื่อครูที่ส่งครบตามเงื่อนไข (อนุมัติครบทุกวิชาสอน) และตรงตามเวลา', $categories['syllabus']['complete_ontime']);
    renderCategoryTable('ตารางที่ ๑.๒: รายชื่อครูที่ส่งไม่ครบตามเงื่อนไข (ค้างส่ง/รออนุมัติบางส่วน) แต่ตรงตามเวลา', $categories['syllabus']['incomplete_ontime']);
    renderCategoryTable('ตารางที่ ๑.๓: รายชื่อครูที่ส่งครบตามเงื่อนไข (อนุมัติครบทุกวิชาสอน) แต่ไม่ตรงตามเวลา', $categories['syllabus']['complete_late']);
    renderCategoryTable('ตารางที่ ๑.๔: รายชื่อครูที่ส่งไม่ครบตามเงื่อนไข (ค้างส่ง/รออนุมัติบางส่วน) และไม่ตรงตามเวลา', $categories['syllabus']['incomplete_late']);
    ?>

    <!-- SECTION 2: LESSON PLAN -->
    <div class="page-break" style="page-break-before: always; margin-top: 30px;"></div>
    <div style="border-bottom: 2px solid #000; padding-bottom: 5px; page-break-inside: avoid; break-inside: avoid;">
        <span style="font-size: 15pt; font-weight: bold; color: #000;">๒. รายงานสรุปการส่งแผนการจัดการเรียนรู้ (Lesson Plan)</span>
    </div>
    <?php
    renderCategoryTable('ตารางที่ ๒.๑: รายชื่อครูที่ส่งครบตามเงื่อนไข (อนุมัติอย่างน้อย ๑ วิชา) และตรงตามเวลา', $categories['plan']['complete_ontime']);
    renderCategoryTable('ตารางที่ ๒.๒: รายชื่อครูที่ส่งไม่ครบตามเงื่อนไข (ค้างส่งแผนการสอน) แต่ตรงตามเวลา', $categories['plan']['incomplete_ontime']);
    renderCategoryTable('ตารางที่ ๒.๓: รายชื่อครูที่ส่งครบตามเงื่อนไข (อนุมัติอย่างน้อย ๑ วิชา) แต่ไม่ตรงตามเวลา', $categories['plan']['complete_late']);
    renderCategoryTable('ตารางที่ ๒.๔: รายชื่อครูที่ส่งไม่ครบตามเงื่อนไข (ค้างส่งแผนการสอน) และไม่ตรงตามเวลา', $categories['plan']['incomplete_late']);
    ?>

    <!-- SECTION 3: TEACHING MATERIALS -->
    <div class="page-break" style="page-break-before: always; margin-top: 30px;"></div>
    <div style="border-bottom: 2px solid #000; padding-bottom: 5px; page-break-inside: avoid; break-inside: avoid;">
        <span style="font-size: 15pt; font-weight: bold; color: #000;">๓. รายงานสรุปการส่งสื่อการเรียนการสอน (Teaching Materials)</span>
    </div>
    <?php
    renderCategoryTable('ตารางที่ ๓.๑: รายชื่อครูที่ส่งครบตามเงื่อนไข (อนุมัติอย่างน้อย ๑ วิชา) และตรงตามเวลา', $categories['mat']['complete_ontime']);
    renderCategoryTable('ตารางที่ ๓.๒: รายชื่อครูที่ส่งไม่ครบตามเงื่อนไข (ค้างส่งสื่อการสอน) แต่ตรงตามเวลา', $categories['mat']['incomplete_ontime']);
    renderCategoryTable('ตารางที่ ๓.๓: รายชื่อครูที่ส่งครบตามเงื่อนไข (อนุมัติอย่างน้อย ๑ วิชา) แต่ไม่ตรงตามเวลา', $categories['mat']['complete_late']);
    renderCategoryTable('ตารางที่ ๓.๔: รายชื่อครูที่ส่งไม่ครบตามเงื่อนไข (ค้างส่งสื่อการสอน) และไม่ตรงตามเวลา', $categories['mat']['incomplete_late']);
    ?>

</div>

</body>
</html>
