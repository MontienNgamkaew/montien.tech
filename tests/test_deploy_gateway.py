"""Code-only deployment safety using disposable directories; no network or DB."""
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import unittest

REPO = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('manual_hostinger', REPO / 'scripts/deploy/manual_hostinger.py')
client = importlib.util.module_from_spec(spec)
spec.loader.exec_module(client)


class GatewayTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='pnp-gateway-test-')
        self.addCleanup(self.temporary.cleanup)
        self.base = Path(self.temporary.name).resolve()
        self.root = self.base / 'webroot'
        self.source = self.base / 'new-code'
        self.control = self.base / 'control'
        for directory in [self.root, self.source, self.control, self.base / 'releases', self.base / 'backups']:
            directory.mkdir()
        for name in client.FILES:
            for folder, content in [(self.root, b'<?php echo "old";\n'), (self.source, b'<?php echo "new";\n')]:
                target = folder / name
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(content)
        (self.root / '.env').write_text('PRIVATE_FIXTURE_DO_NOT_REPLACE=original\n')
        (self.root / 'uploads').mkdir()
        (self.root / 'uploads/user-document.pdf').write_bytes(b'preserved upload')
        self.initial_hashes = {name: hashlib.sha256((self.root / name).read_bytes()).hexdigest() for name in client.FILES}
        config = {'webroot': str(self.root), 'releases': str(self.base / 'releases'), 'backups': str(self.base / 'backups'), 'initial_ref': 'a' * 40, 'initial_hashes': self.initial_hashes}
        (self.control / 'config.json').write_text(json.dumps(config))
        shutil.copyfile(REPO / 'scripts/deploy/gateway.php', self.control / 'gateway.php')
        # No DB used here. Production installs the separately tested REAL backup.php.
        (self.control / 'backup.php').write_text('''<?php
umask(0077);mkdir($argv[2],0700);
file_put_contents($argv[2].'/manifest.json',json_encode(['status'=>'archive_checks_passed_restore_not_tested']));
file_put_contents($argv[2].'/proof.txt','fixture');
$s='';foreach(['manifest.json','proof.txt'] as $f)$s.=hash_file('sha256',$argv[2].'/'.$f).'  '.$f."\\n";
file_put_contents($argv[2].'/SHA256SUMS',$s);
''')
        self.archive = self.base / 'payload.tar'
        client.package(self.source, 'b' * 40, 'a' * 40, self.archive)

    def call(self, command, payload=None, ok=True):
        env = os.environ.copy()
        env['SSH_ORIGINAL_COMMAND'] = command
        result = subprocess.run(['php', str(self.control / 'gateway.php')], input=payload, stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=env)
        if ok:
            self.assertEqual(result.returncode, 0, result.stderr.decode(errors='replace'))
            return json.loads(result.stdout)
        self.assertNotEqual(result.returncode, 0)
        return result

    def upload(self, identifier='test-release'):
        return self.call('pnp-deploy upload ' + identifier, self.archive.read_bytes())

    def assert_original(self):
        for name, digest in self.initial_hashes.items():
            self.assertEqual(hashlib.sha256((self.root / name).read_bytes()).hexdigest(), digest)
        self.assert_private_files()

    def assert_private_files(self):
        self.assertEqual((self.root / '.env').read_text(), 'PRIVATE_FIXTURE_DO_NOT_REPLACE=original\n')
        self.assertEqual((self.root / 'uploads/user-document.pdf').read_bytes(), b'preserved upload')

    def test_status_and_only_protocol_commands(self):
        self.assertEqual(self.call('pnp-deploy status')['deployed_ref'], 'a' * 40)
        self.call('cat ~/.ssh/authorized_keys', ok=False)
        self.call('pnp-deploy status; whoami', ok=False)
        self.call('pnp-deploy upload ../../public_html', ok=False)
        self.assert_original()

    def test_payload_is_exact_allowlist(self):
        with tarfile.open(self.archive) as archive:
            self.assertEqual(set(archive.getnames()), set(client.FILES + ['manifest.json']))
        self.upload()
        self.assert_original()

    def test_reject_forbidden_file(self):
        with tarfile.open(self.archive, 'a') as archive:
            entry = tarfile.TarInfo('.env')
            entry.size = 4
            archive.addfile(entry, io.BytesIO(b'evil'))
        self.call('pnp-deploy upload bad-file', self.archive.read_bytes(), ok=False)
        self.assert_original()

    def test_reject_symlink(self):
        with tarfile.open(self.archive, 'a') as archive:
            entry = tarfile.TarInfo('api/linked.php')
            entry.type = tarfile.SYMTYPE
            entry.linkname = '../../.env'
            archive.addfile(entry)
        self.call('pnp-deploy upload bad-link', self.archive.read_bytes(), ok=False)
        self.assert_original()

    def test_code_drift_stops_before_writing(self):
        (self.root / client.FILES[0]).write_bytes(b'<?php echo "owner hotfix";')
        result = self.call('pnp-deploy status', ok=False)
        self.assertIn(b'drift', result.stderr)
        self.assertIn(b'owner hotfix', (self.root / client.FILES[0]).read_bytes())
        self.assert_private_files()

    def test_deploy_requires_successful_backup(self):
        self.upload()
        self.call('pnp-deploy deploy test-release', ok=False)
        self.assert_original()

    def test_failed_backup_prevents_install(self):
        self.upload()
        (self.control / 'backup.php').write_text('<?php exit(1);')
        self.call('pnp-deploy backup test-release', ok=False)
        self.call('pnp-deploy deploy test-release', ok=False)
        self.assert_original()

    def test_bad_php_rejected(self):
        (self.source / client.FILES[0]).write_bytes(b'<?php this is invalid php!')
        client.package(self.source, 'b' * 40, 'a' * 40, self.archive)
        self.call('pnp-deploy upload bad-php', self.archive.read_bytes(), ok=False)
        self.assert_original()

    def test_code_only_install_and_rollback(self):
        self.upload()
        self.call('pnp-deploy backup test-release')
        reply = self.call('pnp-deploy deploy test-release')
        self.assertTrue(reply['deployed'])
        self.assertEqual(self.call('pnp-deploy status')['deployed_ref'], 'b' * 40)
        for name in client.FILES:
            self.assertEqual((self.root / name).read_bytes(), (self.source / name).read_bytes())
        self.assert_private_files()
        self.call('pnp-deploy rollback wrong-release', ok=False)
        self.call('pnp-deploy rollback test-release')
        self.assert_original()
        self.assertEqual(self.call('pnp-deploy status')['deployed_ref'], 'a' * 40)

    def test_wrong_expected_ref_stops_upload(self):
        client.package(self.source, 'b' * 40, 'c' * 40, self.archive)
        self.call('pnp-deploy upload wrong-ref', self.archive.read_bytes(), ok=False)
        self.assert_original()

    def test_stale_backup_stops_install(self):
        self.upload()
        self.call('pnp-deploy backup test-release')
        marker = self.base / 'releases/test-release/backup-marker.json'
        value = json.loads(marker.read_text())
        value['created'] = 0
        marker.write_text(json.dumps(value))
        self.call('pnp-deploy deploy test-release', ok=False)
        self.assert_original()

    def test_state_record_failure_restores_original_code(self):
        self.upload()
        self.call('pnp-deploy backup test-release')
        # A directory at the state-file location makes atomic state rename fail.
        (self.control / 'state.json').mkdir()
        self.call('pnp-deploy deploy test-release', ok=False)
        self.assert_original()


if __name__ == '__main__':
    unittest.main()
