<?php
return [
    'app_id' => env('WHATSAPP_META_APP_ID'),
    'app_secret' => env('WHATSAPP_META_APP_SECRET'),
    'config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
    'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v24.0'),
];
