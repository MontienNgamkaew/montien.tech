<?php
/**
 * upload_serve.php — serve ไฟล์ upload จากนอก public_html
 *
 * ทำงานร่วมกับ .htaccess ใน pnp-academix/ และ pnpman/
 * ซึ่ง rewrite request uploads/ มาที่นี่แทนการเข้าถึงไฟล์โดยตรง
 * เพื่อให้ไฟล์ที่ผู้ใช้อัปโหลดอยู่นอก git deployment zone
 */
require_once __DIR__ . '/api/env.php';

$app  = $_GET['app'] ?? '';
$file = $_GET['file'] ?? '';

$allowedApps = ['pnp-academix', 'pnpman'];
if (!in_array($app, $allowedApps, true) || $file === '' || str_contains($file, '..') || str_contains($file, "\0")) {
    http_response_code(400);
    exit;
}

$fullPath = upload_base_path($app) . '/' . ltrim($file, '/');

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400');
readfile($fullPath);
