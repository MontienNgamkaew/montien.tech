"""Explicit local synthetic staging only. Start serve-portal-v2.py first."""
import http.cookiejar
import json
from pathlib import Path
import secrets
import unittest
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
URL = 'http://127.0.0.1:18767/pnp-portal/api/v2/index.php?action='

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = self.call('bootstrap')[1]['csrf']
    def call(self, action, data=None, csrf=True):
        headers = {'Content-Type': 'application/json'}
        if csrf and hasattr(self, 'csrf'): headers['X-CSRF-Token'] = self.csrf
        req = urllib.request.Request(URL + action, data=None if data is None else json.dumps(data).encode(), headers=headers)
        try:
            with self.opener.open(req) as r: return r.status, json.load(r)
        except urllib.error.HTTPError as e: return e.code, json.load(e)
    def login(self, name, password): return self.call('login', {'username': name, 'password': password})

class PortalIntegration(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.config = json.loads((ROOT/'private-audit/portal-v2-stage/stage.json').read_text())
        assert all(v.startswith('pnp_v2_stage_') for v in cls.config['databases'].values())
        cls.admin = Client()
        assert cls.admin.login('stage_admin', cls.config['password'])[0] == 200
    def test_01_auth_and_csrf(self):
        c=Client()
        self.assertEqual(c.call('members')[0],401)
        self.assertEqual(c.call('login',{},csrf=False)[0],403)
        self.assertEqual(c.login('stage_member',self.config['password'])[0],200)
        self.assertEqual(c.call('members')[0],403)
        self.assertEqual(c.call('session&app=pnp-lesson-plan&right=manage_settings')[0],403)
    def test_02_members_safe(self):
        status,data=self.admin.call('members');self.assertEqual(status,200)
        self.assertGreaterEqual(data['total'],5)
        self.assertNotIn('password_hash',json.dumps(data))
        master=next(u for u in data['users'] if u['username']=='stage_admin')
        self.assertEqual(self.admin.call('member_state',{'id':master['id'],'state':'archive'})[0],409)
    def test_03_signup_lifecycle_and_password(self):
        c=Client();name='test_'+secrets.token_hex(4);password=secrets.token_urlsafe(15)
        fields=dict(username=name,password=password,first_name='ทดสอบ',last_name='ระบบ',email=name+'@example.invalid',primary_position='ครูพิเศษสอน',department='แผนกทดสอบ A',is_portal_admin=1)
        self.assertEqual(c.call('register',fields)[0],200)
        self.assertEqual(c.login(name,password)[0],403)
        users=self.admin.call('members')[1]['users'];u=next(x for x in users if x['username']==name);uid=u['id']
        self.assertFalse(u['is_portal_admin'])
        self.assertEqual(self.admin.call('member_state',{'id':uid,'state':'approve'})[0],200)
        self.assertEqual(c.login(name,password)[0],200)
        self.assertEqual(c.call('session&app=pnp-man')[0],403)
        grant=dict(user_id=uid,app_id='pnp-man',role='admin',rights=['view'],scope='department',scope_value='แผนกทดสอบ A',revision=0)
        self.assertEqual(self.admin.call('save_grant',grant)[0],200)
        self.assertEqual(self.admin.call('save_grant',grant)[0],409)
        self.assertEqual(c.call('session&app=pnp-man')[0],200)
        self.assertEqual(c.call('session&app=pnp-man&right=edit')[0],403)
        temporary=secrets.token_urlsafe(15)
        self.assertEqual(self.admin.call('reset_password',{'id':uid,'password':temporary})[0],200)
        self.assertEqual(c.call('session')[0],401)
        self.assertEqual(c.login(name,password)[0],401)
        self.assertEqual(c.login(name,temporary)[0],200)
        self.assertEqual(c.call('session&app=pnp-man')[0],403)
        self.assertEqual(c.call('change_password',{'old_password':temporary,'password':password})[0],200)
        self.assertEqual(c.call('session&app=pnp-man')[0],200)
        self.assertEqual(self.admin.call('member_state',{'id':uid,'state':'archive'})[0],200)
        self.assertEqual(c.call('session')[0],401)
        archived=next(x for x in self.admin.call('members')[1]['users'] if x['id']==uid)
        self.assertTrue(archived['archived_at'])
        self.assertEqual(self.admin.call('member_state',{'id':uid,'state':'restore'})[0],200)
        self.assertEqual(c.login(name,password)[0],403)
    def test_04_view_only_and_logout(self):
        c=Client();self.assertEqual(c.login('stage_viewer',self.config['password'])[0],200)
        self.assertEqual(c.call('session&app=pnp-lesson-plan')[0],200)
        self.assertEqual(c.call('session&app=pnp-lesson-plan&right=create')[0],403)
        self.assertEqual(c.call('logout',{})[0],200)
        self.assertEqual(c.call('session')[0],401)
    def test_05_disable_system(self):
        c=Client();self.assertEqual(c.login('stage_member',self.config['password'])[0],200)
        old=next(s['status'] for s in self.admin.call('bootstrap')[1]['systems'] if s['id']=='pnp-man')
        try:
            self.assertEqual(self.admin.call('system_status',{'app_id':'pnp-man','status':'disabled','expected_status':old})[0],200)
            self.assertEqual(c.call('session&app=pnp-man')[0],403)
            self.assertEqual(self.admin.call('session&app=pnp-man')[0],200)
        finally:self.admin.call('system_status',{'app_id':'pnp-man','status':old})
    def test_06_subsystem_sso_and_scope(self):
        base='http://127.0.0.1:18767/pnp-portal/'
        member=Client();self.assertEqual(member.login('stage_member',self.config['password'])[0],200)
        for path,expected in [('pnp-academix/sso.php','pnp-academix/index.php'),('pnp-go/sso.php','pnp-go/dashboard'),('pnpman/','pnpman/')]:
            with member.opener.open(base+path) as r:
                body=r.read().decode();self.assertEqual(r.status,200);self.assertTrue(r.url.endswith(expected));self.assertNotIn('token=',r.url);self.assertNotIn('Fatal error',body)
        with member.opener.open(base+'pnpman/api/get_data.php') as r:self.assertNotIn('password_hash',r.read().decode())
        reviewer=Client();reviewer.login('stage_reviewer',self.config['password'])
        with reviewer.opener.open(base+'pnpman/api/get_data.php') as r:
            body=r.read().decode();self.assertNotIn('stage_other',body);self.assertIn('stage_member',body)
        viewer=Client();viewer.login('stage_viewer',self.config['password']);viewer.opener.open(base+'pnp-academix/sso.php').read()
        try: viewer.opener.open(base+'pnp-academix/admin/settings.php');self.fail('view-only admin reached settings')
        except urllib.error.HTTPError as e:self.assertEqual(e.code,403)
        try: member.opener.open(base+'api/admin_users.php');self.fail('legacy admin management allowed')
        except urllib.error.HTTPError as e:self.assertEqual(e.code,410)
        member.call('logout',{})
        with member.opener.open(base+'pnp-academix/index.php') as r:self.assertIn('/portal-v2/',r.url)
        with member.opener.open(base+'pnp-go/dashboard') as r:self.assertIn('/portal-v2/',r.url)

    def test_06_audit_safe(self):
        status,data=self.admin.call('audit');self.assertEqual(status,200)
        raw=json.dumps(data);self.assertNotIn('password_hash',raw);self.assertNotIn(self.config['password'],raw)

if __name__=='__main__':unittest.main()
