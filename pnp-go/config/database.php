<?php

// อ่านค่าลับจากไฟล์ .env ที่ราก pnp-portal/ (ใช้ตัวช่วยกลางของ api/)
require_once __DIR__ . '/../../api/env.php';

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || (php_sapi_name() === 'cli');

$config = $isLocal ? [
    // === Local XAMPP ===
    'host'     => env('PNPGO_DB_HOST', '127.0.0.1'),
    'port'     => (int) env('PNPGO_DB_PORT', 3306),
    'database' => env('PNPGO_DB_NAME', 'carrequest'),
    'username' => env('PNPGO_DB_USER', 'root'),
    'password' => env('PNPGO_DB_PASS', ''),
    'charset'  => 'utf8mb4',
] : [
    // === Hostinger Production (pnp-go.montien.tech) ===
    // รหัสผ่านอ่านจาก .env (PNPGO_DB_PASS) หรือไฟล์ database.local.php ด้านล่าง
    'host'     => env('PNPGO_DB_HOST', 'localhost'),
    'port'     => (int) env('PNPGO_DB_PORT', 3306),
    'database' => env('PNPGO_DB_NAME', 'u651170081_pnpgo'),
    'username' => env('PNPGO_DB_USER', 'u651170081_pnpgo'),
    'password' => env('PNPGO_DB_PASS', ''),
    'charset'  => 'utf8mb4',
];

// โหลดค่าคอนฟิกส่วนตัว (เช่น รหัสผ่านฐานข้อมูลจริง) หากมีไฟล์ database.local.php
$localConfigPath = __DIR__ . '/database.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;
