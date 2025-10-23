<?php

return [
    'whatsapp' => [
        'token' => env('WS_TOKEN'),
        'phone_id' => env('WS_PHONEID'),
        'api_version' => env('WS_API_VERSION', 'v18.0'),
        'templates' => [
            'process' => env('WS_TEMPLATE_PROCESS', 'proceso'),
            'reception' => env('WS_TEMPLATE_RECEPTION', 'recepcion'),
            'preview' => env('WS_TEMPLATE_PREVIEW', 'recepcion_preview'),
        ],
    ],

    'local_test' => [
        'phone' => env('PROCESS_NOTIF_TEST_PHONE'),
        'email' => env('PROCESS_NOTIF_TEST_EMAIL'),
    ],
];
