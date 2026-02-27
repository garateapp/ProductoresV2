<?php

return [
    'chief_plant_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CUADRATURA_CHIEF_EMAILS', ''))
    ))),

    'manager_email' => env('CUADRATURA_MANAGER_EMAIL'),

    'approval_link_ttl_hours' => (int) env('CUADRATURA_APPROVAL_LINK_TTL_HOURS', 72),

    'sqlsrv' => [
        'views' => [
            'entradas' => env('CUADRATURA_SQLSRV_ENTRADAS_VIEW', 'V_PKG_Produccion_Entradas_XXX'),
            'completo' => env('CUADRATURA_SQLSRV_COMPLETO_VIEW', 'V_PKG_Produccion_Completo_XXX'),
        ],
    ],
];

