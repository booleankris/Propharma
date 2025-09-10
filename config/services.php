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

    'midtrans' => [
        'merchantId'            => env('MIDTRANS_MERCHANT_ID', 'G109813357'),
        'clientKey'             => env('MIDTRANS_CLIENT_KEY', 'Mid-client-Y1ondIEQ_tKSTGny'),
        'serverKey'             => env('MIDTRANS_SERVER_KEY', 'Mid-server-BlaIi6DwKBJiqtkJbPkNv5di'),
        'isProduction'          => env('MIDTRANS_IS_PRODUCTION', true),
        'isSanitized'           => env('MIDTRANS_IS_SANITIZED', true),
        'is3ds'                 => env('MIDTRANS_IS_3DS', true),
        'endpoint'              => env('MIDTRANS_ENDPOINT', "https://app.midtrans.com/snap/v1/transactions"), 
        'landingTransactionUrl' => env('MIDTRANS_LANDING_TRANSACTION_URL', "https://ebiffpos/myevents.id/api/midtrans-feedback"), 
        'feedbackUrl'           => env('MIDTRANS_FEEDBACK_URL', "https://ebiffpos/myevents.id/api/midtrans-feedback"),
        'feedbackTicketUrl'     => env('MIDTRANS_FEEDBACK_URL', "https://ebiffpos/myevents.id/api/midtrans-ticket-feedback"),

    ],


];
