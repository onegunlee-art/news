<?php
/**
 * 카카오 로그인 직접 리다이렉트
 * 
 * 라우터를 거치지 않고 직접 카카오 로그인 URL로 리다이렉트합니다.
 */

header('Content-Type: text/html; charset=utf-8');

// 설정 파일 로드
$configPath = dirname(__DIR__, 2) . '/config/kakao.php';

if (!file_exists($configPath)) {
    die('설정 파일을 찾을 수 없습니다: ' . $configPath);
}

$config = require $configPath;

// REST API 키 확인
if (empty($config['rest_api_key'])) {
    die('REST API 키가 설정되지 않았습니다.');
}

// 카카오 로그인 URL 생성
$params = [
    'client_id' => $config['rest_api_key'],
    'redirect_uri' => $config['oauth']['redirect_uri'],
    'response_type' => 'code',
];

// Scope 추가
if (!empty($config['oauth']['scope'])) {
    $params['scope'] = implode(',', $config['oauth']['scope']);
}

$loginUrl = $config['oauth']['authorize_url'] . '?' . http_build_query($params);

// 직접 리다이렉트
header('Location: ' . $loginUrl);
exit;
