# 카카오 REST API 키 설정 문제 해결

## ❌ 문제: REST API Key 미설정 오류

테스트 페이지에서 "REST API Key 설정"이 미설정으로 표시되는 경우, 다음을 확인하세요.

## ✅ 해결 방법

### 1단계: 디버그 페이지로 확인

브라우저에서 다음 URL을 열어보세요:
```
http://ailand.dothome.co.kr/api/auth/debug.php
```

이 페이지에서 확인할 수 있는 정보:
- 설정 파일 경로
- 파일 존재 여부
- REST API 키 값
- 설정 파일 전체 내용

### 2단계: 설정 파일 확인

로컬에서 `config/kakao.php` 파일을 확인하세요:

**현재 설정된 값:**
```php
$KAKAO_REST_API_KEY = '2b4a37bb18a276469b69bf3d8627e425';

return [
    'rest_api_key' => $KAKAO_REST_API_KEY,
    // ...
];
```

### 3단계: 서버에 파일 업로드 확인

**dothome 호스팅에 배포할 때 다음 파일들이 업로드되었는지 확인:**

1. `config/kakao.php` - **필수!**
2. `config/app.php`
3. `config/database.php`
4. `src/backend/` 전체 폴더
5. `public/` 전체 폴더

### 4단계: FTP로 직접 확인

1. FTP 클라이언트로 서버 접속
2. 다음 경로 확인:
   ```
   /config/kakao.php
   ```
3. 파일이 없다면 업로드
4. 파일이 있다면 내용 확인

### 5단계: 파일 내용 확인

서버의 `config/kakao.php` 파일이 다음 내용을 포함하는지 확인:

```php
<?php
$KAKAO_REST_API_KEY = '2b4a37bb18a276469b69bf3d8627e425';

return [
    'rest_api_key' => $KAKAO_REST_API_KEY,
    'oauth' => [
        'redirect_uri' => 'http://ailand.dothome.co.kr/api/auth/kakao/callback',
        // ...
    ],
    // ...
];
```

## 🔍 문제 진단

### 문제 1: 파일이 서버에 없음

**증상**: 디버그 페이지에서 "파일 존재 여부: NO"

**해결**:
1. 로컬 `config/kakao.php` 파일 확인
2. FTP로 서버에 업로드
3. 파일 권한 확인 (644 또는 755)

### 문제 2: 파일 경로가 잘못됨

**증상**: 디버그 페이지에서 경로가 잘못 표시됨

**해결**:
- 서버 구조에 맞게 경로 수정 필요
- `dirname(__DIR__, 3)`이 올바른 경로를 가리키는지 확인

### 문제 3: 파일 내용이 잘못됨

**증상**: 파일은 있지만 REST API 키가 비어있음

**해결**:
1. 서버의 `config/kakao.php` 파일 열기
2. REST API 키가 올바르게 설정되어 있는지 확인
3. 필요시 수정

## 📋 체크리스트

- [ ] 로컬 `config/kakao.php` 파일에 REST API 키가 설정되어 있음
- [ ] 서버에 `config/kakao.php` 파일이 업로드되어 있음
- [ ] 디버그 페이지(`/api/auth/debug.php`)에서 파일이 발견됨
- [ ] 디버그 페이지에서 REST API 키가 표시됨
- [ ] 테스트 페이지(`/api/auth/test.php`)에서 "설정됨"으로 표시됨

## 🚀 빠른 해결 방법

### 방법 1: FTP로 직접 업로드

1. 로컬 `config/kakao.php` 파일 확인
2. FTP 클라이언트로 서버 접속
3. `/config/kakao.php` 경로에 업로드
4. 파일 권한을 644로 설정
5. 디버그 페이지에서 확인

### 방법 2: GitHub Actions 배포 확인

만약 GitHub Actions를 사용한다면:
1. `.github/workflows/deploy.yml` 확인
2. `config/` 폴더가 배포에 포함되는지 확인
3. 필요시 배포 스크립트 수정

## ⚠️ 주의사항

1. **보안**: `config/kakao.php` 파일은 `.gitignore`에 포함되어 있지 않다면 Git에 커밋되지 않을 수 있습니다.
2. **파일 권한**: 서버에서 파일 읽기 권한이 있어야 합니다 (644 권한 권장).
3. **경로**: 서버 구조에 따라 경로가 다를 수 있습니다.

## 🧪 확인 후 테스트

설정 파일이 올바르게 업로드된 후:

1. 디버그 페이지 확인: `http://ailand.dothome.co.kr/api/auth/debug.php`
2. 테스트 페이지 확인: `http://ailand.dothome.co.kr/api/auth/test.php`
3. 카카오 로그인 테스트: "카카오로 시작하기" 버튼 클릭
