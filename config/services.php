<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'assemblyai' => [
        'key' => env('ASSEMBLYAI_API_KEY'),
        // AssemblyAI realtime WS host (v2 realtime)
        'stream_host' => env('ASSEMBLYAI_STREAM_HOST', 'streaming.assemblyai.com'),
    ],

    'termo' => [
        'api_key' => env('TERMO_API_KEY'),
        'sqlsrv_view' => env('TERMO_SQLSRV_SALIDAS_VIEW', 'V_PKG_Produccion_Salidas_XXX'),
        'auto_consumption' => [
            'enabled' => filter_var(env('AUTO_CONSUMPTION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'conexion' => env('AUTO_CONSUMPTION_CONNECTION', 'sqlsrv'),
            'batch_size' => (int) env('AUTO_CONSUMPTION_BATCH_SIZE', 200),
            'system_user_email' => env('AUTO_CONSUMPTION_SYSTEM_USER_EMAIL', 'sistema.auto@appgreenex.test'),
        ],
    ],

    'precooling' => [
        'api_key' => env('PRECOOLING_API_KEY', env('TERMO_API_KEY')),
    ],

    'sap_service_layer' => [
        'enabled' => filter_var(env('SAP_SL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('SAP_SL_BASE_URL', 'https://188.34.132.98:50005/b1s/v1'),
        'company_db' => env('SAP_SL_COMPANY_DB', 'SBOGREENEX1'),
        'username' => env('SAP_SL_USERNAME', 'GER01'),
        'password' => env('SAP_SL_PASSWORD', ''),
        'timeout' => env('SAP_SL_TIMEOUT', 60),
        'verify_ssl' => filter_var(env('SAP_SL_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
        'default_days_back' => env('SAP_SL_DEFAULT_DAYS_BACK', 30),
    ],

];
