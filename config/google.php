<?php
/**
 * Google OAuth 설정 파일
 *
 * Google Cloud Console (https://console.cloud.google.com)에서
 * OAuth 2.0 클라이언트 ID를 생성한 뒤 client_id, client_secret을 설정하세요.
 * 승인된 리디렉션 URI: https://www.thegist.co.kr/api/auth/google/callback
 */

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'oauth' => [
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'https://www.thegist.co.kr/api/auth/google/callback',
        'scope' => implode(' ', [
            'openid',
            'email',
            'profile',
        ]),
    ],
    'userinfo_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
];
