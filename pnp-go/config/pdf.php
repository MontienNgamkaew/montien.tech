<?php

return [
    'output_dir' => dirname(__DIR__) . '/storage/pdfs',
    'temp_dir' => dirname(__DIR__) . '/storage/mpdf-temp',
    'font_dir' => dirname(__DIR__) . '/storage/fonts',
    'font' => 'thsarabunnew',
    'font_data' => [
        'thsarabunnew' => [
            'R' => 'THSarabunNew.ttf',
            'B' => 'THSarabunNew-Bold.ttf',
            'useOTL' => 0,
            'useKashida' => 0,
        ],
    ],
];
