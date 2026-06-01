<?php
/* -------------------------------------------------------------
 * DOWNLOAD CSV TEMPLATE FOR PORTAL USER IMPORT (MATCHES USER SCREENSHOT)
 * Generates a clean CSV template with UTF-8 BOM for Thai Excel
 * ------------------------------------------------------------- */

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="pnp_import_template.csv"');

// Output UTF-8 BOM to prevent Thai garbled text in Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Headers exactly matching the user's screenshot, including ตำแหน่งหลัก
fputcsv($output, [
    'รหัสประชาชน',
    'ชื่อ',
    'สกุล',
    'วัน/เดือน/ปีเกิด',
    'ชื่อเล่น',
    'เพศ',
    'ตำแหน่งหลัก'
]);

// Dummy data rows exactly matching the user's screenshot examples
fputcsv($output, [
    '3400700386832',
    'นายบัญชา',
    'โคตรแก้ว',
    '1-ก.ย.-19',
    'ผอ.',
    'ชาย',
    'ผู้อำนวยการ'
]);

fputcsv($output, [
    '1350100238268',
    'นายมณเฑียร',
    'งามแก้ว',
    '19-มี.ค.-35',
    'รองฯ มณ',
    'ชาย',
    'รองผู้อำนวยการ'
]);

fputcsv($output, [
    '3469900199996',
    'นางสาวพิมพ์สุชาฎ์',
    'รัตนบรรพต',
    '25-ส.ค.-10',
    'รองฯ พิมพ์',
    'หญิง',
    'รองผู้อำนวยการ'
]);

fputcsv($output, [
    '3349900322845',
    'นางสาวศิริธร',
    'บรรเทา',
    '2-เม.ย.-17',
    'ปู',
    'หญิง',
    'ข้าราชการครู'
]);

fclose($output);
exit;
