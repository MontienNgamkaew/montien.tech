#!/usr/bin/env python3
"""Restore a named backup to NEW local databases and rehearse additive migrations.
Never connects to production. Does not change any original/restored source database.
Reports counts and hashes only; original rows and credentials remain private.
"""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import tarfile

ROOT=Path(__file__).resolve().parents[2]
SOCKET='/private/tmp/montien-restore-20261116/mariadb.sock'
DATADIR=ROOT/'private-audit/mariadb-restore-20261116/data'
CLIENT='/opt/homebrew/Cellar/mariadb@11.8/11.8.9_1/bin/mariadb'

def php(code, values, args=()):
    return subprocess.check_output(['php','-r',code,*args],input=json.dumps(values).encode())

def main():
    os.umask(0o077)
    parser=argparse.ArgumentParser();parser.add_argument('backup');args=parser.parse_args()
    backup=Path(args.backup).resolve()
    if not backup.is_relative_to(ROOT/'backups'):raise SystemExit('Use the private project backups directory')
    for line in (backup/'SHA256SUMS').read_text().splitlines():
        digest,filename=line.split('  ',1);f=backup/filename
        if '/' in filename or hashlib.sha256(f.read_bytes()).hexdigest()!=digest:raise SystemExit('Backup checksum mismatch')
    manifest=json.loads((backup/'manifest.json').read_text())
    source_names={label:entry['database'] for label,entry in manifest['databases'].items()}
    suffix=secrets.token_hex(4);work=ROOT/'private-audit'/('portal-v2-preflight-'+suffix);work.mkdir(mode=0o700)
    values={'socket':SOCKET,'datadir':str(DATADIR),'databases':{k:'pnp_v2_preflight_'+suffix+'_'+k for k in manifest['databases']}}
    guard=r'''
$i=json_decode(stream_get_contents(STDIN),true);$p=new PDO('mysql:unix_socket='.$i['socket'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==realpath($i['datadir'])||(int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1)throw new Exception('Not isolated instance');
foreach($i['databases'] as $db){if(!preg_match('/^pnp_v2_preflight_[a-f0-9]+_(portal|pnpgo|academix)$/D',$db))throw new Exception('Unsafe DB');$q=$p->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$db]);if($q->fetchColumn())throw new Exception('Database already exists');}
'''
    php(guard,values)
    spec=importlib.util.spec_from_file_location('counts',ROOT/'scripts/maintenance/dump-counts.py');mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
    expected={};tables={}
    for label,db in manifest['databases'].items():
        sql=(backup/(label+'.sql')).read_text();expected[label]=mod.count_rows(sql)
        for key,source in source_names.items():sql=sql.replace('`'+source+'`','`'+values['databases'][key]+'`')
        r=subprocess.run([CLIENT,'--socket='+SOCKET,'--user=root','--binary-mode'],input=sql.encode(),capture_output=True)
        if r.returncode:(work/'restore-errors.log').write_bytes(r.stderr);raise SystemExit('Restore failed; see private error log')
        tables[label]=[x['TABLE_NAME'] for x in db['tables'] if x['TABLE_TYPE']=='BASE TABLE']
        manifest['databases'][label]['database']=values['databases'][label]
    values['tables']=tables
    snapshot=r'''
$i=json_decode(stream_get_contents(STDIN),true);$p=new PDO('mysql:unix_socket='.$i['socket'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==realpath($i['datadir'])||(int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1)throw new Exception('Wrong instance');
$out=[];foreach($i['databases'] as $label=>$db){if(!preg_match('/^pnp_v2_preflight_[a-f0-9]+_(portal|pnpgo|academix)$/D',$db))throw new Exception('Unsafe DB');foreach($i['tables'][$label] as $t){if(!preg_match('/^[A-Za-z0-9_]+$/D',$t))throw new Exception('Unsafe table');$rows=$p->query('SELECT * FROM `'.$db.'`.`'.$t.'`')->fetchAll(PDO::FETCH_ASSOC);$hashes=array_map(static fn($r)=>hash('sha256',json_encode($r)),$rows);sort($hashes);$out[$label][$t]=['rows'=>count($rows),'sha256'=>hash('sha256',implode('',$hashes))];}}echo json_encode($out);
'''
    before=json.loads(php(snapshot,values))
    for label,counts in expected.items():
        for t,n in counts.items():
            if before[label][t]['rows']!=n:raise SystemExit('Restore count mismatch')
    code=work/'code';(code/'api/v2').mkdir(parents=True);(code/'scripts/maintenance').mkdir(parents=True)
    for f in ['api/env.php','api/v2/core.php','scripts/maintenance/migrate-portal-v2.php','scripts/maintenance/migrate-portal-v2-go.php','scripts/maintenance/migrate-portal-v2-multirole.php']:shutil.copyfile(ROOT/f,code/f)
    env=os.environ.copy()
    for label,prefix in [('portal','DB_'),('pnpgo','PNPGO_DB_'),('academix','ACADEMIX_DB_')]:env.update({prefix+'HOST':'localhost',prefix+'NAME':values['databases'][label],prefix+'USER':'root',prefix+'PASS':''})
    migrations={}
    for script,label in [('migrate-portal-v2.php','portal'),('migrate-portal-v2-go.php','pnpgo'),('migrate-portal-v2-multirole.php','portal')]:
        r=subprocess.run(['php','-d','pdo_mysql.default_socket='+SOCKET,str(code/'scripts/maintenance'/script),'--apply','--database='+values['databases'][label]],env=env,capture_output=True,text=True)
        (work/(script+'.log')).write_text(r.stdout+r.stderr)
        if r.returncode:raise SystemExit('Migration failed; see private log')
        migrations[script]='passed'
    after=json.loads(php(snapshot,values))
    if before!=after:raise SystemExit('Existing table contents changed')
    archive=work/'restored';archive.mkdir()
    for name in ['webroot.tar.gz','persistent-uploads.tar.gz']:
        with tarfile.open(backup/name) as tar:
            for m in tar.getmembers():
                target=(archive/m.name).resolve()
                if not target.is_relative_to(archive) or m.issym() or m.islnk() or m.isdev():raise SystemExit('Unexpected archive link/path')
            tar.extractall(archive)
    (work/'manifest.json').write_text(json.dumps(manifest))
    (work/'expected-counts.json').write_text(json.dumps(expected))
    r=subprocess.run(['php',str(ROOT/'scripts/maintenance/verify-restored.php'),SOCKET,str(DATADIR),str(work),str(archive/'public_html'),str(archive/'pnp_uploads'),str(work/'expected-counts.json')],capture_output=True,text=True)
    (work/'file-verification.json').write_text(r.stdout)
    if r.returncode not in (0,3):raise SystemExit('Restore integrity failed')
    report={'backup':backup.name,'backup_checksums':'passed','work_directory':str(work),'isolated_databases':values['databases'],'original_tables_preserved':before,'migrations':migrations,'all_original_rows_identical':before==after,'file_verifier_exit':r.returncode,'production_modified':False}
    (work/'report.json').write_text(json.dumps(report,indent=2));print(json.dumps({k:v for k,v in report.items() if k!='original_tables_preserved'},indent=2))

if __name__=='__main__':main()
