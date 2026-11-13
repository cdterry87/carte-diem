<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | For the public portfolio site. While it is on, sign-up is closed and the
    | login page offers a one-click login as the demo user, once the demo
    | email and password are set. `demo:reset` creates the demo account and
    | its sample household, and runs daily while demo mode is on.
    | `user:create` adds real accounts from the terminal.
    |
    */

    'mode' => (bool) env('DEMO_MODE', false),

    'name' => env('DEMO_NAME', 'Demo User'),

    'household' => env('DEMO_HOUSEHOLD', 'Demo Household'),

    'email' => env('DEMO_EMAIL'),

    'password' => env('DEMO_PASSWORD'),

    'timezone' => env('DEMO_TIMEZONE', 'UTC'),

];
