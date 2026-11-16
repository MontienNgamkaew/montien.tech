"""Local synthetic multi-role/delegation authorization checks; never production."""
import importlib.util
import json
from pathlib import Path
import secrets
import subprocess
import unittest
import urllib.request
import urllib.error
from test_portal_v2_integration import Client, ROOT

BASE='http://127.0.0.1:18767/pnp-portal/'
class MultiRole(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.cfg=json.loads((ROOT/'private-audit/portal-v2-stage/stage.json').read_text())
        assert all(n.startswith('pnp_v2_stage_') for n in cls.cfg['databases'].values())
        cls.master=Client();assert cls.master.login('stage_admin',cls.cfg['password'])[0]==200
        cls.parent=Client();assert cls.parent.login('stage_reviewer',cls.cfg['password'])[0]==200
        cls.staff=Client();assert cls.staff.login('stage_member',cls.cfg['password'])[0]==200
        cls.users={u['username']:u for u in cls.master.call('members')[1]['users']}
    def grant(self,name,**changes):
        u=next(u for u in self.master.call('members')[1]['users'] if u['username']==name)
        data=dict(user_id=u['id'],app_id='pnp-academic',roles=['teacher','admin'],rights=['view','approve','export'],self_rights=['view','create','edit','export'],scope='department',scope_value='แผนกทดสอบ A',can_delegate=True,delegable_rights=['view','approve'],revision=u['grants']['pnp-academic']['revision']);data.update(changes)
        return self.master.call('save_grant',data)
    def test_01_teacher_and_admin_workspaces(self):
        self.assertEqual(self.grant('stage_reviewer')[0],200)
        self.assertEqual(self.parent.call('session&app=pnp-academic&workspace=self&right=create')[0],200)
        self.assertEqual(self.parent.call('session&app=pnp-academic&workspace=admin&right=approve')[0],200)
        self.assertEqual(self.parent.call('session&app=pnp-academic&workspace=admin&right=create')[0],403)
        with self.parent.opener.open(BASE+'pnp-academix/sso.php?workspace=self') as r:
            self.assertTrue(r.url.endswith('/teacher/dashboard.php'));self.assertEqual(r.status,200);self.assertIn('งานดูแลระบบ',r.read().decode())
        with self.parent.opener.open(BASE+'pnp-academix/admin/overview.php') as r:self.assertEqual(r.status,200)
    def test_02_delegate_without_losing_teaching(self):
        self.assertEqual(self.grant('stage_reviewer')[0],200)
        uid=self.users['stage_member']['id']
        status,listing=self.parent.call('staff&app=pnp-academic');self.assertEqual(status,200)
        self.assertNotIn('stage_other',[u['username'] for u in listing['members']])
        old=next((a for a in listing['assignments'] if a['user_id']==uid),None)
        data=dict(user_id=uid,app_id='pnp-academic',rights=['view','approve'],scope='department',scope_value='แผนกทดสอบ A',revision=old['revision'] if old else 0)
        self.assertEqual(self.parent.call('assign_staff',data)[0],200)
        self.assertEqual(self.staff.call('session&app=pnp-academic&workspace=admin&right=approve')[0],200)
        self.assertEqual(self.staff.call('session&app=pnp-academic&workspace=self&right=create')[0],200)
        self.assertEqual(self.staff.call('staff&app=pnp-academic')[0],403)
        self.assertEqual(self.staff.call('assign_staff',dict(data,user_id=self.users['stage_other']['id']))[0],403)
        self.assertEqual(self.parent.call('assign_staff',data)[0],409)
    def test_03_escalation_and_scope_denied(self):
        self.assertEqual(self.grant('stage_reviewer')[0],200)
        data=dict(app_id='pnp-academic',rights=['view','approve'],scope='department',scope_value='แผนกทดสอบ A',revision=0)
        for name in ['stage_reviewer','stage_admin','stage_other']:
            self.assertEqual(self.parent.call('assign_staff',dict(data,user_id=self.users[name]['id']))[0],403)
        uid=self.users['stage_viewer']['id']
        self.assertEqual(self.parent.call('assign_staff',dict(data,user_id=uid,rights=['view','manage_settings']))[0],403)
        self.assertEqual(self.parent.call('assign_staff',dict(data,user_id=uid,scope='all'))[0],403)
        self.assertEqual(self.parent.call('members')[0],403)
    def test_04_parent_revocation_removes_staff_authority_immediately(self):
        self.assertEqual(self.grant('stage_reviewer',can_delegate=False,delegable_rights=[])[0],200)
        self.assertEqual(self.staff.call('session&app=pnp-academic&workspace=admin')[0],403)
        self.assertEqual(self.staff.call('session&app=pnp-academic&workspace=self&right=create')[0],200)
        self.assertEqual(self.grant('stage_reviewer')[0],200)
        listing=self.parent.call('staff&app=pnp-academic')[1]
        a=next(a for a in listing['assignments'] if a['user_id']==self.users['stage_member']['id'])
        self.assertEqual(self.parent.call('revoke_staff',dict(app_id='pnp-academic',user_id=a['user_id'],revision=a['revision']))[0],200)
        self.assertEqual(self.staff.call('session&app=pnp-academic&workspace=admin')[0],403)
        self.assertEqual(self.staff.call('session&app=pnp-academic&workspace=self&right=create')[0],200)
    def test_05_self_review_denied_before_write(self):
        self.assertEqual(self.grant('stage_reviewer')[0],200)
        self.parent.opener.open(BASE+'pnp-academix/sso.php?workspace=self').read()
        code=r'''
$i=json_decode(stream_get_contents(STDIN),true);$p=new PDO('mysql:unix_socket=/private/tmp/montien-restore-20261116/mariadb.sock;dbname='.$i['db'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(!preg_match('/^pnp_v2_stage_[a-f0-9]+_academix$/D',$i['db'])||(int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1)throw new Exception('Not staging');
$id=$p->query("SELECT id FROM users WHERE username='stage_reviewer'")->fetchColumn();$s=$p->prepare('INSERT INTO courses(course_code,course_name,teacher_id,semester_id) VALUES (?,?,?,1)');$s->execute([$i['code'],'ทดสอบหลายบทบาท',$id]);$cid=$p->lastInsertId();$s=$p->prepare("INSERT INTO submissions(course_id,system_type,status) VALUES (?,'lesson_plan','pending')");$s->execute([$cid]);$sid=$p->lastInsertId();$s=$p->prepare('UPDATE users SET username=? WHERE id=?');$s->execute(['Stage_Reviewer',$id]);echo $sid;
'''
        sid=subprocess.check_output(['php','-r',code],input=json.dumps({'db':self.cfg['databases']['academix'],'code':'MULTI_'+secrets.token_hex(4)}).encode()).decode()
        req=urllib.request.Request(BASE+'pnp-academix/admin/review.php?submission_id='+sid,data=b'action=approve',headers={'Content-Type':'application/x-www-form-urlencoded'})
        try:self.parent.opener.open(req);self.fail('self review accepted')
        except urllib.error.HTTPError as e:self.assertEqual(e.code,403);self.assertIn('ตนเอง',e.read().decode())
    def test_07_existing_admin_owned_courses_remain_reviewable(self):
        self.assertEqual(self.grant('stage_reviewer')[0],200)
        self.master.opener.open(BASE+'pnp-academix/sso.php?workspace=self').read()
        code=r"""
$i=json_decode(stream_get_contents(STDIN),true);if(!preg_match('/^pnp_v2_stage_[a-f0-9]+_academix$/D',$i['db']))throw new Exception('Not staging');$p=new PDO('mysql:unix_socket=/private/tmp/montien-restore-20261116/mariadb.sock;dbname='.$i['db'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);if((int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1)throw new Exception('Network instance');$id=$p->query("SELECT id FROM users WHERE username='stage_admin'")->fetchColumn();$s=$p->prepare('INSERT INTO courses(course_code,course_name,teacher_id,semester_id) VALUES (?,?,?,1)');$s->execute([$i['code'],$i['code'],$id]);echo $id;
"""
        course='ADMIN-MULTI-'+secrets.token_hex(3)
        uid=subprocess.check_output(['php','-r',code],input=json.dumps({'db':self.cfg['databases']['academix'],'code':course}).encode()).decode()
        with self.parent.opener.open(BASE+'pnp-academix/admin/review_teacher.php?teacher_id='+uid) as r:
            self.assertEqual(r.status,200);self.assertIn(course,r.read().decode())

    def test_08_self_work_survives_no_admin_task_rights(self):
        try:
            self.assertEqual(self.grant('stage_reviewer',rights=[],can_delegate=False,delegable_rights=[])[0],200)
            self.assertEqual(self.parent.call('session&app=pnp-academic&workspace=self&right=create')[0],200)
            self.assertEqual(self.parent.call('session&app=pnp-academic')[0],200)
            self.assertEqual(self.parent.call('session&app=pnp-academic&workspace=admin')[0],403)
        finally:self.grant('stage_reviewer')

    def test_06_master_validation(self):
        self.assertEqual(self.grant('stage_viewer',roles=['teacher'],rights=['view'],can_delegate=True,delegable_rights=['view'])[0],400)
        self.assertEqual(self.grant('stage_viewer',roles=['admin'],self_rights=['view'])[0],200)
        self.assertEqual(self.grant('stage_viewer',delegable_rights=['view','manage_settings'])[0],400)

if __name__=='__main__':unittest.main()
