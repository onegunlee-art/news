# 프로모션 계정 (시장 테스트용)

전체 기사를 열람할 수 있는 계정 하나를 생성합니다. 만료는 **실행 시점 기준 1달 후**입니다.

## 1회 실행

브라우저 또는 curl로 다음 주소를 **한 번만** 호출하세요.

- 배포 서버: `https://www.thegist.co.kr/api/auth/seed-promo-user.php`
- 로컬: `http://localhost/api/auth/seed-promo-user.php` (실제 API 경로에 맞게 변경)

응답 예시:

```json
{
  "success": true,
  "message": "프로모션 계정이 생성되었습니다. ...",
  "email": "promo@thegist.co.kr",
  "password": "ThegistPromo2026!",
  "expires_at": "2026-04-05 12:00:00",
  "login_url": "/login"
}
```

## 로그인 방법

1. 사이트에서 **로그인** 페이지로 이동
2. **이메일**: `promo@thegist.co.kr`
3. **비밀번호**: `ThegistPromo2026!` (응답에 표시된 값 사용)
4. 로그인 후 구독자와 동일하게 모든 기사 열람 가능

## 만료

- `expires_at` 날짜가 지나면 해당 계정은 구독 해제 상태로 전환됩니다.
- 만료 후 다시 1달 사용하려면 위 URL을 **다시 한 번** 호출하면 구독이 1달 연장됩니다.

## 보안

- 사용 후 `public/api/auth/seed-promo-user.php` 파일 삭제를 권장합니다.
