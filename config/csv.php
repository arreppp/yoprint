<?php

return [
    'max_size' => env('CSV_MAX_SIZE', 10240), // in KB
    'validation_enabled' => env('CSV_VALIDATION_ENABLED', true),
    'required_columns' => env(
        'CSV_REQUIRED_COLUMNS',
        'UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,SANMAR_MAINFRAME_COLOR,SIZE,COLOR_NAME,PIECE_PRICE'
    ),
];
