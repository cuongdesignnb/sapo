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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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
    |--------------------------------------------------------------------------
    | Attendance Bridge Agent
    |--------------------------------------------------------------------------
    |
    | Cấu hình cho C# AttendanceBridge app kết nối máy chấm công.
    | HMAC authentication với headers: X-Device-Id, X-Timestamp, X-Signature
    |
    */
    'attendance_agent' => [
        'hmac_key' => env('ATTENDANCE_AGENT_SECRET'),
        'timestamp_tolerance' => env('ATTENDANCE_AGENT_TIMESTAMP_TOLERANCE', 300), // seconds
    ],

];
