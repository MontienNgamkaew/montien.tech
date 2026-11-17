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
require __DIR__.'/includes/landing-view.php';
