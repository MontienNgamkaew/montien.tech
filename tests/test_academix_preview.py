"""Read-only previews against isolated synthetic databases only."""
import hashlib
import json
import urllib.request
import urllib.error
import unittest
import test_academix_teacher_access as access
BASE,Client,ROOT=access.BASE,access.Client,access.ROOT

class Preview(access.TeacherAccess):
    # Do not inherit business test methods; they run in their own suite.
    def test_preview(self):
        c,u,_=self.member();cid,code=self.course(c,u)
        other,ou,_=self.member();ocid,_=self.course(other,ou)
        folder=ROOT/'private-audit/portal-v2-stage/uploads/pnp-academix/uploads'
        file=folder/'preview-fixture.pdf'
        # Small valid, synthetic single-page PDF.
        objects=[b'<< /Type /Catalog /Pages 2 0 R >>',b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 300] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',b'<< /Length 58 >>\nstream\nBT /F1 18 Tf 40 200 Td (Synthetic Academix preview) Tj ET\nendstream',b'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>']
        data=b'%PDF-1.4\n';offsets=[]
        for i,obj in enumerate(objects,1):offsets.append(len(data));data+=f'{i} 0 obj\n'.encode()+obj+b'\nendobj\n'
        xref=len(data);data+=b'xref\n0 6\n0000000000 65535 f \n'+b''.join(f'{x:010} 00000 n \n'.encode() for x in offsets)+f'trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n'.encode();file.write_bytes(data)
        for kind,status,link,path in [('course_syllabus','approved',None,'uploads/preview-fixture.pdf'),('lesson_plan','pending','https://drive.google.com/file/d/abcdefghijk123/view',None),('teaching_materials','rejected','https://youtu.be/abcdefghijk',None)]:
            self.db('INSERT INTO submissions(course_id,system_type,status,submission_timing,drive_link,file_path,feedback) VALUES(?,?,?,\'late\',?,?,?)',[cid,kind,status,link,path,'ข้อเสนอแนะจำลอง'])
        rows=self.db('SELECT * FROM submissions WHERE course_id=? ORDER BY id',[cid]);before=json.dumps(rows,sort_keys=True);stamp=file.stat().st_mtime_ns
        dashboard=self.get(c,'pnp-academix/teacher/dashboard.php')
        for label in ['โครงการสอน','แผนการสอน','สื่อการสอน','อนุมัติแล้ว','รอตรวจ','ต้องแก้ไข','ดูงานที่ส่ง']:self.assertIn(label,dashboard)
        def fingerprint():
            result={}
            for label in self.cfg['databases']:
                for row in self.db('SHOW TABLES',label=label):
                    table=next(iter(row.values()));records=self.db('SELECT * FROM `'+table+'`',label=label)
                    result[label+'.'+table]=sorted(json.dumps(r,sort_keys=True) for r in records)
            return result
        original=fingerprint()
        def request(client,path,method='GET',headers={}):
            try:
                r=client.opener.open(urllib.request.Request(BASE+path,method=method,headers=headers))
                return r.status,r.headers,r.read()
            except urllib.error.HTTPError as e:return e.code,e.headers,e.read()
        path=f'pnp-academix/teacher/preview.php?submission_id={rows[0]["id"]}'
        self.assertEqual(request(c,path)[0],200)
        raw=path+'&raw=1';status,headers,body=request(c,raw)
        self.assertEqual(status,200);self.assertEqual(body,data);self.assertIn('no-store',headers['Cache-Control'])
        self.assertEqual(request(c,raw,headers={'Range':'bytes=0-9'})[::2],(206,data[:10]))
        self.assertEqual(request(c,raw,headers={'Range':'bytes=-10'})[::2],(206,data[-10:]))
        self.assertEqual(request(c,raw,headers={'Range':'bytes=999999-'})[0],416)
        self.assertEqual(request(c,raw,method='HEAD')[2],b'')
        self.assertEqual(request(c,path,method='POST')[0],405)
        self.assertEqual(request(other,path)[0],404)
        self.assertEqual(request(self.master,path)[0],404)
        self.assertEqual(request(Client(),raw)[0],401)
        self.assertEqual(request(c,'pnp-academix/teacher/preview.php?submission_id=0')[0],400)
        for row in rows[1:]:
            page=self.get(c,f'pnp-academix/teacher/preview.php?submission_id={row["id"]}')
            self.assertIn('iframe',page)
        self.assertEqual(fingerprint(),original)
        self.db('UPDATE system_settings SET is_open=0 WHERE semester_id=1')
        try:self.assertEqual(request(c,path)[0],200)
        finally:self.db('UPDATE system_settings SET is_open=1 WHERE semester_id=1')
        self.assertEqual(json.dumps(self.db('SELECT * FROM submissions WHERE course_id=? ORDER BY id',[cid]),sort_keys=True),before)
        self.assertEqual(file.read_bytes(),data);self.assertEqual(file.stat().st_mtime_ns,stamp)
        # Keep a synthetic UI account, IDs only; credentials stay in stage.json.
        (ROOT/'private-audit/portal-v2-stage/ui-fixture.json').write_text(json.dumps({'username':u['username'],'course_id':cid,'submission_ids':[r['id'] for r in rows]}))

# The access suite is run separately.
for name in list(vars(access.TeacherAccess)):
    if name.startswith('test_'):setattr(Preview,name,None)
if __name__=='__main__':unittest.main()
