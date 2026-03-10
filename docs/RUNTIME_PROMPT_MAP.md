# 런타임 프롬프트 구성 맵

이 문서는 기사 분석/생성 시 실제로 작동하는 프롬프트 흐름을 정리합니다.

## 프롬프트 레이어 구조

```
┌─────────────────────────────────────────────────────────┐
│                   System Prompt                         │
│  (PersonaService → YAML fallback → RAG 추가)            │
├─────────────────────────────────────────────────────────┤
│                   User Prompt                           │
│  (AnalysisAgent.buildFullAnalysisPrompt)               │
│  + 참조 이미지 5장                                      │
├─────────────────────────────────────────────────────────┤
│                   GPT 호출                              │
│  model: gpt-5.2 / temperature: 0.7 / max_tokens: 8000  │
├─────────────────────────────────────────────────────────┤
│                   후처리                                │
│  normalizeNarration() / Admin 저장 시 추가 정규화       │
└─────────────────────────────────────────────────────────┘
```

## 1. System Prompt 결정 흐름

파일: `src/agents/agents/AnalysisAgent.php` (line 151)

```php
$basePrompt = $this->personaService 
    ? $this->personaService->getSystemPrompt() 
    : ($this->prompts['system'] ?? '당신은 "The Gist"의 수석 에디터입니다.');
```

### 1.1 PersonaService 기본 프롬프트
파일: `src/agents/services/PersonaService.php` (line 18)

```
당신은 "The Gist"의 수석 에디터입니다. 모든 기사는 지스터(The Gist 독자)를 위한 콘텐츠입니다. 
지스터는 해외 뉴스를 한국어로 이해하고 싶어하는 독자층이며, The Gist의 핵심 독자입니다. 
반드시 지스터 독자 관점에서 작성하고, 요청된 JSON 형식으로만 응답하세요.
```

### 1.2 YAML 기본 프롬프트
파일: `src/agents/config/prompts/analysis.yaml` (line 1-11)

```yaml
system: |
  당신은 "The Gist"의 수석 에디터입니다.
  모든 기사는 지스터(The Gist 독자)를 위한 콘텐츠입니다. 지스터는 해외 뉴스를 한국어로 이해하고 싶어하는 The Gist의 핵심 독자층입니다.
  반드시 요청된 JSON 형식으로만 응답하세요.

  원칙:
  1. 객관적 사실과 주관적 해석을 명확히 구분
  2. 핵심 논점을 4개 이상으로 정리
  3. 지스터 독자 관점에서 시사점 도출
  4. 내레이션은 지스터에게 전달하듯 작성, 충분히 상세하게
  5. 모든 출력은 자연스러운 한국어로 작성
```

### 1.3 RAG 컨텍스트 추가
파일: `src/agents/services/RAGService.php`

RAG가 활성화되면 system prompt 뒤에 다음 형식으로 추가됨:
```
--- RAG Context (편집 전문가 지식) ---
[과거 분석 사례, 피드백, 지식 등]
```

## 2. User Prompt (기사별)

파일: `src/agents/agents/AnalysisAgent.php`

### 2.1 기사 소스별 분기
- **Economist**: `buildEconomistPrompt()` (line 228~)
- **FT**: `buildFTPrompt()` (line 228~ 동일 로직)
- **기타 (Foreign Affairs 등)**: `buildDefaultPrompt()` (line 320~)

### 2.2 공통 규칙 (모든 프롬프트에 포함)

**소제목 식별 방법:**
```
- 본문 중간에 등장하는, 주변보다 큰 글씨 또는 전부 대문자로 된 짧은 문구
- 예: "ANTICIPATION AND ADAPTATION" (섹션 구분용 헤딩)
- content_summary에서 각 소제목을 한글(영문) 형식으로 나열하고, 그 아래 해당 섹션 요약을 작성
```

**무시할 잡음:**
```
- UI 요소: Save, Share, Listen to this story, Reuse this content
- 이미지 캡션/크레딧: Illustration:, Photo:, Getty Images
- 날짜/읽기시간: Mar 5th 2026, 3 min read
- 구독/뉴스레터 유도: Subscribe to..., Sign up for our newsletter
```

**무시할 pull quote:**
```
본문 중간에 등장하는 "pull quote" 스타일의 큰 볼드 텍스트는 요약/분석에서 완전히 무시하세요.
```

### 2.3 JSON 출력 스키마

```json
{
  "news_title": "영문 제목 직역 한국어",
  "author": "저자",
  "original_title": "영문 원제",
  "content_summary": "구조화 형식 (소제목 한글(영문) + 영한 교차 요약). 최소 600자.",
  "key_points": ["핵심 포인트 4개 이상"],
  "narration": "지스터를 위한 내레이션. 인사말 없이. 최소 1000자 이상."
}
```

## 3. 참조 이미지 (5장)

파일: `src/agents/agents/AnalysisAgent.php` (line 386~)
폴더: `src/agents/assets/reference/`

| 순서 | 파일명 | 용도 |
|------|--------|------|
| 1 | subtitle_foreign_affairs.png | 제목 위치 확인 |
| 2 | subheading_reference.jpg | 소제목 식별 |
| 3 | pull_quote_ignore.jpg | 무시할 pull quote |
| 4 | summary_rules.jpg | 요약 룰 |
| 5 | readability_format.png | content_summary 가독성 형식 |

## 4. 후처리

### 4.1 AnalysisAgent 내부
파일: `src/agents/agents/AnalysisAgent.php` (line 459~)

```php
// 인사말 제거
$out = preg_replace('/^(지스터\s+여러분|시청자\s+여러분|청취자\s+여러분)[,.\s]*/u', '', $out);
$out = preg_replace('/^(여러분)[,.\s]*/u', '', $out);
```

### 4.2 Admin 저장 시
파일: `public/api/admin/news.php` (line 163~)

```php
// narration 저장 전 인사말 제거
$narration = trim(preg_replace('/^(지스터\s+여러분|시청자\s+여러분|청취자\s+여러분)[,.\s]*/u', '', trim($narration)));
```

## 5. "지스터" 표현 위치 (제거 대상)

| 파일 | 위치 | 내용 |
|------|------|------|
| `analysis.yaml` | line 3, 9, 10 | system prompt에 지스터 언급 |
| `PersonaService.php` | line 18 | 기본 system prompt에 지스터 언급 |
| `AnalysisAgent.php` | line 312, 370 | user prompt narration 지시에 지스터 언급 |
| `news.php` | line 164 | 후처리에서 "지스터 여러분" 제거 로직 |

## 6. 실제 temperature/model 설정

### 기대값 (config/agents.php)
```php
'analysis' => [
    'model' => 'gpt-5.2',
    'temperature' => 0.45,
    'max_tokens' => 4000,
]
```

### 실제 런타임 (AnalysisAgent.php line 150)
```php
$options = ['model' => 'gpt-5.2', 'timeout' => 180, 'max_tokens' => 8000];
// temperature는 OpenAIService 기본값 0.7 사용
```

**결론**: config/agents.php의 temperature 0.45는 Admin 분석 경로에서 적용되지 않음.

## 7. Admin UI → 백엔드 옵션 전달

파일: `src/frontend/src/pages/AdminPage.tsx` (line 1658~)

```typescript
body: JSON.stringify({
  action: 'analyze',
  url: articleUrl.trim(),
  enable_tts: false,
  enable_interpret: false,
  enable_learning: false,
  use_persona: usePersonaExperiment,
}),
```

파일: `public/api/admin/ai-analyze.php`

```php
$options = [
    'enable_tts' => $input['enable_tts'] ?? false,
    'enable_interpret' => $input['enable_interpret'] ?? true,
    'enable_learning' => $input['enable_learning'] ?? true
    // use_persona는 별도 처리됨
];
```
