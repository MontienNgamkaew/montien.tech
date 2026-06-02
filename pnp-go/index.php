<?php

require __DIR__ . '/bootstrap.php';

if (config('app')['debug'] ?? false) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // production: ไม่แสดง error บนหน้าจอ แต่บันทึกลงไฟล์เพื่อให้ตรวจสอบปัญหาได้
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/storage/php-error.log');
    error_reporting(E_ALL);
}

$router = new Router();

require __DIR__ . '/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
