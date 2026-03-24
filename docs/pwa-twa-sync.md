# PWA 설치 UI · Android TWA · 웹 동기화

## 웹 클라이언트 (`InstallPrompt`)

- **`pwa_install_completed`**: 설치로 간주되는 경우 `localStorage`에 `1` 저장. 이후 같은 출처(origin)에서는 설치 유도 UI를 띄우지 않음. PWA 제거 시 브라우저가 저장소를 지우면 다시 안내 가능.
- **`gist_pwa_ack=1` 쿼리**: 페이지 로드 시 한 번 읽어 위 플래그를 설정하고 URL에서 제거한다. TWA 앱 첫 실행 시 커스텀 스킴/HTTPS 딥링크로 웹을 열 때 동기화에 사용할 수 있다.
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

- Chrome의 `beforeinstallprompt`는 **설치 가능한 PWA**에 대해서만 발생한다. 일반적으로 **Web App Manifest**와 **Service Worker**(오프라인 범위 등)가 필요하다.
- 현재 [`public/index.html`](../public/index.html) / [`src/frontend/index.html`](../src/frontend/index.html)에서는 **기존 Service Worker를 매 방문 해제**하는 스크립트가 있다. **설치 배너를 안정적으로 띄우려면** SW·매니페스트 정책을 재검토해야 한다(별도 작업).
- SW를 켜지 않는 한, **영구 숨김·쿼리 ack·iOS 안내** 등 본 문서의 UX 플래그는 그대로 동작한다.
