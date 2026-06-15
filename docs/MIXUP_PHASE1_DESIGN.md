# Mix-up Phase 1 설계안: 수렴형 Hammer

> **상태**: 설계 검토 중 (코드 작성 전)  
> **브랜치**: `feature/mixup-stance`  
> **작성일**: 2026-06-13

---

## 1. 핵심 설계 결정

### 1.1 런타임 검색 제거
- `findMixUpPairs` (intelligence_embeddings 벡터 검색)를 **사용하지 않음**
- 대신 퀘스트 생성 시점에 **사전 큐레이션**
- 런타임 Hammer는 **조회만** — 검색 없음, 버그 3개 우회

### 1.2 수렴형 모델 채택
- 대립형(pro vs con)이 아닌 **수렴형**(같은 결론, 다른 근거)
- Hammer 질문: "너의 입장은?" → "너의 **근거 축**은?"
- 예: "이란 전쟁은 안 끝난다"는 같은 결론인데, **기술적/정치적/구조적** 이유가 다름

### 1.3 데이터 소스
- **judgement_records** (정제 분석본, thesis 포함)
- intelligence_embeddings **미사용**
- MySQL news, analysis_embeddings **READ만**

---

## 2. 스키마 제약 및 활용

### 2.1 현재 스키마 (변경 불가)

```sql
-- edu_daily_quests
pro_line text NOT NULL,           -- 수렴형에서 의미 전환 필요
con_line text NOT NULL,           -- 수렴형에서 의미 전환 필요
alignment_summary text,           -- 기존 용도 유지
conflict_summary text NOT NULL,   -- 수렴형: "공동 결론"으로 의미 전환
hammer_hints jsonb NOT NULL,      -- 핵심: 근거 축 배열 저장

-- edu_quest_articles.role
CHECK (role IN ('primary', 'context', 'counter'))  -- 'pro', 'con' 없음
```

### 2.2 수렴형 컬럼 매핑

| 컬럼 | 대립형 (기존) | 수렴형 (신규) |
|------|-------------|-------------|
| `pro_line` | 찬성 입장 | **가장 강한 근거 축 A** (예: 기술적 이유) |
| `con_line` | 반대 입장 | **가장 강한 근거 축 B** (예: 정치적 이유) |
| `conflict_summary` | 갈등축 설명 | **공동 결론** (예: "이란 전쟁은 깔끔히 안 끝난다") |
| `alignment_summary` | 공통 인식 | 기존 용도 유지 |
| `hammer_hints` | `{pro: ..., con: ...}` | **근거 축 배열** (아래 구조) |

### 2.3 hammer_hints 신규 구조 (contrast_prompt 방식)

> **설계 원칙**: `hammer_question`(고정 질문)이 아닌 `contrast_prompt`(대비 구조).  
> 가정 뒤집기("발전하면?")가 아니라, 학생 근거를 두 층위 중 어디인지 **양자택일** 시킴.  
> 목표: 학생이 자기 문장의 "근거 층위"를 처음 의식하게 만들기.

```json
{
  "mode": "convergent",
  "shared_conclusion": "이란 전쟁은 깔끔하게 끝나지 않는다",
  "axes": [
    {
      "axis_id": "tech",
      "axis_label": "기술적 한계",
      "thesis": "첨단 정밀타격과 AI 작전으로 전쟁을 짧게 끝낼 수는 있어도 원하는 정치적 결과는 얻지 못한다",
      "author": "로런스 프리드먼",
      "news_id": 555,
      "contrast_prompt": {
        "names_axis": "무기와 기술의 한계 — 아무리 정밀해도 정치적 결말은 못 얻는다",
        "distinguishes_from": {
          "politics": "이건 정치인 탓이 아니야. 프리드먼은 누가 집권하든 기술의 한계는 같다고 봐",
          "structure": "이건 '전쟁은 원래 그래'가 아니야. 프리드먼은 이란이 특히 강해서라고 봐"
        },
        "pivot_question": "네 근거는 '무기의 한계' 때문이야, 아니면 '이란이라는 상대'가 특별해서야?"
      }
    },
    {
      "axis_id": "politics",
      "axis_label": "국내정치 함정",
      "thesis": "군사적 우위만으로는 지속 가능한 질서를 만들지 못한다. 미국 국내정치가 장기 전략을 불가능하게 한다",
      "author": "이코노미스트",
      "news_id": 422,
      "contrast_prompt": {
        "names_axis": "미국 국내정치의 함정 — 이기고도 지속 가능한 질서를 못 만든다",
        "distinguishes_from": {
          "tech": "이건 무기 문제가 아니야. 이코노미스트는 기술이 아무리 좋아도 정권 교체 때문에 장기 전략이 안 된다고 봐",
          "structure": "이건 '전쟁의 본질'이 아니야. 이코노미스트는 미국 정치가 특히 문제라고 봐"
        },
        "pivot_question": "네 근거는 '미국 정치가 특히 불안정해서'야, 아니면 '어느 나라든 정치는 다 그래서'야?"
      }
    },
    {
      "axis_id": "structure",
      "axis_label": "전쟁의 구조",
      "thesis": "완전 해결은 불가능하고, 불안정한 봉합으로 끝나는 게 현대 전쟁의 구조적 귀결이다",
      "author": "기데온 로즈",
      "news_id": 528,
      "contrast_prompt": {
        "names_axis": "전쟁이라는 것 자체의 구조 — 상대가 누구든, 무기가 뭐든, 전쟁은 원래 깔끔히 안 끝난다",
        "distinguishes_from": {
          "tech": "이건 무기 탓이 아니야. 로즈는 무기 얘기를 아예 안 해. 기술이 발전해도 마찬가지라고 봐",
          "politics": "이건 정치인 탓이 아니야. 로즈는 누가 집권하든 전쟁 구조는 같다고 봐"
        },
        "pivot_question": "네 근거는 '이란이 특별해서'야, 아니면 '전쟁이라는 게 원래 그래서'야?"
      }
    }
  ],
  "fallback_adversarial": {
    "pro": "협상으로 끝낼 수 있다는 낙관론",
    "con": "군사력으로 끝낼 수 있다는 매파 입장"
  }
}
```

**주요 필드:**
- `mode`: `"convergent"` | `"adversarial"` — 퀘스트 유형 구분
- `axes[]`: 근거 축 배열 (최소 2개, 권장 3개)
- `contrast_prompt`: **핵심** — 가정 뒤집기 대신 근거 층위 양자택일
  - `names_axis`: 이 축이 뭘 보는지 한 문장
  - `distinguishes_from`: 다른 축과 어떻게 다른지 (학생에게 직접 말할 수 있는 톤)
  - `pivot_question`: 학생 근거를 두 층위 중 어디인지 강제하는 질문
- `fallback_adversarial`: 수렴형에서도 pro_line/con_line NOT NULL 충족용

---

## 3. 이란 클러스터 손 작성 예시

### 3.1 edu_daily_quests 행 (contrast_prompt 구조)

```json
{
  "quest_code": "Q-IRAN-FOREVER-001",
  "quest_title": "이란 전쟁, 정말 끝낼 수 있을까?",
  "grade_band": "high",
  "status": "draft",
  "manual_arc": "ARC-IRAN-REGION",
  
  "pro_line": "기술적 관점: 정밀타격 기술의 한계가 정치적 결말을 막는다 (Freedman)",
  "con_line": "구조적 관점: 불안정한 봉합이 전쟁의 귀결이다 (Rose)",
  
  "alignment_summary": "세 명의 전문가 모두 '이란 전쟁은 미국이 원하는 대로 깔끔하게 끝나지 않는다'는 데 동의한다. 군사적 우위가 정치적 승리로 이어지지 않는다는 공통 인식.",
  
  "conflict_summary": "공동 결론: 이란 전쟁은 깔끔하게 끝나지 않는다. 그러나 '왜' 안 끝나는지에 대한 이유가 다르다 — 기술의 한계인가, 국내정치의 함정인가, 전쟁 구조 자체의 문제인가.",
  
  "hammer_hints": {
    "mode": "convergent",
    "shared_conclusion": "이란 전쟁은 깔끔하게 끝나지 않는다",
    "axes": [
      {
        "axis_id": "tech",
        "axis_label": "기술적 한계",
        "thesis": "첨단 정밀타격과 AI 작전으로 전쟁을 짧게 끝낼 수는 있어도 원하는 정치적 결과는 얻지 못한다. 체제 생존 의지가 강한 이란은 군사적 열세에서도 협상력을 유지한다.",
        "author": "로런스 프리드먼",
        "news_id": 555,
        "contrast_prompt": {
          "names_axis": "무기와 기술의 한계 — 아무리 정밀해도 정치적 결말은 못 얻는다",
          "distinguishes_from": {
            "politics": "이건 정치인 탓이 아니야. 프리드먼은 누가 집권하든 기술의 한계는 같다고 봐",
            "structure": "이건 '전쟁은 원래 그래'가 아니야. 프리드먼은 이란이 특히 강해서라고 봐"
          },
          "pivot_question": "네 근거는 '무기의 한계' 때문이야, 아니면 '이란이라는 상대'가 특별해서야?"
        }
      },
      {
        "axis_id": "politics",
        "axis_label": "국내정치 함정",
        "thesis": "전쟁에서 이겨도 국내정치 때문에 지속 가능한 질서를 못 만든다. 미국 내 여론과 정권 교체가 장기 전략을 불가능하게 한다.",
        "author": "이코노미스트",
        "news_id": 422,
        "contrast_prompt": {
          "names_axis": "미국 국내정치의 함정 — 이기고도 지속 가능한 질서를 못 만든다",
          "distinguishes_from": {
            "tech": "이건 무기 문제가 아니야. 이코노미스트는 기술이 아무리 좋아도 정권 교체 때문에 장기 전략이 안 된다고 봐",
            "structure": "이건 '전쟁의 본질'이 아니야. 이코노미스트는 미국 정치가 특히 문제라고 봐"
          },
          "pivot_question": "네 근거는 '미국 정치가 특히 불안정해서'야, 아니면 '어느 나라든 정치는 다 그래서'야?"
        }
      },
      {
        "axis_id": "structure",
        "axis_label": "전쟁의 구조",
        "thesis": "완전 해결은 불가능하고, 불안정한 봉합으로 끝나는 게 현대 전쟁의 구조적 귀결이다. 이건 이란만의 문제가 아니라 전쟁 자체의 성격이다.",
        "author": "기데온 로즈",
        "news_id": 528,
        "contrast_prompt": {
          "names_axis": "전쟁이라는 것 자체의 구조 — 상대가 누구든, 무기가 뭐든, 전쟁은 원래 깔끔히 안 끝난다",
          "distinguishes_from": {
            "tech": "이건 무기 탓이 아니야. 로즈는 무기 얘기를 아예 안 해. 기술이 발전해도 마찬가지라고 봐",
            "politics": "이건 정치인 탓이 아니야. 로즈는 누가 집권하든 전쟁 구조는 같다고 봐"
          },
          "pivot_question": "네 근거는 '이란이 특별해서'야, 아니면 '전쟁이라는 게 원래 그래서'야?"
        }
      }
    ],
    "fallback_adversarial": {
      "pro": "외교적 해결 가능론 (새 핵합의로 관리 가능)",
      "con": "군사적 해결 가능론 (압도적 힘으로 굴복시킬 수 있다)"
    }
  },
  
  "pilot_priority": "A"
}
```

### 3.2 edu_quest_articles 행들

| news_id | role | title | 비고 |
|---------|------|-------|------|
| 555 | primary | 이란과 영원한 전쟁의 함정 | axis: tech |
| 422 | context | 끝나지 않는 전쟁의 높은 대가 | axis: politics |
| 528 | context | 이란은 베트남처럼, 우크라이나는 한국처럼 | axis: structure |
| 496 | counter | 트럼프가 새 핵합의를 이끌어낼 수 있나 | (참고용, 낙관론) |

**role 매핑**: 현재 스키마에 `axis_tech` 같은 role이 없으므로, `hammer_hints.axes[].news_id`로 연결.

---

## 4. 런타임 Hammer 조회 로직

### 4.1 현재 Hammer.php (대립형)

```php
$counterLine = $stance === 'pro' ? ($quest['con_line'] ?? '') : ($quest['pro_line'] ?? '');
$hints = $quest['hammer_hints'] ?? [];
$hintKey = $stance === 'pro' ? 'con' : 'pro';
$hammerHint = $hints[$hintKey] ?? '';
```

### 4.2 신규 로직 (수렴형 + contrast_prompt)

```php
$hints = $quest['hammer_hints'] ?? [];
$mode = $hints['mode'] ?? 'adversarial';

if ($mode === 'convergent') {
    // 1. 학생 답변에서 근거 축 감지 (LLM 기본, 키워드 fallback)
    $studentAxis = detectStudentAxis($studentReason, $hints['axes'], $llm);
    
    // 2. 다른 축 선택 (학생이 tech면 politics 또는 structure)
    $counterAxis = pickCounterAxis($hints['axes'], $studentAxis);
    
    // 3. contrast_prompt로 프롬프트 구성
    $contrast = $counterAxis['contrast_prompt'];
    $distinguishText = $contrast['distinguishes_from'][$studentAxis['axis_id']] ?? '';
    
    // 프롬프트 (학생 문장을 거울처럼 비춤):
    // "너는 '{$studentAxis['axis_label']}' 관점에서 썼어.
    //  그런데 {$counterAxis['author']}은 같은 결론을 다른 시각으로 봐:
    //  '{$contrast['names_axis']}'
    //  {$distinguishText}
    //  
    //  {$contrast['pivot_question']}"
} else {
    // 기존 대립형 로직 유지
    $counterLine = $stance === 'pro' ? ($quest['con_line'] ?? '') : ($quest['pro_line'] ?? '');
}
```

### 4.3 학생 근거 층위 감지 (detectStudentAxis)

**분류 단위: 결론이 아니라 근거 층위** — 학생은 이미 `shared_conclusion`에 동의한 상태.

| 역할 | 모델 | 이유 |
|------|------|------|
| 층위 분류 | **gpt-5.4-mini** | scores + cue JSON, 경계선 구분 |
| Hammer pivot | **gpt-5.4** | 학생 표현 인용·pivot 품질 |

```php
// JSON 응답 형식
{"axis_id": "tech|politics|structure", "scores": {"tech":0,"politics":0,"structure":0}, "confidence": "high|medium|low", "cue": "학생 단서"}
```

**마진 게이트** (confidence보다 우선):
- `top_score < 0.55` → meta_ask
- `top_score - second_score < 0.20` → meta_ask
- **모호 입력 휴리스틱**: `그냥`/`복잡`/`모르겠` 등 + 층위 단서 없음 → `vague_no_layer_cue` → meta_ask
- meta_ask는 안전망이지 목표가 아님

**counter_map** (랜덤 제거):
```json
{"tech": "structure", "politics": "tech", "structure": "politics"}
```

**Phase 2a 마감 기준 (#5-7)**:
- structure 단독 = 실패
- meta_ask ≤ 1건
- tech/politics 명확 분류 ≥ 2건

### 4.4 안전장치: confidence low 시 메타 질문

> **핵심 원칙**: 분류가 애매하면 추측하지 말고, 학생에게 직접 고르게 한다.

```php
function buildHammerForConvergent(string $studentReason, array $hints, $llm): array {
    $axes = $hints['axes'] ?? [];
    $detection = detectStudentAxis($studentReason, $axes, $llm);
    
    if ($detection['confidence'] === 'low' || $detection['axis'] === null) {
        // 안전장치: 메타 질문으로 학생이 직접 축 선택
        return [
            'mode' => 'meta_ask',
            'message' => buildMetaAskMessage($axes, $hints['shared_conclusion']),
        ];
    }
    
    // confidence high/medium → 정상 contrast_prompt 흐름
    $studentAxis = $detection['axis'];
    $counterAxis = pickCounterAxis($axes, $studentAxis);
    return [
        'mode' => 'contrast',
        'student_axis' => $studentAxis,
        'counter_axis' => $counterAxis,
        'message' => buildContrastMessage($studentAxis, $counterAxis),
    ];
}

function buildMetaAskMessage(array $axes, string $sharedConclusion): string {
    $axisLabels = array_map(fn($ax) => $ax['axis_label'], $axes);
    $options = implode(', ', $axisLabels);
    
    return <<<MSG
우리 둘 다 "{$sharedConclusion}"라고 봤어. 
그런데 '왜' 그런지에 대해선 전문가들 사이에서도 이유가 달라.

네가 방금 쓴 근거는 다음 중 뭐에 가장 가까워?
- {$axisLabels[0]}
- {$axisLabels[1]}
- {$axisLabels[2]}

하나만 골라봐. 고른 다음에 그게 왜 그런지 더 얘기해보자.
MSG;
}
```

**이 안전장치의 장점:**
- 틀릴 수가 없음 (학생이 직접 선택)
- 오히려 교육적으로 더 좋을 수 있음 (학생이 스스로 분류)
- 잘못 분류해서 엉뚱한 pivot_question 던지는 것보다 안전

---

## 5. EduQuestFactory 수정 범위

### 5.1 신규 LLM 단계: 근거 축 추출

```php
private function extractConvergentAxes(array $articles): ?array
{
    // 입력: judgement_records에서 가져온 articles (thesis 포함)
    // 출력: {mode, shared_conclusion, axes[]}
    
    $prompt = <<<PROMPT
이 기사들의 thesis(why_important)를 분석해.
1. 공통 결론이 있는가? 있으면 한 문장으로.
2. 공통 결론에 도달하는 "근거의 축"이 다른가?
   (예: 기술적 이유 / 정치적 이유 / 구조적 이유)
3. 축이 2개 이상이면 수렴형, 아니면 대립형.

수렴형이면:
{
  "mode": "convergent",
  "shared_conclusion": "공동 결론 한 문장",
  "axes": [
    {"axis_id": "...", "axis_label": "...", "thesis": "...", "news_id": N}
  ]
}

축이 뚜렷하지 않거나 억지로 만들어야 하면:
{"mode": "unclear", "reason": "왜 분류가 어려운지"}

대립형이면:
{"mode": "adversarial", "pro_thesis": "...", "con_thesis": "..."}
PROMPT;
}
```

**안전장치**: `mode: "unclear"` 반환 시 퀘스트 생성 스킵 (억지 축 방지).

### 5.2 buildDraftQuest 수정

```php
private function buildDraftQuest(string $arcCode, array $articles): ?array
{
    // ... 기존 코드 ...
    
    // 신규: 근거 축 추출 시도
    $convergentData = $this->extractConvergentAxes($articles);
    
    if ($convergentData !== null && ($convergentData['mode'] ?? '') === 'convergent') {
        // 수렴형 퀘스트 생성
        return $this->buildConvergentQuest($arcCode, $articles, $convergentData);
    }
    
    // 기존 대립형 로직 유지
    return $this->buildAdversarialQuest($arcCode, $articles);
}
```

---

## 6. 기존 코드 처리

### 6.1 findMixUpPairs

| 옵션 | 설명 |
|------|------|
| **A. 유지 (deprecated)** | 주석으로 deprecated 표시, 새 코드에서 미호출 |
| **B. 삭제** | chat.php hammer에서 호출 제거 후 함수 삭제 |
| **C. 폴백 유지** | 수렴형 데이터 없는 구 퀘스트용 fallback |

**권장**: **A** — deprecated 유지. 기존 퀘스트가 아직 hammer_hints.mode 없이 pro/con 구조이므로, 레거시 폴백 필요.

### 6.2 chat.php hammer 단계

```php
// 현재:
if (eduMixupRagEnabled()) {
    $pairs = $rag->findMixUpPairs($topic, '', 3);
    $mixupContext = $rag->formatMixUpContext($pairs);
}

// 변경 후:
$hints = $quest['hammer_hints'] ?? [];
$mode = $hints['mode'] ?? 'adversarial';

if ($mode === 'convergent') {
    // 신규 수렴형 로직 (섹션 4 참조)
    $mixupContext = buildConvergentHammerContext($studentReason, $hints);
} elseif (eduMixupRagEnabled()) {
    // 레거시 폴백 (deprecated)
    $pairs = $rag->findMixUpPairs($topic, '', 3);
    $mixupContext = $rag->formatMixUpContext($pairs);
} else {
    $mixupContext = '';
}
```

---

## 7. 기존 퀘스트 영향

### 7.1 새 퀘스트 vs 기존 퀘스트

| 구분 | 동작 |
|------|------|
| **새 퀘스트** (수렴형) | `hammer_hints.mode = "convergent"` → 신규 로직 |
| **기존 퀘스트** | `hammer_hints.mode` 없음 → 기존 대립형 로직 유지 |

**결론**: 기존 퀘스트 **재생성 불필요**. 새 퀘스트만 새 방식. 점진적 전환.

### 7.2 마이그레이션 없음

- 스키마 변경 없음 (hammer_hints는 이미 jsonb)
- 기존 데이터 변경 없음
- 새 필드(mode, axes)는 새 퀘스트에만 추가

---

## 8. 라이브 무손상 보장

| 체크포인트 | 보장 방법 |
|-----------|----------|
| MySQL news | READ만, 수정 금지 |
| analysis_embeddings | READ만, 수정 금지 |
| judgement_records | READ만, 수정 금지 |
| intelligence_embeddings | **미사용** (findMixUpPairs deprecated) |
| chat.php hammer | `mode` 분기로 기존 동작 100% 유지 |
| 기존 퀘스트 | hammer_hints.mode 없으면 기존 로직 |

---

## 9. 파일 변경 목록 (예정)

| 파일 | 변경 유형 | 내용 |
|------|----------|------|
| `EduQuestFactory.php` | 수정 | `extractConvergentAxes`, `buildConvergentQuest` 추가 |
| `Hammer.php` | 수정 | 수렴형 모드 분기 추가 |
| `chat.php` | 수정 (마지막) | hammer 단계 mixup 소스 교체 |
| `EduRagService.php` | 미변경 | findMixUpPairs deprecated 주석만 |
| 스키마 | 미변경 | 없음 |

---

## 10. 다음 단계

1. **이 설계안 검토** — 이란 클러스터 예시가 wow인지 확인
2. **Phase 2 구현** — 설계 확정 후
3. **수동 테스트** — 이란 퀘스트 1개 손으로 DB에 넣고 hammer 테스트
4. **EduQuestFactory 자동화** — 수동 테스트 성공 후

---

## 부록 A: Hammer 프롬프트 예시 (수렴형 + contrast_prompt)

**시나리오**: 학생이 "미국이 아무리 정밀폭격을 해도 이란은 안 굴복할 거다"라고 씀.  
**감지된 축**: `tech` (무기/기술)  
**던질 축**: `structure` (전쟁의 구조)

```
너는 토론 코치야. 학생이 "이란 전쟁은 안 끝난다"고 썼어.

학생의 근거: "미국이 아무리 정밀폭격을 해도 이란은 안 굴복할 거다"

학생은 **기술적 한계** 관점에서 접근했어 — 무기의 성능이 결과를 바꾸지 못한다는 거지.

그런데 기데온 로즈는 같은 결론을 완전히 다른 시각으로 봐:
**전쟁이라는 것 자체의 구조** — 상대가 누구든, 무기가 뭐든, 전쟁은 원래 깔끔히 안 끝난다.

이건 무기 탓이 아니야. 로즈는 무기 얘기를 아예 안 해. 기술이 발전해도 마찬가지라고 봐.

학생에게 물어봐:
"네 근거는 '이란이 특별해서'야, 아니면 '전쟁이라는 게 원래 그래서'야?"

존중하는 말투로, 2-3문장으로. 학생이 자기 근거의 층위를 의식하게 해.
```

**기대 출력 (Hammer가 생성):**

> "너는 '아무리 폭격해도 안 굴복한다'고 썼는데, 그건 이란이 특별히 강하다는 얘기야. 그런데 로즈는 다르게 봐 — 이란이 아니라 *어떤 전쟁이든* 원래 깔끔하게 안 끝난다는 거지. 너의 '안 굴복한다'는 이란이 강해서야, 아니면 전쟁이라는 게 원래 그런 거야?"

---

## 부록 B: pivot_question이 만드는 차이

| 기존 (가정 뒤집기) | 개선 (양자택일) |
|-------------------|----------------|
| "기술이 더 발전하면 달라질까?" | "네 근거는 무기 한계야, 이란이 특별해서야?" |
| 학생: "음... 달라질 수도 있겠네요" (추측) | 학생: "제가 말한 건 이란이 강해서인데... 그게 전쟁 자체의 문제랑 다른 거구나" (깨달음) |
| 학생 답변과 무관하게 던질 수 있음 | 학생 답변을 직접 거울처럼 비춤 |
| 도망 가능 | 도망 불가능 — 자기 근거를 분류해야 함 |
