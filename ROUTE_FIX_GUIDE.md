# 라우트 문제 해결 가이드

## 🔍 문제 진단

"Endpoint not found" 오류는 라우트가 매칭되지 않아서 발생합니다.

## ✅ 즉시 해결 방법

### 방법 1: 직접 카카오 로그인 (임시)

라우터를 거치지 않고 직접 카카오 로그인으로 이동:

```
http://ailand.dothome.co.kr/api/direct_kakao.php
```

이 파일은 라우터를 거치지 않고 직접 카카오 로그인 URL로 리다이렉트합니다.

### 방법 2: 라우트 문제 해결

#### 1단계: 서버에 파일 업로드 확인

다음 파일들이 서버에 업로드되었는지 확인:

- ✅ `src/backend/Core/Router.php` (수정된 버전)
- ✅ `config/routes.php`
- ✅ `src/backend/Controllers/AuthController.php`

#### 2단계: 라우트 등록 확인

브라우저에서 확인:
```
http://ailand.dothome.co.kr/api/simple_test.php
```

이 페이지에서:
- 라우트가 등록되어 있는지 확인
- `/auth/kakao` 라우트가 있는지 확인

#### 3단계: 경로 매칭 확인

`Router.php`의 `matchPath` 메서드가 정확한 경로를 매칭하는지 확인:

```php
// 정확한 경로 매칭 먼저 시도
if ($pattern === $path) {
    return [];
}
```

## 🔧 근본 원인 분석

### 가능한 원인 1: 라우트가 등록되지 않음

**증상**: `simple_test.php`에서 라우트가 0개로 표시됨

**해결**:
1. `config/routes.php` 파일이 서버에 있는지 확인
2. Autoloader가 제대로 작동하는지 확인
3. 컨트롤러 클래스가 존재하는지 확인

### 가능한 원인 2: 경로 매칭 실패

**증상**: 라우트는 있지만 매칭되지 않음

**해결**:
1. `Router.php`의 `matchPath` 메서드 확인
2. 경로 앞뒤 슬래시 확인
3. 대소문자 구분 확인

### 가능한 원인 3: API prefix 제거 문제

**증상**: `/api/auth/kakao` 요청이 `/auth/kakao`로 변환되지 않음

**해결**:
1. `Router.php`의 `dispatch` 메서드 확인
2. `str_starts_with` 함수가 PHP 8.0+에서 작동하는지 확인

## 📋 체크리스트

- [ ] `Router.php` 파일이 서버에 최신 버전으로 업로드됨
- [ ] `config/routes.php` 파일이 서버에 있음
- [ ] `simple_test.php`에서 라우트가 등록되어 있음
- [ ] `/api/health` 엔드포인트가 작동함
- [ ] `/api/direct_kakao.php`가 작동함 (임시 해결책)

## 🚀 빠른 테스트

1. **직접 카카오 로그인 테스트**:
   ```
   http://ailand.dothome.co.kr/api/direct_kakao.php
   ```

2. **라우트 확인**:
   ```
   http://ailand.dothome.co.kr/api/simple_test.php
   ```

3. **헬스 체크**:
   ```
   http://ailand.dothome.co.kr/api/health
   ```

## 💡 다음 단계

1. `direct_kakao.php`로 카카오 로그인이 작동하는지 확인
2. `simple_test.php`에서 라우트 등록 상태 확인
3. 결과를 바탕으로 근본 원인 해결
