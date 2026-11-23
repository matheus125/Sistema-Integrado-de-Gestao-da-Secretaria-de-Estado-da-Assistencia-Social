<?php

return [
    'exports' => [
        'disk' => env('REPORT_EXPORT_DISK', 'local'),
        'queue' => env('REPORT_EXPORT_QUEUE', 'exports'),
        'timeout' => (int) env('REPORT_EXPORT_TIMEOUT', 120),
        'expires_after_minutes' => (int) env('REPORT_EXPORT_EXPIRES_AFTER', 60),
        'max_rows' => [
            'csv' => (int) env('REPORT_EXPORT_CSV_MAX_ROWS', 10000),
            'xlsx' => (int) env('REPORT_EXPORT_XLSX_MAX_ROWS', 5000),
            'pdf' => (int) env('REPORT_EXPORT_PDF_MAX_ROWS', 500),
        ],
    ],
];
