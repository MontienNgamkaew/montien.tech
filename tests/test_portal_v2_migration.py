"""Run only against the private synthetic staging MariaDB instance."""
import importlib.util
import json
import subprocess
import unittest
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
class MigrationPreservation(unittest.TestCase):
    def test_idempotent_and_preserves_existing_rows(self):
        spec=importlib.util.spec_from_file_location('stage',ROOT/'scripts/maintenance/serve-portal-v2.py')
        stage=importlib.util.module_from_spec(spec);spec.loader.exec_module(stage)
        config=json.loads(stage.CONFIG.read_text())
        self.assertTrue(all(x.startswith('pnp_v2_stage_') for x in config['databases'].values()))
        php=r'''
$i=json_decode(stream_get_contents(STDIN),true);$p=new PDO('mysql:unix_socket='.$i['socket'],'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath($p->query('SELECT @@datadir')->fetchColumn())!==realpath($i['datadir'])||(int)$p->query('SELECT @@skip_networking')->fetchColumn()!==1)throw new Exception('Wrong instance');
$out=[];foreach($i['databases'] as $label=>$name){if(!preg_match('/^pnp_v2_stage_[a-f0-9]+_(portal|pnpgo|academix)$/D',$name))throw new Exception('Unsafe DB');$out[$label]=hash('sha256',json_encode($p->query('SELECT * FROM `'.$name.'`.users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)));}echo json_encode($out);
'''
        inputs=json.dumps(dict(config,socket=stage.SOCKET,datadir=str(stage.DATADIR))).encode()
        def snapshot():return subprocess.check_output(['php','-r',php],input=inputs)
        before=snapshot()
        target=stage.PRIVATE/'public/pnp-portal/scripts/maintenance'
        for script,label in [('migrate-portal-v2.php','portal'),('migrate-portal-v2-go.php','pnpgo')]:
            subprocess.run(['php','-d','pdo_mysql.default_socket='+stage.SOCKET,str(target/script),'--apply','--database='+config['databases'][label]],env=stage.environment(config),check=True,stdout=subprocess.DEVNULL)
        self.assertEqual(before,snapshot(),'Existing users/passwords/IDs changed')

if __name__=='__main__':unittest.main()
