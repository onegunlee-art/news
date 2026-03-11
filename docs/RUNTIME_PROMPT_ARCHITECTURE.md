# 런타임 프롬프트 아키텍처

이 문서는 기사 분석 시 실제로 GPT에 전달되는 프롬프트의 구성을 설명합니다.

단, **Admin 뉴스 작성 화면의 GPT 분석**은 현재 별도 모드로 동작합니다.

- 기본 system prompt만 사용
- Persona 비활성
- RAG 비활성
- `gpt-5.4`
- 1차 구조화 분석 → 2차 narration 생성

## 프롬프트 흐름

```
┌─────────────────────────────────────────────────────────────────┐
│                        System Prompt                             │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. 기본 시스템 프롬프트 (analysis.yaml 또는 PersonaService) ││
│  │    - 일반 경로: PersonaService 가능                         ││
│  │    - Admin 뉴스 작성 GPT 분석: 기본 system만 사용           ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 2. RAG 컨텍스트 (Supabase 설정 시)                          ││
│  │    - 일반 경로에서만 기본 프롬프트 뒤에 덧붙임              ││
│  │    - Admin 뉴스 작성 GPT 분석에서는 사용하지 않음           ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        User Prompt                               │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. 참조 이미지 가이드 (이미지 있을 경우)                     ││
│  │    - 제목/소제목/무시할 부분/요약 룰/가독성 형식             ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 2. 도메인별 분석 프롬프트                                    ││
│  │    - 일반 경로: buildFTPrompt / buildEconomistPrompt /      ││
│  │      buildDefaultPrompt                                     ││
│  │    - Admin 경로: 1차 구조화 분석 후 2차 narration 생성      ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     Reference Images (Vision)                    │
│  1. subtitle_foreign_affairs.png - 제목/소제목 위치 참조         │
│  2. subheading_reference.jpg - 소제목 식별                       │
│  3. pull_quote_ignore.jpg - 무시할 pull quote                    │
│  4. summary_rules.jpg - 요약 작성 규칙                           │
│  5. readability_format.png - content_summary 가독성 형식         │
└─────────────────────────────────────────────────────────────────┘
```

## 주요 파일 위치

| 역할 | 파일 |
|------|------|
| 기본 System Prompt | `src/agents/config/prompts/analysis.yaml` |
| Persona 기본 Prompt | `src/agents/services/PersonaService.php` |
| 도메인별 User Prompt | `src/agents/agents/AnalysisAgent.php` |
| RAG 컨텍스트 빌더 | `src/agents/services/RAGService.php` |
| 참조 이미지 | `src/agents/assets/reference/` |
| 결과 모델 | `src/agents/models/AnalysisResult.php` |

## 현재 기본 System Prompt

```
당신은 "The Gist"의 수석 에디터입니다.
모든 기사는 해외 뉴스를 한국어로 이해하고 싶어하는 독자를 위한 콘텐츠입니다.
반드시 요청된 JSON 형식으로만 응답하세요. 다른 텍스트는 포함하지 마세요.

원칙:
1. 객관적 사실과 주관적 해석을 명확히 구분
2. 핵심 논점을 4개 이상으로 정리
3. 독자 관점에서 시사점 도출
4. 내레이션은 독자에게 전달하듯 작성, 충분히 상세하게
5. 모든 출력은 자연스러운 한국어로 작성
```

## JSON 응답 스키마

```json
{
  "news_title": "영문 제목 직역 한국어",
  "author": "저자",
  "original_title": "영문 원제",
  "sections": [
    {
      "original_heading": "원문 소제목 (ALL CAPS)",
      "translated_heading": "한글 번역",
      "summary": "섹션 요약 2~4문장"
    }
  ],
  "content_summary": "구조화된 요약 (600자+)",
  "key_points": ["핵심 포인트 4개+"],
  "narration": "메인 본문 (1000자+)"
}
```

## Admin 뉴스 작성 GPT 분석 경로

1. `public/api/admin/ai-analyze.php`에서 Persona/RAG 없이 별도 설정을 주입합니다.
2. 모델은 `gpt-5.4`를 사용합니다.
3. 1차 단계:
   - `news_title`
   - `author`
   - `original_title`
   - `sections`
   - `content_summary`
   - `key_points`
   - `critical_analysis.why_important`
4. 2차 단계:
   - 기사 원문 + 1차 분석 결과를 함께 넣고 narration만 생성합니다.
5. Economist는 문단(paragraph) 단위로 먼저 읽고 요약합니다.
6. `content_summary`, `why_important`, `narration` 모두 paragraph별 한 줄 띄기를 강제합니다.

## 소제목 처리 규칙

1. **식별**: ALL CAPS 또는 큰 글씨의 짧은 문구
2. **저장**: `sections` 배열에 구조화하여 저장
3. **표시**: `content_summary`에 "1. 한글 (영문)" 형식으로 포함
4. **narration**: 섹션 전환 시 명확한 문장으로 구분하고 paragraph별 한 줄 띄기

## 무시할 패턴

- Pull quote (강조된 인용문)
- UI 요소 (Save, Share, Listen 등)
- 이미지 캡션/크레딧
- 날짜/읽기시간 단독 라인
- 구독/뉴스레터 유도 문구

## 후처리

| 단계 | 처리 내용 |
|------|-----------|
| 내레이션 정규화 | 인사말 제거 (여러분, 시청자 등) |
| 문단 간격 정규화 | 3줄 이상 공백을 1줄 공백으로 압축 |
| translation_summary | narration 앞 200자로 자동 생성 |
| Admin 저장 | content_summary → content, narration → narration |

## 금지 표현

- `지스터` - 독자 호칭으로 사용 금지
- 인사말 시작 금지 (바로 본문으로)
