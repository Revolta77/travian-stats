<?php

return [

    'deployment' => [
        'token_migration' => env('TOKEN_MIGRATION'),
        'token_cron' => env('TOKEN_CRON'),
    ],

    'report_timezone' => env('TRAVIAN_REPORT_TIMEZONE', 'Europe/Bratislava'),

    // Slovak labels: API / fallback only. The Vue app maps ids to `travianTribes` in `vue/src/locales/*.ts`.
    'tribes' => [
        1 => 'Rimania',
        2 => 'Germáni',
        3 => 'Galovia',
        4 => 'Priroda',
        5 => 'Natari',
        6 => 'Egypťania',
        7 => 'Hunovia',
        8 => 'Spartania',
    ],

    'map' => [
        'coordinate_min' => -400,
        'coordinate_max' => 400,
    ],

];
