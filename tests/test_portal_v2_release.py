"""Exercise install failure/recovery and success/rollback against synthetic local DBs."""
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import unittest

ROOT=Path(__file__).resolve().parents[1]
class ReleaseRehearsal(unittest.TestCase):
    def fixture(self, backup_ok):
        source=ROOT/'private-audit/releases/portal-v2-20261116-v1'
        if not source.is_dir():self.skipTest('Prepared private release required')
        config=json.loads((ROOT/'private-audit/portal-v2-stage/stage.json').read_text())
        self.assertTrue(all(x.startswith('pnp_v2_stage_') for x in config['databases'].values()))
        work=ROOT/'private-audit'/('release-test-'+secrets.token_hex(4));work.mkdir(mode=0o700)
        release=work/'portal-v2-test';release.mkdir();root=work/'webroot';root.mkdir()
        for name in ['payload','tools']:shutil.copytree(source/name,release/name)
        m=json.loads((source/'manifest.json').read_text());m['release']=release.name;m['backup']=str(work/'final-backup')
        old=ROOT/'private-audit/portal-v2-preflight-997b7956/restored/public_html'
        for f in m['files']:
            target=root/f['path']
            if f['before'] is not None:
                target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(old/f['path'],target)
                self.assertEqual(hashlib.sha256(target.read_bytes()).hexdigest(),f['before'])
        (root/'api').mkdir(exist_ok=True);shutil.copyfile(ROOT/'api/env.php',root/'api/env.php')
        shutil.copyfile(ROOT/'index.html',root/'index.html')
        env_lines=[]
        for label,prefix in [('portal','DB_'),('pnpgo','PNPGO_DB_'),('academix','ACADEMIX_DB_')]:
            env_lines += [prefix+'HOST=localhost;unix_socket=/private/tmp/montien-restore-20261116/mariadb.sock',prefix+'NAME='+config['databases'][label],prefix+'USER=root',prefix+'PASS=']
        (root/'.env').write_text('\n'.join(env_lines));os.chmod(root/'.env',0o600)
        (release/'manifest.json').write_text(json.dumps(m))
        backup=work/'backup-helper.php';backup.write_text('<?php '+('echo "Fixture backup verified";' if backup_ok else 'exit(7);'))
        script=(ROOT/'scripts/deploy/portal_v2_release.php').read_text().replace('/home/u651170081/.config/montien-tech-deploy/deployment.lock',str(work/'deployment.lock')).replace('/home/u651170081/.config/montien-tech-deploy/backup.php',str(backup)).replace('sleep(8);','sleep(0);')
        (release/'release.php').write_text(script)
        return root,release,m
    def run_mode(self,root,release,mode):
        return subprocess.run(['php',str(release/'release.php'),mode,str(release),str(root)],capture_output=True,text=True)
    def test_failed_backup_restores_code_and_retains_env(self):
        root,release,m=self.fixture(False);before=(root/'.env').read_bytes()
        r=self.run_mode(root,release,'deploy');self.assertNotEqual(r.returncode,0)
        self.assertEqual((root/'.env').read_bytes(),before)
        self.assertFalse((root/'api/v2/maintenance.flag').exists())
        self.assertFalse(json.loads((root/'api/v2/runtime.local.json').read_text())['enabled'])
        for f in m['files']:
            if f['before'] is not None and f['path']!='pnp-lessonplan/.htaccess':self.assertEqual(hashlib.sha256((root/f['path']).read_bytes()).hexdigest(),f['before'])
    def test_success_then_code_rollback_keeps_lesson_plan_paused(self):
        root,release,m=self.fixture(True);before=(root/'.env').read_bytes()
        r=self.run_mode(root,release,'check');self.assertEqual(r.returncode,0,r.stderr)
        r=self.run_mode(root,release,'deploy');self.assertEqual(r.returncode,0,r.stderr)
        runtime=json.loads((root/'api/v2/runtime.local.json').read_text());self.assertTrue(runtime['enabled']);self.assertEqual(runtime['paused_apps'],['pnp-lesson-plan'])
        self.assertEqual((root/'api/v2').stat().st_mode & 0o777,0o755)
        self.assertEqual((root/'portal-v2/assets').stat().st_mode & 0o777,0o755)
        self.assertEqual((root/'.env').read_bytes(),before)
        self.assertFalse((root/'api/v2/maintenance.flag').exists())
        self.assertEqual(json.loads((release/'before-data.json').read_text()),json.loads((release/'after-data.json').read_text()))
        r=self.run_mode(root,release,'rollback');self.assertEqual(r.returncode,0,r.stderr)
        self.assertFalse(json.loads((root/'api/v2/runtime.local.json').read_text())['enabled'])
        self.assertIn('Require all denied',(root/'pnp-lessonplan/.htaccess').read_text())
        self.assertEqual((root/'.env').read_bytes(),before)

if __name__=='__main__':unittest.main()
