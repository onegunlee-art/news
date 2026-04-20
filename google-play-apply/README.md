# Google Play 등록 패키지 (the gist.)

이 폴더는 **Play Console 제출**에 필요한 **문구·스펙·체크리스트**와 **제출용 PNG**를 한곳에 모은 것입니다.

**그래픽 파일 위치:** [exports/](./exports/) — 아이콘 512, 기능 그래픽 1024×500, 스크린샷 2장(목업). 상세는 [exports/README.md](./exports/README.md).

---

## 폴더 구성

| 파일 | 내용 |
|------|------|
| [STORE_LISTING.md](./STORE_LISTING.md) | 짧은 설명, 긴 설명, 심사 대응 포인트 |
| [ASSETS_CHECKLIST.md](./ASSETS_CHECKLIST.md) | 아이콘 512, 기능 그래픽 1024×500, 스크린샷 스펙 |
| [BRAND_ICON.md](./BRAND_ICON.md) | 앱 아이콘은 **파비콘 g.** 기준 (레포 내 경로 안내) |
| [exports/](./exports/) | **제출용 PNG** (아이콘·배너·스크린샷 목업) |

---

## 빠른 체크

- [ ] 짧은 설명 / 긴 설명 복사 → Play Console
- [ ] **512×512** 아이콘: [exports/app-icon-512.png](./exports/app-icon-512.png) (RGB, 투명 없음)
- [ ] **기능 그래픽** **정확히 1024×500**: [exports/play-feature-graphic-1024x500.png](./exports/play-feature-graphic-1024x500.png) (거절 시 `.jpg` 동일 파일명 시도)
- [ ] **스크린샷** (ASCII 이름): [exports/play-phone-screenshot-01.jpg](./exports/play-phone-screenshot-01.jpg) ~ 최소 2장 — 가능하면 실기 캡처로 교체
- [ ] Digital Asset Links: [public/.well-known/assetlinks.json](../public/.well-known/assetlinks.json) 배포 확인
- [ ] TWA 빌드: [android/twa/README.md](../android/twa/README.md), [`.github/workflows/build-twa-aab.yml`](../.github/workflows/build-twa-aab.yml)
