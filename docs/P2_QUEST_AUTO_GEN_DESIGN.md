# P2-A 설계도 — 경첩 1개로 도는 "최소 퀘스트" 자동 생성

> **범위:** P2-A0 설계 **확정** (2026-06) · P2-A1부터 단계 구현  
> **전제:** 입력 = MySQL `news.content`(게시본) only · `judgement_records` 금지 · EDU `edu_*` 격리 · the gist 본체 READ-only  
> **검증 완료:** P2-H 경첩 6/6 ○, side 6/6 ○, shake 본문 fact 포함  
> **미검증(2단계):** 축(소제목 → 측면 N개) — 이번 설계에 **자리만** 표시

---

## 0. 한 줄 다이어그램

```
MySQL news.content
       │
       ▼
  [경첩 추출]  ← edu_gist_hinge_extract (P2-H 검증 로직 재사용)
       │
       ▼
  hinge JSON ─────────────────────────────────────────────┐
       │                                                  │
       ├─► [1회 생성] hammer_hints + quest shell          │
       │         hook / shared_conclusion / shake         │
       │                                                  │
       ├─► [실시간 코칭] SocraticCoach / Hammer / Director │
       │         A만 → shake  ·  (맥락 복귀 → P2-A5+)      │
       │                                                  │
       └─► [종착 평가] P2-B 자리 (미구현)                  │
                 A만 vs A+B 긴장 품었나                     │
                                                          │
  [2단계] axes[] ──► convergent Hammer / conflict_summary ┘
         (소제목→측면, 630 수동과 동일 레이어)
```

**핵심 통찰:** hook·코치 흔들기·학생 글 목표·(나중에) 축 분기가 **god-branch처럼 따로 만들어지지 않고**, 경첩 JSON 한 덩어리에서 파생된다.

---

## 1. 경첩 JSON 스키마 (확정)

P2-H 검증 출력 — **퀘스트 생성·코칭·평가의 단일 소스**.

| 필드 | 의미 | 검증 |
|------|------|------|
| `hinge` | "A이지만 B" 한 문장 — 글의 핵심 긴장 | 6/6 ○ |
| `side_a` | 통념 / 표면 / 단순 서사 / **질문 프레임** | 6/6 ○ (546 보강) |
| `side_b` | 본문이 드러내는 반대·복잡한 진실 | 6/6 ○ |
| `hook_student` | 14세용 진입 질문 (side_a에서 시작) | 6/6 양호 |
| `shake_prompt` | A만 말한 학생을 B로 흔드는 1문장 + **본문 fact** | 6/6 fact 포함 |
| `article_form` | content 하단 출처 (FA / economist) | judgement 없이도 OK |
| `confidence` | high / medium / low | — |

**저장 제안 (1단계):** `edu_daily_quests`에 넣기 전, `edu_quest_hinge_drafts` 같은 초안 테이블 또는 `hammer_hints._hinge_source` JSON blob — **라이브 7명 cohort 퀘스트와 분리**.

---

## 1-B. 사람 관문 — **확정** (P2-A 운영 모델)

**원칙:** 테스트와 운영을 한 번에 — **애매할 때만 사람**, 단 **라이브 전 전수 검수**가 안전망. 검수 행위 = 정답률 측정 (별도 벤치마크 X).

### confidence 게이트

| LLM `confidence` | UI/CLI 표시 | 사람 부담 |
|------------------|-------------|-----------|
| `high` | 자동 통과 표시 | 전수 검수만 (우선순위 낮음) |
| `medium` | 자동 통과 표시 | 전수 검수만 |
| `low` | **「검증 필요」** 플래그 | 먼저 볼 큐 |
| `null` / `hinge: null` | **「검증 필요」** | 추출 실패·근거 부족 |

→ 상업 모델("애매할 때만 사람")을 **지금부터** 돌리되, 게이트 오판은 **전수 검수**가 잡음.

### 검수 기록 (최소 — **필수**)

본인이 "어차피 다 본다" ≠ 데이터. **승인/수정 클릭**이 없으면 게이트 신뢰도를 숫자로 못 잼.

검수 화면 또는 CLI 보조 도구:

| 동작 | 기록 |
|------|------|
| **승인** | LLM 경첩 그대로 OK (1클릭) |
| **수정** | `review_action=edit` + 변경 필드·수정본 (`hinge` / `side_a` / `side_b` / `hook_student` / `shake_prompt`) |
| (공통) | `llm_confidence`, `news_id`, `reviewed_at`, `reviewer` |

**자동 집계 (게이트 신뢰도):**

| 지표 | 의미 |
|------|------|
| high/medium → 승인 | 게이트 적중 |
| high/medium → 수정 | **과신** (false pass) |
| low/null → 승인 | **보수적 게이트** (false flag) |
| low/null → 수정 | 게이트 적중 |

→ `docs/` 또는 `edu_hinge_reviews` JSONL/테이블에 누적 → **confidence vs 본인판정 일치율** = 게이트 신뢰도.

### 라이브 승격

모든 퀘스트: **검수 기록(승인 또는 수정본) 있어야** `edu_daily_quests` 승격. 게이트만으로 라이브 X.

## 2. 갈래 1 — 경첩 → 퀘스트 생성 (정적, 1회)

### 2-A. 필드 매핑표

| 경첩 JSON | 퀘스트 역할 | 매핑 대상 (`edu_daily_quests` / `hammer_hints`) | 비고 |
|-----------|------------|--------------------------------------------------|------|
| `hook_student` | 학생이 **처음 보는** 질문 | `hammer_hints.hook_short` | UI 카드·목록용 1줄 |
| `hook_student` + `side_a` 맥락 | 세션 시작 시 코치가 읽는 긴 hook | `hammer_hints.hook_full` | myth_bust: `submit_opening` 전 assistant 1턴 ([`chat.php`](../public/api/edu/session/chat.php) L104–107) |
| `side_a` | 학생이 **처음 취할 법한 입장** (A쪽) | `hammer_hints.fallback_adversarial.pro` **또는** `_hinge.side_a` (신규) | 1단계: 찬반 없는 myth_bust — **명시 저장만**, UI stance 없음 |
| `side_b` | 코치가 흔들 **방향** / 공유 결론 | `hammer_hints.shared_conclusion` | convergent Hammer·Reflection의 "이미 드러난 사실" ([`Hammer.php`](../src/backend/Services/edu/Agents/Hammer.php) L76, L126) |
| `shake_prompt` | A-only일 때 **고정 흔들기** 문장 | `hammer_hints._hinge.shake_prompt` (신규) **또는** `hammer_hints.pro`/`con` 중 B쪽 | 1단계: axes 없을 때 Hammer **adversarial_fallback** 입력 ([`Hammer.php`](../src/backend/Services/edu/Agents/Hammer.php) L592–607) |
| `hinge` | 학생 글 **목표 긴장** (종착점 기준) | `hammer_hints._hinge.hinge` (신규) | compose·P2-B 평가 기준; Reflection 3줄 정리 목표 |
| `side_b` (요약) | 퀘스트 부제·정렬 문장 | `alignment_summary` | "많은 분석가/본문이 동의하는 사실" = side_b의 표면층 |
| — | 갈등·층위 분기 | `conflict_summary` | **1단계: 빈칸 또는 hinge 1줄** · 2단계: axes 붙을 때 채움 |
| `article_form` | 기사 유형 | `hammer_hints._meta.article_form` | quest_frame 자동 추론 보조 |
| — | 진입 UX | `hammer_hints.quest_frame` | **규칙:** 질문 프레임 / myth 패턴 → `myth_bust` → `entry_mode=open_response` ([`eduQuestConfig.php`](../public/api/edu/lib/eduQuestConfig.php)) |
| — | Hammer 모드 | `hammer_hints.mode` | **1단계:** `convergent` **아님** → `adversarial` + shake fallback (axes 없음) |
| — | 찬반 라인 | `pro_line`, `con_line` | **1단계: 생략 또는 placeholder** · 630 수동은 한국 핵무장 질문으로 **수동 확장** |
| — | 연관 기사 | `edu_quest_articles` | **1단계:** `news_id` primary 1건만 · context/counter는 2단계 또는 수동 |
| — | 축 | `hammer_hints.axes[]`, `counter_map` | **2단계 전용 — 비움** |

### 2-B. `eduResolveQuestConfig` 파생 (기존 코드 그대로)

| hammer_hints | 파생 |
|--------------|------|
| `quest_frame=myth_bust` | `entry_mode=open_response`, `coach_profile=open` |
| `quest_frame=decision_inquiry` | `entry_mode=stance_pick`, `coach_profile=decision` |
| (기타) | `stance_pick` + `default` |
| `mode=adversarial` (1단계 기본) | Mixup RAG + `pro`/`con` hint 키 |
| `mode=convergent` + `axes≥2` (2단계) | axis 분류 → pivot_question |

### 2-C. 630 수동 퀘스트 vs 경첩 자동 — 대조 (검증 케이스)

**수동 기준:** [`Q-NUKE-AXIS-630`](../tools/edu_nuclear_axis_quest_fixture.php) (news_id=630, 손 제작)

**630에서 기대되는 경첩 (content 기반, P2-H 프롬프트 적용 시):**

| 경첩 필드 | 자동 추출 (예상) | 630 수동 |
|-----------|-----------------|----------|
| `side_a` | "핵무기가 있으면 큰 전쟁·직접 충돌은 막는다" (통념) | hook·shared_conclusion에 **암시** (명시 side_a 없음) |
| `side_b` | "그러나 드론·미사일·재래식 공격은 막지 못함 (우크라이나·이스라엘·인도-파키스탄)" | `shared_conclusion` ≈ **일치** |
| `hinge` | "핵 억지는 강대국 간 직접 충돌은 막지만, 재래식·하이브리드 공격은 막지 못한다" | hook·alignment과 **동일 긴장** |
| `hook_student` | "핵이 있으면 정말 안전할까?" / "큰 전쟁은 막아도 드론 공격은?" | `hook_short`: "핵 억지가 큰 전쟁은 막아도, 미사일·드론… 왜 못 막고?" → **거의 동일** |
| `shake_prompt` | "러시아가 핵 대응을 시사했는데 우크라이나가 전략폭격기 기지를 드론으로 타격했고, 실제 보복은 재래식이었어" | axes Hammer·fallback에 **분산** (직접 1문장 아님) |

**자동으로 재현되는 것 (1단계 최소):**

| 요소 | 판정 |
|------|------|
| 핵심 긴장 (경첩) | ○ — 수동 `shared_conclusion` + hook과 같은 A/B |
| myth_bust 진입 (`open_response`) | ○ — 질문형 hook → `quest_frame=myth_bust` 규칙 |
| `hook_short` / `hook_full` | ○~△ — hook_student 1줄은 거의 같음; hook_full의 **한국 핵무장 확장**은 자동만으론 **없음** (수동 가치 추가) |
| `shared_conclusion` | ○ — side_b에서 직접 매핑 |
| shake 시 본문 fact | ○ — P2-H 철칙 |

**자동만으로 **안** 나오는 것 (의도적 2단계·수동 레이어):**

| 요소 | 이유 | 단계 |
|------|------|------|
| `axes[3]` (군사 / 규범 / 방어) | 소제목→측면 추출 **미검증** | **2단계** |
| `counter_map`, `contrast_prompt`, `pivot_question` | axes 부속 | 2단계 |
| `conflict_summary` ("같은 사실인데 대응은…") | axes 3분기 요약 | 2단계 |
| `pro_line` / `con_line` (한국 핵무장 찬반) | 정책 질문 **수동 확장** | 1단계 **의도적 제외** |
| `quest_title` ("우리나라도 핵을…") | 로컬화·확장 | 수동 또는 3단계 템플릿 |
| context 기사 475/449/615 | Mixup·RAG 풍부화 | 2단계 또는 별도 추천 |
| convergent Hammer (층위 pivot) | axes 필요 | 2단계 |

**검증 결론:**  
경첩 자동 생성으로 **630의 "심장"(hook + shared 긴장 + shake fact)** 은 재현 가능. **"손맛"(한국화 제목, 3축 convergent, context 기사)** 은 수동 레이어 — 자동 ≠ 수동 전체 복제가 아니라 **핵심 긴장 일치**가 1단계 통과 기준.

**1단계 통과 기준 (630 리플레이):**

1. `hinge` / `shared_conclusion` / `hook_short` — 사람이 "같은 퀘스트"라고 ○  
2. myth_bust E2E: opening → evidence → **shake_prompt 기반 hammer** → reflection → writing  
3. axes·convergent 없이도 세션 **완주 가능**

---

## 3. 갈래 2 — 경첩 → 코칭 (실시간, 매 대화)

### 3-A. 코치 행동 규칙 (경첩 주입)

| 학생 상태 | 코치 행동 | 경첩 소스 | 기존 연결 |
|-----------|----------|-----------|-----------|
| **A만** (side_a와 동일·유사) | B쪽으로 흔들기 | `shake_prompt` | Hammer `adversarial_fallback` ([`Hammer.php`](../src/backend/Services/edu/Agents/Hammer.php) L598–600) |
| **경첩 무관** (엇나감) | hinge 맥락으로 **되돌리기** | `hinge` + `hook_student` | **P2-A5+** (1단계 **제외** — 코치 판정 로직 별도) |
| **B까지** (A와 B 긴장 모두) | 깊이·근거 질문 | `side_b` | reasoning/evidence — 기존 Director |
| myth_bust opening | side_a 쪽 자유 서술 수용 | `side_a` | `submit_opening` ([`chat.php`](../public/api/edu/session/chat.php) L91–147) |

### 3-B. FSM × 경첩 주입점

```
stance / opening          reasoning              evidence              hammer                 reflection           writing
     │                        │                     │                    │                       │                  │
     │ hook_full              │ evaluateResponse    │ nudge or advance   │ strike (shake)        │ summarize        │ compose
     │ (hook_student+)        │ (기존)              │ + article fact     │ shake if A-only       │ 3줄 = hinge 목표  │ P2-B slot
     │                        │  ※ 맥락복귀=P2-A5+  │                    │                       │                  │
 open_response ──────────────┴─────────────────────┴────────────────────┴───────────────────────┴──────────────────┘
 (630 myth_bust)              SocraticCoach          Director             Hammer                  Reflection
 stance_pick ──────────────── (1단계 최소 미사용; 2단계 decision_inquiry)
```

| Phase | 에이전트 | 경첩 주입 (1단계) | 기존 quest 필드 |
|-------|----------|-------------------|-----------------|
| opening | (assistant) | `hook_full` ← hook_student + 1문장 side_a 맥락 | `hammer_hints.hook_full` |
| reasoning | SocraticCoach | 기존 myth_bust 흐름 (맥락 복귀 **없음**) | `alignment_summary` |
| evidence | Director + nudge | "본문 fact 찾기" — shake에 쓴 fact와 **중복 OK** | `articles[0]` excerpt |
| hammer | Hammer | **axes 없음:** `shake_prompt` + `side_b`를 counter_line 대체 | `fallback_adversarial` / `_hinge.shake_prompt` |
| reflection | Reflection | 3줄이 `hinge` 긴장을 품었는지 implicit | `shared_conclusion` |
| compose | GistStyleComposer | `essay_structure` 목표 = hinge (P2-B 전) | blueprint |

### 3-C. 1단계 코칭 범위

**포함 (1단계):** hook → opening → reasoning/evidence → **shake 1회** → reflection  
**제외 (다음 조각):**

- **맥락 복귀** (엇나감 감지 + hinge 되돌리기) → **P2-A5**  
- convergent axis 분류 — axes 없음  
- Mixup RAG 다기사 — primary 1편만 (선택)  
- StanceScorer pro/con intensity — myth_bust는 stance=`myth_bust`

---

## 4. 갈래 3 — 경첩 → 학생 글 평가 (P2-B 토대, 자리만)

**이번 단계: 구현 X.** 입력·출력 슬롯만 정의.

### 4-A. 평가 질문

| 판정 | 의미 |
|------|------|
| `A_only` | side_a(통념/질문 한쪽)만 반복, B 미접촉 |
| `A_plus_B` | hinge 긴장(A이지만 B)을 학생 글가 **인지**하고 있음 |
| `off_hinge` | 경첩과 다른 긴장으로 새 글 |

### 4-B. 입력 (P2-B)

- `hinge`, `side_a`, `side_b` (quest `_hinge`)
- `blueprint`: reason, evidence, rebuttal, reflection_lines
- `essay_artifact` (최종 글)

### 4-C. 출력 (P2-B)

```json
{
  "hinge_coverage": "A_only | A_plus_B | off_hinge",
  "evidence_from_article": true,
  "notes": "한 줄"
}
```

→ 누적 성장·수준 평가·교사 대시보드 (P2-B)

### 4-D. 코드 자리 (미구현)

- `GistStyleComposer::compose` 직후 또는 별도 `edu_evaluate_hinge_coverage()`
- 저장: `edu_quest_sessions.hinge_eval_json` (제안)

---

## 5. 축(2단계)이 얹힐 자리 — 1단계가 막지 않음

```
hammer_hints
├── _hinge { hinge, side_a, side_b, hook_student, shake_prompt }  ← 1단계 (필수)
├── hook_short, hook_full, shared_conclusion, quest_frame, mode    ← 1단계 (매핑)
├── fallback_adversarial { pro, con }                              ← 1단계 (side_a / side_b 요약)
├── axes[]                                                         ← 2단계 (비움)
│     ├── axis_id, axis_label, thesis, news_id
│     └── contrast_prompt { names_axis, distinguishes_from, pivot_question }
├── counter_map                                                    ← 2단계
├── alignment_summary, conflict_summary                            ← 1단계 약식 / 2단계 full
└── _meta { article_form, extracted_at, source: news.content }
```

| 2단계 산출 | 소스 | 630 예시 |
|------------|------|----------|
| `axes[0]` | content §1 소제목 | military — "군사로는 막기 어렵다" |
| `axes[1]` | content §2 | norms — "새 약속이 필요하다" |
| `axes[2]` | content §3 | defense — "방어에 투자해야 한다" |
| `mode` | axes≥2 → `convergent` | 630 수동과 동일 |
| `conflict_summary` | axes 라벨 join | "군사 vs 규범 vs 방어" |

**1단계 호환:** `axes=[]` 이면 [`Hammer::fallbackToAdversarial`](../src/backend/Services/edu/Agents/Hammer.php) — **이미 존재**. 2단계는 axes 채우기만 하면 convergent 경로 활성화.

---

## 6. 1단계 "최소 퀘스트" 범위 (진짜 최소)

### 포함 ✅

1. **입력:** `news.content` 1편 (MySQL)  
2. **경첩 추출:** P2-H 스크립트 로직 → `_hinge` JSON  
3. **퀘스트 shell:** `quest_code=AUTO-{news_id}`, primary article 1건, `quest_frame=myth_bust`, `mode=adversarial`  
4. **hammer_hints:** `hook_short`, `hook_full`(짧게), `shared_conclusion`, `_hinge`, `fallback_adversarial`  
5. **학생 UX:** hook → opening → reasoning/evidence → **shake 1회** → reflection → writing  
6. **코칭:** `shake_prompt`만 (맥락 복귀 **없음**)  
7. **격리:** pilot cohort / `live_at=null` / 기존 시드 퀘스트 무변경  

### 제외 ❌ (자리만)

| 제외 | 이유 |
|------|------|
| axes / convergent / counter_map | 2단계 검증 전 |
| P2-B hinge_coverage 평가 | 종착점 — 1단계 후 |
| pro/con stance_pick 퀘스트 | decision_inquiry 별 트랙 |
| context·counter 다기사 자동 편성 | RAG/Mixup 2단계 |
| quest_title 한국화·정책 확장 | 수동 630의 "우리나라 핵" — 템플릿 3단계 |
| judgement_records 입력 | 621 불일치 교훈 |
| 맥락 복귀 (엇나감 감지) | 코치 판정 정교 — **P2-A5** |
| 자동 `edu_daily_quests` 라이브 승격 | 사람 검수 게이트 |

### 최소의 최소 (1단계 퀘스트 — A3 이후)

> **hook → shake → 글** (axes·맥락복귀·평가 없음)

### 첫 조각 (지금) — **P2-A1만**

> **MySQL content → 경첩 JSON + confidence + 검수 기록.** 매핑·퀘스트·라이브 **없음**.

---

## 7. 단계 쪼개기 (한 commit = 한 조각)

| 단계 | 산출 | 라이브 영향 | 검증 |
|------|------|-------------|------|
| **P2-A0** | 본 설계도 합의 | 없음 | ✅ 확정 |
| **P2-A1** | **경첩 추출 CLI** + confidence + **검수 기록** (승인/수정 JSONL) | 없음 | content→JSON, 게이트 집계 |
| **P2-A2** | hinge → `hammer_hints` 매핑 함수 + fixture (`AUTO-630-min`) | 없음 | 630 hook/shared 대조 |
| **P2-A3** | Supabase draft quest 1건 seed (`live_at=null`) | 없음 | catalog 미노출 |
| **P2-A4** | Hammer: `_hinge.shake_prompt` 우선 (axes 없을 때) | 없음 | isolation test |
| **P2-A5** | SocraticCoach: **맥락 복귀** (엇나감→hinge) | 없음 | scripted dialogue |
| **P2-A6** | myth_bust E2E smoke (`AUTO-630-min`, pilot only) | pilot cohort | R6 스타일 |
| **P2-B0** | hinge_coverage 평가 설계 | 없음 | — |
| **P2-A7** | (2단계) 소제목→axes 추출 | 없음 | 630 3축 |
| **P2-A8** | (2단계) convergent + conflict_summary | 없음 | 630 full parity |

**다음 착수 = P2-A1 단독** (A2와 묶지 않음).

### P2-A1 상세 (확정 스펙)

**입력:** MySQL `news.content` (news_id 인자) — `judgement_records` **금지**

**출력:**

```json
{
  "news_id": 630,
  "title": "...",
  "hinge": "A이지만 B",
  "side_a": "...",
  "side_b": "...",
  "hook_student": "...",
  "shake_prompt": "...",
  "article_form": "FA|economist|unknown",
  "confidence": "high|medium|low",
  "needs_review": false,
  "notes": "...",
  "extracted_at": "ISO8601",
  "source": "mysql.news.content"
}
```

- `needs_review`: `true` iff `confidence` ∈ {`low`, `null`} 또는 `hinge == null`
- CLI stdout + `docs/hinge_extractions/{news_id}.json` (또는 배치 manifest)

**검수 기록** (`docs/hinge_reviews/reviews.jsonl` 또는 Supabase `edu_hinge_reviews`):

```json
{
  "news_id": 630,
  "llm_confidence": "high",
  "review_action": "approve|edit",
  "edited_fields": ["side_a"],
  "final_hinge": { "...": "수정본 또는 LLM 원본" },
  "reviewed_at": "ISO8601",
  "reviewer": "iwg"
}
```

**집계 CLI** (`edu_hinge_gate_stats.php` 등): high+수정, low+승인 비율 → 게이트 신뢰도 리포트

**재사용:** P2-H [`edu_gist_hinge_extract_test.php`](../tools/edu_gist_hinge_extract_test.php) 프롬프트·로직; 입력 소스만 MySQL로 전환

**라이브:** **무영향** (추출·기록 도구만)

---

## 8. 리스크·철칙 재확인

| 철칙 | 내용 |
|------|------|
| 입력 | MySQL `news.content` only |
| side_a | 통념/표면/서사/질문프레임 — 서사로 치환 금지 (546) |
| shake | 본문 fact 필수 |
| 격리 | edu_* · draft · 7명 라이브 무영향 |
| god-branch 금지 | hook·shake·축·평가 **별도 invent** — 경첩에서만 파생 |

---

## 9. 합의 체크리스트

- [x] 630 **심장**(hook+shared+shake) = 1단계 통과 기준  
- [x] 1단계 **myth_bust + shake only** (맥락 복귀·axes·평가 제외)  
- [x] **confidence 게이트** + **전수 검수** + **검수 기록→게이트 신뢰도**  
- [x] 첫 조각 **P2-A1만** (추출+기록; A2는 다음 commit)  
- [x] 맥락 복귀 → **P2-A5** (1단계 밖)  
- [ ] 2단계 axes 검증 샘플: **630** (A7)

---

*P2-A0 확정 · **P2-A1 구현** (edu_gist_hinge_extract, edu_hinge_review, edu_hinge_gate_stats)*
