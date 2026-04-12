# 그래픽 에셋 체크리스트 (Google Play)

제출용 PNG는 **[exports/](./exports/)** 에 있습니다. 목업이므로 최종 제출 전 실제 UI 캡처로 바꿀 수 있습니다.

---

## 1. 앱 아이콘 — 512 × 512 px

**파일:** [exports/app-icon-512.png](./exports/app-icon-512.png)

| 항목 | 요구 |
|------|------|
| 형식 | PNG (32비트), 또는 정책에 맞는 형식 |
| 크기 | **512 × 512** |
| 내용 | **파비콘 g. 마크** — [BRAND_ICON.md](./BRAND_ICON.md) 참고 |

**주의:** 마스크 가능 아이콘을 쓰는 경우 안전 영역(중앙 66%) 가이드를 [Play 헬프](https://support.google.com/googleplay/android-developer)에서 확인합니다.

---

## 2. 기능 그래픽 (Feature graphic) — 1024 × 500 px

**파일:** [exports/feature-graphic-1024x500.png](./exports/feature-graphic-1024x500.png)

| 항목 | 요구 |
|------|------|
| 크기 | **1024 × 500** |
| 용도 | 스토어 상단 배너 |

**카피 예시 (한국어)**

- 메인: `글로벌 이슈, AI로 한눈에`
- 서브: `저널·뉴스 구조 분석 · 인사이트 the gist.`

**시각 가이드**

- 다크 배경(`#0a0a0f` 계열) + 브랜드 톤
- **브라우저 주소창·지구본만 있는 일러스트**는 피하고, **분석·데이터·에디토리얼** 느낌
- 글자는 작은 화면에서도 읽히게 대비

---

## 3. 스크린샷 — 최소 2장 (휴대전화)

**파일:**

- [exports/screenshot-01-home.png](./exports/screenshot-01-home.png) — 홈·피드 목업
- [exports/screenshot-02-article-ai.png](./exports/screenshot-02-article-ai.png) — 기사·AI 분석 목업

| 항목 | 요구 |
|------|------|
| 최소 | **2장** |
| 권장 | 4~8장 (주요 기능별) |
| 비율 | 세로형 권장 (대략 **9:16**, 실기기 해상도) |

**권장:** 실제 단말에서 캡처한 PNG로 **교체**하면 심사·신뢰도에 유리합니다. 현재 포함분은 목업입니다.

---

## 4. (선택) 태블릿·기타

정책·카테고리에 따라 7인치·10인치 태블릿 스크린샷이 요구될 수 있습니다. 콘솔 안내에 따릅니다.

---

## 레포 내 참고 파일

- 웹 매니페스트: [public/manifest.webmanifest](../public/manifest.webmanifest)
- TWA 매니페스트: [android/twa/twa-manifest.json](../android/twa/twa-manifest.json)
- Digital Asset Links: [public/.well-known/assetlinks.json](../public/.well-known/assetlinks.json)
