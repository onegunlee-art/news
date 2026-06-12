<?php
/**
 * GIST EDU Agent A2: Stance Scorer
 * 학생 입장을 분석하고 반대 논거 클러스터 선택
 */
declare(strict_types=1);

namespace Services\Edu\Agents;

class StanceScorer
{
    private $llm;

    public function __construct($llmClient)
    {
        $this->llm = $llmClient;
    }

    public function scoreStance(string $stance, string $reason, array $quest): array
    {
        $stanceLabel = $stance === 'pro' ? '찬성' : '반대';
        $conflicts = $quest['conflict_summary'] ?? '';
        
        $systemPrompt = <<<PROMPT
학생의 입장과 이유를 분석해서 가장 효과적인 반론 방향을 선택해.

퀘스트 충돌점: {$conflicts}
학생 입장: {$stanceLabel}
학생 이유: {$reason}

JSON으로 응답:
{
  "stance_strength": 1-5 (학생 논거의 강도),
  "weak_points": ["약점1", "약점2"],
  "counter_cluster": "가장 효과적인 반론 축",
  "recommended_hammer_intensity": "soft"|"medium"|"hard",
  "explanation": "왜 이 반론이 효과적인지 한 줄"
}
PROMPT;

        $response = $this->llm->haiku($systemPrompt, [
            ['role' => 'user', 'content' => "분석해줘."]
        ], 300);

        if (!empty($response['error'])) {
            return [
                'stance_strength' => 3,
                'weak_points' => [],
                'counter_cluster' => $conflicts,
                'recommended_hammer_intensity' => 'medium',
            ];
        }

        $content = $response['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            return json_decode($match[0], true) ?? [
                'stance_strength' => 3,
                'weak_points' => [],
                'counter_cluster' => $conflicts,
                'recommended_hammer_intensity' => 'medium',
            ];
        }

        return [
            'stance_strength' => 3,
            'weak_points' => [],
            'counter_cluster' => $conflicts,
            'recommended_hammer_intensity' => 'medium',
        ];
    }

    public function selectCounterEvidence(string $stance, array $quest, array $articles): array
    {
        $counterRole = $stance === 'pro' ? 'counter' : 'primary';
        $relevant = array_filter($articles, fn($a) => ($a['role'] ?? '') === $counterRole);
        
        if (empty($relevant)) {
            $relevant = $articles;
        }
        
        return array_values($relevant);
    }
}
