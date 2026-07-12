<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'timeout' => (int) env('MIDTRANS_TIMEOUT', 30),
    'snap_expiry_hours' => (int) env('MIDTRANS_SNAP_EXPIRY_HOURS', 24),
];
