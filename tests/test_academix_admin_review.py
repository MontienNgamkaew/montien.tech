"""Admin preview/review on guarded synthetic databases only; never production."""
import json
import re
import urllib.request
import urllib.error
from urllib.parse import urlencode
import unittest
import test_academix_teacher_access as access
BASE,Client,ROOT=access.BASE,access.Client,access.ROOT

class AdminReview(access.TeacherAccess):
    @classmethod
    def setUpClass(cls):
        super().setUpClass();t=cls()
        cls.reviewer=Client();assert cls.reviewer.login('stage_reviewer',cls.cfg['password'])[0]==200
        cls.viewer=Client();assert cls.viewer.login('stage_viewer',cls.cfg['password'])[0]==200
        cls.teacher=Client();assert cls.teacher.login('stage_member',cls.cfg['password'])[0]==200
        cls.other=Client();assert cls.other.login('stage_other',cls.cfg['password'])[0]==200
        for name,rights in [('stage_reviewer',['view','approve','export']),('stage_viewer',['view'])]:
            u=next(u for u in cls.master.call('members')[1]['users'] if u['username']==name)
            grant=dict(user_id=u['id'],app_id='pnp-academic',roles=['teacher','admin'],rights=rights,self_rights=['view','create','edit','export'],scope='department',scope_value='แผนกทดสอบ A',can_delegate=False,delegable_rights=[],revision=u['grants']['pnp-academic']['revision'])
            assert cls.master.call('save_grant',grant)[0]==200
        cls.cid,_=t.course(cls.teacher,{'username':'stage_member'});cls.cid2,_=t.course(cls.teacher,{'username':'stage_member'})
        cls.oid,_=t.course(cls.other,{'username':'stage_other'});cls.selfid,_=t.course(cls.reviewer,{'username':'stage_reviewer'})
        t.db("UPDATE courses SET course_code=CONCAT('20104-2001-',id),course_name='วงจรไฟฟ้ากระแสตรง (ข้อมูลจำลอง)' WHERE id=?",[cls.cid])
        t.db("UPDATE courses SET course_code=CONCAT('20104-2002-',id),course_name='เครื่องมือวัดไฟฟ้า (ข้อมูลจำลอง)' WHERE id=?",[cls.cid2])
        t.db("UPDATE courses SET course_name='OUTSIDE-SCOPE-SECRET' WHERE id=?",[cls.oid])
        folder=ROOT/'private-audit/portal-v2-stage/uploads/pnp-academix/uploads';cls.pdf=folder/'preview-fixture.pdf'
        if not cls.pdf.exists():
            stream=b'BT /F1 18 Tf 40 200 Td (Synthetic Academix preview) Tj ET'
            objects=[b'<< /Type /Catalog /Pages 2 0 R >>',b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 300] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',b'<< /Length '+str(len(stream)).encode()+b' >>\nstream\n'+stream+b'\nendstream',b'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>']
            data=b'%PDF-1.4\n';offsets=[]
            for i,obj in enumerate(objects,1):offsets.append(len(data));data+=f'{i} 0 obj\n'.encode()+obj+b'\nendobj\n'
            start=len(data);data+=b'xref\n0 6\n0000000000 65535 f \n'+b''.join(f'{offset:010} 00000 n \n'.encode() for offset in offsets)+f'trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{start}\n%%EOF\n'.encode();cls.pdf.write_bytes(data)
        assert cls.pdf.read_bytes().startswith(b'%PDF-')
        for kind,status,link in [('course_syllabus','approved',None),('lesson_plan','pending','https://drive.google.com/file/d/abcdefghijk123/view'),('teaching_materials','rejected','https://youtu.be/abcdefghijk')]:
            t.db("INSERT INTO submissions(course_id,system_type,status,submission_timing,file_path,drive_link,feedback) VALUES(?,?,?,'late','uploads/preview-fixture.pdf',?,?)",[cls.cid,kind,status,link,'ข้อเสนอแนะจำลอง'])
        for cid in [cls.oid,cls.selfid]:t.db("INSERT INTO submissions(course_id,system_type,status,submission_timing,file_path) VALUES(?,'lesson_plan','pending','on_time','uploads/preview-fixture.pdf')",[cid])
        cls.rows=t.db('SELECT * FROM submissions WHERE course_id=? ORDER BY id',[cls.cid]);cls.sid=cls.rows[1]['id']
        cls.outsid=t.db('SELECT id FROM submissions WHERE course_id=?',[cls.oid])[0]['id'];cls.ownsid=t.db('SELECT id FROM submissions WHERE course_id=?',[cls.selfid])[0]['id']
        for c in [cls.master,cls.reviewer,cls.viewer]:t.get(c,'pnp-academix/sso.php?workspace=admin')
        (ROOT/'private-audit/portal-v2-stage/admin-ui-fixture.json').write_text(json.dumps(dict(teacher_id=t.db('SELECT teacher_id FROM courses WHERE id=?',[cls.cid])[0]['teacher_id'],submission_id=cls.sid,pdf_submission=cls.rows[0]['id'])))
    def request(self,c,path,method='GET',fields=None,headers=None):
        req=urllib.request.Request(BASE+path,method=method,data=urlencode(fields).encode() if fields is not None else None,headers=headers or {})
        try:
            with c.opener.open(req) as r:return r.status,r.headers,r.read()
        except urllib.error.HTTPError as e:return e.code,e.headers,e.read()
    def page(self,c,sid=None):return self.get(c,f'pnp-academix/admin/review.php?submission_id={sid or self.sid}')
    def fields(self,c,sid=None,**changes):
        page=self.page(c,sid);token=self.token(page);revision=re.search(r'name="review_revision" value="([^"]+)"',page)
        self.assertIsNotNone(revision);data=dict(csrf_token=token,review_revision=revision[1],confirm_review=1,action='approve',feedback='ผลตรวจจำลอง');data.update(changes);return data
    def row(self,sid=None):return self.db('SELECT * FROM submissions WHERE id=?',[sid or self.sid])[0]
    def review(self,c,data,sid=None):return self.request(c,f'pnp-academix/admin/review.php?submission_id={sid or self.sid}','POST',data)
    def fingerprint(self):
        out={}
        for label in self.cfg['databases']:
            for item in self.db('SHOW TABLES',label=label):
                table=next(iter(item.values()));out[label+'.'+table]=sorted(json.dumps(r,sort_keys=True) for r in self.db('SELECT * FROM `'+table+'`',label=label))
        return out
    def test_01_views_previews_are_read_only_and_scoped(self):
        before=self.fingerprint();file=self.pdf.read_bytes();stamp=self.pdf.stat().st_mtime_ns
        page=self.get(self.reviewer,'pnp-academix/admin/overview.php')
        for label in ['โครงการสอน','แผนการสอน','สื่อการสอน','รอตรวจ','ยังไม่ส่ง']:self.assertIn(label,page)
        self.assertNotIn('OUTSIDE-SCOPE-SECRET',page)
        for row in self.rows:
            path=f'pnp-academix/admin/preview.php?submission_id={row["id"]}'
            self.assertEqual(self.request(self.reviewer,path)[0],200)
            self.assertEqual(self.request(self.viewer,path)[0],200)
            status,headers,body=self.request(self.reviewer,path+'&raw=1');self.assertEqual(status,200);self.assertEqual(body,file);self.assertIn('no-store',headers['Cache-Control'])
            status,_,body=self.request(self.reviewer,path+'&raw=1',headers={'Range':'bytes=0-9'});self.assertEqual(status,206);self.assertEqual(body,file[:10])
            self.assertEqual(self.request(self.reviewer,path+'&raw=1','HEAD')[2],b'')
            self.assertEqual(self.request(self.reviewer,path,'POST',{})[0],405)
        self.assertEqual(self.request(self.reviewer,f'pnp-academix/admin/preview.php?submission_id={self.outsid}&raw=1')[0],403)
        self.assertEqual(self.request(self.reviewer,f'pnp-academix/admin/review.php?submission_id={self.outsid}')[0],403)
        tid=self.db('SELECT teacher_id FROM courses WHERE id=?',[self.oid])[0]['teacher_id'];self.assertEqual(self.request(self.reviewer,f'pnp-academix/admin/review_teacher.php?teacher_id={tid}')[0],403)
        self.assertEqual(self.fingerprint(),before);self.assertEqual(self.pdf.read_bytes(),file);self.assertEqual(self.pdf.stat().st_mtime_ns,stamp)
    def test_02_no_approval_for_viewers_teachers_or_own_work(self):
        before=self.row();page=self.page(self.viewer);self.assertNotIn('value="approve"',page)
        self.assertEqual(self.review(self.viewer,{'action':'approve'})[0],403)
        self.assertEqual(self.request(self.teacher,'pnp-academix/admin/overview.php')[0],403)
        self.assertEqual(self.request(self.teacher,f'pnp-academix/admin/preview.php?submission_id={self.sid}&raw=1')[0],403)
        self.assertEqual(self.request(Client(),f'pnp-academix/admin/preview.php?submission_id={self.sid}&raw=1')[0],401)
        own=self.row(self.ownsid);self.assertNotIn('value="approve"',self.page(self.reviewer,self.ownsid))
        self.assertEqual(self.review(self.reviewer,{'action':'approve'},self.ownsid)[0],403)
        self.assertEqual(self.row(),before);self.assertEqual(self.row(self.ownsid),own)
    def test_03_stale_review_does_not_overwrite_replacement(self):
        fields=self.fields(self.reviewer);old=self.row()['drive_link']
        self.db('UPDATE submissions SET drive_link=? WHERE id=?',['https://example.invalid/new-version',self.sid]);before=self.row()
        try:
            status,_,body=self.review(self.reviewer,fields);self.assertEqual(status,409);self.assertIn('เอกสารถูกเปลี่ยน',body.decode());self.assertEqual(self.row(),before);self.assertNotIn('value="approve"',body.decode())
        finally:self.db('UPDATE submissions SET drive_link=? WHERE id=?',[old,self.sid])
    def test_04_csrf_confirmation_and_rejection_reason(self):
        before=self.row()
        for change in [dict(csrf_token='bad'),dict(review_revision='bad'),dict(confirm_review=''),dict(action='reject',feedback='')]:
            self.review(self.reviewer,self.fields(self.reviewer,**change));self.assertEqual(self.row(),before)
    def test_05_explicit_review_changes_only_status_and_feedback(self):
        before=self.row();file=self.pdf.read_bytes();stamp=self.pdf.stat().st_mtime_ns
        for action,status in [('approve','approved'),('reject','rejected')]:
            self.assertEqual(self.review(self.reviewer,self.fields(self.reviewer,action=action,feedback='ผลตรวจ '+status))[0],200)
            after=self.row();self.assertEqual(after['status'],status);self.assertEqual(after['feedback'],'ผลตรวจ '+status)
            self.assertEqual({k:v for k,v in before.items() if k not in ['status','feedback','updated_at']},{k:v for k,v in after.items() if k not in ['status','feedback','updated_at']})
        self.assertEqual(self.pdf.read_bytes(),file);self.assertEqual(self.pdf.stat().st_mtime_ns,stamp)
    def test_06_revoked_scope_blocks_open_preview(self):
        before=self.row();u=next(u for u in self.master.call('members')[1]['users'] if u['username']=='stage_viewer');g=u['grants']['pnp-academic']
        data=dict(user_id=u['id'],app_id='pnp-academic',roles=['teacher','admin'],rights=['view'],self_rights=['view','create','edit','export'],scope='department',scope_value='แผนกทดสอบ B',can_delegate=False,delegable_rights=[],revision=g['revision'])
        self.assertEqual(self.master.call('save_grant',data)[0],200)
        try:self.assertEqual(self.request(self.viewer,f'pnp-academix/admin/preview.php?submission_id={self.sid}&raw=1')[0],403);self.assertEqual(self.row(),before)
        finally:data.update(scope_value='แผนกทดสอบ A',revision=data['revision']+1);self.assertEqual(self.master.call('save_grant',data)[0],200)
for name in vars(access.TeacherAccess):
    if name.startswith('test_'):setattr(AdminReview,name,None)
if __name__=='__main__':unittest.main()
