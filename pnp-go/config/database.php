<?php

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);

$config = $isLocal ? [
    // === Local XAMPP ===
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'carrequest',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
] : [
    // === Hostinger Production (pnp-go.montien.tech) ===
    'host'     => 'localhost',
    'port'     => 3306,
    'database' => 'u651170081_carrequest',
    'username' => 'u651170081_carrequest',
    'password' => 'CHANGE_ON_SERVER',   // ← แก้ผ่าน File Manager บน Hostinger
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
