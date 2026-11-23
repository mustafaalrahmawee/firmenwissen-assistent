<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo User and Workspace
    |--------------------------------------------------------------------------
    |
    | The shared account for the live demo and the workspace it belongs to,
    | created or reset by the command "app:create-demo-user". The password must
    | satisfy the password rule and be at most 72 bytes long, because bcrypt
    | ignores everything after that.
    |
    */

    'user' => [
        'name' => env('DEMO_USER_NAME', 'Demo'),
        'email' => env('DEMO_USER_EMAIL', 'demo@firmenwissen.test'),
        'password' => env('DEMO_USER_PASSWORD', 'Demo2026'),
    ],

    'tenant' => [
        'name' => env('DEMO_TENANT_NAME', 'Demo-Arbeitsbereich'),
    ],

];
