<?php
/**
 * 카카오 API 설정 파일
 * 
 * Kakao Developers (https://developers.kakao.com)에서 앱 등록 후
 * REST API 키를 발급받아 설정하세요.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 * @see https://developers.kakao.com/docs/latest/ko/kakaologin/rest-api
 */

/*
|==========================================================================
| 카카오 REST API 키 설정
|==========================================================================
|
| 1. https://developers.kakao.com 에서 앱 등록
| 2. 앱 키 → REST API 키 복사
| 3. 아래 변수에 붙여넣기
| 4. 카카오 개발자 콘솔에서 리다이렉트 URI 등록 필수:
|    https://www.thegist.co.kr/api/auth/kakao/callback
|
*/
$KAKAO_REST_API_KEY = '2b4a37bb18a276469b69bf3d8627e425';

return [
    /*
    |--------------------------------------------------------------------------
    | 카카오 앱 키
    |--------------------------------------------------------------------------
    |
    | Kakao Developers 콘솔에서 발급받은 키를 입력하세요.
    | - REST API 키: 서버 사이드 인증에 사용
    | - JavaScript 키: 클라이언트 사이드에서 사용 (선택)
    |
    */
    
    'rest_api_key' => $KAKAO_REST_API_KEY,
    'javascript_key' => getenv('KAKAO_JAVASCRIPT_KEY') ?: '',
    'admin_key' => getenv('KAKAO_ADMIN_KEY') ?: '',
    
    /*
    |--------------------------------------------------------------------------
    | OAuth 설정
    |--------------------------------------------------------------------------
    */
    
    'oauth' => [
        // 인가 코드 받기 URL
        'authorize_url' => 'https://kauth.kakao.com/oauth/authorize',
        
        // 토큰 발급 URL
        'token_url' => 'https://kauth.kakao.com/oauth/token',
        
        // 로그아웃 URL
        'logout_url' => 'https://kauth.kakao.com/oauth/logout',
        
        // 리다이렉트 URI (Kakao Developers에 등록 필요)
        // 중요: 카카오 개발자 콘솔에 정확히 동일하게 등록해야 합니다
        'redirect_uri' => getenv('KAKAO_REDIRECT_URI') ?: 'https://www.thegist.co.kr/api/auth/kakao/callback',
        
        // 요청 권한 범위 (scope)
        'scope' => [
            'profile_nickname',
            'profile_image',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API 엔드포인트
    |--------------------------------------------------------------------------
    */
    
    'api' => [
        'base_url' => 'https://kapi.kakao.com',
        
        // 사용자 정보 조회
        'user_info' => '/v2/user/me',
        
        // 토큰 정보 조회
        'token_info' => '/v1/user/access_token_info',
        
        // 로그아웃
        'logout' => '/v1/user/logout',
        
        // 연결 해제
        'unlink' => '/v1/user/unlink',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 사용자 정보 요청 시 가져올 프로퍼티
    |--------------------------------------------------------------------------
    */
    
    'property_keys' => [
        'kakao_account.profile',
        'kakao_account.name',
    ],
];
