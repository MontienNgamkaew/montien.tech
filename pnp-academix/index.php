<?php
declare(strict_types=1);

// Prevent browser/server caching of dynamic page content
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
if (pnp_portal_v2()) require_login();

$isLoggedIn = is_logged_in();
$dashboardUrl = '#';
if ($isLoggedIn) {
    $dashboardUrl = (current_user_role() === 'admin') ? 'admin/overview.php' : 'teacher/dashboard.php';
}
$portalUrl = get_portal_url();
$loginUrl = $portalUrl;

$semester = $pdo->query('SELECT id, semester_name FROM semesters WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
$systemSettings = [];
if ($semester) {
    $stmt = $pdo->prepare('SELECT system_type, deadline_date, is_open FROM system_settings WHERE semester_id = :semester_id');
    $stmt->execute(['semester_id' => $semester['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $systemSettings[$row['system_type']] = $row;
    }
}
$branding = get_branding_settings();

require_once __DIR__.'/includes/academix-ui.php';
ac_start('ระบบบริหารงานวิชาการ',$branding);ac_header($branding,'home');
?>
<main class="ac-main" id="ac-main">
<div class="ac-page-heading"><div><p class="ac-eyebrow">Academic management</p><h1>ระบบบริหารงานวิชาการ</h1><p>ส่งเอกสาร ติดตามผล และเปิดดูงานของคุณในพื้นที่เดียว</p></div><span class="ac-term">ภาคเรียน <?= ac_h($semester['semester_name']??'ยังไม่เปิดภาคเรียน') ?></span></div>
<section class="ac-summary-grid">
<?php foreach(AC_DOCUMENT_TYPES as $key=>$type): $setting=$systemSettings[$key]??null; ?>
<article class="ac-panel ac-summary"><span class="ac-type-number"><?= $type['number'] ?></span><h2><?= $type['title'] ?></h2><p><?= $type['rule'] ?></p><span class="ac-service <?= !empty($setting['is_open'])?'open':'closed' ?>"><?= !empty($setting['is_open'])?'เปิดรับงาน':'ปิดรับงาน' ?></span><p>กำหนดส่ง <?= ac_h(ac_date($setting['deadline_date']??null)) ?></p></article>
<?php endforeach; ?>
</section><section class="ac-panel ac-summary"><h2>พื้นที่ทำงานของคุณ</h2><p>เลือกดูสถานะงานทั้ง 3 ประเภท แยกตามรายวิชา พร้อมผลการตรวจและข้อเสนอแนะ</p><a class="ac-button primary" href="teacher/dashboard.php">ดูสถานะงานของฉัน →</a>
<?php if(pnp_portal_v2()?pnp_portal_can('pnp-academic','view','admin'):current_user_role()==='admin'): ?><a class="ac-button" href="admin/overview.php">งานดูแลระบบ →</a><?php endif; ?>
</section></main><?php ac_end($branding); ?>
