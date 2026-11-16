<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/v2/core.php';
http_response_code(503);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('Retry-After: 3600');
$link=htmlspecialchars(PortalV2::base().'/portal-v2/',ENT_QUOTES,'UTF-8');
?><!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lesson Plan พักบริการชั่วคราว</title><body style="margin:0;background:#f4f6fa;color:#1b2d46;font-family:Tahoma,sans-serif"><main style="max-width:560px;margin:12vh auto;padding:28px;background:white;border:1px solid #dde3ea;border-radius:14px"><p style="color:#267a6c">PNP e-Service</p><h1 style="font-size:25px">Lesson Plan พักบริการชั่วคราว</h1><p style="line-height:1.8">อยู่ระหว่างปรับปรุงการเข้าสู่ระบบและสิทธิ์ผู้ใช้งาน<br>ข้อมูลและไฟล์เดิมยังคงเก็บไว้</p><p><a href="<?= $link ?>" style="color:#176a5b">กลับสู่ Portal</a></p></main></body></html>
