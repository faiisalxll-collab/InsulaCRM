<?php

return [
    'weights' => [
        'location' => 30,
        'price' => 20,
        'area' => 15,
        'rooms' => 10,
        'street_width' => 10,
        'facing' => 5,
        'age' => 5,
        'finance' => 5,
    ],

    'active_property_statuses' => ['active'],
    'active_request_statuses' => ['active'],

    // Keep synchronous for V1. The service boundary is intentionally queue-ready.
    'candidate_limit' => 500,
];
