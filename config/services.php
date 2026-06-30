<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'internal_api' => [
        'key' => env('INTERNAL_API_KEY'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'advancedmd' => [
        'office_code' => env('AMD_OFFICE_CODE'),
        'username' => env('AMD_USERNAME'),
        'password' => env('AMD_PASSWORD'),
        'app_name' => env('AMD_APP_NAME'),
        'login_url' => env('AMD_LOGIN_URL'),
    ],

    'advancedmd_ehr' => [
        'office_code' => env('AMD_EHR_OFFICE_CODE', env('AMD_OFFICE_CODE')),
        'username' => env('AMD_EHR_USERNAME', env('AMD_USERNAME')),
        'password' => env('AMD_EHR_PASSWORD', env('AMD_PASSWORD')),
        'app_name' => env('AMD_EHR_APP_NAME', env('AMD_APP_NAME', 'TEMP')),
        'login_url' => env('AMD_EHR_LOGIN_URL', env('AMD_LOGIN_URL')),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_PHONE'),
    ],

    'app_server' => [
        'base_url'    => env('APP_SERVER_BASE_URL', 'http://10.0.0.23'),
        'api_url'     => env('APP_SERVER_BASE_URL', 'http://10.0.0.23') . '/api',
        'storage_url' => env('APP_SERVER_BASE_URL', 'http://10.0.0.23') . '/storage/files/mh',
        'webdav_url'  => env('APP_SERVER_BASE_URL', 'http://10.0.0.23') . '/webdav/mh',
    ],

    'clinical_notes' => [
        'base_url' => env('CLINICAL_NOTES_API_BASE_URL', env('APP_SERVER_BASE_URL', 'http://10.0.0.23') . '/api'),
    ],

    'clinical_documents' => [
        'base_url' => env('CLINICAL_DOCUMENTS_API_BASE_URL', env('APP_SERVER_BASE_URL', 'http://10.0.0.23') . '/api'),
    ],

];
