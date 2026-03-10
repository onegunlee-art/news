# 런타임 프롬프트 아키텍처

## 개요
분석 품질은 단일 프롬프트 파일이 아니라 여러 레이어의 합성 결과입니다.

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
│   │  - 활성 페르소나 있으면 해당 prompt 사용               │  │
│   │  - 없으면 기본값 (analysis.yaml 또는 하드코딩)         │  │
│   └──────────────────────────────────────────────────────┘  │
│                              │                               │
│                              ▼                               │
│   ┌──────────────────────────────────────────────────────┐  │
│   │ RAGService.buildSystemPromptWithRAG()                 │  │
│   │  - 위 system prompt 뒤에 RAG 문맥 추가                │  │
│   │  - 관련 기사, 피드백, 지식 프레임워크 등              │  │
│   └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│   2. USER PROMPT 결정 (AnalysisAgent)                       │
│   ┌──────────────────────────────────────────────────────┐  │
│   │ buildFullAnalysisPrompt() 분기                        │  │
│   │  - FT.com → buildFTPrompt()                          │  │
│   │  - Economist → buildEconomistPrompt()                │  │
│   │  - 기본 → buildDefaultPrompt()                       │  │
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
│   - model: gpt-5.2                                          │
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
| `src/agents/agents/AnalysisAgent.php` | 도메인별 user prompt 생성 (FT, Economist, 기본) |
| `assets/reference/` | 참조 이미지 (제목/소제목/무시 규칙 등) |

### 후처리
| 파일 | 역할 |
|------|------|
| `src/agents/agents/AnalysisAgent.php` | JSON 파싱, narration 정규화 |
| `src/frontend/src/pages/AdminPage.tsx` | UI 상태 매핑 |
| `public/api/admin/news.php` | DB 저장 |

## 기본 System Prompt (페르소나 비활성화 시)

```
당신은 "The Gist"의 수석 에디터입니다.
모든 기사는 해외 뉴스를 한국어로 이해하고 싶어하는 독자를 위한 콘텐츠입니다.
반드시 독자 관점에서 작성하고, 요청된 JSON 형식으로만 응답하세요.
```

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
- `narration`: 인사말 없이 시작, 최소 1000자
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

1. `config/agents.php`의 `temperature: 0.45`는 Admin 분석 경로에서 실제로 적용되지 않을 수 있음. 실사용은 `config/openai.php` 기본값 `0.7` 경로에 가까움.
2. `use_persona` 옵션은 `ai-analyze.php`에서 명시적으로 처리해야 런타임에 반영됨.
3. `enable_interpret`, `enable_learning` 옵션도 분석 품질에 영향을 줄 수 있음.
