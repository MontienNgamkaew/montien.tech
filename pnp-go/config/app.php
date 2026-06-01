<?php

// ตรวจสอบ environment: local XAMPP หรือ production
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);

return [
    'name'      => 'ระบบขออนุญาตใช้รถยนต์/สั่งซื้อน้ำมันเชื้อเพลิง',
    'base_path' => $isLocal ? '/pnp-portal/pnp-go' : '',   // subdomain ใช้ ''
    'timezone'  => 'Asia/Bangkok',
    'debug'     => $isLocal,                         // ปิด debug บน production
];
