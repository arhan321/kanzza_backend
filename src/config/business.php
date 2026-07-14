<?php

return [
    'store' => [
        'latitude' => (float) env('BUSINESS_STORE_LATITUDE', -6.282288),
        'longitude' => (float) env('BUSINESS_STORE_LONGITUDE', 106.554848),
    ],
    'shipping' => [
        'rate_per_km' => (int) env('BUSINESS_SHIPPING_RATE_PER_KM', 5000),
        'max_distance_km' => (float) env('BUSINESS_SHIPPING_MAX_DISTANCE_KM', 100),
        'distance_tolerance_km' => (float) env('BUSINESS_SHIPPING_DISTANCE_TOLERANCE_KM', 0.1),
    ],
];
