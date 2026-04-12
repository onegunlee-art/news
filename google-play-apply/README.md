# Google Play 등록 패키지 (the gist.)

이 폴더는 **Play Console 제출**에 필요한 **문구·스펙·체크리스트**를 한곳에 모은 것입니다.  
PNG 등 바이너리는 용량·관리 정책에 따라 레포에 넣지 않을 수 있으니, [ASSETS_CHECKLIST.md](./ASSETS_CHECKLIST.md)에 맞춰 `exports/` 등에 두고 콘솔에 업로드하면 됩니다.

---

## 폴더 구성

| 파일 | 내용 |
|------|------|
| [STORE_LISTING.md](./STORE_LISTING.md) | 짧은 설명, 긴 설명, 심사 대응 포인트 |
| [ASSETS_CHECKLIST.md](./ASSETS_CHECKLIST.md) | 아이콘 512, 기능 그래픽 1024×500, 스크린샷 스펙 |
| [BRAND_ICON.md](./BRAND_ICON.md) | 앱 아이콘은 **파비콘 g.** 기준 (레포 내 경로 안내) |

---

## 빠른 체크

- [ ] 짧은 설명 / 긴 설명 복사 → Play Console
- [ ] **512×512** 아이콘: `favicon-G.svg` 또는 웹과 동일한 `icon-512.png` 소스로 제작
- [ ] **기능 그래픽** 1024×500 1장
- [ ] **스크린샷** 최소 2장 (실기기 캡처 권장)
- [ ] Digital Asset Links: [public/.well-known/assetlinks.json](../public/.well-known/assetlinks.json) 배포 확인
- [ ] TWA 빌드: [android/twa/README.md](../android/twa/README.md), [`.github/workflows/build-twa-aab.yml`](../.github/workflows/build-twa-aab.yml)
