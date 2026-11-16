<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__,2).'/api/v2/core.php';
$db=PortalV2::db();$name=$db->query('SELECT DATABASE()')->fetchColumn();$apply=in_array('--apply',$argv,true);
echo json_encode(['database'=>$name,'mode'=>$apply?'apply':'preview','operations'=>'Create role profiles and staff assignments; no old row updates or deletes']).PHP_EOL;
if(!$apply)exit;if(!in_array('--database='.$name,$argv,true))throw new RuntimeException('Exact database confirmation required');
if((int)$db->query("SELECT GET_LOCK('pnp_portal_v2_migration',10)")->fetchColumn()!==1)throw new RuntimeException('Migration locked');
try{
 $db->exec("CREATE TABLE IF NOT EXISTS pnp_v2_role_profiles (user_id INT NOT NULL,app_id VARCHAR(50) NOT NULL,roles_json TEXT NOT NULL,self_rights_json TEXT NOT NULL,can_delegate TINYINT NOT NULL DEFAULT 0,delegable_rights_json TEXT NOT NULL,PRIMARY KEY(user_id,app_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $db->exec("CREATE TABLE IF NOT EXISTS pnp_v2_staff_assignments (user_id INT NOT NULL,app_id VARCHAR(50) NOT NULL,assigned_by INT NOT NULL,rights_json TEXT NOT NULL,scope VARCHAR(20) NOT NULL,scope_value VARCHAR(255) NOT NULL DEFAULT '',revision INT NOT NULL DEFAULT 1,revoked_at DATETIME NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(user_id,app_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $db->exec('INSERT IGNORE INTO pnp_v2_migrations(version) VALUES(3)');
}finally{$db->query("SELECT RELEASE_LOCK('pnp_portal_v2_migration')");}
echo "Multi-role schema ready; existing user IDs, passwords, grants and documents unchanged.\n";
