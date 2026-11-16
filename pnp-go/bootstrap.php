<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/api/portal_access.php';

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
$sessionPath = __DIR__ . '/storage/sessions';

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}

if ($isLocal && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}
if(pnp_portal_v2()){
    session_name('pnp_go_session');
    session_set_cookie_params(['path'=>PortalV2::base().'/pnp-go/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
}
session_start();

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/Database.php';
require __DIR__ . '/app/Router.php';
require __DIR__ . '/app/Controllers/PageController.php';
require __DIR__ . '/app/Controllers/PublicController.php';
require __DIR__ . '/app/Controllers/AuthController.php';
require __DIR__ . '/app/Controllers/DashboardController.php';
require __DIR__ . '/app/Controllers/VendorController.php';
require __DIR__ . '/app/Controllers/VehicleController.php';
require __DIR__ . '/app/Controllers/ReportController.php';
require __DIR__ . '/app/Services/PdfService.php';

date_default_timezone_set(config('app')['timezone']);

require_once dirname(__DIR__) . '/api/portal_access.php';
if(pnp_portal_v2()){
    $route=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    $right='view';
    if(str_contains($route,'/download')||str_ends_with($route,'/report'))$right='export';
    if($post){$right=str_contains($route,'/approve')||str_contains($route,'/reject')?'approve':(str_ends_with($route,'/request')?'create':'edit');if(str_contains($route,'/settings')||str_contains($route,'/vendors')||str_contains($route,'/manage/'))$right='manage_settings';}
    if(str_contains($route,'/settings')||str_contains($route,'/vendors')||str_contains($route,'/manage/'))$right='manage_settings';
    if(str_ends_with($route,'/logout'))$right='view';
    if(str_contains($route,'/delete'))pnp_portal_fail(new PortalProblem(403,'ยังไม่เปิดการลบข้อมูลถาวรในรุ่นใหม่'));
    $context=pnp_portal_guard('pnp-go',$right,str_ends_with($route,'api_pending_count.php'));
    if($context['access']['scope']!=='all')pnp_portal_fail(new PortalProblem(403,'PNP Go รุ่นนี้ยังไม่เปิดใช้ขอบเขตแผนก'));
    if(!str_ends_with($route,'/sso.php')){
        $goDb=Database::connection();$lookup=$goDb->prepare('SELECT id,role FROM users WHERE username=?');$lookup->execute([$context['user']['username']]);$local=$lookup->fetch();
        if(!$local){header('Location: '.PortalV2::base().'/pnp-go/sso.php');exit;}
        $_SESSION['user_id']=(int)$local['id'];
        $localRole=$context['access']['role']==='driver'?'user':$context['access']['role'];
        if($local['role']!==$localRole){$update=$goDb->prepare('UPDATE users SET role=? WHERE id=?');$update->execute([$localRole,$local['id']]);}
    }
}
