<?php
declare(strict_types=1);
function portalGrantScope(string $scope,string $value): array {
    if(!in_array($scope,['all','department'],true))throw new PortalProblem(400,'ขอบเขตไม่ถูกต้อง');
    if($scope==='department'&&($value===''||!PortalV2::query('SELECT id FROM users WHERE department=? LIMIT 1',[$value])->fetchColumn()))throw new PortalProblem(400,'กรุณาเลือกแผนกในทะเบียนกลาง');
    return [$scope,$scope==='all'?'':$value];
}
function portalGrantRights(mixed $rights): array {
    if(!is_array($rights)||array_diff($rights,PortalV2::RIGHTS))throw new PortalProblem(400,'สิทธิ์ไม่ถูกต้อง');
    $rights=array_values(array_unique($rights));if($rights&&!in_array('view',$rights,true))throw new PortalProblem(400,'ต้องมีสิทธิ์ดูข้อมูลด้วย');return $rights;
}
function portalSaveGrant(array $input,array $actor): void {
    if(!PortalV2::multiRoleReady())throw new PortalProblem(503,'ยังไม่ได้เตรียมระบบหลายบทบาท');
    $id=(int)($input['user_id']??0);$app=(string)($input['app_id']??'');$roles=$input['roles']??[(string)($input['role']??'none')];
    if(!isset(PortalV2::APPS[$app])||!is_array($roles)||array_diff($roles,array_merge(PortalV2::APPS[$app]['roles'],['staff'])))throw new PortalProblem(400,'บทบาทไม่ถูกต้อง');
    $roles=array_values(array_unique(array_diff($roles,['none'])));$rights=portalGrantRights($input['rights']??[]);if(!$roles)$rights=[];
    $priority=['admin','staff','director','deputy_director','supply_head','department_head','teacher','driver','user'];$ranked=array_values(array_intersect($priority,$roles));$role=array_intersect($roles,['admin','staff'])?'admin':($ranked[0]??'none');
    if($app==='pnp-go'&&count(array_intersect($roles,['director','deputy_director','supply_head']))>1)throw new PortalProblem(400,'ระบบรถยังไม่รองรับผู้อนุมัติหลายระดับในบัญชีเดียว กรุณาเลือกบทบาทผู้ดูแลหากต้องอนุมัติทุกระดับ');
    if($role!=='admin'&&array_diff($rights,array_unique(array_merge(...array_map([PortalV2::class,'legacyRights'],$roles?:['none'])))))throw new PortalProblem(400,'สิทธิ์ไม่สอดคล้องกับบทบาท');
    [$scope,$value]=portalGrantScope((string)($input['scope']??'all'),trim((string)($input['scope_value']??'')));
    if($scope!=='all'&&($app==='pnp-go'||in_array('manage_settings',$rights,true)))throw new PortalProblem(400,'ระบบรถและสิทธิ์ตั้งค่าต้องมีขอบเขตทั้งองค์กร');
    $self=portalGrantRights($input['self_rights']??(array_intersect($roles,['teacher','user','department_head'])?array_values(array_intersect($rights,['view','create','edit','export'])):[]));
    if(array_diff($self,['view','create','edit','export'])||($self&&!array_intersect($roles,['teacher','user','department_head'])))throw new PortalProblem(400,'สิทธิ์งานส่วนตัวต้องมีบทบาทผู้ใช้งานหรือครูผู้สอน');
    $delegate=!empty($input['can_delegate']);$ceiling=portalGrantRights($input['delegable_rights']??[]);
    if(($delegate||$ceiling)&&!in_array('admin',$roles,true))throw new PortalProblem(400,'เฉพาะบทบาทผู้ดูแลที่ได้รับมอบหมายจึงแต่งตั้งเจ้าหน้าที่ได้');
    if(array_diff($ceiling,$rights))throw new PortalProblem(400,'เพดานสิทธิ์ส่งต่อต้องไม่เกินสิทธิ์ของผู้ดูแล');
    if(!$delegate)$ceiling=[];
    PortalV2::db()->beginTransaction();$target=memberRow($id,true);if((int)$target['is_portal_admin']===1)throw new PortalProblem(409,'ผู้ดูแลสูงสุดมีสิทธิ์ทุกระบบอยู่แล้ว');
    $before=PortalV2::query('SELECT * FROM pnp_v2_grants WHERE user_id=? AND app_id=? FOR UPDATE',[$id,$app])->fetch();$revision=(int)($before['revision']??0);
    if((int)($input['revision']??-1)!==$revision)throw new PortalProblem(409,'สิทธิ์ถูกเปลี่ยนแล้ว กรุณาโหลดใหม่');
    PortalV2::query('INSERT INTO app_roles(user_id,app_id,role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)',[$id,$app,$role]);
    PortalV2::query('INSERT INTO pnp_v2_grants(user_id,app_id,rights_json,scope,scope_value,revision) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE rights_json=VALUES(rights_json),scope=VALUES(scope),scope_value=VALUES(scope_value),revision=revision+1',[$id,$app,json_encode($rights),$scope,$value]);
    PortalV2::query('INSERT INTO pnp_v2_role_profiles(user_id,app_id,roles_json,self_rights_json,can_delegate,delegable_rights_json) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE roles_json=VALUES(roles_json),self_rights_json=VALUES(self_rights_json),can_delegate=VALUES(can_delegate),delegable_rights_json=VALUES(delegable_rights_json)',[$id,$app,json_encode($roles),json_encode($self),(int)$delegate,json_encode($ceiling)]);
    PortalV2::query('UPDATE pnp_v2_staff_assignments SET revoked_at=UTC_TIMESTAMP(),revision=revision+1 WHERE user_id=? AND app_id=? AND revoked_at IS NULL',[$id,$app]);
    PortalV2::audit((int)$actor['id'],$id,'access.change',$before?:[],['app_id'=>$app,'roles'=>$roles,'rights'=>$rights,'self_rights'=>$self,'scope'=>$scope,'scope_value'=>$value,'can_delegate'=>$delegate,'delegable_rights'=>$ceiling]);
    PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'บันทึกหลายบทบาทและเพดานสิทธิ์แล้ว']);
}
function portalStaffAction(string $action,array $input): void {
    if(!PortalV2::multiRoleReady())throw new PortalProblem(503,'ยังไม่ได้เตรียมระบบมอบหมายเจ้าหน้าที่');
    $app=(string)($input['app_id']??$_GET['app']??'');$context=PortalV2::requireApp($app);$actor=$context['user'];$access=$context['access'];
    if(!$access['can_delegate'])throw new PortalProblem(403,'ไม่ได้รับสิทธิ์แต่งตั้งเจ้าหน้าที่');
    if($action==='staff'){
        $rows=PortalV2::query("SELECT u.id,u.username,u.first_name,u.last_name,u.department,u.is_portal_admin FROM users u JOIN pnp_v2_accounts a ON a.user_id=u.id WHERE u.status='active' AND u.is_portal_admin=0 AND a.approval_status='approved' AND a.archived_at IS NULL ORDER BY u.first_name LIMIT 500")->fetchAll();
        $rows=pnpStaffFilter($context,$rows);$assignments=PortalV2::query('SELECT user_id,rights_json,scope,scope_value,revision,revoked_at FROM pnp_v2_staff_assignments WHERE assigned_by=? AND app_id=?',[$actor['id'],$app])->fetchAll();
        PortalV2::json(['ok'=>true,'members'=>$rows,'assignments'=>$assignments,'ceiling'=>$access['delegable_rights'],'scope'=>$access['scope'],'scope_value'=>$access['scope_value']]);
    }
    $id=(int)($input['user_id']??0);if($id===(int)$actor['id'])throw new PortalProblem(403,'เพิ่มสิทธิ์ให้ตนเองไม่ได้');
    PortalV2::db()->beginTransaction();$target=memberRow($id,true);
    if((int)$target['is_portal_admin']===1||$target['status']!=='active'||$target['approval_status']!=='approved'||$target['archived_at'])throw new PortalProblem(403,'บัญชีนี้ไม่สามารถรับการมอบหมายได้');
    if(!PortalV2::scopeAllows($context,$target['username']))throw new PortalProblem(403,'สมาชิกอยู่นอกขอบเขตที่รับผิดชอบ');
    $direct=PortalV2::access($target,$app,'default',false);
    if($direct['has_admin_workspace'])throw new PortalProblem(403,'แก้ไขสิทธิ์ผู้ดูแลหรือเจ้าหน้าที่ที่ผู้ดูแลสูงสุดกำหนดไม่ได้');
    $before=PortalV2::query('SELECT * FROM pnp_v2_staff_assignments WHERE user_id=? AND app_id=? FOR UPDATE',[$id,$app])->fetch();
    if($before&&(int)$before['assigned_by']!==(int)$actor['id'])throw new PortalProblem(403,'แก้ไขการมอบหมายของผู้ดูแลคนอื่นไม่ได้');
    if((int)($input['revision']??-1)!==(int)($before['revision']??0))throw new PortalProblem(409,'การมอบหมายถูกเปลี่ยนแล้ว กรุณาโหลดใหม่');
    if($action==='revoke_staff'){
        if(!$before)throw new PortalProblem(404,'ไม่พบการมอบหมาย');
        PortalV2::query('UPDATE pnp_v2_staff_assignments SET revoked_at=UTC_TIMESTAMP(),revision=revision+1 WHERE user_id=? AND app_id=?',[$id,$app]);
    }else{
        $rights=portalGrantRights($input['rights']??[]);if(!$rights||array_diff($rights,$access['delegable_rights']))throw new PortalProblem(403,'สิทธิ์เกินเพดานที่ได้รับอนุญาตให้ส่งต่อ');
        [$scope,$value]=portalGrantScope((string)($input['scope']??$access['scope']),trim((string)($input['scope_value']??$access['scope_value'])));
        if($access['scope']!=='all'&&($scope!==$access['scope']||$value!==$access['scope_value']))throw new PortalProblem(403,'ขยายขอบเขตเกินสิทธิ์ของตนเองไม่ได้');
        if($app==='pnp-go'&&$scope!=='all')throw new PortalProblem(400,'ระบบรถยังไม่รองรับขอบเขตแผนก');
        PortalV2::query('INSERT INTO pnp_v2_staff_assignments(user_id,app_id,assigned_by,rights_json,scope,scope_value) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rights_json=VALUES(rights_json),scope=VALUES(scope),scope_value=VALUES(scope_value),revoked_at=NULL,revision=revision+1',[$id,$app,$actor['id'],json_encode($rights),$scope,$value]);
    }
    PortalV2::audit((int)$actor['id'],$id,$action==='revoke_staff'?'staff.revoke':'staff.assign',$before?:[],['app_id'=>$app,'rights'=>$rights??[],'scope'=>$scope??null,'scope_value'=>$value??null]);PortalV2::db()->commit();PortalV2::json(['ok'=>true,'message'=>'บันทึกการมอบหมายแล้ว โดยคงสิทธิ์งานส่วนตัวเดิม']);
}
function pnpStaffFilter(array $context,array $rows): array {return array_values(array_filter($rows,static fn($u)=>(int)$u['id']!==(int)$context['user']['id']&&PortalV2::scopeAllows($context,$u['username'])&&!PortalV2::access($u,$context['access']['app_id'],'default',false)['has_admin_workspace']));}
