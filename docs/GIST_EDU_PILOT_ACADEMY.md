# GIST EDU — 파일럿 학원 1 온보딩

> **상태:** Sprint 1 · 시나리오 B GO  
> **로테:** Q-G01 → Q-G05 → Q-G14 (주간 순환)  
> **부모 PT:** v3.1 PDF + Share Card **학생 인용문** ([`exports/gist-edu/parent-reports/`](exports/gist-edu/parent-reports/))

---

## 1. 인프라 체크리스트

| # | 작업 | 명령 / 경로 |
|---|------|-------------|
| 1 | Supabase DDL | [`database/migrations/add_edu_tables.sql`](../database/migrations/add_edu_tables.sql) SQL Editor 실행 |
| 2 | 퀘스트 20건 시드 | `php tools/seed_edu_daily_quests.php` |
| 3 | 데모 학생 + 초대코드 | `php tools/seed_edu_daily_quests.php --students` |
| 4 | 부모 PDF (PT용) | `php tools/generate_edu_parent_report_pdfs.php` |

---

## 2. 학생 온보딩

1. 학원에 초대코드 배포 (예: `EDU-PILOT-01` ~ `EDU-PILOT-10`)
2. 학생 URL: `https://edu.thegist.co.kr/edu` (또는 스테이징 `/edu`)
3. 초대코드 입력 → 4스텝 퀘스트 완주
4. 완료 시 **hero_sentence**가 DB `edu_writing_drafts`에 저장됨 (부모 Share Card Sprint 2 연동)

### UI 4스텝 (LOCKED)

```
찬반 선택 → 반론 읽기 → 5문장 쓰기 → XP·티어
```

---

## 3. 주간 퀘스트 로테

| 주차 (ISO week % 3) | 퀘스트 | 주제 |
|---------------------|--------|------|
| 0 | Q-G01 | AI 일자리 안전망 |
| 1 | Q-G05 | 기후·석유 전환 |
| 2 | Q-G14 | 대만 위기·군사 개입 |

코호트 설정: `edu_pilot_cohorts.rotation_codes`

---

## 4. 게이트 #6 — 부모 인터뷰 (병행)

**프로토콜:** [`GIST_EDU_SPRINT0_GATE.md`](GIST_EDU_SPRINT0_GATE.md) §82

| 질문 | 통과 기준 |
|------|-----------|
| "이 리포트를 월 2~3만원에 받을 의향?" | 3/5+ 긍정 |
| Share Card를 친구에게 보여줄 것 같은가? | 인용문 카드 강조 |
| 숫자 vs 학생 문장 — 어느 쪽이 더 와닿는가? | **문장** 우선 (v3.1 원칙) |

**PT 자료:** PDF 5종 + 본 문서 Share Card 예시

---

## 5. API 엔드포인트 (edu BFF)

| 메서드 | 경로 | 인증 |
|--------|------|------|
| POST | `/api/edu/invite/redeem.php` | — |
| GET | `/api/edu/quests/today.php` | `X-Edu-Token` |
| POST | `/api/edu/session/start.php` | `X-Edu-Token` |
| POST | `/api/edu/session/stance.php` | `X-Edu-Token` |
| POST | `/api/edu/session/hammer.php` | `X-Edu-Token` |
| POST | `/api/edu/session/writing.php` | `X-Edu-Token` |
| POST | `/api/edu/session/complete.php` | `X-Edu-Token` |
| GET | `/api/edu/tier/progress.php` | `X-Edu-Token` |

**경계:** 코어 `public/api/news/*` 등 WRITE **0건**. Partner RAG는 READ only (Sprint 1+ Hammer 고도화).

---

## 6. 운영 연락 · 미결 (CEO)

| 항목 | Sprint 1 기본값 |
|------|-----------------|
| 기사 본문 공개 | Hammer 단계 요약만 |
| middle:high | 10:10 |
| 부모 알림 채널 | Sprint 2 (PDF PT만) |

---

*파일럿 학원 1 — Sprint 1 온보딩 가이드*
