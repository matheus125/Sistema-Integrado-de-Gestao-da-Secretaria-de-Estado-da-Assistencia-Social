<?php

return [
    'max_per_page' => max(1, (int) env('PAGINATION_MAX_PER_PAGE', 100)),
];
