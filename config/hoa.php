<?php

declare(strict_types=1);

return [
    'initial_admin' => [
        'first_name' => env('HOA_INITIAL_ADMIN_FIRST_NAME'),
        'middle_name' => env('HOA_INITIAL_ADMIN_MIDDLE_NAME'),
        'last_name' => env('HOA_INITIAL_ADMIN_LAST_NAME'),
        'suffix' => env('HOA_INITIAL_ADMIN_SUFFIX'),
        'sex' => env('HOA_INITIAL_ADMIN_SEX'),
        'contact_number' => env('HOA_INITIAL_ADMIN_CONTACT_NUMBER'),
        'date_of_birth' => env('HOA_INITIAL_ADMIN_DATE_OF_BIRTH'),
        'email' => env('HOA_INITIAL_ADMIN_EMAIL'),
        'password' => env('HOA_INITIAL_ADMIN_PASSWORD'),
        'password_confirmation' => env('HOA_INITIAL_ADMIN_PASSWORD_CONFIRMATION'),
    ],
];
