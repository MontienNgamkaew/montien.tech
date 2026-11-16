<?php
declare(strict_types=1);
require_once __DIR__.'/v2/core.php';

function pnp_portal_v2(): bool { return PortalV2::enabled(); }
function pnp_portal_can(string $app,string $right): bool {if(!pnp_portal_v2())return true;try{PortalV2::requireApp($app,$right);return true;}catch(PortalProblem $e){return false;}}
function pnp_portal_fail(PortalProblem $error,bool $api=false): never {
    if($api)PortalV2::json(['ok'=>false,'error'=>$error->getMessage(),'kind'=>$error->kind,'portal_url'=>PortalV2::base().'/portal-v2/'],$error->status);
    if(in_array($error->kind,['login_required','password_change_required'],true)){
        $parts=parse_url($_SERVER['REQUEST_URI']??'/');$query=[];parse_str($parts['query']??'',$query);unset($query['token']);
        $return=($parts['path']??'/').($query?'?'.http_build_query($query):'');
        header('Location: '.PortalV2::base().'/portal-v2/?returnTo='.rawurlencode(PortalV2::safeReturn($return)).($error->kind==='password_change_required'?'#password':''));exit;
    }
    http_response_code($error->status);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>สิทธิ์การใช้งาน</title><body><main style="font-family:Tahoma,sans-serif;max-width:560px;margin:12vh auto;padding:24px"><h1>ไม่สามารถเข้าใช้งานได้</h1><p>'.htmlspecialchars($error->getMessage(),ENT_QUOTES,'UTF-8').'</p><a href="'.htmlspecialchars(PortalV2::base().'/portal-v2/',ENT_QUOTES,'UTF-8').'">กลับสู่ Portal</a></main></body></html>';exit;
}
function pnp_portal_guard(string $app,string $right='view',bool $api=false): ?array {
    if(!pnp_portal_v2())return null;
    try{PortalV2::ready();return PortalV2::requireApp($app,$right);}catch(PortalProblem $e){pnp_portal_fail($e,$api);}catch(Throwable $e){error_log('Portal access unavailable: '.get_class($e));pnp_portal_fail(new PortalProblem(503,'ไม่สามารถตรวจสิทธิ์ส่วนกลางได้ กรุณาลองใหม่ภายหลัง'),$api);}
}
function pnp_portal_scope(array $context,string $username,bool $api=false): void {
    if(!PortalV2::scopeAllows($context,$username))pnp_portal_fail(new PortalProblem(403,'ข้อมูลอยู่นอกขอบเขตที่ได้รับมอบหมาย','scope_denied'),$api);
}
function pnp_portal_filter(array $context,array $rows,string $field='username'): array {return array_values(array_filter($rows,static fn(array $row):bool=>PortalV2::scopeAllows($context,(string)($row[$field]??''))));}
function pnp_portal_payload(string $app): ?array {
    if(!pnp_portal_v2())return null;$context=pnp_portal_guard($app);$u=$context['user'];$roles=[];
    foreach(PortalV2::APPS as $id=>$definition)$roles[$id]=PortalV2::access($u,$id)['role'];
    return ['user_id'=>(int)$u['id'],'username'=>$u['username'],'email'=>$u['email'],'title'=>$u['title']??'','first_name'=>$u['first_name'],'last_name'=>$u['last_name'],'primary_position'=>$u['primary_position'],'org_position'=>$u['org_position']??'','department'=>$u['department']??'','roles'=>$roles,'is_portal_admin'=>(int)$u['is_portal_admin'],'avatar'=>$u['avatar_path']??''];
}
function pnp_portal_logout(): void {
    $u=PortalV2::actor(false,true);$token=(string)($_COOKIE['pnp_portal_session']??'');
    if($token)PortalV2::query('UPDATE pnp_v2_sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=?',[hash('sha256',$token)]);
    PortalV2::cookie('pnp_portal_session','',time()-3600);if($u)PortalV2::audit((int)$u['id'],(int)$u['id'],'session.logout');
}
