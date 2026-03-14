<?php
/**
 * Google OAuth 설정 예시
 * 이 파일을 google.php로 복사하거나, .env에 아래 변수를 설정하세요.
 *
 * GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
 * GOOGLE_CLIENT_SECRET=your-client-secret
 * GOOGLE_REDIRECT_URI=https://www.thegist.co.kr/api/auth/google/callback
 *
 * Google Cloud Console에서 OAuth 2.0 클라이언트 ID 생성 후
 * 승인된 리디렉션 URI에 위 GOOGLE_REDIRECT_URI를 등록해야 합니다.
 */

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'oauth' => [
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'https://www.thegist.co.kr/api/auth/google/callback',
        'scope' => implode(' ', ['openid', 'email', 'profile']),
    ],
    'userinfo_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
];
