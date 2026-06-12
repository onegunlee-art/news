# Partner RAG API 전수 조사 보고서

> **조사일**: 2026-06-09  
> **대상**: `public/api/partner/rag-query.php` v2 (`816f72b`)  
> **외부 연동**: [strategy-pipeline](https://github.com/onegunlee-art/strategy-pipeline) (Vercel)

---

## Executive Summary

| 질문 | 판정 |
|------|------|
| RAG API 연동이 잘 돌아가는가? | **예** — Vercel `geo/analyze`에서 `include_analysis=true` 호출 성공, insight·analysis·clusters 수신 |
| 내부 서비스에 **데이터 피해**가 있는가? | **없음** — Partner 경로에 `news` 쓰기 없음 |
| 내부 서비스에 **성능·비용 피해** 가능성? | **있음(잠재)** — OpenAI/Supabase/FPM 공유, rate limit 없음 |
| 1% 위험 기준 충족? | **데이터 무결성·코어 기능**: 충족 / **비용·부하 격리**: 미충족 |

**한 줄 결론**: Partner RAG 연동은 **기능적으로 정상**이며 코어 검색·헬스체크에 **즉각적 피해 없음**. 다만 **동일 인프라 공유**와 **rate limit 부재**로 키 유출·대량 `include_analysis` 시 비용·지연 리스크는 남아 있음.

---

## 1. 런타임 검증 결과

### 1-1. Partner API (프로덕션)

| 테스트 | URL | 결과 | 비고 |
|--------|-----|------|------|
| 키 없음 | `POST /api/partner/rag-query.php` | **401** `Invalid partner key` | 인증 동작 |
| 잘못된 키 | 동일 + `X-Partner-Key: invalid` | **401** | `PARTNER_API_KEY` 서버 설정됨 (503 아님) |
| v2 직접 curl (키 보유) | — | **미실행** | 로컬에 `PARTNER_API_KEY` 없음. EC2 `$KEY`로 재검증 권장 |

### 1-2. 외부 연동 간접 검증 (Vercel)

`POST https://strategy-pipeline.vercel.app/api/geo/analyze` `{"topic":"이란"}` (37초)

| 필드 | 결과 |
|------|------|
| `gistInsight` | 수신 (1문장 인사이트) |
| `gistAnalysis` | 수신 (`full_text` 장문 분석, v2 structured 내용 포함) |
| `gistClusters` | 2개 클러스터 수신 |
| `gistArticles` | **빈 배열** — 클라이언트 `similarity ≥ 0.35` 필터 때문 (원본 유사도 0.27~0.31) |
| `driverMeta` / `geoProb` | Gemini 정상 후속 처리 |

→ **Partner API + `include_analysis=true` + Vercel env(`GIST_RAG_URL`, `GIST_PARTNER_KEY`) 정상 동작 확인.**

### 1-3. 코어 서비스 교차 확인 (Partner 트래픽 직후)

| 엔드포인트 | HTTP | `success` |
|------------|------|-----------|
| `GET /api/health` | 200 | true |
| `GET /` (홈) | 200 | — |
| `POST /api/search.php` (query=이란) | 200 | true, results 3건 |

→ Partner·Vercel 호출 전후 **코어 검색 정상**. 즉각적 성능 저하·장애 없음.

### 1-4. 배포 상태

| 항목 | 값 |
|------|-----|
| 커밋 | `816f72b` |
| GitHub Actions | [#825 success](https://github.com/onegunlee-art/news/actions/runs/27177381093) |
| PHP | 8.2.30 |

---

## 2. 코드·아키텍처 감사

### 2-1. 요청 플로우

```
Client(Vercel) → X-Partner-Key → rag-query.php
  → OpenAI embedding (1회)
  → Supabase RPC search_articles_by_embedding (1회)
  → MySQL SELECT news (published 필터)
  → [결과≥2] GPT gpt-4o-mini 클러스터 (1회)
  → [include_analysis] generatePartnerAnalysis gpt-5.2 (1~2회) + Supabase SELECT×10
```

### 2-2. DB 영향

| SQL | 쓰기? | Partner |
|-----|-------|---------|
| `SELECT news ...` | 읽기 | O |
| `SHOW COLUMNS FROM news` | 읽기 | O (워커당 1회 캐시) |
| `INSERT api_usage_logs` | **쓰기** | OpenAI 호출 시 간접 (usage_log.php) |
| `news` INSERT/UPDATE/DELETE | — | **없음** |

**데이터 오염·삭제 위험: 없음.**

### 2-3. 코어 search.php vs Partner

| | search.php | partner/rag-query.php |
|--|------------|----------------------|
| 인증 | 없음 | `X-Partner-Key` |
| published 필터 | 없음 | 있음 |
| source 필드 | 없음 | 있음 |
| 종합 분석 | 별도 SSE | 동기 JSON v2 |
| timeout | 120s | 180s |
| rate limit | 없음 | 없음 |

**참고**: 공개 `search.php`가 동일 벡터·OpenAI 인프라를 **키 없이** 사용 가능 → abuse 문턱은 Partner보다 낮음.

### 2-4. 공유 인프라 blast radius

| 리소스 | 공유 대상 | Partner abuse 시 영향 |
|--------|-----------|----------------------|
| OpenAI API Key | search, admin, TTS, cron, partner | 계정 quota/RPM, 비용 |
| Supabase pgvector | search, article-chat, partner | RPC 지연 |
| PHP-FPM 8.2 | 전체 API | 워커 고갈 → 502/504 |
| MySQL | 전체 | 연결·읽기 부하, usage_logs 쓰기 |

**요청당 최대 외부 호출** (`include_analysis=true`): OpenAI 2~4회, Supabase 11회, FPM 점유 최대 180초.

---

## 3. 외부 연동 (strategy-pipeline) 상태

| 항목 | 상태 |
|------|------|
| 저장소 | [onegunlee-art/strategy-pipeline](https://github.com/onegunlee-art/strategy-pipeline) (public) |
| 클라이언트 | `dashboard/lib/gistRag.ts` |
| env | `GIST_RAG_URL`, `GIST_PARTNER_KEY` (Vercel secrets, 코드에 미포함) |
| 호출 패턴 | `geo/analyze` → `queryGistRag({ include_analysis: true })` |
| 타임아웃 | 클라이언트 60초 (`AbortSignal.timeout`) |
| 유사도 필터 | `SIMILARITY_THRESHOLD = 0.35` (서버 결과 추가 필터) |
| PR #59 | 머지 — `analysis.full_text` Step2·시그널 카드 주 컨텍스트 |
| PR #63 | 머지 — 중복 Gist 호출 제거, `geo/start` 최적화 |
| v2 슬롯 | `formatGistContextForPrompt`에 alignment/conflict 분기 준비 |

### 알려진 연동 이슈 (기능 장애 아님)

- **gistArticles 빈 배열**: 쿼리「이란」유사도 0.27~0.31 < 0.35 → 기사 목록 UI 비어 있을 수 있음. insight·analysis·clusters는 정상.
- **권장**: threshold 0.30 조정 또는 limit 상향 후 재검토.

---

## 4. 모니터링·비용 스팟 체크

### api_usage_logs (오늘, 조사 시점)

| endpoint | requests | input_tokens | output_tokens |
|----------|----------|--------------|---------------|
| embeddings | 21 | 163 | 0 |
| chat | 54 | 170,703 | 47,819 |

조사 중 Vercel `geo/analyze` 1회 + 로컬 search 2회 후 embeddings +2, chat +2 — **정상 범위 증가**.

### 제한 사항

- `api_usage_logs`에 **partner vs core 구분 metadata 없음**
- OpenAI Usage API: Admin 키 없어 403 (대시보드 직접 확인 필요)
- Partner 전용 access/audit log 없음 (`error_log` 실패만)

### 추가 발견 (보안)

- `/api/admin/usage-dashboard.php`가 **인증 없이** 프로덕션에서 JSON 노출 — Partner 감사와 별개로 **Admin API 보호 검토 권장**

---

## 5. 위험 등급

| 등급 | 시나리오 | 현재 완화 |
|------|----------|-----------|
| **Critical** | 키 유출 + `include_analysis` 대량 호출 | API key만 (rate limit 없음) |
| **High** | OpenAI 429 연쇄 → 전 API 지연 | 재시도 sleep만 |
| **Medium** | usage_logs·Supabase 부하 | 없음 |
| **Low** | 잘못된 키 401, CORS 브라우저 abuse | hash_equals, CORS 헤더 제한 |

---

## 6. 권고 조치 (우선순위)

| # | 조치 | 효과 | 난이도 |
|---|------|------|--------|
| 1 | Partner **rate limit** (nginx `limit_req` 또는 PHP) | 비용·FPM 고갈 방지 | 낮음 |
| 2 | `include_analysis` **일일 quota** | 고비용 호출 제한 | 낮음 |
| 3 | `log_api_usage` metadata `{source:'partner'}` | 비용 분리·감사 | 낮음 |
| 4 | `query` 길이 cap (예: 500자) | embedding 비용 | 낮음 |
| 5 | deploy smoke (401 + secret 200) | 회귀 방지 | 낮음 |
| 6 | usage-dashboard **Admin 인증** | 정보 노출 차단 | 낮음 |
| 7 | (선택) OpenAI 키 분리 | quota 격리 | 중간 |
| 8 | Vercel `SIMILARITY_THRESHOLD` 0.30 검토 | 기사 목록 노출 개선 | 외부 |

---

## 7. EC2 추가 검증 (선택)

로컬에 `PARTNER_API_KEY` 없어 v2 필드 직접 확인은 미완. EC2에서:

```bash
curl -s -X POST "https://www.thegist.co.kr/api/partner/rag-query.php" \
  -H "Content-Type: application/json" -H "X-Partner-Key: $KEY" \
  -d '{"query":"이란","limit":3}' \
  | jq '.success, .search.results[0].source, .meta.analysis_format'

curl -s -X POST "https://www.thegist.co.kr/api/partner/rag-query.php" \
  -H "Content-Type: application/json" -H "X-Partner-Key: $KEY" \
  -d '{"query":"이란","limit":5,"include_analysis":true}' \
  | jq '.meta.analysis_format, .analysis | keys'
```

기대: `source` 문자열, `analysis_format: "structured_v2"`, analysis 5키.

---

## 부록: 조사 범위 파일

- `public/api/partner/rag-query.php`
- `public/api/lib/partnerAuth.php`
- `public/api/search.php`
- `src/backend/Services/SearchAnalysisService.php`
- `public/api/lib/usage_log.php`
- `strategy-pipeline/dashboard/lib/gistRag.ts`
- `strategy-pipeline/dashboard/app/api/geo/analyze/route.ts`
