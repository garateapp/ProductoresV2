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

    /*
    |--------------------------------------------------------------------------
    | Reception delay summary
    |--------------------------------------------------------------------------
    |
    | Daily summary of recepcions whose report was sent after a delay.
    |
    */
    'reception_delay_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('RECEPTION_DELAY_REPORT_RECIPIENTS', ''))
    ))),

    'reception_delay_time' => env('RECEPTION_DELAY_REPORT_TIME', '08:00'),
    'reception_delay_cron' => env('RECEPTION_DELAY_REPORT_CRON'),
    'reception_delay_threshold_hours' => (int) env('RECEPTION_DELAY_REPORT_THRESHOLD_HOURS', 12),
    'reception_delay_lookback_hours' => (int) env('RECEPTION_DELAY_REPORT_LOOKBACK_HOURS', 24),
];

