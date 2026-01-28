<?php
/**
 * 분석 서비스 인터페이스
 * 
 * 뉴스 분석 서비스가 구현해야 하는 계약을 정의합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Interfaces;

/**
 * AnalysisInterface
 * 
 * 뉴스 분석에 필요한 메서드를 정의합니다.
 */
interface AnalysisInterface
{
    /**
     * 키워드 추출
     * 
     * @param string $text 분석할 텍스트
     * @param int $limit 추출할 키워드 수
     * @return array 키워드 목록 [['keyword' => string, 'score' => float], ...]
     */
    public function extractKeywords(string $text, int $limit = 10): array;

    /**
     * 감정 분석
     * 
     * @param string $text 분석할 텍스트
     * @return array 감정 분석 결과 ['sentiment' => string, 'score' => float, 'details' => array]
     */
    public function analyzeSentiment(string $text): array;

    /**
     * 텍스트 요약
     * 
     * @param string $text 요약할 텍스트
     * @param int $maxLength 최대 요약 길이
     * @return string 요약된 텍스트
     */
    public function summarize(string $text, int $maxLength = 200): string;

    /**
     * 전체 분석 (키워드 + 감정 + 요약)
     * 
     * @param string $text 분석할 텍스트
     * @return array 전체 분석 결과
     */
    public function analyze(string $text): array;
}
