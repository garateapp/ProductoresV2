<?php

return [
    'waste' => [
        'review_threshold_quantity' => 50,
        'photo_threshold_quantity' => 100,
    ],
    'last_lot' => (int) env('INVENTORY_LAST_LOT', 0),
];
