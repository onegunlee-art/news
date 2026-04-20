# Google Play 제출용 이미지 (exports)

Play Console 업로드 시 **아래 `play-` 접두사 파일(ASCII 이름)** 을 사용하세요.  
한글 파일명(`스크린샷*.jpg`)은 일부 브라우저·콘솔에서 업로드가 실패할 수 있습니다.

## 필수 그래픽 (픽셀 검증됨)

| 파일 | 용도 | 픽셀 | 형식 |
|------|------|------|------|
| **`play-feature-graphic-1024x500.png`** (또는 `.jpg`) | 기능 그래픽 | **정확히 1024 × 500** | PNG 또는 JPEG |
| **`app-icon-512.png`** | 앱 아이콘 | **512 × 512** | PNG(RGB, 알파 없음) |
| **`play-phone-screenshot-01.jpg`** ~ **`08.jpg`** | 휴대전화 스크린샷 | 1080 × 2316 (예시) | JPEG |

### 과거 이슈 (수정 완료)

- `feature-graphic-1024x500.png` 가 **실제로는 1376×768** 이었음 → Play가 거절.  
  지금은 **1024×500으로 리사이즈·크롭**하여 `feature-graphic-1024x500.png` 및 `play-feature-graphic-1024x500.*` 에 반영됨.
- 앱 아이콘이 **RGBA** 였음 → 일부 업로드에서 경고 가능 → **#0a0a0f 배경 위에 합성한 RGB** 로 `app-icon-512.png` 갱신.

## 업로드 팁

1. **기능 그래픽**: 반드시 **가로 1024, 세로 500** (한 픽셀도 다르면 안 됨).
2. **파일 이름**: 영문·숫자·하이픈만 (`play-...`).
3. 스크린샷은 **최소 2장**; `play-phone-screenshot-01.jpg`, `02.jpg`만 올려도 됨.
4. 용량이 크면 JPG 기능 그래픽(`play-feature-graphic-1024x500.jpg`)을 시도.

## 기타 (이 폴더)

- `release/app-release.aab` — 로컬 빌드 산출물 (저장소에 올리지 않아도 됨)
- `스크린샷*.jpg` — 원본 보관용; 업로드는 `play-phone-screenshot-*.jpg` 사용
