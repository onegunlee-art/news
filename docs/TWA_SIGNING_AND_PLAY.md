# TWA 서명 · Play 스토어 · 내부 테스트

## 1. 키스토어·서명

| 항목 | 설명 |
|------|------|
| 업로드 키 | Play에 올릴 AAB 서명에 사용 (`.jks` / `.keystore`) |
| Play App Signing | Google이 재서명 — **assetlinks.json의 SHA-256**은 콘솔에 안내되는 값 사용 |
| 백업 | 키스토어 파일·alias·비밀번호는 **분실 시 앱 업데이트 불가** — 안전한 백업 필수 |

Bubblewrap으로 생성한 키를 쓰는 경우, 키 도구로 SHA-256 추출:

```bash
keytool -list -v -keystore your.keystore -alias your_alias
```

출력의 `SHA256:` 값을 **콜론 제거·대문자** 형태로 assetlinks에 넣는 경우가 많습니다. Google 문서의 형식에 맞출 것.

## 2. assetlinks.json 갱신

1. 서명이 확정된 뒤 SHA-256 한 개 이상을 [public/.well-known/assetlinks.json](../public/.well-known/assetlinks.json)에 반영
2. 배포 후 `https://www.thegist.co.kr/.well-known/assetlinks.json` 접속해 JSON 확인
3. [Statement List Generator and Tester](https://developers.google.com/digital-asset-links/tools/generator)로 검증

## 3. Play Console 스토어 등록 자산

- 앱 이름·짧은 설명·전체 설명
- 512×512 아이콘
- 스크린샷 (휴대폰 최소 2장 이상 권장)
- 카테고리·연락처 이메일
- **개인정보 처리방침 URL** (필수에 가깝게 요구됨)

## 4. 릴리즈 절차 (권장)

1. **내부 테스트** 트랙에 AAB 업로드
2. 테스터 계정으로 실제 설치
3. 이슈 없으면 **프로덕션** 단계적 출시

## 5. 내부 테스트 시 확인할 기능 (체크리스트)

- [ ] 앱 아이콘 실행 시 메인 로드
- [ ] 로그인 / 로그아웃 (카카오·구글 등 리다이렉트)
- [ ] 구독·결제 진입 (외부 브라우저/인앱 탭 동작)
- [ ] 기사 상세·오디오 재생
- [ ] 기사 챗봇 스트리밍
- [ ] 외부 링크·뒤로가기
- [ ] (선택) 앱 첫 실행 URL에 `?gist_pwa_ack=1` 시 웹 설치 배너 비표시 — [pwa-twa-sync.md](./pwa-twa-sync.md)

TWA에서 **쿠키/스토리지**는 Chrome Custom Tabs 계열과 일반 Chrome 탭이 완전히 같지 않을 수 있어, 로그인 유지 테스트가 중요합니다.
