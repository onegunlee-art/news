# EDU 학생 여정 v1 — 완결 기록

> **완결일**: 2026-06-25  
> **프로덕션 HEAD**: `f5ce8c7` (질문 전문 fix) · 롤백 `77f2064`  
> **범위**: (다) 풀 데모 — 순환 + 멀티주제 + 멀티유저(수동) + 회귀

---

## 트랙 닫힘 선언

| 트랙 | 상태 | 근거 커밋 |
|------|------|-----------|
| 코치 axis_guide (안 떠먹임, 버튼+왜) | **닫힘** | `ede448f`, `77f2064` |
| XP/스트릭/티어 | **닫힘** | 게임화 조각 2 + compose |
| 완주 화면 | **닫힘** | `e6dddf7` |
| 카드 UI + 질문 전문 | **닫힘** | `77f2064` |
| 개인 페이지 | **닫힘** | `02a030e` |
| 주제 630/150/196/288 | **닫힘** | axis_guide seed |

**다음 트랙 (새 기능)**: 초등 디폴트 코치 — v1 완결 후 착수  
**보류**: 7단계/LLM/unlock_tier, 학원 대시보드

---

## 질문 잘림 vs 개인 페이지 (타임라인)

| 시점 | 일 |
|------|-----|
| `ede448f` | 2-C footer `line-clamp-1` → 질문 잘림 **원인** |
| `02a030e` | 개인 페이지 배포 (카드 무관) |
| `77f2064` | 카드 상단 질문 전문 노출 — **수정** |

개인 페이지는 **완성·배포됨**. 잘림은 카드 2-C 부작용이었고 별도 fix 완료.

---

## 자동 검증 (2026-06-25)

```bash
php tools/edu_student_journey_static_verify.php   # 정적 + coach 73건
php tools/edu_coach_guide_test.php              # axis 630/150/196/288 + why
cd src/frontend && npm run build                # TS + Vite
```

| 검사 | 결과 |
|------|------|
| `edu_coach_guide_test.php` | 73 passed, 0 failed |
| `edu_student_journey_static_verify.php` | cards/profile/api/chat 정적 PASS |
| `npm run build` | PASS |
| `GET /api/health` | 200 |
| `GET /api/edu/quests/today.php` | 200 |

`edu_multiuser_separation_test.php` — 로컬 Windows SSL/학생명 미매칭으로 스킵. **프로덕션 EC2 또는 이원근 수동**으로 C항목 확인.

---

## 수동 검증 — 이원근 모바일 (풀 데모)

체크 후 `[ ]` → `[x]`:

### A. 순환

- [ ] `/edu/profile` — 스트릭 크게, XP/티어, 내 글, 다시 보기
- [ ] 홈 → 표지 → 카드 탐구
- [ ] 버튼 → **왜?** → 서술 → 다음 축
- [ ] 긴 질문 **전체** (`…` 없음), 키보드 시 질문 가림 없음
- [ ] 완주 — 불꽃, 구조도, 글
- [ ] 프로필 복귀 — 스트릭 +1, 새 글 목록

### B. 멀티주제

- [ ] 630 (핵) — 버튼+왜 + 서술형 질문 전문
- [ ] 150 또는 196 또는 288 — 동일 스모크

### C. 멀티유저

- [ ] 계정 A / B 프로필·글·스트릭 분리
- [ ] A 완주가 B에 안 섞임

### D. 회귀

- [ ] 선택형 버튼 정상
- [ ] `?ui=chat` 채팅 모드
- [ ] hammer 「다른 시각」 입력 dead zone 없음

**전 항목 통과 시** v1 완결 확정. 이슈 있으면 해당 화면만 최소 fix (코치 FSM 무변경).

---

## 학생 여정 순환 (목표 아키텍처)

```
개인 페이지(스트릭/내 글)
    ↓
퀘스트 표지 → 카드 탐구(버튼+왜)
    ↓
완주(불꽃/구조/글)
    ↓
개인 페이지(스트릭 +1, 포트폴리오)
```

---

## 롤백

[`docs/EDU_DEPLOY_ROLLBACK.md`](EDU_DEPLOY_ROLLBACK.md) — `77f2064`, `02a030e`, `ede448f`
