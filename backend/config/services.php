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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google service account JSON path for Firebase Admin (custom tokens + Firestore).
    | Example: storage_path('app/firebase-credentials.json') or base_path('firebase-credentials.json')
    */
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'web_api_key' => env('FIREBASE_WEB_API_KEY'),
        // Web SDK (Filament chat) — match Firebase Console Web app / flutter web block
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'shwe-eain-jewelry-hub.firebasestorage.app'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', '885710177863'),
        'web_app_id' => env('FIREBASE_WEB_APP_ID', '1:885710177863:web:ef5eb17a2127003d649362'),
    ],

    'media' => [
        // local => Laravel public disk
        // firebase => filesystems.firebase (GCS/Firebase Storage)
        'provider' => env('MEDIA_STORAGE_PROVIDER', 'local'),
    ],

];
