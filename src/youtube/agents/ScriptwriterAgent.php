<?php
declare(strict_types=1);

namespace Youtube\Agents;

use Discovery\Agents\LLMClient;
use Youtube\Contracts\Project;
use Youtube\Contracts\Scene;

/**
 * Generates 6-scene script from Discovery briefing using LLM.
 * Strictly uses only facts from the briefing (no hallucination).
 */
final class ScriptwriterAgent
{
    private const SCENE_COUNT = 6;

    public function __construct(
        private readonly LLMClient $llm,
        private readonly array $config,
    ) {
    }

    /**
     * Generate 6 scenes from a Discovery change.
     * @return list<Scene>
     */
    public function generate(Project $project): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($project);

        $response = $this->llm->complete($systemPrompt, $userPrompt, [
            'max_output_tokens' => (int) ($this->config['llm']['max_tokens'] ?? 4000),
        ]);

        $scenesData = LLMClient::parseJsonFromText($response->text);
        
        if (!is_array($scenesData) || count($scenesData) !== self::SCENE_COUNT) {
            throw new \RuntimeException('ScriptwriterAgent: Expected 6 scenes, got ' . count($scenesData ?? []));
        }

        $scenes = [];
        foreach ($scenesData as $data) {
            $scenes[] = Scene::fromArray($data);
        }

        return $scenes;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
당신은 뉴스 브리핑을 유튜브 쇼츠 대본으로 변환하는 전문가입니다.

## 규칙
1. **데이터 원칙**: 제공된 briefing에 있는 사실만 사용하세요. 새로운 숫자, 이름, 날짜를 절대 지어내지 마세요.
2. **6장면 구조**: 정확히 6개의 장면을 생성하세요.
3. **한국어**: 모든 narration은 한국어로 작성하세요.
4. **시간**: 전체 약 60초 분량입니다.

## 장면 구조
1. **오프닝** (3초): narration 없음, 고정 브랜드 화면
2. **핵심 뉴스** (9초): 헤드라인 + 지도 (what_changed 기반)
3. **왜 중요한가** (15초): 3가지 핵심 포인트 (why_important 기반)
4. **앞으로 전망** (13초): 3가지 전망 (future_impact 기반)
5. **핵심 수치** (10초): 중요한 숫자/통계 (highlights에서 추출, 없으면 핵심 사실)
6. **엔딩** (7초): narration 없음, 고정 브랜드 화면

## 출력 형식 (JSON 배열)
```json
[
  {
    "scene": 1,
    "visual_type": "fixed",
    "narration": "",
    "text_overlay": "THE WORLD CHANGED TODAY"
  },
  {
    "scene": 2,
    "visual_type": "map",
    "narration": "오늘 러시아가 키이우를 미사일로 공격했습니다.",
    "text_overlay": "러시아, 키이우 미사일 공격",
    "location": "Kyiv, Ukraine"
  },
  {
    "scene": 3,
    "visual_type": "text",
    "narration": "이번 공격이 중요한 이유는 세 가지입니다. ...",
    "text_overlay": ["지정학 긴장 고조", "민간인 피해 확대", "확전 우려 증가"]
  },
  {
    "scene": 4,
    "visual_type": "text",
    "narration": "앞으로의 전망은 다음과 같습니다. ...",
    "text_overlay": ["전망 1", "전망 2", "전망 3"]
  },
  {
    "scene": 5,
    "visual_type": "chart",
    "narration": "이번 공격으로 9명이 사망하고 28명이 부상했습니다.",
    "text_overlay": {"numbers": [{"value": "9", "label": "명 사망"}, {"value": "28", "label": "명 부상"}]}
  },
  {
    "scene": 6,
    "visual_type": "fixed",
    "narration": "",
    "text_overlay": "Essential truth. A clear view of the world."
  }
]
```

중요: briefing에 구체적 숫자가 없으면 chart 장면에서도 숫자를 만들지 마세요. 대신 핵심 사실을 텍스트로 표시하세요.
PROMPT;
    }

    private function buildUserPrompt(Project $project): string
    {
        $briefing = $project->briefing;
        $highlights = $project->getHighlights();
        $sources = array_map(fn($s) => $s['name'] ?? 'Unknown', $project->sources);

        return <<<PROMPT
## 뉴스 정보

**제목**: {$project->title}
**날짜**: {$project->editionDate}
**출처**: {$this->formatList($sources)}

## 브리핑 4단

**무슨 변화인가 (what_changed)**:
{$this->getBriefingField($briefing, 'what_changed')}

**왜 일어났나 (why_changed)**:
{$this->getBriefingField($briefing, 'why_changed')}

**왜 중요한가 (why_important)**:
{$this->getBriefingField($briefing, 'why_important')}

**앞으로 어떻게 될까 (future_impact)**:
{$this->getBriefingField($briefing, 'future_impact')}

## 핵심 사실 (highlights)
{$this->formatList($highlights)}

---

위 브리핑을 기반으로 6장면 쇼츠 대본을 JSON 배열로 생성하세요.
숫자나 사실은 반드시 위 브리핑에 있는 것만 사용하세요.
PROMPT;
    }

    private function getBriefingField(array $briefing, string $field): string
    {
        $value = $briefing[$field] ?? '';
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function formatList(array $items): string
    {
        if (empty($items)) {
            return '(없음)';
        }
        return implode("\n", array_map(fn($item, $i) => ($i + 1) . '. ' . $item, $items, array_keys($items)));
    }
}
