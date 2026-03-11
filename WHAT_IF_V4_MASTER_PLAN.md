# TheGist What-if AI 마스터 플랜 (v4)

이 문서는 TheGist What-if 시나리오 AI의 설계·스키마·운영 원칙·구현 TODO를 한곳에 정리한 기준 문서입니다. 구현 시 이 문서를 참조합니다.

---

## 1. 개요

### 1.1 목표

- **기사 RAG**와 **What-if RAG**는 **완전히 분리**하여 운영한다.
- 뉴스 기사 → Event 추출 → Event Graph 갱신 → 시나리오 후보 생성 → RAG 기반 서술형 시나리오 출력.
- 사용자에게 **2~3개 감성 시나리오**만 제공하며, 숫자/확률은 넣지 않는다.
- Palantir 스타일 지정학 시뮬레이션의 “감성 버전”으로, 억지로 시나리오를 뽑지 않는다.

### 1.2 아키텍처 요약

```
News Article
    ↓
Event Extraction (actor, location, event_type)
    ↓
Event Graph Update (actors, relationships, locations)
    ↓
RAG Retrieval (scenario_support_documents, metadata filter)
    ↓
Scenario Tree (2~3개) → LLM Narrative
    ↓
Admin 검수 → 승인 시에만 공개·학습 자산화
```

---

## 2. 운영 원칙

| 원칙 | 내용 |
|------|------|
| **강제 생성 금지** | 증거가 부족하면 시나리오 0개 반환. 억지로 시나리오를 만들지 않는다. |
| **2~3개 시나리오** | 유저에게 감동을 주는 2~3개 수준으로 제한. |
| **숫자/확률 없음** | 확률, 수치 예측은 출력하지 않는다. |
| **한국 relevance** | TheGist 독자(한국) 관점에서 의미 있는 행위자·지역을 Seed에 반영 (예: Yellow Sea, Korean DMZ 등). |
| **Evidence Quality** | 최소 조건 충족 시에만 시나리오 생성: `support_docs >= 3`, `source_diversity >= 2`, `confidence >= 0.7`. |
| **학습 자산화** | **승인된 시나리오만** 임베딩/학습에 사용. |

---

## 3. 최종 스키마 v4 (Supabase / PostgreSQL)

What-if 전용 테이블은 **기사 RAG(critique_embeddings, analysis_embeddings, knowledge_library)** 와 별도로 두며, 필요 시 별도 스키마(예: `whatif`)로 분리 가능.

### 3.1 Data Governance Layer

- **source_registry**: 외부 데이터 소스 등록 (UCDP, SIPRI, World Bank, EIA, GDELT 등). URL, 주기, 마지막 수집 시각.
- **data_refresh_policy**: 소스별 갱신 주기 (daily / weekly / yearly).
- **ingest_runs**: 인입 실행 이력. 성공/실패, 레코드 수, 에러 메시지.
- **data_quality_flags**: 소스별 품질 이슈 플래그 (예: 결측 과다, API 변경).

### 3.2 Knowledge Graph Layer

- **actors**: `id`, `canonical_name`, `actor_type` (country | organization | person | military_group), `country_code`, `region`, `relevance_tier` (core | high | medium | low), `is_seed`, `is_active`, `metadata`, `created_at`, `updated_at`.
- **actor_aliases**: `actor_id`, `alias`, 정규화용. Entity resolution에 사용.
- **actor_relationships**: `actor_a_id`, `actor_b_id`, `relationship_type`, **relationship_scope** (military | trade | energy | diplomacy | political | mixed), `strength_score`, `confidence_score`, **tension_level** (low | medium | high | critical), `start_date`, `end_date`, `last_validated_at`, `source`, `source_ref`, `source_version`, `metadata`.

### 3.3 Strategic Geography Layer

- **strategic_locations**: `id`, `name`, `type`, `parent_location_id`, **location_importance_score** (0.0~1.0, RAG rerank용), `metadata`.
- **strategic_flow_metrics**: `location_id`, `trade_volume`, `energy_flow`, `risk_score`, `as_of_date` 등. 계층 분석용 (예: Suez → Red Sea → Middle East).

### 3.4 State Modeling Layer

- **country_state_snapshots**: GDP, inflation, population, reserves, military spending, oil/gas 등. 추가 필드: **trade_balance**, **current_account**, **fx_rate** (금융 시나리오 강화).

### 3.5 Conflict Modeling Layer

- **conflict_cases**: `external_source`, `external_id` (UCDP/ACLED/SIPRI 연동). **conflict_intensity_score** (0~1) 추가.
- **conflict_case_actors**: 분쟁별 참여 행위자.

### 3.6 Document Layer (RAG 입력)

- **document_generation_runs**: 문서 생성 배치 실행 이력.
- **scenario_support_documents**:  
  - `document_type` (country_profile | strategic_location_profile | conflict_case | scenario_support_document | …), `source_type`, `source_ref`, `title`, `content`, `domain`, `region`, `event_type`, `primary_actor_id`, `primary_location_id`, `as_of_date`, `source_version`, **content_hash**, `version`, `tags`, **embedding** vector(1536), `metadata`.  
  - **제약**: `document_type = 'scenario_support_document'` 일 때 **content 길이 ≤ 500자**.  
  - Unique: `(document_type, source_type, source_ref, version)`.

### 3.7 Event Extraction Layer

- **article_event_mentions**: `article_id`, `article_url`, `article_title`, `article_published_at`, `event_type`, `event_subtype`, `event_domain`, `trigger_text`, `summary`, `severity_tier`, **extraction_confidence**, **validation_status**, **validation_flags**, `extraction_model`, `extraction_version`, `extraction_status` (draft | reviewed | accepted | rejected), `extraction_latency_ms`, `extraction_error_*`, `metadata`.
- **article_event_actors**, **article_event_locations**: 이벤트–행위자/위치 매핑.

### 3.8 Templates / Rules / Config

- **scenario_templates**: 시나리오 프레임 (예: 3-step chain: Trigger → Regional impact → Global consequence). TheGist 10~12개.
- **scenario_reliability_rules**: `min_support_documents`, `min_distinct_actor_count`, `require_strategic_location` 등. **admin_preview** / **public** 구분 가능.
- **scenario_runtime_config**: `retrieval_budget` (top_k, rerank_limit 등).

### 3.9 Shadow Run / Observability

- **scenario_generation_runs**: `input_hash`, `retrieval_signature`, `template_set_hash`, `cache_status`, `extraction_success`, `retrieval_success`, `generation_success`, `latency_ms`, **zero_result_reason** 등 메트릭.
- **scenario_failure_events**: 실패 사유, 스택 정보.
- **scenario_metrics_daily**: 일별 집계 (성공/실패 건수, 지연 시간 등).

### 3.10 Candidates / Evidence / Final Results

- **scenario_candidates**: `support_doc_count`, `source_diversity`, **evidence_score**.
- **scenario_candidate_evidence**: `similarity_score`, `rerank_score`, `final_rank`.
- **scenario_documents**: 최종 선택된 시나리오 문서. `support_doc_count`, `source_diversity`, `evidence_score`, **approved_for_learning**.
- **scenario_embeddings**: 승인된 시나리오만 임베딩 저장 (학습/재검색용).
- **scenario_cache_invalidation_events**: 캐시 무효화 이력.

### 3.11 RPC

- **match_scenario_support_documents**: `query_embedding`, `match_count`, `filter_domain`, `filter_region`, `filter_event_type`, `filter_document_type` 등. HNSW 인덱스 사용.

### 3.12 기사 테이블 (선택)

- 기사 검색·RAG 중복 제거용 **articles** 테이블 (`id`, `url`, `title`, `body`, `source`, `published_at`, `embedding`) 을 두는 설계를 권장. 현재는 `article_event_mentions`만 있어도 동작 가능.

---

## 4. 데이터 인입

### 4.1 외부 소스 (MVP 5개)

| 소스 | 용도 | 인입 방식 | 주기 |
|------|------|-----------|------|
| **UCDP** | 분쟁 사례 (conflict_cases) | CSV 다운로드 → ETL | yearly |
| **SIPRI** | 군사비 (country_state_snapshots 등) | CSV | yearly |
| **World Bank** | GDP, 인플레이션 등 | API | daily |
| **EIA** | 에너지 (석유/가스) | API (API key 필요) | weekly |
| **GDELT** | 지정학 이벤트 | API | realtime / daily |

### 4.2 파이프라인

```
External API / CSV
    ↓
Raw table (소스별)
    ↓
Document Generator (정규화된 설명형 문서)
    ↓
content_hash 비교 → 변경분만 embedding 생성
    ↓
scenario_support_documents INSERT/UPDATE
```

- **Cron / Scheduler**: Airflow 없이 cron으로 daily/weekly/yearly 작업 실행 가능.
- **임베딩**: API 연동 시 배치로 `text-embedding-3-small` 호출. 비용·Rate limit은 배치 크기와 주기로 제어.

---

## 5. 문서 / 임베딩 구조

- **원칙**: Raw DB를 그대로 벡터화하지 않는다. **Raw → 정규화 → Document → Embedding** 순서.
- **문서 타입**: country_profile, strategic_location_profile, conflict_case, scenario_support_document 등.
- **scenario_support_document** 타입은 **500자 이하**로 고정.
- **청킹**: 긴 문서(예: conflict_case 1200~1800자)는 2~3 chunk로 나누어 저장 가능. metadata에 `chunk_index` 등 유지.
- **RAG 검색**: vector search + **metadata filter** (domain, region, event_type, document_type) 후 top_k. 필요 시 rerank.

---

## 6. 시나리오 엔진 흐름

1. **Event Extraction**: 기사에서 `event_type`, `actor`, `location` 추출. 8개 Event Taxonomy 사용.
2. **Actor / Location Mapping**: `actor_aliases`, `strategic_locations`와 매핑.
3. **Retrieval**: `match_scenario_support_documents` RPC로 후보 문서 검색. `retrieval_budget` 적용.
4. **Template 적용**: scenario_templates에서 3-step chain 등 선택.
5. **Evidence filter**: scenario_reliability_rules (min_support_documents, source_diversity, confidence) 충족 시에만 시나리오 생성.
6. **LLM Narrative**: 증거 문서 + 템플릿으로 서술형 시나리오 생성. 숫자/확률 없음.
7. **Admin 검수**: 통과 시 scenario_documents에 저장, approved_for_learning=true인 경우 scenario_embeddings에 저장.

---

## 7. 시드 및 분류 체계

| 항목 | 규모 | 비고 |
|------|------|------|
| **Actor Seed** | 약 150개 | 국가 50 + 조직·비국가·에너지·글로벌기구·테크 등. |
| **Actor Aliases** | 약 600개 | 정규화용. |
| **Strategic Location Seed** | 15~18개 | Hormuz, Suez, Malacca, Taiwan Strait, Korean DMZ, Yellow Sea 등. |
| **Event Taxonomy** | 8개 | military_strike, military_threat, sanction, diplomatic_signal, naval_activity, energy_disruption, trade_restriction, political_shift. |
| **Scenario Template** | 10~12개 | Escalation, Economic Shock, Political 등. 3-step chain 권장. |
| **공개 시나리오** | 0~3개/기사 | 증거 품질 충족 시에만. |

---

## 8. Evidence Quality Rule

- **최소 조건**: `support_docs >= 3`, `source_diversity >= 2`, `confidence >= 0.7`.
- **Source Priority**: 1) academic/think tank, 2) global economic DB, 3) historical conflict DB (예: UCDP, World Bank, SIPRI, EIA).
- 위 조건 미충족 시 시나리오 0개 반환.

---

## 9. 운영 안정성

- **Observability**: scenario_generation_runs, scenario_failure_events, scenario_metrics_daily로 지연/실패/zero_result 원인 분석.
- **Failure handling**: extraction/retrieval/generation 단계별 실패 시 로그 및 zero_result_reason 기록. 재시도 정책은 cron/배치 레벨에서 정의.
- **Cache / Dedupe**: content_hash로 문서 중복 방지. scenario_cache_invalidation_events로 캐시 무효화 이력 관리. Shadow run으로 신규 로직 검증 후 반영.

---

## 10. MVP 완료 조건

- [ ] Data Governance·Knowledge Graph·Strategic Geography·State·Conflict·Document·Event Extraction·Scenario Engine 테이블 및 RPC 적용 (Supabase).
- [ ] UCDP, SIPRI, World Bank, EIA 중 최소 3개 소스 ETL + document generator + scenario_support_documents 적재.
- [ ] Actor Seed ~150, Aliases ~600, Strategic Locations 15~18, Event Taxonomy 8개, Scenario Templates 10~12 반영.
- [ ] Event extraction 파이프라인 (기사 → article_event_mentions) 연동.
- [ ] match_scenario_support_documents 기반 RAG + reliability rules 적용 시나리오 생성 (2~3개, 숫자/확률 없음).
- [ ] Admin에서 What-if 전용 패널: 시나리오 후보 검토, 승인 시에만 공개·학습 자산화.
- [ ] 기사 게시 시 What-if 파이프라인 트리거(또는 배치)로 결과 테스트 가능.
- [ ] scenario_support_document 타입 500자 이하 제약 및 Evidence Quality Rule 적용 확인.

---

## 11. 구현 TODO (참조)

1. **DB**: Supabase에 What-if용 스키마/테이블 생성 (3.1~3.11).
2. **ETL**: UCDP/SIPRI/World Bank/EIA 인입 스크립트 + document generator + embedding 배치.
3. **Cron**: daily/weekly/yearly 작업 등록.
4. **Event Extraction**: 기사 수집/게시 훅에서 article_event_mentions 생성 (또는 배치).
5. **RAG 서비스**: match_scenario_support_documents 호출, reliability rules, 2~3 시나리오 생성 로직.
6. **Admin UI**: What-if 패널 (시나리오 목록, 승인/반려, 학습 반영 플래그).
7. **Observability**: 실행 로그, failure 이벤트, 일별 메트릭 저장 및 모니터링.
8. **캐시**: content_hash 기반 갱신, cache invalidation 이벤트 기록.

---

*문서 버전: v4. 내일 이어서 구현 시 이 문서를 기준으로 진행.*
