# 매체 설명 vs 오디오 불일치 분석

## 현상
- **화면 매체 설명**: "이 글은 ~~" 로 올바르게 표시됨 (새 버전)
- **오디오**: 이전 버전의 매체 설명을 읽어줌

---

## 데이터 흐름

### 1. 화면 표시 (NewsDetailPage.tsx:326)
```tsx
이 글은 {formatDate()}자, {source}에 게재된 "{original_title}" 기사를 ...
```
- `original_title` = `(news.original_title && news.original_title.trim()) || extractTitleFromUrl(news.url) || '원문'`

### 2. TTS 재생 (NewsDetailPage.tsx:61-92)
```tsx
const titleForMeta = (news.original_title && String(news.original_title).trim()) || extractTitleFromUrl(news.url) || 'Article'
const editorialLine = `${dateStr}자 ${sourceDisplay} 저널의 "${titleForMeta}"을 AI 번역, 요약하고 The Gist에서 일부 편집한 글입니다.`
openAndPlay(titleForMeta, editorialLine, mainContent, critiquePart, ...)
```
- **동일한 news 객체** 사용 → `news.original_title` 또는 `extractTitleFromUrl(news.url)` 동일 소스

### 3. TTS API 호출 (audioPlayerStore.ts:66)
```ts
ttsApi.generateStructured(t, m, n, c, newsId)
// t=titleForMeta, m=editorialLine(매체설명), n=narration, c=critiquePart
```

### 4. 백엔드 해시 및 캐시 (TTSController.php)
```php
$fullPayload = $title . '|' . $meta . '|' . $narration . '|' . $critiquePart . '|' . $ttsVoice;
$cacheHash = hash('sha256', $fullPayload);
```
- **캐시 조회 순서**: ① 파일 캐시 → ② Supabase media_cache → ③ Google TTS 신규 생성

---

## 원인 후보

### A. Supabase PostgREST JSONB 필터 오동작 (가능성 높음)
```php
$cacheQuery = 'media_type=eq.tts&generation_params->>hash=eq.' . rawurlencode($cacheHash);
$cached = $supabase->select('media_cache', $cacheQuery, 1);
```
- `generation_params->>hash` 필터가 제대로 적용되지 않으면 **여러 행 중 첫 번째 행**을 반환할 수 있음
- 그 결과, 다른 news_id/다른 해시의 **이전 오디오**가 반환될 수 있음

### B. display vs TTS 데이터 소스 불일치 (가능성 낮음)
- 둘 다 `news.original_title` 또는 `extractTitleFromUrl(news.url)` 사용
- 같은 `news` 상태를 공유하므로 이론상 일치해야 함

### C. detail API 캐시/지연
- detail.php가 CDN/프록시에 의해 캐시되면, DB 업데이트 후에도 이전 응답이 올 수 있음
- 단, display와 TTS가 같은 API 응답을 쓰므로 둘 다 이전 데이터여야 함 → display가 새로 보인다면 이 가설과 맞지 않음

### D. extractTitleFromUrl vs original_title 우선순위
- display: `original_title || extractTitleFromUrl || '원문'`
- TTS: `original_title || extractTitleFromUrl || 'Article'`
- 우선순위 동일. `original_title`이 비어 있고 URL에서 추출한 값이 올바르다면 둘 다 동일해야 함

---

## 적용된 조치 (완료)

### 1. 디버깅 로그 추가
- `storage/logs/tts_debug.log`: `structured_request`, `cache_hit`, `cache_mismatch`, `supabase_miss`, `generating` 이벤트
- title/meta 미리보기, cache_hash, 캐시 소스(file/supabase) 기록

### 2. Supabase 필터 검증 및 RPC 전환
- **RPC 우선**: `get_tts_cache_by_hash(p_hash)` 함수로 hash 기반 조회 (JSONB 필터보다 안정적)
- **REST 폴백**: RPC 미정의 시 기존 PostgREST 필터 사용 + **해시 검증** (반환 row의 hash가 요청 hash와 일치할 때만 캐시 히트)
- 마이그레이션: `database/migrations/add_get_tts_cache_by_hash_rpc.sql` → Supabase SQL Editor에서 실행

### 3. 캐시 무효화 옵션 (미적용)
- `original_title` 변경 시 해당 news_id의 media_cache 레코드 삭제 또는 무효화 로직 추가
- 또는 TTS 요청 시 `news_id`를 함께 전달해, 캐시 조회 시 `news_id` + `hash` 복합 조건 사용 검토

---

## 관련 파일
| 파일 | 역할 |
|------|------|
| `src/frontend/src/pages/NewsDetailPage.tsx` | display(326행), playArticle(61-92행) |
| `src/frontend/src/store/audioPlayerStore.ts` | openAndPlay → ttsApi.generateStructured |
| `src/backend/Controllers/TTSController.php` | 해시 계산, 파일/Supabase 캐시, TTS 생성 |
| `src/agents/services/SupabaseService.php` | select() → PostgREST 쿼리 |
| `public/api/news/detail.php` | original_title 포함 상세 API |
