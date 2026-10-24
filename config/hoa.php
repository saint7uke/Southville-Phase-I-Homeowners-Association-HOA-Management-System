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
    'release' => [
        // These are explicit staging/operations attestations, never inferred from configuration.
        'queue_worker_verified' => (bool) env('HOA_QUEUE_WORKER_VERIFIED', false),
        'scheduler_verified' => (bool) env('HOA_SCHEDULER_VERIFIED', false),
        'backup_restore_verified' => (bool) env('HOA_BACKUP_RESTORE_VERIFIED', false),
        'mail_delivery_verified' => (bool) env('HOA_MAIL_DELIVERY_VERIFIED', false),
        'private_storage_verified' => (bool) env('HOA_PRIVATE_STORAGE_VERIFIED', false),
        'security_incident_closed' => (bool) env('HOA_SECURITY_INCIDENT_CLOSED', false),
    ],
];
