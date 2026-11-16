<?php
/** Admin-operated reviewed release, outside webroot; never run through the old CI gateway. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);umask(0077);
[$script,$mode,$release,$root]=$argv+[null,null,null,null];
if(!in_array($mode,['check','deploy','rollback'],true)||!is_dir($release)||!is_file($root.'/.env'))throw new RuntimeException('Invalid explicit release/root');
$manifest=json_decode(file_get_contents($release.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
if($manifest['release']!==basename($release)||$manifest['paused_apps']!==['pnp-lesson-plan'])throw new RuntimeException('Unexpected release policy');
$lock=fopen('/home/u651170081/.config/montien-tech-deploy/deployment.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Deployment locked');
require_once $root.'/api/env.php';
function saveFile(string $path,string $bytes,int $mode=0644): void {$temp=$path.'.pending-'.bin2hex(random_bytes(4));if(!is_dir(dirname($path))){mkdir(dirname($path),0755,true);global $root;if(str_starts_with($path,$root.'/')){$dir=dirname($path);while($dir!==$root&&str_starts_with($dir,$root.'/')){chmod($dir,0755);$dir=dirname($dir);}}}if(file_put_contents($temp,$bytes)===false)throw new RuntimeException('Cannot stage file');chmod($temp,$mode);if(!rename($temp,$path))throw new RuntimeException('Cannot replace file');}
function database(string $prefix): PDO {$p=new PDO('mysql:host='.env($prefix.'HOST','localhost').';dbname='.env($prefix.'NAME'),env($prefix.'USER'),env($prefix.'PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
function fingerprints(array $connections,array $tables): array {$out=[];foreach($tables as $label=>$names)foreach($names as $name){if(!preg_match('/^[A-Za-z0-9_]+$/D',$name))throw new RuntimeException('Invalid table');$rows=$connections[$label]->query('SELECT * FROM `'.$name.'`')->fetchAll();$hashes=array_map(static fn($row)=>hash('sha256',json_encode($row)),$rows);sort($hashes);$out[$label][$name]=['rows'=>count($rows),'hash'=>hash('sha256',implode('',$hashes))];}return $out;}
function runMigration(string $file,string $db,string $release): void {$p=proc_open([PHP_BINARY,$release.'/tools/scripts/maintenance/'.$file,'--apply','--database='.$db],[0=>['file','/dev/null','r'],1=>['file',$release.'/migration.log','a'],2=>['file',$release.'/migration.log','a']],$pipes);if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Migration failed; inspect private log');}
function safePath(string $path): void {if(str_contains($path,'..')||str_contains($path,'\\')||!preg_match('~^(?:index\.php|\.htaccess|api/(?:config\.php|verify\.php|portal_access\.php|v2/(?:core\.php|index\.php))|portal-v2/(?:index\.html|assets/portal\.(?:css|js)|maintenance\.php|lesson-plan-unavailable\.php)|pnp-lessonplan/\.htaccess|pnp-academix/(?:\.htaccess|auth\.php|index\.php|sso\.php|admin/(?:history|overview|print_report|review)\.php)|pnp-go/(?:\.htaccess|bootstrap\.php|sso\.php|sync_portal_users\.php|config/app\.php|app/Controllers/(?:Auth|Dashboard)Controller\.php)|pnpman/(?:\.htaccess|index\.php|api/(?:db|get_data)\.php|frontend/dist/(?:index\.html|assets/[A-Za-z0-9_.-]+))|upload_serve\.php)$~D',$path))throw new RuntimeException('Path outside release scope');}
foreach($manifest['files'] as $f){$path=$f['path'];safePath($path);if(is_link($root.'/'.$path)||is_link($release.'/payload/'.$path))throw new RuntimeException('Symlink refused');if(hash_file('sha256',$release.'/payload/'.$path)!==$f['after'])throw new RuntimeException('Payload mismatch: '.$path);if($mode!=='rollback'){$current=is_file($root.'/'.$path)?hash_file('sha256',$root.'/'.$path):null;if($current!==$f['before'])throw new RuntimeException('Production drift: '.$path);}
 if(str_ends_with($path,'.php')){$p=proc_open([PHP_BINARY,'-l',$release.'/payload/'.$path],[0=>['file','/dev/null','r'],1=>['file',$release.'/lint.log','a'],2=>['file',$release.'/lint.log','a']],$pipes);if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('PHP lint failed');}}
$connections=['portal'=>database('DB_'),'pnpgo'=>database('PNPGO_DB_'),'academix'=>database('ACADEMIX_DB_')];
if($mode==='check'){echo json_encode(['preflight'=>true,'files'=>count($manifest['files']),'php'=>PHP_VERSION,'database_connections'=>array_keys($connections),'production_modified'=>false]).PHP_EOL;exit;}
$runtime=$root.'/api/v2/runtime.local.json';$maintenance=$root.'/api/v2/maintenance.flag';
$restore=function()use($manifest,$root,$release,$runtime,$maintenance): void {
 saveFile($runtime,json_encode(['enabled'=>false,'base'=>'','paused_apps'=>['pnp-lesson-plan'],'release'=>$manifest['release'],'rolled_back'=>true]));
 foreach(array_reverse($manifest['files']) as $f)if($f['before']!==null&&$f['path']!=='pnp-lessonplan/.htaccess')saveFile($root.'/'.$f['path'],file_get_contents($release.'/rollback/'.$f['path']));
 // New inert files remain; no deletes, no DB restore. Keep Lesson Plan paused as requested.
 foreach($manifest['files'] as $f)if($f['path']==='.htaccess'&&$f['before']===null)saveFile($root.'/.htaccess',"Options -Indexes\nRewriteEngine On\nRewriteRule ^pnp-lessonplan(?:/|$) portal-v2/lesson-plan-unavailable.php [L]\n");
 if(is_file($maintenance))rename($maintenance,$release.'/maintenance-ended-'.time());
};
if($mode==='rollback'){
 $installed=json_decode((string)file_get_contents($release.'/installed.json'),true,512,JSON_THROW_ON_ERROR);
 $q=$connections['portal']->prepare("SELECT COUNT(*) FROM pnp_v2_audit WHERE id>? AND action NOT IN ('session.login','session.logout')");$q->execute([(int)$installed['audit_baseline']]);$mutations=(int)$q->fetchColumn();
 if($mutations)throw new RuntimeException('V2 settings/data mutations exist; use compatible forward fix instead of legacy authorization rollback');
 $restore();echo "Code rollback complete; data retained; Lesson Plan remains paused.\n";exit;
}
if(is_file($runtime)||is_file($maintenance)||file_exists($release.'/installed.json'))throw new RuntimeException('Release already active or maintenance present');
$envHash=hash_file('sha256',$root.'/.env');
foreach($manifest['files'] as $f)if($f['before']!==null){$to=$release.'/rollback/'.$f['path'];if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);if(!copy($root.'/'.$f['path'],$to))throw new RuntimeException('Cannot back up code');}
// Prepare the maintenance responder, then make its Apache barrier visible.
saveFile($root.'/portal-v2/maintenance.php',file_get_contents($release.'/payload/portal-v2/maintenance.php'));
saveFile($maintenance,'Release '.$manifest['release'],0600);
saveFile($root.'/.htaccess',file_get_contents($release.'/payload/.htaccess'));
echo "Maintenance barrier installed; waiting for in-flight PHP requests.\n";flush();sleep(8);
try{
 $before=fingerprints($connections,$manifest['tables']);saveFile($release.'/before-data.json',json_encode($before),0600);
 // Fresh snapshot under the maintenance barrier, outside webroot; never sent to CI.
 $p=proc_open([PHP_BINARY,'/home/u651170081/.config/montien-tech-deploy/backup.php',$root,$manifest['backup']],[0=>['file','/dev/null','r'],1=>['file',$release.'/backup.log','a'],2=>['file',$release.'/backup.log','a']],$pipes);if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Final backup failed');
 foreach($manifest['files'] as $f)saveFile($root.'/'.$f['path'],file_get_contents($release.'/payload/'.$f['path']));
 foreach(['DB_','PNPGO_DB_','ACADEMIX_DB_'] as $prefix)foreach(['HOST','NAME','USER','PASS','PORT'] as $suffix){$key=$prefix.$suffix;$value=env($key,null);if($value!==null)putenv($key.'='.$value);}
 runMigration('migrate-portal-v2.php',(string)env('DB_NAME'),$release);runMigration('migrate-portal-v2-go.php',(string)env('PNPGO_DB_NAME'),$release);
 $after=fingerprints($connections,$manifest['tables']);saveFile($release.'/after-data.json',json_encode($after),0600);if($before!==$after)throw new RuntimeException('Original row fingerprint changed; no database restoration attempted');
 $portal=$connections['portal'];$portal->beginTransaction();$status=$portal->query("SELECT status FROM app_status WHERE app_id='pnp-lesson-plan' FOR UPDATE")->fetchColumn();$q=$portal->prepare("UPDATE app_status SET status='disabled' WHERE app_id='pnp-lesson-plan'");$q->execute();$portal->commit();
 if(hash_file('sha256',$root.'/.env')!==$envHash)throw new RuntimeException('Environment file changed');
 $auditBaseline=(int)$portal->query('SELECT COALESCE(MAX(id),0) FROM pnp_v2_audit')->fetchColumn();
 $state=['audit_baseline'=>$auditBaseline,'release'=>$manifest['release'],'enabled'=>true,'base'=>'','paused_apps'=>['pnp-lesson-plan'],'activated_utc'=>gmdate(DATE_ATOM),'old_lesson_plan_status'=>$status,'all_original_rows_identical_before_pause'=>true,'env_unchanged'=>true,'backup'=>$manifest['backup']];
 saveFile($runtime,json_encode($state));
 foreach($manifest['files'] as $f)if(hash_file('sha256',$root.'/'.$f['path'])!==$f['after'])throw new RuntimeException('Installed hash mismatch');
 saveFile($release.'/installed.json',json_encode($state,JSON_PRETTY_PRINT),0600);
 if(!rename($maintenance,$release.'/maintenance-ended-'.time()))throw new RuntimeException('Cannot end maintenance');
 echo json_encode($state,JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){error_log('Portal release failed: '.$e->getMessage());$restore();throw $e;}
