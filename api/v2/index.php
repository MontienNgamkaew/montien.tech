<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';

function memberRow(int $id,bool $lock=false): array {
    $row=PortalV2::query('SELECT u.*,m.auth_version,m.must_change_password,m.approval_status,m.archived_at FROM users u JOIN pnp_v2_accounts m ON m.user_id=u.id WHERE u.id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();
    if(!$row)throw new PortalProblem(404,'ไม่พบบัญชีสมาชิก');return $row;
}
function profileFields(array $input): array {
    $fields=[];foreach(['first_name','last_name','email','phone','department','primary_position'] as $key){$fields[$key]=trim((string)($input[$key]??''));if(mb_strlen($fields[$key])>($key==='department'?100:($key==='email'?100:($key==='phone'?20:100))))throw new PortalProblem(400,'ข้อมูลบางช่องยาวเกินกำหนด');}
    if($fields['first_name']===''||$fields['last_name']===''||!filter_var($fields['email'],FILTER_VALIDATE_EMAIL))throw new PortalProblem(400,'กรุณากรอกชื่อ นามสกุล และอีเมลให้ถูกต้อง');
    $positions=['ผู้ดูแลระบบ','ผู้อำนวยการ','รองผู้อำนวยการ','ข้าราชการครู','พนักงานราชการครู','ครูพิเศษสอน','เจ้าหน้าที่','แม่บ้าน','พนักงานขับรถ'];
    if(!in_array($fields['primary_position'],$positions,true))throw new PortalProblem(400,'ตำแหน่งไม่ถูกต้อง');return $fields;
}
function createMember(array $input,bool $pending,int $actor=0): array {
    $fields=profileFields($input);$username=trim((string)($input['username']??''));
    if(!preg_match('/^[A-Za-z0-9._-]{3,50}$/D',$username))throw new PortalProblem(400,'ชื่อบัญชีใช้ A–Z, a–z, ตัวเลข จุด ขีดกลาง หรือขีดล่าง 3–50 ตัว');
    $hash=PortalV2::password((string)($input['password']??''));
    $master=!$pending&&!empty($input['is_portal_admin'])?1:0;
    PortalV2::query('INSERT INTO users (username,password_hash,first_name,last_name,email,phone,department,primary_position,status,is_portal_admin) VALUES (?,?,?,?,?,?,?,?,?,?)',[$username,$hash,$fields['first_name'],$fields['last_name'],$fields['email'],$fields['phone'],$fields['department'],$fields['primary_position'],$pending?'suspended':'active',$master]);
    $id=(int)PortalV2::db()->lastInsertId();
    PortalV2::query('INSERT INTO pnp_v2_accounts (user_id,must_change_password,approval_status) VALUES (?,?,?)',[$id,$pending?0:1,$pending?'pending':'approved']);
    PortalV2::audit($actor?:null,$id,$pending?'member.register':'member.create',[],['username'=>$username,'is_portal_admin'=>$master,'approval_status'=>$pending?'pending':'approved']);
    return PortalV2::publicUser(memberRow($id));
}
function lockMasters(): void {PortalV2::query('SELECT id FROM users WHERE is_portal_admin=1 ORDER BY id FOR UPDATE')->fetchAll();}
function assertMasterSurvives(int $target,int $actor,bool $removesAuthority): void {
    if(!$removesAuthority)return;
    if($target===$actor)throw new PortalProblem(409,'ไม่สามารถระงับ เก็บเข้าคลัง หรือปลดสิทธิ์ตัวเองได้');
    $count=(int)PortalV2::query("SELECT COUNT(*) FROM users u JOIN pnp_v2_accounts m ON m.user_id=u.id WHERE u.is_portal_admin=1 AND u.status='active' AND m.approval_status='approved' AND m.archived_at IS NULL AND u.id<>?",[$target])->fetchColumn();
    if($count<1)throw new PortalProblem(409,'ต้องมีผู้ดูแลสูงสุดที่ใช้งานได้อย่างน้อยหนึ่งคน');
}

try{
    PortalV2::ready();$action=(string)($_GET['action']??'bootstrap');$method=$_SERVER['REQUEST_METHOD']??'GET';
    if(!in_array($method,['GET','POST'],true))throw new PortalProblem(405,'Method not allowed');
    $readActions=['bootstrap','session','members','audit','launch'];
    if($method==='GET'&&!in_array($action,$readActions,true))throw new PortalProblem(405,'ต้องใช้ POST สำหรับการเปลี่ยนข้อมูล');
    $input=[];if($method==='POST'){PortalV2::requireCsrf();$input=PortalV2::input();if(in_array($action,$readActions,true))throw new PortalProblem(405,'Method not allowed');}
    if($action==='bootstrap'){
        $settings=['portal_name'=>'PNP e-Service','organization'=>'วิทยาลัยการอาชีพพนมไพร','notice'=>'','support_text'=>'กรุณาติดต่อผู้ดูแลระบบของหน่วยงาน'];
        foreach(PortalV2::query('SELECT setting_key,setting_value FROM pnp_v2_settings')->fetchAll() as $row)if(array_key_exists($row['setting_key'],$settings))$settings[$row['setting_key']]=$row['setting_value'];
        $u=PortalV2::actor(false,true);$systems=[];$statuses=PortalV2::query('SELECT app_id,status FROM app_status')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach(PortalV2::APPS as $id=>$app){$systems[]=['id'=>$id,'name'=>$app['name'],'description'=>$app['description'],'locked'=>PortalV2::appLocked($id),'status'=>PortalV2::appLocked($id)?'disabled':($statuses[$id]??'disabled'),'access'=>$u?PortalV2::access($u,$id):null];}
        PortalV2::json(['ok'=>true,'csrf'=>PortalV2::csrf(),'user'=>$u?PortalV2::publicUser($u):null,'systems'=>$systems,'settings'=>$settings,'rights'=>PortalV2::RIGHTS,'base'=>PortalV2::base(),'preview'=>filter_var(env('PORTAL_PREVIEW','false'),FILTER_VALIDATE_BOOLEAN)]);
    }
    if($action==='login'){
        $name=trim((string)($input['username']??''));$password=(string)($input['password']??'');PortalV2::rate('login',strtolower($name));
        $u=PortalV2::query('SELECT u.*,m.auth_version,m.must_change_password,m.approval_status,m.archived_at FROM users u JOIN pnp_v2_accounts m ON m.user_id=u.id WHERE u.username=?',[$name])->fetch();
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if(!password_verify($password,$u['password_hash']??$dummy))throw new PortalProblem(401,'ชื่อบัญชีหรือรหัสผ่านไม่ถูกต้อง');
        if($u['approval_status']==='pending')throw new PortalProblem(403,'บัญชียังอยู่ระหว่างรอผู้ดูแลอนุมัติ','approval_pending');
        if($u['status']!=='active'||$u['archived_at'])throw new PortalProblem(403,'บัญชีนี้ยังไม่เปิดใช้งาน กรุณาติดต่อผู้ดูแล');
        PortalV2::startSession($u);PortalV2::audit((int)$u['id'],(int)$u['id'],'session.login');
        PortalV2::json(['ok'=>true,'user'=>PortalV2::publicUser($u),'return_to'=>(int)$u['must_change_password']===1?PortalV2::base().'/portal-v2/#password':PortalV2::safeReturn((string)($input['return_to']??''))]);
    }
    if($action==='register'){
        PortalV2::rate('register','',5,3600);PortalV2::db()->beginTransaction();$u=createMember($input,true);PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'รับสมัครแล้ว กรุณารอผู้ดูแลอนุมัติบัญชี']);
    }
    if($action==='forgot_password'){
        PortalV2::rate('forgot','');$identifier=trim((string)($input['identifier']??''));
        $id=PortalV2::query('SELECT id FROM users WHERE username=? OR email=? LIMIT 1',[$identifier,$identifier])->fetchColumn();
        if($id){$pending=PortalV2::query("SELECT id FROM pnp_v2_reset_requests WHERE user_id=? AND status='pending'",[$id])->fetchColumn();if(!$pending)PortalV2::query('INSERT INTO pnp_v2_reset_requests (user_id) VALUES (?)',[$id]);}
        PortalV2::json(['ok'=>true,'message'=>'หากพบบัญชี ระบบจะส่งคำขอให้ผู้ดูแลตรวจสอบและติดต่อกลับ ไม่มีการส่งอีเมลอัตโนมัติ']);
    }
    if($action==='session'){
        $app=(string)($_GET['app']??'');$right=(string)($_GET['right']??'view');if(!in_array($right,PortalV2::RIGHTS,true))throw new PortalProblem(400,'สิทธิ์ไม่ถูกต้อง');$u=$app?PortalV2::requireApp($app,$right)['user']:PortalV2::actor();$roles=[];
        foreach(PortalV2::APPS as $id=>$definition)$roles[$id]=PortalV2::access($u,$id)['role'];
        PortalV2::json(['ok'=>true,'valid'=>true,'user'=>array_merge(PortalV2::publicUser($u),['user_id'=>(int)$u['id'],'roles'=>$roles])]);
    }
    if($action==='launch'){
        $app=(string)($_GET['app']??'');PortalV2::requireApp($app);header('Cache-Control: no-store');header('Location: '.PortalV2::base().PortalV2::APPS[$app]['path']);exit;
    }
    if($action==='logout'){
        $u=PortalV2::actor(true,true);PortalV2::query('UPDATE pnp_v2_sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=?',[hash('sha256',(string)($_COOKIE['pnp_portal_session']??''))]);PortalV2::cookie('pnp_portal_session','',time()-3600);PortalV2::audit((int)$u['id'],(int)$u['id'],'session.logout');PortalV2::json(['ok'=>true]);
    }
    if($action==='change_password'){
        $u=PortalV2::actor(true,true);if(!password_verify((string)($input['old_password']??''),$u['password_hash']))throw new PortalProblem(400,'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        $hash=PortalV2::password((string)($input['password']??''));PortalV2::db()->beginTransaction();PortalV2::query('UPDATE users SET password_hash=? WHERE id=?',[$hash,$u['id']]);PortalV2::query('UPDATE pnp_v2_accounts SET must_change_password=0,auth_version=auth_version+1 WHERE user_id=?',[$u['id']]);PortalV2::audit((int)$u['id'],(int)$u['id'],'password.change');PortalV2::db()->commit();PortalV2::startSession(memberRow((int)$u['id']));PortalV2::json(['ok'=>true,'message'=>'เปลี่ยนรหัสผ่านแล้ว เซสชันเดิมถูกยกเลิก']);
    }
    if($action==='update_profile'){
        $u=PortalV2::actor();$email=trim((string)($input['email']??''));$phone=trim((string)($input['phone']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100||mb_strlen($phone)>20)throw new PortalProblem(400,'อีเมลหรือหมายเลขโทรศัพท์ไม่ถูกต้อง');PortalV2::db()->beginTransaction();PortalV2::query('UPDATE users SET email=?,phone=? WHERE id=?',[$email,$phone,$u['id']]);PortalV2::audit((int)$u['id'],(int)$u['id'],'profile.update',['email'=>$u['email'],'phone'=>$u['phone']],['email'=>$email,'phone'=>$phone]);PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'บันทึกข้อมูลติดต่อแล้ว']);
    }
    $actor=PortalV2::master();$actorId=(int)$actor['id'];
    if($action==='members'){
        $rows=PortalV2::query('SELECT u.*,m.must_change_password,m.approval_status,m.archived_at FROM users u JOIN pnp_v2_accounts m ON m.user_id=u.id ORDER BY u.id DESC LIMIT 500')->fetchAll();$users=[];
        foreach($rows as $row){$u=PortalV2::publicUser($row);$u['grants']=[];foreach(PortalV2::APPS as $id=>$app){$access=PortalV2::access($row,$id);$revision=PortalV2::query('SELECT revision FROM pnp_v2_grants WHERE user_id=? AND app_id=?',[$u['id'],$id])->fetchColumn();$u['grants'][$id]=array_merge($access,['revision'=>(int)($revision?:0)]);}$users[]=$u;}
        $requests=PortalV2::query("SELECT r.id,r.user_id,r.created_at,u.username,u.first_name,u.last_name FROM pnp_v2_reset_requests r JOIN users u ON u.id=r.user_id WHERE r.status='pending' ORDER BY r.id DESC LIMIT 100")->fetchAll();
        $departments=PortalV2::query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
        PortalV2::json(['ok'=>true,'users'=>$users,'departments'=>$departments,'reset_requests'=>$requests,'total'=>(int)PortalV2::query('SELECT COUNT(*) FROM users')->fetchColumn()]);
    }
    if($action==='audit'){
        $rows=PortalV2::query('SELECT a.*,u.username AS actor_name,t.username AS target_name FROM pnp_v2_audit a LEFT JOIN users u ON u.id=a.actor_id LEFT JOIN users t ON t.id=a.target_id ORDER BY a.id DESC LIMIT 200')->fetchAll();PortalV2::json(['ok'=>true,'events'=>$rows]);
    }
    if($action==='save_member'){
        PortalV2::db()->beginTransaction();lockMasters();$id=(int)($input['id']??0);
        if(!$id){$u=createMember($input,false,$actorId);}else{
            $before=memberRow($id,true);$fields=profileFields($input);$master=!empty($input['is_portal_admin'])?1:0;
            assertMasterSurvives($id,$actorId,(int)$before['is_portal_admin']===1&&$master===0);
            PortalV2::query('UPDATE users SET first_name=?,last_name=?,email=?,phone=?,department=?,primary_position=?,is_portal_admin=? WHERE id=?',[$fields['first_name'],$fields['last_name'],$fields['email'],$fields['phone'],$fields['department'],$fields['primary_position'],$master,$id]);
            if((int)$before['is_portal_admin']!==$master)PortalV2::revoke($id);
            $u=PortalV2::publicUser(memberRow($id));PortalV2::audit($actorId,$id,'member.update',PortalV2::publicUser($before),$u);
        }PortalV2::db()->commit();PortalV2::json(['ok'=>true,'user'=>$u,'message'=>'บันทึกบัญชีสมาชิกแล้ว']);
    }
    if($action==='member_state'){
        $id=(int)($input['id']??0);$state=(string)($input['state']??'');if(!in_array($state,['approve','active','suspended','archive','restore'],true))throw new PortalProblem(400,'สถานะไม่ถูกต้อง');
        PortalV2::db()->beginTransaction();lockMasters();$before=memberRow($id,true);
        if((int)$before['is_portal_admin']===1&&in_array($state,['suspended','archive'],true))assertMasterSurvives($id,$actorId,true);
        if($before['archived_at']&&!in_array($state,['restore','archive'],true))throw new PortalProblem(409,'ต้องกู้คืนบัญชีจากคลังก่อน');
        if($state==='active'&&$before['approval_status']!=='approved')throw new PortalProblem(409,'ต้องอนุมัติการสมัครก่อนเปิดบัญชี');
        if($state==='approve'){PortalV2::query("UPDATE pnp_v2_accounts SET approval_status='approved' WHERE user_id=?",[$id]);PortalV2::query("UPDATE users SET status='active' WHERE id=?",[$id]);}
        elseif($state==='archive'){PortalV2::query('UPDATE pnp_v2_accounts SET archived_at=UTC_TIMESTAMP() WHERE user_id=?',[$id]);PortalV2::query("UPDATE users SET status='suspended' WHERE id=?",[$id]);}
        elseif($state==='restore'){PortalV2::query('UPDATE pnp_v2_accounts SET archived_at=NULL WHERE user_id=?',[$id]);PortalV2::query("UPDATE users SET status='suspended' WHERE id=?",[$id]);}
        else PortalV2::query('UPDATE users SET status=? WHERE id=?',[$state,$id]);
        PortalV2::revoke($id);PortalV2::audit($actorId,$id,'member.'.$state,PortalV2::publicUser($before),PortalV2::publicUser(memberRow($id)));PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'เปลี่ยนสถานะบัญชีแล้ว โดยเก็บข้อมูลและประวัติงานเดิมไว้']);
    }
    if($action==='reset_password'){
        $id=(int)($input['id']??0);if($id===$actorId)throw new PortalProblem(400,'กรุณาเปลี่ยนรหัสผ่านของตนเองในเมนูบัญชี');
        $hash=PortalV2::password((string)($input['password']??''));PortalV2::db()->beginTransaction();memberRow($id,true);
        PortalV2::query('UPDATE users SET password_hash=? WHERE id=?',[$hash,$id]);PortalV2::query('UPDATE pnp_v2_accounts SET must_change_password=1,auth_version=auth_version+1 WHERE user_id=?',[$id]);PortalV2::query("UPDATE pnp_v2_reset_requests SET status='resolved',resolved_at=UTC_TIMESTAMP() WHERE user_id=? AND status='pending'",[$id]);PortalV2::audit($actorId,$id,'password.reset',[],['must_change_password'=>true,'sessions_revoked'=>true]);PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'ตั้งรหัสผ่านชั่วคราวแล้ว สมาชิกต้องเปลี่ยนเมื่อเข้าสู่ระบบ']);
    }
    if($action==='save_grant'){
        $id=(int)($input['user_id']??0);$app=(string)($input['app_id']??'');$role=(string)($input['role']??'none');$rights=$input['rights']??[];$scope=(string)($input['scope']??'all');$value=trim((string)($input['scope_value']??''));
        if(!isset(PortalV2::APPS[$app])||!in_array($role,PortalV2::APPS[$app]['roles'],true)||!is_array($rights)||array_diff($rights,PortalV2::RIGHTS)||!in_array($scope,['all','department'],true))throw new PortalProblem(400,'ข้อมูลสิทธิ์ไม่ถูกต้อง');
        $rights=array_values(array_unique($rights));if($role==='none')$rights=[];if($rights&&!in_array('view',$rights,true))throw new PortalProblem(400,'สิทธิ์ทำงานต้องมีสิทธิ์ดูข้อมูลด้วย');
        if($role!=='admin'&&(in_array('manage_users',$rights,true)||in_array('manage_settings',$rights,true)))throw new PortalProblem(400,'การจัดการผู้ใช้และตั้งค่าต้องเป็นผู้ดูแลระบบย่อย');
        if($scope==='department'&&($value===''||!PortalV2::query('SELECT id FROM users WHERE department=? LIMIT 1',[$value])->fetchColumn()))throw new PortalProblem(400,'กรุณาเลือกแผนกจากทะเบียนกลาง');
        if($scope!=='all'&&($app==='pnp-go'||in_array('manage_settings',$rights,true)))throw new PortalProblem(400,'PNP Go และสิทธิ์ตั้งค่าระบบรุ่นนี้ต้องใช้ขอบเขตทั้งองค์กร');
        if($role!=='admin'&&array_diff($rights,PortalV2::legacyRights($role)))throw new PortalProblem(400,'งานที่เลือกไม่สอดคล้องกับบทบาท กรุณาเลือกบทบาทผู้ดูแลหากต้องการสิทธิ์เพิ่มเติม');
        if($scope==='all')$value='';PortalV2::db()->beginTransaction();$target=memberRow($id,true);if((int)$target['is_portal_admin']===1)throw new PortalProblem(409,'ผู้ดูแลสูงสุดมีสิทธิ์บริหารทุกระบบอยู่แล้ว');
        $before=PortalV2::query('SELECT * FROM pnp_v2_grants WHERE user_id=? AND app_id=? FOR UPDATE',[$id,$app])->fetch();$revision=(int)($before['revision']??0);
        if((int)($input['revision']??-1)!==$revision)throw new PortalProblem(409,'สิทธิ์ถูกแก้ไขโดยผู้ดูแลคนอื่น กรุณาโหลดข้อมูลใหม่');
        PortalV2::query('INSERT INTO app_roles (user_id,app_id,role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)',[$id,$app,$role]);
        PortalV2::query('INSERT INTO pnp_v2_grants (user_id,app_id,rights_json,scope,scope_value,revision) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE rights_json=VALUES(rights_json),scope=VALUES(scope),scope_value=VALUES(scope_value),revision=revision+1',[$id,$app,json_encode($rights),$scope,$value]);PortalV2::audit($actorId,$id,'access.change',$before?:[],['app_id'=>$app,'role'=>$role,'rights'=>$rights,'scope'=>$scope,'scope_value'=>$value]);PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'กำหนดสิทธิ์แล้ว มีผลกับคำขอถัดไป']);
    }
    if($action==='system_status'){
        $app=(string)($input['app_id']??'');$status=(string)($input['status']??'');if(PortalV2::appLocked($app)&&$status!=='disabled')throw new PortalProblem(409,'ระบบนี้พักบริการระหว่างเชื่อมสิทธิ์ ยังเปิดจากหน้านี้ไม่ได้');if(!isset(PortalV2::APPS[$app])||!in_array($status,['active','disabled','coming_soon'],true))throw new PortalProblem(400,'สถานะระบบไม่ถูกต้อง');
        PortalV2::db()->beginTransaction();$before=PortalV2::query('SELECT status FROM app_status WHERE app_id=? FOR UPDATE',[$app])->fetchColumn();
        if(isset($input['expected_status'])&&$input['expected_status']!==$before)throw new PortalProblem(409,'สถานะถูกเปลี่ยนแล้ว กรุณาโหลดใหม่');
        PortalV2::query('INSERT INTO app_status (app_id,status) VALUES (?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)',[$app,$status]);PortalV2::audit($actorId,null,'system.status',['app_id'=>$app,'status'=>$before],['app_id'=>$app,'status'=>$status]);PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'เปลี่ยนสถานะบริการแล้ว ข้อมูลเดิมยังคงอยู่']);
    }
    if($action==='save_settings'){
        PortalV2::db()->beginTransaction();foreach(['portal_name','organization','notice','support_text'] as $key){$value=trim((string)($input[$key]??''));if(mb_strlen($value)>2000)throw new PortalProblem(400,'ข้อความยาวเกินกำหนด');if(in_array($key,['portal_name','organization'],true)&&$value==='')throw new PortalProblem(400,'ชื่อระบบและหน่วยงานห้ามว่าง');PortalV2::query('INSERT INTO pnp_v2_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);}PortalV2::audit($actorId,null,'portal.settings',[],array_intersect_key($input,array_flip(['portal_name','organization','notice','support_text'])));PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'บันทึกการตั้งค่า Portal แล้ว']);
    }
    throw new PortalProblem(404,'ไม่พบคำสั่ง');
}catch(PortalProblem $e){PortalV2::rollback();PortalV2::json(['ok'=>false,'error'=>$e->getMessage(),'kind'=>$e->kind],$e->status);}
catch(PDOException $e){PortalV2::rollback();error_log('Portal V2 database operation failed: '.$e->getCode().' driver='.(int)($e->errorInfo[1]??0).' line='.$e->getLine());PortalV2::json(['ok'=>false,'error'=>$e->getCode()==='23000'?'ชื่อบัญชีหรืออีเมลนี้มีอยู่แล้ว':'ไม่สามารถบันทึกข้อมูลได้ กรุณาติดต่อผู้ดูแล'], $e->getCode()==='23000'?409:500);}
catch(Throwable $e){PortalV2::rollback();error_log('Portal V2 operation failed: '.get_class($e));PortalV2::json(['ok'=>false,'error'=>'ไม่สามารถดำเนินการได้ กรุณาติดต่อผู้ดูแล'],500);}
