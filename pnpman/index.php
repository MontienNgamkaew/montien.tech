<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/portal_access.php';
if(pnp_portal_v2())pnp_portal_guard('pnp-man');
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
$entry=__DIR__.'/frontend/dist/index.html';
if(!is_file($entry)){http_response_code(503);exit('กรุณา build frontend ก่อนเปิดใช้งาน');}
readfile($entry);
