# 성능 검증 (전·후 비교)

모바일 로딩 개선 작업 후 아래로 **정량 비교**를 권장합니다.

## Chrome DevTools Lighthouse

1. Chrome에서 `https://www.thegist.co.kr` 열기
2. DevTools → **Lighthouse** 탭
3. Mode: **Navigation**, Device: **Mobile**, Throttling: **Slow 4G** (또는 **Fast 3G**)
4. Categories: **Performance** 체크 → **Analyze page load**

### 목표 지표(참고)

| 지표 | 목표 |
|------|------|
| LCP | ≤ 2.5s |
| TBT | ≤ 300ms |
| Speed Index | 낮을수록 좋음 |

## WebPageTest (선택)

- 테스트 지역: **Seoul** 또는 **Asia**
- URL: 프로덕션 홈
- Repeat View로 **캐시 히트** 후 차이 확인 (`/assets/` 장기 캐시·SW 반영)

## 네트워크 워터폴

DevTools → **Network** → Disable cache **끄고** 두 번째 로드에서 `/assets/*.js`가 **disk cache** 또는 **Service Worker**에서 오는지 확인.
