<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // 소셜 로그인 (ADR-003). 키는 로그인 구현 직전에 발급한다 (ADR-003 §9).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/auth/google/callback',

        // 앱 로그인: ID 토큰의 aud로 허용할 클라이언트 ID (쉼표 구분)
        'id_token_audiences' => array_values(array_filter(explode(',', (string) env('GOOGLE_ID_TOKEN_AUDIENCES', '')))),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/auth/apple/callback',

        // 앱 로그인: ID 토큰의 aud로 허용할 값 = iOS 앱 번들 ID (쉼표 구분)
        'id_token_audiences' => array_values(array_filter(explode(',', (string) env('APPLE_ID_TOKEN_AUDIENCES', '')))),

        // 앱이 보낸 authorization_code를 refresh token으로 바꿀 때 쓰는 키 (ADR-003 §5)
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'), // .p8 파일 경로(backend 기준) 또는 PEM 내용
    ],

];
