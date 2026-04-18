# 앱 아이콘: 파비콘 **g.** 기준

Google Play **512×512** 아이콘은 **웹 파비콘과 동일한 g. 마크**를 사용합니다.
프로덕션(`/icon-512.png`)과 스토어 제출 아이콘(`exports/app-icon-512.png`)은 **픽셀 단위로 동일**합니다.

---

## 단일 진실의 원천 (Single Source of Truth)

| 파일 | 역할 |
|------|------|
| [public/favicon-G.svg](../public/favicon-G.svg) | **벡터 원본** (g. 마크, 흰 배경/검정) — 모든 파생물의 출처 |
| [public/favicon.svg](../public/favicon.svg) | 프로덕션 SVG 파비콘 (= `favicon-G.svg`와 동일) |
| [public/icon-512.png](../public/icon-512.png) | PWA/TWA 512 PNG (manifest·iconUrl 참조) |
| [public/icon-192.png](../public/icon-192.png) | PWA 192 PNG |
| [public/apple-icon.png](../public/apple-icon.png) | iOS 홈 화면 아이콘 |
| [exports/app-icon-512.png](./exports/app-icon-512.png) | **Play Console 제출용** — `public/icon-512.png`와 동일 |

---

## 제출 전 체크리스트

1. `exports/app-icon-512.png` = `public/icon-512.png` (바이트 일치) — ✅
2. 프로덕션 접속(`www.thegist.co.kr`)에서 보이는 파비콘과 앱 아이콘이 **같은 g. 마크**인지 육안 확인
3. Play Console 업로드 시 **잘림·왜곡 없음** (g.의 세리프 꼬리가 잘리지 않는 여백 확보됨)
4. 디자인 수정 시 반드시 `favicon-G.svg` → `icon-*.png` → `exports/app-icon-512.png` 순으로 파생물 재생성
