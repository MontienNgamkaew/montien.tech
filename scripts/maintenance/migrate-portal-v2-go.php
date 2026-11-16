<?php
/** Add missing Go roles without removing any existing enum values or changing user rows. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__,2).'/api/env.php';
$name=(string)env('PNPGO_DB_NAME','carrequest');
$db=new PDO('mysql:host='.env('PNPGO_DB_HOST','localhost').';dbname='.$name.';charset=utf8mb4',(string)env('PNPGO_DB_USER','root'),(string)env('PNPGO_DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$column=$db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
$type=$column['Type'];
if(!preg_match("/^enum\\((.*)\\)$/D",$type,$m))throw new RuntimeException('Expected enum; manual schema review required');
$values=str_getcsv($m[1],',',"'",'\\');
$missing=array_values(array_diff(['user','driver','supply_head','deputy_director','director','admin'],$values));
echo json_encode(['database'=>$name,'append_roles'=>$missing,'mode'=>in_array('--apply',$argv,true)?'apply':'preview','preserve'=>'existing enum values, user IDs and passwords']).PHP_EOL;
if(!in_array('--apply',$argv,true)||!$missing)exit;
if(!in_array('--database='.$name,$argv,true))throw new RuntimeException('Exact --database confirmation required');
$definition='ENUM('.implode(',',array_map([$db,'quote'],array_merge($values,$missing))).')';
$null=$column['Null']==='YES'?' NULL':' NOT NULL';
$default=$column['Default']===null?'':' DEFAULT '.$db->quote($column['Default']);
$db->exec('ALTER TABLE users MODIFY COLUMN role '.$definition.$null.$default);
echo "Go role expansion completed.\n";
