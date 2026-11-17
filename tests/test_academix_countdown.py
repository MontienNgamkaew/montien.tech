"""Pure deadline/animation logic and an authenticated, read-only synthetic landing."""
import json
import subprocess
import unittest
import test_academix_teacher_access as access
ROOT=access.ROOT

class CountdownLogic(unittest.TestCase):
    def test_boundaries_speed_and_mission_selection(self):
        code=r'''
const assert=require('node:assert/strict');const c=require('./pnp-academix/assets/academix-landing.js');
const now=1794826800000,day=86400000;
assert.equal(c.state(now+1,true,now).kind,'active');
assert.equal(c.state(now,true,now).kind,'expired');
assert.equal(c.state(now-1,true,now).mood,'dizzy');
assert.equal(c.state(0,true,now).kind,'expired');
assert.equal(c.state(now+day,false,now).kind,'closed');
assert.equal(c.state(now-1,false,now).mood,'dizzy');
assert.equal(c.state(null,true,now).kind,'undated');
assert.equal(c.state(NaN,true,now).kind,'undated');
assert.equal(c.state(now,true,now,false).kind,'unconfigured');
assert.deepEqual(c.parts(null),{days:'—',hours:'—',minutes:'—',seconds:'—'});
assert.deepEqual(c.parts(0),{days:'00',hours:'00',minutes:'00',seconds:'00'});
assert.deepEqual(c.parts(90061000),{days:'01',hours:'01',minutes:'01',seconds:'01'});
assert.equal(c.parts(1).seconds,'01');assert.equal(c.parts(-10).seconds,'00');
assert.equal(c.parts(1000*86400000).days,'1000');
const durations=[30,7,3,1,1/24,1/1440].map(days=>c.state(now+days*day,true,now).speed);
for(let i=1;i<durations.length;i++)assert.ok(durations[i]<=durations[i-1]);
assert.ok(durations.every(d=>d>=.26&&d<=1.8));
assert.equal(c.state(now+day,true,now).mood,'rush');
const entries=[{key:'past',deadline:now-1,open:true,configured:true},{key:'later',deadline:now+day,open:true,configured:true},{key:'next',deadline:now+3600000,open:true,configured:true},{key:'closed',deadline:now+1,open:false,configured:true}];
assert.equal(c.mission(entries,now).key,'next');
assert.equal(c.mission(entries.slice(0,1),now).state.mood,'dizzy');
assert.equal(c.mission([],now),null);
assert.equal(c.mission([{deadline:null,open:true,configured:true},{deadline:now-1,open:true,configured:true}],now).state.kind,'undated');
console.log('Deadline boundaries, zero/unknown/closed cases, monotonic urgency and mission priority passed');
'''
        subprocess.run(['node','-e',code],cwd=ROOT,check=True,capture_output=True)
    def test_bangkok_datetime_parse_and_invalid_dates(self):
        code=r'''require 'pnp-academix/includes/landing-support.php';$d=ac_deadline_epoch('2026-11-16 18:00:00');if($d!==1794826800000)throw new Exception('Timezone mismatch');foreach([null,'','0000-00-00 00:00:00','2026-02-30 18:00:00','2026-11-16 25:00:00','tomorrow','2026-11-16 18:00:00 UTC'] as $v)if(ac_deadline_epoch($v)!==null)throw new Exception('Invalid date');echo 'ok';'''
        self.assertEqual(subprocess.check_output(['php','-r',code],cwd=ROOT),b'ok')

class CountdownPage(access.TeacherAccess):
    def test_landing_is_read_only(self):
        client=access.Client();self.assertEqual(client.login('stage_member',self.cfg['password'])[0],200)
        self.get(client,'pnp-academix/sso.php?workspace=self')
        def fingerprint():
            result={}
            for label in self.cfg['databases']:
                for item in self.db('SHOW TABLES',label=label):
                    table=next(iter(item.values()));result[label+'.'+table]=sorted(json.dumps(r,sort_keys=True) for r in self.db('SELECT * FROM `'+table+'`',label=label))
            return result
        before=fingerprint();page=self.get(client,'pnp-academix/index.php')
        for marker in ['ac-dark-landing','data-server-now','academix-landing.js','data-unit="seconds"','พักแอนิเมชัน','teacher/dashboard.php']:self.assertIn(marker,page)
        self.assertEqual(page.count('data-deadline-card'),3);self.assertEqual(page.count('data-mascot'),4)
        self.assertNotIn('<form',page);self.assertEqual(fingerprint(),before)
        # Settings are global public-to-members deadlines, not submission details.
        self.assertNotIn('teacher_fullname',page)
for name in vars(access.TeacherAccess):
    if name.startswith('test_'):setattr(CountdownPage,name,None)
if __name__=='__main__':unittest.main()
