"""Pure source/range validation; no database or network."""
import json
from pathlib import Path
import subprocess
import unittest
ROOT=Path(__file__).resolve().parents[1]
class Sources(unittest.TestCase):
    def test_untrusted_urls_and_ranges(self):
        code=r'''require 'pnp-academix/includes/preview-support.php';
$bad=['javascript:alert(1)','data:text/html,hi','https://user:password@drive.google.com/file/d/abcdefghijk/view',"https://example.com/\nfoo"];
foreach($bad as $url)if(ac_external_source($url)!==null)throw new Exception('Unsafe URL');
foreach(['https://drive.google.com.evil.invalid/file/d/abcdefghijk/view','https://example.invalid/document.pdf','http://drive.google.com/file/d/abcdefghijk/view','https://drive.google.com:444/file/d/abcdefghijk/view'] as $url)if(ac_external_source($url)['embed']!==null)throw new Exception('Unsafe embed');
if(ac_external_source('https://drive.google.com/open?id=abcdefghijk')['embed']!=='https://drive.google.com/file/d/abcdefghijk/preview')throw new Exception('Drive');
if(ac_external_source('https://youtu.be/abcdefghijk')['embed']!=='https://www.youtube-nocookie.com/embed/abcdefghijk')throw new Exception('Video');
foreach(['uploads/../api/env.php','uploads/..\\api/env.php','/etc/passwd'] as $path)if(ac_pdf_source($path,[getcwd()]))throw new Exception('Traversal');
foreach(['bytes=5-2','bytes=100-','bytes=-0','bytes=0-1,3-4','garbage'] as $range)if(ac_pdf_range(100,$range)!==null)throw new Exception('Range');
if(ac_pdf_range(100,'bytes=-10')!==[90,99,true])throw new Exception('Suffix');echo 'ok';'''
        self.assertEqual(subprocess.check_output(['php','-r',code],cwd=ROOT),b'ok')
if __name__=='__main__':unittest.main()
