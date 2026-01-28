<?php
/**
 * 카카오 API 설정 파일 (예제)
 * 
 * 이 파일을 kakao.php로 복사하고 실제 값을 입력하세요.
 * https://developers.kakao.com 에서 앱 등록 후 키 발급
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

return [
    'rest_api_key' => 'YOUR_KAKAO_REST_API_KEY',
    'javascript_key' => 'YOUR_KAKAO_JAVASCRIPT_KEY',
    'admin_key' => 'YOUR_KAKAO_ADMIN_KEY',
    
    'oauth' => [
        'authorize_url' => 'https://kauth.kakao.com/oauth/authorize',
        'token_url' => 'https://kauth.kakao.com/oauth/token',
        'logout_url' => 'https://kauth.kakao.com/oauth/logout',
        'redirect_uri' => 'http://ailand.dothome.co.kr/api/auth/kakao/callback',
        'scope' => [
            'profile_nickname',
            'profile_image',
            'account_email',
        ],
    ],
    
    'api' => [
        'base_url' => 'https://kapi.kakao.com',
        'user_info' => '/v2/user/me',
        'token_info' => '/v1/user/access_token_info',
        'logout' => '/v1/user/logout',
        'unlink' => '/v1/user/unlink',
    ],
    
    'property_keys' => [
        'kakao_account.profile',
        'kakao_account.email',
        'kakao_account.name',
    ],
];
