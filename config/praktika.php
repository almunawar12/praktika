<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Seeding
    |--------------------------------------------------------------------------
    |
    | Credentials for the Super Admin account created by UserSeeder. The password
    | is required in production; locally it falls back to "password".
    |
    */

    'seed' => [
        'super_admin_email' => env('PRAKTIKA_SUPER_ADMIN_EMAIL', 'superadmin@praktika.test'),
        'super_admin_password' => env('PRAKTIKA_SUPER_ADMIN_PASSWORD'),
    ],

];
