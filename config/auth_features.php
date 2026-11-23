<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fitur Autentikasi Starter
    |--------------------------------------------------------------------------
    |
    | Toggle fitur autentikasi bawaan starter template. Semua fitur bisa
    | dimatikan/dinyalakan tanpa mengubah kode controller.
    |
    */

    'registration' => [
        'enabled' => env('AUTH_REGISTRATION_ENABLED', true),
    ],

    'email_verification' => [
        'enabled' => env('AUTH_EMAIL_VERIFICATION_ENABLED', false),
        'expire'  => 60, // menit
    ],

    'password_reset' => [
        'enabled' => env('AUTH_PASSWORD_RESET_ENABLED', true),
    ],

];
