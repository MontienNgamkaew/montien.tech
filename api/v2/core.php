<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/env.php';

final class PortalProblem extends RuntimeException
{
    public function __construct(public int $status, string $message, public string $kind = 'request_failed') { parent::__construct($message); }
}

final class PortalV2
{
    public const RIGHTS = ['view', 'create', 'edit', 'approve', 'export', 'manage_users', 'manage_settings'];
    public const APPS = [
        'pnp-academic' => ['name' => 'PNP Academix', 'description' => 'ส่งและตรวจเอกสารงานวิชาการ', 'path' => '/pnp-academix/sso.php', 'roles' => ['none','user','teacher','department_head','admin']],
        'pnp-go' => ['name' => 'PNP Go', 'description' => 'ขอใช้รถยนต์และน้ำมันเชื้อเพลิง', 'path' => '/pnp-go/sso.php', 'roles' => ['none','user','driver','supply_head','deputy_director','director','admin']],
        'pnp-man' => ['name' => 'PNP Man', 'description' => 'บุคลากรและโครงสร้างภาระงาน', 'path' => '/pnpman/', 'roles' => ['none','user','admin']],
        'pnp-lesson-plan' => ['name' => 'PNP Lesson Plan', 'description' => 'จัดทำแผนการสอน', 'path' => '/pnp-lessonplan/dist/', 'roles' => ['none','user','admin']],
    ];
    private static ?PDO $connection = null;
    public static function runtime(): array {
        static $config=null; if($config!==null)return $config;
        $file=__DIR__.'/runtime.local.json';
        if(!is_file($file))return $config=[];
        try{$value=json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new PortalProblem(503,'การตั้งค่าเปิดบริการไม่พร้อม');}
        if(!is_array($value))throw new PortalProblem(503,'การตั้งค่าเปิดบริการไม่ถูกต้อง');
        return $config=$value;
    }
    public static function enabled(): bool { return filter_var(env('PORTAL_V2_ENABLED', self::runtime()['enabled']??false), FILTER_VALIDATE_BOOLEAN); }
    public static function appLocked(string $app): bool { return in_array($app,self::runtime()['paused_apps']??[],true); }
    public static function base(): string {
        $base = rtrim((string)env('PORTAL_BASE_PATH', self::runtime()['base']??''), '/');
        if ($base !== '' && !preg_match('~^/[A-Za-z0-9/_-]+$~D', $base)) throw new PortalProblem(503, 'ตั้งค่าเส้นทาง Portal ไม่ถูกต้อง');
        return $base;
    }
    public static function db(): PDO {
        if (self::$connection) return self::$connection;
        $production = str_contains($_SERVER['HTTP_HOST'] ?? '', 'montien.tech');
        $host = (string)env('DB_HOST', 'localhost'); $name = (string)env('DB_NAME', $production ? 'u651170081_pnp_portal' : 'pnp_portal');
        $user = (string)env('DB_USER', $production ? 'u651170081_pnp_portal' : 'root');
        return self::$connection = new PDO('mysql:host='.$host.';port='.(int)env('DB_PORT',3306).';dbname='.$name.';charset=utf8mb4', $user, (string)env('DB_PASS',''), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    public static function query(string $sql, array $params=[]): PDOStatement { $s=self::db()->prepare($sql);$s->execute($params);return $s; }
    public static function rollback(): void { if(self::$connection?->inTransaction())self::$connection->rollBack(); }
    public static function ready(): void {
        if (!self::enabled()) throw new PortalProblem(503,'Portal รุ่นใหม่ยังไม่เปิดใช้งาน','v2_disabled');
        if (strlen((string)env('JWT_SECRET_KEY','')) < 32) throw new PortalProblem(503,'ยังไม่ได้ตั้งค่าคีย์ส่วนกลาง');
        try { $version=self::query('SELECT version FROM pnp_v2_migrations WHERE version=1')->fetchColumn(); }
        catch (PDOException $e) { throw new PortalProblem(503,'ยังไม่ได้เตรียมฐานข้อมูล Portal รุ่นใหม่','migration_required'); }
        if (!$version) throw new PortalProblem(503,'ยังไม่ได้เตรียมฐานข้อมูล Portal รุ่นใหม่','migration_required');
    }
    public static function json(array $data, int $status=200): never {
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }
    public static function cookie(string $name, string $value, int $expires=0): void {
        $secure=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
        setcookie($name,$value,['expires'=>$expires,'path'=>self::base().'/', 'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        $_COOKIE[$name]=$value;
    }
    public static function csrf(): string {
        $existing=(string)($_COOKIE['pnp_portal_csrf']??'');$parts=explode('.',$existing);
        if(count($parts)===2 && preg_match('/^[a-f0-9]{64}$/D',$parts[0]) && hash_equals(hash_hmac('sha256',$parts[0],(string)env('JWT_SECRET_KEY')),$parts[1])) return $existing;
        $random=bin2hex(random_bytes(32));$value=$random.'.'.hash_hmac('sha256',$random,(string)env('JWT_SECRET_KEY'));
        self::cookie('pnp_portal_csrf',$value);return $value;
    }
    public static function requireCsrf(bool $json=true): void {
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        $expected=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https://':'http://').($_SERVER['HTTP_HOST']??'');
        if($origin!=='' && $origin!==$expected) throw new PortalProblem(403,'คำขอข้ามเว็บไซต์ไม่ได้รับอนุญาต','csrf');
        $provided=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if($provided==='' || !hash_equals(self::csrf(),$provided)) throw new PortalProblem(403,'กรุณาโหลดหน้าใหม่ก่อนทำรายการ','csrf');
        if($json&&!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')) throw new PortalProblem(415,'รองรับข้อมูล JSON เท่านั้น');
    }
    public static function input(): array {
        if((int)($_SERVER['CONTENT_LENGTH']??0)>131072) throw new PortalProblem(413,'ข้อมูลมีขนาดเกินกำหนด');
        try{$value=json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new PortalProblem(400,'รูปแบบข้อมูลไม่ถูกต้อง');}
        if(!is_array($value))throw new PortalProblem(400,'รูปแบบข้อมูลไม่ถูกต้อง');return $value;
    }
    public static function rate(string $category,string $subject,int $max=10,int $seconds=900): void {
        $key=hash_hmac('sha256',$category.'|'.($_SERVER['REMOTE_ADDR']??'local').'|'.$subject,(string)env('JWT_SECRET_KEY'));
        $now=time();$row=self::query('SELECT attempts,window_start FROM pnp_v2_rate_limits WHERE bucket=?',[$key])->fetch();
        if($row && (int)$row['window_start']>$now-$seconds && (int)$row['attempts']>=$max) throw new PortalProblem(429,'ทำรายการบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่');
        self::query('INSERT INTO pnp_v2_rate_limits (bucket,attempts,window_start) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start<? ,1,attempts+1),window_start=IF(window_start<?,VALUES(window_start),window_start)',[$key,$now,$now-$seconds,$now-$seconds]);
    }
    public static function actor(bool $required=true,bool $allowPasswordChange=false): ?array {
        $token=(string)($_COOKIE['pnp_portal_session']??'');
        if(!preg_match('/^[a-f0-9]{96}$/D',$token)){if($required)throw new PortalProblem(401,'กรุณาเข้าสู่ระบบผ่าน Portal','login_required');return null;}
        $user=self::query('SELECT u.*,m.auth_version,m.must_change_password,m.approval_status,m.archived_at,s.auth_version AS session_version FROM pnp_v2_sessions s JOIN users u ON u.id=s.user_id JOIN pnp_v2_accounts m ON m.user_id=u.id WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP() AND s.revoked_at IS NULL',[hash('sha256',$token)])->fetch();
        if(!$user || $user['status']!=='active' || $user['approval_status']!=='approved' || $user['archived_at'] || (int)$user['auth_version']!==(int)$user['session_version']){
            if($required)throw new PortalProblem(401,'บัญชีหรือเซสชันนี้ไม่สามารถใช้งานได้ กรุณาเข้าสู่ระบบใหม่','login_required');return null;
        }
        if(!$allowPasswordChange && (int)$user['must_change_password']===1)throw new PortalProblem(403,'ต้องเปลี่ยนรหัสผ่านชั่วคราวก่อนเข้าใช้งาน','password_change_required');
        return $user;
    }
    public static function master(): array { $u=self::actor();if((int)$u['is_portal_admin']!==1)throw new PortalProblem(403,'เฉพาะผู้ดูแลสูงสุดเท่านั้น');return $u; }
    public static function publicUser(array $u): array {
        return ['id'=>(int)$u['id'],'username'=>$u['username'],'title'=>$u['title']??'','first_name'=>$u['first_name'],'last_name'=>$u['last_name'],'email'=>$u['email']??'','phone'=>$u['phone']??'','department'=>$u['department']??'','primary_position'=>$u['primary_position']??'','status'=>$u['status'],'is_portal_admin'=>(int)$u['is_portal_admin'],'must_change_password'=>(bool)($u['must_change_password']??false),'approval_status'=>$u['approval_status']??'approved','archived_at'=>$u['archived_at']??null];
    }
    public static function legacyRights(string $role): array {
        if($role==='admin')return self::RIGHTS;
        if(in_array($role,['supply_head','deputy_director','director'],true))return ['view','approve','export'];
        if(in_array($role,['user','teacher','driver','department_head'],true))return ['view','create','edit','export'];return [];
    }
    public static function multiRoleReady(): bool {
        static $ready=null;if($ready!==null)return $ready;
        try{return $ready=(bool)self::query('SELECT version FROM pnp_v2_migrations WHERE version=3')->fetchColumn();}catch(PDOException $e){return $ready=false;}
    }
    public static function roleProfile(array $u,string $app): array {
        $row=self::multiRoleReady()?self::query('SELECT * FROM pnp_v2_role_profiles WHERE user_id=? AND app_id=?',[$u['id'],$app])->fetch():false;
        return $row?['roles'=>json_decode($row['roles_json'],true)?:[],'self_rights'=>json_decode($row['self_rights_json'],true)?:[],'can_delegate'=>(bool)$row['can_delegate'],'delegable_rights'=>json_decode($row['delegable_rights_json'],true)?:[]]:[];
    }
    public static function access(array $u,string $app,string $workspace='default',bool $includeStaff=true): array {
        if(!isset(self::APPS[$app]))throw new PortalProblem(404,'ไม่พบระบบย่อย');
        $status=self::appLocked($app)?'disabled':(self::query('SELECT status FROM app_status WHERE app_id=?',[$app])->fetchColumn()?:'disabled');
        $master=(int)$u['is_portal_admin']===1;
        $row=self::query('SELECT r.role,g.rights_json,g.scope,g.scope_value FROM app_roles r LEFT JOIN pnp_v2_grants g ON g.user_id=r.user_id AND BINARY g.app_id=BINARY r.app_id WHERE r.user_id=? AND r.app_id=?',[$u['id'],$app])->fetch();
        $role=$row['role']??'none';$profile=self::roleProfile($u,$app);
        $roles=$master?['admin','teacher']:($profile['roles']??($role==='none'?[]:[$role]));
        $rights=$master?self::RIGHTS:($row&&$row['rights_json']?json_decode($row['rights_json'],true):[]);
        $rights=array_values(array_intersect(self::RIGHTS,is_array($rights)?$rights:[]));
        if(!$master&&(!in_array($role,self::APPS[$app]['roles'],true)||$role==='none'))$rights=[];
        $access=['app_id'=>$app,'role'=>$master?'admin':$role,'roles'=>$roles,'rights'=>$rights,'scope'=>$master?'all':($row['scope']??'all'),'scope_value'=>$master?'':($row['scope_value']??''),'status'=>$status,'can_delegate'=>$master||(!empty($profile['can_delegate'])&&in_array('admin',$roles,true)),'delegable_rights'=>$master?self::RIGHTS:array_values(array_intersect($rights,$profile['delegable_rights']??[])),'self_rights'=>$master?['view','create','edit','export']:($profile['self_rights']??(array_intersect($roles,['teacher','user','department_head'])?array_values(array_intersect($rights,['view','create','edit','export'])):[]))];
        if($includeStaff&&self::multiRoleReady()&&!$master&&!in_array('admin',$roles,true)){
            $staff=self::query('SELECT * FROM pnp_v2_staff_assignments WHERE user_id=? AND app_id=? AND revoked_at IS NULL',[$u['id'],$app])->fetch();
            if($staff){
                $parent=self::query("SELECT u.* FROM users u JOIN pnp_v2_accounts a ON a.user_id=u.id WHERE u.id=? AND u.status='active' AND a.approval_status='approved' AND a.archived_at IS NULL",[$staff['assigned_by']])->fetch();
                if($parent){$pa=self::access($parent,$app,'default',false);$sr=json_decode($staff['rights_json'],true)?:[];
                    $contained=$pa['scope']==='all'||($staff['scope']==='department'&&$pa['scope']==='department'&&$staff['scope_value']===$pa['scope_value']);
                    if($pa['can_delegate']&&$contained&&!array_diff($sr,$pa['delegable_rights'])&&in_array('view',$sr,true)){
                        $access['roles']=array_values(array_unique(array_merge($roles,['staff'])));$access['role']='admin';$access['rights']=$sr;$access['scope']=$staff['scope'];$access['scope_value']=$staff['scope_value'];$access['assignment']=['assigned_by'=>(int)$staff['assigned_by'],'revision'=>(int)$staff['revision']];
                    }
                }
            }
        }
        $access['has_admin_workspace']=$master||(bool)array_intersect($access['roles'],['admin','staff']);
        $access['has_self_workspace']=$master||(bool)array_intersect($access['roles'],['teacher','user','department_head']);
        if($workspace==='self'){$access['rights']=$access['has_self_workspace']?$access['self_rights']:[];$access['role']=$app==='pnp-academic'?'teacher':'user';$access['scope']='self';$access['scope_value']=$u['username'];}
        if($workspace==='admin'&&!$access['has_admin_workspace'])$access['rights']=[];
        return $access;
    }
    public static function requireApp(string $app,string $right='view',string $workspace='default'): array {
        if(!in_array($workspace,['default','self','admin'],true))throw new PortalProblem(400,'พื้นที่ทำงานไม่ถูกต้อง');
        $u=self::actor();if(self::appLocked($app))throw new PortalProblem(403,'ระบบนี้ปิดใช้งานชั่วคราวตามแผนปรับปรุง','app_disabled');$a=self::access($u,$app,$workspace);
        if($workspace==='default'&&$right==='view'&&!in_array('view',$a['rights'],true)&&in_array('view',$a['self_rights'],true))$a=self::access($u,$app,'self');
        if($a['status']!=='active' && (int)$u['is_portal_admin']!==1)throw new PortalProblem(403,'ระบบนี้ยังไม่เปิดให้บริการ','app_disabled');
        if(!in_array($right,$a['rights'],true))throw new PortalProblem(403,'ไม่มีสิทธิ์ดำเนินการนี้ในระบบ','permission_denied');
        return ['user'=>$u,'access'=>$a];
    }
    public static function scopeAllows(array $context,string $username): bool {
        if($context['access']['scope']==='self')return (int)self::query('SELECT id FROM users WHERE username=?',[$username])->fetchColumn()===(int)$context['user']['id'];
        if($context['access']['scope']==='all')return true;
        $department=self::query('SELECT department FROM users WHERE username=?',[$username])->fetchColumn();
        return $department!==false && $context['access']['scope']==='department' && $context['access']['scope_value']!=='' && hash_equals($context['access']['scope_value'],(string)$department);
    }
    public static function audit(?int $actor,?int $target,string $action,array $before=[],array $after=[]): void {
        self::query('INSERT INTO pnp_v2_audit (actor_id,target_id,action,before_json,after_json) VALUES (?,?,?,?,?)',[$actor,$target,$action,json_encode($before,JSON_UNESCAPED_UNICODE),json_encode($after,JSON_UNESCAPED_UNICODE)]);
    }
    public static function password(string $value): string {
        if(strlen($value)<10 || strlen($value)>72)throw new PortalProblem(400,'รหัสผ่านต้องมีความยาว 10–72 ไบต์');return password_hash($value,PASSWORD_DEFAULT);
    }
    public static function startSession(array $u): void {
        $token=bin2hex(random_bytes(48));
        self::query('INSERT INTO pnp_v2_sessions (token_hash,user_id,auth_version,expires_at) VALUES (?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 8 HOUR))',[hash('sha256',$token),$u['id'],$u['auth_version']]);
        self::cookie('pnp_portal_session',$token,time()+28800);
    }
    public static function revoke(int $id): void { self::query('UPDATE pnp_v2_accounts SET auth_version=auth_version+1 WHERE user_id=?',[$id]); }
    public static function safeReturn(string $path): string {
        if($path==='' || str_contains($path,'\\') || str_contains($path,'..') || str_contains($path,'%') || str_contains($path,"\r") || str_contains($path,"\n") || str_starts_with($path,'//'))return self::base().'/portal-v2/';
        foreach(['/pnp-go/','/pnp-academix/','/pnpman/','/pnp-lessonplan/','/portal-v2/'] as $prefix)if(str_starts_with($path,self::base().$prefix))return $path;
        return self::base().'/portal-v2/';
    }
}
