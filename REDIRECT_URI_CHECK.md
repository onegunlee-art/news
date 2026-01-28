# 리다이렉트 URI 확인 및 수정 가이드

## 🔍 현재 설정된 리다이렉트 URI

**코드에 설정된 URI:**
```
http://ailand.dothome.co.kr/api/auth/kakao/callback
```

## ⚠️ 리다이렉트 URI 오류 해결 방법

### 1단계: 카카오 개발자 콘솔에서 확인

1. [Kakao Developers](https://developers.kakao.com) 접속
2. **내 애플리케이션** 선택
3. **제품 설정** → **카카오 로그인** → **Redirect URI** 섹션 확인

**등록된 URI를 정확히 복사하세요!**

### 2단계: URI 비교 체크리스트

다음 항목을 정확히 확인하세요:

- [ ] **프로토콜 일치**: `http://` vs `https://`
- [ ] **도메인 일치**: `ailand.dothome.co.kr` (공백 없음)
- [ ] **경로 일치**: `/api/auth/kakao/callback` (대소문자, 슬래시 정확)
- [ ] **끝 슬래시**: 끝에 `/`가 있으면 안 됨

### 3단계: 가능한 URI 변형들

카카오 개발자 콘솔에 다음 중 하나를 정확히 등록해야 합니다:

**옵션 1 (HTTP):**
```
http://ailand.dothome.co.kr/api/auth/kakao/callback
```

**옵션 2 (HTTPS - SSL 인증서가 있다면):**
```
https://ailand.dothome.co.kr/api/auth/kakao/callback
```

**옵션 3 (포트 포함 - 로컬 테스트용):**
```
http://localhost:5173/api/auth/kakao/callback
```

### 4단계: 설정 파일 수정

카카오 개발자 콘솔에 등록한 URI와 정확히 일치하도록 `config/kakao.php` 파일을 수정하세요.

## 🔧 수정 방법

### 방법 1: HTTP 사용 (현재 설정)

`config/kakao.php` 파일의 60번째 줄:
```php
'redirect_uri' => getenv('KAKAO_REDIRECT_URI') ?: 'http://ailand.dothome.co.kr/api/auth/kakao/callback',
```

카카오 개발자 콘솔에도 정확히 동일하게 등록:
```
http://ailand.dothome.co.kr/api/auth/kakao/callback
```

### 방법 2: HTTPS 사용 (SSL 인증서가 있는 경우)

`config/kakao.php` 파일 수정:
```php
'redirect_uri' => getenv('KAKAO_REDIRECT_URI') ?: 'https://ailand.dothome.co.kr/api/auth/kakao/callback',
```

카카오 개발자 콘솔에도 정확히 동일하게 등록:
```
https://ailand.dothome.co.kr/api/auth/kakao/callback
```

## 🧪 URI 확인 방법

### 1. 테스트 페이지에서 확인

```
http://ailand.dothome.co.kr/api/auth/test.php
```

이 페이지에서 현재 설정된 Redirect URI를 확인할 수 있습니다.

### 2. 직접 URL 테스트

브라우저에서 다음 URL을 열어보세요 (REST API 키 포함):
```
https://kauth.kakao.com/oauth/authorize?client_id=2b4a37bb18a276469b69bf3d8627e425&redirect_uri=http://ailand.dothome.co.kr/api/auth/kakao/callback&response_type=code&scope=profile_nickname,profile_image,account_email
```

**오류 메시지 확인:**
- `redirect_uri_mismatch`: URI가 일치하지 않음
- `invalid_client`: REST API 키 문제
- 정상: 카카오 로그인 페이지 표시

## 📋 체크리스트

카카오 개발자 콘솔에서 확인:

- [ ] **제품 설정** → **카카오 로그인** → **활성화 설정**: ON
- [ ] **제품 설정** → **카카오 로그인** → **Redirect URI**: 정확히 등록됨
- [ ] **앱 설정** → **플랫폼** → **Web**: 등록됨
- [ ] 등록된 URI와 코드의 URI가 **100% 일치**함

## ⚠️ 주의사항

1. **대소문자 구분**: URI는 대소문자를 구분합니다
2. **슬래시**: 끝에 `/`가 있으면 안 됩니다
3. **공백**: URI에 공백이 있으면 안 됩니다
4. **프로토콜**: `http://`와 `https://`는 다릅니다
5. **도메인**: `ailand.dothome.co.kr` (www 없음)

## 🔄 수정 후 확인

1. `config/kakao.php` 파일 수정
2. 카카오 개발자 콘솔에 동일한 URI 등록
3. 테스트 페이지에서 확인: `http://ailand.dothome.co.kr/api/auth/test.php`
4. "카카오 로그인 테스트" 버튼 클릭하여 테스트
