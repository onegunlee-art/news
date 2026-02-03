<?php
/**
 * 분석 서비스 클래스
 * 
 * 뉴스 텍스트 분석 기능을 담당합니다.
 * 
 * @author News Context Analysis Team
 * @version 1.0.0
 */

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AnalysisInterface;
use App\Models\Analysis;
use App\Repositories\AnalysisRepository;
use App\Repositories\NewsRepository;
use RuntimeException;

// Agent Pipeline
require_once dirname(__DIR__, 2) . '/agents/autoload.php';
use Agents\Pipeline\AgentPipeline;

/**
 * AnalysisService 클래스
 * 
 * 키워드 추출, 감정 분석, 텍스트 요약 기능을 제공합니다.
 */
final class AnalysisService implements AnalysisInterface
{
    private AnalysisRepository $analysisRepository;
    private NewsRepository $newsRepository;
    
    /**
     * 감정 분석용 키워드 사전
     */
    private const POSITIVE_WORDS = [
        '성공', '상승', '증가', '호조', '긍정', '발전', '성장', '개선', '회복', '향상',
        '좋은', '뛰어난', '우수', '최고', '훌륭', '칭찬', '기쁨', '행복', '희망', '기대',
        '환영', '축하', '감사', '사랑', '평화', '안정', '혁신', '달성', '돌파', '신기록',
        '호평', '인기', '히트', '베스트', '선도', '주목', '쾌거', '승리', '활약', '협력',
    ];
    
    private const NEGATIVE_WORDS = [
        '실패', '하락', '감소', '부진', '부정', '침체', '위기', '악화', '하락', '저하',
        '나쁜', '최악', '심각', '우려', '불안', '걱정', '공포', '분노', '비판', '논란',
        '반대', '거부', '사고', '재해', '피해', '손실', '적자', '파산', '폭락', '급락',
        '충격', '비난', '갈등', '분쟁', '대립', '마찰', '위반', '처벌', '기소', '구속',
    ];
    
    /**
     * 한국어 불용어 목록
     */
    private const STOPWORDS = [
        '이', '그', '저', '것', '수', '등', '들', '및', '에', '의', '가', '이', '은', '는',
        '를', '을', '에서', '으로', '로', '와', '과', '도', '만', '에게', '까지', '부터',
        '이다', '있다', '하다', '되다', '않다', '없다', '같다', '위해', '대해', '통해',
        '대한', '따라', '관련', '오늘', '내일', '어제', '올해', '지난', '다음', '이번',
        '기자', '뉴스', '보도', '발표', '설명', '밝혔', '전했', '말했', '했다', '된다',
    ];

    /**
     * 생성자
     */
    public function __construct()
    {
        $this->analysisRepository = new AnalysisRepository();
        $this->newsRepository = new NewsRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function extractKeywords(string $text, int $limit = 10): array
    {
        // 텍스트 전처리
        $text = $this->preprocessText($text);
        
        // 단어 추출 (한글, 영문, 숫자)
        preg_match_all('/[\p{Hangul}]+|[a-zA-Z]+/u', $text, $matches);
        $words = $matches[0];
        
        // 불용어 제거 및 최소 길이 필터링
        $words = array_filter($words, function ($word) {
            return mb_strlen($word) >= 2 && !in_array($word, self::STOPWORDS);
        });
        
        // 단어 빈도 계산
        $wordCounts = array_count_values($words);
        arsort($wordCounts);
        
        // TF 계산 및 점수 산정
        $totalWords = count($words);
        $keywords = [];
        
        foreach (array_slice($wordCounts, 0, $limit * 2) as $word => $count) {
            $tf = $count / max($totalWords, 1);
            $lengthBonus = min(mb_strlen($word) / 10, 0.3); // 긴 단어에 보너스
            $score = round(($tf + $lengthBonus) * 100, 2) / 100;
            
            $keywords[] = [
                'keyword' => $word,
                'count' => $count,
                'score' => min($score, 1.0),
            ];
        }
        
        // 점수 기준 정렬
        usort($keywords, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($keywords, 0, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function analyzeSentiment(string $text): array
    {
        $text = $this->preprocessText($text);
        
        // 긍정/부정 단어 카운트
        $positiveCount = 0;
        $negativeCount = 0;
        $matchedPositive = [];
        $matchedNegative = [];
        
        foreach (self::POSITIVE_WORDS as $word) {
            $count = mb_substr_count($text, $word);
            if ($count > 0) {
                $positiveCount += $count;
                $matchedPositive[] = ['word' => $word, 'count' => $count];
            }
        }
        
        foreach (self::NEGATIVE_WORDS as $word) {
            $count = mb_substr_count($text, $word);
            if ($count > 0) {
                $negativeCount += $count;
                $matchedNegative[] = ['word' => $word, 'count' => $count];
            }
        }
        
        // 감정 점수 계산 (-1.0 ~ 1.0)
        $total = $positiveCount + $negativeCount;
        
        if ($total === 0) {
            $sentiment = 'neutral';
            $score = 0.0;
        } else {
            $score = ($positiveCount - $negativeCount) / $total;
            
            if ($score > 0.1) {
                $sentiment = 'positive';
            } elseif ($score < -0.1) {
                $sentiment = 'negative';
            } else {
                $sentiment = 'neutral';
            }
        }
        
        return [
            'sentiment' => $sentiment,
            'score' => round($score, 4),
            'details' => [
                'positive_count' => $positiveCount,
                'negative_count' => $negativeCount,
                'positive_words' => array_slice($matchedPositive, 0, 5),
                'negative_words' => array_slice($matchedNegative, 0, 5),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(string $text, int $maxLength = 200): string
    {
        // 문장 분리
        $sentences = $this->splitSentences($text);
        
        if (empty($sentences)) {
            return '';
        }
        
        // 각 문장의 중요도 점수 계산
        $scoredSentences = [];
        $keywords = $this->extractKeywords($text, 20);
        $keywordList = array_column($keywords, 'keyword');
        
        foreach ($sentences as $index => $sentence) {
            $score = $this->calculateSentenceScore($sentence, $keywordList, $index, count($sentences));
            $scoredSentences[] = [
                'sentence' => $sentence,
                'score' => $score,
                'index' => $index,
            ];
        }
        
        // 점수 기준 정렬
        usort($scoredSentences, fn($a, $b) => $b['score'] <=> $a['score']);
        
        // 요약문 생성 (최대 길이까지)
        $summary = '';
        $selectedSentences = [];
        
        foreach ($scoredSentences as $item) {
            $newSummary = $summary . ($summary ? ' ' : '') . $item['sentence'];
            
            if (mb_strlen($newSummary) <= $maxLength) {
                $summary = $newSummary;
                $selectedSentences[] = $item;
            } else {
                break;
            }
        }
        
        // 원래 순서대로 정렬
        usort($selectedSentences, fn($a, $b) => $a['index'] <=> $b['index']);
        
        // 최종 요약문
        return implode(' ', array_column($selectedSentences, 'sentence'));
    }

    /**
     * {@inheritdoc}
     */
    public function analyze(string $text): array
    {
        $startTime = microtime(true);
        
        $keywords = $this->extractKeywords($text);
        $sentiment = $this->analyzeSentiment($text);
        $summary = $this->summarize($text);
        
        $processingTime = (int) ((microtime(true) - $startTime) * 1000);
        
        return [
            'keywords' => $keywords,
            'sentiment' => $sentiment['sentiment'],
            'sentiment_score' => $sentiment['score'],
            'sentiment_details' => $sentiment['details'],
            'summary' => $summary,
            'processing_time_ms' => $processingTime,
        ];
    }

    /**
     * 뉴스 분석 실행 및 저장
     */
    public function analyzeNews(int $newsId, ?int $userId = null): array
    {
        // 뉴스 조회
        $news = $this->newsRepository->findById($newsId);
        
        if (!$news) {
            throw new RuntimeException('뉴스를 찾을 수 없습니다.');
        }
        
        // 기존 분석 결과 확인
        $existing = $this->analysisRepository->findByNewsId($newsId);
        
        if ($existing) {
            return Analysis::fromArray($existing)->toJson();
        }
        
        // 분석 텍스트 준비
        $text = $news['title'];
        if (!empty($news['content'])) {
            $text .= "\n\n" . $news['content'];
        } elseif (!empty($news['description'])) {
            $text .= "\n\n" . $news['description'];
        }
        
        // 분석 실행
        $result = $this->analyze($text);
        
        // 분석 결과 저장
        $analysis = new Analysis();
        $analysis->setUserId($userId)
                 ->setNewsId($newsId)
                 ->setKeywords($result['keywords'])
                 ->setSentiment($result['sentiment'])
                 ->setSentimentScore($result['sentiment_score'])
                 ->setSentimentDetails($result['sentiment_details'])
                 ->setSummary($result['summary'])
                 ->setProcessingTimeMs($result['processing_time_ms'])
                 ->setStatus('completed')
                 ->setCompletedAt(new \DateTimeImmutable());
        
        $analysisId = $this->analysisRepository->saveAnalysis($analysis);
        $analysis->setId($analysisId);
        
        return $analysis->toJson();
    }

    /**
     * 텍스트 직접 분석
     */
    public function analyzeText(string $text, ?int $userId = null): array
    {
        if (empty(trim($text))) {
            throw new RuntimeException('분석할 텍스트가 없습니다.');
        }
        
        // 분석 실행
        $result = $this->analyze($text);
        
        // 분석 결과 저장
        $analysis = new Analysis();
        $analysis->setUserId($userId)
                 ->setInputText($text)
                 ->setKeywords($result['keywords'])
                 ->setSentiment($result['sentiment'])
                 ->setSentimentScore($result['sentiment_score'])
                 ->setSentimentDetails($result['sentiment_details'])
                 ->setSummary($result['summary'])
                 ->setProcessingTimeMs($result['processing_time_ms'])
                 ->setStatus('completed')
                 ->setCompletedAt(new \DateTimeImmutable());
        
        $analysisId = $this->analysisRepository->saveAnalysis($analysis);
        $analysis->setId($analysisId);
        
        return $analysis->toJson();
    }

    /**
     * 분석 결과 조회
     */
    public function getAnalysisById(int $id): ?array
    {
        $analysis = $this->analysisRepository->findById($id);
        
        if (!$analysis) {
            return null;
        }
        
        return Analysis::fromArray($analysis)->toJson();
    }

    /**
     * 사용자 분석 내역 조회
     */
    public function getUserAnalyses(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $items = $this->analysisRepository->findByUserId($userId, $perPage, $offset);
        $total = $this->analysisRepository->countByUserId($userId);
        
        return [
            'items' => array_map(fn($item) => Analysis::fromArray($item)->toJson(), $items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * 일일 분석 제한 확인
     */
    public function checkDailyLimit(int $userId): bool
    {
        $config = require dirname(__DIR__, 3) . '/config/app.php';
        $limit = 100; // 기본 제한
        
        $todayCount = $this->analysisRepository->countTodayByUserId($userId);
        
        return $todayCount < $limit;
    }

    /**
     * URL 기반 AI 분석 (Agent Pipeline 사용)
     * 
     * 4개 Agent (Validation, Analysis, Interpret, Learning) 파이프라인을 통한 심층 분석
     * 
     * @param string $url 분석할 기사 URL
     * @param int|null $userId 사용자 ID (선택)
     * @param array $options 분석 옵션
     * @return array 분석 결과
     */
    public function analyzeUrl(string $url, ?int $userId = null, array $options = []): array
    {
        if (empty(trim($url))) {
            throw new RuntimeException('분석할 URL을 입력해주세요.');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('유효하지 않은 URL 형식입니다.');
        }

        $startTime = microtime(true);

        // Agent Pipeline 생성 및 설정
        $pipelineConfig = [
            'openai' => $options['openai'] ?? [],
            'enable_interpret' => $options['enable_interpret'] ?? true,
            'enable_learning' => $options['enable_learning'] ?? true,
            'analysis' => [
                'enable_tts' => $options['enable_tts'] ?? false,
                'summary_length' => $options['summary_length'] ?? 3,
                'key_points_count' => $options['key_points_count'] ?? 3
            ],
            'stop_on_failure' => true
        ];

        $pipeline = new AgentPipeline($pipelineConfig);
        $pipeline->setupDefaultPipeline();

        // 파이프라인 실행
        $pipelineResult = $pipeline->run($url);

        $processingTime = (int) ((microtime(true) - $startTime) * 1000);

        // 결과 처리
        if (!$pipelineResult->isSuccess()) {
            // 명확화 필요한 경우
            if ($pipelineResult->needsClarification()) {
                return [
                    'success' => false,
                    'needs_clarification' => true,
                    'clarification_data' => $pipelineResult->clarificationData,
                    'processing_time_ms' => $processingTime
                ];
            }

            throw new RuntimeException($pipelineResult->getError() ?? 'URL 분석 중 오류가 발생했습니다.');
        }

        // 최종 분석 결과 추출
        $finalAnalysis = $pipelineResult->getFinalAnalysis();

        // 결과 포맷팅
        $result = [
            'success' => true,
            'url' => $url,
            'translation_summary' => $finalAnalysis['translation_summary'] ?? '',
            'key_points' => $finalAnalysis['key_points'] ?? [],
            'critical_analysis' => $finalAnalysis['critical_analysis'] ?? [
                'why_important' => '',
                'future_prediction' => ''
            ],
            'audio_url' => $finalAnalysis['audio_url'] ?? null,
            'metadata' => [
                'source_url' => $url,
                'processed_at' => date('c'),
                'agents_used' => array_keys($pipelineResult->results),
                'mock_mode' => $pipeline->isMockMode()
            ],
            'processing_time_ms' => $processingTime
        ];

        // DB에 분석 결과 저장 (선택적)
        if ($userId !== null) {
            $this->saveUrlAnalysis($url, $result, $userId);
        }

        return $result;
    }

    /**
     * URL 분석 결과 저장
     */
    private function saveUrlAnalysis(string $url, array $result, int $userId): void
    {
        try {
            $analysis = new Analysis();
            $analysis->setUserId($userId)
                     ->setInputText("URL: {$url}")
                     ->setKeywords([])
                     ->setSentiment('neutral')
                     ->setSentimentScore(0)
                     ->setSentimentDetails([
                         'ai_analysis' => true,
                         'translation_summary' => $result['translation_summary'],
                         'key_points' => $result['key_points'],
                         'critical_analysis' => $result['critical_analysis']
                     ])
                     ->setSummary($result['translation_summary'])
                     ->setProcessingTimeMs($result['processing_time_ms'])
                     ->setStatus('completed')
                     ->setCompletedAt(new \DateTimeImmutable());
            
            $this->analysisRepository->saveAnalysis($analysis);
        } catch (\Exception $e) {
            // 저장 실패해도 분석 결과는 반환
            error_log("Failed to save URL analysis: " . $e->getMessage());
        }
    }

    /**
     * Agent Pipeline 상태 확인
     */
    public function getPipelineStatus(): array
    {
        $pipeline = new AgentPipeline(['openai' => []]);
        $pipeline->setupDefaultPipeline();

        return [
            'agents' => $pipeline->getAgentNames(),
            'mock_mode' => $pipeline->isMockMode(),
            'ready' => true
        ];
    }

    /**
     * 텍스트 전처리
     */
    private function preprocessText(string $text): string
    {
        // HTML 태그 제거
        $text = strip_tags($text);
        
        // HTML 엔티티 디코딩
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // 여러 공백을 하나로
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 특수문자 제거 (한글, 영문, 숫자, 공백 유지)
        $text = preg_replace('/[^\p{Hangul}a-zA-Z0-9\s]/u', ' ', $text);
        
        return trim($text);
    }

    /**
     * 문장 분리
     */
    private function splitSentences(string $text): array
    {
        // 한국어 문장 종결 패턴
        $text = preg_replace('/([.!?。]+)\s*/u', "$1\n", $text);
        
        $sentences = explode("\n", $text);
        $sentences = array_map('trim', $sentences);
        $sentences = array_filter($sentences, fn($s) => mb_strlen($s) > 10);
        
        return array_values($sentences);
    }

    /**
     * 문장 중요도 점수 계산
     */
    private function calculateSentenceScore(string $sentence, array $keywords, int $index, int $totalSentences): float
    {
        $score = 0.0;
        
        // 1. 키워드 포함 점수
        foreach ($keywords as $keyword) {
            if (mb_strpos($sentence, $keyword) !== false) {
                $score += 0.2;
            }
        }
        
        // 2. 위치 점수 (첫 문장과 마지막 문장에 보너스)
        if ($index === 0) {
            $score += 0.3;
        } elseif ($index === $totalSentences - 1) {
            $score += 0.1;
        } elseif ($index < 3) {
            $score += 0.2;
        }
        
        // 3. 길이 점수 (적당한 길이 선호)
        $length = mb_strlen($sentence);
        if ($length >= 30 && $length <= 100) {
            $score += 0.2;
        } elseif ($length >= 20 && $length <= 150) {
            $score += 0.1;
        }
        
        // 4. 숫자 포함 점수 (통계 정보 가능성)
        if (preg_match('/\d+/', $sentence)) {
            $score += 0.1;
        }
        
        return min($score, 1.0);
    }
}
