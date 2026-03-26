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

## 서명 키 (업로드용 keystore 만들기)

**중요:** 지금 `assetlinks.json`에 넣은 SHA-256이 **어디 값인지**에 따라 다릅니다.

- **방금 새로 만들 keystore**에서 뽑은 지문이어야 한다면: 아래로 `my-upload-key.keystore`를 만든 뒤 `keytool -list`로 **다시** 지문을 확인하고, JSON과 **반드시 일치**시키세요.
- **Play Console → 앱 서명(App signing)**에만 있는 지문이라면: 그 지문은 **Google 앱 서명 인증서**일 수 있고, 로컬 keystore 지문과 **다릅니다**. TWA 연결은 보통 **스토어에 올라간 앱과 같은 서명** 기준이므로 [공식 문서](https://developer.chrome.com/docs/android/trusted-web-activity/integration/)와 콘솔의 **업로드 키 vs 앱 서명 키**를 확인하세요.

### 1) JDK (keytool) 준비

PowerShell에서 `java -version`이 안 되면 JDK를 설치하세요 (예: [Eclipse Temurin](https://adoptium.net/) LTS, 설치 시 **PATH 추가** 체크).  
이미 **Android Studio**만 있다면 내장 JBR의 `keytool.exe`를 쓸 수 있습니다:

`Settings` → `Build, Execution, Deployment` → `Build Tools` → `Gradle` → **Gradle JDK** 경로 → 그 아래 `bin\keytool.exe`

### 2) keystore 생성 (대화형)

프로젝트 루트 또는 `android/twa`에서 (비밀번호·이름 등은 프롬프트에 입력):

```powershell
keytool -genkeypair -v -keystore my-upload-key.keystore -alias my-key-alias -keyalg RSA -keysize 2048 -validity 10000
```

`keytool`이 PATH에 없으면 전체 경로 예:

```powershell
& "C:\Program Files\Eclipse Adoptium\jdk-17.0.13.11-hotspot\bin\keytool.exe" -genkeypair -v -keystore my-upload-key.keystore -alias my-key-alias -keyalg RSA -keysize 2048 -validity 10000
```

### 3) SHA-256 추출

```powershell
keytool -list -v -keystore my-upload-key.keystore -alias my-key-alias
```

출력의 **`SHA256:`** 한 줄을 복사해 [public/.well-known/assetlinks.json](../../public/.well-known/assetlinks.json)의 `sha256_cert_fingerprints`에 넣습니다 (형식은 [Digital Asset Links](https://developers.google.com/digital-asset-links/v1/getting-started)에 맞출 것).

### 4) 보관

- `my-upload-key.keystore`, keystore 비밀번호, **alias**, key 비밀번호는 **분실 시 업데이트 불가** → 안전한 곳에 백업. Git에는 **올리지 마세요** (`.gitignore` 권장).

## 웹 ↔ 앱 동기화 (선택)

앱 최초 실행 시 웹에 한 번 `?gist_pwa_ack=1`을 붙여 열면 설치 유도 배너를 끌 수 있습니다.  
자세한 내용은 [docs/pwa-twa-sync.md](../../docs/pwa-twa-sync.md) 참고.

## 참고

- [Bubblewrap](https://github.com/GoogleChromeLabs/bubblewrap)
- [Digital Asset Links](https://developers.google.com/digital-asset-links/v1/getting-started)
