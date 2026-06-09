# GIST EDU — 부모 리포트 샘플 5 (가상 데이터)

> **상태:** Editorial Orange v3.1 · **실 DB·실 학생 없음** — 결제·PT 검증용 목업  
> **기준:** edu.pdf + **생각 변화 Share Card** (부모 캡처·공유용)  
> **연결 퀘스트:** Q-G01, Q-G05, Q-G14, Q-G17, Q-G20 (approved v2)  
> **형식:** PDF 5종 → [`exports/gist-edu/parent-reports/`](exports/gist-edu/parent-reports/)  
> **재생성:** `php tools/generate_edu_parent_report_pdfs.php`  
> **디자인:** [`GIST_EDU_DESIGN_SYSTEM.md`](GIST_EDU_DESIGN_SYSTEM.md) §7 — g. **78px** · `#f05123` 오렌지

---

## v3.1 공통 구조

| 순서 | 섹션 | 설명 |
|------|------|------|
| 1 | Header | g. 78px + the gist · EDU · 학생·기간·퀘스트 |
| 2 | **Share Card** | 이번 달 가장 큰 변화 (before → after) — **캡처·입소문용** |
| 3 | 이달의 핵심 순간 | lead headline + 승급 알림 |
| 4 | 생각이 바뀐 순간 | Before / After 변환 카드 |
| 5 | 이달 성장 이야기 | narrative 2문단 |
| 6 | 직접 쓴 글 | Writing v1 / v2 블록 |
| 7 | 이번 달 처음 생긴 변화 | 부모 친화 문장 리스트 + 월간 성장 관찰 |
| 8 | **티어 진행** | 맨 아래 — "그래서 지금 Gold" |
| 9 | 이달 한 줄 평가 | 2문장 (italic 없음 — 한글 안전) |

**금지:** stat KPI (`0→2`, `1회 flip`) · Writing Growth Index · Thinker 접미

---

## Share Card 예시 (샘플 3 · 박지훈)

학생 **실제 인용문** (요약 라벨 금지):

```
이번 달 가장 큰 변화
"미국이 무조건 도와야 한다."  →  "개입 여지는 필요하지만, 전쟁 비용이 크면 외교가 우선이다."
```

---

## 샘플 1 — 김민준 · Q-G01

**Share:** "AI 좋은 거니까 규제 말자." → "일자리가 바뀌면 정부가 미리 안전망을 깔아야 한다."  
**처음 생긴 변화:** 기사 근거 처음 · 반론 검토 후 정교화 · 모순 자가 수정  
**Tier:** Gold · 64% · 12일 연속

---

## 샘플 2 — 이서연 · Q-G05

**Share:** "환경 중요, 당장 석유·가스 다 끊자." → "빨리 줄이되, 당장 일자리·물가도 봐야 한다."  
**Tier:** Silver · 33% · 8일 연속

---

## 샘플 3 — 박지훈 · Q-G14

**Share:** 무조건 개입 찬성 → 조건부 찬성 + 한국 경제 함의 고려  
**한 줄 평가:** 단순 찬반을 넘어 국제 이슈가 한국 경제에 미치는 영향을 설명하기 시작  
**Tier:** Gold · 78% · 15일 연속

---

## 샘플 4 — 최유나 · Q-G17 (edu.pdf 기준)

**Share:** 금리 인상 반대 → 고금리 유지 찬성 (입장 전환)  
**처음 생긴 변화:** 기사 근거 처음 · 반론 후 입장 변경 · flip 이유 5문장 설명  
**Tier:** Gold · 22% · 6일 연속

---

## 샘플 5 — 정하은 · Q-G20

**Share:** AI 전부 OK → 최종 판단·책임은 사람  
**Tier:** GIST Challenger · 24일 연속

---

## Sprint 0 게이트

| 체크 | 상태 |
|------|------|
| Share Card + narrative + eval | ✅ v3.1 |
| 티어 맨 아래 | ✅ |
| **부모 인터뷰 ≥3/5** | ⏳ |

---

## PDF 파일

| # | 파일 |
|---|------|
| 1 | [`01_김민준_중2_Q-G01_AI일자리안전망.pdf`](exports/gist-edu/parent-reports/01_김민준_중2_Q-G01_AI일자리안전망.pdf) |
| 2 | [`02_이서연_중3_Q-G05_기후전환.pdf`](exports/gist-edu/parent-reports/02_이서연_중3_Q-G05_기후전환.pdf) |
| 3 | [`03_박지훈_고1_Q-G14_대만위기.pdf`](exports/gist-edu/parent-reports/03_박지훈_고1_Q-G14_대만위기.pdf) |
| 4 | [`04_최유나_고2_Q-G17_금리물가.pdf`](exports/gist-edu/parent-reports/04_최유나_고2_Q-G17_금리물가.pdf) |
| 5 | [`05_정하은_고3_Q-G20_인지적항복.pdf`](exports/gist-edu/parent-reports/05_정하은_고3_Q-G20_인지적항복.pdf) |
