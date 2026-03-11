# 런타임 프롬프트 아키텍처

## 개요
분석 품질은 단일 프롬프트 파일이 아니라 여러 레이어의 합성 결과입니다.

단, **Admin 뉴스 작성의 GPT 분석** 경로는 현재 별도 운영합니다.

- Persona 개입 없음
- RAG 개입 없음
- 기본 system prompt만 사용
- `gpt-5.4` 사용
- 1차 구조화 분석 → 2차 narration 생성

```
┌─────────────────────────────────────────────────────────────┐
│                    Admin AI 분석 요청                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│   1. SYSTEM PROMPT 결정                                     │
│   ┌──────────────────────────────────────────────────────┐  │
│   │ PersonaService.getSystemPrompt()                      │  │
│   │  - 일반 경로에서만 사용                                │  │
│   │  - Admin 뉴스 작성 GPT 분석은 사용하지 않음            │  │
│   └──────────────────────────────────────────────────────┘  │
│                              │                               │
│                              ▼                               │
│   ┌──────────────────────────────────────────────────────┐  │
│   │ RAGService.buildSystemPromptWithRAG()                 │  │
│   │  - 일반 경로에서만 사용                                │  │
│   │  - Admin 뉴스 작성 GPT 분석은 개입하지 않음           │  │
│   └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│   2. USER PROMPT 결정 (AnalysisAgent)                       │
│   ┌──────────────────────────────────────────────────────┐  │
│   │ Admin 경로: 2단계 프롬프트                            │  │
│   │  - 1차: 구조화 분석                                   │  │
│   │  - 2차: 기사 원문 + 1차 결과 기반 narration 생성      │  │
│   │ 일반 경로: buildFullAnalysisPrompt() 분기             │  │
│   └──────────────────────────────────────────────────────┘  │
│                              │                               │
│                              ▼                               │
│   ┌──────────────────────────────────────────────────────┐  │
│   │ 참조 이미지 주입 (있으면)                              │  │
│   │  - subtitle_foreign_affairs.png (제목 위치)           │  │
│   │  - subheading_reference.jpg (소제목 예시)             │  │
│   │  - pull_quote_ignore.jpg (무시할 부분)                │  │
│   │  - summary_rules.jpg (요약 규칙)                      │  │
│   │  - readability_format.png (가독성 형식)               │  │
│   └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│   3. GPT 호출 (OpenAIService.chat)                          │
│   - Admin 경로 model: gpt-5.4                               │
│   - 일반 경로 model: config 기반                            │
│   - temperature: config에 따라 (기본 0.7)                   │
│   - max_tokens: 8000                                        │
│   - timeout: 180초                                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│   4. 응답 후처리 (AnalysisAgent)                            │
│   - JSON 파싱                                               │
│   - narration 정규화 (인사말 제거)                          │
│   - AnalysisResult 객체 생성                                │
│   - sections 필드 지원 (구조화된 소제목)                    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│   5. Admin UI 후처리 (AdminPage.tsx)                        │
│   - content_summary → newsContent                           │
│   - narration → newsNarration                               │
│   - TTS 생성 (요청 시)                                      │
└─────────────────────────────────────────────────────────────┘
```

## 주요 파일

### System Prompt 소스
| 파일 | 역할 |
|------|------|
| `src/agents/config/prompts/analysis.yaml` | 기본 system prompt 및 태스크별 프롬프트 |
| `src/agents/services/PersonaService.php` | 활성 페르소나가 있으면 DB에서 system prompt 로드 |
| `src/agents/services/RAGService.php` | system prompt 뒤에 RAG 문맥 추가 |

### User Prompt 소스
| 파일 | 역할 |
|------|------|
| `src/agents/agents/AnalysisAgent.php` | 일반 경로 프롬프트 + Admin 전용 2단계 프롬프트 |
| `assets/reference/` | 참조 이미지 (제목/소제목/무시 규칙 등) |

### 후처리
| 파일 | 역할 |
|------|------|
| `src/agents/agents/AnalysisAgent.php` | JSON 파싱, narration 정규화 |
| `src/frontend/src/pages/AdminPage.tsx` | UI 상태 매핑 |
| `public/api/admin/news.php` | DB 저장 |

## 기본 System Prompt

```
당신은 "The Gist"의 수석 에디터입니다.
모든 기사는 해외 뉴스를 한국어로 이해하고 싶어하는 독자를 위한 콘텐츠입니다.
반드시 독자 관점에서 작성하고, 요청된 JSON 형식으로만 응답하세요.
```

## Admin 뉴스 작성 GPT 분석 경로

1. 기본 system prompt만 사용
2. Persona 미사용
3. RAG 미사용
4. 1차 구조화 분석:
   - `news_title`
   - `author`
   - `original_title`
   - `content_summary`
   - `key_points`
   - `critical_analysis.why_important`
5. 2차 narration 생성:
   - 기사 원문 + 1차 분석 결과를 함께 참고
   - paragraph별 한 줄 띄기 강제
   - Economist는 paragraph-first 분석

## User Prompt 공통 규칙

### 소제목 식별
- 본문 중간의 큰 글씨 또는 ALL CAPS 짧은 문구
- `content_summary`에서 `한글 (영문)` 형식으로 나열

### 무시할 부분
- Pull quote 스타일의 큰 볼드 텍스트
- UI 요소: Save, Share, Listen, Sign up 등
- 구독/뉴스레터 유도 문구
- 이미지 캡션/크레딧

### 출력 형식
- `news_title`: 영문 제목 직역
- `content_summary`: 구조화된 형식 (소제목 포함), 최소 600자
- `narration`: 인사말 없이 시작, 최소 1000자, paragraph별 한 줄 띄기
- `sections`: 구조화된 소제목 배열 (optional)

## 참조 이미지 목록

| 이미지 | 용도 |
|--------|------|
| `subtitle_foreign_affairs.png` | 제목 위치 확인용 |
| `subheading_reference.jpg` | 소제목 식별 예시 |
| `pull_quote_ignore.jpg` | 무시할 pull quote 예시 |
| `summary_rules.jpg` | content_summary 작성 규칙 |
| `readability_format.png` | 가독성 형식 예시 |

## 금지 표현

- ~~`지스터`~~ → `독자` 사용 (이미 적용됨)
- 인사말로 시작하지 않음

## 주의사항

1. Admin 뉴스 작성 GPT 분석은 `public/api/admin/ai-analyze.php`에서 Persona/RAG 없이 별도 설정으로 실행된다.
2. Admin 경로는 `enable_interpret = false`로 고정되어 RAG 해석이 글 작성에 개입하지 않는다.
3. Admin 경로는 `gpt-5.4`와 2단계 분석-내레이션 파이프라인을 사용한다.
