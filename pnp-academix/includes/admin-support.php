<?php
declare(strict_types=1);
require_once __DIR__.'/academix-ui.php';
function ac_submission_counts(array $submissions,int $courses):array{
 $counts=['submitted'=>count($submissions),'missing'=>max(0,$courses-count($submissions)),'pending'=>0,'approved'=>0,'rejected'=>0,'late'=>0];
 foreach($submissions as $s){if(isset($counts[$s['status']]))$counts[$s['status']]++;if(($s['submission_timing']??null)==='late')$counts['late']++;}return $counts;
}
function ac_review_revision(array $s):string{
 $values=[];foreach(['id','course_id','system_type','status','feedback','file_path','drive_link','submitted_at','submission_timing','updated_at'] as $key)$values[$key]=$s[$key]??null;
 return hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function ac_admin_navigation():void{
 echo '<nav class="ac-admin-nav" aria-label="เมนูผู้ดูแล"><a href="overview.php">ภาพรวมการส่งงาน</a><a href="history.php">ประวัติย้อนหลัง</a>';
 if(!pnp_portal_v2()||pnp_portal_can('pnp-academic','export','admin'))echo '<a href="print_report.php">พิมพ์รายงาน</a>';
 $all=!pnp_portal_v2()||($GLOBALS['pnp_academic_context']['access']['scope']??'')==='all';
 if($all&&(!pnp_portal_v2()||pnp_portal_can('pnp-academic','manage_settings','admin')))echo '<a href="settings.php">ตั้งค่าการรับงาน</a>';
 if($all&&(!pnp_portal_v2()||pnp_portal_can('pnp-academic','manage_users','admin')))echo '<a href="import.php">นำเข้าข้อมูล</a>';
 echo '</nav>';
}
