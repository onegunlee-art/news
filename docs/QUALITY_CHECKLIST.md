# 기사 품질 검증 체크리스트

## 기준 글 스타일 (Foreign Affairs 예시)

### 원문
- **제목**: America Needs an Alliance Audit
- **부제**: Not All Partnerships Are Worth Sustaining
- **소제목**: DON'T SETTLE, TRIM THE FAT, BANG FOR YOUR BUCK, TIME FOR AN AUDIT

### 완성본 스타일
- **제목**: 미 동맹국들에 대한 평가표
- **소제목 형식**: 번호 + 한글 (영문)
  - 예: "1. 정착하지 말 것 (DON'T SETTLE)"
- **본문 구조**: 도입 → 섹션별 분석 → 결론
- **톤**: 객관적이지만 독자에게 설명하듯이

## 필수 체크 항목

### 1. 제목
- [ ] 영문 원제를 직역한 한국어 제목
- [ ] 15~30자 내외
- [ ] 임팩트 문구가 아닌 내용 전달 중심

### 2. 소제목 (sections)
- [ ] 원문의 ALL CAPS 헤딩 모두 포함
- [ ] `번호. 한글 (영문)` 형식
- [ ] 각 섹션별 요약 2~4문장

### 3. 내레이션 (narration)
- [ ] 인사말 없이 바로 본문 시작
- [ ] 도입 → 주요 내용 → 왜 중요한지 자연스럽게 이어서 작성
- [ ] 번호/소제목 형식(1. 2. 등) 강제 없이 말하듯이 흐름 유지
- [ ] 귀로만 들어도 기사 전체를 이해할 수 있을 정도로 상세
- [ ] 최소 1000자 이상

### 4. 금지 표현
- [ ] `지스터` 사용 금지
- [ ] `여러분` 인사말 금지
- [ ] `시청자`, `청취자` 호칭 금지

### 5. 구조화
- [ ] `sections` 배열에 구조화된 소제목 포함
- [ ] `content_summary`에 번호 + 소제목 형식

## 회귀 테스트 케이스

### Case 1: Foreign Affairs 기사
- URL: foreignaffairs.com 계열
- 검증: ALL CAPS 소제목 4개 이상 추출
- 기대: sections 배열에 정확히 매핑

### Case 2: Economist 기사
- URL: economist.com 계열
- 검증: Leader/Briefing 섹션 구분
- 기대: 섹션별 요약 포함

### Case 3: FT 기사
- URL: ft.com 계열
- 검증: 본문 구조 보존
- 기대: 1000자+ narration

### Case 4: 영문 원제 마지막 단어 보존 (제목 회귀)
- 예시 제목: **Why Escalation Favors Iran**
- 검증: original_title이 "Why Escalation Favors Iran" 전체로 나와야 함. "Iran" 등 마지막 단어가 슬러그 재구성으로 잘리지 않음.
- 기대: 스크래퍼 메타(og:title 등)에서 추출한 제목이 그대로 original_title로 사용됨.

### Case 5: Foreign Affairs 식 ALL CAPS 소제목 기사
- 검증: content_summary·sections에 소제목 한글(영문) 형식 반영.
- 기대: narration에는 번호/소제목 형식 없이 자연스러운 말하기 흐름으로 작성.

### Case 6: Economist 부분 본문 기사
- 검증: 본문이 중간에 잘렸거나 광고로 끊긴 경우에도 분석 가능.
- 기대: narration 끝에 "[기사 일부만 분석됨]" 문구가 본문에 포함되지 않음 (별도 메타/배지 처리 권장).

### Case 7: 품질 양호 기존 샘플
- 검증: 과거에 품질이 좋다고 확인된 기사로 재분석 시 제목·소제목·메타 문구가 누락/왜곡되지 않음.
- 기대: The Gist's Critique, 참고자료, 참조 프레임워크 등 메타 문구가 사용자-facing 요약/내레이션에 노출되지 않음.

### Case 8: 프롬프트 원복 회귀 (2025-03)
- **영문 제목**: "Why escalation favors Iran" 등 메타 제목이 original_title에 그대로 유지되는지 확인. (AnalysisAgent에서 article->getTitle() 1순위 적용)
- **내레이션**: 1번, 2번 같은 번호+소제목 형식 없이, 도입 → 주요 내용 → 왜 중요한지가 말하듯 자연스럽게 이어지는지 확인.
- **메타 문구 미노출**: narration·content_summary 저장 전 백엔드 `stripMetaPhrasesFromText` 적용, 원문 AI 분석 표시 시 프론트 `stripAnalysisMetaPhrases` 적용. "지스터 관점의 시사점", "[기사 일부만 분석됨]", "참고자료를 제대로 반영하지 못했습니다" 등이 최종 노출되지 않는지 확인.

## 자동 검증 API

`/api/admin/persona-api.php?action=test` 응답의 `checklist` 항목:
- `has_sections`: sections 배열 존재 여부
- `narration_length`: 내레이션 길이 (1000+ 권장)
- `no_jister`: 지스터 표현 미포함 여부
- `has_subheadings`: 소제목 포함 여부
