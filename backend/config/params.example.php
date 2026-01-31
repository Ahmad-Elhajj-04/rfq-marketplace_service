<?php

return [
    'adminEmail' => 'admin@example.com',

    // JWT secret (replace locally)
    'jwtSecret' => 'CHANGE_ME',

    // Centrifugo publish API (replace locally)
    'centrifugo' => [
        'api_url' => 'http://localhost:8000/api/publish',
        'api_key' => 'CHANGE_ME',
    ],
];