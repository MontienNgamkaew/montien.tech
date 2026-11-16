<?php
/** Reviewed additive V2 patch; admin CLI only, outside webroot. No DB restore. */
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);umask(0077);
[$script,$mode,$release,$root]=$argv+[null,null,null,null];
if(!in_array($mode,['check','deploy','rollback'],true)||!is_dir($release)||!is_file($root.'/.env'))throw new RuntimeException('Explicit root/release required');
$m=json_decode(file_get_contents($release.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);if($m['release']!==basename($release))throw new RuntimeException('Release mismatch');
$lock=fopen('/home/u651170081/.config/montien-tech-deploy/deployment.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Deployment locked');
require_once $root.'/api/env.php';
function patchSave(string $path,string $bytes):void{$missing=[];$dir=dirname($path);while(!is_dir($dir)){$missing[]=$dir;$dir=dirname($dir);}foreach(array_reverse($missing) as $new){if(!mkdir($new,0755)&&!is_dir($new))throw new RuntimeException('Directory failed');chmod($new,0755);}$tmp=$path.'.pending-'.bin2hex(random_bytes(4));if(file_put_contents($tmp,$bytes)===false)throw new RuntimeException('Write failed');chmod($tmp,0644);if(!rename($tmp,$path))throw new RuntimeException('Rename failed');}
function patchDb(string $prefix):PDO{return new PDO('mysql:host='.env($prefix.'HOST','localhost').';dbname='.env($prefix.'NAME'),env($prefix.'USER'),env($prefix.'PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
function patchData(array $dbs,array $tables):array{$result=[];foreach($tables as $label=>$list)foreach($list as $table){if(!preg_match('/^[A-Za-z0-9_]+$/D',$table))throw new RuntimeException('Bad table');$rows=$dbs[$label]->query('SELECT * FROM `'.$table.'`')->fetchAll();$hashes=array_map(static fn($r)=>hash('sha256',json_encode($r)),$rows);sort($hashes);$result[$label][$table]=['rows'=>count($rows),'hash'=>hash('sha256',implode('',$hashes))];}return $result;}
foreach($m['files'] as $f){$p=$f['path'];$parent=$root;foreach(explode('/',$p) as $part){$parent.='/'.$part;if(is_link($parent))throw new RuntimeException('Symlink in target');}if(!in_array($p,['api/v2/core.php','api/v2/index.php','api/v2/grants.php','api/portal_access.php','portal-v2/index.html','portal-v2/assets/portal.js','portal-v2/assets/portal.css','pnp-academix/auth.php','pnp-academix/sso.php','pnp-academix/index.php','pnp-academix/teacher/dashboard.php','pnp-academix/admin/history.php','pnp-academix/admin/print_report.php','pnp-academix/admin/review_teacher.php','pnp-academix/admin/overview.php','pnp-academix/admin/review.php','pnp-go/app/Controllers/DashboardController.php','upload_serve.php','pnp-academix/teacher/submit.php','pnp-academix/teacher/preview.php','pnp-academix/assets/academix.css','pnp-academix/assets/academix.js','pnp-academix/includes/.htaccess','pnp-academix/includes/academix-ui.php','pnp-academix/includes/preview-support.php','pnp-academix/includes/teacher-dashboard-view.php','pnp-academix/admin/preview.php','pnp-academix/assets/academix-admin.js','pnp-academix/includes/admin-support.php','pnp-academix/includes/admin-document-frame.php','pnp-academix/includes/admin-overview-view.php','pnp-academix/includes/admin-teacher-view.php','pnp-academix/includes/admin-review-view.php'],true))throw new RuntimeException('Outside patch scope');if(is_link($root.'/'.$p)||hash_file('sha256',$release.'/payload/'.$p)!==$f['after'])throw new RuntimeException('Unsafe payload');if($mode==='rollback'&&(is_file($root.'/'.$p)?hash_file('sha256',$root.'/'.$p):null)!==$f['after'])throw new RuntimeException('Rollback live drift');if($mode==='check'||$mode==='deploy')if((is_file($root.'/'.$p)?hash_file('sha256',$root.'/'.$p):null)!==$f['before'])throw new RuntimeException('Live drift: '.$p);if(str_ends_with($p,'.php')){$proc=proc_open([PHP_BINARY,'-l',$release.'/payload/'.$p],[0=>['file','/dev/null','r'],1=>['file',$release.'/lint.log','a'],2=>['file',$release.'/lint.log','a']],$pipes);if(!is_resource($proc)||proc_close($proc)!==0)throw new RuntimeException('PHP lint failed');}}
$dbs=['portal'=>patchDb('DB_'),'pnpgo'=>patchDb('PNPGO_DB_'),'academix'=>patchDb('ACADEMIX_DB_')];
$runtime=$root.'/api/v2/runtime.local.json';$flag=$root.'/api/v2/maintenance.flag';$switch=json_decode(file_get_contents($runtime),true,512,JSON_THROW_ON_ERROR);if(empty($switch['enabled'])||!in_array('pnp-lesson-plan',$switch['paused_apps'],true))throw new RuntimeException('Unexpected active runtime');
$tables=[];foreach($dbs as $label=>$db)$tables[$label]=$db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
if($mode==='check'){echo json_encode(['preflight'=>true,'php'=>PHP_VERSION,'files'=>count($m['files']),'tables'=>$tables,'production_modified'=>false]).PHP_EOL;exit;}
$rollback=function()use($m,$root,$release,$runtime,$flag):void{foreach(array_reverse($m['files']) as $f)if($f['before']!==null)patchSave($root.'/'.$f['path'],file_get_contents($release.'/rollback/'.$f['path']));if(is_file($release.'/runtime-before.json'))patchSave($runtime,file_get_contents($release.'/runtime-before.json'));if(is_file($flag))rename($flag,$release.'/maintenance-ended-'.time());};
if($mode==='rollback'){
 $q=$dbs['portal']->query("SELECT COUNT(*) FROM pnp_v2_role_profiles");if($q->fetchColumn()||(int)$dbs['portal']->query('SELECT COUNT(*) FROM pnp_v2_staff_assignments')->fetchColumn())throw new RuntimeException('New role assignments exist; use compatible forward fix');
 $rollback();echo "Code restored; schema retained; no data restored or deleted.\n";exit;
}
if(is_file($flag)||is_file($release.'/installed.json'))throw new RuntimeException('Maintenance/release already present');
foreach($m['files'] as $f)if($f['before']!==null){$to=$release.'/rollback/'.$f['path'];if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($root.'/'.$f['path'],$to);}
copy($runtime,$release.'/runtime-before.json');$envHash=hash_file('sha256',$root.'/.env');
patchSave($flag,$m['release']);chmod($flag,0600);echo "Maintenance enabled; draining in-flight requests.\n";flush();sleep(8);
try{
 $before=patchData($dbs,$tables);file_put_contents($release.'/before-data.json',json_encode($before));
 $proc=proc_open([PHP_BINARY,'/home/u651170081/.config/montien-tech-deploy/backup.php',$root,$m['backup']],[0=>['file','/dev/null','r'],1=>['file',$release.'/backup.log','a'],2=>['file',$release.'/backup.log','a']],$pipes);if(!is_resource($proc)||proc_close($proc)!==0)throw new RuntimeException('Backup failed');
 foreach(['HOST','NAME','USER','PASS','PORT'] as $part){$k='DB_'.$part;$v=env($k,null);if($v!==null)putenv($k.'='.$v);}
 $proc=proc_open([PHP_BINARY,$release.'/tools/scripts/maintenance/migrate-portal-v2-multirole.php','--apply','--database='.env('DB_NAME')],[0=>['file','/dev/null','r'],1=>['file',$release.'/migration.log','a'],2=>['file',$release.'/migration.log','a']],$pipes);if(!is_resource($proc)||proc_close($proc)!==0)throw new RuntimeException('Migration failed');
 $after=patchData($dbs,$tables);
 // Only the new migration marker may differ in a pre-existing table.
 $beforeMarker=$before['portal']['pnp_v2_migrations'];$afterMarker=$after['portal']['pnp_v2_migrations'];unset($before['portal']['pnp_v2_migrations'],$after['portal']['pnp_v2_migrations']);
 if($before!==$after)throw new RuntimeException('Existing row contents changed');
 if(!in_array('pnp_v2_role_profiles',$tables['portal'],true)&&((int)$dbs['portal']->query('SELECT COUNT(*) FROM pnp_v2_role_profiles')->fetchColumn()!==0||(int)$dbs['portal']->query('SELECT COUNT(*) FROM pnp_v2_staff_assignments')->fetchColumn()!==0))throw new RuntimeException('Unexpected role profile data');
 file_put_contents($release.'/after-data.json',json_encode($after));
 // Install the new dependency first while all requests remain behind maintenance.
 $new=$release.'/payload/api/v2/grants.php';if(is_file($new))patchSave($root.'/api/v2/grants.php',file_get_contents($new));
 foreach($m['files'] as $f)patchSave($root.'/'.$f['path'],file_get_contents($release.'/payload/'.$f['path']));
 foreach($m['files'] as $f)if(hash_file('sha256',$root.'/'.$f['path'])!==$f['after'])throw new RuntimeException('Installed mismatch');
 if(hash_file('sha256',$root.'/.env')!==$envHash)throw new RuntimeException('Environment changed');
 $switch['patch_release']=$m['release'];$switch['multirole_enabled_utc']=gmdate(DATE_ATOM);patchSave($runtime,json_encode($switch));
 $state=['release'=>$m['release'],'files'=>count($m['files']),'old_data_unchanged'=>true,'migration_marker_before'=>$beforeMarker,'migration_marker_after'=>$afterMarker,'env_unchanged'=>true,'lesson_plan_still_paused'=>true,'backup'=>$m['backup'],'activated_utc'=>gmdate(DATE_ATOM)];file_put_contents($release.'/installed.json',json_encode($state,JSON_PRETTY_PRINT));
 rename($flag,$release.'/maintenance-ended-'.time());echo json_encode($state,JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){$rollback();throw $e;}
