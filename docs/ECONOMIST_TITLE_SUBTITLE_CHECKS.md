# Economist 제목/부제목 회귀 점검 포인트

Economist 기사 분석 시 제목·부제목(standfirst) 처리 변경 후, 아래 항목으로 회귀 여부를 점검합니다.

## 점검 포인트

### 1. 제목 정규화 (URL 스크래핑)
- [ ] **Leaders 단독**: `og:title` 등이 `"Leaders"` 단독일 때 제목으로 채택하지 않고, 다음 후보(h1, title 태그)를 사용하는지
- [ ] **Prefix 제거**: `"Leaders | Donald Trump must stop soon"` → `"Donald Trump must stop soon"` 으로 노출되는지
- [ ] **다른 섹션**: `Briefing |`, `Finance & economics |`, `Science and technology |` 등 동일하게 prefix 제거되는지

### 2. 부제목(standfirst) → description
- [ ] **메타 우선**: `og:description` / `twitter:description` / meta description 순으로 채워지는지
- [ ] **Economist DOM**: economist.com일 때 standfirst/deck 클래스 등에서 도입 요약이 추출되어 `description`에 들어가는지 (메타가 없거나 짧을 경우)

### 3. 붙여넣기(analyze_content) fallback 제목
- [ ] **URL이 Economist**: 본문 앞에 `Leaders |` 등이 있어도 fallback 제목에 prefix가 포함되지 않는지
- [ ] **제목 없음 시**: `title`이 비어 있고 URL이 economist일 때, 본문 첫 줄에서 라벨 제거 후 80자 이내로 fallback 제목이 생성되는지

### 4. Admin UI
- [ ] **GPT 생성 제목 블록**: 분석 결과에서 제목 아래에 부제목(description)이 별도 줄로 표시되는지
- [ ] **뉴스 제목 입력 아래**: `articleSummary`(description)가 있으면 제목 입력란 아래에 부제목으로 표시되는지

### 5. 데이터 흐름
- [ ] **ArticleData.description**: 스크래퍼·analyze_content 경로 모두에서 `description`이 API 응답 `article.description`으로 내려가는지
- [ ] **프론트 매핑**: `data.article.description` → `setArticleSummary` 호출로 폼에 반영되는지

## 관련 파일
- `src/agents/services/WebScraperService.php`: `extractTitle`, `extractDescription`, `normalizeEconomistTitle`, `extractEconomistStandfirst`
- `public/api/admin/ai-analyze.php`: `fallbackTitleFromPastedContent`, `analyzeContent` fallback
- `src/frontend/src/pages/AdminPage.tsx`: 제목 영역 + GPT 결과 블록 부제목 표시
