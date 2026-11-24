<?php
declare(strict_types=1);
const AC_DOCUMENT_TYPES=[
 'course_syllabus'=>['title'=>'โครงการสอน','prefix'=>'syllabus','number'=>'01','rule'=>'ส่งให้ครบทุกรายวิชา'],
 'lesson_plan'=>['title'=>'แผนการสอน','prefix'=>'plan','number'=>'02','rule'=>'ส่งอย่างน้อย 1 รายวิชา'],
 'teaching_materials'=>['title'=>'สื่อการสอน','prefix'=>'mat','number'=>'03','rule'=>'ส่งอย่างน้อย 1 รายวิชา'],
];
function ac_h(mixed $value):string{return htmlspecialchars((string)($value??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function ac_base():string{return (function_exists('pnp_portal_v2')&&pnp_portal_v2()?PortalV2::base():rtrim(get_portal_url(),'/')).'/pnp-academix';}
function ac_date(?string $date):string{
 if(!$date)return 'ไม่ระบุเวลา';try{$d=new DateTimeImmutable($date,new DateTimeZone('Asia/Bangkok'));return $d->format('d/m/').((int)$d->format('Y')+543).' '.$d->format('H:i').' น.';}catch(Throwable $e){return 'ไม่ระบุเวลา';}
}
function ac_status(?string $status):string{return ['approved'=>'อนุมัติแล้ว','pending'=>'รอตรวจ','rejected'=>'ต้องแก้ไข'][$status??'']??'ยังไม่ส่ง';}
function ac_badge(?string $status,?string $timing=null):string{
 $kind=in_array($status,['approved','pending','rejected'],true)?$status:'missing';
 return '<span class="ac-badge '.$kind.'"><span aria-hidden="true">'.['approved'=>'✓','pending'=>'◷','rejected'=>'!','missing'=>'—'][$kind].'</span> '.ac_status($status).'</span>'.($timing==='late'?'<span class="ac-badge late">ส่งล่าช้า</span>':'');
}
function ac_branding(PDO $db):array{
 $defaults=['system_name'=>'PNP Academix','college_name'=>'วิทยาลัยการอาชีพพนมไพร','logo_text'=>'PNP','logo_path'=>''];
 try{foreach($db->query('SELECT meta_key,meta_value FROM branding_settings')->fetchAll(PDO::FETCH_ASSOC) as $row)if(array_key_exists($row['meta_key'],$defaults))$defaults[$row['meta_key']]=$row['meta_value'];}catch(PDOException $e){}
 return $defaults;
}
function ac_start(string $title,array $branding,bool $scripts=false,bool $landing=false):void{
 header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
 echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>'.ac_h($title).' | PNP Academix</title><link rel="stylesheet" href="'.ac_h(ac_base().'/assets/academix.css?v=admin-review-20261116').'"><link rel="stylesheet" href="'.ac_h(ac_base().'/assets/academix-glass.css?v=glass-20261116-v1').'">';
 if($landing)echo '<link rel="stylesheet" href="'.ac_h(ac_base().'/assets/academix-landing.css?v=cat-countdown-20261116-v1').'"><script defer src="'.ac_h(ac_base().'/assets/academix-landing.js?v=cat-countdown-20261116-v1').'"></script>';
 if($scripts)echo '<script defer src="'.ac_h(ac_base().'/assets/academix.js?v=admin-review-20261116').'"></script>';
 echo '</head><body class="ac-body'.($landing?' ac-dark-landing':'').'"><a class="ac-skip" href="#ac-main">ข้ามไปเนื้อหา</a>';
}
function ac_header(array $branding,string $view='self',?string $viewer=null):void{
 $portal=function_exists('get_portal_url')?get_portal_url():(PortalV2::base().'/portal-v2/');$viewer=$viewer??(function_exists('current_user_fullname')?current_user_fullname():'');
 $admin=function_exists('pnp_portal_can')&&pnp_portal_v2()?pnp_portal_can('pnp-academic','view','admin'):(function_exists('current_user_role')&&current_user_role()==='admin');
 echo '<header class="ac-topbar"><a class="ac-brand" href="'.ac_h(ac_base().'/index.php').'"><span class="ac-brand-mark" aria-hidden="true">PNP</span><span><strong>PNP Academix</strong><small>'.ac_h($branding['college_name']).'</small></span></a><nav aria-label="พื้นที่ทำงาน" class="ac-nav"><a '.($view==='home'?'aria-current="page"':'').' href="'.ac_h(ac_base().'/index.php').'">หน้าแรก</a>';
 $self=!pnp_portal_v2()||pnp_portal_can('pnp-academic','view','self');
 if($self)echo '<a '.($view==='self'?'aria-current="page"':'').' href="'.ac_h(ac_base().'/teacher/dashboard.php').'">งานของฉัน</a>';
 if($admin)echo '<a '.($view==='admin'?'aria-current="page"':'').' href="'.ac_h(ac_base().'/admin/overview.php').'">งานดูแลระบบ</a>';
 echo '<a href="'.ac_h($portal).'">กลับสู่ Portal ↗</a></nav><span class="ac-user">'.ac_h($viewer).'</span></header>';
}
function ac_end(array $branding):void{echo '<footer class="ac-footer">'.ac_h($branding['college_name']).' · PNP Academix</footer></body></html>';}
