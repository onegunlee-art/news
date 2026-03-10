# 플랜 5개 개발 항목 검증 보고서

검증 일시: 2026-03-08  
대상: 썸네일 생성 진단 플랜의 5개 수정 사항

---

## 1. ThumbnailAgent: 폴백 로직 catch 밖으로 이동

**플랜:** `createImage()`가 null을 반환해도 제목 기반 폴백 DALL-E를 시도하도록, 폴백 로직을 catch 블록 밖으로 이동.

**검증 결과: 완료**

- **위치:** `src/agents/agents/ThumbnailAgent.php` 160~196행
- 1차 시도: 내레이션 기반 프롬프트로 `createImage()` 호출 (try-catch 내부).
- **1차 실패 시(null 반환):** catch 밖에서 `if ($newImageUrl === null || $newImageUrl === '')` 로 분기 후, 제목 기반 `buildFallbackPromptFromTitle()` + `createImage()` 재시도 (179~196행).
- 따라서 API 오류 등으로 null이 반환되어도 예외가 나지 않아 catch에 안 들어가는 경우에도 폴백이 실행됨.

---

## 2. createImage() 디버그 로깅 강화

**플랜:** 실패 원인 로깅 강화 + (선택) API 응답에 실패 사유 포함해 Admin에서 확인 가능하게.

**검증 결과: 로깅 강화 완료 / API 응답 포함 미적용**

**완료된 부분**
- **OpenAIService::createImage()**
  - mock/빈 프롬프트: `lastError` 설정 후 `error_log`에 `[DALL-E]` 접두어로 로깅.
  - cURL 오류: `lastError` 설정 후 `error_log('[DALL-E] ' . $this->lastError . ' | timeout=' . $dalleTimeout . 's')`.
  - HTTP 비200: `lastError` 설정 후 `error_log('[DALL-E] API error: ' . $this->lastError)`.
  - 응답에 image URL 없음: `lastError` + `error_log`.
  - 스토리지 디렉터리 생성 실패/쓰기 불가: `lastError` + `error_log`.
  - 다운로드/파일 저장 실패: `lastError` + `error_log`.
- **ThumbnailAgent**
  - 1차/폴백 모두 `createImage()`가 null 반환 시 `$this->openai->getLastError()`를 로그 메시지에 포함 (173, 190행).

**미적용 부분**
- analyzeUrl/analyzeContent 성공 시 JSON의 `debug`에 썸네일 실패 사유(예: `thumbnail_last_error`)를 넣어 Admin UI에서 보이도록 하는 처리는 없음. (서버 로그로만 확인 가능)

---

## 3. DALL-E 타임아웃 60초 → 90초

**플랜:** DALL-E 호출 cURL 타임아웃 90초로 증가.

**검증 결과: 완료**

- **위치:** `src/agents/services/OpenAIService.php` 777행
- `$dalleTimeout = (int) ($options['timeout'] ?? $imgConfig['timeout'] ?? 90);`
- 기본값 90초 사용. 788행에서 `CURLOPT_TIMEOUT => $dalleTimeout` 적용.
- `config/openai.php`의 `images`에는 `timeout` 키가 없어, 코드 기본값 90이 적용됨.

---

## 4. 스토리지 경로 검증

**플랜:** `storage/thumbnails` 경로 존재·쓰기 가능 여부를 명시적으로 검증.

**검증 결과: 완료**

- **위치:** `src/agents/services/OpenAIService.php` 819~833행
- 디렉터리 없으면 `mkdir(..., 0755, true)` 후 `$mkResult` 확인. 실패 시 `lastError = 'Cannot create storage dir: ...'` 및 `error_log` 후 `return null`.
- `is_writable($storagePath)`로 쓰기 가능 여부 검사. 불가 시 `lastError = 'Storage dir not writable: ...'` 및 `error_log` 후 `return null`.
- 위 검증 실패 시 이미지 저장 시도 없이 null 반환.

---

## 5. 배포 시 thumbnails 보존

**플랜:** FTP-Deploy-Action에서 `storage/thumbnails` 제외해 기존 생성 썸네일이 삭제되지 않도록.

**검증 결과: 완료**

- **위치:** `.github/workflows/deploy.yml` 210~216행
- `exclude:` 아래에 `storage/thumbnails/**`, `storage/audio/**` 포함.
- FTP 업로드 시 해당 경로는 제외되므로, 서버에 이미 있는 썸네일/오디오 파일이 덮어쓰기·삭제되지 않음.

---

## 요약

| # | 항목 | 상태 | 비고 |
|---|------|------|------|
| 1 | ThumbnailAgent 폴백 로직 catch 밖 이동 | 완료 | null 반환 시에도 title-only 폴백 실행 |
| 2 | 디버그 로깅 강화 | 로깅 완료 | API 응답에 실패 사유 포함은 미적용 |
| 3 | DALL-E 타임아웃 90초 | 완료 | OpenAIService 기본값 90초 |
| 4 | 스토리지 경로 검증 | 완료 | 존재·쓰기 가능 검사 후 진행 |
| 5 | 배포 시 thumbnails exclude | 완료 | deploy.yml exclude 규칙 적용 |

**결론:** 플랜의 필수 구현(1, 3, 4, 5번)은 모두 반영되었고, 2번은 서버 측 로깅 강화까지 완료. Admin UI에서 실패 사유를 보려면 추후 `debug.thumbnail_last_error`(또는 동일 목적 필드)를 API 응답에 추가하는 작업이 필요함.
