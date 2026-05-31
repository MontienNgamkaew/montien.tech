<?php
// Download CSV template for personnel import
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="personnel_template.csv"');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['ชื่อ-นามสกุล', 'ตำแหน่งหลัก']);

// Sample data
$samples = [
    ['นายสมชาย ใจดี', 'ผู้อำนวยการ'],
    ['นางสาวสมใจ รักเรียน', 'รองผู้อำนวยการ'],
    ['นายประเสริฐ มั่นคง', 'ข้าราชการครู'],
    ['นางวิไล สุขสันต์', 'พนักงานราชการครู'],
    ['นายวิชัย เก่งกาจ', 'ครูพิเศษสอน'],
    ['นางสาวพิมพ์ใจ ดีงาม', 'เจ้าหน้าที่'],
];

foreach ($samples as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
