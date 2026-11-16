#!/usr/bin/env python3
"""Manual-only GitHub Actions client. Never transfers DB/config/uploads to CI."""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import re
import subprocess
import tarfile
import tempfile

FILES = ['api/update_profile.php', 'pnpman/api/manage_personnel.php',
         'pnpman/api/update_settings.php', 'pnp-academix/teacher/submit.php',
         'pnp-academix/admin/settings.php']


def package(root, commit, expected_ref, destination):
    if not re.fullmatch(r'[a-f0-9]{40}', commit) or not re.fullmatch(r'[a-f0-9]{40}', expected_ref):
        raise ValueError('Full commit hashes are required')
    contents = {}
    for relative in FILES:
        source = root / relative
        if source.is_symlink() or source.resolve() != source or not source.is_file():
            raise ValueError('Payload file missing or linked: ' + relative)
        contents[relative] = source.read_bytes()
    manifest = {'format': 1, 'commit': commit, 'expected_ref': expected_ref,
                'hashes': {name: hashlib.sha256(content).hexdigest() for name, content in contents.items()}}
    contents['manifest.json'] = json.dumps(manifest).encode()
    with tarfile.open(destination, 'w', format=tarfile.USTAR_FORMAT) as archive:
        for name, content in contents.items():
            info = tarfile.TarInfo(name)
            info.size = len(content)
            info.mode = 0o600
            archive.addfile(info, io.BytesIO(content))
    if destination.stat().st_size > 4194304:
        raise ValueError('Payload exceeds gateway limit')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--mode', choices=['check', 'deploy', 'rollback'], required=True)
    parser.add_argument('--rollback-release', default='')
    args = parser.parse_args()
    if os.environ.get('GITHUB_REF') != 'refs/heads/main':
        raise SystemExit('Only main may use the production deployment key')
    root = Path(__file__).resolve().parents[2]
    host = os.environ['HOSTINGER_SSH_HOST']
    user = os.environ['HOSTINGER_SSH_USER']
    port = os.environ['HOSTINGER_SSH_PORT']
    if not re.fullmatch(r'[A-Za-z0-9.-]+', host) or not re.fullmatch(r'[A-Za-z0-9_-]+', user) or not port.isdigit() or not 1 <= int(port) <= 65535:
        raise SystemExit('Invalid SSH connection settings')
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip()
    run_id = os.environ['GITHUB_RUN_ID']
    attempt = os.environ['GITHUB_RUN_ATTEMPT']
    if not run_id.isdigit() or not attempt.isdigit():
        raise SystemExit('Invalid run identifier')
    release = 'run-' + run_id + '-' + attempt + '-' + commit[:12]
    os.umask(0o077)
    with tempfile.TemporaryDirectory(prefix='pnp-deploy-', dir=os.environ.get('RUNNER_TEMP')) as temporary:
        temporary = Path(temporary)
        key = temporary / 'key'
        known_hosts = temporary / 'known_hosts'
        key.write_text(os.environ['HOSTINGER_SSH_PRIVATE_KEY'] + '\n')
        known_hosts.write_text(os.environ['HOSTINGER_SSH_KNOWN_HOSTS'] + '\n')
        ssh = ['ssh', '-i', str(key), '-p', port, '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(known_hosts), '-o', 'ConnectTimeout=20', '-o', 'LogLevel=ERROR', user + '@' + host]

        def remote(action, identifier=None, payload=None):
            if identifier is not None and not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_-]{0,90}', identifier):
                raise ValueError('Invalid release identifier')
            command = 'pnp-deploy ' + action + ((' ' + identifier) if identifier else '')
            result = subprocess.run(ssh + [command], input=payload, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=1000)
            if result.returncode:
                # Gateway emits controlled diagnostics, never backup contents or credentials.
                raise RuntimeError(result.stderr.decode(errors='replace').strip() or 'SSH gateway failed')
            reply = json.loads(result.stdout)
            if not reply.get('ok'):
                raise RuntimeError('Gateway did not confirm success')
            return reply

        state = remote('status')
        if args.mode == 'rollback':
            if not args.rollback_release or args.rollback_release != state.get('release'):
                raise ValueError('Specify the currently deployed release ID for rollback')
            result = remote('rollback', args.rollback_release)
        else:
            archive = temporary / 'payload.tar'
            package(root, commit, state['deployed_ref'], archive)
            remote('upload', release, archive.read_bytes())
            remote('check', release)
            if args.mode == 'deploy':
                remote('backup', release)
                result = remote('deploy', release)
            else:
                result = {'ok': True, 'action': 'check', 'release': release, 'commit': commit, 'files': len(FILES), 'deployed': False}
        verified = remote('status')
        if args.mode == 'deploy' and (verified['deployed_ref'] != commit or verified['release'] != release):
            raise RuntimeError('Post-deploy version did not match')
        print(json.dumps(result, indent=2))
        summary = os.environ.get('GITHUB_STEP_SUMMARY')
        if summary:
            with open(summary, 'a') as output:
                output.write('### Hostinger manual deployment\n\n```json\n' + json.dumps(result, indent=2) + '\n```\n')
                output.write('\nNo database migration, config update, upload deletion, or webroot checkout is performed.\n')


if __name__ == '__main__':
    main()
