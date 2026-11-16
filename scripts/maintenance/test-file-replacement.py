#!/usr/bin/env python3
"""Integration tests with real HTTP uploads and SQL faults, on a PRIVATE restore.

Usage: python3 scripts/maintenance/test-file-replacement.py
Requires the isolated MariaDB restore from docs/RESTORE-TEST-20261116.md.
Never uses production .env. Fixtures and triggers persist in the test database.
"""
import base64
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

REPO = Path(__file__).resolve().parents[2]
STAGE = REPO / 'private-audit/staging-20261116'
DATADIR = REPO / 'private-audit/mariadb-restore-20261116/data'
SOCKET = '/private/tmp/montien-restore-20261116/mariadb.sock'
BACKUP = REPO / 'backups/initial-20261116-025625'
CHANGED = ['api/update_profile.php', 'pnpman/api/manage_personnel.php',
           'pnpman/api/update_settings.php', 'pnp-academix/teacher/submit.php',
           'pnp-academix/admin/settings.php']
BRIDGE = r'''
$r=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$p=new PDO('mysql:unix_socket='.$r['socket'].';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==realpath($r['datadir'])||(int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1)throw new Exception('Wrong test server');
if(isset($r['session'])){ini_set('session.save_path',$r['session_dir']);session_id($r['session']);session_start();$_SESSION=$r['data'];session_write_close();echo '{}';exit;}
$s=$p->prepare($r['sql']);$s->execute($r['params']);
echo json_encode(['rows'=>$s->columnCount()?$s->fetchAll(PDO::FETCH_ASSOC):[],'id'=>$p->lastInsertId()]);
'''


def bridge(**values):
    values.update(socket=SOCKET, datadir=str(DATADIR))
    result = subprocess.run(['php', '-r', BRIDGE], input=json.dumps(values).encode(),
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
    return json.loads(result.stdout)


def sql(statement, params=None):
    return bridge(sql=statement, params=params or [])


def multipart(fields, files):
    boundary = 'restore-' + secrets.token_hex(16)
    chunks = []
    for key, value in fields.items():
        chunks.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + key + '"\r\n\r\n' + str(value) + '\r\n').encode())
    for key, (name, content, mime) in files.items():
        chunks.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + key + '"; filename="' + name + '"\r\nContent-Type: ' + mime + '\r\n\r\n').encode() + content + b'\r\n')
    chunks.append(('--' + boundary + '--\r\n').encode())
    return b''.join(chunks), 'multipart/form-data; boundary=' + boundary


def main():
    assert STAGE.resolve().is_relative_to((REPO / 'private-audit').resolve())
    assert not (STAGE / 'pnp-portal/.env').exists()
    manifest = json.loads((BACKUP / 'manifest.json').read_text())
    db = {label: entry['database'] for label, entry in manifest['databases'].items()}
    assert all(re.fullmatch(r'[A-Za-z0-9_]+', name) for name in db.values())
    control = 'montien_restore_test_control'
    # The bridge checks the exact datadir and disabled TCP BEFORE executing SQL.
    sql('CREATE DATABASE IF NOT EXISTS ' + control)
    sql('CREATE TABLE IF NOT EXISTS ' + control + '.faults (target VARCHAR(40) PRIMARY KEY, enabled TINYINT NOT NULL DEFAULT 0)')
    for target in ['profile', 'man_logo', 'academix_logo', 'submission']:
        sql('INSERT INTO ' + control + '.faults VALUES (?,0) ON DUPLICATE KEY UPDATE enabled=0', [target])
    credentials = json.loads((STAGE / 'test-credentials.json').read_text())
    user_id = int(sql('SELECT id FROM `' + db['portal'] + '`.users WHERE username=?', [credentials['username']])['rows'][0]['id'])
    for label, table, key, condition in [
        ('portal', 'users', 'profile', 'NEW.id=' + str(user_id)),
        ('portal', 'college_settings', 'man_logo', 'NEW.id=1'),
        ('academix', 'branding_settings', 'academix_logo', "NEW.meta_key='logo_path'"),
        ('academix', 'submissions', 'submission', '1=1'),
    ]:
        sql('CREATE TRIGGER IF NOT EXISTS `' + db[label] + '`.restore_test_' + key + ' BEFORE UPDATE ON `' + db[label] + '`.`' + table + '` FOR EACH ROW BEGIN IF ' + condition + " AND (SELECT enabled FROM " + control + ".faults WHERE target='" + key + "')=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected restore-test failure'; END IF; END")
    for relative in CHANGED:
        shutil.copyfile(REPO / relative, STAGE / 'pnp-portal' / relative)
    env = os.environ.copy()
    for label, prefix in [('portal', 'DB_'), ('pnpgo', 'PNPGO_DB_'), ('academix', 'ACADEMIX_DB_')]:
        env.update({prefix + 'HOST': 'localhost', prefix + 'NAME': db[label], prefix + 'USER': 'root', prefix + 'PASS': ''})
    env.update(JWT_SECRET_KEY=credentials['jwt_secret'], UPLOAD_BASE_PATH=str(STAGE / 'pnp_uploads'))
    with socket.socket() as available:
        available.bind(('127.0.0.1', 0))
        port = available.getsockname()[1]
    base = 'http://127.0.0.1:' + str(port) + '/pnp-portal/'
    log = (STAGE / 'replacement-http.log').open('ab')
    process = subprocess.Popen(['php', '-d', 'pdo_mysql.default_socket=' + SOCKET, '-d', 'session.save_path=' + str(STAGE / 'sessions'), '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log=' + str(STAGE / 'php-errors.log'), '-S', '127.0.0.1:' + str(port), '-t', str(STAGE), str(STAGE / 'router.php')], env=env, stdout=log, stderr=log)
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(path, fields=None, files=None, token=None, session=None):
        headers = {}
        data = None
        if fields is not None:
            if files is None:
                data = json.dumps(fields).encode()
                headers['Content-Type'] = 'application/json'
            else:
                data, headers['Content-Type'] = multipart(fields, files)
        if token:
            headers['Authorization'] = 'Bearer ' + token
        if session:
            headers['Cookie'] = 'PHPSESSID=' + session
        try:
            with opener.open(urllib.request.Request(base + path, data=data, headers=headers), timeout=30) as response:
                return response.status, response.read()
        except urllib.error.HTTPError as error:
            return error.code, error.read()

    try:
        for attempt in range(50):
            try:
                request('index.html')
                break
            except urllib.error.URLError:
                if process.poll() is not None:
                    raise RuntimeError('Staging PHP exited; see private log')
                time.sleep(0.1)
        status, body = request('api/login.php', {'username': credentials['username'], 'password': credentials['password']})
        assert status == 200
        token = json.loads(body)['token']
        request('pnp-academix/sso.php?' + urllib.parse.urlencode({'token': token}))
        status, body = request('pnp-academix/admin/settings.php')
        admin_csrf = re.search(rb'name="csrf_token" value="([^"]+)"', body).group(1).decode()
        semester = int(sql('SELECT id FROM `' + db['academix'] + '`.semesters WHERE is_active=1 LIMIT 1')['rows'][0]['id'])
        suffix = secrets.token_hex(6)
        teacher_id = int(sql('INSERT INTO `' + db['academix'] + '`.users (username,password,fullname,role,status) VALUES (?,?,?,?,?)', ['restore_teacher_' + suffix, 'not-a-login-hash', 'Restore Test Teacher', 'teacher', 'active'])['id'])
        course_id = int(sql('INSERT INTO `' + db['academix'] + '`.courses (course_code,course_name,teacher_id,semester_id) VALUES (?,?,?,?)', ['restore-' + suffix, 'Restore replacement test', teacher_id, semester])['id'])
        sql('UPDATE `' + db['academix'] + "`.system_settings SET is_open=1 WHERE semester_id=? AND system_type='lesson_plan'", [semester])
        teacher_session = secrets.token_hex(16)
        bridge(session=teacher_session, session_dir=str(STAGE / 'sessions'), data={'user_id': teacher_id, 'role': 'teacher', 'fullname': 'Restore Test Teacher'})
        submit_url = 'pnp-academix/teacher/submit.php?course_id=' + str(course_id) + '&system_type=lesson_plan'
        status, body = request(submit_url, session=teacher_session)
        teacher_csrf = re.search(rb'name="csrf_token" value="([^"]+)"', body).group(1).decode()
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j1ioAAAAASUVORK5CYII=')
        pdf = b'%PDF-1.4\n% replacement regression fixture\n%%EOF\n'
        results = {}
        cases = [
            ('profile', 'profile', STAGE / 'pnp-portal', 'users', 'avatar_path', user_id),
            ('man_photo', 'profile', STAGE / 'pnp_uploads/pnpman', 'users', 'avatar_path', user_id),
            ('man_logo', 'man_logo', STAGE / 'pnp_uploads/pnpman', 'college_settings', 'logo_path', 1),
            ('academix_logo', 'academix_logo', STAGE / 'pnp_uploads/pnp-academix', 'branding_settings', 'meta_value', None),
            ('submission', 'submission', STAGE / 'pnp_uploads/pnp-academix', 'submissions', 'file_path', None),
        ]
        for case, fault, root, table, column, row_id in cases:
            database = db['academix'] if case in ['academix_logo', 'submission'] else db['portal']
            old_relative = 'uploads/replacement-old-' + case + '-' + suffix + ('.pdf' if case == 'submission' else '.png')
            old = root / old_relative
            old.parent.mkdir(parents=True, exist_ok=True)
            old.write_bytes(pdf if case == 'submission' else png)
            old_hash = hashlib.sha256(old.read_bytes()).digest()
            if case == 'submission':
                row_id = int(sql('INSERT INTO `' + database + '`.submissions (course_id,system_type,file_path,submission_timing,status) VALUES (?,?,?,?,?)', [course_id, 'lesson_plan', old_relative, 'on_time', 'pending'])['id'])
            where = "meta_key='logo_path'" if case == 'academix_logo' else 'id=' + str(row_id)
            sql('UPDATE `' + database + '`.`' + table + '` SET `' + column + '`=? WHERE ' + where, [old_relative])

            def current():
                return sql('SELECT `' + column + '` AS path FROM `' + database + '`.`' + table + '` WHERE ' + where)['rows'][0]['path']

            def send(remove=False):
                if case == 'profile':
                    fields = {'email': 'restore-test-20261116@example.invalid', 'avatar': 'data:image/png;base64,' + base64.b64encode(png).decode()}
                    if remove:
                        fields.update(remove_avatar=1, avatar='')
                    return request('api/update_profile.php', fields, token=token)
                if case == 'man_photo':
                    fields = {'action': 'update', 'id': user_id, 'name': 'Restore Test', 'main_title': 'ผู้ดูแลระบบ'}
                    if remove:
                        fields['delete_photo'] = 'true'
                    return request('pnpman/api/manage_personnel.php', fields, {} if remove else {'photo': ('new.png', png, 'image/png')}, token=token)
                if case == 'man_logo':
                    fields = {'college_name': 'Restore Test College', 'theme_preset': 'rose'}
                    if remove:
                        fields['delete_logo'] = 'true'
                    return request('pnpman/api/update_settings.php', fields, {} if remove else {'logo': ('new.png', png, 'image/png')}, token=token)
                if case == 'academix_logo':
                    fields = {'action': 'save_branding', 'csrf_token': admin_csrf, 'system_name': 'Replacement Test ' + suffix, 'college_name': 'Restore Test College', 'logo_text': 'TEST', 'theme_color': 'dark-blue'}
                    if remove:
                        fields['delete_logo'] = '1'
                    return request('pnp-academix/admin/settings.php', fields, {} if remove else {'logo_file': ('new.png', png, 'image/png')})
                return request(submit_url, {'action': 'submit_document', 'course_id': course_id, 'system_type': 'lesson_plan', 'confirm_overwrite': '1', 'csrf_token': teacher_csrf}, {'file_upload': ('new.pdf', pdf, 'application/pdf')}, session=teacher_session)

            # Deny writes at the actual upload destination; no DB pointer may change.
            destination = root / ('uploads/branding' if case == 'academix_logo' else 'uploads')
            if case == 'submission':
                destination = root / 'uploads' / str(semester) / str(teacher_id) / str(course_id) / 'lesson_plan'
            destination.mkdir(parents=True, exist_ok=True)
            original_mode = destination.stat().st_mode & 0o777
            try:
                destination.chmod(0o555)
                send()
                assert current() == old_relative, case + ': path changed after filesystem failure'
                assert hashlib.sha256(old.read_bytes()).digest() == old_hash
            finally:
                destination.chmod(original_mode)
            # Fail AFTER the uploaded file reaches disk, inside the real SQL UPDATE.
            before_branding = sql('SELECT meta_key,meta_value FROM `' + database + '`.branding_settings ORDER BY meta_key')['rows'] if case == 'academix_logo' else None
            sql('UPDATE ' + control + '.faults SET enabled=1 WHERE target=?', [fault])
            failure_status, failure_body = send()
            if case in ['profile', 'man_photo', 'man_logo']:
                assert failure_status == 500, case + ': SQL failure was not reported'
            elif case == 'submission':
                assert 'ไม่สามารถบันทึกเอกสารได้'.encode() in failure_body
            else:
                assert b'Injected restore-test failure' in failure_body
            assert current() == old_relative, case + ': DB changed despite injected failure'
            assert hashlib.sha256(old.read_bytes()).digest() == old_hash, case + ': old bytes lost on DB failure'
            if before_branding is not None:
                after = sql('SELECT meta_key,meta_value FROM `' + database + '`.branding_settings ORDER BY meta_key')['rows']
                assert before_branding == after, 'Branding partially committed'
            if case != 'submission':
                send(remove=True)
                assert current() == old_relative and old.exists(), case + ': failed detach lost original'
            sql('UPDATE ' + control + '.faults SET enabled=0 WHERE target=?', [fault])
            # Successful replacement must point at a NEW file, preserving the old one.
            send()
            new_relative = current()
            assert new_relative and new_relative != old_relative, case + ': replacement did not commit'
            assert (root / new_relative).read_bytes() == (pdf if case == 'submission' else png)
            assert hashlib.sha256(old.read_bytes()).digest() == old_hash
            # A second replacement must not overwrite the first new file.
            send()
            assert current() != new_relative and (root / new_relative).is_file(), case + ': filename collision'
            if case != 'submission':
                newest = current()
                send(remove=True)
                assert current() in [None, ''] and (root / newest).exists() and old.exists(), case + ': detach deleted file'
            else:
                newest = current()
                sql('UPDATE `' + database + '`.submissions SET status=? WHERE id=?', ['approved', row_id])
                send()
                assert current() == newest and (root / newest).exists(), 'Approved submission was overwritten'
            results[case] = 'filesystem failure, SQL failure, preserved original, success and unique filename: passed'
        (STAGE / 'file-replacement-results.json').write_text(json.dumps(results, indent=2))
        print(json.dumps(results, indent=2))
    finally:
        for target in ['profile', 'man_logo', 'academix_logo', 'submission']:
            sql('UPDATE ' + control + '.faults SET enabled=0 WHERE target=?', [target])
        process.terminate()
        process.wait(timeout=10)
        log.close()


if __name__ == '__main__':
    os.umask(0o077)
    main()
