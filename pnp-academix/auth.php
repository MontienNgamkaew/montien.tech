<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/portal_access.php';

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => pnp_portal_v2()?PortalV2::base().'/pnp-academix/':'/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if(pnp_portal_v2())session_name('pnp_academix_session');
    session_start();
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function is_logged_in(): bool
{
    if (pnp_portal_v2() && !pnp_portal_can('pnp-academic','view')) return false;
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_fullname(): string
{
    return $_SESSION['fullname'] ?? 'ผู้ใช้งาน';
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Get the Portal base URL (works on both local XAMPP and Hostinger production).
 * Local XAMPP: /pnp-portal/
 * Hostinger:   / (portal is at document root)
 */
function get_portal_url(): string
{
    if (pnp_portal_v2()) return PortalV2::base() . '/portal-v2/';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, 'montien.tech') !== false) {
        // Production: portal is at root
        return '/';
    }
    // Local XAMPP: portal is under /pnp-portal/
    return '/pnp-portal/';
}

function require_login(): void
{
    if (pnp_portal_v2()) {
        global $pdo;
        $context = pnp_portal_guard('pnp-academic');
        $statement = $pdo->prepare('SELECT id FROM users WHERE username=?');
        $statement->execute([$context['user']['username']]);
        $localId = $statement->fetchColumn();
        if (!$localId) redirect_to(PortalV2::base() . '/pnp-academix/sso.php');
        $_SESSION['user_id'] = (int)$localId;
        $_SESSION['username'] = $context['user']['username'];
        $_SESSION['fullname'] = trim($context['user']['first_name'].' '.$context['user']['last_name']);
        $_SESSION['role'] = $context['access']['role']==='admin' ? 'admin' : 'teacher';
        $GLOBALS['pnp_academic_context'] = $context;
    }
    if (!is_logged_in()) {
        // Redirect to Portal for SSO authentication instead of local login
        redirect_to(get_portal_url());
    }
}

function require_role(string $role): void
{
    require_login();

    if (pnp_portal_v2()) {
        $workspace=$role==='teacher'?'self':'admin';
        $context=pnp_portal_guard('pnp-academic','view',false,$workspace);
        $_SESSION['role']=$role;$GLOBALS['pnp_academic_context']=$context;return;
    }
    if (current_user_role() !== $role) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

function require_admin(): void
{
    require_role('admin');
    if (pnp_portal_v2()) {
        global $pdo;
        $page=basename($_SERVER['SCRIPT_NAME']??'');$action=(string)($_POST['action']??'');$post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
        $right=match($page){'settings.php'=>'manage_settings','print_report.php'=>'export','import.php'=>$post&&$action==='import_courses_csv'?'create':'manage_users',default=>$post?($page==='review.php'?'approve':'manage_users'):'view'};
        $context=pnp_portal_guard('pnp-academic',$right,false,'admin');
        if(($page==='settings.php'||$page==='import.php')&&$context['access']['scope']!=='all')pnp_portal_fail(new PortalProblem(403,'การตั้งค่าและนำเข้าข้อมูลต้องมีขอบเขตทั้งองค์กร'));
        if($post&&(in_array($action,['delete_teacher','cleanup_files','delete_unused_files','garbage_collect','new_semester'],true)||($page==='import.php'&&$action!=='import_courses_csv')))pnp_portal_fail(new PortalProblem(403,'จัดการบัญชีผ่าน Portal กลาง และยังไม่เปิดการลบ/ล้างข้อมูลในรุ่นใหม่นี้'));
        $target=(int)($_GET['teacher_id']??$_POST['teacher_id']??0);
        if($target){$s=$pdo->prepare('SELECT username FROM users WHERE id=?');$s->execute([$target]);$username=$s->fetchColumn();if(!$username)pnp_portal_fail(new PortalProblem(404,'ไม่พบบุคลากร'));pnp_portal_scope($context,(string)$username);}
    }
}

function require_teacher(): void
{
    require_role('teacher');
    if (pnp_portal_v2()) {
        $page=basename($_SERVER['SCRIPT_NAME']??'');$action=(string)($_POST['action']??'');$post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
        if($post&&str_starts_with($action,'delete_'))pnp_portal_fail(new PortalProblem(403,'ยังไม่เปิดการลบข้อมูลถาวรในรุ่นใหม่'));
        $right=$page==='print_memorandum.php'?'export':($post?'create':'view');
        if($post&&$action==='submit_document'){global $pdo;$s=$pdo->prepare('SELECT id FROM submissions WHERE course_id=? AND system_type=? LIMIT 1');$s->execute([(int)($_POST['course_id']??0),(string)($_POST['system_type']??'')]);if($s->fetchColumn())$right='edit';}
        $context=pnp_portal_guard('pnp-academic',$right,false,'self');pnp_portal_scope($context,$context['user']['username']);
    }
}

function create_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_at'] = time();

    unset($_SESSION['csrf_token']);
}

function logout_user(): void
{
    if(pnp_portal_v2())redirect_to(PortalV2::base().'/portal-v2/#logout');
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    session_destroy();
}

function pnp_academic_workspace_links(): void {
    if(!pnp_portal_v2())return;
    $context=$GLOBALS['pnp_academic_context']??pnp_portal_guard('pnp-academic');$a=PortalV2::access($context['user'],'pnp-academic');
    echo '<nav aria-label="พื้นที่ทำงาน" style="padding:12px 24px;background:#edf5f3;display:flex;gap:20px;font-size:14px">';
    if($a['has_self_workspace']&&in_array('view',$a['self_rights'],true))echo '<a href="'.e(PortalV2::base().'/pnp-academix/teacher/dashboard.php').'">งานของฉัน · ส่งงาน</a>';
    if($a['has_admin_workspace'])echo '<a href="'.e(PortalV2::base().'/pnp-academix/admin/overview.php').'">งานดูแลระบบ</a>';
    if($a['can_delegate'])echo '<a href="'.e(PortalV2::base().'/portal-v2/#staff').'">มอบหมายเจ้าหน้าที่</a>';
    echo '</nav>';
}
