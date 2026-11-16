"""Private isolated MariaDB rehearsal of the multi-role patch installer."""
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import unittest
ROOT=Path(__file__).resolve().parents[1]
class PatchRelease(unittest.TestCase):
    def fixture(self,backup_ok):
        source=ROOT/'private-audit/releases'/os.environ.get('PNP_PATCH_FIXTURE_RELEASE','portal-multirole-20261116-v1')
        report=ROOT/'private-audit/portal-v2-preflight-44e6158d/report.json'
        if not source.exists() or not report.exists():self.skipTest('Prepared private rehearsal required')
        cfg=json.loads(report.read_text())['isolated_databases'];self.assertTrue(all(x.startswith('pnp_v2_preflight_') for x in cfg.values()))
        work=ROOT/'private-audit'/('multirole-release-test-'+secrets.token_hex(4));work.mkdir(mode=0o700);root=work/'webroot';root.mkdir();release=work/'mr-test';release.mkdir()
        for n in ['payload','tools']:shutil.copytree(source/n,release/n)
        m=json.loads((source/'manifest.json').read_text());m.update(release=release.name,backup=str(work/'backup'))
        for f in m['files']:
            if f['before'] is not None:
                target=root/f['path'];target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source/'baseline'/f['path'],target)
        (root/'api/v2').mkdir(parents=True,exist_ok=True)
        shutil.copyfile(ROOT/'api/env.php',root/'api/env.php')
        runtime=root/'api/v2/runtime.local.json';runtime.write_text(json.dumps({'enabled':True,'base':'','paused_apps':['pnp-lesson-plan']}))
        lines=[]
        for label,prefix in [('portal','DB_'),('pnpgo','PNPGO_DB_'),('academix','ACADEMIX_DB_')]:lines += [prefix+'HOST=localhost;unix_socket=/private/tmp/montien-restore-20261116/mariadb.sock',prefix+'NAME='+cfg[label],prefix+'USER=root',prefix+'PASS=']
        (root/'.env').write_text('\n'.join(lines));os.chmod(root/'.env',0o600)
        helper=work/'backup.php';helper.write_text('<?php '+('echo "fixture backup";' if backup_ok else 'exit(7);'))
        code=(ROOT/'scripts/deploy/portal_multirole_release.php').read_text().replace('/home/u651170081/.config/montien-tech-deploy/deployment.lock',str(work/'lock')).replace('/home/u651170081/.config/montien-tech-deploy/backup.php',str(helper)).replace('sleep(8);','sleep(0);')
        (release/'release.php').write_text(code);(release/'manifest.json').write_text(json.dumps(m));return root,release,m
    def run_mode(self,root,release,mode):return subprocess.run(['php',str(release/'release.php'),mode,str(release),str(root)],capture_output=True,text=True)
    def test_backup_failure_preserves_old_code_and_runtime(self):
        root,release,m=self.fixture(False);runtime=(root/'api/v2/runtime.local.json').read_bytes();env=(root/'.env').read_bytes()
        r=self.run_mode(root,release,'deploy');self.assertNotEqual(r.returncode,0)
        self.assertEqual(runtime,(root/'api/v2/runtime.local.json').read_bytes());self.assertEqual(env,(root/'.env').read_bytes());self.assertFalse((root/'api/v2/maintenance.flag').exists())
        for f in m['files']:
            if f['before'] is not None:self.assertEqual(hashlib.sha256((root/f['path']).read_bytes()).hexdigest(),f['before'])
    def test_success_preserves_data_and_can_roll_back_before_assignments(self):
        root,release,m=self.fixture(True);env=(root/'.env').read_bytes()
        for mode in ['check','deploy']:
            r=self.run_mode(root,release,mode);self.assertEqual(r.returncode,0,r.stderr)
        for f in m['files']:
            self.assertEqual((root/f['path']).stat().st_mode & 0o777,0o644)
            if f['before'] is None:self.assertEqual((root/f['path']).parent.stat().st_mode & 0o777,0o755)
        installed=json.loads((release/'installed.json').read_text());self.assertTrue(installed['old_data_unchanged']);self.assertTrue(installed['lesson_plan_still_paused']);self.assertEqual(env,(root/'.env').read_bytes())
        r=self.run_mode(root,release,'rollback');self.assertEqual(r.returncode,0,r.stderr)
        for f in m['files']:
            if f['before'] is not None:self.assertEqual(hashlib.sha256((root/f['path']).read_bytes()).hexdigest(),f['before'])
        self.assertEqual(json.loads((root/'api/v2/runtime.local.json').read_text())['paused_apps'],['pnp-lesson-plan'])
        self.assertFalse((root/'api/v2/maintenance.flag').exists())
if __name__=='__main__':unittest.main()
