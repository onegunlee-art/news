# GIST EDU — 디자인 시스템 (LOCKED)

> **상태:** Sprint 0 확정 · EDU 전역 기본  
> **적용:** 부모 리포트 PDF · Sprint 1+ `edu.thegist.co.kr` UI

---

## 1. 브랜드 마크

| 항목 | 값 |
|------|-----|
| **마크** | **g.** — [`public/favicon-G.svg`](../public/favicon-G.svg) |
| **배경** | `#FFFFFF` |
| **글리프** | `#000000` |
| **용도** | 앱 아이콘 · 헤더 · PDF · 부모/학생 화면 상단 |

컬러 로고(`the-gist-logo.jpg`)는 **EDU에서 사용하지 않음**.

---

## 2. 컬러 (블랙 & 화이트 only)

| 토큰 | HEX | 용도 |
|------|-----|------|
| `color_bg` | `#FFFFFF` | 페이지·카드 배경 |
| `color_fg` | `#000000` | 본문·제목·진행 바 fill |
| `color_muted` | `#666666` | 보조 텍스트·캡션 |
| `color_border` | `#E5E5E5` | 테이블·카드·구분선 |
| `color_surface` | `#F5F5F5` | 박스 배경 (알림·스토리) |
| `progress_fill` | `#000000` | Tier XP 바 |
| `progress_track` | `#E5E5E5` | Tier XP 바 트랙 |

**금지:** teal·컬러 악센트·그라데이션·브랜드 컬러 배지 (`#0d9488`, `#14b8a6`, `#ccfbf1` 등)

---

## 3. 타이포

| 용도 | 규칙 |
|------|------|
| 본문 | Noto Sans KR · 10–11pt · `#000000` |
| 제목 h1 | 15pt · bold |
| 섹션 h2 | 12pt · bold · 좌측 4px 검은 보더 |
| 캡션 | 8–9pt · `#666666` |

---

## 4. 컴포넌트

### TierProgressCard

학생·부모 화면 공통. 흑백 카드 + 1px 검은 보더.

```
┌──────────────────────────────────┐
│  Gold  (골드 사상가)              │
│  ████████████░░░░░░  64%         │
│  XP 1,440  /  Platinum: 1,800    │
│  12일 연속                       │
└──────────────────────────────────┘
```

### ParentAlertBox

승급·Challenger 달성. `color_surface` 배경 + 2px `#000000` 보더.

### Tag

흰 배경 · 1px 검은 보더 · 검은 텍스트 (컬러 pill 금지)

---

## 5. 표시 규칙 (LOCKED)

### 학년·학교급 숨김

| 축 | 용도 | UI 노출 |
|----|------|---------|
| `grade_band` (middle/high) | 퀘스트 adaptation·콘텐츠 매칭 | **내부 전용** |
| 학년 라벨 (중1, 고2 등) | — | 학생·부모 화면 **금지** |
| Tier + XP + 연속일 | 학생 정체성 | **표시** |

학생은 **Observer ~ GIST Challenger** 메달 티어만 본다. 중1/고1 같은 학년 표기는 사용하지 않는다.

부모 리포트 PDF 샘플의 `grade_label`은 Sprint 0 검증용 메타데이터이며, Sprint 1+ 제품 UI에는 이 패턴을 이식하지 않는다.

---

## 6. PDF·문서 (학생 UI)

- 학생·앱 UI 헤더: g. SVG 24–28px (B&W)
- 재생성: `php tools/generate_edu_parent_report_pdfs.php`

---

## 7. Parent Report PDF Theme (v3.1 · Editorial Orange)

> **적용:** 부모 리포트 PDF **만** — 학생 UI §2 B&W LOCKED **유지**  
> **기준:** edu.pdf 에디토리얼 레이아웃 + **생각 변화 Share Card**

### 브랜드 컬러 (the gist `--primary`)

| 토큰 | HEX | 용도 |
|------|-----|------|
| `edu_brand` | `#f05123` | 악센트·강조·XP 바·티어 배지 |
| `edu_brand_dark` | `#e03a19` | hover·보더 강조 |
| `edu_brand_light` | `#f36b42` | 보조 강조 |
| `edu_brand_bg` | `#fef3ef` | 승급 알림·after 카드 배경 |
| `edu_ink` | `#1a1a1a` | 본문 |
| `edu_muted` | `#666666` | 캡션 |
| `edu_line` | `#eeeeee` | 구분선 |
| `edu_neutral_bg` | `#f8f8f8` | before 카드·v1 블록 |

### 마크

| 항목 | 값 |
|------|-----|
| **에셋** | [`public/favicon-G-edu.svg`](../public/favicon-G-edu.svg) |
| **크기** | **78px** (학생 UI 대비 3배) |
| **글리프** | g `#1a1a1a` · 마침표 `#f05123` |
| **워드마크** | `the gist · EDU` |

### 섹션 순서 (LOCKED)

1. Header (g. 78px + 학생·기간·퀘스트)
2. **Share Card** — 이번 달 가장 큰 변화 (before → after, 캡처·공유용)
3. 이달의 핵심 순간 (lead headline) + 승급 알림
4. 생각이 바뀐 순간 (Before / After 카드)
5. 이달 성장 이야기 (narrative)
6. Writing v1 / v2 블록
7. **이번 달 처음 생긴 변화** (문장 리스트) + 월간 성장 관찰
8. **티어 바** + 연속일 (맨 아래 — 성장 후 "그래서 Gold")
9. GIST EDU 이달 한 줄 평가 (2문장, **italic 금지** — Dompdf 한글 깨짐 방지)
10. Footer

### ShareCard 컴포넌트

부모가 친구에게 보여줄 **한 장**. PDF 전체 공유가 아닌 입소문 단위.

```
┌─────────────────────────────────────────┐
│  이번 달 가장 큰 변화                    │
│  [ 무조건 개입 찬성 ] → [ 조건부 + 함의 ] │
└─────────────────────────────────────────┘
```

### 학생 실제 문장 우선 (LOCKED)

부모·공유 화면에서는 XP·근거 횟수·입장 수정 횟수보다 **학생이 직접 쓴 한 문장**(Writing v2 또는 입장 정교화 문장)을 전면에 배치한다. 숫자는 보조(티어 바 하단·내부 로그)로만 사용한다.

Share Card·부모 알림·승급 카피는 `transform.after_text` 또는 Writing v2 핵심 문장을 인용한다.

### monthly_first_changes

기계적 stat (`0→2`, `1회 flip`) **금지**. 부모 친화 문장만:

- "기사 근거를 처음 인용했습니다."
- "반대 의견을 검토한 뒤 입장을 수정했습니다."

**금지 (PDF):** Writing Growth Index 숫자 · stat KPI 블록 · `font-style: italic` (한글) · teal/gold · Thinker 접미

---

## 8. 연결 문서

- 티어: [`GIST_EDU_TIER_SPEC.md`](GIST_EDU_TIER_SPEC.md)
- PLAYBOOK: [`GIST_EDU_PLAYBOOK.md`](GIST_EDU_PLAYBOOK.md)
