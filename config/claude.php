<?php
/**
 * Claude (Anthropic) API 설정
 * 
 * 환경변수 ANTHROPIC_API_KEY를 사용합니다.
 */

return [
    'api_key' => $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?? '',
    
    // Claude Sonnet 4.6 (claude-sonnet-4-6)
    'model' => 'claude-sonnet-4-6',
    
    // 토큰 제한 (분석에 충분한 양)
    'max_tokens' => 8192,
    
    // temperature (일관성 있는 출력을 위해 낮게)
    'temperature' => 0.3,
    
    // 타임아웃 (긴 기사 분석용)
    'timeout' => 180,
    
    // Mock 모드 (API 키 없을 시 자동 활성화)
    'mock_mode' => false,
];
