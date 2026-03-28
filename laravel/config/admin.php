<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jednoduchý admin účet (bez tabuľky users)
    |--------------------------------------------------------------------------
    |
    | Prihlasovacie údaje z .env. V produkcii používaj silné heslo a chránený server.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

    /*
    | Platnosť admin tokenu v cache (hodiny).
    |
    */

    'token_ttl_hours' => (int) env('ADMIN_TOKEN_TTL_HOURS', 12),

];
