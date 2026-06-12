# GIST EDU 파일럿 마스터 플랜 v1.0
## edu.thegist.co.kr — Cursor 단계별 실행 문서

> **버전:** Pilot v1.0 · 2026-06-12  
> **범위:** 개인 가입 학생 + 핵심 퀘스트 플로우 + 공유 카드  
> **제외:** 학원/반, 부모 리포트, 반 대항전 (Phase 2로 이관)  
> **관련:** [`GIST_EDU_PLAYBOOK.md`](GIST_EDU_PLAYBOOK.md) · [`GIST_EDU_DESIGN_SYSTEM.md`](GIST_EDU_DESIGN_SYSTEM.md)

---

# 제0장. 절대 원칙 (모든 Phase에 적용)

## 0.1 the gist 서비스 안정성 — 7줄 계약

```
1. EDU는 news·users·analysis_embeddings 테이블에 INSERT/UPDATE/DELETE 절대 금지
2. EDU LLM은 Admin generate·publish·임베딩 생성 훅 호출 금지
3. 코어 읽기 API만 허용: news/detail.php(published만), partner/rag-query.php
4. edu_* 마이그레이션만 EDU Phase에서 실행 (코어 스키마 무변경)
5. 모든 EDU LLM 호출에 source=edu 태깅 + 일일 캡
6. EDU 전용 OpenAI/Anthropic API 키 분리 (코어 quota 보호)
7. nginx에서 edu 서브도메인 독립 — 장애 시 edu만 끄고 www 무영향
```

## 0.2 파일럿 범위 (확정)

| 항목 | 포함 ✅ | 제외 ❌ (Phase 2) |
|------|---------|------------------|
| **퀘스트** | 주 3회 드랍 (수,토,일) | 매일 드랍 |
| **세션 파이프라인** | A1~A5 전체 | - |
| **레벨/티어** | Bronze → Gold 3단계 | Platinum 이상 |
| **전국 통계** | 찬반 % only (숫자 비노출) | 참여자 수, 실시간 카운트 |
| **공유 카드** | 핵심 기능 | - |
| **계정** | 개인 가입 (카카오) | 학원 발급 ID |
| **학원/반** | - | **전체 제외** |
| **반 대항전** | - | **제외** |
| **부모 리포트** | - | **제외** |

## 0.3 서비스 정체성

| 항목 | 확정 내용 |
|------|----------|
| 원천 | **the gist 기사 300개+ 풀** + NYT/Guardian API — 충돌점 2개+ (best 3개) |
| 글 완성 가이드 | **지스트 기사 SCQA 구조**가 학생 글의 뼈대 |
| 기사 타이밍 | Turn 0 쟁점만 → Turn 2 요약 → 완료 후 전문(리워드) |
| 드랍 시각 | **주 3회 오후 4시** (수,토,일) |
| 시그니처 | "생각이 바뀌었다 ⚡" 공유 카드 |
| 디바이스 | 모바일 전용 v1 |

---

# 제1장. 에이전트 아키텍처 (파일럿 버전)

## 1.1 에이전트 호출 구조도

```
                        ┌─────────────────────────────┐
                        │   the gist CORE (읽기 전용)   │
                        │  news DB · Supabase RAG ·    │
                        │  SCQA 분석 · 클러스터        │
                        └──────────┬──────────────────┘
                                   │ SELECT only
                                   ▼
┌──────────────────────────────────────────────────────────────┐
│                      EDU BFF (/api/edu/*)                     │
│                                                                │
│  [배치: 주 3회 새벽 3시 — 수,토,일]                            │
│  A0. Quest Curator ──── 기사 풀 스캔 → 충돌점 2개+ 검출        │
│       │                  → 퀘스트 후보 생성 → 운영자 승인       │
│       ▼                                                        │
│  [실시간: 학생 세션]                                            │
│  A1. Socratic Coach ─── 입장 질문 → 이유 질문 (Turn 1-2)       │
│       │                                                        │
│  A2. Stance Scorer ──── RAG 클러스터 중 학생 입장에            │
│       │                  가장 반대되는 관점 선택                │
│       ▼                                                        │
│  A3. Hammer ─────────── 학생용 언어로 반론 생성 (Turn 3)       │
│       │                                                        │
│  A4. Reflection ─────── 3줄 정리 (학생 확인용)                 │
│       │                                                        │
│  A5. Writing Builder ── 지스트 기사 SCQA 구조를 가이드로        │
│       │                  학생 답변 → 5문장 글 구성              │
│       ▼                                                        │
│  [실시간: 완료 시]                                              │
│  A6. Share Card ─────── 공유 카드 데이터 조립 (LLM 아님)        │
│                                                                │
│  ❌ A7. Parent Narrator ── Phase 2로 이관                       │
└──────────────────────────────────────────────────────────────┘
                                   │ INSERT only
                                   ▼
                        ┌─────────────────────────────┐
                        │     edu_* DB (학생 데이터)    │
                        └─────────────────────────────┘
```

## 1.2 LLM 선택

| 에이전트 | 모델 | 이유 |
|----------|------|------|
| A0 Quest Curator | **Claude Sonnet 4.5** | 충돌점 검출 분석력. 주 3회라 비용 무관 |
| A1 Socratic Coach | **Claude Sonnet 4.5** | 학생 대화 핵심. 한국어 자연스러움 |
| A2 Stance Scorer | **Claude Haiku 4.5** | 분류 작업. 빠르고 저렴 |
| A3 Hammer | **Claude Sonnet 4.5** | 반론 설득력 = 제품의 심장 |
| A4 Reflection | **Claude Haiku 4.5** | 요약 작업 |
| A5 Writing Builder | **Claude Sonnet 4.5** | SCQA 구조화 핵심 가치 |

> **비용 추정**: 세션당 ~$0.05. 주 3회 × 100명 = 월 1,200세션 ≈ 월 $60~80

---

# 제2장. DB 스키마 (파일럿 축소 버전)

## 2.1 테이블 맵 (12개)

```
[퀘스트 공급]                [학생 활동]                  [성장/게임화]
edu_daily_quests            edu_users                    edu_tier_history
edu_quest_articles          edu_sessions                 edu_action_log
                            edu_thinking_logs ★          edu_badges
                            edu_hypothesis_versions ★
                            edu_evidence_logs            [발간물]
                            edu_counter_logs             edu_writing_versions
                            edu_reflections              edu_share_cards

[전국 — 파일럿 축소]
edu_national_stats (% only, 숫자 비노출)

❌ edu_classes              (Phase 2)
❌ edu_class_members        (Phase 2)
❌ edu_parent_reports       (Phase 2)
❌ edu_writing_feedback     (Phase 2)
```

## 2.2 마이그레이션 SQL

```sql
-- =====================================================
-- migration: edu_pilot_001.sql
-- 파일럿 버전 — 학원/반/부모 리포트 제외
-- =====================================================

-- ── 계정 (단순화) ──
CREATE TABLE edu_users (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role            ENUM('student') NOT NULL DEFAULT 'student',
  display_name    VARCHAR(50) NOT NULL,
  kakao_id        VARCHAR(60) UNIQUE,                -- 카카오 로그인
  grade_band      ENUM('middle','high') NOT NULL DEFAULT 'middle',
  tier            ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze',
  streak_current  INT NOT NULL DEFAULT 0,
  streak_best     INT NOT NULL DEFAULT 0,
  last_quest_date DATE NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tier (tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 퀘스트 공급 ──
CREATE TABLE edu_daily_quests (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quest_no          INT NOT NULL UNIQUE,
  quest_date        DATE NOT NULL,
  grade_band        ENUM('middle','high','both') NOT NULL DEFAULT 'both',
  issue_question    VARCHAR(200) NOT NULL,
  stance_pro_label  VARCHAR(60) NOT NULL,
  stance_con_label  VARCHAR(60) NOT NULL,
  conflict_summary  TEXT NOT NULL,
  conflict_count    TINYINT NOT NULL,              -- 2개 이상만 승인
  status            ENUM('candidate','approved','live','archived') NOT NULL DEFAULT 'candidate',
  approved_by       VARCHAR(50) NULL,              -- 운영자 승인
  drops_at          DATETIME NOT NULL,             -- 수,토,일 16:00 KST
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_date_band (quest_date, grade_band),
  INDEX idx_status_date (status, quest_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE edu_quest_articles (
  quest_id      BIGINT UNSIGNED NOT NULL,
  news_id       BIGINT UNSIGNED NOT NULL,
  article_role  ENUM('primary','counter','context') NOT NULL,
  summary_3line TEXT NULL,
  PRIMARY KEY (quest_id, news_id),
  FOREIGN KEY (quest_id) REFERENCES edu_daily_quests(id) ON DELETE CASCADE,
  INDEX idx_news (news_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 학생 세션 ──
CREATE TABLE edu_sessions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id    BIGINT UNSIGNED NOT NULL,
  quest_id      BIGINT UNSIGNED NOT NULL,
  status        ENUM('started','in_progress','completed','abandoned') NOT NULL DEFAULT 'started',
  current_turn  TINYINT NOT NULL DEFAULT 0,
  started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at  DATETIME NULL,
  duration_sec  INT NULL,
  UNIQUE KEY uk_student_quest (student_id, quest_id),
  FOREIGN KEY (student_id) REFERENCES edu_users(id) ON DELETE CASCADE,
  FOREIGN KEY (quest_id) REFERENCES edu_daily_quests(id),
  INDEX idx_quest_status (quest_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 사고 로그 ──
CREATE TABLE edu_thinking_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  BIGINT UNSIGNED NOT NULL,
  turn        TINYINT NOT NULL,
  speaker     ENUM('ai','student') NOT NULL,
  agent       VARCHAR(30) NULL,
  content     TEXT NOT NULL,
  created_at  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  FOREIGN KEY (session_id) REFERENCES edu_sessions(id) ON DELETE CASCADE,
  INDEX idx_session_turn (session_id, turn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 가설 버전 (★ 핵심) ──
CREATE TABLE edu_hypothesis_versions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  BIGINT UNSIGNED NOT NULL,
  version     TINYINT NOT NULL,
  stance      ENUM('pro','con') NOT NULL,
  reasoning   TEXT NULL,
  is_revised  BOOLEAN NOT NULL DEFAULT FALSE,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_session_version (session_id, version),
  FOREIGN KEY (session_id) REFERENCES edu_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 근거/반론 로그 ──
CREATE TABLE edu_evidence_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  BIGINT UNSIGNED NOT NULL,
  source_type ENUM('article_summary','student_own','hammer_counter') NOT NULL,
  news_id     BIGINT UNSIGNED NULL,
  content     TEXT NOT NULL,
  char_count  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES edu_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE edu_counter_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id      BIGINT UNSIGNED NOT NULL,
  cluster_source  VARCHAR(100) NULL,
  counter_text    TEXT NOT NULL,
  student_reply   TEXT NULL,
  reply_char_count INT NOT NULL DEFAULT 0,
  accepted        BOOLEAN NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES edu_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE edu_reflections (
  session_id  BIGINT UNSIGNED PRIMARY KEY,
  summary_3   TEXT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES edu_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 글 ──
CREATE TABLE edu_writing_versions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  BIGINT UNSIGNED NOT NULL,
  version     TINYINT NOT NULL DEFAULT 1,
  body        TEXT NOT NULL,
  scqa_map    JSON NULL,
  word_count  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_session_ver (session_id, version),
  FOREIGN KEY (session_id) REFERENCES edu_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 게임화 (파일럿: Bronze→Gold 3단계만) ──
CREATE TABLE edu_action_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  BIGINT UNSIGNED NOT NULL,
  session_id  BIGINT UNSIGNED NULL,
  action      ENUM('quest_complete','revision','evidence_cited','counter_deep_reply',
                   'streak_day','national_participate') NOT NULL,
  points      INT NOT NULL,
  multiplier  DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  metadata    JSON NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES edu_users(id) ON DELETE CASCADE,
  INDEX idx_student_action (student_id, action),
  INDEX idx_student_date (student_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE edu_tier_history (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  BIGINT UNSIGNED NOT NULL,
  from_tier   VARCHAR(20) NOT NULL,
  to_tier     VARCHAR(20) NOT NULL,
  reason_json JSON NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES edu_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE edu_badges (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  BIGINT UNSIGNED NOT NULL,
  badge_key   VARCHAR(40) NOT NULL,
  earned_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_student_badge (student_id, badge_key),
  FOREIGN KEY (student_id) REFERENCES edu_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 전국 집계 (파일럿: % only, 숫자 비노출) ──
CREATE TABLE edu_national_stats (
  quest_id        BIGINT UNSIGNED PRIMARY KEY,
  total_count     INT NOT NULL DEFAULT 0,          -- 내부 집계용 (UI 비노출)
  pro_count       INT NOT NULL DEFAULT 0,
  con_count       INT NOT NULL DEFAULT 0,
  revision_count  INT NOT NULL DEFAULT 0,
  -- 파생 필드 (% 계산용)
  pro_pct         DECIMAL(5,2) NULL,               -- UI 노출용
  con_pct         DECIMAL(5,2) NULL,
  revision_pct    DECIMAL(5,2) NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (quest_id) REFERENCES edu_daily_quests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 공유 카드 ──
CREATE TABLE edu_share_cards (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  BIGINT UNSIGNED NOT NULL UNIQUE,
  card_data   JSON NOT NULL,
  image_url   VARCHAR(255) NULL,
  share_count INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES edu_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

# 제3장. 레벨 알고리즘 (파일럿 3단계)

## 3.1 채택 알고리즘 (Bronze → Silver → Gold)

```
승급 조건 = (내부 점수 ≥ 티어 임계값) AND (행동 게이트 전부 충족)

내부 점수 (학생에게 숫자 비노출):
  quest_complete      = 10
  revision (v1→v2)    = 20   ← 반론 투입 이후 수정만 인정
  evidence_cited      = 5    ← 30자 이상만
  counter_deep_reply  = 15   ← 50자 이상 재답변
  streak multiplier   = 7일+ ×1.1

파일럿 티어 게이트:
  bronze → silver : 250점  + 완료 5회
  silver → gold   : 700점  + 입장수정 5회
```

## 3.2 UI 표시 규칙

- 점수 숫자 **절대 비노출**
- "탐구 기록" + 행동 횟수만: "수정 3회 더 하면 Gold!"
- XP 대신 "탐구 포인트" 용어 검토 (PLAYBOOK과 정합)

---

# 제4장. 핵심 파이프라인 상세

## 4.1 A0 Quest Curator

**실행**: cron 수,토,일 03:00 KST. 운영자가 오전 중 승인 → 16:00 자동 드랍.

```
입력:  the gist 기사 풀 (300개+) + NYT/Guardian
       + 각 기사의 SCQA 분석 결과
처리:
  1. partner/rag-query로 기사별 관련 클러스터 수집
  2. 충돌점 2개+ (best 3개) 검출
  3. 후보별 퀘스트 질문 생성
  4. edu_daily_quests에 status='candidate' INSERT
출력:  운영자 승인 대기 (Slack/어드민)
```

## 4.2 세션 파이프라인 (Turn 0→5)

```
Turn 0  [학생] 드랍 화면에서 입장 선택 (pro/con)
        → edu_sessions INSERT, edu_hypothesis_versions v1 INSERT
        → 전국 분포는 아직 비노출 (호기심 갭)

Turn 1  [A1 Socratic] "왜 그렇게 생각하나요?"
        [학생] 이유 답변
        → edu_thinking_logs INSERT

Turn 2  [시스템] 기사 3줄 요약 카드 자동 노출
        [A1 Socratic] 근거 보강 질문 1회
        [학생] 근거 답변 → edu_evidence_logs INSERT

Turn 3  [A2 Stance Scorer] 가장 대립하는 클러스터 선택
        [A3 Hammer] 반론 생성 → edu_counter_logs INSERT

Turn 4  [학생] 재답변 (입장 유지 or 수정)
        → 수정 시 edu_hypothesis_versions v2 INSERT
        [A4 Reflection] 3줄 정리

Turn 5  [A5 Writing Builder] 5문장 글 구성
        → edu_writing_versions INSERT

완료    [TierEngine] 점수 기록 + 승급 판정
        [A6 Share Card] card_data 조립
        화면:
          1. 전국 분포 공개 (찬반 % only)
          2. "너는 소수파에서 출발" 메시지
          3. 생각 변화 카드
          4. 기사 전문 리워드 🎁
          5. 공유 버튼
```

## 4.3 전국 통계 UI 규칙 (파일럿)

```
✅ 노출:
  - "찬성 36% vs 반대 64%"
  - "입장 바꾼 학생 34%"
  - "너는 소수파(36%)에서 출발했어"

❌ 비노출:
  - "전국 12,847명이 답했어" (참여자 수)
  - "지금 1,204명 푸는 중" (실시간 카운트)
```

---

# 제5장. Cursor 실행 Phase

## Phase 0 — 인프라 (반나절)

```
□ DNS: edu A레코드 → EC2 IP
□ nginx: edu.thegist.co.kr server 블록 추가
□ certbot --nginx -d edu.thegist.co.kr
□ .env 추가:
   EDU_ANTHROPIC_API_KEY=sk-ant-...
   EDU_JWT_SECRET=...
   EDU_DAILY_LLM_CAP=1000
□ CORS: config/app.php allowed_origins에 edu 추가
□ 검증: curl https://edu.thegist.co.kr/api/edu/health
```

## Phase 1 — DB (1시간)

```
Cursor 지시:
"database/migrations/edu_pilot_001.sql 파일을 생성하라.
 내용은 [제2장 2.2 SQL 전문]. 실행 후 SHOW TABLES LIKE 'edu_%'로
 12개 테이블 확인. 코어 테이블 절대 ALTER 금지."
```

## Phase 2 — BFF 골격 + 카카오 인증 (1일)

```
Cursor 지시:
"public/api/edu/ 디렉토리에 다음을 생성하라:
 1. _bootstrap.php — DB연결, EDU JWT, edu_llm_call() 래퍼
 2. auth_kakao.php — 카카오 로그인 콜백 → edu_users
 3. health.php — 헬스체크
 4. _llm.php — Anthropic Messages API 클라이언트
 보안: news 테이블 접근은 SELECT + published WHERE 절 강제."
```

## Phase 3 — Quest Curator A0 (1일)

```
Cursor 지시:
"1. cron/edu_quest_curator.php 생성
    - 주 3회 (수,토,일) 실행
    - 기사 풀에서 충돌점 2개+ 검출
    - conflict_count < 2면 자동 탈락
 2. api/edu/admin/quests.php — 승인 PATCH
 3. cron/edu_quest_drop.php — drops_at 도래한 approved → live
 crontab:
   0 3 * * 0,3,6 php cron/edu_quest_curator.php
   * * * * * php cron/edu_quest_drop.php"
```

## Phase 4 — 세션 파이프라인 A1~A5 (5일, 핵심)

```
Cursor 지시:
"api/edu/ 에 세션 엔드포인트 생성:
 1. quest_today.php   GET
 2. session_start.php POST {quest_id, stance}
 3. session_turn.php  POST {session_id, answer}
 4. session_complete.php POST
 services/edu/Agents/ 디렉토리에:
   SocraticCoach.php, StanceScorer.php, Hammer.php,
   Reflection.php, WritingBuilder.php"
```

## Phase 5 — TierEngine + 공유 카드 (2일)

```
Cursor 지시:
"1. services/edu/TierEngine.php — Bronze→Silver→Gold 3단계
 2. api/edu/share_card.php — 카드 데이터 조립
    card_data: {quest_no, question, verdict, pro_pct, con_pct, tier, streak}
 3. 전국 통계 갱신: cron/edu_national_stats.php (5분 주기)
    - % 계산만, 숫자는 UI에 비노출"
```

## Phase 6 — React UI 업데이트 (3일)

```
Cursor 지시:
"기존 src/frontend/src/pages/edu/ 수정:
 1. 전국 통계 화면 — % only 표시, 숫자 제거
 2. 완료 화면 — 공유 카드 추가
 3. 호기심 갭 — 답하기 전 분포 숨김, 답 후 공개
 4. '너는 소수파에서 출발' 메시지
 ❌ 반 대항전 섹션 완전 제거"
```

---

# 제6장. 검증 체크리스트

## 파일럿 출시 전 (Phase 6 완료 후)

```
□ 퀘스트 생성: 기사 풀에서 충돌점 2개+ 검출 정상 작동
□ 세션 플로우: Turn 0→5 전체 완주 가능
□ 입장 수정: v1→v2 기록 및 revision 점수 정상 부여
□ 전국 통계: % only 표시, 참여자 수 비노출 확인
□ 공유 카드: 데이터 조립 + 이미지 생성 (Phase 7에서 PNG 렌더링)
□ 티어 승급: Bronze→Silver→Gold 게이트 동작
□ 코어 무간섭: edu 계정으로 news INSERT 시도 → 실패해야 정상
□ www 응답속도: edu 부하 중에도 변화 없어야 함
```

---

# 제7장. Phase 2 이관 항목 (파일럿 이후)

| 항목 | 설명 |
|------|------|
| `edu_classes` | 학원/반 테이블 |
| `edu_class_members` | 반-학생 매핑 |
| `edu_parent_reports` | 부모 리포트 |
| `edu_writing_feedback` | 강사 첨삭 |
| 반 대항전 UI | 입장 수정률 대결 |
| 학원 admin | 선생님 계정 + 반 관리 |
| Platinum 이상 티어 | Diamond, Master, GIST Challenger |
| 참여자 수 표시 | "전국 12,847명" |

---

*연결 파일: [`GIST_EDU_PLAYBOOK.md`](GIST_EDU_PLAYBOOK.md) · [`GIST_EDU_DESIGN_SYSTEM.md`](GIST_EDU_DESIGN_SYSTEM.md) · [`GIST_EDU_QUEST_SEED_20.md`](GIST_EDU_QUEST_SEED_20.md)*
