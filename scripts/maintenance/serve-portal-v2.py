#!/usr/bin/env python3
"""Local-only Portal V2 staging; no production credentials or user data copied.

First run: --setup (creates NEW synthetic databases; never drops old databases).
Then run without flags to serve. Ctrl-C stops this owned preview server.
"""
import argparse
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
PRIVATE = ROOT / 'private-audit/portal-v2-stage'
SOCKET = '/private/tmp/montien-restore-20261116/mariadb.sock'
DATADIR = ROOT / 'private-audit/mariadb-restore-20261116/data'
CONFIG = PRIVATE / 'stage.json'
MANIFEST = ROOT / 'backups/initial-20261116-025625/manifest.json'


def copy_source():
    target = PRIVATE / 'public/pnp-portal'
    target.mkdir(parents=True, exist_ok=True)
    folders = ['api', 'portal-v2', 'pnp-academix', 'pnp-go', 'pnpman', 'pnp-lessonplan', 'scripts/maintenance']
    for folder in folders:
        shutil.copytree(ROOT / folder, target / folder, dirs_exist_ok=True,
                        ignore=shutil.ignore_patterns('.env', '.env.*', 'node_modules', 'dist', '__pycache__', 'sessions', '*.log'))
    for file in ['logo.png', 'upload_serve.php', 'index.html', 'script.js', 'styles.css', 'admin.html', 'admin_script.js', 'admin_styles.css', '.htaccess']:
        shutil.copyfile(ROOT / file, target / file)
    (PRIVATE / 'sessions').mkdir(exist_ok=True)
    (PRIVATE / 'uploads/pnp-academix/uploads').mkdir(parents=True, exist_ok=True)
    (PRIVATE / 'uploads/pnpman/uploads').mkdir(parents=True, exist_ok=True)
    return target


def environment(config):
    result = os.environ.copy()
    for label, prefix in [('portal', 'DB_'), ('pnpgo', 'PNPGO_DB_'), ('academix', 'ACADEMIX_DB_')]:
        result.update({prefix + 'HOST': 'localhost', prefix + 'NAME': config['databases'][label], prefix + 'USER': 'root', prefix + 'PASS': ''})
    result.update(PORTAL_V2_ENABLED='true', PORTAL_PREVIEW='true', PORTAL_BASE_PATH='/pnp-portal', JWT_SECRET_KEY=config['jwt_secret'],
                  UPLOAD_BASE_PATH=str(PRIVATE / 'uploads'), CORS_ALLOWED_ORIGINS='http://127.0.0.1:18767')
    return result


def setup():
    PRIVATE.mkdir(parents=True, mode=0o700, exist_ok=True)
    if CONFIG.exists():
        shutil.copyfile(CONFIG, PRIVATE / ('stage-before-' + str(time.time_ns()) + '.json'))
    manifest = json.loads(MANIFEST.read_text())
    suffix = secrets.token_hex(4)
    databases = {label: 'pnp_v2_stage_' + suffix + '_' + label for label in ['portal', 'pnpgo', 'academix']}
    password = secrets.token_urlsafe(18)
    config = {'databases': databases, 'jwt_secret': secrets.token_hex(32), 'password': password, 'admin_username': 'stage_admin'}
    # This PHP process never imports env.php or application config.
    php = r'''
$i=json_decode(stream_get_contents(STDIN),true);$p=new PDO('mysql:unix_socket='.$i['socket'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==realpath($i['datadir'])||(int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1)throw new Exception('Wrong local instance');
foreach($i['databases'] as $label=>$name){if(!preg_match('/^pnp_v2_stage_[a-f0-9]+_(portal|pnpgo|academix)$/D',$name))throw new Exception('Unsafe DB name');$p->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');foreach($i['manifest']['databases'][$label]['tables'] as $table){$source=$i['manifest']['databases'][$label]['database'];$t=$table['TABLE_NAME'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$source.$t))throw new Exception('Unsafe source');$p->exec('CREATE TABLE `'.$name.'`.`'.$t.'` LIKE `'.$source.'`.`'.$t.'`');}}
$db=$i['databases']['portal'];$s=$p->prepare('INSERT INTO `'.$db.'`.users (username,password_hash,first_name,last_name,email,department,primary_position,is_portal_admin,status) VALUES (?,?,?,?,?,?,?,?,?)');
foreach([['stage_admin','ผู้ดูแล','ตัวอย่าง','แผนกทดสอบ A',1],['stage_member','สมาชิก','ตัวอย่าง A','แผนกทดสอบ A',0],['stage_reviewer','ผู้ตรวจ','ตัวอย่าง A','แผนกทดสอบ A',0],['stage_other','สมาชิก','ตัวอย่าง B','แผนกทดสอบ B',0],['stage_viewer','ผู้ดูรายงาน','ตัวอย่าง','แผนกทดสอบ A',0]] as $u)$s->execute([$u[0],password_hash($i['password'],PASSWORD_DEFAULT),$u[1],$u[2],$u[0].'@example.invalid',$u[3],$u[4]?'ผู้ดูแลระบบ':'ครูพิเศษสอน',$u[4],'active']);
$r=$p->prepare('INSERT INTO `'.$db.'`.app_roles(user_id,app_id,role) VALUES (?,?,?)');foreach([1,2,3,4,5] as $id)foreach(['pnp-academic','pnp-go','pnp-man','pnp-lesson-plan'] as $app){$role=$id===1?'admin':($id===3?($app==='pnp-go'?'supply_head':'admin'):($app==='pnp-academic'?'teacher':'user'));$r->execute([$id,$app,$role]);}
$s=$p->prepare('INSERT INTO `'.$db.'`.app_status(app_id,status) VALUES (?,?)');foreach(['pnp-academic','pnp-go','pnp-man','pnp-lesson-plan'] as $app)$s->execute([$app,'active']);
$ac=$i['databases']['academix'];$p->exec("INSERT INTO `$ac`.semesters (id,semester_name,is_active) VALUES (1,'ทดสอบ / 2569',1)");$s=$p->prepare('INSERT INTO `'.$ac.'`.system_settings (semester_id,system_type,deadline_date,is_open) VALUES (1,?,?,1)');foreach(['course_syllabus','lesson_plan','teaching_materials'] as $type)$s->execute([$type,'2030-12-31 23:59:59']);
echo 'Synthetic databases created; no source data copied'.PHP_EOL;
'''
    values = dict(config, socket=SOCKET, datadir=str(DATADIR), manifest=manifest)
    subprocess.run(['php', '-r', php], input=json.dumps(values).encode(), check=True)
    CONFIG.write_text(json.dumps(config, indent=2))
    target = copy_source()
    subprocess.run(['php', '-d', 'pdo_mysql.default_socket=' + SOCKET, str(target / 'scripts/maintenance/migrate-portal-v2.php'), '--apply', '--database=' + databases['portal']], env=environment(config), check=True)
    subprocess.run(['php', '-d', 'pdo_mysql.default_socket=' + SOCKET, str(target / 'scripts/maintenance/migrate-portal-v2-go.php'), '--apply', '--database=' + databases['pnpgo']], env=environment(config), check=True)
    subprocess.run(['php', '-d', 'pdo_mysql.default_socket=' + SOCKET, str(target / 'scripts/maintenance/migrate-portal-v2-multirole.php'), '--apply', '--database=' + databases['portal']], env=environment(config), check=True)
    # Explicit test grants; no inherited new rights during application requests.
    php = r'''
$i=json_decode(stream_get_contents(STDIN),true);$p=new PDO('mysql:unix_socket='.$i['socket'].';dbname='.$i['databases']['portal'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$s=$p->prepare('UPDATE pnp_v2_grants SET rights_json=?,scope=?,scope_value=? WHERE user_id=? AND app_id=?');
$s->execute([json_encode(['view','approve','export']),'department','แผนกทดสอบ A',3,'pnp-academic']);
$s->execute([json_encode(['view','edit','export']),'department','แผนกทดสอบ A',3,'pnp-man']);
$s->execute([json_encode(['view']),'all','',5,'pnp-academic']);
$s->execute([json_encode(['view']),'all','',5,'pnp-lesson-plan']);
'''
    subprocess.run(['php', '-r', php], input=json.dumps(dict(config, socket=SOCKET)).encode(), check=True)
    print('Private fixture credentials:', CONFIG)


ROUTER = r'''<?php
$uri=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(str_contains($uri,'..')||str_contains($uri,"\0")||preg_match('~/(?:\.git|\.env|private-audit|backups|scripts|tests)(?:/|$|\.)~',$uri)){http_response_code(404);exit;}
$_SERVER['HTTP_HOST']='127.0.0.1:18767';
$base='/pnp-portal';$root=__DIR__.'/public/pnp-portal';$path=str_starts_with($uri,$base)?substr($uri,strlen($base)):$uri;
if($path==='/'||$path==='/index.html'){header('Location: '.$base.'/portal-v2/');exit;}
if(preg_match('~^/(pnp-academix|pnpman)/uploads/(.+)$~',$path,$m)){$_GET['app']=$m[1];$_GET['file']='uploads/'.$m[2];require $root.'/upload_serve.php';return;}
if(preg_match('~^/pnp-go/storage/(pdfs|reports|signatures)/(.+)$~',$path,$m)){$_GET['app']='pnp-go';$_GET['file']='storage/'.$m[1].'/'.$m[2];require $root.'/upload_serve.php';return;}
if(preg_match('~^/uploads/(.+)$~',$path,$m)){$_GET['app']='portal';$_GET['file']='uploads/'.$m[1];require $root.'/upload_serve.php';return;}
if($path==='/pnpman/'){require $root.'/pnpman/index.php';return;}
if(str_starts_with($path,'/pnpman/')&&!is_file($root.$path)&&!is_dir($root.$path)){$asset=$root.'/pnpman/frontend/dist/'.substr($path,strlen('/pnpman/'));if(is_file($asset)){header('Content-Type: '.(str_ends_with($asset,'.js')?'application/javascript':'text/css'));readfile($asset);return;}}
if(str_starts_with($path,'/pnp-go/')&&!is_file($root.$path)){require $root.'/pnp-go/index.php';return;}
if(is_dir($root.$path)&&is_file($root.$path.'/index.php')){require $root.$path.'/index.php';return;}
return false;
'''


def main():
    parser = argparse.ArgumentParser(); parser.add_argument('--setup', action='store_true'); parser.add_argument('--sync', action='store_true'); args = parser.parse_args()
    os.umask(0o077)
    if args.setup: setup(); return
    if not CONFIG.exists(): raise SystemExit('Run with --setup after starting the private MariaDB instance')
    config = json.loads(CONFIG.read_text())
    if args.sync: copy_source(); return
    (PRIVATE / 'router.php').write_text(ROUTER)
    print('Local preview: http://127.0.0.1:18767/pnp-portal/portal-v2/', flush=True)
    print('Test accounts only; credentials remain in private-audit/portal-v2-stage/stage.json', flush=True)
    os.execvpe('php', ['php', '-d', 'pdo_mysql.default_socket=' + SOCKET, '-d', 'session.save_path=' + str(PRIVATE / 'sessions'), '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' + str(PRIVATE / 'php-errors.log'), '-S', '127.0.0.1:18767', '-t', str(PRIVATE / 'public'), str(PRIVATE / 'router.php')], environment(config))


if __name__ == '__main__': main()
