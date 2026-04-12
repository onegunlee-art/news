# 앱 아이콘: 파비콘 **g.** 기준

Google Play **512×512** 아이콘은 **웹 파비콘과 동일한 g. 마크**를 사용합니다.

---

## 레포에서 쓸 소스

| 파일 | 설명 |
|------|------|
| [public/favicon-G.svg](../public/favicon-G.svg) | 소문자 **g** 벡터 마크 (제작 시 우선 참고) |
| [public/icon-512.png](../public/icon-512.png) | PWA/스토어용 **512** PNG가 이미 있다면 Play에 동일 파일 사용 가능 (시각이 g.와 일치하는지 확인) |
| [src/frontend/index.html](../src/frontend/index.html) | 파비콘 링크: `favicon-16/32`, `icon-192/512` 등 |

**주의:** [public/favicon.svg](../public/favicon.svg) 샘플은 내용이 다를 수 있으니, **프로덕션과 동일한 파일**으로 통일할 것.

---

## 제출 전

1. **프로덕션 사이트**에 올라간 아이콘과 **픽셀 단위로 동일**한지 확인
2. 배경색·여백은 Play **적응형 아이콘** 가이드에 맞게 조정
3. 필요 시 디자인 툴에서 `favicon-G.svg` → **512×512 PNG** export
