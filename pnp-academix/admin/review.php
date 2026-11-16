<?php
declare(strict_types=1);

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__).'/includes/admin-support.php';

// Force admin role
require_admin();

$submissionId = isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : 0;
if (!$submissionId) {
    redirect_to('overview.php');
}

// Fetch submission details with course and teacher information
$stmt = $pdo->prepare("
    SELECT s.*, c.course_code, c.course_name, c.teacher_id AS submission_teacher_id, u.fullname AS teacher_fullname, u.username AS teacher_username, sem.semester_name
    FROM submissions s
    INNER JOIN courses c ON s.course_id = c.id
    INNER JOIN users u ON c.teacher_id = u.id
    INNER JOIN semesters sem ON sem.id=c.semester_id
    WHERE s.id = :submission_id
    LIMIT 1
");
$stmt->execute(['submission_id' => $submissionId]);
$submission = $stmt->fetch();
if($submission&&pnp_portal_v2()&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&(int)$submission['submission_teacher_id']===(int)current_user_id())pnp_portal_fail(new PortalProblem(403,'อนุมัติหรือตรวจงานของตนเองไม่ได้ กรุณาให้ผู้ตรวจคนอื่นดำเนินการ'));
if ($submission && pnp_portal_v2()) pnp_portal_scope($GLOBALS['pnp_academic_context'], (string)$submission['teacher_username']);

if (!$submission) {
    exit('ไม่พบข้อมูลการยื่นส่งเอกสารนี้ในระบบ');
}

$errorMessage = '';
$successMessage = '';
$reviewConflict=false;

// Handle review feedback submission (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $action = $_POST['action'] ?? '';
        $feedback = is_string($_POST['feedback']??null)?trim($_POST['feedback']):'';
        
        if (!is_string($_POST['review_revision']??null)||!hash_equals(ac_review_revision($submission),$_POST['review_revision'])) {
            http_response_code(409);$reviewConflict=true;$errorMessage='เอกสารถูกเปลี่ยนหลังเปิดหน้า กรุณาตรวจ Preview ฉบับปัจจุบันอีกครั้งก่อนบันทึก';
        } elseif (($_POST['confirm_review']??'')!=='1') {
            $errorMessage='กรุณายืนยันว่าได้ตรวจเอกสารฉบับนี้แล้ว';
        } elseif ($action !== 'approve' && $action !== 'reject') {
            $errorMessage = 'คำดำเนินการไม่ถูกต้อง';
        } elseif ($action === 'reject' && $feedback === '') {
            $errorMessage = 'กรุณาระบุข้อเสนอแนะ/เหตุผลในการส่งกลับไปแก้ไขเพื่อเป็นแนวทางให้คุณครูครับ';
        } else {
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            
            $stmt = $pdo->prepare("
                UPDATE submissions
                SET status = :status, feedback = :feedback
                WHERE id = :submission_id AND status <=> :old_status AND feedback <=> :old_feedback AND file_path <=> :old_file AND drive_link <=> :old_link AND submitted_at <=> :old_time AND submission_timing <=> :old_timing AND updated_at <=> :old_updated
            ");
            $stmt->execute([
                'status' => $newStatus,
                'feedback' => $feedback !== '' ? $feedback : null,
                'submission_id' => $submissionId,
                'old_status'=>$submission['status'],'old_feedback'=>$submission['feedback'],'old_file'=>$submission['file_path'],'old_link'=>$submission['drive_link'],'old_time'=>$submission['submitted_at'],'old_timing'=>$submission['submission_timing'],'old_updated'=>$submission['updated_at']
            ]);
            if($stmt->rowCount()!==1){http_response_code(409);$reviewConflict=true;$errorMessage='ข้อมูลถูกเปลี่ยนหรือผลนี้ถูกบันทึกแล้ว กรุณาเปิดหน้าใหม่เพื่อตรวจอีกครั้ง';}
            else {
            
            // Set success flash and redirect back to overview
            $_SESSION['success_flash'] = 'บันทึกการประเมินเอกสารเรียบร้อยแล้ว';
            redirect_to('review_teacher.php?teacher_id='.(int)$submission['submission_teacher_id']);
            }
        }
    }
}

$branding=get_branding_settings();
require dirname(__DIR__).'/includes/admin-review-view.php';
