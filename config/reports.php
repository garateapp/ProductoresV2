<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Report preview recipients
    |--------------------------------------------------------------------------
    |
    | Comma separated list of emails that should receive preview copies of
    | quality control reports before they are approved.
    |
    */
    'preview_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('REPORT_PREVIEW_RECIPIENTS', ''))
    ))),

    'preview_phones' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('PREVIEW_PHONE_REPORT', ''))
    ))),
];

