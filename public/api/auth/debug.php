<?php
/**
 * 카카오 설정 디버그 페이지
 * 
 * REST API 키가 제대로 로드되는지 확인합니다.
 */

header('Content-Type: text/html; charset=utf-8');

$configPath = dirname(__DIR__, 3) . '/config/kakao.php';

echo "<h1>카카오 설정 디버그</h1>";
echo "<pre>";

echo "설정 파일 경로: " . $configPath . "\n";
echo "파일 존재 여부: " . (file_exists($configPath) ? 'YES' : 'NO') . "\n\n";

if (file_exists($configPath)) {
    $kakaoConfig = require $configPath;
    
    echo "=== 설정 파일 내용 ===\n";
    echo "REST API Key: " . ($kakaoConfig['rest_api_key'] ?? 'NOT SET') . "\n";
    echo "REST API Key 길이: " . strlen($kakaoConfig['rest_api_key'] ?? '') . "\n";
    echo "REST API Key 비어있음: " . (empty($kakaoConfig['rest_api_key']) ? 'YES' : 'NO') . "\n\n";
    
    echo "Redirect URI: " . ($kakaoConfig['oauth']['redirect_uri'] ?? 'NOT SET') . "\n\n";
    
    echo "=== 환경 변수 ===\n";
    echo "KAKAO_REST_API_KEY: " . (getenv('KAKAO_REST_API_KEY') ?: 'NOT SET') . "\n";
    echo "KAKAO_REDIRECT_URI: " . (getenv('KAKAO_REDIRECT_URI') ?: 'NOT SET') . "\n\n";
    
    echo "=== 전체 설정 배열 ===\n";
    print_r($kakaoConfig);
} else {
    echo "ERROR: 설정 파일을 찾을 수 없습니다!\n";
}

echo "</pre>";
