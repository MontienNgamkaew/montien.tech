<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__,2).'/api/v2/core.php';
$apply=in_array('--apply',$argv,true);$db=PortalV2::db();$database=$db->query('SELECT DATABASE()')->fetchColumn();
$confirm='--database='.$database;
if($apply&&!in_array($confirm,$argv,true)){fwrite(STDERR,"Apply requires --database matching the configured database.\n");exit(1);}
$count=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();$roles=$db->query('SELECT user_id,app_id,role FROM app_roles')->fetchAll();
echo json_encode(['database'=>$database,'users_preserved'=>$count,'existing_roles'=>count($roles),'mode'=>$apply?'apply':'preview','operations'=>'CREATE new pnp_v2_* tables; INSERT IGNORE metadata/grants; no user/password/ID updates or deletes'],JSON_PRETTY_PRINT).PHP_EOL;
if(!$apply)exit;
if((int)$db->query("SELECT GET_LOCK('pnp_portal_v2_migration',10)")->fetchColumn()!==1)throw new RuntimeException('Migration locked');
$sql=[
"CREATE TABLE IF NOT EXISTS pnp_v2_migrations (version INT PRIMARY KEY, completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS pnp_v2_accounts (user_id INT PRIMARY KEY,auth_version INT NOT NULL DEFAULT 1,must_change_password TINYINT NOT NULL DEFAULT 0,approval_status VARCHAR(20) NOT NULL DEFAULT 'approved',archived_at DATETIME NULL,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS pnp_v2_sessions (token_hash CHAR(64) PRIMARY KEY,user_id INT NOT NULL,auth_version INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,expires_at DATETIME NOT NULL,revoked_at DATETIME NULL,INDEX(user_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS pnp_v2_grants (user_id INT NOT NULL,app_id VARCHAR(50) NOT NULL,rights_json TEXT NOT NULL,scope VARCHAR(20) NOT NULL DEFAULT 'all',scope_value VARCHAR(255) NOT NULL DEFAULT '',revision INT NOT NULL DEFAULT 1,PRIMARY KEY(user_id,app_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS pnp_v2_audit (id BIGINT PRIMARY KEY AUTO_INCREMENT,actor_id INT NULL,target_id INT NULL,action VARCHAR(80) NOT NULL,before_json LONGTEXT NOT NULL,after_json LONGTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(target_id),INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS pnp_v2_settings (setting_key VARCHAR(60) PRIMARY KEY,setting_value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS pnp_v2_reset_requests (id BIGINT PRIMARY KEY AUTO_INCREMENT,user_id INT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,resolved_at DATETIME NULL,INDEX(user_id,status)) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS pnp_v2_rate_limits (bucket CHAR(64) PRIMARY KEY,attempts INT NOT NULL,window_start BIGINT NOT NULL) ENGINE=InnoDB",
];
try{
    foreach($sql as $statement)$db->exec($statement);
    $db->beginTransaction();
    $db->exec('INSERT IGNORE INTO pnp_v2_accounts (user_id) SELECT id FROM users');
    $insert=$db->prepare('INSERT IGNORE INTO pnp_v2_grants (user_id,app_id,rights_json) VALUES (?,?,?)');
    $unknown=[];foreach($roles as $r){if(!isset(PortalV2::APPS[$r['app_id']])||!in_array($r['role'],PortalV2::APPS[$r['app_id']]['roles'],true)){$unknown[]=['user_id'=>(int)$r['user_id'],'app_id'=>$r['app_id'],'role'=>$r['role']];continue;}$insert->execute([$r['user_id'],$r['app_id'],json_encode(PortalV2::legacyRights($r['role']))]);}
    $db->exec('INSERT IGNORE INTO pnp_v2_migrations (version) VALUES (1)');$db->commit();
    if((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==$count)throw new RuntimeException('User count changed concurrently; review required');
    echo json_encode(['completed'=>true,'user_count_unchanged'=>true,'roles_requiring_review'=>$unknown],JSON_PRETTY_PRINT).PHP_EOL;
}finally{if($db->inTransaction())$db->rollBack();$db->query("SELECT RELEASE_LOCK('pnp_portal_v2_migration')");}
