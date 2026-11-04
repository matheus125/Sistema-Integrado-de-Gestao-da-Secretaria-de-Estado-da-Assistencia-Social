<?php

return [
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
    'tries' => (int) env('NOTIFICATION_QUEUE_TRIES', 3),
    'timeout' => (int) env('NOTIFICATION_QUEUE_TIMEOUT', 120),
    'backoff' => (int) env('NOTIFICATION_QUEUE_BACKOFF', 60),
];
