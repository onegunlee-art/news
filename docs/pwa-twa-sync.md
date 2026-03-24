# PWA 설치 UI · Android TWA · 웹 동기화

## 웹 클라이언트 (`InstallPrompt`)

- **`pwa_install_completed`**: 설치로 간주되는 경우 `localStorage`에 `1` 저장. 이후 같은 출처(origin)에서는 설치 유도 UI를 띄우지 않음. PWA 제거 시 브라우저가 저장소를 지우면 다시 안내 가능.
- **`gist_pwa_ack=1` 쿼리**: 페이지 로드 시 한 번 읽어 위 플래그를 설정하고 URL에서 제거한다. TWA 앱 첫 실행 시 커스텀 스킴/HTTPS 딥링크로 웹을 열 때 동기화에 사용할 수 있다.
- **`gist_pwa_reset=1` 쿼리**: 설치 관련 `localStorage` 플래그를 모두 지운 뒤 URL에서 제거한다. 배너가 안 보일 때(이전에 「다시 보지 않기」 등) 테스트·확인용으로 사용한다.
- **`appinstalled` / `beforeinstallprompt` 수락**: Android Chrome 등에서 PWA 설치가 완료되면 동일 키를 설정한다.

## Android TWA (Bubblewrap / Play)

- TWA는 **별도 APK**이며 WebView/Chrome Custom Tabs의 저장소가 **모바일 Chrome과 항상 같지 않다**. “스토어 앱을 깔았다”는 사실을 **웹만으로 자동 감지하기 어렵다**.
- 권장 연동:
  1. **Digital Asset Links**로 도메인 검증 후 Play 배포(공식 TWA 절차).
  2. 앱에서 웹을 열 때 `https://www.thegist.co.kr/?gist_pwa_ack=1` 형태로 한 번 진입시키거나, 앱 전용 딥링크 핸들러에서 동일 쿼리를 붙인다.
  3. 웹은 쿼리 처리 후 히스토리에서 파라미터를 제거해 주소창을 깔끔히 유지한다.

## iOS

- Safari는 **“홈 화면에 추가” 완료를 웹 탭에서 자동으로 알 수 없다**. 사용자 확인(「홈 화면에 추가했어요」) 또는 **standalone** 실행(홈 화면 아이콘) 시 UI를 숨긴다.

## PWA 설치 이벤트 전제 조건 (Chrome)

- Chrome의 `beforeinstallprompt`는 **설치 가능한 PWA**에 대해서만 발생한다. 일반적으로 **Web App Manifest**와 **Service Worker**(fetch 이벤트 등)가 필요하다.
- 저장소 기준: [`public/manifest.webmanifest`](../public/manifest.webmanifest), [`public/sw.js`](../public/sw.js), [`src/frontend/index.html`](../src/frontend/index.html)에서 SW 등록.
- 이전에 있던 “매 방문 SW 완전 해제” 스크립트는 제거되어, installable 조건을 맞출 수 있다. 여전히 브라우저·정책에 따라 프롬프트가 안 뜰 수 있어, 프론트에는 **수동 설치 안내(폴백)** 도 유지한다.
- **Digital Asset Links**: [`public/.well-known/assetlinks.json`](../public/.well-known/assetlinks.json) — TWA용 SHA-256은 서명 키 확정 후 치환. 운영 절차는 [TWA_SIGNING_AND_PLAY.md](./TWA_SIGNING_AND_PLAY.md) 참고.
