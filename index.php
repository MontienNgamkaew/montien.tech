<?php
declare(strict_types=1);
require_once __DIR__.'/api/v2/core.php';
header('Cache-Control: no-store');
if(PortalV2::enabled()){header('Location: '.PortalV2::base().'/portal-v2/',true,302);exit;}
header('Content-Type: text/html; charset=utf-8');readfile(__DIR__.'/index.html');
