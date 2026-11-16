"""Synthetic localhost only: automatic teacher access plus existing submission conditions."""
import json
import re
import secrets
import subprocess
import unittest
import urllib.request
import urllib.error
from urllib.parse import urlencode
from test_portal_v2_integration import Client, ROOT
BASE='http://127.0.0.1:18767/pnp-portal/'

class TeacherAccess(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.cfg=json.loads((ROOT/'private-audit/portal-v2-stage/stage.json').read_text())
        assert all(x.startswith('pnp_v2_stage_') for x in cls.cfg['databases'].values())
        cls.master=Client();assert cls.master.login('stage_admin',cls.cfg['password'])[0]==200
    def db(self,sql,params=(),label='academix'):
        code=r'''
$i=json_decode(stream_get_contents(STDIN),true);if(!preg_match('/^pnp_v2_stage_[a-f0-9]+_(portal|academix|pnpgo)$/D',$i['db']))throw new Exception('Not fixture');$p=new PDO('mysql:unix_socket=/private/tmp/montien-restore-20261116/mariadb.sock;dbname='.$i['db'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);if((int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1||realpath($p->query('SELECT @@datadir')->fetchColumn())!==realpath($i['datadir']))throw new Exception('Wrong instance');$s=$p->prepare($i['sql']);$s->execute($i['params']);echo json_encode($s->columnCount()?$s->fetchAll():[]);
'''
        payload=dict(db=self.cfg['databases'][label],sql=sql,params=list(params),datadir=str(ROOT/'private-audit/mariadb-restore-20261116/data'))
        return json.loads(subprocess.check_output(['php','-r',code],input=json.dumps(payload).encode()))
    def member(self,position='ครูพิเศษสอน'):
        name='auto_'+secrets.token_hex(4);password=secrets.token_urlsafe(15)
        fields=dict(username=name,password=password,first_name='ครูอัตโนมัติ',last_name=name,email=name+'@example.invalid',primary_position=position,department='แผนกทดสอบ A')
        status,result=self.master.call('save_member',fields);self.assertEqual(status,200,result)
        client=Client();self.assertEqual(client.login(name,password)[0],200)
        self.assertEqual(client.call('change_password',dict(old_password=password,password=password+'x'))[0],200)
        return client,result['user'],fields
    def get(self,c,path):
        with c.opener.open(BASE+path) as r:return r.read().decode()
    def post(self,c,path,data):
        request=urllib.request.Request(BASE+path,data=urlencode(data).encode(),headers={'Content-Type':'application/x-www-form-urlencoded'})
        with c.opener.open(request) as r:return r.read().decode()
    def token(self,html):
        match=re.search(r'name="csrf_token"\s+value="([^"]+)"',html);self.assertIsNotNone(match);return match[1]
    def course(self,c,user):
        self.get(c,'pnp-academix/sso.php?workspace=self')
        html=self.get(c,'pnp-academix/teacher/dashboard.php');code='AUTO-'+secrets.token_hex(4)
        self.post(c,'pnp-academix/teacher/dashboard.php',dict(action='add_course',course_code=code,course_name=code,csrf_token=self.token(html)))
        rows=self.db('SELECT c.id FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.course_code=? AND u.username=?',[code,user['username']]);self.assertEqual(len(rows),1);return rows[0]['id'],code
    def submit(self,c,cid,kind,**changes):
        path=f'pnp-academix/teacher/submit.php?course_id={cid}&system_type={kind}';html=self.get(c,path)
        fields=dict(action='submit_document',course_id=cid,system_type=kind,drive_link='https://example.invalid/'+kind,csrf_token=self.token(html));fields.update(changes)
        return self.post(c,path,fields)
    def test_01_all_three_teacher_positions_without_app_grants(self):
        for position in ['ข้าราชการครู','พนักงานราชการครู','ครูพิเศษสอน']:
            c,u,_=self.member(position)
            for right in ['view','create','edit','export']:self.assertEqual(c.call('session&app=pnp-academic&workspace=self&right='+right)[0],200)
            for right in ['approve','manage_users','manage_settings']:self.assertEqual(c.call('session&app=pnp-academic&right='+right)[0],403)
            self.assertEqual(c.call('session&app=pnp-academic&workspace=admin')[0],403)
            access=next(a['access'] for a in c.call('bootstrap')[1]['systems'] if a['id']=='pnp-academic')
            self.assertTrue(access['automatic_teacher']);self.assertEqual(access['assigned_roles'],[]);self.assertFalse(access['can_delegate'])
            self.assertEqual(self.db('SELECT COUNT(*) AS n FROM app_roles WHERE user_id=?',[u['id']],'portal')[0]['n'],0)
    def test_02_non_teacher_does_not_receive_access(self):
        c,_,_=self.member('เจ้าหน้าที่');self.assertEqual(c.call('session&app=pnp-academic&workspace=self')[0],403)
    def test_08_saving_automatic_teacher_does_not_create_manual_teacher_role(self):
        c,u,_=self.member();grant=next(a['access'] for a in c.call('bootstrap')[1]['systems'] if a['id']=='pnp-academic')
        data=dict(user_id=u['id'],app_id='pnp-academic',roles=grant['assigned_roles'],rights=grant['rights'],self_rights=grant['assigned_self_rights'],scope='all',scope_value='',can_delegate=False,delegable_rights=[],revision=0)
        self.assertEqual(self.master.call('save_grant',data)[0],200)
        stored=self.db('SELECT roles_json FROM pnp_v2_role_profiles WHERE user_id=? AND app_id=?',[u['id'],'pnp-academic'],'portal')[0]
        self.assertEqual(json.loads(stored['roles_json']),[])
        self.assertEqual(c.call('session&app=pnp-academic&workspace=self&right=create')[0],200)
        self.assertEqual(c.call('session&app=pnp-academic&workspace=admin')[0],403)

    def test_03_registration_waits_for_approval(self):
        name='pending_'+secrets.token_hex(4);password=secrets.token_urlsafe(15);c=Client()
        fields=dict(username=name,password=password,first_name='ครู',last_name='รออนุมัติ',email=name+'@example.invalid',primary_position='ข้าราชการครู')
        self.assertEqual(c.call('register',fields)[0],200);self.assertEqual(c.login(name,password)[0],403)
        u=next(u for u in self.master.call('members')[1]['users'] if u['username']==name)
        self.assertEqual(self.master.call('member_state',dict(id=u['id'],state='approve'))[0],200)
        self.assertEqual(c.login(name,password)[0],200);self.assertEqual(c.call('session&app=pnp-academic&workspace=self&right=create')[0],200)
    def test_04_three_document_types_and_own_results(self):
        c,u,_=self.member();cid,code=self.course(c,u)
        for kind in ['course_syllabus','lesson_plan','teaching_materials']:
            self.submit(c,cid,kind)
            row=self.db('SELECT status,submission_timing FROM submissions WHERE course_id=? AND system_type=?',[cid,kind]);self.assertEqual(row[0]['status'],'pending');self.assertEqual(row[0]['submission_timing'],'on_time')
        self.assertIn(code,self.get(c,'pnp-academix/teacher/dashboard.php'))
        other,_,_=self.member();self.get(other,'pnp-academix/sso.php?workspace=self')
        page=self.get(other,f'pnp-academix/teacher/submit.php?course_id={cid}&system_type=lesson_plan');self.assertNotIn(code,page);self.assertNotIn('https://example.invalid/lesson_plan',page)
    def test_05_closed_late_and_approved_rules_unchanged(self):
        c,u,_=self.member();cid,_=self.course(c,u);kind='lesson_plan'
        try:
            self.db("UPDATE system_settings SET is_open=0 WHERE semester_id=1 AND system_type=?",[kind]);self.submit(c,cid,kind)
            self.assertEqual(self.db('SELECT COUNT(*) AS n FROM submissions WHERE course_id=?',[cid])[0]['n'],0)
            self.db("UPDATE system_settings SET is_open=1,deadline_date='2000-01-01 00:00:00' WHERE semester_id=1 AND system_type=?",[kind]);self.submit(c,cid,kind)
            row=self.db('SELECT id,submission_timing FROM submissions WHERE course_id=?',[cid])[0];self.assertEqual(row['submission_timing'],'late')
            self.db("UPDATE submissions SET status='approved' WHERE id=?",[row['id']]);self.submit(c,cid,kind,drive_link='https://example.invalid/changed',confirm_overwrite=1)
            after=self.db('SELECT status,drive_link FROM submissions WHERE id=?',[row['id']])[0];self.assertEqual(after['status'],'approved');self.assertEqual(after['drive_link'],'https://example.invalid/lesson_plan')
        finally:self.db("UPDATE system_settings SET is_open=1,deadline_date='2030-12-31 23:59:59' WHERE semester_id=1 AND system_type=?",[kind])
    def test_06_position_change_and_suspension(self):
        c,u,fields=self.member();fields.update(id=u['id'],primary_position='เจ้าหน้าที่');self.assertEqual(self.master.call('save_member',fields)[0],200)
        self.assertEqual(c.call('session&app=pnp-academic&workspace=self')[0],403)
        fields['primary_position']='ครูพิเศษสอน';self.assertEqual(self.master.call('save_member',fields)[0],200);self.assertEqual(c.call('session&app=pnp-academic&workspace=self')[0],200)
        self.assertEqual(self.master.call('member_state',dict(id=u['id'],state='suspended'))[0],200);self.assertEqual(c.call('session&app=pnp-academic&workspace=self')[0],401)
    def test_07_cross_user_submission_and_pending_replacement(self):
        c,u,_=self.member();cid,_=self.course(c,u);kind='course_syllabus';self.submit(c,cid,kind)
        before=self.db('SELECT id,drive_link FROM submissions WHERE course_id=?',[cid])[0]
        self.submit(c,cid,kind,drive_link='https://example.invalid/replacement')
        self.assertEqual(self.db('SELECT id,drive_link FROM submissions WHERE course_id=?',[cid])[0],before)
        self.submit(c,cid,kind,drive_link='https://example.invalid/replacement',confirm_overwrite=1)
        after=self.db('SELECT id,drive_link FROM submissions WHERE course_id=?',[cid])[0];self.assertEqual(after['id'],before['id']);self.assertEqual(after['drive_link'],'https://example.invalid/replacement')
        other,_,_=self.member();self.get(other,'pnp-academix/sso.php?workspace=self');self.submit(other,cid,kind,drive_link='https://example.invalid/foreign',confirm_overwrite=1)
        self.assertEqual(self.db('SELECT id,drive_link FROM submissions WHERE course_id=?',[cid])[0],after)

if __name__=='__main__':unittest.main()
