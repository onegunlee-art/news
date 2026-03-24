# Android TWA (Trusted Web Activity)

웹 매니페스트: `https://www.thegist.co.kr/manifest.webmanifest`

## 사전 요건

1. 프로덕션에 `manifest.webmanifest`와 `sw.js`가 배포되어 있을 것
2. `https://www.thegist.co.kr/.well-known/assetlinks.json` 이 JSON으로 응답할 것 (SHA-256은 서명 키 확정 후 갱신)

## Bubblewrap 설치

```bash
npm install -g @bubblewrap/cli
```

## 초기화 (이 디렉터리에서 실행)

```bash
cd android/twa
bubblewrap init --manifest https://www.thegist.co.kr/manifest.webmanifest
```

- **Android package name**: `kr.co.thegist.app` (assetlinks.json과 동일해야 함)
- 아이콘·시작 URL은 매니페스트를 따름

## 빌드 (AAB)

```bash
bubblewrap build
```

생성된 `.aab`는 Play Console에 업로드.

## 서명 키

- Bubblewrap이 생성한 키스토어 또는 별도 업로드 키 사용
- **SHA-256 인증서 지문**을 [public/.well-known/assetlinks.json](../../public/.well-known/assetlinks.json)에 반영
- Play App Signing 사용 시: Play Console에 표시되는 **앱 서명 키** 지문을 사용하는 경우가 많음 (공식 문서 확인)

## 웹 ↔ 앱 동기화 (선택)

앱 최초 실행 시 웹에 한 번 `?gist_pwa_ack=1`을 붙여 열면 설치 유도 배너를 끌 수 있습니다.  
자세한 내용은 [docs/pwa-twa-sync.md](../../docs/pwa-twa-sync.md) 참고.

## 참고

- [Bubblewrap](https://github.com/GoogleChromeLabs/bubblewrap)
- [Digital Asset Links](https://developers.google.com/digital-asset-links/v1/getting-started)
